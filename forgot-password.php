<?php
// forgot-password.php - Ohati Standalone Forgot / Reset Password Page
session_start();
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Ohati</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card-container">
        <div class="auth-card">
            <div class="auth-logo-block">
                <img src="img/logo black transparent.png" alt="Ohati Logo" class="auth-logo" id="auth-logo-img">
            </div>

            <!-- Page 1: Request Code -->
            <div id="step-forgot">
                <div class="auth-modal-header" style="text-align: center;">
                    <h2 class="auth-modal-title">Reset Your Password</h2>
                    <p class="auth-modal-subtitle">Enter your registered email address to receive a password reset link</p>
                </div>
                <form onsubmit="handleForgotSubmit(event)">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="forgot-target" placeholder="name@example.com" required>
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary btn-full">Send Reset Link</button>
                    <button type="button" class="btn btn-ghost btn-full mt-8" onclick="window.location.href='login.php'">Back to Login</button>
                </form>
            </div>

            <!-- Page 2: Reset Password -->
            <div id="step-reset" style="display:none;"></div>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        let resetTargetVal = '';

        function handleForgotSubmit(e) {
            e.preventDefault();
            const target = document.getElementById('forgot-target').value.trim();
            const err = document.getElementById('auth-error-msg');
            const submitBtn = e.target ? e.target.querySelector('button[type="submit"]') : null;

            err.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.origText = submitBtn.textContent;
                submitBtn.textContent = 'Sending Reset Link...';
            }

            API.forgotPassword(target)
                .then(res => {
                    const msg = res.message || "If an account exists with this email address, we've sent you a password reset link. Please check your inbox.";
                    document.getElementById('step-forgot').innerHTML = `
                        <div class="auth-modal-header" style="text-align: center;">
                            <h2 class="auth-modal-title" style="color:var(--accent, #E05A47); margin-bottom:10px;"><i class="fa-solid fa-paper-plane"></i> Reset Link Sent</h2>
                            <p class="auth-modal-subtitle" style="font-size:0.9rem; line-height:1.5; color:var(--text);">${msg}</p>
                        </div>
                        <button type="button" class="btn btn-primary btn-full mt-16" style="margin-top:20px;" onclick="window.location.href='login.php'">Proceed to Login</button>
                    `;
                })
                .catch(e => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.origText || 'Send Reset Link';
                    }
                    err.textContent = e.message;
                    err.style.display = 'block';
                });
        }
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div style="position:relative;">
                            <input type="password" class="form-input" id="reset-pass" name="new_password" autocomplete="new-password" placeholder="Minimum 8 characters" required style="padding-right:40px;">
                            <span onclick="togglePasswordVisibility('reset-pass')" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94A3B8; z-index:5;"><i class="fa-solid fa-eye" id="reset-pass-eye"></i></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div style="position:relative;">
                            <input type="password" class="form-input" id="reset-confirm" name="confirm_password" autocomplete="new-password" placeholder="Confirm new password" required style="padding-right:40px;">
                            <span onclick="togglePasswordVisibility('reset-confirm')" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94A3B8; z-index:5;"><i class="fa-solid fa-eye" id="reset-confirm-eye"></i></span>
                        </div>
                    </div>
                    <div id="reset-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary btn-full">Reset & Sign In</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script>
        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const eye = document.getElementById(id + '-eye');
            if (input && eye) {
                if (input.type === 'password') {
                    input.type = 'text';
                    eye.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    eye.classList.replace('fa-eye-slash', 'fa-eye');
                }
            }
        }
        let resetTargetVal = '';
        let resetCountdownTimer = null;

        function startResetTimer() {
            let secs = 60;
            const cd = document.getElementById('reset-countdown');
            const timerBox = document.getElementById('reset-timer-box');
            const resendContainer = document.getElementById('reset-resend-container');
            if (timerBox) timerBox.style.display = 'block';
            if (resendContainer) resendContainer.style.display = 'none';
            if (resetCountdownTimer) clearInterval(resetCountdownTimer);
            resetCountdownTimer = setInterval(() => {
                secs--;
                if (cd) cd.textContent = secs;
                if (secs <= 0) {
                    clearInterval(resetCountdownTimer);
                    if (timerBox) timerBox.style.display = 'none';
                    if (resendContainer) resendContainer.style.display = 'block';
                }
            }, 1000);
        }

        function resendResetCode(e) {
            if (!resetTargetVal) return;
            const btn = document.getElementById('reset-resend-btn');
            if (btn) btn.disabled = true;
            API.forgotPassword(resetTargetVal)
                .then(() => {
                    if (btn) btn.disabled = false;
                    startResetTimer();
                })
                .catch(err => {
                    if (btn) btn.disabled = false;
                    const errBox = document.getElementById('reset-error-msg');
                    if (errBox) { errBox.textContent = err.message || 'Resend failed. Please wait a minute.'; errBox.style.display = 'block'; }
                });
        }

        function handleForgotSubmit(e) {
            e.preventDefault();
            const target = document.getElementById('forgot-target').value.trim();
            const err = document.getElementById('auth-error-msg');
            const submitBtn = e.target ? e.target.querySelector('button[type="submit"]') : null;

            err.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.origText = submitBtn.textContent;
                submitBtn.textContent = 'Sending...';
            }

            API.forgotPassword(target)
                .then(res => {
                    if (res.demo_code) console.log('Demo Reset OTP:', res.demo_code);
                    resetTargetVal = target;
                    document.getElementById('step-forgot').style.display = 'none';
                    document.getElementById('step-reset').style.display = 'block';
                    startResetTimer();
                })
                .catch(e => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.origText || 'Send Reset Code';
                    }
                    err.textContent = e.message;
                    err.style.display = 'block';
                });
        }

        function handleResetSubmit(e) {
            e.preventDefault();
            const code = document.getElementById('reset-code').value.trim();
            const pass = document.getElementById('reset-pass').value;
            const confirm = document.getElementById('reset-confirm').value;
            const err = document.getElementById('reset-error-msg');
            const submitBtn = e.target ? e.target.querySelector('button[type="submit"]') : null;

            err.style.display = 'none';

            if (code.length < 6) {
                err.textContent = 'Please enter all 6 digits of the reset code.';
                err.style.display = 'block';
                return;
            }

            if (pass !== confirm) {
                err.textContent = 'Passwords do not match.';
                err.style.display = 'block';
                return;
            }

            if (pass.length < 8) {
                err.textContent = 'Password must be at least 8 characters.';
                err.style.display = 'block';
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.origText = submitBtn.textContent;
                submitBtn.textContent = 'Resetting...';
            }

            API.resetPassword(resetTargetVal, code, pass)
                .then(() => {
                    const isNative = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'file:' || window.location.protocol === 'capacitor:';
                    window.location.href = isNative ? 'index.html' : 'login.php';
                })
                .catch(e => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.origText || 'Reset & Sign In';
                    }
                    err.textContent = e.message;
                    err.style.display = 'block';
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-theme');
                const logo = document.getElementById('auth-logo-img');
                if (logo) logo.src = 'img/logo white transparent.png';
            }

            // Auto reset via link
            const urlParams = new URLSearchParams(window.location.search);
            const target = urlParams.get('target');
            const code = urlParams.get('code');
            if (target) {
                resetTargetVal = target;
                if (code) {
                    const codeInput = document.getElementById('reset-code');
                    if (codeInput) codeInput.value = code;
                }
                document.getElementById('step-forgot').style.display = 'none';
                document.getElementById('step-reset').style.display = 'block';
                startResetTimer();
            }
        });
    </script>
</body>
</html>
