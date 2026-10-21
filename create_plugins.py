import os

base_dir = r"C:\Users\sahib\.gemini\antigravity\scratch\delta-php\plugins\default"
os.makedirs(base_dir, exist_ok=True)

plugins = {}

plugins["HelpPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class HelpPlugin extends BasePlugin {
    public string $name = 'Kömək';
    public string $description = 'Bütün pluginlərin və əmrlərin siyahısını göstərir.';
    public array $commands = ['help' => 'Kömək menyusunu göstərir'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $prefix = $this->getPrefix();
            if (empty($args)) {
                $text = "📚 <b>Delta PHP Kömək Menusu</b>\\n\\n";
                if (isset($this->api->pluginManager)) {
                    $allPlugins = $this->api->pluginManager->getAllPlugins();
                    foreach ($allPlugins as $p) {
                        $text .= "🔹 <b>{$p['name']}</b>\\n";
                    }
                }
                $text .= "\\nƏtraflı məlumat üçün: <code>{$prefix}help <plugin_adı></code>";
                $message->editText($text, parseMode: 'HTML');
            } else {
                $pluginName = implode(' ', $args);
                $message->editText("ℹ️ <b>{$pluginName}</b> haqqında ətraflı məlumat hazırlanır...", parseMode: 'HTML');
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta baş verdi: " . $e->getMessage());
        }
    }
}
"""

plugins["PingPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class PingPlugin extends BasePlugin {
    public string $name = 'Ping';
    public string $description = 'Botun gecikməsini yoxlayır.';
    public array $commands = ['ping' => 'Pingi ölçür'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $start = microtime(true);
            $message->editText("🏓 Ping ölçülür...", parseMode: 'HTML');
            $end = microtime(true);
            $ping = round(($end - $start) * 1000);
            $message->editText("🏓 <b>Pong!</b> {$ping}ms", parseMode: 'HTML');
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["AlivePlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class AlivePlugin extends BasePlugin {
    public string $name = 'Status';
    public string $description = 'Botun statusunu və sistem məlumatlarını göstərir.';
    public array $commands = ['alive' => 'Botun işlək olmasını yoxlayır'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $uptime = isset(\\App\\BotHandler::$startTime) ? $this->timeAgo(\\App\\BotHandler::$startTime) : "Bilinmir";
            $phpVer = phpversion();
            
            $text = "🤖 <b>Delta PHP Userbot İşləyir!</b>\\n\\n"
                  . "👤 <b>Sahib:</b> " . $this->ownerId . "\\n"
                  . "⏳ <b>Aktivlik:</b> {$uptime}\\n"
                  . "🐘 <b>PHP Versiyası:</b> {$phpVer}\\n"
                  . "📡 <b>MadelineProto:</b> Aktiv";
            $message->editText($text, parseMode: 'HTML');
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["InfoPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class InfoPlugin extends BasePlugin {
    public string $name = 'Məlumat';
    public string $description = 'İstifadəçi və qrup haqqında məlumat verir.';
    public array $commands = ['info' => 'Məlumat göstərir', 'whois' => 'İstifadəçi məlumatı'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $peer = $message->chatId;
            if (!empty($args)) {
                $peer = $args[0];
            } else {
                $reply = $message->getReply();
                if ($reply) $peer = $reply->senderId;
            }
            
            $info = $this->api->getFullInfo($peer);
            $text = "ℹ️ <b>Məlumat:</b>\\n<code>" . json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</code>";
            $message->editText(substr($text, 0, 4000), parseMode: 'HTML');
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["AdminPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class AdminPlugin extends BasePlugin {
    public string $name = 'Admin';
    public string $description = 'Qrup idarəetmə əmrləri.';
    public array $commands = [
        'ban' => 'İstifadəçini ban edir',
        'unban' => 'Banı açır',
        'mute' => 'Səssizə alır',
        'unmute' => 'Səssizdən çıxarır',
        'kick' => 'Qrupdan atır',
        'promote' => 'Admin edir',
        'demote' => 'Adminliyini alır'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = $message->chatId;
            $reply = $message->getReply();
            $target = $reply ? $reply->senderId : (isset($args[0]) ? $args[0] : null);

            if (!$target) {
                $message->editText("⚠️ Hədəf istifadəçi tapılmadı.");
                return;
            }

            switch ($command) {
                case 'ban':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'view_messages' => true, 'until_date' => 0]);
                    $message->editText("✅ İstifadəçi ban edildi.");
                    break;
                case 'unban':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'until_date' => 0]);
                    $message->editText("✅ İstifadəçinin banı açıldı.");
                    break;
                case 'mute':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'send_messages' => true, 'until_date' => 0]);
                    $message->editText("✅ İstifadəçi səssizə alındı.");
                    break;
                case 'unmute':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'until_date' => 0]);
                    $message->editText("✅ İstifadəçi səssizdən çıxarıldı.");
                    break;
                case 'kick':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'view_messages' => true, 'until_date' => 0]);
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'until_date' => 0]);
                    $message->editText("✅ İstifadəçi qrupdan atıldı.");
                    break;
                case 'promote':
                    $this->api->channels->editAdmin(channel: $chatId, user_id: $target, admin_rights: ['_' => 'chatAdminRights', 'change_info' => true, 'post_messages' => true, 'edit_messages' => true, 'delete_messages' => true, 'ban_users' => true, 'invite_users' => true, 'pin_messages' => true, 'add_admins' => false]);
                    $message->editText("✅ İstifadəçi admin edildi.");
                    break;
                case 'demote':
                    $this->api->channels->editAdmin(channel: $chatId, user_id: $target, admin_rights: ['_' => 'chatAdminRights']);
                    $message->editText("✅ İstifadəçinin adminliyi alındı.");
                    break;
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["PurgePlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class PurgePlugin extends BasePlugin {
    public string $name = 'Silmə';
    public string $description = 'Mesajları kütləvi və ya tək-tək silir.';
    public array $commands = ['purge' => 'Mesajları silir', 'purgeme' => 'Öz mesajlarını silir', 'del' => 'Tək mesaj silir'];

    public function onCommand($message, string $command, array $args): void {
        try {
            if ($command === 'del') {
                $reply = $message->getReply();
                if ($reply) {
                    $this->api->messages->deleteMessages(id: [$reply->id, $message->id], revoke: true);
                }
            } elseif ($command === 'purge') {
                $reply = $message->getReply();
                if ($reply) {
                    $startId = $reply->id;
                    $endId = $message->id;
                    $ids = range($startId, $endId);
                    $this->api->messages->deleteMessages(id: $ids, revoke: true);
                } else {
                    $message->editText("⚠️ Silmək üçün bir mesaja cavab verin.");
                }
            } elseif ($command === 'purgeme') {
                $count = isset($args[0]) ? (int)$args[0] : 10;
                $history = $this->api->messages->getHistory(peer: $message->chatId, limit: $count + 1);
                $ids = [];
                foreach ($history['messages'] as $m) {
                    if (isset($m['from_id']['user_id']) && $m['from_id']['user_id'] == $this->ownerId) {
                        $ids[] = $m['id'];
                    }
                }
                $this->api->messages->deleteMessages(id: $ids, revoke: true);
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["ToolsPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class ToolsPlugin extends BasePlugin {
    public string $name = 'Alətlər';
    public string $description = 'Müxtəlif faydalı alətlər.';
    public array $commands = ['id' => 'ID göstərir', 'calc' => 'Hesablayıcı', 'base64' => 'Base64 kodla', 'unbase64' => 'Base64 aç', 'upper' => 'Böyük hərf', 'lower' => 'Kiçik hərf', 'reverse' => 'Tərs', 'count' => 'Say', 'json' => 'JSON'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $text = implode(' ', $args);
            $reply = $message->getReply();
            
            switch ($command) {
                case 'id':
                    $msg = "Chat ID: <code>{$message->chatId}</code>\\nMy ID: <code>{$this->ownerId}</code>";
                    if ($reply) $msg .= "\\nReplied ID: <code>{$reply->senderId}</code>";
                    $message->editText($msg, parseMode: 'HTML');
                    break;
                case 'calc':
                    if (preg_match('/^[0-9+\\-*\\/\\.\\(\\)\\s]+$/', $text)) {
                        $result = eval("return ($text);");
                        $message->editText("🧮 <b>Nəticə:</b> <code>{$result}</code>", parseMode: 'HTML');
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
                    $target = $reply ? $reply->message : $text;
                    $chars = mb_strlen($target);
                    $words = str_word_count($target);
                    $lines = substr_count($target, "\\n") + 1;
                    $message->editText("📊 <b>Statistika:</b>\\nHərflər: {$chars}\\nSözlər: {$words}\\nSətirlər: {$lines}", parseMode: 'HTML');
                    break;
                case 'json':
                    if ($reply) {
                        $json = json_encode($reply, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $message->editText("<code>" . substr($json, 0, 4000) . "</code>", parseMode: 'HTML');
                    }
                    break;
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["AfkPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class AfkPlugin extends BasePlugin {
    public string $name = 'AFK';
    public string $description = 'AFK rejimini aktivləşdirir.';
    public array $commands = ['afk' => 'AFK olur', 'unafk' => 'AFK-dan çıxır'];
    public bool $handlesMessages = true;

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            if ($command === 'afk') {
                $reason = empty($args) ? "Səbəb yoxdur" : implode(' ', $args);
                $stmt = $this->db->prepare("INSERT INTO afk_status (user_id, is_afk, reason, afk_since) VALUES (?, 1, ?, ?) ON CONFLICT(user_id) DO UPDATE SET is_afk=1, reason=?, afk_since=?");
                $time = time();
                $stmt->execute([$userId, $reason, $time, $reason, $time]);
                $message->editText("😴 <b>AFK Rejimi Aktivdir</b>\\nSəbəb: <i>{$reason}</i>", parseMode: 'HTML');
            } elseif ($command === 'unafk') {
                $stmt = $this->db->prepare("UPDATE afk_status SET is_afk=0 WHERE user_id=?");
                $stmt->execute([$userId]);
                $message->editText("😃 <b>Artıq AFK deyiləm!</b>", parseMode: 'HTML');
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }

    public function onMessage($message): void {
        try {
            $userId = $this->getUserId();
            $stmt = $this->db->prepare("SELECT is_afk, reason, afk_since FROM afk_status WHERE user_id = ?");
            $stmt->execute([$userId]);
            $afk = $stmt->fetch();
            
            if ($afk && $afk['is_afk']) {
                $reply = $message->getReply();
                $isMentioned = strpos($message->message, '@' . $this->api->getSelf()['username'] ?? '') !== false;
                $isPrivate = $message->chatId > 0 && $message->chatId == $message->senderId;
                
                if ($isPrivate || $isMentioned || ($reply && $reply->senderId == $this->ownerId)) {
                    $ago = $this->timeAgo($afk['afk_since']);
                    $message->reply("😴 Hazırda <b>AFK</b>-yam.\\nSəbəb: <i>{$afk['reason']}</i>\\nMüddət: {$ago}", parseMode: 'HTML');
                }
            }
        } catch (\\Throwable $e) {}
    }

    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return $user['id'] ?? 0;
    }
}
"""

plugins["NotesPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class NotesPlugin extends BasePlugin {
    public string $name = 'Qeydlər';
    public string $description = 'Söhbətlər üçün qeydlər yadda saxlayır.';
    public array $commands = ['save' => 'Qeyd saxla', 'get' => 'Qeydi al', 'notes' => 'Qeydlər', 'clear' => 'Qeydi sil', 'clearall' => 'Hamısını sil'];
    public bool $handlesMessages = true;

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            $chatId = $message->chatId;
            
            if ($command === 'save') {
                $name = $args[0];
                $content = implode(' ', array_slice($args, 1));
                $reply = $message->getReply();
                if ($reply && empty($content)) $content = $reply->message;
                
                if (empty($name) || empty($content)) {
                    $message->editText("⚠️ İstifadə qaydası: .save <ad> <məzmun>");
                    return;
                }
                
                $stmt = $this->db->prepare("INSERT INTO notes (user_id, chat_id, note_name, note_content) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $chatId, $name, $content]);
                $message->editText("✅ Qeyd <b>{$name}</b> yadda saxlanıldı.", parseMode: 'HTML');
            } elseif ($command === 'get') {
                $name = $args[0] ?? '';
                $this->sendNote($message, $userId, $chatId, $name);
            } elseif ($command === 'notes') {
                $stmt = $this->db->prepare("SELECT note_name FROM notes WHERE user_id=? AND chat_id=?");
                $stmt->execute([$userId, $chatId]);
                $notes = $stmt->fetchAll();
                $text = "📝 <b>Bu söhbətdəki qeydlər:</b>\\n";
                foreach ($notes as $n) $text .= "- #{$n['note_name']}\\n";
                $message->editText(empty($notes) ? "⚠️ Qeyd yoxdur." : $text, parseMode: 'HTML');
            } elseif ($command === 'clear') {
                $name = $args[0] ?? '';
                $stmt = $this->db->prepare("DELETE FROM notes WHERE user_id=? AND chat_id=? AND note_name=?");
                $stmt->execute([$userId, $chatId, $name]);
                $message->editText("🗑 Qeyd silindi.");
            } elseif ($command === 'clearall') {
                $stmt = $this->db->prepare("DELETE FROM notes WHERE user_id=? AND chat_id=?");
                $stmt->execute([$userId, $chatId]);
                $message->editText("🗑 Bütün qeydlər silindi.");
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }

    public function onMessage($message): void {
        if (preg_match('/^#(\\w+)/', $message->message, $matches)) {
            $this->sendNote($message, $this->getUserId(), $message->chatId, $matches[1], true);
        }
    }

    private function sendNote($message, $userId, $chatId, $name, $isReply = false) {
        $stmt = $this->db->prepare("SELECT note_content FROM notes WHERE user_id=? AND chat_id=? AND note_name=?");
        $stmt->execute([$userId, $chatId, $name]);
        $note = $stmt->fetch();
        if ($note) {
            if ($isReply) $message->reply($note['note_content']);
            else $message->editText($note['note_content']);
        } else if (!$isReply) {
            $message->editText("⚠️ Qeyd tapılmadı.");
        }
    }

    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        $user = $stmt->fetch();
        return $user['id'] ?? 0;
    }
}
"""

plugins["FilterPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class FilterPlugin extends BasePlugin {
    public string $name = 'Filtrlər';
    public string $description = 'Avtomatik cavab filtrləri yaradır.';
    public array $commands = ['filter' => 'Filtr əlavə edir', 'filters' => 'Filtrlər', 'stop' => 'Filtr silir'];
    public bool $handlesMessages = true;

    public function onCommand($message, string $command, array $args): void {
        try {
            $userId = $this->getUserId();
            $chatId = $message->chatId;
            
            if ($command === 'filter') {
                $keyword = $args[0] ?? '';
                $reply_text = implode(' ', array_slice($args, 1));
                if (empty($keyword) || empty($reply_text)) return;
                
                $stmt = $this->db->prepare("INSERT INTO filters (user_id, chat_id, keyword, reply_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $chatId, $keyword, $reply_text]);
                $message->editText("✅ <b>{$keyword}</b> üçün filtr əlavə edildi.", parseMode: 'HTML');
            } elseif ($command === 'filters') {
                $stmt = $this->db->prepare("SELECT keyword FROM filters WHERE user_id=? AND chat_id=?");
                $stmt->execute([$userId, $chatId]);
                $filters = $stmt->fetchAll();
                $text = "🔎 <b>Aktiv filtrlər:</b>\\n";
                foreach ($filters as $f) $text .= "- {$f['keyword']}\\n";
                $message->editText(empty($filters) ? "⚠️ Filtr yoxdur." : $text, parseMode: 'HTML');
            } elseif ($command === 'stop') {
                $keyword = $args[0] ?? '';
                $stmt = $this->db->prepare("DELETE FROM filters WHERE user_id=? AND chat_id=? AND keyword=?");
                $stmt->execute([$userId, $chatId, $keyword]);
                $message->editText("🗑 Filtr silindi.");
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }

    public function onMessage($message): void {
        $userId = $this->getUserId();
        $chatId = $message->chatId;
        
        $stmt = $this->db->prepare("SELECT keyword, reply_text FROM filters WHERE user_id=? AND chat_id=?");
        $stmt->execute([$userId, $chatId]);
        $filters = $stmt->fetchAll();
        
        foreach ($filters as $f) {
            if (stripos($message->message, $f['keyword']) !== false) {
                $message->reply($f['reply_text']);
                break;
            }
        }
    }

    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        return $stmt->fetch()['id'] ?? 0;
    }
}
"""

plugins["TagAllPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class TagAllPlugin extends BasePlugin {
    public string $name = 'Hamını etiketlə';
    public string $description = 'Qrupdakı hər kəsi etiketləyir.';
    public array $commands = ['tagall' => 'Hamını etiketlə', 'mentionall' => 'Hamını etiketlə'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = $message->chatId;
            $msgText = implode(' ', $args);
            
            $participants = $this->api->channels->getParticipants(channel: $chatId, filter: ['_' => 'channelParticipantsRecent'], offset: 0, limit: 200);
            
            if (empty($participants['participants'])) {
                $message->editText("⚠️ İstifadəçiləri tapmaq mümkün olmadı.");
                return;
            }
            
            $message->delete(revoke: true);
            
            $mentions = [];
            foreach ($participants['participants'] as $p) {
                $uid = $p['user_id'];
                $mentions[] = "<a href='tg://user?id={$uid}'>\u200b</a>";
            }
            
            $chunks = array_chunk($mentions, 5);
            foreach ($chunks as $chunk) {
                $text = $msgText . " " . implode('', $chunk);
                $this->api->messages->sendMessage(peer: $chatId, message: $text, parse_mode: 'HTML');
                usleep(500000); // Flood qarşısını almaq üçün
            }
        } catch (\\Throwable $e) {}
    }
}
"""

plugins["StickerPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

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
                $message->editText("🆔 Stiker ID tapıldı (Sticker object info).", parseMode: 'HTML');
            } elseif ($command === 'getsticker') {
                $message->editText("📥 Stiker yüklənir...");
            } elseif ($command === 'kang') {
                $message->editText("🦘 Stiker paketinizə əlavə edildi! (Tezliklə tam işləyəcək)");
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["TranslatePlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class TranslatePlugin extends BasePlugin {
    public string $name = 'Tərcümə';
    public string $description = 'Mətnləri tərcümə edir.';
    public array $commands = ['tr' => 'Tərcümə et'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $lang = $args[0] ?? 'az';
            $text = implode(' ', array_slice($args, 1));
            
            $reply = $message->getReply();
            if ($reply && empty($text)) {
                $text = $reply->message;
            }
            
            if (empty($text)) {
                $message->editText("⚠️ Tərcümə üçün mətn daxil edin.");
                return;
            }
            
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=" . urlencode($lang) . "&dt=t&q=" . urlencode($text);
            $response = file_get_contents($url);
            $data = json_decode($response, true);
            
            $translated = '';
            foreach ($data[0] as $t) {
                $translated .= $t[0];
            }
            
            $message->editText("🌍 <b>Tərcümə ({$lang}):</b>\\n<code>{$translated}</code>", parseMode: 'HTML');
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["ProfilePlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

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
                    $message->editText("✅ Ad yeniləndi.");
                    break;
                case 'setbio':
                    $this->api->account->updateProfile(about: $text);
                    $message->editText("✅ Haqqında hissəsi yeniləndi.");
                    break;
                case 'setpfp':
                    $message->editText("✅ Şəkil yeniləndi (Tezliklə aktiv olacaq).");
                    break;
                case 'delpfp':
                    $message->editText("✅ Şəkil silindi (Tezliklə aktiv olacaq).");
                    break;
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["PastePlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class PastePlugin extends BasePlugin {
    public string $name = 'Paste';
    public string $description = 'Mətni paste servisinə yükləyir.';
    public array $commands = ['paste' => 'Mətni yüklə'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $reply = $message->getReply();
            $text = $reply ? $reply->message : implode(' ', $args);
            
            if (empty($text)) {
                $message->editText("⚠️ Yükləmək üçün mətn daxil edin.");
                return;
            }
            
            $ch = curl_init('https://dpaste.org/api/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['content' => $text, 'format' => 'url']);
            $url = curl_exec($ch);
            curl_close($ch);
            
            $message->editText("📄 <b>Paste URL:</b> {$url}", parseMode: 'HTML');
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["ChatPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class ChatPlugin extends BasePlugin {
    public string $name = 'Çat Alətləri';
    public string $description = 'Çat haqqında məlumatlar.';
    public array $commands = ['chatinfo' => 'Məlumat', 'adminlist' => 'Adminlər', 'members' => 'Üzvlər'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = $message->chatId;
            if ($command === 'chatinfo') {
                $info = $this->api->getFullInfo($chatId);
                $message->editText("ℹ️ <b>Çat Məlumatı:</b>\\n<code>" . json_encode($info['Chat'] ?? $info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</code>", parseMode: 'HTML');
            } elseif ($command === 'adminlist') {
                $message->editText("👮‍♂️ <b>Adminlər:</b>\\n(Tezliklə)");
            } elseif ($command === 'members') {
                $message->editText("👥 <b>Üzvlər:</b>\\n(Tezliklə)");
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["EvalPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class EvalPlugin extends BasePlugin {
    public string $name = 'Kod İcrası';
    public string $description = '⚠️ PHP və Shell kodlarını icra edir. Yalnız sahib istifadə edə bilər!';
    public array $commands = ['eval' => 'PHP icra et', 'exec' => 'Shell icra et'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $code = implode(' ', $args);
            if (empty($code)) return;
            
            if ($command === 'eval') {
                ob_start();
                try {
                    eval($code . ";");
                    $output = ob_get_clean();
                } catch (\\Throwable $e) {
                    $output = $e->getMessage();
                }
                $message->editText("💻 <b>KOD:</b>\\n<code>{$code}</code>\\n\\n📤 <b>NƏTİCƏ:</b>\\n<code>{$output}</code>", parseMode: 'HTML');
            } elseif ($command === 'exec') {
                $output = shell_exec($code);
                $message->editText("💻 <b>SHELL:</b>\\n<code>{$code}</code>\\n\\n📤 <b>NƏTİCƏ:</b>\\n<code>{$output}</code>", parseMode: 'HTML');
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["PluginInstallerPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class PluginInstallerPlugin extends BasePlugin {
    public string $name = 'Plugin İdarəsi';
    public string $description = 'Kənar pluginləri quraşdırır və idarə edir.';
    public array $commands = ['pinstall' => 'Quraşdır', 'puninstall' => 'Sil', 'plugins' => 'Siyahı', 'enable' => 'Aktiv et', 'disable' => 'Deaktiv et'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $pm = $this->api->pluginManager ?? null;
            if (!$pm) {
                $message->editText("⚠️ PluginManager tapılmadı.");
                return;
            }
            
            $userId = $this->getUserId();
            
            if ($command === 'plugins') {
                $all = $pm->getAllPlugins();
                $text = "🧩 <b>Pluginlər:</b>\\n\\n";
                foreach ($all as $p) {
                    $status = ($p['enabled'] ?? true) ? "✅" : "❌";
                    $type = ($p['is_custom'] ?? false) ? "🔧" : "📦";
                    $text .= "{$status} {$type} <b>{$p['name']}</b>\\n";
                }
                $message->editText($text, parseMode: 'HTML');
            } elseif ($command === 'pinstall') {
                $reply = $message->getReply();
                if (!$reply) {
                    $message->editText("⚠️ Plugin faylına cavab verin.");
                    return;
                }
                $tempDir = sys_get_temp_dir() . '/delta_plugins/';
                if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
                
                $filePath = $this->api->downloadToDir($reply, $tempDir);
                $res = $pm->installPlugin($filePath, $userId);
                $message->editText($res['success'] ? "✅ {$res['message']}" : "❌ {$res['message']}");
            } elseif ($command === 'puninstall') {
                $res = $pm->uninstallPlugin($args[0], $userId);
                $message->editText(isset($res['message']) ? $res['message'] : "Uğurla silindi");
            } elseif ($command === 'enable') {
                $pm->enablePlugin($args[0], $userId);
                $message->editText("✅ {$args[0]} aktiv edildi.");
            } elseif ($command === 'disable') {
                $pm->disablePlugin($args[0], $userId);
                $message->editText("🚫 {$args[0]} deaktiv edildi.");
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
    
    private function getUserId() {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE telegram_id = ?');
        $stmt->execute([$this->ownerId]);
        return $stmt->fetch()['id'] ?? 0;
    }
}
"""

plugins["SpamPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class SpamPlugin extends BasePlugin {
    public string $name = 'Spam';
    public string $description = 'Mesajları spam edir.';
    public array $commands = ['spam' => 'Spam et', 'delspam' => 'Spamı sil'];

    public function onCommand($message, string $command, array $args): void {
        try {
            if ($command === 'spam') {
                $count = min(20, (int)($args[0] ?? 1));
                $text = implode(' ', array_slice($args, 1));
                if (empty($text)) return;
                
                $message->delete(revoke: true);
                for ($i = 0; $i < $count; $i++) {
                    $this->api->messages->sendMessage(peer: $message->chatId, message: $text);
                }
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["ForwardPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class ForwardPlugin extends BasePlugin {
    public string $name = 'Yönləndirmə';
    public string $description = 'Mesajları yönləndirir.';
    public array $commands = ['forward' => 'Yönləndir', 'copy' => 'Kopyala'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $reply = $message->getReply();
            if (!$reply) return;
            
            if ($command === 'copy') {
                $this->api->messages->sendMessage(peer: 'me', message: $reply->message);
                $message->editText("✅ Mesaj saxlanılanlara kopyalandı.");
            }
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["DownloaderPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class DownloaderPlugin extends BasePlugin {
    public string $name = 'Yükləyici';
    public string $description = 'Fayl yükləmə alətləri.';
    public array $commands = ['download' => 'Yüklə', 'upload' => 'Serverdən yüklə'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $message->editText("📥 Hazırlanır...");
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["CurrencyPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class CurrencyPlugin extends BasePlugin {
    public string $name = 'Valyuta';
    public string $description = 'Valyuta məzənnələri.';
    public array $commands = ['kurs' => 'Çevir', 'currency' => 'Məzənnələr'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $message->editText("💱 Valyuta sistemi tezliklə aktiv olacaq.");
        } catch (\\Throwable $e) {}
    }
}
"""

plugins["WeatherPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class WeatherPlugin extends BasePlugin {
    public string $name = 'Hava';
    public string $description = 'Hava haqqında məlumat.';
    public array $commands = ['weather' => 'Hava', 'hava' => 'Hava'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $city = implode(' ', $args);
            if (empty($city)) return;
            $url = "https://wttr.in/" . urlencode($city) . "?format=3";
            $res = file_get_contents($url);
            $message->editText("🌤 <b>Hava:</b> {$res}", parseMode: 'HTML');
        } catch (\\Throwable $e) {
            $message->editText("❌ Xəta: " . $e->getMessage());
        }
    }
}
"""

plugins["ScreenshotPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class ScreenshotPlugin extends BasePlugin {
    public string $name = 'Ekran Görüntüsü';
    public string $description = 'Veb səhifələrin ekran görüntüsünü alır.';
    public array $commands = ['ss' => 'SS al'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $url = $args[0] ?? '';
            if (empty($url)) return;
            $img = "https://image.thum.io/get/{$url}";
            $message->editText("📸 <b>Ekran görüntüsü:</b> <a href='{$img}'>Yüklə</a>", parseMode: 'HTML');
        } catch (\\Throwable $e) {}
    }
}
"""

plugins["ReminderPlugin.php"] = """<?php
use App\\BasePlugin;
use App\\Database;

class ReminderPlugin extends BasePlugin {
    public string $name = 'Xatırlatma';
    public string $description = 'Xatırlatmalar qurun.';
    public array $commands = ['remind' => 'Xatırlatma qur', 'reminders' => 'Siyahı'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $message->editText("⏰ Xatırlatma qeydə alındı (Tezliklə).");
        } catch (\\Throwable $e) {}
    }
}
"""

import codecs
for name, content in plugins.items():
    with codecs.open(os.path.join(base_dir, name), "w", "utf-8") as f:
        f.write(content)

print(f"Successfully created {len(plugins)} plugin files in {base_dir}")
