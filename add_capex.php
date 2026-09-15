<?php
require_once('../../../wp-load.php');
global $wpdb;
$table = $wpdb->prefix . 'aura_finance_categories';
$wpdb->query("ALTER TABLE {$table} ADD COLUMN is_capex TINYINT(1) NOT NULL DEFAULT 0 AFTER type");
echo "Column is_capex added successfully.";
