<?php
use App\BasePlugin;
use App\Database;

class NotesPlugin extends BasePlugin {
    public string $name = 'Qeydlər';
    public string $description = 'Söhbətlər üçün qeydlər yadda saxlayır.';
    public array $commands = ['save' => 'Qeyd saxla', 'get' => 'Qeydi al', 'notes' => 'Qeydlər', 'clear' => 'Qeydi sil', 'clearall' => 'Hamısını sil'];
    public bool $handlesMessages = true;

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            $chatId = $message->chatId;
            
            if ($command === 'save') {
                $name = trim($args[0] ?? '');
                $content = trim(implode(' ', array_slice($args, 1)));
                $reply = $message->getReply();
                if ($reply && empty($content)) {
                    $content = $reply->message ?? '';
                }
                
                if (empty($name) || empty($content)) {
                    $message->editText(
                        "ℹ️ <b>Qeyd saxlamaq qaydası:</b>\n\n" .
                        "1. Mətn ilə birbaşa:\n<code>.save [qeyd_adı] [məzmun]</code>\n\n" .
                        "2. Mesaja cavab verərək:\n<code>.save [qeyd_adı]</code> (istənilən mesajı yanıtlayaraq)\n\n" .
                        "<i>Məsələn:</i> <code>.save qaydalar Qrup qaydalarına riayət edin!</code>\n" .
                        "<i>Qeydi oxumaq:</i> <code>#qaydalar</code> və ya <code>.get qaydalar</code>",
                        parseMode: \danog\MadelineProto\ParseMode::HTML
                    );
                    return;
                }
                
                $stmt = $this->db->prepare("INSERT INTO notes (user_id, chat_id, note_name, note_content) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE note_content = VALUES(note_content)");
                $stmt->execute([$userId, $chatId, $name, $content]);
                $message->editText("✅ Qeyd <b>{$name}</b> yadda saxlanıldı.\n<i>Oxumaq üçün:</i> <code>#{$name}</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'get') {
                $name = trim($args[0] ?? '');
                if (empty($name)) {
                    $message->editText("ℹ️ <b>Qeydi oxumaq üçün:</b>\n<code>.get [qeyd_adı]</code> və ya <code>#[qeyd_adı]</code>\n\n<i>Qeydlərin siyahısı üçün:</i> <code>.notes</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $this->sendNote($message, $userId, $chatId, $name);
            } elseif ($command === 'notes') {
                $stmt = $this->db->prepare("SELECT note_name FROM notes WHERE user_id=? AND chat_id=?");
                $stmt->execute([$userId, $chatId]);
                $notes = $stmt->fetchAll();
                $text = "📝 <b>Bu söhbətdəki qeydlər:</b>\n\n";
                foreach ($notes as $n) $text .= "• <code>#{$n['note_name']}</code>\n";
                $message->editText(empty($notes) ? "⚠️ Bu çatda qeyd yoxdur.\n<i>Qeyd əlavə etmək üçün:</i> <code>.save [ad] [məzmun]</code>" : $text, parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'clear') {
                $name = trim($args[0] ?? '');
                if (empty($name)) {
                    $message->editText("ℹ️ <b>Qeydi silmək üçün:</b>\n<code>.clear [qeyd_adı]</code>\n\n<i>Aktiv qeydlər üçün:</i> <code>.notes</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $stmt = $this->db->prepare("DELETE FROM notes WHERE user_id=? AND chat_id=? AND note_name=?");
                $stmt->execute([$userId, $chatId, $name]);
                $message->editText("🗑 <b>{$name}</b> qeydi silindi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'clearall') {
                $stmt = $this->db->prepare("DELETE FROM notes WHERE user_id=? AND chat_id=?");
                $stmt->execute([$userId, $chatId]);
                $message->editText("🗑 Bütün qeydlər silindi.");
            }
        } catch (\Throwable $e) {
            $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }

    public function onMessage($message): void {
        if (preg_match('/^#(\w+)/', $message->message, $matches)) {
            $this->sendNote($message, $this->getUserId(), $message->chatId, $matches[1], true);
        }
    }

    private function sendNote($message, $userId, $chatId, $name, $isReply = false) {
        $stmt = $this->db->prepare("SELECT note_content FROM notes WHERE user_id=? AND chat_id=? AND note_name=?");
        $stmt->execute([$userId, $chatId, $name]);
        $note = $stmt->fetch();
        if ($note) {
            if ($isReply) $message->reply($note['note_content']);
            else $message->editText($note['note_content']);
        } else if (!$isReply) {
            $message->editText("⚠️ Qeyd tapılmadı.");
        }
    }

    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return $user['id'] ?? 0;
    }
}
