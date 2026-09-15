<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$event = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}aura_cal_events LIMIT 1");
print_r($event);
