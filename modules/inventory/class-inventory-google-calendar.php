<?php
/**
 * Integración Google Calendar — Módulo de Inventario FASE 6+
 *
 * Wrapper delgado sobre Aura_Google_Calendar (servicio global).
 * Contiene únicamente la lógica específica del módulo de inventario:
 * sincronizar/eliminar eventos de mantenimiento de equipos.
 *
 * La infraestructura de autenticación (JWT, token, api_request) y la
 * configuración (Service Account JSON, correos, recordatorios) está
 * centralizada en modules/common/class-google-calendar.php.
 *
 * Configuración en: Ajustes → Aura Business Suite → Google Calendar.
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Inventory_Google_Calendar {

    /** Nombre del calendario en Google Calendar */
    const CALENDAR_NAME = 'Mantenimientos CEM';

    /** wp_option que almacena el Calendar ID resuelto */
    const CAL_ID_OPTION = 'aura_gcal_calendar_id_resolved';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Resincronización masiva — invocada desde el tab de Inventario
        add_action( 'wp_ajax_aura_inventory_gcal_resync_all', [ __CLASS__, 'ajax_resync_all' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // ESTADO — Delega al servicio global
    // ─────────────────────────────────────────────────────────────

    public static function is_enabled(): bool {
        return Aura_Google_Calendar::is_enabled();
    }

    // ─────────────────────────────────────────────────────────────
    // SYNC — Crear / actualizar evento de mantenimiento
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea o actualiza el evento Google Calendar para la próxima fecha
     * de mantenimiento del equipo. Si no tiene fecha, elimina el evento.
     *
     * @param int $equipment_id  ID en wp_aura_inventory_equipment
     */
    public static function sync_maintenance_event( int $equipment_id ): void {
        if ( ! self::is_enabled() ) {
            return;
        }

        global $wpdb;
        $equip = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, next_maintenance_date
             FROM {$wpdb->prefix}aura_inventory_equipment
             WHERE id = %d AND deleted_at IS NULL",
            $equipment_id
        ) );

        if ( ! $equip ) {
            error_log( "[AURA GCal] sync_maintenance_event: equipo #{$equipment_id} no encontrado." );
            return;
        }

        if ( empty( $equip->next_maintenance_date ) ) {
            self::delete_maintenance_event( $equipment_id );
            return;
        }

        $cal_id = Aura_Google_Calendar::get_or_create_calendar( self::CALENDAR_NAME, self::CAL_ID_OPTION );
        if ( ! $cal_id ) {
            error_log( "[AURA GCal] sync_maintenance_event: no se pudo obtener/crear el calendar_id." );
            update_option( 'aura_gcal_last_sync_status', 'error:no_calendar_id' );
            return;
        }

        // ── Recordatorios (máximo 5 por evento en Google Calendar) ──
        $raw_days      = get_option( 'aura_gcal_reminder_days', '15,7,3,1' );
        $reminder_days = array_unique( array_filter( array_map( 'intval', explode( ',', $raw_days ) ) ) );
        sort( $reminder_days );

        $overrides = [];
        foreach ( $reminder_days as $d ) {
            if ( count( $overrides ) >= 5 ) {
                break;
            }
            $minutes = $d * 24 * 60;
            if ( $minutes > 0 && $minutes <= 40320 ) {
                $overrides[] = [ 'method' => 'email', 'minutes' => $minutes ];
                if ( count( $overrides ) < 5 ) {
                    $overrides[] = [ 'method' => 'popup', 'minutes' => $minutes ];
                }
            }
        }

        // ── Cuerpo del evento ──────────────────────────────────
        $event = [
            'summary'     => '🔧 Mantenimiento: ' . $equip->name,
            'description' => sprintf(
                "Equipo: %s\nFecha programada: %s\n\nRegistrado automáticamente por AURA Business Suite (%s).",
                $equip->name,
                date_i18n( 'd/m/Y', strtotime( $equip->next_maintenance_date ) ),
                aura_get_org_name()
            ),
            'start'     => [ 'date' => $equip->next_maintenance_date ],
            'end'       => [ 'date' => $equip->next_maintenance_date ],
            'colorId'   => '11',
            'reminders' => [
                'useDefault' => false,
                'overrides'  => $overrides,
            ],
        ];

        Aura_Google_Calendar::sync_event( $cal_id, "aura_gcal_event_{$equipment_id}", $event );
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE — Eliminar evento del calendario
    // ─────────────────────────────────────────────────────────────

    /**
     * Elimina el evento del calendario Google asociado al equipo.
     *
     * @param int $equipment_id
     */
    public static function delete_maintenance_event( int $equipment_id ): void {
        $cal_id = get_option( self::CAL_ID_OPTION, '' );
        if ( empty( $cal_id ) ) {
            return;
        }

        Aura_Google_Calendar::delete_event( $cal_id, "aura_gcal_event_{$equipment_id}" );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Resincronizar TODOS los equipos con GCal
    // ─────────────────────────────────────────────────────────────

    public static function ajax_resync_all(): void {
        check_ajax_referer( 'aura_inventory_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        if ( ! self::is_enabled() ) {
            wp_send_json_error( [ 'message' => __( 'La integración de Google Calendar no está activa.', 'aura-suite' ) ] );
        }

        delete_transient( Aura_Google_Calendar::TOKEN_TRANSIENT );

        global $wpdb;
        $equipos = $wpdb->get_results(
            "SELECT id, name, next_maintenance_date
             FROM {$wpdb->prefix}aura_inventory_equipment
             WHERE requires_maintenance = 1
               AND deleted_at IS NULL"
        );

        if ( empty( $equipos ) ) {
            wp_send_json_success( [
                'message' => __( 'No hay equipos con mantenimiento programado.', 'aura-suite' ),
                'count'   => 0,
            ] );
        }

        $ok    = 0;
        $skip  = 0;
        $names = [];

        foreach ( $equipos as $eq ) {
            if ( empty( $eq->next_maintenance_date ) ) {
                $skip++;
                continue;
            }
            self::sync_maintenance_event( (int) $eq->id );
            $ok++;
            $names[] = $eq->name;
        }

        wp_send_json_success( [
            'message' => sprintf(
                __( '✅ %1$d evento(s) sincronizado(s). %2$d equipo(s) sin fecha de mantenimiento (omitidos).', 'aura-suite' ),
                $ok, $skip
            ),
            'count'   => $ok,
            'skipped' => $skip,
            'names'   => $names,
        ] );
    }
}