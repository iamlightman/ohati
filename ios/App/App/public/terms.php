<?php
// terms.php — Official Ohati Ghana Terms of Service Document
$page_title = "Terms of Service — Ohati Ghana";
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
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-wrap">
            <img src="img/app_icon.png" alt="Ohati Logo">
            <span class="brand">OHATI GHANA</span>
        </div>

        <h1>Terms of Service</h1>
        <p style="color:var(--gray); font-size:0.85rem;">Effective Date: July 29, 2026</p>

        <h2>1. Acceptance of Terms</h2>
        <p>By accessing or using Ohati Ghana via web or mobile application, you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, please do not use our services.</p>

        <h2>2. User & Vendor Accounts</h2>
        <ul>
            <li>Users must provide accurate name, email, and phone number during registration.</li>
            <li>Event professionals and vendors must complete KYC identity verification before offering paid services or receiving verified gold badges.</li>
            <li>Account credentials (email, phone number, full name) are locked after registration to preserve transaction integrity.</li>
        </ul>

        <h2>3. Bookings, Payments & Escrow</h2>
        <ul>
            <li>Bookings agreed upon between clients and vendors through Ohati are subject to platform confirmation terms.</li>
            <li>Vendors are responsible for maintaining accurate pricing, availability calendars, and package deliverables.</li>
        </ul>

        <h2>4. Conduct & Content Policy</h2>
        <p>Users and vendors agree not to post deceptive, offensive, or fraudulent content. Ohati reserves the right to suspend accounts or remove campaigns violating community standards.</p>

        <h2>5. Account Termination</h2>
        <p>You may request account deletion at any time via in-app settings. Ohati reserves the right to suspend accounts involved in fraud or breach of terms.</p>
    </div>
</body>
</html>
