<?php
use App\BasePlugin;

class TagAllPlugin extends BasePlugin {
    public string $name = 'Hamını etiketlə';
    public string $description = 'Qrupdakı hər kəsi 5-5 etiketləyir (.tag, .tagall, .1tag, .tagstop).';
    public array $commands = [
        'tag' => '5-5 nəfər mention ilə etiketləyir (.tag [mesaj])',
        'tagall' => '5-5 nəfər mention ilə etiketləyir (.tagall [mesaj])',
        '1tag' => 'Tək-tək (1-1) hər mesaja 1 nəfər etiketləyir (.1tag [mesaj])',
        'tagstop' => 'Etiketləmə prosesini dərhal dayandırır (.tagstop)',
        'canceltag' => 'Etiketləmə prosesini dərhal dayandırır (.canceltag)',
        'stoptag' => 'Etiketləmə prosesini dərhal dayandırır (.stoptag)',
        'etag' => 'Gizli görünməz etiketləyir (.etag [mesaj])',
        'etagall' => 'Gizli görünməz etiketləyir (.etagall [mesaj])',
        'mentionall' => 'Gizli görünməz etiketləyir (.mentionall [mesaj])'
    ];

    /** Multi-user dəstəkli etiketləmə izləmə: [ownerId => [chatId => bool]] */
    public static array $runningTags = [];

    public static function isTaggingActive(int $ownerId, int $chatId): bool {
        return !empty(self::$runningTags[$ownerId][$chatId]);
    }

    public static function stopTagging(int $ownerId, int $chatId): void {
        self::$runningTags[$ownerId][$chatId] = false;
    }

    public static function stopAllForUser(int $ownerId): int {
        $count = 0;
        if (!empty(self::$runningTags[$ownerId])) {
            foreach (self::$runningTags[$ownerId] as $chatId => $active) {
                if ($active) {
                    self::$runningTags[$ownerId][$chatId] = false;
                    $count++;
                }
            }
        }
        return $count;
    }

    public function onCommand($message, string $command, array $args): void {
        try {
            $chatId = (int) ($message->chatId ?? 0);
            if ($chatId === 0) return;

            $ownerId = $this->ownerId > 0 ? $this->ownerId : (int)($message->senderId ?? 0);

            // Dayandırma komandaları
            if ($command === 'tagstop' || $command === 'canceltag' || $command === 'stoptag') {
                self::stopTagging($ownerId, $chatId);
                $message->editText("🛑 <b>Etiketləmə prosesi dayandırıldı!</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                return;
            }

            $msgText = trim(implode(' ', $args));
            if ($msgText === '') {
                $msgText = "Diqqət qrup!";
            }

            try {
                $message->delete(revoke: true);
            } catch (\Throwable $t) {}

            $isSingle = ($command === '1tag');
            $isHide = ($command === 'etag' || $command === 'mentionall' || $command === 'etagall');

            // Aktiv tag statusunu təyin edirik
            self::$runningTags[$ownerId][$chatId] = true;

            // Əsas bot axınını dondurmamaq üçün asinxron coroutine başladırıq
            \Amp\async(function () use ($chatId, $ownerId, $msgText, $isSingle, $isHide) {
                $users = [];
                $seenIds = [];

                // 1. Supergroup / Channel üçün səhifələmə ilə çəkmə
                if ($chatId < -1000000000000 || str_starts_with((string)$chatId, '-100')) {
                    $offset = 0;
                    while (count($users) < 1000) {
                        if (!self::isTaggingActive($ownerId, $chatId)) return;
                        try {
                            $res = $this->api->channels->getParticipants(
                                channel: $chatId,
                                filter: ['_' => 'channelParticipantsRecent'],
                                offset: $offset,
                                limit: 100
                            );
                            if (empty($res['users'])) break;
                            $countRes = count($res['users']);
                            foreach ($res['users'] as $u) {
                                $uid = (int) ($u['id'] ?? 0);
                                if ($uid > 0 && !isset($seenIds[$uid])) {
                                    $seenIds[$uid] = true;
                                    $users[] = $u;
                                }
                            }
                            $offset += $countRes;
                            if ($countRes < 100) break;
                        } catch (\Throwable $e) {
                            break;
                        }
                    }

                    // Əgər qrupda üzv siyahısı gizlidirsə, axtarış filtri ilə çəkirik
                    if (count($users) < 40) {
                        $searchLetters = ['a', 'e', 'i', 'o', 'u', 'r', 's', 't', 'm', 'n', 'b', 'd', 'k', 'l', 'v', 'y'];
                        foreach ($searchLetters as $letter) {
                            if (!self::isTaggingActive($ownerId, $chatId)) return;
                            try {
                                $res = $this->api->channels->getParticipants(
                                    channel: $chatId,
                                    filter: ['_' => 'channelParticipantsSearch', 'q' => $letter],
                                    offset: 0,
                                    limit: 100
                                );
                                if (!empty($res['users'])) {
                                    foreach ($res['users'] as $u) {
                                        $uid = (int) ($u['id'] ?? 0);
                                        if ($uid > 0 && !isset($seenIds[$uid])) {
                                            $seenIds[$uid] = true;
                                            $users[] = $u;
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {}
                            if (count($users) >= 500) break;
                        }
                    }
                } else {
                    // 2. Normal (Basic) qruplar üçün getFullChat
                    try {
                        $full = $this->api->messages->getFullChat(chat_id: abs($chatId));
                        if (!empty($full['users'])) {
                            foreach ($full['users'] as $u) {
                                $uid = (int) ($u['id'] ?? 0);
                                if ($uid > 0 && !isset($seenIds[$uid])) {
                                    $seenIds[$uid] = true;
                                    $users[] = $u;
                                }
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                // 3. Əlavə olaraq son çat mesajlarından aktiv istifadəçiləri toplayırıq
                try {
                    $history = $this->api->messages->getHistory(peer: $chatId, limit: 100);
                    if (!empty($history['users'])) {
                        foreach ($history['users'] as $u) {
                            $uid = (int) ($u['id'] ?? 0);
                            if ($uid > 0 && !isset($seenIds[$uid])) {
                                $seenIds[$uid] = true;
                                $users[] = $u;
                            }
                        }
                    }
                } catch (\Throwable $e) {}

                // İstifadəçiləri təmizləyirik və etiketləri hazırlayırıq
                $mentions = [];
                foreach ($users as $u) {
                    $uid = (int) ($u['id'] ?? 0);
                    if ($uid <= 0) continue;
                    if (!empty($u['bot']) || !empty($u['deleted'])) continue;
                    if ($ownerId > 0 && $uid === $ownerId) continue;

                    $fname = trim((string) ($u['first_name'] ?? ''));
                    if ($fname === '') $fname = trim((string) ($u['last_name'] ?? ''));
                    if ($fname === '') $fname = !empty($u['username']) ? '@' . $u['username'] : 'İstifadəçi';
                    $fname = mb_substr($fname, 0, 16, 'UTF-8');
                    $safeName = htmlspecialchars($fname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                    if ($isHide) {
                        $mentions[] = "<a href=\"tg://user?id={$uid}\">&#8203;</a>";
                    } else {
                        $mentions[] = "<a href=\"tg://user?id={$uid}\">{$safeName}</a>";
                    }
                }

                if (empty($mentions)) {
                    try {
                        $this->api->sendMessage($chatId, "⚠️ <b>Bu qrupda etiketlənəcək başqa istifadəçi tapılmadı.</b>", parseMode: \danog\MadelineProto\ParseMode::HTML);
                    } catch (\Throwable $t) {}
                    self::stopTagging($ownerId, $chatId);
                    return;
                }

                $chunkSize = $isSingle ? 1 : 5;
                $chunks = array_chunk($mentions, $chunkSize);
                $cleanMsg = htmlspecialchars($msgText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $delay = $isSingle ? 1.5 : 2.5;

                // İstifadəçi .tagstop və ya .restart yazana qədər dövr edir
                while (self::isTaggingActive($ownerId, $chatId)) {
                    foreach ($chunks as $chunk) {
                        if (!self::isTaggingActive($ownerId, $chatId)) {
                            break 2;
                        }

                        if ($isHide) {
                            $text = $cleanMsg . " " . implode('', $chunk);
                        } elseif ($isSingle) {
                            $text = "📣 <b>" . $cleanMsg . "</b> " . $chunk[0];
                        } else {
                            $text = "📣 <b>" . $cleanMsg . "</b>\n" . implode(', ', $chunk);
                        }

                        try {
                            $this->api->sendMessage(
                                $chatId,
                                $text,
                                parseMode: \danog\MadelineProto\ParseMode::HTML
                            );
                        } catch (\Throwable $e) {
                            $errMsg = $e->getMessage();
                            if (preg_match('/(?:FLOOD_WAIT_|SLOWMODE_WAIT_)(\d+)/', $errMsg, $m)) {
                                $wait = (int) $m[1] + 1;
                                if ($wait <= 65) {
                                    \Amp\delay($wait);
                                    if (self::isTaggingActive($ownerId, $chatId)) {
                                        try {
                                            $this->api->sendMessage($chatId, $text, parseMode: \danog\MadelineProto\ParseMode::HTML);
                                        } catch (\Throwable $t2) {}
                                    }
                                } else {
                                    self::stopTagging($ownerId, $chatId);
                                    break 2;
                                }
                            }
                        }

                        \Amp\delay($delay);
                    }

                    // Bir tam dövr bitdikdən sonra 3 saniyə gözləyib yenidən başlayır
                    \Amp\delay(3.0);
                }

                self::stopTagging($ownerId, $chatId);
            });

        } catch (\Throwable $e) {}
    }
}
