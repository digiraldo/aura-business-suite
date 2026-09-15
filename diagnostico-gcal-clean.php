<?php
$_SERVER['HTTP_HOST'] = 'diserwp.test';
$_SERVER['REQUEST_URI'] = '/';
require_once 'C:/laragon/www/diserwp/wp-load.php';

echo "=== IDs de eventos cacheados ===\n";
global $wpdb;
$equipos = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}aura_inventory_equipment WHERE requires_maintenance=1 AND deleted_at IS NULL"
);
foreach ($equipos as $eq) {
    $ev_id = get_option("aura_gcal_event_{$eq->id}", '');
    echo "Equipo #{$eq->id} ({$eq->name}): " . ($ev_id ?: '(ninguno)') . "\n";
}

echo "\n=== Limpiando IDs cacheados para forzar creación nueva ===\n";
foreach ($equipos as $eq) {
    $deleted = delete_option("aura_gcal_event_{$eq->id}");
    echo "Equipo #{$eq->id}: " . ($deleted ? "eliminado" : "(ya estaba vacío)") . "\n";
}
delete_transient('aura_gcal_token');
echo "\nToken transient eliminado.\n";
echo "Listo. Ahora usa 'Resincronizar todos los equipos' en la UI.\n";
