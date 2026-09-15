<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

$prog_id = Aura_Calendar_Programs::save([
    'name' => 'Programa Test Roster',
    'code' => 'ROSTER-1',
    'status' => 'active',
]);

$evt_res = Aura_Calendar_Events::save([
    'program_id'     => $prog_id,
    'title'          => 'Clase Test Roster',
    'start_datetime' => '2025-04-01 10:00:00',
    'end_datetime'   => '2025-04-01 12:00:00',
]);

$eid = $evt_res['ids'][0];
echo "Created event: {$eid}\n";

$data = Aura_Calendar_Attendance::get_event_roster($eid);
print_r($data);

// Limpieza
Aura_Calendar_Events::delete($eid);
Aura_Calendar_Programs::delete($prog_id);
