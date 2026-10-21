<?php
use App\BasePlugin;

class SudoPlugin extends BasePlugin {
    public string $name = 'Sudo Admin İdarəsi';
    public string $description = 'Userbot Sudo Admin icazələrini idarə edir.';
    public array $commands = [
        'sudo' => 'Sudo İdarəetmə Paneli və bütün Owner əmrləri (.sudo) (Yalnız Owner)',
        'upromote' => 'İstifadəçiyə Sudo Admin yetkisi verir (Yalnız Owner)',
        'udemote' => 'İstifadəçidən Sudo Admin yetkisini alır (Yalnız Owner)',
        'udlist' => 'Sudo Admin siyahısını göstərir',
        'urestart' => 'Bütün userbotlara səssiz yenilənmə verib restart atır (Yalnız Owner)',
        'setchannel' => 'Rəsmi kanalı təyin edir (.setchannel @kanal) (Yalnız Owner)',
        'setgroup' => 'Rəsmi qrupu təyin edir (.setgroup @qrup) (Yalnız Owner)',
        'setlog' => 'Loq qrupunu təyin edir (.setlog -100xxx) (Yalnız Owner)',
        'getofficial' => 'Rəsmi resurslara və loq qrupuna baxış (.getofficial) (Yalnız Owner)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $senderId = (int) ($message->senderId ?? 0);

            if ($command === 'udlist') {
                $stmt = $this->db->prepare("SELECT telegram_id, level FROM ub_admins ORDER BY level DESC, id ASC");
                $stmt->execute();
                $admins = $stmt->fetchAll();

                $text = "👑 <b>ApexUserBot Sudo Adminlər:</b>\n\n";
                if (empty($admins)) {
                    $text .= "<i>Hələ ki, Sudo Admin təyin olunmayıb.</i>";
                } else {
                    foreach ($admins as $a) {
                        $badge = ($a['level'] === 'owner') ? '⭐ Owner' : '🛡 Basic';
                        $tid = (int) $a['telegram_id'];
                        $mention = $this->formatUserMention($tid);
                        $text .= "• {$mention} — <b>{$badge}</b>\n";
                    }
                }
                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            // Aşağıdakı əmrlər yalnız Owner (8300069885) üçündür
            if (!$this->isOwner($senderId) && !$this->isOwner($this->ownerId)) {
                $message->editText("⚠️ Bu əmr yalnız <b>Owner (8300069885)</b> tərəfindən istifadə edilə bilər.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'sudo') {
                $prefix = $this->getPrefix();
                $text = "👑 <b>ApexUserBot — Sudo / Owner İdarəetmə Paneli</b>\n\n" .
                        "<i>Yalnız sistem rəhbəri (Owner) tərəfindən icra edilə bilən əmrlər:</i>\n\n" .
                        "⚡ <b>Sistem İdarəsi:</b>\n" .
                        "• <code>{$prefix}reload</code> — Bütün sistemi söndürüb yenidən başladır\n" .
                        "• <code>{$prefix}urestart</code> — Səssiz restart atır və loq qrupuna bildiriş göndərir\n\n" .
                        "🛡 <b>Sudo Admin İdarəsi:</b>\n" .
                        "• <code>{$prefix}upromote &lt;id/reply&gt;</code> — İstifadəçiyə Sudo Admin yetkisi verir\n" .
                        "• <code>{$prefix}udemote &lt;id/reply&gt;</code> — İstifadəçidən Sudo Admin yetkisini alır\n" .
                        "• <code>{$prefix}udlist</code> — Mövcud Sudo Adminlərin siyahısı\n\n" .
                        "📢 <b>Rəsmi Resurslar və Loq:</b>\n" .
                        "• <code>{$prefix}setchannel &lt;@kanal&gt;</code> — Rəsmi kanalı təyin edir (və ya 'sil')\n" .
                        "• <code>{$prefix}setgroup &lt;@qrup&gt;</code> — Rəsmi qrupu təyin edir (və ya 'sil')\n" .
                        "• <code>{$prefix}setlog &lt;-100xxx&gt;</code> — Loq qrupunu təyin edir (və ya 'sil')\n" .
                        "• <code>{$prefix}getofficial</code> — Mövcud rəsmi kanalları və loq qrupunu göstərir\n\n" .
                        "🤖 <b>Köməkçi Bot (Helper Bot):</b>\n" .
                        "• <code>{$prefix}setbot &lt;token&gt;</code> — Qlobal Köməkçi Bot tokenini qeyd edir\n" .
                        "• <code>{$prefix}getbot</code> — Köməkçi botun statusunu yoxlayır";

                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'getofficial') {
                $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE setting_key IN ('official_channel', 'official_group', 'log_group')");
                $stmt->execute();
                $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

                $ch = !empty($settings['official_channel']) ? "<code>{$settings['official_channel']}</code>" : "<i>Təyin edilməyib</i>";
                $gr = !empty($settings['official_group']) ? "<code>{$settings['official_group']}</code>" : "<i>Təyin edilməyib</i>";
                $lg = !empty($settings['log_group']) ? "<code>{$settings['log_group']}</code>" : "<i>Təyin edilməyib</i>";

                $text = "📢 <b>Botun Rəsmi Resursları (bot_settings):</b>\n\n" .
                        "📢 <b>Kanal:</b> {$ch}\n" .
                        "👥 <b>Qrup:</b> {$gr}\n" .
                        "📋 <b>Loq Qrupu:</b> {$lg}\n\n" .
                        "ℹ️ <i>Botlar işə düşərkən kanala və qrupa avtomatik qoşulurlar. Mühüm hadisələr loq qrupuna göndərilir.</i>\n\n" .
                        "<i>Dəyişmək üçün:</i>\n<code>.setchannel @kanal</code>\n<code>.setgroup @qrup</code>\n<code>.setlog -100xxx</code>\n<i>Silmək üçün:</i> <code>.setlog sil</code>";
                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'setlog') {
                $val = trim($args[0] ?? '');
                if ($val === '' || strtolower($val) === 'sil') {
                    $stmt = $this->db->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES ('log_group', '') ON DUPLICATE KEY UPDATE setting_value = ''");
                    $stmt->execute();
                    $message->editText("🗑 Loq qrupu silindi (boşaldıldı).", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $stmt = $this->db->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES ('log_group', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$val, $val]);
                $message->editText("✅ Loq qrupu təyin edildi: <code>{$val}</code>\n<i>Mühüm hadisələr (qurulum, zərərli plugin cəhdləri, restart) bu qrupa göndəriləcək.</i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'urestart') {
                $mention = $this->formatUserMention($senderId);
                $message->editText("🔄 <b>Bütün Userbotlar yenidən başladılır...</b>\n<i>Köhnə proseslər RAM-dan təmizlənir.</i>", parseMode: \danog\MadelineProto\ParseMode::HTML);

                \App\Logger::log("🔰 {$mention} — Userbota müvəffəqiyyətlə restart verildi! — 1 dəqiqə sonra botunuz aktiv olacaq. ✨", $this->db);

                sleep(1);
                $rootDir = dirname(dirname(__DIR__));
                $lockFile = $rootDir . '/sessions/bot.lock';
                if (file_exists($lockFile)) {
                    @unlink($lockFile);
                }
                $myPid = getmypid();
                if (function_exists('posix_kill') && $myPid > 0) {
                    @posix_kill($myPid, 9);
                }
                exit(0);
            }

            if ($command === 'setchannel') {
                $val = trim($args[0] ?? '');
                if ($val === '' || strtolower($val) === 'sil') {
                    $stmt = $this->db->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES ('official_channel', '') ON DUPLICATE KEY UPDATE setting_value = ''");
                    $stmt->execute();
                    $message->editText("🗑 Rəsmi kanal silindi (boşaldıldı).", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $stmt = $this->db->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES ('official_channel', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$val, $val]);
                $message->editText("✅ Rəsmi kanal təyin edildi: <code>{$val}</code>\n<i>Bütün botlar aktivləşdikdə avtomatik bu kanala qoşulacaq.</i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'setgroup') {
                $val = trim($args[0] ?? '');
                if ($val === '' || strtolower($val) === 'sil') {
                    $stmt = $this->db->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES ('official_group', '') ON DUPLICATE KEY UPDATE setting_value = ''");
                    $stmt->execute();
                    $message->editText("🗑 Rəsmi qrup silindi (boşaldıldı).", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $stmt = $this->db->prepare("INSERT INTO bot_settings (setting_key, setting_value) VALUES ('official_group', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$val, $val]);
                $message->editText("✅ Rəsmi qrup təyin edildi: <code>{$val}</code>\n<i>Bütün botlar aktivləşdikdə avtomatik bu qrupa qoşulacaq.</i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $targetId = 0;
            $reply = $message->getReply();
            if ($reply && isset($reply->senderId)) {
                $targetId = (int) $reply->senderId;
            } elseif (!empty($args[0])) {
                $raw = trim($args[0]);
                if (is_numeric($raw)) {
                    $targetId = (int) $raw;
                } else {
                    $info = $this->api->getInfo($raw);
                    $targetId = (int) ($info['bot_api_id'] ?? $info['User']['id'] ?? 0);
                }
            }

            if ($targetId === 0) {
                $message->editText("⚠️ Lütfən istifadəçini yanıtlayın və ya ID/username qeyd edin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $targetMention = $this->formatUserMention($targetId);

            if ($command === 'upromote') {
                if ($this->isOwner($targetId)) {
                    $message->editText("ℹ️ Bu istifadəçi onsuz da əsas <b>Owner</b>-dir!", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $stmt = $this->db->prepare("INSERT INTO ub_admins (telegram_id, added_by, level) VALUES (?, ?, 'admin') ON DUPLICATE KEY UPDATE level='admin'");
                $stmt->execute([$targetId, $senderId]);

                $message->editText("✅ {$targetMention} istifadəçisinə <b>Basic Admin</b> yetkisi verildi!", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'udemote') {
                if ($this->isOwner($targetId)) {
                    $message->editText("⚠️ Əsas <b>Owner</b>-in yetkisini ala bilməzsiniz!", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $stmt = $this->db->prepare("DELETE FROM ub_admins WHERE telegram_id = ? AND level != 'owner'");
                $stmt->execute([$targetId]);

                $message->editText("🚫 {$targetMention} istifadəçisinin Admin yetkisi alındı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
