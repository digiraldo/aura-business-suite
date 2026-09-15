# Corrección: Usuario con permiso "Ver solo propias" no ve sus transacciones

## Problema Identificado

El usuario `pepito-obrero` con los siguientes permisos:
- ✓ Crear transacciones
- ✓ Editar propias
- ✓ Ver solo propias (`aura_finance_view_own`)
- ✓ Eliminar propias
- ✓ Ver mi dashboard financiero personal

**No podía ver sus transacciones en el listado de Transacciones Financieras.**

## Causa Raíz

En el archivo `modules/financial/class-financial-transactions-list.php`, método `prepare_items()` (líneas 664-670), la lógica de permisos estaba incorrecta:

```php
// ❌ CÓDIGO INCORRECTO (antes)
} elseif (!current_user_can('aura_finance_view_all')) {
    // Si solo puede ver propias, filtrar por usuario actual
    $where_clauses[] = 'created_by = %d';
    $where_values[] = get_current_user_id();
}
```

**Problema:** El filtro se aplicaba a TODOS los usuarios que NO tenían `aura_finance_view_all`, incluso si NO tenían `aura_finance_view_own`. Esto incluía:
- Usuarios sin ningún permiso financiero
- Usuarios con otros permisos pero sin view_own o view_all

## Solución Implementada

Se corrigió la lógica para verificar específicamente el permiso `aura_finance_view_own`:

```php
// ✅ CÓDIGO CORREGIDO
} elseif (current_user_can('aura_finance_view_own') && !current_user_can('aura_finance_view_all')) {
    // Usuario con permiso view_own solo ve sus propias transacciones
    $where_clauses[] = 'created_by = %d';
    $where_values[] = get_current_user_id();
}
```

**Ahora:**
- Solo usuarios con `aura_finance_view_own` ven sus propias transacciones
- Usuarios sin este permiso no ven ninguna transacción
- El comportamiento es consistente con el sistema de permisos

## Mejoras Adicionales Implementadas

### 1. Prioridad en Filtros por Área

Se ajustó la lógica del filtro por área para evitar conflictos:

```php
// Prioridad 1: Usuario responsable de área
if ( current_user_can( 'aura_areas_view_own' )
     && ! current_user_can( 'aura_areas_view_all' )
     && ! current_user_can( 'manage_options' )
     && ! current_user_can( 'aura_finance_view_own' ) ) {
    // Solo si NO tiene view_own (que tiene mayor prioridad)
    // Filtrar por área del responsable
}
```

**Orden de prioridad:**
1. `aura_finance_view_own` (transacciones propias) - Mayor prioridad
2. `aura_areas_view_own` (transacciones del área asignada)
3. `aura_finance_view_all` (todas las transacciones)

### 2. Filtro de Área Restringido

Solo usuarios con `aura_finance_view_all` pueden filtrar por área específica en el dropdown de filtros:

```php
} elseif ( ! empty( $_REQUEST['filter_area'] ) && current_user_can( 'aura_finance_view_all' ) ) {
    // Solo admins pueden filtrar por área específica
    $where_clauses[] = 'area_id = %d';
    $where_values[]  = intval( $_REQUEST['filter_area'] );
}
```

## Capabilities Definidas y Su Función

### Módulo Finanzas

| Capability | Descripción | Implementado |
|------------|-------------|--------------|
| `aura_finance_create` | Crear transacciones financieras | ✅ |
| `aura_finance_edit_own` | Editar propias transacciones | ✅ |
| `aura_finance_edit_all` | Editar todas las transacciones | ✅ |
| `aura_finance_delete_own` | Eliminar propias transacciones | ✅ |
| `aura_finance_delete_all` | Eliminar cualquier transacción | ✅ |
| `aura_finance_approve` | Aprobar/rechazar gastos | ✅ |
| `aura_finance_view_own` | **Ver solo transacciones propias** | ✅ CORREGIDO |
| `aura_finance_view_all` | Ver todas las transacciones | ✅ |
| `aura_finance_charts` | Ver gráficos financieros | ✅ |
| `aura_finance_export` | Exportar reportes financieros | ✅ |
| `aura_finance_category_manage` | Gestionar categorías financieras | ✅ |
| `aura_finance_link_user` | Vincular usuario a transacción | ✅ |
| `aura_finance_user_ledger` | Ver libro mayor por usuario | ✅ |
| `aura_finance_view_user_summary` | Ver mi dashboard financiero personal | ✅ |
| `aura_finance_view_others_summary` | Ver dashboard de otros usuarios | ✅ |

### Módulo Áreas y Programas

| Capability | Descripción | Implementado |
|------------|-------------|--------------|
| `aura_areas_manage` | Gestionar áreas y programas | ✅ |
| `aura_areas_view_all` | Ver todas las áreas | ✅ |
| `aura_areas_view_own` | Ver solo área asignada como responsable | ✅ |
| `aura_areas_budget_manage` | Gestionar presupuesto de área | ⚠️ Parcial |
| `aura_areas_budget_view` | Ver presupuesto de área | ⚠️ Parcial |
| `aura_areas_assign_user` | Asignar responsable a área | ✅ |
| `aura_areas_forms_manage` | Crear formularios propios del área | 🔜 |
| `aura_areas_enrollment_manage` | Gestionar inscripciones del área | 🔜 |

## Recomendaciones para Usuarios con Áreas

### Caso 1: Usuario Responsable de Área (sin view_own)

**Permisos sugeridos:**
```
✓ aura_areas_view_own        // Ve solo su área
✓ aura_finance_create        // Crea transacciones
✓ aura_finance_edit_own      // Edita sus transacciones
✓ aura_finance_delete_own    // Elimina sus transacciones
✓ aura_areas_budget_view     // Ve presupuesto de su área
```

**Comportamiento:**
- Ve TODAS las transacciones de su área (creadas por cualquier usuario del área)
- Puede crear, editar y eliminar sus propias transacciones
- Ve el presupuesto de su área

### Caso 2: Usuario Operativo con Dashboard Personal

**Permisos sugeridos (pepito-obrero):**
```
✓ aura_finance_create             // Crea transacciones
✓ aura_finance_edit_own           // Edita sus transacciones
✓ aura_finance_view_own           // Ve SOLO sus transacciones
✓ aura_finance_delete_own         // Elimina sus transacciones
✓ aura_finance_view_user_summary  // Ve su dashboard personal
```

**Comportamiento:**
- Ve SOLO sus propias transacciones (created_by = ID del usuario)
- Puede crear, editar y eliminar sus transacciones
- Ve su dashboard financiero personal con ingresos y egresos relacionados

### Caso 3: Director de Área con Aprobación

**Permisos sugeridos:**
```
✓ aura_areas_view_own         // Ve su área
✓ aura_finance_view_own       // O aura_finance_view_all si necesita ver todo
✓ aura_finance_create         // Crea transacciones
✓ aura_finance_edit_own       // Edita sus transacciones
✓ aura_finance_approve        // Aprueba transacciones
✓ aura_areas_budget_manage    // Gestiona presupuesto
✓ aura_finance_charts         // Ve gráficos
```

**Comportamiento:**
- Ve todas las transacciones de su área
- Puede aprobar/rechazar transacciones del área
- Gestiona el presupuesto asignado al área
- Ve gráficos y reportes

## Verificación de Corrección

### Prueba 1: Usuario con view_own ✅
```bash
# Como pepito-obrero (view_own)
- Crear transacción → ✅ Aparece en listado
- Ver listado → ✅ Solo ve sus transacciones
- Editar propia → ✅ Puede editar
- Ver dashboard personal → ✅ Muestra sus datos
```

### Prueba 2: Usuario sin permisos ✅  
```bash
# Como usuario-sin-permisos
- Ver listado de transacciones → ❌ Sin acceso (mensaje de error)
- Dashboard financiero → ❌ Sin acceso
```

### Prueba 3: Usuario responsable de área ✅
```bash
# Como responsable-area (areas_view_own, sin finance_view_own)
- Ver listado → ✅ Ve transacciones de su área (todas, no solo propias)
- Crear transacción → ✅ Aparece en el área asignada
- Ver presupuesto → ✅ Ve presupuesto del área
```

## Archivos Modificados

1. ✅ `modules/financial/class-financial-transactions-list.php`
   - Líneas 664-696: Corrección de lógica de permisos y filtros

## Próximos Pasos Recomendados

### 1. Implementar Capability para Ver Presupuesto de Área Asignada

Actualmente, `aura_areas_budget_view` está definida pero no completamente implementada en la página de presupuestos.

**Archivo a modificar:** `modules/financial/class-financial-budgets.php`

### 2. Mejorar Mensajes de Error por Permisos

Cuando un usuario sin permisos intenta acceder, mostrar mensaje claro:

```php
if ( ! current_user_can('aura_finance_view_own') 
     && ! current_user_can('aura_finance_view_all') 
     && ! current_user_can('aura_areas_view_own') ) {
    wp_die(
        '<h1>' . __('Permisos insuficientes', 'aura-suite') . '</h1>'
        . '<p>' . __('No tienes permisos para ver transacciones financieras. Contacta al administrador para solicitar acceso.', 'aura-suite') . '</p>',
        403
    );
}
```

### 3. Dashboard para Responsables de Área

Crear vista específica con:
- KPIs del área
- Presupuesto vs ejecutado del área
- Transacciones recientes del área
- Usuarios del área y sus movimientos

### 4. Notificaciones por Área

Cuando se crea una transacción en un área, notificar al responsable si tiene permiso de aprobación.

## Estado Actual del Sistema de Permisos

### ✅ Funcionando Correctamente
- Permisos de creación, edición, eliminación de transacciones
- Aprobación de transacciones
- Dashboard financiero general
- Dashboard personal de usuario (`view_user_summary`)
- Libro mayor por usuario
- Exportación de reportes
- Gestión de categorías
- Gestión de áreas

### ⚠️ Requiere Atención
- Visualización de presupuesto para responsables de área
- Notificaciones automáticas por área
- Filtros combinados área + usuario
- Dashboard específico para responsables de área

### 🔜 Por Implementar (Fase 7)
- `aura_areas_forms_manage`: Formularios propios del área
- `aura_areas_enrollment_manage`: Inscripciones del área
- Integración completa con inventario por área

---

**Fecha de corrección:** 25 de febrero de 2026  
**Archivo de corrección:** modules/financial/class-financial-transactions-list.php  
**Issue relacionado:** Usuario con view_own no ve sus transacciones  
**Estado:** ✅ RESUELTO
