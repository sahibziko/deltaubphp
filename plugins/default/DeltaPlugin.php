<?php
use App\BasePlugin;
use danog\MadelineProto\ParseMode;

class DeltaPlugin extends BasePlugin {
    public string $name = 'Delta (Qlobal İnteraktiv Kömək)';
    public string $description = 'Qlobal köməkçi bot vasitəsilə səhifəli və düyməli interaktiv kömək menyusunu açır (.delta).';
    public array $commands = [
        'delta' => 'İnteraktiv düyməli kömək menyusunu açır (.delta)'
    ];

    private ?string $botUsername = null;
    private int $updateOffset = 0;
    private static bool $workerStarted = false;
    private ?\Amp\Http\Client\HttpClient $httpClient = null;

    public function onLoad(): void {
        $this->ensureSettingExists();
        if (!self::$workerStarted) {
            self::$workerStarted = true;
            \Amp\async(function () {
                $this->runBotWorker();
            });
        }
    }

    public function onCommand($message, string $command, array $args): void {
        try {
            // Yalnız Owner səviyyəsində olan şəxslər tokeni idarə edə bilər
            if ($command === 'setbot' || $command === 'getbot') {
                $senderId = (int) ($message->senderId ?? 0);
                if (!$this->isOwnerLevel($senderId)) {
                    $message->editText("⛔ <b>İcazə verilmədi!</b> Bu əmr yalnız sistemin <b>Owner</b>-i tərəfindən istifadə edilə bilər.", parseMode: ParseMode::HTML);
                    return;
                }
            }

            // 1. .setbot - Qlobal Tokeni bazaya yazmaq (Yalnız Owner)
            if ($command === 'setbot') {
                $token = trim($args[0] ?? '');
                if ($token === '') {
                    $message->editText("⚠️ <b>Token qeyd edin!</b>\n<i>Nümunə: <code>.setbot 1234567890:ABC-DEF1234ghIkl-zyx57W2v1u123ew11</code></i>", parseMode: ParseMode::HTML);
                    return;
                }

                $me = $this->callBotApi($token, 'getMe');
                if (empty($me['ok']) || empty($me['result']['username'])) {
                    $message->editText("❌ <b>Token etibarsızdır!</b>\nTelegram BotFather-dan aldığınız tokeni düzgün yazdığınızdan əmin olun.", parseMode: ParseMode::HTML);
                    return;
                }

                $this->saveBotToken($token);
                $this->botUsername = $me['result']['username'];

                $message->editText(
                    "✅ <b>Qlobal Köməkçi Bot uğurla bağlandı!</b>\n" .
                    "🤖 <b>Bot:</b> @{$this->botUsername}\n" .
                    "🌐 <b>Rejim:</b> Qlobal (Bütün userbot istifadəçiləri üçün ortaqdır)\n\n" .
                    "⚠️ <i>Vacib: Botun @BotFather-da <code>/setinline</code> rejiminin aktiv olduğuna əmin olun!</i>\n" .
                    "İndi <code>.delta</code> yazıb interaktiv menyunu yoxlaya bilərsiniz.",
                    parseMode: ParseMode::HTML
                );
                return;
            }

            // 2. .getbot - Bot statusunu yoxlamaq (Yalnız Owner)
            if ($command === 'getbot') {
                $token = $this->getBotToken();
                if (empty($token)) {
                    $message->editText("ℹ️ <b>Qlobal Köməkçi Bot təyin edilməyib.</b>\nToken əlavə etmək üçün: <code>.setbot &lt;TOKEN&gt;</code> və ya bazada <code>bot_settings</code> cədvəlində <code>helper_bot_token</code> sahəsinə yazın.", parseMode: ParseMode::HTML);
                    return;
                }

                $me = $this->callBotApi($token, 'getMe');
                if (!empty($me['result']['username'])) {
                    $u = $me['result']['username'];
                    $name = htmlspecialchars($me['result']['first_name'] ?? 'Bot', ENT_QUOTES, 'UTF-8');
                    $supportsInline = !empty($me['result']['supports_inline']) ? 'Aktiv ✅' : 'Deaktiv ⚠️ (/setinline edin)';
                    $message->editText(
                        "🤖 <b>Qlobal Köməkçi Bot Məlumatı:</b>\n" .
                        "• <b>Ad:</b> {$name}\n" .
                        "• <b>İstifadəçi adı:</b> @{$u}\n" .
                        "• <b>İnline rejimi:</b> {$supportsInline}\n" .
                        "• <b>Əhatə:</b> Qlobal (Bütün istifadəçilər üçün aktivdir) 🌐\n" .
                        "• <b>Status:</b> Qoşulub və işləyir 🟢",
                        parseMode: ParseMode::HTML
                    );
                } else {
                    $message->editText("⚠️ <b>Bot tokeni bazada var, lakin Telegram-a qoşulmaq alınmadı.</b>", parseMode: ParseMode::HTML);
                }
                return;
            }

            // 3. .delta - İnteraktiv menyunu çağırmaq
            if ($command === 'delta') {
                $token = $this->getBotToken();
                if (empty($token)) {
                    $senderId = (int) ($message->senderId ?? 0);
                    if ($this->isOwnerLevel($senderId)) {
                        $message->editText(
                            "⚠️ <b>Qlobal Köməkçi Bot Tokeni tapılmadı!</b>\n\n" .
                            "Token təyin etmək üçün:\n" .
                            "<b>1. Əmrlə:</b> <code>.setbot &lt;BOT_TOKEN&gt;</code>\n" .
                            "<b>2. phpMyAdmin-lə:</b> <code>bot_settings</code> cədvəlində <b>helper_bot_token</b> sətrinə tokeni yapışdırın.",
                            parseMode: ParseMode::HTML
                        );
                    } else {
                        $message->editText(
                            "⚠️ <b>Köməkçi Bot xidməti aktiv deyil.</b>\nLütfən sistem administratoru ilə əlaqə saxlayın.",
                            parseMode: ParseMode::HTML
                        );
                    }
                    return;
                }

                if (empty($this->botUsername)) {
                    $me = $this->callBotApi($token, 'getMe');
                    if (!empty($me['result']['username'])) {
                        $this->botUsername = $me['result']['username'];
                    }
                }

                if (empty($this->botUsername)) {
                    $message->editText("❌ <b>Köməkçi bota qoşulmaq alınmadı.</b> Lütfən bot tokeninizi yoxlayın.", parseMode: ParseMode::HTML);
                    return;
                }

                $senderId = (int) ($message->senderId ?? $this->ownerId);
                if ($senderId <= 0) $senderId = $this->ownerId;

                try {
                    $results = $this->api->messages->getInlineBotResults([
                        'bot' => '@' . $this->botUsername,
                        'peer' => $message->chatId,
                        'query' => 'delta_' . $senderId,
                        'offset' => ''
                    ]);

                    if (!empty($results['results'])) {
                        $this->api->messages->sendInlineBotResult([
                            'peer' => $message->chatId,
                            'query_id' => $results['query_id'],
                            'id' => $results['results'][0]['id']
                        ]);

                        try {
                            $message->delete();
                        } catch (\Throwable $td) {}
                    } else {
                        $message->editText(
                            "⚠️ <b>İnline cavab alınmadı.</b>\n" .
                            "Lütfən @BotFather-a gedib botunuz üçün <code>/setinline</code> əmrini icra edərək inline rejimini aktivləşdirin!",
                            parseMode: ParseMode::HTML
                        );
                    }
                } catch (\Throwable $tInline) {
                    $err = htmlspecialchars($tInline->getMessage(), ENT_QUOTES, 'UTF-8');
                    $message->editText(
                        "❌ <b>İnline xətası:</b> {$err}\n\n" .
                        "💡 <i>Qeyd: @BotFather-da botunuza <code>/setinline</code> etdiyinizdən əmin olun.</i>",
                        parseMode: ParseMode::HTML
                    );
                }
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    /**
     * Arxa fonda işləyən Asinxron (Non-blocking) Bot Polling Worker-i
     */
    private function runBotWorker(): void {
        while (true) {
            try {
                $token = $this->getBotToken();
                if (empty($token)) {
                    \Amp\delay(5);
                    continue;
                }

                if (empty($this->botUsername)) {
                    $me = $this->callBotApi($token, 'getMe');
                    if (!empty($me['result']['username'])) {
                        $this->botUsername = $me['result']['username'];
                    }
                }

                // timeout: 1 qoyulur, asinxron sorğu MadelineProto-nu bloklamır
                $updates = $this->callBotApi($token, 'getUpdates', [
                    'offset' => $this->updateOffset,
                    'limit' => 20,
                    'timeout' => 1
                ]);

                if (!empty($updates['ok']) && !empty($updates['result']) && is_array($updates['result'])) {
                    foreach ($updates['result'] as $up) {
                        $this->updateOffset = max($this->updateOffset, (int)$up['update_id'] + 1);

                        // A. İnline sorğu (.delta yazanda)
                        if (isset($up['inline_query'])) {
                            $iq = $up['inline_query'];
                            $iqId = $iq['id'];
                            $fromId = (int)($iq['from']['id'] ?? 0);
                            $q = trim($iq['query'] ?? '');

                            $ownerId = $fromId;
                            if (preg_match('/^delta_(\d+)/', $q, $m)) {
                                $ownerId = (int)$m[1];
                            }
                            if ($ownerId <= 0) $ownerId = $fromId;

                            if (!$this->isAuthorizedUser($fromId) && !$this->isAuthorizedUser($ownerId)) {
                                $this->callBotApi($token, 'answerInlineQuery', [
                                    'inline_query_id' => $iqId,
                                    'results' => [],
                                    'cache_time' => 1,
                                    'is_personal' => true
                                ]);
                                continue;
                            }

                            $menuData = $this->buildMenuPage(0, $ownerId);
                            $results = [
                                [
                                    'type' => 'article',
                                    'id' => 'delta_menu_' . time(),
                                    'title' => '⚡ Delta PHP Userbot İnteraktiv Menyu',
                                    'description' => 'Bütün pluginlərin interaktiv səhifəli siyahısı',
                                    'input_message_content' => [
                                        'message_text' => $menuData['text'],
                                        'parse_mode' => 'HTML'
                                    ],
                                    'reply_markup' => [
                                        'inline_keyboard' => $menuData['keyboard']
                                    ]
                                ]
                            ];

                            $this->callBotApi($token, 'answerInlineQuery', [
                                'inline_query_id' => $iqId,
                                'results' => $results,
                                'cache_time' => 1,
                                'is_personal' => true
                            ]);
                        }

                        // B. Düymə klikləri (Callback Query)
                        if (isset($up['callback_query'])) {
                            $cq = $up['callback_query'];
                            $cqId = $cq['id'];
                            $cqData = $cq['data'] ?? '';
                            $cqFromId = (int)($cq['from']['id'] ?? 0);
                            $cqMsg = $cq['message'] ?? null;
                            $inlineMsgId = $cq['inline_message_id'] ?? null;

                            // Düymədən əmri verən istifadəçinin ID-sini çıxarırıq
                            $expectedOwnerId = 0;
                            $parts = explode('_', $cqData);
                            if (str_starts_with($cqData, 'close_menu')) {
                                $expectedOwnerId = (int)($parts[2] ?? 0);
                            } elseif (str_starts_with($cqData, 'page_')) {
                                $expectedOwnerId = (int)($parts[2] ?? 0);
                            } elseif (str_starts_with($cqData, 'plg_')) {
                                $expectedOwnerId = (int)($parts[3] ?? 0);
                            }

                            // Yalnız həmin mesaj üçün .delta əmrini verən şəxs düymələrə basa bilər!
                            if ($expectedOwnerId > 0) {
                                if ($cqFromId !== $expectedOwnerId) {
                                    $this->callBotApi($token, 'answerCallbackQuery', [
                                        'callback_query_id' => $cqId,
                                        'text' => '⚠️ Bu menyu yalnız əmri verən istifadəçiyə aiddir!',
                                        'show_alert' => true
                                    ]);
                                    continue;
                                }
                            } else {
                                if (!$this->isAuthorizedUser($cqFromId)) {
                                    $this->callBotApi($token, 'answerCallbackQuery', [
                                        'callback_query_id' => $cqId,
                                        'text' => '⚠️ Bu menyu yalnız əmri verən istifadəçiyə aiddir!',
                                        'show_alert' => true
                                    ]);
                                    continue;
                                }
                            }

                            $this->callBotApi($token, 'answerCallbackQuery', ['callback_query_id' => $cqId]);

                            $editParams = [];
                            if (!empty($inlineMsgId)) {
                                $editParams['inline_message_id'] = $inlineMsgId;
                            } elseif ($cqMsg) {
                                $editParams['chat_id'] = $cqMsg['chat']['id'] ?? 0;
                                $editParams['message_id'] = $cqMsg['message_id'] ?? 0;
                            } else {
                                continue;
                            }

                            if (str_starts_with($cqData, 'close_menu')) {
                                $this->callBotApi($token, 'editMessageText', array_merge($editParams, [
                                    'text' => "🗑 <b>Kömək menyusu bağlandı.</b>",
                                    'parse_mode' => 'HTML'
                                ]));
                                continue;
                            }

                            if (str_starts_with($cqData, 'page_')) {
                                $targetPage = (int) ($parts[1] ?? 0);
                                $menuOwner = $expectedOwnerId > 0 ? $expectedOwnerId : $cqFromId;
                                $menuData = $this->buildMenuPage($targetPage, $menuOwner);
                                $this->callBotApi($token, 'editMessageText', array_merge($editParams, [
                                    'text' => $menuData['text'],
                                    'parse_mode' => 'HTML',
                                    'reply_markup' => ['inline_keyboard' => $menuData['keyboard']]
                                ]));
                                continue;
                            }

                            if (str_starts_with($cqData, 'plg_')) {
                                $pIndex = (int) ($parts[1] ?? 0);
                                $returnPage = (int) ($parts[2] ?? 0);
                                $menuOwner = $expectedOwnerId > 0 ? $expectedOwnerId : $cqFromId;

                                $detailData = $this->buildPluginDetail($pIndex, $returnPage, $menuOwner);
                                $this->callBotApi($token, 'editMessageText', array_merge($editParams, [
                                    'text' => $detailData['text'],
                                    'parse_mode' => 'HTML',
                                    'reply_markup' => ['inline_keyboard' => $detailData['keyboard']]
                                ]));
                                continue;
                            }
                        }
                    }
                }

            } catch (\Throwable $tLoop) {}

            \Amp\delay(0.2);
        }
    }

    private function buildMenuPage(int $page, int $ownerId = 0): array {
        $allPlugins = [];
        if (isset($this->api->pluginManager)) {
            $allPlugins = array_values(array_filter($this->api->pluginManager->getAllPlugins(), function($p) {
                return !($p instanceof SudoPlugin || get_class($p) === 'SudoPlugin' || ($p->name ?? '') === 'Sudo Admin İdarəsi');
            }));
        }

        $totalPlugins = count($allPlugins);
        $perPage = 10;
        $totalPages = max(1, (int) ceil($totalPlugins / $perPage));

        if ($page < 0) $page = 0;
        if ($page >= $totalPages) $page = $totalPages - 1;

        $slice = array_slice($allPlugins, $page * $perPage, $perPage);

        $keyboard = [];
        $row = [];

        foreach ($slice as $idxOnPage => $plugin) {
            $globalIndex = ($page * $perPage) + $idxOnPage;
            $name = $plugin->name ?? get_class($plugin);
            
            $cleanName = preg_replace('/\(.*?\)/', '', $name);
            $cleanName = trim(mb_substr($cleanName, 0, 16, 'UTF-8'));
            if ($cleanName === '') $cleanName = get_class($plugin);

            $row[] = [
                'text' => "📦 " . $cleanName,
                'callback_data' => "plg_{$globalIndex}_{$page}_{$ownerId}"
            ];

            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        if ($totalPages > 1) {
            $prevPage = ($page > 0) ? $page - 1 : $totalPages - 1;
            $nextPage = ($page < $totalPages - 1) ? $page + 1 : 0;

            $keyboard[] = [
                ['text' => '◀️ Əvvəlki', 'callback_data' => "page_{$prevPage}_{$ownerId}"],
                ['text' => ($page + 1) . ' / ' . $totalPages, 'callback_data' => "page_{$page}_{$ownerId}"],
                ['text' => 'Sonrakı ▶️', 'callback_data' => "page_{$nextPage}_{$ownerId}"]
            ];
        }

        $keyboard[] = [
            ['text' => '❌ Menyunı Bağla', 'callback_data' => "close_menu_{$ownerId}"]
        ];

        $ownerMention = $ownerId > 0 ? $this->formatUserMention($ownerId) : "";
        $ownerLine = $ownerMention !== "" ? "👤 <b>Əmri verən:</b> {$ownerMention}\n" : "";

        $text = "⚡ <b>Delta PHP Userbot — İnteraktiv Kömək Menyusu</b>\n\n" .
                $ownerLine .
                "📌 <i>Ətraflı məlumat və əmrlər üçün istədiyiniz pluginin düyməsinə toxunun:</i>\n\n" .
                "📊 <b>Aktiv pluginlər:</b> {$totalPlugins} ədəd\n" .
                "📄 <b>Səhifə:</b> " . ($page + 1) . " / {$totalPages}\n\n" .
                "🔒 <i>Bu menyu yalnız əmri verən şəxs tərəfindən idarə oluna bilər.</i>";

        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }

    private function buildPluginDetail(int $globalIndex, int $returnPage, int $ownerId = 0): array {
        $prefix = $this->getPrefix();
        $allPlugins = [];
        if (isset($this->api->pluginManager)) {
            $allPlugins = array_values(array_filter($this->api->pluginManager->getAllPlugins(), function($p) {
                return !($p instanceof SudoPlugin || get_class($p) === 'SudoPlugin' || ($p->name ?? '') === 'Sudo Admin İdarəsi');
            }));
        }

        $plugin = $allPlugins[$globalIndex] ?? null;
        if (!$plugin) {
            return [
                'text' => "⚠️ <b>Plugin tapılmadı.</b>",
                'keyboard' => [[['text' => '🔙 Geri', 'callback_data' => "page_{$returnPage}_{$ownerId}"]]]
            ];
        }

        $pName = htmlspecialchars($plugin->name ?? get_class($plugin), ENT_QUOTES, 'UTF-8');
        $pDesc = htmlspecialchars($plugin->description ?? 'Təsvir yoxdur.', ENT_QUOTES, 'UTF-8');
        $pClass = htmlspecialchars(get_class($plugin), ENT_QUOTES, 'UTF-8');

        $text = "📖 <b>Plugin:</b> {$pName}\n" .
                "🏷 <b>Fayl:</b> <code>{$pClass}.php</code>\n" .
                "📝 <b>Təsvir:</b> {$pDesc}\n\n" .
                "⚙️ <b>Komandalar və İstifadə:</b>\n";

        if (!empty($plugin->commands) && is_array($plugin->commands)) {
            foreach ($plugin->commands as $cmd => $desc) {
                $cName = htmlspecialchars($prefix . $cmd, ENT_QUOTES, 'UTF-8');
                $cDesc = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
                $text .= "• <code>{$cName}</code> — {$cDesc}\n";
            }
        } else {
            $text .= "<i>Bu plugin üçün birbaşa komanda yoxdur (arxa fonda xidmət edir).</i>\n";
        }

        $text .= "\n🔒 <i>Yalnız əmri verən istifadəçi idarə edə bilər.</i>";

        $keyboard = [
            [
                ['text' => "🔙 Geri (Səhifə " . ($returnPage + 1) . ")", 'callback_data' => "page_{$returnPage}_{$ownerId}"]
            ]
        ];

        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }

    private function ensureSettingExists(): void {
        if (!$this->db) return;
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS bot_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->db->exec("INSERT IGNORE INTO bot_settings (setting_key, setting_value) VALUES ('helper_bot_token', '')");
        } catch (\Throwable $t) {}
    }

    private function getBotToken(): string {
        if (!$this->db) return '';
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'helper_bot_token'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if (!empty($val)) return trim((string)$val);

            $stmt2 = $this->db->query("SELECT setting_value FROM user_settings WHERE setting_key = 'helper_bot_token' AND setting_value != '' ORDER BY id DESC LIMIT 1");
            $val2 = $stmt2->fetchColumn();
            if (!empty($val2)) return trim((string)$val2);
        } catch (\Throwable $t) {}
        return '';
    }

    private function saveBotToken(string $token): void {
        if (!$this->db) return;
        try {
            $this->ensureSettingExists();
            $stmt = $this->db->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES ('helper_bot_token', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$token, $token]);
        } catch (\Throwable $t) {}
    }

    private function isOwnerLevel(int $senderId): bool {
        if ($this->isOwner($senderId) || $this->isOwner($this->ownerId)) {
            return true;
        }
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM ub_admins WHERE (telegram_id = ? OR telegram_id = ?) AND level = 'owner'");
                $stmt->execute([$senderId, $this->ownerId]);
                if ($stmt->fetch()) return true;
            } catch (\Throwable $t) {}
        }
        return false;
    }

    private function isAuthorizedUser(int $telegramId): bool {
        if ($telegramId <= 0) return false;
        if ($telegramId === $this->ownerId || $telegramId === self::PRIMARY_OWNER_ID) {
            return true;
        }
        if ($this->db) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE telegram_id = ?");
                $stmt->execute([$telegramId]);
                if ($stmt->fetch()) return true;

                $stmt2 = $this->db->prepare("SELECT id FROM ub_admins WHERE telegram_id = ?");
                $stmt2->execute([$telegramId]);
                if ($stmt2->fetch()) return true;
            } catch (\Throwable $t) {}
        }
        return false;
    }

    private function getHttpClient(): ?\Amp\Http\Client\HttpClient {
        if ($this->httpClient === null && class_exists('\Amp\Http\Client\HttpClientBuilder')) {
            $this->httpClient = \Amp\Http\Client\HttpClientBuilder::buildDefault();
        }
        return $this->httpClient;
    }

    /**
     * Tam asinxron (non-blocking) HTTP sorğusu - Userbotu əsla yavaşlatmır!
     */
    private function callBotApi(string $token, string $method, array $params = []): ?array {
        $url = "https://api.telegram.org/bot{$token}/{$method}";

        // 1. Amp Asinxron HTTP Klienti (0 bloklama, 0 gecikmə)
        try {
            $client = $this->getHttpClient();
            if ($client !== null && class_exists('\Amp\Http\Client\Request')) {
                $req = new \Amp\Http\Client\Request($url, 'POST');
                $req->setHeader('Content-Type', 'application/json');
                $req->setBody(json_encode($params));
                $req->setInactivityTimeout(6);
                $req->setTransferTimeout(6);
                $res = $client->request($req);
                $body = $res->getBody()->buffer();
                $data = json_decode($body, true);
                return is_array($data) ? $data : null;
            }
        } catch (\Throwable $t) {}

        // 2. Fallback
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode($params),
                'timeout' => 3
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) return null;
        $data = json_decode($res, true);
        return is_array($data) ? $data : null;
    }
}
