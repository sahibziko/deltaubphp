<?php
use App\BasePlugin;

class ReminderPlugin extends BasePlugin {
    public string $name = 'Xatırlatma';
    public string $description = 'Xatırlatmalar qurun.';
    public array $commands = ['remind' => 'Xatırlatma qur', 'reminders' => 'Siyahı'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            if ($command === 'remind') {
                $time = (int)($args[0] ?? 0);
                $text = implode(' ', array_slice($args, 1));
                if ($time <= 0 || empty($text)) {
                    $message->editText("⚠️ İstifadə: .remind [dəqiqə] [mətn]", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                
                $remindAt = date('Y-m-d H:i:s', time() + ($time * 60));
                
                $stmt = $this->db->prepare('INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
                $key = "reminder_" . uniqid();
                $val = json_encode(['text' => $text, 'time' => $remindAt], JSON_UNESCAPED_UNICODE);
                $stmt->execute([$userId, $key, $val, $val]);
                
                $message->editText("⏰ Xatırlatma qeydə alındı: {$remindAt}", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'reminders') {
                $stmt = $this->db->prepare('SELECT setting_key, setting_value FROM user_settings WHERE user_id = ? AND setting_key LIKE "reminder_%"');
                $stmt->execute([$userId]);
                $reminders = $stmt->fetchAll();
                
                $text = "⏰ <b>Xatırlatmalar:</b>\n";
                foreach ($reminders as $r) {
                    $data = json_decode($r['setting_value'], true);
                    if ($data) {
                        $text .= "• <code>{$data['time']}</code> - {$data['text']}\n";
                    }
                }
                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
    
    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return $user['id'] ?? 0;
    }
}
