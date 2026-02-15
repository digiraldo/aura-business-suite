# Script de diagnóstico para API REST
# Verifica conectividad, autenticación y estado de las rutas

$WP_URL = "https://diserwp.test"
$WP_USER = "di"
$WP_PASS = "mpqd yO6k HISt Ok1w 4mBB E6rV"  # Reemplaza con tu Application Password real

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "DIAGNÓSTICO DE API REST" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Test 1: Verificar acceso básico a wp-json (SIN autenticación)
Write-Host "TEST 1: Acceso básico a /wp-json/" -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "$WP_URL/wp-json/" -Method Get -UseBasicParsing
    Write-Host "✓ Status: $($response.StatusCode) - WordPress REST API responde" -ForegroundColor Green
    
    $json = $response.Content | ConvertFrom-Json
    if ($json.namespaces -contains "aura/v1") {
        Write-Host "✓ Namespace 'aura/v1' está registrado" -ForegroundColor Green
    } else {
        Write-Host "✗ Namespace 'aura/v1' NO está en la lista" -ForegroundColor Red
        Write-Host "  Namespaces disponibles:" -ForegroundColor Gray
        $json.namespaces | ForEach-Object { Write-Host "    - $_" -ForegroundColor Gray }
    }
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 2: Verificar ruta específica de aura/v1 (SIN autenticación)
Write-Host "TEST 2: Acceso a /wp-json/aura/v1 (sin auth)" -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "$WP_URL/wp-json/aura/v1" -Method Get -UseBasicParsing
    Write-Host "✓ Status: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "  Contenido:" -ForegroundColor Gray
    $response.Content | Write-Host -ForegroundColor Gray
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "  Status Code: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
}

Write-Host ""

# Test 3: Verificar autenticación
Write-Host "TEST 3: Verificar credenciales de autenticación" -ForegroundColor Yellow
if ($WP_PASS -eq "XXXX XXXX XXXX XXXX") {
    Write-Host "✗ DEBES CONFIGURAR EL APPLICATION PASSWORD" -ForegroundColor Red
    Write-Host "  Edita este script y reemplaza 'XXXX XXXX XXXX XXXX' con tu password real" -ForegroundColor Yellow
} else {
    Write-Host "✓ Application Password configurado (longitud: $($WP_PASS.Length) caracteres)" -ForegroundColor Green
    
    # Crear credenciales
    $base64AuthInfo = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${WP_USER}:${WP_PASS}"))
    $headers = @{
        "Authorization" = "Basic $base64AuthInfo"
    }
    
    # Probar autenticación con un endpoint protegido
    try {
        $response = Invoke-WebRequest -Uri "$WP_URL/wp-json/wp/v2/users/me" -Method Get -Headers $headers -UseBasicParsing
        Write-Host "✓ Autenticación EXITOSA" -ForegroundColor Green
        $user = $response.Content | ConvertFrom-Json
        Write-Host "  Usuario autenticado: $($user.name) (ID: $($user.id))" -ForegroundColor Green
    } catch {
        Write-Host "✗ Autenticación FALLIDA" -ForegroundColor Red
        Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "  Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
    }
}

Write-Host ""

# Test 4: Probar endpoint de categorías CON autenticación
Write-Host "TEST 4: Acceso a /wp-json/aura/v1/finance/categories (con auth)" -ForegroundColor Yellow
if ($WP_PASS -ne "XXXX XXXX XXXX XXXX") {
    $base64AuthInfo = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${WP_USER}:${WP_PASS}"))
    $headers = @{
        "Authorization" = "Basic $base64AuthInfo"
    }
    
    try {
        $response = Invoke-WebRequest -Uri "$WP_URL/wp-json/aura/v1/finance/categories" -Method Get -Headers $headers -UseBasicParsing
        Write-Host "✓ Status: $($response.StatusCode) - Endpoint responde correctamente" -ForegroundColor Green
        Write-Host "  Respuesta:" -ForegroundColor Gray
        $response.Content | ConvertFrom-Json | ConvertTo-Json -Depth 3 | Write-Host -ForegroundColor Gray
    } catch {
        Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "  Status: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Red
        
        if ($_.Exception.Response.StatusCode.value__ -eq 404) {
            Write-Host "`n  DIAGNÓSTICO DEL 404:" -ForegroundColor Yellow
            Write-Host "  - Las rutas están registradas en WordPress pero no responden" -ForegroundColor Yellow
            Write-Host "  - Esto suele ser un problema de permalinks o .htaccess" -ForegroundColor Yellow
        }
    }
} else {
    Write-Host "⊘ Test omitido - configura el Application Password primero" -ForegroundColor Gray
}

Write-Host ""

# Test 5: Verificar configuración de permalinks
Write-Host "TEST 5: Verificar estructura de permalinks" -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "$WP_URL/?rest_route=/wp/v2/posts" -Method Get -UseBasicParsing
    Write-Host "✓ WordPress acepta parámetro ?rest_route=" -ForegroundColor Green
    Write-Host "  Esto significa que los permalinks NO están configurados correctamente" -ForegroundColor Yellow
    Write-Host "  Ve a: $WP_URL/wp-admin/options-permalink.php" -ForegroundColor Yellow
    Write-Host "  Y selecciona 'Nombre de la entrada' o cualquier opción excepto 'Simple'" -ForegroundColor Yellow
} catch {
    if ($_.Exception.Response.StatusCode.value__ -eq 404) {
        Write-Host "✓ Los permalinks están configurados correctamente (pretty URLs)" -ForegroundColor Green
    }
}

Write-Host ""

# Test 6: Verificar .htaccess
Write-Host "TEST 6: Verificar archivo .htaccess" -ForegroundColor Yellow
$htaccess_path = "C:\laragon\www\diserwp\.htaccess"
if (Test-Path $htaccess_path) {
    Write-Host "✓ Archivo .htaccess existe" -ForegroundColor Green
    $content = Get-Content $htaccess_path -Raw
    if ($content -match "mod_rewrite") {
        Write-Host "✓ Contiene reglas de rewrite" -ForegroundColor Green
    } else {
        Write-Host "✗ No contiene reglas de rewrite (puede ser el problema)" -ForegroundColor Red
        Write-Host "  Ve a: $WP_URL/wp-admin/options-permalink.php" -ForegroundColor Yellow
        Write-Host "  Y guarda los permalinks para regenerar el .htaccess" -ForegroundColor Yellow
    }
} else {
    Write-Host "✗ Archivo .htaccess NO EXISTE" -ForegroundColor Red
    Write-Host "  Este es probablemente el problema del 404" -ForegroundColor Yellow
    Write-Host "  Ve a: $WP_URL/wp-admin/options-permalink.php" -ForegroundColor Yellow
    Write-Host "  Y guarda para crear el archivo automáticamente" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "RESUMEN Y RECOMENDACIONES" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Si todos los tests anteriores pasaron EXCEPTO el Test 4 (404):" -ForegroundColor Yellow
Write-Host "El problema es la configuración de permalinks de WordPress." -ForegroundColor Yellow
Write-Host ""
Write-Host "SOLUCIÓN:" -ForegroundColor Green
Write-Host "1. Ve a: $WP_URL/wp-admin/options-permalink.php" -ForegroundColor White
Write-Host "2. Selecciona 'Nombre de la entrada' (/%postname%/)" -ForegroundColor White
Write-Host "3. Click en 'Guardar cambios'" -ForegroundColor White
Write-Host "4. Verifica que se haya creado/actualizado el archivo .htaccess" -ForegroundColor White
Write-Host "5. Ejecuta nuevamente: .\test-api-rest.ps1" -ForegroundColor White
Write-Host ""
Read-Host "Presiona Enter para salir"
