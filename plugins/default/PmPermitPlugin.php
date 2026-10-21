<?php
use App\BasePlugin;

class PmPermitPlugin extends BasePlugin {
    public string $name = 'PM Təhlükəsizliyi (PmPermit)';
    public string $description = 'Şəxsi mesajlarda icazəsiz yazanları idarə edir və avtomatik bloklayır.';
    public array $commands = [
        'pm' => 'PM qorumasını aktiv/deaktiv edir (.pm on / .pm off)',
        'pmlimit' => 'Maksimum mesaj limitini təyin edir (.pmlimit <sayı>)',
        'approve' => 'İstifadəçiyə PM icazəsi verir',
        'a' => 'İstifadəçiyə PM icazəsi verir',
        'disapprove' => 'İstifadəçinin PM icazəsini ləğv edir',
        'da' => 'İstifadəçinin PM icazəsini ləğv edir',
        'block' => 'İstifadəçini bloklayır',
        'unblock' => 'İstifadəçini blokdan çıxarır',
        'pmlog' => 'PM Permit xəta və hərəkət loglarını göstərir'
    ];
    public bool $handlesMessages = true;

    private array $userMsgCounts = [];
    private array $botCache = [];

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            if ($userId === 0) {
                $message->editText("⚠️ İstifadəçi tapılmadı.");
                return;
            }

            if ($command === 'pm') {
                $status = isset($args[0]) ? strtolower(trim($args[0])) : '';
                if ($status === 'on') {
                    $this->setSetting($userId, 'pm_permit_active', '1');
                    $this->logPm("PM Qoruması aktivləşdirildi (ownerId: {$this->ownerId})");
                    $message->editText("🛡 <b>PM Qoruması aktivləşdirildi!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                } elseif ($status === 'off') {
                    $this->setSetting($userId, 'pm_permit_active', '0');
                    $this->logPm("PM Qoruması söndürüldü (ownerId: {$this->ownerId})");
                    $message->editText("🚫 <b>PM Qoruması söndürüldü!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                } else {
                    $current = $this->getSetting($userId, 'pm_permit_active', '1') === '1' ? 'Aktiv' : 'Deaktiv';
                    $limit = $this->getSetting($userId, 'pm_limit', '3');
                    $message->editText("ℹ️ <b>PM Qoruması:</b> {$current}\n📊 <b>Limit:</b> {$limit} mesaj\n<i>Dəyişmək üçün: <code>.pm on</code> və ya <code>.pm off</code></i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                }
                return;
            }

            if ($command === 'pmlimit') {
                $limit = isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : 3;
                if ($limit <= 0) $limit = 3;
                $this->setSetting($userId, 'pm_limit', (string) $limit);
                $this->logPm("PM Limiti təyin edildi: {$limit}");
                $message->editText("📊 <b>PM Limit təyin edildi:</b> {$limit} mesaj", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'pmlog') {
                $logFile = __DIR__ . '/../../logs/pmpermit.log';
                if (!file_exists($logFile) || filesize($logFile) === 0) {
                    $message->editText("ℹ️ PM Permit üzrə heç bir log tapılmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $lastLines = array_slice($lines, -15);
                $logText = htmlspecialchars(implode("\n", $lastLines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $message->editText("📋 <b>PM Permit Son Loglar:</b>\n\n<code>{$logText}</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $targetId = 0;
            $reply = $message->getReply();
            if ($reply && isset($reply->senderId)) {
                $targetId = (int) $reply->senderId;
            } elseif (is_object($message) && isset($message->chatId) && $message->chatId > 0) {
                $targetId = (int) $message->chatId;
            } elseif (!empty($args[0])) {
                $raw = trim($args[0]);
                if (is_numeric($raw)) {
                    $targetId = (int) $raw;
                } else {
                    try {
                        $info = $this->api->getInfo($raw);
                        $targetId = (int) ($info['bot_api_id'] ?? $info['User']['id'] ?? 0);
                    } catch (\Throwable $t) {}
                }
            }

            if ($targetId === 0) {
                $message->editText("⚠️ Lütfən istifadəçinin mesajına cavab verin və ya şəxsi çata yazın.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $targetMention = $this->formatUserMention($targetId);

            if ($command === 'approve' || $command === 'a') {
                $stmt = $this->db->prepare("INSERT INTO pm_approved (user_id, chat_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE chat_id = chat_id");
                $stmt->execute([$userId, $targetId]);
                unset($this->userMsgCounts[$targetId]);

                $this->logPm("TƏSDİQLƏNDİ (.approve/.a): {$targetId}");
                $message->editText("✅ {$targetMention} istifadəçisinə PM icazəsi verildi!", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'disapprove' || $command === 'da') {
                $stmt = $this->db->prepare("DELETE FROM pm_approved WHERE user_id = ? AND chat_id = ?");
                $stmt->execute([$userId, $targetId]);
                unset($this->userMsgCounts[$targetId]);

                $this->logPm("LƏĞV EDİLDİ (.disapprove/.da): {$targetId}");
                $message->editText("🚫 {$targetMention} istifadəçisinin PM icazəsi ləğv edildi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'block') {
                if ($this->isProtectedUser($targetId)) {
                    $message->editText("⚠️ Üzgünəm, sahibimi / adminimi bloklaya bilmərəm!", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                try {
                    $this->api->contacts->block(id: $targetId);
                } catch (\Throwable $t) {}

                $this->logPm("BLOKLANDI (Manual .block): {$targetId}");
                $message->editText("⛔ {$targetMention} bloklandı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'unblock') {
                try {
                    $this->api->contacts->unblock(id: $targetId);
                } catch (\Throwable $t) {}

                $this->logPm("BLOKDAN ÇIXARILDI (Manual .unblock): {$targetId}");
                $message->editText("🔓 {$targetMention} blokdan çıxarıldı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    public function onMessage($message): void {
        try {
            $chatId = (int) ($message->chatId ?? 0);
            $senderId = (int) ($message->senderId ?? $message->fromId ?? 0);
            if ($senderId === 0 && $chatId > 0) {
                $senderId = $chatId;
            }
            if ($chatId === 0 && $senderId > 0) {
                $chatId = $senderId;
            }

            if ($chatId <= 0) {
                return;
            }

            // Sahibin öz mesajıdırsa -> Atla
            if ($senderId === $this->ownerId) {
                return;
            }

            // Çıxan mesajdırsa (özümüz çata yazırıqsa) -> Atla
            if (!empty($message->out)) {
                return;
            }

            // Telegram bot və rəsmi sistem hesablarını PM Permit yoxlamasından kənarlaşdırırıq
            if ($this->isBotSender($message, $senderId)) {
                return;
            }

            $userId = $this->getUserId();
            if ($userId === 0) {
                return;
            }

            // PM Permit aktivdir? (Susmaya görə aktiv: '1')
            $active = $this->getSetting($userId, 'pm_permit_active', '1');
            if ($active !== '1') {
                return;
            }

            // Əgər yazan istifadəçi DB-də Owner rütbəli admindirsa:
            if ($this->isOwnerSender($senderId)) {
                if (!$this->isApproved($userId, $senderId)) {
                    $stmt = $this->db->prepare("INSERT INTO pm_approved (user_id, chat_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE chat_id = chat_id");
                    $stmt->execute([$userId, $senderId]);

                    $this->sendPmMessage(
                        $chatId,
                        "Sizdə PM-kilidi olsa da , yazan istifadəçi, üstün rütbəli olduğu üçün, bu istifadəçiyə kilid deaktiv edildi. ⚠️"
                    );
                    $this->logPm("👑 Üstün rütbəli Owner ({$senderId}) şəxsi çata yazdı -> Avto-təsdiqləndi.");
                }
                return;
            }

            // Təsdiqlənibsə atla
            if ($this->isApproved($userId, $senderId)) {
                return;
            }

            $limit = (int) $this->getSetting($userId, 'pm_limit', '3');
            if ($limit <= 0) $limit = 3;

            if (!isset($this->userMsgCounts[$senderId])) {
                $this->userMsgCounts[$senderId] = 0;
            }
            $this->userMsgCounts[$senderId]++;

            $currentCount = $this->userMsgCounts[$senderId];

            if ($currentCount > $limit) {
                $sarcasticMessages = [
                    "⛔ <i>Sadəcə bir xəyal qırıqlığı idin. PM-nı məşğul etdiyi üçün bloklandın.</i>",
                    "⛔ <i>Səbrim tükəndi! Sahibimin vaxtını almağa haqqın yox idi. Əlvida!</i>",
                    "⛔ <i>Xəbərdarlıqlara məhəl qoymadın. İndi isə bloka get və orada nə etdiyini düşün!</i>",
                    "⛔ <i>Spam etməkdən bezmədin? Sistem avtomatik olaraq səni qara siyahıya atdı.</i>",
                    "⛔ <i>Təbrik edirəm, bütün şanslarını itirdin! Sahibimin PM-i artıq sənə qapalıdır.</i>"
                ];
                $blockMsg = $sarcasticMessages[array_rand($sarcasticMessages)];

                $this->sendPmMessage($chatId, $blockMsg);
                $this->logPm("🚫 BLOKLANDI -> Sender {$senderId}, Mesaj sayı {$currentCount} > Limit {$limit}");

                try {
                    $this->api->contacts->block(id: $senderId);
                } catch (\Throwable $t) {}

                return;
            }

            $remCount = $limit - $currentCount;
            
            $customPmMsg = $this->getCustomMessage($userId, 'pm');
            
            if (!empty($customPmMsg)) {
                $replyMsg = str_replace(
                    ['{remaining}', '{limit}'],
                    [(string)$remCount, (string)$limit],
                    $customPmMsg
                );
            } else {
                if ($currentCount === 1) {
                    $replyMsg = "👋 <b>Salam!</b> Zəhmət olmasa mesajınızı bir neçə sözlə əvəz edin. <b>{$limit}</b> - qədər yazma haqqınız var.";
                } else {
                    $replyMsg = "⚠️ Artıq <b>{$remCount}</b> - yazmaq haqqın qaldı, unutma! Haqqın bitərsə avtomatik əngəllənəcəksən.";
                }
            }

            $this->sendPmMessage($chatId, $replyMsg);
            $this->logPm("📩 XƏBƏRDARLIQ GÖNDƏRİLDİ -> Sender: {$senderId}, Mesaj: {$currentCount}/{$limit}");

        } catch (\Throwable $e) {
            $this->logPm("XƏTA onMessage: " . $e->getMessage());
        }
    }

    private function isBotSender($message, int $senderId): bool {
        // 1. Telegram Rəsmi Sistem və Servis ID-ləri
        $telegramOfficialIds = [
            777000,      // Telegram Service Notifications (Giriş kodları, rəsmi təhlükəsizlik bildirişləri)
            42777,       // Telegram Support
            1271266957,  // Telegram Replies (Kanal rəyləri bildiriş botu)
            1087968824,  // Group Anonymous Bot (Qrup Anonim İnzibatçı Botu)
            136817688,   // Telegram Notifications
            333000       // Telegram DC Service
        ];

        if ($senderId <= 0 || in_array($senderId, $telegramOfficialIds, true)) {
            return true;
        }

        // Cache-də varsa təkrar API çağırışı etmədən dərhal qaytarırıq
        if (isset($this->botCache[$senderId])) {
            return $this->botCache[$senderId];
        }

        // 2. Telegram Servis Mesajıdırsa (Məs: Qoşulma, pin, zəng və s.)
        if (is_object($message)) {
            if (isset($message->_) && $message->_ === 'messageService') {
                return true;
            }
            if (method_exists($message, 'isService') && $message->isService()) {
                return true;
            }
        }

        // 3. MadelineProto API ilə dəqiq Peer analizi
        try {
            $info = $this->api->getInfo($senderId);
            $type = $info['type'] ?? '';
            $user = $info['User'] ?? [];

            // A. Peer növü: Bot, Kanal, Qrup
            if ($type === 'bot' || $type === 'channel' || $type === 'chat' || $type === 'supergroup') {
                $this->botCache[$senderId] = true;
                return true;
            }

            // B. User obyektində bot təyinatı
            if (!empty($info['bot']) || !empty($user['bot'])) {
                $this->botCache[$senderId] = true;
                return true;
            }

            // C. Telegram Rəsmi Təsdiqlənmiş (Verified) və ya Support hesabı
            if (!empty($user['verified']) || !empty($user['support'])) {
                $this->botCache[$senderId] = true;
                return true;
            }

            // D. İstifadəçi adı "bot" ilə bitən bütün hesablar (məsələn: dllmasterbot, MissRose_bot)
            $username = strtolower((string)($user['username'] ?? $info['username'] ?? ''));
            if ($username !== '' && (str_ends_with($username, 'bot') || str_ends_with($username, '_bot'))) {
                $this->botCache[$senderId] = true;
                return true;
            }

            // E. Rəsmi Telegram servis adları
            $firstName = strtolower((string)($user['first_name'] ?? ''));
            if ($firstName === 'telegram') {
                $this->botCache[$senderId] = true;
                return true;
            }

        } catch (\Throwable $t) {}

        // 4. Alternativ daxili obyekt yoxlanışları
        if (is_object($message)) {
            if (isset($message->sender) && is_object($message->sender)) {
                if (!empty($message->sender->bot) || (isset($message->sender['bot']) && $message->sender['bot'])) {
                    $this->botCache[$senderId] = true;
                    return true;
                }
                if (isset($message->sender->username)) {
                    $un = strtolower((string)$message->sender->username);
                    if ($un !== '' && (str_ends_with($un, 'bot') || str_ends_with($un, '_bot'))) {
                        $this->botCache[$senderId] = true;
                        return true;
                    }
                }
            }

            if (isset($message->chat) && is_object($message->chat)) {
                if (!empty($message->chat->bot) || (isset($message->chat['bot']) && $message->chat['bot'])) {
                    $this->botCache[$senderId] = true;
                    return true;
                }
                if (isset($message->chat->username)) {
                    $un = strtolower((string)$message->chat->username);
                    if ($un !== '' && (str_ends_with($un, 'bot') || str_ends_with($un, '_bot'))) {
                        $this->botCache[$senderId] = true;
                        return true;
                    }
                }
            }
        }

        $this->botCache[$senderId] = false;
        return false;
    }

    private function isOwnerSender(int $telegramId): bool {
        if ($telegramId === self::PRIMARY_OWNER_ID) {
            return true;
        }
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM ub_admins WHERE telegram_id = ? AND level = 'owner'");
                $stmt->execute([$telegramId]);
                return (bool) $stmt->fetch();
            } catch (\Throwable $e) {}
        }
        return false;
    }

    private function sendPmMessage(int $chatId, string $msg): void {
        try {
            $this->api->sendMessage(
                $chatId,
                $msg,
                parseMode: \danog\MadelineProto\ParseMode::HTML
            );
        } catch (\Throwable $t) {
            try {
                $this->api->messages->sendMessage(
                    peer: $chatId,
                    message: $msg,
                    parseMode: \danog\MadelineProto\ParseMode::HTML
                );
            } catch (\Throwable $t2) {}
        }
    }

    private function logPm(string $msg): void {
        try {
            $logFile = __DIR__ . '/../../logs/pmpermit.log';
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $time = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$time}] {$msg}\n", FILE_APPEND);
        } catch (\Throwable $t) {}
    }

    private function isApproved(int $userId, int $chatId): bool {
        $stmt = $this->db->prepare("SELECT id FROM pm_approved WHERE user_id = ? AND chat_id = ?");
        $stmt->execute([$userId, $chatId]);
        return (bool) $stmt->fetch();
    }

    private function getUserId(): int {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        if ($user && isset($user['id'])) {
            return (int) $user['id'];
        }
        $stmt = $this->db->prepare('INSERT INTO users (telegram_id, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->execute([$this->ownerId]);
        
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return (int) ($user['id'] ?? 0);
    }

    private function getSetting(int $userId, string $key, string $default = ''): string {
        $stmt = $this->db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    private function setSetting(int $userId, string $key, string $value): void {
        $stmt = $this->db->prepare("DELETE FROM user_settings WHERE user_id = ? AND setting_key = ?");
        $stmt->execute([$userId, $key]);
        $stmt = $this->db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $key, $value]);
    }

    private function getCustomMessage(int $userId, string $key): string {
        $stmt = $this->db->prepare("SELECT msg_content FROM custom_messages WHERE user_id = ? AND msg_key = ?");
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['msg_content'] : '';
    }
}
