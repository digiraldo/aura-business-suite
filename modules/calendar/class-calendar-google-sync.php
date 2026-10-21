<?php
/**
 * Sincronización con Google Calendar — Módulo de Calendario y Horarios Académicos
 *
 * Se integra con el servicio global Aura_Google_Calendar (modules/common/class-google-calendar.php)
 * para sincronizar clases, talleres, exámenes y actividades académicas en un único
 * calendario unificado en Google Calendar con nombre configurable.
 *
 * @package AuraBusinessSuite
 * @subpackage Calendar
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Calendar_Google_Sync {

    /** Clave de opción en wp_options para el Calendar ID resuelto */
    const CAL_ID_OPTION = 'aura_cal_gcal_calendar_id';

    /** Clave de opción para el nombre del calendario */
    const CAL_NAME_OPTION = 'aura_cal_gcal_name';

    /** Clave de opción para habilitar/deshabilitar auto-sincronización al guardar eventos */
    const AUTO_SYNC_OPTION = 'aura_cal_gcal_auto_sync';

    /**
     * Inicializar hooks y endpoints AJAX
     */
    public static function init(): void {
        add_action( 'wp_ajax_aura_cal_gcal_test_sync', [ __CLASS__, 'ajax_test_sync' ] );
        add_action( 'wp_ajax_aura_cal_gcal_sync_all',  [ __CLASS__, 'ajax_sync_all' ] );
        add_action( 'wp_ajax_aura_cal_gcal_sync_event', [ __CLASS__, 'ajax_sync_single_event' ] );
        add_action( 'wp_ajax_aura_cal_gcal_get_shared_users', [ __CLASS__, 'ajax_get_shared_users' ] );
        add_action( 'wp_ajax_aura_cal_search_contacts_for_share', [ __CLASS__, 'ajax_search_contacts_for_share' ] );
        add_action( 'wp_ajax_aura_cal_gcal_share_contact', [ __CLASS__, 'ajax_share_contact' ] );
        add_action( 'wp_ajax_aura_cal_gcal_revoke_access', [ __CLASS__, 'ajax_revoke_access' ] );
    }

    /**
     * Comprobar si la integración de Google Calendar está habilitada y configurada
     */
    public static function is_enabled(): bool {
        if ( ! class_exists( 'Aura_Google_Calendar' ) ) {
            return false;
        }
        return Aura_Google_Calendar::is_enabled();
    }

    /**
     * Comprobar si la auto-sincronización al crear/editar clases está activa
     */
    public static function is_auto_sync(): bool {
        if ( ! self::is_enabled() ) {
            return false;
        }
        return (bool) get_option( self::AUTO_SYNC_OPTION, '1' );
    }

    /**
     * Obtener el nombre configurado para el calendario de clases
     */
    public static function get_calendar_name(): string {
        $custom_name = trim( (string) get_option( self::CAL_NAME_OPTION, '' ) );
        if ( ! empty( $custom_name ) ) {
            return $custom_name;
        }

        $org_name = trim( (string) get_option( 'aura_org_name', '' ) );
        if ( empty( $org_name ) ) {
            $org_name = get_bloginfo( 'name' );
        }
        if ( empty( $org_name ) ) {
            $org_name = 'CEM';
        }

        return $org_name . ' - Clases';
    }

    /**
     * Obtener o crear el Calendar ID en Google Calendar
     */
    public static function get_calendar_id( bool $force_refresh = false ): ?string {
        if ( ! self::is_enabled() ) {
            return null;
        }

        if ( $force_refresh ) {
            delete_option( self::CAL_ID_OPTION );
        }

        $cal_name = self::get_calendar_name();
        $cal_id   = Aura_Google_Calendar::get_or_create_calendar( $cal_name, self::CAL_ID_OPTION );

        if ( ! empty( $cal_id ) ) {
            // Asegurar que esté compartido con los correos de administración/coordinación
            Aura_Google_Calendar::share_calendar( $cal_id );
        }

        return $cal_id;
    }

    /**
     * Sincronizar un evento individual con Google Calendar
     *
     * @param int $event_id ID del evento en wp_aura_cal_events
     * @return array Resultado ['success' => bool, 'gcal_id' => string|null, 'message' => string]
     */
    public static function sync_event( int $event_id ): array {
        if ( ! self::is_enabled() ) {
            return [
                'success' => false,
                'gcal_id' => null,
                'message' => __( 'La integración con Google Calendar no está configurada o está inactiva.', 'aura' ),
            ];
        }

        global $wpdb;
        $table_events      = $wpdb->prefix . 'aura_cal_events';
        $table_programs    = $wpdb->prefix . 'aura_cal_programs';
        $table_subjects    = $wpdb->prefix . 'aura_cal_subjects';
        $table_instructors = $wpdb->prefix . 'aura_cal_event_instructors';

        // Obtener el evento
        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, p.name AS program_name, p.code AS program_code, p.color AS program_color,
                    s.name AS subject_name, s.code AS subject_code
             FROM {$table_events} e
             LEFT JOIN {$table_programs} p ON p.id = e.program_id
             LEFT JOIN {$table_subjects} s ON s.id = e.subject_id
             WHERE e.id = %d AND e.deleted_at IS NULL",
            $event_id
        ) );

        if ( ! $event ) {
            return [
                'success' => false,
                'gcal_id' => null,
                'message' => __( 'Evento no encontrado o fue eliminado.', 'aura' ),
            ];
        }

        // Si el evento está cancelado, eliminarlo de GCal si ya existía
        if ( $event->status === 'cancelled' ) {
            if ( ! empty( $event->gcal_event_id ) ) {
                self::delete_event( $event_id );
            }
            $wpdb->update(
                $table_events,
                [
                    'gcal_sync_status' => 'skipped',
                    'updated_at'       => current_time( 'mysql' ),
                ],
                [ 'id' => $event_id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
            return [
                'success' => true,
                'gcal_id' => null,
                'message' => __( 'Evento cancelado; retirado de Google Calendar.', 'aura' ),
            ];
        }

        $cal_id = self::get_calendar_id();
        if ( ! $cal_id ) {
            $wpdb->update(
                $table_events,
                [ 'gcal_sync_status' => 'failed' ],
                [ 'id' => $event_id ],
                [ '%s' ],
                [ '%d' ]
            );
            return [
                'success' => false,
                'gcal_id' => null,
                'message' => __( 'No se pudo resolver el ID del calendario de Google.', 'aura' ),
            ];
        }

        // Obtener instructores asignados
        $instructors = $wpdb->get_results( $wpdb->prepare(
            "SELECT ei.*, u.display_name, u.user_email
             FROM {$table_instructors} ei
             JOIN {$wpdb->users} u ON u.ID = ei.teacher_id
             WHERE ei.event_id = %d",
            $event_id
        ) );

        $instructor_names = [];
        $attendees        = [];
        if ( ! empty( $instructors ) ) {
            foreach ( $instructors as $inst ) {
                $instructor_names[] = $inst->display_name;
                if ( ! empty( $inst->user_email ) && is_email( $inst->user_email ) ) {
                    $attendees[] = [
                        'email'       => $inst->user_email,
                        'displayName' => $inst->display_name,
                    ];
                }
            }
        }

        // Construir Summary (Título estricto para Google Calendar: {Nombre del Evento}: {Nombre de la Materia} [{Código Corto del Programa}])
        $summary = trim( (string) $event->title );
        if ( ! empty( $event->subject_name ) ) {
            $summary .= ': ' . trim( (string) $event->subject_name );
        }
        $prog_tag = ! empty( $event->program_code ) ? trim( (string) $event->program_code ) : ( ! empty( $event->program_name ) ? trim( (string) $event->program_name ) : '' );
        if ( ! empty( $prog_tag ) ) {
            $summary .= ' [' . $prog_tag . ']';
        }

        // Construir Descripción rica
        $desc_lines = [];
        if ( ! empty( $event->program_name ) ) {
            $desc_lines[] = '🎓 Programa: ' . $event->program_name . ( ! empty( $event->program_code ) ? ' (' . $event->program_code . ')' : '' );
        }
        if ( ! empty( $event->subject_name ) ) {
            $desc_lines[] = '📚 Materia: ' . $event->subject_name . ( ! empty( $event->subject_code ) ? ' (' . $event->subject_code . ')' : '' );
        }

        $type_labels = [
            'class'    => __( 'Clase regular', 'aura' ),
            'exam'     => __( 'Examen / Evaluación', 'aura' ),
            'workshop' => __( 'Taller / Laboratorio', 'aura' ),
            'activity' => __( 'Actividad extracurricular', 'aura' ),
            'break'    => __( 'Receso / Descanso', 'aura' ),
            'other'    => __( 'Otro', 'aura' ),
        ];
        $desc_lines[] = '📌 Tipo: ' . ( $type_labels[ $event->event_type ] ?? ucfirst( $event->event_type ) );

        if ( ! empty( $instructor_names ) ) {
            $desc_lines[] = '👨‍🏫 Profesor(es): ' . implode( ', ', $instructor_names );
        }

        if ( ! empty( $event->location ) ) {
            $desc_lines[] = '📍 Ubicación: ' . $event->location;
        }

        if ( ! empty( $event->online_url ) ) {
            $desc_lines[] = '💻 Sesión Online: ' . $event->online_url;
        }

        if ( ! empty( $event->description ) ) {
            $desc_lines[] = "\n📝 Detalle:\n" . wp_strip_all_tags( $event->description );
        }

        $desc_lines[] = "\n--\nSincronizado automáticamente por Aura Business Suite";
        $description = implode( "\n", $desc_lines );

        // Configuración de zona horaria y fechas
        $tz_string = wp_timezone_string();
        if ( empty( $tz_string ) ) {
            $tz_string = 'America/Bogota';
        }

        $tz_obj     = wp_timezone();
        $dt_start   = new DateTime( $event->start_datetime, $tz_obj );
        $dt_end     = new DateTime( $event->end_datetime, $tz_obj );

        // Detección de evento multi-día (diferente fecha de inicio y fin)
        $start_date_str = substr( $event->start_datetime, 0, 10 );
        $end_date_str   = substr( $event->end_datetime, 0, 10 );
        $is_multi_day   = ( ! empty( $end_date_str ) && $start_date_str !== $end_date_str );

        if ( $is_multi_day ) {
            // En Google Calendar API, para que se dibuje la barra larga horizontal continua
            // a través de todos los días, el evento debe registrarse con fechas sin hora ('date').
            // NOTA: 'end.date' en Google Calendar API es EXCLUSIVO. Por ello sumamos +1 día a $end_date_str.
            $end_date_exclusive = date( 'Y-m-d', strtotime( $end_date_str . ' +1 day' ) );

            $detailed_schedule = sprintf(
                /* translators: 1: start datetime, 2: end datetime */
                __( '⏰ Horario programado: %1$s a %2$s', 'aura' ),
                $dt_start->format( 'd/m/Y H:i' ),
                $dt_end->format( 'd/m/Y H:i' )
            );

            $payload = [
                'summary'     => $summary,
                'description' => $detailed_schedule . "\n\n" . $description,
                'location'    => ! empty( $event->online_url ) ? $event->online_url : ( $event->location ?? '' ),
                'start'       => [
                    'date' => $start_date_str,
                ],
                'end'         => [
                    'date' => $end_date_exclusive,
                ],
            ];
        } else {
            // Evento en un mismo día con hora fija
            $payload = [
                'summary'     => $summary,
                'description' => $description,
                'location'    => ! empty( $event->online_url ) ? $event->online_url : ( $event->location ?? '' ),
                'start'       => [
                    'dateTime' => $dt_start->format( DateTime::RFC3339 ),
                    'timeZone' => $tz_string,
                ],
                'end'         => [
                    'dateTime' => $dt_end->format( DateTime::RFC3339 ),
                    'timeZone' => $tz_string,
                ],
            ];
        }

        $result     = null;
        $gcal_id    = $event->gcal_event_id;
        $is_update  = ! empty( $gcal_id );

        if ( $is_update ) {
            $url    = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/events/' . rawurlencode( $gcal_id );
            $result = Aura_Google_Calendar::api_request( 'PUT', $url, $payload );

            // Si el evento no existe en Google Calendar (p. ej. fue borrado manualmente en Google), recrearlo
            if ( empty( $result ) || empty( $result['id'] ) ) {
                $is_update = false;
                $gcal_id   = null;
            }
        }

        if ( ! $is_update ) {
            $url    = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/events';
            $result = Aura_Google_Calendar::api_request( 'POST', $url, $payload );
            if ( ! empty( $result['id'] ) ) {
                $gcal_id = $result['id'];
            }
        }

        if ( ! empty( $gcal_id ) && is_array( $result ) ) {
            $wpdb->update(
                $table_events,
                [
                    'gcal_event_id'    => $gcal_id,
                    'gcal_sync_status' => 'synced',
                    'gcal_synced_at'   => current_time( 'mysql' ),
                    'updated_at'       => current_time( 'mysql' ),
                ],
                [ 'id' => $event_id ],
                [ '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );

            return [
                'success' => true,
                'gcal_id' => $gcal_id,
                'title'   => $event->title,
                'dates'   => $event->start_datetime . ' a ' . $event->end_datetime,
                'message' => __( 'Evento sincronizado con éxito en Google Calendar.', 'aura' ),
            ];
        }

        $error_detail = Aura_Google_Calendar::get_last_error();
        $wpdb->update(
            $table_events,
            [
                'gcal_sync_status' => 'failed',
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ 'id' => $event_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return [
            'success' => false,
            'gcal_id' => null,
            'title'   => $event->title,
            'dates'   => $event->start_datetime . ' a ' . $event->end_datetime,
            'message' => $error_detail
                ? sprintf( __( 'Error de Google Calendar: %s', 'aura' ), $error_detail )
                : __( 'Error en la respuesta de Google Calendar API.', 'aura' ),
        ];
    }

    /**
     * Eliminar un evento de Google Calendar
     *
     * @param int $event_id ID del evento en wp_aura_cal_events
     * @return bool True si se eliminó o no requería eliminación
     */
    public static function delete_event( int $event_id ): bool {
        if ( ! self::is_enabled() ) {
            return false;
        }

        global $wpdb;
        $table_events = $wpdb->prefix . 'aura_cal_events';
        $gcal_id      = $wpdb->get_var( $wpdb->prepare(
            "SELECT gcal_event_id FROM {$table_events} WHERE id = %d",
            $event_id
        ) );

        if ( empty( $gcal_id ) ) {
            return true;
        }

        $cal_id = self::get_calendar_id();
        if ( ! $cal_id ) {
            return false;
        }

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/events/' . rawurlencode( $gcal_id );
        Aura_Google_Calendar::api_request( 'DELETE', $url );

        $wpdb->update(
            $table_events,
            [
                'gcal_event_id'    => null,
                'gcal_sync_status' => 'skipped',
                'updated_at'       => current_time( 'mysql' ),
            ],
            [ 'id' => $event_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );

        return true;
    }

    /**
     * Sincronización masiva de eventos (batch)
     *
     * @param int|null    $program_id Filtrar por programa específico (opcional)
     * @param string|null $from_date  Fecha mínima (YYYY-MM-DD), default hoy
     * @param int         $limit      Límite de eventos a procesar por tanda
     * @return array Estadísticas de la sincronización ['total', 'synced', 'failed', 'skipped', 'items']
     */
    public static function batch_sync( ?int $program_id = null, ?string $from_date = null, int $limit = 50 ): array {
        if ( ! self::is_enabled() ) {
            return [
                'total'     => 0,
                'synced'    => 0,
                'failed'    => 0,
                'skipped'   => 0,
                'items'     => [],
                'synced_at' => current_time( 'mysql' ),
                'error'     => __( 'Google Calendar no está habilitado.', 'aura' ),
            ];
        }

        global $wpdb;
        $table_events = $wpdb->prefix . 'aura_cal_events';

        $where = [ 'deleted_at IS NULL', "status != 'cancelled'" ];
        $params = [];

        if ( ! empty( $program_id ) ) {
            $where[]  = 'program_id = %d';
            $params[] = $program_id;
        }

        if ( empty( $from_date ) ) {
            $from_date = current_time( 'Y-m-d' );
        }
        $where[]  = 'DATE(start_datetime) >= %s';
        $params[] = $from_date;

        $where_sql = implode( ' AND ', $where );
        $params[]  = $limit;

        $sql = "SELECT id FROM {$table_events} WHERE {$where_sql} ORDER BY start_datetime ASC LIMIT %d";
        $event_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

        $stats = [
            'total'     => count( $event_ids ),
            'synced'    => 0,
            'failed'    => 0,
            'skipped'   => 0,
            'items'     => [],
            'synced_at' => current_time( 'mysql' ),
        ];

        foreach ( $event_ids as $id ) {
            $res = self::sync_event( (int) $id );
            $stats['items'][] = [
                'id'      => (int) $id,
                'title'   => $res['title'] ?? '',
                'dates'   => $res['dates'] ?? '',
                'success' => ! empty( $res['success'] ),
                'message' => $res['message'] ?? '',
                'gcal_id' => $res['gcal_id'] ?? null,
            ];

            if ( ! empty( $res['success'] ) ) {
                $stats['synced']++;
            } else {
                $stats['failed']++;
            }
        }

        // Persistir el último log de sincronización para consulta visual en Ajustes
        update_option( 'aura_cal_last_sync_log', $stats, false );

        return $stats;
    }

    /**
     * Obtener el último log de sincronización masiva persistido
     */
    public static function get_last_sync_log(): ?array {
        $log = get_option( 'aura_cal_last_sync_log', null );
        return is_array( $log ) ? $log : null;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Probar conexión y obtener/crear el calendario en Google
     */
    public static function ajax_test_sync(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_sync_gcal' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        if ( ! self::is_enabled() ) {
            wp_send_json_error( [
                'message' => __( 'Google Calendar no está activo en Ajustes Globales de Aura. Ve a Ajustes → Google Calendar y configura las credenciales.', 'aura' ),
            ] );
        }

        $cal_name = self::get_calendar_name();
        $cal_id   = self::get_calendar_id( true );

        if ( empty( $cal_id ) ) {
            wp_send_json_error( [
                'message' => sprintf(
                    __( 'No se pudo crear ni resolver el calendario "%s" en Google Calendar. Revisa los logs de PHP.', 'aura' ),
                    $cal_name
                ),
            ] );
        }

        wp_send_json_success( [
            'message'     => sprintf(
                __( '¡Conexión exitosa! Calendario activo: "%s" (ID: %s).', 'aura' ),
                $cal_name,
                $cal_id
            ),
            'calendar_id' => $cal_id,
            'calendar_name' => $cal_name,
        ] );
    }

    /**
     * AJAX: Sincronizar todos los eventos futuros
     */
    public static function ajax_sync_all(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_sync_gcal' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $program_id = ! empty( $_POST['program_id'] ) ? intval( $_POST['program_id'] ) : null;
        $stats      = self::batch_sync( $program_id );

        if ( ! empty( $stats['error'] ) ) {
            wp_send_json_error( [ 'message' => $stats['error'] ] );
        }

        wp_send_json_success( [
            'message' => sprintf(
                __( 'Sincronización completada. Total: %d, Sincronizados: %d, Errores: %d.', 'aura' ),
                $stats['total'],
                $stats['synced'],
                $stats['failed']
            ),
            'stats'   => $stats,
        ] );
    }

    /**
     * AJAX: Sincronizar un único evento
     */
    public static function ajax_sync_single_event(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_sync_gcal' ) && ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $event_id = ! empty( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de evento inválido.', 'aura' ) ] );
        }

        $res = self::sync_event( $event_id );
        if ( $res['success'] ) {
            wp_send_json_success( $res );
        } else {
            wp_send_json_error( $res );
        }
    }

    /**
     * AJAX: Obtener lista de usuarios compartidos con Google Calendar ACL
     */
    public static function ajax_get_shared_users(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        if ( ! self::is_enabled() ) {
            wp_send_json_error( [ 'message' => __( 'Google Calendar no está configurado.', 'aura' ) ] );
        }

        $cal_id = self::get_calendar_id();
        if ( empty( $cal_id ) ) {
            wp_send_json_error( [ 'message' => __( 'No se pudo obtener el ID del calendario.', 'aura' ) ] );
        }

        $acl_items = Aura_Google_Calendar::list_acl_rules( $cal_id );
        
        global $wpdb;
        $table_contacts = $wpdb->prefix . 'aura_contacts';
        $has_contacts_table = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_contacts}'" ) === $table_contacts );

        // Obtener email de la cuenta de servicio para identificarla
        $sa_email = '';
        if ( class_exists( 'Aura_Google_Calendar' ) ) {
            $creds = Aura_Google_Calendar::get_credentials();
            $sa_email = strtolower( trim( (string) ( $creds['client_email'] ?? '' ) ) );
        }

        $users = [];
        foreach ( $acl_items as $item ) {
            $scope = $item['scope'] ?? [];
            $role  = $item['role'] ?? 'reader';
            $id    = $item['id'] ?? '';
            $type  = $scope['type'] ?? 'user';
            $email = $scope['value'] ?? '';

            if ( $type === 'default' ) {
                continue;
            }

            $contact_info = null;
            if ( $has_contacts_table && ! empty( $email ) ) {
                $contact = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id, first_name, last_name, company, email_primary, email_secondary, photo_url 
                     FROM {$table_contacts} 
                     WHERE (email_primary = %s OR email_secondary = %s) AND is_active = 1 
                     LIMIT 1",
                    $email, $email
                ) );

                if ( $contact ) {
                    $contact_info = [
                        'id'         => (int) $contact->id,
                        'name'       => trim( $contact->first_name . ' ' . $contact->last_name ),
                        'company'    => $contact->company,
                        'photo_url'  => $contact->photo_url,
                        'matched_as' => ( strtolower( (string) $contact->email_primary ) === strtolower( (string) $email ) ) ? 'primary' : 'secondary',
                    ];
                }
            }

            $is_service_account = ( ! empty( $sa_email ) && strtolower( $email ) === $sa_email );
            $is_owner           = ( $role === 'owner' );

            $users[] = [
                'rule_id'            => $id,
                'email'              => $email,
                'role'               => $role,
                'role_label'         => ( $role === 'owner' ) ? __( 'Propietario', 'aura' ) : ( ( $role === 'writer' ) ? __( 'Editor (Lectura y Escritura)', 'aura' ) : __( 'Lector (Solo Lectura)', 'aura' ) ),
                'is_owner'           => $is_owner,
                'is_service_account' => $is_service_account,
                'is_deletable'       => ( ! $is_owner && ! $is_service_account ),
                'contact'            => $contact_info,
            ];
        }

        wp_send_json_success( [
            'users'       => $users,
            'total'       => count( $users ),
            'calendar_id' => $cal_id,
        ] );
    }

    /**
     * AJAX: Buscar contactos del Directorio Aura para compartir calendario
     */
    public static function ajax_search_contacts_for_share(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        global $wpdb;
        $table_contacts = $wpdb->prefix . 'aura_contacts';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_contacts}'" ) !== $table_contacts ) {
            wp_send_json_error( [ 'message' => __( 'La tabla de contactos de Aura no está disponible.', 'aura' ) ] );
        }

        $query = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        $query_like = '%' . $wpdb->esc_like( $query ) . '%';

        if ( ! empty( $query ) ) {
            $sql = $wpdb->prepare(
                "SELECT id, first_name, last_name, company, email_primary, email_secondary, photo_url, category 
                 FROM {$table_contacts} 
                 WHERE is_active = 1 
                   AND ( (email_primary IS NOT NULL AND email_primary != '') OR (email_secondary IS NOT NULL AND email_secondary != '') )
                   AND ( first_name LIKE %s OR last_name LIKE %s OR company LIKE %s OR email_primary LIKE %s OR email_secondary LIKE %s )
                 ORDER BY first_name ASC, last_name ASC 
                 LIMIT 25",
                $query_like, $query_like, $query_like, $query_like, $query_like
            );
        } else {
            $sql = "SELECT id, first_name, last_name, company, email_primary, email_secondary, photo_url, category 
                    FROM {$table_contacts} 
                    WHERE is_active = 1 
                      AND ( (email_primary IS NOT NULL AND email_primary != '') OR (email_secondary IS NOT NULL AND email_secondary != '') )
                    ORDER BY first_name ASC, last_name ASC 
                    LIMIT 20";
        }

        $results = $wpdb->get_results( $sql );
        $contacts = [];

        foreach ( $results as $row ) {
            $name = trim( $row->first_name . ' ' . $row->last_name );
            if ( empty( $name ) ) {
                $name = $row->company ?: __( 'Sin Nombre', 'aura' );
            }

            $emails = [];
            if ( ! empty( $row->email_primary ) && is_email( $row->email_primary ) ) {
                $emails[] = [
                    'type'  => 'primary',
                    'label' => __( 'Principal', 'aura' ),
                    'email' => $row->email_primary,
                ];
            }
            if ( ! empty( $row->email_secondary ) && is_email( $row->email_secondary ) ) {
                $emails[] = [
                    'type'  => 'secondary',
                    'label' => __( 'Secundario', 'aura' ),
                    'email' => $row->email_secondary,
                ];
            }

            if ( empty( $emails ) ) {
                continue;
            }

            $contacts[] = [
                'id'        => (int) $row->id,
                'name'      => $name,
                'company'   => $row->company,
                'category'  => $row->category,
                'photo_url' => $row->photo_url,
                'emails'    => $emails,
            ];
        }

        wp_send_json_success( [ 'contacts' => $contacts ] );
    }

    /**
     * AJAX: Compartir calendario con un contacto del Directorio
     */
    public static function ajax_share_contact(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $contact_id = isset( $_POST['contact_id'] ) ? intval( $_POST['contact_id'] ) : 0;
        $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $role       = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'reader';
        $notify     = ! empty( $_POST['send_notifications'] );

        if ( ! $contact_id || empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos de contacto o correo electrónico inválidos.', 'aura' ) ] );
        }

        // REGLA ESTRICTA: El correo debe pertenecer a un contacto existente en wp_aura_contacts
        global $wpdb;
        $table_contacts = $wpdb->prefix . 'aura_contacts';
        $contact = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, first_name, last_name, company, email_primary, email_secondary 
             FROM {$table_contacts} 
             WHERE id = %d AND is_active = 1 LIMIT 1",
            $contact_id
        ) );

        if ( ! $contact ) {
            wp_send_json_error( [ 'message' => __( 'El contacto especificado no existe o está inactivo en el Directorio de Contactos.', 'aura' ) ] );
        }

        $primary_match   = ( strtolower( (string) $contact->email_primary ) === strtolower( $email ) );
        $secondary_match = ( strtolower( (string) $contact->email_secondary ) === strtolower( $email ) );

        if ( ! $primary_match && ! $secondary_match ) {
            wp_send_json_error( [
                'message' => __( 'El correo seleccionado no corresponde ni al email principal ni al secundario registrado en el contacto de Aura.', 'aura' ),
            ] );
        }

        $cal_id = self::get_calendar_id();
        if ( empty( $cal_id ) ) {
            wp_send_json_error( [ 'message' => __( 'No se encontró un calendario de Google activo.', 'aura' ) ] );
        }

        $res = Aura_Google_Calendar::insert_acl_rule( $cal_id, $email, $role, $notify );
        if ( ! $res ) {
            $err = Aura_Google_Calendar::get_last_error();
            wp_send_json_error( [
                'message' => sprintf(
                    __( 'Error al conceder acceso en Google Calendar: %s', 'aura' ),
                    $err ?: __( 'Error desconocido en la API de Google.', 'aura' )
                ),
            ] );
        }

        $contact_name = trim( $contact->first_name . ' ' . $contact->last_name );
        if ( empty( $contact_name ) ) {
            $contact_name = $contact->company ?: $email;
        }

        wp_send_json_success( [
            'message' => sprintf(
                __( '¡Calendario compartido exitosamente con %s (%s) como %s!', 'aura' ),
                $contact_name,
                $email,
                ( $role === 'writer' ) ? __( 'Editor', 'aura' ) : __( 'Lector', 'aura' )
            ),
            'rule' => $res,
        ] );
    }

    /**
     * AJAX: Revocar regla de acceso ACL en Google Calendar
     */
    public static function ajax_revoke_access(): void {
        check_ajax_referer( 'aura_cal_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cal_manage_calendar' ) && ! current_user_can( 'aura_cal_manage_settings' ) && ! current_user_can( 'aura_manage_calendar' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura' ) ] );
        }

        $rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
        if ( empty( $rule_id ) ) {
            wp_send_json_error( [ 'message' => __( 'ID de regla ACL no especificado.', 'aura' ) ] );
        }

        $cal_id = self::get_calendar_id();
        if ( empty( $cal_id ) ) {
            wp_send_json_error( [ 'message' => __( 'No se encontró un calendario de Google activo.', 'aura' ) ] );
        }

        $success = Aura_Google_Calendar::delete_acl_rule( $cal_id, $rule_id );
        if ( ! $success ) {
            $err = Aura_Google_Calendar::get_last_error();
            wp_send_json_error( [
                'message' => sprintf(
                    __( 'No se pudo revocar el acceso en Google Calendar: %s', 'aura' ),
                    $err ?: __( 'Error en la petición a Google.', 'aura' )
                ),
            ] );
        }

        wp_send_json_success( [
            'message' => __( 'Acceso revocado de inmediato. El usuario ya no tiene acceso al calendario en Google.', 'aura' ),
            'rule_id' => $rule_id,
        ] );
    }
}

