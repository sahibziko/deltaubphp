<?php
use App\BasePlugin;

class FontPlugin extends BasePlugin {
    public string $name = 'Şriftlər (Fonts)';
    public string $description = 'Yazıları 11 fərqli estetik və xüsusi şriftə çevirir (.font1 - .font11 və ya .font <1-11>).';
    public array $commands = [
        'font1' => 'Şrift 1: 卂 乃 匚...',
        'font2' => 'Şrift 2: 🅐 🅑 🅒...',
        'font3' => 'Şrift 3: 𝔄 𝔅 ℭ...',
        'font4' => 'Şrift 4: ⲁ ⲃ ⲥ...',
        'font5' => 'Şrift 5: 🄰 🄱 🄲...',
        'font6' => 'Şrift 6: ᵃ ᵇ ᶜ...',
        'font7' => 'Şrift 7: ᴀ ʙ ᴄ...',
        'font8' => 'Şrift 8: 𝐀 𝐁 𝐂...',
        'font9' => 'Şrift 9: 𝓐 𝓑 𝓒...',
        'font10' => 'Şrift 10: 𝘼 𝘽 𝘾...',
        'font11' => 'Şrift 11: 𝙰 𝙱 𝙲...',
        'font' => 'Seçilən şriftə çevirir (.font <1-11> <mətn>)'
    ];

    private static array $fonts = [
        1 => ["卂","乃","匚","刀","乇","下","厶","卄","工","丁","长","乚","从","𠘨","口","尸","㔿","尺","丂","丅","凵","リ","山","乂","丫","乙"],
        2 => ["🅐","🅑","🅒","🅓","🅔","🅕","🅖","🅗","🅘","🅙","🅚","🅛","🅜","🅝","🅞","🅟","🅠","🅡","🅢","🅣","🅤","🅥","🅦","🅧","🅨","🅩"],
        3 => ["𝔄","𝔅","ℭ","𝔇","𝔈","𝔉","𝔊","ℌ","ℑ","𝔍","𝔎","𝔏","𝔐","𝔑","𝔒","𝔓","𝔔","ℜ","𝔖","𝔗","𝔘","𝔙","𝔚","𝔛","𝔜","ℨ"],
        4 => ["ⲁ","ⲃ","ⲥ","ⲇ","ⲉ","ϝ","ⳋ","ⲏ","ⲓ","ⳗ","ⲕ","ⳑ","ⲙ","ⲛ","ⲟ","ⲣ","q","ʀ","ⲋ","ⲧ","υ","ⳳ","ⲱ","ⲭ","ⲩ","ⲍ"],
        5 => ["🄰","🄱","🄲","🄳","🄴","🄵","🄶","🄷","🄸","🄹","🄺","🄻","🄼","🄽","🄾","🄿","🅀","🅁","🅂","🅃","🅄","🅅","🅆","🅇","🅈","🅉"],
        6 => ["ᵃ","ᵇ","ᶜ","ᵈ","ᵉ","ᶠ","ᵍ","ʰ","ᶦ","ʲ","ᵏ","ᶫ","ᵐ","ᶰ","ᵒ","ᵖ","ᶞ","ʳ","ˢ","ᵗ","ᵘ","ᵛ","ʷ","ˣ","ʸ","ᶻ"],
        7 => ["ᴀ","ʙ","ᴄ","ᴅ","ᴇ","ғ","ɢ","ʜ","ɪ","ᴊ","ᴋ","ʟ","ᴍ","ɴ","ᴏ","ᴘ","ǫ","ʀ","s","ᴛ","ᴜ","ᴠ","ᴡ","x","ʏ","ᴢ"],
        8 => ["𝐀","𝐁","𝐂","𝐃","𝐄","𝐅","𝐆","𝐇","𝐈","𝐉","𝐊","𝐋","𝐌","𝐍","𝐎","𝐏","𝐐","𝐑","𝐒","𝐓","𝐔","𝐕","𝐖","𝐗","𝐘","𝐙"],
        9 => ["𝓐","𝓑","𝓒","𝓓","𝓔","𝓕","𝓖","𝓗","𝓘","𝓙","𝓚","𝓛","𝓜","𝓝","𝓞","𝓟","𝓠","𝓡","𝓢","𝓣","𝓤","𝓥","𝓦","𝓧","𝓨","𝓩"],
        10 => ["𝘼","𝘽","𝘾","𝘿","𝙀","𝙁","𝙂","𝙃","𝙄","𝙅","𝙆","𝙇","𝙈","𝙉","𝙊","𝙋","𝙌","𝙍","𝙎","𝙏","𝙐","𝙑","𝙒","𝙓","𝙔","𝙕"],
        11 => ["𝙰","𝙱","𝙲","𝙳","𝙴","𝙵","𝙶","𝙷","𝙸","𝙹","𝙺","𝙻","𝙼","𝙽","𝙾","𝙿","𝚀","𝚁","𝚂","𝚃","𝚄","𝚅","𝚆","𝚇","𝚈","𝚉"]
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $fontIndex = 1;
            $text = '';

            if (preg_match('/^font([1-9]|1[01])$/', $command, $m)) {
                $fontIndex = (int) $m[1];
                $text = trim(implode(' ', $args));
            } elseif ($command === 'font') {
                if (!empty($args[0]) && is_numeric($args[0]) && (int)$args[0] >= 1 && (int)$args[0] <= 11) {
                    $fontIndex = (int) array_shift($args);
                    $text = trim(implode(' ', $args));
                } else {
                    $text = trim(implode(' ', $args));
                }
            }

            // Əgər parametr kimi mətn yazılmayıbsa, cavablanan (reply) mesaja baxırıq
            if ($text === '') {
                $reply = $message->getReply();
                if ($reply && !empty($reply->message)) {
                    $text = $reply->message;
                }
            }

            if ($text === '') {
                $message->editText("⚠️ <b>Mətn qeyd edin və ya bir mesaja cavab olaraq yazın!</b>\n<i>Nümunə: <code>.font{$fontIndex} Salam dünya</code></i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $converted = $this->convertFont($text, $fontIndex);
            $message->editText($converted);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function convertFont(string $text, int $fontIndex): string {
        if (!isset(self::$fonts[$fontIndex])) {
            $fontIndex = 1;
        }

        $fontChars = self::$fonts[$fontIndex];
        $alphabet = range('a', 'z');
        $map = [];

        foreach ($alphabet as $i => $char) {
            $replacement = $fontChars[$i] ?? $char;
            $map[$char] = $replacement;
            $map[strtoupper($char)] = $replacement;
        }

        return strtr($text, $map);
    }
}
