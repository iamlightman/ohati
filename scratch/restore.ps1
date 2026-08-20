$source = "C:\Users\DELL\Videos\ohati app"
$dest = "c:\xampp\htdocs\c eye q portfolio\portfolio\demo\a ohati app"

$items = Get-ChildItem -Path $source -Recurse | Where-Object { 
    $_.FullName -notmatch '\\(android|ios|node_modules|\.git|\.github|scratch)\\' -and
    $_.Name -ne 'android' -and $_.Name -ne 'ios' -and $_.Name -ne 'node_modules' -and $_.Name -ne '.git' -and $_.Name -ne '.github' -and $_.Name -ne 'scratch'
}

Write-Host "Total items to restore from backup:" $items.Count

foreach ($item in $items) {
    $rel = $item.FullName.Substring($source.Length)
    $target = Join-Path $dest $rel
    if ($item.PSIsContainer) {
        if (-not (Test-Path $target)) {
            New-Item -ItemType Directory -Path $target -Force | Out-Null
        }
    } else {
        Copy-Item -Path $item.FullName -Destination $target -Force
        Write-Host "Restored:" $rel
    }
}
Write-Host "Restoration complete."
