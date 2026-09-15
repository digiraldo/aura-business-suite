<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;
$cols = $wpdb->get_results("DESCRIBE {$wpdb->prefix}aura_students");
print_r($cols);
