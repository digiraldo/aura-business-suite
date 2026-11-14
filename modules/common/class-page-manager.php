<?php
/**
 * Aura Page Manager — Gestión centralizada de páginas frontend.
 *
 * Unifica en un solo lugar (Ajustes → pestaña "Páginas") la creación, vinculación
 * y reparación de todas las páginas públicas del plugin (portales, login,
 * inscripciones, certificados, formularios, paz y salvo, horarios).
 *
 * - Es idempotente: nunca duplica páginas. Orden de resolución:
 *   1) ID ya guardado y existente  2) página que ya contiene el shortcode
 *   3) página con el mismo slug     4) crear una nueva.
 * - Cada página sigue guardándose en la opción original de su módulo, por lo que
 *   los módulos existentes continúan funcionando sin cambios.
 *
 * @package AuraBusinessSuite
 * @subpackage Common
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Page_Manager {

    const VERSION        = '1.0.0';
    const OPTION_VERSION = 'aura_pages_provisioned';
    const OPTION_EXTRA   = 'aura_system_pages';
    const NONCE_ACTION   = 'aura_pages_nonce';

    public static function init(): void {
        add_action( 'admin_init', [ __CLASS__, 'maybe_provision' ] );
        add_action( 'wp_ajax_aura_pages_sync', [ __CLASS__, 'ajax_sync' ] );
        add_action( 'wp_ajax_aura_pages_assign', [ __CLASS__, 'ajax_assign' ] );

        if ( ! is_admin() ) {
            add_filter( 'body_class', [ __CLASS__, 'filter_system_body_class' ], 20 );
            add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_system_page_theme' ], 999 );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REGISTRO DE PÁGINAS
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array<string,array>
     */
    public static function registry(): array {
        return [
            'student_login' => [
                'module' => __( 'Estudiantes', 'aura-suite' ),
                'title'  => __( 'Acceso a la Plataforma', 'aura-suite' ),
                'label'  => __( 'Acceso / Login Estudiantes', 'aura-suite' ),
                'slug'   => 'acceso',
                'tag'    => 'aura_login',
                'class'  => 'Aura_Students_Settings',
                'desc'   => __( 'Inicio de sesión para estudiantes e instructores.', 'aura-suite' ),
            ],
            'student_portal' => [
                'module' => __( 'Estudiantes', 'aura-suite' ),
                'title'  => __( 'Portal del Estudiante', 'aura-suite' ),
                'label'  => __( 'Portal del Estudiante (Dashboard)', 'aura-suite' ),
                'slug'   => 'portal-estudiante',
                'tag'    => 'aura_portal',
                'class'  => 'Aura_Students_Settings',
                'desc'   => __( 'Panel del estudiante: pagos, becas, notas, certificados y encuestas.', 'aura-suite' ),
            ],
            'student_enrollment' => [
                'module' => __( 'Estudiantes', 'aura-suite' ),
                'title'  => __( 'Inscripciones', 'aura-suite' ),
                'label'  => __( 'Formulario de Inscripción', 'aura-suite' ),
                'slug'   => 'inscripciones',
                'tag'    => 'aura_enrollment_form',
                'class'  => 'Aura_Students_Settings',
                'desc'   => __( 'Formulario público de inscripción (usa los campos configurados en Estudiantes).', 'aura-suite' ),
            ],
            'student_pazsalvo' => [
                'module' => __( 'Estudiantes / Finanzas', 'aura-suite' ),
                'title'  => __( 'Consulta de Paz y Salvo', 'aura-suite' ),
                'label'  => __( 'Verificación de Paz y Salvo', 'aura-suite' ),
                'slug'   => 'paz-y-salvo',
                'tag'    => 'aura_student_paz_salvo_check',
                'class'  => 'Aura_Students_Settings',
                'desc'   => __( 'Consulta pública del estado financiero (paz y salvo) de un estudiante.', 'aura-suite' ),
            ],
            'teacher_portal' => [
                'module' => __( 'Calendario', 'aura-suite' ),
                'title'  => __( 'Portal del Instructor', 'aura-suite' ),
                'label'  => __( 'Portal del Instructor', 'aura-suite' ),
                'slug'   => 'portal-instructor',
                'tag'    => 'aura_teacher_portal',
                'class'  => 'Aura_Calendar_Admin',
                'desc'   => __( 'Panel del instructor: horarios, asistencia, tareas y calificaciones.', 'aura-suite' ),
            ],
            'student_schedule' => [
                'module' => __( 'Calendario', 'aura-suite' ),
                'title'  => __( 'Mi Horario de Clases', 'aura-suite' ),
                'label'  => __( 'Horario del Estudiante', 'aura-suite' ),
                'slug'   => 'mi-horario',
                'tag'    => 'aura_student_schedule',
                'class'  => 'Aura_Calendar_Admin',
                'desc'   => __( 'Calendario de clases para el estudiante.', 'aura-suite' ),
            ],
            'cert_mine' => [
                'module' => __( 'Certificados', 'aura-suite' ),
                'title'  => __( 'Mis Certificados', 'aura-suite' ),
                'label'  => __( 'Mis Certificados y Diplomas', 'aura-suite' ),
                'slug'   => 'mis-certificados',
                'tag'    => 'aura_mis_certificados',
                'class'  => 'Aura_Certificates_Settings',
                'desc'   => __( 'Lista y descarga de los certificados del usuario.', 'aura-suite' ),
            ],
            'cert_verify' => [
                'module' => __( 'Certificados', 'aura-suite' ),
                'title'  => __( 'Verificación de Certificados', 'aura-suite' ),
                'label'  => __( 'Verificar Autenticidad', 'aura-suite' ),
                'slug'   => 'verificacion-certificados',
                'tag'    => 'aura_verificar_certificado',
                'class'  => 'Aura_Certificates_Settings',
                'desc'   => __( 'Buscador público para validar un folio de certificado.', 'aura-suite' ),
            ],
            'forms_portal' => [
                'module' => __( 'Formularios', 'aura-suite' ),
                'title'  => __( 'Mis Encuestas y Formularios', 'aura-suite' ),
                'label'  => __( 'Portal de Formularios', 'aura-suite' ),
                'slug'   => 'mis-encuestas',
                'tag'    => 'aura_form_portal',
                'class'  => 'Aura_Forms_Settings',
                'desc'   => __( 'Encuestas y formularios asignados al estudiante.', 'aura-suite' ),
            ],
        ];
    }

    /** Páginas cuyo módulo está cargado. */
    public static function active_registry(): array {
        return array_filter( self::registry(), static function ( $p ) {
            return class_exists( $p['class'] );
        } );
    }

    // ─────────────────────────────────────────────────────────────
    // LECTURA / ESCRITURA (cada página en su opción original)
    // ─────────────────────────────────────────────────────────────

    public static function get_id( string $key ): int {
        switch ( $key ) {
            case 'student_login':
                return (int) Aura_Students_Settings::get( 'login_page_id', 0 );
            case 'student_portal':
                return (int) Aura_Students_Settings::get( 'portal_page_id', 0 );
            case 'student_enrollment':
                return (int) Aura_Students_Settings::get( 'enrollment_page_id', 0 );
            case 'teacher_portal':
                return (int) get_option( 'aura_cal_teacher_portal_page_id', 0 );
            case 'cert_mine':
                return (int) Aura_Certificates_Settings::get( 'cert_page_id', 0 );
            case 'forms_portal':
                return (int) Aura_Forms_Settings::get( 'portal_page', 0 );
            default:
                $extra = get_option( self::OPTION_EXTRA, [] );
                return is_array( $extra ) ? (int) ( $extra[ $key ] ?? 0 ) : 0;
        }
    }

    public static function set_id( string $key, int $page_id ): void {
        switch ( $key ) {
            case 'student_login':
            case 'student_portal':
            case 'student_enrollment':
                $map = [
                    'student_login'      => 'login_page_id',
                    'student_portal'     => 'portal_page_id',
                    'student_enrollment' => 'enrollment_page_id',
                ];
                $opts = Aura_Students_Settings::get_all();
                $opts[ $map[ $key ] ] = $page_id;
                update_option( Aura_Students_Settings::OPTION_KEY, $opts );
                break;
            case 'teacher_portal':
                update_option( 'aura_cal_teacher_portal_page_id', $page_id );
                break;
            case 'cert_mine':
                $opts = Aura_Certificates_Settings::get_all();
                $opts['cert_page_id'] = $page_id;
                update_option( Aura_Certificates_Settings::OPTION_KEY, $opts );
                break;
            case 'forms_portal':
                $opts = get_option( Aura_Forms_Settings::OPTION_KEY, [] );
                $opts = is_array( $opts ) ? $opts : [];
                $opts['portal_page'] = $page_id;
                update_option( Aura_Forms_Settings::OPTION_KEY, $opts );
                break;
            default:
                $extra = get_option( self::OPTION_EXTRA, [] );
                $extra = is_array( $extra ) ? $extra : [];
                $extra[ $key ] = $page_id;
                update_option( self::OPTION_EXTRA, $extra );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RESOLUCIÓN Y CREACIÓN (sin duplicados)
    // ─────────────────────────────────────────────────────────────

    private static function find_by_shortcode( string $tag ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status = 'publish'
               AND ( post_content LIKE %s OR post_content LIKE %s )
             ORDER BY ID ASC LIMIT 1",
            '%' . $wpdb->esc_like( '[' . $tag . ']' ) . '%',
            '%' . $wpdb->esc_like( '[' . $tag . ' ' ) . '%'
        ) );
    }

    /**
     * Aplica configuración base de diseño (Divi sin barra lateral y marcador de página del sistema).
     */
    public static function apply_divi_defaults( int $page_id ): void {
        if ( $page_id <= 0 || 'page' !== get_post_type( $page_id ) ) {
            return;
        }
        $current_layout = get_post_meta( $page_id, '_et_pb_page_layout', true );
        if ( '' === $current_layout || 'et_right_sidebar' === $current_layout ) {
            update_post_meta( $page_id, '_et_pb_page_layout', 'et_no_sidebar' );
        }
        update_post_meta( $page_id, '_aura_system_page', '1' );
    }

    /**
     * Devuelve los IDs de todas las páginas del sistema registradas y publicadas.
     *
     * @return int[]
     */
    public static function get_system_page_ids(): array {
        $ids = [];
        foreach ( array_keys( self::active_registry() ) as $key ) {
            $id = self::get_id( $key );
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * Determina si la petición frontend actual corresponde a una página del sistema Aura
     * o a una vista/shortcode público del plugin.
     */
    public static function is_current_system_page(): bool {
        if ( function_exists( 'get_query_var' ) && ! empty( get_query_var( 'aura_cert_verify' ) ) ) {
            return true;
        }
        if ( ! is_page() && ! is_singular() ) {
            return false;
        }
        $post = get_queried_object();
        if ( ! ( $post instanceof WP_Post ) ) {
            return false;
        }
        if ( in_array( (int) $post->ID, self::get_system_page_ids(), true ) ) {
            return true;
        }
        if ( '1' === (string) get_post_meta( $post->ID, '_aura_system_page', true ) ) {
            return true;
        }
        $tags = [];
        foreach ( self::registry() as $def ) {
            if ( ! empty( $def['tag'] ) ) {
                $tags[] = $def['tag'];
            }
        }
        $tags = array_unique( array_merge( $tags, [
            'aura_student_login',
            'aura_community_login',
            'aura_student_portal',
            'aura_portal_estudiante',
            'aura_form',
        ] ) );
        foreach ( $tags as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Agrega clases CSS en <body> para habilitar el modo claro/oscuro automático y ocultar barra lateral en Divi.
     *
     * @param string[] $classes Clases actuales del body.
     * @return string[]
     */
    public static function filter_system_body_class( array $classes ): array {
        if ( ! self::is_current_system_page() ) {
            return $classes;
        }
        $classes[] = 'aura-system-page';
        $page_id   = get_queried_object_id();
        $layout    = $page_id ? get_post_meta( $page_id, '_et_pb_page_layout', true ) : '';
        if ( '' === $layout || 'et_no_sidebar' === $layout || 'et_full_width_page' === $layout ) {
            $classes[] = 'aura-divi-no-sidebar';
        }
        return array_values( array_unique( $classes ) );
    }

    /**
     * Devuelve el HTML estándar del botón conmutador de modo claro/oscuro (.aura-theme-toggle).
     */
    public static function render_theme_toggle( string $label = '', string $extra_class = '' ): string {
        $text  = '' !== $label ? $label : __( 'Modo oscuro', 'aura-suite' );
        $class = trim( 'aura-theme-toggle ' . $extra_class );
        return sprintf(
            '<button type="button" class="%s" aria-label="%s"><span class="dashicons dashicons-moon"></span> <span class="aura-theme-toggle-label">%s</span></button>',
            esc_attr( $class ),
            esc_attr__( 'Cambiar tema', 'aura-suite' ),
            esc_html( $text )
        );
    }

    /**
     * Devuelve una barra superior alineada a la derecha con el botón .aura-theme-toggle.
     */
    public static function render_theme_toggle_bar( string $label = '', string $margin = '0 0 14px 0' ): string {
        return sprintf(
            '<div class="aura-theme-toggle-bar" style="display:flex;justify-content:flex-end;align-items:center;margin:%s;">%s</div>',
            esc_attr( $margin ),
            self::render_theme_toggle( $label )
        );
    }

    /**
     * Encola los assets del conmutador de tema claro/oscuro con versionado por filemtime()
     * para evitar caché obsoleta y asegurar precedencia sobre estilos de tema/Divi.
     *
     * @param string[] $style_deps Dependencias CSS adicionales.
     */
    public static function enqueue_theme_assets( array $style_deps = [ 'dashicons' ] ): void {
        $plugin_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR : dirname( __DIR__, 2 ) . '/';
        $plugin_url = defined( 'AURA_PLUGIN_URL' ) ? AURA_PLUGIN_URL : plugin_dir_url( $plugin_dir . 'aura-business-suite.php' );
        $base_ver   = defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0';

        $css_file = $plugin_dir . 'assets/css/aura-frontend-dark-mode.css';
        $js_file  = $plugin_dir . 'assets/js/aura-frontend-theme.js';
        $css_ver  = file_exists( $css_file ) ? $base_ver . '.' . filemtime( $css_file ) : $base_ver;
        $js_ver   = file_exists( $js_file ) ? $base_ver . '.' . filemtime( $js_file ) : $base_ver;

        if ( ! wp_style_is( 'dashicons', 'enqueued' ) ) {
            wp_enqueue_style( 'dashicons' );
        }
        if ( ! wp_style_is( 'aura-frontend-dark-mode', 'enqueued' ) ) {
            wp_enqueue_style(
                'aura-frontend-dark-mode',
                $plugin_url . 'assets/css/aura-frontend-dark-mode.css',
                array_values( array_unique( array_merge( [ 'dashicons' ], $style_deps ) ) ),
                $css_ver
            );
        }
        if ( ! wp_script_is( 'aura-frontend-theme', 'enqueued' ) ) {
            wp_enqueue_script(
                'aura-frontend-theme',
                $plugin_url . 'assets/js/aura-frontend-theme.js',
                [],
                $js_ver,
                false
            );
        }
    }

    /**
     * Garantiza que todas las páginas del sistema (incluyendo Certificados y Paz y Salvo)
     * carguen los estilos de modo claro/oscuro y el script conmutador de tema.
     */
    public static function enqueue_system_page_theme(): void {
        if ( ! self::is_current_system_page() ) {
            return;
        }
        self::enqueue_theme_assets();
    }

    /**
     * Garantiza que la página exista y esté vinculada.
     *
     * @return array{id:int,status:string}  status: existing|linked|created|error
     */
    public static function ensure( string $key ): array {
        $reg = self::registry();
        if ( ! isset( $reg[ $key ] ) || ! class_exists( $reg[ $key ]['class'] ) ) {
            return [ 'id' => 0, 'status' => 'error' ];
        }
        $def = $reg[ $key ];

        $id = self::get_id( $key );
        if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
            self::apply_divi_defaults( $id );
            return [ 'id' => $id, 'status' => 'existing' ];
        }

        $found = self::find_by_shortcode( $def['tag'] );
        if ( ! $found ) {
            $by_slug = get_page_by_path( $def['slug'] );
            if ( $by_slug && 'publish' === $by_slug->post_status ) {
                $found = (int) $by_slug->ID;
            }
        }
        if ( $found ) {
            self::set_id( $key, $found );
            self::apply_divi_defaults( $found );
            return [ 'id' => $found, 'status' => 'linked' ];
        }

        $new_id = wp_insert_post( [
            'post_title'   => $def['title'],
            'post_name'    => $def['slug'],
            'post_content' => '<!-- wp:shortcode -->[' . $def['tag'] . ']<!-- /wp:shortcode -->',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id() ?: 1,
        ] );
        if ( ! $new_id || is_wp_error( $new_id ) ) {
            return [ 'id' => 0, 'status' => 'error' ];
        }
        self::set_id( $key, (int) $new_id );
        self::apply_divi_defaults( (int) $new_id );
        return [ 'id' => (int) $new_id, 'status' => 'created' ];
    }

    public static function ensure_all(): array {
        $out = [];
        foreach ( array_keys( self::active_registry() ) as $key ) {
            $out[ $key ] = self::ensure( $key );
        }
        return $out;
    }

    /** Aprovisionamiento automático una sola vez por versión. */
    public static function maybe_provision(): void {
        if ( get_option( self::OPTION_VERSION ) === self::VERSION ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        self::ensure_all();
        update_option( self::OPTION_VERSION, self::VERSION );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX
    // ─────────────────────────────────────────────────────────────

    private static function guard(): void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_admin_settings' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ], 403 );
        }
    }

    public static function ajax_sync(): void {
        self::guard();
        $key = sanitize_key( $_POST['key'] ?? '' );
        if ( $key ) {
            $res = [ $key => self::ensure( $key ) ];
        } else {
            $res = self::ensure_all();
        }
        wp_send_json_success( [
            'message' => __( 'Páginas sincronizadas correctamente.', 'aura-suite' ),
            'result'  => $res,
        ] );
    }

    public static function ajax_assign(): void {
        self::guard();
        $key     = sanitize_key( $_POST['key'] ?? '' );
        $page_id = absint( $_POST['page_id'] ?? 0 );
        $reg     = self::active_registry();
        if ( ! isset( $reg[ $key ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Página desconocida.', 'aura-suite' ) ] );
        }
        if ( $page_id && 'page' !== get_post_type( $page_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Selecciona una página válida.', 'aura-suite' ) ] );
        }
        self::set_id( $key, $page_id );
        if ( $page_id > 0 ) {
            self::apply_divi_defaults( $page_id );
        }
        wp_send_json_success( [ 'message' => __( 'Página asignada.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AVISO EN PANTALLAS DE MÓDULO
    // ─────────────────────────────────────────────────────────────

    public static function module_notice(): void {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $targets = [ 'aura-students-settings', 'aura-certificates-settings', 'aura-forms-settings', 'aura-calendar-settings' ];
        if ( ! in_array( $page, $targets, true ) ) {
            return;
        }
        $url = admin_url( 'admin.php?page=aura-settings&tab=pages' );
        echo '<div class="notice notice-info"><p>'
            . esc_html__( 'Las páginas públicas de todos los módulos se crean y administran en un solo lugar: ', 'aura-suite' )
            . '<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'Ajustes → Páginas del Sistema', 'aura-suite' ) . '</strong></a>.</p></div>';
    }

    // ─────────────────────────────────────────────────────────────
    // UI (panel de la pestaña "Páginas")
    // ─────────────────────────────────────────────────────────────

    public static function render_panel(): void {
        $nonce = wp_create_nonce( self::NONCE_ACTION );
        $pages = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );
        ?>
        <div class="aura-tab-panel" id="tab-pages">
            <div class="aura-panel-card">
                <div class="aura-card-header">
                    <div class="card-icon indigo"><span class="dashicons dashicons-admin-page"></span></div>
                    <div class="card-title">
                        <h3><?php esc_html_e( 'Páginas del Sistema (Frontend)', 'aura-suite' ); ?></h3>
                        <p><?php esc_html_e( 'Todas las páginas públicas del plugin en un solo lugar. Se crean automáticamente y nunca se duplican: si ya existe una página con el shortcode, se vincula.', 'aura-suite' ); ?></p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="aura-info-box info" style="margin-bottom:16px;padding:12px 16px;">
                        <p style="margin:0;font-size:13px;line-height:1.55;">
                            <strong>🎨 <?php esc_html_e( 'Integración automática con Divi 4/5 y Modo Claro/Oscuro:', 'aura-suite' ); ?></strong>
                            <?php esc_html_e( 'Al crear o sincronizar páginas aquí, el sistema configura automáticamente el diseño «Sin barra lateral» de Divi (_et_pb_page_layout = et_no_sidebar) y aplica el fondo adaptable de modo oscuro (clase aura-system-page) sin necesidad de editar cada página una por una.', 'aura-suite' ); ?>
                        </p>
                    </div>
                    <p>
                        <button type="button" class="btn btn-primary" id="aura-pages-sync-all">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                            <?php esc_html_e( 'Crear / reparar todas las páginas', 'aura-suite' ); ?>
                        </button>
                        <span id="aura-pages-msg" style="margin-left:10px;font-weight:600;"></span>
                    </p>
                    <table class="widefat striped" id="aura-pages-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Módulo', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Página', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Estado', 'aura-suite' ); ?></th>
                                <th><?php esc_html_e( 'Página asignada', 'aura-suite' ); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( self::active_registry() as $key => $def ) :
                            $id    = self::get_id( $key );
                            $ok    = $id > 0 && 'publish' === get_post_status( $id );
                            ?>
                            <tr data-key="<?php echo esc_attr( $key ); ?>">
                                <td><?php echo esc_html( $def['module'] ); ?></td>
                                <td>
                                    <strong><?php echo esc_html( $def['label'] ); ?></strong><br>
                                    <code>[<?php echo esc_html( $def['tag'] ); ?>]</code>
                                    <div style="color:#64748b;font-size:12px;"><?php echo esc_html( $def['desc'] ); ?></div>
                                </td>
                                <td>
                                    <?php if ( $ok ) : ?>
                                        <span style="color:#059669;font-weight:700;">● <?php esc_html_e( 'Activa', 'aura-suite' ); ?></span>
                                    <?php else : ?>
                                        <span style="color:#dc2626;font-weight:700;">● <?php esc_html_e( 'Falta', 'aura-suite' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select class="aura-page-assign" style="max-width:240px;">
                                        <option value="0"><?php esc_html_e( '— Sin asignar —', 'aura-suite' ); ?></option>
                                        <?php foreach ( $pages as $p ) : ?>
                                            <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $id, $p->ID ); ?>>
                                                <?php echo esc_html( $p->post_title ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <?php if ( $ok ) : ?>
                                        <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'Ver', 'aura-suite' ); ?></a>
                                        <a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'aura-suite' ); ?></a>
                                    <?php else : ?>
                                        <button type="button" class="button button-small aura-page-create"><?php esc_html_e( 'Crear', 'aura-suite' ); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
        jQuery(function ($) {
            var nonce = '<?php echo esc_js( $nonce ); ?>';
            function msg(t, ok) { $('#aura-pages-msg').text(t).css('color', ok ? '#059669' : '#dc2626'); }
            function sync(key) {
                $.post(ajaxurl, { action: 'aura_pages_sync', nonce: nonce, key: key || '' }, function (r) {
                    if (r && r.success) { msg(r.data.message, true); setTimeout(function () { location.reload(); }, 700); }
                    else { msg((r && r.data && r.data.message) || 'Error', false); }
                });
            }
            $('#aura-pages-sync-all').on('click', function () { sync(''); });
            $('#aura-pages-table').on('click', '.aura-page-create', function () { sync($(this).closest('tr').data('key')); });
            $('#aura-pages-table').on('change', '.aura-page-assign', function () {
                var $tr = $(this).closest('tr');
                $.post(ajaxurl, { action: 'aura_pages_assign', nonce: nonce, key: $tr.data('key'), page_id: $(this).val() }, function (r) {
                    if (r && r.success) { msg(r.data.message, true); setTimeout(function () { location.reload(); }, 600); }
                    else { msg((r && r.data && r.data.message) || 'Error', false); }
                });
            });
        });
        </script>
        <?php
    }
}
