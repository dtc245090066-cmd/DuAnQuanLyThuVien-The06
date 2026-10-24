$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
function Check($ok, $message) { if (!$ok) { throw $message }; Write-Host "PASS: $message" }
$r = Invoke-WebRequest 'http://localhost:8080/health' -UseBasicParsing -TimeoutSec 15
Check ($r.StatusCode -eq 200) 'Nginx -> PHP -> MySQL health'
foreach ($header in @('X-Content-Type-Options','X-Frame-Options','Content-Security-Policy','Referrer-Policy','Permissions-Policy')) {
    Check ([bool]$r.Headers[$header]) "Security header $header"
}
& "$PSScriptRoot/smoke.ps1"

function Prom-Query($query) {
    $url = 'http://localhost:9090/api/v1/query?query=' + [uri]::EscapeDataString($query)
    return (Invoke-RestMethod $url -TimeoutSec 15).data.result
}
Check ((@(Prom-Query 'up')).Count -ge 4) 'Prometheus discovered 4 scrape targets'
$down = @(Prom-Query 'up == 0')
Check ($down.Count -eq 0) 'All Prometheus targets UP'
foreach ($query in @('mysql_up', 'probe_success{job="web"}')) {
    $result = @(Prom-Query $query)
    Check ($result.Count -gt 0 -and $result[0].value[1] -eq '1') "$query = 1"
}
$count = @(Prom-Query 'count(container_memory_working_set_bytes{image!=""})')
Check ($count.Count -gt 0 -and [double]$count[0].value[1] -ge 4) 'Real container memory series present'
$grafana = Invoke-RestMethod 'http://localhost:3000/api/health' -TimeoutSec 15
Check ($grafana.database -eq 'ok') 'Grafana database healthy'
$values = @{}
foreach ($line in Get-Content .env) {
    if ($line -match '^([A-Z_]+)=(.*)$') { $values[$matches[1]] = $matches[2] }
}
$basic = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('admin:' + $values.GRAFANA_ADMIN_PASSWORD))
$headers = @{Authorization="Basic $basic"}
$dash = Invoke-RestMethod 'http://localhost:3000/api/dashboards/uid/student-management' -Headers $headers -TimeoutSec 15
Check ($dash.dashboard.panels.Count -ge 8) 'Grafana dashboard provisioned with 8 panels'
foreach ($uid in @('prometheus','loki')) {
    $ds = Invoke-RestMethod "http://localhost:3000/api/datasources/uid/$uid/health" -Headers $headers -TimeoutSec 15
    Check ($ds.status -eq 'OK') "Grafana datasource $uid healthy"
}
try { Invoke-WebRequest 'http://localhost:8080/?page=khong-ton-tai' -UseBasicParsing -TimeoutSec 10 | Out-Null } catch {
    if ([int]$_.Exception.Response.StatusCode -ne 404) { throw }
}
$queries = @(
    '{project="student-management",service=~"nginx|web"}',
    '{project="student-management",service="nginx"} | json | __error__="" | status >= 400',
    'sum by (status) (rate({project="student-management",service="nginx"} | json | __error__="" [5m]))'
)
foreach ($q in $queries) {
    $result = $null
    for ($attempt=0; $attempt -lt 12; $attempt++) {
        $url = 'http://localhost:3100/loki/api/v1/query_range?limit=20&since=1h&query=' + [uri]::EscapeDataString($q)
        $result = Invoke-RestMethod $url -TimeoutSec 15
        if ($result.data.result.Count -gt 0) { break }
        Start-Sleep -Seconds 3
    }
    Check ($result.status -eq 'success' -and $result.data.result.Count -gt 0) "LogQL returns data: $q"
}
$tracked = @(git ls-files .env .secrets)
Check ($tracked.Count -eq 0) 'Secrets excluded from Git'
Write-Host 'ALL CHECKS PASSED'
