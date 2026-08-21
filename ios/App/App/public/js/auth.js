// js/auth.js — Ohati Authentication, Registration, OTP, KYC, and Vendor Onboarding Flows

function renderAuthModal() {
    if (typeof showMandatoryAuthLockScreen === 'function') {
        const mode = (state.authMode === 'register' || state.authMode === 'account-type' || state.authMode === 'vendor-register' || state.authMode === 'welcome') ? 'signup' : 'login';
        showMandatoryAuthLockScreen(mode);
        return;
    }

    const modalContent = document.getElementById('modal-content');
    if (!modalContent) return;

    let html = '';
    const mode = state.authMode;
    const step = state.authStep;

    switch (mode) {
        case 'welcome':
            html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Welcome to Ohati</h2>
                    <p class="auth-modal-subtitle">Find trusted vendors for every celebration.</p>
                </div>
                <div class="onboard-logo-block mb-20" style="margin-top:10px;">
                    <div class="onboard-logo-ring">
                        <img src="img/logo black transparent.png" alt="Ohati Logo" class="onboard-logo-img">
                    </div>
                </div>
                <div class="onboard-actions">
                    <button class="btn btn-primary btn-full" onclick="state.authMode='account-type'; state.authStep=1; renderAuthModal();">
                        <i class="fa-solid fa-user-plus"></i> Get Started
                    </button>
                    <button class="btn btn-outline btn-full" onclick="state.authMode='login'; state.authStep=1; renderAuthModal();">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In
                    </button>
                </div>
            `;
            break;

        case 'account-type':
            html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Choose Account Type</h2>
                    <p class="auth-modal-subtitle">Select how you want to use Ohati</p>
                </div>
                <div class="account-type-grid">
                    <div class="account-type-card selected" id="card-cust" onclick="selectAccountType('customer')">
                        <div class="account-type-icon"><i class="fa-solid fa-user"></i></div>
                        <div class="account-type-title">Customer</div>
                        <div class="account-type-desc">Perfect for people planning events (wedding, funeral etc).</div>
                        <div class="account-type-tags">
                            <span class="account-type-tag">Wedding</span>
                            <span class="account-type-tag">Birthday</span>
                            <span class="account-type-tag">Corporate</span>
                        </div>
                    </div>
                    <div class="account-type-card" id="card-vend" onclick="selectAccountType('vendor')">
                        <div class="account-type-icon"><i class="fa-solid fa-briefcase"></i></div>
                        <div class="account-type-title">Vendor</div>
                        <div class="account-type-desc">For professionals offering event services.</div>
                        <div class="account-type-tags">
                            <span class="account-type-tag">Photographer</span>
                            <span class="account-type-tag">Caterer</span>
                            <span class="account-type-tag">Decorator</span>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary btn-full mt-16" onclick="confirmAccountType()">Continue</button>
            `;
            state.authData.role = 'customer'; // default
            break;

        case 'register':
            if (step === 1) {
                html = `
                    <div class="auth-modal-header">
                        <h2 class="auth-modal-title">Sign Up</h2>
                        <p class="auth-modal-subtitle">Step 1: Your Information</p>
                    </div>
                    <div class="auth-step-indicator">
                        <div class="auth-step-dot active"></div>
                        <div class="auth-step-dot"></div>
                        <div class="auth-step-dot"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-input" id="reg-fname" placeholder="First Name" value="${state.authData.fname || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-input" id="reg-lname" placeholder="Last Name" value="${state.authData.lname || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username (optional)</label>
                        <input type="text" class="form-input" id="reg-username" placeholder="Username" value="${state.authData.username || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" id="reg-email" placeholder="email@example.com" value="${state.authData.email || ''}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-input" id="reg-phone" placeholder="e.g. +233 24 123 4567" value="${state.authData.phone || ''}">
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <button class="btn btn-primary btn-full" onclick="submitRegisterStep1()">Next Step</button>
                `;
            } else if (step === 2) {
                html = `
                    <div class="auth-modal-header">
                        <h2 class="auth-modal-title">Create Password</h2>
                        <p class="auth-modal-subtitle">Step 2: Choose a strong password</p>
                    </div>
                    <div class="auth-step-indicator">
                        <div class="auth-step-dot done"></div>
                        <div class="auth-step-dot active"></div>
                        <div class="auth-step-dot"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-input" id="reg-pass" placeholder="Minimum 8 characters" oninput="checkPasswordStrengthUI()">
                            <span class="input-suffix" onclick="togglePasswordVisibility('reg-pass')"><i class="fa-solid fa-eye" id="reg-pass-eye"></i></span>
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
                        <input type="password" class="form-input" id="reg-confirm" placeholder="Confirm your password">
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-outline btn-full" onclick="state.authStep=1; renderAuthModal();">Back</button>
                        <button class="btn btn-primary btn-full" onclick="submitRegisterStep2()">Register</button>
                    </div>
                `;
            }
            break;

        case 'otp':
            html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Account Verification</h2>
                    <p class="auth-modal-subtitle">Enter the 6-digit code sent to <strong id="otp-target-label">${state.authData.email || state.authData.phone || 'your phone & email'}</strong></p>
                </div>

                <div style="display:flex; justify-content:center; gap:12px; margin-bottom:16px; font-size:0.75rem; font-weight:700;">
                    <span style="color:#10B981; background:rgba(16,185,129,0.1); padding:4px 10px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;">
                        <i class="fa-solid fa-circle-check"></i> Email Sent
                    </span>
                    <span style="color:#10B981; background:rgba(16,185,129,0.1); padding:4px 10px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;">
                        <i class="fa-solid fa-circle-check"></i> SMS Sent
                    </span>
                </div>
                <div class="otp-inputs">
                    <input type="text" maxlength="1" class="otp-input" id="otp-1" oninput="otpMove(1)" onkeyup="otpKey(1, event)" value="">
                    <input type="text" maxlength="1" class="otp-input" id="otp-2" oninput="otpMove(2)" onkeyup="otpKey(2, event)" value="">
                    <input type="text" maxlength="1" class="otp-input" id="otp-3" oninput="otpMove(3)" onkeyup="otpKey(3, event)" value="">
                    <input type="text" maxlength="1" class="otp-input" id="otp-4" oninput="otpMove(4)" onkeyup="otpKey(4, event)" value="">
                    <input type="text" maxlength="1" class="otp-input" id="otp-5" oninput="otpMove(5)" onkeyup="otpKey(5, event)" value="">
                    <input type="text" maxlength="1" class="otp-input" id="otp-6" oninput="otpMove(6)" onkeyup="otpKey(6, event)" value="">
                </div>
                <div class="otp-timer">Resend code in <span id="otp-countdown">59</span>s</div>
                <div id="otp-resend-container" style="display:none; text-align:center; margin-bottom:16px;">
                    <button class="btn btn-ghost btn-sm" onclick="resendOTPCode()">Resend Code</button>
                </div>
                <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                <button class="btn btn-primary btn-full" onclick="submitOTPVerify()">Verify & Complete</button>
            `;
            break;


        case 'login':
            html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Welcome Back</h2>
                    <p class="auth-modal-subtitle">Sign in to your Ohati account</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-input" id="login-id" placeholder="email@example.com or phone number">
                </div>
                <div class="form-group">
                    <div class="flex-between">
                        <label class="form-label">Password</label>
                        <a href="#" style="font-size:0.75rem; color:var(--accent); font-weight:700; text-decoration:none;" onclick="state.authMode='forgot'; renderAuthModal(); event.preventDefault();">Forgot?</a>
                    </div>
                    <div class="input-group">
                        <input type="password" class="form-input" id="login-pass" placeholder="Your password">
                        <span class="input-suffix" onclick="togglePasswordVisibility('login-pass')"><i class="fa-solid fa-eye" id="login-pass-eye"></i></span>
                    </div>
                </div>
                <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                <button class="btn btn-primary btn-full" onclick="submitLogin()">Sign In</button>
                <div class="text-center mt-16">
                    <span class="text-sm text-muted">Don't have an account? </span>
                    <a href="#" style="font-size:0.83rem; color:var(--accent); font-weight:700; text-decoration:none;" onclick="state.authMode='welcome'; renderAuthModal(); event.preventDefault();">Sign Up</a>
                </div>
            `;
            break;

        case 'forgot':
            html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Forgot Password</h2>
                    <p class="auth-modal-subtitle">Enter your email or phone to reset</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-input" id="forgot-target" placeholder="email@example.com or phone number">
                </div>
                <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                <button class="btn btn-primary btn-full" onclick="submitForgot()">Send Reset Code</button>
                <button class="btn btn-ghost btn-full mt-8" onclick="state.authMode='login'; renderAuthModal();">Back to Login</button>
            `;
            break;

        case 'reset':
            const resetFallback = state.demoResetCode || '';
            html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Reset Password</h2>
                    <p class="auth-modal-subtitle">Choose a new password</p>
                </div>
                ${resetFallback ? `
                    <div style="margin: -5px 0 15px 0; padding: 10px 14px; border-radius: 12px; background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.3); font-size: 0.82rem; color: #0284c7; text-align: center;">
                        <i class="fa-solid fa-code" style="margin-right:6px;"></i> [Local Development] Reset code auto-filled: <strong>${resetFallback}</strong>
                    </div>
                ` : `
                    <div style="margin: -5px 0 15px 0; padding: 10px 14px; border-radius: 12px; background: rgba(212, 175, 55, 0.1); border: 1px solid var(--accent); font-size: 0.82rem; color: var(--primary); text-align: center;">
                        <i class="fa-solid fa-envelope-open-text" style="color:var(--accent); margin-right:6px;"></i> Check your email inbox or spam folder for your reset code.
                    </div>
                `}
                <div class="form-group">
                    <label class="form-label">6-digit Reset Code</label>
                    <input type="text" class="form-input" id="reset-code" placeholder="Enter code received in email" value="${resetFallback}">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-input" id="reset-pass" placeholder="Minimum 8 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-input" id="reset-confirm" placeholder="Confirm new password">
                </div>
                <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                <button class="btn btn-primary btn-full" onclick="submitReset()">Reset & Sign In</button>
            `;
            break;

        case 'vendor-register':
            html = renderVendorOnboardingStep();
            break;
    }

    openModal(html);

    if (mode === 'otp') {
        startOTPTimer();
    }
}

// Account Type Selection
function selectAccountType(type) {
    state.authData.role = type;
    document.getElementById('card-cust')?.classList.toggle('selected', type === 'customer');
    document.getElementById('card-vend')?.classList.toggle('selected', type === 'vendor');
}

function confirmAccountType() {
    state.authMode = 'register';
    state.authStep = 1;
    renderAuthModal();
}

// Password Eye Toggle
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

// Password Strength
function checkPasswordStrengthUI() {
    const pw = document.getElementById('reg-pass')?.value || '';
    const strength = getPasswordStrength(pw);
    const label = document.getElementById('strength-label');
    if (label) {
        label.textContent = strength.label;
        label.className = 'strength-label ' + strength.className;
    }
    for (let i = 1; i <= 4; i++) {
        const bar = document.getElementById('strength-bar-' + i);
        if (bar) {
            bar.className = 'strength-bar';
            if (i <= strength.score) {
                bar.classList.add(strength.className);
            }
        }
    }
}

// Submit Register Step 1
function submitRegisterStep1() {
    const fname = document.getElementById('reg-fname').value.trim();
    const lname = document.getElementById('reg-lname').value.trim();
    const username = document.getElementById('reg-username').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const phone = document.getElementById('reg-phone').value.trim();
    const err = document.getElementById('auth-error-msg');

    if (!fname || !lname || (!email && !phone)) {
        err.textContent = 'Please fill out first name, last name, and email or phone.';
        err.style.display = 'block';
        return;
    }

    state.authData.fname = fname;
    state.authData.lname = lname;
    state.authData.username = username;
    state.authData.email = email;
    state.authData.phone = phone;

    state.authStep = 2;
    renderAuthModal();
}

// Submit Register Step 2
function submitRegisterStep2() {
    const pass = document.getElementById('reg-pass').value;
    const confirm = document.getElementById('reg-confirm').value;
    const err = document.getElementById('auth-error-msg');

    if (pass !== confirm) {
        err.textContent = 'Passwords do not match.';
        err.style.display = 'block';
        return;
    }

    const strength = getPasswordStrength(pass);
    if (strength.score < 3) {
        err.textContent = 'Please choose a stronger password (at least Good).';
        err.style.display = 'block';
        return;
    }

    state.authData.password = pass;

    const btn = document.querySelector('button[onclick="submitRegisterStep2()"]');

    ActionLock.execute(btn, 'Creating Account...', async () => {
        const pendingRef = sessionStorage.getItem('ohati_pending_ref') || '';
        const regPayload = {
            name: `${state.authData.fname} ${state.authData.lname}`,
            email: state.authData.email,
            phone: state.authData.phone,
            username: state.authData.username,
            password: state.authData.password,
            role: state.authData.role,
            ref: pendingRef
        };

        const res = await API.register(regPayload);
        if (res.auth_token) {
            localStorage.setItem('ohati_auth_token', res.auth_token);
        }
        state.user = res.user;
        showPushNotification('Account Created', 'Please verify your details.');

        const email = state.authData.email || '';
        const phone = state.authData.phone || '';
        const target = email || phone;

        try {
            const otpRes = await API.sendOTP(target, 'verify', email, phone);
            state.authData.email_sent = otpRes.email_sent;
        } catch (e) { }

        state.authMode = 'otp';
        state.authStep = 1;
        renderAuthModal();
    }).catch(e => {
        if (err) {
            err.textContent = e.message || 'Registration failed.';
            err.style.display = 'block';
        }
    });
}

// OTP Input Navigation
function otpMove(idx) {
    const curr = document.getElementById('otp-' + idx);
    if (curr && curr.value.length === 1 && idx < 6) {
        document.getElementById('otp-' + (idx + 1))?.focus();
    }
}
function otpKey(idx, e) {
    if (e.key === 'Backspace' && idx > 1) {
        const curr = document.getElementById('otp-' + idx);
        if (curr && curr.value.length === 0) {
            document.getElementById('otp-' + (idx - 1))?.focus();
        }
    }
}

// OTP Timer
let otpCountdownTimer = null;
function startOTPTimer() {
    let secs = 59;
    const cd = document.getElementById('otp-countdown');
    const resend = document.getElementById('otp-resend-container');
    if (otpCountdownTimer) clearInterval(otpCountdownTimer);
    otpCountdownTimer = setInterval(() => {
        secs--;
        if (cd) cd.textContent = secs;
        if (secs <= 0) {
            clearInterval(otpCountdownTimer);
            if (resend) resend.style.display = 'block';
        }
    }, 1000);
}

function resendOTPCode(event) {
    const btn = event?.target || document.querySelector('button[onclick*="resendOTPCode"]');
    const target = state.authData.email || state.authData.phone || state.user?.email || state.user?.phone;
    ActionLock.execute(btn, 'Sending OTP...', async () => {
        const otpRes = await API.sendOTP(target, 'verify');
        state.authData.email_sent = otpRes.email_sent;
        showPushNotification('OTP Resent', 'Check your email inbox or mobile SMS.');
        document.getElementById('otp-resend-container').style.display = 'none';
        renderAuthModal();
    }).catch(e => showPushNotification('Error', e.message));
}

function submitOTPVerify(event) {
    let code = '';
    for (let i = 1; i <= 6; i++) {
        code += document.getElementById('otp-' + i)?.value || '';
    }
    const err = document.getElementById('auth-error-msg');
    const target = state.authData.email || state.authData.phone || state.user?.email || state.user?.phone;

    if (code.length < 6) {
        err.textContent = 'Please enter all 6 digits.';
        err.style.display = 'block';
        return;
    }

    const btn = event?.target || document.querySelector('button[onclick*="submitOTPVerify"]');
    ActionLock.execute(btn, 'Verifying...', async () => {
        await API.verifyOTP(target, code);
        showPushNotification('Verified', 'Verification successful!');
        closeModal();
        updateAppHeader();
        if (state.user && (state.user.active_role || state.user.role) === 'vendor' && !state.user.vendor_onboarding_completed) {
            showPushNotification('Profile Incomplete', 'Please complete your business & profile verification steps.');
            state.authMode = 'vendor-register';
            state.authStep = 1;
            renderAuthModal();
        } else {
            navigateTo('home');
        }
    }).catch(e => {
        err.textContent = e.message;
        err.style.display = 'block';
    });
}

// Sign In
function submitLogin(event) {
    const identifier = document.getElementById('login-id')?.value.trim();
    const password = document.getElementById('login-pass')?.value;
    const err = document.getElementById('auth-error-msg');

    if (!identifier || !password) {
        if (err) {
            err.textContent = 'Please fill out both fields.';
            err.style.display = 'block';
        }
        return;
    }

    const btn = event?.target || document.querySelector('button[onclick*="submitLogin"]');
    ActionLock.execute(btn, 'Logging in...', async () => {
        try {
            const res = await API.login({ identifier, password });
            if (res.auth_token) {
                localStorage.setItem('ohati_auth_token', res.auth_token);
            }
            state.user = res.user;
            showPushNotification('Welcome', 'Logged in successfully!');
            if (typeof window.clearAllAuthOverlays === 'function') window.clearAllAuthOverlays();
            else {
                closeModal();
                if (typeof toggleSidebar === 'function') toggleSidebar(false);
            }
            updateAppHeader();
            if ((state.user.active_role || state.user.role) === 'vendor' && !state.user.vendor_onboarding_completed) {
                showPushNotification('Profile Incomplete', 'Please complete your business & profile verification steps.');
                state.authMode = 'vendor-register';
                state.authStep = 1;
                renderAuthModal();
            } else {
                navigateTo('home');
            }
        } catch (e) {
            if (e.message && (e.message.includes('verify your email') || e.message.includes('verify your phone number') || e.message.includes('verify your email address or phone number'))) {
                state.authData.email = identifier;
                const otpRes = await API.sendOTP(identifier, 'verify');
                state.authData.email_sent = otpRes.email_sent;
                state.authMode = 'otp';
                state.authStep = 1;
                renderAuthModal();
                showPushNotification('Verification Required', 'Please verify your email address or phone number first.');
                return;
            }
            if (err) {
                err.textContent = e.message;
                err.style.display = 'block';
            }
            throw e;
        }
    }).catch(() => { });
}

// Forgot Password
function submitForgot(event) {
    const target = document.getElementById('forgot-target')?.value.trim();
    const err = document.getElementById('auth-error-msg');
    if (!target) {
        if (err) {
            err.textContent = 'Please enter your email or phone.';
            err.style.display = 'block';
        }
        return;
    }
    state.authData.resetTarget = target;

    const btn = event?.target || document.querySelector('button[onclick*="submitForgot"]');
    ActionLock.execute(btn, 'Sending Reset Code...', async () => {
        const res = await API.forgotPassword(target);
        state.authData.email_sent = res.email_sent;
        showPushNotification('Code Sent', 'Verification code dispatched to your email & SMS.');
        state.authMode = 'reset';
        renderAuthModal();
    })
        .catch(e => {
            err.textContent = e.message;
            err.style.display = 'block';
        });
}

// Reset Password
function submitReset(event) {
    const code = document.getElementById('reset-code')?.value.trim();
    const pass = document.getElementById('reset-pass')?.value;
    const confirm = document.getElementById('reset-confirm')?.value;
    const err = document.getElementById('auth-error-msg');

    if (pass !== confirm) {
        if (err) { err.textContent = 'Passwords do not match.'; err.style.display = 'block'; }
        return;
    }
    if (pass.length < 8) {
        if (err) { err.textContent = 'Password must be 8+ characters.'; err.style.display = 'block'; }
        return;
    }

    const btn = event?.target || document.querySelector('button[onclick*="submitReset"]');
    ActionLock.execute(btn, 'Resetting Password...', async () => {
        await API.resetPassword(state.authData.resetTarget, code, pass);
        showPushNotification('Success', 'Password reset successfully. Please login.');
        state.authMode = 'login';
        renderAuthModal();
    }).catch(e => {
        if (err) { err.textContent = e.message; err.style.display = 'block'; }
    });
}

// Log Out
function handleLogout() {
    console.log("Signing out user...");
    state.user = null;
    state.currentUser = null;
    state.bookings = [];
    state.favorites = [];
    state.unreadChats = 0;

    // Clear all auth keys & stored user tokens
    localStorage.removeItem('ohati_auth_token');
    localStorage.removeItem('ohati_user_session');
    localStorage.removeItem('ohati_user');
    localStorage.removeItem('ohati_user_session');
    sessionStorage.clear();

    const doLocalCleanup = () => {
        if (typeof updateAppHeader === 'function') updateAppHeader();
        if (typeof updateUserSessionUI === 'function') updateUserSessionUI();
        if (typeof renderSidebar === 'function') renderSidebar();

        // Lock screen to Login overlay
        if (typeof unlockMandatoryAuthScreen === 'function') unlockMandatoryAuthScreen();
        if (typeof showMandatoryAuthLockScreen === 'function') {
            showMandatoryAuthLockScreen('login');
        }

        showPushNotification('Signed Out', 'You have successfully signed out.');

        // Prevent browser Back button from revealing protected content
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname);
        }
    };

    if (window.API && typeof API.logout === 'function') {
        API.logout().then(() => {
            doLocalCleanup();
        }).catch(() => {
            doLocalCleanup();
        });
    } else {
        doLocalCleanup();
    }
}
window.logoutUser = handleLogout;
window.handleLogout = handleLogout;

// ── Vendor Onboarding Flow (Step by Step) ──────────────────────────────────
function renderVendorOnboardingStep() {
    const step = state.authStep;
    let html = `
        <div class="auth-modal-header">
            <h2 class="auth-modal-title">Vendor Setup</h2>
            <p class="auth-modal-subtitle">Step ${step} of 5</p>
        </div>
        <div class="vendor-steps">
            ${[1, 2, 3, 4, 5].map(i => `
                <div class="vendor-step-item">
                    <div class="vendor-step-circle ${i === step ? 'active' : (i < step ? 'done' : '')}">
                        ${i < step ? '<i class="fa-solid fa-check"></i>' : i}
                    </div>
                </div>
                ${i < 5 ? `<div class="vendor-step-line ${i < step ? 'done' : ''}"></div>` : ''}
            `).join('')}
        </div>
    `;

    switch (step) {
        case 1:
            html += `
                <div class="form-group">
                    <label class="form-label">Business Name</label>
                    <input type="text" class="form-input" id="v-bizname" placeholder="e.g. Chill & Serve Ghana" value="${state.authData.bizname || ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-select" id="v-category">
                        ${state.categories.map(c => `<option value="${c.name}" ${state.authData.category === c.name ? 'selected' : ''}>${c.name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Business Description</label>
                    <textarea class="form-textarea" id="v-desc" placeholder="Describe your experience, team, and services...">${state.authData.desc || ''}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Years in Business</label>
                    <input type="number" class="form-input" id="v-experience" placeholder="e.g. 5" value="${state.authData.experience || ''}">
                </div>
                <div style="display:flex;gap:10px;" class="mt-12">
                    <button class="btn btn-outline btn-full" onclick="state.authMode='account-type'; renderAuthModal();">Back</button>
                    <button class="btn btn-primary btn-full" onclick="saveVendorStep1()">Next Step</button>
                </div>
            `;
            break;

        case 2:
            html += `
                <div class="form-group">
                    <label class="form-label">Primary Phone</label>
                    <input type="text" class="form-input" id="v-phone" placeholder="+233..." value="${state.authData.phone || ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">WhatsApp Number</label>
                    <input type="text" class="form-input" id="v-whatsapp" placeholder="+233..." value="${state.authData.whatsapp || ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">Business Email</label>
                    <input type="email" class="form-input" id="v-email" placeholder="sales@mybusiness.com" value="${state.authData.email || ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">Website (optional)</label>
                    <input type="url" class="form-input" id="v-website" placeholder="https://..." value="${state.authData.website || ''}">
                </div>
                <div style="display:flex;gap:10px;" class="mt-12">
                    <button class="btn btn-outline btn-full" onclick="state.authStep=1; renderAuthModal();">Back</button>
                    <button class="btn btn-primary btn-full" onclick="saveVendorStep2()">Next Step</button>
                </div>
            `;
            break;

        case 3:
            const radiusVal = parseInt(state.authData.radius) || 50;
            const hoursType = state.authData.hours_type || 'always';
            html += `
                <div class="form-group">
                    <label class="form-label">Business Location</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" class="form-input" id="v-address" placeholder="e.g. East Legon, Accra" value="${state.authData.address || ''}">
                        <button class="btn btn-outline" onclick="getLiveLocation(event)" style="padding: 0 12px; background: var(--gray-50); border: 1px solid var(--gray-300);" title="Use Live Location"><i class="fa-solid fa-location-crosshairs"></i></button>
                    </div>
                    <div id="v-coords-status" style="font-size:0.75rem; color:var(--success); margin-top:4px; display:${state.authData.latitude ? 'block' : 'none'};">
                        <i class="fa-solid fa-check-circle"></i> Live Location linked (${state.authData.latitude ? parseFloat(state.authData.latitude).toFixed(4) : ''}, ${state.authData.longitude ? parseFloat(state.authData.longitude).toFixed(4) : ''})
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Service Radius (from location)</label>
                    <input type="range" min="1" max="500" class="form-range" id="v-radius-range" value="${radiusVal}" oninput="document.getElementById('v-radius-val').textContent = this.value + ' km';">
                    <div style="font-size:0.75rem; color:var(--gray-500); display:flex; justify-content:space-between; margin-top:4px;">
                        <span>1 km</span>
                        <strong id="v-radius-val">${radiusVal} km</strong>
                        <span>500 km</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Working Hours / Availability</label>
                    <div style="display:flex; gap:16px; margin-bottom:10px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.83rem;">
                            <input type="radio" name="v-hours-type" value="always" ${hoursType === 'always' ? 'checked' : ''} onchange="toggleHoursTypeUI('always')">
                            <span>Always Available / 24/7</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:0.83rem;">
                            <input type="radio" name="v-hours-type" value="custom" ${hoursType === 'custom' ? 'checked' : ''} onchange="toggleHoursTypeUI('custom')">
                            <span>Custom Days & Hours</span>
                        </label>
                    </div>
                    
                    <div id="v-custom-hours-container" style="display:${hoursType === 'custom' ? 'block' : 'none'}; background:var(--gray-50); padding:12px; border-radius:8px; border:1px solid var(--gray-200);">
                        ${['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => {
                const isChecked = state.authData.custom_days?.[day]?.active;
                const startVal = state.authData.custom_days?.[day]?.start || '08:00';
                const endVal = state.authData.custom_days?.[day]?.end || '17:00';
                return `
                                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; font-size:0.8rem;">
                                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                                        <input type="checkbox" id="v-day-${day}" ${isChecked ? 'checked' : ''} onchange="toggleDayInputs('${day}')">
                                        <span>${day}</span>
                                    </label>
                                    <div id="v-day-${day}-inputs" style="display:${isChecked ? 'flex' : 'none'}; align-items:center; gap:4px;">
                                        <input type="time" class="form-input" id="v-day-${day}-start" value="${startVal}" style="padding:2px 4px; font-size:0.75rem; width:80px;">
                                        <span>to</span>
                                        <input type="time" class="form-input" id="v-day-${day}-end" value="${endVal}" style="padding:2px 4px; font-size:0.75rem; width:80px;">
                                    </div>
                                    <div id="v-day-${day}-closed" style="display:${isChecked ? 'none' : 'block'}; color:var(--gray-400); font-size:0.75rem;">Closed</div>
                                </div>
                            `;
            }).join('')}
                    </div>
                </div>
                <div style="display:flex;gap:10px;" class="mt-12">
                    <button class="btn btn-outline btn-full" onclick="state.authStep=2; renderAuthModal();">Back</button>
                    <button class="btn btn-primary btn-full" onclick="saveVendorStep3()">Next Step</button>
                </div>
            `;
            break;

        case 4:
            html += `
                <h4 style="font-size:0.9rem;margin-bottom:12px;">Add Service Packages</h4>
                <div id="v-packages-container">
                    ${(state.authData.packages || [['Basic Package', '', 'Describe package detail services.']]).map((p, i) => `
                        <div class="card mb-12" style="position:relative;padding:12px;">
                            <button class="btn btn-ghost btn-sm" style="position:absolute;top:6px;right:6px;" onclick="removeVendorPackage(${i})"><i class="fa-solid fa-trash text-error"></i></button>
                            <div class="form-group" style="margin-bottom:8px;">
                                <input type="text" class="form-input" id="p-name-${i}" placeholder="Package Name" value="${p[0]}">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <textarea class="form-textarea" style="min-height:60px;" id="p-details-${i}" placeholder="Included items details...">${p[2]}</textarea>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <button class="btn btn-outline btn-sm btn-full mb-12" onclick="addVendorPackageRow()"><i class="fa-solid fa-plus"></i> Add Package</button>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-outline btn-full" onclick="state.authStep=3; renderAuthModal();">Back</button>
                    <button class="btn btn-primary btn-full" onclick="saveVendorStep4()">Next Step</button>
                </div>
            `;
            break;

        case 5:
            html += `
                <h4 style="font-size:0.9rem;margin-bottom:12px;">Verify Owner Identity (KYC)</h4>
                <p style="font-size:0.75rem;color:var(--gray-500);margin-bottom:12px;">Required before accepting active client event bookings.</p>
                <div class="form-group">
                    <label class="form-label">Accepted ID Type</label>
                    <select class="form-select" id="v-id-type">
                        <option value="Ghana Card / National ID">Ghana Card / National ID</option>
                        <option value="Passport">Passport</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Voter ID">Voter ID</option>
                    </select>
                </div>
                <div class="kyc-upload-zone mb-12" onclick="document.getElementById('file-id-front').click()">
                    <i class="fa-solid fa-id-card"></i>
                    <p id="front-status">${state.authData.id_front ? `<i class="fa-solid fa-circle-check text-success"></i> Uploaded` : 'Upload Front of ID'}</p>
                    <input type="file" id="file-id-front" accept="image/*" style="display:none;" onchange="handleKycFileSelect(event, 'id-front')">
                    <input type="hidden" id="v-id-front" value="${state.authData.id_front || ''}">
                </div>
                <div class="kyc-upload-zone mb-16" onclick="document.getElementById('file-selfie').click()">
                    <i class="fa-solid fa-camera"></i>
                    <p id="selfie-status">${state.authData.selfie ? `<i class="fa-solid fa-circle-check text-success"></i> Uploaded` : 'Upload Selfie with ID'}</p>
                    <input type="file" id="file-selfie" accept="image/*" style="display:none;" onchange="handleKycFileSelect(event, 'selfie')">
                    <input type="hidden" id="v-selfie" value="${state.authData.selfie || ''}">
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-outline btn-full" onclick="state.authStep=4; renderAuthModal();">Back</button>
                    <button class="btn btn-primary btn-full" onclick="saveVendorStep5()">Submit Application</button>
                </div>
            `;
            break;
    }

    return html;
}

function simulateFileUpload(type) {
    const status = document.getElementById(type === 'id-front' ? 'front-status' : 'selfie-status');
    const hidden = document.getElementById(type === 'id-front' ? 'v-id-front' : 'v-selfie');
    if (status && hidden) {
        status.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Uploading...`;
        setTimeout(() => {
            const fakeUrl = `img/kyc/${type}_uploaded.jpg`;
            hidden.value = fakeUrl;
            state.authData[type === 'id-front' ? 'id_front' : 'selfie'] = fakeUrl;
            status.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i> Uploaded successfully!`;
        }, 1500);
    }
}

window.handleKycFileSelect = function (event, type) {
    const file = event.target.files[0];
    if (!file) return;
    const status = document.getElementById(type === 'id-front' ? 'front-status' : 'selfie-status');
    const hidden = document.getElementById(type === 'id-front' ? 'v-id-front' : 'v-selfie');
    if (status && hidden) {
        status.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Reading file...`;
        const reader = new FileReader();
        reader.onload = function (e) {
            hidden.value = e.target.result;
            state.authData[type === 'id-front' ? 'id_front' : 'selfie'] = e.target.result;
            status.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i> ${file.name.substring(0, 15)}... loaded!`;
        };
        reader.readAsDataURL(file);
    }
};

function addVendorPackageRow() {
    collectCurrentPackages();
    state.authData.packages.push(['New Package', '', 'Package inclusions...']);
    renderAuthModal();
}

function removeVendorPackage(idx) {
    collectCurrentPackages();
    state.authData.packages.splice(idx, 1);
    renderAuthModal();
}

function collectCurrentPackages() {
    state.authData.packages = [];
    const container = document.getElementById('v-packages-container');
    if (!container) return;
    const cards = container.querySelectorAll('.card');
    cards.forEach((c, i) => {
        const name = document.getElementById(`p-name-${i}`)?.value || '';
        const price = '';
        const details = document.getElementById(`p-details-${i}`)?.value || '';
        state.authData.packages.push([name, price, details]);
    });
}

function saveVendorStep1() {
    const biz = document.getElementById('v-bizname').value.trim();
    const cat = document.getElementById('v-category').value;
    const desc = document.getElementById('v-desc').value.trim();
    const exp = parseInt(document.getElementById('v-experience').value) || 0;

    if (!biz || !desc) {
        showPushNotification('Fields Required', 'Please input business name and description.');
        return;
    }
    state.authData.bizname = biz;
    state.authData.category = cat;
    state.authData.desc = desc;
    state.authData.experience = exp;

    state.authStep = 2;
    renderAuthModal();
}

function saveVendorStep2() {
    state.authData.phone = document.getElementById('v-phone').value.trim();
    state.authData.whatsapp = document.getElementById('v-whatsapp').value.trim();
    state.authData.email = document.getElementById('v-email').value.trim();
    state.authData.website = document.getElementById('v-website').value.trim();

    state.authStep = 3;
    renderAuthModal();
}

/************* VENDOR REGISTRATION STEP 3 *************/
function saveVendorStep3() {
    state.authData.address = document.getElementById('v-address').value.trim();
    state.authData.radius = document.getElementById('v-radius-range').value + ' km';

    const hoursTypeRadios = document.getElementsByName('v-hours-type');
    let hoursType = 'always';
    for (const r of hoursTypeRadios) {
        if (r.checked) hoursType = r.value;
    }
    state.authData.hours_type = hoursType;

    if (hoursType === 'always') {
        state.authData.hours = 'Always Available / 24/7';
    } else {
        const customDays = {};
        const parts = [];
        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach(day => {
            const chk = document.getElementById('v-day-' + day);
            if (chk && chk.checked) {
                const start = document.getElementById('v-day-' + day + '-start').value;
                const end = document.getElementById('v-day-' + day + '-end').value;
                customDays[day] = { active: true, start, end };
                parts.push(`${day}: ${start}-${end}`);
            } else {
                customDays[day] = { active: false };
            }
        });
        state.authData.custom_days = customDays;
        state.authData.hours = parts.length > 0 ? parts.join(', ') : 'Closed';
    }

    state.authStep = 4;
    renderAuthModal();
}

window.getLiveLocation = function (event) {
    if (event) event.preventDefault();
    if (!navigator.geolocation) {
        showPushNotification('Not Supported', 'Geolocation is not supported by your browser.');
        return;
    }
    const statusEl = document.getElementById('v-coords-status');
    const addressInput = document.getElementById('v-address');
    showPushNotification('Locating', 'Fetching coordinates...');
    navigator.geolocation.getCurrentPosition(
        pos => {
            state.authData.latitude = pos.coords.latitude;
            state.authData.longitude = pos.coords.longitude;
            if (statusEl) {
                statusEl.innerHTML = `<i class="fa-solid fa-check-circle"></i> Live Location linked (${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)})`;
                statusEl.style.display = 'block';
            }
            if (addressInput && !addressInput.value.trim()) {
                addressInput.value = 'Live Location (' + pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4) + ')';
            }
            showPushNotification('Location Linked', 'Successfully acquired coordinates!');
        },
        err => {
            showPushNotification('Location Error', 'Unable to retrieve location: ' + err.message);
        }
    );
};

window.toggleHoursTypeUI = function (type) {
    const container = document.getElementById('v-custom-hours-container');
    if (container) {
        container.style.display = type === 'custom' ? 'block' : 'none';
    }
};

window.toggleDayInputs = function (day) {
    const chk = document.getElementById('v-day-' + day);
    const inputs = document.getElementById('v-day-' + day + '-inputs');
    const closed = document.getElementById('v-day-' + day + '-closed');
    if (chk && inputs && closed) {
        inputs.style.display = chk.checked ? 'flex' : 'none';
        closed.style.display = chk.checked ? 'none' : 'block';
    }
};

/************* VENDOR REGISTRATION STEP 4 *************/
function saveVendorStep4() {
    collectCurrentPackages();
    state.authStep = 5;
    renderAuthModal();
}

/************* VENDOR REGISTRATION STEP 5 *************/
function saveVendorStep5() {
    const idType = document.getElementById('v-id-type').value;
    const idFront = document.getElementById('v-id-front').value;
    const selfie = document.getElementById('v-selfie').value;

    if (!idFront || !selfie) {
        showPushNotification('KYC Documents Required', 'Please upload both your ID front and selfie before proceeding.');
        return;
    }

    state.authData.id_type = idType;
    state.authData.id_front = idFront;
    state.authData.selfie = selfie;

    const btn = document.querySelector('button[onclick="saveVendorStep5()"]');

    ActionLock.execute(btn, 'Submitting Application...', async () => {
        try {
            const payload = {
                business_name: state.authData.bizname,
                category: state.authData.category,
                description: state.authData.desc,
                location: state.authData.address,
                phone: state.authData.phone,
                email: state.authData.email,
                experience: state.authData.experience
            };

            const res = await API.registerVendor(payload);
            const vid = res.vendor_id;
            const pkgs = (state.authData.packages || []).map(p => ({
                name: p[0],
                price: p[1],
                details: p[2]
            }));

            const updatePayload = {
                id: vid,
                whatsapp: state.authData.whatsapp,
                website: state.authData.website,
                service_radius: state.authData.radius,
                packages_pricing: pkgs,
                instant_booking: 0,
                verification_status: 'pending',
                verification_badge: 'blue'
            };

            await API.updateVendor(updatePayload);
            await API.updateProfile({
                kyc_status: 'pending_verification',
                kyc_id_type: state.authData.id_type,
                kyc_id_front: state.authData.id_front,
                kyc_selfie: state.authData.selfie,
                kyc_submitted_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
            });

            const sessionRes = await API.getSession();
            state.user = sessionRes.user;
            showPushNotification('Application Submitted', 'Our moderation team will review your application.');
            closeModal();
            if (typeof updateSidebarUI === 'function') updateSidebarUI();
            if (typeof navigateTo === 'function') navigateTo('vendor-dash');
        } catch (e) {
            showPushNotification('Submission Error', e.message || 'Error completing application');
        }
    });
}


window.closeAccountDeletionModal = function () {
    const modal = document.getElementById('account-deletion-custom-modal');
    if (modal) modal.remove();
};

window.showAccountDeletionModal = function () {
    window.closeAccountDeletionModal();

    const user = (window.state && window.state.user) ? window.state.user : null;
    const userDisplay = user ? (user.email || user.phone || user.name || 'Your Account') : '';

    const modal = document.createElement('div');
    modal.id = 'account-deletion-custom-modal';
    modal.className = 'modal-overlay open';
    modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); z-index:999999; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; animation:fadeIn 0.25s ease-out;';

    modal.innerHTML = `
        <div class="modal-sheet" style="width:100%; max-width:440px; border-radius:28px; padding:28px 24px; text-align:center; background:#0F1923; color:#fff; border:1px solid rgba(239,68,68,0.35); box-shadow:0 25px 60px rgba(0,0,0,0.8); position:relative; animation:slideUp 0.3s cubic-bezier(0.16,1,0.3,1);">
            <button onclick="closeAccountDeletionModal()" style="position:absolute; top:18px; right:18px; background:rgba(255,255,255,0.08); border:none; color:#94A3B8; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1rem; transition:all 0.2s;">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div style="width:68px; height:68px; border-radius:50%; background:rgba(239,68,68,0.12); border:2px solid #EF4444; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; color:#EF4444; font-size:1.8rem; box-shadow:0 0 20px rgba(239,68,68,0.25);">
                <i class="fa-solid fa-user-slash"></i>
            </div>

            <h3 style="font-family:'Fraunces',serif; font-size:1.45rem; font-weight:800; margin-bottom:6px; color:#fff;">Delete Ohati Account</h3>
            <p style="font-size:0.83rem; color:#94A3B8; line-height:1.5; margin-bottom:20px;">
                This action will permanently deactivate your profile, remove public listings, and anonymize your account data.
            </p>

            <div id="del-modal-error" style="display:none; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; padding:10px 14px; border-radius:12px; margin-bottom:16px; text-align:left;"></div>

            ${user ? `
                <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:12px 16px; margin-bottom:18px; text-align:left; display:flex; align-items:center; gap:12px;">
                    <div style="width:38px; height:38px; border-radius:50%; background:#1E293B; display:flex; align-items:center; justify-content:center; color:#F2A735; font-weight:bold; font-size:1rem; border:1px solid rgba(242,167,53,0.3);">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div style="overflow:hidden;">
                        <div style="font-size:0.85rem; font-weight:700; color:#fff;">${user.name || 'Active Account'}</div>
                        <div style="font-size:0.75rem; color:#94A3B8; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">${userDisplay}</div>
                    </div>
                </div>
                <div style="text-align:left; margin-bottom:20px;">
                    <label style="font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px; display:block;">Enter Password to Confirm:</label>
                    <div style="position:relative;">
                        <input type="password" id="del-modal-pass" placeholder="Your password" style="width:100%; padding:12px 14px; background:#18222D; border:1px solid rgba(255,255,255,0.15); border-radius:12px; color:#fff; font-size:0.9rem; box-sizing:border-box; outline:none;">
                    </div>
                </div>
            ` : `
                <div style="text-align:left; margin-bottom:14px;">
                    <label style="font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px; display:block;">Email or Phone Number:</label>
                    <input type="text" id="del-modal-id" placeholder="email@example.com or phone" style="width:100%; padding:12px 14px; background:#18222D; border:1px solid rgba(255,255,255,0.15); border-radius:12px; color:#fff; font-size:0.9rem; box-sizing:border-box; outline:none;">
                </div>
                <div style="text-align:left; margin-bottom:20px;">
                    <label style="font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px; display:block;">Password:</label>
                    <input type="password" id="del-modal-pass" placeholder="Your password" style="width:100%; padding:12px 14px; background:#18222D; border:1px solid rgba(255,255,255,0.15); border-radius:12px; color:#fff; font-size:0.9rem; box-sizing:border-box; outline:none;">
                </div>
            `}

            <div style="display:flex; gap:12px; margin-top:8px;">
                <button onclick="closeAccountDeletionModal()" style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#CBD5E1; font-weight:700; border-radius:14px; padding:13px; font-size:0.9rem; cursor:pointer; transition:all 0.2s;">
                    Cancel
                </button>
                <button id="del-modal-submit-btn" onclick="executeCustomAccountDeletion()" style="flex:1.4; background:linear-gradient(135deg,#EF4444,#DC2626); color:#fff; font-weight:700; border:none; border-radius:14px; padding:13px; font-size:0.9rem; cursor:pointer; box-shadow:0 4px 15px rgba(239,68,68,0.4); transition:all 0.2s;">
                    <i class="fa-solid fa-trash-can" style="margin-right:6px;"></i> Delete Account
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => {
        const inputToFocus = document.getElementById('del-modal-pass') || document.getElementById('del-modal-id');
        if (inputToFocus) inputToFocus.focus();
    }, 100);
};

window.executeCustomAccountDeletion = function () {
    const errorBox = document.getElementById('del-modal-error');
    const btn = document.getElementById('del-modal-submit-btn');
    if (errorBox) errorBox.style.display = 'none';

    const user = (window.state && window.state.user) ? window.state.user : null;
    const passInput = document.getElementById('del-modal-pass');
    const idInput = document.getElementById('del-modal-id');

    const password = passInput ? passInput.value : '';
    const identifier = idInput ? idInput.value.trim() : (user ? (user.email || user.phone) : '');

    if (!password) {
        if (errorBox) { errorBox.textContent = 'Please enter your password to confirm deletion.'; errorBox.style.display = 'block'; }
        if (passInput) passInput.focus();
        return;
    }

    if (!user && !identifier) {
        if (errorBox) { errorBox.textContent = 'Please enter your email or phone number.'; errorBox.style.display = 'block'; }
        if (idInput) idInput.focus();
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Deleting...';
    }

    const payload = user ? { user_id: user.id, password } : { identifier, password };

    API.post('delete_account', payload).then(res => {
        closeAccountDeletionModal();
        if (typeof showAccountDeletedSuccessModal === 'function') {
            showAccountDeletedSuccessModal();
        } else {
            alert('Your account has been deleted.');
            if (typeof handleLogout === 'function') handleLogout();
        }
    }).catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash-can" style="margin-right:6px;"></i> Delete Account';
        }
        if (errorBox) {
            errorBox.textContent = err.message || 'Account deletion failed. Please check your credentials.';
            errorBox.style.display = 'block';
        } else {
            alert(err.message || 'Account deletion failed.');
        }
    });
};

window.confirmDeleteAccount = function () {
    window.showAccountDeletionModal();
};




window.showAccountDeletedSuccessModal = function () {
    let modal = document.getElementById('account-deleted-pro-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'account-deleted-pro-modal';
        modal.className = 'modal-overlay open';
        modal.style.zIndex = '999999';
        modal.innerHTML = `
            <div class="modal-sheet" style="max-width:440px; margin:auto; border-radius:24px; padding:28px 24px; text-align:center; background:#0F1923; color:#fff; border:1px solid rgba(255,255,255,0.15); box-shadow:0 24px 60px rgba(0,0,0,0.8);">
                <div style="width:72px; height:72px; border-radius:50%; background:rgba(239,68,68,0.15); border:2px solid #EF4444; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; color:#EF4444; font-size:2rem;">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <h3 style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:800; margin-bottom:8px; color:#fff;">Account Deleted</h3>
                <p style="font-size:0.88rem; color:#94A3B8; line-height:1.5; margin-bottom:22px;">
                    Your account and associated profile data have been deleted.
                </p>
                <button class="btn btn-primary btn-full" onclick="closeAccountDeletedProModal()" style="background:linear-gradient(135deg,#EF4444,#DC2626); color:#fff; font-weight:700; border-radius:14px; padding:14px;">Return Home</button>
            </div>
        `;
        document.body.appendChild(modal);
    } else {
        modal.classList.add('open');
    }
};

window.closeAccountDeletedProModal = function () {
    const modal = document.getElementById('account-deleted-pro-modal');
    if (modal) modal.remove();
    if (typeof handleLogout === 'function') handleLogout();
    else if (typeof navigateTo === 'function') navigateTo('home');
};

window.triggerAccountDeletionFlow = function () {
    showConfirmModal({
        title: 'Delete Account?',
        message: 'Are you sure you want to delete your account? This will deactivate your profile and log you out immediately.',
        icon: 'fa-trash-can',
        confirmText: 'Yes, Delete Account',
        cancelText: 'Cancel',
        type: 'danger',
        onConfirm: () => {
            if (window.API && typeof API.deleteAccount === 'function') {
                API.deleteAccount().then(res => {
                    showAccountDeletedSuccessModal();
                }).catch(err => {
                    showAccountDeletedSuccessModal();
                });
            } else {
                showAccountDeletedSuccessModal();
            }
        }
    });
};

window.showMandatoryAuthLockScreen = function (initialMode) {
    const currentPath = decodeURIComponent(window.location.pathname.split('/').pop() || '');
    if (currentPath.includes('login.php') || currentPath.includes('register.php')) {
        return;
    }
    let overlay = document.getElementById('mandatory-auth-lock-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'mandatory-auth-lock-overlay';
        overlay.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:#081729; z-index:9999999; display:flex; align-items:center; justify-content:center; padding:calc(20px + env(safe-area-inset-top, 0px)) 20px calc(20px + env(safe-area-inset-bottom, 0px)) 20px; box-sizing:border-box; overflow-y:auto;';
        document.body.appendChild(overlay);
    } else {
        overlay.style.display = 'flex';
    }

    if (!window._mandatorySignupDraft) window._mandatorySignupDraft = {};

    window.renderMandatoryAuthContent = function (currentMode) {
        const mode = currentMode || 'login';
        window._currentAuthLockMode = mode;
        if (overlay) overlay.style.display = 'flex';

        if (mode === 'otp') {
            const email = window._mandatorySignupDraft.email || '';
            const phone = window._mandatorySignupDraft.phone || '';
            const role = window._mandatorySignupDraft.role || 'customer';
            const stepLabel = (role === 'vendor') ? 'Step 3 of 3' : 'Step 2 of 2';
            overlay.innerHTML = `
                <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:24px; width:100%; max-width:440px; padding:32px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                    <div style="width:76px; height:76px; border-radius:20px; overflow:hidden; border:2px solid var(--accent, #F2A735); margin:0 auto 16px; box-shadow:0 8px 24px rgba(242,167,53,0.25);">
                        <img src="img/app_icon.png" style="width:100%; height:100%; object-fit:cover;" alt="Ohati App Icon">
                    </div>
                    <div style="font-size:0.75rem; font-weight:800; color:var(--accent, #F2A735); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">${stepLabel}</div>
                    <h2 style="font-family:'Fraunces',serif; font-size:1.6rem; font-weight:800; margin:0 0 6px 0; color:#FFF;">Verify Your Account</h2>
                    <p style="font-size:0.85rem; color:#94A3B8; margin:0 0 20px 0;">A 6-digit verification code was sent to <strong>${phone || email}</strong> via SMS & Email.</p>

                    <form onsubmit="handleMandatoryOTPVerifySubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:16px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">6-Digit OTP Code</label>
                            <input type="text" id="m-lock-otp" maxlength="6" required placeholder="123456" style="width:100%; padding:14px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:1.4rem; letter-spacing:6px; text-align:center; font-weight:800; outline:none; box-sizing:border-box;">
                        </div>
                        <div id="m-lock-error" style="display:none; padding:10px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; text-align:center;"></div>
                        <button type="submit" id="m-lock-btn" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem;">Verify & Complete Registration</button>
                    </form>

                    <div style="margin-top:20px; font-size:0.85rem; color:#94A3B8;">
                        Didn't receive the code? <a href="#" onclick="handleResendSignupOTP(event); return false;" style="color:var(--accent, #F2A735); font-weight:700; text-decoration:none;">Resend OTP</a>
                    </div>
                    <div style="margin-top:10px; font-size:0.85rem; color:#94A3B8;">
                        <a href="#" onclick="renderMandatoryAuthContent('${role === 'vendor' ? 'vendor-details' : 'signup'}'); return false;" style="color:#CBD5E1; text-decoration:underline;">Back</a>
                    </div>
                </div>
            `;
        } else if (mode === 'vendor-details') {
            const draft = window._mandatorySignupDraft || {};
            overlay.innerHTML = `
                <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:24px; width:100%; max-width:440px; padding:32px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                    <div style="width:76px; height:76px; border-radius:20px; overflow:hidden; border:2px solid var(--accent, #F2A735); margin:0 auto 16px; box-shadow:0 8px 24px rgba(242,167,53,0.25);">
                        <img src="img/app_icon.png" style="width:100%; height:100%; object-fit:cover;" alt="Ohati App Icon">
                    </div>
                    <div style="font-size:0.75rem; font-weight:800; color:var(--accent, #F2A735); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Step 2 of 3 — Vendor Profile</div>
                    <h2 style="font-family:'Fraunces',serif; font-size:1.6rem; font-weight:800; margin:0 0 6px 0; color:#FFF;">Vendor Profile Details</h2>
                    <p style="font-size:0.85rem; color:#94A3B8; margin:0 0 20px 0;">Tell clients about your business & primary service</p>

                    <form onsubmit="handleMandatoryVendorDetailsSubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Business / Brand Name *</label>
                            <input type="text" id="m-lock-bname" required placeholder="e.g. Royal Crown Event Services" value="${draft.business_name || draft.bname || ''}" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Primary Service Category *</label>
                            <select id="m-lock-category" required style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none;">
                                ${['Photography & Videography', 'Catering & Drinks', 'DJ & Sound System', 'Event Planning & Decor', 'Makeup & Hair Styling', 'Venues & Halls', 'Ushering & Security', 'MC & Entertainment', 'Other Event Services'].map(c => `<option value="${c}" ${(draft.category === c) ? 'selected' : ''} style="background:#0F1923; color:#FFF;">${c}</option>`).join('')}
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">City / Business Location *</label>
                            <input type="text" id="m-lock-location" required placeholder="e.g. Accra, Ghana" value="${draft.location || draft.city || ''}" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Short Service Description</label>
                            <textarea id="m-lock-desc" rows="2" placeholder="Brief description of services offered..." style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.85rem; outline:none; box-sizing:border-box; resize:none;">${draft.description || ''}</textarea>
                        </div>
                        <div id="m-lock-error" style="display:none; padding:10px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; text-align:center;"></div>
                        <button type="submit" id="m-lock-btn" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem; margin-top:6px;">Continue to Verification</button>
                    </form>

                    <div style="margin-top:20px; font-size:0.85rem; color:#94A3B8;">
                        <a href="#" onclick="renderMandatoryAuthContent('signup'); return false;" style="color:#CBD5E1; text-decoration:underline;">Back to Step 1</a>
                    </div>
                </div>
            `;
        } else if (mode === 'signup') {
            const draft = window._mandatorySignupDraft || {};
            const isVendorSelected = (draft.role === 'vendor');
            overlay.innerHTML = `
                <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:24px; width:100%; max-width:440px; padding:32px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                    <div style="width:76px; height:76px; border-radius:20px; overflow:hidden; border:2px solid var(--accent, #F2A735); margin:0 auto 16px; box-shadow:0 8px 24px rgba(242,167,53,0.25);">
                        <img src="img/app_icon.png" style="width:100%; height:100%; object-fit:cover;" alt="Ohati App Icon">
                    </div>
                    <div style="font-size:0.75rem; font-weight:800; color:var(--accent, #F2A735); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Step 1 — Basic Credentials</div>
                    <h2 style="font-family:'Fraunces',serif; font-size:1.6rem; font-weight:800; margin:0 0 6px 0; color:#FFF;">Create Your Account</h2>
                    <p style="font-size:0.85rem; color:#94A3B8; margin:0 0 20px 0;">Join Ohati to discover and book verified event services</p>

                    <form onsubmit="handleMandatorySignupSubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Account Role</label>
                            <select id="m-lock-role" onchange="toggleVendorAuthFields(this.value)" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none;">
                                <option value="customer" ${(draft.role === 'customer' || !draft.role) ? 'selected' : ''} style="background:#0F1923; color:#FFF;">Customer (Planning Events)</option>
                                <option value="vendor" ${(draft.role === 'vendor') ? 'selected' : ''} style="background:#0F1923; color:#FFF;">Vendor (Offering Event Services)</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Full Name *</label>
                            <input type="text" id="m-lock-name" required placeholder="John Doe" value="${draft.name || ''}" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Email Address *</label>
                            <input type="email" id="m-lock-email" required placeholder="email@example.com" value="${draft.email || ''}" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Phone Number *</label>
                            <input type="tel" id="m-lock-phone" required placeholder="+233 24 123 4567" value="${draft.phone || ''}" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Password *</label>
                            <input type="password" id="m-lock-pass" required placeholder="Minimum 6 characters" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:4px;">Confirm Password *</label>
                            <input type="password" id="m-lock-confirm" required placeholder="Re-enter password" style="width:100%; padding:12px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div id="m-lock-error" style="display:none; padding:10px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; text-align:center;"></div>
                        <button type="submit" id="m-lock-btn" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem; margin-top:6px;">
                            ${isVendorSelected ? 'Continue to Vendor Details' : 'Send Verification Code'}
                        </button>
                    </form>

                    <div style="margin-top:20px; font-size:0.85rem; color:#94A3B8;">
                        Already have an account? <a href="#" onclick="renderMandatoryAuthContent('login'); return false;" style="color:var(--accent, #F2A735); font-weight:700; text-decoration:none;">Log In</a>
                    </div>
                </div>
            `;
        } else {
            overlay.innerHTML = `
                <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:24px; width:100%; max-width:440px; padding:32px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                    <div style="width:76px; height:76px; border-radius:20px; overflow:hidden; border:2px solid var(--accent, #F2A735); margin:0 auto 16px; box-shadow:0 8px 24px rgba(242,167,53,0.25);">
                        <img src="img/app_icon.png" style="width:100%; height:100%; object-fit:cover;" alt="Ohati App Icon">
                    </div>
                    <h2 style="font-family:'Fraunces',serif; font-size:1.6rem; font-weight:800; margin:0 0 6px 0; color:#FFF;">Sign In to Ohati</h2>
                    <p style="font-size:0.85rem; color:#94A3B8; margin:0 0 24px 0;">Please log in to access event vendors and services</p>

                    <form onsubmit="handleMandatoryLoginSubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:16px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Email or Phone Number</label>
                            <input type="text" id="m-lock-id" required placeholder="email@example.com or phone" style="width:100%; padding:13px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.95rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Password</label>
                            <input type="password" id="m-lock-pass" required placeholder="Your password" style="width:100%; padding:13px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.95rem; outline:none; box-sizing:border-box;">
                        </div>
                        <div id="m-lock-error" style="display:none; padding:10px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; text-align:center;"></div>
                        <button type="submit" id="m-lock-btn" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem; margin-top:6px;">Sign In</button>
                    </form>

                    <div style="margin-top:24px; font-size:0.85rem; color:#94A3B8;">
                        Don't have an account? <a href="#" onclick="renderMandatoryAuthContent('signup'); return false;" style="color:var(--accent, #F2A735); font-weight:700; text-decoration:none;">Sign up</a>
                    </div>
                </div>
            `;
        }
    };

    window.renderMandatoryAuthContent(initialMode || 'login');
};

window.unlockMandatoryAuthScreen = function () {
    window._currentAuthLockMode = null;
    const overlay = document.getElementById('mandatory-auth-lock-overlay');
    if (overlay) {
        overlay.style.display = 'none';
        try { overlay.remove(); } catch (e) { }
    }
};

window.handleMandatoryLoginSubmit = function (e) {
    if (e) e.preventDefault();
    const btn = document.getElementById('m-lock-btn');
    const idInput = document.getElementById('m-lock-id');
    const passInput = document.getElementById('m-lock-pass');
    const errBox = document.getElementById('m-lock-error');

    if (errBox) errBox.style.display = 'none';

    const identifier = idInput ? idInput.value.trim() : '';
    const password = passInput ? passInput.value : '';

    if (!identifier || !password) {
        if (errBox) { errBox.textContent = 'Please enter both identifier and password.'; errBox.style.display = 'block'; }
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.65';
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Signing in...';
    }
    if (idInput) idInput.disabled = true;
    if (passInput) passInput.disabled = true;

    function unlockLogin() {
        if (btn) {
            btn.disabled = false;
            btn.style.pointerEvents = 'auto';
            btn.style.opacity = '1';
            btn.textContent = 'Sign In';
        }
        if (idInput) idInput.disabled = false;
        if (passInput) passInput.disabled = false;
    }

    API.login({ identifier, password }).then(res => {
        if (res.user) {
            state.user = res.user;
            const token = res.auth_token || res.token;
            if (token) localStorage.setItem('ohati_auth_token', token);
            localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            if (typeof window.clearAllAuthOverlays === 'function') window.clearAllAuthOverlays();
            else if (typeof window.unlockMandatoryAuthScreen === 'function') window.unlockMandatoryAuthScreen();
            if (typeof updateAppHeader === 'function') updateAppHeader();

            Promise.allSettled([
                API.getCategories(),
                API.getVendors(),
                API.getVendors({ premium_only: 1 }),
                API.get('get_advertisements'),
                API.getPopularVendors(),
                API.getBookings(),
                API.getFavorites(),
                API.getEvent(),
                API.get('get_faqs')
            ]).then(results => {
                state.categories = results[0].status === 'fulfilled' ? results[0].value : [];
                state.vendors = results[1].status === 'fulfilled' ? results[1].value : [];
                state.bookings = results[5].status === 'fulfilled' ? results[5].value : [];
                state.favorites = results[6].status === 'fulfilled' ? results[6].value : [];

                if (typeof navigateTo === 'function') {
                    navigateTo('home');
                }
            });
        } else {
            unlockLogin();
            throw new Error(res.error || 'Login failed.');
        }
    }).catch(err => {
        unlockLogin();
        if (err && (err.requires_verification || (err.message && err.message.toLowerCase().includes('verification code')))) {
            const targetVal = err.target || err.email || err.phone || identifier;
            window._mandatorySignupDraft = { target: targetVal, email: targetVal, phone: targetVal };
            window.renderMandatoryAuthContent('otp');
            setTimeout(() => {
                const otpErr = document.getElementById('m-lock-error');
                if (otpErr) {
                    otpErr.textContent = err.message || 'Account verification incomplete. A new code has been sent via SMS & Email.';
                    otpErr.style.display = 'block';
                }
            }, 50);
            return;
        }
        if (errBox) { errBox.textContent = err.message || 'Invalid credentials.'; errBox.style.display = 'block'; }
    });
};

window.toggleVendorAuthFields = function (role) {
    const btn = document.getElementById('m-lock-btn');
    if (btn) {
        btn.textContent = (role === 'vendor') ? 'Continue to Vendor Details' : 'Send Verification Code';
    }
};

window.handleMandatorySignupSubmit = function (e) {
    if (e) e.preventDefault();
    const nameInput = document.getElementById('m-lock-name');
    const emailInput = document.getElementById('m-lock-email');
    const phoneInput = document.getElementById('m-lock-phone');
    const passInput = document.getElementById('m-lock-pass');
    const confirmInput = document.getElementById('m-lock-confirm');
    const roleSelect = document.getElementById('m-lock-role');
    const errBox = document.getElementById('m-lock-error');

    if (errBox) errBox.style.display = 'none';

    const name = nameInput ? nameInput.value.trim() : '';
    const email = emailInput ? emailInput.value.trim() : '';
    const phone = phoneInput ? phoneInput.value.trim() : '';
    const password = passInput ? passInput.value : '';
    const confirm = confirmInput ? confirmInput.value : '';
    const role = roleSelect ? roleSelect.value : 'customer';

    const parts = name.split(' ');
    const fname = parts[0] || '';
    const lname = parts.slice(1).join(' ') || '';

    if (!name || !email || !phone || !password || !confirm) {
        if (errBox) { errBox.textContent = 'Please fill out all required fields.'; errBox.style.display = 'block'; }
        return;
    }

    if (password.length < 6) {
        if (errBox) { errBox.textContent = 'Password must be at least 6 characters long.'; errBox.style.display = 'block'; }
        return;
    }

    if (password !== confirm) {
        if (errBox) { errBox.textContent = 'Passwords do not match. Please re-enter your password.'; errBox.style.display = 'block'; }
        return;
    }

    if (!window._mandatorySignupDraft) window._mandatorySignupDraft = {};
    Object.assign(window._mandatorySignupDraft, {
        name, fname, lname, email, phone, password, confirm, confirm_password: confirm, role
    });

    if (role === 'vendor') {
        window.renderMandatoryAuthContent('vendor-details');
        return;
    }

    // Customer flow: Send OTP immediately
    const btn = document.getElementById('m-lock-btn');
    if (btn) {
        btn.disabled = true;
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.65';
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Sending Code...';
    }

    API.post('send_otp', {
        target: email || phone,
        email: email,
        phone: phone
    }).then(res => {
        window.renderMandatoryAuthContent('otp');
    }).catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.style.pointerEvents = 'auto';
            btn.style.opacity = '1';
            btn.textContent = 'Send Verification Code';
        }
        if (errBox) { errBox.textContent = err.message || 'Failed to send OTP code. Please try again.'; errBox.style.display = 'block'; }
    });
};

window.handleMandatoryVendorDetailsSubmit = function (e) {
    if (e) e.preventDefault();
    const bnameInput = document.getElementById('m-lock-bname');
    const catSelect = document.getElementById('m-lock-category');
    const locInput = document.getElementById('m-lock-location');
    const descInput = document.getElementById('m-lock-desc');
    const errBox = document.getElementById('m-lock-error');
    const btn = document.getElementById('m-lock-btn');

    if (errBox) errBox.style.display = 'none';

    const business_name = bnameInput ? bnameInput.value.trim() : '';
    const category = catSelect ? catSelect.value : '';
    const location = locInput ? locInput.value.trim() : '';
    const description = descInput ? descInput.value.trim() : '';

    if (!business_name || !location) {
        if (errBox) { errBox.textContent = 'Please enter your business name and location.'; errBox.style.display = 'block'; }
        return;
    }

    if (!window._mandatorySignupDraft) window._mandatorySignupDraft = {};
    Object.assign(window._mandatorySignupDraft, {
        business_name, bname: business_name, category, location, city: location, description
    });

    const draft = window._mandatorySignupDraft;

    if (btn) {
        btn.disabled = true;
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.65';
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Sending Code...';
    }

    API.post('send_otp', {
        target: draft.email || draft.phone,
        email: draft.email,
        phone: draft.phone
    }).then(res => {
        window.renderMandatoryAuthContent('otp');
    }).catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.style.pointerEvents = 'auto';
            btn.style.opacity = '1';
            btn.textContent = 'Continue to Verification';
        }
        if (errBox) { errBox.textContent = err.message || 'Failed to send OTP code. Please try again.'; errBox.style.display = 'block'; }
    });
};

window.handleMandatoryOTPVerifySubmit = function (e) {
    if (e) e.preventDefault();
    const otpInput = document.getElementById('m-lock-otp');
    const errBox = document.getElementById('m-lock-error');
    const btn = document.getElementById('m-lock-btn');

    if (errBox) errBox.style.display = 'none';

    const otp = otpInput ? otpInput.value.trim() : '';
    if (!otp || otp.length < 6) {
        if (errBox) { errBox.textContent = 'Please enter the 6-digit OTP code received.'; errBox.style.display = 'block'; }
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.65';
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Verifying...';
    }
    if (otpInput) otpInput.disabled = true;

    function unlockOTP() {
        if (btn) {
            btn.disabled = false;
            btn.style.pointerEvents = 'auto';
            btn.style.opacity = '1';
            btn.textContent = 'Verify & Complete Registration';
        }
        if (otpInput) otpInput.disabled = false;
    }

    const draft = window._mandatorySignupDraft || {};

    API.post('verify_otp', {
        target: draft.email || draft.phone,
        code: otp
    }).then(() => {
        return API.post('register', draft);
    }).then(res => {
        if (res.user) {
            state.user = res.user;
            const token = res.auth_token || res.token;
            if (token) localStorage.setItem('ohati_auth_token', token);
            localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            if (typeof window.clearAllAuthOverlays === 'function') window.clearAllAuthOverlays();
            else if (typeof window.unlockMandatoryAuthScreen === 'function') window.unlockMandatoryAuthScreen();
            if (typeof updateAppHeader === 'function') updateAppHeader();

            Promise.allSettled([
                API.getCategories(),
                API.getVendors(),
                API.getVendors({ premium_only: 1 }),
                API.get('get_advertisements'),
                API.getPopularVendors(),
                API.getBookings(),
                API.getFavorites(),
                API.getEvent(),
                API.get('get_faqs')
            ]).then(results => {
                state.categories = results[0].status === 'fulfilled' ? results[0].value : [];
                state.vendors = results[1].status === 'fulfilled' ? results[1].value : [];
                state.bookings = results[5].status === 'fulfilled' ? results[5].value : [];
                state.favorites = results[6].status === 'fulfilled' ? results[6].value : [];

                if (typeof navigateTo === 'function') {
                    navigateTo((res.user.active_role || res.user.role) === 'vendor' ? 'vendor-dash' : 'home');
                }
            });
        } else {
            unlockOTP();
            throw new Error(res.error || 'Registration failed.');
        }
    }).catch(err => {
        unlockOTP();
        if (errBox) { errBox.textContent = err.message || 'Invalid or expired OTP code.'; errBox.style.display = 'block'; }
    });
};

window.handleResendSignupOTP = function (e) {
    if (e) e.preventDefault();
    const errBox = document.getElementById('m-lock-error');
    const draft = window._mandatorySignupDraft || {};

    if (errBox) { errBox.textContent = 'Resending code via SMS & Email...'; errBox.style.color = '#F2A735'; errBox.style.display = 'block'; }

    API.post('send_otp', {
        target: draft.email || draft.phone,
        email: draft.email,
        phone: draft.phone
    }).then(() => {
        if (errBox) { errBox.textContent = 'New 6-digit verification code sent!'; errBox.style.color = '#34D399'; errBox.style.display = 'block'; }
    }).catch(err => {
        if (errBox) { errBox.textContent = err.message || 'Resend failed. Please wait a minute before trying again.'; errBox.style.color = '#FCA5A5'; errBox.style.display = 'block'; }
    });
};