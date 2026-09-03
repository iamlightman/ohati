<?php
// forgot-password.php — Ohati Standalone Password Reset Request Page
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
    <title>Forgot Password — Ohati</title>
    <link rel="stylesheet" href="style.css?v=3.9.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card-container">
        <div class="auth-card">
            <div class="auth-logo-block">
                <img src="img/logo black transparent.png" alt="Ohati Logo" class="auth-logo" id="auth-logo-img">
            </div>

            <div id="step-forgot">
                <div class="auth-modal-header" style="text-align: center;">
                    <h2 class="auth-modal-title">Reset Your Password</h2>
                    <p class="auth-modal-subtitle">Enter your registered email address to receive a password reset link.</p>
                </div>
                <form onsubmit="handleForgotSubmit(event)">
                    <div class="form-group mb-16">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="forgot-target" placeholder="name@example.com" required>
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <button type="submit" class="btn btn-primary btn-full" id="forgot-submit-btn">Send Reset Link</button>
                    <button type="button" class="btn btn-ghost btn-full mt-8" onclick="window.location.href='login.php'">Back to Login</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/utils.js?v=3.9.1"></script>
    <script src="js/api.js?v=3.9.1"></script>
    <script>
        function handleForgotSubmit(e) {
            e.preventDefault();
            const targetInput = document.getElementById('forgot-target');
            const target = targetInput ? targetInput.value.trim() : '';
            const err = document.getElementById('auth-error-msg');
            const submitBtn = document.getElementById('forgot-submit-btn');

            if (err) err.style.display = 'none';
            if (!target) return;

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.origText = submitBtn.textContent;
                submitBtn.textContent = 'Sending Reset Link...';
            }

            API.forgotPassword(target)
                .then(res => {
                    const msg = (res && res.message) ? res.message : "If an account exists with this email address, we've sent you a password reset link. Please check your inbox.";
                    const card = document.getElementById('step-forgot');
                    if (card) {
                        card.innerHTML = `
                            <div class="auth-modal-header" style="text-align: center;">
                                <div style="font-size: 2.8rem; color: #E05A47; margin-bottom: 12px;"><i class="fa-solid fa-paper-plane"></i></div>
                                <h2 class="auth-modal-title" style="margin-bottom: 8px;">Reset Link Sent</h2>
                                <p class="auth-modal-subtitle" style="font-size: 0.9rem; line-height: 1.5; color: var(--gray-600); max-width: 360px; margin: 0 auto 20px auto;">${msg}</p>
                            </div>
                            <button type="button" class="btn btn-primary btn-full" onclick="window.location.href='login.php'">Proceed to Login</button>
                        `;
                    }
                })
                .catch(e => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.origText || 'Send Reset Link';
                    }
                    if (err) {
                        err.textContent = e.message || 'Error sending password reset email. Please try again.';
                        err.style.display = 'block';
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-theme');
                const logo = document.getElementById('auth-logo-img');
                if (logo) logo.src = 'img/logo white transparent.png';
            }
        });
    </script>
</body>
</html>
