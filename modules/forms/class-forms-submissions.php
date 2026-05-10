<?php
/**
 * Submissions del Módulo de Formularios — Guardar envíos
 *
 * Procesa el envío de formularios desde el frontend público y el portal
 * del estudiante, aplicando validación server-side, anti-spam y sanitización.
 *
 * AJAX actions registradas:
 *  - aura_form_submit  — Guardar submission con todos los controles de seguridad
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Submissions {

    // Límite de envíos por IP por formulario (ventana de 10 minutos)
    const RATE_LIMIT_MAX     = 5;
    const RATE_LIMIT_SECONDS = 600;

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Usuarios no autenticados y autenticados pueden enviar formularios públicos
        add_action( 'wp_ajax_nopriv_aura_form_submit', [ __CLASS__, 'ajax_submit' ] );
        add_action( 'wp_ajax_aura_form_submit',        [ __CLASS__, 'ajax_submit' ] );

        // Admin: gestión de respuestas
        add_action( 'wp_ajax_aura_forms_get_submissions',   [ __CLASS__, 'ajax_get_submissions' ] );
        add_action( 'wp_ajax_aura_forms_get_submission',    [ __CLASS__, 'ajax_get_submission' ] );
        add_action( 'wp_ajax_aura_forms_delete_submission', [ __CLASS__, 'ajax_delete_submission' ] );
        add_action( 'wp_ajax_aura_forms_bulk_submissions',  [ __CLASS__, 'ajax_bulk_submissions' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — ENVIAR FORMULARIO
    // ─────────────────────────────────────────────────────────────

    public static function ajax_submit(): void {
        global $wpdb;

        // ── 1. Nonce dinámico por formulario ──────────────────────
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de formulario inválido.', 'aura-suite' ) ], 400 );
        }

        check_ajax_referer( 'aura_form_submit_' . $form_id, 'nonce' );

        // ── 2. Cargar formulario ──────────────────────────────────
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aura_forms
                  WHERE id = %d AND is_active = 1 AND deleted_at IS NULL",
                $form_id
            )
        );

        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Formulario no disponible.', 'aura-suite' ) ], 404 );
        }

        // ── 3. Verificar expiración y límite ──────────────────────
        if ( $form->close_date && strtotime( $form->close_date ) < time() ) {
            wp_send_json_error( [ 'message' => __( 'Este formulario ha expirado.', 'aura-suite' ) ], 403 );
        }

        if ( $form->requires_login && ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Debes estar autenticado para enviar este formulario.', 'aura-suite' ) ], 403 );
        }

        if ( $form->max_submissions > 0 ) {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions WHERE form_id = %d",
                    $form_id
                )
            );
            if ( $count >= $form->max_submissions ) {
                wp_send_json_error( [ 'message' => __( 'Este formulario ya no acepta más respuestas.', 'aura-suite' ) ], 403 );
            }
        }

        // ── 4. Honeypot anti-spam ─────────────────────────────────
        // El campo "_aura_hp" debe llegar vacío; los bots lo rellenan
        if ( ! empty( $_POST['_aura_hp'] ) ) {
            // Silenciar sin revelar que es honeypot
            wp_send_json_success( [ 'message' => self::get_success_message( $form ), 'redirect_url' => '' ] );
        }

        // ── 5. Rate limiting por IP ───────────────────────────────
        $ip            = self::get_client_ip();
        $transient_key = 'aura_rl_' . md5( $ip . '_' . $form_id );
        $attempts      = (int) get_transient( $transient_key );

        if ( $attempts >= self::RATE_LIMIT_MAX ) {
            wp_send_json_error(
                [ 'message' => __( 'Demasiados intentos. Espera unos minutos antes de volver a enviar.', 'aura-suite' ) ],
                429
            );
        }

        // Incrementar contador
        set_transient( $transient_key, $attempts + 1, self::RATE_LIMIT_SECONDS );

        // ── 6. Cargar campos del formulario ───────────────────────
        $fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aura_form_fields
                  WHERE form_id = %d ORDER BY sort_order ASC",
                $form_id
            )
        );

        // ── 7. Validar y sanitizar respuestas ─────────────────────
        $data_map     = []; // field_uid => valor sanitizado
        $errors       = [];
        $uploaded_files = [];

        // Tipos que no generan respuesta
        $no_response_types = [ 'image', 'downloadable', 'section_title', 'paragraph' ];

        foreach ( $fields as $field ) {
            $ftype     = $field->field_type;
            $field_key = 'field_' . $field->id;

            // Tipos sin respuesta
            if ( in_array( $ftype, $no_response_types, true ) ) {
                continue;
            }

            // ── Manejo especial por tipo ──────────────────────────

            // Archivo
            if ( $ftype === 'file' ) {
                if ( isset( $_FILES[ $field_key ] ) && $_FILES[ $field_key ]['error'] === UPLOAD_ERR_OK ) {
                    $upload = self::handle_file_upload( $field, $_FILES[ $field_key ] );
                    if ( is_wp_error( $upload ) ) {
                        $errors[] = $field->label . ': ' . $upload->get_error_message();
                    } else {
                        $data_map[ $field->field_uid ] = $upload;
                        $uploaded_files[]              = $upload;
                    }
                } elseif ( $field->is_required ) {
                    $errors[] = sprintf( __( '"%s" es obligatorio.', 'aura-suite' ), $field->label );
                }
                continue;
            }

            // Fecha de nacimiento
            if ( $ftype === 'birthdate' ) {
                $raw_date = isset( $_POST[ $field_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) : '';
                if ( $raw_date !== '' ) {
                    $birth_ts = strtotime( $raw_date );
                    if ( $birth_ts && $birth_ts < time() ) {
                        $age = self::calculate_age( $raw_date );
                        $data_map[ $field->field_uid ]              = $age; // edad en años
                        $data_map[ $field->field_uid . '_iso_date' ] = $raw_date; // fecha ISO
                    } else {
                        $errors[] = sprintf( __( '"%s" contiene una fecha inválida.', 'aura-suite' ), $field->label );
                    }
                } elseif ( $field->is_required ) {
                    $errors[] = sprintf( __( '"%s" es obligatorio.', 'aura-suite' ), $field->label );
                }
                continue;
            }

            // accept_only_terms (checkbox)
            if ( $ftype === 'accept_only_terms' ) {
                $checked = ! empty( $_POST[ $field_key ] );
                if ( $checked ) {
                    $data_map[ $field->field_uid ] = 'accepted';
                } elseif ( $field->is_required ) {
                    $errors[] = sprintf( __( 'Debes aceptar los términos en "%s".', 'aura-suite' ), $field->label );
                } else {
                    $data_map[ $field->field_uid ] = null;
                }
                continue;
            }

            // terms (radio agree/disagree)
            if ( $ftype === 'terms' ) {
                $val = isset( $_POST[ $field_key . '_agreement_response' ] )
                    ? sanitize_text_field( wp_unslash( $_POST[ $field_key . '_agreement_response' ] ) )
                    : '';
                if ( in_array( $val, [ 'agree', 'disagree' ], true ) ) {
                    $data_map[ $field->field_uid ] = $val;
                } elseif ( $field->is_required ) {
                    $errors[] = sprintf( __( '"%s" es obligatorio.', 'aura-suite' ), $field->label );
                }
                continue;
            }

            // checkbox (multi)
            if ( $ftype === 'checkbox' ) {
                $vals = isset( $_POST[ $field_key ] ) && is_array( $_POST[ $field_key ] )
                    ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $field_key ] ) )
                    : [];
                if ( ! empty( $vals ) ) {
                    $data_map[ $field->field_uid ] = wp_json_encode( $vals );
                } elseif ( $field->is_required ) {
                    $errors[] = sprintf( __( '"%s" es obligatorio.', 'aura-suite' ), $field->label );
                }
                continue;
            }

            // select multiple
            if ( $ftype === 'select' && $field->options_json ) {
                $decoded = json_decode( $field->options_json, true );
                // Verificar si es multiple_select (almacenado en default_value o en campo extra)
                // Usamos la presencia de array en POST como señal
                if ( isset( $_POST[ $field_key ] ) && is_array( $_POST[ $field_key ] ) ) {
                    $vals = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $field_key ] ) );
                    // Reemplazar __other__ con el texto libre
                    $other_key = $field_key . '_other_text';
                    $vals = array_map( function( $v ) use ( $other_key ) {
                        if ( $v === '__other__' ) {
                            $other_text = sanitize_text_field( wp_unslash( $_POST[ $other_key ] ?? '' ) );
                            return $other_text !== '' ? $other_text : 'Otro';
                        }
                        return $v;
                    }, $vals );
                    $data_map[ $field->field_uid ] = wp_json_encode( $vals );
                    if ( empty( $vals ) && $field->is_required ) {
                        $errors[] = sprintf( __( '"%s" es obligatorio.', 'aura-suite' ), $field->label );
                    }
                    continue;
                }
            }

            // ── Campo genérico de texto/valor simple ──────────────
            $raw = isset( $_POST[ $field_key ] ) ? wp_unslash( $_POST[ $field_key ] ) : ''; // phpcs:ignore

            // Opción "Otro" en radio / select simple
            if ( $raw === '__other__' && in_array( $ftype, [ 'radio', 'select' ], true ) ) {
                $raw = sanitize_text_field( wp_unslash( $_POST[ $field_key . '_other_text' ] ?? '' ) );
                if ( $raw === '' ) {
                    $raw = __( 'Otro', 'aura-suite' );
                }
            }

            // Sanitizar según tipo
            $sanitized = self::sanitize_field_value( $ftype, $raw );

            // Validar requerido
            if ( $field->is_required && ( $sanitized === '' || $sanitized === null ) ) {
                $errors[] = sprintf( __( '"%s" es obligatorio.', 'aura-suite' ), $field->label );
                continue;
            }

            // Validar email
            if ( $ftype === 'email' && $sanitized !== '' && ! is_email( $sanitized ) ) {
                $errors[] = sprintf( __( '"%s" debe ser un correo electrónico válido.', 'aura-suite' ), $field->label );
                continue;
            }

            // Validar rango numérico
            if ( $ftype === 'number' && $sanitized !== '' ) {
                $num = (float) $sanitized;
                if ( $field->min_value !== null && $num < (float) $field->min_value ) {
                    $errors[] = sprintf( __( '"%s" debe ser mayor o igual a %s.', 'aura-suite' ), $field->label, $field->min_value );
                    continue;
                }
                if ( $field->max_value !== null && $num > (float) $field->max_value ) {
                    $errors[] = sprintf( __( '"%s" debe ser menor o igual a %s.', 'aura-suite' ), $field->label, $field->max_value );
                    continue;
                }
            }

            if ( $sanitized !== '' ) {
                $data_map[ $field->field_uid ] = $sanitized;
            }
        }

        // Devolver errores de validación
        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'message' => implode( ' | ', $errors ), 'errors' => $errors ], 422 );
        }

        // ── 8. Extraer nombre y email del primer campo mapeado ────
        $submitted_name  = '';
        $submitted_email = '';

        foreach ( $fields as $field ) {
            if ( $field->mapping_key === 'first_name' && isset( $data_map[ $field->field_uid ] ) ) {
                $submitted_name = sanitize_text_field( $data_map[ $field->field_uid ] );
            }
            if ( $field->mapping_key === 'last_name' && isset( $data_map[ $field->field_uid ] ) ) {
                $submitted_name = trim( $submitted_name . ' ' . sanitize_text_field( $data_map[ $field->field_uid ] ) );
            }
            if ( ( $field->mapping_key === 'email' || $field->field_type === 'email' ) && isset( $data_map[ $field->field_uid ] ) && $submitted_email === '' ) {
                $submitted_email = sanitize_email( $data_map[ $field->field_uid ] );
            }
        }

        // ── 9. Guardar en BD ──────────────────────────────────────
        $data_json = wp_json_encode( $data_map );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [ 'message' => __( 'Error al procesar los datos del formulario.', 'aura-suite' ) ], 500 );
        }

        $insert_result = $wpdb->insert(
            $wpdb->prefix . 'aura_form_submissions',
            [
                'form_id'          => $form_id,
                'wp_user_id'       => is_user_logged_in() ? get_current_user_id() : null,
                'submitted_name'   => $submitted_name ?: null,
                'submitted_email'  => $submitted_email ?: null,
                'data_json'        => $data_json,
                'source_url'       => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : null,
                'ip_address'       => $ip,
                'user_agent'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
                'status'           => 'received',
                'submitted_at'     => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( ! $insert_result ) {
            wp_send_json_error( [ 'message' => __( 'No se pudo guardar tu respuesta. Intenta nuevamente.', 'aura-suite' ) ], 500 );
        }

        $submission_id = $wpdb->insert_id;

        // ── 10. Hook para otros módulos (inscripción, notif) ───────
        do_action( 'aura_form_submission_saved', $submission_id, $form_id );

        // ── 11. Respuesta de éxito ────────────────────────────────
        $redirect_url = $form->redirect_url ?: '';

        wp_send_json_success(
            [
                'message'      => self::get_success_message( $form ),
                'redirect_url' => esc_url_raw( $redirect_url ),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Sube un archivo respetando la whitelist de extensiones y tamaño máximo.
     *
     * @param object $field     Fila de aura_form_fields.
     * @param array  $file_data Entrada de $_FILES.
     * @return string|WP_Error Ruta relativa del archivo subido o error.
     */
    private static function handle_file_upload( object $field, array $file_data ): string|WP_Error {
        // Validar extensión
        $allowed_ext = ! empty( $field->allowed_extensions )
            ? array_map( 'trim', explode( ',', strtolower( $field->allowed_extensions ) ) )
            : [ 'jpg', 'jpeg', 'png', 'pdf' ];

        // Extensiones ejecutables nunca permitidas (defensa en profundidad)
        $blocked_ext = [ 'php', 'php3', 'php4', 'php5', 'phtml', 'js', 'exe', 'sh', 'bat', 'cmd', 'dll', 'pl', 'py' ];
        $allowed_ext = array_diff( $allowed_ext, $blocked_ext );

        $file_name = sanitize_file_name( $file_data['name'] );
        $ext       = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

        if ( ! in_array( $ext, $allowed_ext, true ) ) {
            return new WP_Error( 'bad_ext', __( 'Tipo de archivo no permitido.', 'aura-suite' ) );
        }

        // Validar tamaño
        $max_kb    = (int) ( $field->max_file_size_kb ?: 5120 );
        $file_size = (int) $file_data['size'];

        if ( $file_size > $max_kb * 1024 ) {
            return new WP_Error( 'too_large', sprintf( __( 'El archivo supera el límite de %s KB.', 'aura-suite' ), $max_kb ) );
        }

        // Directorio destino
        $upload_dir = wp_upload_dir();
        $form_id    = $field->form_id;
        $sub_dir    = "aura-forms/{$form_id}/" . gmdate( 'Y/m' );
        $dest_dir   = trailingslashit( $upload_dir['basedir'] ) . $sub_dir;

        if ( ! wp_mkdir_p( $dest_dir ) ) {
            return new WP_Error( 'mkdir', __( 'No se pudo crear el directorio de subida.', 'aura-suite' ) );
        }

        // Generar nombre único con hash
        $safe_name = uniqid( 'af_', true ) . '.' . $ext;
        $dest_path = $dest_dir . '/' . $safe_name;

        // Mover archivo
        if ( ! move_uploaded_file( $file_data['tmp_name'], $dest_path ) ) {
            return new WP_Error( 'move', __( 'Error al guardar el archivo.', 'aura-suite' ) );
        }

        return $sub_dir . '/' . $safe_name;
    }

    /**
     * Sanitiza un valor de campo según su tipo.
     *
     * @param string $type  Tipo de campo.
     * @param mixed  $value Valor crudo del POST.
     * @return string Valor sanitizado.
     */
    private static function sanitize_field_value( string $type, mixed $value ): string {
        if ( ! is_string( $value ) ) {
            $value = '';
        }

        switch ( $type ) {
            case 'email':
                return sanitize_email( $value );
            case 'number':
            case 'scale':
                return is_numeric( $value ) ? sanitize_text_field( $value ) : '';
            case 'date':
            case 'time':
            case 'birthdate':
                return sanitize_text_field( $value );
            case 'textarea':
                return wp_kses_post( $value );
            case 'hidden':
                return sanitize_text_field( $value );
            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Calcula la edad en años completos desde una fecha ISO (Y-m-d).
     *
     * @param string $date_iso Fecha de nacimiento en formato Y-m-d.
     * @return int Edad en años.
     */
    private static function calculate_age( string $date_iso ): int {
        $birth   = new DateTime( $date_iso );
        $today   = new DateTime( 'today' );
        $diff    = $today->diff( $birth );
        return (int) $diff->y;
    }

    /**
     * Obtiene la IP real del cliente con soporte para proxies confiables.
     *
     * @return string Dirección IP sanitizada.
     */
    private static function get_client_ip(): string {
        $candidates = [
            $_SERVER['HTTP_CLIENT_IP']       ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP']       ?? '',
            $_SERVER['REMOTE_ADDR']          ?? '',
        ];

        foreach ( $candidates as $ip ) {
            // Si viene una lista de IPs (X-Forwarded-For puede tenerlas), tomar la primera
            $ip = trim( explode( ',', $ip )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return sanitize_text_field( $ip );
            }
        }

        return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    }

    /**
     * Devuelve el mensaje de éxito del formulario o uno genérico.
     *
     * @param object $form Fila de aura_forms.
     * @return string Mensaje de éxito.
     */
    private static function get_success_message( object $form ): string {
        return $form->success_message
            ? wp_kses_post( $form->success_message )
            : __( '¡Gracias! Tu respuesta ha sido enviada correctamente.', 'aura-suite' );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — ADMIN: LISTAR SUBMISSIONS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_submissions(): void {
        global $wpdb;

        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'No tienes permiso.', 'aura-suite' ), 403 );
        }

        $form_id  = absint( $_POST['form_id'] ?? 0 );
        $per_page = min( absint( $_POST['per_page'] ?? 20 ), 100 );
        $paged    = max( absint( $_POST['paged'] ?? 1 ), 1 );
        $status   = sanitize_key( $_POST['status'] ?? '' );
        $search   = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

        if ( ! $form_id ) {
            wp_send_json_error( __( 'ID de formulario inválido.', 'aura-suite' ), 400 );
        }

        $where  = [ 's.form_id = %d' ];
        $params = [ $form_id ];

        $allowed_statuses = [ 'received', 'reviewed', 'spam' ];
        if ( $status && in_array( $status, $allowed_statuses, true ) ) {
            $where[]  = 's.status = %s';
            $params[] = $status;
        }

        if ( $search !== '' ) {
            $where[]  = '(s.submitted_name LIKE %s OR s.submitted_email LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = ( $paged - 1 ) * $per_page;

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aura_form_submissions s WHERE {$where_sql}",
                ...$params
            )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.id, s.submitted_name, s.submitted_email, s.status,
                        s.ip_address, s.submitted_at, s.enrollment_id
                   FROM {$wpdb->prefix}aura_form_submissions s
                  WHERE {$where_sql}
               ORDER BY s.submitted_at DESC
                  LIMIT %d OFFSET %d",
                ...[ ...$params, $per_page, $offset ]
            )
        );

        wp_send_json_success( [
            'total'    => $total,
            'pages'    => (int) ceil( $total / $per_page ),
            'paged'    => $paged,
            'per_page' => $per_page,
            'rows'     => $rows,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — ADMIN: DETALLE DE UNA SUBMISSION
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_submission(): void {
        global $wpdb;

        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'No tienes permiso.', 'aura-suite' ), 403 );
        }

        $sub_id = absint( $_POST['sub_id'] ?? 0 );
        if ( ! $sub_id ) {
            wp_send_json_error( __( 'ID inválido.', 'aura-suite' ), 400 );
        }

        $sub = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aura_form_submissions WHERE id = %d",
                $sub_id
            )
        );

        if ( ! $sub ) {
            wp_send_json_error( __( 'Respuesta no encontrada.', 'aura-suite' ), 404 );
        }

        // Cargar etiquetas de campos del formulario
        $fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT field_uid, label, field_type
                   FROM {$wpdb->prefix}aura_form_fields
                  WHERE form_id = %d
               ORDER BY sort_order ASC",
                $sub->form_id
            )
        );

        $label_map = [];
        foreach ( $fields as $f ) {
            $label_map[ $f->field_uid ] = [ 'label' => $f->label, 'type' => $f->field_type ];
        }

        $data_raw    = json_decode( $sub->data_json, true ) ?: [];
        $data_parsed = [];

        foreach ( $data_raw as $uid => $value ) {
            // Ignorar claves auxiliares _iso_date
            if ( str_ends_with( (string) $uid, '_iso_date' ) ) {
                continue;
            }

            $label = isset( $label_map[ $uid ] ) ? $label_map[ $uid ]['label'] : $uid;
            $ftype = $label_map[ $uid ]['type'] ?? 'text';

            // Fecha de nacimiento: dos filas separadas — {label} (fecha) y {label} (edad)
            if ( $ftype === 'birthdate' ) {
                $iso_date = $data_raw[ $uid . '_iso_date' ] ?? '';
                $data_parsed[] = [
                    'uid'   => $uid . '_iso_date',
                    'label' => $label . ' (fecha)',
                    'type'  => 'text',
                    'value' => $iso_date ?: '—',
                ];
                $age_display = ( $value !== '' && $value !== null ) ? sprintf( '%d años', (int) $value ) : '—';
                $data_parsed[] = [
                    'uid'   => $uid,
                    'label' => $label . ' (edad)',
                    'type'  => 'text',
                    'value' => $age_display,
                ];
                continue;
            }

            // Decodificar arrays JSON (checkbox, multiselect)
            if ( is_string( $value ) ) {
                $decoded = json_decode( $value, true );
                if ( is_array( $decoded ) ) {
                    $value = implode( ', ', $decoded );
                }
            }

            $data_parsed[] = [
                'uid'   => $uid,
                'label' => $label,
                'type'  => $ftype,
                'value' => $value,
            ];
        }

        wp_send_json_success( [
            'sub' => [
                'id'              => (int) $sub->id,
                'form_id'         => (int) $sub->form_id,
                'submitted_name'  => $sub->submitted_name,
                'submitted_email' => $sub->submitted_email,
                'status'          => $sub->status,
                'ip_address'      => $sub->ip_address,
                'submitted_at'    => $sub->submitted_at,
                'enrollment_id'   => $sub->enrollment_id ? (int) $sub->enrollment_id : null,
            ],
            'fields' => $data_parsed,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — ADMIN: ELIMINAR SUBMISSION
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_submission(): void {
        global $wpdb;

        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'No tienes permiso.', 'aura-suite' ), 403 );
        }

        $sub_id = absint( $_POST['sub_id'] ?? 0 );
        if ( ! $sub_id ) {
            wp_send_json_error( __( 'ID inválido.', 'aura-suite' ), 400 );
        }

        $result = $wpdb->delete(
            $wpdb->prefix . 'aura_form_submissions',
            [ 'id' => $sub_id ],
            [ '%d' ]
        );

        if ( $result === false ) {
            wp_send_json_error( __( 'Error al eliminar la respuesta.', 'aura-suite' ), 500 );
        }

        wp_send_json_success( [ 'message' => __( 'Respuesta eliminada correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — ADMIN: ACCIONES MASIVAS SOBRE SUBMISSIONS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_bulk_submissions(): void {
        global $wpdb;

        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        $bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );

        // Permisos según acción
        if ( $bulk_action === 'delete' ) {
            if ( ! current_user_can( 'aura_forms_delete' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( __( 'No tienes permiso.', 'aura-suite' ), 403 );
            }
        } elseif ( $bulk_action === 'mark_reviewed' ) {
            if ( ! current_user_can( 'aura_forms_view_responses_all' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( __( 'No tienes permiso.', 'aura-suite' ), 403 );
            }
        } else {
            wp_send_json_error( __( 'Acción no reconocida.', 'aura-suite' ), 400 );
        }

        $ids_raw = isset( $_POST['ids'] ) && is_array( $_POST['ids'] )
            ? array_map( 'absint', $_POST['ids'] )
            : [];
        $ids = array_values( array_filter( $ids_raw ) );

        if ( empty( $ids ) ) {
            wp_send_json_error( __( 'No se seleccionaron respuestas.', 'aura-suite' ), 400 );
        }

        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

        if ( $bulk_action === 'delete' ) {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}aura_form_submissions WHERE id IN ({$placeholders})",
                    ...$ids
                )
            );
            wp_send_json_success( [
                'message' => sprintf(
                    /* translators: %d = number of deleted submissions */
                    _n( '%d respuesta eliminada.', '%d respuestas eliminadas.', $deleted, 'aura-suite' ),
                    $deleted
                ),
                'deleted' => $deleted,
            ] );
        }

        if ( $bulk_action === 'mark_reviewed' ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}aura_form_submissions
                        SET status = 'reviewed',
                            reviewed_by = %d,
                            reviewed_at = %s
                      WHERE id IN ({$placeholders})",
                    ...[ get_current_user_id(), current_time( 'mysql' ), ...$ids ]
                )
            );
            wp_send_json_success( [
                'message' => __( 'Respuestas marcadas como revisadas.', 'aura-suite' ),
            ] );
        }
    }
}
