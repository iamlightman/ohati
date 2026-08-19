$currDir = "c:\xampp\htdocs\c eye q portfolio\portfolio\demo\a ohati app"
$backDir = "C:\Users\DELL\Videos\ohati app"

$backFiles = Get-ChildItem -Path $backDir -Recurse -File | Where-Object { $_.FullName -notmatch '\\(node_modules|\.git|scratch|\.vscode)\\' }
$currFiles = Get-ChildItem -Path $currDir -Recurse -File | Where-Object { $_.FullName -notmatch '\\(node_modules|\.git|scratch|\.vscode)\\' }

Write-Host "=== Total Backup Files (excl node_modules/.git/scratch):" $backFiles.Count
Write-Host "=== Total Current Files (excl node_modules/.git/scratch):" $currFiles.Count

Write-Host "`n--- FILES WITH SIZE DIFFERENCES OR MISSING IN CURRENT ---"
foreach ($bf in $backFiles) {
    $relPath = $bf.FullName.Substring($backDir.Length)
    $cfPath = Join-Path $currDir $relPath
    if (-not (Test-Path $cfPath)) {
        Write-Host "[MISSING IN CURRENT]:" $relPath
    } else {
        $cf = Get-Item $cfPath
        if ($bf.Length -ne $cf.Length) {
            Write-Host ("[SIZE DIFF] {0} | Backup: {1} bytes | Current: {2} bytes" -f $relPath, $bf.Length, $cf.Length)
        }
    }
}

Write-Host "`n--- EXTRA FILES IN CURRENT WORKSPACE (NOT IN BACKUP) ---"
foreach ($cf in $currFiles) {
    $relPath = $cf.FullName.Substring($currDir.Length)
    $bfPath = Join-Path $backDir $relPath
    if (-not (Test-Path $bfPath) -and $relPath -notmatch '^\\(android|ios|www)') {
        Write-Host "[EXTRA IN CURRENT]:" $relPath
    }
}
