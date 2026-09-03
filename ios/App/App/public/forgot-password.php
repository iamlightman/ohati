<?php
// forgot-password.php — Ohati Official Password Reset Request Page
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
    <link rel="stylesheet" href="style.css?v=3.9.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card-container">
        <div class="auth-card">
            <div class="auth-logo-block">
                <img src="img/logo black transparent.png" alt="Ohati Logo" class="auth-logo" id="auth-logo-img">
            </div>

            <!-- Form State -->
            <div id="step-forgot">
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Reset Your Password</h2>
                    <p class="auth-modal-subtitle">Enter your registered email address to receive a reset link</p>
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

            <!-- Success Sent State -->
            <div id="step-forgot-sent" style="display:none;">
                <div class="auth-modal-header" style="text-align: center;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #D1FAE5; color: #10B981; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 12px auto;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <h2 class="auth-modal-title" style="color: var(--primary);">Reset Link Sent</h2>
                    <p class="auth-modal-subtitle" style="font-size: 0.88rem; color: var(--gray-600); line-height: 1.5; margin-top: 8px;">
                        If an account exists with <strong id="sent-email-display">this email address</strong>, we've sent a password reset link to your email.
                    </p>
                </div>

                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 16px; margin: 16px 0; font-size: 0.82rem; color: #4B5563; line-height: 1.5; text-align: left;">
                    <div style="font-weight: 700; color: #1F2937; margin-bottom: 4px;">
                        <i class="fa-solid fa-shield-halved" style="color: var(--accent); margin-right: 6px;"></i> Security Instructions:
                    </div>
                    <ul style="margin: 0; padding-left: 18px;">
                        <li>Check your inbox and click the <strong>Reset Password</strong> button.</li>
                        <li>The link will expire in <strong>24 hours</strong> and can only be used once.</li>
                        <li>If you don't see the email, please check your spam or junk folder.</li>
                    </ul>
                </div>

                <button type="button" class="btn btn-primary btn-full" onclick="window.location.href='login.php'"><i class="fa-solid fa-right-to-bracket" style="margin-right:6px;"></i> Return to Login</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/utils.js?v=3.9.2"></script>
    <script src="js/api.js?v=3.9.2"></script>
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
                    document.getElementById('sent-email-display').textContent = target;
                    document.getElementById('step-forgot').style.display = 'none';
                    document.getElementById('step-forgot-sent').style.display = 'block';
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
