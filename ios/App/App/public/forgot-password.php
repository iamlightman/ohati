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
    <title>Reset Your Password — Ohati</title>
    <link rel="stylesheet" href="style.css?v=3.9.3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..900;1,9..144,300..900&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 20px;
            background: #070E17;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #FFF;
        }
        .forgot-card {
            background: #0F1923;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 32px 24px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.8);
            text-align: center;
        }
        .app-logo-box {
            width: 76px;
            height: 76px;
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid #F2A735;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(242,167,53,0.25);
        }
        .app-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .forgot-title {
            font-family: 'Fraunces', serif;
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0 0 6px 0;
            color: #FFF;
        }
        .forgot-subtitle {
            font-size: 0.85rem;
            color: #94A3B8;
            margin: 0 0 24px 0;
            line-height: 1.5;
        }
        .form-label-custom {
            display: block;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            color: #CBD5E1;
            margin-bottom: 6px;
        }
        .form-input-custom {
            width: 100%;
            padding: 13px;
            border-radius: 12px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.15);
            color: #FFF;
            font-size: 0.95rem;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s, background 0.2s;
        }
        .form-input-custom:focus {
            border-color: #F2A735;
            background: rgba(255,255,255,0.1);
        }
        .btn-gold {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #F2A735, #D98E1C);
            color: #000;
            font-weight: 800;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 6px;
            transition: transform 0.15s, opacity 0.15s;
        }
        .btn-gold:active {
            transform: scale(0.98);
        }
        .btn-gold:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .forgot-footer {
            margin-top: 24px;
            font-size: 0.85rem;
            color: #94A3B8;
        }
        .forgot-footer a {
            color: #F2A735;
            font-weight: 700;
            text-decoration: none;
        }
        .forgot-footer a:hover {
            text-decoration: underline;
        }
        .error-box {
            display: none;
            padding: 10px;
            border-radius: 10px;
            background: rgba(239,68,68,0.15);
            border: 1px solid #EF4444;
            color: #FCA5A5;
            font-size: 0.8rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="forgot-card">
        <div class="app-logo-box">
            <img src="img/app_icon.png" alt="Ohati App Icon" id="auth-logo-img">
        </div>

        <!-- Initial Request State -->
        <div id="step-forgot">
            <h2 class="forgot-title">Reset Your Password</h2>
            <p class="forgot-subtitle">Enter your registered email address to receive a password reset link.</p>

            <form onsubmit="handleForgotSubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:16px;">
                <div>
                    <label class="form-label-custom">Email Address</label>
                    <input type="email" id="forgot-target" class="form-input-custom" required placeholder="name@example.com">
                </div>
                <div id="auth-error-msg" class="error-box"></div>
                <button type="submit" id="forgot-submit-btn" class="btn-gold">Send Reset Link</button>
            </form>

            <div class="forgot-footer">
                Remembered your password? <a href="login.php">Log In</a>
            </div>
        </div>

        <!-- Success Sent State -->
        <div id="step-forgot-sent" style="display:none;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(16,185,129,0.15); color: #10B981; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 12px auto;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h2 class="forgot-title" style="color: #FFF;">Reset Link Sent</h2>
            <p class="forgot-subtitle" style="font-size: 0.88rem; color: #94A3B8; line-height: 1.5; margin-top: 8px;">
                If an account exists with <strong id="sent-email-display" style="color:#FFF;">this email address</strong>, we've sent a password reset link to your email.
            </p>

            <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: 14px 16px; margin: 16px 0; font-size: 0.82rem; color: #CBD5E1; line-height: 1.5; text-align: left;">
                <div style="font-weight: 700; color: #FFF; margin-bottom: 4px;">
                    <i class="fa-solid fa-shield-halved" style="color: #F2A735; margin-right: 6px;"></i> Security Instructions:
                </div>
                <ul style="margin: 0; padding-left: 18px; color: #94A3B8;">
                    <li>Check your inbox and click the <strong>Reset Password</strong> button.</li>
                    <li>The link will expire in <strong>24 hours</strong> and can only be used once.</li>
                    <li>If you don't see the email, please check your spam or junk folder.</li>
                </ul>
            </div>

            <button type="button" class="btn-gold" onclick="window.location.href='login.php'"><i class="fa-solid fa-right-to-bracket" style="margin-right:6px;"></i> Return to Login</button>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/utils.js?v=3.9.3"></script>
    <script src="js/api.js?v=3.9.3"></script>
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
    </script>
</body>
</html>
