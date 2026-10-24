$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if (Test-Path .env) { Write-Host '.env already exists; kept unchanged.'; exit 0 }
function New-Password {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
}
$content = [IO.File]::ReadAllText((Join-Path (Get-Location) '.env.example'))
$content = $content.Replace('CHANGE_ME_RANDOM_APP_PASSWORD', (New-Password)).Replace('CHANGE_ME_RANDOM_ROOT_PASSWORD', (New-Password))
[IO.File]::WriteAllText((Join-Path (Get-Location) '.env'), $content, (New-Object System.Text.UTF8Encoding($false)))
Write-Host 'Created .env with two different random passwords. Do not upload .env.'
