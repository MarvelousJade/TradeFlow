[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string] $AivenProject,

    [Parameter(Mandatory)]
    [string] $AdminEmail,

    [string] $AivenService = 'tradeflow-mysql',
    [string] $RenderService = 'tradeflow-booking',
    [string] $RenderRegion = 'ohio',
    [string] $RenderBranch = 'agent/free-deployment',
    [string] $Repository = 'https://github.com/MarvelousJade/TradeFlow',
    [string] $AdminUser = 'tradeflow-admin',
    [string] $AivenCli,
    [string] $RenderCli
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repositoryRoot = Split-Path -Parent $PSScriptRoot
if (-not $AivenCli) {
    $AivenCli = Join-Path $repositoryRoot '.tools\aiven-venv\Scripts\avn.exe'
}
if (-not $RenderCli) {
    $RenderCli = Join-Path $repositoryRoot '.tools\render\cli_v2.22.0.exe'
}

foreach ($cli in @($AivenCli, $RenderCli)) {
    if (-not (Test-Path -LiteralPath $cli -PathType Leaf)) {
        throw "Required CLI not found: $cli"
    }
}

function Invoke-JsonCommand {
    param(
        [Parameter(Mandatory)]
        [string] $Command,

        [Parameter(Mandatory)]
        [string[]] $Arguments
    )

    $output = & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed: $([IO.Path]::GetFileName($Command))."
    }

    return ($output -join "`n") | ConvertFrom-Json
}

function New-Secret {
    param([int] $ByteCount = 48)

    $bytes = [Security.Cryptography.RandomNumberGenerator]::GetBytes($ByteCount)
    return [Convert]::ToBase64String($bytes)
}

function Get-PropertyValue {
    param(
        [Parameter(Mandatory)]
        [object] $InputObject,

        [Parameter(Mandatory)]
        [string] $Name
    )

    $property = $InputObject.PSObject.Properties[$Name]
    if ($property) {
        return $property.Value
    }

    return $null
}

$aiven = Invoke-JsonCommand -Command $AivenCli -Arguments @(
    'service', 'get', $AivenService,
    '--project', $AivenProject,
    '--json'
)

if ($aiven.plan -ne 'free-1-1gb') {
    throw "Refusing to deploy with Aiven plan '$($aiven.plan)'; expected free-1-1gb."
}
if ($aiven.state -ne 'RUNNING') {
    throw "Aiven service '$AivenService' is not ready; current state: $($aiven.state)."
}
if ([string]::IsNullOrWhiteSpace([string] $aiven.service_uri)) {
    throw "Aiven did not return a MySQL service URI."
}

$databaseUri = [Uri] $aiven.service_uri
$encodedCredentials = $databaseUri.UserInfo -split ':', 2
if ($encodedCredentials.Count -ne 2) {
    throw 'The Aiven MySQL URI did not contain a username and password.'
}

$databaseHost = "$($databaseUri.Host):$($databaseUri.Port)"
$databaseName = [Uri]::UnescapeDataString($databaseUri.AbsolutePath.TrimStart('/'))
$databaseUser = [Uri]::UnescapeDataString($encodedCredentials[0])
$databasePassword = [Uri]::UnescapeDataString($encodedCredentials[1])

$credentialDirectory = Join-Path $repositoryRoot '.tools\aiven-mysql'
New-Item -ItemType Directory -Path $credentialDirectory -Force | Out-Null
& $AivenCli service user-creds-download $AivenService `
    --project $AivenProject `
    --username $databaseUser `
    --target-directory $credentialDirectory
if ($LASTEXITCODE -ne 0) {
    throw 'Aiven CA certificate download failed.'
}

$caPath = Join-Path $credentialDirectory 'ca.pem'
if (-not (Test-Path -LiteralPath $caPath -PathType Leaf)) {
    throw "Aiven CA certificate not found at $caPath."
}

$existingServices = Invoke-JsonCommand -Command $RenderCli -Arguments @(
    'services', '--output', 'json'
)
$existingService = @($existingServices) | Where-Object {
    $candidate = Get-PropertyValue -InputObject $_ -Name 'service'
    if (-not $candidate) {
        $candidate = $_
    }

    (Get-PropertyValue -InputObject $candidate -Name 'name') -eq $RenderService
}
if ($existingService) {
    throw "Render service '$RenderService' already exists; refusing to create a duplicate."
}

$adminPassword = New-Secret -ByteCount 24
$wordpressConfig = @'
define('WP_ENVIRONMENT_TYPE', 'production');
define('WP_MEMORY_LIMIT', '256M');
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);
define('AUTOMATIC_UPDATER_DISABLED', true);

$tradeflow_site_url = rtrim((string) getenv('TRADEFLOW_SITE_URL'), '/');
if ($tradeflow_site_url !== '') {
    define('WP_HOME', $tradeflow_site_url);
    define('WP_SITEURL', $tradeflow_site_url);
}

if ((string) getenv('TRADEFLOW_DB_SSL') === '1') {
    define('MYSQL_CLIENT_FLAGS', MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT);
}

if (
    isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
    && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
) {
    $_SERVER['HTTPS'] = 'on';
}
'@

$environment = [ordered] @{
    PORT                       = '10000'
    WORDPRESS_DB_HOST          = $databaseHost
    WORDPRESS_DB_NAME          = $databaseName
    WORDPRESS_DB_USER          = $databaseUser
    WORDPRESS_DB_PASSWORD      = $databasePassword
    WORDPRESS_TABLE_PREFIX     = 'tf_'
    WORDPRESS_DEBUG            = '0'
    WORDPRESS_AUTH_KEY         = New-Secret
    WORDPRESS_SECURE_AUTH_KEY  = New-Secret
    WORDPRESS_LOGGED_IN_KEY    = New-Secret
    WORDPRESS_NONCE_KEY        = New-Secret
    WORDPRESS_AUTH_SALT        = New-Secret
    WORDPRESS_SECURE_AUTH_SALT = New-Secret
    WORDPRESS_LOGGED_IN_SALT   = New-Secret
    WORDPRESS_NONCE_SALT       = New-Secret
    TRADEFLOW_DB_SSL           = '1'
    TRADEFLOW_SITE_TITLE       = 'TradeFlow'
    TRADEFLOW_ADMIN_USER       = $AdminUser
    TRADEFLOW_ADMIN_PASSWORD   = $adminPassword
    TRADEFLOW_ADMIN_EMAIL      = $AdminEmail
    TRADEFLOW_TIMEZONE         = 'America/Toronto'
    WORDPRESS_CONFIG_EXTRA     = $wordpressConfig
}

$renderArguments = @(
    'services', 'create',
    '--name', $RenderService,
    '--type', 'web_service',
    '--repo', $Repository,
    '--runtime', 'docker',
    '--branch', $RenderBranch,
    '--plan', 'free',
    '--region', $RenderRegion,
    '--health-check-path', '/wp-json/tradeflow/v1/health',
    '--secret-file', "aiven-ca.pem:$caPath",
    '--confirm',
    '--output', 'json'
)
foreach ($entry in $environment.GetEnumerator()) {
    $renderArguments += @('--env-var', "$($entry.Key)=$($entry.Value)")
}

$render = Invoke-JsonCommand -Command $RenderCli -Arguments $renderArguments
$service = Get-PropertyValue -InputObject $render -Name 'service'
if (-not $service) {
    $service = $render
}
$serviceDetails = Get-PropertyValue -InputObject $service -Name 'serviceDetails'
$serviceUrl = if ($serviceDetails -and (Get-PropertyValue -InputObject $serviceDetails -Name 'url')) {
    Get-PropertyValue -InputObject $serviceDetails -Name 'url'
} elseif (Get-PropertyValue -InputObject $service -Name 'url') {
    Get-PropertyValue -InputObject $service -Name 'url'
} else {
    "https://$RenderService.onrender.com"
}

Set-Clipboard -Value $adminPassword

[PSCustomObject] @{
    AivenService         = $AivenService
    AivenPlan            = $aiven.plan
    RenderService        = $RenderService
    RenderServiceId      = Get-PropertyValue -InputObject $service -Name 'id'
    SiteUrl              = $serviceUrl
    WordPressAdminUrl    = "$($serviceUrl.TrimEnd('/'))/wp-admin/"
    WordPressAdminUser   = $AdminUser
    AdminPasswordStatus  = 'Copied to the Windows clipboard'
} | Format-List
