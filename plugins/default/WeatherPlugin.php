<?php
use App\BasePlugin;

class WeatherPlugin extends BasePlugin {
    public string $name = 'Hava';
    public string $description = 'Hava haqqında məlumat.';
    public array $commands = ['weather' => 'Hava', 'hava' => 'Hava'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $city = implode(' ', $args);
            if (empty($city)) {
                $message->editText("⚠️ Şəhər adı daxil edin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }
            $url = "https://wttr.in/" . urlencode($city) . "?format=3";
            $res = @file_get_contents($url);
            if ($res) {
                $message->editText("🌤 <b>Hava:</b> " . htmlspecialchars($res), parseMode: \danog\MadelineProto\ParseMode::HTML);
            } else {
                $message->editText("⚠️ Məlumat tapılmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
