<?php
/**
 * Aura Vehicle Admin — Fase 1
 * Registra el menú del módulo de vehículos bajo Aura Suite
 * y gestiona el encolado de sus assets de administración.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Admin {

    /** Slug del menú principal del módulo de Vehículos */
    const MENU_SLUG = 'aura-vehicles';

    // ─────────────────────────────────────────────────────────────
    // INICIALIZACIÓN
    // ─────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'admin_menu',             array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts',  array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_aura_vehicle_crop_photo', array( 'Aura_Vehicle_Manager', 'ajax_crop_vehicle_photo' ) );
        // Brecha #8 — otorgar acceso al menú a cualquier usuario con alguna capability de vehículos o membresía de área.
        add_filter( 'user_has_cap', array( __CLASS__, 'grant_vehicles_access' ), 10, 3 );
    }

    /**
     * Brecha #8 — capability virtual 'aura_vehicles_access'.
     * Se concede a cualquier usuario que tenga al menos una capability del módulo
     * o que pertenezca a alguna área. Se usa únicamente para controlar la
     * visibilidad del menú de administración, sin afectar el CBAC de los endpoints REST.
     *
     * @param array $allcaps  Capabilities actuales del usuario.
     * @param array $caps     Capabilities requeridas para la comprobación.
     * @param array $args     Argumentos extra pasados por WP (cap, user_id, object_id...).
     * @return array
     */
    public static function grant_vehicles_access( array $allcaps, array $caps, array $args ): array {
        if ( ! in_array( 'aura_vehicles_access', $caps, true ) ) {
            return $allcaps;
        }

        // Si ya la tiene (p. ej. admin), salir rápido.
        if ( ! empty( $allcaps['aura_vehicles_access'] ) ) {
            return $allcaps;
        }

        // Cualquier capability específica del módulo otorga acceso al menú.
        $vehicle_caps = array(
            'aura_vehicles_create', 'aura_vehicles_edit', 'aura_vehicles_delete',
            'aura_vehicles_exits_create', 'aura_vehicles_exits_edit_own',
            'aura_vehicles_exits_edit_all', 'aura_vehicles_km_update',
            'aura_vehicles_view_all', 'aura_vehicles_reports', 'aura_vehicles_alerts',
            'aura_vehicles_audit', 'aura_vehicles_settings',
        );
        foreach ( $vehicle_caps as $cap ) {
            if ( ! empty( $allcaps[ $cap ] ) ) {
                $allcaps['aura_vehicles_access'] = true;
                return $allcaps;
            }
        }

        // También conceder si el usuario pertenece a al menos un área.
        $user_id = isset( $args[1] ) ? (int) $args[1] : get_current_user_id();
        if ( $user_id > 0 ) {
            global $wpdb;
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aura_area_users WHERE user_id = %d",
                $user_id
            ) );
            if ( $count > 0 ) {
                $allcaps['aura_vehicles_access'] = true;
            }
        }

        return $allcaps;
    }

    // ─────────────────────────────────────────────────────────────
    // MENÚS
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar el menú principal del módulo y sus submenús.
     * El módulo aparece como menú de nivel superior, después de Formularios (posición 4.9).
     */
    public static function register_menus() {
        // Menú principal (top-level)
        add_menu_page(
            __( 'Vehículos — AURA', 'aura-suite' ),
            __( 'Vehículos', 'aura-suite' ),
            'aura_vehicles_access',
            self::MENU_SLUG,
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-car',
            3.6
        );

        // Submenú duplicado del dashboard (estándar WP)
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'aura-suite' ),
            __( 'Dashboard', 'aura-suite' ),
            'aura_vehicles_access',
            self::MENU_SLUG,
            array( __CLASS__, 'page_dashboard' )
        );

        // Salidas (registro de viajes / trips)
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Salidas', 'aura-suite' ),
            __( 'Salidas', 'aura-suite' ),
            'aura_vehicles_exits_create',
            'aura-vehicles-trips',
            array( __CLASS__, 'page_trips' )
        );

        // Vehículos (listado de flota)
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Vehículos', 'aura-suite' ),
            __( 'Vehículos', 'aura-suite' ),
            'aura_vehicles_access',
            'aura-vehicles-list',
            array( __CLASS__, 'page_vehicles' )
        );

        // Reportes
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Reportes', 'aura-suite' ),
            __( 'Reportes', 'aura-suite' ),
            'aura_vehicles_reports',
            'aura-vehicles-reports',
            array( __CLASS__, 'page_reports' )
        );

        // Catálogos configurables
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Catálogos', 'aura-suite' ),
            __( 'Catálogos', 'aura-suite' ),
            'aura_vehicles_edit',
            'aura-vehicles-catalogs',
            array( __CLASS__, 'page_catalogs' )
        );

        // Auditoría
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Auditoría', 'aura-suite' ),
            __( 'Auditoría', 'aura-suite' ),
            'aura_vehicles_audit',
            'aura-vehicles-audit',
            array( __CLASS__, 'page_audit' )
        );

        // Configuración del módulo
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Configuración', 'aura-suite' ),
            __( 'Configuración', 'aura-suite' ),
            'aura_vehicles_settings',
            'aura-vehicles-settings',
            array( __CLASS__, 'page_settings' )
        );
    }

    // ─────────────────────────────────────────────────────────────
    // ASSETS
    // ─────────────────────────────────────────────────────────────

    /**
     * Encolar CSS y JS del módulo únicamente en las páginas del módulo.
     *
     * NOTA IMPORTANTE: No usamos el parámetro $hook_suffix porque WordPress lo
     * genera como `sanitize_title(título_menú)_page_slug`, y para menús con
     * títulos no-ASCII (ej: "Vehículos" → "vehiculos") el valor resultante
     * difiere del slug. En su lugar usamos $_GET['page'] que siempre contiene
     * el slug exacto registrado en add_menu_page / add_submenu_page.
     *
     * @param string $hook Sufijo de la pantalla actual (no usado directamente).
     */
    public static function enqueue_assets( $hook ) {
        // Determinar la página actual por su slug (seguro: sanitize_key preserva guiones).
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $vehicle_slugs = array(
            'aura-vehicles',
            'aura-vehicles-trips',
            'aura-vehicles-list',
            'aura-vehicles-reports',
            'aura-vehicles-catalogs',
            'aura-vehicles-audit',
            'aura-vehicles-settings',
        );

        if ( ! in_array( $page, $vehicle_slugs, true ) ) {
            return;
        }

        $module_url = AURA_PLUGIN_URL . 'modules/vehicles/';
        $version    = defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.0.0';

        // CSS base del módulo (modales, formularios, tabla, badges, etc.)
        wp_enqueue_style(
            'aura-vehicle-admin',
            $module_url . 'assets/css/vehicle-admin.css',
            array(),
            $version
        );

        // JS base del módulo
        wp_enqueue_script(
            'aura-vehicle-admin',
            $module_url . 'assets/js/vehicle-admin.js',
            array( 'jquery' ),
            $version,
            true
        );

        // Pasar configuración global al JS (nonce REST + base URL de la API)
        wp_localize_script(
            'aura-vehicle-admin',
            'auraVehiclesConfig',
            array(
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'apiBase'  => rest_url( 'aura/v1/' ),
                'siteUrl'  => get_site_url(),
                'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                'vehNonce' => wp_create_nonce( 'aura_vehicles_nonce' ),
            )
        );

        // ── Fase 4: Catálogos ─────────────────────────────────────
        if ( 'aura-vehicles-catalogs' === $page ) {
            wp_enqueue_script(
                'sortablejs',
                'https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js',
                array(),
                '1.15.3',
                true
            );
            wp_enqueue_script(
                'aura-vehicle-catalogs',
                $module_url . 'assets/js/vehicle-catalogs.js',
                array( 'jquery', 'aura-vehicle-admin', 'sortablejs' ),
                $version,
                true
            );
        }

        // ── Fase 5: Dashboard ─────────────────────────────────────
        if ( 'aura-vehicles' === $page ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
                array(),
                '4.4.4',
                true
            );
            wp_enqueue_script(
                'aura-vehicle-dashboard',
                $module_url . 'assets/js/vehicle-dashboard.js',
                array( 'jquery', 'aura-vehicle-admin', 'chartjs' ),
                $version,
                true
            );
        }

        // ── Fase 6: Reportes ──────────────────────────────────────
        if ( 'aura-vehicles-reports' === $page ) {
            wp_enqueue_style(
                'datatables-css',
                'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
                array(),
                '2.2.2'
            );
            wp_enqueue_style(
                'datatables-responsive-css',
                'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
                array( 'datatables-css' ),
                '3.0.4'
            );
            wp_enqueue_script(
                'datatables-js',
                'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
                array( 'jquery' ),
                '2.2.2',
                true
            );
            wp_enqueue_script(
                'datatables-responsive-js',
                'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
                array( 'datatables-js' ),
                '3.0.4',
                true
            );
            wp_enqueue_script(
                'aura-vehicle-reports',
                $module_url . 'assets/js/vehicle-reports.js',
                array( 'jquery', 'aura-vehicle-admin', 'datatables-responsive-js' ),
                $version,
                true
            );
        }

        // ── Fase 7: Auditoría ─────────────────────────────────────
        if ( 'aura-vehicles-audit' === $page ) {
            wp_enqueue_style(
                'datatables-css',
                'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
                array(),
                '2.2.2'
            );
            wp_enqueue_style(
                'datatables-responsive-css',
                'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
                array( 'datatables-css' ),
                '3.0.4'
            );
            wp_enqueue_script(
                'datatables-js',
                'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
                array( 'jquery' ),
                '2.2.2',
                true
            );
            wp_enqueue_script(
                'datatables-responsive-js',
                'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
                array( 'datatables-js' ),
                '3.0.4',
                true
            );
            wp_enqueue_script(
                'aura-vehicle-audit',
                $module_url . 'assets/js/vehicle-audit.js',
                array( 'jquery', 'aura-vehicle-admin', 'datatables-responsive-js' ),
                $version,
                true
            );
        }

        // ── Fase 9: Configuración ─────────────────────────────────
        if ( 'aura-vehicles-settings' === $page ) {
            wp_enqueue_script(
                'aura-vehicle-settings',
                $module_url . 'assets/js/vehicle-settings.js',
                array( 'jquery', 'aura-vehicle-admin' ),
                $version,
                true
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CALLBACKS DE PÁGINA
    // ─────────────────────────────────────────────────────────────

    public static function page_dashboard() {
        include AURA_PLUGIN_DIR . 'modules/vehicles/admin/views/page-dashboard.php';
    }

    public static function page_trips() {
        include AURA_PLUGIN_DIR . 'modules/vehicles/admin/views/page-trips.php';
    }

    public static function page_vehicles() {
        include AURA_PLUGIN_DIR . 'modules/vehicles/admin/views/page-vehicles.php';
    }

    public static function page_reports() {
        include AURA_PLUGIN_DIR . 'modules/vehicles/admin/views/page-reports.php';
    }

    public static function page_catalogs() {
        include AURA_PLUGIN_DIR . 'modules/vehicles/admin/views/page-catalogs.php';
    }

    public static function page_audit() {
        include AURA_PLUGIN_DIR . 'modules/vehicles/admin/views/page-audit.php';
    }

    public static function page_settings() {
        include AURA_PLUGIN_DIR . 'modules/vehicles/admin/views/page-settings.php';
    }
}
