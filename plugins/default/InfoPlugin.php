<?php
use App\BasePlugin;

class InfoPlugin extends BasePlugin {
    public string $name = 'Məlumat';
    public string $description = 'İstifadəçi və qrup haqqında etibarlı məlumat verir.';
    public array $commands = [
        'info' => 'Məlumat göstərir',
        'whois' => 'İstifadəçi haqqında ətraflı məlumat'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $peer = $message->chatId;
            $reply = $message->getReply();
            
            if ($reply && isset($reply->senderId)) {
                $peer = $reply->senderId;
            } elseif (!empty($args)) {
                $peer = trim($args[0]);
            }
            
            $info = $this->api->getFullInfo($peer);
            $user = $info['User'] ?? [];
            $full = $info['full'] ?? [];

            if (!empty($user)) {
                $uid = $user['id'] ?? 0;
                $first = htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $last = htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $uname = !empty($user['username']) ? '@' . htmlspecialchars($user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Yoxdur';
                $bio = !empty($full['about']) ? htmlspecialchars($full['about'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Yoxdur';
                $isBot = !empty($user['bot']) ? 'Bəli 🤖' : 'Xeyr 👤';
                $dc = $user['photo']['dc_id'] ?? 'Bilinmir';

                $text = "👤 <b>İstifadəçi Məlumatı:</b>\n\n"
                      . "🆔 <b>ID:</b> <code>{$uid}</code>\n"
                      . "👤 <b>Ad:</b> {$first} {$last}\n"
                      . "🌐 <b>Username:</b> {$uname}\n"
                      . "🤖 <b>Bot:</b> {$isBot}\n"
                      . "📡 <b>Data Center:</b> DC {$dc}\n"
                      . "📝 <b>Bio:</b> <i>{$bio}</i>";
            } else {
                $chatTitle = htmlspecialchars($info['Chat']['title'] ?? 'Söhbət', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $chatId = $info['Chat']['id'] ?? $peer;
                $text = "💬 <b>Söhbət Məlumatı:</b>\n\n"
                      . "🆔 <b>ID:</b> <code>{$chatId}</code>\n"
                      . "📌 <b>Başlıq:</b> {$chatTitle}";
            }

            $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
