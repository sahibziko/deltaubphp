<?php
use App\BasePlugin;

class NeEdecemPlugin extends BasePlugin {
    public string $name = 'Nə Edəcəm (Gələcək Təxmini)';
    public string $description = 'Gələcəkdə kiminlə, harada və nə edəcəyinizi əyləncəli şəkildə təxmin edir (.needecem).';
    public array $commands = [
        'needecem' => 'Nə edəcəyinizi təxmin edir (.needecem)'
    ];

    private static array $kim = [
        'Epi', 'Aslixan', 'Okaber', 'Elza Seyidcahan', 'Pərviz Bülbülə', 'Polad Aləmdar', 'qonşu',
        'Madonna', 'Joker', 'Harley Quinn', 'Əhməd Cavad', 'Bayram Nurlu', 'Bayram Kürdəxanlı',
        'Rəhim Rəhimli', 'Badclause', 'Üzeyir Mehdizadə', 'Ayı', 'Dəlilər', 'Amerikan zəncisi',
        'Ən yaxın dostun', 'düşmənin', 'Öz-özün', 'Pikaçu', 'Hörümçək adam', 'Rıza baba',
        'satanistlər', 'robot', 'Yad planetlilər', 'Murat boz', 'Monaliza', 'Heykəl', 'əjdaha',
        'heç özün də bilmirsən kim', 'gizli izləyicin', 'fanatın', 'Deadpool', 'Harry Potter', 'Kəlbətin'
    ];

    private static array $harda = [
        'Amerikada', 'Bakıda', 'kafedə', 'Türkiyədə', 'Marsda', 'Adada', 'Pubg-da', 'Diskotekada',
        'evdə', 'suyun altında', 'zon-da', 'dəlixanada', 'bölmədə', 'kitabxanada', 'muzeydə',
        'Avtobusda', 'Moskvada', 'dənizdə', 'toyda', 'Parkda', 'Xəyalında', 'yuxuda',
        'yağışlı havada', 'yayın 80 dərəcə istisində', 'bütün günü', 'hər axşam', 'asta-asta',
        'Almaniyada', 'Tokyoda', 'Parisdə', 'Rusiyada', 'Gürcüstanda', 'yalandan'
    ];

    private static array $ne = [
        'domino oynayacaqsan', 'dağa dırmanacaqsan', 'film izləyəcəksən', 'çay içəcəksən',
        'yemək yeyəcəksən', 'rəqs edəcəksən', 'yolunuzu itirəcəksiz', 'duet edəcəksən',
        'soyğun edəcəksən', 'meyxana oxuyacaqsan', 'selfi çəkəcəksən', 'kofe içəcəksən',
        'səyahət edəcəksən', 'gəmiyə minəcəksən', 'mafia oynayacaqsan', 'dava edəcəksən',
        'küsəcəksən', 'nəsə icad edəcəksən', 'gizlənqaç oynayacaqsan', 'futbol oynayacaqsan',
        'sinemaya gedəcəksən', 'dəlixanadan qaçacaqsan', 'tarix yazacaqsan', 'ayin edəcəksən'
    ];

    private static array $emojis = ['😈', '☠️', '❄️', '🏹', '⚔️', '🤭', '😝', '🥺', '😊'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $message->editText("🔮 <b>ᴇᴅə ʙɪʟəᴄəᴋʟəʀɪɴ ʜᴇsᴀʙʟᴀɴıʀ...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            \Amp\delay(0.8);

            // FloodWait-ə düşmədən maraqlı gərginlik animasiyası (3 addım)
            for ($i = 0; $i < 3; $i++) {
                $e = self::$emojis[array_rand(self::$emojis)];
                try {
                    $message->editText("Nə edəcəksən, öyrənməyə hazırsan?.. {$e}");
                } catch (\danog\MadelineProto\FloodWaitException $fw) {
                    break;
                } catch (\Throwable $t) {}

                \Amp\delay(0.7);
            }

            $k = self::$kim[array_rand(self::$kim)];
            $h = self::$harda[array_rand(self::$harda)];
            $n = self::$ne[array_rand(self::$ne)];

            $result = "🔮 <b>𝙴𝚍ə𝚌ə𝚢𝚒𝚗 ş𝚎𝚢 𝚑𝚎𝚜𝚊𝚋𝚕𝚊𝚗𝚍ı :</b>\n\n" .
                      "Sən əlbəttə bir gün, <b>{$k}</b> ilə <b>{$h}</b> <b>{$n}</b>. 😉";

            $message->editText($result, parseMode: \danog\MadelineProto\ParseMode::HTML);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
