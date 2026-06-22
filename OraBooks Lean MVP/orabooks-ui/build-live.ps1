# Build OraBooks React UI into ../assets/react for LIVE WordPress plugin.
# Double-click build-live.cmd or run: powershell -File build-live.ps1
$ErrorActionPreference = 'Stop'

$uiRoot = $PSScriptRoot
$shareRoot = Split-Path $uiRoot -Parent
Write-Host 'Building OraBooks UI for LIVE deploy...' -ForegroundColor Cyan
Write-Host "Output: $shareRoot\assets\react\" -ForegroundColor Gray

function Enter-BuildDirectory {
    $drive = 'O'
    $mapped = net use $drive`: 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) {
        net use $drive`: $uiRoot /persistent:no | Out-Null
    }
    Set-Location "$drive`:"
}

Enter-BuildDirectory
try {
    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "npm run build failed with exit code $LASTEXITCODE"
    }
    Write-Host ''
    Write-Host 'SUCCESS - Live plugin assets updated.' -ForegroundColor Green
    Write-Host 'Hard refresh live site (Ctrl+F5) then open /classification-test' -ForegroundColor Yellow
} finally {
    net use O: /delete /y 2>$null | Out-Null
}
