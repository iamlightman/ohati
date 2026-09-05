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
                        <div class="account-type-title">Event Vendor</div>
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
                const isVendor = state.authData.role === 'vendor';
                html = `
                    <div class="auth-modal-header">
                        <h2 class="auth-modal-title">Sign Up</h2>
                        <p class="auth-modal-subtitle">Step 1 of 3: Your Information (${isVendor ? 'Event Vendor' : 'Customer'})</p>
                    </div>
                    <div class="auth-step-indicator mb-16">
                        <div class="auth-step-dot active"></div>
                        <div class="auth-step-dot"></div>
                        <div class="auth-step-dot"></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;" class="mb-14">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">First Name <span style="color:#EF4444;">*</span></label>
                            <input type="text" class="form-input" id="reg-fname" placeholder="First Name" value="${state.authData.fname || ''}">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Last Name <span style="color:#EF4444;">*</span></label>
                            <input type="text" class="form-input" id="reg-lname" placeholder="Last Name" value="${state.authData.lname || ''}">
                        </div>
                    </div>
                    ${isVendor ? `
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;" class="mb-14">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Business Name <span style="color:#EF4444;">*</span></label>
                            <input type="text" class="form-input" id="reg-bizname" placeholder="e.g. Royal Crown Events" value="${state.authData.bizname || state.authData.business_name || ''}">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Username (optional)</label>
                            <input type="text" class="form-input" id="reg-username" placeholder="Username" value="${state.authData.username || ''}">
                        </div>
                    </div>
                    ` : `
                    <div class="form-group mb-14">
                        <label class="form-label">Username (optional)</label>
                        <input type="text" class="form-input" id="reg-username" placeholder="Username" value="${state.authData.username || ''}">
                    </div>
                    `}
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;" class="mb-14">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Email Address <span style="color:#EF4444;">*</span></label>
                            <input type="email" class="form-input" id="reg-email" placeholder="email@example.com" value="${state.authData.email || ''}">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Phone Number <span style="color:#EF4444;">*</span></label>
                            <input type="tel" class="form-input" id="reg-phone" placeholder="e.g. +233 24 123 4567" value="${state.authData.phone || ''}">
                        </div>
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <button class="btn btn-primary btn-full" onclick="submitRegisterStep1()">Next Step: Upload Photos</button>
                `;
            } else if (step === 2) {
                const avatarSrc = state.authData.avatar || 'img/default-avatar.png';
                const coverSrc = state.authData.cover_photo || 'img/default-cover.jpg';
                const hasAvatar = !!state.authData.avatar;
                const hasCover = !!state.authData.cover_photo;

                html = `
                    <div class="auth-modal-header">
                        <h2 class="auth-modal-title">Upload Profile & Cover Photos</h2>
                        <p class="auth-modal-subtitle">Step 2 of 3: Both photos are compulsory <span style="color:#EF4444;">*</span></p>
                    </div>
                    <div class="auth-step-indicator">
                        <div class="auth-step-dot done"></div>
                        <div class="auth-step-dot active"></div>
                        <div class="auth-step-dot"></div>
                    </div>

                    <!-- Profile Avatar Upload Block -->
                    <div class="form-group mb-16">
                        <label class="form-label" style="font-weight:700;">1. Profile Picture <span style="color:#EF4444;">*</span></label>
                        <div style="display:flex; align-items:center; gap:16px; background:#F8FAFC; padding:12px; border-radius:14px; border:1px solid #E2E8F0;">
                            <div style="position:relative; width:68px; height:68px; border-radius:50%; overflow:hidden; border:3px solid var(--accent); flex-shrink:0; background:#E2E8F0; box-shadow:0 4px 10px rgba(0,0,0,0.08);">
                                <img id="reg-avatar-preview" src="${avatarSrc}" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                            <div style="flex:1;">
                                <div id="reg-avatar-status" style="font-size:0.78rem; font-weight:700; color:${hasAvatar ? '#10B981' : '#64748B'}; margin-bottom:6px;">
                                    ${hasAvatar ? '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Profile Photo Loaded & Cropped' : 'No profile image selected'}
                                </div>
                                <button type="button" class="btn btn-outline btn-sm auth-upload-btn" onclick="document.getElementById('reg-avatar-file').click()" style="font-size:0.75rem; padding:8px 14px; font-weight:700;">
                                    <i class="fa-solid fa-camera" style="margin-right:6px;"></i> Upload & Crop Profile Photo
                                </button>
                                <input type="file" id="reg-avatar-file" accept="image/*" style="display:none;" onchange="handleRegFileSelect(event, 'avatar')">
                            </div>
                        </div>
                    </div>

                    <!-- Cover Photo Upload Block -->
                    <div class="form-group mb-16">
                        <label class="form-label" style="font-weight:700;">2. Cover Photo Banner <span style="color:#EF4444;">*</span></label>
                        <div style="position:relative; width:100%; height:170px; border-radius:14px; overflow:hidden; border:2px dashed var(--accent); background:linear-gradient(135deg, #0B1F3A 0%, #1B2B4B 100%); display:flex; align-items:center; justify-content:center; flex-direction:column; text-align:center; color:#FFF; margin-bottom:8px;">
                            <img id="reg-cover-preview" src="${coverSrc}" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; opacity:${hasCover ? '1' : '0.45'}; transition:all 0.3s ease;">
                            <div style="position:relative; z-index:2; padding:12px 18px; background:rgba(11,31,58,0.82); border-radius:12px; border:1px solid rgba(242,167,53,0.4); backdrop-filter:blur(4px); max-width:88%;">
                                <div style="font-family:'Fraunces',serif; font-size:1.08rem; font-weight:800; color:#FFFFFF; margin-bottom:2px;">
                                    <i class="fa-solid fa-image" style="color:var(--accent); margin-right:6px;"></i> Your Cover Image Here
                                </div>
                                <div style="font-size:0.75rem; color:#F1F5F9; margin-bottom:8px;">Upload & crop your official profile cover banner</div>
                                <button type="button" class="btn btn-primary btn-sm auth-upload-btn" onclick="document.getElementById('reg-cover-file').click()" style="padding:8px 18px; font-weight:800; font-size:0.75rem; border-radius:8px;">
                                    <i class="fa-solid fa-cloud-arrow-up" style="margin-right:6px;"></i> Upload & Crop Cover Image
                                </button>
                                <input type="file" id="reg-cover-file" accept="image/*" style="display:none;" onchange="handleRegFileSelect(event, 'cover')">
                            </div>
                        </div>
                        <div id="reg-cover-status" style="font-size:0.78rem; font-weight:700; color:${hasCover ? '#10B981' : '#64748B'}; text-align:center;">
                            ${hasCover ? '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Cover Photo Loaded & Cropped' : 'Click above to select and crop your cover image'}
                        </div>
                    </div>

                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-outline btn-full" onclick="state.authStep=1; renderAuthModal();">Back</button>
                        <button class="btn btn-primary btn-full" onclick="submitRegisterStep2()">Next Step: Password</button>
                    </div>
                `;
            } else if (step === 3) {
                html = `
                    <div class="auth-modal-header">
                        <h2 class="auth-modal-title">Create Password</h2>
                        <p class="auth-modal-subtitle">Step 3 of 3: Choose a strong password</p>
                    </div>
                    <div class="auth-step-indicator">
                        <div class="auth-step-dot done"></div>
                        <div class="auth-step-dot done"></div>
                        <div class="auth-step-dot active"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span style="color:#EF4444;">*</span></label>
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
                        <label class="form-label">Confirm Password <span style="color:#EF4444;">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-input" id="reg-confirm" placeholder="Confirm your password">
                            <span class="input-suffix" onclick="togglePasswordVisibility('reg-confirm')"><i class="fa-solid fa-eye" id="reg-confirm-eye"></i></span>
                        </div>
                    </div>
                    <div id="auth-error-msg" class="form-error mb-12" style="display:none;"></div>
                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-outline btn-full" onclick="state.authStep=2; renderAuthModal();">Back</button>
                        <button class="btn btn-primary btn-full" onclick="submitRegisterStep3()">Register Account</button>
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
                <div class="otp-inputs" onpaste="handleOtpPaste(event)">
                    <input type="tel" maxlength="1" class="otp-input" id="otp-1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); otpMove(1)" onkeyup="otpKey(1, event)" value="">
                    <input type="tel" maxlength="1" class="otp-input" id="otp-2" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); otpMove(2)" onkeyup="otpKey(2, event)" value="">
                    <input type="tel" maxlength="1" class="otp-input" id="otp-3" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); otpMove(3)" onkeyup="otpKey(3, event)" value="">
                    <input type="tel" maxlength="1" class="otp-input" id="otp-4" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); otpMove(4)" onkeyup="otpKey(4, event)" value="">
                    <input type="tel" maxlength="1" class="otp-input" id="otp-5" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); otpMove(5)" onkeyup="otpKey(5, event)" value="">
                    <input type="tel" maxlength="1" class="otp-input" id="otp-6" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); otpMove(6)" onkeyup="otpKey(6, event)" value="">
                </div>
                <div class="otp-timer" id="otp-timer-box">Resend code in <span id="otp-countdown">60</span>s</div>
                <div id="otp-resend-container" style="display:none; text-align:center; margin-bottom:12px;">
                    <button class="btn btn-ghost btn-sm" onclick="resendOTPCode()">Resend Code</button>
                </div>
                <p style="font-size:0.75rem; color:var(--text-muted, #94A3B8); margin-bottom:16px; text-align:center; line-height:1.4;">
                    <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent, #F2A735); margin-right:4px;"></i>
                    Note: Email OTP may take a minute or two to enter your inbox. Please check your spam folder if delayed.
                </p>
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
                        <a href="forgot-password.php" style="font-size:0.75rem; color:var(--accent); font-weight:700; text-decoration:none;" onclick="window.location.href='forgot-password.php'; return false;">Forgot?</a>
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
            window.location.href = 'forgot-password.php';
            state.authMode = 'login';
            renderAuthModal();
            return;

        case 'forgot-sent':
            const sentEmail = state.authData?.resetTarget || document.getElementById('forgot-target')?.value || '';
            html = `
                <div class="auth-modal-header" style="text-align: center;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #D1FAE5; color: #10B981; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 12px;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <h2 class="auth-modal-title" style="color: var(--primary);">Reset Link Sent</h2>
                    <p class="auth-modal-subtitle" style="font-size: 0.88rem; color: var(--gray-600); line-height: 1.5; margin-top: 8px;">
                        If an account exists with ${sentEmail ? `<strong>${escapeHtml(sentEmail)}</strong>` : 'this email address'}, we've sent a password reset link to your email.
                    </p>
                </div>

                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 16px; margin: 16px 0; font-size: 0.82rem; color: #4B5563; line-height: 1.5; text-align: left;">
                    <div style="font-weight: 700; color: #1F2937; margin-bottom: 4px;"><i class="fa-solid fa-shield-halved" style="color: var(--accent); margin-right: 6px;"></i> Security Instructions:</div>
                    <ul style="margin: 0; padding-left: 18px;">
                        <li>Check your inbox and click the <strong>Reset Password</strong> button.</li>
                        <li>The link will expire in <strong>24 hours</strong> and can only be used once.</li>
                        <li>If you don't see the email, please check your spam or junk folder.</li>
                    </ul>
                </div>

                <button class="btn btn-primary btn-full" onclick="state.authMode='login'; renderAuthModal();"><i class="fa-solid fa-right-to-bracket" style="margin-right:6px;"></i> Return to Login</button>
            `;
            break;

        case 'reset':
            html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">Reset Password</h2>
                    <p class="auth-modal-subtitle">Choose a new password</p>
                </div>
                <div style="margin: -5px 0 15px 0; padding: 10px 14px; border-radius: 12px; background: rgba(212, 175, 55, 0.1); border: 1px solid var(--accent); font-size: 0.82rem; color: var(--primary); text-align: center;">
                    <i class="fa-solid fa-envelope-open-text" style="color:var(--accent); margin-right:6px;"></i> Check your email inbox or SMS for your reset code.
                </div>
                <div class="form-group">
                    <label class="form-label">6-digit Reset Code</label>
                    <input type="tel" class="form-input" id="reset-code" name="reset_otp_code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,'')" placeholder="Enter 6-digit code received" value="">
                </div>
                <div class="otp-timer mb-12" id="reset-timer-box" style="font-size:0.8rem; color:#6B7280; text-align:center;">Resend code in <span id="reset-countdown">60</span>s</div>
                <div id="reset-resend-container" style="display:none; text-align:center; margin-bottom:12px;">
                    <button type="button" class="btn btn-ghost btn-sm" id="reset-resend-btn" onclick="resendResetCode(event)" style="color:var(--primary); font-weight:700;">Resend Code</button>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-input" id="reset-pass" name="new_password" autocomplete="new-password" placeholder="Minimum 8 characters">
                        <span class="input-suffix" onclick="togglePasswordVisibility('reset-pass')"><i class="fa-solid fa-eye" id="reset-pass-eye"></i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" class="form-input" id="reset-confirm" name="confirm_password" autocomplete="new-password" placeholder="Confirm new password">
                        <span class="input-suffix" onclick="togglePasswordVisibility('reset-confirm')"><i class="fa-solid fa-eye" id="reset-confirm-eye"></i></span>
                    </div>
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
    } else if (mode === 'reset') {
        startResetOTPTimer();
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

window.handleRegFileSelect = function (event, type) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    if (type === 'avatar' && typeof window.openAvatarCropperModal === 'function') {
        window.openAvatarCropperModal(file, function(croppedDataUrl) {
            state.authData.avatar = croppedDataUrl;
            const img = document.getElementById('reg-avatar-preview');
            if (img) img.src = croppedDataUrl;
            const status = document.getElementById('reg-avatar-status');
            if (status) status.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Profile Photo Loaded & Cropped!';
        });
        return;
    }

    if (type === 'cover' && typeof window.openCoverCropperModal === 'function') {
        window.openCoverCropperModal(file, function(croppedDataUrl) {
            state.authData.cover_photo = croppedDataUrl;
            const img = document.getElementById('reg-cover-preview');
            if (img) {
                img.src = croppedDataUrl;
                img.style.opacity = '1';
            }
            const status = document.getElementById('reg-cover-status');
            if (status) status.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Cover Photo Loaded & Cropped!';
        });
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        const base64 = e.target.result;
        if (type === 'avatar') {
            state.authData.avatar = base64;
            const img = document.getElementById('reg-avatar-preview');
            if (img) img.src = base64;
            const status = document.getElementById('reg-avatar-status');
            if (status) status.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Profile Photo Loaded';
        } else if (type === 'cover') {
            state.authData.cover_photo = base64;
            const img = document.getElementById('reg-cover-preview');
            if (img) {
                img.src = base64;
                img.style.opacity = '1';
            }
            const status = document.getElementById('reg-cover-status');
            if (status) status.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Cover Photo Loaded';
        }
    };
    reader.readAsDataURL(file);
};

// Submit Register Step 1
function submitRegisterStep1() {
    const fname = document.getElementById('reg-fname').value.trim();
    const lname = document.getElementById('reg-lname').value.trim();
    const username = document.getElementById('reg-username')?.value.trim() || '';
    const email = document.getElementById('reg-email').value.trim();
    const phone = document.getElementById('reg-phone').value.trim();
    const biznameEl = document.getElementById('reg-bizname');
    const bizname = biznameEl ? biznameEl.value.trim() : '';
    const err = document.getElementById('auth-error-msg');
    const isVendor = state.authData.role === 'vendor';

    if (!fname || !lname || (!email && !phone)) {
        err.textContent = 'Please fill out first name, last name, and email or phone.';
        err.style.display = 'block';
        return;
    }

    if (isVendor && !bizname) {
        err.textContent = 'Please enter your Business Name.';
        err.style.display = 'block';
        return;
    }

    state.authData.fname = fname;
    state.authData.lname = lname;
    state.authData.username = username;
    state.authData.email = email;
    state.authData.phone = phone;
    state.authData.bizname = bizname;
    state.authData.business_name = bizname;

    state.authStep = 2;
    renderAuthModal();
}

// Submit Register Step 2 (Compulsory Media Validation)
function submitRegisterStep2() {
    const err = document.getElementById('auth-error-msg');

    if (!state.authData.avatar || !state.authData.cover_photo) {
        if (err) {
            err.textContent = 'Please upload both your Profile Picture and Cover Photo to continue.';
            err.style.display = 'block';
        }
        return;
    }

    state.authStep = 3;
    renderAuthModal();
}

// Submit Register Step 3 (Final Registration)
function submitRegisterStep3() {
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

    const btn = document.querySelector('button[onclick="submitRegisterStep3()"]');

    ActionLock.execute(btn, 'Creating Account...', async () => {
        const pendingRef = sessionStorage.getItem('ohati_pending_ref') || '';
        const regPayload = {
            name: `${state.authData.fname} ${state.authData.lname}`,
            email: state.authData.email,
            phone: state.authData.phone,
            username: state.authData.username,
            password: state.authData.password,
            role: state.authData.role,
            avatar: state.authData.avatar,
            cover_photo: state.authData.cover_photo,
            business_name: state.authData.bizname || state.authData.business_name || '',
            ref: pendingRef
        };

        let res;
        try {
            res = await API.register(regPayload);
        } catch (e) {
            if (e.message && (e.message.includes('already registered') || e.message.includes('verify'))) {
                showPushNotification('Account Verification', 'Proceeding to OTP verification...');
            } else {
                throw e;
            }
        }
        if (res && res.auth_token) {
            localStorage.setItem('ohati_auth_token', res.auth_token);
        }
        if (res && res.user) {
            state.user = res.user;
        }
        showPushNotification('Account Created', 'Please enter the 6-digit OTP code sent to your phone/email.');

        const email = state.authData.email || '';
        const phone = state.authData.phone || '';
        const target = email || phone;

        try {
            const otpRes = await API.sendOTP(target, 'verify', email, phone);
            if (otpRes && otpRes.email_sent) state.authData.email_sent = otpRes.email_sent;
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

// OTP Input Navigation & Paste Handling
function handleOtpPaste(e) {
    const paste = (e.clipboardData || window.clipboardData)?.getData('text')?.trim();
    if (paste && /^\d{6}$/.test(paste)) {
        e.preventDefault();
        for (let i = 1; i <= 6; i++) {
            const el = document.getElementById('otp-' + i);
            if (el) el.value = paste[i - 1];
        }
        document.getElementById('otp-6')?.focus();
    }
}
function otpMove(idx) {
    const curr = document.getElementById('otp-' + idx);
    if (!curr) return;
    curr.value = curr.value.replace(/\D/g, '');
    if (curr.value.length >= 1 && idx < 6) {
        if (curr.value.length > 1 && /^\d{6}$/.test(curr.value.trim())) {
            const val = curr.value.trim();
            for (let i = 1; i <= 6; i++) {
                const el = document.getElementById('otp-' + i);
                if (el) el.value = val[i - 1];
            }
            document.getElementById('otp-6')?.focus();
            return;
        }
        curr.value = curr.value.slice(-1);
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
    let secs = 60;
    const cd = document.getElementById('otp-countdown');
    const timerBox = document.getElementById('otp-timer-box');
    const resend = document.getElementById('otp-resend-container');
    if (timerBox) timerBox.style.display = 'block';
    if (resend) resend.style.display = 'none';
    if (otpCountdownTimer) clearInterval(otpCountdownTimer);
    otpCountdownTimer = setInterval(() => {
        secs--;
        if (cd) cd.textContent = secs;
        if (secs <= 0) {
            clearInterval(otpCountdownTimer);
            if (timerBox) timerBox.style.display = 'none';
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
        sessionStorage.setItem('ohati_just_registered_kyc_prompt', '1');
        if (state.user && (state.user.active_role || state.user.role) === 'vendor' && !state.user.vendor_onboarding_completed) {
            showPushNotification('Profile Incomplete', 'Please complete your business & profile verification steps.');
            state.authMode = 'vendor-register';
            state.authStep = 1;
            renderAuthModal();
        } else {
            window.location.reload();
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
            if (res.user) {
                localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            }
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
                window.location.reload();
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
            err.textContent = 'Please enter your email address.';
            err.style.display = 'block';
        }
        return;
    }

    const btn = event?.target || document.querySelector('button[onclick*="submitForgot"]');
    ActionLock.execute(btn, 'Sending Reset Link...', async () => {
        const res = await API.forgotPassword(target);
        state.authData.resetTarget = target;
        state.authMode = 'forgot-sent';
        renderAuthModal();
    }).catch(e => {
        if (err) {
            err.textContent = e.message || 'Could not process password reset request.';
            err.style.display = 'block';
        }
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

window.resendResetCode = function(event) {
    const target = state.authData.resetTarget || '';
    if (!target) {
        showPushNotification('Error', 'Target email/phone missing. Please try again.');
        return;
    }
    const btn = event?.target || document.getElementById('reset-resend-btn');
    if (btn) btn.disabled = true;
    API.forgotPassword(target)
        .then(res => {
            if (btn) btn.disabled = false;
            showPushNotification('Code Resent', 'A new password reset code has been sent.');
            startResetOTPTimer();
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            showPushNotification('Resend Error', err.message || 'Could not resend reset code.');
        });
};

function startResetOTPTimer() {
    let secs = 60;
    const cd = document.getElementById('reset-countdown');
    const timerBox = document.getElementById('reset-timer-box');
    const resendContainer = document.getElementById('reset-resend-container');
    if (timerBox) timerBox.style.display = 'block';
    if (resendContainer) resendContainer.style.display = 'none';
    if (window._resetCountdownTimer) clearInterval(window._resetCountdownTimer);
    window._resetCountdownTimer = setInterval(() => {
        secs--;
        if (cd) cd.textContent = secs;
        if (secs <= 0) {
            clearInterval(window._resetCountdownTimer);
            if (timerBox) timerBox.style.display = 'none';
            if (resendContainer) resendContainer.style.display = 'block';
        }
    }, 1000);
}

// Log Out
function handleLogout() {
    console.log("Signing out user...");
    const token = localStorage.getItem('ohati_auth_token') || (state.user ? state.user.auth_token : '');

    state.user = null;
    state.currentUser = null;
    state.vendor = null;
    state.bookings = [];
    state.userBookings = [];
    state.favorites = [];
    state.notifications = [];
    state.unreadChats = 0;
    state.unreadNotifications = 0;
    state.activeChatPartner = null;
    state.activeChatVendorId = null;
    state.stats = null;

    // Clear all auth keys & stored user tokens
    localStorage.removeItem('ohati_auth_token');
    localStorage.removeItem('ohati_user_session');
    localStorage.removeItem('ohati_user');
    localStorage.removeItem('ohati_vendor');
    localStorage.removeItem('ohati_notifications');
    localStorage.removeItem('ohati_stats');
    try { localStorage.clear(); } catch(e){}
    try { sessionStorage.clear(); } catch(e){}

    // Expire session cookie on client side if possible
    try {
        document.cookie.split(";").forEach(function(c) {
            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
        });
    } catch(e){}

    const doLocalCleanup = () => {
        if (typeof updateAppHeader === 'function') updateAppHeader();
        if (typeof updateUserSessionUI === 'function') updateUserSessionUI();
        if (typeof updateSidebarContent === 'function') updateSidebarContent();
        if (typeof renderSidebar === 'function') renderSidebar();

        // Lock screen to Login overlay
        if (typeof unlockMandatoryAuthScreen === 'function') unlockMandatoryAuthScreen();
        if (typeof showMandatoryAuthLockScreen === 'function') {
            showMandatoryAuthLockScreen('login');
        }

        if (typeof showPushNotification === 'function') {
            showPushNotification('Signed Out', 'You have successfully signed out.');
        }

        // Prevent browser Back button from revealing protected content
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname);
        }
        const isNative = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'file:' || window.location.protocol === 'capacitor:';
        if (isNative) {
            window.location.href = 'index.html';
        } else {
            window.location.href = 'index.php?logged_out=1';
        }
    };

    if (window.API && typeof API.logout === 'function') {
        API.logout(token).then(() => {
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
                <div style="text-align:center; padding:6px 0 12px 0;">
                    <div style="width:54px; height:54px; border-radius:50%; background:rgba(242,167,53,0.12); color:var(--accent); display:flex; align-items:center; justify-content:center; margin:0 auto 10px auto; font-size:1.6rem;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h3 style="font-family:'Fraunces',serif; font-size:1.15rem; margin-bottom:4px; color:var(--primary);">Identity Verification</h3>
                    <p style="font-size:0.75rem; color:var(--gray-600); line-height:1.4; max-width:380px; margin:0 auto 14px auto;">
                        Verify your identity with your Ghana Card or National ID to get your blue verified badge and start accepting client bookings.
                    </p>
                </div>

                <div style="background:linear-gradient(135deg, rgba(11,31,58,0.03) 0%, rgba(242,167,53,0.06) 100%); border:1px solid #E2E8F0; border-radius:14px; padding:14px; margin-bottom:16px;">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="width:36px; height:36px; border-radius:50%; background:rgba(242,167,53,0.15); color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                        <div>
                            <div style="font-size:0.83rem; font-weight:700; color:#0F172A;">Instant Automated Verification</div>
                            <div style="font-size:0.72rem; color:#64748B;">Scan Ghana Card & Selfie (~60 seconds)</div>
                        </div>
                    </div>
                    <ul style="font-size:0.72rem; color:#475569; padding-left:18px; margin:0 0 14px 0; line-height:1.5;">
                        <li>Instant automated Ghana Card & Passport check</li>
                        <li>Unlocks verified blue badge on your live profile</li>
                        <li>Higher ranking & client trust on Ohati</li>
                    </ul>
                    <button class="btn btn-primary btn-full mb-8" id="btn-start-didit-onboarding" onclick="startOnboardingDiditKyc()" style="padding:11px; font-weight:700; font-size:0.85rem; border-radius:10px; box-shadow:0 4px 12px rgba(242, 167, 53, 0.25);">
                        <i class="fa-solid fa-bolt"></i> Verify Identity Now
                    </button>
                </div>

                <button class="btn btn-outline btn-full mb-10" onclick="skipVendorKycAndFinish()" style="font-size:0.78rem; border-color:#CBD5E1; color:#475569; padding:9px;">
                    <i class="fa-solid fa-arrow-right"></i> Skip for Now & Go to Dashboard
                </button>

                <div style="display:flex; justify-content:flex-start;">
                    <button class="btn btn-ghost btn-sm" onclick="state.authStep=4; renderAuthModal();" style="font-size:0.75rem;"><i class="fa-solid fa-arrow-left"></i> Back</button>
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
        const fileInput = document.getElementById(type === 'id-front' ? 'v-file-id-front' : 'v-file-selfie');
        if (fileInput && fileInput.files && fileInput.files[0]) {
            handleKycFileSelect({ target: fileInput }, type);
            return;
        }
        status.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Processing Document...`;
        setTimeout(() => {
            status.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i> Ready for Verification`;
        }, 800);
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

/************* VENDOR REGISTRATION STEP 5 DATA SAVER *************/
async function saveVendorStep5Data() {
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
        instant_booking: 0
    };

    await API.updateVendor(updatePayload);
    return vid;
}

window.startOnboardingDiditKyc = async function() {
    const btn = document.getElementById('btn-start-didit-onboarding');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving & Launching Portal...';
    }

    try {
        await saveVendorStep5Data();
        closeModal();
        const res = await API.initDiditKyc();
        if (res && res.url) {
            if (typeof renderDiditKycScreen === 'function') {
                renderDiditKycScreen({ url: res.url, session_id: res.session_id });
            }
            if (typeof navigateTo === 'function') {
                navigateTo('didit-kyc', { url: res.url, session_id: res.session_id });
            }
            if (typeof openDiditVerificationUrl === 'function') {
                openDiditVerificationUrl(res.url);
            }
        } else {
            throw new Error(res?.error || 'Could not retrieve verification portal URL.');
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Verify Identity Now';
        }
        showPushNotification('Initialization Error', err.message || 'Could not launch verification process.');
    }
};

window.skipVendorKycAndFinish = async function() {
    try {
        await saveVendorStep5Data();
        const sessionRes = await API.getSession();
        if (sessionRes && sessionRes.user) state.user = sessionRes.user;
        closeModal();
        showPushNotification('Vendor Setup Complete! 🎉', 'Your profile is saved. You can complete identity verification anytime from your dashboard.');
        if (typeof updateSidebarUI === 'function') updateSidebarUI();
        if (typeof navigateTo === 'function') navigateTo('vendor-dash');
    } catch (err) {
        showPushNotification('Save Error', err.message || 'Could not save vendor setup.');
    }
};


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
    let currentUser = (window.state && window.state.user && window.state.user.id) ? window.state.user : null;
    if (!currentUser) {
        try {
            const cached = localStorage.getItem('ohati_user_session');
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && parsed.id) {
                    if (!window.state) window.state = {};
                    window.state.user = parsed;
                    currentUser = parsed;
                }
            }
        } catch(e) {}
    }

    if (currentUser && currentUser.id) {
        if (typeof unlockMandatoryAuthScreen === 'function') unlockMandatoryAuthScreen();
        if (initialMode === 'signup' || initialMode === 'vendor-details' || initialMode === 'vendor-register' || initialMode === 'become-vendor' || initialMode === 'account-type') {
            if (currentUser.has_vendor_profile || currentUser.vendor_id || (window.state && window.state.vendor)) {
                if (typeof switchAccountType === 'function') switchAccountType('vendor');
            } else {
                if (typeof openBecomeVendorModal === 'function') openBecomeVendorModal();
            }
        }
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
                            <input type="tel" id="m-lock-otp" name="m_lock_otp_code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,'')" maxlength="6" required placeholder="123456" style="width:100%; padding:14px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:1.4rem; letter-spacing:6px; text-align:center; font-weight:800; outline:none; box-sizing:border-box;">
                        </div>
                        <div id="m-lock-error" style="display:none; padding:10px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; text-align:center;"></div>
                        <button type="submit" id="m-lock-btn" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem;">Verify & Complete Registration</button>
                    </form>

                    <div style="margin-top:16px; font-size:0.8rem; color:#94A3B8; text-align:center;" id="m-otp-timer-box">
                        Resend code in <span id="m-otp-countdown">60</span>s
                    </div>
                    <div id="m-otp-resend-container" style="display:none; margin-top:16px; font-size:0.85rem; color:#94A3B8; text-align:center;">
                        Didn't receive the code? <a href="#" onclick="handleResendSignupOTP(event); return false;" style="color:var(--accent, #F2A735); font-weight:700; text-decoration:none;">Resend OTP</a>
                    </div>
                    <p style="font-size:0.75rem; color:#94A3B8; margin-top:10px; text-align:center; line-height:1.4;">
                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--accent, #F2A735); margin-right:4px;"></i>
                        Note: Email OTP may take a minute or two to enter your inbox. Please check your spam folder if delayed.
                    </p>
                    <div style="margin-top:10px; font-size:0.85rem; color:#94A3B8;">
                        <a href="#" onclick="renderMandatoryAuthContent('${role === 'vendor' ? 'vendor-details' : 'signup'}'); return false;" style="color:#CBD5E1; text-decoration:underline;">Back</a>
                    </div>
                </div>
            `;
            setTimeout(() => { if (typeof startMandatoryOTPTimer === 'function') startMandatoryOTPTimer(); }, 50);
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
                <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:24px; width:100%; max-width:540px; padding:36px 32px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                    <div style="width:76px; height:76px; border-radius:20px; overflow:hidden; border:2px solid var(--accent, #F2A735); margin:0 auto 16px; box-shadow:0 8px 24px rgba(242,167,53,0.25);">
                        <img src="img/app_icon.png" style="width:100%; height:100%; object-fit:cover;" alt="Ohati App Icon">
                    </div>
                    <div style="font-size:0.75rem; font-weight:800; color:var(--accent, #F2A735); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Step 1 — Basic Credentials</div>
                    <h2 style="font-family:'Fraunces',serif; font-size:1.6rem; font-weight:800; margin:0 0 6px 0; color:#FFF;">Create Your Account</h2>
                    <p style="font-size:0.85rem; color:#94A3B8; margin:0 0 24px 0;">Join Ohati to discover and book verified event services</p>

                    <form onsubmit="handleMandatorySignupSubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:16px;">
                    <form onsubmit="handleMandatorySignupSubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:16px;">
                        <!-- Segmented Account Type Selector -->
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Account Type</label>
                            <div style="background:rgba(255,255,255,0.05); padding:4px; border-radius:14px; border:1px solid rgba(255,255,255,0.12); display:grid; grid-template-columns:1fr 1fr; gap:4px;">
                                <button type="button" id="role-btn-customer" onclick="selectRolePill('customer')" style="padding:10px; border-radius:10px; border:none; background:${(draft.role === 'customer' || !draft.role) ? 'linear-gradient(135deg, var(--accent, #F2A735), #D98E1C)' : 'transparent'}; color:${(draft.role === 'customer' || !draft.role) ? '#000' : '#94A3B8'}; font-weight:800; font-size:0.85rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all 0.2s ease; box-shadow:${(draft.role === 'customer' || !draft.role) ? '0 4px 12px rgba(242,167,53,0.3)' : 'none'};">
                                    <i class="fa-solid fa-user"></i> Customer
                                </button>
                                <button type="button" id="role-btn-vendor" onclick="selectRolePill('vendor')" style="padding:10px; border-radius:10px; border:none; background:${(draft.role === 'vendor') ? 'linear-gradient(135deg, var(--accent, #F2A735), #D98E1C)' : 'transparent'}; color:${(draft.role === 'vendor') ? '#000' : '#94A3B8'}; font-weight:800; font-size:0.85rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all 0.2s ease; box-shadow:${(draft.role === 'vendor') ? '0 4px 12px rgba(242,167,53,0.3)' : 'none'};">
                                    <i class="fa-solid fa-store"></i> Vendor
                                </button>
                                <input type="hidden" id="m-lock-role" value="${draft.role || 'customer'}">
                            </div>
                        </div>

                        <!-- Hero Full Width Field: Full Name -->
                        <div>
                            <label style="display:block; font-size:0.78rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Full Name *</label>
                            <div style="position:relative;">
                                <input type="text" id="m-lock-name" required placeholder="John Doe" value="${draft.name || ''}" style="width:100%; padding:12px 14px 12px 38px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                                <i class="fa-solid fa-user" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--accent, #F2A735); font-size:0.85rem;"></i>
                            </div>
                        </div>

                        <!-- Asymmetric Contact Row: Email & Phone -->
                        <div style="display:grid; grid-template-columns:1.35fr 1fr; gap:12px;">
                            <div>
                                <label style="display:block; font-size:0.78rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Email Address *</label>
                                <div style="position:relative;">
                                    <input type="email" id="m-lock-email" required placeholder="name@example.com" value="${draft.email || ''}" style="width:100%; padding:12px 14px 12px 38px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                                    <i class="fa-solid fa-envelope" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.85rem;"></i>
                                </div>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.78rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Phone Number *</label>
                                <div style="position:relative;">
                                    <input type="tel" id="m-lock-phone" required placeholder="+233 24 123 4567" value="${draft.phone || ''}" style="width:100%; padding:12px 14px 12px 38px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                                    <i class="fa-solid fa-phone" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.85rem;"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Security Pair: Password & Confirm Password -->
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div>
                                <label style="display:block; font-size:0.78rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Password *</label>
                                <div style="position:relative;">
                                    <input type="password" id="m-lock-pass" required placeholder="Min 6 chars" style="width:100%; padding:12px 36px 12px 38px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                                    <i class="fa-solid fa-lock" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.85rem;"></i>
                                    <span onclick="togglePasswordVisibility('m-lock-pass')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94A3B8; z-index:5;"><i class="fa-solid fa-eye" id="m-lock-pass-eye"></i></span>
                                </div>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.78rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">Confirm Password *</label>
                                <div style="position:relative;">
                                    <input type="password" id="m-lock-confirm" required placeholder="Re-enter password" style="width:100%; padding:12px 36px 12px 38px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.9rem; outline:none; box-sizing:border-box;">
                                    <i class="fa-solid fa-lock" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.85rem;"></i>
                                    <span onclick="togglePasswordVisibility('m-lock-confirm')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94A3B8; z-index:5;"><i class="fa-solid fa-eye" id="m-lock-confirm-eye"></i></span>
                                </div>
                            </div>
                        </div>

                        <!-- Integrated Media Card (Avatar & Cover Banner for all account types) -->
                        <div id="m-vendor-media-block" style="display:block; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:14px; box-sizing:border-box;">
                            <div style="display:flex; align-items:center; margin-bottom:10px;">
                                <span style="font-size:0.78rem; font-weight:700; color:#CBD5E1;"><i class="fa-solid fa-images" style="color:var(--accent, #F2A735); margin-right:6px;"></i> Branding & Media</span>
                            </div>
                            
                            <div style="position:relative; width:100%; height:64px; border-radius:12px; overflow:hidden; border:1px solid rgba(242,167,53,0.35); margin-bottom:12px; display:flex; align-items:flex-end; padding:8px 12px; box-sizing:border-box;">
                                <div id="m-cover-sample-design" style="display:${draft.cover_photo ? 'none' : 'block'}; position:absolute; top:0; left:0; width:100%; height:100%; background:linear-gradient(135deg, #091526 0%, #15243B 45%, #2B1E0A 80%, #0F1923 100%); pointer-events:none;">
                                    <div style="position:absolute; top:-20px; right:-20px; width:100px; height:100px; border-radius:50%; background:radial-gradient(circle, rgba(242,167,53,0.25) 0%, rgba(242,167,53,0.05) 50%, transparent 80%); filter:blur(6px);"></div>
                                    <div style="position:absolute; inset:0; background:repeating-linear-gradient(45deg, transparent, transparent 18px, rgba(255,255,255,0.02) 18px, rgba(255,255,255,0.02) 19px); opacity:0.7;"></div>
                                    <div style="position:absolute; bottom:0; left:0; width:100%; height:2px; background:linear-gradient(90deg, transparent 0%, rgba(242,167,53,0.6) 50%, transparent 100%);"></div>
                                </div>
                                <img id="m-cover-preview" src="${draft.cover_photo || ''}" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; display:${draft.cover_photo ? 'block' : 'none'}; z-index:1;">
                                
                                <div style="position:relative; z-index:3; width:44px; height:44px; border-radius:50%; border:2px solid var(--accent, #F2A735); overflow:hidden; background:#1E293B; box-shadow:0 4px 12px rgba(0,0,0,0.5); flex-shrink:0;">
                                    <img id="m-avatar-preview" src="${draft.avatar || 'img/default-avatar.png'}" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                <button type="button" class="auth-upload-btn" onclick="document.getElementById('m-avatar-file').click()" style="font-size:0.75rem; padding:8px 12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.22); color:#FFFFFF !important; border-radius:10px; cursor:pointer; width:100%; font-weight:600; display:inline-flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s ease;">
                                    <i class="fa-solid fa-camera" style="color:var(--accent, #F2A735);"></i> <span>${draft.avatar ? 'Change Photo' : 'Profile Photo'}</span>
                                </button>
                                <button type="button" class="auth-upload-btn" onclick="document.getElementById('m-cover-file').click()" style="font-size:0.75rem; padding:8px 12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.22); color:#FFFFFF !important; border-radius:10px; cursor:pointer; width:100%; font-weight:600; display:inline-flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s ease;">
                                    <i class="fa-solid fa-image" style="color:var(--accent, #F2A735);"></i> <span>${draft.cover_photo ? 'Change Banner' : 'Cover Banner'}</span>
                                </button>
                            </div>
                            <input type="file" id="m-avatar-file" accept="image/*" style="display:none;" onchange="handleMandatoryFileSelect(event, 'avatar')">
                            <input type="file" id="m-cover-file" accept="image/*" style="display:none;" onchange="handleMandatoryFileSelect(event, 'cover')">
                        </div>

                        <div id="m-lock-error" style="display:none; padding:10px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; text-align:center;"></div>
                        <button type="submit" id="m-lock-btn" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem; margin-top:8px;">
                            ${isVendorSelected ? 'Continue to Vendor Details' : 'Send Verification Code'}
                        </button>
                    </form>

                    <div style="margin-top:20px; font-size:0.85rem; color:#94A3B8;">
                        Already have an account? <a href="#" onclick="renderMandatoryAuthContent('login'); return false;" style="color:var(--accent, #F2A735); font-weight:700; text-decoration:none;">Log In</a>
                    </div>
                </div>
            `;
        } else if (mode === 'forgot') {
            window.location.href = 'forgot-password.php';
            renderMandatoryAuthContent('login');
            return;
        } else if (mode === 'forgot-sent') {
            const target = window._forgotTarget || '';
            overlay.innerHTML = `
                <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:24px; width:100%; max-width:440px; padding:32px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                    <div style="width:64px; height:64px; border-radius:50%; background:rgba(16,185,129,0.15); border:2px solid #10B981; color:#10B981; margin:0 auto 16px; display:flex; align-items:center; justify-content:center; font-size:1.6rem;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <h2 style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:800; margin:0 0 8px 0; color:#FFF;">Reset Link Sent</h2>
                    <p style="font-size:0.88rem; color:#94A3B8; margin:0 0 20px 0; line-height:1.5;">
                        If an account exists with ${target ? `<strong style="color:#FFF;">${escapeHtml(target)}</strong>` : 'this email address'}, we've dispatched a password reset link to your email.
                    </p>

                    <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:14px 16px; text-align:left; font-size:0.8rem; color:#CBD5E1; margin-bottom:20px; line-height:1.5;">
                        <div style="font-weight:700; color:var(--accent, #F2A735); margin-bottom:4px;"><i class="fa-solid fa-circle-info"></i> Security Details:</div>
                        <ul style="margin:0; padding-left:18px;">
                            <li>Open your email inbox and click the <strong>Reset Password</strong> button.</li>
                            <li>The link expires in <strong>24 hours</strong> for your security.</li>
                            <li>Check your spam/junk folder if the email is delayed.</li>
                        </ul>
                    </div>

                    <button onclick="renderMandatoryAuthContent('login')" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem;">Return to Login</button>
                </div>
            `;
        } else if (mode === 'reset-pass') {
            const target = window._forgotTarget || '';
            overlay.innerHTML = `
                <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.12); border-radius:24px; width:100%; max-width:440px; padding:32px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.8); color:#FFF; text-align:center;">
                    <div style="width:76px; height:76px; border-radius:20px; overflow:hidden; border:2px solid var(--accent, #F2A735); margin:0 auto 16px; box-shadow:0 8px 24px rgba(242,167,53,0.25);">
                        <img src="img/app_icon.png" style="width:100%; height:100%; object-fit:cover;" alt="Ohati App Icon">
                    </div>
                    <h2 style="font-family:'Fraunces',serif; font-size:1.6rem; font-weight:800; margin:0 0 6px 0; color:#FFF;">Enter New Password</h2>
                    <p style="font-size:0.85rem; color:#94A3B8; margin:0 0 20px 0;">Code sent to <strong>${target}</strong>. Enter your 6-digit OTP code and new password.</p>

                    <form onsubmit="handleMandatoryResetPasswordSubmit(event)" style="text-align:left; display:flex; flex-direction:column; gap:14px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">6-Digit OTP Code</label>
                            <input type="tel" id="m-lock-reset-code" name="m_lock_otp_code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onbeforeinput="if(event.data && /\D/.test(event.data)) event.preventDefault();" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,'')" maxlength="6" required placeholder="123456" style="width:100%; padding:13px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:1.3rem; letter-spacing:4px; text-align:center; font-weight:800; outline:none; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:#CBD5E1; margin-bottom:6px;">New Password (min. 8 characters)</label>
                            <div style="position:relative;">
                                <input type="password" id="m-lock-reset-pass" required placeholder="Enter new password" style="width:100%; padding:13px 40px 13px 13px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.95rem; outline:none; box-sizing:border-box;">
                                <span onclick="togglePasswordVisibility('m-lock-reset-pass')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94A3B8; z-index:5;"><i class="fa-solid fa-eye" id="m-lock-reset-pass-eye"></i></span>
                            </div>
                        </div>
                        <div id="m-lock-reset-error" style="display:none; padding:10px; border-radius:10px; background:rgba(239,68,68,0.15); border:1px solid #EF4444; color:#FCA5A5; font-size:0.8rem; text-align:center;"></div>
                        <div id="m-lock-reset-success" style="display:none; padding:10px; border-radius:10px; background:rgba(34,197,94,0.15); border:1px solid #22C55E; color:#86EFAC; font-size:0.85rem; text-align:center;"></div>
                        <button type="submit" id="m-lock-reset-btn" style="width:100%; padding:14px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; font-weight:800; border-radius:14px; border:none; cursor:pointer; font-size:1rem; margin-top:6px;">Save New Password</button>
                    </form>

                    <div style="margin-top:20px; font-size:0.85rem; color:#94A3B8;">
                        <a href="#" onclick="renderMandatoryAuthContent('login'); return false;" style="color:#CBD5E1; text-decoration:underline;">Back to Log In</a>
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
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <label style="font-size:0.75rem; font-weight:700; color:#CBD5E1; margin:0;">Password</label>
                                <a href="forgot-password.php" onclick="window.location.href='forgot-password.php'; return false;" style="font-size:0.75rem; color:var(--accent, #F2A735); font-weight:700; text-decoration:none;">Forgot?</a>
                            </div>
                            <div style="position:relative;">
                                <input type="password" id="m-lock-pass" required placeholder="Your password" style="width:100%; padding:13px 40px 13px 13px; border-radius:12px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#FFF; font-size:0.95rem; outline:none; box-sizing:border-box;">
                                <span onclick="togglePasswordVisibility('m-lock-pass')" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94A3B8; z-index:5;"><i class="fa-solid fa-eye" id="m-lock-pass-eye"></i></span>
                            </div>
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
            if (typeof updateUserSessionUI === 'function') updateUserSessionUI();
            if (typeof updateSidebarContent === 'function') updateSidebarContent();

            const isNative = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'file:' || window.location.protocol === 'capacitor:';
            if (isNative) {
                window.location.href = 'index.html';
            } else {
                window.location.reload();
            }
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

window.handleMandatoryFileSelect = function (event, type) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    if (!window._mandatorySignupDraft) window._mandatorySignupDraft = {};

    if (type === 'cover' && typeof window.openCoverCropperModal === 'function') {
        window.openCoverCropperModal(file, function(croppedDataUrl) {
            window._mandatorySignupDraft.cover_photo = croppedDataUrl;
            const img = document.getElementById('m-cover-preview');
            if (img) {
                img.src = croppedDataUrl;
                img.style.display = 'block';
            }
            const sampleDesign = document.getElementById('m-cover-sample-design');
            if (sampleDesign) sampleDesign.style.display = 'none';
            const status = document.getElementById('m-cover-status');
            if (status) status.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Cover Photo Loaded & Cropped!';
        });
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        const base64 = e.target.result;
        if (type === 'avatar') {
            window._mandatorySignupDraft.avatar = base64;
            const img = document.getElementById('m-avatar-preview');
            if (img) img.src = base64;
            const status = document.getElementById('m-avatar-status');
            if (status) status.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Profile Photo Loaded';
        } else if (type === 'cover') {
            window._mandatorySignupDraft.cover_photo = base64;
            const img = document.getElementById('m-cover-preview');
            if (img) {
                img.src = base64;
                img.style.display = 'block';
            }
            const sampleDesign = document.getElementById('m-cover-sample-design');
            if (sampleDesign) sampleDesign.style.display = 'none';
            const status = document.getElementById('m-cover-status');
            if (status) status.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Cover Photo Loaded';
        }
    };
    reader.readAsDataURL(file);
};

window.selectRolePill = function (role) {
    const roleInput = document.getElementById('m-lock-role');
    if (roleInput) roleInput.value = role;
    const btnCust = document.getElementById('role-btn-customer');
    const btnVend = document.getElementById('role-btn-vendor');
    if (btnCust && btnVend) {
        if (role === 'customer') {
            btnCust.style.background = 'linear-gradient(135deg, var(--accent, #F2A735), #D98E1C)';
            btnCust.style.color = '#000';
            btnCust.style.boxShadow = '0 4px 12px rgba(242,167,53,0.3)';
            btnVend.style.background = 'transparent';
            btnVend.style.color = '#94A3B8';
            btnVend.style.boxShadow = 'none';
        } else {
            btnVend.style.background = 'linear-gradient(135deg, var(--accent, #F2A735), #D98E1C)';
            btnVend.style.color = '#000';
            btnVend.style.boxShadow = '0 4px 12px rgba(242,167,53,0.3)';
            btnCust.style.background = 'transparent';
            btnCust.style.color = '#94A3B8';
            btnCust.style.boxShadow = 'none';
        }
    }
    const mediaBlock = document.getElementById('m-vendor-media-block');
    if (mediaBlock) {
        mediaBlock.style.display = 'block';
    }
    toggleVendorAuthFields(role);
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

    if (!window._mandatorySignupDraft?.avatar || !window._mandatorySignupDraft?.cover_photo) {
        if (errBox) { errBox.textContent = 'Please upload both your Profile Picture and Cover Photo to continue.'; errBox.style.display = 'block'; }
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
    }).then(otpRes => {
        if (otpRes && otpRes.user) {
            return otpRes;
        }
        return API.post('register', draft);
    }).then(res => {
        if (res && res.user) {
            state.user = res.user;
            const token = res.auth_token || res.token;
            if (token) localStorage.setItem('ohati_auth_token', token);
            localStorage.setItem('ohati_user_session', JSON.stringify(res.user));
            if (typeof window.clearAllAuthOverlays === 'function') window.clearAllAuthOverlays();
            else if (typeof window.unlockMandatoryAuthScreen === 'function') window.unlockMandatoryAuthScreen();
            if (typeof updateAppHeader === 'function') updateAppHeader();
            window.location.reload();
        } else {
            unlockOTP();
            throw new Error((res && res.error) ? res.error : 'Registration failed.');
        }
    }).catch(err => {
        unlockOTP();
        if (errBox) {
            errBox.textContent = (err && err.error) ? err.error : (err && err.message ? err.message : 'Registration failed. Please try again.');
            errBox.style.display = 'block';
        }
    });
};

window.showDiditKycModalPopup = function(user) {
    if (!user) return;
    const kycSt = (user.kyc_status || '').toLowerCase();
    if (kycSt === 'approved' || kycSt === 'pending_verification' || kycSt === 'in review' || kycSt === 'rejected') {
        return;
    }
    if (sessionStorage.getItem('ohati_kyc_skipped_' + user.id) === '1') {
        return;
    }
    if (document.getElementById('didit-kyc-popup-container')) return;

    const modalHtml = `
        <div id="didit-kyc-popup-overlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(8px); z-index:999999; display:flex; align-items:center; justify-content:center; padding:20px;">
            <div style="background:#0F1923; border:1px solid rgba(255,255,255,0.15); border-radius:24px; width:100%; max-width:440px; padding:28px 24px; box-shadow:0 24px 60px rgba(0,0,0,0.9); color:#FFF; text-align:center; position:relative;">
                <div style="width:72px; height:72px; border-radius:20px; background:rgba(242,167,53,0.12); border:2px solid var(--accent, #F2A735); margin:0 auto 16px; display:flex; align-items:center; justify-content:center; color:var(--accent, #F2A735); font-size:2rem; box-shadow:0 8px 24px rgba(242,167,53,0.25);">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <div style="font-size:0.75rem; font-weight:800; color:var(--accent, #F2A735); text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Identity Verification</div>
                <h3 style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:800; margin:0 0 8px 0; color:#FFF;">Verify Your Identity</h3>
                <p style="font-size:0.85rem; color:#94A3B8; margin:0 0 20px 0; line-height:1.4;">
                    Complete quick automated verification with your Ghana Card or Passport for enhanced safety and trusted badge status on Ohati.
                </p>
                <div style="background:rgba(255,255,255,0.04); border-radius:12px; padding:14px; margin-bottom:20px; text-align:left; font-size:0.8rem; color:#CBD5E1; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-shield-halved" style="color:var(--accent, #F2A735);"></i> Ghana Card & Passport check</div>
                    <div style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-lock" style="color:#34D399;"></i> Bank-grade encryption & data security</div>
                    <div style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-badge-check" style="color:#60A5FA;"></i> Earn your Verified badge for higher trust</div>
                </div>

                <div style="display:flex; flex-direction:column; gap:10px;">
                    <button class="btn btn-primary btn-full" id="btn-popup-start-kyc" onclick="startPopupDiditKyc(event)" style="padding:13px; font-weight:800; font-size:0.95rem; border-radius:12px; background:linear-gradient(135deg, var(--accent, #F2A735), #D98E1C); color:#000; border:none; cursor:pointer;">
                        <i class="fa-solid fa-shield-check" style="margin-right:6px;"></i> Start Verification
                    </button>
                    <button class="btn btn-outline btn-full" onclick="skipPopupDiditKyc(${user.id})" style="padding:12px; font-weight:700; font-size:0.85rem; border-radius:12px; background:transparent; border:1px solid rgba(255,255,255,0.2); color:#CBD5E1; cursor:pointer;">
                        Skip for now
                    </button>
                </div>
            </div>
        </div>
    `;

    const el = document.createElement('div');
    el.id = 'didit-kyc-popup-container';
    el.innerHTML = modalHtml;
    document.body.appendChild(el);
};

window.skipPopupDiditKyc = function(userId) {
    if (userId) {
        sessionStorage.setItem('ohati_kyc_skipped_' + userId, '1');
    }
    const popup = document.getElementById('didit-kyc-popup-container');
    if (popup) popup.remove();
};

window.startPopupDiditKyc = async function(e) {
    if (e && e.preventDefault) e.preventDefault();
    const btn = document.getElementById('btn-popup-start-kyc');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Launching Verification...';
    }
    try {
        const res = await API.initDiditKyc();
        const popup = document.getElementById('didit-kyc-popup-container');
        if (popup) popup.remove();
        if (res && res.url) {
            if (typeof renderDiditKycScreen === 'function') {
                renderDiditKycScreen({ url: res.url, session_id: res.session_id });
            }
            if (typeof navigateTo === 'function') {
                navigateTo('didit-kyc', { url: res.url, session_id: res.session_id }, { force: true });
            }
            if (typeof openDiditVerificationUrl === 'function') {
                openDiditVerificationUrl(res.url);
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-shield-check" style="margin-right:6px;"></i> Start Verification';
        }
        alert(err.message || 'Failed to initialize identity verification.');
    }
};

let mandatoryOtpTimer = null;
window.startMandatoryOTPTimer = function() {
    let secs = 60;
    const cd = document.getElementById('m-otp-countdown');
    const timerBox = document.getElementById('m-otp-timer-box');
    const resend = document.getElementById('m-otp-resend-container');
    if (timerBox) timerBox.style.display = 'block';
    if (resend) resend.style.display = 'none';
    if (mandatoryOtpTimer) clearInterval(mandatoryOtpTimer);
    mandatoryOtpTimer = setInterval(() => {
        secs--;
        if (cd) cd.textContent = secs;
        if (secs <= 0) {
            clearInterval(mandatoryOtpTimer);
            if (timerBox) timerBox.style.display = 'none';
            if (resend) resend.style.display = 'block';
        }
    }, 1000);
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
        if (typeof window.startMandatoryOTPTimer === 'function') window.startMandatoryOTPTimer();
    }).catch(err => {
        if (errBox) { errBox.textContent = err.message || 'Resend failed. Please wait a minute before trying again.'; errBox.style.color = '#FCA5A5'; errBox.style.display = 'block'; }
    });
};

window.handleMandatoryForgotPasswordSubmit = function (e) {
    if (e) e.preventDefault();
    const btn = document.getElementById('m-lock-forgot-btn');
    const idInput = document.getElementById('m-lock-forgot-id');
    const errBox = document.getElementById('m-lock-forgot-error');

    if (errBox) errBox.style.display = 'none';
    const target = idInput ? idInput.value.trim() : '';

    if (!target) {
        if (errBox) { errBox.textContent = 'Please enter your email address.'; errBox.style.display = 'block'; }
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Sending reset link...';
    }

    fetch('api.php?action=forgot_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target: target })
    }).then(r => r.json()).then(res => {
        if (res.success) {
            window._forgotTarget = target;
            renderMandatoryAuthContent('forgot-sent');
        } else {
            if (btn) { btn.disabled = false; btn.textContent = 'Send Reset Link'; }
            if (errBox) { errBox.textContent = res.error || 'Failed to send reset link.'; errBox.style.display = 'block'; }
        }
    }).catch(err => {
        if (btn) { btn.disabled = false; btn.textContent = 'Send Reset Link'; }
        if (errBox) { errBox.textContent = 'Network error. Please try again.'; errBox.style.display = 'block'; }
    });
};

window.handleMandatoryResetPasswordSubmit = function (e) {
    if (e) e.preventDefault();
    const btn = document.getElementById('m-lock-reset-btn');
    const codeInput = document.getElementById('m-lock-reset-code');
    const passInput = document.getElementById('m-lock-reset-pass');
    const errBox = document.getElementById('m-lock-reset-error');
    const succBox = document.getElementById('m-lock-reset-success');

    if (errBox) errBox.style.display = 'none';
    if (succBox) succBox.style.display = 'none';

    const target = window._forgotTarget || '';
    const code = codeInput ? codeInput.value.trim() : '';
    const password = passInput ? passInput.value : '';

    if (!code || !password) {
        if (errBox) { errBox.textContent = 'Please enter both the OTP code and new password.'; errBox.style.display = 'block'; }
        return;
    }
    if (password.length < 8) {
        if (errBox) { errBox.textContent = 'Password must be at least 8 characters.'; errBox.style.display = 'block'; }
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Saving password...';
    }

    fetch('api.php?action=reset_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target: target, code: code, password: password })
    }).then(r => r.json()).then(res => {
        if (res.success) {
            if (succBox) {
                succBox.textContent = 'Password reset successfully! Redirecting to Log In...';
                succBox.style.display = 'block';
            }
            setTimeout(() => {
                renderMandatoryAuthContent('login');
            }, 1800);
        } else {
            if (btn) { btn.disabled = false; btn.textContent = 'Save New Password'; }
            if (errBox) { errBox.textContent = res.error || 'Invalid code or password reset failed.'; errBox.style.display = 'block'; }
        }
    }).catch(err => {
        if (btn) { btn.disabled = false; btn.textContent = 'Save New Password'; }
        if (errBox) { errBox.textContent = 'Network error. Please try again.'; errBox.style.display = 'block'; }
    });
};