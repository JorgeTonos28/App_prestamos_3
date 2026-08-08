param(
    [string] $LocalUrl = 'http://127.0.0.1:8001'
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectRoot '.env'

function Set-EnvValue {
    param(
        [Parameter(Mandatory = $true)] [string] $Key,
        [Parameter(Mandatory = $true)] [AllowEmptyString()] [string] $Value
    )

    $content = [System.IO.File]::ReadAllText($envPath)
    $line = "${Key}=${Value}"
    $pattern = '(?m)^' + [regex]::Escape($Key) + '=.*$'

    if ([regex]::IsMatch($content, $pattern)) {
        $content = [regex]::Replace($content, $pattern, $line)
    } else {
        if ($content.Length -gt 0 -and -not $content.EndsWith("`n")) {
            $content += [Environment]::NewLine
        }

        $content += $line + [Environment]::NewLine
    }

    [System.IO.File]::WriteAllText(
        $envPath,
        $content,
        (New-Object System.Text.UTF8Encoding($false))
    )
}

function Get-EnvValue {
    param([Parameter(Mandatory = $true)] [string] $Key)

    $content = [System.IO.File]::ReadAllText($envPath)
    $match = [regex]::Match($content, '(?m)^' + [regex]::Escape($Key) + '=(.*)$')

    if (-not $match.Success) {
        return ''
    }

    return $match.Groups[1].Value.Trim()
}

if (-not (Test-Path -LiteralPath $envPath)) {
    throw "No se encontró el archivo .env en $projectRoot."
}

$command = Get-Command cloudflared.exe -ErrorAction SilentlyContinue
$candidates = @(
    $(if ($command) { $command.Source }),
    "${env:ProgramFiles(x86)}\cloudflared\cloudflared.exe",
    "$env:ProgramFiles\cloudflared\cloudflared.exe"
) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }
$cloudflared = $candidates | Select-Object -First 1

if (-not $cloudflared) {
    throw 'cloudflared no está instalado. Ejecuta: winget install --id Cloudflare.cloudflared --exact'
}

try {
    Invoke-WebRequest -Uri $LocalUrl -UseBasicParsing -TimeoutSec 5 | Out-Null
} catch {
    throw "PRESTO no responde en $LocalUrl. Inicia primero: php artisan serve --host=127.0.0.1 --port=8001"
}

$webhookToken = Get-EnvValue -Key 'LABSMOBILE_WEBHOOK_TOKEN'
if ([string]::IsNullOrWhiteSpace($webhookToken)) {
    $bytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    } finally {
        $generator.Dispose()
    }

    $webhookToken = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
    Set-EnvValue -Key 'LABSMOBILE_WEBHOOK_TOKEN' -Value $webhookToken
    Write-Host 'Se generó LABSMOBILE_WEBHOOK_TOKEN en .env.' -ForegroundColor Green
}

$configured = $false

Write-Host "Abriendo túnel HTTPS hacia $LocalUrl..." -ForegroundColor Cyan
Write-Host 'Mantén esta ventana abierta mientras pruebas ACK. Presiona Ctrl+C para cerrar.' -ForegroundColor Yellow

try {
    & $cloudflared tunnel --url $LocalUrl --no-autoupdate 2>&1 | ForEach-Object {
        $line = $_.ToString()
        Write-Host $line

        if (-not $configured -and $line -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
            $publicUrl = $matches[0]
            $ackUrl = "$publicUrl/webhooks/labsmobile/delivery"
            Set-EnvValue -Key 'LABSMOBILE_ACK_URL' -Value $ackUrl

            Push-Location $projectRoot
            try {
                & php artisan config:clear | Out-Null
                if ($LASTEXITCODE -ne 0) {
                    throw 'Laravel no pudo limpiar la caché de configuración.'
                }
            } finally {
                Pop-Location
            }

            $configured = $true
            Write-Host ''
            Write-Host 'ACK local activo.' -ForegroundColor Green
            Write-Host "URL pública: $ackUrl"
            Write-Host 'No copies el token privado en pantallas ni mensajes.' -ForegroundColor Yellow
            Write-Host ''
        }
    }
} finally {
    if ($configured) {
        Set-EnvValue -Key 'LABSMOBILE_ACK_URL' -Value ''

        Push-Location $projectRoot
        try {
            & php artisan config:clear | Out-Null
        } finally {
            Pop-Location
        }

        Write-Host 'Túnel cerrado; LABSMOBILE_ACK_URL quedó desactivada en .env.' -ForegroundColor Yellow
    }
}
