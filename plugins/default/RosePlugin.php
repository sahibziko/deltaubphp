<?php
use App\BasePlugin;

class RosePlugin extends BasePlugin {
    public string $name = 'Rose Bot (Federasiya və Məlumat)';
    public string $description = '@MissRose_bot inteqrasiyası ilə fban, fedinfo və istifadəçi məlumatları almaq.';
    public array $commands = [
        'fstat' => 'Şəxsin federasiya ban (fban) statusunu yoxlayır (.fstat <id/tag> və ya cavablayaraq)',
        'rinfo' => 'Rose Bot vasitəsilə istifadəçi məlumatı alır (.rinfo <id/tag>)',
        'roseinfo' => 'Rose Bot vasitəsilə istifadəçi məlumatı alır (.roseinfo <id/tag>)',
        'fedinfo' => 'Federasiya haqqında məlumat verir (.fedinfo <fed_id>)',
        'myfeds' => 'Səlahiyyətli olduğunuz federasiyaları göstərir'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $reply = $message->getReply();
            $targetUser = '';

            if ($reply && isset($reply->senderId)) {
                $targetUser = (string) $reply->senderId;
            } elseif (!empty($args[0])) {
                $targetUser = trim($args[0]);
            }

            if ($command === 'fstat') {
                $message->editText("🔄 <b>@MissRose_bot vasitəsilə fban statusu yoxlanılır...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                
                $cmdText = !empty($targetUser) ? "/fedstat {$targetUser}" : "/fedstat";
                $this->processRoseCommand($message, $cmdText);
                return;
            }

            if ($command === 'rinfo' || $command === 'roseinfo') {
                $message->editText("🔄 <b>@MissRose_bot vasitəsilə məlumat alınır...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                
                $cmdText = !empty($targetUser) ? "/info {$targetUser}" : "/info";
                $this->processRoseCommand($message, $cmdText);
                return;
            }

            if ($command === 'fedinfo') {
                $fedId = isset($args[0]) ? trim($args[0]) : '';
                $message->editText("🔄 <b>Federasiya məlumatı sorğulanır...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                
                $cmdText = !empty($fedId) ? "/fedinfo {$fedId}" : "/fedinfo";
                $this->processRoseCommand($message, $cmdText);
                return;
            }

            if ($command === 'myfeds') {
                $message->editText("🔄 <b>Federasiyalarınız sorğulanır...</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                
                $this->processRoseCommand($message, "/myfeds");
                return;
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function processRoseCommand($message, string $roseCmd): void {
        $roseBot = '@MissRose_bot';

        // Botun blokda olmadığını yoxla və blokdan çıxart
        try {
            $this->api->contacts->unblock(id: $roseBot);
        } catch (\Throwable $t) {}

        // Rose Bot-a sorğunu göndər
        try {
            $this->api->messages->sendMessage(
                peer: $roseBot,
                message: $roseCmd
            );
        } catch (\Throwable $t) {
            $message->editText("❌ Sorğunu @MissRose_bot hesabına göndərmək mümkün olmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
            return;
        }

        // Cavab üçün 1.8 saniyə gözlə
        usleep(1800000);

        try {
            $history = $this->api->messages->getHistory(
                peer: $roseBot,
                limit: 5
            );

            $messages = $history['messages'] ?? [];
            $roseReply = null;

            foreach ($messages as $msg) {
                if (empty($msg['out'])) {
                    $roseReply = $msg;
                    break;
                }
            }

            if (!$roseReply) {
                $message->editText("⚠️ @MissRose_bot cavab vermədi. Lütfən botun blokda olmadığını yoxlayın.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $replyText = trim($roseReply['message'] ?? '');

            // Əgər cavabda media/fayl varsa və ya cavab mətni var:
            if (!empty($roseReply['media'])) {
                try {
                    $this->api->messages->forwardMessages(
                        from_peer: $roseBot,
                        id: [(int)$roseReply['id']],
                        to_peer: $message->chatId
                    );
                    $message->delete();
                    return;
                } catch (\Throwable $t) {}
            }

            if (!empty($replyText)) {
                $textEsc = htmlspecialchars($replyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $message->editText("🌹 <b>@MissRose_bot Nəticəsi:</b>\n\n{$textEsc}", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } else {
                try {
                    $this->api->messages->forwardMessages(
                        from_peer: $roseBot,
                        id: [(int)$roseReply['id']],
                        to_peer: $message->chatId
                    );
                    $message->delete();
                } catch (\Throwable $t) {
                    $message->editText("ℹ️ @MissRose_bot cavab göndərdi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                }
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Cavab alınarkən xəta: " . $err);
        }
    }
}
