# =============================================================================
# verify-assetlinks.ps1
# -----------------------------------------------------------------------------
# Google Play Trusted Web Activity (TWA) Digital Asset Links checker.
#
# When Play Console shows
#   "Domain ownership not verified / Digital Asset Links JSON test failed"
# the cause is almost always one of:
#   1) Server returns text/html (SPA fallback) instead of application/json
#   2) The file is not deployed (404)
#   3) DNS still points to old hosting
#   4) sha256_cert_fingerprints does NOT match the Play "App signing key"
#      (most common when Play App Signing is enabled)
#
# This script auto-checks 1)-3). For 4) pass the expected SHA-256 from
# Play Console -> App integrity -> App signing.
#
# Usage:
#   pwsh ./scripts/verify-assetlinks.ps1
#   pwsh ./scripts/verify-assetlinks.ps1 -ExpectedSha256 "AA:BB:..."
# =============================================================================

[CmdletBinding()]
param(
    [string]$Domain = "www.thegist.co.kr",
    [string]$PackageName = "kr.co.thegist.app",
    [string]$ExpectedSha256
)

$ErrorActionPreference = "Stop"
$script:fail = 0

function Write-Pass([string]$msg) { Write-Host "[PASS] $msg" -ForegroundColor Green }
function Write-Fail([string]$msg) { Write-Host "[FAIL] $msg" -ForegroundColor Red; $script:fail++ }
function Write-Info([string]$msg) { Write-Host "[INFO] $msg" -ForegroundColor Cyan }

Write-Info "Domain : $Domain"
Write-Info "Package: $PackageName"
if ($ExpectedSha256) { Write-Info "Expected SHA-256: $ExpectedSha256" }
Write-Host ""

# -----------------------------------------------------------------------------
# 1) DNS lookup for both www and apex
# -----------------------------------------------------------------------------
Write-Info "1) DNS A records"
$apex = $Domain -replace '^www\.',''
foreach ($hostName in @($Domain, $apex)) {
    try {
        $a = Resolve-DnsName -Type A -Name $hostName -ErrorAction Stop |
             Where-Object { $_.Type -eq 'A' } | Select-Object -First 1
        if ($a) {
            Write-Pass "$hostName -> $($a.IPAddress)"
        } else {
            Write-Fail "$hostName has no A record"
        }
    } catch {
        Write-Fail "$hostName DNS lookup failed: $($_.Exception.Message)"
    }
}
Write-Host ""

# -----------------------------------------------------------------------------
# 2) HTTPS response - status / content-type / body
# -----------------------------------------------------------------------------
$url = "https://$Domain/.well-known/assetlinks.json"
Write-Info "2) GET $url"
$tmp = New-TemporaryFile
$headersTmp = New-TemporaryFile
try {
    $curlArgs = @(
        '-sS', '--ssl-no-revoke', '--max-time', '15',
        '-D', $headersTmp.FullName, '-o', $tmp.FullName,
        '-w', '%{http_code}|%{content_type}',
        $url
    )
    $write = & curl.exe @curlArgs
    $parts = $write -split '\|', 2
    $statusCode = [int]$parts[0]
    $contentType = $parts[1]
    $body = Get-Content $tmp.FullName -Raw -ErrorAction SilentlyContinue

    if ($statusCode -eq 200) {
        Write-Pass "HTTP 200"
    } else {
        Write-Fail "HTTP $statusCode (must be 200)"
    }

    if ($contentType -match 'application/json') {
        Write-Pass "Content-Type: $contentType"
    } else {
        Write-Fail "Content-Type: $contentType (must be application/json). SPA index.html may be returned."
    }

    try {
        $json = $body | ConvertFrom-Json -ErrorAction Stop
        Write-Pass "JSON parsed (statements: $($json.Count))"
    } catch {
        Write-Fail "JSON parse failed: $($_.Exception.Message)"
        if ($body) {
            $head = $body.Substring(0, [Math]::Min(200, $body.Length))
            Write-Host "First 200 chars: $head"
        }
        return
    }

    $found = $false
    $foundFps = @()
    foreach ($s in $json) {
        $tgt = $s.target
        if ($tgt.namespace -eq 'android_app' -and $tgt.package_name -eq $PackageName) {
            $found = $true
            foreach ($fp in $tgt.sha256_cert_fingerprints) { $foundFps += $fp }
        }
    }
    if ($found) {
        Write-Pass "package matched: $PackageName"
        foreach ($fp in $foundFps) {
            Write-Info "  - SHA-256: $fp"
        }
        if ($ExpectedSha256) {
            $norm = $ExpectedSha256.ToUpper()
            $hit = $foundFps | Where-Object { $_.ToUpper() -eq $norm }
            if ($hit) {
                Write-Pass "expected SHA-256 present: $ExpectedSha256"
            } else {
                Write-Fail "expected SHA-256 ($ExpectedSha256) NOT in JSON. Update assetlinks.json."
            }
        }
    } else {
        Write-Fail "package $PackageName not present in JSON"
    }
} finally {
    Remove-Item $tmp -ErrorAction SilentlyContinue
    Remove-Item $headersTmp -ErrorAction SilentlyContinue
}
Write-Host ""

# -----------------------------------------------------------------------------
# 3) Google official Asset Links API
# -----------------------------------------------------------------------------
Write-Info "3) Google Digital Asset Links API"
$apiUrl = "https://digitalassetlinks.googleapis.com/v1/statements:list" +
          "?source.web.site=https://$Domain" +
          "&relation=delegate_permission/common.handle_all_urls&prettyPrint=true"
try {
    $resp = & curl.exe -sS --ssl-no-revoke --max-time 20 $apiUrl
    $api = $resp | ConvertFrom-Json
    if ($api.statements -and $api.statements.Count -gt 0) {
        Write-Pass "Google recognized $($api.statements.Count) statement(s)"
        foreach ($s in $api.statements) {
            $fp = $s.target.androidApp.certificate.sha256Fingerprint
            $pkg = $s.target.androidApp.packageName
            Write-Info "  - $pkg : $fp"
        }
    } else {
        Write-Fail "Google response has no statements. Body: $resp"
    }
} catch {
    Write-Fail "Google API call failed: $($_.Exception.Message)"
}
Write-Host ""

# -----------------------------------------------------------------------------
# Result
# -----------------------------------------------------------------------------
if ($script:fail -eq 0) {
    Write-Host "=== ALL CHECKS PASSED ===" -ForegroundColor Green
    if (-not $ExpectedSha256) {
        Write-Host "Note: pass -ExpectedSha256 from Play Console (App signing key) to verify the fingerprint match." -ForegroundColor Yellow
    }
    exit 0
} else {
    Write-Host ("=== {0} FAILURE(S). Fix [FAIL] items above. ===" -f $script:fail) -ForegroundColor Red
    exit 1
}
