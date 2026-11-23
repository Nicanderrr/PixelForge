$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
New-Item -ItemType Directory -Path '.runtime' -Force | Out-Null

# The bundled, isolated database is optional. Other machines can use their own MySQL .env.
$databaseExe = Join-Path $projectRoot '.runtime\mariadb-11.4.14-winx64\bin\mariadbd.exe'
if ((Test-Path -LiteralPath $databaseExe) -and -not (Get-NetTCPConnection -LocalPort 3308 -State Listen -ErrorAction SilentlyContinue)) {
    $databaseConfig = Join-Path $projectRoot '.runtime\database\my.ini'
    Start-Process -FilePath $databaseExe -ArgumentList @("--defaults-file=$databaseConfig", '--bind-address=127.0.0.1') -WindowStyle Hidden | Out-Null
}

if (Get-NetTCPConnection -LocalPort 8030 -State Listen -ErrorAction SilentlyContinue) {
    Write-Host 'Port 8030 is already occupied. No existing process was changed.'
    exit 1
}

$php = (Get-Command php).Source
$launches = @{
    web = @('artisan', 'serve', '--host=127.0.0.1', '--port=8030', '--no-reload')
    queue = @('artisan', 'queue:listen', '--sleep=1', '--tries=3', '--timeout=60')
    scheduler = @('artisan', 'schedule:work')
}
$processIds = @{}
foreach ($name in $launches.Keys) {
    $process = Start-Process -FilePath $php -ArgumentList $launches[$name] -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput ".runtime\$name.log" -RedirectStandardError ".runtime\$name.error.log"
    $processIds[$name] = $process.Id
}
$processIds | ConvertTo-Json | Set-Content '.runtime\processes.json'
Write-Host 'Forma is starting at http://127.0.0.1:8030. Local queue and scheduler started.'
Write-Host 'Logs are in .runtime. Provider secrets are read from .env; local queue:listen reloads them for each job.'
