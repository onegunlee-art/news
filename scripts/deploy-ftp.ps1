# ===========================================
# News Context - dothome FTP Deploy Script
# ===========================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " News Context - dothome FTP Deploy" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# FTP Settings
$ftpServer = "ftp.dothome.co.kr"
$ftpUser = "ailand"
$ftpRemotePath = "/html"

# Get project root
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptPath

Write-Host "[INFO] Project Root: $projectRoot" -ForegroundColor Gray

# Check if FTP password is provided
$ftpPassword = Read-Host "FTP Password for '$ftpUser'" -AsSecureString
$ftpPasswordPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($ftpPassword)
)

if ([string]::IsNullOrEmpty($ftpPasswordPlain)) {
    Write-Host "[ERROR] FTP password is required!" -ForegroundColor Red
    exit 1
}

# Create FTP session
Write-Host ""
Write-Host "[1/5] Connecting to FTP server..." -ForegroundColor Yellow

try {
    # Use WebClient for FTP upload
    $webclient = New-Object System.Net.WebClient
    $webclient.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPasswordPlain)

    Write-Host "[OK] FTP connection ready" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] FTP connection failed: $_" -ForegroundColor Red
    exit 1
}

# Function to upload file
function Upload-File {
    param (
        [string]$LocalPath,
        [string]$RemotePath
    )
    
    try {
        $uri = "ftp://$ftpServer$RemotePath"
        $webclient.UploadFile($uri, $LocalPath)
        Write-Host "  [OK] $RemotePath" -ForegroundColor DarkGray
    } catch {
        Write-Host "  [FAIL] $RemotePath - $_" -ForegroundColor Red
    }
}

# Function to upload directory
function Upload-Directory {
    param (
        [string]$LocalDir,
        [string]$RemoteDir
    )
    
    # Get all files in directory
    $files = Get-ChildItem -Path $LocalDir -File -Recurse
    
    foreach ($file in $files) {
        $relativePath = $file.FullName.Substring($LocalDir.Length)
        $remotePath = "$RemoteDir$relativePath" -replace '\\', '/'
        Upload-File -LocalPath $file.FullName -RemotePath $remotePath
    }
}

Write-Host ""
Write-Host "[2/5] Uploading public files (HTML, CSS, JS, Assets)..." -ForegroundColor Yellow

$publicPath = Join-Path $projectRoot "public"
Upload-Directory -LocalDir $publicPath -RemoteDir $ftpRemotePath

Write-Host ""
Write-Host "[3/5] Uploading backend source..." -ForegroundColor Yellow

$srcPath = Join-Path $projectRoot "src"
if (Test-Path $srcPath) {
    Upload-Directory -LocalDir $srcPath -RemoteDir "/src"
}

Write-Host ""
Write-Host "[4/5] Uploading config files..." -ForegroundColor Yellow

$configPath = Join-Path $projectRoot "config"
if (Test-Path $configPath) {
    Upload-Directory -LocalDir $configPath -RemoteDir "/config"
}

Write-Host ""
Write-Host "[5/5] Creating storage directories..." -ForegroundColor Yellow

# Create placeholder files for storage
$storageCachePath = Join-Path $projectRoot "storage\cache\.gitkeep"
$storageLogsPath = Join-Path $projectRoot "storage\logs\.gitkeep"

if (Test-Path $storageCachePath) {
    Upload-File -LocalPath $storageCachePath -RemotePath "/storage/cache/.gitkeep"
}
if (Test-Path $storageLogsPath) {
    Upload-File -LocalPath $storageLogsPath -RemotePath "/storage/logs/.gitkeep"
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " Deployment Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Your site is now available at:" -ForegroundColor White
Write-Host "  https://ailand.dothome.co.kr" -ForegroundColor Cyan
Write-Host ""
Write-Host "Admin panel:" -ForegroundColor White
Write-Host "  https://ailand.dothome.co.kr/myadmin" -ForegroundColor Cyan
Write-Host ""
