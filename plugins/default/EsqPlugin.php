<?php
use App\BasePlugin;

class EsqPlugin extends BasePlugin {
    public string $name = 'Eşq Ölçən (Love Calculator)';
    public string $description = 'Cavab verdiyiniz və ya qeyd etdiyiniz istifadəçi ilə aranızdakı eşq faizini ölçür (.esq).';
    public array $commands = [
        'esq' => 'Eşq faizini ölçür (.esq <istifadəçi/cavablayaraq>)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $firstUser = '';
            $secondUser = '';
            $firstId = $this->ownerId;
            $secondId = 0;

            // 1. İstifadəçiləri müəyyən edirik
            $reply = $message->getReply();
            if ($reply && isset($reply->senderId)) {
                $secondId = (int) $reply->senderId;
                
                // Birinci istifadəçi
                if (!empty($args[0])) {
                    $firstUser = htmlspecialchars(implode(' ', $args), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $firstId = crc32($firstUser);
                } else {
                    $firstUser = $this->formatUserMention($this->ownerId);
                }

                $secondUser = $this->formatUserMention($secondId);
            } elseif (!empty($args[0])) {
                $target = trim($args[0]);
                try {
                    $info = $this->api->getInfo($target);
                    $secondId = (int) ($info['bot_api_id'] ?? $info['User']['id'] ?? 0);
                    $secondUser = $this->formatUserMention($secondId, $info);
                    $firstUser = $this->formatUserMention($this->ownerId);
                } catch (\Throwable $t) {
                    $secondId = crc32($target);
                    $secondUser = htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $firstUser = $this->formatUserMention($this->ownerId);
                }
            } else {
                $message->editText("⚠️ <b>Zəhmət olmasa bir istifadəçinin mesajına cavab verin və ya istifadəçi adını qeyd edin!</b>\n<i>Nümunə: <code>.esq @istifadeci</code></i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            // 2. FloodWait-dən qorunan zərif animasiya (3 addım, 0.8s fasilə)
            $hearts = ["❤️", "🧡", "💛", "💚", "💙", "💜", "🖤"];
            $stages = [25, 60, 90];

            foreach ($stages as $pct) {
                try {
                    $heart = $hearts[array_rand($hearts)];
                    $message->editText("{$heart} <i>Eşq faizi hesablanır... [{$pct}%]</i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                } catch (\danog\MadelineProto\FloodWaitException $e) {
                    break;
                } catch (\Throwable $t) {}

                \Amp\delay(0.8);
            }

            // 3. Eşq faizinin təyini və yadda saxlanması (Eyni iki şəxs üçün sabit qalır)
            $cacheFile = dirname(__DIR__, 2) . '/sessions/esq_data.json';
            $cache = [];
            if (file_exists($cacheFile)) {
                $content = @file_get_contents($cacheFile);
                if (!empty($content)) {
                    $cache = json_decode($content, true) ?: [];
                }
            }

            $pairKey = min($firstId, $secondId) . '_' . max($firstId, $secondId);
            if (isset($cache[$pairKey])) {
                $faiz = (int) $cache[$pairKey];
            } else {
                $faiz = rand(15, 100);
                $cache[$pairKey] = $faiz;
                $dir = dirname($cacheFile);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @file_put_contents($cacheFile, json_encode($cache));
            }

            // 4. Nəticənin çıxarılması
            $resultText = "💖 {$firstUser} ilə {$secondUser} arasındakı eşq hesablandı!\n\n📊 <b>Nəticə:</b> <code>%{$faiz}</code>";
            if ($faiz >= 85) {
                $resultText .= "\n🔥 <i>Əsl məhəbbət! Bir-biriniz üçün yaranmısınız.</i>";
            } elseif ($faiz >= 50) {
                $resultText .= "\n✨ <i>Gözəl uyğunluq var, münasibətlər inkişaf edə bilər.</i>";
            } else {
                $resultText .= "\n💔 <i>Dost qalsanız daha yaxşıdır...</i>";
            }

            $message->editText($resultText, parseMode: \danog\MadelineProto\ParseMode::HTML);

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}

