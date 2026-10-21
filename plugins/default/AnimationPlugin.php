<?php
use App\BasePlugin;

class AnimationPlugin extends BasePlugin {
    public string $name = 'Animasiyalar (Animations)';
    public string $description = 'Mesaj animasiyaları (.salam, .aleykum, .etiraf, .he / .hə, .yo).';
    public array $commands = [
        'salam' => 'Salam vermək üçün animasiya (.salam)',
        'aleykum' => 'Salam almaq üçün animasiya (.aleykum)',
        'etiraf' => 'Sevgi etirafı animasiyası (.etiraf)',
        'he' => 'Animasiyalı "həəə" cavabı (.he və ya .hə)',
        'hə' => 'Animasiyalı "həəə" cavabı (.hə)',
        'yo' => 'Animasiyalı "yooo" cavabı (.yo)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            // 1. .salam - Salam vermək üçün
            if ($command === 'salam') {
                $message->editText("Salam Əlöyküm..🐺");
                $frames = [
                    "S",
                    "SA",
                    "SAL",
                    "SALA",
                    "SALAM",
                    "<b>Salam verdim alana</b>",
                    "<b>Panyatqası olana</b>",
                    "🦅 Salammm",
                    "Necəsüz 🤔",
                    "Mən gəldim 🤗",
                    "<b>Xoş Gəldim</b>",
                    "<b>Salam 🔥</b>"
                ];
                $this->runAnimation($message, $frames, 0.6);
                return;
            }

            // 2. .aleykum - Salam almaq üçün
            if ($command === 'aleykum') {
                $message->editText("Əlöyküm Salam..🐺");
                $frames = [
                    "A",
                    "AL",
                    "ALE",
                    "ALEY",
                    "ALEYK",
                    "ALEYKU",
                    "ALEYKUM",
                    "🦅 Aleykum Salam",
                    "Necəsən 🤔",
                    "<b>Xoş Gəldin</b>",
                    "<b>Həmişə sən gələsan</b>",
                    "<b>Aleykum Salam🔥</b>"
                ];
                $this->runAnimation($message, $frames, 0.6);
                return;
            }

            // 3. .etiraf - Sevgi etirafı
            if ($command === 'etiraf') {
                $message->editText("Kraliçam ❤️");
                $frames = [
                    "❤️ Sən ❤️",
                    "❤️ Səni ❤️",
                    "❤️ Səni daim ❤️",
                    "❤️ Səni daim yanımda ❤️",
                    "❤️ Səni daim yanımda olmağını ❤️",
                    "❤️ Səni daim yanımda olmağını istəyirəm ❤️",
                    "💖 <b>SƏNİ ÇOX SEVİRƏM ❤️</b>"
                ];
                $this->runAnimation($message, $frames, 0.9);
                return;
            }

            // 4. .he / .hə - Uzanaraq gedən "həəəəə"
            if ($command === 'he' || $command === 'hə') {
                $t = "hə";
                $frames = [$t];
                for ($j = 0; $j < 6; $j++) {
                    $t = mb_substr($t, 0, -1, 'UTF-8') . "əəə";
                    $frames[] = $t;
                }
                $this->runAnimation($message, $frames, 0.7, false);
                return;
            }

            // 5. .yo - Uzanaraq gedən "yooooo"
            if ($command === 'yo') {
                $t = "yo";
                $frames = [$t];
                for ($j = 0; $j < 6; $j++) {
                    $t = substr($t, 0, -1) . "ooo";
                    $frames[] = $t;
                }
                $this->runAnimation($message, $frames, 0.7, false);
                return;
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function runAnimation($message, array $frames, float $delaySeconds, bool $useHtml = true): void {
        foreach ($frames as $frame) {
            \Amp\delay($delaySeconds);
            try {
                if ($useHtml) {
                    $message->editText($frame, parseMode: \danog\MadelineProto\ParseMode::HTML);
                } else {
                    $message->editText($frame);
                }
            } catch (\danog\MadelineProto\FloodWaitException $fw) {
                break;
            } catch (\Throwable $t) {}
        }
    }
}
