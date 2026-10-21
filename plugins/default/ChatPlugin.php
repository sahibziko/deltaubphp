<?php
use App\BasePlugin;

class ChatPlugin extends BasePlugin {
    public string $name = 'Çat Alətləri';
    public string $description = 'Çat haqqında məlumatlar.';
    public array $commands = [
        'chatinfo' => 'Çat haqqında məlumat',
        'adminlist' => 'Qrup adminlərinin siyahısı'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = $message->chatId;
            if ($command === 'chatinfo') {
                $info = $this->api->getFullInfo($chatId);
                $message->editText("ℹ️ <b>Çat Məlumatı:</b>\n<code>" . json_encode($info['Chat'] ?? $info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'adminlist') {
                $admins = $this->api->channels->getParticipants(channel: $chatId, filter: ['_' => 'channelParticipantsAdmins'], offset: 0, limit: 100);
                $users = [];
                foreach ($admins['users'] ?? [] as $u) {
                    $uid = (int) ($u['id'] ?? 0);
                    if ($uid > 0) $users[$uid] = $u;
                }

                $text = "👮‍♂️ <b>Qrup Adminləri:</b>\n\n";
                $count = 0;
                foreach ($admins['participants'] ?? [] as $admin) {
                    $uid = (int) ($admin['user_id'] ?? $admin['peer']['user_id'] ?? 0);
                    if ($uid <= 0) continue;
                    $count++;
                    $uInfo = isset($users[$uid]) ? ['User' => $users[$uid]] : null;
                    $mention = $this->formatUserMention($uid, $uInfo);
                    $rank = !empty($admin['rank']) ? " — <i>" . htmlspecialchars($admin['rank'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</i>" : "";
                    $text .= "{$count}. {$mention}{$rank}\n";
                }

                if ($count === 0) {
                    $text .= "<i>Admin tapılmadı və ya qrup deyil.</i>";
                }

                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) {
            $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
