<?php
// vendor-register.php - Ohati Standalone Vendor Onboarding / KYC Registration
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Onboarding - Ohati</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-card-container" style="max-width: 500px;">
        <div class="auth-card" id="vendor-onboard-card">
            <!-- Rendered by JavaScript dynamically below -->
            <div class="full-spinner-wrap"><div class="spinner"></div></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script>
        const isLoggedIn = <?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>;
        const state = {
            categories: [],
            authStep: isLoggedIn ? 2 : 1,
            authData: {
                packages: [['Basic Package', 'GH₵ 3,000', 'Describe standard wedding service details.']],
                hours_mode: 'always',
                custom_hours: {}
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            API.getCategories().then(cats => {
                state.categories = cats;
                state.authData.category = cats[0]?.name || 'Photography';
                renderStep();
            });
        });

        function renderStep() {
            const container = document.getElementById('vendor-onboard-card');
            
            if (state.isOtpStep) {
                container.innerHTML = `
                    <div class="auth-modal-header" style="text-align:center;">
                        <h2 class="auth-modal-title">Account Verification</h2>
                        <p class="auth-modal-subtitle">Enter the 6-digit OTP sent to <strong>${state.otpTarget || 'your email/phone'}</strong></p>
                    </div>
                    <div style="margin: -5px 0 15px 0; padding: 10px 14px; border-radius: 12px; background: rgba(212, 175, 55, 0.1); border: 1px solid var(--accent); font-size: 0.82rem; color: var(--primary); text-align: center;">
                        <i class="fa-solid fa-envelope-open-text" style="color:var(--accent); margin-right:6px;"></i> Check your email inbox or SMS for your verification code.
                    </div>
                    <div class="otp-inputs" style="display:flex; justify-content:center; gap:8px; margin-bottom:16px;" onpaste="handleVOtpPaste(event)">
                        <input type="text" maxlength="1" class="otp-input" id="v-otp-1" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); vOtpMove(1)" onkeyup="vOtpKey(1, event)">
                        <input type="text" maxlength="1" class="otp-input" id="v-otp-2" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); vOtpMove(2)" onkeyup="vOtpKey(2, event)">
                        <input type="text" maxlength="1" class="otp-input" id="v-otp-3" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); vOtpMove(3)" onkeyup="vOtpKey(3, event)">
                        <input type="text" maxlength="1" class="otp-input" id="v-otp-4" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); vOtpMove(4)" onkeyup="vOtpKey(4, event)">
                        <input type="text" maxlength="1" class="otp-input" id="v-otp-5" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); vOtpMove(5)" onkeyup="vOtpKey(5, event)">
                        <input type="text" maxlength="1" class="otp-input" id="v-otp-6" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-lpignore="true" data-1p-ignore="true" spellcheck="false" autocorrect="off" onkeydown="if(event.key.length===1 && !/[0-9]/.test(event.key)){event.preventDefault();}" oninput="this.value=this.value.replace(/[^0-9]/g,''); vOtpMove(6)" onkeyup="vOtpKey(6, event)">
                    </div>
                    <div id="v-otp-error" class="form-error mb-12" style="display:none;"></div>
                    <button class="btn btn-primary btn-full" onclick="verifyStep1Otp()">Verify Code & Continue</button>
                    <button class="btn btn-ghost btn-full mt-8" onclick="state.isOtpStep=false; renderStep();">Back</button>
                `;
                return;
            }

            const step = state.authStep;
            const isUserLoggedIn = isLoggedIn || state.userIsLoggedIn;
            
            let displayTitle = "Vendor Profile Details Setup";
            let displaySubtitle = "";
            let currentDisplayStep = 1;

            if (isUserLoggedIn) {
                currentDisplayStep = Math.max(1, step - 1);
                displaySubtitle = `Step ${currentDisplayStep} of 5 — Profile Setup`;
            } else {
                if (step === 1) {
                    displayTitle = "Vendor Account Registration";
                    displaySubtitle = "Create account credentials & password";
                    currentDisplayStep = 1;
                } else {
                    currentDisplayStep = Math.max(1, step - 1);
                    displaySubtitle = `Next Steps: Step ${currentDisplayStep} of 5 — Vendor Details`;
                }
            }

            let html = `
                <div class="auth-modal-header">
                    <h2 class="auth-modal-title">${displayTitle}</h2>
                    <p class="auth-modal-subtitle">${displaySubtitle}</p>
                </div>
            `;

            if (isUserLoggedIn || step > 1) {
                html += `
                <div class="vendor-steps">
                    ${Array.from({length: 5}, (_, index) => {
                        const i = index + 1;
                        const isActive = i === currentDisplayStep;
                        const isDone = i < currentDisplayStep;
                        return `
                            <div class="vendor-step-item">
                                <div class="vendor-step-circle ${isActive ? 'active' : (isDone ? 'done' : '')}">
                                    ${isDone ? '<i class="fa-solid fa-check"></i>' : i}
                                </div>
                            </div>
                            ${i < 5 ? `<div class="vendor-step-line ${isDone ? 'done' : ''}"></div>` : ''}
                        `;
                    }).join('')}
                </div>
                `;
            }

            switch (step) {
                case 1: // Login Details Creation
                    html += `
                        <h4 style="font-size:0.9rem;margin-bottom:12px;">Step 1: Account Credentials & Password</h4>
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-input" id="v-reg-name" placeholder="John Doe" value="${state.authData.reg_name || ''}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input" id="v-reg-email" placeholder="email@example.com" value="${state.authData.reg_email || ''}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-input" id="v-reg-phone" placeholder="e.g. +233..." value="${state.authData.reg_phone || ''}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-input" id="v-reg-pass" placeholder="Minimum 8 characters">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-input" id="v-reg-confirm" placeholder="Confirm your password">
                        </div>
                        <div id="v-step1-error" class="form-error mb-12" style="display:none;"></div>
                        <button class="btn btn-primary btn-full mt-12" onclick="saveStep1Guest()">Create Account & Next Steps</button>
                    `;
                    break;

                case 2: // Business Details
                    html += `
                        <h4 style="font-size:0.9rem;margin-bottom:12px;">Next Step 1: Business Profile Details</h4>
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
                            ${!isLoggedIn && !state.userIsLoggedIn ? `<button class="btn btn-outline btn-full" onclick="state.authStep=1; renderStep();">Back</button>` : ''}
                            <button class="btn btn-primary btn-full" onclick="saveStep2()">Next Step</button>
                        </div>
                    `;
                    break;

                case 3: // Contact Info
                    html += `
                        <h4 style="font-size:0.9rem;margin-bottom:12px;">Next Step 2: Contact Info</h4>
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
                            <button class="btn btn-outline btn-full" onclick="state.authStep=2; renderStep();">Back</button>
                            <button class="btn btn-primary btn-full" onclick="saveStep3()">Next Step</button>
                        </div>
                    `;
                    break;

                case 4: // Location & Hours
                    html += `
                        <h4 style="font-size:0.9rem;margin-bottom:12px;">Next Step 3: Location & Service Coverage</h4>
                        <div class="form-group">
                            <label class="form-label">Business Address / Location</label>
                            <div style="display:flex; gap:8px;">
                                <input type="text" class="form-input" id="v-address" placeholder="e.g. East Legon, Accra" value="${state.authData.address || ''}" style="flex:1;">
                                <button class="btn btn-outline" style="white-space:nowrap; padding:0 12px;" onclick="pinLiveLocation()"><i class="fa-solid fa-location-crosshairs"></i> Pin</button>
                            </div>
                            <input type="hidden" id="v-lat" value="${state.authData.lat || ''}">
                            <input type="hidden" id="v-lng" value="${state.authData.lng || ''}">
                            <div id="location-pin-status" style="font-size:0.75rem; color:var(--primary); font-weight:600; margin-top:4px; display:none;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Service Coverage</label>
                            <select class="form-select" id="v-radius">
                                <option value="5 km" ${state.authData.radius === '5 km' ? 'selected' : ''}>Within 5 km radius</option>
                                <option value="10 km" ${state.authData.radius === '10 km' ? 'selected' : ''}>Within 10 km radius</option>
                                <option value="25 km" ${state.authData.radius === '25 km' ? 'selected' : ''}>Within 25 km radius</option>
                                <option value="50 km" ${state.authData.radius === '50 km' ? 'selected' : ''}>Within 50 km radius</option>
                                <option value="Nationwide" ${state.authData.radius === 'Nationwide' || !state.authData.radius ? 'selected' : ''}>Nationwide</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Working Hours / Schedule</label>
                            <select class="form-select" id="v-hours-mode" onchange="toggleHoursFields()">
                                <option value="always" ${state.authData.hours_mode === 'always' ? 'selected' : ''}>Always Available (24/7)</option>
                                <option value="custom" ${state.authData.hours_mode === 'custom' ? 'selected' : ''}>Specific Days & Hours</option>
                            </select>
                        </div>
                        <div id="v-custom-hours-container" style="display:${state.authData.hours_mode === 'custom' ? 'block' : 'none'}; background:var(--gray-50); padding:12px; border-radius:8px; margin-bottom:12px;">
                            ${['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => {
                                const savedDay = state.authData.custom_hours?.[day] || { active: true, start: '08:00', end: '18:00' };
                                return `
                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                        <label style="width:50px; font-weight:600; font-size:0.8rem;">
                                            <input type="checkbox" id="hours-active-${day}" ${savedDay.active ? 'checked' : ''}> ${day}
                                        </label>
                                        <input type="time" class="form-input" id="hours-start-${day}" value="${savedDay.start}" style="padding:4px; font-size:0.75rem; height:auto;">
                                        <span style="font-size:0.75rem;">to</span>
                                        <input type="time" class="form-input" id="hours-end-${day}" value="${savedDay.end}" style="padding:4px; font-size:0.75rem; height:auto;">
                                    </div>
                                `;
                            }).join('')}
                        </div>
                        <div style="display:flex;gap:10px;" class="mt-12">
                            <button class="btn btn-outline btn-full" onclick="state.authStep=3; renderStep();">Back</button>
                            <button class="btn btn-primary btn-full" onclick="saveStep4()">Next Step</button>
                        </div>
                    `;
                    break;

                case 5: // Packages
                    html += `
                        <h4 style="font-size:0.9rem;margin-bottom:12px;">Next Step 4: Add Service Packages</h4>
                        <div id="v-packages-container">
                            ${state.authData.packages.map((p, i) => `
                                <div class="card mb-12" style="position:relative;padding:12px;">
                                    <button class="btn btn-ghost btn-sm" style="position:absolute;top:6px;right:6px;" onclick="removePackage(${i})"><i class="fa-solid fa-trash text-error"></i></button>
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <input type="text" class="form-input" id="p-name-${i}" placeholder="Package Name" value="${p[0]}">
                                    </div>
                                    <div class="form-group" style="margin-bottom:8px;">
                                        <input type="text" class="form-input" id="p-price-${i}" placeholder="Starting Price (e.g. GHS 4,500)" value="${p[1]}">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <textarea class="form-textarea" style="min-height:60px;" id="p-details-${i}" placeholder="Included items details...">${p[2]}</textarea>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        <button class="btn btn-outline btn-sm btn-full mb-12" onclick="addPackageRow()"><i class="fa-solid fa-plus"></i> Add Package</button>
                        <div style="display:flex;gap:10px;">
                            <button class="btn btn-outline btn-full" onclick="state.authStep=4; renderStep();">Back</button>
                            <button class="btn btn-primary btn-full" onclick="saveStep5()">Next Step</button>
                        </div>
                    `;
                    break;

                case 6: // KYC
                    html += `
                        <h4 style="font-size:0.9rem;margin-bottom:12px;">Next Step 5: Verify Owner Identity (KYC)</h4>
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
                            <button class="btn btn-outline btn-full" onclick="state.authStep=5; renderStep();">Back</button>
                            <button class="btn btn-primary btn-full" onclick="saveStep6()">Submit Application</button>
                        </div>
                    `;
                    break;
            }

            container.innerHTML = html;
        }

        function toggleHoursFields() {
            const val = document.getElementById('v-hours-mode').value;
            document.getElementById('v-custom-hours-container').style.display = val === 'custom' ? 'block' : 'none';
        }

        function pinLiveLocation() {
            const btn = document.querySelector('button[onclick="pinLiveLocation()"]');
            const status = document.getElementById('location-pin-status');
            if (btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Pinning...';
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    state.authData.lat = lat;
                    state.authData.lng = lng;
                    
                    document.getElementById('v-lat').value = lat;
                    document.getElementById('v-lng').value = lng;
                    
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                        .then(r => r.json())
                        .then(data => {
                            const addr = data.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                            document.getElementById('v-address').value = addr;
                            state.authData.address = addr;
                            if (status) {
                                status.textContent = `📍 Live location pinned! (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                                status.style.display = 'block';
                            }
                            if (btn) btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Pin';
                        })
                        .catch(() => {
                            const addr = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                            document.getElementById('v-address').value = addr;
                            state.authData.address = addr;
                            if (status) {
                                status.textContent = `📍 Live location pinned! (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                                status.style.display = 'block';
                            }
                            if (btn) btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Pin';
                        });
                }, err => {
                    alert('Error getting location: ' + err.message);
                    if (btn) btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Pin';
                });
            } else {
                alert('Geolocation not supported by this browser.');
                if (btn) btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Pin';
            }
        }

        function handleKycFileSelect(event, type) {
            const file = event.target.files[0];
            if (!file) return;
            const status = document.getElementById(type === 'id-front' ? 'front-status' : 'selfie-status');
            const hidden = document.getElementById(type === 'id-front' ? 'v-id-front' : 'v-selfie');
            if (status && hidden) {
                status.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Reading file...`;
                const reader = new FileReader();
                reader.onload = function(e) {
                    hidden.value = e.target.result;
                    state.authData[type === 'id-front' ? 'id_front' : 'selfie'] = e.target.result;
                    status.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i> ${file.name.substring(0, 20)} loaded!`;
                };
                reader.readAsDataURL(file);
            }
        }

        function addPackageRow() {
            collectPackages();
            state.authData.packages.push(['New Package', 'GH₵ 0', 'Details...']);
            renderStep();
        }

        function removePackage(idx) {
            collectPackages();
            state.authData.packages.splice(idx, 1);
            renderStep();
        }

        function collectPackages() {
            state.authData.packages = [];
            const container = document.getElementById('v-packages-container');
            if (!container) return;
            const cards = container.querySelectorAll('.card');
            cards.forEach((c, i) => {
                const name = document.getElementById(`p-name-${i}`)?.value || '';
                const price = document.getElementById(`p-price-${i}`)?.value || '';
                const details = document.getElementById(`p-details-${i}`)?.value || '';
                state.authData.packages.push([name, price, details]);
            });
        }

        function handleVOtpPaste(e) {
            const paste = (e.clipboardData || window.clipboardData)?.getData('text')?.trim();
            if (paste && /^\d{6}$/.test(paste)) {
                e.preventDefault();
                for (let i = 1; i <= 6; i++) {
                    const el = document.getElementById('v-otp-' + i);
                    if (el) el.value = paste[i - 1];
                }
                document.getElementById('v-otp-6')?.focus();
            }
        }
        function vOtpMove(idx) {
            const curr = document.getElementById('v-otp-' + idx);
            if (!curr) return;
            curr.value = curr.value.replace(/\D/g, '');
            if (curr.value.length >= 1 && idx < 6) {
                if (curr.value.length > 1 && /^\d{6}$/.test(curr.value.trim())) {
                    const val = curr.value.trim();
                    for (let i = 1; i <= 6; i++) {
                        const el = document.getElementById('v-otp-' + i);
                        if (el) el.value = val[i - 1];
                    }
                    document.getElementById('v-otp-6')?.focus();
                    return;
                }
                curr.value = curr.value.slice(-1);
                document.getElementById('v-otp-' + (idx + 1))?.focus();
            }
        }
        function vOtpKey(idx, e) {
            if (e.key === 'Backspace' && idx > 1) {
                const curr = document.getElementById('v-otp-' + idx);
                if (curr && curr.value.length === 0) {
                    document.getElementById('v-otp-' + (idx - 1))?.focus();
                }
            }
        }

        function saveStep1Guest() {
            const name = document.getElementById('v-reg-name').value.trim();
            const email = document.getElementById('v-reg-email').value.trim();
            const phone = document.getElementById('v-reg-phone').value.trim();
            const pass = document.getElementById('v-reg-pass').value;
            const confirm = document.getElementById('v-reg-confirm').value;
            const err = document.getElementById('v-step1-error');
            
            err.style.display = 'none';

            if (!name || (!email && !phone) || !pass) {
                err.textContent = 'Please fill in Name, Password, and either Email or Phone.';
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

            state.authData.reg_name = name;
            state.authData.reg_email = email;
            state.authData.reg_phone = phone;
            state.authData.reg_pass = pass;

            const btn = document.querySelector('button[onclick="saveStep1Guest()"]');
            ActionLock.execute(btn, 'Creating Account...', async () => {
                const userPayload = {
                    name: name,
                    email: email,
                    phone: phone,
                    password: pass,
                    role: 'vendor'
                };
                const res = await API.register(userPayload);
                if (res.auth_token) {
                    localStorage.setItem('ohati_auth_token', res.auth_token);
                }
                state.registeredUser = res.user;
                state.otpTarget = email || phone;
                state.isOtpStep = true;
                
                try {
                    await API.sendOTP(state.otpTarget, 'verify', email, phone);
                } catch(e) {}
                
                renderStep();
            }).catch(e => {
                if (err) {
                    err.textContent = e.message || 'Registration error occurred';
                    err.style.display = 'block';
                }
            });
        }

        function verifyStep1Otp() {
            let code = '';
            for (let i = 1; i <= 6; i++) {
                code += document.getElementById('v-otp-' + i)?.value || '';
            }
            const err = document.getElementById('v-otp-error');
            if (code.length < 6) {
                if (err) { err.textContent = 'Please enter all 6 digits.'; err.style.display = 'block'; }
                return;
            }
            const btn = document.querySelector('button[onclick="verifyStep1Otp()"]');
            ActionLock.execute(btn, 'Verifying Code...', async () => {
                await API.verifyOTP(state.otpTarget, code);
                state.isOtpStep = false;
                state.userIsLoggedIn = true;
                state.authStep = 2;
                renderStep();
            }).catch(e => {
                if (err) { err.textContent = e.message || 'Invalid code.'; err.style.display = 'block'; }
            });
        }

        function saveStep2() {
            const biz = document.getElementById('v-bizname').value.trim();
            const cat = document.getElementById('v-category').value;
            const desc = document.getElementById('v-desc').value.trim();
            const exp = parseInt(document.getElementById('v-experience').value) || 0;

            if (!biz || !desc) {
                alert('Please input business name and description.');
                return;
            }
            state.authData.bizname = biz;
            state.authData.category = cat;
            state.authData.desc = desc;
            state.authData.experience = exp;

            state.authStep = 3;
            renderStep();
        }

        function saveStep3() {
            state.authData.phone = document.getElementById('v-phone').value.trim();
            state.authData.whatsapp = document.getElementById('v-whatsapp').value.trim();
            state.authData.email = document.getElementById('v-email').value.trim();
            state.authData.website = document.getElementById('v-website').value.trim();

            state.authStep = 4;
            renderStep();
        }

        function saveStep4() {
            const addr = document.getElementById('v-address').value.trim();
            const radius = document.getElementById('v-radius').value;
            const hoursMode = document.getElementById('v-hours-mode').value;

            if (!addr) {
                alert('Please enter business address.');
                return;
            }

            state.authData.address = addr;
            state.authData.radius = radius;
            state.authData.hours_mode = hoursMode;

            if (hoursMode === 'custom') {
                const custom = {};
                ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach(day => {
                    custom[day] = {
                        active: document.getElementById('hours-active-' + day).checked,
                        start: document.getElementById('hours-start-' + day).value,
                        end: document.getElementById('hours-end-' + day).value
                    };
                });
                state.authData.custom_hours = custom;
                state.authData.hours = Object.entries(custom)
                    .filter(([_, d]) => d.active)
                    .map(([day, d]) => `${day}: ${d.start}-${d.end}`)
                    .join(', ');
            } else {
                state.authData.hours = 'Always Available';
            }

            state.authStep = 5;
            renderStep();
        }

        function saveStep5() {
            collectPackages();
            state.authStep = 6;
            renderStep();
        }

        function saveStep6() {
            state.authData.id_type = document.getElementById('v-id-type').value;
            state.authData.id_front = document.getElementById('v-id-front').value;
            state.authData.selfie = document.getElementById('v-selfie').value;

            if (!state.authData.id_front || !state.authData.selfie) {
                alert('Please upload ID front and selfie before proceeding.');
                return;
            }

            submitFinalApplication();
        }

        function submitFinalApplication() {
            const btn = document.querySelector('button[onclick="saveStep6()"]') || document.querySelector('button[onclick="submitFinalApplication()"]');

            ActionLock.execute(btn, 'Submitting Application...', async () => {
                if (!isLoggedIn && !state.userIsLoggedIn) {
                    const userPayload = {
                        name: state.authData.reg_name,
                        email: state.authData.reg_email,
                        phone: state.authData.reg_phone,
                        password: state.authData.reg_pass,
                        role: 'vendor'
                    };
                    await API.register(userPayload);
                }
                await submitVendorApplicationData();
            }).catch(e => {
                const errMsg = e.message || e || 'Registration error occurred';
                if (typeof showPushNotification === 'function') {
                    showPushNotification('Registration Error', errMsg, 'error');
                } else {
                    alert('Registration Error: ' + errMsg);
                }
            });
        }

        async function submitVendorApplicationData() {
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
            const pkgs = state.authData.packages.map(p => ({
                name: p[0],
                price: p[1],
                details: p[2]
            }));

            const updatePayload = {
                id: vid,
                whatsapp: state.authData.whatsapp,
                website: state.authData.website,
                service_radius: state.authData.radius,
                working_hours: state.authData.hours_mode === 'custom' ? state.authData.custom_hours : { always: true },
                latitude: state.authData.lat || '',
                longitude: state.authData.lng || '',
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
            const isNative = (typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) || window.location.protocol === 'file:' || window.location.protocol === 'capacitor:';
            window.location.href = isNative ? 'index.html?screen=vendor-dash' : 'vendor-dash.php';
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-theme');
            }
        });
    </script>
</body>
</html>
