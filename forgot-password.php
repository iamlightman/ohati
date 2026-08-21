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
                    <h2 class="auth-modal-title">Forgot Password</h2>
                    <p class="auth-modal-subtitle">Enter your email or phone to reset</p>
                </div>
                <form onsubmit="handleForgotSubmit(event)">
                    <div class="form-group">
                        <label class="form-label">Email or Phone Number</label>
                        <input type="text" class="form-input" id="forgot-target" placeholder="email@example.com or phone" required>
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary btn-full">Send Reset Code</button>
                    <button type="button" class="btn btn-ghost btn-full mt-8" onclick="window.location.href='login.php'">Back to Login</button>
                </form>
            </div>

            <!-- Page 2: Reset Password -->
            <div id="step-reset" style="display:none;">
                <div class="auth-modal-header" style="text-align: center;">
                    <h2 class="auth-modal-title">Reset Password</h2>
                    <p class="auth-modal-subtitle">Choose a new password</p>
                </div>
                <form onsubmit="handleResetSubmit(event)">
                    <div class="form-group">
                        <label class="form-label">6-digit Reset Code</label>
                        <input type="text" class="form-input" id="reset-code" placeholder="Enter code received" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-input" id="reset-pass" placeholder="Minimum 8 characters" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-input" id="reset-confirm" placeholder="Confirm new password" required>
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
                submitBtn.textContent = 'Sending...';
            }

            API.forgotPassword(target)
                .then(res => {
                    if (res.demo_code) console.log('Demo Reset OTP:', res.demo_code);
                    resetTargetVal = target;
                    document.getElementById('step-forgot').style.display = 'none';
                    document.getElementById('step-reset').style.display = 'block';
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
            if (target && code) {
                resetTargetVal = target;
                const codeInput = document.getElementById('reset-code');
                if (codeInput) codeInput.value = code;
                document.getElementById('step-forgot').style.display = 'none';
                document.getElementById('step-reset').style.display = 'block';
            }
        });
    </script>
</body>
</html>
