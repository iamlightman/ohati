Add-Type -AssemblyName System.Drawing
$items = Get-ChildItem -Path "sizes" -Recurse -Filter "*.png"
foreach ($item in $items) {
    $img = [System.Drawing.Image]::FromFile($item.FullName)
    [PSCustomObject]@{
        Filename    = $item.Name
        Dimensions  = "$($img.Width) x $($img.Height)"
        PixelFormat = $img.PixelFormat.ToString()
        Folder      = $item.Directory.Name
    }
    $img.Dispose()
}
