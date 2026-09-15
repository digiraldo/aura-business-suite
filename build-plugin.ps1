<#
.SYNOPSIS
Script para empaquetar el plugin Aura Business Suite.

.DESCRIPTION
Este script recopila EXCLUSIVAMENTE los archivos de producción necesarios para que el plugin
funcione en WordPress. Excluye cualquier archivo de entorno de desarrollo (.git, Node, Composer, IDEs, etc.),
archivos de documentación interna (.md), copias de seguridad (.zip) y carpetas irrelevantes.
Genera "aura-business-suite.zip" con la estructura adecuada (carpeta base incluida)
para que WordPress reconozca el zip como una *Actualización* del plugin.
#>

$pluginName = "aura-business-suite"
$sourcePath = $PSScriptRoot
$parentPath = Split-Path $sourcePath -Parent
$zipName = "$pluginName.zip"
$zipPath = Join-Path $sourcePath $zipName

$tempBase = Join-Path $sourcePath "temp-build-pkg"
$tempPluginDir = Join-Path $tempBase $pluginName

Write-Host "Iniciando empaquetado optimizado del plugin '$pluginName'..." -ForegroundColor Cyan

# Limpiar restos anteriores si existen
if (Test-Path $tempBase) { Remove-Item -Recurse -Force $tempBase }
if (Test-Path $zipPath) { Remove-Item -Force $zipPath }

# Crear estructura temporal (con el nombre del plugin root para WP)
New-Item -ItemType Directory -Force -Path $tempPluginDir | Out-Null

<# 
  =============================================================
  DEFINICIÓN DE REGLAS DE EXCLUSIÓN
  =============================================================
#>

$excludePatterns = @(
    # Control de versiones
    ".git", ".gitignore", ".gitattributes",
    
    # Dependencias de desarrollo / Entornos virtuales
    "node_modules", "vendor", ".venv", "venv", "env",
    
    # Archivos de configuración de herramientas (si hubiese)
    "composer.json", "composer.lock", "package.json", "package-lock.json",
    "webpack.*.js", "gulpfile.js", "Gruntfile.js", "phpcs.xml", "phpunit.xml",
    ".eslintrc*", ".prettierrc*", "tsconfig.json",
    
    # IDEs y sistemas de archivos
    ".vscode", ".idea", ".DS_Store", "Thumbs.db", ".gemini",

    # Archivos comprimidos, logs, copias
    "*.zip", "*.tar", "*.gz", "*.rar", "*.log", "*.bak", "*.tmp", "error_log",
    
    # Documentación interna y diseño
    "documentacion", "docs", "*.md", "PRD*", "prd*",
    
    # El propio script de compilación y temp folder
    "build-plugin.ps1", "temp-build-pkg"
)

# Patrones para asegurar que el Copy-Item no capture basura oculta,
# aunque $excludePatterns captura mucho, un Where-Object puede refinarlo si es necesario.

Write-Host "Copiando archivos de producción y filtrando recursos de diseño/desarrollo..."

Get-ChildItem -Path $sourcePath -Exclude $excludePatterns | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination $tempPluginDir -Recurse -Force
}

# Verificación opcional post-copia: Limpiar carpetas vacías si las hay
# (A veces copy-item crea los directorios padres si excluye todo el contenido en un directorio profundo)
Get-ChildItem -Path $tempPluginDir -Recurse -Directory | Where-Object { 
    @(Get-ChildItem -Path $_.FullName -Force).Count -eq 0 
} | Remove-Item -Force -Recurse -ErrorAction SilentlyContinue

Write-Host "Archivos filtrados exitosamente."

# Comprimir el directorio temporal que contiene la carpeta 'aura-business-suite'
Write-Host "Comprimiendo estructura para WordPress (esto tomará unos segundos)..." -ForegroundColor Yellow
Compress-Archive -Path $tempPluginDir -DestinationPath $zipPath -CompressionLevel Optimal

# Limpieza final
Write-Host "Limpiando archivos temporales..."
Remove-Item -Recurse -Force $tempBase

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "¡Empaquetado completado exitosamente!" -ForegroundColor Green
Write-Host "Archivo generado : $zipPath" -ForegroundColor White
Write-Host "Tamaño : $((Get-Item $zipPath).Length / 1MB).ToString('0.00') MB" -ForegroundColor Yellow
Write-Host "Este archivo está listo para ser subido a WordPress " 
Write-Host "y REEMPLAZARÁ la versión existente conservando los datos."
Write-Host "==========================================================" -ForegroundColor Green
