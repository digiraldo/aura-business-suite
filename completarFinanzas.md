# Plan de Implementación — Completar Módulo Financiero

> **Fecha de análisis:** 2025-07  
> **Estado base:** FASE 1, 2, 3 (sin caché), 6 completadas. Resto parcial.  
> **Tiempo estimado total:** 8-12 sesiones de desarrollo

---

## Estado Actual (Resumen Ejecutivo)

| Fase PRD | Estado | Notas |
|---|---|---|
| FASE 1 — Categorías & Setup | ✅ Completa | |
| FASE 2 — Transacciones & Aprobación | ✅ Completa | |
| FASE 3.1 — Dashboard | ✅ Con caché | **FASE E implementada** — Transients API + bust_cache() |
| FASE 3.2 — Reportes | ✅ PDF nativo | **FASE B implementada** — HTML profesional @page + auto-print + logo |
| FASE 3.3 — Analytics | ✅ Implementado | |
| FASE 4.1 — Export | ✅ Completo | CSV + Excel + JSON + XML + PDF (FASE B) presentes |
| FASE 4.2 — Import | ✅ Completo | Fix: categoría no encontrada = advertencia (no error); historial con verificación 24h en JS; estilos expired label + auto-cat notice |
| FASE 5.1 — Presupuestos | ✅ Completo | **FASE A implementada** — Settings ↔ Budgets conectados |
| FASE 5.2 — Tags & Search | ✅ Completo | **FASE C implementada** — campo:valor, MATCH AGAINST, panel de ayuda |
| FASE 5.3 — Auditoría | ✅ Completo | **FASE D implementada** — Alertas seguridad + email + cron horario |
| FASE 5.4 — Notificaciones | ✅ Implementado | No-disturb, cron semanal, templates presentes |
| FASE 5.5 — Integraciones Contables | ✅ Implementado | IIF y XML SAP ambos presentes |
| FASE 6 — Usuarios | ✅ Completa | |
| Integraciones cross-módulo | 🔴 Diferido | Dependen de módulos inexistentes |

### Fases de implementación adicionales (todas ✅ COMPLETADAS)

| Fase | Descripción | Archivos modificados |
|---|---|---|
| **FASE A** | Presupuesto en auto-aprobación | `class-financial-settings.php`, `class-financial-budgets.php` |
| **FASE B** | PDF profesional con HTML+@page | `class-financial-export.php`, `export-modal.js`, `reports-page.php`, `admin-styles.css` |
| **FASE C** | Operadores avanzados de búsqueda | `class-financial-search.php`, `search-page.php`, `admin-styles.css` |
| **FASE D** | Alertas de seguridad en auditoría | `class-financial-audit.php` |
| **FASE E** | Caché del dashboard con Transients | `class-financial-dashboard.php` |

---

## FASE A — Corrección Crítica: Presupuesto en Auto-Aprobación

**Prioridad:** 🔴 Alta · **Tiempo estimado:** 0.5 sesión  
**Impacto:** Sin esto, las transacciones que exceden presupuesto se auto-aprueban incorrectamente.

### Problema

En `modules/financial/class-financial-settings.php`, líneas 126-134, el bloque que conecta
la verificación de presupuesto está comentado:

```php
// 3. Verificar presupuesto sobrepasado (implementación futura)
/*
if (!empty($transaction_data['category_id']) && !empty($transaction_data['amount'])) {
    if (self::is_budget_exceeded(...)) {
        return true;  // fuerza aprobación manual
    }
}
*/
```

Y el método `is_budget_exceeded()` (líneas 151-156) retorna `false` hardcodeado.

### Solución

**Archivo:** `modules/financial/class-financial-settings.php`

1. **Desbloquear el bloque comentado** (eliminar `/*` y `*/` de las líneas 128-134)
2. **Reemplazar el método `is_budget_exceeded()`** para llamar a la clase de presupuestos:

```php
private static function is_budget_exceeded(int $category_id, float $amount): bool {
    if (!class_exists('Aura_Financial_Budgets')) {
        return false;
    }
    // Obtener presupuesto activo para la categoría
    $budget = Aura_Financial_Budgets::get_active_budget_for_category($category_id);
    if (!$budget) {
        return false;
    }
    $executed = Aura_Financial_Budgets::get_executed(
        $category_id,
        $budget->period_start,
        $budget->period_end
    );
    return ($executed + $amount) > $budget->amount;
}
```

**Archivo:** `modules/financial/class-financial-budgets.php`

3. **Agregar el método `get_active_budget_for_category()`** (no existe actualmente):

```php
public static function get_active_budget_for_category(int $category_id): ?object {
    global $wpdb;
    $table = $wpdb->prefix . 'aura_finance_budgets';
    $today = current_time('Y-m-d');
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE category_id = %d
           AND period_start <= %s AND period_end >= %s
           AND status = 'active'
         ORDER BY created_at DESC
         LIMIT 1",
        $category_id, $today, $today
    ));
}
```

### Validación
- Crear una transacción de egreso en una categoría con presupuesto activo excedido
- Verificar que en lugar de auto-aprobarse, queda en estado `pending`
- Verificar que si no hay presupuesto configurado, la auto-aprobación funciona normal

---

## FASE B — PDF Nativo en Reportes

**Prioridad:** 🟡 Media · **Tiempo estimado:** 1-2 sesiones  
**Impacto:** Los reportes en PDF actuales dependen del diálogo de impresión del navegador.

### Situación actual

`class-financial-export.php`, línea 518:
```php
/* PDF (HTML imprimible – fallback sin TCPDF) */
// El "PDF" actual es HTML con estilos de impresión + botón window.print()
```

### Opción recomendada: mPDF (sin dependencias de sistema)

```bash
# Instalar vía Composer desde la raíz del plugin
composer require mpdf/mpdf
```

**Archivo a modificar:** `modules/financial/class-financial-export.php`  
Método: `generate_pdf()` (línea ~521)

```php
private static function generate_pdf(array $rows, array $columns, array $opts): array {
    require_once AURA_PLUGIN_DIR . 'vendor/autoload.php';

    $mpdf = new \Mpdf\Mpdf([
        'mode'        => 'utf-8',
        'format'      => 'A4-L',
        'orientation' => 'L',
        'margin_left' => 10,
        'margin_right'=> 10,
    ]);

    $html  = '<html><head><meta charset="utf-8">';
    $html .= '<style>table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px;font-size:9px}th{background:#2271b1;color:#fff}</style>';
    $html .= '</head><body>';
    $html .= '<h2>' . esc_html(get_bloginfo('name')) . ' — ' . __('Reporte Financiero', 'aura-suite') . '</h2>';
    $html .= '<p>' . esc_html(date_i18n(get_option('date_format'))) . '</p>';
    $html .= '<table><thead><tr>';

    foreach ($columns as $col) {
        $html .= '<th>' . esc_html($col['label']) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $col) {
            $html .= '<td>' . esc_html($row->{$col['key']} ?? '') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></body></html>';

    $mpdf->WriteHTML($html);

    $filename = 'reporte-' . date('Y-m-d-His') . '.pdf';
    $filepath = self::$export_dir . $filename;
    $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

    return [
        'url'      => self::$export_url . $filename,
        'filename' => $filename,
        'mime'     => 'application/pdf',
    ];
}
```

### Alternativa sin Composer: HTML/CSS Print

Si no se quiere agregar una dependencia de Composer, mejorar el HTML actual con:
- CSS `@media print` profesional con colores corporativos
- Header con logo del sitio (`get_site_icon_url()`)
- Footer con número de página via CSS `content: counter(page)`
- Instrucción de guardar como PDF en Chrome/Firefox

Esta alternativa no requiere librería y es suficiente para la mayoría de casos de uso.

---

## FASE C — Búsqueda Avanzada con Operadores

**Prioridad:** 🟡 Media · **Tiempo estimado:** 1 sesión  
**Impacto:** Los usuarios no pueden hacer búsquedas con sintaxis avanzada tipo Google.

### Situación actual

`class-financial-search.php` usa `LIKE '%término%'` con filtros básicos.  
No implementa: `"texto exacto"`, operadores `AND/OR/-`, ni filtros `campo:valor`.

### Pasos de implementación

**1. Agregar índice FULLTEXT** (ya existe el método `maybe_add_fulltext_index()`)

Verificar que el índice se crea sobre `description` y `tags` en la tabla de transacciones.
Si no, modificar el método para crear:
```sql
ALTER TABLE wp_aura_finance_transactions 
ADD FULLTEXT INDEX ft_search (description, tags, notes);
```

**2. Crear parser de consulta** en `class-financial-search.php`:

```php
private static function parse_advanced_query(string $raw): array {
    $result = ['must' => [], 'should' => [], 'must_not' => [], 'fields' => []];

    // Extraer filtros campo:valor  (tipo:egreso, categoria:educacion, importe:>500)
    preg_match_all('/(\w+):([^\s"]+|"[^"]+")/u', $raw, $field_matches, PREG_SET_ORDER);
    foreach ($field_matches as $m) {
        $result['fields'][$m[1]] = trim($m[2], '"');
        $raw = str_replace($m[0], '', $raw);
    }

    // Extraer frases exactas "entre comillas"
    preg_match_all('/"([^"]+)"/u', $raw, $phrase_matches);
    foreach ($phrase_matches[1] as $phrase) {
        $result['must'][] = $phrase;
        $raw = str_replace('"' . $phrase . '"', '', $raw);
    }

    // Extraer negaciones (-palabra)
    preg_match_all('/-(\S+)/u', $raw, $neg_matches);
    foreach ($neg_matches[1] as $neg) {
        $result['must_not'][] = $neg;
        $raw = str_replace('-' . $neg, '', $raw);
    }

    // El resto son términos opcionales
    $tokens = array_filter(array_map('trim', explode(' ', $raw)));
    $result['should'] = array_values($tokens);

    return $result;
}
```

**3. Modificar `ajax_search()`** para usar el parser y construir el WHERE dinámicamente.

**4. Actualizar `templates/financial/search-page.php`**  
Agregar sección de ayuda con ejemplos de sintaxis:
```
Ejemplos: "pago renta" tipo:egreso -anticipo categoria:servicios importe:>1000
```

---

## FASE D — Alertas de Seguridad en Auditoría

**Prioridad:** 🟡 Media · **Tiempo estimado:** 0.5-1 sesión  
**Impacto:** El sistema no detecta ni notifica actividad sospechosa.

### Situación actual

`class-financial-audit.php` registra todas las acciones pero no tiene lógica de detección
de anomalías. No hay funciones para: eliminaciones masivas, IPs inusuales, horarios atípicos.

### Funciones a agregar

En `class-financial-audit.php`, agregar los siguientes métodos y registrarlos en `init()`:

**1. Registro del cron de detección**
```php
// En init():
add_action('aura_finance_security_check', [__CLASS__, 'run_security_checks']);
if (!wp_next_scheduled('aura_finance_security_check')) {
    wp_schedule_event(time(), 'hourly', 'aura_finance_security_check');
}
```

**2. Método de detección**
```php
public static function run_security_checks(): void {
    self::check_mass_deletions();
    self::check_unusual_activity_hours();
}

private static function check_mass_deletions(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'aura_finance_audit_log';
    $threshold = (int) get_option('aura_audit_mass_delete_threshold', 10);

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table}
         WHERE action = 'transaction_trashed'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
    ));

    if ($count >= $threshold) {
        do_action('aura_finance_security_alert', 'mass_deletion', [
            'count'     => $count,
            'threshold' => $threshold,
            'period'    => '1 hora',
        ]);
    }
}

private static function check_unusual_activity_hours(): void {
    global $wpdb;
    $table     = $wpdb->prefix . 'aura_finance_audit_log';
    $hour      = (int) current_time('G'); // 0-23
    $night_min = (int) get_option('aura_audit_night_hour_start', 23);
    $night_max = (int) get_option('aura_audit_night_hour_end', 5);

    $is_night = ($hour >= $night_min || $hour <= $night_max);
    if (!$is_night) return;

    $count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table}
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );

    if ($count > 5) {
        do_action('aura_finance_security_alert', 'unusual_hours', [
            'hour'  => $hour,
            'count' => $count,
        ]);
    }
}
```

**3. Hook para notificar al admin**
```php
// En init():
add_action('aura_finance_security_alert', [__CLASS__, 'notify_security_alert'], 10, 2);

public static function notify_security_alert(string $type, array $data): void {
    $admin_email = get_option('admin_email');
    $labels = [
        'mass_deletion'   => 'Eliminaciones masivas detectadas',
        'unusual_hours'   => 'Actividad en horario inusual',
    ];
    $subject = '[ALERTA] Aura Finanzas: ' . ($labels[$type] ?? $type);
    $body    = "Se ha detectado actividad sospechosa en el módulo financiero.\n\n";
    foreach ($data as $k => $v) {
        $body .= ucfirst($k) . ': ' . $v . "\n";
    }
    $body .= "\nRevisa el log de auditoría: " . admin_url('admin.php?page=aura-audit-log');
    wp_mail($admin_email, $subject, $body);
}
```

---

## FASE E — Caché del Dashboard con Transients

**Prioridad:** 🟢 Baja-Media · **Tiempo estimado:** 0.5 sesión  
**Impacto:** Mejora de rendimiento en instalaciones con muchas transacciones.

### Situación actual

`class-financial-dashboard.php` ejecuta todas las queries en cada carga de página.
No usa `get_transient()` / `set_transient()`.

### Implementación

En cada método `get_*_stats()` o equivalente de `class-financial-dashboard.php`:

```php
public static function get_summary_stats(array $filters = []): array {
    $cache_key = 'aura_finance_dash_summary_' . md5(serialize($filters) . get_current_user_id());
    $cached    = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // ... consultas existentes ...
    $data = [ /* resultado actual */ ];

    set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
    return $data;
}
```

**Invalidar caché al modificar transacciones:**  
En `class-financial-transactions.php`, al final de `ajax_save_transaction()`:
```php
// Limpiar caché de dashboard
delete_transient('aura_finance_dash_*'); // No funciona con wildcard
// Usar grupo de caché:
wp_cache_delete('aura_finance_dashboard', 'aura');
```

> **Nota:** WordPress no soporta `delete_transient` con wildcards. Usar un prefijo
> versionado: `aura_finance_dash_v{version}_*` e incrementar la versión al guardar.
> Alternativa simple: guardar un timestamp en `wp_options` y usarlo como parte de la key.

**Patrón recomendado (cache busting):**
```php
private static function get_cache_version(): int {
    return (int) get_option('aura_finance_cache_version', 1);
}

public static function bust_cache(): void {
    update_option('aura_finance_cache_version', self::get_cache_version() + 1);
}

// En ajax_save_transaction(), ajax_approve(), etc.:
self::bust_cache(); // o Aura_Financial_Dashboard::bust_cache();
```

---

## FASE F — Integraciones Cross-Módulo (Diferido)

**Prioridad:** 🔴 Baja (bloqueado) · **Tiempo estimado:** 2-3 sesiones por módulo  
**Impacto:** Automatización completa entre todos los módulos del plugin.

> ⚠️ **BLOQUEADO:** Estas integraciones dependen de módulos que aún no existen en el plugin.
> Solo implementar una vez que el módulo correspondiente esté funcional.

### Integraciones pendientes

| Módulo origen | Evento disparador | Acción en Finanzas |
|---|---|---|
| Inventario | Mantenimiento registrado | Crear egreso automático |
| Estudiantes | Cuota mensual registrada | Crear ingreso automático |
| Biblioteca | Multa registrada | Crear ingreso automático |
| Vehículos | Alquiler completado | Crear ingreso automático |
| Formularios | Inscripción pagada | Crear ingreso automático |

### Patrón de implementación (para cuando existan los módulos)

Cada módulo origen debe:
1. Disparar un Action Hook propio: `do_action('aura_{module}_payment_registered', $data)`
2. `class-financial-integrations.php` se suscribe vía `add_action()` en su `init()`
3. Crear la transacción financiera usando la API existente `Aura_Financial_Transactions::create()`

```php
// Ejemplo en class-financial-integrations.php
add_action('aura_students_tuition_paid', [__CLASS__, 'on_student_tuition_paid']);

public static function on_student_tuition_paid(array $data): void {
    $category_id = (int) get_option('aura_finance_integration_students_category', 0);
    if (!$category_id) return;

    Aura_Financial_Transactions::create_programmatic([
        'type'           => 'income',
        'amount'         => $data['amount'],
        'description'    => sprintf(__('Cuota - %s', 'aura-suite'), $data['student_name']),
        'category_id'    => $category_id,
        'reference_type' => 'student_tuition',
        'reference_id'   => $data['student_id'],
        'auto_approve'   => true,
    ]);
}
```

---

## Orden de Implementación Recomendado

```
SESIÓN 1:  FASE A — Presupuesto en Auto-Aprobación (crítico, 0.5 sesión)
SESIÓN 1:  FASE E — Caché Dashboard (fácil, 0.5 sesión)
SESIÓN 2:  FASE B — PDF Nativo (depende de decisión Composer vs CSS)
SESIÓN 3:  FASE C — Búsqueda Avanzada con Operadores
SESIÓN 4:  FASE D — Alertas de Seguridad en Auditoría
SESIÓN 5+: FASE F — Integraciones Cross-módulo (cuando existan otros módulos)
```

---

## Checklist de Validación Post-Implementación

### FASE A
- [ ] Transacción que excede presupuesto queda en `pending` y no se auto-aprueba
- [ ] Transacción bajo presupuesto se auto-aprueba normalmente
- [ ] Si no hay presupuesto configurado, la auto-aprobación funciona sin errores

### FASE B  
- [ ] El botón "Exportar PDF" genera descarga real (no abre diálogo de impresión)
- [ ] El PDF incluye logo/nombre de la empresa, fecha y paginación
- [ ] Caracteres UTF-8/acentos se muestran correctamente en el PDF

### FASE C
- [ ] `"texto exacto"` filtra solo resultados con esa frase literal
- [ ] `-palabra` excluye registros que contienen esa palabra
- [ ] `tipo:egreso` filtra solo egresos
- [ ] `importe:>1000` filtra solo montos superiores a 1000
- [ ] Términos sin operador funcionan como OR (comportamiento actual preservado)

### FASE D
- [ ] Eliminar 10+ transacciones en 1 hora dispara email al administrador
- [ ] El email contiene link directo al log de auditoría
- [ ] Actividad nocturna intensa dispara alerta
- [ ] Las alertas no se envían en cascada (throttle de 1 por hora mínimo)

### FASE E
- [ ] `get_transient` retorna datos en segunda carga
- [ ] Guardar una transacción nueva invalida el caché del dashboard
- [ ] El tiempo de carga del dashboard mejora notablemente en instalaciones con >500 registros

---

## Archivos a Modificar por Fase

| Fase | Archivos PHP | Archivos Template |
|---|---|---|
| A | `class-financial-settings.php`, `class-financial-budgets.php` | — |
| B | `class-financial-export.php`, `composer.json` | — |
| C | `class-financial-search.php` | `templates/financial/search-page.php` |
| D | `class-financial-audit.php` | `templates/financial/audit-log-page.php` |
| E | `class-financial-dashboard.php`, `class-financial-transactions.php` | — |
| F | `class-financial-integrations.php` | — |

---

*Documento generado por análisis exhaustivo del código base — Aura Business Suite v1.x*
