<?php
/**
 * Admin del Módulo de Estudiantes e Inscripciones
 *
 * Responsabilidades:
 *  - Registrar menús en WordPress Admin
 *  - Encolar CSS y JS del módulo exclusivamente en páginas de estudiantes
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Admin {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ], 15 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // MENÚS
    // ─────────────────────────────────────────────────────────────

    public static function register_menus(): void {
        $has_access = (
            current_user_can( 'aura_students_view_all' ) ||
            current_user_can( 'aura_students_create' ) ||
            current_user_can( 'aura_students_courses_manage' ) ||
            current_user_can( 'aura_students_payments_view_all' ) ||
            current_user_can( 'aura_students_payments_register' ) ||
            current_user_can( 'aura_students_approve' ) ||
            current_user_can( 'aura_students_reports' ) ||
            current_user_can( 'manage_options' )
        );

        if ( ! $has_access ) {
            return;
        }

        // Menú principal
        add_menu_page(
            __( 'Estudiantes — AURA', 'aura-suite' ),
            __( 'Estudiantes', 'aura-suite' ),
            'read',
            'aura-students',
            [ 'Aura_Students_Dashboard', 'render' ],
            'dashicons-groups',
            3.3
        );

        // Dashboard (entrada raíz del menú)
        add_submenu_page(
            'aura-students',
            __( 'Dashboard Estudiantes', 'aura-suite' ),
            __( 'Dashboard', 'aura-suite' ),
            'read',
            'aura-students',
            [ 'Aura_Students_Dashboard', 'render' ]
        );

        // Listado de estudiantes
        if ( current_user_can( 'aura_students_view_all' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-students',
                __( 'Listado de Estudiantes', 'aura-suite' ),
                __( 'Estudiantes', 'aura-suite' ),
                'read',
                'aura-students-list',
                [ 'Aura_Students_CRUD', 'render_list' ]
            );
        }

        // Nuevo Estudiante
        if ( current_user_can( 'aura_students_create' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-students',
                __( 'Nuevo Estudiante', 'aura-suite' ),
                __( '+ Nuevo Estudiante', 'aura-suite' ),
                'read',
                'aura-students-new',
                [ 'Aura_Students_CRUD', 'render_form' ]
            );
        }

        // Cursos y Programas
        if ( current_user_can( 'aura_students_courses_manage' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-students',
                __( 'Cursos y Programas', 'aura-suite' ),
                __( 'Cursos', 'aura-suite' ),
                'read',
                'aura-students-courses',
                [ 'Aura_Students_Courses', 'render_list' ]
            );
        }

        // Inscripciones y aprobaciones
        if (
            current_user_can( 'aura_students_enrollments_manage' ) ||
            current_user_can( 'aura_students_approve' ) ||
            current_user_can( 'manage_options' )
        ) {
            add_submenu_page(
                'aura-students',
                __( 'Inscripciones', 'aura-suite' ),
                __( 'Inscripciones', 'aura-suite' ),
                'read',
                'aura-students-enrollments',
                [ 'Aura_Students_Enrollments', 'render_enrollments' ]
            );
        }

        // Pagos y Cuotas
        if (
            current_user_can( 'aura_students_payments_view_all' ) ||
            current_user_can( 'aura_students_payments_register' ) ||
            current_user_can( 'manage_options' )
        ) {
            add_submenu_page(
                'aura-students',
                __( 'Pagos y Cuotas', 'aura-suite' ),
                __( 'Pagos', 'aura-suite' ),
                'read',
                'aura-students-payments',
                [ 'Aura_Students_Payments', 'render_payments' ]
            );
        }

        // Becas
        if ( current_user_can( 'aura_students_scholarships_view' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-students',
                __( 'Becas', 'aura-suite' ),
                __( 'Becas', 'aura-suite' ),
                'read',
                'aura-students-scholarships',
                [ 'Aura_Students_Scholarships', 'render_scholarships' ]
            );
        }

        // Paz y Salvo
        if ( current_user_can( 'aura_students_status_view' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-students',
                __( 'Paz y Salvo', 'aura-suite' ),
                __( 'Paz y Salvo', 'aura-suite' ),
                'read',
                'aura-students-paz-salvo',
                [ 'Aura_Students_Dashboard', 'render_paz_salvo' ]
            );
        }

        // Reportes
        if ( current_user_can( 'aura_students_reports' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-students',
                __( 'Reportes de Estudiantes', 'aura-suite' ),
                __( 'Reportes', 'aura-suite' ),
                'read',
                'aura-students-reports',
                [ 'Aura_Students_Reports', 'render' ]
            );
        }

        // Configuración
        if ( current_user_can( 'aura_students_settings' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-students',
                __( 'Configuración Estudiantes', 'aura-suite' ),
                __( 'Configuración', 'aura-suite' ),
                'read',
                'aura-students-settings',
                [ 'Aura_Students_Settings', 'render' ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ASSETS
    // ─────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        $students_hooks = [
            'toplevel_page_aura-students',
            'estudiantes_page_aura-students-list',
            'estudiantes_page_aura-students-new',
            'estudiantes_page_aura-students-courses',
            'estudiantes_page_aura-students-enrollments',
            'estudiantes_page_aura-students-payments',
            'estudiantes_page_aura-students-scholarships',
            'estudiantes_page_aura-students-paz-salvo',
            'estudiantes_page_aura-students-reports',
            'estudiantes_page_aura-students-settings',
        ];

        if ( ! in_array( $hook, $students_hooks, true ) ) {
            return;
        }

        $ver = AURA_VERSION;

        wp_enqueue_style(
            'aura-students-admin',
            AURA_PLUGIN_URL . 'assets/css/students-admin.css',
            [ 'aura-admin-styles' ],
            $ver
        );

        wp_enqueue_script(
            'aura-students-admin',
            AURA_PLUGIN_URL . 'assets/js/students-admin.js',
            [ 'jquery' ],
            $ver,
            false // Debe estar en <head> para que wp_localize_script inyecte auraStudents antes del template inline
        );

        wp_localize_script( 'aura-students-admin', 'auraStudents', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'aura_students_nonce' ),
            'library_active' => class_exists( 'Aura_Library_Loans' ) ? '1' : '0',
            'i18n'           => [
                'error'         => __( 'Error al procesar la solicitud.', 'aura-suite' ),
                'confirm_del'   => __( '¿Está seguro de que desea eliminar este registro?', 'aura-suite' ),
                'loading'       => __( 'Cargando…', 'aura-suite' ),
                'saved'         => __( 'Guardado correctamente.', 'aura-suite' ),
                'lib_loans'     => __( 'Préstamos Biblioteca', 'aura-suite' ),
                'lib_overdue'   => __( '⚠ Préstamos vencidos', 'aura-suite' ),
                'lib_fines'     => __( '💸 Multas pendientes', 'aura-suite' ),
                'lib_no_loans'  => __( 'Sin préstamos activos.', 'aura-suite' ),
                'lib_due'       => __( 'Vence:', 'aura-suite' ),
            ],
        ] );

        // Nonces para el módulo de reportes
        if ( $hook === 'estudiantes_page_aura-students-reports' ) {
            wp_localize_script( 'aura-students-admin', 'auraStudentsReports', [
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'aura_students_reports_nonce' ),
                'exportNonce' => wp_create_nonce( 'aura_students_reports_export' ),
            ] );
        }
    }
}
