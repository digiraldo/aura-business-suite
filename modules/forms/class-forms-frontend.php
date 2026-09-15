<?php
/**
 * Frontend del Módulo de Formularios — Shortcodes y renderizado público
 *
 * Registra los shortcodes:
 *  - [aura_form id="X"]      — Renderiza un formulario específico (público o con login)
 *  - [aura_form_portal]      — Pestaña "Mis Formularios" del portal del estudiante
 *
 * También intercepta la URL amigable /formulario/{slug}/ para renderizar
 * el formulario sin shortcode.
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Frontend {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_shortcode( 'aura_form',        [ __CLASS__, 'shortcode_form' ] );
        add_shortcode( 'aura_form_portal', [ __CLASS__, 'shortcode_portal' ] );
        add_action( 'template_redirect',   [ __CLASS__, 'handle_form_url' ] );
        add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'maybe_enqueue_assets' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE [aura_form id="X" slug="..." redirect="..."]
    // ─────────────────────────────────────────────────────────────

    public static function shortcode_form( array $atts ): string {
        $atts = shortcode_atts(
            [
                'id'       => 0,
                'slug'     => '',
                'redirect' => '',
            ],
            $atts,
            'aura_form'
        );

        $form = self::load_form( (int) $atts['id'], sanitize_title( $atts['slug'] ) );

        if ( ! $form ) {
            return '<p class="aura-form-notice aura-form-notice--error">' .
                   esc_html__( 'Formulario no encontrado.', 'aura-suite' ) . '</p>';
        }

        return self::render_form( $form, sanitize_url( $atts['redirect'] ) );
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE [aura_form_portal]
    // ─────────────────────────────────────────────────────────────

    public static function shortcode_portal( array $atts ): string {
        // ── 1. Autenticación ─────────────────────────────────────
        if ( ! is_user_logged_in() ) {
            return '<p class="aura-form-notice aura-form-notice--login">' .
                   wp_kses(
                       sprintf(
                           /* translators: %s login URL */
                           __( 'Debes <a href="%s">iniciar sesión</a> para ver tus formularios.', 'aura-suite' ),
                           esc_url( wp_login_url( get_permalink() ) )
                       ),
                       [ 'a' => [ 'href' => [] ] ]
                   ) . '</p>';
        }

        if ( ! current_user_can( 'aura_students_view_own' ) && ! current_user_can( 'manage_options' ) ) {
            return '<p class="aura-form-notice aura-form-notice--error">' .
                   esc_html__( 'No tienes permiso para ver esta página.', 'aura-suite' ) . '</p>';
        }

        // ── 2. Cargar estudiante ─────────────────────────────────
        global $wpdb;
        $student_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aura_students WHERE wp_user_id = %d LIMIT 1",
                get_current_user_id()
            )
        );

        if ( ! $student_id ) {
            return '<p class="aura-form-notice aura-form-notice--error">' .
                   esc_html__( 'No se encontró tu perfil de estudiante.', 'aura-suite' ) . '</p>';
        }

        // ── 3. Encuestas pendientes ──────────────────────────────
        $pending_assignments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.form_id, a.expires_at, a.assigned_at,
                        f.title AS form_title, f.description AS form_description,
                        f.slug AS form_slug,
                        c.name AS course_name
                   FROM {$wpdb->prefix}aura_form_assignments a
                   JOIN {$wpdb->prefix}aura_forms f ON f.id = a.form_id
                   LEFT JOIN {$wpdb->prefix}aura_student_courses c ON c.id = f.course_id
                  WHERE a.student_id = %d
                    AND a.status = 'pending'
                    AND ( a.expires_at IS NULL OR a.expires_at > NOW() )
                  ORDER BY a.assigned_at DESC",
                $student_id
            )
        );

        // Pre-renderizar cada formulario pendiente
        if ( is_array( $pending_assignments ) ) {
            foreach ( $pending_assignments as $assignment ) {
                $form = self::load_form( (int) $assignment->form_id, '' );
                $assignment->form_html = $form
                    ? self::render_form( $form )
                    : '<p class="aura-form-notice aura-form-notice--error">' .
                      esc_html__( 'Formulario no disponible.', 'aura-suite' ) . '</p>';
            }
        } else {
            $pending_assignments = [];
        }

        // ── 4. Encuestas completadas (últimas 20) ────────────────
        $completed_assignments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.id, a.form_id, a.completed_at, a.submission_id,
                        f.title AS form_title
                   FROM {$wpdb->prefix}aura_form_assignments a
                   JOIN {$wpdb->prefix}aura_forms f ON f.id = a.form_id
                  WHERE a.student_id = %d
                    AND a.status = 'completed'
                  ORDER BY a.completed_at DESC
                  LIMIT 20",
                $student_id
            )
        ) ?: [];

        // ── 5. Assets adicionales del portal ────────────────────
        $nonce = wp_create_nonce( 'aura_students_frontend_nonce' );

        // ── 6. Renderizar template ───────────────────────────────
        ob_start();
        include AURA_PLUGIN_DIR . 'templates/forms/frontend/form-portal.php';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // URL AMIGABLE /formulario/{slug}/
    // ─────────────────────────────────────────────────────────────

    public static function handle_form_url(): void {
        $slug = get_query_var( 'aura_form_slug', '' );
        if ( empty( $slug ) ) {
            return;
        }

        $form = self::load_form( 0, sanitize_title( $slug ) );

        status_header( 200 );
        nocache_headers();

        $site_name  = get_bloginfo( 'name' );
        $form_title = $form ? $form->title : __( 'Formulario', 'aura-suite' );
        $home_url   = home_url( '/' );
        $primary    = ( $form && ! empty( $form->primary_color ) ) ? $form->primary_color : '#2563eb';
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $form_title . ' — ' . $site_name ); ?></title>
    <style>:root{--afs-brand:<?php echo esc_attr( $primary ); ?>;}</style>
    <?php wp_head(); ?>
</head>
<body class="aura-form-standalone">
<?php wp_body_open(); ?>

<div class="afs-bg-blobs" aria-hidden="true">
    <span class="afs-blob afs-blob-1"></span>
    <span class="afs-blob afs-blob-2"></span>
</div>

<main class="afs-main" role="main" id="afs-main-content">
    <div class="afs-container">
        <?php
        if ( $form ) {
            echo self::render_form( $form, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<p class="aura-form-notice aura-form-notice--error">' .
                 esc_html__( 'Formulario no encontrado.', 'aura-suite' ) . '</p>';
        }
        ?>
    </div>
</main>

<button type="button" id="afs-theme-toggle" class="afs-theme-toggle"
        aria-label="<?php esc_attr_e( 'Cambiar modo claro / oscuro', 'aura-suite' ); ?>">
    <svg class="afs-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
    <svg class="afs-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>

<footer class="afs-footer" role="contentinfo">
    <a href="<?php echo esc_url( $home_url ); ?>" class="afs-footer-brand">
        <?php echo esc_html( $site_name ); ?>
    </a>
</footer>

<script>
(function(){
    var key = 'afs_theme';
    var saved = localStorage.getItem(key);
    if ( saved === 'dark' || ( ! saved && window.matchMedia('(prefers-color-scheme:dark)').matches ) ) {
        document.documentElement.setAttribute('data-afs-theme', 'dark');
    }
    document.getElementById('afs-theme-toggle').addEventListener('click', function(){
        var isDark = document.documentElement.getAttribute('data-afs-theme') === 'dark';
        var next = isDark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-afs-theme', next);
        localStorage.setItem(key, next);
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
        <?php
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER CENTRAL
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera el HTML completo del formulario (validaciones + shortcode unificado).
     *
     * @param object $form     Fila de aura_forms.
     * @param string $redirect URL de redirect override (opcional).
     * @return string HTML del formulario.
     */
    public static function render_form( object $form, string $redirect = '' ): string {
        // ── 1. Verificar estado del formulario ────────────────────
        if ( ! $form->is_active ) {
            return '<p class="aura-form-notice aura-form-notice--closed">' .
                   esc_html__( 'Este formulario está cerrado.', 'aura-suite' ) . '</p>';
        }

        if ( $form->close_date && strtotime( $form->close_date ) < time() ) {
            return '<p class="aura-form-notice aura-form-notice--closed">' .
                   esc_html__( 'Este formulario ha expirado.', 'aura-suite' ) . '</p>';
        }

        if ( $form->requires_login && ! is_user_logged_in() ) {
            return '<p class="aura-form-notice aura-form-notice--login">' .
                   wp_kses(
                       sprintf(
                           /* translators: %s login URL */
                           __( 'Debes <a href="%s">iniciar sesión</a> para completar este formulario.', 'aura-suite' ),
                           esc_url( wp_login_url( get_permalink() ) )
                       ),
                       [ 'a' => [ 'href' => [] ] ]
                   ) . '</p>';
        }

        // ── 2. Verificar límite de submissions ────────────────────
        if ( $form->max_submissions > 0 ) {
            global $wpdb;
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions WHERE form_id = %d",
                    $form->id
                )
            );
            if ( $count >= $form->max_submissions ) {
                return '<p class="aura-form-notice aura-form-notice--closed">' .
                       esc_html__( 'Este formulario ya no acepta más respuestas.', 'aura-suite' ) . '</p>';
            }
        }

        // ── 3. Cargar campos ──────────────────────────────────────
        global $wpdb;
        $fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aura_form_fields
                  WHERE form_id = %d
                  ORDER BY sort_order ASC",
                $form->id
            )
        );

        // ── 4. Encolar assets frontend ────────────────────────────
        // Si ya se encolaron desde maybe_enqueue_assets (URL amigable o shortcode),
        // wp_enqueue_* es idempotente. Solo localizamos si el formId aún no fue enviado
        // (evita doble nonce cuando se llega desde URL amigable).
        if ( ! wp_script_is( 'aura-forms-frontend', 'done' ) ) {
            self::enqueue_assets( $form );
        }

        // ── 5. Renderizar template ────────────────────────────────
        $override_redirect = $redirect ?: $form->redirect_url;

        ob_start();
        include AURA_PLUGIN_DIR . 'templates/forms/frontend/form-render.php';
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // ASSETS
    // ─────────────────────────────────────────────────────────────

    /**
     * Encola assets en páginas que contengan los shortcodes.
     * Se llama en wp_enqueue_scripts para que los estilos estén en el head.
     */
    public static function maybe_enqueue_assets(): void {
        global $post;
        if (
            is_a( $post, 'WP_Post' ) &&
            (
                has_shortcode( $post->post_content, 'aura_form' ) ||
                has_shortcode( $post->post_content, 'aura_form_portal' )
            )
        ) {
            self::enqueue_assets( null );
        }
        // También cargar en URL amigable /formulario/{slug}/
        $form_slug = get_query_var( 'aura_form_slug', '' );
        if ( $form_slug !== '' ) {
            // Cargamos el formulario aquí para poder incluir formNonce y formId en el JS.
            $form_for_assets = self::load_form( 0, sanitize_title( $form_slug ) );
            self::enqueue_assets( $form_for_assets ?: null );
        }
    }

    /**
     * Encola CSS y JS del frontend para un formulario específico.
     *
     * @param object|null $form Formulario; null = solo styles genéricos.
     */
    public static function enqueue_assets( ?object $form ): void {
        $ver = AURA_VERSION;

        wp_enqueue_style(
            'aura-forms-frontend',
            AURA_PLUGIN_URL . 'assets/css/forms-frontend.css',
            [],
            $ver
        );

        wp_enqueue_script(
            'aura-forms-frontend',
            AURA_PLUGIN_URL . 'assets/js/forms-frontend.js',
            [ 'jquery' ],
            $ver,
            true
        );

        $localize = [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'i18n'    => [
                'required'     => __( 'Este campo es obligatorio.', 'aura-suite' ),
                'invalidEmail' => __( 'Ingresa un correo electrónico válido.', 'aura-suite' ),
                'fileSize'     => __( 'El archivo supera el tamaño máximo permitido.', 'aura-suite' ),
                'fileExt'      => __( 'Tipo de archivo no permitido.', 'aura-suite' ),
                'sending'      => __( 'Enviando…', 'aura-suite' ),
                'send'         => __( 'Enviar', 'aura-suite' ),
                'serverError'  => __( 'Error del servidor. Intenta nuevamente.', 'aura-suite' ),
            ],
        ];

        if ( $form ) {
            $localize['formNonce'] = wp_create_nonce( 'aura_form_submit_' . $form->id );
            $localize['formId']    = $form->id;
        }

        wp_localize_script( 'aura-forms-frontend', 'auraFormsFrontend', $localize );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve la URL pública de un formulario a partir de su slug.
     *
     * Usa siempre la URL amigable /formulario/{slug}/ generada por el sistema
     * de rewrite rules. Es el punto central de construcción de URLs del módulo;
     * todos los demás archivos deben llamar a este método.
     *
     * @param string $slug Slug del formulario.
     * @return string URL absoluta del formulario.
     */
    public static function get_form_url( string $slug ): string {
        return trailingslashit( site_url( 'formulario/' . sanitize_title( $slug ) ) );
    }

    /**
     * Devuelve la URL del portal de formularios del estudiante.
     *
     * Usa la página configurada en Ajustes > Integración Frontend > Página del portal.
     * Si no hay página configurada, devuelve la URL del sitio.
     *
     * @return string URL absoluta del portal.
     */
    public static function get_portal_url(): string {
        $page_id = (int) Aura_Forms_Settings::get( 'portal_page' );
        if ( $page_id > 0 ) {
            $url = get_permalink( $page_id );
            if ( $url ) {
                return $url;
            }
        }
        return home_url( '/' );
    }

    /**
     * Carga un formulario activo por ID o por slug.
     *
     * @param int    $id   ID del formulario (0 = ignorar).
     * @param string $slug Slug del formulario (vacío = ignorar).
     * @return object|null Fila de aura_forms o null.
     */
    public static function load_form( int $id, string $slug ): ?object {
        global $wpdb;

        if ( $id > 0 ) {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}aura_forms
                      WHERE id = %d AND deleted_at IS NULL",
                    $id
                )
            ) ?: null;
        }

        if ( $slug !== '' ) {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}aura_forms
                      WHERE slug = %s AND deleted_at IS NULL",
                    $slug
                )
            ) ?: null;
        }

        return null;
    }
}
