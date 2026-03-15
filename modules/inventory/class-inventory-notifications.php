<?php
/**
 * Sistema de Alertas y Notificaciones del Módulo de Inventario — FASE 6
 *
 * Responsabilidades:
 *  - Cron diario de alertas de mantenimiento (6 AM): 15/7/3/1 días antes + vencido
 *  - Cron diario de alertas de préstamos vencidos (8 AM)
 *  - Cron semanal: resumen de estado del inventario (lunes 7 AM)
 *  - Notificaciones WhatsApp a prestatarios externos (sin cuenta WP)
 *  - Fallback a email cuando WhatsApp no está configurado
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Notifications {

    const NONCE = 'aura_inventory_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT & CRON SCHEDULING
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Programar crons la primera vez que se ejecuta WordPress
        add_action( 'wp', [ __CLASS__, 'schedule_cron_jobs' ] );

        // Handlers de cron
        add_action( 'aura_inventory_check_maintenance_daily', [ __CLASS__, 'run_maintenance_check' ] );
        add_action( 'aura_inventory_loan_alerts_daily',       [ __CLASS__, 'run_loan_alerts' ] );
        add_action( 'aura_inventory_weekly_summary',          [ __CLASS__, 'send_weekly_summary' ] );

        // AJAX admin: disparar alertas manualmente para pruebas
        add_action( 'wp_ajax_aura_inventory_test_alerts',       [ __CLASS__, 'ajax_test_alerts'          ] );
        add_action( 'wp_ajax_aura_inventory_send_test_email',   [ __CLASS__, 'ajax_send_test_email'      ] );
        add_action( 'wp_ajax_aura_inventory_save_whatsapp',     [ __CLASS__, 'ajax_save_whatsapp'        ] );
        add_action( 'wp_ajax_aura_inventory_test_whatsapp',     [ __CLASS__, 'ajax_test_whatsapp'        ] );
    }

    /**
     * Programar los tres cron jobs si no están ya registrados.
     * Se ejecuta en el hook 'wp' (después de que WordPress se carga).
     */
    public static function schedule_cron_jobs(): void {
        // Cron 1: alertas de mantenimiento — 6:00 AM diario
        if ( ! wp_next_scheduled( 'aura_inventory_check_maintenance_daily' ) ) {
            $first_run = strtotime( 'today 06:00:00' );
            if ( $first_run < time() ) {
                $first_run = strtotime( 'tomorrow 06:00:00' );
            }
            wp_schedule_event( $first_run, 'daily', 'aura_inventory_check_maintenance_daily' );
        }

        // Cron 2: alertas de préstamos vencidos — 8:00 AM diario
        if ( ! wp_next_scheduled( 'aura_inventory_loan_alerts_daily' ) ) {
            $first_run = strtotime( 'today 08:00:00' );
            if ( $first_run < time() ) {
                $first_run = strtotime( 'tomorrow 08:00:00' );
            }
            wp_schedule_event( $first_run, 'daily', 'aura_inventory_loan_alerts_daily' );
        }

        // Cron 3: resumen semanal — lunes 7:00 AM
        if ( ! wp_next_scheduled( 'aura_inventory_weekly_summary' ) ) {
            // Calcular próximo lunes a las 7 AM
            $next_monday = strtotime( 'next monday 07:00:00' );
            wp_schedule_event( $next_monday, 'weekly', 'aura_inventory_weekly_summary' );
        }
    }

    /**
     * Dar de baja los tres cron jobs.
     * Llamado desde plugin deactivate().
     */
    public static function clear_cron_jobs(): void {
        wp_clear_scheduled_hook( 'aura_inventory_check_maintenance_daily' );
        wp_clear_scheduled_hook( 'aura_inventory_loan_alerts_daily' );
        wp_clear_scheduled_hook( 'aura_inventory_weekly_summary' );
    }

    // ─────────────────────────────────────────────────────────────
    // CRON 1 — Alertas de mantenimiento
    // ─────────────────────────────────────────────────────────────

    /**
     * Revisar equipos con mantenimiento próximo o vencido y enviar alertas.
     * Se ejecuta a las 6:00 AM diariamente.
     */
    public static function run_maintenance_check(): void {
        global $wpdb;
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $today   = current_time( 'Y-m-d' );

        // Equipos que requieren mantenimiento y tienen fecha de próximo mantenimiento
        $equipments = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, next_maintenance_date, responsible_user_id, area_id
             FROM {$t_equip}
             WHERE requires_maintenance = 1
               AND next_maintenance_date IS NOT NULL
               AND deleted_at IS NULL
               AND status NOT IN ('retired','repair')
             ORDER BY next_maintenance_date ASC"
        ) );

        if ( empty( $equipments ) ) {
            return;
        }

        foreach ( $equipments as $equip ) {
            $days_until = (int) floor(
                ( strtotime( $equip->next_maintenance_date ) - strtotime( $today ) ) / DAY_IN_SECONDS
            );

            // Determinar nivel de alerta
            if ( $days_until <= 0 ) {
                // Vencido o vence hoy: alerta diaria (sin deduplicación mensual)
                self::send_maintenance_alert( $equip, 'overdue' );
            } elseif ( $days_until === 1 ) {
                self::send_maintenance_alert( $equip, 'final1' );
            } elseif ( $days_until === 3 ) {
                self::send_maintenance_alert( $equip, 'urgent3' );
            } elseif ( $days_until === 7 ) {
                self::send_maintenance_alert( $equip, 'reminder7' );
            } elseif ( $days_until === 15 ) {
                self::send_maintenance_alert( $equip, 'reminder15' );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CRON 2 — Alertas de préstamos vencidos
    // ─────────────────────────────────────────────────────────────

    /**
     * Buscar préstamos vencidos (no devueltos después de expected_return_date)
     * y notificar al responsable + al prestatario externo por WhatsApp.
     * Se ejecuta a las 8:00 AM diariamente.
     */
    public static function run_loan_alerts(): void {
        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $today   = current_time( 'Y-m-d' );

        // ── Recordatorios previos al vencimiento (3 y 1 día antes) ─────
        $upcoming_loans = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.id, l.equipment_id, l.borrowed_by_user_id,
                    l.borrowed_to_name, l.borrowed_to_phone,
                    l.expected_return_date,
                    e.name AS equipment_name,
                    DATEDIFF( l.expected_return_date, %s ) AS days_until_due
             FROM {$t_loans} l
             INNER JOIN {$t_equip} e ON e.id = l.equipment_id
             WHERE l.actual_return_date IS NULL
               AND DATEDIFF( l.expected_return_date, %s ) IN (3, 1)
             ORDER BY days_until_due ASC",
            $today,
            $today
        ) );

        foreach ( $upcoming_loans as $loan ) {
            if ( empty( $loan->borrowed_to_phone ) ) {
                continue; // Solo WhatsApp para externos con teléfono
            }
            $days  = (int) $loan->days_until_due;
            $event = ( $days === 3 ) ? 'reminder3' : 'reminder1';

            // Anti-duplicado: un recordatorio por préstamo por día
            $sent_key = 'aura_inv_loan_reminder_' . $loan->id . '_' . $today;
            if ( get_option( $sent_key ) ) {
                continue;
            }
            update_option( $sent_key, time(), false );

            self::notify_borrower( (int) $loan->id, $event );
        }

        // ── Préstamos activos cuya fecha de devolución ya pasó ──────────
        $overdue_loans = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.id, l.equipment_id, l.borrowed_by_user_id,
                    l.borrowed_to_name, l.borrowed_to_phone,
                    l.expected_return_date,
                    e.name AS equipment_name,
                    DATEDIFF( %s, l.expected_return_date ) AS days_overdue
             FROM {$t_loans} l
             INNER JOIN {$t_equip} e ON e.id = l.equipment_id
             WHERE l.actual_return_date IS NULL
               AND l.expected_return_date < %s
             ORDER BY days_overdue ASC",
            $today,
            $today
        ) );

        foreach ( $overdue_loans as $loan ) {
            // Clave anti-duplicado: un email por préstamo por día
            $sent_key = 'aura_inv_loan_alert_' . $loan->id . '_' . $today;
            if ( get_option( $sent_key ) ) {
                continue;
            }
            update_option( $sent_key, time(), false );

            // Notificar al responsable del equipo o al administrador
            self::notify_admin_loan_overdue( $loan );

            // Notificar al prestatario externo por WhatsApp (si tiene teléfono)
            if ( ! empty( $loan->borrowed_to_phone ) ) {
                self::notify_borrower( (int) $loan->id, 'overdue' );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CRON 3 — Resumen semanal
    // ─────────────────────────────────────────────────────────────

    /**
     * Enviar resumen semanal de equipos en mantenimiento, préstamos vencidos
     * y mantenimientos próximos.
     * Se ejecuta los lunes a las 7:00 AM.
     */
    public static function send_weekly_summary(): void {
        global $wpdb;
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';
        $t_maint = $wpdb->prefix . 'aura_inventory_maintenance';
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $today   = current_time( 'Y-m-d' );
        $in_7    = date( 'Y-m-d', strtotime( '+7 days' ) );

        // Equipos en mantenimiento/reparación
        $in_maintenance = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$t_equip}
             WHERE status IN ('maintenance','repair') AND deleted_at IS NULL"
        );

        // Préstamos vencidos
        $loans_overdue = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t_loans}
             WHERE actual_return_date IS NULL AND expected_return_date < %s",
            $today
        ) );

        // Mantenimientos próximos en los próximos 7 días
        $upcoming_maint = $wpdb->get_results( $wpdb->prepare(
            "SELECT name, next_maintenance_date,
                    DATEDIFF( next_maintenance_date, %s ) AS days_until
             FROM {$t_equip}
             WHERE requires_maintenance = 1
               AND next_maintenance_date BETWEEN %s AND %s
               AND deleted_at IS NULL
             ORDER BY next_maintenance_date ASC
             LIMIT 20",
            $today,
            $today,
            $in_7
        ) );

        // No enviar si no hay nada que reportar
        if ( $in_maintenance === 0 && $loans_overdue === 0 && empty( $upcoming_maint ) ) {
            return;
        }

        $to      = get_option( 'admin_email' );
        $subject = sprintf(
            __( '[AURA Inventario] Resumen semanal — %s', 'aura-suite' ),
            date_i18n( 'j \d\e F Y', strtotime( $today ) )
        );

        // Construir cuerpo del email
        $body  = self::email_header( $subject );
        $body .= '<h2 style="color:#1e3a5f;">' . esc_html( $subject ) . '</h2>';

        // Estadísticas rápidas
        $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;">'
               . __( 'Equipos en mantenimiento / reparación', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;font-weight:bold;">'
               . esc_html( $in_maintenance ) . '</td></tr>';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;">'
               . __( 'Préstamos vencidos sin devolver', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;font-weight:bold;color:'
               . ( $loans_overdue > 0 ? '#c0392b' : '#27ae60' ) . ';">'
               . esc_html( $loans_overdue ) . '</td></tr>';
        $body .= '</table>';

        // Mantenimientos próximos
        if ( ! empty( $upcoming_maint ) ) {
            $body .= '<h3 style="color:#2c6e9e;">'
                   . __( 'Mantenimientos en los próximos 7 días', 'aura-suite' ) . '</h3>';
            $body .= '<table style="width:100%;border-collapse:collapse;">';
            $body .= '<tr style="background:#1e3a5f;color:#fff;">'
                   . '<th style="padding:8px;text-align:left;">' . __( 'Equipo',         'aura-suite' ) . '</th>'
                   . '<th style="padding:8px;text-align:left;">' . __( 'Fecha',          'aura-suite' ) . '</th>'
                   . '<th style="padding:8px;text-align:left;">' . __( 'Días restantes', 'aura-suite' ) . '</th>'
                   . '</tr>';
            foreach ( $upcoming_maint as $i => $m ) {
                $bg = $i % 2 === 0 ? '#f9f9f9' : '#fff';
                $body .= "<tr style=\"background:{$bg};\">"
                       . '<td style="padding:8px;border-bottom:1px solid #eee;">' . esc_html( $m->name ) . '</td>'
                       . '<td style="padding:8px;border-bottom:1px solid #eee;">'
                       . esc_html( date_i18n( 'd/m/Y', strtotime( $m->next_maintenance_date ) ) ) . '</td>'
                       . '<td style="padding:8px;border-bottom:1px solid #eee;">'
                       . esc_html( (int) $m->days_until ) . ' ' . __( 'días', 'aura-suite' ) . '</td>'
                       . '</tr>';
            }
            $body .= '</table>';
        }

        $body .= '<p style="margin-top:24px;font-size:13px;color:#888;">'
               . sprintf(
                   __( 'Ver inventario en: %s', 'aura-suite' ),
                   '<a href="' . esc_url( admin_url( 'admin.php?page=aura-inventory' ) ) . '">'
                   . admin_url( 'admin.php?page=aura-inventory' ) . '</a>'
               )
               . '</p>';
        $body .= self::email_footer();

        wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS — Enviar alertas de mantenimiento
    // ─────────────────────────────────────────────────────────────

    /**
     * Construir y enviar email de alerta de mantenimiento.
     *
     * @param object $equip  Fila de la tabla equipment (id, name, next_maintenance_date, responsible_user_id)
     * @param string $level  reminder15 | reminder7 | urgent3 | final1 | overdue
     */
    /**
     * Utilidad: obtener todos los destinatarios de email configurados.
     * Incluye admin_email + correos adicionales de aura_inventory_settings.
     */
    private static function get_configured_recipients( array $extra_users = [] ): array {
        $settings = Aura_Inventory_Categories::get_settings();
        $recipients = [ get_option( 'admin_email' ) ];

        // Correos adicionales configurados en la pestaña Notificaciones
        if ( ! empty( $settings['email_extra'] ) ) {
            foreach ( preg_split( '/[\r\n,]+/', $settings['email_extra'] ) as $mail ) {
                $mail = sanitize_email( trim( $mail ) );
                if ( $mail ) {
                    $recipients[] = $mail;
                }
            }
        }

        // Usuarios WordPress adicionales (p.ej. responsable del equipo)
        foreach ( $extra_users as $uid ) {
            $user = get_userdata( (int) $uid );
            if ( $user && $user->user_email ) {
                $recipients[] = $user->user_email;
            }
        }

        return array_unique( array_filter( $recipients ) );
    }

    private static function send_maintenance_alert( object $equip, string $level ): void {
        // Respetar la opción email_alerts del panel de configuración
        $settings = Aura_Inventory_Categories::get_settings();
        if ( empty( $settings['email_alerts'] ) ) {
            return;
        }

        // Configuración del nivel
        $levels = [
            'reminder15' => [
                'subject' => sprintf( __( '[AURA] Recordatorio: Mantenimiento en 15 días — %s', 'aura-suite' ), $equip->name ),
                'color'   => '#2c6e9e',
                'label'   => __( 'Mantenimiento en 15 días', 'aura-suite' ),
            ],
            'reminder7'  => [
                'subject' => sprintf( __( '[AURA] Recordatorio: Mantenimiento en 7 días — %s', 'aura-suite' ), $equip->name ),
                'color'   => '#2c6e9e',
                'label'   => __( 'Mantenimiento en 7 días', 'aura-suite' ),
            ],
            'urgent3'    => [
                'subject' => sprintf( __( '[ALERTA] Mantenimiento próximo en 3 días — %s', 'aura-suite' ), $equip->name ),
                'color'   => '#e67e22',
                'label'   => __( 'Mantenimiento en 3 días', 'aura-suite' ),
            ],
            'final1'     => [
                'subject' => sprintf( __( '[URGENTE] Mantenimiento MAÑANA — %s', 'aura-suite' ), $equip->name ),
                'color'   => '#c0392b',
                'label'   => __( 'Mantenimiento MAÑANA', 'aura-suite' ),
            ],
            'overdue'    => [
                'subject' => sprintf( __( '[URGENTE] Mantenimiento VENCIDO — %s', 'aura-suite' ), $equip->name ),
                'color'   => '#c0392b',
                'label'   => __( 'Mantenimiento VENCIDO', 'aura-suite' ),
            ],
        ];

        if ( ! isset( $levels[ $level ] ) ) {
            return;
        }

        // Anti-duplicado: para overdue, clave diaria; para el resto, mensual
        if ( $level === 'overdue' ) {
            $sent_key = 'aura_inv_maint_' . $equip->id . '_overdue_' . current_time( 'Y-m-d' );
        } else {
            $sent_key = 'aura_inv_maint_' . $equip->id . '_' . $level . '_' . current_time( 'Y-m' );
        }

        if ( get_option( $sent_key ) ) {
            return; // Ya se envió esta alerta
        }
        update_option( $sent_key, time(), false ); // no autoload

        $cfg = $levels[ $level ];

        // Destinatarios: admin + correos adicionales + responsable del equipo
        $extra_users = ! empty( $equip->responsible_user_id ) ? [ $equip->responsible_user_id ] : [];
        $recipients  = self::get_configured_recipients( $extra_users );

        $date_fmt = ! empty( $equip->next_maintenance_date )
            ? date_i18n( 'd/m/Y', strtotime( $equip->next_maintenance_date ) )
            : __( 'No definida', 'aura-suite' );

        $body  = self::email_header( $cfg['subject'] );
        $body .= '<div style="border-left:4px solid ' . esc_attr( $cfg['color'] ) . ';padding-left:16px;margin-bottom:24px;">';
        $body .= '<h2 style="color:' . esc_attr( $cfg['color'] ) . ';margin:0 0 8px;">'
               . esc_html( $cfg['label'] ) . '</h2>';
        $body .= '</div>';

        $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;width:40%;">'
               . __( 'Equipo', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;font-weight:bold;">'
               . esc_html( $equip->name ) . '</td></tr>';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;">'
               . __( 'Fecha próximo mantenimiento', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;">'
               . esc_html( $date_fmt ) . '</td></tr>';
        $body .= '</table>';

        $body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=aura-inventory-maintenance' ) ) . '" '
               . 'style="background:' . esc_attr( $cfg['color'] ) . ';color:#fff;padding:10px 20px;'
               . 'text-decoration:none;border-radius:4px;">'
               . __( 'Ver mantenimientos', 'aura-suite' ) . '</a></p>';
        $body .= self::email_footer();

        foreach ( $recipients as $to ) {
            wp_mail( $to, $cfg['subject'], $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS — Notificación de préstamo vencido al admin
    // ─────────────────────────────────────────────────────────────

    /**
     * Enviar email al administrador cuando un préstamo está vencido.
     *
     * @param object $loan  Fila de préstamo enriquecida con equipment_name y days_overdue
     */
    private static function notify_admin_loan_overdue( object $loan ): void {
        $to      = get_option( 'admin_email' );
        $subject = sprintf(
            __( '[ALERTA] Equipo no devuelto (%d días) — %s', 'aura-suite' ),
            (int) $loan->days_overdue,
            $loan->equipment_name
        );

        $borrower = ! empty( $loan->borrowed_to_name )
            ? $loan->borrowed_to_name
            : self::get_user_display_name( (int) $loan->borrowed_by_user_id );

        $date_fmt = date_i18n( 'd/m/Y', strtotime( $loan->expected_return_date ) );

        $body  = self::email_header( $subject );
        $body .= '<div style="border-left:4px solid #c0392b;padding-left:16px;margin-bottom:24px;">';
        $body .= '<h2 style="color:#c0392b;margin:0 0 8px;">'
               . __( 'Equipo no devuelto — Préstamo vencido', 'aura-suite' ) . '</h2>';
        $body .= '</div>';
        $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;width:40%;">'
               . __( 'Equipo', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;font-weight:bold;">'
               . esc_html( $loan->equipment_name ) . '</td></tr>';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;">'
               . __( 'Prestatario', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;">'
               . esc_html( $borrower ) . '</td></tr>';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;">'
               . __( 'Fecha esperada de devolución', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;color:#c0392b;">'
               . esc_html( $date_fmt ) . '</td></tr>';
        $body .= '<tr><td style="padding:10px;background:#f0f4f8;border:1px solid #d0d9e4;">'
               . __( 'Días de atraso', 'aura-suite' ) . '</td>'
               . '<td style="padding:10px;border:1px solid #d0d9e4;font-weight:bold;color:#c0392b;">'
               . esc_html( (int) $loan->days_overdue ) . '</td></tr>';
        $body .= '</table>';
        $body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=aura-inventory-loans' ) ) . '" '
               . 'style="background:#c0392b;color:#fff;padding:10px 20px;'
               . 'text-decoration:none;border-radius:4px;">'
               . __( 'Ver préstamos', 'aura-suite' ) . '</a></p>';
        $body .= self::email_footer();

        wp_mail( $to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // WHATSAPP — Notificaciones a prestatarios externos
    // ─────────────────────────────────────────────────────────────

    /**
     * Enviar notificación WhatsApp al prestatario externo de un préstamo.
     * Si WhatsApp no está habilitado o falla, envía email como fallback al admin.
     *
     * @param int    $loan_id  ID del préstamo en wp_aura_inventory_loans
     * @param string $event    checkout | reminder3 | reminder1 | overdue0 | overdue | checkin
     */
    public static function notify_borrower( int $loan_id, string $event ): void {
        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_inventory_loans';
        $t_equip = $wpdb->prefix . 'aura_inventory_equipment';

        $loan = $wpdb->get_row( $wpdb->prepare(
            "SELECT l.*, e.name AS equipment_name
             FROM {$t_loans} l
             INNER JOIN {$t_equip} e ON e.id = l.equipment_id
             WHERE l.id = %d",
            $loan_id
        ) );

        if ( ! $loan || empty( $loan->borrowed_to_phone ) ) {
            return;
        }

        $name      = ! empty( $loan->borrowed_to_name ) ? $loan->borrowed_to_name : __( 'Estimado/a', 'aura-suite' );
        $equipment = $loan->equipment_name;
        $fecha     = ! empty( $loan->expected_return_date )
                        ? date_i18n( 'd/m/Y', strtotime( $loan->expected_return_date ) )
                        : '';
        $signature = get_option( 'aura_whatsapp_signature', aura_get_org_name() );

        $messages = [
            'checkout'   => sprintf(
                __( "Hola %s, tienes *%s* prestado hasta el *%s*.\n_%s_", 'aura-suite' ),
                $name, $equipment, $fecha, $signature
            ),
            'reminder3'  => sprintf(
                __( "Recuerda devolver *%s* el *%s*. ¡Gracias!\n_%s_", 'aura-suite' ),
                $equipment, $fecha, $signature
            ),
            'reminder1'  => sprintf(
                __( "Mañana vence tu préstamo de *%s* (fecha: *%s*).\n_%s_", 'aura-suite' ),
                $equipment, $fecha, $signature
            ),
            'overdue0'   => sprintf(
                __( "Hoy vence el préstamo de *%s*. Por favor entrégalo hoy.\n_%s_", 'aura-suite' ),
                $equipment, $signature
            ),
            'overdue'    => sprintf(
                __( "Tu préstamo de *%s* está VENCIDO. Por favor comunícate con nosotros.\n_%s_", 'aura-suite' ),
                $equipment, $signature
            ),
            'checkin'    => sprintf(
                __( "Hemos recibido *%s* correctamente. ¡Gracias, %s!\n_%s_", 'aura-suite' ),
                $equipment, $name, $signature
            ),
        ];

        if ( ! isset( $messages[ $event ] ) ) {
            return;
        }

        $message = $messages[ $event ];
        $phone   = preg_replace( '/[^0-9+]/', '', $loan->borrowed_to_phone );

        // Intentar enviar por WhatsApp; si falla, no reenviar email (ya lo gestiona notify_admin_loan_overdue)
        self::send_whatsapp( $phone, $message );
    }

    /**
     * Enviar un mensaje de WhatsApp usando el proveedor configurado globalmente.
     * Delega a Aura_Notifications::send_whatsapp() (servicio global).
     *
     * @param  string $phone    Número E.164 (ej. +57987654321)
     * @param  string $message  Texto del mensaje
     * @return bool             true si la petición HTTP fue exitosa
     */
    private static function send_whatsapp( string $phone, string $message ): bool {
        return Aura_Notifications::send_whatsapp( $phone, $message );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Prueba manual de alertas (admin)
    // ─────────────────────────────────────────────────────────────

    /**
     * Disparar crons manualmente desde el panel de administración para pruebas.
     * Requiere nonce + manage_options.
     */
    public static function ajax_test_alerts(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $type = sanitize_key( $_POST['alert_type'] ?? 'maintenance' );

        switch ( $type ) {
            case 'maintenance':
                self::run_maintenance_check();
                wp_send_json_success( [ 'message' => __( 'Alertas de mantenimiento procesadas.', 'aura-suite' ) ] );
                break;
            case 'loans':
                self::run_loan_alerts();
                wp_send_json_success( [ 'message' => __( 'Alertas de préstamos procesadas.', 'aura-suite' ) ] );
                break;
            case 'summary':
                self::send_weekly_summary();
                wp_send_json_success( [ 'message' => __( 'Resumen semanal enviado.', 'aura-suite' ) ] );
                break;
            default:
                wp_send_json_error( [ 'message' => __( 'Tipo de alerta no válido.', 'aura-suite' ) ] );
        }
    }

    /**
     * AJAX — Enviar un correo de prueba directo a los destinatarios configurados.
     * No usa anti-duplicado, no depende del cron. Para verificar que los emails llegan.
     */
    public static function ajax_send_test_email(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $recipients = self::get_configured_recipients();
        if ( empty( $recipients ) ) {
            wp_send_json_error( [ 'message' => __( 'No hay destinatarios configurados.', 'aura-suite' ) ] );
        }

        $site    = aura_get_org_name();
        $subject = sprintf( __( '[AURA] Correo de prueba — %s', 'aura-suite' ), $site );
        $body    = self::email_header( $subject );
        $body   .= '<h2 style="color:#27ae60;">✅ ' . __( 'Prueba de notificaciones exitosa', 'aura-suite' ) . '</h2>';
        $body   .= '<p>' . sprintf(
            __( 'Este es un correo de prueba enviado desde <strong>%s</strong> el %s.', 'aura-suite' ),
            esc_html( $site ),
            date_i18n( 'd/m/Y H:i:s' )
        ) . '</p>';
        $body   .= '<p>' . __( 'Si recibiste este correo, el sistema de notificaciones está funcionando correctamente.', 'aura-suite' ) . '</p>';
        $body   .= '<p style="color:#888;font-size:12px;">' . __( 'Destinatarios configurados:', 'aura-suite' ) . ' ' . esc_html( implode( ', ', $recipients ) ) . '</p>';
        $body   .= self::email_footer();

        // Capturar error de wp_mail si ocurre (Post SMTP / PHPMailer lo reporta aquí)
        $mail_error = null;
        $capture_error = function ( \WP_Error $err ) use ( &$mail_error ) {
            $mail_error = $err->get_error_message();
        };
        add_action( 'wp_mail_failed', $capture_error );

        $admin_email = get_option( 'admin_email' );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . aura_get_org_name() . ' <' . $admin_email . '>',
        ];

        $sent   = 0;
        $failed = [];
        foreach ( $recipients as $to ) {
            if ( wp_mail( $to, $subject, $body, $headers ) ) {
                $sent++;
            } else {
                $failed[] = $to;
            }
        }

        remove_action( 'wp_mail_failed', $capture_error );

        if ( $sent > 0 ) {
            $msg = sprintf(
                __( '✅ Correo de prueba enviado a %d destinatario(s): %s', 'aura-suite' ),
                $sent,
                implode( ', ', $recipients )
            );
            if ( ! empty( $failed ) ) {
                $msg .= sprintf( __( ' (Falló para: %s)', 'aura-suite' ), implode( ', ', $failed ) );
            }
            wp_send_json_success( [ 'message' => $msg ] );
        } else {
            $detail = $mail_error
                ? sprintf( __( 'Error SMTP: %s', 'aura-suite' ), $mail_error )
                : __( 'wp_mail() retornó false. Revisa Post SMTP → Registro de correos para más detalles.', 'aura-suite' );
            wp_send_json_error( [ 'message' => $detail ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PLANTILLAS DE EMAIL
    // ─────────────────────────────────────────────────────────────

    private static function email_header( string $title ): string {
        $org = esc_html( aura_get_org_name() );
        $logo_html = '';
        if ( get_option( 'aura_org_logo_in_email', true ) && (int) get_option( 'aura_org_logo_id', 0 ) > 0 ) {
            $logo_html = '<div style="text-align:center;margin-bottom:10px;"><img src="' . esc_url( aura_get_org_logo_url( 'medium' ) ) . '" alt="' . $org . '" style="max-height:70px;width:auto;"></div>';
        }
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body '
             . 'style="font-family:Arial,sans-serif;font-size:14px;color:#333;max-width:640px;margin:0 auto;">'
             . '<div style="background:#1e3a5f;padding:20px 24px;text-align:center;">'
             . $logo_html
             . '<h1 style="color:#fff;margin:0;font-size:18px;">' . $org . ' <span style="color:#7ecfff;">Inventario</span></h1>'
             . '</div>'
             . '<div style="padding:24px;">';
    }

    private static function email_footer(): string {
        $url = esc_url( admin_url( 'admin.php?page=aura-inventory' ) );
        $org = esc_html( aura_get_org_name() );
        return '</div>'
             . '<div style="background:#f5f5f5;padding:16px 24px;font-size:12px;color:#888;">'
             . '<p style="margin:0;">'
             . sprintf( __( 'Este mensaje fue generado automáticamente por %s.', 'aura-suite' ), $org )
             . ' <a href="' . $url . '" style="color:#2c6e9e;">'
             . __( 'Ir al panel de inventario', 'aura-suite' )
             . '</a></p>'
             . '</div></body></html>';
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────

    private static function get_user_display_name( int $user_id ): string {
        $user = get_userdata( $user_id );
        return $user ? $user->display_name : __( 'Desconocido', 'aura-suite' );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Guardar configuración de WhatsApp
    // ─────────────────────────────────────────────────────────────

    /**
     * Guarda las opciones de WhatsApp (proveedor, token, número, etc.).
     * El token sólo se actualiza si el campo no llega vacío (permite preservarlo).
     */
    public static function ajax_save_whatsapp(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $provider = sanitize_key( $_POST['whatsapp_provider'] ?? 'callmebot' );
        if ( ! in_array( $provider, [ 'callmebot', 'twilio', 'meta' ], true ) ) {
            $provider = 'callmebot';
        }

        update_option( 'aura_whatsapp_enabled',  ! empty( $_POST['whatsapp_enabled'] ) ? '1' : '0' );
        update_option( 'aura_whatsapp_provider', $provider );
        update_option( 'aura_whatsapp_from',     sanitize_text_field( $_POST['whatsapp_from']      ?? '' ) );
        update_option( 'aura_whatsapp_twilio_sid',   sanitize_text_field( $_POST['whatsapp_twilio_sid']   ?? '' ) );
        update_option( 'aura_whatsapp_meta_phone_id', sanitize_text_field( $_POST['whatsapp_meta_phone_id'] ?? '' ) );
        update_option( 'aura_whatsapp_signature',    sanitize_text_field( $_POST['whatsapp_signature']    ?? aura_get_org_name() ) );

        // Token: solo actualizar si viene un valor nuevo (evita borrar token existente)
        $token = sanitize_text_field( $_POST['whatsapp_api_token'] ?? '' );
        if ( ! empty( $token ) ) {
            update_option( 'aura_whatsapp_api_token', $token );
        }

        wp_send_json_success( [ 'message' => __( 'Configuración de WhatsApp guardada.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Enviar mensaje WhatsApp de prueba
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía un mensaje de prueba al número indicado usando la configuración guardada.
     */
    public static function ajax_test_whatsapp(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $phone = sanitize_text_field( $_POST['phone'] ?? '' );
        if ( empty( $phone ) ) {
            wp_send_json_error( [ 'message' => __( 'Debes indicar un número de teléfono de destino.', 'aura-suite' ) ] );
        }

        if ( ! get_option( 'aura_whatsapp_enabled', '0' ) ) {
            wp_send_json_error( [ 'message' => __( 'WhatsApp no está habilitado. Actívalo y guarda antes de probar.', 'aura-suite' ) ] );
        }

        $site    = esc_html( aura_get_org_name() );
        $message = sprintf(
            __( '✅ Prueba de notificación desde %s. Si ves este mensaje, la integración WhatsApp funciona correctamente. (%s)', 'aura-suite' ),
            $site,
            date_i18n( 'd/m/Y H:i' )
        );

        $sent = self::send_whatsapp( $phone, $message );

        if ( $sent ) {
            wp_send_json_success( [
                'message' => sprintf(
                    __( 'Mensaje enviado a %s. Revisa el teléfono destinatario.', 'aura-suite' ),
                    esc_html( $phone )
                ),
            ] );
        } else {
            wp_send_json_error( [
                'message' => __( 'El mensaje no pudo enviarse. Verifica el proveedor, API token y número de origen.', 'aura-suite' ),
            ] );
        }
    }
}
