# ===========================================
# PHP 자동 설치 스크립트 (Windows)
# ===========================================
# 관리자 권한 PowerShell에서 실행: .\scripts\install-php.ps1
# ===========================================

$ErrorActionPreference = "Stop"

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " PHP 8.4 설치 (Windows)" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# 관리자 권한 확인
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "⚠ 이 스크립트는 관리자 권한이 필요합니다!" -ForegroundColor Yellow
    Write-Host "PowerShell을 관리자 권한으로 다시 실행해주세요." -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Enter를 눌러 종료"
    exit 1
}

$phpDir = "C:\php"
$phpVersion = "8.4.3"
$phpZip = "php-$phpVersion-Win32-vs17-x64.zip"
$phpUrl = "https://windows.php.net/downloads/releases/$phpZip"

# 이미 설치 확인
if (Test-Path "$phpDir\php.exe") {
    $version = & "$phpDir\php.exe" --version | Select-Object -First 1
    Write-Host "PHP가 이미 설치되어 있습니다: $version" -ForegroundColor Yellow
    $continue = Read-Host "다시 설치하시겠습니까? (y/N)"
    if ($continue -ne "y") {
        exit 0
    }
}

# 1. 디렉토리 생성
Write-Host ""
Write-Host "[1/5] 디렉토리 생성..." -ForegroundColor Yellow
if (-not (Test-Path $phpDir)) {
    New-Item -ItemType Directory -Path $phpDir -Force | Out-Null
}
Write-Host "  ✓ $phpDir" -ForegroundColor Green

# 2. PHP 다운로드
Write-Host ""
Write-Host "[2/5] PHP $phpVersion 다운로드 중..." -ForegroundColor Yellow
$tempFile = "$env:TEMP\$phpZip"

try {
    Invoke-WebRequest -Uri $phpUrl -OutFile $tempFile -UseBasicParsing
    Write-Host "  ✓ 다운로드 완료" -ForegroundColor Green
} catch {
    Write-Host "  ✗ 다운로드 실패: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "수동 설치:" -ForegroundColor Yellow
    Write-Host "1. https://windows.php.net/download 방문" -ForegroundColor Gray
    Write-Host "2. PHP 8.4 VS17 x64 Thread Safe 다운로드" -ForegroundColor Gray
    Write-Host "3. C:\php 에 압축 해제" -ForegroundColor Gray
    exit 1
}

# 3. 압축 해제
Write-Host ""
Write-Host "[3/5] 압축 해제 중..." -ForegroundColor Yellow
Expand-Archive -Path $tempFile -DestinationPath $phpDir -Force
Remove-Item $tempFile -Force
Write-Host "  ✓ 압축 해제 완료" -ForegroundColor Green

# 4. php.ini 설정
Write-Host ""
Write-Host "[4/5] php.ini 설정 중..." -ForegroundColor Yellow
$phpIni = "$phpDir\php.ini"
if (-not (Test-Path $phpIni)) {
    Copy-Item "$phpDir\php.ini-development" $phpIni
}

# 필요한 확장 활성화
$extensions = @(
    "extension=curl",
    "extension=mbstring",
    "extension=openssl",
    "extension=pdo_mysql",
    "extension=fileinfo"
)

$content = Get-Content $phpIni
foreach ($ext in $extensions) {
    $extName = $ext -replace "extension=", ""
    $content = $content -replace ";extension=$extName", "extension=$extName"
}
$content | Set-Content $phpIni

Write-Host "  ✓ php.ini 설정 완료" -ForegroundColor Green

# 5. PATH 추가
Write-Host ""
Write-Host "[5/5] 환경 변수 설정 중..." -ForegroundColor Yellow
$currentPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
if ($currentPath -notlike "*$phpDir*") {
    [Environment]::SetEnvironmentVariable("Path", "$currentPath;$phpDir", "Machine")
    $env:Path = "$env:Path;$phpDir"
    Write-Host "  ✓ PATH에 추가됨" -ForegroundColor Green
} else {
    Write-Host "  - PATH에 이미 존재" -ForegroundColor Gray
}

# 확인
Write-Host ""
Write-Host "=========================================" -ForegroundColor Green
Write-Host " PHP 설치 완료!" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green
Write-Host ""

& "$phpDir\php.exe" --version

Write-Host ""
Write-Host "새 PowerShell 창에서 'php --version' 명령으로 확인하세요." -ForegroundColor Yellow
Write-Host ""
