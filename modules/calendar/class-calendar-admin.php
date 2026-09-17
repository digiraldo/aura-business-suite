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

    const OPTION_TEACHER_PORTAL_PAGE = 'aura_cal_teacher_portal_page_id';
    const OPTION_TEACHER_CODE_PREFIX = 'aura_cal_teacher_code_prefix';

    /**
     * Inicializar hooks de administración
     */
    public static function init(): void {
        add_action( 'admin_menu',                          [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts',               [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_aura_cal_save_settings',             [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_aura_cal_create_teacher_portal_page', [ __CLASS__, 'ajax_create_teacher_portal_page' ] );
    }

    /**
     * Registrar menús y submenús en el admin de WordPress
     */
    public static function register_menus(): void {
        $has_access = (
            current_user_can( 'aura_cal_view_calendar' ) ||
            current_user_can( 'aura_view_calendar' ) ||
            current_user_can( 'aura_cal_manage_calendar' ) ||
            current_user_can( 'aura_create_calendar_events' ) ||
            current_user_can( 'aura_manage_calendar' ) ||
            current_user_can( 'aura_teach_calendar' ) ||
            current_user_can( 'manage_options' )
        );

        if ( ! $has_access ) {
            return;
        }

        // Menú principal de Calendario (Posición 3.25: debajo de Inventario 3.2 y antes de Estudiantes 3.3)
        add_menu_page(
            __( 'Calendario Académico — AURA', 'aura-suite' ),
            __( 'Calendario', 'aura-suite' ),
            'read',
            'aura-calendar',
            [ __CLASS__, 'render_main' ],
            'dashicons-calendar-alt',
            3.25
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
        if ( current_user_can( 'aura_cal_manage_programs' ) || current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ) ) {
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
        if ( current_user_can( 'aura_cal_view_grades' ) || current_user_can( 'aura_cal_manage_grades' ) || current_user_can( 'aura_record_grades' ) || current_user_can( 'manage_options' ) ) {
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
        if ( current_user_can( 'aura_cal_view_tasks' ) || current_user_can( 'aura_cal_manage_tasks' ) || current_user_can( 'aura_cal_grade_tasks' ) || current_user_can( 'aura_cal_submit_tasks' ) || current_user_can( 'aura_teach_calendar' ) || current_user_can( 'manage_options' ) ) {
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
        if ( current_user_can( 'aura_cal_manage_settings' ) || current_user_can( 'aura_manage_calendar' ) || current_user_can( 'manage_options' ) ) {
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

        // Obtener profesores / instructores estandarizados
        $teachers_clean = self::get_instructors();

        $teacher_portal_page_id = self::get_teacher_portal_page_id();
        $teacher_portal_url     = $teacher_portal_page_id > 0 ? get_permalink( $teacher_portal_page_id ) : home_url( '/portal-instructor/' );

        wp_localize_script( 'aura-calendar-admin', 'auraCalData', [
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'calendar_url'        => admin_url( 'admin.php?page=aura-calendar' ),
            'nonce'               => wp_create_nonce( 'aura_cal_nonce' ),
            'current_user_id'     => get_current_user_id(),
            'teacher_portal_url'  => $teacher_portal_url,
            'teacher_code_prefix' => self::get_teacher_code_prefix(),
            'user_can_edit'       => current_user_can( 'aura_cal_manage_calendar' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ),
            'user_can_delete'     => current_user_can( 'aura_cal_delete_events' ) || current_user_can( 'aura_delete_calendar_events' ) || current_user_can( 'manage_options' ),
            'user_can_attendance' => current_user_can( 'aura_cal_take_attendance' ) || current_user_can( 'aura_take_attendance' ) || current_user_can( 'manage_options' ),
            'user_can_grade'      => current_user_can( 'aura_cal_manage_grades' ) || current_user_can( 'aura_record_grades' ) || current_user_can( 'manage_options' ),
            'user_can_programs'   => current_user_can( 'aura_cal_manage_programs' ) || current_user_can( 'aura_create_calendar_events' ) || current_user_can( 'manage_options' ),
            'user_can_tasks'      => current_user_can( 'aura_cal_manage_tasks' ) || current_user_can( 'aura_cal_grade_tasks' ) || current_user_can( 'manage_options' ),
            'gcal_enabled'        => Aura_Calendar_Google_Sync::is_enabled(),
            'gcal_name'           => Aura_Calendar_Google_Sync::get_calendar_name(),
            'programs'            => $programs,
            'teachers'            => $teachers_clean,
            'paletteColors'       => self::get_palette_colors(),
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

        if ( ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $cal_name  = sanitize_text_field( $_POST['cal_name'] ?? '' );
        $auto_sync = ! empty( $_POST['auto_sync'] ) ? '1' : '0';

        $teacher_portal_page_id = intval( $_POST['teacher_portal_page_id'] ?? 0 );
        $teacher_code_prefix    = strtoupper( sanitize_text_field( $_POST['teacher_code_prefix'] ?? 'CEM-PROF' ) );

        update_option( Aura_Calendar_Google_Sync::CAL_NAME_OPTION, $cal_name );
        update_option( Aura_Calendar_Google_Sync::AUTO_SYNC_OPTION, $auto_sync );
        update_option( self::OPTION_TEACHER_PORTAL_PAGE, $teacher_portal_page_id );
        update_option( self::OPTION_TEACHER_CODE_PREFIX, $teacher_code_prefix );

        wp_send_json_success( [ 'message' => __( 'Ajustes guardados correctamente.', 'aura' ) ] );
    }

    /**
     * Obtener ID de la página asignada al Portal del Instructor
     *
     * @return int
     */
    public static function get_teacher_portal_page_id(): int {
        $saved = (int) get_option( self::OPTION_TEACHER_PORTAL_PAGE, 0 );
        if ( $saved > 0 && get_post_status( $saved ) === 'publish' ) {
            return $saved;
        }

        // Búsqueda automática si no está configurado explícitamente
        global $wpdb;
        $found_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[aura_teacher_portal%' LIMIT 1" );
        if ( $found_id > 0 ) {
            update_option( self::OPTION_TEACHER_PORTAL_PAGE, $found_id );
            return $found_id;
        }

        return 0;
    }

    /**
     * Obtener prefijo de código de instructor
     *
     * @return string
     */
    public static function get_teacher_code_prefix(): string {
        return (string) get_option( self::OPTION_TEACHER_CODE_PREFIX, 'CEM-PROF' );
    }

    /**
     * Obtener listado de usuarios que califican como Instructores en Aura Suite
     *
     * @return array
     */
    public static function get_instructors(): array {
        global $wpdb;
        $prefix = self::get_teacher_code_prefix();

        $all_users = get_users( [
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 300,
        ] );

        $instructors = [];

        // Áreas asignadas por usuario si el módulo de áreas está activo
        $t_area_users   = $wpdb->prefix . 'aura_area_users';
        $t_areas        = $wpdb->prefix . 'aura_areas';
        $user_areas_map = [];

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_area_users}'" ) === $t_area_users && $wpdb->get_var( "SHOW TABLES LIKE '{$t_areas}'" ) === $t_areas ) {
            $area_rows = $wpdb->get_results(
                "SELECT au.user_id, a.id AS area_id, a.name AS area_name, a.color AS area_color
                 FROM {$t_area_users} au
                 JOIN {$t_areas} a ON a.id = au.area_id
                 WHERE a.status = 'active'"
            );
            if ( is_array( $area_rows ) ) {
                foreach ( $area_rows as $ar ) {
                    $user_areas_map[ $ar->user_id ][] = [
                        'id'    => (int) $ar->area_id,
                        'name'  => $ar->area_name,
                        'color' => $ar->area_color,
                    ];
                }
            }
        }

        // Revisar si existen en wp_aura_students con profile_type = 'teacher'
        $t_students              = $wpdb->prefix . 'aura_students';
        $teacher_students_wp_ids = [];
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_students}'" ) === $t_students ) {
            $stu_teachers = $wpdb->get_results( "SELECT wp_user_id, id_number, phone, photo_url FROM {$t_students} WHERE profile_type = 'teacher'" );
            if ( is_array( $stu_teachers ) ) {
                foreach ( $stu_teachers as $st ) {
                    if ( ! empty( $st->wp_user_id ) ) {
                        $teacher_students_wp_ids[ $st->wp_user_id ] = $st;
                    }
                }
            }
        }

        foreach ( $all_users as $u ) {
            $is_instructor = (
                user_can( $u->ID, 'aura_cal_view_calendar' ) ||
                user_can( $u->ID, 'aura_teach_calendar' ) ||
                user_can( $u->ID, 'manage_options' ) ||
                in_array( 'academic_teacher', (array) $u->roles, true ) ||
                in_array( 'administrator', (array) $u->roles, true ) ||
                in_array( 'editor', (array) $u->roles, true ) ||
                isset( $teacher_students_wp_ids[ $u->ID ] )
            );

            if ( ! $is_instructor ) {
                continue;
            }

            $meta_code = get_user_meta( $u->ID, 'aura_teacher_code', true );
            if ( empty( $meta_code ) && isset( $teacher_students_wp_ids[ $u->ID ] ) && ! empty( $teacher_students_wp_ids[ $u->ID ]->id_number ) ) {
                $meta_code = $teacher_students_wp_ids[ $u->ID ]->id_number;
            }
            if ( empty( $meta_code ) ) {
                $meta_code = $prefix . '-' . str_pad( (string) $u->ID, 3, '0', STR_PAD_LEFT );
            }

            $avatar_url = get_avatar_url( $u->ID, [ 'size' => 64 ] );
            if ( isset( $teacher_students_wp_ids[ $u->ID ] ) && ! empty( $teacher_students_wp_ids[ $u->ID ]->photo_url ) ) {
                $avatar_url = $teacher_students_wp_ids[ $u->ID ]->photo_url;
            }

            $instructors[] = [
                'id'     => (int) $u->ID,
                'name'   => $u->display_name,
                'email'  => $u->user_email,
                'code'   => $meta_code,
                'avatar' => $avatar_url,
                'areas'  => $user_areas_map[ $u->ID ] ?? [],
            ];
        }

        return $instructors;
    }

    /**
     * AJAX: Crear página de WordPress para el Portal del Instructor automáticamente
     */
    public static function ajax_create_teacher_portal_page(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        // Comprobar si ya existe
        $existing_id = self::get_teacher_portal_page_id();
        if ( $existing_id > 0 ) {
            wp_send_json_success( [
                'message'   => __( 'Ya existe una página configurada para el Portal del Instructor.', 'aura' ),
                'page_id'   => $existing_id,
                'permalink' => get_permalink( $existing_id ),
            ] );
        }

        $page_data = [
            'post_title'   => __( 'Portal del Instructor', 'aura' ),
            'post_name'    => 'portal-instructor',
            'post_content' => '<!-- wp:shortcode -->[aura_teacher_portal]<!-- /wp:shortcode -->',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ];

        $page_id = wp_insert_post( $page_data );

        if ( is_wp_error( $page_id ) || ! $page_id ) {
            wp_send_json_error( [ 'message' => __( 'Error al crear la página en WordPress.', 'aura' ) ] );
        }

        update_option( self::OPTION_TEACHER_PORTAL_PAGE, (int) $page_id );

        wp_send_json_success( [
            'message'   => __( '¡Página del Portal del Instructor creada con éxito!', 'aura' ),
            'page_id'   => (int) $page_id,
            'permalink' => get_permalink( $page_id ),
        ] );
    }

    /**
     * Obtener los 20 colores predeterminados del archivo colores20.json
     *
     * @return array
     */
    public static function get_palette_colors(): array {
        $json_file = AURA_PLUGIN_DIR . 'colores20.json';
        if ( file_exists( $json_file ) ) {
            $content = file_get_contents( $json_file );
            $decoded = json_decode( $content, true );
            if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                return $decoded;
            }
        }

        // Fallback canónico de los 20 colores
        return [
            [ 'id' => 1,  'hex' => '#5D5FEF', 'name' => 'Azul Eléctrico (Principal)' ],
            [ 'id' => 2,  'hex' => '#E05300', 'name' => 'Naranja Intenso' ],
            [ 'id' => 3,  'hex' => '#00B67A', 'name' => 'Verde Esmeralda' ],
            [ 'id' => 4,  'hex' => '#FF9F1C', 'name' => 'Amarillo Caléndula' ],
            [ 'id' => 5,  'hex' => '#3A86FF', 'name' => 'Azul Brillante' ],
            [ 'id' => 6,  'hex' => '#9B5DE5', 'name' => 'Violeta Vibrante' ],
            [ 'id' => 7,  'hex' => '#06B6D4', 'name' => 'Turquesa Cyan' ],
            [ 'id' => 8,  'hex' => '#F15BB5', 'name' => 'Rosa Neón/Fucsia' ],
            [ 'id' => 9,  'hex' => '#00F5D4', 'name' => 'Aguamarina Eléctrico' ],
            [ 'id' => 10, 'hex' => '#FF006E', 'name' => 'Magenta Vivo' ],
            [ 'id' => 11, 'hex' => '#70E000', 'name' => 'Verde Lima Neón' ],
            [ 'id' => 12, 'hex' => '#FFBE0B', 'name' => 'Amarillo Oro' ],
            [ 'id' => 13, 'hex' => '#FB5607', 'name' => 'Naranja Rojizo' ],
            [ 'id' => 14, 'hex' => '#8338EC', 'name' => 'Púrpura Profundo' ],
            [ 'id' => 15, 'hex' => '#0077B6', 'name' => 'Azul Océano' ],
            [ 'id' => 16, 'hex' => '#00F5D4', 'name' => 'Verde Menta Vivo' ],
            [ 'id' => 17, 'hex' => '#FF70A6', 'name' => 'Salmón Encendido' ],
            [ 'id' => 18, 'hex' => '#A2D2FF', 'name' => 'Azul Pastel Brillante' ],
            [ 'id' => 19, 'hex' => '#D90429', 'name' => 'Rojo Carmín' ],
            [ 'id' => 20, 'hex' => '#4CC9F0', 'name' => 'Azul Cielo Eléctrico' ],
        ];
    }

    /**
     * Renderizar la paleta de 20 colores en HTML vinculada a un input de tipo color
     *
     * @param string $target_input_id ID del input color (sin #)
     * @param string $current_color Hexadecimal actual
     * @return string HTML
     */
    public static function render_color_palette( string $target_input_id, string $current_color = '#5D5FEF' ): string {
        $colors = self::get_palette_colors();
        $current_upper = strtoupper( trim( $current_color ) );

        $html = '<div class="aura-color-palette" data-target-input="#' . esc_attr( $target_input_id ) . '" role="group" aria-label="' . esc_attr__( 'Paleta de colores predefinida', 'aura' ) . '">';
        foreach ( $colors as $c ) {
            $hex_upper = strtoupper( trim( $c['hex'] ) );
            $is_active = ( $hex_upper === $current_upper );
            $html .= sprintf(
                '<button type="button" class="aura-swatch %s" data-color="%s" title="%s" style="background-color: %s;" aria-label="%s"></button>',
                $is_active ? 'is-selected' : '',
                esc_attr( $c['hex'] ),
                esc_attr( $c['name'] . ' (' . $c['hex'] . ')' ),
                esc_attr( $c['hex'] ),
                esc_attr( $c['name'] )
            );
        }
        $html .= '</div>';
        return $html;
    }
}
