<?php
/**
 * Aura Vehicle Module — Fase 1
 * Singleton principal del módulo de vehículos.
 * Carga los sub-componentes del módulo y registra assets.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Module {

    /** @var self|null Instancia única */
    private static $instance = null;

    // ─────────────────────────────────────────────────────────────
    // SINGLETON
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener o crear la instancia única del módulo.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado — usar get_instance().
     */
    private function __construct() {
        $this->init();
    }

    // ─────────────────────────────────────────────────────────────
    // ARRANQUE
    // ─────────────────────────────────────────────────────────────

    /**
     * Enganchar todos los sub-componentes del módulo.
     */
    private function init() {
        // Fase 1: setup de BD + admin (menús + assets).
        Aura_Vehicle_Setup::init();
        Aura_Vehicle_Admin::init();

        // Los stubs de alertas y reportes se mantienen activos para
        // no romper hooks existentes; se adaptarán en fases 6 y 8.
        Aura_Vehicle_Alerts::init();

        // Fase 2: REST de vehículos
        Aura_Vehicle_Rest_Vehicles::init();

        // Fase 3: REST de salidas
        Aura_Vehicle_Rest_Trips::init();

        // Fase 4: REST de catálogos
        Aura_Vehicle_Rest_Catalogs::init();

        // Fase 5: REST de estadísticas / dashboard
        Aura_Vehicle_Rest_Stats::init();

        // Fase 6: REST de reportes y exportación
        Aura_Vehicle_Rest_Reports::init();

        // Fase 7: REST de auditoría
        Aura_Vehicle_Rest_Audit::init();

        // Fase 9: REST de configuración
        Aura_Vehicle_Rest_Settings::init();

        // Fase QR: endpoints de generación/validación de QR + shortcode + query_var
        Aura_Vehicle_Rest_Qr::init();

        // AJAX login para página QR (público y logueado)
        add_action( 'wp_ajax_nopriv_aura_qr_login', array( 'Aura_Vehicle_Rest_Qr', 'ajax_qr_login' ) );
        add_action( 'wp_ajax_aura_qr_login',        array( 'Aura_Vehicle_Rest_Qr', 'ajax_qr_login' ) );

        // Fase 10: Integración con módulo Financial (condicional)
        Aura_Vehicle_Financial_Bridge::init();
    }
}
