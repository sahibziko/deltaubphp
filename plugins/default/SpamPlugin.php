<?php
use App\BasePlugin;

class SpamPlugin extends BasePlugin {
    public string $name = 'Spam';
    public string $description = 'Mesajları spam edir və ya silir.';
    public array $commands = [
        'spam' => 'Spam göndərir (.spam <say> <mesaj>)',
        'delspam' => 'Göndərilən spam mesajlarını silir (.delspam <say>)',
        'spamstop' => 'Davam edən spamı dayandırır (.spamstop)'
    ];

    public static array $runningSpams = [];

    public static function stopForUser(int $ownerId): bool {
        if (!empty(self::$runningSpams[$ownerId])) {
            self::$runningSpams[$ownerId] = false;
            return true;
        }
        return false;
    }

    public function onCommand($message, string $command, array $args): void {
        try {
            $ownerId = $this->ownerId > 0 ? $this->ownerId : (int)($message->senderId ?? 0);

            if ($command === 'spamstop') {
                self::stopForUser($ownerId);
                $message->editText("🛑 <b>Spam dayandırıldı!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'spam') {
                $count = min(100, max(1, (int)($args[0] ?? 1)));
                $text = implode(' ', array_slice($args, 1));
                if (empty($text)) {
                    $reply = $message->getReply();
                    if ($reply && !empty($reply->message)) {
                        $text = $reply->message;
                    }
                }
                if (empty($text)) {
                    $message->editText("⚠️ <b>İstifadə:</b> <code>.spam &lt;say&gt; &lt;mesaj&gt;</code> və ya mesaja cavab verərək <code>.spam &lt;say&gt;</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $chatId = $message->chatId;
                try {
                    $message->delete(revoke: true);
                } catch (\Throwable $t) {}

                self::$runningSpams[$ownerId] = true;

                \Amp\async(function () use ($chatId, $count, $text, $ownerId) {
                    for ($i = 0; $i < $count; $i++) {
                        if (empty(self::$runningSpams[$ownerId])) {
                            break;
                        }
                        try {
                            $this->api->messages->sendMessage(peer: $chatId, message: $text);
                        } catch (\Throwable $e) {}
                        \Amp\delay(0.2);
                    }
                    self::$runningSpams[$ownerId] = false;
                });

            } elseif ($command === 'delspam') {
                $count = min(100, max(1, (int)($args[0] ?? 10)));
                $history = $this->api->messages->getHistory(peer: $message->chatId, limit: $count);
                $ids = [];
                foreach ($history['messages'] ?? [] as $msg) {
                    if (isset($msg['out']) && $msg['out']) {
                        $ids[] = $msg['id'];
                    }
                }
                if (!empty($ids)) {
                    $this->api->messages->deleteMessages(id: $ids, revoke: true);
                }
            }
        } catch (\Throwable $e) {
            $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
