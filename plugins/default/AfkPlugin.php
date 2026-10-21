<?php
use App\BasePlugin;

class AfkPlugin extends BasePlugin {
    public string $name = 'AFK';
    public string $description = 'AFK rejimini aktivləşdirir.';
    public array $commands = ['afk' => 'AFK olur', 'unafk' => 'AFK-dan çıxır'];
    public bool $handlesMessages = true;

    public static string $defaultMessage = "Bir neçə dəqiqə içində gələcəyəm. Ancaq gəlməsəm...\ndaha çox gözlə.";

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            if ($command === 'afk') {
                $reason = empty($args) ? "" : trim(implode(' ', $args));
                $now = date('Y-m-d H:i:s');
                
                $stmt = $this->db->prepare("INSERT INTO afk_status (user_id, is_afk, reason, afk_since) VALUES (?, 1, ?, ?) ON DUPLICATE KEY UPDATE is_afk=1, reason=?, afk_since=?");
                $stmt->execute([$userId, $reason, $now, $reason, $now]);
                
                $customAfk = $this->getCustomMessage($userId, 'afk');
                $baseMsg = !empty($customAfk) ? $customAfk : self::$defaultMessage;

                $text = "😴 <b>AFK Rejimi Aktivdir</b>\n\n" . $baseMsg;
                if (!empty($reason)) {
                    $text .= "\n\nSəbəb: <i>" . htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</i>";
                }

                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'unafk') {
                $afk = $this->getAfkStatus($userId);
                $stmt = $this->db->prepare("UPDATE afk_status SET is_afk=0 WHERE user_id=?");
                $stmt->execute([$userId]);
                
                $duration = '';
                if ($afk && !empty($afk['afk_since'])) {
                    $duration = "\n⏱ AFK müddəti: " . $this->timeAgo(strtotime($afk['afk_since']));
                }
                $message->editText("😃 <b>Artıq AFK deyilsiniz!</b>{$duration}", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) {
            $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
    }

    public function onMessage($message): void {
        try {
            $senderId = $message->senderId ?? 0;
            if ($senderId == $this->ownerId) {
                return;
            }

            $userId = $this->getUserId();
            $afk = $this->getAfkStatus($userId);
            
            if (!$afk || !$afk['is_afk']) {
                return;
            }

            $shouldReply = false;
            $chatId = $message->chatId ?? 0;
            
            if ($chatId > 0) {
                $shouldReply = true;
            }
            
            if (!$shouldReply) {
                $self = $this->api->getSelf();
                $username = $self['username'] ?? '';
                if ($username && stripos($message->message ?? '', '@' . $username) !== false) {
                    $shouldReply = true;
                }
            }
            
            if (!$shouldReply) {
                try {
                    $reply = $message->getReply();
                    if ($reply && ($reply->senderId ?? 0) == $this->ownerId) {
                        $shouldReply = true;
                    }
                } catch (\Throwable $e) {}
            }

            if ($shouldReply) {
                $ago = !empty($afk['afk_since']) ? $this->timeAgo(strtotime($afk['afk_since'])) : 'az öncə';
                $customAfk = $this->getCustomMessage($userId, 'afk');
                $baseMsg = !empty($customAfk) ? $customAfk : self::$defaultMessage;

                $replyText = "😴 Hazırda <b>AFK</b>-yam.\n\n" . $baseMsg;
                if (!empty($afk['reason'])) {
                    $replyText .= "\n\nSəbəb: <i>" . htmlspecialchars($afk['reason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</i>";
                }
                $replyText .= "\n⏱ Müddət: {$ago}";

                $message->reply(
                    $replyText,
                    parseMode: \danog\MadelineProto\ParseMode::HTML
                );
            }
        } catch (\Throwable $e) {}
    }

    private function getUserId(): int {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return (int) ($user['id'] ?? 0);
    }

    private function getAfkStatus(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT is_afk, reason, afk_since FROM afk_status WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function getCustomMessage(int $userId, string $key): string {
        if (!$this->db || $userId === 0) return '';
        $stmt = $this->db->prepare("SELECT msg_content FROM custom_messages WHERE user_id = ? AND msg_key = ?");
        $stmt->execute([$userId, $key]);
        $row = $stmt->fetch();
        return $row ? $row['msg_content'] : '';
    }
}
