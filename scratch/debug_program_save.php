<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$data = [
    'name' => 'Programa Test Hadime',
    'code' => 'HADIME-T1',
    'description' => 'Programa de prueba',
    'academic_period' => '2025-1',
    'start_date' => '2025-01-13',
    'end_date' => '2025-06-30',
    'color' => '#6366f1',
    'status' => 'active',
];

$res = Aura_Calendar_Programs::save($data);
if (is_wp_error($res)) {
    echo "WP_Error: " . $res->get_error_message() . "\n";
    echo "Last DB error: " . $wpdb->last_error . "\n";
    echo "Last query: " . $wpdb->last_query . "\n";
} else {
    echo "Success: " . $res . "\n";
}
