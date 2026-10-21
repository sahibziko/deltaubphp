<?php
use App\BasePlugin;
use danog\MadelineProto\ParseMode;

class OzelPlugin extends BasePlugin {
    public string $name = 'Özəl Mesaj (Direct Message)';
    public string $description = 'Qrupda və ya hər hansı çatda olarkən başqa bir şəxsin özəlinə mesaj göndərir (.ozel).';
    public array $commands = [
        'ozel' => 'Qeyd olunan şəxsə birbaşa mesaj göndərir (.ozel <id/@istifadeci> <mesaj> və ya cavablayaraq)',
        'dm' => 'Qeyd olunan şəxsə birbaşa mesaj göndərir (.dm <id/@istifadeci> <mesaj>)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            if (empty($args)) {
                $message->editText(
                    "⚠️ <b>İstifadə qaydası:</b>\n" .
                    "1. <code>.ozel @istifadeci Salam necəsən?</code>\n" .
                    "2. Mesaja cavab verərək: <code>.ozel @istifadeci</code>",
                    parseMode: ParseMode::HTML
                );
                return;
            }

            // 1. Hədəf istifadəçini müəyyən edirik
            $targetRaw = trim(array_shift($args));
            $targetPeer = $targetRaw;

            if (is_numeric($targetRaw)) {
                $targetPeer = (int) $targetRaw;
            } elseif (str_contains($targetRaw, 't.me/')) {
                $parts = explode('/', trim($targetRaw, '/'));
                $targetPeer = '@' . end($parts);
            } elseif (!str_starts_with($targetRaw, '@') && !is_numeric($targetRaw)) {
                $targetPeer = '@' . $targetRaw;
            }

            // Peer-in mövcudluğunu yoxlayırıq
            try {
                $info = $this->api->getInfo($targetPeer);
                $targetPeer = $info['bot_api_id'] ?? $info['User']['id'] ?? $targetPeer;
            } catch (\Throwable $t) {}

            $textMsg = trim(implode(' ', $args));
            $reply = $message->getReply();
            $sent = false;

            // 2. Mesaja cavab verilibsə yönləndiririk və ya kopyalayırıq
            if ($reply) {
                try {
                    $this->api->messages->forwardMessages(
                        from_peer: $message->chatId,
                        id: [(int)$reply->id],
                        to_peer: $targetPeer
                    );
                    $sent = true;
                } catch (\Throwable $tFwd) {
                    if (!empty($reply->message)) {
                        $this->api->sendMessage($targetPeer, $reply->message);
                        $sent = true;
                    }
                }
            }

            // 3. Əlavə mətn yazılıbsa onu da göndəririk
            if ($textMsg !== '') {
                $this->api->sendMessage($targetPeer, $textMsg);
                $sent = true;
            }

            if ($sent) {
                $message->editText("<code>Mesaj göndərildi ✔️</code>", parseMode: ParseMode::HTML);
            } else {
                $message->editText("⚠️ <b>Mesajı göndərmək istədiyiniz bir şəxs və ya mətn seçin!</b>", parseMode: ParseMode::HTML);
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ <b>Xəta:</b> " . $err, parseMode: ParseMode::HTML);
        }
    }
}
