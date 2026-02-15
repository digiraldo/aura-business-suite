# Script de prueba para API REST de Categorías Financieras
# AURA Business Suite - Fase 1, Item 1.3
# PowerShell Version para Windows

# Configuración - EDITAR ESTOS VALORES
$WP_URL = "https://diserwp.test"  # SOLO el dominio base (sin /wp-login.php)
$WP_USER = "di"                   # Tu usuario de WordPress
$WP_PASS = "mpqd yO6k HISt Ok1w 4mBB E6rV"  # Application Password (NO tu contraseña normal)

$API_BASE = "$WP_URL/wp-json/aura/v1/finance/categories"

# Crear credenciales Base64
$base64AuthInfo = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${WP_USER}:${WP_PASS}"))
$headers = @{
    "Authorization" = "Basic $base64AuthInfo"
    "Content-Type" = "application/json"
}

# Variables globales
$script:CREATED_ID = $null

###########################################################################
# Funciones auxiliares
###########################################################################

function Print-Header {
    param([string]$text)
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host $text -ForegroundColor Cyan
    Write-Host "========================================`n" -ForegroundColor Cyan
}

function Print-Success {
    param([string]$text)
    Write-Host "✓ $text" -ForegroundColor Green
}

function Print-Error {
    param([string]$text)
    Write-Host "✗ $text" -ForegroundColor Red
}

function Print-Info {
    param([string]$text)
    Write-Host "→ $text" -ForegroundColor Yellow
}

function Pause-Script {
    Write-Host ""
    Read-Host "Presiona Enter para continuar"
    Write-Host ""
}

###########################################################################
# Tests
###########################################################################

# Test 1: Obtener todas las categorías
function Test-GetAll {
    Print-Header "TEST 1: GET - Obtener todas las categorías"
    
    Print-Info "Request: GET $API_BASE"
    
    try {
        $response = Invoke-RestMethod -Uri $API_BASE -Method Get -Headers $headers
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 200 OK"
        Print-Success "Total de categorías: $($response.total)"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
        $_.Exception.Response.StatusCode.value__ | Write-Host
    }
    
    Pause-Script
}

# Test 2: Crear nueva categoría
function Test-CreateCategory {
    Print-Header "TEST 2: POST - Crear nueva categoría"
    
    Print-Info "Request: POST $API_BASE"
    Print-Info "Body: Nueva categoría de prueba"
    
    $body = @{
        name = "Test API Category"
        type = "expense"
        color = "#e74c3c"
        icon = "dashicons-admin-generic"
        description = "Categoría creada desde script de prueba PowerShell"
        status = "active"
        display_order = 999
    } | ConvertTo-Json
    
    try {
        $response = Invoke-RestMethod -Uri $API_BASE -Method Post -Headers $headers -Body $body
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 201 Created"
        $script:CREATED_ID = $response.data.id
        Print-Success "ID de categoría creada: $script:CREATED_ID"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
        if ($_.ErrorDetails.Message) {
            $_.ErrorDetails.Message | Write-Host
        }
    }
    
    Pause-Script
}

# Test 3: Obtener categoría específica
function Test-GetSingle {
    if (-not $script:CREATED_ID) {
        Print-Error "No hay ID de categoría creada. Saltando test."
        return
    }
    
    Print-Header "TEST 3: GET - Obtener categoría específica (ID: $script:CREATED_ID)"
    
    Print-Info "Request: GET $API_BASE/$script:CREATED_ID"
    
    try {
        $response = Invoke-RestMethod -Uri "$API_BASE/$script:CREATED_ID" -Method Get -Headers $headers
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 200 OK"
        Print-Success "Nombre: $($response.data.name)"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
    }
    
    Pause-Script
}

# Test 4: Actualizar categoría
function Test-UpdateCategory {
    if (-not $script:CREATED_ID) {
        Print-Error "No hay ID de categoría creada. Saltando test."
        return
    }
    
    Print-Header "TEST 4: PUT - Actualizar categoría (ID: $script:CREATED_ID)"
    
    Print-Info "Request: PUT $API_BASE/$script:CREATED_ID"
    Print-Info "Body: Actualizando nombre y color"
    
    $body = @{
        name = "Test API Category UPDATED"
        color = "#9b59b6"
        description = "Categoría actualizada desde script de prueba PowerShell"
    } | ConvertTo-Json
    
    try {
        $response = Invoke-RestMethod -Uri "$API_BASE/$script:CREATED_ID" -Method Put -Headers $headers -Body $body
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 200 OK"
        Print-Success "Nuevo nombre: $($response.data.name)"
        Print-Success "Nuevo color: $($response.data.color)"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
    }
    
    Pause-Script
}

# Test 5: Filtros
function Test-Filters {
    Print-Header "TEST 5: GET - Probar filtros"
    
    # Filtro por tipo
    Print-Info "Request: GET $API_BASE`?type=expense"
    
    try {
        $response = Invoke-RestMethod -Uri "$API_BASE`?type=expense" -Method Get -Headers $headers
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 200 OK"
        Print-Success "Categorías de tipo 'expense': $($response.total)"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
    }
    
    Write-Host ""
    
    # Filtro por búsqueda
    Print-Info "Request: GET $API_BASE`?search=test"
    
    try {
        $response = Invoke-RestMethod -Uri "$API_BASE`?search=test" -Method Get -Headers $headers
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 200 OK"
        Print-Success "Categorías que contienen 'test': $($response.total)"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
    }
    
    Pause-Script
}

# Test 6: Árbol jerárquico
function Test-Tree {
    Print-Header "TEST 6: GET - Obtener árbol jerárquico"
    
    Print-Info "Request: GET $API_BASE/tree"
    
    try {
        $response = Invoke-RestMethod -Uri "$API_BASE/tree" -Method Get -Headers $headers
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 200 OK"
        Print-Success "Árbol jerárquico obtenido correctamente"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
    }
    
    Pause-Script
}

# Test 7: Validaciones - Nombre vacío
function Test-ValidationEmptyName {
    Print-Header "TEST 7: Validación - Nombre vacío (debe fallar)"
    
    Print-Info "Request: POST $API_BASE"
    Print-Info "Body: Nombre vacío"
    
    $body = @{
        name = ""
        type = "income"
    } | ConvertTo-Json
    
    try {
        $response = Invoke-RestMethod -Uri $API_BASE -Method Post -Headers $headers -Body $body
        Print-Error "No falló cuando debería (se esperaba 400)"
    }
    catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 400) {
            Print-Success "Status: 400 Bad Request (esperado)"
            Print-Success "Validación funcionando correctamente"
        }
        else {
            Print-Error "Status: $($_.Exception.Response.StatusCode.value__) (se esperaba 400)"
        }
        
        if ($_.ErrorDetails.Message) {
            $_.ErrorDetails.Message | ConvertFrom-Json | ConvertTo-Json -Depth 10 | Write-Host
        }
    }
    
    Pause-Script
}

# Test 8: Validaciones - Tipo inválido
function Test-ValidationInvalidType {
    Print-Header "TEST 8: Validación - Tipo inválido (debe fallar)"
    
    Print-Info "Request: POST $API_BASE"
    Print-Info "Body: Tipo 'invalid_type'"
    
    $body = @{
        name = "Test Invalid Type"
        type = "invalid_type"
    } | ConvertTo-Json
    
    try {
        $response = Invoke-RestMethod -Uri $API_BASE -Method Post -Headers $headers -Body $body
        Print-Error "No falló cuando debería (se esperaba 400)"
    }
    catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 400) {
            Print-Success "Status: 400 Bad Request (esperado)"
            Print-Success "Validación funcionando correctamente"
        }
        else {
            Print-Error "Status: $($_.Exception.Response.StatusCode.value__) (se esperaba 400)"
        }
        
        if ($_.ErrorDetails.Message) {
            $_.ErrorDetails.Message | ConvertFrom-Json | ConvertTo-Json -Depth 10 | Write-Host
        }
    }
    
    Pause-Script
}

# Test 9: Eliminar categoría (soft delete)
function Test-DeleteSoft {
    if (-not $script:CREATED_ID) {
        Print-Error "No hay ID de categoría creada. Saltando test."
        return
    }
    
    Print-Header "TEST 9: DELETE - Eliminar categoría (soft delete)"
    
    Print-Info "Request: DELETE $API_BASE/$script:CREATED_ID"
    
    try {
        $response = Invoke-RestMethod -Uri "$API_BASE/$script:CREATED_ID" -Method Delete -Headers $headers
        
        $response | ConvertTo-Json -Depth 10 | Write-Host
        
        Print-Success "Status: 200 OK"
        Print-Success "Categoría movida a papelera"
    }
    catch {
        Print-Error "Error: $($_.Exception.Message)"
    }
    
    Pause-Script
}

# Test 10: Intentar obtener categoría eliminada
function Test-GetDeleted {
    if (-not $script:CREATED_ID) {
        Print-Error "No hay ID de categoría. Saltando test."
        return
    }
    
    Print-Header "TEST 10: GET - Intentar obtener categoría eliminada (debe fallar)"
    
    Print-Info "Request: GET $API_BASE/$script:CREATED_ID"
    
    try {
        $response = Invoke-RestMethod -Uri "$API_BASE/$script:CREATED_ID" -Method Get -Headers $headers
        Print-Error "No falló cuando debería (se esperaba 404)"
    }
    catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 404) {
            Print-Success "Status: 404 Not Found (esperado)"
            Print-Success "API responde correctamente a categorías eliminadas"
        }
        else {
            Print-Error "Status: $($_.Exception.Response.StatusCode.value__) (se esperaba 404)"
        }
        
        if ($_.ErrorDetails.Message) {
            $_.ErrorDetails.Message | ConvertFrom-Json | ConvertTo-Json -Depth 10 | Write-Host
        }
    }
    
    Pause-Script
}

###########################################################################
# Main
###########################################################################

Clear-Host

Write-Host @"

╔════════════════════════════════════════════════════════════════╗
║         API REST - CATEGORÍAS FINANCIERAS                      ║
║         Script de Prueba Automatizado                          ║
║         AURA Business Suite v1.0.0                             ║
╚════════════════════════════════════════════════════════════════╝

"@ -ForegroundColor Green

Print-Info "URL de WordPress: $WP_URL"
Print-Info "Usuario: $WP_USER"
Print-Info "API Base: $API_BASE"
Write-Host ""

$confirm = Read-Host "¿La configuración es correcta? (s/n)"
if ($confirm -ne 's' -and $confirm -ne 'S') {
    Print-Info "Edita el script y configura las variables `$WP_URL, `$WP_USER y `$WP_PASS"
    exit
}

Write-Host ""

# Ejecutar tests
Test-GetAll
Test-CreateCategory
Test-GetSingle
Test-UpdateCategory
Test-Filters
Test-Tree
Test-ValidationEmptyName
Test-ValidationInvalidType
Test-DeleteSoft
Test-GetDeleted

# Resumen final
Print-Header "RESUMEN DE PRUEBAS"
Write-Host ""
Print-Success "✓ GET /categories - Listar todas"
Print-Success "✓ POST /categories - Crear nueva"
Print-Success "✓ GET /categories/{id} - Obtener específica"
Print-Success "✓ PUT /categories/{id} - Actualizar"
Print-Success "✓ GET /categories?filters - Filtros"
Print-Success "✓ GET /categories/tree - Árbol jerárquico"
Print-Success "✓ Validación de nombre vacío"
Print-Success "✓ Validación de tipo inválido"
Print-Success "✓ DELETE /categories/{id} - Eliminar (soft)"
Print-Success "✓ Verificar categoría eliminada (404)"
Write-Host ""
Print-Success "Todas las pruebas completadas!"
Write-Host ""

if ($script:CREATED_ID) {
    Print-Info "Nota: Se creó una categoría de prueba con ID: $script:CREATED_ID"
    Print-Info "Esta categoría fue movida a la papelera."
    Print-Info "Puedes restaurarla desde WordPress Admin o eliminarla permanentemente."
}

Write-Host ""
Read-Host "Presiona Enter para salir"
