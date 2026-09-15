<?php
$_SERVER['HTTP_HOST'] = 'diserwp.test';
$_SERVER['REQUEST_URI'] = '/';
require_once 'C:/laragon/www/diserwp/wp-load.php';
delete_transient('aura_gcal_token');
echo "Token transient eliminado.\n";
$cal_id = get_option('aura_gcal_calendar_id_resolved', '');
echo "Calendar ID guardado: " . ($cal_id ?: '(vacio)') . "\n";
echo "GCal habilitado: " . get_option('aura_gcal_enabled', '0') . "\n";
$json = get_option('aura_gcal_service_account_json', '');
if ($json) {
    $creds = json_decode($json, true);
    echo "client_email: " . ($creds['client_email'] ?? '(no encontrado)') . "\n";
    echo "type: " . ($creds['type'] ?? '(no encontrado)') . "\n";
    echo "private_key presente: " . (!empty($creds['private_key']) ? 'SI' : 'NO') . "\n";
    echo "project_id: " . ($creds['project_id'] ?? '(no encontrado)') . "\n";
} else {
    echo "JSON credenciales: (vacio)\n";
}
// Verificar openssl
echo "openssl_sign disponible: " . (function_exists('openssl_sign') ? 'SI' : 'NO') . "\n";
// Último sync status
echo "Ultimo sync status: " . get_option('aura_gcal_last_sync_status', '(ninguno)') . "\n";
