<?php
/**
 * Script temporal para crear la tabla wp_aura_area_users
 * Ejecutar una vez desde: http://diserwp.test/wp-content/plugins/aura-business-suite/migrate-area-users-table.php
 * 
 * IMPORTANTE: Eliminar este archivo después de ejecutarlo
 * 
 * @package AuraBusinessSuite
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que el usuario sea administrador
if (!current_user_can('manage_options')) {
    wp_die('No tienes permisos para ejecutar esta migración.');
}

global $wpdb;
$charset = $wpdb->get_charset_collate();

/* ================================================================
 * 1. Crear tabla wp_aura_area_users
 * ================================================================ */

$table = $wpdb->prefix . 'aura_area_users';

$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
    id              BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    area_id         BIGINT UNSIGNED  NOT NULL,
    user_id         BIGINT UNSIGNED  NOT NULL,
    role            VARCHAR(50)      NOT NULL DEFAULT 'responsible' COMMENT 'responsible, coordinator, viewer',
    assigned_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by     BIGINT UNSIGNED  NOT NULL DEFAULT 0,
    UNIQUE KEY uq_area_user (area_id, user_id),
    INDEX idx_area (area_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role)
) {$charset};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);

echo "<h2>✅ Migración Completada</h2>";
echo "<p>La tabla <code>{$table}</code> ha sido creada exitosamente.</p>";

/* ================================================================
 * 2. Migrar responsables existentes a la nueva tabla
 * ================================================================ */

$areas_table = $wpdb->prefix . 'aura_areas';

// Obtener todas las áreas que tienen un responsable asignado
$areas_with_responsible = $wpdb->get_results(
    "SELECT id, responsible_user_id 
     FROM {$areas_table} 
     WHERE responsible_user_id IS NOT NULL AND responsible_user_id > 0"
);

$migrated_count = 0;

foreach ($areas_with_responsible as $area) {
    // Verificar si ya existe la relación
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE area_id = %d AND user_id = %d",
        $area->id,
        $area->responsible_user_id
    ));
    
    if (!$exists) {
        $wpdb->insert(
            $table,
            [
                'area_id'     => $area->id,
                'user_id'     => $area->responsible_user_id,
                'role'        => 'responsible',
                'assigned_by' => 0, // Sistema
            ],
            ['%d', '%d', '%s', '%d']
        );
        $migrated_count++;
    }
}

echo "<h3>📊 Resumen de Migración</h3>";
echo "<ul>";
echo "<li><strong>Total de áreas con responsable:</strong> " . count($areas_with_responsible) . "</li>";
echo "<li><strong>Relaciones migradas:</strong> {$migrated_count}</li>";
echo "</ul>";

/* ================================================================
 * 3. Verificar estado de la tabla
 * ================================================================ */

$total_relations = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$total_unique_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$table}");
$total_unique_areas = $wpdb->get_var("SELECT COUNT(DISTINCT area_id) FROM {$table}");

echo "<h3>📈 Estadísticas Actuales</h3>";
echo "<ul>";
echo "<li><strong>Total de relaciones área-usuario:</strong> {$total_relations}</li>";
echo "<li><strong>Usuarios únicos asignados:</strong> {$total_unique_users}</li>";
echo "<li><strong>Áreas con usuarios asignados:</strong> {$total_unique_areas}</li>";
echo "</ul>";

/* ================================================================
 * 4. Próximos pasos
 * ================================================================ */

echo "<h3>🎯 Próximos Pasos</h3>";
echo "<ol>";
echo "<li>✅ La tabla ha sido creada correctamente</li>";
echo "<li>✅ Los responsables existentes han sido migrados</li>";
echo "<li>🔄 Ahora puedes asignar múltiples usuarios a cada área desde:</li>";
echo "<ul>";
echo "<li><a href='" . admin_url('admin.php?page=aura-areas') . "'>Áreas y Programas</a></li>";
echo "<li><a href='" . admin_url('admin.php?page=aura-permissions') . "'>Gestión de Permisos</a></li>";
echo "</ul>";
echo "<li>⚠️ <strong>IMPORTANTE:</strong> Elimina este archivo (migrate-area-users-table.php) después de verificar que todo funciona correctamente</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='" . admin_url('admin.php?page=aura-areas') . "' class='button button-primary'>Ver Áreas y Programas</a> ";
echo "<a href='" . admin_url('admin.php?page=aura-permissions') . "' class='button'>Ver Gestión de Permisos</a></p>";
