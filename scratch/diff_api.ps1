$backApi = Get-Content "scratch/backup_ref/api.php"
$currApi = Get-Content "api.php"

$diff = Compare-Object $backApi $currApi -SyncWindow 5
Write-Host "api.php diff count:" $diff.Count
$diff | Select-Object -First 40 | Format-Table -AutoSize
