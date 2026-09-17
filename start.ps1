$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$waasabiPhp = 'E:\xampp\php\php.exe'
if (!(Test-Path -LiteralPath $waasabiPhp)) { $waasabiPhp = (Get-Command php -CommandType Application).Source }
& $waasabiPhp artisan serve --host=127.0.0.1 --port=8081
