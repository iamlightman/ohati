Add-Type -AssemblyName System.Drawing

$projectRoot = "c:\xampp\htdocs\c eye q portfolio\portfolio\demo\ohati app"
$sizesDir = Join-Path $projectRoot "sizes"
$sizesIpad = Join-Path $sizesDir "ipad"

New-Item -ItemType Directory -Force -Path $sizesIpad | Out-Null

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

function Generate-IPadScreenshot($w, $h, $headline, $subhead, $accentColorHex, $screenType, $outputPath) {
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
    $glowBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(40, $accentColor.R, $accentColor.G, $accentColor.B))
    $g.FillEllipse($glowBrush, [int]($w * 0.15), [int]-($h * 0.1), [int]($w * 0.7), [int]($h * 0.35))
    $glowBrush.Dispose()

    # Brand pill header
    $pillBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(35, 255, 255, 255))
    $pillBorder = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(60, $accentColor.R, $accentColor.G, $accentColor.B), 3)
    Draw-RoundedRectangle $g $pillBrush ([int]($w/2 - 250)) 150 500 80 40
    Draw-RoundedRectangleBorder $g $pillBorder ([int]($w/2 - 250)) 150 500 80 40
    $pillBrush.Dispose()
    $pillBorder.Dispose()

    $fontBrand = New-Object System.Drawing.Font("Segoe UI", 32, [System.Drawing.FontStyle]::Bold)
    $goldBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#FFB800"))
    $sfCenter = New-Object System.Drawing.StringFormat
    $sfCenter.Alignment = [System.Drawing.StringAlignment]::Center
    $sfCenter.LineAlignment = [System.Drawing.StringAlignment]::Center
    $g.DrawString("OHATI EVENT MARKETPLACE", $fontBrand, $goldBrush, (New-Object System.Drawing.RectangleF([int]($w/2 - 250), 150, 500, 80)), $sfCenter)

    # Headline
    $fontHead = New-Object System.Drawing.Font("Segoe UI", 68, [System.Drawing.FontStyle]::Bold)
    $whiteBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
    $rectHead = New-Object System.Drawing.RectangleF(100, 260, ($w - 200), 200)
    $g.DrawString($headline, $fontHead, $whiteBrush, $rectHead, $sfCenter)

    # Subhead
    $fontSub = New-Object System.Drawing.Font("Segoe UI", 36, [System.Drawing.FontStyle]::Regular)
    $grayBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#A0AEC0"))
    $rectSub = New-Object System.Drawing.RectangleF(150, 480, ($w - 300), 120)
    $g.DrawString($subhead, $fontSub, $grayBrush, $rectSub, $sfCenter)

    # iPad Frame dimensions
    $padX = [int]($w * 0.08)
    $padY = 640
    $padW = [int]($w * 0.84)
    $padH = ($h - $padY + 200)

    # Outer iPad Bezel
    $padBorderPen = New-Object System.Drawing.Pen([System.Drawing.ColorTranslator]::FromHtml("#1E293B"), 16)
    $padBodyBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#0F172A"))
    Draw-RoundedRectangle $g $padBodyBrush $padX $padY $padW $padH 60
    Draw-RoundedRectangleBorder $g $padBorderPen $padX $padY $padW $padH 60

    # iPad Screen area
    $screenX = $padX + 20
    $screenY = $padY + 20
    $screenW = $padW - 40
    $screenH = $padH - 40
    $screenBgBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#080D14"))
    Draw-RoundedRectangle $g $screenBgBrush $screenX $screenY $screenW $screenH 45

    $contentY = $screenY + 60

    if ($screenType -eq "home") {
        # Sidebar / Navigation for iPad
        $sidebarW = 420
        $sideBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#0F1923"))
        Draw-RoundedRectangle $g $sideBrush $screenX $screenY $sidebarW $screenH 45
        
        $fontNavH = New-Object System.Drawing.Font("Segoe UI", 34, [System.Drawing.FontStyle]::Bold)
        $fontNavI = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Regular)
        $g.DrawString("Ohati Ghana", $fontNavH, $goldBrush, ($screenX + 40), ($screenY + 60))

        $navItems = @('Explore Vendors', 'Photographers', 'Catering and Chefs', 'DJs and Entertainment', 'Decor and Venues', 'Messages and Chat', 'My Bookings')
        $nY = $screenY + 160
        foreach ($item in $navItems) {
            $g.DrawString($item, $fontNavI, $whiteBrush, ($screenX + 40), $nY)
            $nY += 85
        }

        # Main Grid Area
        $mainX = $screenX + $sidebarW + 40
        $mainW = $screenW - $sidebarW - 60

        # Search Bar
        $barBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#16202E"))
        Draw-RoundedRectangle $g $barBrush $mainX $contentY $mainW 90 25
        $fontSearch = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Regular)
        $g.DrawString("Search verified vendors across Ghana...", $fontSearch, $grayBrush, ($mainX + 40), ($contentY + 25))

        # Featured Grid Title
        $fontSec = New-Object System.Drawing.Font("Segoe UI", 36, [System.Drawing.FontStyle]::Bold)
        $g.DrawString("Featured Verified Event Pros", $fontSec, $whiteBrush, $mainX, ($contentY + 130))

        # 2-column grid of vendors for iPad
        $vendors = @(
            @{Name="Lumiere Photography"; Cat="Wedding & Aerial Drone Coverage"; Rate="5.0 ★ (48)"; Price="GHc 2,500"; Color="#3B82F6"},
            @{Name="Royal Delight Catering"; Cat="Buffet & Cocktail Stations"; Rate="4.9 ★ (64)"; Price="GHc 1,800"; Color="#10B981"},
            @{Name="DJ Mic Sound Systems"; Cat="Concert Sound & Stage Lighting"; Rate="5.0 ★ (31)"; Price="GHc 1,200"; Color="#8B5CF6"},
            @{Name="Grand Marquee Venues"; Cat="Luxury 500-Guest Banquet Hall"; Rate="4.8 ★ (52)"; Price="GHc 8,000"; Color="#EC4899"},
            @{Name="Glamour Glow Makeup"; Cat="Bridal MUA & Hairstyling"; Rate="5.0 ★ (27)"; Price="GHc 950"; Color="#F59E0B"},
            @{Name="Blissful Decor & Floral"; Cat="Modern Wedding Stage Decor"; Rate="4.9 ★ (39)"; Price="GHc 3,400"; Color="#06B6D4"}
        )

        $gridY = $contentY + 200
        $cardW = [int](($mainW - 30) / 2)
        $cardH = 260

        for ($i=0; $i -lt $vendors.Length; $i++) {
            $col = $i % 2
            $row = [math]::Floor($i / 2)
            $cx = $mainX + ($col * ($cardW + 30))
            $cy = $gridY + ($row * ($cardH + 25))

            $cBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#131D2A"))
            Draw-RoundedRectangle $g $cBrush $cx $cy $cardW $cardH 25

            $v = $vendors[$i]
            $avBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml($v.Color))
            $g.FillEllipse($avBrush, ($cx + 30), ($cy + 30), 80, 80)
            $avBrush.Dispose()

            $fontVT = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)
            $fontVC = New-Object System.Drawing.Font("Segoe UI", 22, [System.Drawing.FontStyle]::Regular)
            $g.DrawString($v.Name, $fontVT, $whiteBrush, ($cx + 130), ($cy + 30))
            $g.DrawString($v.Cat, $fontVC, $grayBrush, ($cx + 130), ($cy + 75))
            $g.DrawString($v.Rate, $fontVC, $goldBrush, ($cx + 30), ($cy + 180))
            $g.DrawString($v.Price, $fontVT, $goldBrush, ($cx + $cardW - 190), ($cy + 180))

            $cBrush.Dispose()
        }
    }
    elseif ($screenType -eq "vendor") {
        # 2-Pane Split for iPad (Profile Left, Packages Right)
        $leftW = [int]($screenW * 0.42)
        $rightX = $screenX + $leftW + 40
        $rightW = $screenW - $leftW - 60

        # Left Pane (Vendor Info & Gallery)
        $coverBrush = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#1D4ED8"))
        Draw-RoundedRectangle $g $coverBrush ($screenX + 30) $contentY ($leftW - 30) 340 30
        
        $fontVBig = New-Object System.Drawing.Font("Segoe UI", 38, [System.Drawing.FontStyle]::Bold)
        $fontSubBig = New-Object System.Drawing.Font("Segoe UI", 26, [System.Drawing.FontStyle]::Regular)
        $g.DrawString("Lumiere Photography", $fontVBig, $whiteBrush, ($screenX + 30), ($contentY + 380))
        $g.DrawString("Verified Partner • Accra, Ghana`nRating: 5.0 ★ (48 authentic reviews)", $fontSubBig, $goldBrush, ($screenX + 30), ($contentY + 440))

        # Left Buttons
        $btnHalfW = [int](($leftW - 45) / 2)
        $btnChat = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#1E293B"))
        $btnBook = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#FFB800"))
        Draw-RoundedRectangle $g $btnChat ($screenX + 30) ($contentY + 540) $btnHalfW 100 25
        Draw-RoundedRectangle $g $btnBook ($screenX + 45 + $btnHalfW) ($contentY + 540) $btnHalfW 100 25
        
        $fontBtn = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)
        $btnTextDark = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::Black)
        $g.DrawString("Direct Chat", $fontBtn, $whiteBrush, (New-Object System.Drawing.RectangleF(($screenX + 30), ($contentY + 540), $btnHalfW, 100)), $sfCenter)
        $g.DrawString("Book Now", $fontBtn, $btnTextDark, (New-Object System.Drawing.RectangleF(($screenX + 45 + $btnHalfW), ($contentY + 540), $btnHalfW, 100)), $sfCenter)

        # Right Pane (Service Packages)
        $fontSec = New-Object System.Drawing.Font("Segoe UI", 36, [System.Drawing.FontStyle]::Bold)
        $g.DrawString("Available Service Packages", $fontSec, $whiteBrush, $rightX, $contentY)

        $packages = @(
            @{Name="Silver Event Package"; Desc="Full-day 1 photographer + 200 retouched high-res photos"; Price="GHc 2,500"},
            @{Name="Gold Wedding Deluxe"; Desc="2 Photographers + 4K Drone Aerials + Video highlights reel"; Price="GHc 4,800"},
            @{Name="Platinum Royal Coverage"; Desc="Complete photo book + Video documentary + Photobooth station"; Price="GHc 7,500"},
            @{Name="Pre-Wedding Studio Shoot"; Desc="Studio session + 3 outfit changes + Framed canvas print"; Price="GHc 1,800"}
        )

        $pkgY = $contentY + 70
        foreach ($p in $packages) {
            $pBg = New-Object System.Drawing.SolidBrush([System.Drawing.ColorTranslator]::FromHtml("#131D2A"))
            Draw-RoundedRectangle $g $pBg $rightX $pkgY $rightW 220 25
            
            $fontPTitle = New-Object System.Drawing.Font("Segoe UI", 30, [System.Drawing.FontStyle]::Bold)
            $fontPDesc = New-Object System.Drawing.Font("Segoe UI", 24, [System.Drawing.FontStyle]::Regular)
            
            $g.DrawString($p.Name, $fontPTitle, $whiteBrush, ($rightX + 40), ($pkgY + 30))
            $g.DrawString($p.Desc, $fontPDesc, $grayBrush, ($rightX + 40), ($pkgY + 80))
            $g.DrawString($p.Price, $fontPTitle, $goldBrush, ($rightX + 40), ($pkgY + 140))

            $pkgY += 250
            $pBg.Dispose()
        }
    }

    $bmp.Save($outputPath, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose()
    $bmp.Dispose()
    Write-Output "Generated iPad: $outputPath"
}

# Generate 12.9" / 13" iPad Pro Display (2048 x 2732)
Generate-IPadScreenshot 2048 2732 "DISCOVER VERIFIED EVENT PROS" "Ghana's premier marketplace for weddings, birthdays & celebrations" "#3B82F6" "home" (Join-Path $sizesIpad "1_ipad_home_2048x2732.png")
Generate-IPadScreenshot 2048 2732 "COMPARE PACKAGES & PORTFOLIOS" "Transparent pricing, authentic past portfolios and verified reviews" "#10B981" "vendor" (Join-Path $sizesIpad "2_ipad_vendor_2048x2732.png")

Write-Output "All iPad screenshots ready in $sizesIpad!"
