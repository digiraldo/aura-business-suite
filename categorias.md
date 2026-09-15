# PROMPT — Upgrade Jerárquico de Categorías con Drag & Drop (WordPress Plugin)

## ROL Y OBJETIVO
Eres un desarrollador full-stack senior especializado en WordPress. Tu misión es **refactorizar y evolucionar el módulo de categorías existente** en este plugin contable/financiero para transformarlo en un **Sistema Jerárquico de Categorías Padre e Hijas con soporte Drag & Drop interactivo**.

> **IMPORTANTE — ESTADO ACTUAL DE LA APP:**
> La aplicación **ya cuenta con una tabla de base de datos básica y una página funcional de categorías**. No debes romper la estructura existente ni perder datos: debes aplicar una **migración de esquema idempotente/segura**, actualizar la interfaz a una vista en árbol interactiva y extender los selectores de toda la app.

---

## 1. MIGRACIÓN DE BASE DE DATOS (IDEMPOTENTE Y SEGURA)

Adapta la tabla existente de categorías (usualmente `wp_di_finanzas_categories` o similar con `$wpdb->prefix`) para incorporar jerarquía y ordenamiento sin perder las categorías existentes:

```php
/**
 * Migración automática idempotente para agregar columnas jerárquicas
 */
public function migrate_categories_schema() {
    global $wpdb;
    $table = $wpdb->prefix . 'di_finanzas_categories';

    // 1. Verificar y agregar 'parent_id' si no existe
    $has_parent = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'parent_id'");
    if (empty($has_parent)) {
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `parent_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `type`, ADD INDEX `idx_parent_id` (`parent_id`)");
        // Asegurar que registros existentes queden como Padres (parent_id = NULL)
        $wpdb->query("UPDATE `{$table}` SET `parent_id` = NULL WHERE `parent_id` = 0");
    }

    // 2. Verificar y agregar 'display_order' para Drag & Drop
    $has_order = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'display_order'");
    if (empty($has_order)) {
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `display_order` INT NOT NULL DEFAULT 0 AFTER `is_active`");
    }

    // 3. Verificar y agregar 'budget_type' si no existe
    $has_budget = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'budget_type'");
    if (empty($has_budget)) {
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `budget_type` ENUM('operational','capital','both') NOT NULL DEFAULT 'operational' AFTER `display_order`");
    }
}
```

---

## 2. REFACTORIZACIÓN DE LA PÁGINA DE CATEGORÍAS (TREE VIEW & DRAG & DROP)

Reemplaza o actualiza el listado plano actual por un **Árbol Jerárquico Visual (Tree View)** interactivo con arrastre de mouse:

### Interfaz del Árbol (Tree View)
- **Filtros por Pestañas:** `📁 Todas` | `📈 Ingresos` | `📉 Egresos` | `🏗️ CapEx`.
- **Categorías Padre (Nivel 1):** Muestran botón para colapsar/expandir `▶ / ▼`, contador de subcategorías y botón `➕ Agregar Subcategoría` (que abre el modal con `parent_id` preseleccionado).
- **Categorías Hijas (Nivel 2):** Se renderizan anidadas bajo su padre con sangría visual y líneas conectoras.
- **Acciones por fila:** `⠿ Handle Arrastre` | `🎨 Color + Ícono` | `Nombre` | `Badge Tipo` | `✏️ Editar` | `➕ Subcategoría` | `🗑️ Archivar/Eliminar`.

### Lógica Drag & Drop (Arrastrar y Soltar con el Mouse)
1. **Acciones con el Mouse:**
   - **Anidar bajo un Padre:** Arrastrar cualquier categoría (sea padre o hija) y soltarla sobre otra categoría padre para convertirla en su subcategoría (`parent_id = ID_Padre`).
   - **Mover a la Raíz:** Arrastrar una subcategoría fuera de su padre hacia el nivel principal para convertirla en categoría Padre independiente (`parent_id = NULL`).
   - **Reordenar:** Mover verticalmente entre elementos del mismo nivel para cambiar el orden de visualización (`display_order`).
2. **Drop Target Visual:** La zona receptora se resalta con borde punteado e iluminación en el color primario (`var(--di-primary)`).
3. **Persistencia Inmediata por AJAX (`di_finance_reorder_categories`):**
   - Al soltar el mouse, JS extrae la nueva estructura `{hierarchy: [{id: 1, parent_id: null, display_order: 1}, {id: 2, parent_id: 1, display_order: 1}]}` y la envía por AJAX.
4. **Validación Backend Anti-Ciclos:**
   - Si se intenta arrastrar una categoría padre dentro de una de sus propias hijas, el servidor rechaza la petición (`400 Bad Request`) para evitar ciclos infinitos y la interfaz revierte la posición.

---

## 3. SELECCIÓN UNIVERSAL EN TRANSACCIONES, TRANSFERENCIAS Y FORMULARIOS

> **REGLA FUNDAMENTAL DE NEGOCIO:**
> En toda la aplicación (creación de transacciones, transferencias entre cuentas, vales de caja chica, presupuestos y reportes), **TODAS las categorías son 100% SELECCIONABLES**, tanto las **Categorías Padre** como cualquiera de sus **Categorías Hijas**. Nunca deshabilitar una categoría padre.

### Formato de Selectores (`<select>` o Dropdowns personalizados):
Actualiza todos los selectores de categorías para renderizar la jerarquía con sangría visual clara:

```html
<select name="category_id" id="category_id" class="aura-select" required>
  <option value="">-- Seleccionar Categoría --</option>
  
  <!-- Categoría Padre (TOTALMENTE SELECCIONABLE) -->
  <option value="1" class="parent-option">💼 Nómina y Personal</option>
  <!-- Categorías Hijas (TOTALMENTE SELECCIONABLES) -->
  <option value="2" class="child-option">&nbsp;&nbsp;&nbsp;&nbsp;— Salarios</option>
  <option value="3" class="child-option">&nbsp;&nbsp;&nbsp;&nbsp;— Honorarios</option>

  <!-- Otra Categoría Padre directa sin hijas (SELECCIONABLE) -->
  <option value="4" class="parent-option">⚡ Servicios Públicos</option>
</select>
```

### Consultas de Reportes y Filtros:
- Si el usuario filtra por una **Categoría Padre**, la consulta SQL debe abarcar los movimientos de la categoría padre Y los de todas sus hijas:
  ```sql
  WHERE (t.category_id = %d OR t.category_id IN (SELECT id FROM wp_di_finanzas_categories WHERE parent_id = %d))
  ```
- Si selecciona una **Categoría Hija**, la consulta filtra únicamente por el `category_id` específico.

---

## 4. ENDPOINTS AJAX Y BACKEND PHP

Asegura que los endpoints utilicen los estándares de seguridad de WordPress:
- `check_ajax_referer('di_finance_nonce', 'nonce')`
- `current_user_can('di_finance_manage_categories')`
- Consultas preparadas con `$wpdb->prepare()`

### Endpoints Clave:
1. **`ajax_get_categories_tree`**: Retorna las categorías activas agrupadas en estructura de árbol (`parents` con sus respectivos `children[]`).
2. **`ajax_save_category`**: Guarda o edita una categoría. Admite el campo `parent_id` (que puede ser `null` o el ID de un padre).
3. **`ajax_reorder_categories`**: Recibe el array `hierarchy` tras el Drag & Drop, valida que no haya referencias circulares y actualiza `parent_id` y `display_order` dentro de una transacción (`START TRANSACTION / COMMIT`).
4. **`ajax_delete_category`**: Al eliminar una categoría padre, sus hijas no se pierden (se actualizan a `parent_id = NULL` gracias a `ON DELETE SET NULL`).

---

## 5. ENTREGABLES ESPERADOS

1. **Método de Migración:** Script de actualización de tabla que preserve los datos existentes.
2. **Vista Tree View:** Plantilla actualizada con soporte de acordeón/expansión y pestañas.
3. **JavaScript Drag & Drop:** Manejo del evento de arrastre (mouse), actualización visual en tiempo real y llamada AJAX.
4. **Helper de Renderizado de Selects:** Función PHP/JS reutilizable para pintar el `<select>` jerárquico en transacciones, transferencias y modales.







## 6. MEJORAS

De acuerdo a los estándares y mejores prácticas de software financiero empresarial moderno (**QuickBooks, SAP Business One, Oracle NetSuite, Xero, Odoo**), la gestión de **Categorías y Subcategorías** es el pilar central para el control de costos, la contabilidad analítica y la toma de decisiones.

A continuación, te presento las funcionalidades clave que complementan y elevan el módulo actual al nivel de las soluciones corporativas más robustas:

---

### 1. 🔄 Fusión y Reasignación Masiva de Categorías (*Category Merging & Bulk Reassignment*)
* **El problema real:** Con el tiempo, distintos usuarios crean categorías duplicadas o con variaciones de nombre (ej. *"Agua & Comida"* vs *"Agua y Comida"*, o *"Gasolina"* vs *"Combustibles"*).
* **Mejor práctica:**
  * Una herramienta para **"Fusionar Categoría A en Categoría B"**: reasigna automáticamente todas las transacciones históricas de la categoría origen a la categoría destino y archiva/elimina la origen en un solo paso seguro.
  * **Cambio masivo de categoría:** Poder seleccionar múltiples transacciones desde la lista y cambiarlas de categoría en lote (*Bulk Categorization*).

---

### 2. ⚡ Reglas de Auto-Categorización Inteligente (*Smart Matching Rules*)
* **El problema real:** La categorización manual toma mucho tiempo y produce errores humanos o clasificaciones inconsistentes.
* **Mejor práctica:**
  * Crear reglas automáticas del tipo: *“Si el concepto contiene 'Uber' o 'Gasolinera' ➔ asignar a 'Transporte / Combustibles'”* o *“Si el proveedor es 'AWS' ➔ asignar a 'Servicios Cloud'”*.
  * Al importar extractos bancarios o al escribir en el formulario, el sistema sugiere o pre-asigna la subcategoría correcta.

---

### 3. 🔢 Códigos Contables / Plan de Cuentas Jerárquico (*Chart of Accounts & SKU*)
* **El problema real:** Las auditorías contables y despachos fiscales requieren una codificación estandarizada (NIIF / GAAP / SAT).
* **Mejor práctica:**
  * Asignar un **código contable numérico** a cada categoría y subcategoría (ej. `4.1.01` Ingresos Operativos, `5.2.03.01` Gastos de Oficina / Papelería, `1.2.04` CapEx / Maquinaria).
  * Los reportes y exportaciones muestran tanto el código como el nombre, facilitando la integración con sistemas contables externos.

---

### 4. 🎯 Alertas de Desvío Presupuestario por Categoría en Tiempo Real
* **El problema real:** Los directores se enteran de que se excedió el presupuesto de una categoría a final de mes cuando ya se gastó el dinero.
* **Mejor práctica:**
  * **Semáforo preventivo:** Al crear o aprobar una transacción, el sistema indica el % ejecutado de esa categoría:
    * 🟢 Menos del 80%: Normal.
    * 🟡 80% - 99%: Advertencia de límite próximo.
    * 🔴 100%+: Bloqueo o requerimiento de aprobación especial por sobregiro presupuestario.

---

### 5. 🏢 Restricción de Categorías por Área / Centro de Costos
* **El problema real:** Un usuario de *Recursos Humanos* no debería registrar gastos en *“Mantenimiento de Servidores”*, ni *Ventas* en *“Químicos de Planta”*.
* **Mejor práctica:**
  * Vincular qué categorías están disponibles para cada **Área / Departamento** o qué roles tienen permiso para utilizar categorías restringidas (ej. solo Dirección puede imputar a *Gastos de Capital / CapEx* o *Honorarios Legales*).

---

### 6. 🧾 Tratamiento Fiscal y Deducibilidad (*Tax Deductibility Tags*)
* **El problema real:** No todos los egresos son 100% deducibles de impuestos.
* **Mejor práctica:**
  * Configurar a nivel de categoría:
    * **Tipo de Deducibilidad:** *Deducible 100%*, *Deducible Parcial (ej. 50% comidas/viáticos)*, o *No Deducible*.
    * **Tasa impositiva por defecto:** IVA 16%, Exento, Retención, etc.
  * Permite generar reportes de utilidad contable vs. utilidad fiscal de forma automática.

---

### 7. 📈 Análisis Comparativo Período vs Período (MoM / YoY por Categoría)
* **El problema real:** Saber cuánto se gastó este mes es útil, pero saber si creció un 25% respecto al mes anterior o al mismo mes del año pasado es vital.
* **Mejor práctica:**
  * En el reporte de Análisis por Categoría, incluir la columna de **Variación vs Mes Anterior (%)** o **vs Mismo Mes Año Anterior (%)** con alertas visuales de incrementos anómalos.

---

### 💡 Resumen de Prioridades Recomendadas

| Prioridad | Funcionalidad | Impacto Operativo |
| :--- | :--- | :--- |
| **Alta** | **Fusión / Reasignación Masiva de Categorías** | Permite limpiar y consolidar categorías duplicadas de inmediato. |
| **Alta** | **Reglas de Auto-Categorización por Palabras Clave** | Ahorra hasta un 70% del tiempo de registro manual. |
| **Media** | **Códigos Contables (Plan de Cuentas)** | Facilita auditorías y estandarización contable. |
| **Media** | **Alertas de Límite Presupuestario al Registrar** | Evita fugas de capital antes de que ocurran. |

¿Cuál de estas funcionalidades te gustaría que comencemos a planificar o implementar primero?