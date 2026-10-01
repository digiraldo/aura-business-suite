<?php
require_once 'c:/laragon/www/diserwp/wp-load.php';
$evts = Aura_Calendar_Events::get_events(['start' => '2026-09-27T00:00:00', 'end' => '2026-11-08T00:00:00']);
echo "Total: " . count($evts) . PHP_EOL;
echo json_encode(array_slice($evts, 0, 2), JSON_PRETTY_PRINT);
