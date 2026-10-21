<?php
use App\BasePlugin;

class CarxPlugin extends BasePlugin {
    public string $name = 'Çarx (Slot Machine)';
    public string $description = 'Çarxı fırladaraq şansınızı yoxlayır (.carx).';
    public array $commands = [
        'carx' => 'Çarxı fırladır (.carx)'
    ];

    private static array $symbols = ['🌨️', '⛷️', '❄️', '⛄', '⚔️', '🎇', '😝', '🎄', '🎅'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $message->editText("🎰 <b>Çᴀʀx Çᴇᴠʀɪʟɪʀ...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            \Amp\delay(0.8);

            // FloodWait-dən qorunan 4 addımlı fırlanma animasiyası
            for ($step = 0; $step < 4; $step++) {
                $s = self::$symbols[array_rand(self::$symbols)];
                $frame = "🎰 <b>Çarx fırlanır :</b>\n————————\n" .
                         "| [{$s}][{$s}][{$s}] |\n" .
                         "| [{$s}][{$s}][{$s}] |\n" .
                         "| [{$s}][{$s}][{$s}] |\n" .
                         "————————";

                try {
                    $message->editText($frame, parseMode: \danog\MadelineProto\ParseMode::HTML);
                } catch (\danog\MadelineProto\FloodWaitException $fw) {
                    break;
                } catch (\Throwable $t) {}

                \Amp\delay(0.7);
            }

            // Təsadüfi 3x3 nəticə
            $g = [];
            for ($i = 0; $i < 9; $i++) {
                $g[$i] = self::$symbols[array_rand(self::$symbols)];
            }

            $result = "🎰 <b>Ç𝚊𝚛𝚡 𝚍𝚊𝚢𝚊𝚗𝚍ı :</b>\n————————\n" .
                      "| [{$g[0]}][{$g[1]}][{$g[2]}] |\n" .
                      "| [{$g[3]}][{$g[4]}][{$g[5]}] |\n" .
                      "| [{$g[6]}][{$g[7]}][{$g[8]}] |\n" .
                      "————————";

            // Əgər orta cərgə eynidirsə Jackpot!
            if ($g[3] === $g[4] && $g[4] === $g[5]) {
                $result .= "\n🔥 <b>JACKPOT! Təbriklər, uddunuz!</b> 🎉";
            }

            $message->editText($result, parseMode: \danog\MadelineProto\ParseMode::HTML);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
