param(
    [string]$PhpPath = '',
    [string]$Environment = 'local',
    [ValidateRange(1024, 65535)][int]$ApiPort = 8120,
    [ValidateRange(1024, 65535)][int]$ClientPort = 5173
)

$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
if (-not $PhpPath) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) { $PhpPath = $phpCommand.Source }
    elseif (Test-Path -LiteralPath 'C:\OSPanel\modules\PHP-8.5\php.exe') { $PhpPath = 'C:\OSPanel\modules\PHP-8.5\php.exe' }
    else { throw 'Укажите -PhpPath с путём к PHP 8.3 или новее.' }
}
if (-not (Test-Path -LiteralPath (Join-Path $projectDirectory 'vendor\autoload.php'))) { throw 'Сначала выполните composer install.' }
if (-not (Test-Path -LiteralPath (Join-Path $projectDirectory '.env'))) { throw 'Сначала создайте .env по README и выполните миграции.' }
$logDirectory = Join-Path $projectDirectory 'storage\logs'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

$apiUrl = "http://127.0.0.1:$ApiPort"
$clientUrl = "http://localhost:$ClientPort"
$apiRunning = $false
try {
    $health = Invoke-RestMethod -Uri "$apiUrl/api/v1/health" -TimeoutSec 2
    $apiRunning = $health.data.name -eq 'KeyCraft AI'
} catch { }
if (-not $apiRunning) {
    $apiProcess = Start-Process -FilePath $PhpPath -WorkingDirectory $projectDirectory -ArgumentList @('artisan', 'serve', "--env=$Environment", '--host=127.0.0.1', "--port=$ApiPort", '--no-reload') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'server.out.log') -RedirectStandardError (Join-Path $logDirectory 'server.err.log') -PassThru
    Write-Host "API запущен, PID $($apiProcess.Id)."
} else { Write-Host 'API уже запущен. Окружение работающего сервера не переключается.' }
$clientRunning = $false
try {
    $page = Invoke-WebRequest -Uri $clientUrl -TimeoutSec 2
    $clientRunning = $page.Content.Contains('KeyCraft AI')
} catch { }
if (-not $clientRunning) {
    $clientProcess = Start-Process -FilePath $PhpPath -WorkingDirectory $projectDirectory -ArgumentList @('-S', "localhost:$ClientPort", '-t', 'client') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'client.out.log') -RedirectStandardError (Join-Path $logDirectory 'client.err.log') -PassThru
    Write-Host "Клиент запущен, PID $($clientProcess.Id)."
} else { Write-Host 'Клиент уже запущен.' }
Write-Host "Клиент: $clientUrl"
Write-Host "Документация: $apiUrl/docs"
Write-Host "Проверка API и MySQL: $apiUrl/api/v1/health"
Write-Host 'MySQL должен быть запущен отдельно. При смене портов обновите client/config.js и FRONTEND_URL в .env.'
