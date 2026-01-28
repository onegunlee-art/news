# ===========================================
# News 맥락 분석 - 원클릭 실행
# ===========================================
# PowerShell에서 실행: .\run.ps1
# ===========================================

$ProjectRoot = $PSScriptRoot

Write-Host ""
Write-Host "=========================================" -ForegroundColor Magenta
Write-Host "   News 맥락 분석 홈페이지" -ForegroundColor Magenta
Write-Host "=========================================" -ForegroundColor Magenta
Write-Host ""
Write-Host "  1. 환경 설정 (setup)" -ForegroundColor White
Write-Host "  2. 서버 실행 (start)" -ForegroundColor White
Write-Host "  3. 테스트 실행 (test)" -ForegroundColor White
Write-Host "  4. PHP 설치 (관리자 필요)" -ForegroundColor White
Write-Host ""

$choice = Read-Host "선택 (1-4)"

switch ($choice) {
    "1" { & "$ProjectRoot\scripts\setup.ps1" }
    "2" { & "$ProjectRoot\scripts\start.ps1" }
    "3" { & "$ProjectRoot\scripts\test.ps1" }
    "4" { & "$ProjectRoot\scripts\install-php.ps1" }
    default { Write-Host "잘못된 선택" -ForegroundColor Red }
}
