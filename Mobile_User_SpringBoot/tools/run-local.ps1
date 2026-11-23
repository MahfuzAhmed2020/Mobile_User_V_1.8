param([switch]$Test)
$ErrorActionPreference='Stop'
$project=Split-Path $PSScriptRoot -Parent
$config=Join-Path $project '.runtime/local-env.json'
if(Test-Path -LiteralPath $config){
 (Get-Content -LiteralPath $config -Raw | ConvertFrom-Json).PSObject.Properties | ForEach-Object { [Environment]::SetEnvironmentVariable($_.Name,[string]$_.Value,'Process') }
}
Push-Location $project
try{if($Test){mvn -B -ntp test}else{java -jar target/mobile-user-1.0.0.jar}}finally{Pop-Location}
