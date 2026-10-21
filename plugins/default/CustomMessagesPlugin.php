<?php
use App\BasePlugin;

class CustomMessagesPlugin extends BasePlugin {
    public string $name = 'Standart Mesajları Dəyişdir';
    public string $description = 'Alive, PM Permit və AFK kimi sistem mesajlarını fərdiləşdirir.';
    public array $commands = [
        'deyisdir' => 'Sistem mesajını dəyişdirir (.deyisdir <pm/alive/afk> <mesaj>)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            if ($userId === 0) {
                $message->editText("⚠️ İstifadəçi tapılmadı.");
                return;
            }

            $key = isset($args[0]) ? strtolower(trim($args[0])) : '';
            $content = trim(implode(' ', array_slice($args, 1)));
            $reply = $message->getReply();

            if ($reply && empty($content)) {
                $content = trim($reply->message ?? '');
            }

            if ($key === 'list') {
                $stmt = $this->db->prepare("SELECT msg_key, msg_content FROM custom_messages WHERE user_id = ?");
                $stmt->execute([$userId]);
                $msgs = $stmt->fetchAll();

                if (empty($msgs)) {
                    $message->editText("ℹ️ Xüsusi dəyişdirilmiş heç bir sistem mesajı tapılmadı (bütün mesajlar standartdır).", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $text = "📝 <b>Dəyişdirilmiş Sistem Mesajları:</b>\n\n";
                foreach ($msgs as $m) {
                    $kEsc = htmlspecialchars(strtoupper($m['msg_key']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $cEsc = htmlspecialchars($m['msg_content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $text .= "• <b>{$kEsc}:</b> <i>{$cEsc}</i>\n\n";
                }
                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($key === 'reset') {
                $resetKey = isset($args[1]) ? strtolower(trim($args[1])) : '';
                if (empty($resetKey)) {
                    $message->editText("⚠️ Sıfırlanacaq açarı qeyd edin: <code>.deyisdir reset &lt;pm/alive/afk&gt;</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $stmt = $this->db->prepare("DELETE FROM custom_messages WHERE user_id = ? AND msg_key = ?");
                $stmt->execute([$userId, $resetKey]);

                $keyEsc = htmlspecialchars(strtoupper($resetKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $message->editText("🔄 <b>{$keyEsc}</b> mesajı sıfırlandı və standart vəziyyətinə qaytarıldı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if (!in_array($key, ['pm', 'alive', 'afk'])) {
                $message->editText(
                    "⚠️ <b>Keçərsiz düymə!</b>\n\n"
                  . "📌 <b>İstifadə qaydası:</b>\n"
                  . "• <code>.deyisdir pm &lt;mesaj&gt;</code> (və ya mesaja cavab verərək)\n"
                  . "• <code>.deyisdir alive &lt;mesaj&gt;</code>\n"
                  . "• <code>.deyisdir afk &lt;mesaj&gt;</code>\n"
                  . "• <code>.deyisdir list</code> (Siyahı)\n"
                  . "• <code>.deyisdir reset &lt;key&gt;</code> (Standarta qaytar)",
                    parseMode: \danog\MadelineProto\ParseMode::HTML
                );
                return;
            }

            // Əgər mesaj yazılmayıbsa -> Standarta (default) sıfırla
            if (empty($content)) {
                $stmt = $this->db->prepare("DELETE FROM custom_messages WHERE user_id = ? AND msg_key = ?");
                $stmt->execute([$userId, $key]);

                $keyEsc = htmlspecialchars(strtoupper($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $message->editText("🔄 <b>{$keyEsc}</b> mesajı sıfırlandı və standart (default) vəziyyətinə qaytarıldı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            // Mesaj yazılıbsa -> Yenilə
            $stmt = $this->db->prepare("INSERT INTO custom_messages (user_id, msg_key, msg_content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE msg_content = ?");
            $stmt->execute([$userId, $key, $content, $content]);

            $keyEsc = htmlspecialchars(strtoupper($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("✅ <b>{$keyEsc}</b> mesajı uğurla dəyişdirildi!", parseMode: \danog\MadelineProto\ParseMode::HTML);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function getUserId(): int {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return (int) ($user['id'] ?? 0);
    }
}
