param([string]$OutputDirectory = '')
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
if (-not $OutputDirectory) { $OutputDirectory = Join-Path $repoRoot 'dist' }
$outputPath = [IO.Path]::GetFullPath($OutputDirectory)
if (Test-Path -LiteralPath $outputPath) {
    if (@(Get-ChildItem -LiteralPath $outputPath -Force).Count -gt 0) { throw 'Output directory must be empty; historical packages are never overwritten.' }
} else { New-Item -ItemType Directory -Path $outputPath | Out-Null }
Add-Type -AssemblyName System.IO.Compression.FileSystem
$packages = @(
    @{ Directory = Join-Path $repoRoot 'plugins/mao-tk-zibll-toolkit'; File = 'mao-tk-zibll-toolkit-1.20.0.zip' },
    @{ Directory = Join-Path $repoRoot 'skills/maotk-toolkit-articles'; File = 'maotk-toolkit-articles-1.3.0.zip' }
)
$checksums = @()
foreach ($package in $packages) {
    $zipPath = Join-Path $outputPath $package.File
    [IO.Compression.ZipFile]::CreateFromDirectory($package.Directory, $zipPath, [IO.Compression.CompressionLevel]::Optimal, $true)
    $hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $checksums += "$hash  $($package.File)"
}
[IO.File]::WriteAllText((Join-Path $outputPath 'SHA256SUMS.txt'), ($checksums -join "`n") + "`n", [Text.UTF8Encoding]::new($false))
$checksums
