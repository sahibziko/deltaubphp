<?php
use App\BasePlugin;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\ParseMode;

class ForwardPlugin extends BasePlugin {
    public string $name = 'Yönləndirmə və Saxlama';
    public string $description = 'Mesajları kopyalayır, yönləndirir və bir dəfəlik (TTL) baxılan mediaları saxlayır.';
    public array $commands = [
        'copy' => 'Mesajı / mediayanı kopyalayıb Saved Messages-ə göndərir (.copy)',
        'send' => 'Mesajı / mediayanı kopyalayıb Saved Messages-ə göndərir (.send)',
        'forward' => 'Mesajı Saved Messages-ə yönləndirir (.forward)',
        'bs' => 'Bir dəfəlik baxılan şəkil/videonu daimi olaraq Saved Messages-də saxlayır (.bs)',
        'baxim' => 'Mesajı / mediayanı daimi olaraq Saved Messages-də saxlayır (.baxim)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $reply = $message->getReply();

            // .bs və .baxim komandaları
            if ($command === 'bs' || $command === 'baxim') {
                if (!$reply) {
                    $message->editText("⚠️ Lütfən bir dəfəlik baxılan şəkil/video mesajına cavab verərək daxil edin.", parseMode: ParseMode::HTML);
                    return;
                }

                $saved = false;
                $tmpDir = sys_get_temp_dir() . '/bs_' . time() . '_' . rand(1000, 9999);
                if (!is_dir($tmpDir)) {
                    @mkdir($tmpDir, 0777, true);
                }

                $downloadedFile = null;

                // Step 1: Media faylını endiririk
                try {
                    $res = $this->api->downloadToDir($reply, $tmpDir);
                    if (is_string($res) && file_exists($res)) {
                        $downloadedFile = $res;
                    }
                } catch (\Throwable $tDl1) {
                    try {
                        if (is_object($reply) && !empty($reply->media)) {
                            $res = $this->api->downloadToDir($reply->media, $tmpDir);
                            if (is_string($res) && file_exists($res)) {
                                $downloadedFile = $res;
                            }
                        }
                    } catch (\Throwable $tDl2) {}
                }

                // Step 2: LocalFile obyekti yaradıb Saved Messages-ə HTML formatı ilə göndəririk
                if ($downloadedFile && file_exists($downloadedFile)) {
                    $captionText = is_object($reply) ? ($reply->message ?? '') : '';
                    $caption = "🖼 <b>Yadda saxlanılmış Bir Dəfəlik Media (.bs)</b>" . ($captionText !== '' ? "\n\n" . htmlspecialchars($captionText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '');

                    // MadelineProto v8 üçün LocalFile wrapper
                    $fileArg = class_exists(LocalFile::class) ? new LocalFile($downloadedFile) : $downloadedFile;

                    // 1. sendPhoto (HTML formatı ilə)
                    try {
                        $this->api->sendPhoto(
                            peer: 'me',
                            file: $fileArg,
                            caption: $caption,
                            parse_mode: ParseMode::HTML
                        );
                        $saved = true;
                    } catch (\Throwable $tS1) {
                        try {
                            $this->api->sendPhoto('me', $fileArg, $caption, ParseMode::HTML);
                            $saved = true;
                        } catch (\Throwable $tS2) {
                            // 2. sendDocument (Fallback HTML formatı ilə)
                            try {
                                $this->api->sendDocument(
                                    peer: 'me',
                                    file: $fileArg,
                                    caption: $caption,
                                    parse_mode: ParseMode::HTML
                                );
                                $saved = true;
                            } catch (\Throwable $tS3) {
                                try {
                                    $this->api->sendDocument('me', $fileArg, $caption, ParseMode::HTML);
                                    $saved = true;
                                } catch (\Throwable $tS4) {}
                            }
                        }
                    }

                    @unlink($downloadedFile);
                    @rmdir($tmpDir);
                }

                if ($saved) {
                    // Uğurlu olduqda .bs mesajını silirik və çata bildiriş yazmırıq
                    $this->deleteSilent($message);
                } else {
                    $message->editText("❌ <b>Bir dəfəlik media yadda saxlanıla bilmədi.</b>", parseMode: ParseMode::HTML);
                }
                return;
            }

            if (!$reply) {
                $message->editText("⚠️ Lütfən mesaja cavab verərək istifadə edin.", parseMode: ParseMode::HTML);
                return;
            }

            $chatId = is_object($message) ? ($message->chatId ?? 'me') : 'me';
            $replyId = is_object($reply) ? ($reply->id ?? 0) : 0;

            if ($command === 'copy' || $command === 'send') {
                $this->saveMessageToSaved($message, $reply, "✅ Mesaj saxlanılanlara (Saved Messages) kopyalandı.");
                return;
            }

            if ($command === 'forward') {
                try {
                    $this->api->messages->forwardMessages(
                        from_peer: $chatId,
                        id: [(int)$replyId],
                        to_peer: 'me'
                    );
                    $message->editText("✅ Mesaj saxlanılanlara yönləndirildi.", parseMode: ParseMode::HTML);
                } catch (\Throwable $t) {
                    $this->saveMessageToSaved($message, $reply, "✅ Mesaj saxlanılanlara kopyalandı.");
                }
                return;
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err, parseMode: ParseMode::HTML);
        }
    }

    private function deleteSilent($message): void {
        try {
            if (is_object($message) && method_exists($message, 'delete')) {
                $message->delete(revoke: true);
            }
        } catch (\Throwable $t) {
            try {
                if (is_object($message) && method_exists($message, 'delete')) {
                    $message->delete();
                }
            } catch (\Throwable $t2) {}
        }
    }

    private function saveMessageToSaved($message, $reply, string $successMsg): void {
        try {
            $chatId = is_object($message) ? ($message->chatId ?? 'me') : 'me';
            $replyId = is_object($reply) ? ($reply->id ?? 0) : 0;
            $replyMedia = is_object($reply) ? ($reply->media ?? null) : null;
            $replyText = is_object($reply) ? ($reply->message ?? '') : '';

            if (!empty($replyMedia)) {
                $mediaObj = $replyMedia;
                if (is_object($mediaObj) && method_exists($mediaObj, 'toTL')) {
                    try {
                        $mediaObj = $mediaObj->toTL();
                    } catch (\Throwable $t) {}
                }

                $this->api->messages->sendMedia(
                    peer: 'me',
                    media: $mediaObj,
                    message: $replyText,
                    parse_mode: ParseMode::HTML
                );
            } else {
                $this->api->messages->sendMessage(
                    peer: 'me',
                    message: $replyText,
                    parse_mode: ParseMode::HTML
                );
            }

            $message->editText($successMsg, parseMode: ParseMode::HTML);
        } catch (\Throwable $t) {
            try {
                $chatId = is_object($message) ? ($message->chatId ?? 'me') : 'me';
                $replyId = is_object($reply) ? ($reply->id ?? 0) : 0;
                $this->api->messages->forwardMessages(
                    from_peer: $chatId,
                    id: [(int)$replyId],
                    to_peer: 'me'
                );
                $message->editText($successMsg, parseMode: ParseMode::HTML);
            } catch (\Throwable $t2) {
                $err = htmlspecialchars($t2->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
                $message->editText("❌ Kopyalarkən xəta: " . $err, parseMode: ParseMode::HTML);
            }
        }
    }
}
