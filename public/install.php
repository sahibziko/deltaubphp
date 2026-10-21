<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delta PHP - Quraşdırma</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="install-body">
    <div class="background-elements">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>
    
    <div class="container">
        <div class="glass-card wizard-card">
            <div class="logo-container">
                <i class="fa-solid fa-paper-plane"></i>
                <h1>Δ Delta PHP</h1>
                <p>Telegram Userbot Quraşdırıcısı</p>
            </div>
            
            <div class="wizard-progress">
                <div class="step-indicator active" id="indicator-1">1</div>
                <div class="step-line"></div>
                <div class="step-indicator" id="indicator-2">2</div>
                <div class="step-line"></div>
                <div class="step-indicator" id="indicator-3"><i class="fa-solid fa-lock"></i></div>
            </div>

            <div class="step-container">
                <!-- Addım 1: Nömrə daxil etmə -->
                <div class="step active" id="step-1">
                    <h2>Telefon Nömrəniz</h2>
                    <p class="text-muted">Telegram nömrənizi daxil edin (Məsələn: <code>+994501234567</code>):</p>
                    <form id="phone-form">
                        <div class="input-group">
                            <span class="input-prefix"><i class="fa-solid fa-phone"></i></span>
                            <input type="text" id="phone_input" placeholder="+994 (__) ___ __ __" required autocomplete="tel">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <span>Kodu Göndər</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>

                <!-- Addım 2: Təsdiq Kodu -->
                <div class="step" id="step-2">
                    <h2>Telegram-dan Gələn Kod</h2>
                    <p class="text-muted" id="step-2-desc">Telegram-dan gələn kodu yazın:</p>
                    <form id="code-form">
                        <div class="input-group">
                            <span class="input-prefix"><i class="fa-solid fa-hashtag"></i></span>
                            <input type="text" id="code_input" placeholder="Telegram-dan gələn kod" maxlength="10" required autocomplete="one-time-code">
                        </div>
                        <div class="resend-timer text-muted">
                            Yenidən göndər: <span id="timer">02:00</span>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <span>Təsdiqlə</span>
                            <i class="fa-solid fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-secondary btn-block" id="btn-back-step1">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Geri qayıt</span>
                        </button>
                    </form>
                </div>

                <!-- Addım 3: 2FA Şifrəsi -->
                <div class="step" id="step-3">
                    <h2>İki Addımlı Şifrə (2FA)</h2>
                    <p class="text-muted">Hesabınızın 2FA bulud şifrəsini daxil edin:</p>
                    <form id="2fa-form">
                        <div class="input-group">
                            <span class="input-prefix"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" id="password_input" placeholder="2FA Şifrəsi" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <span>Daxil ol</span>
                            <i class="fa-solid fa-unlock"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Addım 4: Uğurlu! -->
                <div class="step text-center" id="step-4">
                    <div class="success-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2>Uğurlu!</h2>
                    <p class="text-muted">Delta Userbot uğurla quraşdırıldı və aktivləşdirildi.</p>
                    <button id="go-dashboard" type="button" class="btn btn-success btn-block mt-4">
                        Panelə Keçid
                    </button>
                </div>
            </div>
            
            <div id="error-message" class="error-message hidden"></div>
            
            <!-- Loading Overlay -->
            <div id="loading-overlay" class="loading-overlay hidden">
                <div class="spinner"></div>
                <p>Zəhmət olmasa gözləyin...</p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/install.js?v=<?= time() ?>"></script>
</body>
</html>
