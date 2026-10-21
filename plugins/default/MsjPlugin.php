<?php
use App\BasePlugin;

class MsjPlugin extends BasePlugin {
    public string $name = 'Barmaq Çərçivə (Msj Animation)';
    public string $description = 'Mesajınızı emojili barmaqlar çərçivəsində animasiya ilə göstərir (.msj <mətn>).';
    public array $commands = [
        'msj' => 'Mesajı barmaqlarla göstərir (.msj <yazı>)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $inputStr = trim(implode(' ', $args));

            if ($inputStr === '') {
                $reply = $message->getReply();
                if ($reply && !empty($reply->message)) {
                    $inputStr = trim($reply->message);
                }
            }

            if ($inputStr === '') {
                $message->editText("⚠️ <b>Mətn qeyd edin və ya bir mesaja cavab olaraq yazın!</b>\n<i>Nümunə: <code>.msj Salam</code></i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $frames = [
                "👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿\n👉🏿👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👈🏿\n👉🏿👉🏾👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👈🏾👈🏿",
                "👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿\n👉🏿👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👈🏿\n👉🏿👉🏾👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏼👇🏼👇🏼👇🏼👇🏼👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏻👇🏻👇🏻👇🏻👇🏻👈🏽👈🏾👈🏿",
                "👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿\n👉🏿👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👈🏿\n👉🏿👉🏾👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏼👇🏼👇🏼👇🏼👇🏼👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏻👇🏻👇🏻👇🏻👇🏻👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽 " . $inputStr . " 👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👆🏻👆🏻👆🏻👆🏻👆🏻👈🏽👈🏾👈🏿",
                "👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿\n👉🏿👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👈🏿\n👉🏿👉🏾👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏼👇🏼👇🏼👇🏼👇🏼👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏻👇🏻👇🏻👇🏻👇🏻👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽 " . $inputStr . " 👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👆🏻👆🏻👆🏻👆🏻👆🏻👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👆🏼👆🏼👆🏼👆🏼👆🏼👈🏽👈🏾👈🏿\n👉🏿👉🏾👆🏽👆🏽👆🏽👆🏽👆🏽👆🏽👆🏽👈🏾👈🏿",
                "👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿👇🏿\n👉🏿👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👇🏾👈🏿\n👉🏿👉🏾👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👇🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏼👇🏼👇🏼👇🏼👇🏼👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👇🏻👇🏻👇🏻👇🏻👇🏻👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽 " . $inputStr . " 👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👆🏻👆🏻👆🏻👆🏻👆🏻👈🏽👈🏾👈🏿\n👉🏿👉🏾👉🏽👆🏼👆🏼👆🏼👆🏼👆🏼👈🏽👈🏾👈🏿\n👉🏿👉🏾👆🏽👆🏽👆🏽👆🏽👆🏽👆🏽👆🏽👈🏾👈🏿\n👉🏿👆🏾👆🏾👆🏾👆🏾👆🏾👆🏾👆🏾👆🏾👆🏾👈🏿\n👉🏿👆🏿👆🏿👆🏿👆🏿👆🏿👆🏿👆🏿👆🏿👆🏿👈🏿"
            ];

            foreach ($frames as $frame) {
                try {
                    $message->editText($frame);
                } catch (\danog\MadelineProto\FloodWaitException $fw) {
                    break;
                } catch (\Throwable $t) {}

                \Amp\delay(0.6);
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
