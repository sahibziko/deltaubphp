<?php
use App\BasePlugin;

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
        'demote' => 'Adminliyini alır',
        'elave' => 'İstifadəçini qrupa əlavə edir (.elave @username)',
        'add' => 'İstifadəçini qrupa əlavə edir (.add @username)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = $message->chatId;
            $reply = $message->getReply();
            $target = $reply ? ($reply->senderId ?? null) : (isset($args[0]) ? trim($args[0]) : null);

            if (!$target) {
                if ($command === 'elave' || $command === 'add') {
                    $message->editText("ℹ️ <b>Qrupa əlavə etmək qaydası:</b>\n<code>.elave @username</code> və ya <code>.elave (istifadəçini yanıtlayaraq)</code>\n\n<i>Məsələn:</i> <code>.elave @ilkin</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    return;
                }
                $message->editText("⚠️ Hədəf istifadəçi tapılmadı.");
                return;
            }

            // Target ID sayıya çevirək
            $targetId = 0;
            if (is_numeric($target)) {
                $targetId = (int) $target;
            } else {
                try {
                    $info = $this->api->getInfo($target);
                    $targetId = (int) ($info['bot_api_id'] ?? $info['User']['id'] ?? 0);
                } catch (\Throwable $t) {}
            }

            // Ban/Mute/Kick/Demote əmrlərində Owner (8300069885) və Sudo Admin qoruması
            if (in_array($command, ['ban', 'mute', 'kick', 'demote']) && $targetId > 0 && $this->isProtectedUser($targetId)) {
                $message->editText("⚠️ Üzgünəm, sahibimi / adminimi əngəlləyə və ya idarə edə bilmərəm!", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $targetMention = $targetId > 0 ? $this->formatUserMention($targetId) : "İstifadəçi";

            switch ($command) {
                case 'ban':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'view_messages' => true, 'until_date' => 0]);
                    $message->editText("✅ {$targetMention} ban edildi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'unban':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'until_date' => 0]);
                    $message->editText("✅ {$targetMention} banı açıldı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'mute':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'send_messages' => true, 'until_date' => 0]);
                    $message->editText("✅ {$targetMention} səssizə alındı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'unmute':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'until_date' => 0]);
                    $message->editText("✅ {$targetMention} səssizdən çıxarıldı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'kick':
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'view_messages' => true, 'until_date' => 0]);
                    $this->api->channels->editBanned(channel: $chatId, participant: $target, banned_rights: ['_' => 'chatBannedRights', 'until_date' => 0]);
                    $message->editText("✅ {$targetMention} qrupdan atıldı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'promote':
                    $this->api->channels->editAdmin(channel: $chatId, user_id: $target, admin_rights: ['_' => 'chatAdminRights', 'change_info' => true, 'post_messages' => true, 'edit_messages' => true, 'delete_messages' => true, 'ban_users' => true, 'invite_users' => true, 'pin_messages' => true, 'add_admins' => false]);
                    $message->editText("✅ {$targetMention} admin edildi.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'demote':
                    $this->api->channels->editAdmin(channel: $chatId, user_id: $target, admin_rights: ['_' => 'chatAdminRights']);
                    $message->editText("✅ {$targetMention} adminliyi alındı.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    break;
                case 'elave':
                case 'add':
                    try {
                        $inviteUser = $targetId > 0 ? $targetId : $target;
                        try {
                            $this->api->channels->inviteToChannel(channel: $chatId, users: [$inviteUser]);
                        } catch (\Throwable $tInvite) {
                            $this->api->messages->addChatUser(chat_id: $chatId, user_id: $inviteUser, fwd_limit: 100);
                        }
                        $message->editText("✅ {$targetMention} qrupa əlavə edildi!", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    } catch (\Throwable $eInvite) {
                        $msg = $eInvite->getMessage();
                        if (stripos($msg, 'USER_ALREADY_PARTICIPANT') !== false) {
                            $message->editText("ℹ️ {$targetMention} artıq bu qrupdadır.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        } elseif (stripos($msg, 'USER_PRIVACY_RESTRICTED') !== false) {
                            $message->editText("⚠️ {$targetMention} istifadəçisinin məxfilik (gizlilik) parametrləri onu qrupa əlavə etməyə icazə vermir.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        } elseif (stripos($msg, 'USER_NOT_MUTUAL_CONTACT') !== false) {
                            $message->editText("⚠️ {$targetMention} yalnız qarşılıqlı kontaktların onu qrupa əlavə etməsinə icazə verir.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        } elseif (stripos($msg, 'CHANNELS_TOO_MUCH') !== false || stripos($msg, 'USER_CHANNELS_TOO_MUCH') !== false) {
                            $message->editText("⚠️ {$targetMention} çox sayda qrup və kanala üzvdür (Telegram limiti dolub).", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        } elseif (stripos($msg, 'CHAT_ADMIN_REQUIRED') !== false) {
                            $message->editText("⚠️ Qrupa istifadəçi əlavə etmək üçün admin səlahiyyəti tələb olunur.", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        } else {
                            $err = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
                            $message->editText("❌ Əlavə edilə bilmədi: {$err}", parseMode: \danog\MadelineProto\ParseMode::HTML);
                        }
                    }
                    break;
            }
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
