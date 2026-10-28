<?php
/**
 * Template: Tareas y Evaluaciones — Módulo de Estudiantes
 *
 * Gestión de actividades, lecturas asociadas a la biblioteca, proyectos y recepción/calificación
 * de entregas estudiantiles bajo la interfaz del Módulo de Estudiantes.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$can_view = current_user_can( 'aura_cal_view_tasks' ) ||
            current_user_can( 'aura_cal_manage_tasks' ) ||
            current_user_can( 'aura_cal_grade_tasks' ) ||
            current_user_can( 'aura_teach_calendar' ) ||
            current_user_can( 'aura_students_courses_manage' ) ||
            current_user_can( 'manage_options' );

if ( ! $can_view ) {
    wp_die( esc_html__( 'No tienes permisos suficientes para acceder a tareas y evaluaciones.', 'aura-suite' ) );
}
?>

<div class="wrap aura-app-container aura-students-tasks">

    <?php
    $header_actions = [];
    if ( current_user_can( 'aura_cal_view_grades' ) || current_user_can( 'aura_cal_manage_grades' ) || current_user_can( 'manage_options' ) ) {
        $header_actions[] = [
            'label' => __( 'Calificaciones', 'aura-suite' ),
            'url'   => admin_url( 'admin.php?page=aura-students-grades' ),
            'icon'  => 'dashicons-awards',
            'class' => 'aura-btn-secondary',
        ];
    }

    Aura_UI::render_page_header( [
        'title'       => __( 'Tareas y Evaluaciones', 'aura-suite' ),
        'description' => __( 'Asignación de actividades, trabajos prácticos, lecturas asociadas a la biblioteca y calificación de entregas.', 'aura-suite' ),
        'icon'        => 'dashicons-clipboard',
        'badge'       => __( 'Actividades Académicas', 'aura-suite' ),
        'actions'     => $header_actions,
    ] );
    ?>

    <div style="margin-top: 20px;">
        <?php include AURA_PLUGIN_DIR . 'templates/calendar/tab-tasks.php'; ?>
    </div>

</div><!-- /.aura-students-tasks -->

