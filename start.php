<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\UserManager;
use App\BotHandler;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$config = require __DIR__ . '/config/app.php';
$dbConfig = require __DIR__ . '/config/database.php';

echo "\033[36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n";
echo "\033[36m  Δ Delta PHP Userbot v{$config['version']}\033[0m\n";
echo "\033[36m━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\033[0m\n\n";

try {
    $db = Database::getInstance($dbConfig);
    echo "\033[32m✓ Verilənlər bazasına qoşuldu\033[0m\n";
    
    // Config-i PluginManager-ə cache et
    \App\PluginManager::$appConfig = $config;
} catch (\Exception $e) {
    die("\033[31m✗ DB xətası: " . $e->getMessage() . "\033[0m\n");
}

$userManager = new UserManager($db);

// --user=TELEGRAM_ID parametri
$singleUserId = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--user=')) {
        $singleUserId = (int) substr($arg, 7);
    }
}

if ($singleUserId) {
    $user = $userManager->getUserByTelegramId($singleUserId);
    if (!$user) {
        die("\033[31m✗ İstifadəçi tapılmadı: $singleUserId\033[0m\n");
    }
    $users = [$user];
    echo "Tək istifadəçi: {$user['telegram_id']}\n";
} else {
    $users = $userManager->getAllActiveUsers();
    echo "Aktiv istifadəçilər: " . count($users) . "\n";
}

if (empty($users)) {
    die("\033[33m⚠ Aktiv istifadəçi yoxdur.\033[0m\n");
}

// MadelineProto ayar yaradıcısı funksiyası
$createSettings = function(?int $apiId, ?string $apiHash) use ($config): Settings {
    $targetApiId = ($apiId && $apiId > 0) ? $apiId : (int)$config['api_id'];
    $targetApiHash = (!empty($apiHash)) ? $apiHash : (string)$config['api_hash'];

    $settings = new Settings();
    $settings->setAppInfo(
        (new AppInfo())
            ->setApiId($targetApiId)
            ->setApiHash($targetApiHash)
    );
    return $settings;
};

// Tək istifadəçidirsə sadə yol
if (count($users) === 1) {
    $user = $users[0];
    $sessionPath = $user['session_file'];
    $userSet = $userManager->getUserSettings((int)$user['id']);
    $userApiId = !empty($userSet['api_id']) ? (int)$userSet['api_id'] : null;
    $userApiHash = !empty($userSet['api_hash']) ? (string)$userSet['api_hash'] : null;
    $settings = $createSettings($userApiId, $userApiHash);

    echo "\033[32m✓ Bot işə salınır: {$user['telegram_id']}\033[0m\n";
    echo "  Session: $sessionPath\n";
    if ($userApiId) {
        echo "  API ID: {$userApiId}\n";
    }
    echo "\n";
    
    BotHandler::startAndLoop($sessionPath, $settings);
} else {
    // Çox istifadəçi - Hər istifadəçiyə öz API parametrləri ilə instance yarat
    $apis = [];
    foreach ($users as $user) {
        $sessionPath = $user['session_file'];
        $userSet = $userManager->getUserSettings((int)$user['id']);
        $userApiId = !empty($userSet['api_id']) ? (int)$userSet['api_id'] : null;
        $userApiHash = !empty($userSet['api_hash']) ? (string)$userSet['api_hash'] : null;
        $settings = $createSettings($userApiId, $userApiHash);

        echo "\033[32m✓ Yüklənir: {$user['telegram_id']}" . ($userApiId ? " (API: {$userApiId})" : "") . "\033[0m\n";
        $apis[] = new API($sessionPath, $settings);
    }
    
    echo "\n\033[32m✓ Bütün botlar işə salınır...\033[0m\n\n";
    API::startAndLoopMulti($apis, BotHandler::class);
}
