Construye el ZIP de actualización del plugin WordPress "Aura Business Suite" para subir a Hostinger.

**Ruta local del plugin:**
c:\laragon\www\diserwp\wp-content\plugins\aura-business-suite\

**Reglas obligatorias del ZIP:**
1. El ZIP debe llamarse `aura-business-suite.zip` y guardarse en la raíz del plugin
2. Dentro del ZIP debe existir una carpeta raíz llamada `aura-business-suite/` (sin esto WordPress muestra "El archivo del plugin no existe")
3. NO incluir el directorio `vendor/` — ya está instalado en el servidor de Hostinger
4. Corregir BOM (Byte Order Mark) en todos los archivos .php, .js y .css antes de empaquetar

**Archivos/carpetas a incluir:**
- Archivos raíz: `aura-business-suite.php`, `composer.json`, `LICENSE`, `aura-icono.svg`
- Directorios completos: `assets/`, `modules/`, `templates/`
- NO incluir: `vendor/`, `aura-business-suite/` (carpeta de builds anteriores), `.venv/`, `*.zip`, archivos `.ps1`, `documentacion/`, `image/`

**Script PowerShell exacto para construir (ejecutar en terminal):**

$base = "c:\laragon\www\diserwp\wp-content\plugins\aura-business-suite"
$tmp  = "$env:TEMP\aura-build-$(Get-Random)"
$dest = "$tmp\aura-business-suite"
$zip  = "$base\aura-business-suite.zip"

if (Test-Path $zip) { Remove-Item $zip -Force }
New-Item -ItemType Directory -Path $dest -Force | Out-Null

foreach ($f in @('aura-business-suite.php','composer.json','LICENSE','aura-icono.svg')) {
    $src = "$base\$f"
    if (Test-Path $src) { Copy-Item $src "$dest\$f" }
}

foreach ($d in @('assets','modules','templates')) {
    Copy-Item "$base\$d" "$dest\$d" -Recurse -Force
}

$bomFixed = 0
Get-ChildItem "$dest" -Recurse -Include *.php,*.js,*.css | ForEach-Object {
    $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        [System.IO.File]::WriteAllBytes($_.FullName, $bytes[3..($bytes.Length-1)])
        $bomFixed++
    }
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($tmp, $zip, 'Optimal', $false)
Remove-Item $tmp -Recurse -Force

$z = [System.IO.Compression.ZipFile]::OpenRead($zip)
$total = $z.Entries.Count
$hasMain = ($z.Entries | Where-Object { $_.FullName -eq 'aura-business-suite/aura-business-suite.php' }) -ne $null
$hasVendor = ($z.Entries | Where-Object { $_.FullName -like 'aura-business-suite/vendor/*' }).Count
$z.Dispose()
$sizeMB = [math]::Round((Get-Item $zip).Length / 1MB, 2)
Write-Host "BOM corregidos: $bomFixed"
Write-Host "Entradas: $total | Main: $hasMain | Vendor: $hasVendor | Tamaño: ${sizeMB} MB"

**Verificación esperada:**
- Main: True (existe aura-business-suite/aura-business-suite.php)
- Vendor: 0 (sin archivos vendor)
- Tamaño aproximado: ~1.1 a 1.3 MB

**Para instalar en Hostinger:**
1. WordPress Admin → Plugins → Añadir nuevo → Subir plugin
2. Seleccionar `aura-business-suite.zip`
3. Instalar ahora → Reemplazar la instalación actual

**Nota:** Los scripts `build-zip.ps1` y `build-update-lite.ps1` que existen en el proyecto NO sirven:
- `build-zip.ps1` incluye vendor/ → demasiado pesado
- `build-update-lite.ps1` crea ZIP plano sin carpeta raíz → WordPress rechaza la activación
Siempre usar el script PowerShell inline de arriba.