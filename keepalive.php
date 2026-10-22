<?php
/**
 * Delta PHP - Bot Keepalive Script
 * Həm Web URL (Cron-job), həm də CLI cron tərəfindən çağırıla bilər.
 */

http_response_code(200);

$rootDir = __DIR__;
$lockFile = $rootDir . '/sessions/bot.lock';
$logFile = $rootDir . '/logs/bot.log';

if (!is_dir($rootDir . '/sessions')) {
    @mkdir($rootDir . '/sessions', 0755, true);
}
if (!is_dir($rootDir . '/logs')) {
    @mkdir($rootDir . '/logs', 0755, true);
}

// 1. Bu istifadəçinin öz start.php prosesi artıq işləyirmi?
$botRunning = false;
$pid = 0;

$currentUser = get_current_user();
$startScript = $rootDir . '/start.php';

if (function_exists('shell_exec')) {
    // Shared hostda başqa istifadəçilərlə qarışmasın deyə yalnız öz istifadəçimiz və tam yol yoxlanılır
    $cmdCheck = "ps -u " . escapeshellarg($currentUser) . " -o pid,cmd 2>/dev/null | grep " . escapeshellarg($startScript) . " | grep -v grep";
    $psOut = @shell_exec($cmdCheck);
    if (!empty($psOut)) {
        if (preg_match('/^\s*(\d+)/m', (string)$psOut, $m)) {
            $foundPid = (int) $m[1];
            if ($foundPid > 0 && $foundPid !== getmypid()) {
                $botRunning = true;
                $pid = $foundPid;
            }
        }
    }
}

// Əgər ps tapmadısa, lock faylı ilə yoxlayırıq
if (!$botRunning && file_exists($lockFile)) {
    $lockPid = (int) @file_get_contents($lockFile);
    if ($lockPid > 0) {
        if (file_exists("/proc/$lockPid/cmdline")) {
            $cmdLine = (string) @file_get_contents("/proc/$lockPid/cmdline");
            if (str_contains($cmdLine, $startScript)) {
                $botRunning = true;
                $pid = $lockPid;
            }
        }
    }
    if (!$botRunning) {
        @unlink($lockFile);
    }
}

// Əgər proses artıq işləyirsə, ÇIXIRIQ (Təkrar proses açılmır!)
if ($botRunning) {
    @file_put_contents($lockFile, $pid);
    echo "OK: Bot is running (PID: $pid)\n";
    exit;
}

// 2. Bot işləmir: Köhnə orphan MadelineProto workerlərini təmizləyirik (saytda aktiv giriş cəhdi yoxdursa)
if (function_exists('shell_exec') && count(glob($rootDir . '/sessions/temp_*')) === 0) {
    @shell_exec("pkill -u " . escapeshellarg($currentUser) . " -9 -f 'MadelineProto worker' 2>/dev/null");
}
if (file_exists($lockFile)) {
    @unlink($lockFile);
}

// 3. Botu təzədən başladırıq
$phpBin = 'php';
if (@file_exists('/opt/alt/php83/usr/bin/php')) {
    $phpBin = '/opt/alt/php83/usr/bin/php';
} elseif (defined('PHP_BINARY') && PHP_BINARY) {
    $phpBin = PHP_BINARY;
}

$command = "cd " . escapeshellarg($rootDir) . " && " . escapeshellarg($phpBin) . " " . escapeshellarg($startScript) . " >> " . escapeshellarg($logFile) . " 2>&1 & echo $!";

$newPid = trim((string) @shell_exec($command));

if ($newPid && is_numeric($newPid)) {
    @file_put_contents($lockFile, $newPid);
    echo "OK: Bot started (PID: $newPid)\n";
} else {
    $fallbackCommand = "cd " . escapeshellarg($rootDir) . " && " . escapeshellarg($phpBin) . " " . escapeshellarg($startScript) . " >> " . escapeshellarg($logFile) . " 2>&1 &";
    if (function_exists('pclose') && function_exists('popen')) {
        @pclose(@popen($fallbackCommand, "r"));
    }
    echo "OK: Keepalive triggered\n";
}
