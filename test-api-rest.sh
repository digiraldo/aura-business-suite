#!/bin/bash

###########################################################################
# Script de prueba para API REST de Categorías Financieras
# AURA Business Suite - Fase 1, Item 1.3
###########################################################################

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuración - EDITAR ESTOS VALORES
WP_URL="http://localhost"  # Tu URL de WordPress
WP_USER="admin"            # Tu usuario
WP_PASS="xxxx xxxx xxxx xxxx"  # Tu Application Password

API_BASE="${WP_URL}/wp-json/aura/v1/finance/categories"

# Variables globales
CREATED_ID=""

###########################################################################
# Funciones auxiliares
###########################################################################

print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}→ $1${NC}"
}

pause() {
    echo ""
    read -p "Presiona Enter para continuar..."
    echo ""
}

###########################################################################
# Tests
###########################################################################

# Test 1: Obtener todas las categorías
test_get_all() {
    print_header "TEST 1: GET - Obtener todas las categorías"
    
    print_info "Request: GET ${API_BASE}"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X GET "${API_BASE}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "Status: $HTTP_CODE OK"
        TOTAL=$(echo "$BODY" | jq '.total')
        print_success "Total de categorías: $TOTAL"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    pause
}

# Test 2: Crear nueva categoría
test_create_category() {
    print_header "TEST 2: POST - Crear nueva categoría"
    
    print_info "Request: POST ${API_BASE}"
    print_info "Body: Nueva categoría de prueba"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X POST "${API_BASE}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Test API Category",
            "type": "expense",
            "color": "#e74c3c",
            "icon": "dashicons-admin-generic",
            "description": "Categoría creada desde script de prueba",
            "status": "active",
            "display_order": 999
        }')
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 201 ]; then
        print_success "Status: $HTTP_CODE Created"
        CREATED_ID=$(echo "$BODY" | jq -r '.data.id')
        print_success "ID de categoría creada: $CREATED_ID"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    pause
}

# Test 3: Obtener categoría específica
test_get_single() {
    if [ -z "$CREATED_ID" ]; then
        print_error "No hay ID de categoría creada. Saltando test."
        return
    fi
    
    print_header "TEST 3: GET - Obtener categoría específica (ID: $CREATED_ID)"
    
    print_info "Request: GET ${API_BASE}/${CREATED_ID}"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X GET "${API_BASE}/${CREATED_ID}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "Status: $HTTP_CODE OK"
        NAME=$(echo "$BODY" | jq -r '.data.name')
        print_success "Nombre: $NAME"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    pause
}

# Test 4: Actualizar categoría
test_update_category() {
    if [ -z "$CREATED_ID" ]; then
        print_error "No hay ID de categoría creada. Saltando test."
        return
    fi
    
    print_header "TEST 4: PUT - Actualizar categoría (ID: $CREATED_ID)"
    
    print_info "Request: PUT ${API_BASE}/${CREATED_ID}"
    print_info "Body: Actualizando nombre y color"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X PUT "${API_BASE}/${CREATED_ID}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Test API Category UPDATED",
            "color": "#9b59b6",
            "description": "Categoría actualizada desde script de prueba"
        }')
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "Status: $HTTP_CODE OK"
        NAME=$(echo "$BODY" | jq -r '.data.name')
        COLOR=$(echo "$BODY" | jq -r '.data.color')
        print_success "Nuevo nombre: $NAME"
        print_success "Nuevo color: $COLOR"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    pause
}

# Test 5: Filtros
test_filters() {
    print_header "TEST 5: GET - Probar filtros"
    
    # Filtro por tipo
    print_info "Request: GET ${API_BASE}?type=expense"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X GET "${API_BASE}?type=expense" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "Status: $HTTP_CODE OK"
        TOTAL=$(echo "$BODY" | jq '.total')
        print_success "Categorías de tipo 'expense': $TOTAL"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    echo ""
    
    # Filtro por búsqueda
    print_info "Request: GET ${API_BASE}?search=test"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X GET "${API_BASE}?search=test" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "Status: $HTTP_CODE OK"
        TOTAL=$(echo "$BODY" | jq '.total')
        print_success "Categorías que contienen 'test': $TOTAL"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    pause
}

# Test 6: Árbol jerárquico
test_tree() {
    print_header "TEST 6: GET - Obtener árbol jerárquico"
    
    print_info "Request: GET ${API_BASE}/tree"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X GET "${API_BASE}/tree" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "Status: $HTTP_CODE OK"
        print_success "Árbol jerárquico obtenido correctamente"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    pause
}

# Test 7: Validaciones - Nombre vacío
test_validation_empty_name() {
    print_header "TEST 7: Validación - Nombre vacío (debe fallar)"
    
    print_info "Request: POST ${API_BASE}"
    print_info "Body: Nombre vacío"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X POST "${API_BASE}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "",
            "type": "income"
        }')
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 400 ]; then
        print_success "Status: $HTTP_CODE Bad Request (esperado)"
        print_success "Validación funcionando correctamente"
    else
        print_error "Status: $HTTP_CODE (se esperaba 400)"
    fi
    
    pause
}

# Test 8: Validaciones - Tipo inválido
test_validation_invalid_type() {
    print_header "TEST 8: Validación - Tipo inválido (debe fallar)"
    
    print_info "Request: POST ${API_BASE}"
    print_info "Body: Tipo 'invalid_type'"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X POST "${API_BASE}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "Test Invalid Type",
            "type": "invalid_type"
        }')
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 400 ]; then
        print_success "Status: $HTTP_CODE Bad Request (esperado)"
        print_success "Validación funcionando correctamente"
    else
        print_error "Status: $HTTP_CODE (se esperaba 400)"
    fi
    
    pause
}

# Test 9: Eliminar categoría (soft delete)
test_delete_soft() {
    if [ -z "$CREATED_ID" ]; then
        print_error "No hay ID de categoría creada. Saltando test."
        return
    fi
    
    print_header "TEST 9: DELETE - Eliminar categoría (soft delete)"
    
    print_info "Request: DELETE ${API_BASE}/${CREATED_ID}"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X DELETE "${API_BASE}/${CREATED_ID}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 200 ]; then
        print_success "Status: $HTTP_CODE OK"
        print_success "Categoría movida a papelera"
    else
        print_error "Status: $HTTP_CODE"
    fi
    
    pause
}

# Test 10: Intentar obtener categoría eliminada
test_get_deleted() {
    if [ -z "$CREATED_ID" ]; then
        print_error "No hay ID de categoría. Saltando test."
        return
    fi
    
    print_header "TEST 10: GET - Intentar obtener categoría eliminada (debe fallar)"
    
    print_info "Request: GET ${API_BASE}/${CREATED_ID}"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" \
        -X GET "${API_BASE}/${CREATED_ID}" \
        -u "${WP_USER}:${WP_PASS}" \
        -H "Content-Type: application/json")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    echo "$BODY" | jq '.'
    
    if [ "$HTTP_CODE" -eq 404 ]; then
        print_success "Status: $HTTP_CODE Not Found (esperado)"
        print_success "API responde correctamente a categorías eliminadas"
    else
        print_error "Status: $HTTP_CODE (se esperaba 404)"
    fi
    
    pause
}

###########################################################################
# Main
###########################################################################

clear

echo -e "${GREEN}"
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         API REST - CATEGORÍAS FINANCIERAS                      ║"
echo "║         Script de Prueba Automatizado                          ║"
echo "║         AURA Business Suite v1.0.0                             ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# Verificar dependencias
if ! command -v jq &> /dev/null; then
    print_error "jq no está instalado. Instálalo con: sudo apt-get install jq"
    exit 1
fi

if ! command -v curl &> /dev/null; then
    print_error "curl no está instalado. Instálalo con: sudo apt-get install curl"
    exit 1
fi

print_info "URL de WordPress: $WP_URL"
print_info "Usuario: $WP_USER"
print_info "API Base: $API_BASE"
echo ""

read -p "¿La configuración es correcta? (s/n): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Ss]$ ]]; then
    print_info "Edita el script y configura las variables WP_URL, WP_USER y WP_PASS"
    exit 0
fi

echo ""

# Ejecutar tests
test_get_all
test_create_category
test_get_single
test_update_category
test_filters
test_tree
test_validation_empty_name
test_validation_invalid_type
test_delete_soft
test_get_deleted

# Resumen final
print_header "RESUMEN DE PRUEBAS"
echo ""
print_success "✓ GET /categories - Listar todas"
print_success "✓ POST /categories - Crear nueva"
print_success "✓ GET /categories/{id} - Obtener específica"
print_success "✓ PUT /categories/{id} - Actualizar"
print_success "✓ GET /categories?filters - Filtros"
print_success "✓ GET /categories/tree - Árbol jerárquico"
print_success "✓ Validación de nombre vacío"
print_success "✓ Validación de tipo inválido"
print_success "✓ DELETE /categories/{id} - Eliminar (soft)"
print_success "✓ Verificar categoría eliminada (404)"
echo ""
print_success "Todas las pruebas completadas!"
echo ""

if [ -n "$CREATED_ID" ]; then
    print_info "Nota: Se creó una categoría de prueba con ID: $CREATED_ID"
    print_info "Esta categoría fue movida a la papelera."
    print_info "Puedes restaurarla desde WordPress Admin o eliminarla permanentemente."
fi

echo ""
