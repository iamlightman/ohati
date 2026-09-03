# OHATI AGENT README
## Production Application Operating Rules & Source of Truth

> **CRITICAL — READ THIS FILE FIRST**
>
> This file is mandatory reading for any AI coding agent, developer agent, automation agent, or other system that is about to inspect, modify, debug, refactor, deploy, or otherwise work on the Ohati application.
>
> **Ohati is an existing production application. It is already live on the Google Play Store and Apple App Store.**
>
> Therefore, Ohati MUST NOT be treated as a new/greenfield project.

---

# 1. NON-NEGOTIABLE OPERATING RULES

## 1.1 Do not assume

The agent MUST NOT assume what the owner wants.

If an instruction is ambiguous, incomplete, contradictory, or can reasonably be interpreted in more than one way:

**STOP and ASK before making the change.**

Do not guess.

Do not select the interpretation that is merely easiest to implement.

Do not select the interpretation that the agent personally considers "better."

The owner's explicit instruction is the authority.

---

## 1.2 No unauthorized design changes

Unless the owner explicitly requests a design/UI change:

- Do NOT redesign pages.
- Do NOT change layouts.
- Do NOT change colors.
- Do NOT change typography.
- Do NOT change spacing.
- Do NOT change buttons.
- Do NOT change icons.
- Do NOT change navigation.
- Do NOT change animations.
- Do NOT change responsive behavior.
- Do NOT change the visual hierarchy.
- Do NOT replace existing components simply because another component appears cleaner or more modern.

A bug fix is NOT permission to redesign the interface.

If a UI change is technically required to fix the requested problem, make the smallest necessary change and explain it afterward.

---

## 1.3 No unauthorized feature changes

Unless explicitly requested by the owner:

- Do NOT add features.
- Do NOT remove features.
- Do NOT disable features.
- Do NOT merge features.
- Do NOT split features.
- Do NOT change feature workflows.
- Do NOT invent missing functionality.
- Do NOT substitute a different feature for an existing one.
- Do NOT turn a real feature into a demo/mock feature.
- Do NOT remove something because the agent thinks it is unnecessary.

If the agent discovers a potentially useful improvement, report it separately.

**A suggestion is not authorization.**

---

## 1.4 No unauthorized behavioral changes

Existing behavior is part of the production system.

Do not change:

- Authentication behavior
- Authorization behavior
- Customer/vendor roles
- Customer/vendor switching
- Booking workflows
- Payment workflows
- Notification behavior
- Messaging behavior
- Calling behavior
- KYC behavior
- Admin permissions
- Database behavior
- API contracts
- Existing routes
- Existing URLs
- Existing form behavior

unless the owner explicitly requests the change or it is strictly necessary for the requested bug fix.

---

## 1.5 Preserve existing functionality

When fixing one problem:

**Fix the problem without unnecessarily changing anything else.**

Prefer a surgical, minimal change over a broad rewrite.

Do not rewrite entire files or modules merely because the existing implementation is not how the agent would have built it.

Before modifying code, inspect the existing implementation and understand its dependencies.

---

## 1.6 Do not modify unrelated areas

A task concerning one page or feature does not authorize changes to unrelated pages or features.

For every task:

1. Identify the affected files.
2. Identify dependencies.
3. Modify only what is necessary.
4. Avoid unrelated cleanup.
5. Avoid unrelated refactoring.
6. Avoid unrelated dependency changes.

## 1.7 Multi-Directory File Mirroring & Synchronization

Ohati maintains a mirrored asset architecture across web and mobile native wrappers.

Whenever an agent modifies a JavaScript, CSS, or PHP file:

- **The change MUST be synchronized across all target directories**:
  - `/` (root workspace)
  - `www/`
  - `www/js/`
  - `js/`
  - `ios/App/App/public/`

Failure to mirror code across all asset folders will cause web, Android, and iOS builds to drift out of sync.

---

# 2. PRODUCTION-SAFETY RULES

Ohati is live.

Every change must be treated as a potential production-impacting change.

## NEVER:

- Delete production data without explicit authorization.
- Perform destructive database migrations without explicit authorization.
- Change database structure casually.
- Change production database connections to localhost.
- Replace production services with local/demo services.
- Hard-code temporary credentials.
- Commit secrets, API keys, passwords, tokens, private keys, or database credentials.
- Disable security controls simply to make something work.
- Bypass authentication or authorization.
- Expose private user information.
- Introduce test/demo accounts or fake production records.
- Replace real API responses with hard-coded responses.
- Remove validation merely to bypass an error.
- Hide errors instead of fixing their cause.

Any destructive or potentially irreversible operation requires explicit owner approval.

---

# 3. REQUIRED AGENT WORKFLOW

## BEFORE CODING

The agent MUST:

1. Read this entire file.
2. Understand the requested task.
3. Identify the exact existing feature/page/module involved.
4. Inspect the relevant source code.
5. Trace related routes, APIs, database operations, and dependencies.
6. Determine what is currently implemented.
7. Check whether the requested change conflicts with existing behavior.
8. Identify risks to other functionality.
9. Ask the owner if anything is unclear.
10. Only then begin implementation.

---

## WHILE CODING

The agent MUST:

- Make the smallest appropriate change.
- Preserve existing functionality.
- Preserve existing UI unless UI change was requested.
- Reuse the existing architecture where practical.
- Preserve existing routes and API contracts unless change is explicitly authorized.
- Preserve existing data.
- Maintain security controls.
- Avoid unnecessary dependencies.
- Avoid unrelated refactoring.
- Avoid speculative improvements.
- Avoid replacing working systems with abstractions that are not required by the task.

---

## AFTER CODING

The agent MUST:

1. Test the exact requested change.
2. Test the surrounding functionality that could have been affected.
3. Check for relevant PHP/server errors.
4. Check browser/console errors where applicable.
5. Check API responses where applicable.
6. Check database operations where applicable.
7. Check authentication/authorization where applicable.
8. Check responsive behavior where applicable.
9. Confirm that unrelated functionality was not intentionally changed.
10. Report exactly what was changed.
11. Report anything that could not be verified.

Never say "fixed" merely because code was changed.

A fix should be considered complete only after reasonable verification.

---

# 4. CHANGE-CONTROL PRINCIPLE

Every requested change should be treated as one of these categories:

- Bug fix
- Existing feature modification
- Existing UI adjustment
- Security fix
- Performance fix
- Approved new feature
- Approved feature removal
- Approved architectural/database change

The agent MUST NOT silently convert one category into another.

### Example

If the owner says:

> "Fix the booking button."

The agent must fix the booking button.

It must NOT:

- redesign the booking page,
- change the booking workflow,
- add a new booking system,
- remove existing booking options,
- change payment behavior,
- change customer/vendor permissions,

unless required by the requested fix or explicitly authorized.

---

# 5. OHATI SOURCE OF TRUTH

The following catalog defines the existing Ohati platform architecture, pages, screens, modules, functions, and features supplied for this project.

**Do not invent additional platform functionality and present it as existing functionality.**

Where the catalog does not establish an implementation detail, the agent must inspect the actual code or ask the owner.

---

# 6. PLATFORM ARCHITECTURE & DUAL-ROLE ENGINE

## Hybrid Single-Page Application (SPA) Engine

- Seamless client-side route handling:
  - `js/screens.js`
  - `js/app.js`
- Server-side PHP fallbacks for direct deep links and SEO indexing:
  - `index.php`
  - `sitemap.php`

## Dynamic Role Switcher

Logged-in users can toggle between:

- Customer Mode
- Vendor Mode

Customer → Vendor:

- Launches an in-app setup modal to collect business details if the user is not yet a vendor.

Vendor → Customer:

- Toggles active mode instantly without extra requirements.

**Do not alter this role-switching behavior without explicit instruction.**

---

# 7. CUSTOMER & VENDOR FRONTEND SCREENS

| Screen / Page | Route / File | Core Purpose / Functionality |
|---|---|---|
| Home Dashboard | `home.php` / `#screen-home` | Hero banner search, featured category grid, top-rated vendor carousel, active advertisement banners, and quick action bar. |
| Search & Discovery | `search.php` / `#screen-search` | Multi-filter vendor search by category, region, city, budget, rating, and verified badge. Instant autocomplete and live results grid. |
| Vendor Detail View | `detail.php` / `#screen-detail` | Vendor showcase with photo gallery lightbox, service packages, pricing, interactive location map, verified reviews, follow button, chat CTA, and direct booking modal. |
| Vendor Directory | `#screen-vendors` | Full categorized listing of registered vendors across Ghana. |
| Comparison Tool | `compare.php` / `#screen-compare` | Side-by-side comparison of up to 4 selected vendors: price, category, packages, location, and rating. |
| Saved / Favorites | `favorites.php` / `#screen-favorites` | Saved vendor list synchronized to the user account. |
| Direct Messaging & Inbox | `chat.php` / `#screen-chat` | Real-time chat inbox with online status indicators, search filter, voice note recorder, image/attachment uploader, and unread badges. |
| Voice & Video Calling | Integrated WebRTC | Peer-to-peer WebRTC voice and video calls between customers and vendors with ringing sounds and active call controls. |
| Booking & Price Negotiation | `bookings.php` / `#screen-bookings` | Booking manager with Pending, Price Offered, Confirmed, Completed, and Cancelled statuses; custom price negotiation and deposit payment flow. |
| Event Planner & Budget Checklist | `planner.php` / `#screen-planner` | Event budget calculator, dynamic category checklist, expense logger, and PDF/Print invoice exporter. |
| Job Marketplace — Client | `user-jobs.php` / `#screen-jobs-client` | Post custom event jobs with budget, location, and description; view vendor proposals; shortlist quotes; hire vendors. |
| Job Marketplace — Vendor | `vendor-jobs.php` / `#screen-jobs-vendor` | Browse open client jobs, filter by category/location, and submit competitive proposals with custom quotes and cover letters. |
| Vendor Management Dashboard | `vendor-dash.php` / `#screen-vendor-dash` | Vendor CRM: booking management, earnings stats, service package editor, auto-responder settings, availability toggle. |
| Advertising & Boost Center | `vendor-ads.php` / `promotions.php` | Create and boost ad campaigns such as home banner, category top, and search boost with Paystack/manual payment proof. |
| Notifications Center | `notifications.php` / `#screen-notifications` | Deduplicated real-time alerts for booking updates, payment confirmations, message alerts, and platform notices. |
| User Profile & Account Settings | `profile.php` / `profile-edit.php` | Account management, profile picture upload, password change, identity verification status, and notification preferences. |
| Wedding & Event Blog | `blog.php` / `blog-detail.php` | Article feed, category tags, read times, search, social sharing, and interactive comments with moderation flag. |
| Help & Support Center | `help.php` / `#screen-help` | FAQ accordions, platform guidelines, issue reporter, and direct WhatsApp support link. |
| App Download & Privacy Terms | `privacy_policy.php`, `terms.php` | Official platform privacy policies, terms of service, and app-store download modal. |

---

# 8. SECURITY, AUTHENTICATION & VERIFICATION

## Numeric OTP Enforcement

All 6-digit OTP fields must:

- Accept digits `0-9` only.
- Block letters.
- Block symbols.

## Didit Automated KYC

Existing KYC integration:

- `didit_helper.php`
- `didit_webhook.php`

Supported identity documents:

- Ghana Card
- Passport
- Driver's License

Includes selfie matching.

## CSRF & Rate Limiting

Session-based CSRF validation and rate limiting are handled through:

- `auth_guard.php`

Security protections MUST NOT be disabled or bypassed to solve ordinary implementation problems.

---

# 9. PAYMENTS

## Important clarification

**Ohati does NOT use escrow.**

Do not add, restore, invent, or reintroduce escrow functionality unless the owner explicitly requests it in a future instruction.

Do not infer an escrow workflow from old documentation.

Do not create a vendor escrow balance or escrow-release workflow.

## Paystack

The supplied catalog identifies Paystack for:

- Direct card payments
- Mobile Money payments
  - MTN
  - Vodafone/Telecel
  - AirtelTigo

Relevant files:

- `payment_api.php`
- `payments.php`

## Manual Payment / Bank Transfer Verification

Existing catalog functionality includes:

- Receipt submission.
- Admin verification for manual bank/MoMo deposits.

**Do not change the payment architecture without explicit instruction.**

---

# 10. REAL-TIME COMMUNICATION & NOTIFICATIONS

## Central Notification Engine

`NotificationService` in:

- `notification.js`

Responsibilities include:

- Preventing duplicate toast alerts.
- Tracking unread counts per user session.

## Voice Notes

Browser-native HTML5 Audio Recorder:

- `MediaRecorder`
- Voice messages up to 60 seconds.

## WebRTC Calling

Existing calling engine uses the signal-server cases in `api.php`:

- `initiate_call`
- `check_incoming_call`
- `answer_call`
- `reject_call`
- `end_call`
- `update_call_status`
- `send_ice_candidate`
- `get_call_number`
- `get_call_details`

Do not replace or redesign the calling architecture without explicit authorization.

---

# 11. ADMIN MANAGEMENT CONSOLE

Location:

`/admin/`

The administrative backoffice comprises 29 specialized modules:

## Complete Admin Modules Catalog

| Module | File | Functionality |
|---|---|---|
| Dashboard Overview | `admin/index.php` | Platform KPI metrics, total revenue, active bookings, total vendors/users, quick activity feeds. |
| Vendor Management | `admin/vendors.php` | Approve/reject vendor registrations, edit vendor details, assign Premium Gold Badges, toggle active status. |
| User Account Control | `admin/users.php` | Search accounts, edit roles, view login history, reset credentials, suspend/ban users. |
| KYC Identity Verification | `admin/kyc.php`, `admin/kyc_history.php` | Review pending Ghana Card/selfie verification submissions and approve/reject identity badges. |
| Booking Administration | `admin/bookings.php` | View platform bookings and manage booking status. **Direct payment & direct payout model. Escrow is strictly forbidden.** |
| Payment & Payout Control | `admin/payments.php` | Review manual payment receipts, verify Paystack transactions, process vendor withdrawal payouts. |
| Job Market Oversight | `admin/jobs.php` | Manage client jobs, review vendor proposals, remove inappropriate listings. |
| Promotions & Ads Manager | `admin/promotions.php` | Review vendor ad campaigns, approve featured home banners, set campaign start/end dates. |
| Discount Requests | `admin/discounts.php` | Manage special vendor discount requests and promo vouchers. |
| Blog & Article CMS | `admin/blog.php` | Create, edit, and publish wedding blog articles with featured images. |
| Category Manager | `admin/categories.php` | Create and reorder vendor categories with custom FontAwesome icons. |
| Platform Reviews Moderation | `admin/reviews.php` | Approve/delete ratings and reviews and assign "Verified Booking" badges. |
| Financial Audit Log | `admin/audit_log.php` | Immutable log of direct payments, wallet payouts, and admin overrides with IP/device tracking. |
| Issues & Support Tickets | `admin/issues.php` | Manage support reports, technical issues, and user/comment flags. |
| Referral Program Tracker | `admin/referrals.php` | Track referral bonus payouts, referrer codes, and invitation rewards. |
| OTP Audit Logs | `admin/otp_logs.php` | Log SMS/email OTP delivery statuses and verification timestamps. |
| Deleted Accounts & Trash Archive | `admin/deleted_accounts.php`, `admin/trash.php` | Archive deleted accounts and soft-deleted platform records. |
| System Settings | `admin/settings.php` | Global platform configuration, currency settings, email/SMS API keys, platform commission rates. |
| Database Backup & Restore | `admin/restore_database.php` | One-click database backup exporter/importer tool. |
| Custom Content Editor | `admin/content.php` | Manage custom system landing text and platform content blocks. |
| Diagnostics & Generator Tool | `admin/generator_tool.php` | Diagnostic system tool and test data utility. |
| Admin System Setup | `admin/setup.php` | Initial admin environment setup and database schema validator. |
| Navigation Sidebar Component | `admin/sidebar.php` | Admin layout navigation sidebar component. |
| Security Guard | `admin/auth_guard.php` | Admin authentication enforcement and CSRF protection. |
| Admin Auth Controllers | `admin/login.php`, `admin/logout.php`, `admin/reset_password.php` | Secure admin login, logout, and password recovery controllers. |

---

# 12. BACKEND INFRASTRUCTURE & API SERVICES

## `api.php`

Main REST API engine handling:

- Authentication
- Vendors
- Search
- Bookings
- Chat
- WebRTC calling
- Profile operations

## `payment_api.php`

Payment gateway, payment-related operations, and payout engine.

**Do not add escrow functionality.**

## `jobs_api.php`

Handles:

- Client job postings
- Proposals
- Shortlisting
- Hiring

## `blog_api.php`

Handles:

- Blog article delivery
- Comments
- Post reactions

## `mail_helper.php`

Responsive HTML email notification sender using:

- PHPMailer
- SMTP

## `sms_helper.php`

Automated:

- SMS notifications
- OTP dispatch

Uses:

- Arkesel SMS gateway

## `storage_helper.php`

Handles:

- Image compression
- Avatar cropping
- Document upload processing

## `cron_notification_worker.php`

Background cron worker for:

- Automated event reminders
- Ad campaign expiration checks

---

# 13. UI/UX PRESERVATION RULES

Unless explicitly requested:

### Preserve

- Existing page layouts.
- Existing navigation.
- Existing button placement.
- Existing component behavior.
- Existing responsive behavior.
- Existing customer/vendor experiences.
- Existing admin UI.
- Existing light/dark behavior.
- Existing forms.
- Existing modal behavior.
- Existing animations.
- Existing visual identity.

### Do not perform unsolicited "modernization"

The agent must not say:

- "I redesigned this to make it better."
- "I changed the layout for a better UX."
- "I replaced the component with a more modern one."

unless that was explicitly requested.

---

# 14. DATABASE RULES

The database is production-critical.

Before changing database-related code:

1. Identify the tables involved.
2. Identify relationships.
3. Identify existing reads/writes.
4. Identify API dependencies.
5. Identify admin dependencies.
6. Determine migration impact.
7. Ask for approval if the change is structural or destructive.

## Never

- Drop tables casually.
- Delete production rows casually.
- Rename columns without checking dependencies.
- Change column types without impact analysis.
- Remove indexes without reason.
- Reset production data.
- Replace the production DB with localhost.
- Hard-code database credentials.

If database credentials are found in source code or configuration, do not expose them in responses, logs, commits, or generated documentation.

---

# 15. API & INTEGRATION RULES

Before changing an API:

- Identify its callers.
- Identify request parameters.
- Identify response structure.
- Identify authentication requirements.
- Identify frontend dependencies.
- Identify admin dependencies.
- Preserve backward compatibility where possible.

Do not silently change an API response that existing screens depend on.

Do not replace a real integration with a fake response just to make the UI appear functional.

---

# 16. BUG-FIXING PROTOCOL

When the owner reports a bug:

### Step 1 — Reproduce

Determine:

- What action causes it.
- Which user role experiences it.
- Which page/screen is involved.
- Whether it is frontend, backend, database, API, integration, or deployment related.

### Step 2 — Trace

Inspect:

- Relevant PHP.
- JavaScript.
- API endpoint.
- Database query.
- Session/authentication.
- Storage/upload flow.
- External integration.
- Console/server logs.

### Step 3 — Identify root cause

Do not merely hide the symptom.

### Step 4 — Apply minimal fix

Change only what is required.

### Step 5 — Regression test

Verify:

- The reported issue is fixed.
- The original functionality still works.
- Related workflows still work.

### Step 6 — Report

Tell the owner:

- Root cause.
- Files changed.
- What was changed.
- What was tested.
- Any remaining uncertainty.

---

# 17. WHEN THE AGENT DISCOVERS OTHER PROBLEMS

If the agent notices unrelated bugs or possible improvements:

**DO NOT FIX THEM AUTOMATICALLY.**

Instead, report them separately.

Example:

> "While fixing the booking issue, I noticed an unrelated problem with profile image caching. I did not modify it because it was outside the requested task."

This rule exists to prevent scope creep and accidental production regressions.

---

# 18. NEW FEATURE REQUESTS

If the owner explicitly requests a new feature:

Before implementation, the agent should determine:

- Where it belongs.
- Which existing screens it affects.
- Which user roles need it.
- Which APIs are affected.
- Whether database changes are required.
- Whether admin management is required.
- Whether notifications are required.
- Whether permissions are required.
- Whether existing workflows will be affected.

If any of those requirements are unclear and materially affect implementation:

**ASK BEFORE CODING.**

Do not invent requirements.

---

# 19. FEATURE REMOVAL

Removing a feature requires explicit authorization.

Before removal:

- Identify all frontend references.
- Identify backend/API references.
- Identify database dependencies.
- Identify admin dependencies.
- Identify navigation links.
- Identify notifications.
- Identify permissions.
- Identify external integrations.

Do not simply hide a feature in the UI and declare it removed.

---

# 20. ROLE & PERMISSION SAFETY

Ohati has:

- Customer Mode
- Vendor Mode
- Administrative controls

Do not casually modify role permissions.

A user being able to switch modes is not the same thing as changing their underlying account permissions.

Do not:

- Grant admin access.
- Remove admin access.
- Bypass KYC restrictions.
- Bypass authentication.
- Bypass vendor controls.
- Expose vendor-only functionality to customers.
- Expose customer-only functionality to vendors.

unless explicitly instructed and properly authorized.

---

# 21. AUTHENTICATION & SECURITY

Never solve an authentication problem by weakening authentication.

Never:

- Remove password validation.
- Disable OTP verification.
- Disable CSRF.
- Disable rate limiting.
- Hard-code login success.
- Create authentication bypasses.
- Store plaintext secrets unnecessarily.
- Expose session tokens.
- Log sensitive credentials.

If a security control is causing a legitimate bug, diagnose and fix the implementation rather than removing the control.

---

# 22. FILE UPLOAD & STORAGE SAFETY

Relevant existing service:

`storage_helper.php`

Uploads may include:

- Profile images
- Vendor images
- Attachments
- Identity documents

Do not weaken upload validation or security simply to make an upload work.

When fixing uploads, check:

- File type validation.
- File size.
- Storage path.
- Permissions.
- Filename handling.
- Database record.
- URL/path generation.
- Image processing.
- Frontend rendering.

---

# 23. PRODUCTION DEPLOYMENT RULES

Before deployment:

- Confirm the requested change only.
- Check for syntax errors.
- Check relevant server errors.
- Check API behavior.
- Check database compatibility.
- Check configuration differences between local and production.
- Verify production URLs/configuration.
- Never assume localhost behavior equals production behavior.

After deployment:

- Verify the affected feature in the production environment when possible.
- Check relevant logs.
- Check for new errors.
- Confirm existing functionality remains available.

Never deploy unrelated changes as part of a requested fix.

---

# 24. LOCAL VS PRODUCTION

Ohati may behave differently between development and production.

Do not assume:

> "It works on localhost, therefore it works in production."

Check:

- PHP version.
- Server configuration.
- File permissions.
- URL/rewrite behavior.
- Database connection.
- Environment configuration.
- SMTP.
- SMS gateway.
- Paystack configuration.
- WebRTC signaling.
- Storage paths.
- Cron jobs.

Do not replace production configuration with development configuration.

---

# 25. NO DEMO / MOCK BEHAVIOR

Because Ohati is a live application:

Do not create fake implementations where real functionality is expected.

Examples of prohibited shortcuts:

- Fake booking success.
- Fake payment success.
- Fake KYC approval.
- Fake notification success.
- Fake vendor data.
- Fake database results.
- Hard-coded dashboard statistics.
- Dummy chat messages.
- Fake call connection.
- Fake upload success.

If the requested functionality cannot currently be completed because a dependency is missing, report the dependency instead of pretending it works.

---

# 26. TESTING STANDARD

Testing should be appropriate to the change.

### Frontend

Check:

- Page loads.
- Buttons work.
- Forms submit.
- Modals open/close.
- Data renders.
- Navigation works.
- Responsive behavior remains intact.

### Backend

Check:

- PHP syntax.
- Errors/exceptions.
- API responses.
- Authentication.
- Authorization.
- Database queries.

### Database

Check:

- Correct records are read.
- Correct records are written.
- No unintended records are changed.
- Existing data remains intact.

### Integrations

Where relevant:

- Paystack.
- Didit.
- SMTP/PHPMailer.
- Arkesel.
- WebRTC.
- Storage.

---

# 27. COMMUNICATION STANDARD

When reporting work, use this structure:

## Task

What the owner requested.

## Investigation

What was inspected.

## Root Cause

What caused the problem, if applicable.

## Changes Made

Exact files/modules changed.

## Testing

What was tested.

## Not Changed

Important unrelated areas intentionally left untouched.

## Remaining Issues

Anything that could not be verified or requires owner input.

Do not exaggerate completion.

Do not claim testing that was not actually performed.

---

# 28. OWNER APPROVAL IS REQUIRED FOR

The agent MUST ask before performing any of the following unless the owner has already explicitly authorized it for the current task:

- Redesigning UI.
- Adding features.
- Removing features.
- Changing major workflows.
- Changing customer/vendor role behavior.
- Changing authentication behavior.
- Changing authorization.
- Changing database schema.
- Destructive database operations.
- Deleting production data.
- Replacing integrations.
- Changing payment architecture.
- Changing public routes/API contracts.
- Introducing major dependencies.
- Migrating frameworks.
- Rewriting major modules.
- Changing app-wide visual identity.
- Changing production infrastructure.
- Making changes outside the requested scope.

---

# 29. FINAL RULE

## DO NOT TURN OHATI INTO A DIFFERENT APP.

The goal of an agent working on Ohati is:

**Understand → Preserve → Fix/Implement exactly what was requested → Test → Report.**

Not:

**Assume → Redesign → Rewrite → Add things → Remove things → Change workflows → Hope it works.**

Ohati is already a production application.

Treat the existing codebase, data, workflows, integrations, UI, roles, APIs, and user experience as valuable production assets.

### The default behavior is PRESERVE.

### The default behavior is NOT CHANGE.

### The default behavior is ASK when uncertain.

### Explicit owner instructions override the default only for the scope of that instruction.

---

# 30. QUICK AGENT CHECKLIST

Before every task:

- [ ] Read `OHATI_AGENT_README.md`.
- [ ] Understand the owner's exact request.
- [ ] Identify the affected page/feature/module.
- [ ] Inspect existing implementation.
- [ ] Check dependencies.
- [ ] Ask if anything is ambiguous.
- [ ] Do not redesign unless requested.
- [ ] Do not add features unless requested.
- [ ] Do not remove features unless requested.
- [ ] Do not assume.
- [ ] Do not modify unrelated functionality.
- [ ] Do not use demo/mock behavior.
- [ ] Do not weaken security.
- [ ] Do not alter production data unnecessarily.
- [ ] Do not introduce escrow into Ohati.
- [ ] Make the smallest appropriate change.
- [ ] Test the requested change.
- [ ] Test for regressions.
- [ ] Report exactly what changed.
- [ ] Report anything not verified.

---

# END OF OHATI AGENT README

**If you are an AI coding agent: your first action when beginning work on Ohati must be to read and follow this document.**
