<?php
use App\BasePlugin;

class MelumatPlugin extends BasePlugin {
    public string $name = 'Hesab Statistikası';
    public string $description = 'Hesabınızın ətraflı statistikalarını göstərir (.melumat)';
    public array $commands = [
        'melumat' => 'Hesabınızın ətraflı statistikasını göstərir (.melumat)',
        'stats' => 'Hesabınızın ətraflı statistikasını göstərir (.stats)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $startTime = microtime(true);
            try {
                $message->editText("<code>Məlumat toplanana qədər gözləyin...</code>", parseMode: \danog\MadelineProto\ParseMode::HTML);
            } catch (\Throwable $t) {}

            $private_chats = 0;
            $bots = 0;
            $groups = 0;
            $broadcast_channels = 0;
            $admin_in_groups = 0;
            $creator_in_groups = 0;
            $admin_in_broadcast_channels = 0;
            $creator_in_channels = 0;
            $unread = 0;
            $unread_mentions = 0;

            // Bütün dialoqları gətiririk
            $dialogs = $this->api->getFullDialogs();

            foreach ($dialogs as $dialog) {
                $unread += (int) ($dialog['unread_count'] ?? 0);
                $unread_mentions += (int) ($dialog['unread_mentions_count'] ?? 0);

                $peer = $dialog['peer'] ?? [];
                $peerType = $peer['_'] ?? '';

                try {
                    $info = $this->api->getInfo($peer);
                    $type = $info['type'] ?? '';

                    if ($type === 'user') {
                        $private_chats++;
                    } elseif ($type === 'bot') {
                        $private_chats++;
                        $bots++;
                    } elseif ($type === 'chat') {
                        $groups++;
                        $chatData = $info['Chat'] ?? [];
                        $isCreator = !empty($chatData['creator']);
                        $isAdmin = $isCreator || !empty($chatData['admin_rights']);
                        if ($isCreator) $creator_in_groups++;
                        if ($isAdmin) $admin_in_groups++;
                    } elseif ($type === 'supergroup') {
                        $groups++;
                        $chatData = $info['Chat'] ?? [];
                        $isCreator = !empty($chatData['creator']);
                        $isAdmin = $isCreator || !empty($chatData['admin_rights']);
                        if ($isCreator) $creator_in_groups++;
                        if ($isAdmin) $admin_in_groups++;
                    } elseif ($type === 'channel') {
                        $broadcast_channels++;
                        $chatData = $info['Chat'] ?? [];
                        $isCreator = !empty($chatData['creator']);
                        $isAdmin = $isCreator || !empty($chatData['admin_rights']);
                        if ($isCreator) $creator_in_channels++;
                        if ($isAdmin) $admin_in_broadcast_channels++;
                    }
                } catch (\Throwable $t) {
                    if ($peerType === 'peerUser') {
                        $private_chats++;
                    } elseif ($peerType === 'peerChat') {
                        $groups++;
                    } elseif ($peerType === 'peerChannel') {
                        $groups++;
                    }
                }
            }

            $stopTime = number_format(microtime(true) - $startTime, 2);

            $me = $this->api->getSelf();
            $meId = $me['id'] ?? $this->ownerId;
            $names = array_filter([$me['first_name'] ?? '', $me['last_name'] ?? '']);
            $fullName = implode(' ', $names);
            if (empty($fullName)) $fullName = "No Name";
            $safeName = htmlspecialchars($fullName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $meMention = "<a href=\"tg://user?id={$meId}\">{$safeName}</a>";

            $contacts = max(0, $private_chats - $bots);
            $adminOnlyGroups = max(0, $admin_in_groups - $creator_in_groups);
            $adminOnlyChannels = max(0, $admin_in_broadcast_channels - $creator_in_channels);

            $response  = "🔸 <b>Statistikan</b> {$meMention}\n\n";
            $response .= "<b>Şəxsi mesajların :</b> {$private_chats}\n";
            $response .= "   📊 <code>Kontaktlar : {$contacts}</code>\n";
            $response .= "   📊 <code>Botlar : {$bots}</code>\n";
            $response .= "<b>Qruplar :</b> {$groups}\n\n";
            $response .= "<b>Kanallar :</b> {$broadcast_channels}\n\n";
            $response .= "<b>Admin olduğun qruplar :</b> {$admin_in_groups}\n";
            $response .= "   📊 <code>Sahibi olduğun qruplar : {$creator_in_groups}</code>\n";
            $response .= "   📊 <code>Admin olduğun qruplar : {$adminOnlyGroups}</code>\n";
            $response .= "<b>Admin olduğun kanallar :</b> {$admin_in_broadcast_channels}\n";
            $response .= "   📊 <code>Qurucu olduğun kanallar : {$creator_in_channels}</code>\n";
            $response .= "   📊 <code>Admin olduğun kanallar : {$adminOnlyChannels}</code>\n";
            $response .= "✉️ Oxunmamış Mesajlar : {$unread}\n\n";
            $response .= "📧 Baxılmamış tağlar : {$unread_mentions}\n\n";
            $response .= "<i>Bunları hesablamaq üçün {$stopTime} saniyə vaxt sərf olundu</i>";

            $message->editText($response, parseMode: \danog\MadelineProto\ParseMode::HTML);

        } catch (\Throwable $e) {
            try {
                $message->editText("❌ <b>Xəta:</b> " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), parseMode: \danog\MadelineProto\ParseMode::HTML);
            } catch (\Throwable $t) {}
        }
    }
}
