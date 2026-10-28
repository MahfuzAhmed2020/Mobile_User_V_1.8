$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$runtimeRoot = Join-Path $projectRoot '.runtime'
$binary = Join-Path $runtimeRoot 'bin\MailHog.exe'
$existing = Get-Process -Name MailHog -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $binary }
if ($existing) {
    Write-Output 'MailHog is already running. Inbox: http://127.0.0.1:8025'
    return
}
if (-not (Test-Path -LiteralPath $binary)) {
    throw 'Install the official MailHog Windows binary as .runtime\bin\MailHog.exe first.'
}
New-Item -ItemType Directory -Path (Join-Path $runtimeRoot 'mailhog') -Force | Out-Null
$arguments = @('-smtp-bind-addr','127.0.0.1:1025','-api-bind-addr','127.0.0.1:8025',
    '-ui-bind-addr','127.0.0.1:8025','-storage','maildir',
    '-maildir-path', ('"' + (Join-Path $runtimeRoot 'mailhog') + '"'))
$process = Start-Process -FilePath $binary -ArgumentList $arguments -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $runtimeRoot 'mailhog.stdout.log') `
    -RedirectStandardError (Join-Path $runtimeRoot 'mailhog.stderr.log')
Write-Output "MailHog PID: $($process.Id)"
Write-Output 'Inbox: http://127.0.0.1:8025'
