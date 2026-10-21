<?php
use App\BasePlugin;

class ClonePlugin extends BasePlugin {
    public string $name = 'Klonlama';
    public string $description = 'İstifadəçinin profil məlumatlarını və şəklini klonlayır.';
    public array $commands = [
        'klon' => 'İstifadəçini klonlayır (ad, bio, pp)',
        'revert' => 'Profili əvvəlki vəziyyətinə qaytarır'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();

            if ($command === 'revert') {
                $stmt = $this->db->prepare("SELECT first_name, last_name, about, photo_path FROM user_clones WHERE user_id = ?");
                $stmt->execute([$userId]);
                $cloneData = $stmt->fetch();

                if (!$cloneData) {
                    $message->editText("⚠️ Saxlanılmış profil məlumatı tapılmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                // 1. Klonlanmış profil şəklini sil
                try {
                    $myPhotos = $this->api->photos->getUserPhotos(user_id: 'me', offset: 0, max_id: 0, limit: 1);
                    if (!empty($myPhotos['photos'])) {
                        $topPhoto = $myPhotos['photos'][0];
                        $this->api->photos->deletePhotos(id: [$topPhoto]);
                    }
                } catch (\Throwable $t) {}

                // 2. Orijinal PP var idisə, yenidən yüklə
                if (!empty($cloneData['photo_path']) && file_exists($cloneData['photo_path'])) {
                    try {
                        $this->api->photos->uploadProfilePhoto(['file' => $cloneData['photo_path']]);
                    } catch (\Throwable $t) {}
                    @unlink($cloneData['photo_path']);
                }

                // 3. Ad, soyad və bio-nu əvvəlki halına qaytar
                $this->api->account->updateProfile(
                    first_name: $cloneData['first_name'] ?? '',
                    last_name: $cloneData['last_name'] ?? '',
                    about: $cloneData['about'] ?? ''
                );

                $stmt = $this->db->prepare("DELETE FROM user_clones WHERE user_id = ?");
                $stmt->execute([$userId]);

                $message->editText("🔄 <b>Profiliniz və profil şəkliniz əvvəlki vəziyyətinə qaytarıldı!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'klon') {
                // Yoxla: Əgər artıq klonlanıbsa, icazə vermə
                $stmt = $this->db->prepare("SELECT id FROM user_clones WHERE user_id = ?");
                $stmt->execute([$userId]);
                if ($stmt->fetch()) {
                    $message->editText("⚠️ Artıq bir istifadəçini klonlamısınız! Əvvəlcə <code>.revert</code> edin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $reply = $message->getReply();
                $targetId = 0;
                if ($reply && isset($reply->senderId)) {
                    $targetId = (int) $reply->senderId;
                } elseif (!empty($args[0])) {
                    $raw = trim($args[0]);
                    if (is_numeric($raw)) {
                        $targetId = (int) $raw;
                    } else {
                        $info = $this->api->getInfo($raw);
                        $targetId = (int) ($info['bot_api_id'] ?? $info['User']['id'] ?? 0);
                    }
                }

                if ($targetId === 0) {
                    $message->editText("⚠️ Lütfən klonlamaq istədiyiniz istifadəçini yanıtlayın və ya ID/username qeyd edin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                // Öz orijinal məlumatlarını saxla
                $selfInfo = $this->api->getFullInfo('me');
                $myFirst = $selfInfo['User']['first_name'] ?? '';
                $myLast = $selfInfo['User']['last_name'] ?? '';
                $myAbout = $selfInfo['full']['about'] ?? '';
                
                $savedPhotoPath = null;
                try {
                    $tempDir = sys_get_temp_dir() . '/delta_clones/';
                    if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);
                    $myPhoto = $selfInfo['full']['profile_photo'] ?? null;
                    if ($myPhoto) {
                        $savedPhotoPath = $this->api->downloadToDir($myPhoto, $tempDir);
                    }
                } catch (\Throwable $t) {}

                $stmt = $this->db->prepare("INSERT INTO user_clones (user_id, telegram_id, first_name, last_name, about, photo_path) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $this->ownerId, $myFirst, $myLast, $myAbout, $savedPhotoPath]);

                // Hədəf istifadəçinin məlumatlarını al
                $targetInfo = $this->api->getFullInfo($targetId);
                $targetUser = $targetInfo['User'] ?? [];
                $targetFirst = $targetUser['first_name'] ?? '';
                $targetLast = $targetUser['last_name'] ?? '';
                $targetAbout = $targetInfo['full']['about'] ?? '';

                // Hədəfin şəklini endir və profil şəkli (main photo) et
                try {
                    $targetPhoto = $targetInfo['full']['profile_photo'] ?? null;
                    if ($targetPhoto) {
                        $tempDir = sys_get_temp_dir() . '/delta_clones/';
                        if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);
                        $downloadedPhoto = $this->api->downloadToDir($targetPhoto, $tempDir);
                        if ($downloadedPhoto && file_exists($downloadedPhoto)) {
                            $this->api->photos->uploadProfilePhoto(['file' => $downloadedPhoto]);
                            @unlink($downloadedPhoto);
                        }
                    }
                } catch (\Throwable $t) {}

                // Profili yenilə
                $this->api->account->updateProfile(
                    first_name: $targetFirst,
                    last_name: $targetLast,
                    about: $targetAbout
                );

                $targetNameEsc = htmlspecialchars(trim("$targetFirst $targetLast"), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $message->editText("🎭 <b>{$targetNameEsc}</b> uğurla klonlandı!\n<i>Əvvəlki halına qaytarmaq üçün: <code>.revert</code></i>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function getUserId(): int {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return (int) ($user['id'] ?? 0);
    }
}
