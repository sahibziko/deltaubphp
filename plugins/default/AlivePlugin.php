<?php
use App\BasePlugin;

class AlivePlugin extends BasePlugin {
    public string $name = 'Status';
    public string $description = 'Botun statusunu və sistem məlumatlarını göstərir.';
    public array $commands = ['alive' => 'Botun işlək olmasını yoxlayır'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            $customMsg = $this->getCustomMessage($userId, 'alive');

            if (!empty($customMsg)) {
                $uptime = isset(\App\BotHandler::$startTime) ? $this->timeAgo(\App\BotHandler::$startTime) : "Bilinmir";
                $phpVer = phpversion();
                
                $text = str_replace(
                    ['{uptime}', '{php}', '{owner_id}'],
                    [$uptime, $phpVer, (string)$this->ownerId],
                    $customMsg
                );
                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $uptime = isset(\App\BotHandler::$startTime) ? $this->timeAgo(\App\BotHandler::$startTime) : "Bilinmir";
            $phpVer = phpversion();
            
            $text = "🤖 <b>Delta PHP Userbot İşləyir!</b>\n\n"
                  . "👤 <b>Sahib:</b> <code>" . $this->ownerId . "</code>\n"
                  . "⏳ <b>Aktivlik:</b> {$uptime}\n"
                  . "🐘 <b>PHP Versiyası:</b> {$phpVer}\n"
                  . "📡 <b>MadelineProto:</b> Aktiv";
            $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function getUserId(): int {
        if (!$this->db) return 0;
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return (int) ($user['id'] ?? 0);
    }

    private function getCustomMessage(int $userId, string $key): string {
        if (!$this->db || $userId === 0) return '';
        $stmt = $this->db->prepare("SELECT msg_content FROM custom_messages WHERE user_id = ? AND msg_key = ?");
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['msg_content'] : '';
    }
}
