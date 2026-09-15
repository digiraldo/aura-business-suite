<?php
/**
 * Fase 8 — Sistema de Alertas de Mantenimiento de Vehículos
 *
 * Compara el kilometraje actual de cada vehículo con el umbral configurado
 * para determinar si se requiere mantenimiento, envía notificaciones internas
 * y correos a los usuarios con la capability `aura_vehicles_alerts`, y evita
 * alertas duplicadas durante el mismo día.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Alerts {

    // ── Tablas ────────────────────────────────────────────────────
    const TABLE_VEHICLES = 'aura_vehicles';
    const TABLE_TRIPS    = 'aura_vehicle_trips';
    const TABLE_AUDIT    = 'aura_vehicle_audit';

    // ── Opciones de configuración ─────────────────────────────────
    /** Intervalo de km entre mantenimientos (ej.: 5000). */
    const OPT_KM_INTERVAL  = 'aura_vehicles_km_before_maintenance';
    /** Si es true, no se pueden crear salidas tipo rental con mantenimiento vencido. */
    const OPT_BLOCK_RENTAL = 'aura_vehicles_block_with_pending_maint';
    /** Emails adicionales separados por coma para recibir alertas. */
    const OPT_EXTRA_EMAILS = 'aura_vehicles_alert_emails';

    const DEFAULT_KM_INTERVAL = 5000;

    // ─────────────────────────────────────────────────────────────
    // ARRANQUE
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar el cron job diario y su callback.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'schedule_cron' ) );
        add_action( 'aura_daily_vehicle_alerts', array( __CLASS__, 'check_maintenance_due' ) );
    }

    /**
     * Programar el cron si aún no está registrado.
     */
    public static function schedule_cron() {
        if ( ! wp_next_scheduled( 'aura_daily_vehicle_alerts' ) ) {
            wp_schedule_event( time(), 'daily', 'aura_daily_vehicle_alerts' );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REVISIÓN DIARIA (CRON)
    // ─────────────────────────────────────────────────────────────

    /**
     * Recorre todos los vehículos activos y envía alertas cuando corresponde.
     * Ejecutado una vez al día por WP-Cron.
     */
    public static function check_maintenance_due() {
        global $wpdb;

        $interval = (int) get_option( self::OPT_KM_INTERVAL, self::DEFAULT_KM_INTERVAL );

        $vehicles = $wpdb->get_results(
            "SELECT id, plate, brand, model, mileage, next_maintenance_km
               FROM {$wpdb->prefix}" . self::TABLE_VEHICLES . "
              WHERE active = 1 AND deleted = 0 AND mileage > 0"
        );

        if ( empty( $vehicles ) ) {
            return;
        }

        $to_alert = array();
        foreach ( $vehicles as $v ) {
            $alert_data = self::evaluate_vehicle( $v, $interval );
            if ( null !== $alert_data ) {
                $to_alert[] = $alert_data;
            }
        }

        if ( ! empty( $to_alert ) ) {
            self::send_alerts( $to_alert );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REVISIÓN INMEDIATA (check-in)
    // ─────────────────────────────────────────────────────────────

    /**
     * Verificar un vehículo concreto justo después de actualizar su kilometraje.
     * Llamado desde Aura_Vehicle_Trip_Manager::check_in().
     *
     * @param int $vehicle_id ID del vehículo.
     */
    public static function check_vehicle_after_checkin( int $vehicle_id ) {
        global $wpdb;

        $interval = (int) get_option( self::OPT_KM_INTERVAL, self::DEFAULT_KM_INTERVAL );

        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, plate, brand, model, mileage, next_maintenance_km
               FROM {$wpdb->prefix}" . self::TABLE_VEHICLES . "
              WHERE id = %d AND active = 1 AND deleted = 0",
            $vehicle_id
        ) );

        if ( ! $vehicle || ! $vehicle->mileage ) {
            return;
        }

        $alert_data = self::evaluate_vehicle( $vehicle, $interval );
        if ( null !== $alert_data ) {
            self::send_alerts( array( $alert_data ) );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // BLOQUEO DE RENTAL (validación en creación de salida)
    // ─────────────────────────────────────────────────────────────

    /**
     * Indica si un vehículo está bloqueado para salidas tipo rental
     * porque su mantenimiento está vencido y la opción de bloqueo está activa.
     *
     * @param  int $vehicle_id ID del vehículo.
     * @return bool
     */
    public static function is_blocked_for_rental( int $vehicle_id ): bool {
        if ( ! get_option( self::OPT_BLOCK_RENTAL, false ) ) {
            return false;
        }

        global $wpdb;

        $interval = (int) get_option( self::OPT_KM_INTERVAL, self::DEFAULT_KM_INTERVAL );

                $vehicle = $wpdb->get_row( $wpdb->prepare(
                        "SELECT mileage, next_maintenance_km FROM {$wpdb->prefix}" . self::TABLE_VEHICLES . "
              WHERE id = %d AND active = 1 AND deleted = 0",
            $vehicle_id
        ) );

        if ( ! $vehicle || ! $vehicle->mileage ) {
            return false;
        }

        $last_maint_km = self::get_last_maintenance_km( $vehicle_id );
        $next_maint_km = self::resolve_next_maintenance_km( $vehicle, $last_maint_km, $interval );

        return ( (int) $vehicle->mileage >= $next_maint_km );
    }

    // ─────────────────────────────────────────────────────────────
    // COMPATIBILIDAD — dashboard (sigue funcionando con la nueva lógica)
    // ─────────────────────────────────────────────────────────────

    /**
     * Retorna los vehículos que necesitan atención (para KPIs del dashboard).
     *
     * @return array
     */
    public static function get_vehicles_needing_attention(): array {
        global $wpdb;

        $interval = (int) get_option( self::OPT_KM_INTERVAL, self::DEFAULT_KM_INTERVAL );

        $vehicles = $wpdb->get_results(
            "SELECT id, plate, brand, model, mileage
               FROM {$wpdb->prefix}" . self::TABLE_VEHICLES . "
              WHERE active = 1 AND deleted = 0 AND mileage > 0"
        );

        $attention = array();
        foreach ( $vehicles as $v ) {
            $data = self::evaluate_vehicle( $v, $interval, /* skip_dup_check */ true );
            if ( null !== $data ) {
                $attention[] = $data;
            }
        }

        return $attention;
    }

    // ─────────────────────────────────────────────────────────────
    // LÓGICA INTERNA
    // ─────────────────────────────────────────────────────────────

    /**
     * Evalúa un vehículo y devuelve datos de alerta o null.
     *
     * @param  object $vehicle          Fila de la tabla aura_vehicles.
     * @param  int    $interval         Intervalo de km entre mantenimientos.
     * @param  bool   $skip_dup_check   Si true, omite la verificación de alerta duplicada.
     * @return array|null
     */
    private static function evaluate_vehicle( $vehicle, int $interval, bool $skip_dup_check = false ): ?array {
        $current_km    = (int) $vehicle->mileage;
        $last_maint_km = self::get_last_maintenance_km( (int) $vehicle->id );
        $next_maint_km = self::resolve_next_maintenance_km( $vehicle, $last_maint_km, $interval );
        $km_remaining  = $next_maint_km - $current_km;

        // Solo alerta si está dentro del umbral (≤ 10 % del intervalo restante) o ya lo superó
        $alert_threshold = max( 1, (int) round( $interval * 0.10 ) );
        if ( $km_remaining > $alert_threshold ) {
            return null;
        }

        // Evitar duplicados: no enviar la misma alerta dos veces el mismo día
        if ( ! $skip_dup_check && self::alert_sent_today( (int) $vehicle->id ) ) {
            return null;
        }

        $display_name = trim( $vehicle->brand . ' ' . $vehicle->model );

        return array(
            'id'            => (int) $vehicle->id,
            'display_name'  => $display_name ?: 'Vehículo',
            'plate'         => $vehicle->plate,
            'mileage'       => $current_km,
            'last_maint_km' => $last_maint_km,
            'next_maint_km' => $next_maint_km,
            'km_remaining'  => $km_remaining,
            'urgency'       => $km_remaining <= 0 ? 'critical' : 'warning',
        );
    }

    /**
     * Resolver el próximo kilometraje de mantenimiento.
     * Usa el valor específico guardado en el vehículo cuando exista; de lo contrario,
     * cae al intervalo global basado en el último mantenimiento registrado.
     */
    private static function resolve_next_maintenance_km( $vehicle, int $last_maint_km, int $interval ): int {
        $custom_next_km = isset( $vehicle->next_maintenance_km ) ? (int) $vehicle->next_maintenance_km : 0;

        if ( $custom_next_km > 0 ) {
            return $custom_next_km;
        }

        return $last_maint_km + $interval;
    }

    /**
     * Obtener kilometraje del último mantenimiento completado del vehículo.
     *
     * @param  int $vehicle_id
     * @return int 0 si no hay mantenimiento registrado.
     */
    private static function get_last_maintenance_km( int $vehicle_id ): int {
        global $wpdb;

        $km = $wpdb->get_var( $wpdb->prepare(
            "SELECT return_odometer
               FROM {$wpdb->prefix}" . self::TABLE_TRIPS . "
              WHERE vehicle_id = %d
                AND trip_type  = 'maintenance'
                AND status     = 'returned'
                AND deleted    = 0
              ORDER BY return_datetime DESC
              LIMIT 1",
            $vehicle_id
        ) );

        return (int) ( $km ?? 0 );
    }

    /**
     * Verificar si ya se envió una alerta hoy para este vehículo.
     *
     * @param  int $vehicle_id
     * @return bool
     */
    private static function alert_sent_today( int $vehicle_id ): bool {
        global $wpdb;

        $today = current_time( 'Y-m-d' );

        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT id
               FROM {$wpdb->prefix}" . self::TABLE_AUDIT . "
              WHERE operation   = 'vehicle_maintenance_alert'
                AND entity_type = 'vehicle'
                AND entity_id   = %d
                AND DATE(created_at) = %s
              LIMIT 1",
            $vehicle_id,
            $today
        ) );

        return ! empty( $found );
    }

    // ─────────────────────────────────────────────────────────────
    // ENVÍO
    // ─────────────────────────────────────────────────────────────

    /**
     * Enviar alertas a todos los destinatarios configurados.
     *
     * @param array $vehicles Lista de arrays generados por evaluate_vehicle().
     */
    public static function send_alerts( array $vehicles ) {
        if ( empty( $vehicles ) ) {
            return;
        }

        // Usuarios internos con capability
        $recipients = get_users( array( 'capability' => 'aura_vehicles_alerts' ) );

        // Emails adicionales de la configuración
        $extra_emails = self::get_extra_emails();

        foreach ( $vehicles as $vehicle ) {
            // Registrar en auditoría
            Aura_Vehicle_Audit_Manager::log(
                'vehicle_maintenance_alert',
                'vehicle',
                $vehicle['id'],
                array(
                    'km_remaining'  => $vehicle['km_remaining'],
                    'next_maint_km' => $vehicle['next_maint_km'],
                    'urgency'       => $vehicle['urgency'],
                )
            );

            // Notificación interna + email para cada usuario
            foreach ( $recipients as $user ) {
                self::create_internal_notification( $user->ID, $vehicle );
                self::dispatch_email( $user->user_email, $vehicle );
            }

            // Solo email para destinatarios externos
            foreach ( $extra_emails as $email ) {
                self::dispatch_email( $email, $vehicle );
            }
        }
    }

    /**
     * Crear notificación interna (user_meta) para un usuario.
     */
    private static function create_internal_notification( int $user_id, array $v ) {
        $label   = 'critical' === $v['urgency'] ? '🔴 CRÍTICO' : '🟡 Advertencia';
        $km_msg  = $v['km_remaining'] <= 0
            ? 'ha <strong>superado</strong> el intervalo de mantenimiento en ' . number_format( abs( $v['km_remaining'] ) ) . ' km'
            : 'tiene solo <strong>' . number_format( $v['km_remaining'] ) . ' km</strong> restantes para mantenimiento';

        $message = sprintf(
            '%s — %s (%s) %s. Km actuales: %s | Próximo mant.: %s km.',
            $label,
            esc_html( $v['display_name'] ),
            esc_html( $v['plate'] ),
            $km_msg,
            number_format( $v['mileage'] ),
            number_format( $v['next_maint_km'] )
        );

        Aura_Notifications::create_notification(
            $user_id,
            $message,
            'critical' === $v['urgency'] ? 'error' : 'warning',
            'vehicles'
        );
    }

    /**
     * Enviar email de alerta de mantenimiento.
     */
    private static function dispatch_email( string $email, array $v ) {
        if ( ! is_email( $email ) ) {
            return;
        }

        $site_name = function_exists( 'aura_get_org_name' ) ? aura_get_org_name() : get_bloginfo( 'name' );
        $color     = 'critical' === $v['urgency'] ? '#dc2626' : '#d97706';
        $label     = 'critical' === $v['urgency'] ? '🔴 CRÍTICO' : '🟡 Atención';

        $km_msg = $v['km_remaining'] <= 0
            ? '<strong style="color:#dc2626;">Ha superado el intervalo de mantenimiento en ' . number_format( abs( $v['km_remaining'] ) ) . ' km</strong>'
            : 'Quedan <strong>' . number_format( $v['km_remaining'] ) . ' km</strong> para el próximo mantenimiento programado.';

        $subject = sprintf(
            '[%s] %s — Mantenimiento requerido · %s',
            $site_name,
            $label,
            $v['plate']
        );

        $rows = array(
            'Vehículo'               => esc_html( $v['display_name'] ),
            'Placa'                  => esc_html( $v['plate'] ),
            'Km actuales'            => number_format( $v['mileage'] ) . ' km',
            'Último mantenimiento'   => $v['last_maint_km'] ? number_format( $v['last_maint_km'] ) . ' km' : 'Sin registro',
            'Próximo mantenimiento'  => number_format( $v['next_maint_km'] ) . ' km',
            'Km restantes'           => number_format( $v['km_remaining'] ) . ' km',
        );

        $rows_html = '';
        foreach ( $rows as $label_row => $val ) {
            $rows_html .= '<tr>'
                . '<td style="padding:9px 14px;border-top:1px solid #e5e7eb;font-size:13px;color:#374151;">' . esc_html( $label_row ) . '</td>'
                . '<td style="padding:9px 14px;border-top:1px solid #e5e7eb;font-size:13px;font-weight:600;">' . $val . '</td>'
                . '</tr>';
        }

        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">'
            . '<div style="max-width:600px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">'
            . '<div style="background:' . $color . ';padding:20px 24px;">'
            . '<h2 style="margin:0;color:#fff;font-size:18px;">' . esc_html( $site_name ) . ' — Alerta de Mantenimiento</h2>'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<p style="font-size:15px;margin:0 0 16px;">' . $km_msg . '</p>'
            . '<table style="width:100%;border-collapse:collapse;border-radius:6px;overflow:hidden;border:1px solid #e5e7eb;">'
            . '<thead><tr style="background:#f9fafb;">'
            . '<th style="padding:9px 14px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Campo</th>'
            . '<th style="padding:9px 14px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Valor</th>'
            . '</tr></thead><tbody>' . $rows_html . '</tbody></table>'
            . '<p style="margin:20px 0 0;">'
            . '<a href="' . esc_url( admin_url( 'admin.php?page=aura-vehicles' ) ) . '" style="display:inline-block;background:#1e3a5f;color:#fff;padding:10px 22px;border-radius:4px;text-decoration:none;font-size:14px;">Ver Flota en AURA</a>'
            . '</p>'
            . '<hr style="margin:24px 0;border:none;border-top:1px solid #e5e7eb;">'
            . '<p style="font-size:12px;color:#9ca3af;margin:0;">Notificación automática generada el ' . current_time( 'd/m/Y \a \l\a\s H:i' ) . ' — Aura Business Suite</p>'
            . '</div></div>'
            . '</body></html>';

        add_filter( 'wp_mail_content_type', static function() { return 'text/html'; } );
        wp_mail( $email, $subject, $body );
    }

    /**
     * Parsear la lista de emails adicionales desde la opción de configuración.
     *
     * @return string[]
     */
    private static function get_extra_emails(): array {
        $raw = get_option( self::OPT_EXTRA_EMAILS, '' );
        if ( ! $raw ) {
            return array();
        }

        $emails = array();
        foreach ( preg_split( '/[\s,;]+/', $raw ) as $e ) {
            $e = trim( $e );
            if ( is_email( $e ) ) {
                $emails[] = $e;
            }
        }

        return $emails;
    }
}
