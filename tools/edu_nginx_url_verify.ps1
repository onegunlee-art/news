# EDU nginx URL redirect verification (phase 1)
# Usage: powershell -File tools/edu_nginx_url_verify.ps1

$ErrorActionPreference = "Continue"
$edu = "https://edu.thegist.co.kr"
$www = "https://www.thegist.co.kr"

function Get-StatusAndLocation([string]$Url) {
    $raw = curl.exe -sSI $Url 2>$null
    $status = ($raw | Select-String -Pattern '^HTTP/' | Select-Object -First 1).Line
    $location = ($raw | Select-String -Pattern '^location:' -CaseSensitive:$false | Select-Object -First 1).Line
    return @{ Status = $status; Location = $location }
}

Write-Host "=== EDU URL redirect verify ===" -ForegroundColor Cyan

$eduRoot = Get-StatusAndLocation "$edu/"
$ok1 = $eduRoot.Status -match '302' -and $eduRoot.Location -match '/edu'
Write-Host ("[{0}] edu / -> {1} {2}" -f $(if ($ok1) { 'OK' } else { 'FAIL' }), $eduRoot.Status, $eduRoot.Location)

$eduAdmin = Get-StatusAndLocation "$edu/admin"
$ok2 = $eduAdmin.Status -match '302' -and $eduAdmin.Location -match '/edu/admin'
Write-Host ("[{0}] edu /admin -> {1} {2}" -f $(if ($ok2) { 'OK' } else { 'FAIL' }), $eduAdmin.Status, $eduAdmin.Location)

$wwwRoot = Get-StatusAndLocation "$www/"
$ok3 = $wwwRoot.Status -match '200' -and $wwwRoot.Location -eq $null
Write-Host ("[{0}] www / -> {1} (no redirect)" -f $(if ($ok3) { 'OK' } else { 'FAIL' }), $wwwRoot.Status)

$wwwAdmin = Get-StatusAndLocation "$www/admin"
$ok4 = $wwwAdmin.Status -match '200' -and $wwwAdmin.Location -eq $null
Write-Host ("[{0}] www /admin -> {1} (no redirect)" -f $(if ($ok4) { 'OK' } else { 'FAIL' }), $wwwAdmin.Status)

$eduEdu = curl.exe -sS -o NUL -w "%{http_code}" "$edu/edu"
Write-Host ("[{0}] edu /edu -> HTTP {1}" -f $(if ($eduEdu -eq '200') { 'OK' } else { 'FAIL' }), $eduEdu)

if ($ok1 -and $ok2 -and $ok3 -and $ok4 -and ($eduEdu -eq '200')) {
    Write-Host "`nAll checks passed." -ForegroundColor Green
    exit 0
}
Write-Host "`nSome checks failed." -ForegroundColor Red
exit 1
