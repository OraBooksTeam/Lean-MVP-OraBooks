$log = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) 'merge-audit.log'
$src = "\\10.124.1.254\Jahid_ Shared_Folder\OraBooks - WPMU Frontend Basic Accounting"
$dest = "\\10.124.1.254\Jahid_ Shared_Folder\Project Share Folder\Lean MVP OraBooks\OraBooks Lean MVP\accounting"
"Audit $(Get-Date)" | Out-File $log
function Get-RelFiles($root, $exclude) {
    Get-ChildItem $root -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($root.Length).TrimStart('\')
        if ($exclude -contains $rel) { return }
        [PSCustomObject]@{ Rel = $rel; Hash = (Get-FileHash $_.FullName -Algorithm SHA256).Hash; Full = $_.FullName }
    } | Where-Object { $_ }
}
$skip = @('OraBooks - WPMU febacc.php')
$srcMap = @{}
Get-RelFiles $src $skip | ForEach-Object { $srcMap[$_.Rel] = $_ }
$destMap = @{}
Get-RelFiles $dest @() | ForEach-Object { $destMap[$_.Rel] = $_ }
$missing = $srcMap.Keys | Where-Object { -not $destMap.ContainsKey($_) } | Sort-Object
$extra = $destMap.Keys | Where-Object { -not $srcMap.ContainsKey($_) } | Sort-Object
$diffHash = @()
foreach ($k in ($srcMap.Keys | Where-Object { $destMap.ContainsKey($_) })) {
    if ($srcMap[$k].Hash -ne $destMap[$k].Hash) { $diffHash += $k }
}
"Source: $($srcMap.Count) files" | Out-File $log -Append
"Merged: $($destMap.Count) files" | Out-File $log -Append
"Missing in merged: $($missing.Count)" | Out-File $log -Append
$missing | Out-File $log -Append
"Extra in merged: $($extra.Count)" | Out-File $log -Append
$extra | Out-File $log -Append
"Hash differs: $($diffHash.Count)" | Out-File $log -Append
$diffHash | Out-File $log -Append
