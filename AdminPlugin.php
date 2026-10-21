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
        'demote' => 'Adminliyini alır'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = $message->chatId;
            $reply = $message->getReply();
            $target = $reply ? ($reply->senderId ?? null) : (isset($args[0]) ? $args[0] : null);

            if (!$target) {
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
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
