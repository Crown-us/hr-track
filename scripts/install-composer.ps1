$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$toolsDirectory = Join-Path $projectRoot '.tools'
New-Item -ItemType Directory -Path $toolsDirectory -Force | Out-Null
$installer = Join-Path $toolsDirectory 'composer-setup.php'
Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile $installer
$expected = (Invoke-RestMethod -Uri 'https://composer.github.io/installer.sig').Trim()
$actual = (Get-FileHash -LiteralPath $installer -Algorithm SHA384).Hash.ToLowerInvariant()
if ($actual -ne $expected) { throw 'Hash installer Composer tidak cocok; instalasi dibatalkan.' }
& php $installer "--install-dir=$toolsDirectory" '--filename=composer.phar'
if ($LASTEXITCODE -ne 0) { throw 'Instalasi Composer gagal.' }
