Add-Type -AssemblyName System.Drawing

$projectRoot = "c:\xampp\htdocs\c eye q portfolio\portfolio\demo\ohati app"
$iconDir = Join-Path $projectRoot "ios_templates\Assets.xcassets\AppIcon.appiconset"
New-Item -ItemType Directory -Force -Path $iconDir | Out-Null

$srcPath = Join-Path $projectRoot "img\new_icon_ohati.png"
$src = [System.Drawing.Image]::FromFile($srcPath)

# 1024x1024 App Store Icon
$bmp = New-Object System.Drawing.Bitmap(1024, 1024, [System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

$solidBlack = [System.Drawing.Color]::FromArgb(255, 15, 25, 35)
$g.Clear($solidBlack)
$g.DrawImage($src, 0, 0, 1024, 1024)

$target1 = Join-Path $iconDir "App-Icon-1024x1024@1x.png"
$target2 = Join-Path $projectRoot "ios_templates\AppIcon-1024.png"
$target3 = Join-Path $projectRoot "app_store_icon_1024x1024.png"

$bmp.Save($target1, [System.Drawing.Imaging.ImageFormat]::Png)
$bmp.Save($target2, [System.Drawing.Imaging.ImageFormat]::Png)
$bmp.Save($target3, [System.Drawing.Imaging.ImageFormat]::Png)

$g.Dispose()
$bmp.Dispose()
$src.Dispose()

# Create Contents.json for Xcode asset catalog
$json = @'
{
  "images" : [
    {
      "filename" : "App-Icon-1024x1024@1x.png",
      "idiom" : "universal",
      "platform" : "ios",
      "size" : "1024x1024"
    }
  ],
  "info" : {
    "author" : "xcode",
    "version" : 1
  }
}
'@

$contentsPath = Join-Path $iconDir "Contents.json"
Set-Content -Path $contentsPath -Value $json

Write-Output "App Store 1024x1024 Icon successfully created at $target3!"
