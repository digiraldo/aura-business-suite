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
