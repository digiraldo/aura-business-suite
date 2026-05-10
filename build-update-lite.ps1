<#
.SYNOPSIS
Build script for FLAT LITE update of Aura Business Suite (No root folder in ZIP)
Version: 1.7.5
#>

$pluginName = "aura-business-suite"
$sourcePath = $PSScriptRoot
$zipPath = "$sourcePath\$pluginName.zip"

$tempBase = "$sourcePath\temp-build-lite"
$tempPluginDir = "$tempBase\$pluginName"

Clear-Host
Write-Host "==============================================================="
Write-Host " STARTING FLAT LITE BUILD (NO ROOT FOLDER) - v1.7.5            "
Write-Host "==============================================================="
Write-Host "Preparing temporary workspace..."

# 1. Initial cleanup
if (Test-Path $tempBase) { Remove-Item -Recurse -Force $tempBase -ErrorAction SilentlyContinue | Out-Null }
if (Test-Path $zipPath)  { Remove-Item -Force $zipPath -ErrorAction SilentlyContinue | Out-Null }

# Create directory structure
New-Item -ItemType Directory -Force -Path $tempPluginDir | Out-Null

# 2. CORE WHITELIST
$whitelistNodes = @(
    "aura-business-suite.php",
    "assets",
    "modules",
    "templates",
    "includes",
    "composer.json", 
    "LICENSE",
    "aura-icono.svg",
    "favicon.ico",
    "favicon.png"
)

Write-Host "Copying core system files..."

foreach ($node in $whitelistNodes) {
    $nodePath = "$sourcePath\$node"
    if (Test-Path $nodePath) {
        if ((Get-Item $nodePath).PSIsContainer) {
            Copy-Item -Path $nodePath -Destination "$tempPluginDir" -Recurse -Force
        } else {
            Copy-Item -Path $nodePath -Destination "$tempPluginDir" -Force
        }
    }
}

# 3. DEEP CLEANUP
Write-Host "Performing deep cleanup..." -ForegroundColor Gray
$cleanupPatterns = @("*.md", "*.zip", "*.log", "*.bak", ".git", ".github", ".venv", ".vscode", "composer.lock", "build-*.ps1")
foreach ($pattern in $cleanupPatterns) {
    Get-ChildItem -Path $tempPluginDir -Recurse -Include $pattern -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

# 4. ZIP Generation (FLAT STRUCTURE)
Write-Host "Compressing source into FLAT ZIP (Files at root)..." -ForegroundColor Yellow

# IMPORTANT: To create a FLAT zip, we must be INSIDE the directory and target '*'
Push-Location $tempPluginDir
try {
    # El destino debe ser una ruta absoluta fuera de la carpeta que estamos zipeando
    Compress-Archive -Path * -DestinationPath $zipPath -CompressionLevel Optimal
} finally {
    Pop-Location
}

# 5. Final cleanup
if (Test-Path $tempBase) { Remove-Item -Recurse -Force $tempBase -ErrorAction SilentlyContinue | Out-Null }

if (Test-Path $zipPath) {
    $sizeMB = (Get-Item $zipPath).Length / 1MB
    $roundedSize = [math]::Round($sizeMB, 2)
    Write-Host "==============================================================="
    Write-Host " FLAT LITE ZIP GENERATED SUCCESSFULLY!" -ForegroundColor Green
    Write-Host "==============================================================="
    Write-Host " Location : $zipPath"
    Write-Host " Size     : $roundedSize MB"
    Write-Host " Structure: FLAT (Files at root level)"
    Write-Host "==============================================================="
    Write-Host " INSTRUCCIONES:"
    Write-Host " 1. Sube este ZIP."
    Write-Host " 2. WordPress detectará que NO hay carpeta raíz y creará una."
    Write-Host " 3. Dado que el ZIP se llama '$pluginName.zip', WP lo pondrá en la carpeta correcta."
} else {
    Write-Host " ERROR: Failed to generate ZIP file. " -ForegroundColor Red
}
