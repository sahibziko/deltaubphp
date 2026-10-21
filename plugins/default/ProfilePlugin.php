<?php
use App\BasePlugin;

class ProfilePlugin extends BasePlugin {
    public string $name = 'Profil';
    public string $description = 'Profil məlumatlarını yeniləyir.';
    public array $commands = ['setname' => 'Adı dəyiş', 'setbio' => 'Haqqında dəyiş', 'setpfp' => 'Şəkil qoy', 'delpfp' => 'Şəkli sil'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $text = implode(' ', $args);
            switch ($command) {
                case 'setname':
                    $parts = explode('|', $text);
                    $first = trim($parts[0] ?? '');
                    $last = trim($parts[1] ?? '');
                    $this->api->account->updateProfile(first_name: $first, last_name: $last);
                    $message->editText("✅ Ad yeniləndi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'setbio':
                    $this->api->account->updateProfile(about: $text);
                    $message->editText("✅ Haqqında hissəsi yeniləndi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'setpfp':
                    $reply = $message->getReply();
                    if ($reply && isset($reply->media)) {
                        $file = $this->api->downloadToDir($reply, sys_get_temp_dir());
                        $this->api->photos->uploadProfilePhoto(file: $file);
                        @unlink($file);
                        $message->editText("✅ Şəkil yeniləndi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    } else {
                        $message->editText("⚠️ Şəkilə cavab verin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    }
                    break;
                case 'delpfp':
                    $photos = $this->api->photos->getUserPhotos(user_id: 'me', offset: 0, max_id: 0, limit: 1);
                    if (!empty($photos['photos'])) {
                        $this->api->photos->deletePhotos(id: [$photos['photos'][0]['id'] ?? $photos['photos'][0]]);
                        $message->editText("✅ Şəkil silindi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    } else {
                        $message->editText("⚠️ Silinəcək şəkil yoxdur.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    }
                    break;
            }
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
