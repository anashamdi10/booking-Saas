<#
.SYNOPSIS
    Stages a clean copy of the API and prints the deploy command.

.DESCRIPTION
    Wasmer packages everything found in the directory you run `wasmer deploy`
    from, so deploying straight out of backend/ would ship .env (with APP_KEY
    and database credentials), the SQLite file and development logs. This
    script copies only what the running app needs.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\package.ps1
#>
$ErrorActionPreference = 'Stop'

$root  = Split-Path -Parent $PSScriptRoot
$stage = Join-Path ([System.IO.Path]::GetTempPath()) 'photographer-api-package'

# The SPA is served by this same app, so deploying before building it would
# publish an empty shell.
if (-not (Test-Path (Join-Path $root 'public\index.html'))) {
    throw 'public/index.html is missing - run `npm run build:spa` inside frontend/ first.'
}

# TiDB Cloud requires TLS; MYSQL_ATTR_SSL_CA points here at runtime.
if (-not (Test-Path (Join-Path $root 'certs\tidb-ca.pem'))) {
    Write-Warning 'certs/tidb-ca.pem is missing - MYSQL_ATTR_SSL_CA will point at a file that is not there.'
}

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Path $stage | Out-Null

# robocopy: any exit code below 8 means success.
robocopy $root $stage /E /NFL /NDL /NJH /NJS `
    /XD (Join-Path $root '.git') (Join-Path $root 'node_modules') `
    /XF (Join-Path $root '.env') (Join-Path $root '.env.backup') '*.sqlite' '*.log' |
    Out-Null

if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE." }

# Laravel writes into these; they must exist even when empty.
foreach ($dir in 'storage\logs', 'storage\framework\cache', 'storage\framework\sessions',
                 'storage\framework\views', 'bootstrap\cache') {
    New-Item -ItemType Directory -Force -Path (Join-Path $stage $dir) | Out-Null
}

Write-Host ''
Write-Host "Package staged at: $stage"
Write-Host 'Next steps:'
Write-Host "    cd `"$stage`""
Write-Host '    wasmer deploy'
