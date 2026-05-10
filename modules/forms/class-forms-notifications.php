<?php
/**
 * Notifications del Módulo de Formularios
 *
 * Envía emails relacionados con el ciclo de vida de los formularios:
 *  - Nueva submission genérica → admin
 *  - Inscripción recibida      → postulante + admin
 *  - Encuesta asignada         → estudiante
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Notifications {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Nueva submission → notificar admin (FASE 3)
        add_action( 'aura_form_submission_saved',    [ __CLASS__, 'on_submission_saved' ],    20, 2 );
        // Enrollment recibido → notificar postulante (FASE 4)
        add_action( 'aura_form_enrollment_submitted', [ __CLASS__, 'on_enrollment_submitted' ], 10, 3 );
    }

    // ─────────────────────────────────────────────────────────────
    // HOOK LISTENERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Disparado por Aura_Forms_Submissions::ajax_submit() después de guardar.
     * Para formularios de tipo 'enrollment', el hook aura_form_enrollment_submitted
     * ya maneja la notificación al postulante desde on_enrollment_submitted().
     *
     * @param int $submission_id
     * @param int $form_id
     */
    public static function on_submission_saved( int $submission_id, int $form_id ): void {
        global $wpdb;

        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT type, title, notify_admin_emails FROM {$wpdb->prefix}aura_forms WHERE id = %d",
                $form_id
            )
        );

        if ( ! $form ) return;

        // Solo notificar admin en formularios genéricos y de encuesta
        // (los de inscripción tienen su propio flow en on_enrollment_submitted)
        if ( in_array( $form->type, [ 'generic', 'survey', 'feedback' ], true ) ) {
            self::notify_admin_new_submission( $submission_id );
        }
    }

    /**
     * Disparado por Aura_Forms_Enrollment cuando se crea el enrollment pendiente.
     *
     * @param int $submission_id
     * @param int $form_id
     * @param int $enrollment_id  ID en aura_student_enrollments (o 0 si falla)
     */
    public static function on_enrollment_submitted( int $submission_id, int $form_id, int $enrollment_id ): void {
        self::notify_enrollment_received( $submission_id );
        self::notify_admin_new_submission( $submission_id );
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: ADMIN — NUEVA SUBMISSION
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al admin sobre una nueva respuesta recibida.
     *
     * @param int $submission_id
     */
    public static function notify_admin_new_submission( int $submission_id ): void {
        global $wpdb;

        $sub = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.*, f.title AS form_title, f.type AS form_type, f.notify_admin_emails
                   FROM {$wpdb->prefix}aura_form_submissions s
                   JOIN {$wpdb->prefix}aura_forms f ON f.id = s.form_id
                  WHERE s.id = %d",
                $submission_id
            )
        );

        if ( ! $sub ) return;

        // Determinar destinatario: override por formulario o settings globales
        $to_raw = $sub->notify_admin_emails
            ? $sub->notify_admin_emails
            : Aura_Forms_Settings::get( 'admin_notification_email', get_option( 'admin_email' ) );

        $to_list = array_filter( array_map( 'sanitize_email', explode( ',', $to_raw ) ) );
        if ( empty( $to_list ) ) return;

        $site_name   = get_bloginfo( 'name' );
        $type_labels = [
            'generic'    => __( 'Genérico',       'aura-suite' ),
            'enrollment' => __( 'Inscripción',    'aura-suite' ),
            'survey'     => __( 'Encuesta',       'aura-suite' ),
            'feedback'   => __( 'Feedback auto.', 'aura-suite' ),
        ];
        $type_label = $type_labels[ $sub->form_type ] ?? $sub->form_type;

        /* translators: 1: Site name, 2: Form title, 3: From type */
        $subject = sprintf(
            __( '[%1$s] Nueva respuesta en "%2$s" (%3$s)', 'aura-suite' ),
            $site_name,
            $sub->form_title,
            $type_label
        );

        $detail_url = admin_url(
            'admin.php?page=aura-forms-list&action=view-submission&sub_id=' . $submission_id . '&form_id=' . $sub->form_id
        );

        $name  = $sub->submitted_name  ? $sub->submitted_name  : __( '(anónimo)', 'aura-suite' );
        $email = $sub->submitted_email ? $sub->submitted_email : '—';
        $date  = wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $sub->submitted_at ) );

        $message  = sprintf( __( 'Se ha recibido una nueva respuesta en el formulario "%s".', 'aura-suite' ), $sub->form_title ) . "\n\n";
        $message .= __( 'Detalles:', 'aura-suite' ) . "\n";
        $message .= sprintf( __( 'Nombre: %s', 'aura-suite' ), $name )  . "\n";
        $message .= sprintf( __( 'Email: %s',  'aura-suite' ), $email ) . "\n";
        $message .= sprintf( __( 'Fecha: %s',  'aura-suite' ), $date )  . "\n\n";
        $message .= sprintf( __( 'Ver detalle: %s', 'aura-suite' ), $detail_url ) . "\n";

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        foreach ( $to_list as $to ) {
            wp_mail( $to, $subject, $message, $headers );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: POSTULANTE — INSCRIPCIÓN RECIBIDA
    // ─────────────────────────────────────────────────────────────

    /**
     * Confirma al postulante que su inscripción fue recibida.
     *
     * @param int $submission_id
     */
    public static function notify_enrollment_received( int $submission_id ): void {
        global $wpdb;

        $sub = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT s.submitted_email, s.submitted_name, s.submitted_at,
                        f.title AS form_title, f.success_message
                   FROM {$wpdb->prefix}aura_form_submissions s
                   JOIN {$wpdb->prefix}aura_forms f ON f.id = s.form_id
                  WHERE s.id = %d",
                $submission_id
            )
        );

        if ( ! $sub || ! $sub->submitted_email ) return;

        $site_name = get_bloginfo( 'name' );
        $first_name = $sub->submitted_name
            ? explode( ' ', $sub->submitted_name )[0]
            : __( 'participante', 'aura-suite' );

        /* translators: 1: Site name, 2: Form title */
        $subject = sprintf(
            __( '[%1$s] Postulación recibida: %2$s', 'aura-suite' ),
            $site_name,
            $sub->form_title
        );

        $message  = sprintf( __( 'Hola %s,', 'aura-suite' ), $first_name ) . "\n\n";
        $message .= sprintf( __( 'Hemos recibido tu postulación para "%s".', 'aura-suite' ), $sub->form_title ) . "\n";
        $message .= __( 'Revisaremos tu solicitud y te contactaremos a la brevedad.', 'aura-suite' ) . "\n\n";
        $message .= sprintf( __( 'Fecha de recepción: %s', 'aura-suite' ), wp_date( get_option( 'date_format' ) . ' H:i', strtotime( $sub->submitted_at ) ) ) . "\n\n";
        $message .= '— ' . $site_name;

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        wp_mail( $sub->submitted_email, $subject, $message, $headers );
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: ESTUDIANTE — ENCUESTA ASIGNADA
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al estudiante cuando se le asigna una encuesta.
     * Llamado directamente desde Aura_Forms_Assignments::create_assignment()
     * cuando el trigger es 'manual'.
     *
     * @param int $assignment_id
     */
    public static function notify_student_assignment( int $assignment_id ): void {
        global $wpdb;

        $assignment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.student_id, a.expires_at,
                        f.title AS form_title, f.slug AS form_slug
                   FROM {$wpdb->prefix}aura_form_assignments a
                   JOIN {$wpdb->prefix}aura_forms f ON f.id = a.form_id
                  WHERE a.id = %d",
                $assignment_id
            )
        );

        if ( ! $assignment ) return;

        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT first_name, last_name, email FROM {$wpdb->prefix}aura_students WHERE id = %d",
                $assignment->student_id
            )
        );

        if ( ! $student || ! $student->email ) return;

        $to         = sanitize_email( $student->email );
        $site_name  = get_bloginfo( 'name' );
        $nombre     = sanitize_text_field( $student->first_name );
        $form_url   = Aura_Forms_Frontend::get_form_url( $assignment->form_slug );
        $portal_url = Aura_Forms_Frontend::get_portal_url();
        $expira     = $assignment->expires_at
            ? wp_date( get_option( 'date_format' ), strtotime( $assignment->expires_at ) )
            : __( 'sin fecha límite', 'aura-suite' );

        // Obtener plantilla de email desde settings
        $subject_tpl = Aura_Forms_Settings::get( 'assignment_email_subject' )
            ?: __( 'Tienes una encuesta pendiente', 'aura-suite' );
        $body_tpl    = Aura_Forms_Settings::get( 'assignment_email_body' )
            ?: __( "Hola {nombre},\n\nSe te ha asignado una nueva encuesta: {formulario}.\n\nPuedes completarla aquí: {url}\n\nFecha límite: {expira}", 'aura-suite' );

        // Reemplazar placeholders
        $replacements = [
            '{nombre}'     => $nombre,
            '{formulario}' => $assignment->form_title,
            '{url}'        => $form_url,
            '{portal}'     => $portal_url,
            '{expira}'     => $expira,
            '{sitio}'      => $site_name,
        ];

        $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject_tpl );
        $message = str_replace( array_keys( $replacements ), array_values( $replacements ), wp_strip_all_tags( $body_tpl ) );

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        wp_mail( $to, $subject, $message, $headers );
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: POSTULANTE — INSCRIPCIÓN APROBADA
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al postulante que su inscripción fue aprobada.
     * Nota: Aura_Forms_Enrollment ya delega en Aura_Students_Notifications::send_approval_email()
     * cuando ésta existe. Este método es un respaldo adicional para el módulo de formularios.
     *
     * @param int $enrollment_id  ID en aura_student_enrollments
     */
    public static function notify_enrollment_approved( int $enrollment_id ): void {
        // Delegado a Aura_Students_Notifications en class-forms-enrollment.php
        // Este método se reserva para lógica adicional específica de formularios.
    }

    // ─────────────────────────────────────────────────────────────
    // EMAIL: POSTULANTE — INSCRIPCIÓN RECHAZADA
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifica al postulante que su inscripción fue rechazada.
     * Nota: Aura_Forms_Enrollment ya delega en Aura_Students_Notifications::send_rejection_email()
     * cuando ésta existe.
     *
     * @param int    $submission_id
     * @param string $reason
     */
    public static function notify_enrollment_rejected( int $submission_id, string $reason ): void {
        // Delegado a Aura_Students_Notifications en class-forms-enrollment.php
        // Este método se reserva para lógica adicional específica de formularios.
    }
}
