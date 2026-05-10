<?php
/**
 * Aura Library Admin — Fase 1
 * Registra los menús del módulo de Biblioteca y gestiona el encolado de assets.
 *
 * Posición en el menú: 5.0 (entre Vehículos 4.9 y Electricidad 5.1)
 * Capability de acceso: aura_library_access (virtual — se otorga automáticamente
 * a cualquier usuario con al menos una cap aura_library_* activa).
 *
 * @package    Aura_Business_Suite
 * @subpackage Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Library_Admin {

    /** Slug del menú principal */
    const MENU_SLUG = 'aura-library';

    // ─────────────────────────────────────────────────────────────
    // INICIALIZACIÓN
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'admin_menu',            array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // Capability virtual: se concede al menú si el usuario tiene al menos
        // una capability específica del módulo Biblioteca.
        add_filter( 'user_has_cap', array( __CLASS__, 'grant_library_access' ), 10, 3 );
    }

    // ─────────────────────────────────────────────────────────────
    // CAPABILITY VIRTUAL
    // ─────────────────────────────────────────────────────────────

    /**
     * Concede la capability virtual 'aura_library_access' a cualquier usuario
     * que tenga al menos una capability específica del módulo de Biblioteca.
     * Esto controla únicamente la visibilidad del menú admin.
     *
     * @param array $allcaps  Capabilities actuales del usuario.
     * @param array $caps     Capabilities requeridas en la comprobación.
     * @param array $args     Argumentos extra (cap, user_id, object_id...).
     * @return array
     */
    public static function grant_library_access( array $allcaps, array $caps, array $args ): array {
        if ( ! in_array( 'aura_library_access', $caps, true ) ) {
            return $allcaps;
        }

        // Si ya tiene la cap (p.ej. admin), salir rápido.
        if ( ! empty( $allcaps['aura_library_access'] ) ) {
            return $allcaps;
        }

        // Cualquier capability específica del módulo otorga acceso al menú.
        $library_caps = array(
            'aura_library_create',
            'aura_library_edit',
            'aura_library_delete',
            'aura_library_view_catalog',
            'aura_library_loan_create',
            'aura_library_loan_return',
            'aura_library_loan_extend',
            'aura_library_view_loans_own',
            'aura_library_view_loans_all',
            'aura_library_reports',
            'aura_library_alerts',
            'aura_library_settings',
            'aura_library_audit',
        );

        foreach ( $library_caps as $cap ) {
            if ( ! empty( $allcaps[ $cap ] ) ) {
                $allcaps['aura_library_access'] = true;
                return $allcaps;
            }
        }

        return $allcaps;
    }

    // ─────────────────────────────────────────────────────────────
    // REGISTRO DE MENÚS
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar el menú principal y los submenús del módulo de Biblioteca.
     * Hook: admin_menu
     */
    public static function register_menus(): void {
        // ── Menú principal ────────────────────────────────────────
        add_menu_page(
            __( 'Biblioteca — AURA', 'aura-suite' ),
            __( 'Biblioteca', 'aura-suite' ),
            'aura_library_access',
            self::MENU_SLUG,
            array( __CLASS__, 'render_dashboard' ),
            'dashicons-book',
            3.7
        );

        // ── Submenús ──────────────────────────────────────────────

        // Dashboard (duplica el entry point del menú raíz)
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard Biblioteca', 'aura-suite' ),
            __( 'Dashboard', 'aura-suite' ),
            'aura_library_access',
            self::MENU_SLUG,
            array( __CLASS__, 'render_dashboard' )
        );

        // Catálogo de libros
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Catálogo de Libros', 'aura-suite' ),
            __( 'Catálogo', 'aura-suite' ),
            'aura_library_view_catalog',
            'aura-library-books',
            array( __CLASS__, 'render_books' )
        );

        // Préstamos
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Préstamos de Libros', 'aura-suite' ),
            __( 'Préstamos', 'aura-suite' ),
            'aura_library_view_loans_all',
            'aura-library-loans',
            array( __CLASS__, 'render_loans' )
        );

        // Reservas
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Reservas de Libros', 'aura-suite' ),
            __( 'Reservas', 'aura-suite' ),
            'aura_library_view_loans_all',
            'aura-library-reservations',
            array( __CLASS__, 'render_reservations' )
        );

        // Reportes
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Reportes de Biblioteca', 'aura-suite' ),
            __( 'Reportes', 'aura-suite' ),
            'aura_library_reports',
            'aura-library-reports',
            array( __CLASS__, 'render_reports' )
        );

        // Auditoría
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Auditoría — Biblioteca', 'aura-suite' ),
            __( 'Auditoría', 'aura-suite' ),
            'aura_library_audit',
            'aura-library-audit',
            array( __CLASS__, 'render_audit' )
        );

        // Configuración
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Configuración — Biblioteca', 'aura-suite' ),
            __( 'Configuración', 'aura-suite' ),
            'aura_library_settings',
            'aura-library-settings',
            array( __CLASS__, 'render_settings' )
        );
    }

    // ─────────────────────────────────────────────────────────────
    // ENCOLADO DE ASSETS
    // ─────────────────────────────────────────────────────────────

    /**
     * Encolar CSS y JS del módulo únicamente en sus páginas de admin.
     * Hook: admin_enqueue_scripts
     *
     * @param string $hook Sufijo de la página actual del panel.
     */
    public static function enqueue_assets( string $hook ): void {
        // Identificar las páginas del módulo por el sufijo del hook o el parámetro page.
        $library_hooks = array(
            'toplevel_page_aura-library',
            'biblioteca_page_aura-library-books',
            'biblioteca_page_aura-library-loans',
            'biblioteca_page_aura-library-reservations',
            'biblioteca_page_aura-library-reports',
            'biblioteca_page_aura-library-audit',
            'biblioteca_page_aura-library-settings',
        );

        // También detectar por el parámetro GET 'page' para mayor compatibilidad.
        $current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
        $is_library_page = strpos( $current_page, 'aura-library' ) === 0
                           || in_array( $hook, $library_hooks, true );

        if ( ! $is_library_page ) {
            return;
        }

        $version = defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0';
        $css_url = defined( 'AURA_PLUGIN_URL' ) ? AURA_PLUGIN_URL . 'assets/css/' : '';
        $js_url  = defined( 'AURA_PLUGIN_URL' ) ? AURA_PLUGIN_URL . 'assets/js/'  : '';

        // Admin styles globales del plugin
        wp_enqueue_style(
            'aura-admin-styles',
            $css_url . 'admin-styles.css',
            array(),
            $version
        );

        // dashicons (ya registrados por WP, pero se asegura)
        wp_enqueue_style( 'dashicons' );

        // Fase 6: CSS del Dashboard y Reportes de Biblioteca
        wp_enqueue_style(
            'aura-library-dashboard',
            $css_url . 'library-dashboard.css',
            array( 'aura-admin-styles' ),
            $version
        );

        // Admin scripts globales
        wp_enqueue_script(
            'aura-admin-scripts',
            $js_url . 'admin-scripts.js',
            array( 'jquery' ),
            $version,
            true
        );

        // Fase 6: Chart.js (CDN con SRI) — solo en Dashboard y Reportes
        $is_dashboard = ( $current_page === 'aura-library' || $hook === 'toplevel_page_aura-library' );
        $is_reports   = ( $current_page === 'aura-library-reports' );

        if ( $is_dashboard || $is_reports ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
        }

        // Fase 6: JS del Dashboard (solo en la página Dashboard)
        if ( $is_dashboard ) {
            wp_enqueue_script(
                'aura-library-dashboard',
                $js_url . 'library-dashboard.js',
                array( 'jquery', 'chartjs' ),
                $version,
                true
            );
            wp_localize_script( 'aura-library-dashboard', 'auraLibDash', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'i18n'     => array(
                    'loans'            => __( 'Préstamos', 'aura-business-suite' ),
                    'days_late'        => __( 'días', 'aura-business-suite' ),
                    'no_overdue'       => __( 'Sin préstamos vencidos.', 'aura-business-suite' ),
                    'no_loans'         => __( 'Sin préstamos registrados.', 'aura-business-suite' ),
                    'no_reservations'  => __( 'Sin reservas pendientes.', 'aura-business-suite' ),
                    'error'            => __( 'Error al cargar los datos.', 'aura-business-suite' ),
                ),
            ) );
        }

        // Fase 6: JS de Reportes (solo en la página de Reportes)
        if ( $is_reports ) {
            wp_enqueue_script(
                'aura-library-reports',
                $js_url . 'library-reports.js',
                array( 'jquery', 'chartjs' ),
                $version,
                true
            );
            wp_localize_script( 'aura-library-reports', 'auraLibReports', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'aura_library_nonce' ),
                'i18n'     => array(
                    'loading'          => __( 'Cargando…', 'aura-business-suite' ),
                    'error'            => __( 'Error al cargar los datos.', 'aura-business-suite' ),
                    'no_data'          => __( 'Sin datos para este período.', 'aura-business-suite' ),
                    'confirm_export'   => __( '¿Generar reporte? Esto puede tardar unos segundos.', 'aura-business-suite' ),
                ),
            ) );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER DE PÁGINAS (Fase 1 — stubs que cargan templates)
    // ─────────────────────────────────────────────────────────────

    /**
     * Render: Dashboard principal.
     */
    public static function render_dashboard(): void {
        if ( ! current_user_can( 'aura_library_access' ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta sección.', 'aura-suite' ) );
        }
        self::load_template( 'dashboard' );
    }

    /**
     * Render: Catálogo de libros.
     */
    public static function render_books(): void {
        if ( ! current_user_can( 'aura_library_view_catalog' ) ) {
            wp_die( esc_html__( 'No tienes permiso para ver el catálogo de biblioteca.', 'aura-suite' ) );
        }
        self::load_template( 'books-list' );
    }

    /**
     * Render: Préstamos.
     */
    public static function render_loans(): void {
        if ( ! current_user_can( 'aura_library_view_loans_all' ) && ! current_user_can( 'aura_library_view_loans_own' ) ) {
            wp_die( esc_html__( 'No tienes permiso para ver los préstamos.', 'aura-suite' ) );
        }
        self::load_template( 'loans-list' );
    }

    /**
     * Render: Reservas.
     */
    public static function render_reservations(): void {
        if ( ! current_user_can( 'aura_library_view_loans_all' ) ) {
            wp_die( esc_html__( 'No tienes permiso para ver las reservas.', 'aura-suite' ) );
        }
        self::load_template( 'reservations-list' );
    }

    /**
     * Render: Reportes.
     */
    public static function render_reports(): void {
        if ( ! current_user_can( 'aura_library_reports' ) ) {
            wp_die( esc_html__( 'No tienes permiso para ver los reportes de biblioteca.', 'aura-suite' ) );
        }
        self::load_template( 'reports' );
    }

    /**
     * Render: Auditoría.
     */
    public static function render_audit(): void {
        if ( ! current_user_can( 'aura_library_audit' ) ) {
            wp_die( esc_html__( 'No tienes permiso para ver la auditoría de biblioteca.', 'aura-suite' ) );
        }
        self::load_template( 'audit' );
    }

    /**
     * Render: Configuración.
     */
    public static function render_settings(): void {
        if ( ! current_user_can( 'aura_library_settings' ) ) {
            wp_die( esc_html__( 'No tienes permiso para configurar el módulo de biblioteca.', 'aura-suite' ) );
        }
        self::load_template( 'settings' );
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────

    /**
     * Cargar un template del módulo de Biblioteca.
     *
     * Ruta: templates/library/{$template}.php
     * Si el template no existe muestra un mensaje de "Próximamente".
     *
     * @param string $template Nombre del template sin extensión.
     */
    private static function load_template( string $template ): void {
        $template_path = defined( 'AURA_PLUGIN_DIR' )
            ? AURA_PLUGIN_DIR . 'templates/library/' . $template . '.php'
            : plugin_dir_path( __DIR__ . '/../../' ) . 'templates/library/' . $template . '.php';

        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Placeholder mientras se implementan las fases sucesivas.
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Biblioteca', 'aura-suite' ) . '</h1>';
            echo '<div class="notice notice-info"><p>';
            echo esc_html__( 'Esta sección está en desarrollo. Será implementada en las próximas fases.', 'aura-suite' );
            echo '</p></div></div>';
        }
    }
}
