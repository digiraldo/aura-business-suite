<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';

global $wpdb;
$table = $wpdb->prefix . 'aura_cal_events';
$rows = $wpdb->get_results("SELECT id, title, start_datetime, end_datetime, gcal_sync_status, gcal_event_id, deleted_at FROM {$table} ORDER BY id ASC");

echo "Total eventos en tabla: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo sprintf("[%d] %s | %s -> %s | Status: %s | GCal: %s | Deleted: %s\n", 
        $r->id, 
        $r->title, 
        $r->start_datetime, 
        $r->end_datetime, 
        $r->gcal_sync_status, 
        $r->gcal_event_id ? $r->gcal_event_id : 'NINGUNO',
        $r->deleted_at ? $r->deleted_at : 'NO'
    );
}

echo "\n--- Probando payload sin attendees para evento 14 ---\n";
$cal_id = Aura_Calendar_Google_Sync::get_calendar_id();
$row14 = $wpdb->get_row("SELECT * FROM {$table} WHERE id = 14");

$dt_start = new DateTime($row14->start_datetime, wp_timezone());
$dt_end   = new DateTime($row14->end_datetime, wp_timezone());

$payload_test = [
    'summary'     => $row14->title,
    'description' => "Probando sincronización sin attendees",
    'start'       => [
        'dateTime' => $dt_start->format(DateTime::RFC3339),
        'timeZone' => wp_timezone_string() ?: 'America/Bogota',
    ],
    'end'         => [
        'dateTime' => $dt_end->format(DateTime::RFC3339),
        'timeZone' => wp_timezone_string() ?: 'America/Bogota',
    ],
];

$url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($cal_id) . '/events';
$res_test = Aura_Google_Calendar::api_request('POST', $url, $payload_test);
echo "Resultado API:\n";
print_r($res_test);


