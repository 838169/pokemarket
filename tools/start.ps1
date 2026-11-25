param(
    [ValidateSet('all', 'web', 'pokemon', 'trainer', 'notifications')][string]$Service = 'all',
    [string]$Php = 'C:\xampp\php\php.exe',
    [switch]$Lan,
    [switch]$Continuous
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path -LiteralPath $Php)) { $Php = (Get-Command php -ErrorAction Stop).Source }
$notificationScript = Join-Path $PSScriptRoot 'notificaciones.php'
if ($Continuous -and $Service -ne 'all') { throw 'Usa -Continuous para administrar el conjunto de servicios.' }
if ($Service -eq 'notifications') {
    & $Php $notificationScript
    exit $LASTEXITCODE
}
$bindAddress = 'localhost'
if ($Lan) {
    $bindAddress = '0.0.0.0'
    Remove-Item Env:POKEMARKET_LAN_CLIENT -ErrorAction SilentlyContinue
    $addresses = @(foreach ($adapter in [Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces()) {
        if ($adapter.OperationalStatus -ne 'Up') { continue }
        $network = $adapter.GetIPProperties()
        if ($network.GatewayAddresses.Address.AddressFamily -notcontains 'InterNetwork') { continue }
        $network.UnicastAddresses | Where-Object { $_.Address.AddressFamily -eq 'InterNetwork' }
    })
    if (-not $addresses) { throw 'No hay una red IPv4 conectada.' }
    $env:POKEMARKET_LAN = '1'
    $env:POKEMARKET_LAN_SUBNETS = ($addresses | ForEach-Object { "$($_.Address)/$($_.PrefixLength)" }) -join ','
    $ports = if ($Service -eq 'all') { @(8000,8001,8002) } else { @(@{web=8000;pokemon=8001;trainer=8002}[$Service]) }
    $occupied = [Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners() |
        Where-Object { $_.Port -in $ports }
    if ($occupied) {
        $occupiedPorts = ($occupied.Port | Sort-Object -Unique) -join ', '
        throw "Puertos ocupados: $occupiedPorts. Pulsa Ctrl+C en la consola donde iniciaste Pokemarket y vuelve a ejecutar con -Lan."
    }
    foreach ($address in $addresses) { Write-Host "Abre http://$($address.Address):8000 desde la misma red" }
}
$definitions = @{
    web = @{ Port = 8000; Folder = $projectRoot; Public = (Join-Path $projectRoot 'frontend'); Router = (Join-Path $PSScriptRoot 'frontend-router.php') }
    pokemon = @{ Port = 8001; Folder = (Join-Path $projectRoot 'backend/pokemon-service'); Public = 'public'; Router = 'public/router.php' }
    trainer = @{ Port = 8002; Folder = (Join-Path $projectRoot 'backend/trainer-service'); Public = 'public'; Router = 'public/router.php' }
}
if ($Service -ne 'all') {
    $definition = $definitions[$Service]
    $publicPath = if ([IO.Path]::IsPathRooted($definition.Public)) { $definition.Public } else { Join-Path $definition.Folder $definition.Public }
    $routerPath = if ([IO.Path]::IsPathRooted($definition.Router)) { $definition.Router } else { Join-Path $definition.Folder $definition.Router }
    $serviceAddress = if ($Service -eq 'web') { $bindAddress } else { 'localhost' }
    & $Php -S "${serviceAddress}:$($definition.Port)" -t $publicPath $routerPath
    exit $LASTEXITCODE
}
$operation = Join-Path $projectRoot '.local-backups/operations'
$running = @{}
$pipeline = $null
$active = $null
$attempted = $null
$healthLast = @{}
$nextProbe = [datetime]::MinValue
$listeners = [Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties()

function Write-Operation([string]$Message) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    Add-Content -LiteralPath (Join-Path $operation 'operations.log') -Value $line -Encoding UTF8
    Write-Host $line
}

function Stop-ManagedServices {
    foreach ($process in $running.Values) {
        if (-not $process.HasExited) { Stop-Process -Id $process.Id; $process.WaitForExit() }
    }
    $running.Clear()
}

function Start-ManagedService([string]$Name, [string]$Folder) {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
    $worker = $Name -eq 'notifications'
    $serviceAddress = if ($Name -eq 'web') { $bindAddress } else { 'localhost' }
    $port = $definitions[$Name].Port
    if ($worker) {
        $arguments = '"' + (Join-Path $Folder 'tools/notificaciones.php') + '"'
    } else {
        $public = if ($Name -eq 'web') { 'frontend' } else { "backend/$Name-service/public" }
        $router = if ($Name -eq 'web') { 'tools/frontend-router.php' } else { "$public/router.php" }
        $arguments = @('-S', "${serviceAddress}:$port", '-t',
            ('"' + (Join-Path $Folder $public) + '"'), ('"' + (Join-Path $Folder $router) + '"'))
    }
    $savedEnvFile = $env:POKEMARKET_ENV_FILE
    try {
        $env:POKEMARKET_ENV_FILE = Join-Path $projectRoot '.env'
        $running[$Name] = Start-Process -FilePath $Php -ArgumentList $arguments -WorkingDirectory $Folder -WindowStyle Hidden -PassThru `
            -RedirectStandardOutput (Join-Path $operation "$Name-$stamp.out.log") -RedirectStandardError (Join-Path $operation "$Name-$stamp.err.log")
    } finally {
        $env:POKEMARKET_ENV_FILE = $savedEnvFile
    }
    Write-Operation "PROCESO $Name iniciado (PID $($running[$Name].Id))."
}

function Read-Health([string]$Path) {
    $timer = [Diagnostics.Stopwatch]::StartNew()
    $status = 'DOWN'
    $requestId = ''
    $probeHost = if ($Lan) { '127.0.0.1' } else { 'localhost' }
    try {
        $result = Invoke-WebRequest -Uri "http://${probeHost}:8000$Path" -UseBasicParsing -TimeoutSec 5
        $status = "HTTP $($result.StatusCode)"
        $id = ($result.Content | ConvertFrom-Json -ErrorAction SilentlyContinue).request_id
        if ($id -match '^[a-f0-9]{16}$') { $requestId = " request_id=$id" }
    } catch {
        if ($_.Exception.Response) { $status = "HTTP $([int]$_.Exception.Response.StatusCode)" }
    }
    @{ Status = $status; Detail = "$($timer.ElapsedMilliseconds) ms$requestId" }
}

New-Item -ItemType Directory -Path $operation -Force | Out-Null
try {
    $occupied = @($listeners.GetActiveTcpListeners() | Where-Object { $_.Port -in @(8000,8001,8002) })
    if ($occupied) { throw "Detén primero los procesos anteriores. Puertos ocupados: $($occupied.Port -join ', ')." }
    if ($Continuous) {
        $node = (Get-Command node -ErrorAction Stop).Source
        $savedPhp = $env:PHP_BINARY
        try {
            $env:PHP_BINARY = $Php
            $pipeline = Start-Process -FilePath $node -ArgumentList ('"' + (Join-Path $PSScriptRoot 'deploy-local.mjs') + '"') -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru `
                -RedirectStandardOutput (Join-Path $operation 'ci.log') -RedirectStandardError (Join-Path $operation 'ci-errors.log')
        } finally {
            $env:PHP_BINARY = $savedPhp
        }
        Write-Operation 'CI/CD activo. La primera versión se inicia después de pasar las pruebas.'
    } else {
        $active = $projectRoot
    }
    $services = @('pokemon', 'trainer', 'web', 'notifications')
    $checks = [ordered]@{ pokemon = '/api/pokemon/health'; trainer = '/api/entrenadores/health' }
    while ($true) {
        if ($Continuous) {
            if ($pipeline.HasExited) { throw 'El proceso de CI terminó. Revisa ci-errors.log.' }
            $version = Get-Content -LiteralPath (Join-Path $operation 'release.txt') -Raw -ErrorAction SilentlyContinue
            if ($version -match '^[0-9a-f-]{36}$' -and $version -ne $attempted) {
                $attempted = $version
                $release = Join-Path $operation "releases/$version"
                if (Test-Path -LiteralPath (Join-Path $release 'tools/frontend-router.php')) {
                    $previous = $active
                    Stop-ManagedServices
                    try {
                        foreach ($name in $services) { Start-ManagedService $name $release }
                        $ready = $false
                        for ($attempt = 0; $attempt -lt 5; $attempt++) {
                            Start-Sleep -Milliseconds 500
                            $failed = @($services | Where-Object { $running[$_].HasExited })
                            if ($failed) { throw "Procesos detenidos: $($failed -join ', '). Revisa sus registros." }
                            if ((Read-Health '/login.php').Status -eq 'HTTP 200') {
                                $ready = $true
                                break
                            }
                        }
                        if (!$ready) { throw 'El login no respondió HTTP 200 en la comprobación local.' }
                        $active = $release
                        $healthLast.Clear()
                        Write-Operation "CD OK · Desplegada la versión $version."
                    } catch {
                        Stop-ManagedServices
                        $active = $previous
                        Write-Operation "CD FAIL · Se conserva la versión anterior. $($_.Exception.Message)"
                    }
                }
            }
        }
        if ($active) {
            foreach ($name in $services) {
                if ($running.ContainsKey($name) -and $running[$name].HasExited) {
                    Write-Operation "CAÍDA $name · PID $($running[$name].Id). Reiniciando."
                    $running.Remove($name)
                }
                if (-not $running.ContainsKey($name)) { Start-ManagedService $name $active }
            }
            if ([datetime]::UtcNow -ge $nextProbe) {
                $healthLines = foreach ($name in $checks.Keys) {
                    $health = Read-Health $checks[$name]
                    $line = "$name $($health.Status) $($health.Detail)"
                    if ($healthLast[$name] -ne $health.Status) { Write-Operation "SALUD $line" }
                    $healthLast[$name] = $health.Status
                    $line
                }
                $healthLines | Set-Content -LiteralPath (Join-Path $operation 'health.txt') -Encoding UTF8
                $nextProbe = [datetime]::UtcNow.AddSeconds(10)
            }
        }
        Start-Sleep -Seconds 2
    }
} finally {
    if ($pipeline -and -not $pipeline.HasExited) { Stop-Process -Id $pipeline.Id }
    Stop-ManagedServices
}
