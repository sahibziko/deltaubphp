<?php
/**
 * Delta PHP - Diaqnostika testi
 * Bu faylı public/ qovluğuna at, brauzerdə aç
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Delta PHP Diaqnostika</h2>";

// 1. PHP versiyası
echo "<p>✅ PHP versiya: " . phpversion() . "</p>";

// 2. Composer autoload
echo "<h3>📦 Composer</h3>";
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "<p>✅ vendor/autoload.php tapıldı</p>";
    require_once $autoloadPath;
    
    // MadelineProto yoxla
    if (class_exists('danog\MadelineProto\API')) {
        echo "<p>✅ MadelineProto yüklənib</p>";
    } else {
        echo "<p>❌ MadelineProto tapılmadı!</p>";
    }

    // App sinifləri
    if (class_exists('App\Database')) {
        echo "<p>✅ App\Database sinfi tapıldı</p>";
    } else {
        echo "<p>❌ App\Database sinfi tapılmadı!</p>";
    }
} else {
    echo "<p>❌ vendor/autoload.php TAPILMADI! composer install lazımdır</p>";
    echo "<p>Gözlənilən yol: " . realpath(__DIR__ . '/..') . "/vendor/autoload.php</p>";
}

// 3. Config faylları
echo "<h3>⚙️ Config</h3>";
$appConfigPath = __DIR__ . '/../config/app.php';
if (file_exists($appConfigPath)) {
    $config = require $appConfigPath;
    echo "<p>✅ config/app.php tapıldı</p>";
    echo "<p>API ID: " . ($config['api_id'] ?: '<b style=\"color:red\">BOŞ! my.telegram.org-dan al</b>') . "</p>";
    echo "<p>API Hash: " . ($config['api_hash'] ? '***' . substr($config['api_hash'], -4) : '<b style="color:red">BOŞ!</b>') . "</p>";
} else {
    echo "<p>❌ config/app.php tapılmadı!</p>";
}

$dbConfigPath = __DIR__ . '/../config/database.php';
if (file_exists($dbConfigPath)) {
    $dbConfig = require $dbConfigPath;
    echo "<p>✅ config/database.php tapıldı</p>";
    echo "<p>DB Host: {$dbConfig['host']}</p>";
    echo "<p>DB Name: {$dbConfig['database']}</p>";
    echo "<p>DB User: {$dbConfig['username']}</p>";
} else {
    echo "<p>❌ config/database.php tapılmadı!</p>";
}

// 4. DB bağlantısı
echo "<h3>🗄️ MySQL Bağlantısı</h3>";
if (isset($dbConfig)) {
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        echo "<p>✅ MySQL bağlantısı uğurlu!</p>";
        
        // Cədvəlləri yoxla
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (count($tables) > 0) {
            echo "<p>✅ Cədvəllər tapıldı: " . implode(', ', $tables) . "</p>";
        } else {
            echo "<p>❌ Cədvəllər BOŞ! schema.sql import edilməyib. phpMyAdmin-dən import et.</p>";
        }
    } catch (PDOException $e) {
        echo "<p>❌ MySQL xətası: <b>" . $e->getMessage() . "</b></p>";
    }
}

// 5. Qovluq icazələri
echo "<h3>📂 Qovluq İcazələri</h3>";
$dirs = ['sessions', 'logs', 'plugins/custom'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (is_dir($path)) {
        echo "<p>" . (is_writable($path) ? "✅" : "❌ Yazma icazəsi yoxdur!") . " $dir/ " . substr(sprintf('%o', fileperms($path)), -4) . "</p>";
    } else {
        echo "<p>❌ $dir/ qovluğu yoxdur!</p>";
    }
}

// 6. PHP extensionlar
echo "<h3>🧩 PHP Extensions</h3>";
$required = ['mbstring', 'xml', 'json', 'fileinfo', 'gmp', 'openssl', 'iconv', 'gd', 'pdo_mysql'];
foreach ($required as $ext) {
    echo "<p>" . (extension_loaded($ext) ? "✅" : "❌ YOX!") . " $ext</p>";
}

echo "<br><p><b>Bu faylı testdən sonra silməyi unutma!</b></p>";
