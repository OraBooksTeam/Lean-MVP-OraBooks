# Build OraBooks React UI into ../assets/react for LIVE WordPress plugin.
# Run from any folder: double-click or `powershell -File build-live.ps1`
$ErrorActionPreference = 'Stop'

$uiRoot = $PSScriptRoot
Write-Host "Building OraBooks UI for LIVE deploy..." -ForegroundColor Cyan
Write-Host "Output: $uiRoot\..\assets\react\" -ForegroundColor Gray

Push-Location $uiRoot
try {
    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "npm run build failed with exit code $LASTEXITCODE"
    }
    Write-Host ""
    Write-Host "SUCCESS — Live plugin assets updated." -ForegroundColor Green
    Write-Host "Refresh your live site (hard refresh Ctrl+F5) and open /classification-test" -ForegroundColor Yellow
} finally {
    Pop-Location
}
