<?php
/**
 * Notificaciones del Módulo de Biblioteca
 *
 * Envía emails y mensajes WhatsApp relacionados a préstamos, devoluciones y reservas.
 *
 * @package AuraBusinessSuite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Notifications {

    // ─────────────────────────────────────────────────────────────
    // EMAIL: Préstamo registrado
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía confirmación de préstamo al lector y al bibliotecario (email extra).
     *
     * @param int $loan_id
     */
    public static function send_loan_confirmation( int $loan_id ): void {
        if ( ! get_option( 'aura_library_email_alerts', true ) ) {
            return;
        }

        $loan = self::get_loan_data( $loan_id );
        if ( ! $loan ) {
            return;
        }

        $org   = aura_get_org_name();
        $title = sprintf( __( 'Confirmación de préstamo — %s', 'aura-business-suite' ), $org );

        $body = sprintf(
            '<p>' . __( 'Estimado/a %s,', 'aura-business-suite' ) . '</p>
            <p>' . __( 'Tu préstamo del libro <strong>%s</strong> ha sido registrado.', 'aura-business-suite' ) . '</p>
            <ul>
                <li>' . __( 'Fecha de préstamo: %s', 'aura-business-suite' ) . '</li>
                <li>' . __( 'Fecha de devolución: %s', 'aura-business-suite' ) . '</li>
                <li>' . __( 'Ubicación: %s', 'aura-business-suite' ) . '</li>
            </ul>
            <p>' . __( 'Por favor devuelve el libro antes de la fecha indicada.', 'aura-business-suite' ) . '</p>',
            esc_html( $loan['borrower_name'] ),
            esc_html( $loan['book_title'] ),
            esc_html( $loan['loan_date'] ),
            esc_html( $loan['due_date'] ),
            esc_html( $loan['location'] )
        );

        // Notificar al lector
        if ( $loan['borrower_email'] ) {
            self::send_email( $loan['borrower_email'], $title, $body );
        }

        // Notificación interna (campana)
        if ( $loan['borrower_user_id'] ) {
            Aura_Notifications::create_notification(
                $loan['borrower_user_id'],
                sprintf( __( 'Préstamo registrado: %s — devolver antes del %s', 'aura-business-suite' ), $loan['book_title'], $loan['due_date'] ),
                'info',
                'library'
            );
        }

        // WhatsApp al lector
        $signature = get_option( 'aura_whatsapp_signature', aura_get_org_name() );
        $wa_phone  = self::get_user_phone( $loan['borrower_user_id'] ?? 0 );
        if ( $wa_phone ) {
            $wa_msg = sprintf(
                __( "Hola %s, tu préstamo de *%s* ha sido registrado. Dévuelve antes del *%s*.\n_%s_", 'aura-business-suite' ),
                $loan['borrower_name'],
                $loan['book_title'],
                $loan['due_date'],
                $signature
            );
            self::send_whatsapp( $wa_phone, $wa_msg );
        }

        // Email al bibliotecario (email extra configurado)
        $extra_email = get_option( 'aura_library_email_extra', '' );
        if ( $extra_email ) {
            $lib_subject = sprintf( __( 'Nuevo préstamo registrado — %s', 'aura-business-suite' ), $org );
            $lib_body = sprintf(
                '<p><strong>' . __( 'Nuevo préstamo en Biblioteca', 'aura-business-suite' ) . '</strong></p>
                <ul>
                    <li>' . __( 'Lector: %s', 'aura-business-suite' ) . '</li>
                    <li>' . __( 'Libro: %s', 'aura-business-suite' ) . '</li>
                    <li>' . __( 'Fecha de préstamo: %s', 'aura-business-suite' ) . '</li>
                    <li>' . __( 'Fecha de devolución: %s', 'aura-business-suite' ) . '</li>
                </ul>',
                esc_html( $loan['borrower_name'] ),
                esc_html( $loan['book_title'] ),
                esc_html( $loan['loan_date'] ),
                esc_html( $loan['due_date'] )
            );
            self::send_email( $extra_email, $lib_subject, $lib_body );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL + WHATSAPP: Recordatorio de vencimiento
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía recordatorio de vencimiento próximo al lector.
     *
     * @param int    $loan_id
     * @param string $type  '3days' | 'today'
     */
    public static function send_reminder( int $loan_id, string $type = '3days' ): void {
        if ( ! get_option( 'aura_library_email_alerts', true ) ) {
            return;
        }

        $loan = self::get_loan_data( $loan_id );
        if ( ! $loan ) {
            return;
        }

        $org = aura_get_org_name();

        if ( $type === 'today' ) {
            $subject = sprintf( __( 'Hoy vence tu préstamo — %s', 'aura-business-suite' ), $org );
            $intro   = __( 'Hoy es la fecha límite para devolver el libro <strong>%s</strong>.', 'aura-business-suite' );
            $wa_msg  = sprintf( __( "[%s] Recordatorio: Hoy vence el préstamo del libro «%s». Por favor devuélvelo hoy.", 'aura-business-suite' ), $org, $loan['book_title'] );
            $notif   = sprintf( __( 'Hoy vence el préstamo: %s', 'aura-business-suite' ), $loan['book_title'] );
        } else {
            $days    = (int) get_option( 'aura_library_reminder_days', 3 );
            $subject = sprintf( __( 'Tu préstamo vence en %d días — %s', 'aura-business-suite' ), $days, $org );
            $intro   = sprintf( __( 'Tu préstamo del libro <strong>%%s</strong> vence en %d días (el %%s).', 'aura-business-suite' ), $days );
            $wa_msg  = sprintf( __( "[%s] Recordatorio: El préstamo del libro «%s» vence el %s. No olvides devolverlo.", 'aura-business-suite' ), $org, $loan['book_title'], $loan['due_date'] );
            $notif   = sprintf( __( 'Tu préstamo vence pronto: %s (%s)', 'aura-business-suite' ), $loan['book_title'], $loan['due_date'] );
        }

        $body = sprintf(
            '<p>' . __( 'Estimado/a %s,', 'aura-business-suite' ) . '</p>
            <p>' . $intro . '</p>
            <ul>
                <li>' . __( 'Libro: <strong>%s</strong>', 'aura-business-suite' ) . '</li>
                <li>' . __( 'Fecha de devolución: <strong>%s</strong>', 'aura-business-suite' ) . '</li>
            </ul>
            <p>' . __( 'Si necesitas más tiempo, puedes solicitar una extensión.', 'aura-business-suite' ) . '</p>',
            esc_html( $loan['borrower_name'] ),
            esc_html( $loan['book_title'] ),
            esc_html( $loan['book_title'] ),
            esc_html( $loan['due_date'] )
        );

        if ( $loan['borrower_email'] ) {
            self::send_email( $loan['borrower_email'], $subject, $body );
        }

        if ( $loan['borrower_phone'] ) {
            self::send_whatsapp( $loan['borrower_phone'], $wa_msg );
        }

        if ( $loan['borrower_user_id'] ) {
            Aura_Notifications::create_notification( $loan['borrower_user_id'], $notif, 'warning', 'library' );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL + WHATSAPP: Préstamo vencido
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía alerta de préstamo vencido al lector y al bibliotecario.
     *
     * @param int $loan_id
     */
    public static function send_overdue_alert( int $loan_id ): void {
        if ( ! get_option( 'aura_library_email_alerts', true ) ) {
            return;
        }

        $loan = self::get_loan_data( $loan_id );
        if ( ! $loan ) {
            return;
        }

        $fine = class_exists( 'Aura_Library_Fines' )
            ? Aura_Library_Fines::calculate_fine( $loan['due_date'], null )
            : 0.0;

        $org     = aura_get_org_name();
        $subject = sprintf( __( 'Préstamo vencido — %s', 'aura-business-suite' ), $org );

        $body = sprintf(
            '<p>' . __( 'Estimado/a %s,', 'aura-business-suite' ) . '</p>
            <p>' . __( 'El préstamo del libro <strong>%s</strong> lleva <strong>%d días</strong> vencido.', 'aura-business-suite' ) . '</p>
            <ul>
                <li>' . __( 'Fecha de devolución: %s', 'aura-business-suite' ) . '</li>
                <li>' . __( 'Multa acumulada: %s', 'aura-business-suite' ) . '</li>
            </ul>
            <p>' . __( 'Por favor devuelve el libro a la brevedad posible.', 'aura-business-suite' ) . '</p>',
            esc_html( $loan['borrower_name'] ),
            esc_html( $loan['book_title'] ),
            (int) $loan['days_overdue'],
            esc_html( $loan['due_date'] ),
            number_format( $fine, 2 )
        );

        if ( $loan['borrower_email'] ) {
            self::send_email( $loan['borrower_email'], $subject, $body );
        }

        $wa_msg = sprintf(
            __( "[%s] AVISO: El préstamo del libro «%s» lleva %d días vencido. Multa acumulada: %s. Por favor devuélvelo.", 'aura-business-suite' ),
            $org,
            $loan['book_title'],
            (int) $loan['days_overdue'],
            number_format( $fine, 2 )
        );
        if ( $loan['borrower_phone'] ) {
            self::send_whatsapp( $loan['borrower_phone'], $wa_msg );
        }

        if ( $loan['borrower_user_id'] ) {
            Aura_Notifications::create_notification(
                $loan['borrower_user_id'],
                sprintf( __( 'Préstamo vencido: %s — Multa: %s', 'aura-business-suite' ), $loan['book_title'], number_format( $fine, 2 ) ),
                'error',
                'library'
            );
        }

        // Notificar al bibliotecario
        $extra_email = get_option( 'aura_library_email_extra', '' );
        if ( $extra_email ) {
            $lib_subject = sprintf( __( 'Préstamo vencido sin devolver — %s', 'aura-business-suite' ), $org );
            $lib_body = sprintf(
                '<p><strong>' . __( 'Alerta de préstamo vencido', 'aura-business-suite' ) . '</strong></p>
                <ul>
                    <li>' . __( 'Lector: %s', 'aura-business-suite' ) . '</li>
                    <li>' . __( 'Libro: %s', 'aura-business-suite' ) . '</li>
                    <li>' . __( 'Fecha de devolución: %s', 'aura-business-suite' ) . '</li>
                    <li>' . __( 'Días vencido: %d', 'aura-business-suite' ) . '</li>
                    <li>' . __( 'Multa acumulada: %s', 'aura-business-suite' ) . '</li>
                </ul>',
                esc_html( $loan['borrower_name'] ),
                esc_html( $loan['book_title'] ),
                esc_html( $loan['due_date'] ),
                (int) $loan['days_overdue'],
                number_format( $fine, 2 )
            );
            self::send_email( $extra_email, $lib_subject, $lib_body );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: Devolución registrada
    // ─────────────────────────────────────────────────────────────

    /**
     * Envía confirmación de devolución al lector.
     *
     * @param int   $loan_id
     * @param float $fine  Multa aplicada (0 si ninguna).
     */
    public static function send_return_confirmation( int $loan_id, float $fine = 0.0 ): void {
        if ( ! get_option( 'aura_library_email_alerts', true ) ) {
            return;
        }

        $loan = self::get_loan_data( $loan_id );
        if ( ! $loan ) {
            return;
        }

        $org     = aura_get_org_name();
        $subject = sprintf( __( 'Devolución registrada — %s', 'aura-business-suite' ), $org );

        $fine_line = $fine > 0
            ? '<li>' . sprintf( __( 'Multa aplicada: %s', 'aura-business-suite' ), number_format( $fine, 2 ) ) . '</li>'
            : '<li>' . __( 'Sin multa aplicada.', 'aura-business-suite' ) . '</li>';

        $body = sprintf(
            '<p>' . __( 'Estimado/a %s,', 'aura-business-suite' ) . '</p>
            <p>' . __( 'La devolución del libro <strong>%s</strong> ha sido registrada.', 'aura-business-suite' ) . '</p>
            <ul>
                <li>' . __( 'Devuelto el: %s', 'aura-business-suite' ) . '</li>
                %s
            </ul>
            <p>' . __( '¡Gracias por devolver el libro a tiempo!', 'aura-business-suite' ) . '</p>',
            esc_html( $loan['borrower_name'] ),
            esc_html( $loan['book_title'] ),
            esc_html( $loan['return_date'] ?? gmdate( 'Y-m-d' ) ),
            $fine_line
        );

        if ( $loan['borrower_email'] ) {
            self::send_email( $loan['borrower_email'], $subject, $body );
        }

        if ( $loan['borrower_user_id'] ) {
            Aura_Notifications::create_notification(
                $loan['borrower_user_id'],
                sprintf( __( 'Devolución registrada: %s', 'aura-business-suite' ), $loan['book_title'] ),
                'success',
                'library'
            );
        }

        // WhatsApp al lector
        $signature = get_option( 'aura_whatsapp_signature', aura_get_org_name() );
        $wa_phone  = self::get_user_phone( $loan['borrower_user_id'] ?? 0 );
        if ( $wa_phone ) {
            $wa_msg = $fine > 0
                ? sprintf(
                    __( "Hola %s, hemos recibido *%s* correctamente. Se aplicó una multa de *%s*.\n_%s_", 'aura-business-suite' ),
                    $loan['borrower_name'], $loan['book_title'], number_format( $fine, 2 ), $signature
                )
                : sprintf(
                    __( "Hola %s, hemos recibido *%s* correctamente. ¡Gracias!\n_%s_", 'aura-business-suite' ),
                    $loan['borrower_name'], $loan['book_title'], $signature
                );
            self::send_whatsapp( $wa_phone, $wa_msg );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL + WHATSAPP: Reserva disponible
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al usuario que su reserva está disponible.
     *
     * @param int $reservation_id
     */
    public static function send_reservation_available( int $reservation_id ): void {
        if ( ! get_option( 'aura_library_email_alerts', true ) ) {
            return;
        }

        global $wpdb;
        $t_res   = $wpdb->prefix . 'aura_library_reservations';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $res = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*, b.title AS book_title, b.location
             FROM {$t_res} r
             LEFT JOIN {$t_books} b ON b.id = r.book_id
             WHERE r.id = %d",
            $reservation_id
        ) );

        if ( ! $res ) {
            return;
        }

        $user  = get_user_by( 'id', $res->user_id );
        if ( ! $user ) {
            return;
        }

        $org          = aura_get_org_name();
        $org_name     = $org;
        $user_name    = $user->display_name;
        $user_email   = $user->user_email;
        $user_phone   = self::get_user_phone( (int) $res->user_id );
        $book_title   = $res->book_title ?? __( 'libro reservado', 'aura-business-suite' );
        $expires_date = $res->expires_at ? substr( $res->expires_at, 0, 10 ) : '';

        $subject = sprintf( __( 'Tu reserva está disponible — %s', 'aura-business-suite' ), $org_name );
        $body = sprintf(
            '<p>' . __( 'Estimado/a %s,', 'aura-business-suite' ) . '</p>
            <p>' . __( 'El libro <strong>%s</strong> que reservaste está disponible para recoger.', 'aura-business-suite' ) . '</p>
            <ul>
                <li>' . __( 'Ubicación: %s', 'aura-business-suite' ) . '</li>
                %s
            </ul>
            <p>' . __( 'Acércate a recogerlo antes de que la reserva expire.', 'aura-business-suite' ) . '</p>',
            esc_html( $user_name ),
            esc_html( $book_title ),
            esc_html( $res->location ?? '' ),
            $expires_date ? '<li>' . sprintf( __( 'La reserva expira el: %s', 'aura-business-suite' ), esc_html( $expires_date ) ) . '</li>' : ''
        );

        self::send_email( $user_email, $subject, $body );

        $wa_msg = sprintf(
            __( "[%s] Tu reserva del libro «%s» está disponible. Recógelo a la brevedad.", 'aura-business-suite' ),
            $org_name,
            $book_title
        );
        if ( $user_phone ) {
            self::send_whatsapp( $user_phone, $wa_msg );
        }

        Aura_Notifications::create_notification(
            (int) $res->user_id,
            sprintf( __( 'Tu reserva está disponible: %s', 'aura-business-suite' ), $book_title ),
            'info',
            'library'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: Reserva expirada
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al usuario que su reserva ha expirado.
     *
     * @param int $reservation_id
     */
    public static function send_reservation_expired( int $reservation_id ): void {
        if ( ! get_option( 'aura_library_email_alerts', true ) ) {
            return;
        }

        global $wpdb;
        $t_res   = $wpdb->prefix . 'aura_library_reservations';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $res = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*, b.title AS book_title
             FROM {$t_res} r
             LEFT JOIN {$t_books} b ON b.id = r.book_id
             WHERE r.id = %d",
            $reservation_id
        ) );

        if ( ! $res ) {
            return;
        }

        $user = get_user_by( 'id', $res->user_id );
        if ( ! $user ) {
            return;
        }

        $org        = aura_get_org_name();
        $book_title = $res->book_title ?? __( 'libro reservado', 'aura-business-suite' );
        $subject    = sprintf( __( 'Tu reserva ha expirado — %s', 'aura-business-suite' ), $org );

        $body = sprintf(
            '<p>' . __( 'Estimado/a %s,', 'aura-business-suite' ) . '</p>
            <p>' . __( 'Lamentablemente, tu reserva del libro <strong>%s</strong> ha expirado porque no fue recogida a tiempo.', 'aura-business-suite' ) . '</p>
            <p>' . __( 'Si todavía necesitas el libro puedes realizar una nueva reserva.', 'aura-business-suite' ) . '</p>',
            esc_html( $user->display_name ),
            esc_html( $book_title )
        );

        self::send_email( $user->user_email, $subject, $body );

        Aura_Notifications::create_notification(
            (int) $res->user_id,
            sprintf( __( 'Tu reserva expiró: %s', 'aura-business-suite' ), $book_title ),
            'warning',
            'library'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene los datos necesarios para enviar notificaciones de préstamo.
     *
     * @param  int        $loan_id
     * @return array|null
     */
    private static function get_loan_data( int $loan_id ): ?array {
        global $wpdb;
        $t_loans = $wpdb->prefix . 'aura_library_loans';
        $t_books = $wpdb->prefix . 'aura_library_books';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT l.*, b.title AS book_title, b.location,
                    DATEDIFF(NOW(), l.due_date) AS days_overdue
             FROM {$t_loans} l
             LEFT JOIN {$t_books} b ON b.id = l.book_id
             WHERE l.id = %d",
            $loan_id
        ) );

        if ( ! $row ) {
            return null;
        }

        $user = get_user_by( 'id', $row->borrower_user_id );

        return [
            'loan_id'          => (int) $row->id,
            'book_title'       => $row->book_title ?? '',
            'location'         => $row->location ?? '',
            'loan_date'        => $row->loan_date ?? '',
            'due_date'         => $row->due_date ?? '',
            'return_date'      => $row->return_date ?? null,
            'days_overdue'     => max( 0, (int) $row->days_overdue ),
            'borrower_user_id' => (int) $row->borrower_user_id,
            'borrower_name'    => $user ? $user->display_name : __( 'Lector', 'aura-business-suite' ),
            'borrower_email'   => $user ? $user->user_email : '',
            'borrower_phone'   => $user ? self::get_user_phone( (int) $row->borrower_user_id ) : '',
        ];
    }

    /**
     * Obtiene el teléfono/WhatsApp del usuario desde user_meta o tabla de estudiantes.
     *
     * @param  int    $user_id
     * @return string Teléfono o cadena vacía.
     */
    private static function get_user_phone( int $user_id ): string {
        // Intentar campo 'whatsapp' primero (definido en módulo de Estudiantes)
        $phone = get_user_meta( $user_id, 'whatsapp', true );
        if ( ! $phone ) {
            $phone = get_user_meta( $user_id, 'phone', true );
        }
        // Buscar en tabla de estudiantes si existe
        if ( ! $phone ) {
            global $wpdb;
            $t_students = $wpdb->prefix . 'aura_students';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$t_students}'" ) === $t_students ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $phone = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(whatsapp, phone) FROM {$t_students} WHERE user_id = %d LIMIT 1",
                    $user_id
                ) );
            }
        }
        return (string) ( $phone ?? '' );
    }

    /**
     * Envía un email HTML.
     *
     * @param string $to
     * @param string $subject
     * @param string $body  Contenido HTML del cuerpo (sin envolver en template).
     */
    private static function send_email( string $to, string $subject, string $body ): void {
        if ( ! is_email( $to ) ) {
            return;
        }

        $html = self::get_html_template( $subject, $body );

        add_filter( 'wp_mail_content_type', [ 'Aura_Notifications', 'set_html_content_type' ] );
        wp_mail( $to, $subject, $html );
        remove_filter( 'wp_mail_content_type', [ 'Aura_Notifications', 'set_html_content_type' ] );
    }

    /**
     * Envía un mensaje de WhatsApp usando el proveedor global de Aura.
     *
     * @param string $phone
     * @param string $message
     */
    private static function send_whatsapp( string $phone, string $message ): void {
        if ( ! $phone ) {
            return;
        }
        if ( class_exists( 'Aura_Notifications' ) && method_exists( 'Aura_Notifications', 'send_whatsapp' ) ) {
            // aura_whatsapp_enabled debe estar activo para que se envíe.
            Aura_Notifications::send_whatsapp( $phone, $message );
        }
    }

    /**
     * Genera un template HTML básico para emails de Biblioteca.
     *
     * @param string $title
     * @param string $body
     * @return string
     */
    private static function get_html_template( string $title, string $body ): string {
        $org      = esc_html( aura_get_org_name() );
        $year     = gmdate( 'Y' );
        $site_url = esc_url( home_url() );
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$title}</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;">
    <div style="background:#2271b1;padding:20px 30px;">
      <h1 style="color:#fff;margin:0;font-size:20px;">{$org} — Biblioteca</h1>
    </div>
    <div style="padding:30px;">
      {$body}
    </div>
    <div style="background:#f0f0f0;padding:15px 30px;font-size:12px;color:#666;text-align:center;">
      &copy; {$year} <a href="{$site_url}" style="color:#2271b1;">{$org}</a>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
