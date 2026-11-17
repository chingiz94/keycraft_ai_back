param([string]$PhpPath='C:\OSPanel\modules\PHP-8.5\php.exe',[switch]$Development)
$ErrorActionPreference='Stop'
$projectDirectory=$PSScriptRoot
$frontendDirectory=Join-Path $projectDirectory 'frontend'
$nextScript=Join-Path $frontendDirectory 'node_modules/next/dist/bin/next'
$nodeCommand=Get-Command node -ErrorAction SilentlyContinue
if (-not $nodeCommand) { throw 'Установите Node.js 24 LTS.' }
if (-not (Test-Path $PhpPath)) { $PhpPath=(Get-Command php -ErrorAction Stop).Source }
if (-not (Test-Path $nextScript)) { throw 'Выполните npm ci в frontend.' }
if (-not $Development -and -not (Test-Path (Join-Path $frontendDirectory '.next/BUILD_ID'))) { throw 'Выполните npm run build в frontend либо передайте -Development.' }
$logDirectory=Join-Path $projectDirectory 'storage/logs'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
if (-not (Get-NetTCPConnection -State Listen -LocalPort 8120 -ErrorAction SilentlyContinue)) {
 Start-Process -FilePath $PhpPath -WorkingDirectory $projectDirectory -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=8120','--no-reload') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'server.out.log') -RedirectStandardError (Join-Path $logDirectory 'server.err.log') | Out-Null
}
if (-not (Get-NetTCPConnection -State Listen -LocalPort 5173 -ErrorAction SilentlyContinue)) {
 $quotedNextScript='"{0}"' -f $nextScript
 $arguments=@($quotedNextScript,$(if($Development){'dev'}else{'start'}),'--hostname','localhost','--port','5173')
 if ($Development) { $arguments+='--webpack' }
 Start-Process -FilePath $nodeCommand.Source -WorkingDirectory $frontendDirectory -ArgumentList $arguments -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'frontend.out.log') -RedirectStandardError (Join-Path $logDirectory 'frontend.err.log') | Out-Null
}
for ($attempt=0;$attempt -lt 20;$attempt++) {
 try { $health=Invoke-RestMethod 'http://127.0.0.1:8120/api/v1/health' -TimeoutSec 2; $page=Invoke-WebRequest 'http://localhost:5173' -TimeoutSec 2; if($health.data.database -eq 'ok' -and $page.StatusCode -eq 200 -and $page.Content.Contains('KeyCraft')) { Write-Host 'Сайт: http://localhost:5173'; Write-Host 'Документация: http://127.0.0.1:8120/docs'; return } } catch { }
 Start-Sleep -Milliseconds 500
}
throw 'Не удалось проверить запуск. Проверьте MySQL, .env и storage/logs.'
