param([string]$MySqlBin = $env:HRIS_MYSQL_BIN)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$dataDirectory = Join-Path $projectRoot '.local\mysql-data'
if (-not $MySqlBin) {
    $localConfig = Join-Path $projectRoot '.local\runtime.json'
    if (Test-Path -LiteralPath $localConfig) {
        $MySqlBin = (Get-Content -Raw -LiteralPath $localConfig | ConvertFrom-Json).mysqlBin
    }
}
if (-not $MySqlBin -or -not (Test-Path -LiteralPath (Join-Path $MySqlBin 'mysqld.exe'))) {
    throw 'Tentukan folder bin MySQL dengan -MySqlBin atau HRIS_MYSQL_BIN. Lihat README.'
}
if (-not (Test-Path -LiteralPath (Join-Path $dataDirectory 'mysql'))) {
    throw 'Data MySQL lokal belum diinisialisasi. Skrip ini tidak membuat atau menimpa database.'
}
$listener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
if ($listener) {
    Write-Output 'Port 3307 sudah dipakai. Tidak menjalankan instance tambahan; cek php artisan migrate:status.'
    exit 0
}
$baseDirectory = Split-Path -Parent $MySqlBin
$arguments = @(
    '--no-defaults',
    "--basedir=`"$baseDirectory`"",
    "--datadir=`"$dataDirectory`"",
    '--bind-address=127.0.0.1',
    '--port=3307',
    '--mysqlx=OFF',
    "--log-error=`"$(Join-Path $projectRoot '.local\logs\mysql.log')`"",
    "--pid-file=`"$(Join-Path $projectRoot '.local\mysql.pid')`""
)
$process = Start-Process -FilePath (Join-Path $MySqlBin 'mysqld.exe') -ArgumentList $arguments -WindowStyle Hidden -PassThru
$deadline = [DateTime]::UtcNow.AddSeconds(30)
do {
    if (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue) {
        Write-Output 'MySQL menerima koneksi pada 127.0.0.1:3307. Periksa akun dengan php artisan migrate:status.'
        exit 0
    }
    if ($process.HasExited -and $process.ExitCode -ne 0) {
        throw 'MySQL gagal dimulai; periksa .local/logs/mysql.log.'
    }
    Start-Sleep -Milliseconds 500
} while ([DateTime]::UtcNow -lt $deadline)
throw 'MySQL belum menerima koneksi setelah 30 detik; periksa .local/logs/mysql.log.'
