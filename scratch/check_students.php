<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;
$table_stud = $wpdb->prefix . 'aura_students';
$rows = $wpdb->get_results("SELECT id, first_name, last_name, email, status FROM {$table_stud}");
print_r($rows);
