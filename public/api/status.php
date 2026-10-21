<?php
/**
 * Delta PHP Userbot - Status API
 * Bot prosesini web panel vasitəsilə start/stop edir
 */
header('Content-Type: application/json; charset=utf-8');
ob_start();
session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\UserManager;
use App\SessionManager;

function respond(array $data): never {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'error' => 'Giriş etməmisiniz']);
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Bot prosesinin işlədiyini yoxla
function isBotRunning(): bool {
    $output = shell_exec("pgrep -f 'php start.php' 2>/dev/null");
    return !empty(trim($output ?? ''));
}

// Botu işə sal
function startBot(): bool {
    $baseDir = dirname(dirname(__DIR__));
    $logFile = $baseDir . '/logs/bot.log';
    $cmd = "cd " . escapeshellarg($baseDir) . " && nohup php start.php >> " . escapeshellarg($logFile) . " 2>&1 & echo $!";
    $pid = trim(shell_exec($cmd) ?? '');
    
    if ($pid && is_numeric($pid)) {
        file_put_contents($baseDir . '/sessions/bot.lock', $pid);
        return true;
    }
    return false;
}

// Botu dayandır
function stopBot(): bool {
    shell_exec("pkill -f 'php start.php' 2>/dev/null");
    $baseDir = dirname(dirname(__DIR__));
    $lockFile = $baseDir . '/sessions/bot.lock';
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    return true;
}

try {
    $dbConfig = require __DIR__ . '/../../config/database.php';
    $config = require __DIR__ . '/../../config/app.php';
    $db = Database::getInstance($dbConfig);
    $userManager = new UserManager($db);
    $sessionManager = new SessionManager($config['sessions_dir']);

    $user = $userManager->getUserById($userId);
    if (!$user) {
        throw new Exception('İstifadəçi tapılmadı');
    }

    // ─── Status al ───
    if ($action === 'get_status') {
        $botRunning = isBotRunning();

        // DB status-u faktiki vəziyyətlə sinhronla
        $realStatus = $botRunning ? 'active' : 'inactive';
        if ($user['status'] !== $realStatus) {
            $userManager->setStatus($user['telegram_id'], $realStatus);
            $user['status'] = $realStatus;
        }

        // Plugin sayını hesabla
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_plugins WHERE user_id = ?");
        $stmt->execute([$userId]);
        $pluginCount = $stmt->fetch()['total'] ?? 0;

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_plugins WHERE user_id = ? AND enabled = 1");
        $stmt->execute([$userId]);
        $activePlugins = $stmt->fetch()['total'] ?? 0;

        // Log sayı
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM logs WHERE user_id = ?");
        $stmt->execute([$userId]);
        $logCount = $stmt->fetch()['total'] ?? 0;

        // Uptime
        $uptime = 'Deaktiv';
        if ($botRunning) {
            $pid = trim(shell_exec("pgrep -f 'php start.php' 2>/dev/null | head -1") ?? '');
            if ($pid) {
                $elapsed = trim(shell_exec("ps -o etimes= -p $pid 2>/dev/null") ?? '');
                if ($elapsed && is_numeric($elapsed)) {
                    $s = (int) $elapsed;
                    $h = floor($s / 3600);
                    $m = floor(($s % 3600) / 60);
                    $uptime = "{$h} saat {$m} dəqiqə";
                }
            }
        }

        respond([
            'success' => true,
            'user' => [
                'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'phone' => $user['phone'],
                'prefix' => $user['prefix'],
            ],
            'status' => $user['status'],
            'plugin_count' => (int) $activePlugins . '/' . (int) $pluginCount,
            'uptime' => $uptime,
            'command_count' => (int) $logCount,
            'bot_running' => $botRunning
        ]);
    }

    // ─── Bot Start/Stop ───
    if ($action === 'toggle_status') {
        $status = $_POST['status'] ?? '';
        
        if ($status === 'active') {
            if (isBotRunning()) {
                respond(['success' => true, 'message' => 'Bot artıq işləyir']);
            }
            
            $started = startBot();
            if ($started) {
                $userManager->setStatus($user['telegram_id'], 'active');
                $userManager->log($userId, 'bot_start', 'Bot panel vasitəsilə işə salındı');
                
                // 2 saniyə gözlə ki proses başlasın
                sleep(2);
                
                respond([
                    'success' => true,
                    'message' => 'Bot uğurla işə salındı!',
                    'status' => 'active'
                ]);
            } else {
                respond(['success' => false, 'error' => 'Bot işə salına bilmədi']);
            }
        } elseif ($status === 'inactive') {
            stopBot();
            $userManager->setStatus($user['telegram_id'], 'inactive');
            $userManager->log($userId, 'bot_stop', 'Bot panel vasitəsilə dayandırıldı');
            
            respond([
                'success' => true,
                'message' => 'Bot dayandırıldı',
                'status' => 'inactive'
            ]);
        }
        
        throw new Exception('Yanlış status dəyəri');
    }

    // ─── Prefix dəyiş ───
    if ($action === 'change_prefix') {
        $newPrefix = $_POST['prefix'] ?? '.';
        if (strlen($newPrefix) > 3) {
            throw new Exception('Prefix 3 simvoldan çox ola bilməz');
        }
        $userManager->updateUser($user['telegram_id'], ['prefix' => $newPrefix]);
        $userManager->log($userId, 'prefix_change', "Prefix: $newPrefix");

        respond(['success' => true, 'message' => "Prefix dəyişdirildi: $newPrefix"]);
    }

    // ─── Logları al ───
    if ($action === 'get_logs') {
        $limit = min((int) ($_GET['limit'] ?? 50), 200);
        $logs = $userManager->getLogs($userId, $limit);

        respond(['success' => true, 'logs' => $logs]);
    }

    // ─── Çıxış ───
    if ($action === 'logout') {
        session_destroy();
        respond([
            'success' => true,
            'message' => 'Uğurla çıxış etdiniz',
            'redirect' => 'install.php'
        ]);
    }

    // ─── Hesabı sil ───
    if ($action === 'delete_account') {
        stopBot();
        $sessionManager->deleteSession($user['telegram_id']);
        $userManager->deleteUser($user['telegram_id']);
        session_destroy();

        respond([
            'success' => true,
            'message' => 'Hesab silindi',
            'redirect' => 'install.php'
        ]);
    }

    throw new Exception('Naməlum əməliyyat');

} catch (Exception $e) {
    respond(['success' => false, 'error' => $e->getMessage()]);
}
