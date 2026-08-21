<?php
// register.php - Ohati Standalone Register Page
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
    <title>Sign Up - Ohati</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card-container">
        <div class="auth-card">
            <div class="auth-logo-block">
                <img src="img/logo black transparent.png" alt="Ohati Logo" class="auth-logo" id="auth-logo-img">
            </div>

            <!-- Page 1: Role Selection -->
            <div id="step-role">
                <div class="auth-modal-header" style="text-align: center;">
                    <h2 class="auth-modal-title">Choose Account Type</h2>
                    <p class="auth-modal-subtitle">Select how you want to use Ohati</p>
                </div>
                <div class="account-type-grid">
                    <div class="account-type-card selected" id="card-cust" onclick="selectRole('customer')">
                        <div class="account-type-icon"><i class="fa-solid fa-user"></i></div>
                        <div class="account-type-title">Customer</div>
                        <div class="account-type-desc">Perfect for people planning events (wedding, funeral etc).</div>
                    </div>
                    <div class="account-type-card" id="card-vend" onclick="selectRole('vendor')">
                        <div class="account-type-icon"><i class="fa-solid fa-briefcase"></i></div>
                        <div class="account-type-title">Vendor</div>
                        <div class="account-type-desc">For professionals offering event services.</div>
                    </div>
                </div>
                <button class="btn btn-primary btn-full mt-16" onclick="showRegisterForm()">Continue</button>
            </div>

            <!-- Page 2: Signup Form -->
            <div id="step-form" style="display:none;">
                <div class="auth-modal-header" style="text-align: center;">
                    <h2 class="auth-modal-title">Sign Up</h2>
                    <p class="auth-modal-subtitle">Create your Ohati account</p>
                </div>
                <form onsubmit="handleRegisterSubmit(event)">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" id="reg-name" placeholder="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="reg-email" placeholder="email@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-input" id="reg-phone" placeholder="e.g. +233 24 123 4567">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-input" id="reg-pass" placeholder="Minimum 8 characters" oninput="checkStrength()" required>
                            <span class="input-suffix" onclick="togglePasswordVisibility('reg-pass')">
                                <i class="fa-solid fa-eye" id="reg-pass-eye"></i>
                            </span>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="strength-bar-1"></div>
                            <div class="strength-bar" id="strength-bar-2"></div>
                            <div class="strength-bar" id="strength-bar-3"></div>
                            <div class="strength-bar" id="strength-bar-4"></div>
                        </div>
                        <div class="strength-label" id="strength-label">Password strength</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-input" id="reg-confirm" placeholder="Confirm your password" required>
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" class="btn btn-outline btn-full" onclick="showRoleSelection()">Back</button>
                        <button type="submit" class="btn btn-primary btn-full">Register</button>
                    </div>
                </form>
            </div>

            <!-- Page 3: OTP Verification -->
            <div id="step-otp" style="display:none;">
                <div class="auth-modal-header" style="text-align: center;">
                    <h2 class="auth-modal-title">Verification</h2>
                    <p class="auth-modal-subtitle">We sent a 6-digit OTP code to <strong id="otp-target-label"></strong></p>
                </div>
                <div style="margin: -5px 0 15px 0; padding: 10px 14px; border-radius: 12px; background: rgba(212, 175, 55, 0.1); border: 1px solid var(--accent); font-size: 0.82rem; color: var(--primary); text-align: center;">
                    <i class="fa-solid fa-envelope-open-text" style="color:var(--accent); margin-right:6px;"></i> Check your email inbox or spam folder for your verification code.
                </div>
                <div class="otp-inputs">
                    <input type="text" maxlength="1" class="otp-input" id="otp-1" oninput="otpMove(1)" onkeyup="otpKey(1, event)">
                    <input type="text" maxlength="1" class="otp-input" id="otp-2" oninput="otpMove(2)" onkeyup="otpKey(2, event)">
                    <input type="text" maxlength="1" class="otp-input" id="otp-3" oninput="otpMove(3)" onkeyup="otpKey(3, event)">
                    <input type="text" maxlength="1" class="otp-input" id="otp-4" oninput="otpMove(4)" onkeyup="otpKey(4, event)">
                    <input type="text" maxlength="1" class="otp-input" id="otp-5" oninput="otpMove(5)" onkeyup="otpKey(5, event)">
                    <input type="text" maxlength="1" class="otp-input" id="otp-6" oninput="otpMove(6)" onkeyup="otpKey(6, event)">
                </div>
                <div class="otp-timer">Resend code in <span id="otp-countdown">59</span>s</div>
                <div id="otp-resend-container" style="display:none; text-align:center; margin-bottom:16px;">
                    <button class="btn btn-ghost btn-sm" onclick="resendOTPCode()">Resend Code</button>
                </div>
                <div id="otp-error-msg" class="form-error mb-12" style="display:none;"></div>
                <button class="btn btn-primary btn-full" onclick="submitOTPVerify()">Verify & Complete</button>
            </div>

            <div class="text-center mt-16" id="auth-footer-redirect">
                <span class="text-sm text-muted">Already have an account? </span>
                <a href="login.php" style="font-size:0.83rem; color:var(--accent); font-weight:700; text-decoration:none;">Sign In</a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script>
        let selectedRoleVal = 'customer';
        let registeredUser = null;
        let otpTargetVal = '';
        let otpTimerInterval = null;

        function selectRole(role) {
            selectedRoleVal = role;
            document.getElementById('card-cust').classList.toggle('selected', role === 'customer');
            document.getElementById('card-vend').classList.toggle('selected', role === 'vendor');
        }

        function showRegisterForm() {
            document.getElementById('step-role').style.display = 'none';
            document.getElementById('step-form').style.display = 'block';
        }

        function showRoleSelection() {
            document.getElementById('step-form').style.display = 'none';
            document.getElementById('step-role').style.display = 'block';
        }

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

        function checkStrength() {
            const pw = document.getElementById('reg-pass').value;
            const strength = getPasswordStrength(pw);
            const label = document.getElementById('strength-label');
            label.textContent = strength.label;
            label.className = 'strength-label ' + strength.className;
            
            for (let i = 1; i <= 4; i++) {
                const bar = document.getElementById('strength-bar-' + i);
                bar.className = 'strength-bar';
                if (i <= strength.score) {
                    bar.classList.add(strength.className);
                }
            }
        }

        function handleRegisterSubmit(e) {
            e.preventDefault();
            const name = document.getElementById('reg-name').value.trim();
            const email = document.getElementById('reg-email').value.trim();
            const phone = document.getElementById('reg-phone').value.trim();
            const pass = document.getElementById('reg-pass').value;
            const confirm = document.getElementById('reg-confirm').value;
            const err = document.getElementById('auth-error-msg');

            err.style.display = 'none';

            if (!email && !phone) {
                err.textContent = 'Please provide either email or phone number.';
                err.style.display = 'block';
                return;
            }

            if (pass !== confirm) {
                err.textContent = 'Passwords do not match.';
                err.style.display = 'block';
                return;
            }

            const strength = getPasswordStrength(pass);
            if (strength.score < 3) {
                err.textContent = 'Please choose a stronger password.';
                err.style.display = 'block';
                return;
            }

            const submitBtn = e.target ? e.target.querySelector('button[type="submit"]') : null;
            if (submitBtn) { 
                submitBtn.disabled = true; 
                submitBtn.dataset.origText = submitBtn.textContent; 
                submitBtn.textContent = 'Creating Account...'; 
            }

            const payload = {
                name: name,
                email: email,
                phone: phone,
                password: pass,
                role: selectedRoleVal
            };

            API.register(payload)
                .then(res => {
                    registeredUser = res.user;
                    otpTargetVal = email || phone;
                    document.getElementById('otp-target-label').textContent = otpTargetVal;

                    API.sendOTP(otpTargetVal, 'verify')
                        .then(otpRes => {
                            if (otpRes.demo_code) console.log('Demo OTP Code:', otpRes.demo_code);
                            document.getElementById('step-form').style.display = 'none';
                            document.getElementById('auth-footer-redirect').style.display = 'none';
                            document.getElementById('step-otp').style.display = 'block';
                            startOTPTimerLocal();
                        })
                        .catch(e => {
                            err.textContent = e.message;
                            err.style.display = 'block';
                            if (typeof showPushNotification === 'function') {
                                showPushNotification('Registration Warning', e.message, 'warning');
                            }
                            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.dataset.origText || 'Create Account'; }
                        });
                })
                .catch(e => {
                    err.textContent = e.message;
                    err.style.display = 'block';
                    if (typeof showPushNotification === 'function') {
                        showPushNotification('Registration Failed', e.message, 'error');
                    }
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.dataset.origText || 'Create Account'; }
                });
        }

        function otpMove(idx) {
            const curr = document.getElementById('otp-' + idx);
            if (curr && curr.value.length === 1 && idx < 6) {
                document.getElementById('otp-' + (idx + 1)).focus();
            }
        }

        function otpKey(idx, e) {
            if (e.key === 'Backspace' && idx > 1) {
                const curr = document.getElementById('otp-' + idx);
                if (curr && curr.value.length === 0) {
                    document.getElementById('otp-' + (idx - 1)).focus();
                }
            }
        }

        function startOTPTimerLocal() {
            let secs = 59;
            const cd = document.getElementById('otp-countdown');
            const resend = document.getElementById('otp-resend-container');
            if (otpTimerInterval) clearInterval(otpTimerInterval);
            
            otpTimerInterval = setInterval(() => {
                secs--;
                if (cd) cd.textContent = secs;
                if (secs <= 0) {
                    clearInterval(otpTimerInterval);
                    if (resend) resend.style.display = 'block';
                }
            }, 1000);
        }

        function resendOTPCode() {
            API.sendOTP(otpTargetVal, 'verify')
                .then(res => {
                    if (res.demo_code) console.log('Demo OTP Code:', res.demo_code);
                    document.getElementById('otp-resend-container').style.display = 'none';
                    startOTPTimerLocal();
                });
        }

        function submitOTPVerify() {
            let code = '';
            for (let i = 1; i <= 6; i++) {
                const el = document.getElementById('otp-' + i);
                if (el) code += el.value.trim();
            }
            const err = document.getElementById('otp-error-msg');

            if (code.length < 6) {
                err.textContent = 'Please enter all 6 digits.';
                err.style.display = 'block';
                return;
            }

            const btn = document.querySelector('button[onclick="submitOTPVerify()"]');
            if (btn) { btn.disabled = true; btn.textContent = 'Verifying...'; }

            API.verifyOTP(otpTargetVal, code)
                .then(res => {
                    const role = (res.user && res.user.role) ? res.user.role : selectedRoleVal;
                    if (role === 'vendor') {
                        const isNative = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'file:' || window.location.protocol === 'capacitor:';
                        if (res.user && res.user.has_vendor_profile) {
                            window.location.href = isNative ? 'index.html?screen=vendor-dash' : 'vendor-dash.php';
                        } else if (res.user && res.user.role === 'vendor') {
                            window.location.href = isNative ? 'index.html?screen=vendor-register' : 'vendor-register.php';
                        } else {
                            window.location.href = isNative ? 'index.html' : 'index.php';
                        }
                })
                .catch(e => {
                    err.textContent = e.message;
                    err.style.display = 'block';
                    if (btn) { btn.disabled = false; btn.textContent = 'Verify & Complete'; }
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-theme');
                const logo = document.getElementById('auth-logo-img');
                if (logo) logo.src = 'img/logo white transparent.png';
            }

            const urlParams = new URLSearchParams(window.location.search);
            const target = urlParams.get('target');
            if (target) {
                otpTargetVal = target;
                const label = document.getElementById('otp-target-label');
                if (label) label.textContent = otpTargetVal;
                
                // Show OTP block
                const stepRole = document.getElementById('step-role');
                if (stepRole) stepRole.style.display = 'none';
                const stepForm = document.getElementById('step-form');
                if (stepForm) stepForm.style.display = 'none';
                const authFooter = document.getElementById('auth-footer-redirect');
                if (authFooter) authFooter.style.display = 'none';
                
                const stepOtp = document.getElementById('step-otp');
                if (stepOtp) stepOtp.style.display = 'block';
                
                // Send/Resend OTP code automatically
                API.sendOTP(otpTargetVal, 'verify')
                    .then(res => {
                        if (res.demo_code) console.log('Demo OTP Code:', res.demo_code);
                        startOTPTimerLocal();
                    })
                    .catch(e => {
                        const err = document.getElementById('otp-error-msg');
                        if (err) {
                            err.textContent = e.message;
                            err.style.display = 'block';
                        }
                    });
            }
        });
    </script>
</body>
</html>
