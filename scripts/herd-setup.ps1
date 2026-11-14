<#
.SYNOPSIS
    Set up NPO WorkDash as its own Laravel Herd site. Safe to run more than once.

.DESCRIPTION
    Reads docs/herd/profile.json, then:
      1. checks Herd is running, finds its PHP 8.3, Composer and Node 22, and puts
         them first on PATH for this PowerShell session only;
      2. checks the PHP extensions, and that public/.htaccess stays safe for
         Cloudron's Apache under FastCGI;
      3. checks Herd's MySQL 8.4 service answers on its port;
      4. links the site (herd link) and pins it to PHP 8.3 (herd isolate), unless
         that is already done;
      5. copies .env.example (Herd values) to .env if .env is missing;
      6. installs Composer and npm dependencies, and generates APP_KEY if blank;
      7. creates this project's database and its test database if missing, and
         runs the migrations;
      8. offers to load the fake demo data (php artisan demo:seed).

    It only touches this project's site link, .env and databases.

.PARAMETER SeedDemo
    Load the demo data without asking.

.PARAMETER NoDemo
    Skip the demo data without asking.

.PARAMETER HerdRoot
    Herd's folder. Defaults to %USERPROFILE%\.config\herd.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\herd-setup.ps1 -SeedDemo
#>
[CmdletBinding()]
param(
    [switch]$SeedDemo,
    [switch]$NoDemo,
    [string]$HerdRoot = (Join-Path $env:USERPROFILE '.config\herd')
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$Message) { Write-Host "==> $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "    $Message" -ForegroundColor Green }
function Write-Warn([string]$Message) { Write-Host "    WARNING: $Message" -ForegroundColor Yellow }

# Windows PowerShell turns a native tool's stderr into errors, so run tools
# with 'Continue' and judge them by their exit code.
function Invoke-Checked([string]$What, [scriptblock]$Command) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $Command } finally { $ErrorActionPreference = $previous }
    if ($LASTEXITCODE -ne 0) { throw "$What failed (exit $LASTEXITCODE)." }
}

function Get-NativeOutput([scriptblock]$Command) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { $output = & $Command 2>&1 } finally { $ErrorActionPreference = $previous }
    return ($output | Out-String).Trim()
}

function Get-EnvValue([string]$Path, [string]$Key) {
    $line = Get-Content $Path | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if ($null -eq $line) { return $null }
    return ($line -replace "^$Key=", '').Trim('"')
}

if ($SeedDemo -and $NoDemo) { throw 'Use -SeedDemo or -NoDemo, not both.' }

$repo = Split-Path $PSScriptRoot -Parent
$p = Get-Content (Join-Path $repo 'docs\herd\profile.json') -Raw | ConvertFrom-Json

Write-Step "NPO WorkDash Herd set-up ($($p.appUrl))"

# 1. Herd tools ----------------------------------------------------------------
Write-Step 'Checking Herd'
$herdBin = Join-Path $HerdRoot 'bin'
if (-not (Test-Path (Join-Path $herdBin 'herd.bat'))) {
    throw "Laravel Herd was not found at $HerdRoot. Install Herd for Windows, or pass -HerdRoot."
}
$notRunning = @('Herd', 'nginx' | Where-Object { -not (Get-Process $_ -ErrorAction SilentlyContinue) })
if ($notRunning.Count -gt 0) { throw "Not running: $($notRunning -join ', '). Open Herd, then run this again." }

$phpDir = Join-Path $herdBin "php$($p.php -replace '\.', '')"
if (-not (Test-Path (Join-Path $phpDir 'php.exe'))) { throw "Herd has no PHP $($p.php). Install it with: herd php:install $($p.php)" }

$composerPhar = Join-Path $herdBin 'composer.phar'
if (-not (Test-Path $composerPhar)) { throw "Composer was not found at $composerPhar." }

$nodeDir = Get-ChildItem (Join-Path $herdBin 'nvm') -Directory -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -match "^v$($p.node)\.\d+\.\d+$" } |
    Sort-Object { [version]($_.Name.TrimStart('v')) } -Descending |
    Select-Object -First 1
if (-not $nodeDir) { throw "Herd's nvm has no Node $($p.node). Turn on Node.js in Herd > Settings, then run: herd node:install $($p.node)" }

$env:Path = "$phpDir;$herdBin;$($nodeDir.FullName);" + $env:Path
if (-not $env:XDEBUG_MODE) { $env:XDEBUG_MODE = 'off' }

$phpVersion = "$(php -r 'echo PHP_VERSION;')".Trim()
$nodeVersion = "$(node -v)".Trim()
Write-Ok "PHP $phpVersion  ($phpDir)"
Write-Ok "Node $nodeVersion  ($($nodeDir.FullName))"
if ($phpVersion -notlike "$($p.php).*") { throw "PHP on PATH is $phpVersion, not $($p.php)." }
if ($nodeVersion -notlike "v$($p.node).*") { throw "Node on PATH is $nodeVersion, not $($p.node)." }

# 2. PHP extensions and Cloudron's .htaccess -------------------------------------
$loaded = "$(php -r "echo implode(',', array_map('strtolower', get_loaded_extensions()));")".Split(',')
$missing = @($p.phpExtensions | Where-Object { $loaded -notcontains $_ })
if ($missing.Count -gt 0) {
    throw "Herd's PHP $phpVersion is missing these extensions: $($missing -join ', '). Open its php.ini with 'herd ini $($p.php)', remove the ; before extension=<name>, run 'herd restart php', then run this again."
}
Write-Ok "PHP extensions: $($p.phpExtensions -join ', ')"

$htaccess = Join-Path $repo 'public\.htaccess'
$depth = 0
$problems = @()
foreach ($line in Get-Content $htaccess) {
    if ($line -match '^\s*<IfModule\s+(php_module|mod_php\d*\.c)\s*>') { $depth++ }
    elseif ($depth -gt 0 -and $line -match '^\s*</IfModule>') { $depth-- }
    elseif ($depth -eq 0 -and $line -match '^\s*php_(value|flag|admin_value|admin_flag)\b') { $problems += $line.Trim() }
}
if ($problems.Count -gt 0) { throw "public\.htaccess has PHP settings that FastCGI answers with a 500 error on Cloudron: $($problems -join '; '). Put them inside <IfModule php_module>." }
if (-not (Select-String -Path $htaccess -Pattern 'HTTP_AUTHORIZATION' -Quiet)) {
    Write-Warn 'public\.htaccess no longer passes the Authorization header to PHP. API tokens on Cloudron need the HTTP_AUTHORIZATION rewrite rule.'
}
Write-Ok 'public\.htaccess is safe for Cloudron'

# 3. MySQL service ---------------------------------------------------------------
Write-Step "Herd MySQL $($p.database.version) on port $($p.database.port)"
$services = Get-NativeOutput { herd services:list }
$mysqlRow = $services -split "`r?`n" | Where-Object { $_ -match '\|\s*mysql\s*\|' -and $_ -match "\|\s*$($p.database.port)\s*\|" } | Select-Object -First 1
if (-not $mysqlRow) {
    throw "Herd has no MySQL service on port $($p.database.port). Create one with: herd services:create mysql --service-version=$($p.database.version).<latest patch> --port=$($p.database.port)"
}
if ($mysqlRow -notmatch "\|\s*$([regex]::Escape($p.database.version))\.\d+\s*\|") { Write-Warn "The MySQL service on port $($p.database.port) is not version $($p.database.version): $($mysqlRow.Trim())" }
if (-not (Get-NetTCPConnection -State Listen -LocalPort $p.database.port -ErrorAction SilentlyContinue)) {
    throw "Herd's MySQL service on port $($p.database.port) is not running. Start it in Herd > Services, then run this again."
}
$mysqlBin = Get-ChildItem (Join-Path $herdBin 'services\mysql') -Directory -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -like "$($p.database.version).*" } |
    Sort-Object { [version]$_.Name } -Descending |
    Select-Object -First 1
if (-not $mysqlBin) { throw "Herd's MySQL $($p.database.version) tools were not found in $herdBin\services\mysql." }
$env:Path = "$($mysqlBin.FullName)\bin;" + $env:Path
Write-Ok "MySQL $($mysqlBin.Name) answers on port $($p.database.port)"

# 4. Herd site -------------------------------------------------------------------
Write-Step "Herd site $($p.hostname)"
$repoFull = (Resolve-Path $repo).Path.TrimEnd('\')
$siteLink = Join-Path $HerdRoot "config\valet\Sites\$($p.herdSite)"
Push-Location $repo
try {
    if (Test-Path $siteLink) {
        $target = "$((Get-Item $siteLink -Force).Target)".TrimEnd('\')
        if ($target -and $target -ne $repoFull) {
            throw "The Herd site $($p.herdSite) already points at $target, not this clone ($repoFull). Run 'herd unlink $($p.herdSite)' there first, then run this again."
        }
        Write-Ok 'Already linked to this clone'
    } else {
        Invoke-Checked 'herd link' { herd link $p.herdSite }
        Write-Ok "Linked $($p.hostname) to $repoFull"
    }
    if ($p.phpIsolated) {
        $sitePhp = Get-NativeOutput { herd which-php $p.herdSite }
        if ($sitePhp -match "php$($p.php -replace '\.', '')[\\/]") {
            Write-Ok "Pinned to PHP $($p.php)"
        } else {
            Invoke-Checked 'herd isolate' { herd isolate $p.php --site=$($p.herdSite) }
            Write-Ok "Pinned $($p.hostname) to PHP $($p.php)"
        }
    }

    # 5. .env --------------------------------------------------------------------
    Write-Step '.env'
    $envFile = Join-Path $repo '.env'
    if (Test-Path $envFile) { Write-Ok '.env already exists, left as it is' }
    else { Copy-Item (Join-Path $repo '.env.example') $envFile; Write-Ok 'Copied .env.example to .env' }
    foreach ($check in @(@('APP_ENV', 'local'), @('APP_URL', $p.appUrl), @('DB_DATABASE', $p.database.name), @('DB_HOST', $p.database.host), @('DB_PORT', "$($p.database.port)"), @('MAIL_HOST', $p.mail.host), @('MAIL_PORT', "$($p.mail.port)"))) {
        $value = Get-EnvValue $envFile $check[0]
        if ($value -ne $check[1]) { Write-Warn "$($check[0]) in .env is '$value'. The Herd profile expects '$($check[1])'." }
    }

    # 6. Dependencies ------------------------------------------------------------
    Write-Step 'Dependencies'
    Invoke-Checked 'composer install' { php $composerPhar install --no-interaction --no-progress }
    if (Test-Path (Join-Path $repo 'node_modules')) { Write-Ok 'node_modules already there. Run npm ci yourself after package-lock.json changes.' }
    else { Invoke-Checked 'npm ci' { npm ci --no-audit --no-fund } }
    if (-not (Get-EnvValue $envFile 'APP_KEY')) { Invoke-Checked 'key:generate' { php artisan key:generate --no-interaction } }
    else { Write-Ok 'APP_KEY already set' }

    # 7. Databases ---------------------------------------------------------------
    $dbName = Get-EnvValue $envFile 'DB_DATABASE'
    $dbHost = Get-EnvValue $envFile 'DB_HOST'
    $dbPort = Get-EnvValue $envFile 'DB_PORT'
    $dbUser = Get-EnvValue $envFile 'DB_USERNAME'
    Write-Step "Database $dbName"
    if ($dbName -notmatch '^[A-Za-z0-9_]+$') { throw "DB_DATABASE '$dbName' must use only letters, numbers and underscores." }
    if ((Get-EnvValue $envFile 'DB_PASSWORD')) { throw "DB_PASSWORD is set in .env. Herd's MySQL uses root with no password; the script only creates databases that way." }
    try { Invoke-Checked 'mysqladmin ping' { mysqladmin -u $dbUser -h $dbHost -P $dbPort --connect-timeout=5 ping 2>$null | Out-Null } }
    catch { throw "MySQL is not answering on ${dbHost}:${dbPort}. Start Herd's MySQL service, then run this again." }
    foreach ($name in @($dbName, $p.database.testing)) {
        Invoke-Checked "CREATE DATABASE $name" { mysql -u $dbUser -h $dbHost -P $dbPort -e "CREATE DATABASE IF NOT EXISTS ``$name`` CHARACTER SET $($p.database.charset) COLLATE $($p.database.collation);" }
        Write-Ok "$name is ready"
    }
    Invoke-Checked 'migrate' { php artisan migrate --no-interaction }

    # 8. Demo data ---------------------------------------------------------------
    Write-Step 'Demo data'
    $loadDemo = $SeedDemo.IsPresent
    if (-not $SeedDemo -and -not $NoDemo) { $loadDemo = (Read-Host '    Load the fake demo data now? [y/N]') -match '^(y|yes)$' }
    if ($loadDemo) { Invoke-Checked 'demo:seed' { php artisan demo:seed --no-interaction } }
    else { Write-Ok 'Skipped. Load it any time with: php artisan demo:seed' }
} finally {
    Pop-Location
}

Write-Step 'Done'
Write-Ok "Live reload: npm run dev (Vite hot reload on port $($p.devServerPort)); composer dev also runs the queue worker"
Write-Ok "Before any work: powershell -NoProfile -ExecutionPolicy Bypass -File scripts\preview.ps1"
