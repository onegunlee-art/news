# ===========================================
# News Context Analysis - Start Server
# ===========================================
# Run in PowerShell: .\scripts\start.ps1
# ===========================================

$ErrorActionPreference = "Continue"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " News Context Analysis - Start Server" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $ProjectRoot

# Find PHP path
$phpPath = $null
try {
    php --version 2>$null | Out-Null
    $phpPath = "php"
} catch {
    $commonPaths = @(
        "C:\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php-8.2*\php.exe",
        "C:\laragon\bin\php\php-8.1*\php.exe"
    )
    foreach ($path in $commonPaths) {
        $resolved = Resolve-Path $path -ErrorAction SilentlyContinue
        if ($resolved) {
            $phpPath = $resolved.Path
            break
        }
    }
}

Write-Host "[SELECT] Choose server to run:" -ForegroundColor Yellow
Write-Host ""
Write-Host "  1. Frontend only (React Dev Server - port 5173)" -ForegroundColor White
Write-Host "  2. Backend only (PHP Server - port 8000)" -ForegroundColor White
Write-Host "  3. Both servers (Recommended)" -ForegroundColor Green
Write-Host "  4. Build frontend then PHP server only" -ForegroundColor White
Write-Host ""

$choice = Read-Host "Select (1-4)"

switch ($choice) {
    "1" {
        Write-Host ""
        Write-Host "Starting frontend dev server..." -ForegroundColor Cyan
        Write-Host "-> http://localhost:5173" -ForegroundColor Green
        Write-Host ""
        Write-Host "Press Ctrl+C to stop." -ForegroundColor Gray
        Write-Host ""
        Set-Location "src\frontend"
        npm run dev
    }
    "2" {
        if (-not $phpPath) {
            Write-Host "PHP is not installed!" -ForegroundColor Red
            exit 1
        }
        Write-Host ""
        Write-Host "Starting PHP backend server..." -ForegroundColor Cyan
        Write-Host "-> http://localhost:8000" -ForegroundColor Green
        Write-Host "-> API: http://localhost:8000/api/health" -ForegroundColor Green
        Write-Host ""
        Write-Host "Press Ctrl+C to stop." -ForegroundColor Gray
        Write-Host ""
        Set-Location "public"
        & $phpPath -S localhost:8000
    }
    "3" {
        Write-Host ""
        Write-Host "Starting both servers..." -ForegroundColor Cyan
        Write-Host ""
        
        # Start PHP server in background
        if ($phpPath) {
            Write-Host "Starting PHP server (port 8000)..." -ForegroundColor Yellow
            $phpProcess = Start-Process -FilePath $phpPath -ArgumentList "-S", "localhost:8000" -WorkingDirectory "$ProjectRoot\public" -PassThru -WindowStyle Minimized
            Write-Host "  OK PHP server started (PID: $($phpProcess.Id))" -ForegroundColor Green
            Write-Host "  -> http://localhost:8000" -ForegroundColor Gray
        } else {
            Write-Host "  ! PHP not found - skipping backend" -ForegroundColor Yellow
        }
        
        Write-Host ""
        Write-Host "Starting frontend server (port 5173)..." -ForegroundColor Yellow
        Write-Host "  -> http://localhost:5173" -ForegroundColor Gray
        Write-Host ""
        Write-Host "=========================================" -ForegroundColor Green
        Write-Host " Open http://localhost:5173 in browser" -ForegroundColor Green
        Write-Host "=========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "Press Ctrl+C to stop." -ForegroundColor Gray
        Write-Host "(PHP server will also stop)" -ForegroundColor Gray
        Write-Host ""
        
        # Handle Ctrl+C
        try {
            Set-Location "src\frontend"
            npm run dev
        } finally {
            if ($phpProcess -and -not $phpProcess.HasExited) {
                Write-Host ""
                Write-Host "Stopping PHP server..." -ForegroundColor Yellow
                Stop-Process -Id $phpProcess.Id -Force -ErrorAction SilentlyContinue
            }
        }
    }
    "4" {
        if (-not $phpPath) {
            Write-Host "PHP is not installed!" -ForegroundColor Red
            exit 1
        }
        
        Write-Host ""
        Write-Host "Building frontend..." -ForegroundColor Yellow
        Set-Location "src\frontend"
        npm run build
        
        if ($LASTEXITCODE -ne 0) {
            Write-Host "Build failed!" -ForegroundColor Red
            exit 1
        }
        
        Write-Host ""
        Write-Host "OK Build complete" -ForegroundColor Green
        Write-Host ""
        Write-Host "Starting PHP server..." -ForegroundColor Cyan
        Write-Host "-> http://localhost:8000" -ForegroundColor Green
        Write-Host ""
        Write-Host "Press Ctrl+C to stop." -ForegroundColor Gray
        Write-Host ""
        
        Set-Location "$ProjectRoot\public"
        & $phpPath -S localhost:8000
    }
    default {
        Write-Host "Invalid selection." -ForegroundColor Red
    }
}
