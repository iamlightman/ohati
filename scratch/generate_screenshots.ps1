Add-Type -AssemblyName System.Drawing

$projectRoot = "c:\xampp\htdocs\c eye q portfolio\portfolio\demo\ohati app"
$sizesDir = Join-Path $projectRoot "sizes"
$sizes65 = Join-Path $sizesDir "6.5_inch"
$sizes67 = Join-Path $sizesDir "6.7_inch"
$sizesIpad = Join-Path $sizesDir "ipad"

New-Item -ItemType Directory -Force -Path $sizes65 | Out-Null
New-Item -ItemType Directory -Force -Path $sizes67 | Out-Null
New-Item -ItemType Directory -Force -Path $sizesIpad | Out-Null

$realIconPath = Join-Path $projectRoot "img\new_icon_ohati.png"

function Draw-RoundedRectangle([System.Drawing.Graphics]$g, [System.Drawing.Brush]$brush, [int]$x, [int]$y, [int]$width, [int]$height, [int]$radius) {
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d = $radius * 2
    $path.AddArc($x, $y, $d, $d, 180, 90)
    $path.AddArc($x + $width - $d, $y, $d, $d, 270, 90)
    $path.AddArc($x + $width - $d, $y + $height - $d, $d, $d, 0, 90)
    $path.AddArc($x, $y + $height - $d, $d, $d, 90, 90)
    $path.CloseFigure()
    $g.FillPath($brush, $path)
    $path.Dispose()
}

function Draw-RoundedRectangleBorder([System.Drawing.Graphics]$g, [System.Drawing.Pen]$pen, [int]$x, [int]$y, [int]$width, [int]$height, [int]$radius) {
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d = $radius * 2
    $path.AddArc($x, $y, $d, $d, 180, 90)
    $path.AddArc($x + $width - $d, $y, $d, $d, 270, 90)
    $path.AddArc($x + $width - $d, $y + $height - $d, $d, $d, 0, 90)
    $path.AddArc($x, $y + $height - $d, $d, $d, 90, 90)
    $path.CloseFigure()
    $g.DrawPath($pen, $path)
    $path.Dispose()
}

function Draw-RoundedImage([System.Drawing.Graphics]$g, [System.Drawing.Image]$img, [int]$x, [int]$y, [int]$width, [int]$height, [int]$radius) {
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d = $radius * 2
    $path.AddArc($x, $y, $d, $d, 180, 90)
    $path.AddArc($x + $width - $d, $y, $d, $d, 270, 90)
    $path.AddArc($x + $width - $d, $y + $height - $d, $d, $d, 0, 90)
    $path.AddArc($x, $y + $height - $d, $d, $d, 90, 90)
    $path.CloseFigure()
    
    $state = $g.Save()
    $g.SetClip($path)
    $g.DrawImage($img, $x, $y, $width, $height)
    $g.Restore($state)
    $path.Dispose()
}

function Generate-AppStoreScreenshot($w, $h, $headline, $subhead, $accentColorHex, $screenType, $outputPath) {
    # Strictly Format24bppRgb to remove ANY alpha channel / transparency
    $bmp = New-Object System.Drawing.Bitmap($w, $h, [System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

    # Solid base background
    $solidBlack = [System.Drawing.Color]::FromArgb(255, 11, 19, 27)
    $g.Clear($solidBlack)

    # Gradient background
    $rectBg = New-Object System.Drawing.Rectangle(0, 0, $w, $h)
    $cTop = [System.Drawing.Color]::FromArgb(255, 11, 19, 27)
    $cBot = [System.Drawing.Color]::FromArgb(255, 6, 10, 14)
    $gradBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush($rectBg, $cTop, $cBot, 90.0)
    $g.FillRectangle($gradBrush, $rectBg)
    $gradBrush.Dispose()

    # Ambient Accent Glow
    $accentColor = [System.Drawing.ColorTranslator]::FromHtml($accentColorHex)
    $glowBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, [math]::Min(255, $accentColor.R + 20), [math]::Min(255, $accentColor.G + 20), [math]::Min(255, $accentColor.B + 20)))
    $glowPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255, $accentColor.R, $accentColor.G, $accentColor.B), 4)
    $g.DrawEllipse($glowPen, [int]($w * 0.1), [int]-($h * 0.05), [int]($w * 0.8), [int]($h * 0.25))
    $glowBrush.Dispose()
    $glowPen.Dispose()

    # Top Brand Header with Real Icon
    $headerY = 120
    if (Test-Path $realIconPath) {
        $realIcon = [System.Drawing.Image]::FromFile($realIconPath)
        $iconSize = 80
        $iconX = [int]($w/2 - 220)
        Draw-RoundedImage $g $realIcon $iconX $headerY $iconSize $iconSize 18
        $realIcon.Dispose()
    }

    # Brand Title Next to Real Icon
    $fontBrand = New-Object System.Drawing.Font("Segoe UI", 32, [System.Drawing.FontStyle]::Bold)
    $goldBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 255, 184, 0))
    $g.DrawString("OHATI", $fontBrand, $goldBrush, [int]($w/2 - 120), ($headerY + 8))
    
    $fontTag = New-Object System.Drawing.Font("Segoe UI", 18, [System.Drawing.FontStyle]::Bold)
    $grayBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 148, 163, 184))
    $g.DrawString("VERIFIED EVENT MARKETPLACE", $fontTag, $grayBrush, [int]($w/2 - 120), ($headerY + 46))

    # Headline
    $fontHead = New-Object System.Drawing.Font("Segoe UI", 56, [System.Drawing.FontStyle]::Bold)
    $whiteBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 255, 255, 255))
    $sfCenter = New-Object System.Drawing.StringFormat
    $sfCenter.Alignment = [System.Drawing.StringAlignment]::Center
    $sfCenter.LineAlignment = [System.Drawing.StringAlignment]::Center
    $rectHead = New-Object System.Drawing.RectangleF(80, 240, ($w - 160), 200)
    $g.DrawString($headline, $fontHead, $whiteBrush, $rectHead, $sfCenter)

    # Subhead
    $fontSub = New-Object System.Drawing.Font("Segoe UI", 30, [System.Drawing.FontStyle]::Regular)
    $rectSub = New-Object System.Drawing.RectangleF(100, 460, ($w - 200), 120)
    $g.DrawString($subhead, $fontSub, $grayBrush, $rectSub, $sfCenter)

    # Phone Frame dimensions
    $phoneX = [int]($w * 0.08)
    $phoneY = 620
    $phoneW = [int]($w * 0.84)
    $phoneH = ($h - $phoneY + 200)

    # Outer Phone Border & Body
    $phoneBorderPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255, 30, 41, 59), 12)
    $phoneBodyBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 15, 23, 42))
    Draw-RoundedRectangle $g $phoneBodyBrush $phoneX $phoneY $phoneW $phoneH 70
    Draw-RoundedRectangleBorder $g $phoneBorderPen $phoneX $phoneY $phoneW $phoneH 70

    # Phone Screen area
    $screenX = $phoneX + 16
    $screenY = $phoneY + 16
    $screenW = $phoneW - 32
    $screenH = $phoneH - 32
    $screenBgBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 8, 13, 20))
    Draw-RoundedRectangle $g $screenBgBrush $screenX $screenY $screenW $screenH 55

    # Notch
    $notchBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 0, 0, 0))
    Draw-RoundedRectangle $g $notchBrush ([int]($w/2 - 120)) ($screenY + 18) 240 50 25
    $notchBrush.Dispose()

    # Content Area
    $contentY = $screenY + 100

    if ($screenType -eq "home") {
        # Search Bar
        $barBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 22, 32, 46))
        Draw-RoundedRectangle $g $barBrush ($screenX + 30) $contentY ($screenW - 60) 90 25
        $fontSearch = New-Object System.Drawing.Font("Segoe UI", 26, [System.Drawing.FontStyle]::Regular)
        $g.DrawString("Search photographers, catering, DJs in Ghana...", $fontSearch, $grayBrush, ($screenX + 60), ($contentY + 26))

        # Categories Title
        $fontSec = New-Object System.Drawing.Font("Segoe UI", 32, [System.Drawing.FontStyle]::Bold)
        $g.DrawString("Top Event Categories", $fontSec, $whiteBrush, ($screenX + 30), ($contentY + 130))

        # Category Chips
        $cats = @("Photography", "Catering", "DJs & Sound", "Decor & Floral", "Venues", "MC / Hosts")
        $catX = $screenX + 30
        $catY = $contentY + 190
        for ($i=0; $i -lt $cats.Length; $i++) {
            $col = $i % 3
            $row = [math]::Floor($i / 3)
            $cx = $catX + ($col * [int](($screenW - 60)/3))
            $cy = $catY + ($row * 100)
            $chipW = [int](($screenW - 90)/3)
            $chipBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 26, 38, 54))
            Draw-RoundedRectangle $g $chipBrush $cx $cy $chipW 75 20
            $fontChip = New-Object System.Drawing.Font("Segoe UI", 22, [System.Drawing.FontStyle]::Bold)
            $rectChip = New-Object System.Drawing.RectangleF($cx, $cy, $chipW, 75)
            $g.DrawString($cats[$i], $fontChip, $whiteBrush, $rectChip, $sfCenter)
            $chipBrush.Dispose()
        }

        # Featured Vendors Section
        $vY = $contentY + 430
        $g.DrawString("Verified Top Vendors", $fontSec, $whiteBrush, ($screenX + 30), $vY)

        $vendors = @(
            @{Name="Lumiere Photography Studios"; Cat="Wedding & Aerial Drone Coverage"; Rate="5.0 ★ (48 reviews)"; Price="GHc 2,500"; ColorHex="#3B82F6"},
            @{Name="Royal Delight Gourmet Catering"; Cat="Buffet, Private Chef & Canapés"; Rate="4.9 ★ (64 reviews)"; Price="GHc 1,800"; ColorHex="#10B981"},
            @{Name="DJ Mic Sound & Stage Lighting"; Cat="Concerts, Corporate & Wedding DJ"; Rate="5.0 ★ (31 reviews)"; Price="GHc 1,200"; ColorHex="#8B5CF6"},
            @{Name="Grand Marquee & Event Center"; Cat="500-Capacity Luxury Banquet Hall"; Rate="4.8 ★ (52 reviews)"; Price="GHc 8,000"; ColorHex="#EC4899"}
        )

        $cardY = $vY + 60
        foreach ($v in $vendors) {
            $cBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 19, 29, 42))
            Draw-RoundedRectangle $g $cBrush ($screenX + 30) $cardY ($screenW - 60) 220 30
            
            $colObj = [System.Drawing.ColorTranslator]::FromHtml($v.ColorHex)
            $avBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, $colObj.R, $colObj.G, $colObj.B))
            $g.FillEllipse($avBrush, ($screenX + 55), ($cardY + 35), 90, 90)
            $avBrush.Dispose()

            $fontVTitle = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)
            $fontVCat = New-Object System.Drawing.Font("Segoe UI", 22, [System.Drawing.FontStyle]::Regular)
            $fontVPrice = New-Object System.Drawing.Font("Segoe UI", 26, [System.Drawing.FontStyle]::Bold)
            
            $g.DrawString($v.Name, $fontVTitle, $whiteBrush, ($screenX + 165), ($cardY + 35))
            $g.DrawString($v.Cat, $fontVCat, $grayBrush, ($screenX + 165), ($cardY + 78))
            $g.DrawString($v.Rate, $fontVCat, $goldBrush, ($screenX + 165), ($cardY + 120))
            $g.DrawString($v.Price, $fontVPrice, $goldBrush, ($screenX + $screenW - 230), ($cardY + 120))

            $cardY += 250
            $cBrush.Dispose()
        }
    }
    elseif ($screenType -eq "vendor") {
        # Profile Cover
        $coverBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 30, 58, 138))
        Draw-RoundedRectangle $g $coverBrush ($screenX + 30) $contentY ($screenW - 60) 280 30
        $coverBrush.Dispose()

        # Avatar with Real Icon
        if (Test-Path $realIconPath) {
            $realIcon = [System.Drawing.Image]::FromFile($realIconPath)
            Draw-RoundedImage $g $realIcon ([int]($screenX + ($screenW/2) - 80)) ($contentY + 180) 160 160 30
            $realIcon.Dispose()
        }

        # Vendor Name & Badge
        $fontVBig = New-Object System.Drawing.Font("Segoe UI", 36, [System.Drawing.FontStyle]::Bold)
        $fontSubBig = New-Object System.Drawing.Font("Segoe UI", 24, [System.Drawing.FontStyle]::Regular)
        $g.DrawString("Lumiere Photography Studios", $fontVBig, $whiteBrush, (New-Object System.Drawing.RectangleF($screenX, ($contentY + 360), $screenW, 50)), $sfCenter)
        $g.DrawString("KYC Verified Partner • Accra, Ghana • ★ 5.0 (48 reviews)", $fontSubBig, $goldBrush, (New-Object System.Drawing.RectangleF($screenX, ($contentY + 415), $screenW, 40)), $sfCenter)

        # Action Buttons
        $halfW = [int](($screenW - 100) / 2)
        $btnChat = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 30, 41, 59))
        $btnBook = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 255, 184, 0))
        Draw-RoundedRectangle $g $btnChat ($screenX + 40) ($contentY + 475) $halfW 90 25
        Draw-RoundedRectangle $g $btnBook ($screenX + 60 + $halfW) ($contentY + 475) $halfW 90 25
        
        $fontBtn = New-Object System.Drawing.Font("Segoe UI", 26, [System.Drawing.FontStyle]::Bold)
        $btnTextDark = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 0, 0, 0))
        $g.DrawString("Direct Chat", $fontBtn, $whiteBrush, (New-Object System.Drawing.RectangleF(($screenX + 40), ($contentY + 475), $halfW, 90)), $sfCenter)
        $g.DrawString("Book Package", $fontBtn, $btnTextDark, (New-Object System.Drawing.RectangleF(($screenX + 60 + $halfW), ($contentY + 475), $halfW, 90)), $sfCenter)

        # Packages
        $pY = $contentY + 600
        $fontSec = New-Object System.Drawing.Font("Segoe UI", 30, [System.Drawing.FontStyle]::Bold)
        $g.DrawString("Service Packages & Menus", $fontSec, $whiteBrush, ($screenX + 30), $pY)

        $packages = @(
            @{Name="Silver Event Package"; Desc="Full-day 1 photographer + 200 retouched photos"; Price="GHc 2,500"},
            @{Name="Gold Wedding Deluxe"; Desc="2 Photographers + Drone Aerial + Video highlights"; Price="GHc 4,800"},
            @{Name="Platinum Royal Coverage"; Desc="Complete photo book + Video documentary + Photobooth"; Price="GHc 7,500"}
        )

        $pkgY = $pY + 55
        foreach ($p in $packages) {
            $pBg = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 19, 29, 42))
            Draw-RoundedRectangle $g $pBg ($screenX + 30) $pkgY ($screenW - 60) 190 25
            
            $fontPTitle = New-Object System.Drawing.Font("Segoe UI", 26, [System.Drawing.FontStyle]::Bold)
            $fontPDesc = New-Object System.Drawing.Font("Segoe UI", 20, [System.Drawing.FontStyle]::Regular)
            
            $g.DrawString($p.Name, $fontPTitle, $whiteBrush, ($screenX + 55), ($pkgY + 25))
            $g.DrawString($p.Desc, $fontPDesc, $grayBrush, ($screenX + 55), ($pkgY + 70))
            $g.DrawString($p.Price, $fontPTitle, $goldBrush, ($screenX + 55), ($pkgY + 120))

            $pkgY += 220
            $pBg.Dispose()
        }
    }
    elseif ($screenType -eq "chat") {
        # Chat Header
        $headBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 22, 32, 46))
        Draw-RoundedRectangle $g $headBrush ($screenX + 30) $contentY ($screenW - 60) 110 25
        $fontCTitle = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)
        $fontCSub = New-Object System.Drawing.Font("Segoe UI", 20, [System.Drawing.FontStyle]::Regular)
        $greenBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 16, 185, 129))
        
        $g.DrawString("Royal Delight Gourmet Catering", $fontCTitle, $whiteBrush, ($screenX + 60), ($contentY + 22))
        $g.DrawString("● Online • Usually responds in minutes", $fontCSub, $greenBrush, ($screenX + 60), ($contentY + 62))

        # Messages
        $mY = $contentY + 160

        # Vendor Msg 1
        $b1 = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 30, 41, 59))
        Draw-RoundedRectangle $g $b1 ($screenX + 40) $mY 520 150 25
        $fontM = New-Object System.Drawing.Font("Segoe UI", 22, [System.Drawing.FontStyle]::Regular)
        $g.DrawString("Hello! We would be delighted to cater for your wedding in Accra. How many guests are you expecting?", $fontM, $whiteBrush, (New-Object System.Drawing.RectangleF(($screenX + 60), ($mY + 20), 480, 110)))

        # Customer Msg 2
        $mY += 190
        $b2 = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 5, 150, 105))
        Draw-RoundedRectangle $g $b2 ($screenX + $screenW - 560) $mY 520 120 25
        $g.DrawString("Hi! We are expecting 250 guests. Can you send the Gold Buffet menu?", $fontM, $whiteBrush, (New-Object System.Drawing.RectangleF(($screenX + $screenW - 540), ($mY + 20), 480, 80)))

        # Vendor Voice Note Msg 3
        $mY += 160
        $b3 = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 30, 41, 59))
        Draw-RoundedRectangle $g $b3 ($screenX + 40) $mY 500 130 25
        $fontVn = New-Object System.Drawing.Font("Segoe UI", 24, [System.Drawing.FontStyle]::Bold)
        $g.DrawString("▶  Voice Note (0:45)", $fontVn, $goldBrush, ($screenX + 70), ($mY + 28))
        $g.DrawString("|||l|l|||l||l|||l||l|||l||l", $fontVn, $grayBrush, ($screenX + 70), ($mY + 70))

        # Booking Offer Card
        $mY += 180
        $bCard = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 20, 35, 52))
        $bPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255, 255, 184, 0), 3)
        Draw-RoundedRectangle $g $bCard ($screenX + 40) $mY ($screenW - 80) 280 30
        Draw-RoundedRectangleBorder $g $bPen ($screenX + 40) $mY ($screenW - 80) 280 30

        $fontCardT = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)
        $g.DrawString("Custom Wedding Buffet Quote", $fontCardT, $goldBrush, ($screenX + 70), ($mY + 30))
        $g.DrawString("250 Guests • 3-Course Buffet + Live Grilling Station`nTotal: GHc 12,500", $fontM, $whiteBrush, ($screenX + 70), ($mY + 80))

        $acceptBtn = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 255, 184, 0))
        Draw-RoundedRectangle $g $acceptBtn ($screenX + 70) ($mY + 175) ($screenW - 140) 70 20
        $btnDark = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 0, 0, 0))
        $fontBtnSm = New-Object System.Drawing.Font("Segoe UI", 24, [System.Drawing.FontStyle]::Bold)
        $g.DrawString("Confirm & Secure Booking", $fontBtnSm, $btnDark, (New-Object System.Drawing.RectangleF(($screenX + 70), ($mY + 175), ($screenW - 140), 70)), $sfCenter)
    }

    # Save strictly as 24-bit PNG without alpha channel
    $bmp.Save($outputPath, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose()
    $bmp.Dispose()
    Write-Output "Generated 24-bit No-Alpha: $outputPath"
}

# 1. Generate 6.5" Display (1242 x 2688) - Strictly 24-bit No Alpha
Generate-AppStoreScreenshot 1242 2688 "DISCOVER TOP EVENT PROS" "Ghana's verified marketplace for weddings, parties & corporate events" "#3B82F6" "home" (Join-Path $sizes65 "1_home_1242x2688.png")
Generate-AppStoreScreenshot 1242 2688 "COMPARE PACKAGES & MENUS" "Transparent pricing, authentic past portfolios and verified reviews" "#10B981" "vendor" (Join-Path $sizes65 "2_vendor_profile_1242x2688.png")
Generate-AppStoreScreenshot 1242 2688 "DIRECT CHAT & VOICE NOTES" "Negotiate details, send voice notes and confirm instant bookings" "#FFB800" "chat" (Join-Path $sizes65 "3_chat_voice_1242x2688.png")

# 2. Generate 6.7" Display (1290 x 2796) - Strictly 24-bit No Alpha
Generate-AppStoreScreenshot 1290 2796 "DISCOVER TOP EVENT PROS" "Ghana's verified marketplace for weddings, parties & corporate events" "#3B82F6" "home" (Join-Path $sizes67 "1_home_1290x2796.png")
Generate-AppStoreScreenshot 1290 2796 "COMPARE PACKAGES & MENUS" "Transparent pricing, authentic past portfolios and verified reviews" "#10B981" "vendor" (Join-Path $sizes67 "2_vendor_profile_1290x2796.png")
Generate-AppStoreScreenshot 1290 2796 "DIRECT CHAT & VOICE NOTES" "Negotiate details, send voice notes and confirm instant bookings" "#FFB800" "chat" (Join-Path $sizes67 "3_chat_voice_1290x2796.png")

Copy-Item "$sizes65\*" $sizesDir -Force
Write-Output "All screenshots successfully generated with strict 24-bit RGB (No Alpha Channel) in $sizesDir!"
