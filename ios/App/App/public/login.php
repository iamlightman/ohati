<?php
// login.php - Ohati Standalone Login Page
session_start();
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'vendor') {
        header('Location: vendor-dash.php');
    } else {
        header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Ohati</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card-container">
        <div class="auth-card">
            <div class="auth-logo-block">
                <img src="img/logo black transparent.png" alt="Ohati Logo" class="auth-logo" id="auth-logo-img">
            </div>
            
            <div class="auth-modal-header" style="text-align: center;">
                <h2 class="auth-modal-title">Welcome Back</h2>
                <p class="auth-modal-subtitle">Sign in to your Ohati account</p>
            </div>

            <form id="login-form" onsubmit="handleLoginPageSubmit(event)">
                <div class="form-group">
                    <label class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-input" id="login-id" placeholder="email@example.com or phone" required>
                </div>
                <div class="form-group">
                    <div class="flex-between">
                        <label class="form-label">Password</label>
                        <a href="forgot-password.php" style="font-size:0.75rem; color:var(--accent); font-weight:700; text-decoration:none;">Forgot?</a>
                    </div>
                    <div class="input-group">
                        <input type="password" class="form-input" id="login-pass" placeholder="Your password" required>
                        <span class="input-suffix" onclick="togglePasswordVisibility('login-pass')">
                            <i class="fa-solid fa-eye" id="login-pass-eye"></i>
                        </span>
                    </div>
                </div>
                <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                <button type="submit" class="btn btn-primary btn-full">Sign In</button>
            </form>

            <div class="text-center mt-16">
                <span class="text-sm text-muted">Don't have an account? </span>
                <a href="register.php" style="font-size:0.83rem; color:var(--accent); font-weight:700; text-decoration:none;">Sign Up</a>
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

        function handleLoginPageSubmit(e) {
            e.preventDefault();
            const identifier = document.getElementById('login-id').value.trim();
            const password = document.getElementById('login-pass').value;
            const err = document.getElementById('auth-error-msg');
            const submitBtn = e.target ? e.target.querySelector('button[type="submit"]') : null;

            err.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.origText = submitBtn.textContent;
                submitBtn.textContent = 'Signing in...';
            }

            API.login({ identifier, password })
                .then(res => {
                    if (res.user.role === 'admin') {
                        window.location.href = 'admin/index.php';
                    } else if (res.user.role === 'vendor' || res.user.active_role === 'vendor') {
                        if (res.user.vendor_onboarding_completed) {
                            window.location.href = 'vendor-dash.php';
                        } else {
                            window.location.href = 'vendor-register.php';
                        }
                    } else {
                        window.location.href = 'index.php';
                    }
                })
                .catch(e => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = submitBtn.dataset.origText || 'Sign In';
                    }
                    if (e.message && (e.message.includes('verify') || e.message.includes('verification'))) {
                        err.textContent = e.message;
                        err.style.display = 'block';
                        setTimeout(() => { window.location.href = 'register.php?target=' + encodeURIComponent(identifier); }, 2000);
                        return;
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
        });
    </script>
</body>
</html>
