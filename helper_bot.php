<?php
/**
 * Delta PHP - Qlobal Köməkçi Bot Arxa Fon İşçisi (Worker)
 * Bu skript userbot prosesindən tam müstəqil işləyir və userbotu əsla yavaşlatmır.
 */

$rootDir = __DIR__;
$lockFile = $rootDir . '/sessions/helper_bot.lock';
$cacheFile = $rootDir . '/sessions/plugins_cache.json';

if (!is_dir($rootDir . '/sessions')) {
    @mkdir($rootDir . '/sessions', 0755, true);
}
@file_put_contents($lockFile, getmypid());

$dbConfigFile = $rootDir . '/config/database.php';
if (!file_exists($dbConfigFile)) {
    sleep(5);
    exit(1);
}
$dbConfig = require $dbConfigFile;
if (!is_array($dbConfig)) {
    sleep(5);
    exit(1);
}

// DB qoşulması
try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\Throwable $e) {
    sleep(5);
    exit(1);
}

function getBotToken(PDO $db): string {
    try {
        $stmt = $db->query("SELECT setting_value FROM bot_settings WHERE setting_key = 'helper_bot_token' LIMIT 1");
        $val = $stmt->fetchColumn();
        if (!empty($val)) return trim((string)$val);

        $stmt2 = $db->query("SELECT setting_value FROM user_settings WHERE setting_key = 'helper_bot_token' AND setting_value != '' ORDER BY id DESC LIMIT 1");
        $val2 = $stmt2->fetchColumn();
        if (!empty($val2)) return trim((string)$val2);
    } catch (\Throwable $e) {}
    return '';
}

function callBotApi(string $token, string $method, array $params = []): ?array {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($params),
            'timeout' => 25
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) return null;
    $data = json_decode($res, true);
    return is_array($data) ? $data : null;
}

function loadPlugins(string $cacheFile): array {
    if (!file_exists($cacheFile)) return [];
    $raw = @file_get_contents($cacheFile);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function buildMenuPage(array $plugins, int $page, int $ownerId = 0): array {
    $totalPlugins = count($plugins);
    $perPage = 10;
    $totalPages = max(1, (int) ceil($totalPlugins / $perPage));

    if ($page < 0) $page = 0;
    if ($page >= $totalPages) $page = $totalPages - 1;

    $slice = array_slice($plugins, $page * $perPage, $perPage);
    $keyboard = [];
    $row = [];

    foreach ($slice as $idxOnPage => $plugin) {
        $globalIndex = ($page * $perPage) + $idxOnPage;
        $name = $plugin['name'] ?? $plugin['class'] ?? 'Plugin';
        $cleanName = preg_replace('/\(.*?\)/', '', $name);
        $cleanName = trim(mb_substr($cleanName, 0, 16, 'UTF-8'));
        if ($cleanName === '') $cleanName = $plugin['class'] ?? 'Plugin';

        $row[] = [
            'text' => "📦 " . $cleanName,
            'callback_data' => "plg_{$globalIndex}_{$page}_{$ownerId}"
        ];

        if (count($row) === 2) {
            $keyboard[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) $keyboard[] = $row;

    if ($totalPages > 1) {
        $prevPage = ($page > 0) ? $page - 1 : $totalPages - 1;
        $nextPage = ($page < $totalPages - 1) ? $page + 1 : 0;

        $keyboard[] = [
            ['text' => '◀️ Əvvəlki', 'callback_data' => "page_{$prevPage}_{$ownerId}"],
            ['text' => ($page + 1) . ' / ' . $totalPages, 'callback_data' => "page_{$page}_{$ownerId}"],
            ['text' => 'Sonrakı ▶️', 'callback_data' => "page_{$nextPage}_{$ownerId}"]
        ];
    }

    $keyboard[] = [
        ['text' => '❌ Menyunı Bağla', 'callback_data' => "close_menu_{$ownerId}"]
    ];

    $ownerLine = $ownerId > 0 ? "👤 <b>Əmri verən:</b> <a href=\"tg://user?id={$ownerId}\">İstifadəçi</a> (<code>{$ownerId}</code>)\n" : "";

    $text = "⚡ <b>Delta PHP Userbot — İnteraktiv Kömək Menyusu</b>\n\n" .
            $ownerLine .
            "📌 <i>Ətraflı məlumat və əmrlər üçün istədiyiniz pluginin düyməsinə toxunun:</i>\n\n" .
            "📊 <b>Aktiv pluginlər:</b> {$totalPlugins} ədəd\n" .
            "📄 <b>Səhifə:</b> " . ($page + 1) . " / {$totalPages}\n\n" .
            "🔒 <i>Bu menyu yalnız əmri verən şəxs tərəfindən idarə oluna bilər.</i>";

    return [
        'text' => $text,
        'keyboard' => $keyboard
    ];
}

function buildPluginDetail(array $plugins, int $globalIndex, int $returnPage, int $ownerId = 0): array {
    $plugin = $plugins[$globalIndex] ?? null;
    if (!$plugin) {
        return [
            'text' => "⚠️ <b>Plugin tapılmadı.</b>",
            'keyboard' => [[['text' => '🔙 Geri', 'callback_data' => "page_{$returnPage}_{$ownerId}"]]]
        ];
    }

    $pName = htmlspecialchars($plugin['name'] ?? $plugin['class'] ?? 'Plugin', ENT_QUOTES, 'UTF-8');
    $pDesc = htmlspecialchars($plugin['description'] ?? 'Təsvir yoxdur.', ENT_QUOTES, 'UTF-8');
    $pClass = htmlspecialchars($plugin['class'] ?? 'Plugin', ENT_QUOTES, 'UTF-8');

    $text = "📖 <b>Plugin:</b> {$pName}\n" .
            "🏷 <b>Fayl:</b> <code>{$pClass}.php</code>\n" .
            "📝 <b>Təsvir:</b> {$pDesc}\n\n" .
            "⚙️ <b>Komandalar və İstifadə:</b>\n";

    if (!empty($plugin['commands']) && is_array($plugin['commands'])) {
        foreach ($plugin['commands'] as $cmd => $desc) {
            $cName = htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8');
            $cDesc = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
            $text .= "• <code>{$cName}</code> — {$cDesc}\n";
        }
    } else {
        $text .= "<i>Bu plugin üçün birbaşa komanda yoxdur (arxa fonda xidmət edir).</i>\n";
    }

    $text .= "\n🔒 <i>Yalnız əmri verən istifadəçi idarə edə bilər.</i>";

    $keyboard = [
        [
            ['text' => "🔙 Geri (Səhifə " . ($returnPage + 1) . ")", 'callback_data' => "page_{$returnPage}_{$ownerId}"]
        ]
    ];

    return [
        'text' => $text,
        'keyboard' => $keyboard
    ];
}

$updateOffset = 0;

while (true) {
    try {
        $token = getBotToken($db);
        if (empty($token)) {
            sleep(5);
            continue;
        }

        $plugins = loadPlugins($cacheFile);

        $updates = callBotApi($token, 'getUpdates', [
            'offset' => $updateOffset,
            'limit' => 20,
            'timeout' => 15
        ]);

        if (!empty($updates['ok']) && !empty($updates['result']) && is_array($updates['result'])) {
            foreach ($updates['result'] as $up) {
                $updateOffset = max($updateOffset, (int)$up['update_id'] + 1);

                // A. İnline sorğu (.delta)
                if (isset($up['inline_query'])) {
                    $iq = $up['inline_query'];
                    $iqId = $iq['id'];
                    $fromId = (int)($iq['from']['id'] ?? 0);
                    $q = trim($iq['query'] ?? '');

                    $ownerId = $fromId;
                    if (preg_match('/^delta_(\d+)/', $q, $m)) {
                        $ownerId = (int)$m[1];
                    }
                    if ($ownerId <= 0) $ownerId = $fromId;

                    $menuData = buildMenuPage($plugins, 0, $ownerId);
                    $results = [
                        [
                            'type' => 'article',
                            'id' => 'delta_menu_' . time(),
                            'title' => '⚡ Delta PHP Userbot İnteraktiv Menyu',
                            'description' => 'Bütün pluginlərin interaktiv səhifəli siyahısı',
                            'input_message_content' => [
                                'message_text' => $menuData['text'],
                                'parse_mode' => 'HTML'
                            ],
                            'reply_markup' => [
                                'inline_keyboard' => $menuData['keyboard']
                            ]
                        ]
                    ];

                    callBotApi($token, 'answerInlineQuery', [
                        'inline_query_id' => $iqId,
                        'results' => $results,
                        'cache_time' => 1,
                        'is_personal' => true
                    ]);
                }

                // B. Düymə klikləri
                if (isset($up['callback_query'])) {
                    $cq = $up['callback_query'];
                    $cqId = $cq['id'];
                    $cqData = $cq['data'] ?? '';
                    $cqFromId = (int)($cq['from']['id'] ?? 0);
                    $inlineMsgId = $cq['inline_message_id'] ?? null;
                    $cqMsg = $cq['message'] ?? null;

                    $expectedOwnerId = 0;
                    $parts = explode('_', $cqData);
                    if (str_starts_with($cqData, 'close_menu')) {
                        $expectedOwnerId = (int)($parts[2] ?? 0);
                    } elseif (str_starts_with($cqData, 'page_')) {
                        $expectedOwnerId = (int)($parts[2] ?? 0);
                    } elseif (str_starts_with($cqData, 'plg_')) {
                        $expectedOwnerId = (int)($parts[3] ?? 0);
                    }

                    if ($expectedOwnerId > 0 && $cqFromId !== $expectedOwnerId) {
                        callBotApi($token, 'answerCallbackQuery', [
                            'callback_query_id' => $cqId,
                            'text' => '⚠️ Bu menyu yalnız əmri verən istifadəçiyə aiddir!',
                            'show_alert' => true
                        ]);
                        continue;
                    }

                    callBotApi($token, 'answerCallbackQuery', ['callback_query_id' => $cqId]);

                    $editParams = [];
                    if (!empty($inlineMsgId)) {
                        $editParams['inline_message_id'] = $inlineMsgId;
                    } elseif ($cqMsg) {
                        $editParams['chat_id'] = $cqMsg['chat']['id'] ?? 0;
                        $editParams['message_id'] = $cqMsg['message_id'] ?? 0;
                    } else {
                        continue;
                    }

                    if (str_starts_with($cqData, 'close_menu')) {
                        callBotApi($token, 'editMessageText', array_merge($editParams, [
                            'text' => "🗑 <b>Kömək menyusu bağlandı.</b>",
                            'parse_mode' => 'HTML'
                        ]));
                        continue;
                    }

                    if (str_starts_with($cqData, 'page_')) {
                        $targetPage = (int) ($parts[1] ?? 0);
                        $menuOwner = $expectedOwnerId > 0 ? $expectedOwnerId : $cqFromId;
                        $menuData = buildMenuPage($plugins, $targetPage, $menuOwner);
                        callBotApi($token, 'editMessageText', array_merge($editParams, [
                            'text' => $menuData['text'],
                            'parse_mode' => 'HTML',
                            'reply_markup' => ['inline_keyboard' => $menuData['keyboard']]
                        ]));
                        continue;
                    }

                    if (str_starts_with($cqData, 'plg_')) {
                        $pIndex = (int) ($parts[1] ?? 0);
                        $returnPage = (int) ($parts[2] ?? 0);
                        $menuOwner = $expectedOwnerId > 0 ? $expectedOwnerId : $cqFromId;

                        $detailData = buildPluginDetail($plugins, $pIndex, $returnPage, $menuOwner);
                        callBotApi($token, 'editMessageText', array_merge($editParams, [
                            'text' => $detailData['text'],
                            'parse_mode' => 'HTML',
                            'reply_markup' => ['inline_keyboard' => $detailData['keyboard']]
                        ]));
                        continue;
                    }
                }
            }
        }
    } catch (\Throwable $t) {
        sleep(2);
    }
}
