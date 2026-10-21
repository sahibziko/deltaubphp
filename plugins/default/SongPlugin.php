<?php
use App\BasePlugin;
use danog\MadelineProto\ParseMode;

class SongPlugin extends BasePlugin {
    public string $name = 'Musiqi Yükləyici';
    public string $description = 'Telegram musiqi botları və Deezer vasitəsilə istənilən mahnını axtarıb MP3 formatında çata göndərir.';
    public array $commands = [
        'song' => 'Musiqi axtarıb MP3 göndərir (.song <mahnı_adı>)',
        'deez' => 'Deezer-dən musiqi axtarıb göndərir (.deez <mahnı_adı>)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $query = trim(implode(' ', $args));
            $reply = $message->getReply();

            if (empty($query) && $reply) {
                $query = trim($reply->message ?? '');
            }

            if (empty($query)) {
                $message->editText(
                    "⚠️ <b>İstifadə qaydası:</b>\n"
                  . "• <code>.song &lt;mahnı adı və ya müğənni&gt;</code>\n"
                  . "• <code>.deez &lt;mahnı adı&gt;</code>",
                    parseMode: ParseMode::HTML
                );
                return;
            }

            $queryEsc = htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("🔍 <code>{$queryEsc}</code> axtarılır...", parseMode: ParseMode::HTML);

            // 1. Əvvəlcə Telegram Inline Musiqi Botları ilə axtarış
            if ($this->tryInlineBots($message, $command, $query)) {
                return;
            }

            // 2. Fallback: Deezer rəsmi API ilə birbaşa axtarış
            if ($this->tryDeezerApi($message, $query)) {
                return;
            }

            // 3. Əgər heç bir mənbədən tapılmadısa
            $message->editText("❌ <code>{$queryEsc}</code> üçün musiqi tapılmadı.\n<i>Lütfən mahnı və ya müğənni adını daha dəqiq yazın.</i>", parseMode: ParseMode::HTML);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    /**
     * Telegram Inline Musiqi botları vasitəsilə axtarış və birbaşa göndərmə
     */
    private function tryInlineBots(object $message, string $command, string $query): bool {
        $bots = ($command === 'deez') 
            ? ['@DeezerMusicBot', '@vkmusbot', '@ytsmbot', '@spotifysavebot'] 
            : ['@vkmusbot', '@DeezerMusicBot', '@ytsmbot', '@spotifysavebot'];

        foreach ($bots as $bot) {
            try {
                $results = $this->api->messages->getInlineBotResults([
                    'bot' => $bot,
                    'peer' => $message->chatId,
                    'query' => $query,
                    'offset' => ''
                ]);

                if (!empty($results['results']) && is_array($results['results'])) {
                    // Audio tipində olan ilk nəticəni tapırıq
                    $selectedId = null;
                    foreach ($results['results'] as $r) {
                        if (($r['type'] ?? '') === 'audio') {
                            $selectedId = $r['id'];
                            break;
                        }
                    }

                    if (!$selectedId) {
                        $selectedId = $results['results'][0]['id'] ?? null;
                    }

                    if ($selectedId !== null) {
                        $sentUpdates = $this->api->messages->sendInlineBotResult([
                            'peer' => $message->chatId,
                            'query_id' => $results['query_id'],
                            'id' => $selectedId
                        ]);

                        // Mahnı adını və müğənnini təmizləyirik
                        $trackTitle = $chosenResult['title'] ?? $query;
                        $trackDesc = $chosenResult['description'] ?? '';
                        $header = $trackTitle;
                        if (!empty($trackDesc) && !str_contains($trackTitle, $trackDesc)) {
                            $header .= " — " . $trackDesc;
                        }

                        $customCaption = "🎵 <b>" . htmlspecialchars($header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>\n\n"
                                       . "⚡ <b>DeltaUB — ilə musiqi tapıldı!</b>";

                        // Göndərilən mesajın ID-sini tapıb altındakı reklamı öz yazımızla əvəzləyirik
                        $msgId = 0;
                        if (isset($sentUpdates['id'])) {
                            $msgId = (int)$sentUpdates['id'];
                        } elseif (!empty($sentUpdates['updates']) && is_array($sentUpdates['updates'])) {
                            foreach ($sentUpdates['updates'] as $u) {
                                if (isset($u['message']['id'])) {
                                    $msgId = (int)$u['message']['id'];
                                    break;
                                } elseif (isset($u['id'])) {
                                    $msgId = (int)$u['id'];
                                    break;
                                }
                            }
                        }

                        if ($msgId > 0) {
                            try {
                                $this->api->messages->editMessage([
                                    'peer' => $message->chatId,
                                    'id' => $msgId,
                                    'message' => $customCaption,
                                    'parse_mode' => 'HTML'
                                ]);
                            } catch (\Throwable $te) {}
                        }

                        try {
                            $message->delete();
                        } catch (\Throwable $td) {}

                        return true;
                    }
                }
            } catch (\Throwable $tBot) {
                // Digər bota keç
            }
        }

        return false;
    }

    /**
     * Deezer API vasitəsilə musiqi yüklənməsi
     */
    private function tryDeezerApi(object $message, string $query): bool {
        try {
            $url = "https://api.deezer.com/search?q=" . urlencode($query) . "&limit=1";
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);

            $res = @file_get_contents($url, false, $ctx);
            if (!$res) return false;

            $data = json_decode($res, true);
            if (empty($data['data'][0])) return false;

            $track = $data['data'][0];
            $audioUrl = $track['preview'] ?? null;
            if (!$audioUrl) return false;

            $title = $track['title'] ?? $query;
            $artist = $track['artist']['name'] ?? 'Musiqi';
            $duration = (int) ($track['duration'] ?? 180);

            $tempDir = sys_get_temp_dir() . '/delta_songs/';
            if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);
            $filePath = $tempDir . md5($query . microtime(true)) . '.mp3';

            $audioData = @file_get_contents($audioUrl, false, $ctx);
            if (!$audioData || strlen($audioData) < 1000) return false;

            file_put_contents($filePath, $audioData);

            $caption = "🎵 <b>" . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</b>\n"
                     . "👤 " . htmlspecialchars($artist, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n\n"
                     . "⚡ <b>DeltaUB — ilə musiqi tapıldı!</b>";

            $this->api->messages->sendMedia([
                'peer' => $message->chatId,
                'media' => [
                    '_' => 'inputMediaUploadedDocument',
                    'file' => $filePath,
                    'mime_type' => 'audio/mpeg',
                    'attributes' => [
                        [
                            '_' => 'documentAttributeAudio',
                            'voice' => false,
                            'title' => $title,
                            'performer' => $artist,
                            'duration' => $duration
                        ]
                    ]
                ],
                'message' => $caption,
                'parse_mode' => 'HTML'
            ]);

            try {
                $message->delete();
            } catch (\Throwable $td) {}

            @unlink($filePath);
            return true;

        } catch (\Throwable $t) {
            return false;
        }
    }
}
