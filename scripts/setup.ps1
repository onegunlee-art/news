# ===========================================
# News Context Analysis - Local Dev Setup
# ===========================================
# Run in PowerShell: .\scripts\setup.ps1
# ===========================================

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " News Context Analysis - Setup" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# Move to project root
Set-Location $ProjectRoot
Write-Host "[INFO] Project path: $ProjectRoot" -ForegroundColor Gray

# ===========================================
# 1. Check Node.js
# ===========================================
Write-Host ""
Write-Host "[1/5] Checking Node.js..." -ForegroundColor Yellow

try {
    $nodeVersion = node --version 2>$null
    Write-Host "  OK Node.js installed: $nodeVersion" -ForegroundColor Green
} catch {
    Write-Host "  X Node.js not found!" -ForegroundColor Red
    Write-Host "  -> Install from https://nodejs.org (LTS version)" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter after installing Node.js"
}

# Check npm
try {
    $npmVersion = npm --version 2>$null
    Write-Host "  OK npm installed: v$npmVersion" -ForegroundColor Green
} catch {
    Write-Host "  X npm not found" -ForegroundColor Red
    exit 1
}

# ===========================================
# 2. Check PHP
# ===========================================
Write-Host ""
Write-Host "[2/5] Checking PHP..." -ForegroundColor Yellow

$phpPath = $null
try {
    $phpVersion = php --version 2>$null | Select-Object -First 1
    Write-Host "  OK PHP installed: $phpVersion" -ForegroundColor Green
    $phpPath = "php"
} catch {
    # Check common PHP paths
    $commonPaths = @(
        "C:\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php-8.2*\php.exe",
        "C:\laragon\bin\php\php-8.1*\php.exe",
        "C:\wamp64\bin\php\php8*\php.exe"
    )
    
    foreach ($path in $commonPaths) {
        $resolved = Resolve-Path $path -ErrorAction SilentlyContinue
        if ($resolved) {
            $phpPath = $resolved.Path
            Write-Host "  OK PHP found: $phpPath" -ForegroundColor Green
            break
        }
    }
    
    if (-not $phpPath) {
        Write-Host "  X PHP not installed!" -ForegroundColor Red
        Write-Host ""
        Write-Host "  Install one of these:" -ForegroundColor Yellow
        Write-Host "  1. XAMPP: https://www.apachefriends.org" -ForegroundColor Gray
        Write-Host "  2. Laragon: https://laragon.org" -ForegroundColor Gray
        Write-Host "  3. PHP: https://windows.php.net/download" -ForegroundColor Gray
        Write-Host ""
        Write-Host "  (You can continue without PHP for frontend-only testing)" -ForegroundColor Yellow
    }
}

# ===========================================
# 3. Create config files
# ===========================================
Write-Host ""
Write-Host "[3/5] Creating config files..." -ForegroundColor Yellow

# database.php
if (-not (Test-Path "config\database.php")) {
    Copy-Item "config\database.example.php" "config\database.php"
    Write-Host "  OK config/database.php created" -ForegroundColor Green
} else {
    Write-Host "  - config/database.php exists" -ForegroundColor Gray
}

# kakao.php
if (-not (Test-Path "config\kakao.php")) {
    Copy-Item "config\kakao.example.php" "config\kakao.php"
    Write-Host "  OK config/kakao.php created" -ForegroundColor Green
} else {
    Write-Host "  - config/kakao.php exists" -ForegroundColor Gray
}

# Create storage directories
if (-not (Test-Path "storage\cache")) {
    New-Item -ItemType Directory -Path "storage\cache" -Force | Out-Null
}
if (-not (Test-Path "storage\logs")) {
    New-Item -ItemType Directory -Path "storage\logs" -Force | Out-Null
}
Write-Host "  OK storage directories verified" -ForegroundColor Green

# ===========================================
# 4. Install frontend dependencies
# ===========================================
Write-Host ""
Write-Host "[4/5] Installing frontend dependencies..." -ForegroundColor Yellow

Set-Location "src\frontend"

if (-not (Test-Path "node_modules")) {
    Write-Host "  Running npm install... (this may take a while)" -ForegroundColor Gray
    npm install 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  OK npm packages installed" -ForegroundColor Green
    } else {
        Write-Host "  X npm install failed" -ForegroundColor Red
    }
} else {
    Write-Host "  - node_modules already exists" -ForegroundColor Gray
}

Set-Location $ProjectRoot

# ===========================================
# 5. Done
# ===========================================
Write-Host ""
Write-Host "=========================================" -ForegroundColor Green
Write-Host " Setup Complete!" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Run the server with:" -ForegroundColor Yellow
Write-Host ""
Write-Host "  .\scripts\start.ps1" -ForegroundColor Cyan
Write-Host ""
Write-Host "Or manually:" -ForegroundColor Gray
Write-Host "  Frontend: cd src\frontend; npm run dev" -ForegroundColor Gray
Write-Host "  Backend:  cd public; php -S localhost:8000" -ForegroundColor Gray
Write-Host ""
