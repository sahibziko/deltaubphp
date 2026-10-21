<?php
/**
 * Delta PHP Userbot - Authentication API
 * Handles:
 * 1. QR Code Login (get_qr, check_qr) - Fastest & works on any number without SMS
 * 2. Single-Code Phone Login (send_code, verify_code, verify_2fa)
 * 3. Fallback my.telegram.org Web Scraper (tg_send_code, tg_verify_web_code)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Display errors as JSON, not HTML
header('Content-Type: application/json; charset=utf-8');
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootDir = dirname(dirname(__DIR__));
if (!file_exists($rootDir . '/vendor/autoload.php')) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => 'vendor/autoload.php tapılmadı. Composer install olunmalıdır.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $rootDir . '/vendor/autoload.php';

use App\Database;
use App\UserManager;
use App\SessionManager;
use App\TelegramOrgScraper;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

function respond(array $data): never {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function (\Throwable $e) {
    respond(['success' => false, 'error' => 'Xəta: ' . $e->getMessage()]);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {
    $config = require $rootDir . '/config/app.php';
    $dbConfig = require $rootDir . '/config/database.php';

    $db = Database::getInstance($dbConfig);
    $db->ensureConnection();

    // Auto-create auth_sessions table if missing
    $db->exec("CREATE TABLE IF NOT EXISTS `auth_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_token` VARCHAR(64) NOT NULL UNIQUE,
        `phone` VARCHAR(32) NOT NULL,
        `session_file` VARCHAR(255) NOT NULL,
        `step` VARCHAR(64) DEFAULT 'phone_sent',
        `api_id` INT DEFAULT NULL,
        `api_hash` VARCHAR(64) DEFAULT NULL,
        `random_hash` VARCHAR(64) DEFAULT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    try { $db->exec("ALTER TABLE `auth_sessions` MODIFY COLUMN `step` VARCHAR(64) NOT NULL DEFAULT 'phone_sent';"); } catch (\Throwable $e) {}
    try { $db->exec("ALTER TABLE `auth_sessions` ADD COLUMN `api_id` INT DEFAULT NULL AFTER `step`;"); } catch (\Throwable $e) {}
    try { $db->exec("ALTER TABLE `auth_sessions` ADD COLUMN `api_hash` VARCHAR(64) DEFAULT NULL AFTER `api_id`;"); } catch (\Throwable $e) {}
    try { $db->exec("ALTER TABLE `auth_sessions` ADD COLUMN `random_hash` VARCHAR(64) DEFAULT NULL AFTER `api_hash`;"); } catch (\Throwable $e) {}

    $userManager = new UserManager($db);
    $sessionManager = new SessionManager($config['sessions_dir']);

    $action = $_POST['action'] ?? '';

    $buildSettings = function(int $targetId = 0, string $targetHash = '') use ($config): Settings {
        $s = new Settings();
        $apiId = ($targetId > 0) ? $targetId : (int)($config['api_id'] ?? 2402932);
        $apiHash = (!empty($targetHash)) ? $targetHash : (string)($config['api_hash'] ?? '5dc4eb55aef563155881c509d8caab79');
        $s->setAppInfo(
            (new AppInfo())
                ->setApiId($apiId)
                ->setApiHash($apiHash)
        );
        return $s;
    };

    // ══════════════════════════════════════════════════════════════════════
    // QR KOD İLƏ GİRİŞ (1 SANİYƏDƏ SKAN ET VƏ DAXİL OL)
    // ══════════════════════════════════════════════════════════════════════

    // ─── QR 1: Yeni QR Kod Al ───
    if ($action === 'get_qr') {
        $customApiId = !empty($_POST['api_id']) ? (int)$_POST['api_id'] : 0;
        $customApiHash = !empty($_POST['api_hash']) ? trim($_POST['api_hash']) : '';

        $apiId = ($customApiId > 0) ? $customApiId : (int)($config['api_id'] ?? 2402932);
        $apiHash = (!empty($customApiHash)) ? $customApiHash : (string)($config['api_hash'] ?? '5dc4eb55aef563155881c509d8caab79');

        $token = bin2hex(random_bytes(16));
        $sessionsDir = $config['sessions_dir'];
        if (!is_dir($sessionsDir)) {
            @mkdir($sessionsDir, 0755, true);
        }
        $sessionFile = $sessionsDir . '/temp_' . $token . '.madeline';

        $settings = $buildSettings($apiId, $apiHash);
        $madelineProto = new API($sessionFile, $settings);
        $qr = $madelineProto->qrLogin();

        if (!$qr) {
            throw new Exception('QR kod hazırlana bilmədi. Zəhmət olmasa yenidən cəhd edin.');
        }

        $svg = $qr->getQRSvg(260, 2);

        $stmt = $db->prepare(
            "INSERT INTO auth_sessions (session_token, phone, session_file, step, api_id, api_hash, expires_at) 
             VALUES (?, 'qr_login', ?, 'qr_pending', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
        );
        $stmt->execute([$token, $sessionFile, $apiId, $apiHash]);

        $_SESSION['auth_token'] = $token;

        respond([
            'success' => true,
            'token' => $token,
            'svg' => $svg
        ]);
    }

    // ─── QR 2: QR Kod Statusunu Yoxla (Polling) ───
    if ($action === 'check_qr') {
        $token = $_POST['token'] ?? ($_SESSION['auth_token'] ?? '');
        if (empty($token)) {
            throw new Exception('Token tapılmadı');
        }

        $stmt = $db->prepare("SELECT * FROM auth_sessions WHERE session_token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $authSession = $stmt->fetch();
        if (!$authSession) {
            respond(['success' => false, 'status' => 'expired', 'error' => 'Sessiyanın vaxtı bitib, yenidən başladın']);
        }

        $apiId = !empty($authSession['api_id']) ? (int)$authSession['api_id'] : (int)($config['api_id'] ?? 2402932);
        $apiHash = !empty($authSession['api_hash']) ? (string)$authSession['api_hash'] : (string)($config['api_hash'] ?? '5dc4eb55aef563155881c509d8caab79');

        $settings = $buildSettings($apiId, $apiHash);
        $madelineProto = new API($authSession['session_file'], $settings);

        $qr = null;
        try {
            $qr = $madelineProto->qrLogin();
            if ($qr) {
                try {
                    $qr = $qr->waitForLoginOrQrCodeExpiration(new \Amp\TimeoutCancellation(2.0));
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {}

        $authStatus = $madelineProto->getAuthorization();
        if ($authStatus === API::WAITING_PASSWORD) {
            $stmt = $db->prepare("UPDATE auth_sessions SET step = '2fa_required' WHERE session_token = ?");
            $stmt->execute([$token]);

            respond([
                'success' => true,
                'status' => 'requires_2fa',
                'message' => 'Hesabınızda 2FA (iki mərhələli təsdiq) var. Şifrənizi daxil edin.'
            ]);
        }

        $isLoggedIn = false;
        $self = null;
        try {
            $self = $madelineProto->getSelf();
            if (!empty($self['id'])) {
                $isLoggedIn = true;
            }
        } catch (\Throwable $e) {}

        if ($isLoggedIn && $self) {
            $telegramId = $self['id'];
            $phone = $self['phone'] ?? ($authSession['phone'] !== 'qr_login' ? $authSession['phone'] : '');
            $finalSessionFile = $authSession['session_file'];

            $existingUser = $userManager->getUserByTelegramId($telegramId);
            $isFirstTime = !$existingUser;
            if ($existingUser) {
                $userManager->updateUser($telegramId, [
                    'phone' => $phone,
                    'first_name' => $self['first_name'] ?? '',
                    'last_name' => $self['last_name'] ?? '',
                    'username' => $self['username'] ?? '',
                    'session_file' => $finalSessionFile,
                    'status' => 'active'
                ]);
                $userId = $existingUser['id'];
            } else {
                $userId = $userManager->createUser([
                    'telegram_id' => $telegramId,
                    'phone' => $phone,
                    'first_name' => $self['first_name'] ?? '',
                    'last_name' => $self['last_name'] ?? '',
                    'username' => $self['username'] ?? '',
                    'session_file' => $finalSessionFile
                ]);
            }

            if ($userId && $apiId > 0 && !empty($apiHash)) {
                try {
                    $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, 'api_id', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                       ->execute([$userId, (string)$apiId, (string)$apiId]);
                    $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, 'api_hash', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                       ->execute([$userId, (string)$apiHash, (string)$apiHash]);
                } catch (\Throwable $t) {}
            }

            if ($isFirstTime) {
                try {
                    $uName = trim(($self['first_name'] ?? '') . ' ' . ($self['last_name'] ?? ''));
                    if ($uName === '') {
                        $uName = $self['username'] ?? 'İstifadəçi';
                    }
                    $mention = !empty($self['username']) 
                        ? "<a href=\"https://t.me/{$self['username']}\">@" . htmlspecialchars($self['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a>"
                        : "<a href=\"tg://user?id={$telegramId}\">" . htmlspecialchars($uName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a>";

                    \App\Logger::log("🔸 {$mention} — DeltaUserbot Qurulumunu tamamladı. Xoş istifadələr! 😊", $db);
                } catch (\Throwable $tLog) {}
            }

            $stmt = $db->prepare("UPDATE auth_sessions SET step = 'completed', phone = ? WHERE session_token = ?");
            $stmt->execute([$phone, $token]);

            $_SESSION['user_id'] = $userId;
            $_SESSION['telegram_id'] = $telegramId;

            $userManager->log($userId, 'login', 'QR Kod vasitəsilə giriş');

            // Tək əsas çox-istifadəçili bot prosesini yenilə
            $logFile = $rootDir . '/logs/bot.log';
            $lockFile = $rootDir . '/sessions/bot.lock';
            if (file_exists($lockFile)) {
                @unlink($lockFile);
            }
            @shell_exec("pkill -f 'php start.php' 2>/dev/null");
            usleep(300000);
            @shell_exec("cd " . escapeshellarg($rootDir) . " && nohup php start.php >> " . escapeshellarg($logFile) . " 2>&1 &");

            respond([
                'success' => true,
                'status' => 'logged_in',
                'redirect' => 'dashboard.php',
                'message' => 'Uğurla daxil oldunuz!'
            ]);
        }

        $newSvg = null;
        if ($qr) {
            try {
                $newSvg = $qr->getQRSvg(260, 2);
            } catch (\Throwable $e) {}
        }

        respond([
            'success' => true,
            'status' => 'pending',
            'svg' => $newSvg
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // NÖMRƏ İLƏ GİRİŞ (TƏK KOD REJİMİ)
    // ══════════════════════════════════════════════════════════════════════

    // ─── Addım 1: Telefon nömrəsi göndər (1-Addımlı Kod İstəyi) ───
    if ($action === 'send_code') {
        $rawPhone = trim($_POST['phone'] ?? '');
        if (empty($rawPhone)) {
            throw new Exception('Telefon nömrəsi daxil edilməyib');
        }

        // Nömrəni E.164 standartına gətiririk (məs: 0501234567 -> +994501234567)
        $digits = preg_replace('/[^\d]/', '', $rawPhone);
        if (str_starts_with($digits, '994') && strlen($digits) >= 12) {
            $phone = '+' . $digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $phone = '+994' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && in_array(substr($digits, 0, 2), ['50', '51', '55', '70', '77', '99'])) {
            $phone = '+994' . $digits;
        } else {
            $phone = '+' . $digits;
        }

        $customApiId = !empty($_POST['api_id']) ? (int)$_POST['api_id'] : 0;
        $customApiHash = !empty($_POST['api_hash']) ? trim($_POST['api_hash']) : '';

        $apiId = ($customApiId > 0) ? $customApiId : (int)($config['api_id'] ?? 2402932);
        $apiHash = (!empty($customApiHash)) ? $customApiHash : (string)($config['api_hash'] ?? '5dc4eb55aef563155881c509d8caab79');

        $token = bin2hex(random_bytes(16));
        $sessionsDir = $config['sessions_dir'];
        if (!is_dir($sessionsDir)) {
            @mkdir($sessionsDir, 0755, true);
        }

        $sessionFile = $sessionsDir . '/temp_' . $token . '.madeline';

        $settings = $buildSettings($apiId, $apiHash);
        $madelineProto = new API($sessionFile, $settings);
        $sentCode = $madelineProto->phoneLogin($phone);

        $logDir = $rootDir . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/auth.log',
            date('Y-m-d H:i:s') . " | SendCode | Phone: {$phone} | API: {$apiId} | Result: " . json_encode($sentCode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND
        );

        $codeType = $sentCode['type']['_'] ?? '';
        $deliveryMessage = '📩 Doğrulama kodu göndərildi.';

        if ($codeType === 'auth.sentCodeTypeApp') {
            $deliveryMessage = '📩 Kod <b>Telegram tətbiqinizə</b> (rəsmi 777000 bildiriş çatına) göndərildi. Zəhmət olmasa Telegram proqramını açın.';
        } elseif ($codeType === 'auth.sentCodeTypeSms') {
            $deliveryMessage = '📱 <b>DİQQƏT:</b> Telegram kodu tətbiqə yox, mobil nömrənizə <b>SMS</b> ilə göndərdi. Zəhmət olmasa telefonun SMS qutusunu yoxlayın!';
        } elseif ($codeType === 'auth.sentCodeTypeEmailCode') {
            $email = htmlspecialchars($sentCode['type']['email_pattern'] ?? '', ENT_QUOTES, 'UTF-8');
            $deliveryMessage = "📧 Kod Telegram hesabınıza bağlı olan <b>Email ünvanınıza ({$email})</b> göndərildi! Poçt qutunuzu yoxlayın.";
        }

        $stmt = $db->prepare(
            "INSERT INTO auth_sessions (session_token, phone, session_file, step, api_id, api_hash, expires_at) 
             VALUES (?, ?, ?, 'code_sent', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
        );
        $stmt->execute([$token, $phone, $sessionFile, $apiId, $apiHash]);

        $_SESSION['auth_token'] = $token;

        respond([
            'success' => true,
            'token' => $token,
            'code_type' => $codeType,
            'message' => $deliveryMessage
        ]);
    }

    // ─── Addım 2: Təsdiq Kodunu doğrula ───
    if ($action === 'verify_code') {
        $token = $_POST['token'] ?? ($_SESSION['auth_token'] ?? '');
        $code = trim($_POST['code'] ?? '');

        if (empty($token) || empty($code)) {
            throw new Exception('Token və ya kod daxil edilməyib');
        }

        $stmt = $db->prepare(
            "SELECT * FROM auth_sessions WHERE session_token = ? AND expires_at > NOW()"
        );
        $stmt->execute([$token]);
        $authSession = $stmt->fetch();

        if (!$authSession) {
            throw new Exception('Session müddəti bitib, yenidən cəhd edin');
        }

        $apiId = !empty($authSession['api_id']) ? (int)$authSession['api_id'] : (int)($config['api_id'] ?? 2402932);
        $apiHash = !empty($authSession['api_hash']) ? (string)$authSession['api_hash'] : (string)($config['api_hash'] ?? '5dc4eb55aef563155881c509d8caab79');

        $settings = $buildSettings($apiId, $apiHash);
        $madelineProto = new API($authSession['session_file'], $settings);
        $authorization = $madelineProto->completePhoneLogin($code);

        if (isset($authorization['_']) && $authorization['_'] === 'account.password') {
            $stmt = $db->prepare("UPDATE auth_sessions SET step = '2fa_required' WHERE session_token = ?");
            $stmt->execute([$token]);

            respond([
                'success' => true,
                'requires_2fa' => true,
                'message' => '2FA şifrəsi tələb olunur'
            ]);
        }

        if (isset($authorization['_']) && $authorization['_'] === 'account.needSignup') {
            throw new Exception('Bu nömrə ilə Telegram hesabı mövcud deyil');
        }

        $self = $madelineProto->getSelf();
        $telegramId = $self['id'];
        $finalSessionFile = $authSession['session_file'];

        $existingUser = $userManager->getUserByTelegramId($telegramId);
        $isFirstTime = !$existingUser;
        if ($existingUser) {
            $userManager->updateUser($telegramId, [
                'phone' => $authSession['phone'],
                'first_name' => $self['first_name'] ?? '',
                'last_name' => $self['last_name'] ?? '',
                'username' => $self['username'] ?? '',
                'session_file' => $finalSessionFile,
                'status' => 'active'
            ]);
            $userId = $existingUser['id'];
        } else {
            $userId = $userManager->createUser([
                'telegram_id' => $telegramId,
                'phone' => $authSession['phone'],
                'first_name' => $self['first_name'] ?? '',
                'last_name' => $self['last_name'] ?? '',
                'username' => $self['username'] ?? '',
                'session_file' => $finalSessionFile
            ]);
        }

        if ($userId && $apiId > 0 && !empty($apiHash)) {
            try {
                $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, 'api_id', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                   ->execute([$userId, (string)$apiId, (string)$apiId]);
                $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, 'api_hash', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                   ->execute([$userId, (string)$apiHash, (string)$apiHash]);
            } catch (\Throwable $t) {}
        }

        if ($isFirstTime) {
            try {
                $uName = trim(($self['first_name'] ?? '') . ' ' . ($self['last_name'] ?? ''));
                if ($uName === '') {
                    $uName = $self['username'] ?? 'İstifadəçi';
                }
                $mention = !empty($self['username']) 
                    ? "<a href=\"https://t.me/{$self['username']}\">@" . htmlspecialchars($self['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a>"
                    : "<a href=\"tg://user?id={$telegramId}\">" . htmlspecialchars($uName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a>";

                \App\Logger::log("🔸 {$mention} — DeltaUserbot Qurulumunu tamamladı. Xoş istifadələr! 😊", $db);
            } catch (\Throwable $tLog) {}
        }

        $stmt = $db->prepare("UPDATE auth_sessions SET step = 'completed' WHERE session_token = ?");
        $stmt->execute([$token]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['telegram_id'] = $telegramId;

        $userManager->log($userId, 'login', 'Web panel vasitəsilə giriş');

        // Tək əsas çox-istifadəçili bot prosesini yenilə
        $logFile = $rootDir . '/logs/bot.log';
        $lockFile = $rootDir . '/sessions/bot.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        @shell_exec("pkill -f 'php start.php' 2>/dev/null");
        usleep(300000);
        @shell_exec("cd " . escapeshellarg($rootDir) . " && nohup php start.php >> " . escapeshellarg($logFile) . " 2>&1 &");

        respond([
            'success' => true,
            'redirect' => 'dashboard.php',
            'message' => 'Uğurla daxil oldunuz!'
        ]);
    }

    // ─── Addım 3: 2FA şifrəsi ───
    if ($action === 'verify_2fa') {
        $token = $_POST['token'] ?? ($_SESSION['auth_token'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($token) || empty($password)) {
            throw new Exception('Token və ya şifrə daxil edilməyib');
        }

        $stmt = $db->prepare(
            "SELECT * FROM auth_sessions WHERE session_token = ? AND step = '2fa_required' AND expires_at > NOW()"
        );
        $stmt->execute([$token]);
        $authSession = $stmt->fetch();

        if (!$authSession) {
            throw new Exception('Session tapılmadı və ya müddəti bitib');
        }

        $apiId = !empty($authSession['api_id']) ? (int)$authSession['api_id'] : (int)($config['api_id'] ?? 2402932);
        $apiHash = !empty($authSession['api_hash']) ? (string)$authSession['api_hash'] : (string)($config['api_hash'] ?? '5dc4eb55aef563155881c509d8caab79');

        $settings = $buildSettings($apiId, $apiHash);
        $madelineProto = new API($authSession['session_file'], $settings);
        $authorization = $madelineProto->complete2falogin($password);

        $self = $madelineProto->getSelf();
        $telegramId = $self['id'];
        $finalSessionFile = $authSession['session_file'];

        $existingUser = $userManager->getUserByTelegramId($telegramId);
        $isFirstTime = !$existingUser;
        if ($existingUser) {
            $userManager->updateUser($telegramId, [
                'phone' => $authSession['phone'],
                'first_name' => $self['first_name'] ?? '',
                'last_name' => $self['last_name'] ?? '',
                'username' => $self['username'] ?? '',
                'session_file' => $finalSessionFile,
                'status' => 'active'
            ]);
            $userId = $existingUser['id'];
        } else {
            $userId = $userManager->createUser([
                'telegram_id' => $telegramId,
                'phone' => $authSession['phone'],
                'first_name' => $self['first_name'] ?? '',
                'last_name' => $self['last_name'] ?? '',
                'username' => $self['username'] ?? '',
                'session_file' => $finalSessionFile
            ]);
        }

        if ($userId && $apiId > 0 && !empty($apiHash)) {
            try {
                $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, 'api_id', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                   ->execute([$userId, (string)$apiId, (string)$apiId]);
                $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, 'api_hash', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                   ->execute([$userId, (string)$apiHash, (string)$apiHash]);
            } catch (\Throwable $t) {}
        }

        if ($isFirstTime) {
            try {
                $uName = trim(($self['first_name'] ?? '') . ' ' . ($self['last_name'] ?? ''));
                if ($uName === '') {
                    $uName = $self['username'] ?? 'İstifadəçi';
                }
                $mention = !empty($self['username']) 
                    ? "<a href=\"https://t.me/{$self['username']}\">@" . htmlspecialchars($self['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a>"
                    : "<a href=\"tg://user?id={$telegramId}\">" . htmlspecialchars($uName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a>";

                \App\Logger::log("🔸 {$mention} — DeltaUserbot Qurulumunu tamamladı. Xoş istifadələr! 😊", $db);
            } catch (\Throwable $tLog) {}
        }

        $stmt = $db->prepare("UPDATE auth_sessions SET step = 'completed' WHERE session_token = ?");
        $stmt->execute([$token]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['telegram_id'] = $telegramId;

        $userManager->log($userId, 'login', 'Web panel vasitəsilə 2FA ilə giriş');

        // Tək əsas çox-istifadəçili bot prosesini yenilə
        $logFile = $rootDir . '/logs/bot.log';
        $lockFile = $rootDir . '/sessions/bot.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        @shell_exec("pkill -f 'php start.php' 2>/dev/null");
        usleep(300000);
        @shell_exec("cd " . escapeshellarg($rootDir) . " && nohup php start.php >> " . escapeshellarg($logFile) . " 2>&1 &");

        respond([
            'success' => true,
            'redirect' => 'dashboard.php',
            'message' => 'Uğurla daxil oldunuz!'
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // İSTƏYƏ BAĞLI: my.telegram.org Web Girişi (Köhnə ehtiyat rejim)
    // ══════════════════════════════════════════════════════════════════════
    if ($action === 'tg_send_code') {
        $rawPhone = trim($_POST['phone'] ?? '');
        if (empty($rawPhone)) {
            throw new Exception('Telefon nömrəsi daxil edilməyib');
        }

        $res = TelegramOrgScraper::sendPassword($rawPhone);
        if (!$res['success']) {
            throw new Exception($res['error']);
        }

        $cleanPhone = $res['phone'];
        $token = bin2hex(random_bytes(16));
        $sessionsDir = $config['sessions_dir'];
        if (!is_dir($sessionsDir)) {
            @mkdir($sessionsDir, 0755, true);
        }
        $sessionFile = $sessionsDir . '/temp_' . $token . '.madeline';

        $stmt = $db->prepare(
            "INSERT INTO auth_sessions (session_token, phone, session_file, step, random_hash, expires_at) 
             VALUES (?, ?, ?, 'phone_sent', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
        );
        $stmt->execute([$token, $cleanPhone, $sessionFile, $res['random_hash']]);

        $_SESSION['auth_token'] = $token;

        respond([
            'success' => true,
            'token' => $token,
            'phone' => $cleanPhone,
            'step' => 'tg_web_code',
            'message' => 'Telegram tətbiqinizə rəsmi Telegram tərəfindən <b>Web Giriş Kodu</b> göndərildi.'
        ]);
    }

    if ($action === 'tg_verify_web_code') {
        $token = $_POST['token'] ?? ($_SESSION['auth_token'] ?? '');
        $webCode = trim($_POST['web_code'] ?? '');

        if (empty($token) || empty($webCode)) {
            throw new Exception('Token və ya Web giriş şifrəsi daxil edilməyib');
        }

        $stmt = $db->prepare("SELECT * FROM auth_sessions WHERE session_token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $authSession = $stmt->fetch();
        if (!$authSession) {
            throw new Exception('Session tapılmadı və ya vaxtı bitib, yenidən cəhd edin.');
        }

        $appData = TelegramOrgScraper::loginAndGetApp($authSession['phone'], (string)$authSession['random_hash'], $webCode);
        if (!$appData['success']) {
            throw new Exception($appData['error']);
        }

        $apiId = (int)$appData['api_id'];
        $apiHash = (string)$appData['api_hash'];

        $settings = $buildSettings($apiId, $apiHash);
        $madelineProto = new API($authSession['session_file'], $settings);
        $sentCode = $madelineProto->phoneLogin($authSession['phone']);

        $stmt = $db->prepare("UPDATE auth_sessions SET step = 'code_sent', api_id = ?, api_hash = ? WHERE session_token = ?");
        $stmt->execute([$apiId, $apiHash, $token]);

        respond([
            'success' => true,
            'token' => $token,
            'api_id' => $apiId,
            'api_hash' => $apiHash,
            'step' => 'bot_code',
            'message' => '✅ API açarları uğurla alındı! İndi Telegram tətbiqinizə göndərilən <b>Userbot giriş kodunu</b> daxil edin.'
        ]);
    }

    throw new Exception('Bilinməyən əməliyyat');

} catch (\Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'too many tries') !== false || stripos($msg, 'FLOOD_WAIT') !== false || stripos($msg, 'FloodWait') !== false) {
        $msg = '⚠️ Telegram Limiti: Çox sayda ard-arda kod istəndiyi üçün Telegram tərəfindən bu nömrəyə müvəqqəti limit qoyulub (Flood Wait). Zəhmət olmasa bir qədər sonra və ya başqa nömrə ilə yoxlayın.';
    } elseif (stripos($msg, 'PHONE_NUMBER_INVALID') !== false) {
        $msg = '⚠️ Daxil etdiyiniz telefon nömrəsi yanlışdır. Format: +994501234567';
    } elseif (stripos($msg, 'PHONE_CODE_INVALID') !== false) {
        $msg = '⚠️ Daxil etdiyiniz təsdiq kodu səhvdir.';
    } elseif (stripos($msg, 'PHONE_CODE_EXPIRED') !== false) {
        $msg = '⚠️ Təsdiq kodunun vaxtı bitib. Zəhmət olmasa yenidən kod istəyin.';
    }
    respond([
        'success' => false,
        'error' => $msg
    ]);
}
