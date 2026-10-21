<?php
use App\BasePlugin;
use App\Database;

class PingPlugin extends BasePlugin {
    public string $name = 'Ping';
    public string $description = 'Botun gecikməsini yoxlayır.';
    public array $commands = ['ping' => 'Pingi ölçür'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $start = microtime(true);
            $message->editText("🏓 Ping ölçülür...", parseMode: \danog\MadelineProto\ParseMode::HTML);
            $end = microtime(true);
            $ping = round(($end - $start) * 1000);
            $message->editText("🏓 <b>Pong!</b> {$ping}ms", parseMode: \danog\MadelineProto\ParseMode::HTML);
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
