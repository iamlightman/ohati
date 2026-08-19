$currDir = "c:\xampp\htdocs\c eye q portfolio\portfolio\demo\a ohati app"
$backDir = "C:\Users\DELL\Videos\ohati app"

$filesToCompare = @(
    "api.php",
    "db.php",
    "debug_login.php",
    "login.php",
    "style.css",
    "vendor-dash.php",
    "js\auth.js",
    "js\chat.js",
    "js\jobs.js",
    "js\screens.js"
)

foreach ($f in $filesToCompare) {
    $bf = Join-Path $backDir $f
    $cf = Join-Path $currDir $f
    Write-Host "`n=========================================="
    Write-Host "COMPARING: $f"
    Write-Host "Backup Size: $((Get-Item $bf).Length) | Current Size: $((Get-Item $cf).Length)"
    Write-Host "=========================================="
    
    # Run fc or compare-object lines
    $diff = Compare-Object (Get-Content $bf) (Get-Content $cf) -SyncWindow 5
    Write-Host "Total Line Differences:" $diff.Count
    if ($diff.Count -gt 0 -and $diff.Count -le 60) {
        $diff | Format-Table -AutoSize
    } elseif ($diff.Count -gt 60) {
        Write-Host "First 30 diffs:"
        $diff | Select-Object -First 30 | Format-Table -AutoSize
    }
}
