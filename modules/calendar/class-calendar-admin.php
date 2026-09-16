<?php
/**
 * Panel de Administración — Módulo de Calendario y Horarios Académicos
 *
 * Registra los menús de administración de WordPress, gestiona el encolado
 * de estilos y scripts (incluyendo FullCalendar v6), y renderiza las vistas
 * de la interfaz administrativa bajo el Design System de Aura Suite.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Admin {

    /**
     * Inicializar hooks de administración
     */
    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_aura_cal_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
    }

    /**
     * Registrar menús y submenús en el admin de WordPress
     */
    public static function register_menus(): void {
        $has_access = (
            current_user_can( 'aura_view_calendar' ) ||
            current_user_can( 'aura_create_calendar_events' ) ||
            current_user_can( 'aura_manage_calendar' ) ||
            current_user_can( 'aura_teach_calendar' ) ||
            current_user_can( 'manage_options' )
        );

        if ( ! $has_access ) {
            return;
        }

        // Menú principal de Calendario
        add_menu_page(
            __( 'Calendario Académico — AURA', 'aura-suite' ),
            __( 'Calendario', 'aura-suite' ),
            'read',
            'aura-calendar',
            [ __CLASS__, 'render_main' ],
            'dashicons-calendar-alt',
            3.6
        );

        // Submenú 1: Vista del Calendario (página por defecto)
        add_submenu_page(
            'aura-calendar',
            __( 'Vista de Calendario — AURA', 'aura-suite' ),
            __( 'Calendario', 'aura-suite' ),
            'read',
            'aura-calendar',
            [ __CLASS__, 'render_main' ]
        );

        // Submenú 2: Programas y Materias
        if ( current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-calendar',
                __( 'Programas y Materias — AURA', 'aura-suite' ),
                __( 'Programas y Materias', 'aura-suite' ),
                'read',
                'aura-calendar-programs',
                [ __CLASS__, 'render_programs' ]
            );
        }

        // Submenú 3: Calificaciones
        if ( current_user_can( 'aura_record_grades' ) || current_user_can( 'aura_view_calendar' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-calendar',
                __( 'Calificaciones — AURA', 'aura-suite' ),
                __( 'Calificaciones', 'aura-suite' ),
                'read',
                'aura-calendar-grades',
                [ __CLASS__, 'render_grades' ]
            );
        }

        // Submenú 4: Tareas y Evaluaciones
        if ( current_user_can( 'aura_teach_calendar' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-calendar',
                __( 'Tareas y Evaluaciones — AURA', 'aura-suite' ),
                __( 'Tareas', 'aura-suite' ),
                'read',
                'aura-calendar-tasks',
                [ __CLASS__, 'render_tasks' ]
            );
        }

        // Submenú 5: Configuración
        if ( current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-calendar',
                __( 'Configuración de Calendario — AURA', 'aura-suite' ),
                __( 'Configuración', 'aura-suite' ),
                'read',
                'aura-calendar-settings',
                [ __CLASS__, 'render_settings' ]
            );
        }
    }

    /**
     * Encolar hojas de estilo y scripts
     */
    public static function enqueue_assets( string $hook ): void {
        $allowed_hooks = [
            'toplevel_page_aura-calendar',
            'calendario_page_aura-calendar-programs',
            'calendario_page_aura-calendar-grades',
            'calendario_page_aura-calendar-tasks',
            'calendario_page_aura-calendar-settings',
        ];

        // Verificar si la página actual pertenece al módulo
        $is_calendar_page = false;
        foreach ( $allowed_hooks as $h ) {
            if ( strpos( $hook, 'aura-calendar' ) !== false ) {
                $is_calendar_page = true;
                break;
            }
        }

        if ( ! $is_calendar_page ) {
            return;
        }

        // Cargar Design System Base de Aura si no está ya cargado
        if ( ! wp_style_is( 'aura-design-system', 'enqueued' ) ) {
            wp_enqueue_style(
                'aura-design-system',
                AURA_PLUGIN_URL . 'assets/css/design-system.css',
                [],
                AURA_VERSION
            );
        }

        // FullCalendar v6 (CDN jsDelivr bundle unificado con DayGrid, TimeGrid, Interaction, List)
        wp_enqueue_script(
            'fullcalendar-bundle',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
            [],
            '6.1.15',
            true
        );

        $cal_ver = AURA_VERSION . '.' . ( file_exists( AURA_PLUGIN_DIR . 'assets/js/calendar-admin.js' ) ? filemtime( AURA_PLUGIN_DIR . 'assets/js/calendar-admin.js' ) : time() );

        // Estilos propios del módulo de calendario
        wp_enqueue_style(
            'aura-calendar-admin',
            AURA_PLUGIN_URL . 'assets/css/calendar-admin.css',
            [ 'aura-design-system' ],
            $cal_ver
        );

        // Script administrativo del módulo de calendario
        wp_enqueue_script(
            'aura-calendar-admin',
            AURA_PLUGIN_URL . 'assets/js/calendar-admin.js',
            [ 'jquery', 'fullcalendar-bundle' ],
            $cal_ver,
            true
        );

        // Obtener programas activos para los selectores
        $programs = Aura_Calendar_Programs::get_all( [ 'status' => 'active', 'limit' => 100 ] );

        // Obtener usuarios profesores / coordinadores / administradores
        $teacher_users = get_users( [
            'role__in' => [ 'administrator', 'editor', 'author', 'subscriber' ],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'number'   => 200,
        ] );

        $teachers_clean = [];
        foreach ( $teacher_users as $tu ) {
            $teachers_clean[] = [
                'id'    => $tu->ID,
                'name'  => $tu->display_name,
                'email' => $tu->user_email,
            ];
        }

        wp_localize_script( 'aura-calendar-admin', 'auraCalData', [
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'calendar_url'        => admin_url( 'admin.php?page=aura-calendar' ),
            'nonce'               => wp_create_nonce( 'aura_cal_nonce' ),
            'current_user_id'     => get_current_user_id(),
            'user_can_edit'       => current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ),
            'user_can_delete'     => current_user_can( 'aura_delete_calendar_events' ) || current_user_can( 'manage_options' ),
            'user_can_attendance' => current_user_can( 'aura_take_attendance' ) || current_user_can( 'manage_options' ),
            'user_can_grade'      => current_user_can( 'aura_record_grades' ) || current_user_can( 'manage_options' ),
            'gcal_enabled'        => Aura_Calendar_Google_Sync::is_enabled(),
            'gcal_name'           => Aura_Calendar_Google_Sync::get_calendar_name(),
            'programs'            => $programs,
            'teachers'            => $teachers_clean,
            'i18n'                => [
                'confirm_delete'        => __( '¿Estás seguro de eliminar este elemento? Esta acción no se puede deshacer.', 'aura' ),
                'confirm_delete_series' => __( '¿Deseas eliminar únicamente esta clase o TODAS las clases futuras de esta serie?', 'aura' ),
                'syncing'               => __( 'Sincronizando con Google Calendar...', 'aura' ),
                'saved'                 => __( 'Guardado con éxito.', 'aura' ),
                'error'                 => __( 'Ocurrió un error inesperado.', 'aura' ),
                'select_program'        => __( 'Selecciona un programa...', 'aura' ),
                'select_subject'        => __( 'Selecciona una materia...', 'aura' ),
                'today'                 => __( 'Hoy', 'aura' ),
                'month'                 => __( 'Mes', 'aura' ),
                'week'                  => __( 'Semana', 'aura' ),
                'day'                   => __( 'Día', 'aura' ),
                'list'                  => __( 'Agenda', 'aura' ),
                'fullscreen'            => __( 'Pantalla Completa', 'aura' ),
                'exit_fullscreen'       => __( 'Salir de Pantalla Completa', 'aura' ),
            ],
        ] );
    }

    /**
     * Renderizar vista principal (Tab Calendario por defecto)
     */
    public static function render_main(): void {
        $active_tab = 'calendar';
        include AURA_PLUGIN_DIR . 'templates/calendar/main.php';
    }

    /**
     * Renderizar tab de Programas y Materias
     */
    public static function render_programs(): void {
        $active_tab = 'programs';
        include AURA_PLUGIN_DIR . 'templates/calendar/main.php';
    }

    /**
     * Renderizar tab de Calificaciones
     */
    public static function render_grades(): void {
        $active_tab = 'grades';
        include AURA_PLUGIN_DIR . 'templates/calendar/main.php';
    }

    /**
     * Renderizar tab de Tareas
     */
    public static function render_tasks(): void {
        $active_tab = 'tasks';
        include AURA_PLUGIN_DIR . 'templates/calendar/main.php';
    }

    /**
     * Renderizar tab de Configuración
     */
    public static function render_settings(): void {
        $active_tab = 'settings';
        include AURA_PLUGIN_DIR . 'templates/calendar/main.php';
    }

    /**
     * AJAX: Guardar ajustes del módulo de Calendario
     */
    public static function ajax_save_settings(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $cal_name  = sanitize_text_field( $_POST['cal_name'] ?? '' );
        $auto_sync = ! empty( $_POST['auto_sync'] ) ? '1' : '0';

        update_option( Aura_Calendar_Google_Sync::CAL_NAME_OPTION, $cal_name );
        update_option( Aura_Calendar_Google_Sync::AUTO_SYNC_OPTION, $auto_sync );

        wp_send_json_success( [ 'message' => __( 'Ajustes guardados correctamente.', 'aura' ) ] );
    }
}
