# Build OraBooks React UI into ../assets/react (LIVE WordPress plugin folder).
# Double-click build-live.cmd after any orabooks-ui change.
$ErrorActionPreference = 'Stop'

$uiRoot = $PSScriptRoot
$outDir = Join-Path (Split-Path $uiRoot -Parent) 'assets\react'

Write-Host 'Building OraBooks UI for LIVE...' -ForegroundColor Cyan
Write-Host "Source: $uiRoot"
Write-Host "Output: $outDir"

$cmd = "pushd `"$uiRoot`" && npm run build && popd"
cmd.exe /c $cmd

if ($LASTEXITCODE -ne 0) {
    throw "npm run build failed with exit code $LASTEXITCODE"
}

Write-Host ''
Write-Host 'SUCCESS - assets/react updated on live plugin folder.' -ForegroundColor Green
Write-Host 'Hard refresh live site (Ctrl+F5), then open /classification-test' -ForegroundColor Yellow
