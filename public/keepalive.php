<?php
/**
 * Delta PHP - Bulletproof Web Keepalive Endpoint
 * Prevents duplicate process accumulation & cPanel NPROC (100/100) exhaustion.
 */

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$rootDir = dirname(__DIR__);
$lockFile = $rootDir . '/sessions/bot.lock';
$logFile = $rootDir . '/logs/bot.log';

if (!is_dir($rootDir . '/sessions')) {
    @mkdir($rootDir . '/sessions', 0755, true);
}
if (!is_dir($rootDir . '/logs')) {
    @mkdir($rootDir . '/logs', 0755, true);
}

// 1. Check via pgrep if start.php process is already running for this codebase
$runningPids = [];

if (function_exists('shell_exec')) {
    $pgrepOut = @shell_exec("pgrep -f " . escapeshellarg($rootDir . '/start.php') . " 2>/dev/null");
    if (empty($pgrepOut)) {
        $pgrepOut = @shell_exec("pgrep -f 'start.php' 2>/dev/null");
    }
    if (!empty($pgrepOut)) {
        $lines = explode("\n", trim((string)$pgrepOut));
        foreach ($lines as $line) {
            $p = (int) trim($line);
            if ($p > 0 && $p !== getmypid()) {
                $runningPids[] = $p;
            }
        }
    }
}

// 2. Fallback check via lockfile /proc
if (empty($runningPids) && file_exists($lockFile)) {
    $pidFromLock = (int) @file_get_contents($lockFile);
    if ($pidFromLock > 0) {
        if (file_exists("/proc/$pidFromLock/cmdline")) {
            $cmd = (string) @file_get_contents("/proc/$pidFromLock/cmdline");
            if (str_contains($cmd, 'start.php')) {
                $runningPids[] = $pidFromLock;
            } else {
                @unlink($lockFile);
            }
        } elseif (function_exists('posix_kill') && @posix_kill($pidFromLock, 0)) {
            $runningPids[] = $pidFromLock;
        } else {
            @unlink($lockFile);
        }
    } else {
        @unlink($lockFile);
    }
}

// Check database active users count: If 0, kill all processes and free cPanel resources
$activeUserCount = null;
try {
    $dbConfig = @require $rootDir . '/config/database.php';
    if (!empty($dbConfig) && !empty($dbConfig['database'])) {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
        $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT]);
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        if ($stmt !== false) {
            $activeUserCount = (int) $stmt->fetchColumn();
        }
    }
    // Expired auth sessions təmizlənməsi
    try {
        $pdo->exec("DELETE FROM auth_sessions WHERE expires_at < NOW() OR step = 'completed'");
    } catch (\Throwable $e) {}
} catch (\Throwable $e) {}

if ($activeUserCount !== null && $activeUserCount === 0) {
    if (!empty($runningPids)) {
        foreach ($runningPids as $p) {
            if (function_exists('posix_kill')) {
                @posix_kill($p, 9);
            }
        }
    }
    if (function_exists('shell_exec')) {
        @shell_exec("pkill -9 -f 'start.php' 2>/dev/null");
        @shell_exec("pkill -9 -f 'MadelineProto' 2>/dev/null");
    }
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    echo json_encode([
        'status' => 'stopped',
        'message' => 'Bazada aktiv istifadəçi yoxdur. Bütün proseslər dayandırıldı.',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// If ANY start.php process is ALREADY running: DO NOTHING and exit immediately!
if (!empty($runningPids)) {
    $activePid = $runningPids[0];
    @file_put_contents($lockFile, $activePid);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Userbot is already running',
        'pid' => $activePid,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 3. Bot is NOT running: Clean up orphan workers and old lockfile
if (function_exists('shell_exec')) {
    @shell_exec("pkill -9 -f 'MadelineProto' 2>/dev/null");
}
if (file_exists($lockFile)) {
    @unlink($lockFile);
}

// Ensure CLI PHP binary is used instead of php-cgi (reduces CPU usage from 50% to 0.1%)
$cliCandidates = [
    '/opt/alt/php83/usr/bin/php',
    '/usr/local/bin/php',
    '/usr/bin/php',
    '/usr/local/php83/bin/php'
];

$phpBin = 'php';
foreach ($cliCandidates as $cand) {
    if (@file_exists($cand) && @is_executable($cand)) {
        $phpBin = $cand;
        break;
    }
}

$startScript = $rootDir . '/start.php';
$command = "cd " . escapeshellarg($rootDir) . " && nohup " . escapeshellarg($phpBin) . " " . escapeshellarg($startScript) . " >> " . escapeshellarg($logFile) . " 2>&1 & echo $!";

$newPid = 0;
if (function_exists('shell_exec')) {
    $newPid = (int) trim((string) @shell_exec($command));
} elseif (function_exists('exec')) {
    $out = [];
    @exec($command, $out);
    if (!empty($out)) {
        $newPid = (int) trim($out[0]);
    }
}

if ($newPid > 0) {
    @file_put_contents($lockFile, $newPid);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Userbot started successfully with CLI PHP',
        'pid' => $newPid,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    $fallbackCmd = "cd " . escapeshellarg($rootDir) . " && nohup " . escapeshellarg($phpBin) . " " . escapeshellarg($startScript) . " >> " . escapeshellarg($logFile) . " 2>&1 &";
    if (function_exists('pclose') && function_exists('popen')) {
        @pclose(@popen($fallbackCmd, "r"));
    }
    echo json_encode([
        'status' => 'ok',
        'message' => 'Userbot keepalive process dispatched with CLI PHP',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
