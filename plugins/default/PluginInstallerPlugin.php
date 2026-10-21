<?php
use App\BasePlugin;
use danog\MadelineProto\ParseMode;

class PluginInstallerPlugin extends BasePlugin {
    public string $name = 'Plugin İdarəsi';
    public string $description = 'Kənar pluginləri quraşdırır və təhlükəsiz idarə edir.';
    public array $commands = [
        'pinstall' => 'Kənar plugin quraşdırır (fayla reply ilə)',
        'puninstall' => 'Quraşdırdığınız kənar plugini silir (.puninstall [ad])',
        'plugins' => 'Aktiv pluginlərin siyahısını göstərir'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $pm = $this->api->pluginManager ?? null;
            if (!$pm) {
                $message->editText("⚠️ PluginManager tapılmadı.", parseMode: ParseMode::HTML);
                return;
            }
            
            $userId = $this->getUserId();
            
            if ($command === 'plugins') {
                $all = $pm->getAllPlugins();
                $text = "🧩 <b>Yüklənmiş Pluginlər:</b>\n\n";
                $defaultList = [];
                $customList = [];

                foreach ($all as $p) {
                    $nameEsc = htmlspecialchars($p->name ?? 'Naməlum', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    if (!empty($p->isCustom)) {
                        $customList[] = "• 🧩 <b>{$nameEsc}</b> <i>(Kənar)</i>";
                    } else {
                        $defaultList[] = "• 📦 <b>{$nameEsc}</b>";
                    }
                }

                $text .= "📦 <b>Əsas Sistem Pluginləri (" . count($defaultList) . "):</b>\n";
                $text .= implode("\n", $defaultList) . "\n\n";

                if (!empty($customList)) {
                    $text .= "🧩 <b>Sizin Kənar Pluginləriniz (" . count($customList) . "):</b>\n";
                    $text .= implode("\n", $customList) . "\n";
                } else {
                    $text .= "🧩 <b>Kənar Pluginlər:</b> <i>Hələ ki, heç bir kənar plugin quraşdırılmayıb.</i>\n";
                }

                $message->editText($text, parseMode: ParseMode::HTML);
                return;
            }

            if ($command === 'pinstall') {
                $reply = $message->getReply();
                if (!$reply) {
                    $message->editText(
                        "ℹ️ <b>Kənar plugin quraşdırmaq qaydası:</b>\n" .
                        "1. İstədiyiniz <code>.php</code> plugin faylını Telegram çata göndərin.\n" .
                        "2. Həmin fayla cavab (reply) verərək <code>.pinstall</code> yazın.\n\n" .
                        "🛡 <b>Təhlükəsizlik Zəmanəti:</b>\n" .
                        "• Bot avtomatik olaraq kodu zərərli skriptlərə qarşı yoxlayır.\n" .
                        "• Kənar pluginlərin Verilənlər Bazasına (DB) toxunması, sistem/shell əmrləri icra etməsi və server fayllarını dəyişməsi qəti qadağandır.",
                        parseMode: ParseMode::HTML
                    );
                    return;
                }

                $message->editText("🔍 <i>Plugin yüklənir və təhlükəsizlik auditindən keçirilir...</i>", parseMode: ParseMode::HTML);

                $tempDir = sys_get_temp_dir() . '/delta_plugins/';
                if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);
                
                $filePath = $this->api->downloadToDir($reply, $tempDir);
                if (!$filePath || !file_exists($filePath)) {
                    $message->editText("❌ Faylı yükləmək mümkün olmadı.", parseMode: ParseMode::HTML);
                    return;
                }

                $res = $pm->installPlugin($filePath, $this->ownerId);

                // Əgər təhlükəsizlik auditindən keçməyibsə -> Loq qrupuna bildiriş göndər
                if (!$res['success']) {
                    try {
                        $senderId = (int) ($message->senderId ?? $this->ownerId);
                        $userMention = $this->formatUserMention($senderId);
                        $adminTags = \App\Logger::getAdminTags($this->db);
                        $logMsg = "🔰 {$userMention} — zərərli plugin yükləməyə çalışdı!\n{$adminTags}";
                        \App\Logger::log($logMsg, $this->db);
                    } catch (\Throwable $tLog) {}
                }

                $message->editText($res['message'], parseMode: ParseMode::HTML);
                return;
            }

            if ($command === 'puninstall') {
                $target = isset($args[0]) ? trim($args[0]) : '';

                // Əgər parametr verilməyibsə -> Yalnız silinə biləcək kənar pluginlərin siyahısını göstər
                if (empty($target)) {
                    $customs = $pm->getCustomPlugins($this->ownerId);
                    if (empty($customs)) {
                        $message->editText(
                            "ℹ️ <b>Quraşdırılmış kənar plugininiz yoxdur.</b>\n\n" .
                            "⚠️ <i>Qeyd: Yalnız öz quraşdırdığınız kənar pluginləri silə bilərsiniz. Əsas (default) sistem pluginləri toxunulmazdır və silinə bilməz!</i>",
                            parseMode: ParseMode::HTML
                        );
                        return;
                    }

                    $text = "🗑 <b>Silə biləcəyiniz kənar pluginləriniz:</b>\n\n";
                    $count = 1;
                    foreach ($customs as $cp) {
                        $cpName = htmlspecialchars($cp['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $text .= "{$count}. 🧩 <b>{$cpName}</b> — Silmək üçün: <code>.puninstall {$cpName}</code>\n";
                        $count++;
                    }
                    $text .= "\n🔒 <i>Əsas (default) sistem pluginləri qorunur və silinə bilməz.</i>";
                    $message->editText($text, parseMode: ParseMode::HTML);
                    return;
                }

                $res = $pm->uninstallPlugin($target, $this->ownerId);
                $message->editText($res['message'], parseMode: ParseMode::HTML);
                return;
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err, parseMode: ParseMode::HTML);
        }
    }
    
    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return $user['id'] ?? 0;
    }
}
