# Import line models from Windows (e.g. c:\cloudflared\) into public/assets/lines/
# Usage: .\tools\import-line-models.ps1 -Source "C:\cloudflared"
param(
    [string]$Source = ".\import\line-models"
)

$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Dest = Join-Path $Root "public\assets\lines"
New-Item -ItemType Directory -Force -Path $Source, $Dest | Out-Null

$map = @{
    "model 21" = 21; "model 22" = 22; "model 23" = 23; "model 24" = 24
    "model 25" = 25; "model 26" = 26; "model27" = 27; "model 28" = 28
    "model 29" = 29; "model 30" = 30; "model31" = 31; "model 32" = 32
    "model 33" = 33; "model 34" = 34; "model 35" = 35; "model 36" = 36
    "model 37" = 37; "model 38" = 38
}

$count = 0
foreach ($entry in $map.GetEnumerator()) {
    $num = $entry.Value
    $destFile = Join-Path $Dest ("type_{0}.svg" -f $num)
    $bases = @(
        $entry.Key,
        ("model{0}" -f $num),
        ("model_{0}" -f $num),
        ("type_{0}" -f $num)
    )
    $done = $false
    foreach ($base in $bases) {
        $svg = Join-Path $Source ($base + ".svg")
        $ai = Join-Path $Source ($base + ".ai")
        if (Test-Path $svg) {
            Copy-Item -Force $svg $destFile
            Write-Host "OK copied $svg -> type_$num.svg"
            $count++
            $done = $true
            break
        }
        if (Test-Path $ai) {
            $ink = Get-Command inkscape -ErrorAction SilentlyContinue
            if ($ink) {
                & inkscape $ai --export-filename=$destFile --export-type=svg
                if (Test-Path $destFile) {
                    Write-Host "OK converted $ai -> type_$num.svg"
                    $count++
                    $done = $true
                    break
                }
            }
        }
    }
    if (-not $done) {
        Write-Host "SKIP type_$num"
    }
}

Write-Host "Imported $count line model(s) into $Dest"
