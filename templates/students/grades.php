<?php
/**
 * Template: Libro de Calificaciones — Módulo de Estudiantes
 *
 * Integra la matriz de calificaciones, cálculo de promedios ponderados en tiempo real
 * y registro de notas/retroalimentaciones bajo la interfaz del Módulo de Estudiantes.
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$can_view = current_user_can( 'aura_cal_view_grades' ) ||
            current_user_can( 'aura_cal_manage_grades' ) ||
            current_user_can( 'aura_record_grades' ) ||
            current_user_can( 'aura_students_courses_manage' ) ||
            current_user_can( 'manage_options' );

if ( ! $can_view ) {
    wp_die( esc_html__( 'No tienes permisos suficientes para acceder al libro de calificaciones.', 'aura-suite' ) );
}
?>

<div class="wrap aura-app-container aura-students-grades">

    <?php
    $header_actions = [];
    if ( current_user_can( 'aura_cal_view_tasks' ) || current_user_can( 'aura_cal_manage_tasks' ) || current_user_can( 'manage_options' ) ) {
        $header_actions[] = [
            'label' => __( 'Ver Tareas', 'aura-suite' ),
            'url'   => admin_url( 'admin.php?page=aura-students-tasks' ),
            'icon'  => 'dashicons-clipboard',
            'class' => 'aura-btn-secondary',
        ];
    }

    Aura_UI::render_page_header( [
        'title'       => __( 'Libro de Calificaciones', 'aura-suite' ),
        'description' => __( 'Visualización y ponderación de notas académicas por programa, materia y estudiante con cálculo de promedios en tiempo real.', 'aura-suite' ),
        'icon'        => 'dashicons-awards',
        'badge'       => __( 'Evaluación Académica', 'aura-suite' ),
        'actions'     => $header_actions,
    ] );
    ?>

    <div style="margin-top: 20px;">
        <?php include AURA_PLUGIN_DIR . 'templates/calendar/tab-grades.php'; ?>
    </div>

</div><!-- /.aura-students-grades -->

