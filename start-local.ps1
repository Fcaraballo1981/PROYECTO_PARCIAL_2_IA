param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Error 'No se encontró PHP. Instalá PHP 8.1 o posterior y agregalo al PATH.'
}

$phpVersion = [Version](php -r 'echo PHP_VERSION;')
if ($phpVersion -lt [Version]'8.1.0') {
    Write-Error "Se necesita PHP 8.1 o posterior. Versión encontrada: $phpVersion."
}

$requiredExtensions = @('curl', 'json', 'mbstring', 'session', 'fileinfo')
$loadedExtensions = @(php -m | ForEach-Object { $_.Trim().ToLowerInvariant() })
$missingExtensions = @($requiredExtensions | Where-Object { $_ -notin $loadedExtensions })
if ($missingExtensions.Count -gt 0) {
    Write-Error "Faltan extensiones de PHP: $($missingExtensions -join ', ')."
}

$requestedPort = $Port
while (Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue) {
    if ($Port -eq 65535) {
        Write-Error "No se encontro un puerto libre a partir del puerto $requestedPort."
    }
    $Port++
}

if ($Port -ne $requestedPort) {
    Write-Warning "El puerto $requestedPort esta ocupado. Se usara automaticamente el puerto $Port."
}

function Import-DotEnv([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return
    }

    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed.Length -eq 0 -or $trimmed.StartsWith('#')) {
            continue
        }

        if ($trimmed.StartsWith('export ')) {
            $trimmed = $trimmed.Substring(7).TrimStart()
        }

        $separator = $trimmed.IndexOf('=')
        if ($separator -le 0) {
            continue
        }

        $name = $trimmed.Substring(0, $separator).Trim()
        if ($name -notmatch '^[A-Za-z_][A-Za-z0-9_]*$') {
            continue
        }

        if (-not [string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($name, 'Process'))) {
            continue
        }

        $value = $trimmed.Substring($separator + 1).Trim()
        if ($value.Length -ge 2) {
            $first = $value[0]
            $last = $value[$value.Length - 1]
            if (($first -eq '"' -and $last -eq '"') -or ($first -eq "'" -and $last -eq "'")) {
                $value = $value.Substring(1, $value.Length - 2)
            }
        }

        [Environment]::SetEnvironmentVariable($name, $value, 'Process')
    }
}

$envFile = Join-Path $PSScriptRoot '.env'
Import-DotEnv $envFile

if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) {
    Write-Warning 'No se encontró .env. Copiá .env.example como .env y agregá tus claves.'
}

if ([string]::IsNullOrWhiteSpace($env:GEMINI_API_KEY)) {
    Write-Warning 'GEMINI_API_KEY no está configurada en .env. No habrá análisis automático.'
}

$url = "http://127.0.0.1:$Port"
Write-Host "`nServidor listo en $url" -ForegroundColor Green
Write-Host 'El análisis visual usa Gemini cuando GEMINI_API_KEY está configurada.' -ForegroundColor DarkGray
Write-Host 'Dejá esta ventana abierta. Para detenerlo, presioná Ctrl+C.' -ForegroundColor DarkGray

php -d extension=pdo_sqlite -d extension=sqlite3 -d upload_max_filesize=6M -d post_max_size=26M -d max_execution_time=180 -S "127.0.0.1:$Port" -t . router.php
