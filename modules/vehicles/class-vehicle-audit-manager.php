<?php
/**
 * Aura Vehicle Audit Manager
 * Registra todas las operaciones del módulo de vehículos en wp_aura_vehicle_audit.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Audit_Manager {

    // ─────────────────────────────────────────────────────────────
    // LOGGING
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar una operación en el log de auditoría.
     *
     * @param string     $operation   Nombre de la operación (p.ej. 'vehicle_created').
     * @param string     $entity_type Tipo de entidad (p.ej. 'vehicle', 'trip').
     * @param int        $entity_id   ID del objeto afectado (0 si no aplica).
     * @param array|null $details     Datos adicionales para guardar como JSON.
     */
    public static function log(
        string $operation,
        string $entity_type = '',
        int    $entity_id   = 0,
        $details            = null
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'aura_vehicle_audit',
            array(
                'operation'   => $operation,
                'entity_type' => $entity_type ?: null,
                'entity_id'   => $entity_id   ?: null,
                'user_id'     => get_current_user_id() ?: null,
                'ip_address'  => self::get_user_ip(),
                'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] )
                                 ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 300 )
                                 : null,
                'details'     => $details !== null
                                 ? wp_json_encode( $details, JSON_UNESCAPED_UNICODE )
                                 : null,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener la IP real del usuario evitando proxies.
     */
    private static function get_user_ip(): string {
        $keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );
        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = filter_var(
                    strtok( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ), ',' ),
                    FILTER_VALIDATE_IP
                );
                if ( $ip ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
