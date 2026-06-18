$ErrorActionPreference = 'Stop'
$base = Split-Path -Parent $MyInvocation.MyCommand.Path
$lean = Join-Path $base 'OraBooks Lean MVP'
$src = Join-Path $base 'OraBooks - WPMU Frontend Basic Accounting'
$dest = Join-Path $lean 'accounting'
$log = Join-Path $base 'merge-accounting.log'

"Start $(Get-Date)" | Out-File $log
try {
    New-Item -ItemType Directory -Force -Path $dest | Out-Null
    foreach ($d in @('includes','templates','assets','acc_report')) {
        $from = Join-Path $src $d
        $to = Join-Path $dest $d
        if (Test-Path $to) { Remove-Item $to -Recurse -Force }
        Copy-Item -Path $from -Destination $to -Recurse -Force
        "Copied $d" | Out-File $log -Append
    }
    $count = (Get-ChildItem $dest -Recurse -File).Count
    "Done. Files: $count" | Out-File $log -Append
} catch {
    "ERROR: $_" | Out-File $log -Append
    exit 1
}
