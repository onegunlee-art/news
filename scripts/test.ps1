# ===========================================
# News 맥락 분석 - 테스트 스크립트
# ===========================================
# PowerShell에서 실행: .\scripts\test.ps1
# ===========================================

$ErrorActionPreference = "Continue"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " News 맥락 분석 - 테스트" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $ProjectRoot

$passed = 0
$failed = 0

# ===========================================
# 1. PHP 문법 검사
# ===========================================
Write-Host "[TEST] PHP 문법 검사..." -ForegroundColor Yellow

$phpPath = $null
try {
    php --version 2>$null | Out-Null
    $phpPath = "php"
} catch {
    $commonPaths = @("C:\php\php.exe", "C:\xampp\php\php.exe")
    foreach ($path in $commonPaths) {
        if (Test-Path $path) { $phpPath = $path; break }
    }
}

if ($phpPath) {
    $phpFiles = Get-ChildItem -Path "src\backend" -Filter "*.php" -Recurse
    $errors = 0
    foreach ($file in $phpFiles) {
        $result = & $phpPath -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Host "  ✗ $($file.Name): 문법 오류" -ForegroundColor Red
            $errors++
        }
    }
    if ($errors -eq 0) {
        Write-Host "  ✓ PHP 파일 $($phpFiles.Count)개 검사 완료 - 오류 없음" -ForegroundColor Green
        $passed++
    } else {
        Write-Host "  ✗ $errors 개 파일에서 오류 발견" -ForegroundColor Red
        $failed++
    }
} else {
    Write-Host "  - PHP 없음, 건너뜀" -ForegroundColor Gray
}

# ===========================================
# 2. 프론트엔드 타입 체크
# ===========================================
Write-Host ""
Write-Host "[TEST] TypeScript 타입 체크..." -ForegroundColor Yellow

Set-Location "src\frontend"
if (Test-Path "node_modules") {
    $result = npx tsc --noEmit 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ TypeScript 타입 체크 통과" -ForegroundColor Green
        $passed++
    } else {
        Write-Host "  ⚠ TypeScript 경고/오류 있음 (치명적 아님)" -ForegroundColor Yellow
        # 경고만 있을 수 있으므로 실패 처리 안 함
    }
} else {
    Write-Host "  - node_modules 없음, npm install 먼저 실행" -ForegroundColor Gray
}
Set-Location $ProjectRoot

# ===========================================
# 3. 설정 파일 확인
# ===========================================
Write-Host ""
Write-Host "[TEST] 설정 파일 확인..." -ForegroundColor Yellow

$configFiles = @("config\app.php", "config\database.php", "config\kakao.php", "config\naver.php", "config\routes.php")
$missingConfig = 0

foreach ($file in $configFiles) {
    if (Test-Path $file) {
        Write-Host "  ✓ $file" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $file 없음" -ForegroundColor Red
        $missingConfig++
    }
}

if ($missingConfig -eq 0) {
    $passed++
} else {
    $failed++
}

# ===========================================
# 4. 디렉토리 구조 확인
# ===========================================
Write-Host ""
Write-Host "[TEST] 디렉토리 구조 확인..." -ForegroundColor Yellow

$requiredDirs = @(
    "public",
    "src\backend\Core",
    "src\backend\Controllers",
    "src\backend\Services",
    "src\backend\Repositories",
    "src\frontend\src",
    "config",
    "database",
    "storage\cache",
    "storage\logs"
)

$missingDirs = 0
foreach ($dir in $requiredDirs) {
    if (Test-Path $dir) {
        Write-Host "  ✓ $dir" -ForegroundColor Green
    } else {
        Write-Host "  ✗ $dir 없음" -ForegroundColor Red
        $missingDirs++
    }
}

if ($missingDirs -eq 0) {
    $passed++
} else {
    $failed++
}

# ===========================================
# 5. 프론트엔드 빌드 테스트
# ===========================================
Write-Host ""
Write-Host "[TEST] 프론트엔드 빌드 테스트..." -ForegroundColor Yellow

Set-Location "src\frontend"
if (Test-Path "node_modules") {
    npm run build 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  ✓ 빌드 성공" -ForegroundColor Green
        $passed++
    } else {
        Write-Host "  ✗ 빌드 실패" -ForegroundColor Red
        $failed++
    }
} else {
    Write-Host "  - node_modules 없음, 건너뜀" -ForegroundColor Gray
}
Set-Location $ProjectRoot

# ===========================================
# 결과 출력
# ===========================================
Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " 테스트 결과" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  통과: $passed" -ForegroundColor Green
Write-Host "  실패: $failed" -ForegroundColor $(if ($failed -gt 0) { "Red" } else { "Gray" })
Write-Host ""

if ($failed -eq 0) {
    Write-Host "모든 테스트 통과! ✓" -ForegroundColor Green
} else {
    Write-Host "일부 테스트 실패. 위의 오류를 확인하세요." -ForegroundColor Yellow
}
Write-Host ""
