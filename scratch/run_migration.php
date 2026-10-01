<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once __DIR__ . '/../modules/calendar/class-calendar-setup.php';

Aura_Calendar_Setup::maybe_add_modules_and_externals_columns();

global $wpdb;
$t = $wpdb->prefix . 'aura_cal_event_instructors';
$cols = $wpdb->get_col("SHOW COLUMNS FROM `{$t}`");
echo "COLUMNS IN {$t}:\n" . implode(', ', $cols) . "\n";
