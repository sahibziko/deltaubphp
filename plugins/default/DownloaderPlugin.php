<?php
use App\BasePlugin;

class DownloaderPlugin extends BasePlugin {
    public string $name = 'Yükləyici';
    public string $description = 'Fayl yükləmə alətləri.';
    public array $commands = ['download' => 'Yüklə', 'upload' => 'Serverdən yüklə'];

    public function onCommand($message, string $command, array $args): void {
        try {
            if ($command === 'download') {
                $reply = $message->getReply();
                if (!$reply) {
                    $message->editText("⚠️ Mediyaya cavab verin.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $message->editText("📥 Yüklənir...", parseMode: \danog\MadelineProto\ParseMode::HTML);
                $file = $this->api->downloadToDir($reply, sys_get_temp_dir());
                $message->editText("✅ Yükləndi: <code>{$file}</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'upload') {
                $file = implode(' ', $args);
                if (!file_exists($file)) {
                    $message->editText("⚠️ Fayl tapılmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $message->editText("📤 Göndərilir...", parseMode: \danog\MadelineProto\ParseMode::HTML);
                $this->api->messages->sendMedia(peer: $message->chatId, media: ['_' => 'inputMediaUploadedDocument', 'file' => $file]);
                $message->editText("✅ Göndərildi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
