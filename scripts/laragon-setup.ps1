<#
.SYNOPSIS
    Set up NPO WorkDash as its own Laragon project. Safe to run more than once.

.DESCRIPTION
    Reads docs/laragon/profile.json, then:
      1. finds Laragon's PHP 8.3, Composer, Node 22 and MySQL 8.4 and puts them
         first on PATH for this PowerShell session only, and checks the PHP
         extensions;
      2. checks WorkDash's own virtual host runs PHP 8.3 through FastCGI
         (docs/laragon/npo-workdash.test.conf), and that public/.htaccess is safe under
         FastCGI; -InstallVhost copies the vhost into Laragon and reloads it;
      3. links C:\laragon\www\npo-workdash to this clone (a junction), unless it is
         already there;
      4. copies .env.example (Laragon values) to .env if .env is missing;
      5. installs Composer and npm dependencies, and generates APP_KEY if blank;
      6. creates this project's database and its test database if missing, and runs the migrations;
      7. offers to load the fake demo data (php artisan demo:seed).

    It only touches this project's folder link, virtual host, .env and database.

.PARAMETER SeedDemo
    Load the demo data without asking.

.PARAMETER NoDemo
    Skip the demo data without asking.

.PARAMETER InstallVhost
    Copy docs/laragon/npo-workdash.test.conf into Laragon's sites-enabled folder, remove
    Laragon's auto.npo-workdash.test.conf, and reload Laragon. Changes Laragon's files,
    so run it only when the owner has agreed.

.EXAMPLE
    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\laragon-setup.ps1 -SeedDemo
#>
[CmdletBinding()]
param(
    [switch]$SeedDemo,
    [switch]$NoDemo,
    [switch]$InstallVhost,
    [string]$LaragonRoot = 'C:\laragon'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$Message) { Write-Host "==> $Message" -ForegroundColor Cyan }
function Write-Ok([string]$Message) { Write-Host "    $Message" -ForegroundColor Green }
function Write-Warn([string]$Message) { Write-Host "    WARNING: $Message" -ForegroundColor Yellow }

function Get-NewestFolder([string]$Parent, [string]$Pattern) {
    Get-ChildItem $Parent -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match $Pattern } |
        Sort-Object Name -Descending |
        Select-Object -First 1
}

# Windows PowerShell turns a native tool's stderr into errors, so run tools
# with 'Continue' and judge them by their exit code.
function Invoke-Checked([string]$What, [scriptblock]$Command) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $Command } finally { $ErrorActionPreference = $previous }
    if ($LASTEXITCODE -ne 0) { throw "$What failed (exit $LASTEXITCODE)." }
}

function Get-EnvValue([string]$Path, [string]$Key) {
    $line = Get-Content $Path | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if ($null -eq $line) { return $null }
    return ($line -replace "^$Key=", '').Trim('"')
}

if ($SeedDemo -and $NoDemo) { throw 'Use -SeedDemo or -NoDemo, not both.' }

$repo = Split-Path $PSScriptRoot -Parent
$p = Get-Content (Join-Path $repo 'docs\laragon\profile.json') -Raw | ConvertFrom-Json

Write-Step "NPO WorkDash Laragon set-up ($($p.appUrl))"

# 1. Laragon tools ------------------------------------------------------------
Write-Step 'Checking Laragon'
if (-not (Test-Path (Join-Path $LaragonRoot 'laragon.exe'))) {
    throw "Laragon was not found at $LaragonRoot. Install Laragon (Full), or pass -LaragonRoot."
}

$phpDir = Get-Item (Join-Path $LaragonRoot "bin\php\$($p.phpBuild)") -ErrorAction SilentlyContinue
if (-not $phpDir) { $phpDir = Get-NewestFolder "$LaragonRoot\bin\php" "^php-$([regex]::Escape($p.php))\.\d+(-\d+)?-(nts-)?Win32" }
if (-not $phpDir) { throw "PHP $($p.php) is not installed in Laragon. Add it with Menu > PHP > Version > Get more, then run this again." }
if ($phpDir.Name -ne $p.phpBuild) { Write-Warn "Profile PHP $($p.phpBuild) is not installed; using $($phpDir.Name). Update docs\laragon\npo-workdash.test.conf to match." }

$composerPhar = Join-Path $LaragonRoot 'bin\composer\composer.phar'
if (-not (Test-Path $composerPhar)) { throw "Composer was not found at $composerPhar." }
$nodeDir = Get-NewestFolder "$LaragonRoot\bin\nodejs" "^node-v?$($p.node)(\D|$)"
if (-not $nodeDir) { throw "Node $($p.node) is not installed in Laragon. Add it with Menu > Tools > Quick add." }
$mysqlDir = Get-NewestFolder "$LaragonRoot\bin\mysql" "^mysql-$([regex]::Escape($p.database.version))\."
if (-not $mysqlDir) { throw "MySQL $($p.database.version) is not installed in Laragon." }

$env:Path = "$($phpDir.FullName);$(Split-Path $composerPhar);$($nodeDir.FullName);$($mysqlDir.FullName)\bin;" + $env:Path
if (-not $env:XDEBUG_MODE) { $env:XDEBUG_MODE = 'off' }

$phpVersion = "$(php -r 'echo PHP_VERSION;')".Trim()
Write-Ok "PHP $phpVersion  ($($phpDir.FullName))"
Write-Ok "Node $("$(node -v)".Trim()), MySQL $($mysqlDir.Name)"
if ($phpVersion -notlike "$($p.php).*") { throw "PHP on PATH is $phpVersion, not $($p.php)." }

$loaded = "$(php -r "echo implode(',', array_map('strtolower', get_loaded_extensions()));")".Split(',')
$missing = @($p.phpExtensions | Where-Object { $loaded -notcontains $_ })
if ($missing.Count -gt 0) { throw "PHP $phpVersion is missing these extensions: $($missing -join ', '). Turn them on in $($phpDir.FullName)\php.ini." }
Write-Ok "PHP extensions: $($p.phpExtensions -join ', ')"

# 2. WorkDash's virtual host: PHP 8.3 through FastCGI --------------------------------
Write-Step 'Virtual host and FastCGI'
$sites = Join-Path $LaragonRoot 'etc\apache2\sites-enabled'
$ownVhost = Join-Path $sites $p.hostname
$ownVhost = "$ownVhost.conf"
$autoVhost = Join-Path $sites "auto.$($p.hostname).conf"
$template = Join-Path $repo $p.siteVhost
if ($InstallVhost) {
    Copy-Item $template $ownVhost -Force
    if (Test-Path $autoVhost) { Remove-Item $autoVhost -Force }
    if (Get-Process laragon -ErrorAction SilentlyContinue) {
        & (Join-Path $LaragonRoot 'laragon.exe') reload
        Start-Sleep -Seconds 5
    }
    Write-Ok "Installed $ownVhost and reloaded Laragon"
}
if ((Test-Path $ownVhost) -and ((Get-Content $ownVhost -Raw) -eq (Get-Content $template -Raw))) {
    Write-Ok "$ownVhost runs $($p.phpBuild) through FastCGI"
    if (Test-Path $autoVhost) { Write-Warn "$autoVhost also exists and may take the site first. Delete it, then Reload Apache." }
} else {
    Write-Warn "WorkDash is served by Laragon's global PHP, not its own PHP $($p.php). To match its Cloudron app, run this script with -InstallVhost (it changes Laragon's sites-enabled folder, so ask the owner first)."
}

$httpdConf = Get-NewestFolder "$LaragonRoot\bin\apache" '^httpd-' | ForEach-Object { Join-Path $_.FullName 'conf\httpd.conf' }
$handler = if ($httpdConf -and (Test-Path $httpdConf)) { Select-String -Path $httpdConf -Pattern '^\s*Include\s+"?[^"]*[\\/]etc[\\/]apache2[\\/](mod_php|fcgid)\.conf' | Select-Object -First 1 }
if (-not $handler -or $handler.Matches[0].Groups[1].Value -ne $p.apacheHandler) {
    Write-Warn "Laragon's Apache does not load fcgid.conf, so FastCGI is off. Choose an NTS PHP in Menu > PHP > Version, then Reload Apache."
}

$depth = 0
$problems = @()
foreach ($line in Get-Content (Join-Path $repo 'public\.htaccess')) {
    if ($line -match '^\s*<IfModule\s+(php_module|mod_php\d*\.c)\s*>') { $depth++ }
    elseif ($depth -gt 0 -and $line -match '^\s*</IfModule>') { $depth-- }
    elseif ($depth -eq 0 -and $line -match '^\s*php_(value|flag|admin_value|admin_flag)\b') { $problems += $line.Trim() }
}
if ($problems.Count -gt 0) { throw "public\.htaccess has PHP settings that FastCGI answers with a 500 error: $($problems -join '; '). Put them inside <IfModule php_module>." }
Write-Ok 'public\.htaccess is safe under FastCGI'

# 3. Project folder -------------------------------------------------------------
Write-Step "Project folder $($p.folder)"
$repoFull = (Resolve-Path $repo).Path.TrimEnd('\')
if (Test-Path $p.folder) {
    $item = Get-Item $p.folder -Force
    $target = if ($item.LinkType) { "$($item.Target)".TrimEnd('\') } else { $item.FullName.TrimEnd('\') }
    if ($target -ne $repoFull) { throw "$($p.folder) already points at $target, not this clone ($repoFull). Remove or rename it yourself, then run this again." }
    Write-Ok 'Already linked to this clone'
} else {
    New-Item -ItemType Junction -Path $p.folder -Target $repoFull | Out-Null
    Write-Ok "Linked to $repoFull. Click Reload in Laragon so it creates http://$($p.hostname)."
}

Push-Location $repo
try {
    # 4. .env -------------------------------------------------------------------
    Write-Step '.env'
    $envFile = Join-Path $repo '.env'
    if (Test-Path $envFile) { Write-Ok '.env already exists, left as it is' }
    else { Copy-Item (Join-Path $repo '.env.example') $envFile; Write-Ok 'Copied .env.example to .env' }
    foreach ($check in @(@('APP_ENV', 'local'), @('APP_URL', $p.appUrl), @('DB_DATABASE', $p.database.name), @('DB_HOST', $p.database.host))) {
        $value = Get-EnvValue $envFile $check[0]
        if ($value -ne $check[1]) { Write-Warn "$($check[0]) in .env is '$value'. The Laragon profile expects '$($check[1])'." }
    }

    # 5. Dependencies -----------------------------------------------------------
    Write-Step 'Dependencies'
    Invoke-Checked 'composer install' { php $composerPhar install --no-interaction --no-progress }
    if (Test-Path (Join-Path $repo 'node_modules')) { Write-Ok 'node_modules already there. Run npm ci yourself after package-lock.json changes.' }
    else { Invoke-Checked 'npm ci' { npm ci --no-audit --no-fund } }
    if (-not (Get-EnvValue $envFile 'APP_KEY')) { Invoke-Checked 'key:generate' { php artisan key:generate --no-interaction } }
    else { Write-Ok 'APP_KEY already set' }

    # 6. Database ---------------------------------------------------------------
    $dbName = Get-EnvValue $envFile 'DB_DATABASE'
    $dbHost = Get-EnvValue $envFile 'DB_HOST'
    $dbPort = Get-EnvValue $envFile 'DB_PORT'
    $dbUser = Get-EnvValue $envFile 'DB_USERNAME'
    Write-Step "Database $dbName"
    if ($dbName -notmatch '^[A-Za-z0-9_]+$') { throw "DB_DATABASE '$dbName' must use only letters, numbers and underscores." }
    if ((Get-EnvValue $envFile 'DB_PASSWORD')) { throw 'DB_PASSWORD is set in .env. Laragon uses root with no password; the script only creates databases that way.' }
    try { Invoke-Checked 'mysqladmin ping' { mysqladmin -u $dbUser -h $dbHost -P $dbPort --connect-timeout=5 ping 2>$null | Out-Null } }
    catch { throw "MySQL is not answering on ${dbHost}:${dbPort}. Click Start All in Laragon, then run this again." }
    Invoke-Checked 'CREATE DATABASE' { mysql -u $dbUser -h $dbHost -P $dbPort -e "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET $($p.database.charset) COLLATE $($p.database.collation);" }
    Write-Ok "$dbName is ready"
    $testDb = $p.database.testing
    Invoke-Checked 'CREATE DATABASE (tests)' { mysql -u $dbUser -h $dbHost -P $dbPort -e "CREATE DATABASE IF NOT EXISTS ``$testDb`` CHARACTER SET $($p.database.charset) COLLATE $($p.database.collation);" }
    Write-Ok "$($p.database.testing) is ready for the tests"
    Invoke-Checked 'migrate' { php artisan migrate --no-interaction }

    # 7. Demo data --------------------------------------------------------------
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
