<div align="center">

![Logo AURA](image/logo-aura.png)

# PRD - Módulo de Finanzas
## AURA Business Suite - Sistema de Gestión Financiera
### Prompts de Desarrollo por Fases e Ítems

> **Gestión Financiera Completa para Pequeñas Empresas y Fundaciones**

</div>

---

## 📋 Índice de Contenidos

1. [Introducción](#1-introducción)
2. [Arquitectura del Módulo](#2-arquitectura-del-módulo)
3. [FASE 1: Configuración y Categorías](#fase-1-configuración-y-categorías)
4. [FASE 2: Gestión de Transacciones](#fase-2-gestión-de-transacciones)
5. [FASE 3: Dashboard y Visualizaciones](#fase-3-dashboard-y-visualizaciones)
6. [FASE 4: Reportes y Exportación](#fase-4-reportes-y-exportación)
7. [FASE 5: Funciones Avanzadas](#fase-5-funciones-avanzadas)
8. [Mejores Prácticas](#mejores-prácticas)

---

## 1. Introducción

### 1.1 Visión del Módulo
El módulo de Finanzas de AURA está diseñado específicamente para pequeñas empresas y fundaciones que necesitan:
- Control preciso de ingresos y egresos
- Gestión de categorías personalizables
- Flujo de aprobación de transacciones
- Reportes financieros claros y visuales
- Trazabilidad completa de operaciones

### 1.2 Usuarios Objetivo
- **Fundaciones**: Control de donaciones, becas y asignaciones
- **Pequeñas Empresas**: Gestión de gastos operativos y ventas
- **ONGs**: Transparencia en uso de recursos
- **Cooperativas**: Administración de fondos comunes

### 1.3 Funcionalidades Principales

#### Gestión de Categorías
- ✅ Crear categorías de ingresos y egresos
- ✅ Editar categorías existentes
- ✅ Eliminar categorías (con validación de uso)
- ✅ Categorías activas/inactivas (soft delete)
- ✅ Subcategorías jerárquicas (opcional)
- ✅ Asignación de colores para visualización

#### Gestión de Transacciones
- ✅ Registro de ingresos y egresos
- ✅ Edición de transacciones propias o todas (según permisos)
- ✅ Eliminación con confirmación (soft o hard delete)
- ✅ Adjuntar comprobantes (imágenes/PDFs)
- ✅ Sistema de estados (pendiente, aprobado, rechazado)
- ✅ Notas y observaciones
- ✅ Etiquetas personalizadas

---

## 2. Arquitectura del Módulo

### 2.1 Estructura de Base de Datos

```sql
-- Tabla de categorías financieras
CREATE TABLE wp_aura_finance_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('income', 'expense', 'both') DEFAULT 'both',
    parent_id BIGINT UNSIGNED NULL,
    color VARCHAR(7) DEFAULT '#3498db',
    icon VARCHAR(50) DEFAULT 'dashicons-category',
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    display_order INT DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES wp_aura_finance_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_active (is_active),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de transacciones financieras
CREATE TABLE wp_aura_finance_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('income', 'expense') NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT NOT NULL,
    notes TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    payment_method VARCHAR(50),
    reference_number VARCHAR(100),
    recipient_payer VARCHAR(255),
    receipt_file VARCHAR(255),
    tags VARCHAR(500),
    created_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejection_reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (category_id) REFERENCES wp_aura_finance_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES wp_users(ID) ON DELETE SET NULL,
    INDEX idx_type (transaction_type),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_date (transaction_date),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de presupuestos por categoría (opcional)
CREATE TABLE wp_aura_finance_budgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    budget_amount DECIMAL(15, 2) NOT NULL,
    period_type ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    alert_threshold INT DEFAULT 80,
    is_active BOOLEAN DEFAULT 1,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES wp_aura_finance_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Capabilities del Módulo

```php
// Array de capabilities del módulo de finanzas
$finance_capabilities = [
    'aura_finance_create',          // Crear transacciones
    'aura_finance_edit_own',        // Editar propias transacciones
    'aura_finance_edit_all',        // Editar cualquier transacción
    'aura_finance_delete_own',      // Eliminar propias transacciones
    'aura_finance_delete_all',      // Eliminar cualquier transacción
    'aura_finance_approve',         // Aprobar/rechazar transacciones
    'aura_finance_view_own',        // Ver solo propias transacciones
    'aura_finance_view_all',        // Ver todas las transacciones
    'aura_finance_charts',          // Ver gráficos financieros
    'aura_finance_export',          // Exportar reportes
    'aura_finance_category_manage', // Gestionar categorías
    'aura_finance_budget_manage',   // Gestionar presupuestos
];
```

---

## FASE 1: Configuración y Categorías

### 📌 Objetivo de la Fase
Implementar el sistema completo de gestión de categorías financieras con interfaz CRUD, validaciones y jerarquías opcionales.

---

### Item 1.1: Crear Custom Post Type para Categorías

**Prompt de Desarrollo:**
```
Crea un Custom Post Type llamado 'aura_fin_category' para gestionar las categorías 
financieras en WordPress. Debe incluir:

1. Register el CPT con estos parámetros:
   - public: false
   - show_ui: true
   - show_in_menu: 'aura-business-suite'
   - supports: ['title', 'custom-fields']
   - capability_type: 'aura_finance_category'
   
2. Agregar meta boxes personalizados para:
   - Tipo de categoría (Ingreso/Egreso/Ambos)
   - Categoría padre (dropdown para jerarquía)
   - Color (color picker)
   - Icono (dashicons selector)
   - Estado activo/inactivo
   - Orden de visualización
   
3. Implementar validaciones:
   - Nombre único de categoría (slug único)
   - No permitir eliminar categorías con transacciones asociadas
   - No permitir ciclos en jerarquía padre-hijo
   
4. Archivo: modules/financial/class-financial-categories-cpt.php

5. Hook de activación: crear tabla wp_aura_finance_categories
```

**Checklist de Implementación:**
- [ ] CPT registrado correctamente
- [ ] Meta boxes funcionales
- [ ] Validaciones activas
- [ ] Tabla creada en activación
- [ ] Documentación en código

---

### Item 1.2: Interfaz de Gestión de Categorías

**Prompt de Desarrollo:**
```
Desarrolla una interfaz administrativa para gestionar categorías financieras con las 
siguientes características:

1. Tabla de categorías (WP_List_Table extendida):
   - Columnas: Nombre, Tipo, Categoría Padre, Color (badge), Estado, Acciones
   - Filtros: Por tipo (Ingreso/Egreso), por estado (Activo/Inactivo)
   - Búsqueda en tiempo real (AJAX)
   - Ordenamiento por columnas
   - Acciones en fila: Editar, Desactivar/Activar, Eliminar
   - Acción masiva: Desactivar seleccionadas
   
2. Modal para crear/editar categoría:
   - Campos:
     * Nombre de categoría (requerido)
     * Tipo: Radio buttons (Ingreso/Egreso/Ambos)
     * Categoría padre: Select dropdown (opcional)
     * Color: Color picker (#hex)
     * Icono: Selector de Dashicons
     * Descripción: Textarea
     * Estado: Checkbox (Activo)
   
   - Validaciones frontend (JavaScript):
     * Nombre no vacío
     * Color en formato hex válido
     
   - Validación backend (PHP):
     * Sanitize todos los campos
     * Verificar slug único
     * Verificar que categoría padre no cree ciclo
     
3. Mensajes de confirmación:
   - Antes de eliminar: "¿Eliminar categoría X? Esta acción no se puede deshacer."
   - Si tiene transacciones: "No se puede eliminar. Tiene N transacciones asociadas. 
     ¿Desactivarla en su lugar?"
   
4. AJAX handlers:
   - wp_ajax_aura_create_category
   - wp_ajax_aura_update_category
   - wp_ajax_aura_delete_category
   - wp_ajax_aura_toggle_category_status
   - wp_ajax_aura_get_categories
   
5. Archivos:
   - templates/financial/categories-page.php
   - assets/js/financial-categories.js
   - assets/css/financial-categories.css
   - modules/financial/class-financial-categories.php

6. Permisos requeridos: aura_finance_category_manage
```

**Checklist de Implementación:**
- [ ] Tabla WP_List_Table implementada
- [ ] Modal funcional con todos los campos
- [ ] Validaciones frontend y backend
- [ ] AJAX handlers funcionando
- [ ] Mensajes de confirmación
- [ ] Verificación de permisos
- [ ] Responsive design
- [ ] Testing con datos reales

---

### Item 1.3: API REST para Categorías

**Prompt de Desarrollo:**
```
Implementa endpoints REST API para consumir categorías desde frontend (VueJS/React) 
o aplicaciones externas:

1. Endpoints a crear:
   
   GET /wp-json/aura/v1/finance/categories
   - Parámetros: type (income/expense), status (active/inactive), parent_id
   - Respuesta: Lista de categorías con estructura jerárquica
   - Permisos: aura_finance_view_own o aura_finance_view_all
   
   POST /wp-json/aura/v1/finance/categories
   - Body: {name, type, parent_id, color, icon, description}
   - Respuesta: Categoría creada con ID
   - Permisos: aura_finance_category_manage
   
   PUT /wp-json/aura/v1/finance/categories/{id}
   - Body: Campos a actualizar
   - Respuesta: Categoría actualizada
   - Permisos: aura_finance_category_manage
   
   DELETE /wp-json/aura/v1/finance/categories/{id}
   - Parámetros: force (true para hard delete)
   - Respuesta: Éxito o error con razón
   - Permisos: aura_finance_category_manage
   
   GET /wp-json/aura/v1/finance/categories/tree
   - Respuesta: Árbol jerárquico completo de categorías
   - Útil para dropdowns con subcategorías

2. Validaciones en cada endpoint:
   - Verificar permisos
   - Sanitizar entradas
   - Validar tipos de datos
   - Manejar errores con códigos HTTP apropiados
   
3. Documentación Swagger/OpenAPI (comentarios PHPDoc)

4. Archivo: modules/financial/class-financial-categories-api.php
```

**Checklist de Implementación:**
- [ ] 5 endpoints implementados
- [ ] Autenticación y permisos verificados
- [ ] Validaciones y sanitización
- [ ] Respuestas JSON consistentes
- [ ] Manejo de errores HTTP
- [ ] Documentación PHPDoc
- [ ] Testing con Postman/Insomnia

---

### Item 1.4: Categorías Predeterminadas

**Prompt de Desarrollo:**
```
Crea un sistema de categorías predeterminadas que se instalen automáticamente al 
activar el módulo, facilitando el inicio rápido:

1. Categorías de INGRESOS predeterminadas:
   - Donaciones (#27ae60, dashicons-heart)
     └─ Donación General (subcategoría)
     └─ Donación Especial (subcategoría)
      └─ Donación de Misiones (subcategoría)
      └─ Donación de Construcción (subcategoría)
      └─ Donación de Emergencia (subcategoría)
      └─ Donación de Alimentos (subcategoría)
      └─ Donación de Voluntarios (subcategoría)
   - Ofrendas (#2ecc71, dashicons-groups)
     └─ Ofrenda General (subcategoría)
     └─ Ofrenda Especial (subcategoría)
      └─ Ofrenda de Misiones (subcategoría)
      └─ Ofrenda de Construcción (subcategoría)
      └─ Ofrenda de Emergencia (subcategoría)
      └─ Ofrenda de Alimentos (subcategoría)
      └─ Ofrenda de Voluntarios (subcategoría)
   - Ventas de Productos (#3498db, dashicons-cart)
   - Ventas de Servicios (#2980b9, dashicons-admin-tools)
   - Subvenciones (#16a085, dashicons-money-alt)
   - Intereses Bancarios (#8e44ad, dashicons-chart-line)
   - Otros Ingresos (#95a5a6, dashicons-plus-alt)

2. Categorías de EGRESOS predeterminadas:
   - Salarios y Sueldos (#e74c3c, dashicons-groups)
     └─ Salario (subcategoría)
     └─ Honorarios (subcategoría)
   - Donaciones (#27ae60, dashicons-heart)
     └─ Donación General (subcategoría)
     └─ Donación Especial (subcategoría)
      └─ Donación de Misiones (subcategoría)
      └─ Donación de Construcción (subcategoría)
      └─ Donación de Emergencia (subcategoría)
      └─ Donación de Alimentos (subcategoría)
      └─ Donación de Voluntarios (subcategoría)
   - Ofrendas (#2ecc71, dashicons-groups)
     └─ Ofrenda General (subcategoría)
     └─ Ofrenda Especial (subcategoría)
      └─ Ofrenda de Misiones (subcategoría)
      └─ Ofrenda de Construcción (subcategoría)
      └─ Ofrenda de Emergencia (subcategoría)
      └─ Ofrenda de Alimentos (subcategoría)
      └─ Ofrenda de Voluntarios (subcategoría)
   - Servicios Públicos (#e67e22, dashicons-lightbulb)
     └─ Electricidad (subcategoría)
     └─ Internet (subcategoría)
     └─ Gas (subcategoría)
     └─ Agua (subcategoría)
     └─ Teléfono (subcategoría)
   - Mantenimiento (#d35400, dashicons-admin-tools)
     └─ Vehículos (subcategoría)
     └─ Instalaciones (subcategoría)
     └─ Herramientas (subcategoría)
     └─ Jardinería (subcategoría)
     └─ Riego (subcategoría)
   - Suministros de Oficina (#f39c12, dashicons-portfolio)
   - Programas y Proyectos (#9b59b6, dashicons-welcome-learn-more)
   - Becas y Ayudas (#1abc9c, dashicons-welcome-learn-more)
   - Marketing (#34495e, dashicons-megaphone)
   - Tecnología (#2c3e50, dashicons-desktop)
   - Otros Gastos (#7f8c8d, dashicons-minus)

3. Implementación:
   - Función: aura_finance_install_default_categories()
   - Hook: register_activation_hook
   - Verificar que no existan antes de crear
   - Opción para reinstalar categorías
   - Exportar/importar categorías personalizadas (JSON)
   
4. Archivo: modules/financial/class-financial-setup.php
```

**Checklist de Implementación:**
- [ ] 15+ categorías predeterminadas creadas
- [ ] Jerarquía de subcategorías funcional
- [ ] Colores e iconos asignados
- [ ] Verificación de duplicados
- [ ] Opción de reinstalación
- [ ] Sistema de importar/exportar categorías

---

## FASE 2: Gestión de Transacciones

### 📌 Objetivo de la Fase
Implementar el sistema completo de registro, edición y eliminación de transacciones financieras con flujo de aprobación y adjuntos.

---

### Item 2.1: Formulario de Registro de Transacciones

**Prompt de Desarrollo:**
```
Desarrolla un formulario completo para registrar ingresos y egresos con interfaz 
intuitiva y validaciones robustas:

1. Diseño de formulario (ubicación: página "Nueva Transacción"):
   
   A. Selector de tipo (Toggle switch destacado):
      [💰 INGRESO] ←→ [💸 EGRESO]
      - Verde para ingresos, rojo para egresos
      - Cambia dinámicamente labels y opciones
   
   B. Campos principales:
      * Fecha de transacción: Datepicker (por defecto hoy)
      * Categoría: Select filtrado por tipo seleccionado
      * Monto: Input numérico con formato de moneda ($)
      * Descripción: Textarea (requerido, min 10 caracteres)
      * Método de pago: Select (Efectivo, Transferencia, Cheque, Tarjeta)
      * Número de referencia: Input texto (factura, cheque, etc.)
      * Beneficiario/Pagador: Input texto
      
   C. Campos opcionales (colapsables):
      * Notas adicionales: Textarea
      * Etiquetas: Input con autocompletado
      * Comprobante: Upload de archivo (JPG, PNG, PDF, max 5MB)
      * Proyecto relacionado: Select (si aplica)
      
   D. Previsualización:
      - Card con resumen antes de guardar
      - Muestra: Tipo, Monto, Categoría, Descripción
      
2. Validaciones frontend (JavaScript):
   - Monto mayor a 0
   - Fecha no futura (opcional, según configuración)
   - Categoría seleccionada
   - Descripción mínimo 10 caracteres
   - Formato de archivo válido
   - Tamaño de archivo no excede límite
   
3. Envío AJAX:
   - Action: wp_ajax_aura_save_transaction
   - Mostrar loading spinner
   - Mensaje de éxito con opción "Ver transacción" o "Crear otra"
   - Manejo de errores con mensajes claros
   
4. Funcionalidad de autoguardado (draft):
   - Guardar en localStorage cada 30 segundos
   - Recuperar draft al reabrir formulario
   - Botón "Limpiar draft"
   
5. Archivos:
   - templates/financial/transaction-form.php
   - assets/js/transaction-form.js
   - assets/css/transaction-form.css
   - modules/financial/class-financial-transactions.php (lógica backend)

6. Permisos: aura_finance_create
```

**Checklist de Implementación:**
- [ ] Formulario con todos los campos
- [ ] Toggle ingreso/egreso funcional
- [ ] Datepicker y selects funcionando
- [ ] Upload de archivos implementado
- [ ] Validaciones frontend completas
- [ ] AJAX save funcional
- [ ] Autoguardado en localStorage
- [ ] Previsualización de transacción
- [ ] Responsive design
- [ ] Testing exhaustivo

---

### Item 2.2: Listado de Transacciones con Filtros Avanzados

**Prompt de Desarrollo:**
```
Crea un listado completo de transacciones con filtros potentes y acciones 
contextuales según permisos:

1. Tabla de transacciones (mejorando WP_List_Table):
   
   Columnas:
   - Estado: Badge (Pendiente/Aprobado/Rechazado)
   - Fecha: Formato dd/mm/yyyy
   - Tipo: Icono + color (↑ verde ingreso, ↓ rojo egreso)
   - Categoría: Badge con color de categoría
   - Descripción: Truncada a 50 chars con tooltip
   - Monto: Formato moneda con signo
   - Método de pago: Icono + texto
   - Creado por: Avatar + nombre
   - Acciones: Dropdown contextual
   
2. Filtros avanzados (sidebar colapsable):
   
   A. Filtros básicos:
      - Rango de fechas: Date range picker
      - Tipo: Checkbox (Ingresos, Egresos)
      - Estado: Checkbox (Pendiente, Aprobado, Rechazado)
      - Categoría: Select múltiple jerárquico
      
   B. Filtros avanzados:
      - Rango de monto: Min - Max
      - Método de pago: Select múltiple
      - Creado por: Select de usuarios (solo si tiene aura_finance_view_all)
      - Etiquetas: Input con autocompletado
      - Con/sin comprobante: Radio buttons
      
   C. Filtros guardados:
      - Guardar configuración actual como "Filtro favorito"
      - Aplicar filtros guardados con un click
      - Ejemplos predefinidos: "Este mes", "Pendientes de aprobación", 
        "Mis transacciones", "Gastos mayores a $1000"
   
3. Búsqueda global:
   - Buscar en: Descripción, Notas, Referencia, Beneficiario
   - Resultados en tiempo real (AJAX)
   - Resaltar texto encontrado
   
4. Acciones por transacción (según permisos):
   
   Si tiene aura_finance_view_all o es creador:
   - Ver detalle (modal)
   
   Si tiene aura_finance_edit_own/all:
   - Editar (modal o página)
   
   Si tiene aura_finance_approve y estado=pendiente:
   - Aprobar
   - Rechazar (con motivo)
   
   Si tiene aura_finance_delete_own/all:
   - Eliminar (con confirmación)
   
5. Acciones masivas:
   - Seleccionar múltiples transacciones (checkbox)
   - Acciones disponibles:
     * Exportar seleccionadas (CSV/PDF)
     * Cambiar categoría (si son editables)
     * Aprobar seleccionadas (si aplica)
     * Eliminar seleccionadas (con confirmación)
   
6. Estadísticas en cabecera:
   - Total ingresos (filtrados): $X,XXX.XX
   - Total egresos (filtrados): $X,XXX.XX
   - Balance: $X,XXX.XX (verde/rojo)
   - Cantidad de transacciones: N
   
7. Paginación:
   - 20, 50, 100 por página
   - AJAX load more o scroll infinito (opcional)
   
8. Archivos:
   - templates/financial/transactions-list.php
   - assets/js/transactions-list.js
   - modules/financial/class-financial-transactions-list.php
```

**Checklist de Implementación:**
- [ ] Tabla con todas las columnas
- [ ] Filtros básicos funcionales
- [ ] Filtros avanzados funcionales
- [ ] Búsqueda en tiempo real
- [ ] Acciones contextuales por permisos
- [ ] Acciones masivas
- [ ] Estadísticas en cabecera
- [ ] Paginación funcional
- [ ] Performance optimizado (índices en BD)
- [ ] Responsive con scroll horizontal en móvil

---

### Item 2.3: Modal de Detalle de Transacción

**Prompt de Desarrollo:**
```
Implementa un modal detallado para visualizar toda la información de una transacción 
con opciones de acción rápida:

1. Diseño del modal (fullscreen en móvil, 800px en desktop):
   
   A. Cabecera:
      - Badge de estado (grande, destacado)
      - Monto (grande, color según tipo)
      - Tipo de transacción (icono)
      - Fecha
      - Botón cerrar (X)
      
   B. Sección principal (tabs):
      
      Tab 1: Información General
      - Categoría (badge con color)
      - Descripción completa
      - Método de pago
      - Número de referencia
      - Beneficiario/Pagador
      - Etiquetas (badges)
      - Creado por (avatar + nombre + fecha)
      
      Tab 2: Notas y Observaciones
      - Notas del creador
      - Historial de cambios (si fue editada)
      - Motivo de rechazo (si aplica)
      
      Tab 3: Comprobante
      - Visualizador de imagen/PDF
      - Botones: Descargar, Ampliar, Eliminar
      - Opción de subir si no hay
      
      Tab 4: Auditoría (solo admin/auditor)
      - Creado el: fecha + hora + usuario
      - Última edición: fecha + hora + usuario
      - Aprobado por: usuario + fecha
      - IP de origen
      - Cambios realizados (log)
   
   C. Pie del modal (acciones rápidas):
      
      Botones dinámicos según permisos:
      - [Editar] → Abrir formulario de edición
      - [Aprobar] → Confirmar aprobación
      - [Rechazar] → Modal para ingresar motivo
      - [Eliminar] → Confirmación de eliminación
      - [Duplicar] → Crear nueva basada en esta
      - [Exportar PDF] → Generar comprobante PDF
      
2. Interacciones:
   - Abrir modal con AJAX desde listado
   - Navegación entre tabs sin recargar
   - Actualización en tiempo real si otros usuarios modifican
   - Cerrar con ESC o click fuera
   
3. AJAX handlers:
   - wp_ajax_aura_get_transaction_details
   - wp_ajax_aura_approve_transaction
   - wp_ajax_aura_reject_transaction
   
4. Archivos:
   - templates/financial/transaction-modal.php
   - assets/js/transaction-modal.js
```

**Checklist de Implementación:**
- [ ] Modal con diseño completo
- [ ] 4 tabs implementados
- [ ] Acciones rápidas funcionales
- [ ] Visualizador de comprobantes
- [ ] Log de auditoría
- [ ] AJAX load de datos
- [ ] Actualización en tiempo real
- [ ] Responsive design

---

### Item 2.4: Edición de Transacciones

**Prompt de Desarrollo:**
```
Implementa la funcionalidad de editar transacciones existentes con control de 
permisos y registro de cambios:

1. Lógica de permisos:
   
   A. Puede editar si:
      - Tiene aura_finance_edit_all, O
      - Tiene aura_finance_edit_own Y es el creador Y estado=pendiente
      
   B. Restricciones:
      - No editar transacciones aprobadas (solo admin puede)
      - No editar transacciones de hace más de 30 días (configurable)
      - No cambiar el tipo (ingreso/egreso) una vez creada
      
2. Formulario de edición:
   - Reutilizar formulario de creación
   - Pre-llenar todos los campos con datos actuales
   - Marcar campos modificados con indicador visual
   - Botón "Restaurar valor original" por campo
   - Comparación lado a lado: "Valor actual" vs "Nuevo valor"
   
3. Registro de cambios:
   - Guardar en tabla wp_aura_finance_transaction_history:
     * transaction_id
     * field_changed (columna modificada)
     * old_value
     * new_value
     * changed_by (usuario)
     * changed_at (timestamp)
     * change_reason (opcional)
   
   - Tabla de historial visible en modal de detalle (tab Auditoría)
   
4. Validaciones específicas:
   - No permitir cambio de categoría si afecta presupuestos
   - Verificar que nueva categoría coincida con tipo de transacción
   - Si cambia monto significativamente (>20%), requerir motivo
   
5. Notificaciones:
   - Si transacción estaba aprobada y se edita, volver a "pendiente"
   - Notificar al aprobador que debe revisar nuevamente
   - Email opcional al creador si alguien más edita su transacción
   
6. Implementación:
   - Action: wp_ajax_aura_update_transaction
   - Hook: do_action('aura_finance_transaction_updated', $old, $new, $user_id)
   - Archivo: modules/financial/class-financial-transactions-update.php
```

**Checklist de Implementación:**
- [ ] Control de permisos implementado
- [ ] Formulario de edición funcional
- [ ] Registro de cambios en BD
- [ ] Validaciones específicas
- [ ] Notificaciones configuradas
- [ ] Tab de auditoría mostrando historial
- [ ] Testing de casos edge

---

### Item 2.5: Eliminación de Transacciones (Soft Delete)

**Prompt de Desarrollo:**
```
Implementa un sistema de eliminación con soft delete, recuperación y eliminación 
permanente:

1. Tipos de eliminación:
   
   A. Soft Delete (por defecto):
      - Marcar campo deleted_at con timestamp
      - Transacción no aparece en listados normales
      - Recuperable desde "Papelera"
      - Permisos: aura_finance_delete_own o aura_finance_delete_all
      
   B. Hard Delete (permanente):
      - Eliminar completamente de BD
      - Solo administradores
      - Requiere doble confirmación
      - No recuperable
   
2. Flujo de eliminación:
   
   Paso 1: Usuario hace click en "Eliminar"
   - Modal de confirmación:
     "¿Eliminar transacción de $X,XXX por [Descripción]?
      Esta acción enviará la transacción a la papelera.
      [Cancelar] [Enviar a Papelera]"
   
   Paso 2: Transacción marcada como eliminada
   - Actualizar deleted_at = NOW()
   - Mensaje: "Transacción enviada a papelera. [Deshacer]"
   - Botón deshacer disponible 10 segundos
   
3. Página de Papelera:
   - Similar a listado de transacciones
   - Filtros activos
   - Columna adicional: "Eliminado el"
   - Acciones: [Restaurar] [Eliminar permanentemente]
   - Acción masiva: Vaciar papelera (después de N días)
   
4. Restauración:
   - Limpiar campo deleted_at
   - Transacción vuelve a listado principal
   - Mensaje: "Transacción restaurada exitosamente"
   
5. Auto-limpieza:
   - Cron job diario: aura_finance_empty_trash
   - Eliminar permanentemente transacciones en papelera > 30 días
   - Configurable en ajustes
   
6. Validaciones:
   - No permitir eliminar transacciones referenciadas (ej. en reportes fiscales)
   - Advertir si es una transacción de alto monto (configurable)
   - Log de eliminaciones en tabla de auditoría
   
7. Implementación:
   - wp_ajax_aura_delete_transaction
   - wp_ajax_aura_restore_transaction
   - wp_ajax_aura_permanent_delete_transaction
   - Función: aura_finance_empty_trash_scheduled()
   - Archivo: modules/financial/class-financial-transactions-delete.php
```

**Checklist de Implementación:**
- [ ] Soft delete implementado
- [ ] Modal de confirmación
- [ ] Botón "Deshacer" funcional
- [ ] Página de papelera completa
- [ ] Restauración funcionando
- [ ] Hard delete con doble confirmación
- [ ] Cron job de auto-limpieza
- [ ] Validaciones de seguridad
- [ ] Log de auditoría

---

### Item 2.6: Sistema de Aprobación y Rechazo

**Prompt de Desarrollo:**
```
Desarrolla un flujo completo de aprobación de transacciones con notificaciones y 
seguimiento:

1. Estados de transacción:
   - pending: Recién creada, esperando aprobación
   - approved: Aprobada por usuario con permisos
   - rejected: Rechazada con motivo especificado
   
2. Flujo de aprobación:
   
   A. Usuario con aura_finance_approve:
      - Ve badge "Pendientes: N" en menú
      - Acceso a página "Aprobaciones Pendientes"
      - Listado filtrado solo con status=pending
      
   B. Aprobar transacción:
      - Botón "Aprobar" en listado o modal
      - Opcional: Agregar nota de aprobación
      - Actualizar:
        * status = 'approved'
        * approved_by = current_user_id
        * approved_at = NOW()
      - Notificación al creador: "Tu transacción fue aprobada"
      
   C. Rechazar transacción:
      - Botón "Rechazar" en listado o modal
      - Modal obligatorio: "Motivo de rechazo" (textarea, min 20 chars)
      - Actualizar:
        * status = 'rejected'
        * approved_by = current_user_id (quien rechaza)
        * rejection_reason = motivo
        * approved_at = NOW()
      - Notificación al creador: "Tu transacción fue rechazada. Motivo: [...]"
      - Opción: Permitir editar y reenviar (vuelve a pending)
   
3. Página "Aprobaciones Pendientes":
   - Contador en tiempo real
   - Filtros: Por monto, por categoría, por usuario
   - Ordenar: Por fecha, por monto, por prioridad
   - Vista rápida con datos clave
   - Aprobar/Rechazar en masa (checkbox)
   
4. Notificaciones:
   
   A. Email (opcional, configurable):
      - Al creador cuando su transacción es aprobada/rechazada
      - Al aprobador cuando hay nuevas pendientes (diario)
      
   B. Notificación in-app:
      - Badge con contador en menú
      - Dropdown con últimas 5 pendientes
      - Link a la transacción específica
      
5. Dashboard widget:
   - "Transacciones Pendientes"
   - Muestra últimas 5
   - Link "Ver todas"
   
6. Permisos y restricciones:
   - Usuario no puede aprobar sus propias transacciones
   - Admin puede configurar aprobación automática bajo cierto monto
   - Multi-nivel: Aprobador 1 (hasta $X) → Aprobador 2 (hasta $Y) → Admin (sin límite)
   
7. Implementación:
   - wp_ajax_aura_approve_transaction
   - wp_ajax_aura_reject_transaction
   - wp_ajax_aura_get_pending_count
   - Hook: do_action('aura_finance_transaction_approved', $transaction_id, $approver_id)
   - Hook: do_action('aura_finance_transaction_rejected', $transaction_id, $approver_id, $reason)
   - Archivo: modules/financial/class-financial-approval.php
```

**Checklist de Implementación:**
- [ ] Estados de transacción funcionando
- [ ] Flujo de aprobación completo
- [ ] Página de pendientes
- [ ] Modal de rechazo con validación
- [ ] Sistema de notificaciones in-app
- [ ] Emails opcionales
- [ ] Dashboard widget
- [ ] Restricción de auto-aprobación
- [ ] Niveles de aprobación (opcional)
- [ ] Testing de workflow completo

---

## FASE 3: Dashboard y Visualizaciones

### 📌 Objetivo de la Fase
Crear un dashboard financiero interactivo con gráficos, KPIs y reportes visuales para análisis rápido.

---

### Item 3.1: Dashboard Principal del Módulo

**Prompt de Desarrollo:**
```
Desarrolla un dashboard financiero completo con widgets interactivos y gráficos en 
tiempo real:

1. Estructura del dashboard (layout en grid responsivo):
   
   Fila 1: KPIs principales (4 cards)
   ┌─────────────┬─────────────┬─────────────┬─────────────┐
   │ Total       │ Total       │ Balance     │ Pendientes  │
   │ Ingresos    │ Egresos     │ Neto        │ Aprobación  │
   │ $XX,XXX     │ $XX,XXX     │ $XX,XXX     │ N trans.    │
   │ ↑ +15%      │ ↓ -8%       │ ● Verde/Rojo│ [Ver]       │
   └─────────────┴─────────────┴─────────────┴─────────────┘
   
   Fila 2: Gráficos principales
   ┌──────────────────────────────┬─────────────────────────┐
   │ Ingresos vs Egresos          │ Gastos por Categoría    │
   │ (Gráfico de líneas mensual)  │ (Gráfico de dona)       │
   │                              │                         │
   │ Chart.js/ApexCharts          │ Top 5 categorías        │
   └──────────────────────────────┴─────────────────────────┘
   
   Fila 3: Información adicional
   ┌──────────────────────────────┬─────────────────────────┐
   │ Últimas Transacciones        │ Alertas y Notificaciones│
   │ (Tabla compacta, 10 items)   │ • Presupuesto excedido  │
   │ [Ver todas →]                │ • N pendientes de aprobar│
   │                              │ • Comprobantes faltantes│
   └──────────────────────────────┴─────────────────────────┘

2. Filtros globales del dashboard:
   - Selector de período:
     * Hoy
     * Esta semana
     * Este mes (default)
     * Este trimestre
     * Este año
     * Personalizado (date range picker)
   
   - Comparar con período anterior (checkbox)
   
   - Filtrar por categorías (multiselect)
   
3. KPIs detallados:
   
   A. Total Ingresos:
      - Monto del período
      - Porcentaje de cambio vs período anterior
      - Icono tendencia (↑↓)
      - Sparkline (mini gráfico)
      - Click para drill-down
      
   B. Total Egresos:
      - Igual que ingresos
      - Desglose: Fijos vs Variables (tooltip)
      
   C. Balance Neto:
      - Ingresos - Egresos
      - Color dinámico (verde si positivo, rojo si negativo)
      - Barra de progreso hacia objetivo (si hay presupuesto)
      
   D. Pendientes de Aprobación:
      - Contador live
      - Monto total pendiente
      - Link directo a página de aprobaciones
   
4. Gráfico "Ingresos vs Egresos":
   - Tipo: Líneas (dual)
   - Eje X: Meses/semanas/días (según período)
   - Eje Y: Monto ($)
   - Línea verde: Ingresos
   - Línea roja: Egresos
   - Área sombreada: Balance
   - Interactivo: Hover muestra tooltip con valores
   - Exportar: PNG, SVG, CSV
   
5. Gráfico "Gastos por Categoría":
   - Tipo: Dona (doughnut)
   - Colores: De cada categoría
   - Porcentajes mostrados
   - Leyenda: Nombre + monto + %
   - Click en segmento: Filtra transacciones de esa categoría
   - Centro: Total de egresos
   
6. Widget "Últimas Transacciones":
   - Tabla compacta: Fecha, Tipo, Categoría, Monto, Estado
   - Solo 10 más recientes
   - Estados con badges
   - Link "Ver todas" a página de listado
   
7. Widget "Alertas":
   - Alertas codificadas por color:
     * Rojo: Crítico (presupuesto excedido >100%)
     * Amarillo: Advertencia (presupuesto >80%)
     * Azul: Informativo
   - Ejemplos:
     * "Presupuesto de 'Suministros' al 95%"
     * "5 transacciones pendientes hace más de 7 días"
     * "10 transacciones sin comprobante"
   
8. Tecnologías:
   - Chart.js o ApexCharts para gráficos
   - AJAX para actualización de datos
   - LocalStorage para guardar preferencias de filtros
   
9. Archivos:
   - templates/financial/dashboard.php
   - assets/js/financial-dashboard.js
   - modules/financial/class-financial-dashboard.php
   - assets/css/financial-dashboard.css
```

**Checklist de Implementación:**
- [ ] Layout en grid responsivo
- [ ] 4 KPIs funcionales con datos reales
- [ ] Gráfico de líneas ingresos vs egresos
- [ ] Gráfico de dona por categorías
- [ ] Widget de últimas transacciones
- [ ] Widget de alertas
- [ ] Filtros globales funcionando
- [ ] Comparación con período anterior
- [ ] AJAX refresh sin recargar página
- [ ] Exportación de gráficos
- [ ] Responsive en móvil/tablet
- [ ] Performance optimizado

---

### Item 3.2: Reportes Financieros Predefinidos

**Prompt de Desarrollo:**
```
Implementa una librería de reportes financieros listos para usar, exportables y 
personalizables:

1. Tipos de reportes predefinidos:
   
   A. Estado de Resultados (P&L):
      - Ingresos por categoría
      - Egresos por categoría
      - Balance neto
      - Período: Seleccionable
      - Comparación año anterior
      
   B. Flujo de Efectivo:
      - Efectivo inicial
      - Entradas de efectivo
      - Salidas de efectivo
      - Efectivo final
      - Desglose por método de pago
      
   C. Análisis por Categoría:
      - Top 10 categorías con más movimiento
      - Distribución porcentual
      - Tendencia mensual por categoría
      - Gráfico de barras comparativo
      
   D. Reporte de Transacciones Pendientes:
      - Listado completo de pendientes
      - Agrupado por usuario creador
      - Antigüedad de cada transacción
      - Monto total pendiente
      
   E. Reporte de Presupuesto:
      - Presupuesto asignado vs ejecutado
      - Porcentaje de ejecución
      - Proyección al final del período
      - Alertas de sobregiro
      
   F. Reporte de Auditoría:
      - Todas las transacciones del período
      - Con detalles de auditoría
      - Creador, aprobador, fechas
      - Cambios realizados
      
2. Interfaz de generación de reportes:
   
   Página: "Reportes Financieros"
   
   ┌─────────────────────────────────────────────┐
   │ Seleccionar tipo de reporte:               │
   │ [Dropdown con los 6 tipos]                  │
   │                                             │
   │ Configurar parámetros:                      │
   │ • Período: [Date range picker]             │
   │ • Categorías: [Multiselect]                │
   │ • Estados: [Pendiente, Aprobado, Rechazado]│
   │ • Creado por: [Select usuarios]            │
   │                                             │
   │ Formato de salida:                          │
   │ ○ Ver en pantalla                          │
   │ ○ Exportar PDF                             │
   │ ○ Exportar Excel                           │
   │ ○ Exportar CSV                             │
   │                                             │
   │ [Generar Reporte]                           │
   └─────────────────────────────────────────────┘
   
3. Vista previa en pantalla:
   - Tabla responsive con datos
   - Gráficos relevantes según tipo
   - Totales y subtotales destacados
   - Paginación si es extenso
   - Botones: [Imprimir] [Exportar] [Guardar configuración]
   
4. Exportación PDF:
   - Librería: TCPDF o DomPDF
   - Header: Logo, nombre empresa, fecha
   - Contenido: Tabla formateada + gráficos
   - Footer: Página X de Y, usuario generador
   - Marca de agua: "Generado el [fecha]"
   
5. Exportación Excel:
   - Librería: PhpSpreadsheet
   - Hoja 1: Datos tabulados
   - Hoja 2: Gráficos (si aplica)
   - Formato: Números con formato moneda, fechas dd/mm/yyyy
   - Totales en negrita
   
6. Reportes programados (opcional):
   - Configurar envío automático por email
   - Periodicidad: Semanal, quincenal, mensual
   - Destinatarios: Múltiples emails
   - Formato: PDF adjunto
   - Cron job: aura_finance_scheduled_reports
   
7. Guardar configuraciones:
   - Usuario puede guardar configuración de reporte favorito
   - Nombre descriptivo: "Reporte mensual de gastos operativos"
   - Aplicar con un click
   - Compartir con otros usuarios (opcional)
   
8. Implementación:
   - modules/financial/class-financial-reports.php
   - templates/financial/reports-page.php
   - assets/js/financial-reports.js
   - wp_ajax_aura_generate_report
   - wp_ajax_aura_export_report_pdf
   - wp_ajax_aura_export_report_excel
```

**Checklist de Implementación:**
- [ ] 6 tipos de reportes implementados
- [ ] Interfaz de selección y configuración
- [ ] Vista previa en pantalla
- [ ] Exportación PDF funcional
- [ ] Exportación Excel funcional
- [ ] Exportación CSV funcional
- [ ] Reportes programados (cron)
- [ ] Guardar configuraciones
- [ ] Permisos por tipo de reporte
- [ ] Testing de cada tipo
- [ ] Documentación de uso

---

### Item 3.3: Gráficos Interactivos Avanzados

**Prompt de Desarrollo:**
```
Crea una página de análisis visual con gráficos interactivos y comparaciones 
avanzadas:

1. Página "Análisis Visual" con tabs:
   
   Tab 1: Tendencias Temporales
   - Gráfico de líneas: Ingresos, Egresos, Balance (3 líneas)
   - Selector de granularidad: Día/Semana/Mes/Trimestre/Año
   - Zoom interactivo (arrastrar para ampliar)
   - Marcadores de eventos (ej. "Mayor ingreso del año")
   - Proyección futura (línea punteada basada en tendencia)
   
   Tab 2: Distribución por Categorías
   - Gráfico de barras horizontales: Top 10 categorías
   - Toggle: Ingresos / Egresos / Ambos
   - Ordenar por: Monto / Frecuencia / Alfabético
   - Subcategorías expandibles
   - Drill-down: Click abre listado de transacciones
   
   Tab 3: Comparaciones
   - Selector de períodos: Período A vs Período B
   - Gráfico de barras comparativas lado a lado
   - Tabla de diferencias absolutas y porcentuales
   - Destacar categorías con mayor variación
   
   Tab 4: Análisis de Patrones
   - Heatmap: Día de la semana vs Hora (cuándo se registran más)
   - Gráfico de dispersión: Correlación monto vs frecuencia por categoría
   - Identificación de outliers (transacciones atípicas)
   
   Tab 5: Presupuesto vs Realidad
   - Gráfico de barras con dos colores:
     * Verde: Dentro de presupuesto
     * Rojo: Sobre presupuesto
   - Porcentaje de ejecución por categoría
   - Proyección: "A este ritmo, terminará el mes en X%"
   
2. Controles interactivos:
   - Filtros aplicados a todos los tabs
   - Sincronización de períodos entre tabs
   - Botón "Resetear vista"
   - Modo pantalla completa por gráfico
   - Comparar hasta 3 categorías simultáneamente
   
3. Tooltips enriquecidos:
   - Al hacer hover en punto/barra:
     * Fecha exacta
     * Monto
     * Cantidad de transacciones
     * Categoría principal
     * Link "Ver detalles"
   
4. Anotaciones manuales:
   - Usuario puede agregar anotaciones en gráficos
   - Ej. "Aumento por campaña de donaciones"
   - Guardar en BD, mostrar como marcadores persistentes
   
5. Librería de gráficos:
   - ApexCharts (recomendado por interactividad)
   - Fallback: Chart.js
   - Consistencia visual con paleta de colores del proyecto
   
6. Archivos:
   - templates/financial/visual-analytics.php
   - assets/js/financial-charts-advanced.js
   - modules/financial/class-financial-analytics.php
```

**Checklist de Implementación:**
- [ ] 5 tabs de análisis implementados
- [ ] Gráficos interactivos funcionando
- [ ] Filtros sincronizados entre tabs
- [ ] Tooltips enriquecidos
- [ ] Sistema de anotaciones
- [ ] Zoom y drill-down
- [ ] Proyecciones futuras
- [ ] Performance con grandes volúmenes de datos
- [ ] Responsive design
- [ ] Testing de interactividad

---

## FASE 4: Reportes y Exportación

### 📌 Objetivo de la Fase
Implementar capacidades avanzadas de exportación, integración con otras herramientas y reportes personalizables.

---

### Item 4.1: Sistema de Exportación Multi-formato

**Prompt de Desarrollo:**
```
Desarrolla un sistema robusto de exportación que soporte múltiples formatos y use 
cases:

1. Formatos soportados:
   
   A. CSV (valores separados por coma):
      - Columnas: ID, Fecha, Tipo, Categoría, Monto, Descripción, Estado, Creador
      - Encoding: UTF-8 con BOM (compatibilidad Excel)
      - Delimitador configurable: coma, punto y coma, tab
      - Uso: Importar a Excel, Google Sheets, sistemas contables
      
   B. Excel (.xlsx):
      - Librería: PhpSpreadsheet
      - Múltiples hojas: Transacciones, Resumen, Gráficos
      - Formato: Números con formato moneda, fechas formateadas
      - Estilos: Encabezados en negrita, filas alternadas
      - Filtros automáticos en encabezados
      - Totales al final de columna "Monto"
      - Gráfico embebido (opcional)
      
   C. PDF:
      - Librería: TCPDF
      - Orientación: Horizontal (landscape) para tablas anchas
      - Header personalizado: Logo, título, período
      - Footer: Número de página, fecha generación, usuario
      - Tabla responsive: Ajuste automático de columnas
      - Saltos de página inteligentes
      - Marca de agua: "Confidencial" (opcional)
      
   D. JSON:
      - Estructura completa de transacciones
      - Uso: APIs, integraciones, backups
      - Incluir metadata: Total de registros, fecha de exportación
      
   E. XML:
      - Formato: Estándar contable (opcional)
      - Uso: Importar a software contable especializado
   
2. Interfaz de exportación:
   
   Botón "Exportar" en listado de transacciones
   → Abrir modal:
   
   ┌───────────────────────────────────────────┐
   │ Exportar Transacciones                    │
   │                                           │
   │ Formato:                                  │
   │ ○ CSV    ○ Excel    ○ PDF    ○ JSON      │
   │                                           │
   │ Datos a exportar:                         │
   │ ☑ Usar filtros actuales (N transacciones)│
   │ ☐ Todas las transacciones                │
   │ ☐ Selección actual (M seleccionadas)     │
   │                                           │
   │ Columnas a incluir:                       │
   │ ☑ ID ☑ Fecha ☑ Tipo ☑ Categoría         │
   │ ☑ Monto ☑ Descripción ☑ Estado          │
   │ ☐ Notas ☐ Método de pago ☐ Referencia   │
   │ ☐ Creado por ☐ Aprobado por             │
   │                                           │
   │ Opciones adicionales (según formato):     │
   │ • Incluir logo empresa [CSV/Excel/PDF]   │
   │ • Incluir totales [Excel/PDF]            │
   │ • Incluir gráfico resumen [Excel/PDF]    │
   │                                           │
   │ [Cancelar]  [Exportar]                   │
   └───────────────────────────────────────────┘
   
3. Generación de archivo:
   - Mostrar loading con progreso (si >1000 registros)
   - Generar en background si >5000 registros
   - Descargar automáticamente o link de descarga
   - Guardar en /wp-content/uploads/aura-exports/ (temporal)
   - Auto-eliminar archivos después de 24 horas (cron job)
   
4. Exportación programada:
   - Configurar exportación automática periódica
   - Enviar por email
   - Subir a servidor FTP/SFTP (opcional)
   - Sincronizar con Google Drive/Dropbox (integración futura)
   
5. Límites y permisos:
   - Solo exportar transacciones que el usuario tiene permiso de ver
   - Límite de registros por exportación: Configurable (default 10,000)
   - Log de exportaciones: Quién, cuándo, cuántos registros
   
6. Implementación:
   - wp_ajax_aura_export_transactions
   - modules/financial/class-financial-export.php
   - Función: aura_finance_generate_csv(), generate_excel(), generate_pdf()
```

**Checklist de Implementación:**
- [ ] 5 formatos de exportación funcionando
- [ ] Modal de opciones de exportación
- [ ] Exportación de datos filtrados
- [ ] Selección de columnas
- [ ] Opciones específicas por formato
- [ ] Generación en background (grandes volúmenes)
- [ ] Auto-limpieza de archivos temporales
- [ ] Verificación de permisos
- [ ] Log de exportaciones
- [ ] Testing con diferentes volúmenes de datos

---

### Item 4.2: Importación de Transacciones (CSV/Excel)

**Prompt de Desarrollo:**
```
Implementa un sistema de importación masiva de transacciones desde archivos 
externos con validación y previsualización:

1. Página " Importar Transacciones":
   
   Paso 1: Subir archivo
   ┌────────────────────────────────────────┐
   │ Seleccionar archivo:                   │
   │ [Seleccionar archivo .csv o .xlsx]    │
   │                                        │
   │ Formatos aceptados: CSV, Excel (.xlsx)│
   │ Tamaño máximo: 5 MB                   │
   │ Máximo registros: 1,000                │
   │                                        │
   │ [Subir y Validar]                      │
   └────────────────────────────────────────┘
   
   Paso 2: Mapear columnas
   - Mostrar vista previa (primeras 5 filas)
   - Mapear columnas del archivo a campos del sistema:
   
   | Columna Archivo | ← | Campo Sistema     |
   |-----------------|---|-------------------|
   | fecha           | → | transaction_date  |
   | tipo            | → | transaction_type  |
   | categoria       | → | category_id       |
   | monto           | → | amount            |
   | descripcion     | → | description       |
   | [Ignorar]       |   | notes             |
   
   - Auto-detección inteligente de columnas
   - Opción de guardar mapeo como plantilla
   
   Paso 3: Validar datos
   - Ejecutar validaciones:
     * Fechas en formato válido
     * Montos numéricos
     * Categorías existen en sistema (o crear nuevas)
     * Tipos válidos (income/expense)
   
   - Mostrar errores por fila:
     Fila 3: ❌ Categoría "Ventas" no existe. [Crear automáticamente] [Ignorar fila]
     Fila 7: ❌ Monto inválido: "1.500,00" (formato incorrecto)
     Fila 12: ⚠️ Descripción vacía (se llenará con "Importado")
   
   - Estadísticas:
     * Total filas: 50
     * Válidas: 47
     * Con errores: 3
     * Con advertencias: 5
   
   Paso 4: Confirmar importación
   ┌────────────────────────────────────────┐
   │ Opciones de importación:               │
   │                                        │
   │ Estado de transacciones importadas:    │
   │ ○ Pendientes (requieren aprobación)   │
   │ ● Aprobadas automáticamente           │
   │                                        │
   │ Si categoría no existe:                │
   │ ● Crear automáticamente               │
   │ ○ Marcar fila como error              │
   │                                        │
   │ Duplicados (misma fecha + monto):      │
   │ ○ Ignorar fila                        │
   │ ○ Importar como nueva                 │
   │ ● Solicitar confirmación               │
   │                                        │
   │ [Cancelar] [Importar N transacciones] │
   └────────────────────────────────────────┘
   
   Paso 5: Resultado
   - Barra de progreso durante importación
   - Resumen final:
     ✅ 47 transacciones importadas exitosamente
     ❌ 3 filas ignoradas (ver log de errores)
     
   - Botones:
     [Ver transacciones importadas]
     [Descargar log de errores]
     [Importar otro archivo]

2. Plantilla de ejemplo:
   - Botón "Descargar plantilla CSV" pre-llenada con columnas correctas
   - 2-3 filas de ejemplo
   
3. Validaciones específicas:
   - Formato de fecha: dd/mm/yyyy, yyyy-mm-dd, etc. (auto-detectar)
   - Formato de monto: Detectar separador decimal (. o ,)
   - Tipo: Aceptar "ingreso/egreso" o "income/expense" o "I/E"
   - Categoría: Buscar por nombre o ID
   
4. Detección de duplicados:
   - Comparar: fecha + monto + descripción (similarity 80%)
   - Mostrar modal: "Posible duplicado encontrado. ¿Importar de todas formas?"
   
5. Rollback:
   - Guardar ID de importación
   - Opción "Deshacer importación" (enviar a papelera todas)
   - Disponible 24 horas después de importar
   
6. Implementación:
   - wp_ajax_aura_upload_import_file
   - wp_ajax_aura_validate_import
   - wp_ajax_aura_execute_import
   - modules/financial/class-financial-import.php
```

**Checklist de Implementación:**
- [ ] Upload de archivo CSV/Excel
- [ ] Vista previa de datos
- [ ] Mapeo de columnas
- [ ] Validaciones robustas
- [ ] Creación automática de categorías
- [ ] Detección de duplicados
- [ ] Importación con progreso
- [ ] Log de errores descargable
- [ ] Plantilla de ejemplo
- [ ] Opción de rollback
- [ ] Testing con archivos reales

---

## FASE 5: Funciones Avanzadas

### 📌 Objetivo de la Fase
Implementar características avanzadas que elevan el módulo a nivel profesional.

---

### Item 5.1: Sistema de Presupuestos por Categoría

**Prompt de Desarrollo:**
```
Desarrolla un sistema completo de gestión de presupuestos con alertas y seguimiento:

1. Página "Presupuestos":
   
   Vista principal: Tabla de presupuestos
   ┌────────────────────────────────────────────────────────────┐
   │ Categoría      │ Período  │ Presupuesto │ Ejecutado │ %  │
   │────────────────│──────────│─────────────│───────────│────│
   │ Suministros    │ Mensual  │ $5,000      │ $4,750    │ 95%│
   │ Mantenimiento  │ Mensual  │ $3,000      │ $1,200    │ 40%│
   │ Salarios       │ Mensual  │ $50,000     │ $50,000   │100%│
   └────────────────────────────────────────────────────────────┘
   
   - Barra de progreso visual por presupuesto:
     * Verde: 0-70%
     * Amarillo: 71-90%
     * Naranja: 91-100%
     * Rojo: >100% (sobrepasado)
   
   - Acciones: [Editar] [Ver detalle] [Eliminar]
   - Botón: [+ Nuevo Presupuesto]

2. Formulario "Crear/Editar Presupuesto":
   
   ┌────────────────────────────────────────┐
   │ Categoría: [Select]                    │
   │                                        │
   │ Monto del presupuesto: [$_____]        │
   │                                        │
   │ Período:                               │
   │ ● Mensual  ○ Trimestral  ○ Anual     │
   │                                        │
   │ Vigencia:                              │
   │ Desde: [01/01/2026]                   │
   │ Hasta: [31/12/2026]                   │
   │                                        │
   │ Alertas:                               │
   │ ☑ Enviar alerta al llegar a [80]%    │
   │ ☑ Enviar alerta al sobrepasar         │
   │                                        │
   │ Notificar a:                           │
   │ ☑ Creador del presupuesto             │
   │ ☑ Usuarios con rol: [Administrador]   │
   │ ☑ Email adicional: [email]            │
   │                                        │
   │ [Cancelar] [Guardar Presupuesto]       │
   └────────────────────────────────────────┘

3. Detalle de presupuesto (modal o página):
   
   A. Gráfico circular:
      - Ejecutado (color de categoría)
      - Disponible (gris claro)
      - Sobregiro (rojo, si aplica)
      
   B. Estadísticas:
      - Presupuesto total: $5,000
      - Ejecutado: $4,750 (95%)
      - Disponible: $250 (5%)
      - Proyección fin de período: $5,200 (104%)
      
   C. Transacciones del presupuesto:
      - Listado filtrado de transacciones de esa categoría en el período
      - Ordenadas por fecha descendente
      - Subtotal acumulado
      
   D. Historial:
      - Comparación con períodos anteriores
      - Gráfico de líneas: Presupuesto vs Ejecutado (últimos 6 períodos)

4. Widget de dashboard "Estado de Presupuestos":
   - Mostrar 5 presupuestos más críticos (mayor %)
   - Alertas en rojo si están sobrepasados
   - Link a página completa

5. Sistema de alertas:
   
   A. Alerta al 80%:
      - Notificación in-app
      - Email (opcional):
        Asunto: "⚠️ Presupuesto de [Categoría] al 80%"
        Cuerpo: Detalles del presupuesto, link al detalle
        
   B. Alerta al 100%:
      - Notificación destacada (roja)
      - Email obligatorio
        
   C. Alerta de sobregiro:
      - Notificación crítica
      - Email a administradores
      - Opcional: Bloquear nuevas transacciones de esa categoría
   
6. Reportes de presupuesto:
   - "Comparativo Presupuesto vs Real" (PDF/Excel)
   - Análisis de variaciones
   - Categorías con mayor desviación
   - Recomendaciones (IA opcional)

7. Funciones avanzadas:
   - Ajuste de presupuesto (aumentar/disminuir en un %)
   - Transferir presupuesto entre categorías
   - Presupuestos flexibles (rango min-max)
   - Presupuesto por proyecto (si hay módulo de proyectos)

8. Implementación:
   - Tabla: wp_aura_finance_budgets
   - modules/financial/class-financial-budgets.php
   - templates/financial/budgets-page.php
   - wp_ajax_aura_create_budget
   - wp_ajax_aura_get_budget_progress
   - Cron: aura_finance_check_budgets_daily()
```

**Checklist de Implementación:**
- [ ] CRUD completo de presupuestos
- [ ] Cálculo de ejecución en tiempo real
- [ ] Alertas al 80% y 100%
- [ ] Widget en dashboard
- [ ] Gráficos de presupuesto
- [ ] Proyección de fin de período
- [ ] Comparación histórica
- [ ] Reportes de presupuesto
- [ ] Notificaciones por email
- [ ] Testing de alertas

---

### Item 5.2: Etiquetas (Tags) y Búsqueda Avanzada

**Prompt de Desarrollo:**
```
Implementa un sistema de etiquetado flexible y búsqueda full-text avanzada:

1. Sistema de etiquetas:
   
   A. Agregar etiquetas a transacciones:
      - Campo "Etiquetas" en formulario de transacción
      - Input con autocompletado (suggest etiquetas existentes)
      - Múltiples etiquetas separadas por coma
      - Máximo 10 etiquetas por transacción
      
   B. Gestión de etiquetas:
      - Página "Etiquetas" (similar a categorías)
      - Listar todas las etiquetas con contador de uso
      - Renombrar etiqueta (actualiza todas las transacciones)
      - Fusionar etiquetas duplicadas
      - Eliminar etiquetas no usadas
      
   C. Visualización:
      - Tags como badges con colores suaves
      - Click en tag filtra transacciones con ese tag
      - Nube de etiquetas (tag cloud) en dashboard

2. Búsqueda avanzada:
   
   Página "Búsqueda Avanzada" o expandir formulario en listado:
   
   ┌──────────────────────────────────────────────┐
   │ Búsqueda de Transacciones                    │
   │                                              │
   │ Texto libre: [____________________________] │
   │ Buscar en: ☑ Descripción ☑ Notas           │
   │            ☑ Referencia ☑ Beneficiario      │
   │                                              │
   │ Filtros:                                     │
   │ Fecha: [Desde] - [Hasta]                   │
   │ Tipo: ☐ Ingresos ☐ Egresos                 │
   │ Categorías: [Multi-select]                  │
   │ Estado: [Todos / Pendiente / Aprobado...]  │
   │ Monto: Min [___] - Max [___]               │
   │ Método de pago: [Multi-select]              │
   │ Etiquetas: [Multi-select con autocompletar] │
   │ Creado por: [Select usuarios]               │
   │ Con comprobante: ○ Sí ○ No ○ Ambos        │
   │                                              │
   │ [Limpiar filtros] [Buscar]                  │
   │ [Guardar búsqueda...]                       │
   └──────────────────────────────────────────────┘
   
3. Búsquedas guardadas:
   - Guardar configuración de filtros actual
   - Nombre descriptivo: "Gastos mayores a $5000 del último trimestre"
   - Acceso rápido desde sidebar
   - Compartir con otros usuarios (opcional)

4. Operadores avanzados en texto libre:
   - "frase exacta" → Buscar frase completa
   - palabra1 AND palabra2 → Ambas presentes
   - palabra1 OR palabra2 → Al menos una presente
   - -palabra → Excluir resultados con esa palabra
   - categoría:Suministros → Buscar en campo específico
   - monto:>1000 → Operadores numéricos

5. Resultados de búsqueda:
   - Tabla similar a listado principal
   - Resaltar términos encontrados (highlight)
   - Ordenar por relevancia o por fecha
   - Estadísticas: "X resultados encontrados, Total: $Y"
   - Exportar resultados

6. Búsqueda relacionada:
   - Sugerencias: "También buscar: [tag relacionado]"
   - Últimas búsquedas del usuario (localStorage)

7. Implementación:
   - Full-text search con MySQL MATCH AGAINST
   - Índices en columnas: description, notes, reference_number
   - wp_ajax_aura_advanced_search
   - modules/financial/class-financial-search.php
```

**Checklist de Implementación:**
- [ ] Sistema de etiquetas funcional
- [ ] Autocompletado de tags
- [ ] Gestión de etiquetas (CRUD)
- [ ] Nube de etiquetas
- [ ] Formulario de búsqueda avanzada
- [ ] Operadores de búsqueda
- [ ] Búsquedas guardadas
- [ ] Highlighting de resultados
- [ ] Full-text search optimizado
- [ ] Testing de performance

---

### Item 5.3: Auditoría y Trazabilidad Completa

**Prompt de Desarrollo:**
```
Implementa un sistema robusto de auditoría que registre todas las acciones del módulo:

1. Tabla de logs de auditoría:

```sql
CREATE TABLE wp_aura_finance_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;
```

2. Acciones a registrar:
   
   Transacciones:
   - transaction_created
   - transaction_updated (con diff de campos)
   - transaction_deleted
   - transaction_restored
   - transaction_approved
   - transaction_rejected
   
   Categorías:
   - category_created
   - category_updated
   - category_deleted
   
   Presupuestos:
   - budget_created
   - budget_updated
   - budget_exceeded
   
   Exportaciones:
   - export_executed (formato, cantidad de registros)
   
   Importaciones:
   - import_executed (cantidad exitosas, cantidad errores)
   
   Configuraciones:
   - settings_updated

3. Función de logging:

```php
function aura_finance_log_action( $action, $entity_type, $entity_id = null, $old_value = null, $new_value = null ) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $ip_address = aura_get_user_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $wpdb->insert(
        $wpdb->prefix . 'aura_finance_audit_log',
        [
            'user_id' => $user_id,
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'old_value' => json_encode($old_value),
            'new_value' => json_encode($new_value),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
        ]
    );
}
```

4. Página "Registro de Auditoría":
   
   Solo accesible para: administradores y auditores
   
   Tabla de logs:
   ┌───────────────────────────────────────────────────────────┐
   │ Fecha/Hora  │ Usuario  │ Acción           │ Entidad    │ IP      │
   │─────────────│──────────│──────────────────│────────────│─────────│
   │ 15/02 14:30 │ Juan P.  │ Transacción creada│ Trans #123│ 192...  │
   │ 15/02 14:32 │ María L. │ Transacción aprobada│ Trans #123│ 10...│
   │ 15/02 15:00 │ Carlos R.│ Categoría eliminada│ Cat #45  │ 172...  │
   └───────────────────────────────────────────────────────────┘
   
   Filtros:
   - Por usuario
   - Por acción
   - Por rango de fechas
   - Por tipo de entidad
   - Por IP (detectar accesos sospechosos)
   
   Detalle de log (expandible):
   - Diff visual de cambios (antes/después)
   - User agent completo
   - Link a la entidad (si aún existe)

5. Widget de dashboard "Actividad Reciente":
   - Últimas 10 acciones en el módulo
   - Tiempo relativo ("hace 5 minutos")
   - Filtro rápido: "Ver solo mis acciones"

6. Alertas de seguridad:
   - Detectar múltiples intentos de eliminación
   - Detectar cambios masivos en poco tiempo
   - Accesos desde IPs inusuales
   - Exportaciones de gran volumen

7. Retención de logs:
   - Configurar tiempo de retención (default: 1 año)
   - Archivar logs antiguos
   - Opción de exportar logs antes de eliminar

8. Implementación:
   - Hook: do_action('aura_finance_log', $action, $entity_type, ...)
   - modules/financial/class-financial-audit.php
   - templates/financial/audit-log-page.php
```

**Checklist de Implementación:**
- [ ] Tabla de audit log creada
- [ ] Función de logging implementada
- [ ] Todas las acciones críticas logueadas
- [ ] Página de visualización de logs
- [ ] Filtros de búsqueda en logs
- [ ] Diff visual de cambios
- [ ] Widget de actividad reciente
- [ ] Alertas de seguridad
- [ ] Auto-limpieza de logs antiguos
- [ ] Exportación de logs

---

### Item 5.4: Notificaciones y Recordatorios

**Prompt de Desarrollo:**
```
Desarrolla un sistema completo de notificaciones in-app y por email:

1. Tipos de notificaciones:
   
   A. Transacciones:
      - Nueva transacción pendiente tu aprobación
      - Tu transacción fue aprobada
      - Tu transacción fue rechazada (con motivo)
      - Transacción editada por otro usuario
      
   B. Presupuestos:
      - Presupuesto alcanzó 80%
      - Presupuesto sobrepasado
      - Nuevo presupuesto asignado a tu categoría
      
   C. Recordatorios:
      - Ingresar transacciones pendientes de registro
      - Revisar transacciones sin comprobante
      - Transacciones rechazadas sin acción (hace 7 días)
      
   D. Sistema:
      - Nuevo reporte programado disponible
      - Importación completada
      - Exportación lista para descargar

2. Centro de notificaciones (in-app):
   
   Icono de campana en top bar con contador de no leídas
   
   Dropdown al hacer click:
   ┌─────────────────────────────────────────┐
   │ Notificaciones (3 nuevas)         [⚙️]  │
   │─────────────────────────────────────────│
   │ 🔴 Presupuesto "Suministros" al 95%     │
   │    hace 5 minutos                       │
   │─────────────────────────────────────────│
   │ 🟢 Transacción #123 aprobada            │
   │    hace 1 hora                          │
   │─────────────────────────────────────────│
   │ 🟡 5 transacciones pendientes           │
   │    hace 2 horas                         │
   │─────────────────────────────────────────│
   │ [Ver todas las notificaciones →]        │
   └─────────────────────────────────────────┘
   
   Página completa de notificaciones:
   - Listado completo con paginación
   - Marcar como leída/no leída
   - Filtrar por tipo
   - Eliminar notificaciones antiguas
   - Configuración de preferencias

3. Configuración de notificaciones (por usuario):
   
   Página "Mis Notificaciones"
   
   ┌────────────────────────────────────────────┐
   │ Notificaciones In-App      │ Email         │
   │────────────────────────────│───────────────│
   │ Transacciones pendientes   │ ☑ Sí  ☐ No   │
   │ Aprobaciones/Rechazos      │ ☑ Sí  ☐ No   │
   │ Alertas de presupuesto     │ ☑ Sí  ☐ No   │
   │ Recordatorios              │ ☐ Sí  ☑ No   │
   │ Reportes programados       │ ☑ Sí  ☐ No   │
   │────────────────────────────│───────────────│
   │ Frecuencia de emails:                     │
   │ ○ Inmediato                               │
   │ ● Diario (resumen)                        │
   │ ○ Semanal                                 │
   │────────────────────────────│───────────────│
   │ No molestar:                              │
   │ ☑ Fines de semana                         │
   │ ☑ Fuera de horario (6pm - 8am)           │
   └────────────────────────────────────────────┘

4. Templates de email:
   
   Usar HTML templates profesionales:
   - Header con logo
   - Contenido de notificación
   - Botón de acción (ej. "Ver Transacción")
   - Footer con link de desuscripción
   
   Ejemplo:
   ```html
   <!DOCTYPE html>
   <html>
   <head>...</head>
   <body>
     <div style="max-width: 600px; margin: 0 auto;">
       <h2>Presupuesto Sobrepasado</h2>
       <p>El presupuesto de la categoría <strong>Suministros</strong> 
          ha sido sobrepasado.</p>
       <p><strong>Presupuesto:</strong> $5,000<br>
          <strong>Ejecutado:</strong> $5,250 (105%)</p>
       <a href="[link]" style="...">Ver Detalle</a>
     </div>
   </body>
   </html>
   ```

5. Recordatorios automáticos (Cron jobs):
   
   Diario a las 9:00 AM:
   - Verificar presupuestos cercanos a límite
   - Contar transacciones pendientes por > 7 días
   - Enviar resumen a usuarios con notificaciones activas
   
   Semanal (lunes 9:00 AM):
   - Resumen de la semana anterior
   - Transacciones más grandes
   - Estado de presupuestos

6. Notificaciones push (opcional, futuro):
   - Integración con navegadores (Web Push API)
   - OneSignal o similar

7. Implementación:
   - Tabla: wp_aura_notifications
   - modules/common/class-notifications.php (reutilizable para otros módulos)
   - wp_ajax_aura_mark_notification_read
   - wp_ajax_aura_get_notifications
   - Función: aura_send_notification($user_id, $type, $title, $message, $link)
   - Cron: aura_finance_daily_reminders()
```

**Checklist de Implementación:**
- [ ] Tabla de notificaciones creada
- [ ] Icono de campana con contador
- [ ] Dropdown de notificaciones
- [ ] Página completa de notificaciones
- [ ] Marcar como leída/no leída
- [ ] Configuración de preferencias
- [ ] Templates de email HTML
- [ ] Envío de emails funcionando
- [ ] Cron jobs de recordatorios
- [ ] Testing de todos los tipos de notificación

---

### Item 5.5: Integraciones con Software Contable

**Prompt de Desarrollo:**
```
Implementa conectores para exportar datos a software contable popular:

1. Formatos de exportación específicos:
   
   A. QuickBooks:
      - Formato IIF (Intuit Interchange Format)
      - Mapear: Transacciones → Invoices/Expenses
      - Categorías → Accounts
      
   B. SAP o Contabilidad Electrónica (México):
      - Formato XML estándar
      - Cumplir con requerimientos fiscales
      
   C. Excel personalizado:
      - Plantilla configurable
      - Columnas personalizables
      - Fórmulas predefinidas

2. Asistente de exportación:
   
   Página "Exportar a Software Contable"
   
   Paso 1: Seleccionar software
   ○ QuickBooks
   ○ SAP
   ○ Contabilidad Electrónica MX
   ○ Excel Personalizado
   
   Paso 2: Configurar período y filtros
   - Período fiscal: [Select]
   - Solo transacciones aprobadas: ☑
   - Excluir categorías internas: ☑
   
   Paso 3: Mapear cuentas contables
   | Categoría AURA      | Cuenta Contable |
   |---------------------|-----------------|
   | Ventas de Servicios | → [4010]        |
   | Suministros         | → [5010]        |
   | Salarios            | → [5020]        |
   
   - Guardar mapeo para próximas exportaciones
   
   Paso 4: Descargar archivo
   - Previsualización
   - [Descargar archivo .iif/.xml/.xlsx]

3. Sincronización bidireccional (opcional, futuro):
   - API de QuickBooks Online
   - Sincronizar transacciones automáticamente
   - Evitar duplicados (por fecha + monto + concepto)

4. Implementación:
   - modules/financial/class-financial-integrations.php
   - wp_ajax_aura_export_accounting_format
```

**Checklist de Implementación:**
- [ ] Exportación formato QuickBooks (IIF)
- [ ] Exportación formato XML contable
- [ ] Asistente de exportación
- [ ] Mapeo de cuentas contables
- [ ] Guardar configuración de mapeo
- [ ] Validación de formatos
- [ ] Documentación de uso

---

## Mejores Prácticas

### 🔒 Seguridad

1. **Validación y Sanitización**
   - Siempre usar `sanitize_text_field()`, `absint()`, `sanitize_email()` en inputs
   - Validar en frontend Y backend
   - Escapar salidas con `esc_html()`, `esc_url()`, `esc_attr()`

2. **Nonces y Verificaciones**
   - Todos los formularios deben incluir `wp_nonce_field()`
   - Verificar con `check_ajax_referer()` en AJAX handlers
   - Verificar permisos con `current_user_can()` antes de acciones

3. **Prevención de SQL Injection**
   - Usar `$wpdb->prepare()` para queries personalizadas
   - Nunca concatenar variables directamente en SQL

4. **Control de Acceso**
   - Verificar capabilities en cada endpoint
   - No confiar en verificaciones del frontend
   - Log de accesos sospechosos

### ⚡ Performance

1. **Optimización de Consultas**
   - Usar índices en columnas frecuentemente filtradas
   - Evitar `SELECT *`, especificar columnas necesarias
   - Paginar resultados (20-50 por página)
   - Caché de queries costosas con Transients API

2. **Assets**
   - Minificar CSS/JS en producción
   - Cargar scripts solo en páginas necesarias
   - Usar `wp_enqueue_script()` con dependencias correctas
   - Lazy loading de imágenes/comprobantes

3. **AJAX**
   - Evitar requests síncronos
   - Debounce en búsquedas en tiempo real (300ms)
   - Mostrar loading states
   - Manejo de errores con mensajes claros

### 🎨 UX/UI

1. **Consistencia Visual**
   - Seguir guidelines de WordPress Admin
   - Paleta de colores consistente
   - Iconografía clara y uniforme
   - Espaciado y tipografía coherentes

2. **Feedback al Usuario**
   - Mensajes de éxito/error claros
   - Loading spinners en operaciones largas
   - Confirmaciones antes de acciones destructivas
   - Progress bars en procesos de múltiples pasos

3. **Accesibilidad**
   - Labels claros en formularios
   - Contraste de colores suficiente (WCAG AA)
   - Navegación por teclado
   - ARIA labels donde sea necesario

### 📱 Responsive Design

1. **Mobile First**
   - Diseñar primero para móvil
   - Tablas con scroll horizontal en móvil
   - Botones grandes touch-friendly (min 44x44px)
   - Menús colapsables en pantallas pequeñas

2. **Breakpoints**
   - Mobile: < 768px
   - Tablet: 768px - 1024px
   - Desktop: > 1024px

### 🧪 Testing

1. **Casos de Prueba Esenciales**
   - Crear transacción con todos los campos
   - Aprobar/Rechazar transacción
   - Editar transacción con historial
   - Eliminar (soft delete) y restaurar
   - Filtros combinados en listado
   - Exportación de 1000+ registros
   - Importación con errores de formato
   - Presupuesto alcanzando límites
   - Búsqueda con operadores avanzados

2. **Testing de Permisos**
   - Usuario sin permisos no puede acceder
   - Usuario solo ve sus transacciones (aura_finance_view_own)
   - Usuario no puede aprobar sus propias transacciones
   - Verificar cada capability en diferentes escenarios

3. **Testing de Performance**
   - 10,000+ transacciones: Listado < 2s
   - Exportación 5,000 registros < 10s
   - Dashboard carga < 1s con cache
   - Búsqueda < 500ms

### 📚 Documentación

1. **Código**
   - PHPDoc para todas las funciones y clases
   - Comentarios en lógica compleja
   - README en cada módulo

2. **Usuario Final**
   - Guía de inicio rápido (PDF)
   - Tooltips en interfaz
   - Video tutoriales (opcional)
   - FAQ

3. **Desarrollador**
   - Arquitectura del módulo
   - Hooks y filtros disponibles
   - Ejemplos de extensiones
   - API documentation

---

## Roadmap Futuro (Post-MVP)

### Versión 2.0
- [ ] Dashboard personalizable (drag & drop widgets)
- [ ] Proyectos: Asociar transacciones a proyectos específicos
- [ ] Conciliación bancaria: Importar extractos y conciliar automáticamente
- [ ] Facturación: Generar facturas desde transacciones
- [ ] Multi-moneda: Soporte para múltiples divisas con conversión automática
- [ ] API REST pública: Documentada con Swagger
- [ ] App móvil: React Native o Flutter
- [ ] Inteligencia Artificial: Predicción de gastos, categorización automática
- [ ] Blockchain: Trazabilidad inmutable de transacciones críticas

### Integraciones Futuras
- [ ] Google Drive / Dropbox: Sync automático de comprobantes
- [ ] Stripe / PayPal: Importación automática de transacciones
- [ ] Zapier: Automatizaciones con 1000+ apps
- [ ] Slack / Microsoft Teams: Notificaciones de aprobaciones
- [ ] WhatsApp Business: Alertas y recordatorios

---

## Conclusión

Este PRD proporciona una hoja de ruta completa para desarrollar un módulo de finanzas profesional y robusto para pequeñas empresas y fundaciones. Cada fase está diseñada para ser implementada de manera incremental, con ítems claramente definidos y checklists de verificación.

### Próximos Pasos

1. **Revisar y aprobar este PRD** con stakeholders
2. **Estimar tiempos de desarrollo** para cada fase/ítem
3. **Priorizar funcionalidades** según necesidad de negocio
4. **Asignar recursos** (desarrolladores, diseñadores, QA)
5. **Comenzar Fase 1** con configuración de categorías
6. **Iterar y mejorar** basándose en feedback de usuarios

### Criterios de Éxito

✅ **MVP funcional en 4-6 semanas**  
✅ **Sistema de permisos granulares operativo**  
✅ **Categorías y transacciones CRUD completo**  
✅ **Dashboard con gráficos en tiempo real**  
✅ **Exportación multi-formato**  
✅ **100% de cobertura de permisos verificados**  
✅ **Documentación completa de usuario y desarrollador**  
✅ **Performance: Listado de 10K transacciones < 2s**

---

<div align="center">

**Desarrollado con ❤️ para democratizar la gestión financiera**

</div>
