<?php
/**
 * Delta PHP Userbot - Plugins API
 * Plugin siyahısı, toggle, prefix dəyişmə
 */
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\UserManager;

function respond(array $data): never {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'error' => 'Giriş etməmisiniz']);
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $config = require __DIR__ . '/../../config/app.php';
    $db = Database::getInstance($dbConfig);
    $userManager = new UserManager($db);

    $user = $userManager->getUserById($userId);
    if (!$user) {
        throw new Exception('İstifadəçi tapılmadı');
    }

    // ─── Plugin siyahısı ───
    if ($action === 'list') {
        // DB-dən istifadəçinin pluginlərini çək
        $stmt = $db->prepare(
            "SELECT plugin_name, is_custom, enabled FROM user_plugins WHERE user_id = ? ORDER BY plugin_name"
        );
        $stmt->execute([$userId]);
        $dbPlugins = $stmt->fetchAll();

        // Default plugin fayllarından metadata çək
        $defaultDir = $config['plugins_dir'];
        $pluginMeta = [];

        if (is_dir($defaultDir)) {
            foreach (glob($defaultDir . '/*.php') as $file) {
                $className = basename($file, '.php');
                
                // Fayldan metadata çıxar (class-ı load etmədən)
                $content = file_get_contents($file);
                
                $name = $className;
                if (preg_match("/public\s+string\s+\\\$name\s*=\s*['\"](.+?)['\"]/", $content, $m)) {
                    $name = $m[1];
                }
                
                $description = '';
                if (preg_match("/public\s+string\s+\\\$description\s*=\s*['\"](.+?)['\"]/", $content, $m)) {
                    $description = $m[1];
                }
                
                $version = '1.0.0';
                if (preg_match("/public\s+string\s+\\\$version\s*=\s*['\"](.+?)['\"]/", $content, $m)) {
                    $version = $m[1];
                }

                // Komandaları çıxar
                $commands = [];
                if (preg_match("/public\s+array\s+\\\$commands\s*=\s*\[(.+?)\]/s", $content, $m)) {
                    preg_match_all("/['\"](\w+)['\"]\s*=>/", $m[1], $cmdMatches);
                    $commands = $cmdMatches[1] ?? [];
                }

                $pluginMeta[$className] = [
                    'class_name' => $className,
                    'name' => $name,
                    'description' => $description,
                    'version' => $version,
                    'commands' => $commands,
                    'is_custom' => 0
                ];
            }
        }

        // Custom plugin fayllarını da yoxla
        $customDir = $config['custom_plugins_dir'] . '/' . $user['telegram_id'];
        if (is_dir($customDir)) {
            foreach (glob($customDir . '/*.php') as $file) {
                $className = basename($file, '.php');
                $content = file_get_contents($file);
                
                $name = $className;
                if (preg_match("/public\s+string\s+\\\$name\s*=\s*['\"](.+?)['\"]/", $content, $m)) {
                    $name = $m[1];
                }
                
                $description = '';
                if (preg_match("/public\s+string\s+\\\$description\s*=\s*['\"](.+?)['\"]/", $content, $m)) {
                    $description = $m[1];
                }

                $pluginMeta[$className] = [
                    'class_name' => $className,
                    'name' => $name,
                    'description' => $description,
                    'version' => '1.0.0',
                    'commands' => [],
                    'is_custom' => 1
                ];
            }
        }

        // DB məlumatları ilə birləşdir
        $plugins = [];
        $dbMap = [];
        foreach ($dbPlugins as $dbp) {
            $dbMap[$dbp['plugin_name']] = $dbp;
        }

        foreach ($pluginMeta as $className => $meta) {
            $enabled = 1;
            $isCustom = $meta['is_custom'];
            
            if (isset($dbMap[$className])) {
                $enabled = (int) $dbMap[$className]['enabled'];
                $isCustom = (int) $dbMap[$className]['is_custom'];
            }

            $plugins[] = [
                'class_name' => $className,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'version' => $meta['version'],
                'commands' => $meta['commands'],
                'enabled' => $enabled,
                'is_custom' => $isCustom
            ];
        }

        respond(['success' => true, 'plugins' => $plugins]);
    }

    // ─── Plugin toggle ───
    if ($action === 'toggle') {
        $pluginName = $_POST['plugin_name'] ?? '';
        $enabled = (int) ($_POST['enabled'] ?? 0);

        if (empty($pluginName)) {
            throw new Exception('Plugin adı göstərilməyib');
        }

        // Əvvəlcə mövcudluğunu yoxla
        $stmt = $db->prepare("SELECT id FROM user_plugins WHERE user_id = ? AND plugin_name = ?");
        $stmt->execute([$userId, $pluginName]);
        
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE user_plugins SET enabled = ? WHERE user_id = ? AND plugin_name = ?");
            $stmt->execute([$enabled, $userId, $pluginName]);
        } else {
            $stmt = $db->prepare("INSERT INTO user_plugins (user_id, plugin_name, enabled) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $pluginName, $enabled]);
        }

        $action_text = $enabled ? 'aktiv edildi' : 'deaktiv edildi';
        $userManager->log($userId, 'plugin_toggle', "$pluginName $action_text");

        respond(['success' => true, 'message' => "Plugin $action_text"]);
    }

    // ─── Prefix dəyiş ───
    if ($action === 'set_prefix') {
        $prefix = $_POST['prefix'] ?? '.';
        
        if (strlen($prefix) > 5 || strlen($prefix) < 1) {
            throw new Exception('Prefix 1-5 simvol olmalıdır');
        }

        $userManager->setPrefix($user['telegram_id'], $prefix);
        $userManager->log($userId, 'prefix_change', "Prefix dəyişdirildi: $prefix");

        respond(['success' => true, 'message' => "Prefix dəyişdirildi: $prefix"]);
    }

    throw new Exception('Naməlum əməliyyat');

} catch (Exception $e) {
    respond(['success' => false, 'error' => $e->getMessage()]);
}
