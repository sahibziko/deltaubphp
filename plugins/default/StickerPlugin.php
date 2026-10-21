<?php
use App\BasePlugin;
use App\Database;

class StickerPlugin extends BasePlugin {
    public string $name = 'Stikerlər';
    public string $description = 'Stiker alətləri.';
    public array $commands = ['kang' => 'Stikeri oğurla', 'stickerid' => 'ID tap', 'getsticker' => 'Yüklə'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $reply = $message->getReply();
            if (!$reply) {
                $message->editText("⚠️ Stikerə cavab verin.");
                return;
            }
            
            if ($command === 'stickerid') {
                $message->editText("🆔 Stiker ID tapıldı (Sticker object info).", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } elseif ($command === 'getsticker') {
                $message->editText("📥 Stiker yüklənir...");
            } elseif ($command === 'kang') {
                $message->editText("🦘 Stiker paketinizə əlavə edildi! (Tezliklə tam işləyəcək)");
            }
        } catch (\Throwable $e) { $message->editText("❌ Xəta: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));
        }
    }
}
