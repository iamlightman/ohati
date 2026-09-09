<?php
// download.php — Official Universal Download Page for Ohati Mobile App

$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

$is_ios = (bool)preg_match('/(iphone|ipad|ipod)/i', $user_agent);
$is_android = (bool)preg_match('/android/i', $user_agent);

$ios_store_url = 'https://apps.apple.com/ng/app/ohati/id6801835847';
$android_store_url = 'https://play.google.com/store/apps/details?id=com.ohati.app';

if ($is_ios) {
    header("Location: " . $ios_store_url, true, 302);
    exit();
}

if ($is_android) {
    header("Location: " . $android_store_url, true, 302);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Ohati App — Event Vendor Marketplace</title>
    <link rel="icon" type="image/png" href="img/app_icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: #0C1A30;
            color: #FFFFFF;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -20%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, rgba(12, 26, 48, 0) 70%);
            pointer-events: none;
        }

        .download-card {
            background: rgba(27, 43, 75, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 28px;
            padding: 40px 32px;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 10;
        }

        .app-logo {
            width: 88px;
            height: 88px;
            border-radius: 22px;
            object-fit: cover;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(212, 175, 55, 0.4);
        }

        .download-title {
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #FFFFFF;
            letter-spacing: -0.5px;
        }

        .download-subtitle {
            font-size: 14.5px;
            color: #CBD5E0;
            line-height: 1.5;
            margin-bottom: 28px;
        }

        .store-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }

        .store-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.25s ease;
        }

        .store-btn-apple {
            background: #FFFFFF;
            color: #0C1A30;
        }

        .store-btn-apple:hover {
            background: #F4F7FA;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.2);
        }

        .store-btn-google {
            background: #D4AF37;
            color: #0C1A30;
        }

        .store-btn-google:hover {
            background: #E5C76B;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.3);
        }

        .qr-section {
            background: rgba(12, 26, 48, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .qr-img {
            width: 140px;
            height: 140px;
            border-radius: 12px;
            background: #FFFFFF;
            padding: 8px;
        }

        .qr-label {
            font-size: 12.5px;
            color: #A0AEC0;
            font-weight: 500;
        }

        .back-link {
            display: inline-block;
            margin-top: 24px;
            color: #D4AF37;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            transition: opacity 0.2s ease;
        }

        .back-link:hover {
            opacity: 0.8;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="download-card">
        <img src="img/app_icon.png" alt="Ohati Logo" class="app-logo">
        <h1 class="download-title">Download the Ohati App</h1>
        <p class="download-subtitle">Get the full Ohati experience on your mobile device — Fast booking, real-time chat & instant alerts!</p>

        <div class="store-buttons">
            <a href="<?php echo htmlspecialchars($ios_store_url); ?>" target="_blank" class="store-btn store-btn-apple">
                <i class="fa-brands fa-apple fa-lg"></i> Download on App Store
            </a>
            <a href="<?php echo htmlspecialchars($android_store_url); ?>" target="_blank" class="store-btn store-btn-google">
                <i class="fa-brands fa-google-play fa-lg"></i> Get it on Google Play
            </a>
        </div>

        <div class="qr-section">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://ohati.com/download" alt="Scan QR Code to Download" class="qr-img">
            <span class="qr-label"><i class="fa-solid fa-qrcode"></i> Scan QR code with your phone camera</span>
        </div>

        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Continue to Ohati Web</a>
    </div>

</body>
</html>
