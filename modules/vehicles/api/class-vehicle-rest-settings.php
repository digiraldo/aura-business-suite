<?php
/**
 * REST API — Configuración del Módulo de Vehículos (Fase 9)
 *
 * Endpoints:
 *   GET  /aura/v1/vehicles/settings — recupera todas las opciones
 *   POST /aura/v1/vehicles/settings — guarda las opciones (requiere manage_options)
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Rest_Settings {

    // ------------------------------------------------------------------
    // Mapa de opciones: clave WP → tipo + default + rango permitido
    // ------------------------------------------------------------------

    private static $options_map = array(
        'aura_vehicles_module_name'             => array( 'type' => 'string',  'default' => '' ),
        'aura_vehicles_rate_per_km'             => array( 'type' => 'float',   'default' => 0.00 ),
        'aura_vehicles_km_before_maintenance'   => array( 'type' => 'int',     'default' => 5000, 'min' => 100 ),
        'aura_vehicles_block_with_pending_maint'=> array( 'type' => 'bool',    'default' => false ),
        'aura_vehicles_audit_retention_days'    => array( 'type' => 'int',     'default' => 365, 'min' => 30, 'max' => 3650 ),
        'aura_vehicles_alert_emails'            => array( 'type' => 'string',  'default' => '' ),
        // Fase 10: Integración Financial
        'aura_vehicles_fin_integration_enabled' => array( 'type' => 'bool',    'default' => false ),
        'aura_vehicles_fin_income_category_id'  => array( 'type' => 'int',     'default' => 0 ),
        'aura_vehicles_fin_expense_category_id' => array( 'type' => 'int',     'default' => 0 ),
        'aura_vehicles_fin_sync_trip_expenses'  => array( 'type' => 'bool',    'default' => false ),
    );

    // ------------------------------------------------------------------
    // Registro de rutas
    // ------------------------------------------------------------------

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        $ns = 'aura/v1';

        register_rest_route( $ns, '/vehicles/settings', array(
            // GET — leer configuración actual
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_settings' ),
                'permission_callback' => array( __CLASS__, 'can_manage' ),
            ),
            // POST — guardar configuración
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'save_settings' ),
                'permission_callback' => array( __CLASS__, 'can_manage' ),
                'args'                => self::save_args(),
            ),
        ) );

        // GET — listar categorías del módulo Financial (para select en UI)
        register_rest_route( $ns, '/vehicles/settings/financial-categories', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'get_financial_categories' ),
            'permission_callback' => array( __CLASS__, 'can_manage' ),
            'args'                => array(
                'type' => array(
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        ) );

        // POST — ejecutar revisión de alertas de mantenimiento manualmente
        register_rest_route( $ns, '/vehicles/settings/run-alerts', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'run_alerts_now' ),
            'permission_callback' => array( __CLASS__, 'can_manage' ),
        ) );
    }

    // ------------------------------------------------------------------
    // Permiso
    // ------------------------------------------------------------------

    public static function can_manage(): bool {
        return current_user_can( 'aura_vehicles_settings' )
            || current_user_can( 'manage_options' );
    }

    // ------------------------------------------------------------------
    // GET /aura/v1/vehicles/settings
    // ------------------------------------------------------------------

    public static function get_settings( WP_REST_Request $request ): WP_REST_Response {
        $data = array();

        foreach ( self::$options_map as $key => $meta ) {
            $raw = get_option( $key, $meta['default'] );
            $data[ $key ] = self::cast( $raw, $meta['type'], $meta['default'] );
        }

        return new WP_REST_Response( $data, 200 );
    }

    // ------------------------------------------------------------------
    // POST /aura/v1/vehicles/settings
    // ------------------------------------------------------------------

    public static function save_settings( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $changes = array();

        foreach ( self::$options_map as $key => $meta ) {
            // Solo procesar claves presentes en el body
            if ( ! $request->has_param( $key ) ) {
                continue;
            }

            $raw   = $request->get_param( $key );
            $value = self::cast( $raw, $meta['type'], $meta['default'] );

            // Rango numérico opcional
            if ( 'int' === $meta['type'] || 'float' === $meta['type'] ) {
                if ( isset( $meta['min'] ) && $value < $meta['min'] ) {
                    $value = $meta['min'];
                }
                if ( isset( $meta['max'] ) && $value > $meta['max'] ) {
                    $value = $meta['max'];
                }
            }

            $old = get_option( $key, $meta['default'] );

            update_option( $key, $value );

            if ( (string) $old !== (string) $value ) {
                $changes[ $key ] = array( 'from' => $old, 'to' => $value );
            }
        }

        // Registrar en auditoría si hubo cambios
        if ( ! empty( $changes ) && class_exists( 'Aura_Vehicle_Audit_Manager' ) ) {
            Aura_Vehicle_Audit_Manager::log(
                0,
                'settings_updated',
                sprintf(
                    /* translators: %s: lista de claves cambiadas */
                    __( 'Configuración actualizada. Campos modificados: %s', 'aura-suite' ),
                    implode( ', ', array_keys( $changes ) )
                )
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Configuración guardada correctamente.', 'aura-suite' ),
                'changes' => count( $changes ),
            ),
            200
        );
    }

    // ------------------------------------------------------------------
    // GET /aura/v1/vehicles/settings/financial-categories
    // ------------------------------------------------------------------

    public static function get_financial_categories( WP_REST_Request $request ): WP_REST_Response {
        if ( ! class_exists( 'Aura_Vehicle_Financial_Bridge' ) ) {
            return new WP_REST_Response( array(), 200 );
        }

        $type = $request->get_param( 'type' ) ?: '';
        $cats = Aura_Vehicle_Financial_Bridge::get_categories( $type );

        $result = array();
        foreach ( $cats as $id => $name ) {
            $result[] = array( 'id' => $id, 'name' => $name );
        }

        return new WP_REST_Response( $result, 200 );
    }

    // ------------------------------------------------------------------
    // POST /aura/v1/vehicles/settings/run-alerts
    // ------------------------------------------------------------------

    public static function run_alerts_now( WP_REST_Request $request ): WP_REST_Response {
        if ( ! class_exists( 'Aura_Vehicle_Alerts' ) ) {
            return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Módulo de alertas no disponible.', 'aura-suite' ) ), 500 );
        }

        $sent = Aura_Vehicle_Alerts::check_maintenance_due();

        return new WP_REST_Response(
            array(
                'success' => true,
                'sent'    => $sent,
                'message' => sprintf(
                    /* translators: %d: número de alertas enviadas */
                    _n( 'Se envió %d alerta.', 'Se enviaron %d alertas.', $sent, 'aura-suite' ),
                    $sent
                ),
            ),
            200
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Convierte un valor crudo al tipo esperado.
     */
    private static function cast( $value, string $type, $default ) {
        switch ( $type ) {
            case 'int':
                return is_numeric( $value ) ? (int) $value : (int) $default;
            case 'float':
                return is_numeric( $value ) ? (float) $value : (float) $default;
            case 'bool':
                if ( is_bool( $value ) ) {
                    return $value;
                }
                return in_array( $value, array( '1', 'true', 1, true ), true );
            case 'string':
            default:
                return is_string( $value ) ? sanitize_textarea_field( $value ) : (string) $default;
        }
    }

    /**
     * Argumentos de sanitización para la ruta POST.
     */
    private static function save_args(): array {
        return array(
            'aura_vehicles_module_name'              => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
            'aura_vehicles_rate_per_km'              => array( 'type' => 'number',  'sanitize_callback' => 'floatval' ),
            'aura_vehicles_km_before_maintenance'    => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'aura_vehicles_block_with_pending_maint' => array( 'type' => 'boolean' ),
            'aura_vehicles_audit_retention_days'     => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'aura_vehicles_alert_emails'             => array( 'type' => 'string',  'sanitize_callback' => 'sanitize_textarea_field' ),
            // Fase 10: Integración Financial
            'aura_vehicles_fin_integration_enabled'  => array( 'type' => 'boolean' ),
            'aura_vehicles_fin_income_category_id'   => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'aura_vehicles_fin_expense_category_id'  => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            'aura_vehicles_fin_sync_trip_expenses'   => array( 'type' => 'boolean' ),
        );
    }
}
