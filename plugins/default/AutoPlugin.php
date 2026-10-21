<?php
use App\BasePlugin;

class AutoPlugin extends BasePlugin {
    public string $name = 'Avto Saat və Bio';
    public string $description = 'Profil adına və ya bio-suna avtomatik olaraq cari saat və tarixi (Bakı vaxtı ilə) qoyur.';
    public array $commands = [
        'auto' => 'Avto rejimi aktiv edir (.auto ad / .auto bio / .auto both / .auto off)',
        'autostop' => 'Avto rejimi söndürür'
    ];
    public bool $handlesMessages = true;

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            if ($userId === 0) {
                $message->editText("⚠️ İstifadəçi tapılmadı.");
                return;
            }

            if ($command === 'autostop') {
                $this->setAutoSetting($userId, 'auto_mode', 'off');
                $message->editText("🚫 <b>Avto saat/bio yenilənməsi söndürüldü!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'auto') {
                $mode = isset($args[0]) ? strtolower(trim($args[0])) : '';
                
                if (!in_array($mode, ['ad', 'bio', 'both', 'off', 'stop'])) {
                    $message->editText(
                        "⚠️ <b>Keçərsiz seçim!</b>\n\n"
                      . "📌 <b>İstifadə qaydası:</b>\n"
                      . "• <code>.auto ad</code> - Soyada avtomatik Bakı saatı qoyur (məs: <code>| 14:10</code>)\n"
                      . "• <code>.auto bio</code> - Bio-ya avtomatik Bakı tarixi və saatı qoyur\n"
                      . "• <code>.auto both</code> - Həm soyada, həm de bio-ya qoyur\n"
                      . "• <code>.auto off</code> - Rejimi söndürür",
                        parseMode: \danog\MadelineProto\ParseMode::HTML
                    );
                    return;
                }

                if ($mode === 'off' || $mode === 'stop') {
                    $this->setAutoSetting($userId, 'auto_mode', 'off');
                    $message->editText("🚫 <b>Avto saat/bio yenilənməsi söndürüldü!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $this->setAutoSetting($userId, 'auto_mode', $mode);
                $this->setAutoSetting($userId, 'auto_last_update', '0'); // Dərhal yenilənsin

                // Dərhal profili yenilə
                $this->performProfileUpdate($mode);

                $modeTitle = ($mode === 'ad') ? 'Soyad' : (($mode === 'bio') ? 'Bio' : 'Soyad və Bio');
                $message->editText("✅ <b>Avto {$modeTitle} yenilənməsi (Bakı vaxtı) aktiv edildi!</b>\n<i>Hər dəqiqə avtomatik yenilənəcək.</i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    public function onMessage($message): void {
        try {
            $userId = $this->getUserId();
            if ($userId === 0) return;

            $mode = $this->getAutoSetting($userId, 'auto_mode', 'off');
            if ($mode === 'off') return;

            $lastUpdate = (int) $this->getAutoSetting($userId, 'auto_last_update', '0');
            $now = time();

            // 60 saniyədən bir yenilə
            if (($now - $lastUpdate) >= 60) {
                $this->setAutoSetting($userId, 'auto_last_update', (string) $now);
                $this->performProfileUpdate($mode);
            }
        } catch (\Throwable $e) {}
    }

    private function performProfileUpdate(string $mode): void {
        try {
            date_default_timezone_set('Asia/Baku');
            $hm = date('H:i');
            $dmy = date('d.m.Y');

            if ($mode === 'ad' || $mode === 'both') {
                $this->api->account->updateProfile(
                    last_name: "| {$hm}"
                );
            }

            if ($mode === 'bio' || $mode === 'both') {
                $this->api->account->updateProfile(
                    about: "📅 {$dmy} | ⌚ {$hm} | ApexUserBot"
                );
            }
        } catch (\Throwable $t) {}
    }

    private function getUserId(): int {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return (int) ($user['id'] ?? 0);
    }

    private function getAutoSetting(int $userId, string $key, string $default = ''): string {
        $stmt = $this->db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?");
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    private function setAutoSetting(int $userId, string $key, string $value): void {
        $stmt = $this->db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$userId, $key, $value, $value]);
    }

    public static function stopForUser(int $ownerId, ?\App\Database $db): bool {
        if (!$db) return false;
        try {
            $stmt = $db->prepare('SELECT id FROM users WHERE telegram_id = ?');
            $stmt->execute([$ownerId]);
            $user = $stmt->fetch();
            if ($user && !empty($user['id'])) {
                $userId = (int) $user['id'];
                $check = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = 'auto_mode'");
                $check->execute([$userId]);
                $val = $check->fetchColumn();
                if ($val && $val !== 'off') {
                    $upd = $db->prepare("UPDATE user_settings SET setting_value = 'off' WHERE user_id = ? AND setting_key = 'auto_mode'");
                    $upd->execute([$userId]);
                    return true;
                }
            }
        } catch (\Throwable $e) {}
        return false;
    }
}
