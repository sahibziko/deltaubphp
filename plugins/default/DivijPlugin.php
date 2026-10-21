<?php
use App\BasePlugin;

class DivijPlugin extends BasePlugin {
    public string $name = 'Divij (Tanışlıq və Sözlər)';
    public string $description = 'Təsadüfi romantik, gülməli və divij sözləri göndərir (.divij).';
    public array $commands = [
        'divij' => 'Təsadüfi divij sözü göndərir (.divij)'
    ];

    private static array $phrases = [
        'Sən yaramdan öp keçməzsə o yaramın Bezpantovluğudur..',
        'Ben sana стрелка olaraq kalbimi atdım',
        'Ürəyim sıxılır əzilmirsən ki? :)',
        'Sana sroçnu ihtiyyacım var 😒',
        'Salam xanım neçə ilin oğrusuyam sizin kimi qəlb oğurluya bilməmişəm. Necəsiz 😄',
        'Bu gün hiç tadım duzum yok istiyirsən dişlə bax 🙈',
        'Salam xanım yazdığınızı gördüm qatılıram sizə, bəlkə bu mövzunu Whatsapp-da Müzakirə edək?',
        'Otval verməsəydin kiçik bir pokaleniyamız ola bilirdi.',
        'Sənin ürəyin orta qapından sərnişin götürən Avtobus qədər doludur 😒',
        'Elə bil sizi hardasa görmüşəm üzünüz çox tanışdı e',
        'Salam. Mən balaca bir mesajam 🤗. Gəldim deyim ki, Sahibim sizinlə tanış olmaq istəyir. İndi mən geri qayıtmalıyam gedim sahibimə nə deyim? 😊',
        'Salam xanım. Mənə 10 ardıcıl rəqəm deyə bilərsiniz ki zəng etsəm sizin nömrəniz çıxsın',
        'Salam xanım yolu itirmişəm ürəyinizə gedən yolu göstərə bilərsiz?',
        'Salam xanım dövlət olsam gözəlliyinizə görə sizdən vergi alardım.',
        'Salam göndərirəm güldən, Çiçəkdən cavab gözləyirəm gözəl Mələkdən 🤗🤭',
        'Salam xanım icazə olar mənim Eşq adlı təyyarəm sizin Sevgi adlı hava limanına ensin.',
        'Salam xanım Cənnətdən zəng vurmuşdular mələk qaçıb o sizsiniz? 😻',
        'Salam xanım Qəbələdə 7 gözəl Şəlaləsinin yanında şəkil çəkdirmisiz qərar verdim ki, o artıq 8 gözəl şəlaləsi olmalıdır. Çox gözəlsiz )',
        'Salam xanım bilmirsiz Qəlbinizə neçə nömrəli avtobus gedir?',
        '20 mələk var, 10-u yatır, 9-u uçur və qalır biri və ən gözəli bu mesajı oxuyaraq gülümsəyir. Salam xanım )',
        'Sən xristian ölkəsində qalan müsəlman, sən isə mənim həsrət qaldığım azan səsi',
        'Hamını sevə bilərdim sevməyə səndən başlamasaydım )',
        'Bu gün həkimə getmişdim. Böyrəyimdə daş, ciyərimdə qum, qəlbimdə səni tapdılar.',
        'Sənə gülüm desəm olmaz solub gedərsən. Sənə mələyim desəm olmaz uçub gedərsən. Səni sevirəm desəm nə deyərsən?',
        'Dünyanı gözəl olduğu üçün deyil, içində sən olduğun üçün sevirəm.',
        'Bugünki tarixə diqqətlə bax. İlk tanış olduğumuz gün kimi yadda qalsın :)',
        'Çox yorulmuş olmalısan, bütün günü ancaq beynimdə gəzmisən )',
        'İlk görüşdə sevgiyə inanırsan, yoxsa gedim, təzdən gəlim? )',
        'Gözlərin Ferrarimin rəngindədir',
        'Telefonumun parlaqlığı artdı, mənə gülümsədin?',
        'Bugün evdən çıxmamısan deyəsən, günəş çıxmıyıb :(',
        'Qələmin var? Keçmişimi silib, gələcəyimizi yazmaq istəyirəm',
        'Deyəsən, muzeydəyəm. Sən bir sənət əsərisən',
        'Salam verirəm gözələ, çağırıram özələ 🙈',
        'Cənnətdən düşəndə, çox ağrıdın?',
        'Gəmimin kapitanı ol deyəcəm, amma, onsuz hər yol səndən keçir',
        'Mən Dünya, sən Ay. Hərgün başımda fırlanırsan',
        'Axı bu qədər gözəl olmaq cinayətdir',
        'Bu dünyaya 2 günəş artıqdır, sən gəl mənim günəşim ol 💕',
        'Sənə şeir yazardım, amma, varlığın şeir kimidir',
        'Gözlərinə baxanda məni itirirsən, sehrbazsan sən? 😅',
        'Görünüşün o qədər gözəldir ki, sənətkar olsam, yalnız səndən ilham alardım 🤠',
        'Sən o qədər lükssən ki, mənim başımdaki adi sətirlər şərəfinə ayələrə bükülür 🥰',
        'Sən, mənim gecəmin səmasındaki parlaq ulduz kimisən. Yalnız sənlə birlikdə...',
        'Belə bir şəhzadəyə layiq olmaq üçün hansı əjdəhanı öldürmək lazımdır? 😉',
        'Həyatım boyu axtardığım xəzinəni tapmaqda şanslıydım. Bunu anlayaraq, gözümün önünə gözlərin gəldi )',
        'Mavi gözlərin okeana bənzəyir, mən isə onun içində boğulan insan',
        'Nə ölüm, nə günüm. Bir tək yanımda sən qal, gülüm 🥰',
        'Gözünün rəngini bilməyə gəldim, Könlünün haqqını almağa gəldim, Sevgilin varmı, olmağa gəldim, Ürəyinə məzar aç, ölməyə gəldim ;)',
        'Səni gördüm, ürəyimi kəsdin. Yəqin ki, ürək masajı etməyi bilirsən :)',
        'Sənə 1 günün 24 saat, 1 saatın 60 dəqiqə, 1 dəqiqənin 60 saniyə olduğu öyrədilib amma sənsiz keçən hər saniyənin sonsuzluq qədər uzun olduğu öyrədilmədi. Mənə Sonsuzluğun uzunluğunu göstərməyəcək hər saniyəm olarsan?',
        'Gəl, qaçdı-tutdu oynayaq, sən qaç, mən səni tutum, keçək şəxsiyə 😅',
        'O qədər Pubg oynadım, sənə vurulduğum kimi vurulmadım )',
        'Gözəl olmaqdan başqa, hansı işlərlə məşğul olursan',
        'Əgər səhv deyirəmsə, məni öp. Dinozavrlar hələ də yaşayır düz deyil? )',
        'Sizi evə qədər izləyə bilərəm? Çünki, uşaq vaxtı həmişə xəyallarının arxasınca get deyib, öyrədiblər 😅',
        'O qədər gözəlsən ki, plugindəki bütün sözlər az qalar yanında 😉🥰',
        'Salam, xanım, necəsiniz? Allah sizə hərşey verib, bir mənim nömrəmdən başqa 🤠',
        'Salam, xanım, çolkanız olsam göz üstündə saxlayarsız məni? 🙈'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $randomPhrase = self::$phrases[array_rand(self::$phrases)];
            $message->editText("<code>{$randomPhrase}</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
