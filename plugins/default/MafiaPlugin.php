<?php
use App\BasePlugin;

class MafiaPlugin extends BasePlugin {
    public string $name = 'Mafia Rolu (Mafia Game)';
    public string $description = 'Mafiada rolunuzu təyin edir və animasiyalı şəkildə göstərir (.mafia).';
    public array $commands = [
        'mafia' => 'Mafiadakı rolunuzu hesablayır (.mafia)'
    ];

    private static array $roller = [
        'Komissar Kattani!🕵️',
        'Vətəndaş!💂',
        'Kamikadze!👩‍💻',
        'Doktor!👩‍⚕️',
        'Məşuqə!💃',
        'Don!🕴️'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            // Başlanğıc mesajı
            $message->editText("🎲 <b>Mafiadakı rolun hesablanır...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            \Amp\delay(1.3);

            // FloodWait-ə düşmədən ruletka animasiyası (4 addım)
            $totalSteps = 16;
            $chosenRole = self::$roller[array_rand(self::$roller)];

            for ($i = 0; $i < $totalSteps; $i++) {
                $tempRole = self::$roller[array_rand(self::$roller)];
                try {
                    $message->editText("Mafiada rolun tapılmaq üzərədir.. 🤫 <b>{$tempRole}</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                } catch (\danog\MadelineProto\FloodWaitException $fw) {
                    break;
                } catch (\Throwable $t) {}

                \Amp\delay(0.4);
            }

            // Yekun nəticə
            $message->editText("Mafiadakı rolun tapıldı✅ : 🤫 <b>{$chosenRole}</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
