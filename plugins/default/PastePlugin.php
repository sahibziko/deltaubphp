<?php
use App\BasePlugin;

class PastePlugin extends BasePlugin {
    public string $name = 'Paste';
    public string $description = 'Mətni paste servisinə yükləyir.';
    public array $commands = ['paste' => 'Mətni yüklə'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $reply = $message->getReply();
            $text = $reply ? $reply->message : implode(' ', $args);
            
            if (empty($text)) {
                $message->editText("⚠️ Yükləmək üçün mətn daxil edin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }
            
            $ch = curl_init('https://dpaste.org/api/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['content' => $text, 'format' => 'url']);
            $url = curl_exec($ch);
            curl_close($ch);
            
            $message->editText("📄 <b>Paste URL:</b> {$url}", parseMode: \danog\MadelineProto\ParseMode::HTML);
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
