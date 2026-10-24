$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if (!(Test-Path .env)) { & "$PSScriptRoot/setup.ps1" }
$envPath = Join-Path (Get-Location) '.env'
$content = [IO.File]::ReadAllText($envPath)
function New-Secret {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
}
foreach ($key in @('GRAFANA_ADMIN_PASSWORD','MYSQL_EXPORTER_PASSWORD')) {
    if ($content -notmatch "(?m)^$key=") { $content = $content.TrimEnd() + [Environment]::NewLine + "$key=$(New-Secret)" + [Environment]::NewLine }
}
[IO.File]::WriteAllText($envPath, $content, (New-Object Text.UTF8Encoding($false)))
$match = [regex]::Match($content, '(?m)^MYSQL_EXPORTER_PASSWORD=([a-f0-9]{64})\r?$')
if (!$match.Success) { throw 'MYSQL_EXPORTER_PASSWORD must be 64 hexadecimal characters.' }
$monitorPassword = $match.Groups[1].Value
New-Item -ItemType Directory -Force .secrets | Out-Null
$cnf = "[client]" + [Environment]::NewLine + "user=metrics" + [Environment]::NewLine + "password=$monitorPassword" + [Environment]::NewLine
[IO.File]::WriteAllText((Join-Path (Get-Location) '.secrets/exporter.cnf'), $cnf, (New-Object Text.UTF8Encoding($false)))
$sql = @"
CREATE USER IF NOT EXISTS 'metrics'@'%' IDENTIFIED BY '$monitorPassword' WITH MAX_USER_CONNECTIONS 3;
ALTER USER 'metrics'@'%' IDENTIFIED BY '$monitorPassword' WITH MAX_USER_CONNECTIONS 3;
GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'metrics'@'%';
GRANT SELECT ON performance_schema.* TO 'metrics'@'%';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'student_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON student_management.* TO 'student_app'@'%';
"@
$sql | docker compose exec -T db sh -c 'export MYSQL_PWD=$MYSQL_ROOT_PASSWORD; exec mysql -uroot'
if ($LASTEXITCODE -ne 0) { throw 'Database setup failed. Start db, wait healthy, then retry.' }
Write-Host 'Monitoring credentials and least-privilege metrics user configured. Passwords are in .env; do not screenshot.'
