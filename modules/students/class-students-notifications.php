<?php
/**
 * Notificaciones del Módulo de Estudiantes
 *
 * Wrapper sobre Aura_Notifications (global) para todos los
 * eventos de email y WhatsApp del módulo de estudiantes.
 *
 * Delegación:
 *   - Email → wp_mail()  (igual que Aura_Notifications internamente)
 *   - WhatsApp → Aura_Notifications::send_whatsapp()
 *   - Notificaciones internas → Aura_Notifications::create_notification()
 *
 * Eventos que gestiona:
 *   send_new_applicant_alert   — Nueva solicitud recibida  → admins
 *   send_approval_email        — Solicitud aprobada + credenciales → estudiante
 *   send_rejection_email       — Solicitud rechazada → estudiante
 *   send_enrollment_email      — Inscripción a curso confirmada → estudiante
 *   send_payment_receipt       — Pago registrado (confirmación) → estudiante
 *   process_daily_reminders    — WP Cron: recordatorios + marcar vencidas
 *
 * @package AuraBusinessSuite
 * @subpackage Students
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Students_Notifications {

    // ─────────────────────────────────────────────────────────────
    // INIT — registrar handler del cron
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'aura_students_daily_reminders', [ __CLASS__, 'process_daily_reminders' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // NUEVA SOLICITUD — notificar a admins aprobadores
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica a todos los admins con `aura_students_approve`
     * cuando llega una nueva solicitud de inscripción.
     *
     * @param int $student_id  ID en aura_students
     */
    public static function notify_new_applicant( int $student_id ): void {
        global $wpdb;

        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name, last_name, email, profile_type, created_at
             FROM {$wpdb->prefix}aura_students
             WHERE id = %d AND deleted_at IS NULL",
            $student_id
        ) );

        if ( ! $student ) return;

        $approvers = get_users( [ 'capability' => 'aura_students_approve', 'fields' => 'all' ] );
        if ( empty( $approvers ) ) {
            // Fallback al email del administrador de WordPress
            $approvers_emails = [ get_option( 'admin_email' ) ];
        } else {
            $approvers_emails = wp_list_pluck( $approvers, 'user_email' );
        }

        $site_name   = aura_get_org_name();
        $full_name   = esc_html( $student->first_name . ' ' . $student->last_name );
        $type_label  = self::profile_type_label( $student->profile_type );
        $review_url  = admin_url( 'admin.php?page=aura-students-enrollments' );
        $subject     = sprintf( __( '[%s] Nueva solicitud de %s', 'aura-suite' ), $site_name, $full_name );

        $body = self::email_html(
            '🎓 Nueva Solicitud de Inscripción',
            sprintf(
                '<p>Se ha recibido una nueva solicitud en el portal.</p>
                <div class="data-row"><span class="data-label">Nombre:</span> %s</div>
                <div class="data-row"><span class="data-label">Email:</span> %s</div>
                <div class="data-row"><span class="data-label">Perfil:</span> %s</div>
                <div class="data-row"><span class="data-label">Fecha:</span> %s</div>
                <p style="margin-top:20px;">
                    <a href="%s" class="button">Revisar Solicitud</a>
                </p>',
                $full_name,
                esc_html( $student->email ),
                esc_html( $type_label ),
                esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $student->created_at ) ) ),
                esc_url( $review_url )
            )
        );

        foreach ( $approvers_emails as $email ) {
            wp_mail( $email, $subject, $body );
        }

        // Notificación interna (campana admin)
        foreach ( $approvers as $approver ) {
            Aura_Notifications::create_notification(
                $approver->ID,
                sprintf( __( 'Nueva solicitud de %s — revisa Inscripciones.', 'aura-suite' ), $full_name ),
                'info',
                'students'
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // APROBACIÓN — email al estudiante con credenciales
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía email de bienvenida con credenciales al estudiante aprobado.
     *
     * @param int    $student_id  ID en aura_students
     * @param string $password    Contraseña generada al crear el WP user
     */
    public static function send_approval_email( int $student_id, string $password ): void {
        global $wpdb;

        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name, last_name, email FROM {$wpdb->prefix}aura_students
             WHERE id = %d AND deleted_at IS NULL",
            $student_id
        ) );

        if ( ! $student ) return;

        $settings   = get_option( 'aura_students_settings', [] );
        if ( empty( $settings['send_credentials_email'] ?? true ) ) return;

        $site_name  = aura_get_org_name();
        $portal_url = self::get_portal_url( $settings );
        $login_url  = self::get_login_url( $settings );
        $full_name  = esc_html( $student->first_name . ' ' . $student->last_name );
        $subject    = sprintf( __( '[%s] ¡Tu solicitud ha sido aprobada!', 'aura-suite' ), $site_name );

        $body = self::email_html(
            '✅ Solicitud Aprobada',
            sprintf(
                '<p>Hola <strong>%s</strong>,</p>
                <p>¡Nos complace informarte que tu solicitud ha sido <span style="color:#10b981;font-weight:700;">aprobada</span>!</p>
                <p>A continuación encontrarás tus datos de acceso al portal:</p>
                <div class="card" style="background:#f5f3ff;border-left:4px solid #8b5cf6;">
                    <div class="data-row"><span class="data-label">Usuario / Email:</span> %s</div>
                    <div class="data-row"><span class="data-label">Contraseña temporal:</span> <code style="background:#ede9fe;padding:2px 6px;border-radius:4px;">%s</code></div>
                </div>
                <p>Por seguridad, te recomendamos cambiar tu contraseña al ingresar por primera vez.</p>
                %s
                <p style="margin-top:20px;">
                    <a href="%s" class="button">Acceder al Portal</a>
                </p>
                <p style="margin-top:16px;font-size:13px;color:#6b7280;">
                    Si tienes dudas, responde este correo o contáctanos directamente.
                </p>',
                $full_name,
                esc_html( $student->email ),
                esc_html( $password ),
                $portal_url ? sprintf( '<p><strong>URL del portal:</strong> <a href="%s">%s</a></p>', esc_url( $portal_url ), esc_url( $portal_url ) ) : '',
                esc_url( $login_url ?: wp_login_url() )
            )
        );

        wp_mail( $student->email, $subject, $body );
    }

    // ─────────────────────────────────────────────────────────────
    // RECHAZO — email al postulante
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al postulante que su solicitud fue rechazada.
     *
     * @param int    $student_id  ID en aura_students
     * @param string $reason      Razón de rechazo (puede estar vacía)
     */
    public static function send_rejection_email( int $student_id, string $reason = '' ): void {
        global $wpdb;

        $student = $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name, last_name, email FROM {$wpdb->prefix}aura_students
             WHERE id = %d AND deleted_at IS NULL",
            $student_id
        ) );

        if ( ! $student ) return;

        $site_name = aura_get_org_name();
        $full_name = esc_html( $student->first_name . ' ' . $student->last_name );
        $subject   = sprintf( __( '[%s] Resultado de tu solicitud', 'aura-suite' ), $site_name );

        $reason_block = $reason
            ? sprintf(
                '<div class="card" style="background:#fff5f5;border-left:4px solid #dc2626;">
                    <p><strong>Motivo:</strong> %s</p>
                 </div>',
                esc_html( $reason )
            )
            : '';

        $body = self::email_html(
            '📋 Resultado de tu Solicitud',
            sprintf(
                '<p>Hola <strong>%s</strong>,</p>
                <p>Gracias por tu interés en <strong>%s</strong>.</p>
                <p>Lamentablemente, en esta ocasión tu solicitud <span style="color:#dc2626;font-weight:700;">no ha sido aprobada</span>.</p>
                %s
                <p>Si crees que hay un error o deseas más información, no dudes en contactarnos.</p>',
                $full_name,
                esc_html( $site_name ),
                $reason_block
            )
        );

        wp_mail( $student->email, $subject, $body );
    }

    // ─────────────────────────────────────────────────────────────
    // INSCRIPCIÓN — confirmar al estudiante
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía confirmación de inscripción a curso al estudiante.
     *
     * @param int $student_id    ID en aura_students
     * @param int $enrollment_id ID en aura_student_enrollments
     */
    public static function send_enrollment_email( int $student_id, int $enrollment_id ): void {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.first_name, s.last_name, s.email, s.phone, s.phone_country,
                    e.net_cost, e.payment_scheme, e.installment_count,
                    e.installment_amount, e.scholarship_pct, e.scholarship_type,
                    c.name AS course_name, c.start_date, c.end_date,
                    a.name AS area_name
             FROM   {$wpdb->prefix}aura_students s
             JOIN   {$wpdb->prefix}aura_student_enrollments e ON e.id = %d AND e.student_id = s.id
             JOIN   {$wpdb->prefix}aura_student_courses c     ON c.id = e.course_id
             LEFT  JOIN {$wpdb->prefix}aura_areas a            ON a.id = c.area_id
             WHERE  s.id = %d AND s.deleted_at IS NULL",
            $enrollment_id,
            $student_id
        ) );

        if ( ! $row ) return;

        $site_name = aura_get_org_name();
        $full_name = esc_html( $row->first_name . ' ' . $row->last_name );
        $subject   = sprintf( __( '[%s] Inscripción confirmada: %s', 'aura-suite' ), $site_name, $row->course_name );
        $settings  = get_option( 'aura_students_settings', [] );
        $portal_url = self::get_portal_url( $settings );

        $scheme_text = $row->payment_scheme === 'installments'
            ? sprintf(
                __( '%d cuotas de %s', 'aura-suite' ),
                $row->installment_count,
                number_format( (float) $row->installment_amount, 2 )
            )
            : __( 'Pago completo', 'aura-suite' );

        $scholarship_block = $row->scholarship_pct > 0
            ? sprintf(
                '<div class="data-row"><span class="data-label">Beca:</span> %d%% (%s)</div>',
                $row->scholarship_pct,
                esc_html( $row->scholarship_type === 'external' ? __( 'Externa', 'aura-suite' ) : __( 'Interna', 'aura-suite' ) )
            )
            : '';

        $body = self::email_html(
            '🎓 Inscripción Confirmada',
            sprintf(
                '<p>Hola <strong>%s</strong>,</p>
                <p>Tu inscripción ha sido <span style="color:#10b981;font-weight:700;">confirmada</span>. Aquí están los detalles:</p>
                <div class="card">
                    %s
                    <div class="data-row"><span class="data-label">Curso:</span> %s</div>
                    <div class="data-row"><span class="data-label">Inicio:</span> %s</div>
                    <div class="data-row"><span class="data-label">Costo neto:</span> %s %s</div>
                    <div class="data-row"><span class="data-label">Esquema:</span> %s</div>
                    %s
                </div>
                %s
                <p>¡Nos vemos pronto!</p>',
                $full_name,
                $row->area_name ? sprintf( '<div class="data-row"><span class="data-label">Área/Programa:</span> %s</div>', esc_html( $row->area_name ) ) : '',
                esc_html( $row->course_name ),
                $row->start_date ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->start_date ) ) ) : __( 'Por confirmar', 'aura-suite' ),
                esc_html( number_format( (float) $row->net_cost, 2 ) ),
                esc_html( get_option( 'aura_students_settings', [] )['default_currency'] ?? 'USD' ),
                esc_html( $scheme_text ),
                $scholarship_block,
                $portal_url ? sprintf(
                    '<p style="margin-top:20px;"><a href="%s" class="button">Ver mi Portal</a></p>',
                    esc_url( $portal_url )
                ) : ''
            )
        );

        wp_mail( $row->email, $subject, $body );

        // WhatsApp si tiene teléfono y está activo
        if ( ! empty( $row->phone ) && ! empty( $row->phone_country ) ) {
            $phone = $row->phone_country . preg_replace( '/\D/', '', $row->phone );
            $msg   = sprintf(
                __( 'Hola %s, tu inscripción a *%s* ha sido confirmada. Costo: %s. ¡Esperamos verte pronto! 🎓', 'aura-suite' ),
                $row->first_name,
                $row->course_name,
                number_format( (float) $row->net_cost, 2 )
            );
            Aura_Notifications::send_whatsapp( $phone, $msg );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RECIBO DE PAGO — confirmación al estudiante
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía confirmación de pago registrado al estudiante.
     *
     * @param int $student_id ID en aura_students
     * @param int $payment_id ID en aura_student_payments
     */
    public static function send_payment_receipt( int $student_id, int $payment_id ): void {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.first_name, s.last_name, s.email, s.phone, s.phone_country,
                    p.amount, p.payment_date, p.payment_method, p.installment_num,
                    p.reference_number, p.receipt_url,
                    c.name AS course_name,
                    e.total_paid, e.balance_due
             FROM   {$wpdb->prefix}aura_student_payments p
             JOIN   {$wpdb->prefix}aura_students s          ON s.id = %d
             JOIN   {$wpdb->prefix}aura_student_enrollments e ON e.id = p.enrollment_id
             JOIN   {$wpdb->prefix}aura_student_courses c    ON c.id = p.course_id
             WHERE  p.id = %d AND p.student_id = %d AND s.deleted_at IS NULL",
            $student_id,
            $payment_id,
            $student_id
        ) );

        if ( ! $row ) return;

        $site_name  = aura_get_org_name();
        $full_name  = esc_html( $row->first_name . ' ' . $row->last_name );
        $subject    = sprintf( __( '[%s] Confirmación de pago recibido', 'aura-suite' ), $site_name );
        $currency   = get_option( 'aura_students_settings', [] )['default_currency'] ?? 'USD';

        $method_map = [
            'cash'     => __( 'Efectivo', 'aura-suite' ),
            'transfer' => __( 'Transferencia', 'aura-suite' ),
            'card'     => __( 'Tarjeta', 'aura-suite' ),
            'check'    => __( 'Cheque', 'aura-suite' ),
            'other'    => __( 'Otro', 'aura-suite' ),
        ];
        $method_label = $method_map[ $row->payment_method ] ?? $row->payment_method;

        $balance_block = (float) $row->balance_due > 0
            ? sprintf(
                '<p style="color:#dc2626;"><strong>%s %s</strong> %s</p>',
                esc_html( number_format( (float) $row->balance_due, 2 ) ),
                esc_html( $currency ),
                __( 'pendiente de pago.', 'aura-suite' )
            )
            : '<p style="color:#10b981;"><strong>✅ ' . __( '¡Pago completo! No tienes saldo pendiente.', 'aura-suite' ) . '</strong></p>';

        $receipt_block = $row->receipt_url
            ? sprintf( '<div class="data-row"><span class="data-label">Comprobante:</span> <a href="%s">%s</a></div>',
                esc_url( $row->receipt_url ), __( 'Descargar', 'aura-suite' ) )
            : '';

        $body = self::email_html(
            '💰 Pago Recibido',
            sprintf(
                '<p>Hola <strong>%s</strong>,</p>
                <p>Hemos registrado tu pago. Aquí está el resumen:</p>
                <div class="card">
                    <div class="data-row"><span class="data-label">Curso:</span> %s</div>
                    %s
                    <div class="data-row"><span class="data-label">Fecha:</span> %s</div>
                    <div class="data-row"><span class="data-label">Monto pagado:</span> <strong>%s %s</strong></div>
                    <div class="data-row"><span class="data-label">Método:</span> %s</div>
                    %s
                    %s
                </div>
                %s',
                $full_name,
                esc_html( $row->course_name ),
                $row->installment_num ? sprintf( '<div class="data-row"><span class="data-label">Cuota #:</span> %d</div>', $row->installment_num ) : '',
                esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->payment_date ) ) ),
                esc_html( number_format( (float) $row->amount, 2 ) ),
                esc_html( $currency ),
                esc_html( $method_label ),
                $row->reference_number ? sprintf( '<div class="data-row"><span class="data-label">Referencia:</span> %s</div>', esc_html( $row->reference_number ) ) : '',
                $receipt_block,
                $balance_block
            )
        );

        wp_mail( $row->email, $subject, $body );
    }

    // ─────────────────────────────────────────────────────────────
    // RECORDATORIO DE CUOTA — envío individual
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía recordatorio (email + WhatsApp) cuando una cuota
     * está por vencer en los próximos días.
     *
     * @param object $student     Fila de aura_students
     * @param object $installment Fila de aura_student_installment_schedule JOIN course
     */
    private static function send_payment_reminder( object $student, object $installment ): void {
        $site_name = aura_get_org_name();
        $full_name = esc_html( $student->first_name . ' ' . $student->last_name );
        $currency  = get_option( 'aura_students_settings', [] )['default_currency'] ?? 'USD';
        $due_str   = date_i18n( get_option( 'date_format' ), strtotime( $installment->due_date ) );
        $subject   = sprintf( __( '[%s] Recordatorio: cuota próxima a vencer', 'aura-suite' ), $site_name );
        $settings  = get_option( 'aura_students_settings', [] );

        $body = self::email_html(
            '⏰ Recordatorio de Pago',
            sprintf(
                '<p>Hola <strong>%s</strong>,</p>
                <p>Este es un recordatorio amistoso: tienes una cuota que vence próximamente.</p>
                <div class="card" style="background:#fff7ed;border-left:4px solid #f59e0b;">
                    <div class="data-row"><span class="data-label">Curso:</span> %s</div>
                    <div class="data-row"><span class="data-label">Cuota #:</span> %d</div>
                    <div class="data-row"><span class="data-label">Vencimiento:</span> <strong>%s</strong></div>
                    <div class="data-row"><span class="data-label">Monto:</span> <strong>%s %s</strong></div>
                </div>
                <p>Para evitar recargos, realiza tu pago antes de la fecha indicada.</p>
                %s',
                $full_name,
                esc_html( $installment->course_name ),
                $installment->installment_num,
                $due_str,
                esc_html( number_format( (float) $installment->expected_amount, 2 ) ),
                esc_html( $currency ),
                self::get_portal_url( $settings ) ? sprintf(
                    '<p style="margin-top:16px;"><a href="%s" class="button">Ver mi Portal de Pagos</a></p>',
                    esc_url( self::get_portal_url( $settings ) )
                ) : ''
            )
        );

        wp_mail( $student->email, $subject, $body );

        // WhatsApp
        if ( ! empty( $student->phone ) && ! empty( $student->phone_country ) ) {
            $phone = $student->phone_country . preg_replace( '/\D/', '', $student->phone );
            $msg   = sprintf(
                __( 'Hola %s, recuerda que tu cuota #%d de *%s* vence el %s por un monto de %s %s. ¡Realiza tu pago a tiempo! 💳', 'aura-suite' ),
                $student->first_name,
                $installment->installment_num,
                $installment->course_name,
                $due_str,
                number_format( (float) $installment->expected_amount, 2 ),
                $currency
            );
            Aura_Notifications::send_whatsapp( $phone, $msg );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CUOTA VENCIDA — notificar a estudiante y admin
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica a estudiante y admins cuando una cuota queda vencida.
     *
     * @param object $student     Fila de aura_students
     * @param object $installment Fila JOIN aura_student_installment_schedule + course
     */
    private static function send_overdue_notice( object $student, object $installment ): void {
        $site_name = aura_get_org_name();
        $full_name = esc_html( $student->first_name . ' ' . $student->last_name );
        $currency  = get_option( 'aura_students_settings', [] )['default_currency'] ?? 'USD';
        $due_str   = date_i18n( get_option( 'date_format' ), strtotime( $installment->due_date ) );

        // Email al estudiante
        $subject_student = sprintf( __( '[%s] Cuota vencida sin pago', 'aura-suite' ), $site_name );
        $body_student    = self::email_html(
            '🔴 Cuota Vencida',
            sprintf(
                '<p>Hola <strong>%s</strong>,</p>
                <p>La siguiente cuota está <span style="color:#dc2626;font-weight:700;">vencida</span> y aún no hemos recibido tu pago:</p>
                <div class="card" style="background:#fff5f5;border-left:4px solid #dc2626;">
                    <div class="data-row"><span class="data-label">Curso:</span> %s</div>
                    <div class="data-row"><span class="data-label">Cuota #:</span> %d</div>
                    <div class="data-row"><span class="data-label">Fecha de vencimiento:</span> %s</div>
                    <div class="data-row"><span class="data-label">Monto pendiente:</span> <strong>%s %s</strong></div>
                </div>
                <p>Por favor, contáctanos a la brevedad para regularizar tu situación.</p>',
                $full_name,
                esc_html( $installment->course_name ),
                $installment->installment_num,
                $due_str,
                esc_html( number_format( (float) $installment->expected_amount, 2 ) ),
                esc_html( $currency )
            )
        );
        wp_mail( $student->email, $subject_student, $body_student );

        // Email a admins con capability aura_students_payments_view_all
        $admins = get_users( [ 'capability' => 'aura_students_payments_view_all', 'fields' => 'all' ] );
        if ( empty( $admins ) ) {
            $admins_emails = [ get_option( 'admin_email' ) ];
        } else {
            $admins_emails = wp_list_pluck( $admins, 'user_email' );
        }

        $subject_admin = sprintf(
            __( '[%s] Cuota vencida — %s / %s', 'aura-suite' ),
            $site_name,
            $student->first_name . ' ' . $student->last_name,
            $installment->course_name
        );
        $body_admin = self::email_html(
            '🔴 Cuota Vencida sin Pago',
            sprintf(
                '<p>La siguiente cuota está vencida y requiere atención:</p>
                <div class="card">
                    <div class="data-row"><span class="data-label">Estudiante:</span> %s (%s)</div>
                    <div class="data-row"><span class="data-label">Curso:</span> %s</div>
                    <div class="data-row"><span class="data-label">Cuota #:</span> %d</div>
                    <div class="data-row"><span class="data-label">Venció el:</span> %s</div>
                    <div class="data-row"><span class="data-label">Monto:</span> %s %s</div>
                </div>
                <p style="margin-top:16px;">
                    <a href="%s" class="button">Gestionar Pagos</a>
                </p>',
                esc_html( $full_name ),
                esc_html( $student->email ),
                esc_html( $installment->course_name ),
                $installment->installment_num,
                $due_str,
                esc_html( number_format( (float) $installment->expected_amount, 2 ) ),
                esc_html( $currency ),
                esc_url( admin_url( 'admin.php?page=aura-students-payments' ) )
            )
        );
        foreach ( $admins_emails as $email ) {
            wp_mail( $email, $subject_admin, $body_admin );
        }

        // Notificación interna a admins
        foreach ( $admins as $admin ) {
            Aura_Notifications::create_notification(
                $admin->ID,
                sprintf(
                    __( 'Cuota vencida: %s — %s (#%d)', 'aura-suite' ),
                    $full_name,
                    $installment->course_name,
                    $installment->installment_num
                ),
                'error',
                'students'
            );
        }

        // WhatsApp al estudiante
        if ( ! empty( $student->phone ) && ! empty( $student->phone_country ) ) {
            $phone = $student->phone_country . preg_replace( '/\D/', '', $student->phone );
            $msg   = sprintf(
                __( 'Hola %s, tu cuota #%d de *%s* venció el %s por %s %s. Por favor comunícate con nosotros para regularizar tu pago. 🔴', 'aura-suite' ),
                $student->first_name,
                $installment->installment_num,
                $installment->course_name,
                $due_str,
                number_format( (float) $installment->expected_amount, 2 ),
                $currency
            );
            Aura_Notifications::send_whatsapp( $phone, $msg );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GRADUACIÓN — felicitación al estudiante
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía felicitación por graduación (email + WhatsApp).
     *
     * @param int $student_id    ID en aura_students
     * @param int $enrollment_id ID en aura_student_enrollments
     */
    public static function send_graduation_email( int $student_id, int $enrollment_id ): void {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.first_name, s.last_name, s.email, s.phone, s.phone_country,
                    c.name AS course_name, a.name AS area_name
             FROM   {$wpdb->prefix}aura_students s
             JOIN   {$wpdb->prefix}aura_student_enrollments e ON e.id = %d AND e.student_id = s.id
             JOIN   {$wpdb->prefix}aura_student_courses c     ON c.id = e.course_id
             LEFT  JOIN {$wpdb->prefix}aura_areas a            ON a.id = c.area_id
             WHERE  s.id = %d AND s.deleted_at IS NULL",
            $enrollment_id,
            $student_id
        ) );

        if ( ! $row ) return;

        $site_name = aura_get_org_name();
        $full_name = esc_html( $row->first_name . ' ' . $row->last_name );
        $subject   = sprintf( __( '[%s] ¡Felicitaciones por tu graduación!', 'aura-suite' ), $site_name );
        $settings  = get_option( 'aura_students_settings', [] );

        $body = self::email_html(
            '🏅 ¡Felicitaciones!',
            sprintf(
                '<p>Hola <strong>%s</strong>,</p>
                <p style="font-size:18px;">🎉 ¡Has completado exitosamente <strong>%s</strong>!</p>
                %s
                <p>Tu dedicación y esfuerzo son un ejemplo para toda la comunidad de <strong>%s</strong>.</p>
                <p>Pronto recibirás información sobre tu certificado/diploma.</p>
                %s',
                $full_name,
                esc_html( $row->course_name ),
                $row->area_name ? sprintf( '<p><strong>Programa:</strong> %s</p>', esc_html( $row->area_name ) ) : '',
                esc_html( $site_name ),
                self::get_portal_url( $settings ) ? sprintf(
                    '<p style="margin-top:20px;"><a href="%s" class="button">Ver mis Certificados</a></p>',
                    esc_url( self::get_portal_url( $settings ) )
                ) : ''
            )
        );

        wp_mail( $row->email, $subject, $body );

        if ( ! empty( $row->phone ) && ! empty( $row->phone_country ) ) {
            $phone = $row->phone_country . preg_replace( '/\D/', '', $row->phone );
            $msg   = sprintf(
                __( '🎉 ¡Felicitaciones %s! Has completado exitosamente *%s* en %s. Pronto recibirás tu certificado. ¡Mucho éxito! 🏅', 'aura-suite' ),
                $row->first_name,
                $row->course_name,
                $site_name
            );
            Aura_Notifications::send_whatsapp( $phone, $msg );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CRON DIARIO — recordatorios y detección de vencidas
    // ─────────────────────────────────────────────────────────────

    /**
     * Callback del WP Cron `aura_students_daily_reminders`.
     *
     * Proceso:
     *  1. Buscar cuotas con due_date = hoy + {reminder_days_before} y status='pending'
     *     → enviar recordatorio email + WhatsApp
     *  2. Buscar cuotas con due_date < hoy y status='pending'
     *     → actualizar a 'overdue', actualizar payment_status de la inscripción, notificar
     */
    public static function process_daily_reminders(): void {
        global $wpdb;

        $settings            = get_option( 'aura_students_settings', [] );
        $reminder_days       = max( 1, (int) ( $settings['reminder_days_before'] ?? 3 ) );
        $overdue_alert_on    = isset( $settings['overdue_alert_enabled'] ) ? (bool) $settings['overdue_alert_enabled'] : true;
        $t_schedule          = $wpdb->prefix . 'aura_student_installment_schedule';
        $t_enrollments       = $wpdb->prefix . 'aura_student_enrollments';

        // ── 1. RECORDATORIOS: cuotas próximas a vencer ────────────
        $reminder_date = gmdate( 'Y-m-d', strtotime( "+{$reminder_days} days" ) );

        $upcoming = $wpdb->get_results( $wpdb->prepare(
            "SELECT sch.id AS schedule_id, sch.enrollment_id, sch.installment_num,
                    sch.due_date, sch.expected_amount,
                    c.name AS course_name,
                    s.id AS student_id, s.first_name, s.last_name,
                    s.email, s.phone, s.phone_country
             FROM   {$t_schedule} sch
             JOIN   {$t_enrollments} e    ON e.id = sch.enrollment_id
             JOIN   {$wpdb->prefix}aura_student_courses c ON c.id = e.course_id
             JOIN   {$wpdb->prefix}aura_students s        ON s.id = e.student_id
             WHERE  sch.due_date = %s
               AND  sch.status   = 'pending'
               AND  s.deleted_at IS NULL",
            $reminder_date
        ) );

        foreach ( $upcoming as $item ) {
            $student     = (object) [
                'first_name'   => $item->first_name,
                'last_name'    => $item->last_name,
                'email'        => $item->email,
                'phone'        => $item->phone,
                'phone_country'=> $item->phone_country,
            ];
            $installment = (object) [
                'installment_num' => $item->installment_num,
                'due_date'        => $item->due_date,
                'expected_amount' => $item->expected_amount,
                'course_name'     => $item->course_name,
            ];
            self::send_payment_reminder( $student, $installment );
        }

        // ── 2. VENCIDAS: cuotas con due_date < hoy y aún 'pending' ─
        if ( ! $overdue_alert_on ) return;

        $today   = gmdate( 'Y-m-d' );
        $overdue = $wpdb->get_results( $wpdb->prepare(
            "SELECT sch.id AS schedule_id, sch.enrollment_id, sch.installment_num,
                    sch.due_date, sch.expected_amount,
                    c.name AS course_name,
                    s.id AS student_id, s.first_name, s.last_name,
                    s.email, s.phone, s.phone_country
             FROM   {$t_schedule} sch
             JOIN   {$t_enrollments} e    ON e.id = sch.enrollment_id
             JOIN   {$wpdb->prefix}aura_student_courses c ON c.id = e.course_id
             JOIN   {$wpdb->prefix}aura_students s        ON s.id = e.student_id
             WHERE  sch.due_date < %s
               AND  sch.status   = 'pending'
               AND  s.deleted_at IS NULL",
            $today
        ) );

        foreach ( $overdue as $item ) {
            // Marcar cuota como overdue
            $wpdb->update(
                $t_schedule,
                [ 'status' => 'overdue' ],
                [ 'id' => $item->schedule_id ],
                [ '%s' ],
                [ '%d' ]
            );

            // Actualizar payment_status de la inscripción a 'overdue' si no está 'paid'
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$t_enrollments}
                 SET payment_status = 'overdue'
                 WHERE id = %d AND payment_status NOT IN ('paid')",
                $item->enrollment_id
            ) );

            // Notificar
            $student     = (object) [
                'first_name'   => $item->first_name,
                'last_name'    => $item->last_name,
                'email'        => $item->email,
                'phone'        => $item->phone,
                'phone_country'=> $item->phone_country,
            ];
            $installment = (object) [
                'installment_num' => $item->installment_num,
                'due_date'        => $item->due_date,
                'expected_amount' => $item->expected_amount,
                'course_name'     => $item->course_name,
            ];
            self::send_overdue_notice( $student, $installment );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Construye un email HTML con header y footer coherentes con el
     * estilo de Aura_Notifications.
     *
     * @param string $title Título de la sección principal
     * @param string $body  HTML del cuerpo (sin wrapper)
     * @return string HTML completo
     */
    private static function email_html( string $title, string $body ): string {
        $site_name = aura_get_org_name();
        $show_logo = get_option( 'aura_org_logo_in_email', true )
                     && (int) get_option( 'aura_org_logo_id', 0 ) > 0;
        $logo_url  = $show_logo ? aura_get_org_logo_url( 'medium' ) : '';

        $logo_tag = $logo_url
            ? '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '" style="max-height:70px;width:auto;margin:0 auto 8px;display:block;">'
            : '';

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;}
  .container{max-width:600px;margin:0 auto;padding:20px;}
  .header{text-align:center;padding:24px 20px;background:linear-gradient(135deg,#8b5cf6 0%,#6d28d9 100%);}
  .header h2{color:#fff;margin:8px 0 0;}
  .content{padding:30px;background:#f9fafb;}
  .card{background:#fff;padding:20px;border-radius:8px;margin:16px 0;box-shadow:0 2px 4px rgba(0,0,0,.08);}
  .button{display:inline-block;padding:11px 22px;background:#8b5cf6;color:#fff;text-decoration:none;border-radius:6px;font-weight:700;}
  .data-row{padding:8px 0;border-bottom:1px solid #f3f4f6;}
  .data-label{font-weight:700;color:#5b21b6;}
  .footer{text-align:center;padding:20px;color:#9ca3af;font-size:12px;}
  code{background:#ede9fe;padding:2px 6px;border-radius:4px;font-size:.95em;}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    ' . $logo_tag . '
    <h2>' . esc_html( $site_name ) . '</h2>
  </div>
  <div class="content">
    <h3 style="margin-top:0;">' . esc_html( $title ) . '</h3>
    ' . $body . '
  </div>
  <div class="footer">
    <p>Email automático de <strong>' . esc_html( $site_name ) . '</strong> · Aura Business Suite</p>
    <p>&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . '</p>
  </div>
</div>
</body>
</html>';
    }

    /**
     * Devuelve URL del portal del estudiante leída desde opciones.
     *
     * @param array $settings Opción aura_students_settings
     * @return string URL o cadena vacía
     */
    private static function get_portal_url( array $settings ): string {
        $page_id = (int) ( $settings['portal_page_id'] ?? 0 );
        return $page_id > 0 ? (string) get_permalink( $page_id ) : '';
    }

    /**
     * Devuelve URL de la página de login del estudiante.
     *
     * @param array $settings Opción aura_students_settings
     * @return string URL o cadena vacía
     */
    private static function get_login_url( array $settings ): string {
        $page_id = (int) ( $settings['login_page_id'] ?? 0 );
        return $page_id > 0 ? (string) get_permalink( $page_id ) : '';
    }

    /**
     * Etiqueta legible del tipo de perfil.
     *
     * @param string $profile_type Código del perfil
     * @return string Etiqueta i18n
     */
    private static function profile_type_label( string $profile_type ): string {
        $map = [
            'student'     => __( 'Estudiante', 'aura-suite' ),
            'volunteer'   => __( 'Voluntario', 'aura-suite' ),
            'teacher'     => __( 'Instructor', 'aura-suite' ),
            'participant' => __( 'Participante', 'aura-suite' ),
            'intern'      => __( 'Practicante', 'aura-suite' ),
        ];
        return $map[ $profile_type ] ?? $profile_type;
    }
}
