<?php
/**
 * Aura Library Module — Fase 1
 * Singleton principal del módulo de Biblioteca.
 * Carga los sub-componentes del módulo y los inicializa.
 *
 * @package    Aura_Business_Suite
 * @subpackage Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Library_Module {

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
    public static function get_instance(): self {
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
    private function init(): void {
        // Fase 1: setup de BD + admin (menús + assets)
        Aura_Library_Setup::init();
        Aura_Library_Admin::init();

        // Fase 2: Catálogo de libros
        Aura_Library_Books::init();

        // Fase 3: Préstamos y devoluciones
        Aura_Library_Loans::init();

        // Fase 4: Reservas
        Aura_Library_Reservations::init();

        // Fase 5: Notificaciones y Cron
        Aura_Library_Cron::init();

        // Fase 6: Dashboard y Reportes
        Aura_Library_Reports::init();

        // Fase 8: Auditoría + Configuración + REST API
        Aura_Library_Audit::init();
        Aura_Library_Settings::init();
        Aura_Library_Api::init();
    }
}
