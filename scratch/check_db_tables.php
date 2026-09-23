<?php
require_once 'c:/laragon/www/diserwp/wp-load.php';
global $wpdb;

$tables = [
    'aura_cal_programs',
    'aura_cal_subjects',
    'aura_cal_events',
    'aura_cal_event_instructors',
    'aura_cal_attendance',
    'aura_cal_grades',
    'aura_cal_tasks',
    'aura_cal_task_submissions',
];

foreach ( $tables as $t ) {
    $full = $wpdb->prefix . $t;
    echo "=== TABLA: {$full} ===\n";
    $cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$full}`" );
    if ( empty( $cols ) ) {
        echo " [NO EXISTE]\n";
        continue;
    }
    foreach ( $cols as $c ) {
        echo " - {$c->Field} ({$c->Type})\n";
    }
    echo "\n";
}
