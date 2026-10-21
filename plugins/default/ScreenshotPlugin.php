<?php
use App\BasePlugin;

class ScreenshotPlugin extends BasePlugin {
    public string $name = 'Ekran Görüntüsü';
    public string $description = 'Veb səhifələrin ekran görüntüsünü alır.';
    public array $commands = ['ss' => 'SS al'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $url = $args[0] ?? '';
            if (empty($url)) {
                $message->editText("⚠️ URL daxil edin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }
            $img = "https://image.thum.io/get/{$url}";
            $message->editText("📸 <b>Ekran görüntüsü:</b> <a href='{$img}'>Yüklə</a>", parseMode: \danog\MadelineProto\ParseMode::HTML);
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
