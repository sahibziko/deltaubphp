<?php
namespace App;

class Logger
{
    /**
     * Helper bot vasitəsilə log_group qrupuna bildiriş göndərir
     */
    public static function log(string $message, ?Database $db = null): bool
    {
        try {
            if (!$db) {
                $db = Database::getInstance();
            }
            if (!$db) {
                return false;
            }

            $stmt = $db->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE setting_key IN ('helper_bot_token', 'log_group')");
            $stmt->execute();
            $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $token = trim($settings['helper_bot_token'] ?? '');
            $logGroup = trim($settings['log_group'] ?? '');

            // Əgər boşdursa -> heç nə etmə (es keç)
            if ($token === '' || $logGroup === '') {
                return false;
            }

            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $data = [
                'chat_id' => $logGroup,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ];

            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $res = curl_exec($ch);
                curl_close($ch);
                return !empty($res);
            }
        } catch (\Throwable $e) {}

        return false;
    }

    /**
     * Admin tağları hazırlayır (ub_admins cədvəlindən və əsas owner-dən)
     */
    public static function getAdminTags(?Database $db = null): string
    {
        $tags = [];
        $tags[] = '<a href="tg://user?id=8300069885">👑 Sahib</a>';

        if ($db) {
            try {
                $stmt = $db->prepare("SELECT telegram_id, level FROM ub_admins WHERE telegram_id != 8300069885");
                $stmt->execute();
                foreach ($stmt->fetchAll() as $row) {
                    $id = (int) $row['telegram_id'];
                    if ($id > 0) {
                        $role = ($row['level'] === 'owner') ? '⭐ Sahib' : '🛡 Admin';
                        $tags[] = "<a href=\"tg://user?id={$id}\">{$role}</a>";
                    }
                }
            } catch (\Throwable $e) {}
        }

        return implode(' ', $tags);
    }
}
