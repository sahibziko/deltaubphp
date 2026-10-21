<?php
use App\BasePlugin;

class CurrencyPlugin extends BasePlugin {
    public string $name = 'Valyuta';
    public string $description = 'Valyuta məzənnələri.';
    public array $commands = ['kurs' => 'Çevir', 'currency' => 'Məzənnələr'];

    public function onCommand($message, string $command, array $args): void {
        try {
            if ($command === 'currency' || $command === 'kurs') {
                $base = strtoupper($args[0] ?? 'USD');
                $target = strtoupper($args[1] ?? 'AZN');
                $amount = (float)($args[2] ?? 1);
                
                $res = @file_get_contents("https://api.exchangerate-api.com/v4/latest/{$base}");
                if ($res) {
                    $data = json_decode($res, true);
                    if (isset($data['rates'][$target])) {
                        $rate = $data['rates'][$target];
                        $result = $amount * $rate;
                        $message->editText("💱 <b>{$amount} {$base} = {$result} {$target}</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        return;
                    }
                }
                $message->editText("⚠️ Məzənnə tapılmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
