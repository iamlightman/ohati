/* js/jobs.js - Event Jobs Marketplace UI & Logic */

if (typeof showToast !== 'function') {
    window.showToast = function(msg, type = 'info') {
        const titleMap = { error: 'Error', warning: 'Notice', success: 'Success', info: 'Information' };
        const title = titleMap[type] || 'Notice';
        if (typeof showPushNotification === 'function') {
            showPushNotification(title, msg);
        } else {
            alert(`${title}: ${msg}`);
        }
    };
}

if (typeof escapeHtml !== 'function') {
    window.escapeHtml = function(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };
}

if (typeof number_format !== 'function') {
    window.number_format = function(num, decimals = 2) {
        if (num === null || num === undefined || isNaN(num)) return '0.00';
        return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    };
}

if (typeof escapeJsString !== 'function') {
    window.escapeJsString = function(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
    };
}

const JobsModule = {
    currentCategories: [],
    draftJobData: {},
    lastNotifId: null,

    getUser() {
        if (typeof state !== 'undefined' && state && state.user) return state.user;
        if (typeof window.state !== 'undefined' && window.state && window.state.user) return window.state.user;
        if (typeof AppState !== 'undefined' && AppState && AppState.user) return AppState.user;
        try {
            const stored = localStorage.getItem('user') || sessionStorage.getItem('user');
            if (stored) return JSON.parse(stored);
        } catch (e) {}
        return null;
    },

    displayModal(modalHtml) {
        if (typeof openModal === 'function') {
            openModal(modalHtml);
        } else {
            const overlay = document.getElementById('modal-overlay');
            const content = document.getElementById('modal-content');
            if (overlay && content) {
                content.innerHTML = modalHtml;
                overlay.classList.add('open');
                overlay.classList.add('active');
            }
        }
    },

    init() {
        this.loadCategories();
    },

    async loadCategories() {
        try {
            const res = await API.get('job_get_categories');
            if (res && res.categories) {
                this.currentCategories = res.categories;
            }
        } catch (e) {
            console.error('Failed to load job categories:', e);
        }
    },

    openCreateJobModal(jobToEdit = null) {
        return this.openPostJobModal(jobToEdit);
    },

    // ── MULTI-STEP POST JOB MODAL ─────────────────────────────────────────
    openPostJobModal(jobToEdit = null) {
        const user = this.getUser();
        if (!user) {
            if (typeof openLoginModal === 'function') openLoginModal();
            else showToast('Please sign in to post a job.', 'warning');
            return;
        }

        const modalHtml = `
            <div class="job-modal-header" style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--gray-200, #E2E8F0);">
                <h3 style="margin:0; font-size:1.2rem; color:var(--primary, #1B2B4B);"><i class="fa-solid fa-briefcase" style="color:var(--accent, #F2A735); margin-right:8px;"></i>${jobToEdit ? 'Edit Job' : 'Post an Event Job'}</h3>
                <button onclick="closeModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--gray-500);"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div class="job-step-wizard" style="display:flex; justify-content:space-around; background:var(--gray-100, #F8FAFC); padding:12px 20px; border-bottom:1px solid var(--gray-200, #E2E8F0);">
                <div class="wizard-step active" id="job-step-indicator-1" style="font-weight:700; color:var(--primary, #1B2B4B); font-size:0.85rem;"><span style="background:var(--primary, #1B2B4B); color:#fff; padding:2px 8px; border-radius:50%; margin-right:6px;">1</span> General Info</div>
                <div class="wizard-step" id="job-step-indicator-2" style="font-weight:600; color:var(--gray-500); font-size:0.85rem;"><span style="background:var(--gray-300, #CBD5E1); color:#333; padding:2px 8px; border-radius:50%; margin-right:6px;">2</span> Budget & Location</div>
                <div class="wizard-step" id="job-step-indicator-3" style="font-weight:600; color:var(--gray-500); font-size:0.85rem;"><span style="background:var(--gray-300, #CBD5E1); color:#333; padding:2px 8px; border-radius:50%; margin-right:6px;">3</span> Media & Settings</div>
            </div>

            <form id="post-job-form" onsubmit="event.preventDefault();" style="padding:20px; max-height:75vh; overflow-y:auto;">
                <!-- STEP 1 -->
                <div class="job-step-pane" id="job-step-pane-1">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Event Job Title *</label>
                        <input type="text" id="job-input-title" class="form-control" placeholder="e.g. Wedding MC needed for Accra ceremony" value="${jobToEdit ? escapeHtml(jobToEdit.title) : ''}" required style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                    </div>

                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Category *</label>
                            <select id="job-input-category" class="form-control" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                                ${this.currentCategories.map(c => `<option value="${escapeHtml(c.name)}" ${jobToEdit && jobToEdit.category === c.name ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Subcategory (Optional)</label>
                            <input type="text" id="job-input-subcategory" class="form-control" placeholder="e.g. Traditional Wedding" value="${jobToEdit ? escapeHtml(jobToEdit.subcategory || '') : ''}" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Job Description *</label>
                        <textarea id="job-input-description" class="form-control" rows="4" placeholder="Describe the job duties, event theme, guest count, and expectations in detail..." required style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">${jobToEdit ? escapeHtml(jobToEdit.description) : ''}</textarea>
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Required Skills / Equipment</label>
                        <input type="text" id="job-input-skills" class="form-control" placeholder="e.g. Drone Camera, English & Twi fluency, Wireless Mics" value="${jobToEdit ? escapeHtml(jobToEdit.required_skills || '') : ''}" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                    </div>

                    <div style="display:flex; justify-content:flex-end; margin-top:16px;">
                        <button type="button" class="btn btn-primary" onclick="JobsModule.goToStep(2)">Next: Budget & Location <i class="fa-solid fa-arrow-right" style="margin-left:6px;"></i></button>
                    </div>
                </div>

                <!-- STEP 2 -->
                <div class="job-step-pane" id="job-step-pane-2" style="display:none;">
                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Budget (GHS) *</label>
                            <input type="number" id="job-input-budget" class="form-control" placeholder="e.g. 5000" min="0" value="${jobToEdit ? jobToEdit.budget : ''}" required style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Negotiable?</label>
                            <select id="job-input-negotiable" class="form-control" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                                <option value="1" ${!jobToEdit || jobToEdit.negotiable == 1 ? 'selected' : ''}>Yes, open to quotes</option>
                                <option value="0" ${jobToEdit && jobToEdit.negotiable == 0 ? 'selected' : ''}>No, fixed budget</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Event Type</label>
                            <select id="job-input-event-type" class="form-control" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                                <option value="physical" ${!jobToEdit || jobToEdit.event_type === 'physical' ? 'selected' : ''}>Physical Event Location</option>
                                <option value="online" ${jobToEdit && jobToEdit.event_type === 'online' ? 'selected' : ''}>Online / Virtual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Location / Region</label>
                            <input type="text" id="job-input-location" class="form-control" placeholder="e.g. East Legon, Accra" value="${jobToEdit ? escapeHtml(jobToEdit.location || '') : ''}" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                        </div>
                    </div>

                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Event Date</label>
                            <input type="date" id="job-input-event-date" class="form-control" value="${jobToEdit ? jobToEdit.event_date : ''}" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Proposal Deadline</label>
                            <input type="date" id="job-input-deadline" class="form-control" value="${jobToEdit ? jobToEdit.deadline : ''}" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-top:16px;">
                        <button type="button" class="btn btn-secondary" onclick="JobsModule.goToStep(1)"><i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i> Back</button>
                        <button type="button" class="btn btn-primary" onclick="JobsModule.goToStep(3)">Next: Media & Options <i class="fa-solid fa-arrow-right" style="margin-left:6px;"></i></button>
                    </div>
                </div>

                <!-- STEP 3 -->
                <div class="job-step-pane" id="job-step-pane-3" style="display:none;">
                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Vendors Needed</label>
                            <input type="number" id="job-input-num-vendors" class="form-control" min="1" value="${jobToEdit ? jobToEdit.num_vendors : '1'}" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Visibility</label>
                            <select id="job-input-visibility" class="form-control" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                                <option value="public" ${!jobToEdit || jobToEdit.visibility === 'public' ? 'selected' : ''}>Public (All Vendors)</option>
                                <option value="invite_only" ${jobToEdit && jobToEdit.visibility === 'invite_only' ? 'selected' : ''}>Invite Only</option>
                                <option value="private" ${jobToEdit && jobToEdit.visibility === 'private' ? 'selected' : ''}>Private Draft</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block; cursor:pointer;">
                            <input type="checkbox" id="job-input-urgent" ${jobToEdit && jobToEdit.is_urgent == 1 ? 'checked' : ''} style="margin-right:6px;">
                            <span style="color:#DC2626; font-weight:700;"><i class="fa-solid fa-bolt"></i> Mark as Urgent Job</span> (Highlights your job card)
                        </label>
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Attachments (Images, PDF, Floor Plans)</label>
                        <input type="file" id="job-input-files" multiple accept="image/*,.pdf,video/*" class="form-control" style="width:100%; padding:8px; border:1px dashed var(--gray-400); border-radius:8px;">
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-top:20px;">
                        <button type="button" class="btn btn-secondary" onclick="JobsModule.goToStep(2)"><i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i> Back</button>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="btn btn-outline" id="btn-save-draft" onclick="JobsModule.submitJobPost('draft')">Save Draft</button>
                            <button type="button" class="btn btn-primary" id="btn-publish-job" onclick="JobsModule.submitJobPost('open')"><i class="fa-solid fa-paper-plane" style="margin-right:6px;"></i> Publish Job</button>
                        </div>
                    </div>
                </div>
            </form>
        `;

        this.displayModal(modalHtml);
    },

    goToStep(stepNum) {
        for (let i = 1; i <= 3; i++) {
            const pane = document.getElementById(`job-step-pane-${i}`);
            if (pane) pane.style.display = (i === stepNum) ? 'block' : 'none';
        }

        for (let i = 1; i <= 3; i++) {
            const ind = document.getElementById(`job-step-indicator-${i}`);
            if (ind) {
                if (i === stepNum) {
                    ind.style.fontWeight = '700';
                    ind.style.color = 'var(--primary, #1B2B4B)';
                } else {
                    ind.style.fontWeight = '600';
                    ind.style.color = 'var(--gray-500)';
                }
            }
        }
    },

    async submitJobPost(targetStatus = 'open') {
        const titleEl = document.getElementById('job-input-title');
        const categoryEl = document.getElementById('job-input-category');
        const descEl = document.getElementById('job-input-description');

        const title = titleEl ? titleEl.value.trim() : '';
        const category = categoryEl ? categoryEl.value : '';
        const description = descEl ? descEl.value.trim() : '';

        if (!title || !category || !description) {
            showToast('Please fill in Title, Category, and Description.', 'warning');
            this.goToStep(1);
            return;
        }

        const budgetEl = document.getElementById('job-input-budget');
        const budget = budgetEl ? (parseFloat(budgetEl.value) || 0) : 0;
        if (targetStatus === 'open' && budget <= 0) {
            showToast('Please enter a valid budget for your job.', 'warning');
            this.goToStep(2);
            return;
        }

        // Anti-duplicate loading button state
        const saveBtn = document.getElementById('btn-save-draft');
        const pubBtn = document.getElementById('btn-publish-job');
        const targetBtn = targetStatus === 'draft' ? saveBtn : pubBtn;
        const originalHtml = targetBtn ? targetBtn.innerHTML : '';

        if (saveBtn) saveBtn.disabled = true;
        if (pubBtn) pubBtn.disabled = true;

        if (targetBtn) {
            targetBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> ${targetStatus === 'draft' ? 'Saving Draft...' : 'Publishing...'}`;
        }

        // Process attachments if any
        const fileInput = document.getElementById('job-input-files');
        const attachments = [];
        if (fileInput && fileInput.files.length > 0) {
            for (let i = 0; i < fileInput.files.length; i++) {
                const file = fileInput.files[i];
                try {
                    const base64 = await this.fileToBase64(file);
                    attachments.push({
                        name: file.name,
                        type: file.type.includes('image') ? 'image' : (file.type.includes('pdf') ? 'pdf' : 'other'),
                        data: base64
                    });
                } catch (err) {}
            }
        }

        const user = this.getUser();
        const payload = {
            user_id: user ? user.id : 0,
            title,
            category,
            subcategory: document.getElementById('job-input-subcategory') ? document.getElementById('job-input-subcategory').value.trim() : '',
            description,
            required_skills: document.getElementById('job-input-skills') ? document.getElementById('job-input-skills').value.trim() : '',
            budget,
            negotiable: document.getElementById('job-input-negotiable') ? parseInt(document.getElementById('job-input-negotiable').value) : 1,
            event_type: document.getElementById('job-input-event-type') ? document.getElementById('job-input-event-type').value : 'physical',
            location: document.getElementById('job-input-location') ? document.getElementById('job-input-location').value.trim() : '',
            event_date: document.getElementById('job-input-event-date') ? document.getElementById('job-input-event-date').value : '',
            deadline: document.getElementById('job-input-deadline') ? document.getElementById('job-input-deadline').value : '',
            num_vendors: document.getElementById('job-input-num-vendors') ? (parseInt(document.getElementById('job-input-num-vendors').value) || 1) : 1,
            visibility: document.getElementById('job-input-visibility') ? document.getElementById('job-input-visibility').value : 'public',
            is_urgent: (document.getElementById('job-input-urgent') && document.getElementById('job-input-urgent').checked) ? 1 : 0,
            status: targetStatus,
            attachments
        };

        try {
            const res = await API.post('job_post_create', payload);

            if (res && res.success) {
                showToast(res.message || 'Job posted successfully!', 'success');
                if (typeof closeModal === 'function') closeModal();
                if (typeof navigateTo === 'function') navigateTo('user-jobs');
            } else {
                showToast(res ? (res.error || 'Failed to post job.') : 'Failed to post job.', 'error');
            }
        } catch (e) {
            showToast(e.message || 'Network error while posting job.', 'error');
        } finally {
            if (saveBtn) saveBtn.disabled = false;
            if (pubBtn) pubBtn.disabled = false;
            if (targetBtn) targetBtn.innerHTML = originalHtml;
        }
    },

    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    },

    // ── PROPOSAL SUBMISSION MODAL ─────────────────────────────────────────
    openApplyModal(jobId, jobTitle, budget) {
        const user = this.getUser();
        if (!user) {
            if (typeof openLoginModal === 'function') openLoginModal();
            else showToast('Please sign in to submit proposals.', 'warning');
            return;
        }

        const modalHtml = `
            <div style="padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="margin:0; font-size:1.15rem; color:var(--primary);"><i class="fa-solid fa-paper-plane" style="color:var(--accent); margin-right:8px;"></i>Submit Proposal</h3>
                    <button onclick="closeModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div style="background:var(--gray-100); padding:12px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
                    <strong style="display:block; color:var(--primary);">${escapeHtml(jobTitle)}</strong>
                    <span style="color:var(--gray-600);">Client Budget: GHS ${number_format(budget, 2)}</span>
                </div>

                <form onsubmit="event.preventDefault(); JobsModule.submitProposal(${jobId});">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Your Price Quote (GHS) *</label>
                        <input type="number" id="proposal-input-quote" class="form-control" value="${budget}" min="1" required style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Estimated Delivery / Timeline *</label>
                        <input type="text" id="proposal-input-timeline" class="form-control" placeholder="e.g. 3 days before event / On-site full day" required style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Cover Letter / Proposal Details *</label>
                        <textarea id="proposal-input-letter" class="form-control" rows="4" placeholder="Explain why you are the best fit for this event job, your experience, and setup details..." required style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label style="font-weight:600; font-size:0.85rem; margin-bottom:4px; display:block;">Portfolio Links (Optional)</label>
                        <input type="text" id="proposal-input-portfolio" class="form-control" placeholder="https://instagram.com/mywork or link to gallery" style="width:100%; padding:10px; border:1px solid var(--gray-300); border-radius:8px;">
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit-proposal"><i class="fa-solid fa-paper-plane" style="margin-right:6px;"></i> Send Proposal</button>
                    </div>
                </form>
            </div>
        `;

        this.displayModal(modalHtml);
    },

    async submitProposal(jobId) {
        const quoteEl = document.getElementById('proposal-input-quote');
        const timelineEl = document.getElementById('proposal-input-timeline');
        const letterEl = document.getElementById('proposal-input-letter');
        const portfolioEl = document.getElementById('proposal-input-portfolio');

        const quote = quoteEl ? parseFloat(quoteEl.value) : 0;
        const timeline = timelineEl ? timelineEl.value.trim() : '';
        const letter = letterEl ? letterEl.value.trim() : '';
        const portfolio = portfolioEl ? portfolioEl.value.trim() : '';

        if (!quote || !timeline || !letter) {
            showToast('Please fill in Quote, Timeline, and Cover Letter.', 'warning');
            return;
        }

        const btn = document.getElementById('btn-submit-proposal');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="margin-right:6px;"></i> Sending Proposal...`;
        }

        const user = this.getUser();
        try {
            const res = await API.post('job_submit_proposal', {
                user_id: user ? user.id : 0,
                job_id: jobId,
                price_quote: quote,
                delivery_timeline: timeline,
                cover_letter: letter,
                portfolio_links: portfolio
            });

            if (res && res.success) {
                showToast(res.message || 'Proposal submitted!', 'success');
                if (typeof closeModal === 'function') closeModal();
                if (typeof navigateTo === 'function') navigateTo('vendor-jobs');
            } else {
                showToast(res ? (res.error || 'Failed to submit proposal.') : 'Failed to submit proposal.', 'error');
            }
        } catch (e) {
            showToast(e.message || 'Network error while submitting proposal.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    },

    // ── PROPOSALS REVIEW INBOX (Host View) ────────────────────────────────
    async openProposalsInboxModal(jobId, jobTitle) {
        try {
            const res = await API.get(`job_get_proposals&job_id=${jobId}`);
            if (!res || !res.success) {
                showToast(res ? res.error : 'Failed to fetch proposals.', 'error');
                return;
            }

            const proposals = res.proposals || [];

            const modalHtml = `
                <div style="padding:20px; max-height:80vh; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <div>
                            <h3 style="margin:0; font-size:1.15rem; color:var(--primary);"><i class="fa-solid fa-inbox" style="color:var(--accent); margin-right:8px;"></i>Proposals Inbox</h3>
                            <span style="font-size:0.85rem; color:var(--gray-600);">${escapeHtml(jobTitle)} (${proposals.length} received)</span>
                        </div>
                        <button onclick="closeModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    ${proposals.length === 0 ? `
                        <div style="text-align:center; padding:30px; color:var(--gray-500);">
                            <i class="fa-solid fa-envelope-open" style="font-size:2rem; margin-bottom:8px;"></i>
                            <p>No proposals received for this job yet.</p>
                        </div>
                    ` : `
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            ${proposals.map(p => `
                                <div style="background:#fff; border:1px solid var(--gray-200); border-radius:10px; padding:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <img src="${escapeHtml(window.resolveImageUrl(p.vendor_avatar))}" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                                            <div>
                                                <strong style="color:var(--primary); font-size:0.95rem;">${escapeHtml(p.vendor_name)}</strong>
                                                <div style="font-size:0.75rem; color:var(--gray-500);">${p.rating ? `<i class="fa-solid fa-star" style="color:#F59E0B;"></i> ${p.rating} (${p.reviews_count || 0})` : 'New Vendor'} • ${escapeHtml(p.vendor_location || 'Ghana')}</div>
                                            </div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div style="font-size:1.15rem; font-weight:800; color:var(--primary);">GHS ${number_format(p.price_quote, 2)}</div>
                                            <span style="font-size:0.75rem; color:var(--gray-500);"><i class="fa-regular fa-clock"></i> ${escapeHtml(p.delivery_timeline)}</span>
                                        </div>
                                    </div>

                                    <p style="font-size:0.85rem; color:var(--gray-700); line-height:1.5; background:var(--gray-100); padding:10px; border-radius:8px; margin-bottom:12px;">
                                        ${escapeHtml(p.cover_letter)}
                                    </p>

                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span class="badge" style="padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; text-transform:capitalize; background:var(--gray-200);">${escapeHtml(p.status)}</span>
                                        <div style="display:flex; gap:6px;">
                                            ${p.status === 'hired' ? `
                                                <button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-check-circle" style="color:#16A34A;"></i> Hired</button>
                                            ` : `
                                                <button class="btn btn-outline btn-sm" onclick="JobsModule.shortlistProposal(${p.id}, ${jobId})">Shortlist</button>
                                                <button class="btn btn-danger btn-sm" onclick="JobsModule.rejectProposal(${p.id}, ${jobId})">Reject</button>
                                                <button class="btn btn-primary btn-sm" onclick="JobsModule.hireVendor(${p.id}, ${jobId}, '${escapeJsString(p.vendor_name)}')"><i class="fa-solid fa-handshake"></i> Hire Vendor</button>
                                            `}
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `}
                </div>
            `;

            this.displayModal(modalHtml);
        } catch (e) { showToast('Error loading proposals.', 'error'); }
    },

    async shortlistProposal(appId, jobId) {
        try {
            const res = await API.post('job_shortlist_proposal', { application_id: appId });
            if (res && res.success) {
                showToast('Vendor proposal shortlisted!', 'success');
                closeModal();
            } else showToast(res.error || 'Failed to shortlist.', 'error');
        } catch (e) { showToast('Network error.', 'error'); }
    },

    async rejectProposal(appId, jobId) {
        try {
            const res = await API.post('job_reject_proposal', { application_id: appId });
            if (res && res.success) {
                showToast('Proposal rejected.', 'info');
                closeModal();
            } else showToast(res.error || 'Failed to reject.', 'error');
        } catch (e) { showToast('Network error.', 'error'); }
    },

    async hireVendor(appId, jobId, vendorName) {
        showConfirmModal({
            title: 'Hire Vendor?',
            message: `Are you sure you want to hire <strong>${escapeHtml(vendorName)}</strong> for this job?`,
            icon: 'fa-handshake',
            confirmText: 'Hire Vendor',
            cancelText: 'Cancel',
            type: 'primary',
            onConfirm: async () => {
                try {
                    const res = await API.post('job_hire_vendor', { application_id: appId });
                    if (res && res.success) {
                        showToast(`🎉 Congratulations! You have hired ${vendorName}.`, 'success');
                        closeModal();
                        if (typeof navigateTo === 'function') navigateTo('user-jobs');
                    } else showToast(res.error || 'Failed to hire vendor.', 'error');
                } catch (e) { showToast('Network error while hiring vendor.', 'error'); }
            }
        });
    },

    async toggleSaveJob(jobId, btnElem) {
        const user = this.getUser();
        if (!user) {
            if (typeof openLoginModal === 'function') openLoginModal();
            return;
        }

        try {
            const res = await API.post('job_toggle_save', { job_id: jobId });
            if (res && res.success) {
                showToast(res.message, 'success');
                if (btnElem) {
                    const icon = btnElem.querySelector('i');
                    if (icon) {
                        if (res.is_saved) icon.className = 'fa-solid fa-bookmark';
                        else icon.className = 'fa-regular fa-bookmark';
                    }
                }
            }
        } catch (e) { showToast('Network error.', 'error'); }
    },

    // ── NOTIFICATION PREFERENCES & REAL-TIME POLLING ──────────────────────
    startNotificationPolling() {
        if (this.notifPollTimer) clearInterval(this.notifPollTimer);
        this.notifPollTimer = setInterval(() => this.pollNotifications(), 10000);
        this.pollNotifications();
    },

    async pollNotifications() {
        const user = this.getUser();
        if (!user) return;
        try {
            const res = await API.get('job_get_notifications');
            if (res && res.success) {
                const unread = res.unread_count || 0;
                const badges = [document.getElementById('notif-badge'), document.getElementById('chat-nav-badge'), document.getElementById('chat-nav-badge-desktop')];
                badges.forEach(b => {
                    if (b) {
                        if (unread > 0) {
                            b.innerText = unread;
                            b.style.display = 'inline-flex';
                        } else {
                            b.style.display = 'none';
                        }
                    }
                });

                const notifs = res.notifications || [];
                if (notifs.length > 0) {
                    const latest = notifs[0];
                    const seenKey = 'ohati_seen_notif_' + latest.id;
                    if (latest.is_read == 0 && !localStorage.getItem(seenKey)) {
                        localStorage.setItem(seenKey, '1');
                        this.lastNotifId = latest.id;
                    }
                }
            }
        } catch (e) {}
    },

    openJobDetailsModal(jobId) {
        this.openJobDetails(jobId);
    },

    async openJobDetails(jobId) {
        try {
            const res = await API.get(`job_get_details&job_id=${jobId}`);
            if (!res || !res.success || !res.job) {
                showToast('Failed to load job details.', 'error');
                return;
            }

            const job = res.job;
            const client = res.client || {};
            const attachments = res.attachments || [];
            const hasApplied = res.has_applied || false;

            const modalHtml = `
                <div style="padding:24px; max-height:85vh; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                        <div>
                            <span style="background:var(--accent-light, #FEF3C7); color:var(--accent-dark, #D97706); padding:4px 10px; border-radius:12px; font-size:0.75rem; font-weight:700; text-transform:uppercase;">${escapeHtml(job.category)}</span>
                            ${job.is_urgent == 1 ? '<span style="background:#FEE2E2; color:#DC2626; padding:4px 10px; border-radius:12px; font-size:0.75rem; font-weight:700; margin-left:6px;"><i class="fa-solid fa-bolt"></i> URGENT</span>' : ''}
                            <h2 style="margin:8px 0 4px 0; font-size:1.4rem; color:var(--primary, #1B2B4B);">${escapeHtml(job.title)}</h2>
                            <span style="font-size:0.85rem; color:var(--gray-600);"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(job.location || 'Nationwide')} • Posted ${escapeHtml(job.created_at)}</span>
                        </div>
                        <button onclick="closeModal()" style="background:none; border:none; font-size:1.3rem; cursor:pointer; color:var(--gray-500);"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:12px; background:var(--gray-100, #F8FAFC); padding:16px; border-radius:12px; margin-bottom:20px;">
                        <div>
                            <span style="font-size:0.75rem; color:var(--gray-500); font-weight:600; display:block;">ESTIMATED BUDGET</span>
                            <strong style="font-size:1.15rem; color:var(--primary, #1B2B4B);">GHS ${number_format(job.budget, 2)}</strong>
                            <span style="font-size:0.7rem; color:var(--gray-500);">${job.negotiable == 1 ? 'Negotiable' : 'Fixed Price'}</span>
                        </div>
                        <div>
                            <span style="font-size:0.75rem; color:var(--gray-500); font-weight:600; display:block;">EVENT DATE</span>
                            <strong style="font-size:0.95rem; color:var(--primary, #1B2B4B);">${escapeHtml(job.event_date || 'Flexible')}</strong>
                        </div>
                        <div>
                            <span style="font-size:0.75rem; color:var(--gray-500); font-weight:600; display:block;">DEADLINE</span>
                            <strong style="font-size:0.95rem; color:var(--primary, #1B2B4B);">${escapeHtml(job.deadline || 'Open')}</strong>
                        </div>
                        <div>
                            <span style="font-size:0.75rem; color:var(--gray-500); font-weight:600; display:block;">PROPOSALS</span>
                            <strong style="font-size:0.95rem; color:var(--primary, #1B2B4B);">${job.applications_count || 0} received</strong>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="margin:0 0 8px 0; color:var(--primary, #1B2B4B);">Description</h4>
                        <p style="font-size:0.92rem; line-height:1.6; color:var(--gray-700); white-space:pre-wrap;">${escapeHtml(job.description)}</p>
                    </div>

                    ${job.required_skills ? `
                        <div style="margin-bottom:20px;">
                            <h4 style="margin:0 0 8px 0; color:var(--primary, #1B2B4B);">Required Skills / Equipment</h4>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                ${job.required_skills.split(',').map(s => `<span style="background:var(--gray-200, #E2E8F0); color:var(--primary); padding:4px 10px; border-radius:6px; font-size:0.8rem; font-weight:600;">${escapeHtml(s.trim())}</span>`).join('')}
                            </div>
                        </div>
                    ` : ''}

                    ${attachments.length > 0 ? `
                        <div style="margin-bottom:20px;">
                            <h4 style="margin:0 0 8px 0; color:var(--primary, #1B2B4B);">Attachments</h4>
                            <div style="display:flex; gap:10px; overflow-x:auto;">
                                ${attachments.map(att => `<a href="${escapeHtml(att.file_path)}" target="_blank" style="padding:8px 12px; background:var(--gray-100); border-radius:8px; text-decoration:none; color:var(--primary); font-size:0.85rem; font-weight:600;"><i class="fa-solid fa-paperclip"></i> ${escapeHtml(att.file_name || 'Attachment')}</a>`).join('')}
                            </div>
                        </div>
                    ` : ''}

                    <div style="background:#F0F9FF; border:1px solid #BAE6FD; padding:14px; border-radius:10px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
                        <div>
                            <strong style="color:#0369A1; display:block;">Posted by ${escapeHtml(client.name || 'Host')}</strong>
                            <span style="font-size:0.78rem; color:#0284C7;">${client.total_jobs_posted || 1} jobs posted on Ohati</span>
                        </div>
                        <span class="badge" style="background:#E0F2FE; color:#0369A1; font-weight:700;">Verified Host</span>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                        ${hasApplied ? `
                            <button type="button" class="btn btn-secondary" disabled style="opacity:0.7;"><i class="fa-solid fa-circle-check" style="color:#16A34A;"></i> Proposal Submitted</button>
                        ` : `
                            <button type="button" class="btn btn-primary" onclick="closeModal(); JobsModule.openApplyModal(${job.id}, '${escapeJsString(job.title)}', ${job.budget});"><i class="fa-solid fa-paper-plane"></i> Submit Proposal</button>
                        `}
                    </div>
                </div>
            `;

            this.displayModal(modalHtml);
        } catch (e) { showToast('Error loading job details.', 'error'); }
    },

    async openNotificationPreferencesModal() {
        const user = this.getUser();
        if (!user) {
            if (typeof openLoginModal === 'function') openLoginModal();
            return;
        }

        try {
            const res = await API.get('job_get_notification_preferences');
            const prefs = (res && res.preferences) ? res.preferences : { pref_inapp: 1, pref_push: 1, pref_sms: 1, pref_email: 1 };

            const modalHtml = `
                <div style="padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 style="margin:0; font-size:1.15rem; color:var(--primary);"><i class="fa-solid fa-bell-gear" style="color:var(--accent); margin-right:8px;"></i>Notification Preferences</h3>
                        <button onclick="closeModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <form onsubmit="event.preventDefault(); JobsModule.saveNotificationPreferences();">
                        <div style="display:flex; flex-direction:column; gap:14px; margin-bottom:20px;">
                            <label style="display:flex; justify-content:space-between; align-items:center; background:var(--gray-100); padding:12px; border-radius:8px; cursor:pointer;">
                                <span><i class="fa-solid fa-bell" style="color:var(--primary); margin-right:8px;"></i> In-App Notifications</span>
                                <input type="checkbox" id="pref-inapp" ${prefs.pref_inapp == 1 ? 'checked' : ''}>
                            </label>
                            <label style="display:flex; justify-content:space-between; align-items:center; background:var(--gray-100); padding:12px; border-radius:8px; cursor:pointer;">
                                <span><i class="fa-solid fa-mobile-screen-button" style="color:var(--primary); margin-right:8px;"></i> Push / Browser Notifications</span>
                                <input type="checkbox" id="pref-push" ${prefs.pref_push == 1 ? 'checked' : ''}>
                            </label>
                            <label style="display:flex; justify-content:space-between; align-items:center; background:var(--gray-100); padding:12px; border-radius:8px; cursor:pointer;">
                                <span><i class="fa-solid fa-comment-sms" style="color:var(--primary); margin-right:8px;"></i> SMS Alerts</span>
                                <input type="checkbox" id="pref-sms" ${prefs.pref_sms == 1 ? 'checked' : ''}>
                            </label>
                            <label style="display:flex; justify-content:space-between; align-items:center; background:var(--gray-100); padding:12px; border-radius:8px; cursor:pointer;">
                                <span><i class="fa-solid fa-envelope" style="color:var(--primary); margin-right:8px;"></i> Email Digest</span>
                                <input type="checkbox" id="pref-email" ${prefs.pref_email == 1 ? 'checked' : ''}>
                            </label>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:8px;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="btn-save-notif-prefs"><i class="fa-solid fa-floppy-disk"></i> Save Preferences</button>
                        </div>
                    </form>
                </div>
            `;

            this.displayModal(modalHtml);
        } catch (e) { showToast('Error loading notification settings.', 'error'); }
    },

    async saveNotificationPreferences() {
        const payload = {
            pref_inapp: document.getElementById('pref-inapp').checked ? 1 : 0,
            pref_push: document.getElementById('pref-push').checked ? 1 : 0,
            pref_sms: document.getElementById('pref-sms').checked ? 1 : 0,
            pref_email: document.getElementById('pref-email').checked ? 1 : 0
        };

        try {
            const res = await API.post('job_update_notification_preferences', payload);
            if (res && res.success) {
                showToast(res.message, 'success');
                closeModal();
            } else showToast(res.error || 'Failed to save preferences.', 'error');
        } catch (e) { showToast('Network error.', 'error'); }
    }
};

// Auto-initialize when loaded
document.addEventListener('DOMContentLoaded', () => {
    JobsModule.init();
    JobsModule.startNotificationPolling();
});
