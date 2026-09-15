<?php
/**
 * Test de Asistencia y Calificaciones
 */
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;

echo "=== TEST ASISTENCIA Y CALIFICACIONES ===\n\n";

// 1. Crear Programa y Materia temporales
$prog_id = Aura_Calendar_Programs::save([
    'name' => 'Programa Calidad ' . wp_rand(100, 999),
    'code' => 'CAL-' . wp_rand(10, 99),
    'academic_period' => '2025-1',
    'status' => 'active',
]);

$subj_id = Aura_Calendar_Subjects::save([
    'program_id' => $prog_id,
    'name' => 'Liderazgo Pastoral',
    'code' => 'LID202',
    'total_hours' => 30,
    'status' => 'active',
]);

// 2. Crear Evento
$evt_res = Aura_Calendar_Events::save([
    'program_id'     => $prog_id,
    'subject_id'     => $subj_id,
    'title'          => 'Sesión 1: Visión y Misión',
    'event_type'     => 'class',
    'start_datetime' => '2025-03-01 10:00:00',
    'end_datetime'   => '2025-03-01 12:00:00',
    'status'         => 'scheduled',
]);

$event_id = $evt_res['ids'][0];
echo "✅ Evento creado con ID: {$event_id}\n";

// 3. Crear estudiante temporal en wp_aura_students si no hay
$table_stud = $wpdb->prefix . 'aura_students';
$student_id = (int)$wpdb->get_var("SELECT id FROM {$table_stud} LIMIT 1");
if (!$student_id) {
    $wpdb->insert($table_stud, [
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'email' => 'juan.perez@test.org',
        'student_code' => 'STU-001',
        'status' => 'active',
        'created_at' => current_time('mysql'),
    ]);
    $student_id = (int)$wpdb->insert_id;
    echo "✅ Estudiante de prueba creado con ID: {$student_id}\n";
} else {
    echo "✅ Estudiante existente detectado con ID: {$student_id}\n";
}

// 4. Probar Asistencia
echo "\n--- Probando Asistencia ---\n";
$save_att = Aura_Calendar_Attendance::save_attendance($event_id, [
    [
        'student_id' => $student_id,
        'status'     => 'present',
        'notes'      => 'Llegó puntual con cuaderno de notas',
    ]
]);
echo "✅ Asistencia guardada: " . ($save_att ? 'SÍ' : 'NO') . "\n";

$roster = Aura_Calendar_Attendance::get_event_roster($event_id);
echo "✅ Roster de asistencia recuperado. Total alumnos: " . count($roster['roster'] ?? []) . "\n";
if (!empty($roster['roster'])) {
    $st_att = $roster['roster'][0];
    echo "   Alumno: {$st_att->first_name} {$st_att->last_name} | Estado: {$st_att->attendance_status} | Nota: {$st_att->attendance_notes}\n";
}

// 5. Probar Calificaciones
echo "\n--- Probando Calificaciones ---\n";
$grade_id = Aura_Calendar_Grades::save([
    'program_id' => $prog_id,
    'subject_id' => $subj_id,
    'student_id' => $student_id,
    'eval_title' => 'Examen Parcial 1',
    'eval_type'  => 'partial',
    'score'      => 95.5,
    'max_score'  => 100,
    'weight'     => 1.0,
    'feedback'   => 'Excelente comprensión de los conceptos.',
]);
echo "✅ Calificación registrada con ID: {$grade_id}\n";

$matrix = Aura_Calendar_Grades::get_grades($prog_id, $subj_id);
echo "✅ Matriz de calificaciones recuperada. Total registros: " . count($matrix) . "\n";
if (!empty($matrix)) {
    $row = $matrix[0];
    echo "   Promedio ponderado calculado: {$row['final_average']}%\n";
}

// 6. Probar Tareas
echo "\n--- Probando Tareas ---\n";
$task_id = Aura_Calendar_Tasks::save_task([
    'program_id'   => $prog_id,
    'subject_id'   => $subj_id,
    'event_id'     => $event_id,
    'title'        => 'Lectura Capítulo 1 y Resumen',
    'description'  => 'Leer las páginas 10 a 25 y redactar síntesis.',
    'due_datetime' => '2025-03-08 23:59:59',
    'max_score'    => 100,
    'status'       => 'published',
]);
echo "✅ Tarea registrada con ID: {$task_id}\n";

$tasks_list = Aura_Calendar_Tasks::get_tasks(['program_id' => $prog_id]);
echo "✅ Lista de tareas recuperada. Total: " . count($tasks_list) . "\n";

// 7. Limpieza
echo "\n--- Limpieza final ---\n";
Aura_Calendar_Tasks::delete_task($task_id);
Aura_Calendar_Grades::delete($grade_id);
Aura_Calendar_Events::delete($event_id);
Aura_Calendar_Subjects::delete($subj_id);
Aura_Calendar_Programs::delete($prog_id);
echo "✅ Registros de prueba eliminados exitosamente.\n";

echo "\n=== FIN DEL TEST DE ASISTENCIA Y NOTAS ===\n";
