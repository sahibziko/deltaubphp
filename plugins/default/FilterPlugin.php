<?php
use App\BasePlugin;
use App\Database;

class FilterPlugin extends BasePlugin {
    public string $name = 'Filtrlər';
    public string $description = 'Avtomatik cavab filtrləri yaradır.';
    public array $commands = ['filter' => 'Filtr əlavə edir', 'filters' => 'Filtrlər', 'stop' => 'Filtr silir'];
    public bool $handlesMessages = true;

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            $chatId = $message->chatId;
            
            if ($command === 'filter') {
                $keyword = trim($args[0] ?? '');
                $reply_text = trim(implode(' ', array_slice($args, 1)));
                if (empty($keyword) || empty($reply_text)) {
                    $message->editText("ℹ️ <b>Filtr əlavə etmək qaydası:</b>\n<code>.filter [söz] [cavab mətni]</code>\n\n<i>Məsələn:</i> <code>.filter salam Hər vaxtınız xeyir!</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                
                $stmt = $this->db->prepare("INSERT INTO filters (user_id, chat_id, keyword, reply_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $chatId, $keyword, $reply_text]);
                $message->editText("✅ <b>{$keyword}</b> üçün filtr əlavə edildi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'filters') {
                $stmt = $this->db->prepare("SELECT keyword FROM filters WHERE user_id=? AND chat_id=?");
                $stmt->execute([$userId, $chatId]);
                $filters = $stmt->fetchAll();
                $text = "🔎 <b>Aktiv filtrlər:</b>\n";
                foreach ($filters as $f) $text .= "- {$f['keyword']}\n";
                $message->editText(empty($filters) ? "⚠️ Filtr yoxdur." : $text, parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'stop') {
                $keyword = trim($args[0] ?? '');
                if (empty($keyword)) {
                    $message->editText("ℹ️ <b>Filtri silmək qaydası:</b>\n<code>.stop [söz]</code>\n\n<i>Aktiv filtrlərə baxmaq üçün:</i> <code>.filters</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $stmt = $this->db->prepare("DELETE FROM filters WHERE user_id=? AND chat_id=? AND keyword=?");
                $stmt->execute([$userId, $chatId, $keyword]);
                $message->editText("🗑 <b>{$keyword}</b> filtri silindi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) {
            $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }

    public function onMessage($message): void {
        // Əgər mesaj istifadəçinin özü (out) və ya sahibi tərəfindən göndərilibsə -> Reaksiya vermə
        if (!empty($message->out)) {
            return;
        }

        $senderId = (int) ($message->senderId ?? $message->fromId ?? 0);
        if ($senderId === $this->ownerId || $this->isOwner($senderId)) {
            return;
        }

        $userId = $this->getUserId();
        $chatId = $message->chatId;
        
        $stmt = $this->db->prepare("SELECT keyword, reply_text FROM filters WHERE user_id=? AND chat_id=?");
        $stmt->execute([$userId, $chatId]);
        $filters = $stmt->fetchAll();
        
        foreach ($filters as $f) {
            if (stripos($message->message, $f['keyword']) !== false) {
                $message->reply($f['reply_text']);
                break;
            }
        }
    }

    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        return $stmt->fetch()['id'] ?? 0;
    }
}
