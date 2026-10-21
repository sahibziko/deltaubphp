<?php
use App\BasePlugin;
use App\Database;

class TranslatePlugin extends BasePlugin {
    public string $name = 'Tərcümə';
    public string $description = 'Mətnləri tərcümə edir.';
    public array $commands = ['tr' => 'Tərcümə et'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $lang = $args[0] ?? 'az';
            $text = implode(' ', array_slice($args, 1));
            
            $reply = $message->getReply();
            if ($reply && empty($text)) {
                $text = $reply->message;
            }
            
            if (empty($text)) {
                $message->editText("⚠️ Tərcümə üçün mətn daxil edin.");
                return;
            }
            
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=" . urlencode($lang) . "&dt=t&q=" . urlencode($text);
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            
            $translated = '';
            foreach ($data[0] as $t) {
                $translated .= $t[0];
            }
            
            $message->editText("🌍 <b>Tərcümə ({$lang}):</b>\n<code>{$translated}</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
