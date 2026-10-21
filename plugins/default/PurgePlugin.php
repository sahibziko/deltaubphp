<?php
use App\BasePlugin;
use danog\MadelineProto\ParseMode;

class PurgePlugin extends BasePlugin {
    public string $name = 'Silmə (Purge)';
    public string $description = 'Mesajları kütləvi və ya tək-tək silir (.purge, .purgeme, .del).';
    public array $commands = [
        'purge' => 'Cavab verilən mesajdan sonrakı bütün mesajları silir (.purge)',
        'purgeme' => 'Öz yazdığın mesajları silir (.purgeme <sayı>)',
        'del' => 'Yanıtladığın mesajı silir (.del)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = $message->chatId;

            if ($command === 'del') {
                $reply = $message->getReply();
                if (!$reply) {
                    $message->editText("⚠️ Silmək üçün bir mesaja cavab verin.");
                    return;
                }
                
                $ids = [(int)$reply->id, (int)$message->id];
                $this->deleteMsgIds($chatId, $ids);
                return;
            }

            if ($command === 'purge') {
                $reply = $message->getReply();
                if (!$reply) {
                    $message->editText("⚠️ Purge etmək üçün yuxarıdakı bir mesaja cavab verin.");
                    return;
                }

                $startId = min((int)$reply->id, (int)$message->id);
                $endId = max((int)$reply->id, (int)$message->id);
                
                $ids = range($startId, $endId);
                $total = count($ids);

                $this->deleteMsgIds($chatId, $ids);

                $statusMsg = $this->api->sendMessage($chatId, "🗑 <b>{$total} mesaj uğurla silindi!</b>", parseMode: ParseMode::HTML);
                \Amp\delay(2.5);
                try {
                    if (isset($statusMsg['id'])) {
                        $this->deleteMsgIds($chatId, [(int)$statusMsg['id']]);
                    }
                } catch (\Throwable $t) {}
                return;
            }

            if ($command === 'purgeme') {
                $targetCount = isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : 10;
                if ($targetCount <= 0) $targetCount = 10;
                
                // Chat tarixçəsini alırıq (maksimum 100 mesaj gətiririk ki, aralarından öz mesajlarımızı tapaq)
                $fetchLimit = min(max($targetCount * 3 + 10, 30), 100);
                $history = $this->api->messages->getHistory(peer: $chatId, limit: $fetchLimit);
                $messages = $history['messages'] ?? [];
                
                $myIds = [];
                foreach ($messages as $m) {
                    $isMine = false;
                    
                    // 1. Outgoing bayrağı (Ən etibarlı göstərici - cari istifadəçinin göndərdiyi mesajlarda out=true olur)
                    if (!empty($m['out'])) {
                        $isMine = true;
                    } else {
                        // 2. from_id yoxlanışı (integer, array və ya sender_id formatında)
                        $fromId = 0;
                        if (isset($m['from_id'])) {
                            if (is_numeric($m['from_id'])) {
                                $fromId = (int)$m['from_id'];
                            } elseif (is_array($m['from_id'])) {
                                $fromId = (int)($m['from_id']['user_id'] ?? $m['from_id']['channel_id'] ?? 0);
                            }
                        } elseif (isset($m['sender_id'])) {
                            $fromId = (int)$m['sender_id'];
                        }
                        
                        if ($fromId > 0 && $fromId === $this->ownerId) {
                            $isMine = true;
                        }
                    }

                    if ($isMine && isset($m['id'])) {
                        $myIds[] = (int) $m['id'];
                        if (count($myIds) >= $targetCount) {
                            break;
                        }
                    }
                }

                // Əgər .purgeme əmrinin özü siyahıya düşməyibsə, onu da əlavə edirik
                if (is_object($message) && isset($message->id) && !in_array((int)$message->id, $myIds, true)) {
                    $myIds[] = (int)$message->id;
                }

                if (empty($myIds)) {
                    $message->editText("⚠️ Silinəcək öz mesajınız tapılmadı.");
                    return;
                }

                $this->deleteMsgIds($chatId, $myIds);
                
                $deletedCount = count($myIds);
                $statusMsg = $this->api->sendMessage($chatId, "🗑 Özünüzə aid <b>{$deletedCount}</b> mesaj silindi!", parseMode: ParseMode::HTML);
                \Amp\delay(2.5);
                try {
                    if (isset($statusMsg['id'])) {
                        $this->deleteMsgIds($chatId, [(int)$statusMsg['id']]);
                    }
                } catch (\Throwable $t) {}
            }
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err);
        }
    }

    private function deleteMsgIds($chatId, array $ids): void {
        if (empty($ids)) return;
        $chunks = array_chunk($ids, 100);
        foreach ($chunks as $chunk) {
            try {
                $this->api->channels->deleteMessages(channel: $chatId, id: $chunk);
            } catch (\Throwable $t) {
                try {
                    $this->api->messages->deleteMessages(id: $chunk, revoke: true);
                } catch (\Throwable $t2) {}
            }
        }
    }
}
