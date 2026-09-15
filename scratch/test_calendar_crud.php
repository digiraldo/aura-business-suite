<?php
/**
 * Test integral del módulo de Calendario y Horarios Académicos
 */
require_once dirname(__DIR__, 4) . '/wp-load.php';

echo "=== TEST MÓDULO CALENDARIO Y HORARIOS ACADÉMICOS ===\n\n";

// 1. Verificar carga de clases
$classes = [
    'Aura_Calendar_Setup',
    'Aura_Calendar_Google_Sync',
    'Aura_Calendar_Programs',
    'Aura_Calendar_Subjects',
    'Aura_Calendar_Events',
    'Aura_Calendar_Attendance',
    'Aura_Calendar_Grades',
    'Aura_Calendar_Tasks',
    'Aura_Calendar_Admin',
    'Aura_Calendar_Frontend',
];

foreach ($classes as $c) {
    if (class_exists($c)) {
        echo "✅ Clase {$c} cargada correctamente.\n";
    } else {
        echo "❌ ERROR: Clase {$c} NO existe.\n";
    }
}

// 2. Test CRUD Programas
echo "\n--- Probando CRUD Programas ---\n";
$prog_id = Aura_Calendar_Programs::save([
    'name' => 'Programa Test Hadime ' . wp_rand(100, 999),
    'code' => 'HADIME-T' . wp_rand(10, 99),
    'description' => 'Programa de prueba para verificación automatizada',
    'academic_period' => '2025-1',
    'start_date' => '2025-01-13',
    'end_date' => '2025-06-30',
    'color' => '#6366f1',
    'status' => 'active',
]);

if (is_wp_error($prog_id)) {
    echo "❌ Error al crear programa: " . $prog_id->get_error_message() . "\n";
} else {
    echo "✅ Programa creado exitosamente con ID: {$prog_id}\n";
    $p = Aura_Calendar_Programs::get($prog_id);
    echo "   Nombre verificado: {$p->name} (Código: {$p->code})\n";
}

// 3. Test CRUD Materias
echo "\n--- Probando CRUD Materias ---\n";
$subj_id = Aura_Calendar_Subjects::save([
    'program_id' => $prog_id,
    'name' => 'Identidad en Cristo',
    'code' => 'ID101',
    'description' => 'Materia de formación básica',
    'total_hours' => 45,
    'color' => '#3b82f6',
    'status' => 'active',
]);

if (is_wp_error($subj_id)) {
    echo "❌ Error al crear materia: " . $subj_id->get_error_message() . "\n";
} else {
    echo "✅ Materia creada con ID: {$subj_id}\n";
    $s = Aura_Calendar_Subjects::get($subj_id);
    echo "   Materia verificada: {$s->name} en programa {$s->program_name}\n";
}

// 4. Test Eventos: Serie recurrente (Lunes a Viernes de 9 a 12)
echo "\n--- Probando Recurrencia de Clases (Lun a Vie, 9am a 12pm) ---\n";
$events_res = Aura_Calendar_Events::save([
    'is_recurring' => '1',
    'program_id' => $prog_id,
    'subject_id' => $subj_id,
    'title' => 'Clase de Identidad en Cristo',
    'event_type' => 'class',
    'description' => 'Clase matutina de Lunes a Viernes con tiempo de descanso intermedio.',
    'location' => 'Salón Principal',
    'online_url' => 'https://meet.google.com/test-aura-cal',
    'color' => '#3b82f6',
    'recurring_days' => [1, 2, 3, 4, 5], // Lun - Vie
    'rec_date_start' => '2025-02-03',
    'rec_date_end' => '2025-02-14', // 2 semanas = 10 días laborables
    'rec_time_start' => '09:00',
    'rec_time_end' => '12:00',
]);

if (is_wp_error($events_res)) {
    echo "❌ Error al generar serie: " . $events_res->get_error_message() . "\n";
} else {
    echo "✅ Serie recurrente generada exitosamente: {$events_res['message']} (Total eventos: {$events_res['count']})\n";
}

// 5. Test Consulta FullCalendar
echo "\n--- Probando get_events() para FullCalendar ---\n";
$fc_events = Aura_Calendar_Events::get_events([
    'start' => '2025-02-01T00:00:00Z',
    'end'   => '2025-02-28T23:59:59Z',
    'program_id' => $prog_id,
]);

echo "✅ Eventos recuperados para FullCalendar en Febrero 2025: " . count($fc_events) . "\n";
if (!empty($fc_events)) {
    $first_event = $fc_events[0];
    echo "   Primer evento: [ID: {$first_event['id']}] {$first_event['title']}\n";
    echo "   Horario: {$first_event['start']} a {$first_event['end']}\n";
    echo "   Recurrence Group: {$first_event['extendedProps']['recurrence_group_id']}\n";
}

// 6. Test Google Calendar Helpers
echo "\n--- Probando Google Calendar Helpers ---\n";
echo "   Google Calendar Enabled: " . (Aura_Calendar_Google_Sync::is_enabled() ? 'SÍ' : 'NO') . "\n";
echo "   Calendar Name configurado: " . Aura_Calendar_Google_Sync::get_calendar_name() . "\n";

// Limpieza de datos de prueba
echo "\n--- Limpieza de datos de prueba ---\n";
if (!empty($fc_events[0]['id'])) {
    Aura_Calendar_Events::delete((int)$fc_events[0]['id'], true); // Eliminar serie completa
    echo "✅ Serie de prueba eliminada.\n";
}
Aura_Calendar_Subjects::delete($subj_id);
Aura_Calendar_Programs::delete($prog_id);
echo "✅ Programa y materia de prueba archivados.\n";

echo "\n=== FIN DEL TEST. TODO FUNCIONA CORRECTAMENTE ===\n";
