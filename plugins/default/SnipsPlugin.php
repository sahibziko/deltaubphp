<?php
use App\BasePlugin;

class SnipsPlugin extends BasePlugin {
    public string $name = 'Snips (Qısayollar)';
    public string $description = 'Qısayol qeydləri (mətn, səs, musiqi, media) saxlayır və $snip_adı yazdıqda göndərir.';
    public array $commands = [
        'snip' => 'Qısayol saxlayır (.snip <ad> <məzmun> və ya mətn/səs/musiqi cavablayaraq)',
        'snips' => 'Bütün qısayolları göstərir',
        'remsnip' => 'Qısayolu silir (.remsnip <ad> və ya .rmsnip <ad>)',
        'rmsnip' => 'Qısayolu silir (.rmsnip <ad>)',
        'resetsnip' => 'Bütün qısayolları silir və sıfırlayır'
    ];
    public bool $handlesMessages = true;

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            if ($userId === 0) {
                $message->editText("⚠️ İstifadəçi tapılmadı.");
                return;
            }

            if ($command === 'snip') {
                $keyword = isset($args[0]) ? trim(ltrim($args[0], '$')) : '';
                $content = trim(implode(' ', array_slice($args, 1)));
                $reply = $message->getReply();
                
                $mediaType = null;
                $mediaPath = null;

                if ($reply) {
                    if (empty($content) && !empty($reply->message)) {
                        $content = $reply->message;
                    }
                    if (isset($reply->media) && !empty($reply->media)) {
                        $mediaType = 'media';
                        $mediaObj = $reply->media;
                        if (is_object($mediaObj) && method_exists($mediaObj, 'toTL')) {
                            try {
                                $mediaObj = $mediaObj->toTL();
                            } catch (\Throwable $t) {}
                        }
                        $mediaPath = base64_encode(serialize($mediaObj));
                    }
                }

                if (empty($keyword) || (empty($content) && empty($mediaPath))) {
                    $message->editText(
                        "❌ <b>Boş buraxmayın!</b>\n\n"
                      . "ℹ️ <b>İstifadə qaydası:</b>\n"
                      . "• <code>.snip &lt;qısayol_adı&gt; &lt;məzmun&gt;</code>\n"
                      . "• Və ya mətn/səs/musiqi/mediaya cavab verərək: <code>.snip &lt;qısayol_adı&gt;</code>",
                        parseMode: \danog\MadelineProto\ParseMode::HTML
                    );
                    return;
                }

                try {
                    $stmt = $this->db->prepare(
                        "INSERT INTO snips (user_id, keyword, content, media_type, media_path) " .
                        "VALUES (?, ?, ?, ?, ?) " .
                        "ON DUPLICATE KEY UPDATE content = VALUES(content), media_type = VALUES(media_type), media_path = VALUES(media_path)"
                    );
                    $stmt->execute([$userId, $keyword, $content, $mediaType, $mediaPath]);
                } catch (\Throwable $t) {
                    try {
                        $this->db->exec("ALTER TABLE snips ADD COLUMN media_type VARCHAR(20) DEFAULT NULL");
                    } catch (\Throwable $e1) {}
                    try {
                        $this->db->exec("ALTER TABLE snips ADD COLUMN media_path LONGTEXT DEFAULT NULL");
                    } catch (\Throwable $e2) {}

                    $stmt = $this->db->prepare(
                        "INSERT INTO snips (user_id, keyword, content, media_type, media_path) " .
                        "VALUES (?, ?, ?, ?, ?) " .
                        "ON DUPLICATE KEY UPDATE content = VALUES(content), media_type = VALUES(media_type), media_path = VALUES(media_path)"
                    );
                    $stmt->execute([$userId, $keyword, $content, $mediaType, $mediaPath]);
                }

                $keyEsc = htmlspecialchars($keyword, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $typeText = ($mediaType === 'media') ? " 🎙️/🎵 (Media)" : "";
                $message->editText(
                    "✅ Snip <b>\${$keyEsc}</b>{$typeText} uğurla yadda saxlanıldı!\n<i>İstifadə etmək üçün çatda <code>\${$keyEsc}</code> yazın.</i>",
                    parseMode: \danog\MadelineProto\ParseMode::HTML
                );
                return;
            }

            if ($command === 'snips') {
                $stmt = $this->db->prepare("SELECT keyword, media_type FROM snips WHERE user_id = ? ORDER BY keyword ASC");
                $stmt->execute([$userId]);
                $snips = $stmt->fetchAll();

                if (empty($snips)) {
                    $message->editText("ℹ️ Saxlanılmış heç bir snip (qısayol) tapılmadı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $text = "📚 <b>Saxlanılmış Qısayollar (Snips):</b>\n\n";
                foreach ($snips as $s) {
                    $kEsc = htmlspecialchars($s['keyword'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $mIcon = (!empty($s['media_type'])) ? " 🎵" : "";
                    $text .= "• <code>\${$kEsc}</code>{$mIcon}\n";
                }
                $message->editText($text, parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'remsnip' || $command === 'rmsnip') {
                $keyword = isset($args[0]) ? trim(ltrim($args[0], '$')) : '';
                if (empty($keyword)) {
                    $message->editText("⚠️ Silinəcək snip adını qeyd edin: <code>.rmsnip &lt;ad&gt;</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }

                $stmt = $this->db->prepare("DELETE FROM snips WHERE user_id = ? AND keyword = ?");
                $stmt->execute([$userId, $keyword]);

                $keyEsc = htmlspecialchars($keyword, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $message->editText("🗑 Snip <b>\${$keyEsc}</b> silindi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            if ($command === 'resetsnip') {
                $stmt = $this->db->prepare("DELETE FROM snips WHERE user_id = ?");
                $stmt->execute([$userId]);

                $message->editText("✅ <b>Bütün qısayollar (snips) uğurla silindi və sıfırlandı!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }

    public function onMessage($message): void {
        try {
            $text = trim($message->message ?? '');
            if (empty($text) || !str_starts_with($text, '$')) {
                return;
            }

            if (!preg_match('/^\$([a-zA-Z0-9_]+)/', $text, $matches)) {
                return;
            }

            $keyword = $matches[1];
            $userId = $this->getUserId();
            if ($userId === 0) return;

            $stmt = $this->db->prepare("SELECT content, media_type, media_path FROM snips WHERE user_id = ? AND keyword = ?");
            $stmt->execute([$userId, $keyword]);
            $snip = $stmt->fetch();

            if ($snip) {
                $reply = $message->getReply();
                $replyToId = 0;
                if ($reply && isset($reply->id)) {
                    $replyToId = (int) $reply->id;
                } elseif (!empty($message->replyToMsgId)) {
                    $replyToId = (int) $message->replyToMsgId;
                }

                if ($snip['media_type'] === 'media' && !empty($snip['media_path'])) {
                    $rawMedia = base64_decode($snip['media_path']);
                    $mediaObj = @unserialize($rawMedia);
                    
                    if (is_array($mediaObj) || is_object($mediaObj)) {
                        $mediaArray = is_object($mediaObj) ? (array)$mediaObj : $mediaObj;
                        $inputMedia = $this->buildInputMedia($mediaArray) ?? $mediaObj;

                        try {
                            $sendParams = [
                                'peer' => $message->chatId,
                                'media' => $inputMedia,
                                'message' => $snip['content'] ?? '',
                                'parse_mode' => \danog\MadelineProto\ParseMode::HTML
                            ];
                            if ($replyToId > 0) {
                                $sendParams['reply_to_msg_id'] = $replyToId;
                            }

                            $this->api->messages->sendMedia(...$sendParams);

                            // Göndərmə UĞURLU olduqda komanda mesajını sil
                            try {
                                $message->delete();
                            } catch (\Throwable $t) {}
                            
                            return;
                        } catch (\Throwable $sendErr) {
                            $errText = htmlspecialchars($sendErr->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $message->editText("❌ Snip media göndərilərkən xəta: " . $errText, parseMode: \danog\MadelineProto\ParseMode::HTML);
                            return;
                        }
                    }
                }

                if (!empty($snip['content'])) {
                    if ($replyToId > 0) {
                        try {
                            $this->api->messages->sendMessage(
                                peer: $message->chatId,
                                message: $snip['content'],
                                reply_to_msg_id: $replyToId,
                                parse_mode: \danog\MadelineProto\ParseMode::HTML
                            );
                            try {
                                $message->delete();
                            } catch (\Throwable $t) {}
                            return;
                        } catch (\Throwable $tSend) {}
                    }
                    $message->editText($snip['content']);
                }
            }
        } catch (\Throwable $e) {}
    }

    private function buildInputMedia(array $media): ?array {
        if (!isset($media['_'])) {
            return null;
        }

        $type = $media['_'];

        if ($type === 'messageMediaDocument' && isset($media['document']['id'])) {
            $doc = $media['document'];
            return [
                '_' => 'inputMediaDocument',
                'id' => [
                    '_' => 'inputDocument',
                    'id' => $doc['id'],
                    'access_hash' => $doc['access_hash'],
                    'file_reference' => $doc['file_reference'] ?? ''
                ]
            ];
        }

        if ($type === 'messageMediaPhoto' && isset($media['photo']['id'])) {
            $photo = $media['photo'];
            return [
                '_' => 'inputMediaPhoto',
                'id' => [
                    '_' => 'inputPhoto',
                    'id' => $photo['id'],
                    'access_hash' => $photo['access_hash'],
                    'file_reference' => $photo['file_reference'] ?? ''
                ]
            ];
        }

        if (str_starts_with($type, 'inputMedia')) {
            return $media;
        }

        return null;
    }

    private function getUserId(): int {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        if ($user && isset($user['id'])) {
            return (int) $user['id'];
        }
        $stmt = $this->db->prepare('INSERT INTO users (telegram_id, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->execute([$this->ownerId]);
        
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return (int) ($user['id'] ?? 0);
    }
}
