# Remove SL-### traceability text from project files without changing logic.
$ErrorActionPreference = 'Stop'

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Root = Split-Path -Parent $ScriptDir
$Parent = Split-Path -Parent $Root
$SearchRoots = @($Root, $Parent)
$Exts = @('.php', '.ts', '.tsx', '.md', '.txt', '.json', '.jsx', '.js', '.css', '.html')
$SkipDirs = @('node_modules', 'vendor', '.git', 'dist', 'build', '.next', 'coverage')

function Test-SkipPath {
    param([string]$FullPath)
    foreach ($d in $SkipDirs) {
        if ($FullPath -match [regex]::Escape([IO.Path]::DirectorySeparatorChar + $d + [IO.Path]::DirectorySeparatorChar) -or
            $FullPath -match [regex]::Escape([IO.Path]::AltDirectorySeparatorChar + $d + [IO.Path]::AltDirectorySeparatorChar)) {
            return $true
        }
    }
    return $false
}

function Clean-SlText {
    param([string]$Text)
    if ($Text -notmatch 'SL-\d+') { return $Text }

    $Text = [regex]::Replace($Text, '\(\s*SL-\d+(?:\s+through\s+SL-\d+)?\s*\)', '', 'IgnoreCase')
    $Text = [regex]::Replace($Text, '\bSL-\d+\s+through\s+SL-\d+\b', '', 'IgnoreCase')
    $Text = [regex]::Replace($Text, '\bSL-\d+\b', '', 'IgnoreCase')

    $Text = [regex]::Replace($Text, '\(\s*\)', '')
    $Text = [regex]::Replace($Text, '[ \t]+([,.;:])', '$1')
    $Text = [regex]::Replace($Text, '[ \t]{2,}', ' ')
    $Text = [regex]::Replace($Text, ' \r?\n', "`n")
    $Text = [regex]::Replace($Text, '\n{3,}', "`n`n")
    return $Text
}

$changed = 0
$remaining = 0

foreach ($base in $SearchRoots) {
    if (-not (Test-Path -LiteralPath $base)) { continue }
    Get-ChildItem -LiteralPath $base -Recurse -File | ForEach-Object {
        $path = $_.FullName
        if (Test-SkipPath $path) { return }
        if ($Exts -notcontains $_.Extension.ToLower()) { return }
        try { $content = [System.IO.File]::ReadAllText($path) } catch { return }
        if ($content -notmatch 'SL-\d+') { return }

        $updated = Clean-SlText $content
        if ($updated -ne $content) {
            [System.IO.File]::WriteAllText($path, $updated, [System.Text.UTF8Encoding]::new($false))
            $script:changed++
        }
    }
}

foreach ($base in $SearchRoots) {
    if (-not (Test-Path -LiteralPath $base)) { continue }
    Get-ChildItem -LiteralPath $base -Recurse -File | ForEach-Object {
        $path = $_.FullName
        if (Test-SkipPath $path) { return }
        if ($Exts -notcontains $_.Extension.ToLower()) { return }
        try {
            $content = [System.IO.File]::ReadAllText($path)
            $matches = [regex]::Matches($content, 'SL-\d+', 'IgnoreCase')
            $script:remaining += $matches.Count
        } catch { }
    }
}

Write-Output "Updated files: $changed"
Write-Output "Remaining SL- references: $remaining"
if ($remaining -gt 0) { exit 1 } else { exit 0 }
