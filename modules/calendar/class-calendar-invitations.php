<?php
/**
 * Servicio de Generación de Invitaciones de Calendario (.ics) y Enlaces para Profesores
 *
 * Soporta:
 * - Generador estándar RFC 5545 (.ics) compatible con Google Calendar, Outlook, Apple Calendar y Thunderbird.
 * - Enlaces directos pre-llenados "Agregar a Google Calendar", "Agregar a Outlook Web" y "Office 365".
 * - Envío de invitaciones formales por correo electrónico con archivo .ics adjunto y botones de acción.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Invitations {

    /**
     * Inicializar hooks y endpoints AJAX
     */
    public static function init(): void {
        // Descarga de archivo .ics (disponible para usuarios logueados y vía enlace seguro firmado)
        add_action( 'wp_ajax_aura_cal_download_ics',        [ __CLASS__, 'ajax_download_ics' ] );
        add_action( 'wp_ajax_nopriv_aura_cal_download_ics', [ __CLASS__, 'ajax_download_ics' ] );

        // Obtener enlaces de "Agregar a calendario"
        add_action( 'wp_ajax_aura_cal_get_calendar_links',  [ __CLASS__, 'ajax_get_calendar_links' ] );

        // Enviar invitación por correo electrónico
        add_action( 'wp_ajax_aura_cal_send_invitation_email', [ __CLASS__, 'ajax_send_invitation_email' ] );
    }

    /**
     * Generar contenido estándar iCalendar RFC 5545 (.ics)
     *
     * @param int  $event_id           ID del evento.
     * @param bool $include_attendees  Si se deben incluir las directivas ATTENDEE de los profesores.
     * @param string $method           Método iCalendar (REQUEST para invitaciones, PUBLISH para agendas estáticas).
     * @return string|null Contenido en formato .ics o null si el evento no existe.
     */
    public static function generate_ics( int $event_id, bool $include_attendees = true, string $method = 'REQUEST' ): ?string {
        if ( ! class_exists( 'Aura_Calendar_Events' ) ) {
            return null;
        }

        $event = Aura_Calendar_Events::get( $event_id );
        if ( ! $event ) {
            return null;
        }

        $tz_string = wp_timezone_string();
        try {
            $site_tz = new DateTimeZone( $tz_string );
        } catch ( Exception $e ) {
            $site_tz = new DateTimeZone( 'UTC' );
        }

        $utc_tz = new DateTimeZone( 'UTC' );

        // Fechas
        $start_dt = new DateTimeImmutable( $event->start_datetime, $site_tz );
        $end_dt   = new DateTimeImmutable( $event->end_datetime, $site_tz );

        // Detección de Todo el Día
        $start_date_str = substr( $event->start_datetime, 0, 10 );
        $end_date_str   = substr( $event->end_datetime, 0, 10 );
        $start_time_str = substr( $event->start_datetime, 11, 5 );
        $end_time_str   = substr( $event->end_datetime, 11, 5 );
        $duration_secs  = max( 0, $end_dt->getTimestamp() - $start_dt->getTimestamp() );

        $is_multi_day     = ( $start_date_str !== $end_date_str );
        $is_all_day_hours = ( $duration_secs >= 86400 || ( $start_time_str === '00:00' && ( $end_time_str === '00:00' || $end_time_str === '23:59' || $duration_secs >= 82800 ) ) );
        $is_all_day       = ( $is_all_day_hours || $is_multi_day );

        if ( $is_all_day ) {
            $dtstart = 'DTSTART;VALUE=DATE:' . $start_dt->format( 'Ymd' );
            // En RFC 5545, DTEND para VALUE=DATE es exclusivo (día posterior al término)
            $next_day = $end_dt->modify( '+1 day' );
            $dtend   = 'DTEND;VALUE=DATE:' . $next_day->format( 'Ymd' );
        } else {
            // Conversión a UTC con sufijo Z
            $start_utc = $start_dt->setTimezone( $utc_tz );
            $end_utc   = $end_dt->setTimezone( $utc_tz );
            $dtstart   = 'DTSTART:' . $start_utc->format( 'Ymd\THis\Z' );
            $dtend     = 'DTEND:' . $end_utc->format( 'Ymd\THis\Z' );
        }

        $now_utc   = ( new DateTimeImmutable( 'now', $utc_tz ) )->format( 'Ymd\THis\Z' );
        $site_name = get_bloginfo( 'name' ) ?: 'Centro Mateo';
        $site_url  = home_url();
        $domain    = wp_parse_url( $site_url, PHP_URL_HOST ) ?: 'centromateo.org';
        $admin_em  = get_option( 'admin_email' ) ?: ( 'noreply@' . $domain );

        // UID único y persistente
        $uid = 'aura-cal-' . $event_id . '-' . md5( $event->created_at . $event->id ) . '@' . $domain;

        // Título del evento
        $summary = $event->title;
        if ( ! empty( $event->subject_name ) ) {
            $summary = $event->subject_name . ' — ' . $summary;
        }

        // Descripción enriquecida
        $desc_parts = [];
        if ( ! empty( $event->program_name ) ) {
            $desc_parts[] = 'Programa: ' . $event->program_name;
        }
        if ( ! empty( $event->subject_name ) ) {
            $subj_line = 'Materia: ' . $event->subject_name;
            if ( ! empty( $event->module_name ) ) {
                $subj_line .= ' (' . $event->module_name . ')';
            }
            $desc_parts[] = $subj_line;
        }
        if ( ! empty( $event->instructors ) ) {
            $inst_names = array_map( function( $i ) {
                $i_arr = (array) $i;
                $role  = ( isset( $i_arr['role'] ) && $i_arr['role'] === 'lead' ) ? 'Titular' : 'Docente';
                $name  = $i_arr['name'] ?? 'Profesor';
                if ( ! empty( $i_arr['is_external'] ) && ! empty( $i_arr['external_org'] ) ) {
                    $name .= ' [' . $i_arr['external_org'] . ']';
                }
                return $name . ' (' . $role . ')';
            }, (array) $event->instructors );
            $desc_parts[] = 'Profesor(es): ' . implode( ', ', $inst_names );
        }
        if ( ! empty( $event->student_leaders ) ) {
            $ldr_names = array_map( function( $l ) {
                $l_arr = (array) $l;
                return ( $l_arr['name'] ?? '' ) . ' (' . ( $l_arr['role_label'] ?? 'Líder' ) . ')';
            }, (array) $event->student_leaders );
            $desc_parts[] = 'Líderes Estudiantiles: ' . implode( ', ', $ldr_names );
        }
        if ( ! empty( $event->location ) ) {
            $desc_parts[] = 'Aula / Salón: ' . $event->location;
        }
        if ( ! empty( $event->online_url ) ) {
            $desc_parts[] = 'Sesión Virtual: ' . $event->online_url;
        }
        if ( ! empty( $event->description ) ) {
            $desc_parts[] = "\nDetalles y Temario:\n" . $event->description;
        }
        $desc_parts[] = "\nPortal Académico: " . $site_url;

        $description = implode( "\n", $desc_parts );

        // Ubicación
        $location = ! empty( $event->location ) ? $event->location : ( ! empty( $event->online_url ) ? $event->online_url : $site_name );

        // Construcción de líneas iCalendar
        $lines = [
            'BEGIN:VCALENDAR',
            'PRODID:-//Aura Business Suite//Calendar Events//ES',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:' . strtoupper( $method ),
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $now_utc,
            'CREATED:' . $now_utc,
            'LAST-MODIFIED:' . $now_utc,
            $dtstart,
            $dtend,
            'SUMMARY:' . self::escape_ics_text( $summary ),
            'DESCRIPTION:' . self::escape_ics_text( $description ),
            'LOCATION:' . self::escape_ics_text( $location ),
            'STATUS:' . ( $event->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED' ),
            'SEQUENCE:0',
            'TRANSP:OPAQUE',
            'ORGANIZER;CN="' . self::escape_ics_text( $site_name ) . '":MAILTO:' . $admin_em,
        ];

        if ( ! empty( $event->online_url ) ) {
            $lines[] = 'URL:' . $event->online_url;
        }

        // Asistentes / Instructores
        if ( $include_attendees && ! empty( $event->instructors ) ) {
            foreach ( $event->instructors as $inst ) {
                $inst_arr   = (array) $inst;
                $inst_email = $inst_arr['email'] ?? '';
                if ( empty( $inst_email ) || ! is_email( $inst_email ) ) {
                    continue;
                }
                $inst_name = $inst_arr['name'] ?? 'Profesor';
                $role      = ( isset( $inst_arr['role'] ) && $inst_arr['role'] === 'lead' ) ? 'REQ-PARTICIPANT' : 'OPT-PARTICIPANT';
                $lines[]   = 'ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=' . $role . ';PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN="' . self::escape_ics_text( $inst_name ) . '":MAILTO:' . $inst_email;
            }
        }

        // Alarma / Recordatorio 30 minutos antes
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'TRIGGER:-PT30M';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:Recordatorio: ' . self::escape_ics_text( $summary );
        $lines[] = 'END:VALARM';

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // Plegado de líneas a 75 octetos según RFC 5545
        $folded_lines = [];
        foreach ( $lines as $line ) {
            $folded_lines[] = self::fold_ics_line( $line );
        }

        return implode( "\r\n", $folded_lines ) . "\r\n";
    }

    /**
     * Escapar caracteres especiales para iCalendar RFC 5545
     */
    private static function escape_ics_text( string $text ): string {
        $text = str_replace( '\\', '\\\\', $text );
        $text = str_replace( ';', '\;', $text );
        $text = str_replace( ',', '\,', $text );
        $text = str_replace( ["\r\n", "\n", "\r"], '\n', $text );
        return $text;
    }

    /**
     * Plegar líneas a 75 bytes según especificación RFC 5545
     */
    private static function fold_ics_line( string $line ): string {
        if ( strlen( $line ) <= 75 ) {
            return $line;
        }

        $result = '';
        $len    = strlen( $line );
        $first  = true;

        while ( $len > 0 ) {
            $chunk_size = $first ? 75 : 74;
            $chunk      = substr( $line, 0, $chunk_size );
            $line       = substr( $line, $chunk_size );
            $len        = strlen( $line );

            if ( $first ) {
                $result .= $chunk;
                $first   = false;
            } else {
                $result .= "\r\n " . $chunk;
            }
        }

        return $result;
    }

    /**
     * Generar enlaces directos para agregar a proveedores de calendario
     *
     * @param int $event_id ID del evento
     * @return array Enlaces de Google Calendar, Outlook Web, Office 365 y descarga .ics
     */
    public static function get_calendar_links( int $event_id ): array {
        if ( ! class_exists( 'Aura_Calendar_Events' ) ) {
            return [];
        }

        $event = Aura_Calendar_Events::get( $event_id );
        if ( ! $event ) {
            return [];
        }

        $tz_string = wp_timezone_string();
        try {
            $site_tz = new DateTimeZone( $tz_string );
        } catch ( Exception $e ) {
            $site_tz = new DateTimeZone( 'UTC' );
        }
        $utc_tz = new DateTimeZone( 'UTC' );

        $start_dt = new DateTimeImmutable( $event->start_datetime, $site_tz );
        $end_dt   = new DateTimeImmutable( $event->end_datetime, $site_tz );

        $start_utc = $start_dt->setTimezone( $utc_tz );
        $end_utc   = $end_dt->setTimezone( $utc_tz );

        // Detección allDay
        $start_date_str = substr( $event->start_datetime, 0, 10 );
        $end_date_str   = substr( $event->end_datetime, 0, 10 );
        $duration_secs  = max( 0, $end_dt->getTimestamp() - $start_dt->getTimestamp() );
        $is_all_day     = ( $start_date_str !== $end_date_str || $duration_secs >= 82800 );

        // Título y descripción
        $title = $event->title;
        if ( ! empty( $event->subject_name ) ) {
            $title = $event->subject_name . ' — ' . $title;
        }

        $location = ! empty( $event->location ) ? $event->location : ( ! empty( $event->online_url ) ? $event->online_url : get_bloginfo( 'name' ) );

        $details_parts = [];
        if ( ! empty( $event->program_name ) ) {
            $details_parts[] = '🎓 Programa: ' . $event->program_name;
        }
        if ( ! empty( $event->subject_name ) ) {
            $details_parts[] = '📚 Materia: ' . $event->subject_name;
        }
        if ( ! empty( $event->location ) ) {
            $details_parts[] = '📍 Aula: ' . $event->location;
        }
        if ( ! empty( $event->online_url ) ) {
            $details_parts[] = '💻 Sesión Virtual: ' . $event->online_url;
        }
        if ( ! empty( $event->description ) ) {
            $details_parts[] = "\nDetalles:\n" . $event->description;
        }
        $details_parts[] = "\nPortal: " . home_url();
        $details = implode( "\n", $details_parts );

        // Lista de emails de instructores para auto-añadir en Google Calendar
        $inst_emails = [];
        if ( ! empty( $event->instructors ) ) {
            foreach ( $event->instructors as $ins ) {
                $ins_arr = (array) $ins;
                $i_em    = $ins_arr['email'] ?? '';
                if ( ! empty( $i_em ) && is_email( $i_em ) ) {
                    $inst_emails[] = $i_em;
                }
            }
        }

        // 1. Google Calendar Link
        if ( $is_all_day ) {
            $gcal_dates = $start_dt->format( 'Ymd' ) . '/' . $end_dt->modify( '+1 day' )->format( 'Ymd' );
        } else {
            $gcal_dates = $start_utc->format( 'Ymd\THis\Z' ) . '/' . $end_utc->format( 'Ymd\THis\Z' );
        }

        $gcal_params = [
            'action'   => 'TEMPLATE',
            'text'     => $title,
            'dates'    => $gcal_dates,
            'details'  => $details,
            'location' => $location,
        ];
        if ( ! empty( $inst_emails ) ) {
            $gcal_params['add'] = implode( ',', $inst_emails );
        }
        $google_link = 'https://calendar.google.com/calendar/render?' . http_build_query( $gcal_params );

        // 2. Outlook Web (Personal) & Office 365 (Empresarial/Educativo)
        $start_iso = $start_utc->format( 'Y-m-d\TH:i:s\Z' );
        $end_iso   = $end_utc->format( 'Y-m-d\TH:i:s\Z' );

        $outlook_params = [
            'path'     => '/calendar/action/compose',
            'rru'      => 'addevent',
            'subject'  => $title,
            'startdt'  => $start_iso,
            'enddt'    => $end_iso,
            'body'     => $details,
            'location' => $location,
        ];
        if ( $is_all_day ) {
            $outlook_params['allday'] = 'true';
        }

        $outlook_live_link = 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query( $outlook_params );
        $outlook_365_link  = 'https://outlook.office.com/calendar/0/deeplink/compose?' . http_build_query( $outlook_params );

        // 3. Descarga directa de archivo .ics firmado
        $nonce    = wp_create_nonce( 'aura_cal_ics_' . $event_id );
        $ics_link = add_query_arg( [
            'action'   => 'aura_cal_download_ics',
            'event_id' => $event_id,
            'token'    => $nonce,
        ], admin_url( 'admin-ajax.php' ) );

        return [
            'google'      => $google_link,
            'outlook'     => $outlook_live_link,
            'office365'   => $outlook_365_link,
            'ics_download'=> $ics_link,
            'title'       => $title,
            'date_label'  => $start_dt->format( 'd/m/Y' ),
            'time_label'  => $is_all_day ? __( 'Todo el día', 'aura' ) : ( $start_dt->format( 'H:i' ) . ' — ' . $end_dt->format( 'H:i' ) ),
        ];
    }

    /**
     * Enviar invitaciones por correo electrónico con archivo .ics adjunto
     *
     * @param int   $event_id          ID del evento
     * @param array $recipient_emails  Lista opcional de correos específicos (si está vacía, se envía a los instructores del evento)
     * @param string $custom_note      Mensaje o nota opcional agregada por el remitente
     * @return array Resumen del envío: ['sent' => count, 'failed' => count, 'recipients' => [...]]
     */
    public static function send_invitations_email( int $event_id, array $recipient_emails = [], string $custom_note = '' ): array {
        if ( ! class_exists( 'Aura_Calendar_Events' ) ) {
            return [ 'success' => false, 'message' => __( 'Módulo de eventos no disponible.', 'aura' ) ];
        }

        $event = Aura_Calendar_Events::get( $event_id );
        if ( ! $event ) {
            return [ 'success' => false, 'message' => __( 'Evento no encontrado.', 'aura' ) ];
        }

        // Recopilar destinatarios
        $recipients = [];
        if ( ! empty( $recipient_emails ) ) {
            foreach ( $recipient_emails as $em ) {
                if ( is_email( $em ) ) {
                    $recipients[] = [
                        'name'  => '',
                        'email' => sanitize_email( $em ),
                        'role'  => 'Instructor',
                    ];
                }
            }
        } else {
            // Obtener de los instructores asignados
            if ( ! empty( $event->instructors ) ) {
                foreach ( $event->instructors as $inst ) {
                    $inst_arr   = (array) $inst;
                    $em         = $inst_arr['email'] ?? '';
                    if ( ! empty( $em ) && is_email( $em ) ) {
                        $role_label = ( isset( $inst_arr['role'] ) && $inst_arr['role'] === 'lead' ) ? __( 'Profesor Titular', 'aura' ) : __( 'Docente / Expositor', 'aura' );
                        $recipients[] = [
                            'name'  => $inst_arr['name'] ?? '',
                            'email' => sanitize_email( $em ),
                            'role'  => $role_label,
                        ];
                    }
                }
            }

            // Fallback al creador del evento si no hay profesores asignados
            if ( empty( $recipients ) && ! empty( $event->created_by ) ) {
                $creator = get_userdata( (int) $event->created_by );
                if ( $creator && is_email( $creator->user_email ) ) {
                    $recipients[] = [
                        'name'  => $creator->display_name,
                        'email' => $creator->user_email,
                        'role'  => __( 'Titular del Evento', 'aura' ),
                    ];
                }
            }
        }

        if ( empty( $recipients ) ) {
            return [
                'success' => false,
                'sent'    => 0,
                'failed'  => 0,
                'message' => __( 'No se encontraron correos electrónicos válidos para los profesores o instructores de este evento.', 'aura' ),
            ];
        }

        // Generar archivo .ics temporal
        $ics_content = self::generate_ics( $event_id, true, 'REQUEST' );
        if ( empty( $ics_content ) ) {
            return [ 'success' => false, 'message' => __( 'Error al generar el archivo de calendario .ics.', 'aura' ) ];
        }

        $upload_dir = wp_upload_dir();
        $temp_dir   = $upload_dir['basedir'] . '/aura-temp-ics';
        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
            file_put_contents( $temp_dir . '/index.php', '<?php // Silence' );
        }

        $slug      = sanitize_title( $event->title ?: 'invitacion-clase' );
        $temp_file = $temp_dir . '/' . $slug . '-' . $event_id . '-' . time() . '.ics';
        file_put_contents( $temp_file, $ics_content );

        $cal_links = self::get_calendar_links( $event_id );

        // Datos del correo
        $site_name = get_bloginfo( 'name' ) ?: 'Centro Mateo';
        $admin_em  = get_option( 'admin_email' );
        $subject   = sprintf( __( '📅 Invitación de Clase: %s', 'aura' ), $cal_links['title'] );

        $sent_count   = 0;
        $failed_count = 0;
        $sent_to      = [];

        // Encabezados MIME para invitar formalmente
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_em . '>',
        ];

        $attachments = [ $temp_file ];

        foreach ( $recipients as $recip ) {
            $to_email = $recip['email'];
            $to_name  = $recip['name'] ?: $to_email;

            $html_body = self::render_invitation_email_html( $event, $recip, $cal_links, $custom_note );

            $mail_sent = wp_mail( $to_email, $subject, $html_body, $headers, $attachments );
            if ( $mail_sent ) {
                $sent_count++;
                $sent_to[] = $to_email;
            } else {
                $failed_count++;
            }
        }

        // Limpiar archivo temporal
        if ( file_exists( $temp_file ) ) {
            @unlink( $temp_file );
        }

        return [
            'success'    => $sent_count > 0,
            'sent'       => $sent_count,
            'failed'     => $failed_count,
            'recipients' => $sent_to,
            'message'    => sprintf(
                __( 'Invitaciones procesadas: %d enviadas exitosamente%s.', 'aura' ),
                $sent_count,
                $failed_count > 0 ? sprintf( __( ', %d fallidas', 'aura' ), $failed_count ) : ''
            ),
        ];
    }

    /**
     * Renderizar plantilla HTML elegante para la invitación por correo electrónico
     */
    private static function render_invitation_email_html( object $event, array $recipient, array $cal_links, string $custom_note = '' ): string {
        $site_name = get_bloginfo( 'name' ) ?: 'Centro Mateo';
        $site_url  = home_url();

        $org_name = trim( (string) get_option( 'aura_org_name', '' ) ) ?: $site_name;

        // Tipo de evento badge
        $type_labels = [
            'class'    => __( '📖 Clase Académica', 'aura' ),
            'exam'     => __( '📝 Evaluación / Examen', 'aura' ),
            'workshop' => __( '🔬 Taller Práctico', 'aura' ),
            'activity' => __( '🎯 Actividad Institucional', 'aura' ),
            'break'    => __( '☕ Receso / Pausa', 'aura' ),
        ];
        $type_badge = $type_labels[ $event->event_type ] ?? __( '📍 Sesión de Calendario', 'aura' );

        $color = ! empty( $event->color ) ? $event->color : '#6366f1';

        // Fecha legible en español
        $tz_string = wp_timezone_string();
        try {
            $site_tz = new DateTimeZone( $tz_string );
        } catch ( Exception $e ) {
            $site_tz = new DateTimeZone( 'UTC' );
        }
        $start_dt = new DateTimeImmutable( $event->start_datetime, $site_tz );
        $end_dt   = new DateTimeImmutable( $event->end_datetime, $site_tz );

        // Nombres de días y meses en español
        $dias   = [ 'Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado' ];
        $meses  = [ 1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre' ];

        $dia_semana = $dias[ $start_dt->format( 'l' ) ] ?? $start_dt->format( 'l' );
        $mes_nombre = $meses[ (int) $start_dt->format( 'n' ) ] ?? $start_dt->format( 'F' );
        $fecha_txt  = $dia_semana . ', ' . $start_dt->format( 'j' ) . ' de ' . $mes_nombre . ' de ' . $start_dt->format( 'Y' );
        $hora_txt   = $start_dt->format( 'H:i' ) . ' — ' . $end_dt->format( 'H:i' ) . ' hrs';

        $custom_note_html = '';
        if ( ! empty( $custom_note ) ) {
            $custom_note_html = '
            <div style="margin: 20px 0; padding: 14px 18px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; font-size: 13.5px; color: #92400e; line-height: 1.5;">
                <strong>' . esc_html__( 'Nota de Coordinación:', 'aura' ) . '</strong><br>' . nl2br( esc_html( $custom_note ) ) . '
            </div>';
        }

        $online_button = '';
        if ( ! empty( $event->online_url ) ) {
            $online_button = '
            <div style="margin: 22px 0 10px 0; text-align: center;">
                <a href="' . esc_url( $event->online_url ) . '" target="_blank" style="background: #10b981; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px; display: inline-block; box-shadow: 0 4px 12px rgba(16,185,129,0.3);">
                    💻 ' . esc_html__( 'Unirse a la Sesión Virtual', 'aura' ) . '
                </a>
            </div>';
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $cal_links['title'] ); ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f1f5f9; padding: 30px 15px;">
        <tr>
            <td align="center">
                <!-- Tarjeta Principal del Correo -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); border: 1px solid #e2e8f0;">
                    
                    <!-- Header con Banner e Identidad Institucional -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 26px 30px; text-align: left; border-bottom: 4px solid <?php echo esc_attr( $color ); ?>;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: #94a3b8; display: block; margin-bottom: 4px;">
                                        <?php echo esc_html( $org_name ); ?> &bull; <?php esc_html_e( 'Portal Académico', 'aura' ); ?>
                                    </span>
                                    <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; line-height: 1.3;">
                                        <?php esc_html_e( 'Invitación a Sesión de Clase', 'aura' ); ?>
                                    </h1>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Contenido Principal -->
                    <tr>
                        <td style="padding: 30px;">
                            
                            <p style="margin: 0 0 16px 0; font-size: 15px; color: #334155; line-height: 1.5;">
                                <?php printf( esc_html__( 'Hola %s,', 'aura' ), '<strong>' . esc_html( $recipient['name'] ?: $recipient['email'] ) . '</strong>' ); ?>
                            </p>
                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.55;">
                                <?php esc_html_e( 'Has sido asignado como docente para la siguiente sesión programada en el calendario académico institucional. Adjuntamos el archivo de calendario (.ics) y los botones directos para incorporarla a tu agenda.', 'aura' ); ?>
                            </p>

                            <?php echo $custom_note_html; ?>

                            <!-- Tarjeta de Detalles del Evento -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <div style="margin-bottom: 12px;">
                                            <span style="display: inline-block; background-color: <?php echo esc_attr( $color ); ?>; color: #ffffff; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">
                                                <?php echo esc_html( $type_badge ); ?>
                                            </span>
                                        </div>

                                        <h2 style="margin: 0 0 14px 0; font-size: 17px; font-weight: 700; color: #0f172a; line-height: 1.35;">
                                            <?php echo esc_html( $cal_links['title'] ); ?>
                                        </h2>

                                        <!-- Filas de datos -->
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 13.5px; color: #334155;">
                                            <tr>
                                                <td width="24" style="vertical-align: top; padding: 6px 0;">📅</td>
                                                <td style="vertical-align: top; padding: 6px 0;"><strong><?php echo esc_html( $fecha_txt ); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td width="24" style="vertical-align: top; padding: 6px 0;">🕐</td>
                                                <td style="vertical-align: top; padding: 6px 0;"><strong><?php echo esc_html( $hora_txt ); ?></strong></td>
                                            </tr>
                                            <?php if ( ! empty( $event->location ) ) : ?>
                                            <tr>
                                                <td width="24" style="vertical-align: top; padding: 6px 0;">📍</td>
                                                <td style="vertical-align: top; padding: 6px 0;"><?php echo esc_html( $event->location ); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $event->program_name ) ) : ?>
                                            <tr>
                                                <td width="24" style="vertical-align: top; padding: 6px 0;">🎓</td>
                                                <td style="vertical-align: top; padding: 6px 0;"><?php echo esc_html( $event->program_name ); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $event->subject_name ) ) : ?>
                                            <tr>
                                                <td width="24" style="vertical-align: top; padding: 6px 0;">📚</td>
                                                <td style="vertical-align: top; padding: 6px 0;">
                                                    <?php echo esc_html( $event->subject_name ); ?>
                                                    <?php if ( ! empty( $event->module_name ) ) : ?>
                                                        <span style="color: #64748b; font-size: 12px;">(<?php echo esc_html( $event->module_name ); ?>)</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>

                                        <?php if ( ! empty( $event->description ) ) : ?>
                                        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #cbd5e1; font-size: 13px; color: #475569; line-height: 1.5; white-space: pre-wrap;">
                                            <?php echo esc_html( $event->description ); ?>
                                        </div>
                                        <?php endif; ?>

                                        <?php echo $online_button; ?>

                                    </td>
                                </tr>
                            </table>

                            <!-- Botones Directos de "Agregar a Calendario" -->
                            <div style="text-align: center; margin-bottom: 24px;">
                                <span style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; display: block; margin-bottom: 12px;">
                                    <?php esc_html_e( 'Agrega esta clase a tu calendario personal:', 'aura' ); ?>
                                </span>

                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center" style="margin: 0 auto;">
                                    <tr>
                                        <!-- Google Calendar -->
                                        <td style="padding: 4px;">
                                            <a href="<?php echo esc_url( $cal_links['google'] ); ?>" target="_blank" style="background-color: #4285f4; color: #ffffff; padding: 10px 16px; font-size: 12.5px; font-weight: 700; text-decoration: none; border-radius: 8px; display: inline-block;">
                                                📅 Google Calendar
                                            </a>
                                        </td>
                                        <!-- Outlook Web -->
                                        <td style="padding: 4px;">
                                            <a href="<?php echo esc_url( $cal_links['outlook'] ); ?>" target="_blank" style="background-color: #0078d4; color: #ffffff; padding: 10px 16px; font-size: 12.5px; font-weight: 700; text-decoration: none; border-radius: 8px; display: inline-block;">
                                                📆 Outlook / 365
                                            </a>
                                        </td>
                                        <!-- Archivo .ics -->
                                        <td style="padding: 4px;">
                                            <a href="<?php echo esc_url( $cal_links['ics_download'] ); ?>" style="background-color: #475569; color: #ffffff; padding: 10px 16px; font-size: 12.5px; font-weight: 700; text-decoration: none; border-radius: 8px; display: inline-block;">
                                                📎 Descargar .ics
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p style="margin: 0; font-size: 12px; color: #94a3b8; text-align: center; line-height: 1.4;">
                                <?php esc_html_e( 'Nota: El archivo de invitación .ics también se encuentra adjunto a este correo para apertura automática en Apple Calendar, Outlook de escritorio o Thunderbird.', 'aura' ); ?>
                            </p>

                        </td>
                    </tr>

                    <!-- Footer del Correo -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b;">
                            <p style="margin: 0 0 6px 0;">
                                &copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>. <?php esc_html_e( 'Todos los derechos reservados.', 'aura' ); ?>
                            </p>
                            <p style="margin: 0;">
                                <a href="<?php echo esc_url( $site_url ); ?>" style="color: #6366f1; text-decoration: none; font-weight: 600;">
                                    <?php echo esc_html( $site_url ); ?>
                                </a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────
    // ENDPOINTS AJAX
    // ─────────────────────────────────────────────────────────────

    /**
     * Endpoint AJAX: Descargar archivo .ics
     */
    public static function ajax_download_ics(): void {
        $event_id = intval( $_GET['event_id'] ?? 0 );
        $token    = sanitize_text_field( $_GET['token'] ?? '' );

        if ( ! $event_id ) {
            wp_die( esc_html__( 'ID de evento inválido.', 'aura' ), 400 );
        }

        // Si no está logueado, validar el token firmado
        if ( ! is_user_logged_in() ) {
            if ( ! wp_verify_nonce( $token, 'aura_cal_ics_' . $event_id ) ) {
                wp_die( esc_html__( 'Enlace de descarga expirado o no autorizado.', 'aura' ), 403 );
            }
        }

        $ics = self::generate_ics( $event_id, true, 'PUBLISH' );
        if ( empty( $ics ) ) {
            wp_die( esc_html__( 'Evento no encontrado.', 'aura' ), 404 );
        }

        $event = Aura_Calendar_Events::get( $event_id );
        $slug  = sanitize_title( $event ? $event->title : 'evento' ) ?: 'evento';
        $filename = $slug . '-' . $event_id . '.ics';

        // Headers para descarga directa
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $ics ) );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo $ics;
        exit;
    }

    /**
     * Endpoint AJAX: Obtener enlaces para agregar a calendario
     */
    public static function ajax_get_calendar_links(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        $event_id = intval( $_POST['event_id'] ?? 0 );
        if ( ! $event_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de evento inválido.', 'aura' ) ] );
        }

        $links = self::get_calendar_links( $event_id );
        if ( empty( $links ) ) {
            wp_send_json_error( [ 'message' => __( 'Evento no encontrado.', 'aura' ) ] );
        }

        wp_send_json_success( $links );
    }

    /**
     * Endpoint AJAX: Enviar invitación por correo electrónico
     */
    public static function ajax_send_invitation_email(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) &&
             ! current_user_can( 'aura_create_calendar_events' ) &&
             ! current_user_can( 'aura_teach_calendar' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes para enviar invitaciones.', 'aura' ) ] );
        }

        $event_id    = intval( $_POST['event_id'] ?? 0 );
        $custom_note = sanitize_textarea_field( $_POST['custom_note'] ?? '' );
        $emails_raw  = sanitize_text_field( $_POST['recipient_emails'] ?? '' );

        $recipient_emails = [];
        if ( ! empty( $emails_raw ) ) {
            $parts = array_map( 'trim', explode( ',', $emails_raw ) );
            foreach ( $parts as $p ) {
                if ( is_email( $p ) ) {
                    $recipient_emails[] = $p;
                }
            }
        }

        if ( ! $event_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de evento inválido.', 'aura' ) ] );
        }

        $res = self::send_invitations_email( $event_id, $recipient_emails, $custom_note );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res );
        } else {
            wp_send_json_error( $res );
        }
    }
}
