<?php
// privacy_policy.php — Official Ohati Privacy Policy & Data Safety Document
$page_title = "Privacy Policy — Ohati";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" href="img/app_icon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#0F1923; --accent:#E05A47; --bg:#F8FAFC; --card:#FFFFFF; --text:#1E293B; --gray:#64748B; --border:#E2E8F0; }
        * { box-sizing:border-box; margin:0; padding:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        body { background:var(--bg); color:var(--text); line-height:1.6; padding:24px 16px; }
        .container { max-width:800px; margin:0 auto; background:var(--card); border-radius:16px; padding:32px; border:1px solid var(--border); box-shadow:0 4px 12px rgba(0,0,0,0.05); }
        .logo-wrap { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
        .logo-wrap img { width:40px; height:40px; border-radius:8px; object-fit:cover; }
        .brand { font-size:1.3rem; font-weight:800; color:var(--primary); }
        h1 { font-size:1.8rem; font-weight:800; color:var(--primary); margin-bottom:12px; }
        h2 { font-size:1.2rem; font-weight:700; color:var(--primary); margin:24px 0 8px 0; border-bottom:2px solid #F1F5F9; padding-bottom:4px; }
        p, li { font-size:0.9rem; color:var(--text); margin-bottom:12px; }
        ul { padding-left:20px; }
        .badge { background:rgba(224,90,71,0.1); color:var(--accent); font-weight:700; font-size:0.75rem; padding:4px 10px; border-radius:12px; display:inline-block; margin-bottom:16px; }
        .last-updated { font-size:0.8rem; color:var(--gray); margin-bottom:20px; }
        .contact-box { background:#F1F5F9; padding:16px; border-radius:12px; margin-top:24px; font-size:0.85rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-wrap">
            <img src="img/app_icon.png" alt="Ohati Logo">
            <span class="brand">OHATI</span>
        </div>

        <span class="badge"><i class="fa-solid fa-shield-halved"></i> App Store & Play Store Compliant</span>
        <h1>Privacy Policy & Data Safety</h1>
        <div class="last-updated">Last Updated: July 29, 2026</div>

        <p>Welcome to <strong>Ohati</strong>. We are committed to protecting your personal information and your right to privacy. This Privacy Policy explains how we collect, use, store, and safeguard your data when you visit our website or use our mobile applications (Android and iOS).</p>

        <h2>1. Information We Collect</h2>
        <p>We collect information that you voluntarily provide to us when registering on Ohati, booking event professionals, submitting vendor verification applications, or contacting customer support:</p>
        <ul>
            <li><strong>Account & Contact Info:</strong> Full Name, Email Address, Phone Number, Profile Photo.</li>
            <li><strong>Vendor Identity Verification (KYC):</strong> Government-Issued Identification Cards (Ghana Card, Passport, Driver's License) for vendor background check and verification.</li>
            <li><strong>Booking & Event Details:</strong> Event Type, Date, Location, Guest Count, Negotiated Prices, and Booking Notes.</li>
            <li><strong>Payment & Transaction Information:</strong> Mobile Money (MTN, Vodafone/Telecel, AT) transaction references, Bank transfer receipts, and payout account details. We do not store raw credit card numbers.</li>
            <li><strong>Device & Media Access:</strong> Camera access (for taking KYC selfies & event photos), Photo Library access (for uploading gallery portfolios and receipt screenshots), and Location data (to discover nearby event services in Ghana).</li>
        </ul>

        <h2>2. How We Use Your Information</h2>
        <ul>
            <li>To connect event hosts with verified wedding and event professionals across Ghana.</li>
            <li>To verify vendor credentials, prevent fraud, and enforce quality standards.</li>
            <li>To process booking inquiries, deposit payments, wallet balance payouts, and referral rewards.</li>
            <li>To dispatch SMS and Email notifications regarding booking updates, system alerts, and security OTPs.</li>
            <li>To continuously improve app performance, stability, and security posture.</li>
        </ul>

        <h2>3. Account Deletion & Right to be Forgotten</h2>
        <p>You have full control over your personal data. You can delete your Ohati account at any time directly inside the mobile app or web app:</p>
        <ul>
            <li>In-App Account Deletion: Go to <strong>Profile & Settings &rarr; Account & Privacy &rarr; Delete Account</strong>.</li>
            <li>Upon deletion, your personal credentials (email and phone number) are anonymized and your active sessions are terminated immediately in compliance with Google Play and Apple App Store rules.</li>
        </ul>

        <h2>4. Third-Party Services & Data Security</h2>
        <p>We implement strict administrative, technical, and physical security measures (including TLS encryption, database parameterization, secure password hashing, and restricted file storage) to protect your personal data. We do not sell your personal data to third parties.</p>

        <h2>5. Contact Us</h2>
        <div class="contact-box">
            If you have questions regarding this Privacy Policy or wish to exercise your privacy rights, please contact our Data Protection Officer at:<br>
            <strong>Email:</strong> <a href="mailto:ohatiwebsite@gmail.com" style="color:var(--accent);">ohatiwebsite@gmail.com</a><br>
            <strong>Location:</strong> Accra, Ghana
        </div>
    </div>
</body>
</html>
