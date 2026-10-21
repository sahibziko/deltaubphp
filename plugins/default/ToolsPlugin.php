<?php
use App\BasePlugin;

class ToolsPlugin extends BasePlugin {
    public string $name = 'Alətlər';
    public string $description = 'Müxtəlif faydalı alətlər və sistem əmrləri.';
    public array $commands = [
        'id' => 'ID göstərir',
        'calc' => 'Hesablayıcı',
        'base64' => 'Base64 kodla',
        'unbase64' => 'Base64 aç',
        'upper' => 'Böyük hərf',
        'lower' => 'Kiçik hərf',
        'reverse' => 'Tərs',
        'count' => 'Say',
        'json' => 'JSON',
        'restart' => 'Userbotun məlumatlarını və plaginlərini kəsintisiz yeniləyir (.restart)',
        'reload' => 'Bot prosesini tam söndürüb yenidən başladır (Yalnız Owner)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $text = implode(' ', $args);
            $reply = $message->getReply();
            
            switch ($command) {
                case 'reload':
                    $senderId = (int) ($message->senderId ?? $this->ownerId);
                    if (!$this->isOwner($senderId) && !$this->isOwner($this->ownerId)) {
                        $message->editText("⚠️ <b>İcazə verilmədi!</b>\nBu əmr yalnız bot sahibinə (Owner) məxsusdur.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        return;
                    }

                    $message->editText("🔄 <b>Delta PHP Userbot tam yenidən başladılır...</b>\n<i>Köhnə proses RAM-dan təmizlənir və bütün sistem yenidən yüklənir.</i>", parseMode: \danog\MadelineProto\ParseMode::HTML);

                    $mention = $this->formatUserMention($senderId);
                    \App\Logger::log("🔰 {$mention} — Userbota müvəffəqiyyətlə restart verildi! — 1 dəqiqə sonra botunuz aktiv olacaq. ✨", $this->db);

                    sleep(1);

                    $rootDir = dirname(dirname(__DIR__));
                    $lockFile = $rootDir . '/sessions/bot.lock';

                    if (file_exists($lockFile)) {
                        @unlink($lockFile);
                    }

                    $myPid = getmypid();
                    if (function_exists('posix_kill') && $myPid > 0) {
                        @posix_kill($myPid, 9);
                    }

                    exit(0);

                case 'restart':
                    // Adi istifadəçilər üçün sürətli və kəsintisiz restart
                    $ownerId = $this->ownerId > 0 ? $this->ownerId : (int)($message->senderId ?? 0);
                    $prefix = $this->getPrefix();

                    // 1. İstifadəçinin bütün aktiv təkrarlanan proseslərini dayandırırıq
                    $stoppedList = [];

                    // Etiketləmə (tagall, tag, 1tag, etag və s.)
                    if (class_exists('TagAllPlugin') && method_exists('TagAllPlugin', 'stopAllForUser')) {
                        $tagsCount = \TagAllPlugin::stopAllForUser($ownerId);
                        if ($tagsCount > 0) $stoppedList[] = "🛑 Etiketləmə (tagall)";
                    }

                    // AutoPP (Canlı saatlı PP)
                    if (class_exists('AutoPpPlugin') && method_exists('AutoPpPlugin', 'stopForUser')) {
                        if (\AutoPpPlugin::stopForUser($ownerId)) {
                            $stoppedList[] = "🛑 AutoPP (Sağ saatlı PP)";
                        }
                    }

                    // Avto Saat və Bio
                    if (class_exists('AutoPlugin') && method_exists('AutoPlugin', 'stopForUser')) {
                        if (\AutoPlugin::stopForUser($ownerId, $this->db)) {
                            $stoppedList[] = "🛑 Avto Saat və Bio";
                        }
                    }

                    // Spam
                    if (class_exists('SpamPlugin') && method_exists('SpamPlugin', 'stopForUser')) {
                        if (\SpamPlugin::stopForUser($ownerId)) {
                            $stoppedList[] = "🛑 Aktiv Spam";
                        }
                    }

                    // 2. İstifadəçinin xüsusi plaginlərini yenidən oxuyuruq
                    $pluginCount = 0;
                    if (isset($this->api->pluginManager)) {
                        try {
                            $this->api->pluginManager->loadCustomPlugins();
                        } catch (\Throwable $t) {}
                        $pluginCount = count($this->api->pluginManager->getAllPlugins());
                    }

                    $stoppedSection = !empty($stoppedList)
                        ? "\n\n<b>Dayandırılan aktiv proseslər:</b>\n• " . implode("\n• ", $stoppedList)
                        : "";

                    $msg = "⚡ <b>Delta PHP Userbot uğurla yeniləndi!</b>\n\n" .
                           "👤 <b>İstifadəçi ID:</b> <code>{$ownerId}</code>\n" .
                           "🔘 <b>Prefiks:</b> <code>{$prefix}</code>\n" .
                           "📦 <b>Aktiv Pluginlər:</b> {$pluginCount} ədəd\n" .
                           "🗄 <b>Baza və Keş:</b> Yeniləndi ⚡" .
                           $stoppedSection . "\n\n" .
                           "<i>Bütün arxa fon dövrləri dayandırıldı və bot kəsintisiz hazır vəziyyətə gətirildi.</i>";

                    $message->editText($msg, parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;

                case 'id':
                    $chatId = is_object($message) ? ($message->chatId ?? 'me') : 'me';
                    $msg = "Chat ID: <code>{$chatId}</code>\nMy ID: <code>{$this->ownerId}</code>";
                    if ($reply && is_object($reply) && isset($reply->senderId)) {
                        $msg .= "\nReplied ID: <code>{$reply->senderId}</code>";
                    }
                    $message->editText($msg, parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;

                case 'calc':
                    if (preg_match('/^[0-9+\\-*\/\\.\\(\\)\\s]+$/', $text)) {
                        $result = eval("return ($text);");
                        $message->editText("🧮 <b>Nəticə:</b> <code>{$result}</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    } else {
                        $message->editText("⚠️ Yalnız riyazi ifadələrə icazə verilir.");
                    }
                    break;

                case 'base64':
                    $message->editText("🔐 " . base64_encode($text));
                    break;

                case 'unbase64':
                    $message->editText("🔓 " . base64_decode($text));
                    break;

                case 'upper':
                    $message->editText(mb_strtoupper($text));
                    break;

                case 'lower':
                    $message->editText(mb_strtolower($text));
                    break;

                case 'reverse':
                    $message->editText(strrev($text));
                    break;

                case 'count':
                    $target = $reply ? (is_object($reply) ? ($reply->message ?? '') : '') : $text;
                    $chars = mb_strlen($target);
                    $words = str_word_count($target);
                    $lines = substr_count($target, "\n") + 1;
                    $message->editText("📊 <b>Statistika:</b>\nHərflər: {$chars}\nSözlər: {$words}\nSətirlər: {$lines}", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;

                case 'json':
                    if ($reply) {
                        $json = json_encode($reply, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $message->editText("<code>" . htmlspecialchars(substr((string)$json, 0, 3900), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
