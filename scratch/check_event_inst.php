<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$events = $wpdb->get_results('SELECT id, title, subject_id, program_id FROM wp_aura_cal_events ORDER BY id DESC LIMIT 10');
echo 'TOTAL EVENTS: ' . count($events) . PHP_EOL;
foreach ($events as $e) {
    $inst = $wpdb->get_results($wpdb->prepare('SELECT * FROM wp_aura_cal_event_instructors WHERE event_id = %d', $e->id));
    echo "Event #{$e->id} [{$e->title}] (Subj: {$e->subject_id}): " . count($inst) . " insts in db\n";
    foreach ($inst as $i) {
        $name = $i->is_external ? $i->external_name : 'WP User #' . $i->teacher_id;
        echo "   -> role: {$i->role}, is_ext: {$i->is_external}, name: {$name}, tp_id: " . ($i->third_party_id ?? 'null') . "\n";
    }
}
