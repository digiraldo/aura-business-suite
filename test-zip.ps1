$pluginName = "test-plugin"
$tempBase = "$PSScriptRoot\test-temp"
$tempPluginDir = "$tempBase\$pluginName"
mkdir $tempPluginDir
"hello" > "$tempPluginDir\test.txt"
Compress-Archive -Path $tempPluginDir -DestinationPath "$PSScriptRoot\test.zip" -Force
Get-ChildItem "$PSScriptRoot\test.zip" | Select-Object -ExpandProperty Name
# To see contents we need another way as Get-ChildItem only shows the zip file itself
Expand-Archive -Path "$PSScriptRoot\test.zip" -DestinationPath "$PSScriptRoot\test-unzipped"
Get-ChildItem -Path "$PSScriptRoot\test-unzipped" -Recurse
rm -Recurse -Force "$PSScriptRoot\test-temp", "$PSScriptRoot\test.zip", "$PSScriptRoot\test-unzipped"
