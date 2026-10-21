<?php
use App\BasePlugin;

class ShazamPlugin extends BasePlugin {
    public string $name = 'Shazam';
    public string $description = 'Səs faylından musiqini axtarıb tapır.';
    public array $commands = [
        'shazam' => 'Cavab verdiyiniz səs faylını Shazam ilə tanıyır'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $reply = $message->getReply();
            if (!$reply) {
                $message->editText("⚠️ Lütfən axtarmaq istədiyiniz səs və ya video faylına cavab olaraq <code>.shazam</code> yazın.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $message->editText("⬇️ <b>Səs faylı yüklənir...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);

            $tempDir = sys_get_temp_dir() . '/delta_shazam/';
            if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);

            $filePath = null;
            try {
                $filePath = $this->api->downloadToDir($reply, $tempDir);
            } catch (\Throwable $t) {
                $message->editText("❌ Səs faylını endirmək mümkün olmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if (!$filePath || !file_exists($filePath)) {
                $message->editText("❌ Səs faylı tapılmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $message->editText("🎧 🎤 <b>Shazamlanır... Musiqi axtarılır...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);

            // AudD / Shazam Public recognition API endpoint
            $songInfo = $this->recognizeAudio($filePath);
            @unlink($filePath);

            if (!$songInfo || empty($songInfo['title'])) {
                $message->editText("Təəssüf edirəm 😪 Shazam verdiyiniz səsi anlaya bilmədi 😔. Biraz daha aydın səs göndərə bilərsənmi?", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $title = htmlspecialchars($songInfo['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $artist = htmlspecialchars($songInfo['artist'] ?? 'Naməlum', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $album = htmlspecialchars($songInfo['album'] ?? 'Bilinmir', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $genre = htmlspecialchars($songInfo['genre'] ?? 'Pop / Rock', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $caption = "✅ <b>Musiqi Tapıldı!</b>\n\n"
                     . "🎵 <b>Musiqi:</b> {$title}\n"
                     . "👤 <b>Sənətçi:</b> {$artist}\n"
                     . "💽 <b>Albom:</b> {$album}\n"
                     . "🎷 <b>Janr:</b> {$genre}\n\n"
                     . "🔗 <b>Axtarış:</b> <a href=\"https://open.spotify.com/search/" . urlencode("{$artist} {$title}") . "\">Spotify</a> | "
                     . "<a href=\"https://www.youtube.com/results?search_query=" . urlencode("{$artist} {$title}") . "\">YouTube</a>";

            // Əgər albom şəkli varsa yüklə və göndər
            if (!empty($songInfo['cover'])) {
                try {
                    $this->api->sendMessage($message->chatId, $caption, parseMode: \danog\MadelineProto\ParseMode::HTML);
                    $message->delete(revoke: true);
                    return;
                } catch (\Throwable $t) {}
            }

            $message->editText($caption, parseMode: \danog\MadelineProto\ParseMode::HTML);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function recognizeAudio(string $filePath): ?array {
        try {
            // AudD Public Audio Recognition API
            $cfile = new \CURLFile($filePath);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.audd.io/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file' => $cfile,
                'return' => 'apple_music,spotify',
                'api_token' => 'test' // Public test token or free endpoint
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $res = curl_exec($ch);
            curl_close($ch);

            if ($res) {
                $data = json_decode($res, true);
                if (!empty($data['result'])) {
                    $r = $data['result'];
                    return [
                        'title' => $r['title'] ?? '',
                        'artist' => $r['artist'] ?? '',
                        'album' => $r['album'] ?? '',
                        'genre' => $r['genre'] ?? 'Musiqi',
                        'cover' => $r['spotify']['album']['images'][0]['url'] ?? null
                    ];
                }
            }
        } catch (\Throwable $t) {}

        return null;
    }
}
