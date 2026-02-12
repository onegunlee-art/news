# ===========================================
# News - 배포 스크립트 (빌드 + main 푸시)
# ===========================================
# GitHub Actions가 main 푸시 시 자동 FTP 배포
# 실행: .\scripts\deploy.ps1
# ===========================================

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " News - Deploy (Build + Push to main)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $ProjectRoot

# 1. 프론트엔드 빌드
Write-Host "[1/3] Building frontend..." -ForegroundColor Yellow
Set-Location "src\frontend"
$env:VITE_API_URL = "/api"
npm run build

if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Frontend build failed!" -ForegroundColor Red
    exit 1
}

Write-Host "[OK] Build complete" -ForegroundColor Green
Set-Location $ProjectRoot
Write-Host ""

# 2. Git 상태 확인
Write-Host "[2/3] Checking Git status..." -ForegroundColor Yellow
$status = git status --porcelain
if ([string]::IsNullOrWhiteSpace($status)) {
    Write-Host "[INFO] No changes to commit. Already up to date." -ForegroundColor Gray
    Write-Host ""
    Write-Host "To force deploy, make a small change and run again." -ForegroundColor Gray
    exit 0
}

git status --short
Write-Host ""

# 3. 커밋 & 푸시
Write-Host "[3/3] Commit and push to main..." -ForegroundColor Yellow
git add -A
git commit -m "Deploy: 지스터 독자 통일 (GPT 프롬프트 + 내레이션 정규화)"
git push origin main

if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Push failed!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " Deployment triggered!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "GitHub Actions will build and deploy to FTP." -ForegroundColor White
Write-Host "Check: https://github.com/YOUR_REPO/actions" -ForegroundColor Gray
Write-Host ""
Write-Host "Site: https://www.thegist.co.kr" -ForegroundColor Cyan
Write-Host ""
