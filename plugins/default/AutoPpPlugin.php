<?php
use App\BasePlugin;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\FloodWaitException;

class AutoPpPlugin extends BasePlugin {
    public string $name = 'Auto PP (Saatlı Profil Şəkli)';
    public string $description = 'Hər dəqiqə dəyişən saatlı elit profil şəkli quraşdırır (.autopp / .autopp stop)';
    public array $commands = [
        'autopp' => 'Avtomatik saatlı profil şəkli (.autopp və ya .autopp stop)',
        'pp' => 'Avtomatik saatlı profil şəkli (.pp)',
        'autostop' => 'AutoPP prosesini dayandırır (.autostop)'
    ];

    public static array $runningUsers = [];

    public static function isRunningForUser(int $userId): bool {
        return !empty(self::$runningUsers[$userId]);
    }

    public static function stopForUser(int $userId): bool {
        if (!empty(self::$runningUsers[$userId])) {
            self::$runningUsers[$userId] = false;
            return true;
        }
        return false;
    }

    // Yüksək keyfiyyətli, elit və estetik qaranlıq fon şəkilləri (Aesthetic Dark HQ 600x600)
    private static array $eliteImages = [
        "https://images.unsplash.com/photo-1509198397868-475647b2a1e5?auto=format&fit=crop&w=600&h=600&q=80", // Cyberpunk Neon
        "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&h=600&q=80", // Luxury Fluid Dark
        "https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=600&h=600&q=80", // Supercar Dark
        "https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=600&h=600&q=80", // Porsche Dark Aesthetic
        "https://images.unsplash.com/photo-1550684848-fac1c5b4e853?auto=format&fit=crop&w=600&h=600&q=80", // Neon Geometric Dark
        "https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=600&h=600&q=80", // Dark Minimalist
        "https://images.unsplash.com/photo-1542282088-72c9c27ed0cd?auto=format&fit=crop&w=600&h=600&q=80", // BMW Black Dark
        "https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=600&h=600&q=80", // Dark Night Mountains
        "https://images.unsplash.com/photo-1508739773434-c26b3d09e071?auto=format&fit=crop&w=600&h=600&q=80", // Dark Moody Landscape
        "https://images.unsplash.com/photo-1617814076367-b759c7d7e738?auto=format&fit=crop&w=600&h=600&q=80", // Cyber City Night
        "https://images.unsplash.com/photo-1563089145-599997674d42?auto=format&fit=crop&w=600&h=600&q=80", // Neon Glow Abstract
        "https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?auto=format&fit=crop&w=600&h=600&q=80", // Cyberpunk Anime Vibe
        "https://images.unsplash.com/photo-1514565131-fce0801e5785?auto=format&fit=crop&w=600&h=600&q=80", // Moody City Lights
        "https://images.unsplash.com/photo-1614850523459-c2f4c699c52e?auto=format&fit=crop&w=600&h=600&q=80", // Dark Silk Flow
        "https://images.unsplash.com/photo-1579546929518-9e396f3cc809?auto=format&fit=crop&w=600&h=600&q=80", // Gradient Glow Aesthetic
        "https://images.unsplash.com/photo-1516116211227-bbc0cfa11d61?auto=format&fit=crop&w=600&h=600&q=80", // Tokyo Neon Night
        "https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=600&h=600&q=80", // Matrix Tech Dark
        "https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&h=600&q=80", // Technology Abstract
        "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&h=600&q=80"  // Deep Minimalist Sunset
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $param = strtolower(trim($args[0] ?? ''));
            $ownerId = $this->ownerId > 0 ? $this->ownerId : (int)($message->senderId ?? 0);

            if ($command === 'autostop' || $param === 'stop' || $param === 'off') {
                self::stopForUser($ownerId);
                $message->editText("🛑 <b>AutoPP dayandırıldı!</b>", parseMode: ParseMode::HTML);
                return;
            }

            if (self::isRunningForUser($ownerId)) {
                $message->editText("⚠️ <b>AutoPP artıq işləyir!</b>\nDayandırmaq üçün: <code>.autopp stop</code>", parseMode: ParseMode::HTML);
                return;
            }

            self::$runningUsers[$ownerId] = true;
            $message->editText("✨ <b>AutoPP aktivləşdirildi!</b>\nProfil şəkliniz hər dəqiqə elit arxa plan və canlı saat ilə yenilənəcək.\nDayandırmaq üçün: <code>.autopp stop</code>", parseMode: ParseMode::HTML);

            $sessionsDir = dirname(__DIR__, 2) . '/sessions';
            if (!is_dir($sessionsDir)) {
                @mkdir($sessionsDir, 0755, true);
            }
            $bgDir = $sessionsDir . '/autopp_bgs';
            if (!is_dir($bgDir)) {
                @mkdir($bgDir, 0755, true);
            }

            $tempPhotoPath = $sessionsDir . '/autopp_' . $this->ownerId . '.jpg';
            $fontPath = $sessionsDir . '/BebasNeue.ttf';

            // Şrift faylı yoxdursa təhlükəsiz cURL ilə yükləyirik
            if (!file_exists($fontPath)) {
                $fontUrls = [
                    "https://cdn.jsdelivr.net/gh/google/fonts@main/ofl/bebasneue/BebasNeue-Regular.ttf",
                    "https://raw.githubusercontent.com/google/fonts/main/ofl/bebasneue/BebasNeue-Regular.ttf"
                ];
                foreach ($fontUrls as $fUrl) {
                    $fontData = self::safeHttpDownload($fUrl, 6);
                    if (!empty($fontData) && strlen($fontData) > 10000) {
                        @file_put_contents($fontPath, $fontData);
                        break;
                    }
                }
            }

            // Asinxron arxa fon dövrü
            \Amp\async(function () use ($tempPhotoPath, $fontPath, $bgDir, $ownerId) {
                $iteration = 0;
                while (self::isRunningForUser($ownerId)) {
                    $iteration++;
                    try {
                        // 1. Fon şəklini əldə edirik: Əvvəlcə lokal keşdən oxuyuruq
                        $cachedBgs = glob($bgDir . '/*.jpg');
                        $canvas = null;

                        if (!empty($cachedBgs) && function_exists('imagecreatefromstring')) {
                            $chosenFile = $cachedBgs[array_rand($cachedBgs)];
                            $rawImg = @file_get_contents($chosenFile);
                            if (!empty($rawImg)) {
                                $srcImg = @imagecreatefromstring($rawImg);
                                if ($srcImg !== false) {
                                    $canvas = imagecreatetruecolor(600, 600);
                                    imagecopyresampled(
                                        $canvas, $srcImg,
                                        0, 0, 0, 0,
                                        600, 600,
                                        imagesx($srcImg), imagesy($srcImg)
                                    );
                                    imagedestroy($srcImg);
                                }
                            }
                        }

                        // Əgər lokal fon yoxdursa, internetdən təhlükəsiz yükləyirik (xətasız cURL)
                        if ($canvas === null) {
                            $randomUrl = self::$eliteImages[array_rand(self::$eliteImages)];
                            $netImg = self::safeHttpDownload($randomUrl, 4);
                            if (!empty($netImg) && function_exists('imagecreatefromstring')) {
                                $srcImg = @imagecreatefromstring($netImg);
                                if ($srcImg !== false) {
                                    $canvas = imagecreatetruecolor(600, 600);
                                    imagecopyresampled(
                                        $canvas, $srcImg,
                                        0, 0, 0, 0,
                                        600, 600,
                                        imagesx($srcImg), imagesy($srcImg)
                                    );
                                    imagedestroy($srcImg);
                                    @file_put_contents($bgDir . '/bg_' . time() . '.jpg', $netImg);
                                }
                            }
                        }

                        // Əgər internet və ya şəkil yenə alınmadısa -> Estetik Dark Prosedural Qradiyent yaradırıq
                        if ($canvas === null) {
                            $canvas = self::generateProceduralBg();
                        }

                        // Saatı Bakı vaxtı ilə alırıq
                        $currentTime = (new \DateTime('now', new \DateTimeZone('Asia/Baku')))->format('H:i');
                        $fontSize = 120;

                        if (file_exists($fontPath) && function_exists('imagettftext')) {
                            $bbox = imagettfbbox($fontSize, 0, $fontPath, $currentTime);
                            $textWidth = abs($bbox[4] - $bbox[0]);
                            $textHeight = abs($bbox[5] - $bbox[1]);
                            $textX = (int) ((600 - $textWidth) / 2);
                            $textY = (int) ((600 + $textHeight) / 2);

                            // Estetik kölgə və kontur
                            for ($dx = -3; $dx <= 3; $dx++) {
                                for ($dy = -3; $dy <= 3; $dy++) {
                                    if ($dx !== 0 || $dy !== 0) {
                                        $outline = imagecolorallocatealpha($canvas, 0, 0, 0, 90);
                                        imagettftext($canvas, $fontSize, 0, $textX + $dx, $textY + $dy, $outline, $fontPath, $currentTime);
                                    }
                                }
                            }
                            $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 55);
                            imagettftext($canvas, $fontSize, 0, $textX + 5, $textY + 6, $shadow, $fontPath, $currentTime);

                            $white = imagecolorallocate($canvas, 255, 255, 255);
                            imagettftext($canvas, $fontSize, 0, $textX, $textY, $white, $fontPath, $currentTime);
                        } else {
                            // Sadə şrift fallback
                            $tempText = imagecreatetruecolor(50, 20);
                            $tw = imagecolorallocate($tempText, 255, 255, 255);
                            imagestring($tempText, 5, 2, 2, $currentTime, $tw);
                            imagecopyresized($canvas, $tempText, 150, 250, 0, 0, 300, 100, 50, 20);
                            imagedestroy($tempText);
                        }

                        imagejpeg($canvas, $tempPhotoPath, 92);
                        imagedestroy($canvas);

                        // 2. Yeni profil şəklini Telegram-a yükləyirik
                        if (file_exists($tempPhotoPath)) {
                            try {
                                $this->api->photos->uploadProfilePhoto([
                                    'file' => new LocalFile($tempPhotoPath)
                                ]);
                            } catch (FloodWaitException $fw) {
                                $wait = method_exists($fw, 'getWaitTime') ? $fw->getWaitTime() : 120;
                                \Amp\delay($wait);
                                continue;
                            } catch (\Throwable $tUpload) {}

                            // 3. Əvvəlki köhnə profil şəkillərini təmizləyirik (hər 5 dövrdən bir)
                            if ($iteration % 5 === 0) {
                                try {
                                    $oldPhotos = $this->api->photos->getUserPhotos([
                                        'user_id' => 'me',
                                        'offset' => 1,
                                        'max_id' => 0,
                                        'limit' => 3
                                    ]);
                                    if (!empty($oldPhotos['photos'])) {
                                        $toDelete = [];
                                        foreach ($oldPhotos['photos'] as $op) {
                                            $toDelete[] = [
                                                '_' => 'inputPhoto',
                                                'id' => $op['id'],
                                                'access_hash' => $op['access_hash'],
                                                'file_reference' => $op['file_reference']
                                            ];
                                        }
                                        if (!empty($toDelete)) {
                                            $this->api->photos->deletePhotos(['id' => $toDelete]);
                                        }
                                    }
                                } catch (\Throwable $tDel) {}
                            }
                        }

                    } catch (\Throwable $loopEx) {}

                    // Hər 60 saniyədən bir yenilənmə (qeyri-bloklayıcı Amp delay)
                    \Amp\delay(60);
                }

                @unlink($tempPhotoPath);
            });

        } catch (\Throwable $e) {
            self::stopForUser($ownerId);
            try {
                $message->editText("❌ <b>Xəta:</b> " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), parseMode: ParseMode::HTML);
            } catch (\Throwable $t) {}
        }
    }

    private static function safeHttpDownload(string $url, int $timeout = 4): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            $data = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && !empty($data) && strlen($data) > 500) {
                return $data;
            }
        }
        return null;
    }

    private static function generateProceduralBg() {
        $canvas = imagecreatetruecolor(600, 600);
        $themes = [
            [10, 15, 30, 25, 40, 75],     // Midnight Blue
            [25, 10, 35, 60, 20, 80],     // Cyber Violet
            [8, 25, 20, 18, 55, 45],      // Emerald Noir
            [30, 15, 10, 70, 35, 20],     // Sunset Rust
            [15, 15, 20, 40, 42, 50],     // Charcoal Slate
            [5, 5, 12, 18, 18, 35],       // Deep Space
        ];
        $theme = $themes[array_rand($themes)];
        [$r1, $g1, $b1, $r2, $g2, $b2] = $theme;

        for ($y = 0; $y < 600; $y++) {
            $ratio = $y / 600;
            $r = (int) ($r1 + ($r2 - $r1) * $ratio);
            $g = (int) ($g1 + ($g2 - $g1) * $ratio);
            $b = (int) ($b1 + ($b2 - $b1) * $ratio);
            $color = imagecolorallocate($canvas, $r, $g, $b);
            imageline($canvas, 0, $y, 600, $y, $color);
        }

        // Estetik dairəvi parıltı effekti
        for ($i = 0; $i < 3; $i++) {
            $circleColor = imagecolorallocatealpha($canvas, 255, 255, 255, 122);
            imageellipse($canvas, 300, 300, 480 + ($i * 30), 480 + ($i * 30), $circleColor);
        }

        return $canvas;
    }
}
