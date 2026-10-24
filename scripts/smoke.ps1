$ErrorActionPreference = 'Stop'
$base = 'http://localhost:8080'
$health = Invoke-RestMethod "$base/health"
if ($health.status -ne 'ok' -or $health.database -ne 'ok') { throw 'Health failed' }
$first = Invoke-WebRequest "$base/?page=classes" -SessionVariable browser -UseBasicParsing
$token = [regex]::Match($first.Content, 'name="csrf" value="([a-f0-9]+)"').Groups[1].Value
if (!$token) { throw 'Missing CSRF token' }
$suffix = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
function Save-Row($page, $fields) {
    $fields.csrf = $token
    $result = Invoke-WebRequest "$base/?page=$page" -Method Post -Body $fields -WebSession $browser -UseBasicParsing
    if ($result.Content -match 'class="error"') { throw "CRUD failed: $page" }
    return $result.Content
}
function Find-Id($html, $marker) {
    foreach ($row in [regex]::Matches($html, '<tr><td>(\d+)</td>(.*?)</tr>', 'Singleline')) {
        if ($row.Value.Contains($marker)) { return $row.Groups[1].Value }
    }
    throw "Missing saved row: $marker"
}
$html = Save-Row 'classes' @{id='0';name="TEST$suffix"}
$classId = Find-Id $html "TEST$suffix"
$html = Save-Row 'students' @{id='0';code="T$suffix";name='Smoke Test';email='smoke@example.com';class_id=$classId}
$studentId = Find-Id $html "T$suffix"
$html = Save-Row 'grades' @{id='0';student_id=$studentId;subject="TEST$suffix";score='8.5'}
$gradeId = Find-Id $html "TEST$suffix"
$html = Save-Row 'grades' @{id=$gradeId;student_id=$studentId;subject="TEST$suffix";score='9'}
if ($html -notmatch '<td>9.00</td>') { throw 'Grade update failed' }
$blocked = Invoke-WebRequest "$base/?page=classes" -Method Post -WebSession $browser -UseBasicParsing -Body @{csrf=$token;action='delete';id=$classId}
if ($blocked.Content -notmatch 'class="error"') { throw 'Foreign key protection failed' }
$badScore = Invoke-WebRequest "$base/?page=grades" -Method Post -WebSession $browser -UseBasicParsing -Body @{csrf=$token;id=$gradeId;student_id=$studentId;subject="TEST$suffix";score='11'}
if ($badScore.Content -notmatch 'class="error"') { throw 'Score validation failed' }
$null = Save-Row 'grades' @{action='delete';id=$gradeId}
$null = Save-Row 'students' @{action='delete';id=$studentId}
$null = Save-Row 'classes' @{action='delete';id=$classId}
Write-Host 'PASS: health, class/student/grade create, grade update, FK protection, invalid score, delete.'
