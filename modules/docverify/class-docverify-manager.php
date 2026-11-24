<?php
/**
 * Gestor CRUD, Almacenamiento Google Drive / Local y Auditoría de Validación Documental
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_Manager {

    /**
     * Calcula el estado efectivo considerando revocación y fecha de expiración.
     *
     * @param object|array $doc
     * @return string 'active' | 'expired' | 'revoked'
     */
    public static function get_effective_status( $doc ): string {
        $status     = is_array( $doc ) ? (string) ( $doc['status'] ?? 'active' ) : (string) ( $doc->status ?? 'active' );
        $expires_at = is_array( $doc ) ? (string) ( $doc['expires_at'] ?? '' ) : (string) ( $doc->expires_at ?? '' );

        if ( $status === 'revoked' ) {
            return 'revoked';
        }
        if ( $expires_at !== '' && $expires_at !== '0000-00-00' ) {
            $today = current_time( 'Y-m-d' );
            if ( $expires_at < $today ) {
                return 'expired';
            }
        }
        return 'active';
    }

    /**
     * Comprueba si Google Drive está configurado y listo para usar desde Validación Documental.
     */
    public static function is_drive_available(): bool {
        if ( ! Aura_DocVerify_Settings::get( 'use_google_drive', true ) ) {
            return false;
        }
        if ( ! class_exists( 'Aura_Drive_Manager' ) ) {
            return false;
        }
        $drive = new Aura_Drive_Manager();
        return $drive->is_ready();
    }

    /**
     * Lista firmantes activos registrados en AURA (solo lectura) para autocompletar en el formulario.
     */
    public static function get_available_signers(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_signers';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $exists ) {
            return [];
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( "SELECT id, name, title, wp_user_id FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC" ) ?: [];
    }

    /**
     * Registra un nuevo documento verificable a partir de un archivo PDF subido.
     *
     * @param array      $data     Campos del formulario sanitizados.
     * @param array|null $pdf_file Entrada de $_FILES['signed_pdf'].
     * @return array|WP_Error
     */
    public static function register_document( array $data, ?array $pdf_file = null ) {
        global $wpdb;

        if ( empty( $pdf_file ) || empty( $pdf_file['tmp_name'] ) || ! empty( $pdf_file['error'] ) ) {
            return new WP_Error( 'missing_pdf', __( 'Debes adjuntar el archivo PDF firmado del documento.', 'aura-suite' ) );
        }

        $tmp_upload = (string) $pdf_file['tmp_name'];
        $valid_pdf  = Aura_DocVerify_PDF::validate_pdf_file( $tmp_upload );
        if ( is_wp_error( $valid_pdf ) ) {
            return $valid_pdf;
        }

        $doc_type      = sanitize_key( $data['doc_type'] ?? 'official_letter' );
        $title_subject = sanitize_text_field( $data['title_subject'] ?? '' );
        $holder_name   = sanitize_text_field( $data['holder_name'] ?? '' );
        $issued_at     = self::sanitize_date( $data['issued_at'] ?? '' ) ?: current_time( 'Y-m-d' );
        $expires_at    = self::sanitize_date( $data['expires_at'] ?? '' );

        if ( $title_subject === '' ) {
            return new WP_Error( 'missing_title', __( 'El título o asunto del documento es obligatorio.', 'aura-suite' ) );
        }
        if ( $holder_name === '' ) {
            return new WP_Error( 'missing_holder', __( 'El nombre del titular o persona invitada es obligatorio.', 'aura-suite' ) );
        }

        $issued_year       = (int) substr( $issued_at, 0, 4 ) ?: (int) current_time( 'Y' );
        $folio             = Aura_DocVerify_Folio::generate( $doc_type, $issued_year );
        $verification_code = Aura_DocVerify_Folio::generate_verification_code();
        $verify_url        = Aura_DocVerify_Folio::build_verify_url( $folio, $verification_code );

        // Guardar copia temporal de trabajo en el directorio privado protegido
        $base     = Aura_DocVerify_Setup::ensure_protected_storage_dir();
        $ym_rel   = substr( $issued_at, 0, 4 ) . '/' . substr( $issued_at, 5, 2 );
        $dest_dir = $base . '/pdf/' . $ym_rel;
        wp_mkdir_p( $dest_dir );
        self::protect_subdir( $dest_dir );

        $orig_filename  = sanitize_file_name( (string) ( $pdf_file['name'] ?? ( $folio . '.pdf' ) ) );
        $orig_local     = $dest_dir . '/' . sanitize_file_name( $folio ) . '-original.pdf';

        if ( ! @move_uploaded_file( $tmp_upload, $orig_local ) ) {
            if ( ! @copy( $tmp_upload, $orig_local ) ) {
                return new WP_Error( 'pdf_move_failed', __( 'No se pudo almacenar temporalmente el PDF en el servidor.', 'aura-suite' ) );
            }
        }

        $sha256_original = Aura_DocVerify_PDF::compute_sha256( $orig_local );
        $orig_size       = (int) filesize( $orig_local );

        // Generar QR local (PNG + SVG)
        $qr_files = Aura_DocVerify_PDF::generate_qr_files( $folio, $verify_url );

        $stamp_mode = sanitize_key( $data['stamp_mode'] ?? Aura_DocVerify_Settings::get( 'default_stamp_mode', 'annex_page' ) );
        if ( ! in_array( $stamp_mode, [ 'annex_page', 'overlay_corner', 'none' ], true ) ) {
            $stamp_mode = 'annex_page';
        }
        $stamp_pos = sanitize_key( $data['stamp_position'] ?? Aura_DocVerify_Settings::get( 'default_stamp_pos', 'bottom_right' ) );
        if ( ! in_array( $stamp_pos, [ 'bottom_right', 'bottom_left', 'bottom_bar' ], true ) ) {
            $stamp_pos = 'bottom_right';
        }

        $holder_doc_raw    = trim( sanitize_text_field( $data['holder_doc_number'] ?? '' ) );
        $holder_doc_masked = $holder_doc_raw !== '' ? Aura_DocVerify_Folio::mask_document_number( $holder_doc_raw ) : '';
        $holder_doc_hash   = $holder_doc_raw !== '' ? Aura_DocVerify_Folio::hash_document_number( $holder_doc_raw ) : '';

        $privacy_mode = sanitize_key( $data['privacy_mode'] ?? Aura_DocVerify_Settings::get( 'default_privacy_mode', 'standard_masked' ) );
        if ( ! in_array( $privacy_mode, [ 'standard_masked', 'pin_protected', 'public_summary' ], true ) ) {
            $privacy_mode = 'standard_masked';
        }
        $allow_public_pdf = ! empty( $data['allow_public_pdf'] ) ? 1 : 0;

        $access_pin_raw  = trim( (string) ( $data['access_pin'] ?? '' ) );
        $access_pin_hash = $access_pin_raw !== '' ? wp_hash_password( $access_pin_raw ) : '';

        $doc_payload = [
            'folio'                 => $folio,
            'verification_code'     => $verification_code,
            'doc_type'              => $doc_type,
            'title_subject'         => $title_subject,
            'doc_language'          => sanitize_key( $data['doc_language'] ?? 'es' ),
            'recipient_entity'      => sanitize_text_field( $data['recipient_entity'] ?? '' ),
            'recipient_country'     => sanitize_text_field( $data['recipient_country'] ?? '' ),
            'purpose'               => sanitize_text_field( $data['purpose'] ?? '' ),
            'holder_name'           => $holder_name,
            'holder_doc_type'       => sanitize_key( $data['holder_doc_type'] ?? 'none' ),
            'holder_doc_masked'     => $holder_doc_masked,
            'holder_doc_hash'       => $holder_doc_hash,
            'travel_or_event_start' => self::sanitize_date( $data['travel_or_event_start'] ?? '' ),
            'travel_or_event_end'   => self::sanitize_date( $data['travel_or_event_end'] ?? '' ),
            'stay_address'          => sanitize_text_field( $data['stay_address'] ?? '' ),
            'signer_id'             => ! empty( $data['signer_id'] ) ? absint( $data['signer_id'] ) : null,
            'signer_name'           => sanitize_text_field( $data['signer_name'] ?? '' ),
            'signer_title'          => sanitize_text_field( $data['signer_title'] ?? '' ),
            'signer_email'          => sanitize_email( $data['signer_email'] ?? '' ),
            'issued_at'             => $issued_at,
            'expires_at'            => $expires_at,
            'sha256_original'       => $sha256_original,
            'qr_png_path'           => $qr_files['png_path'],
            'qr_svg_path'           => $qr_files['svg_path'],
            'verify_url'            => $verify_url,
            'stamp_mode'            => $stamp_mode,
            'stamp_position'        => $stamp_pos,
        ];

        // Generar PDF sellado o con Hoja Anexa si corresponde
        $stamped_local  = '';
        $sha256_stamped = '';
        $stamp_warning  = '';

        if ( $stamp_mode !== 'none' ) {
            $candidate_stamped = $dest_dir . '/' . sanitize_file_name( $folio ) . '-certificado.pdf';
            $stamp_res         = Aura_DocVerify_PDF::create_stamped_pdf( $orig_local, $candidate_stamped, $doc_payload, $stamp_mode, $stamp_pos );
            if ( is_wp_error( $stamp_res ) ) {
                $stamp_warning             = $stamp_res->get_error_message();
                $doc_payload['stamp_mode'] = 'none';
            } else {
                $stamped_local  = $candidate_stamped;
                $sha256_stamped = Aura_DocVerify_PDF::compute_sha256( $stamped_local );
            }
        }

        // Subir a Google Drive (si está habilitado y listo)
        $storage_driver         = 'local';
        $drive_orig_file_id     = '';
        $drive_orig_url         = '';
        $drive_stamped_file_id  = '';
        $drive_stamped_url      = '';
        $keep_local             = (bool) Aura_DocVerify_Settings::get( 'keep_local_backup', false );

        if ( self::is_drive_available() ) {
            $drive         = new Aura_Drive_Manager();
            $drive_folder  = (string) Aura_DocVerify_Settings::get( 'drive_folder_name', 'Validación Documental' );
            // Privado: nunca otorgar 'anyone' en Google Drive; se sirve vía proxy PHP tras validación
            $make_public   = false;

            $up_orig = $drive->upload_file(
                $orig_local,
                sanitize_file_name( $folio ) . '-original.pdf',
                'application/pdf',
                $drive_folder,
                $issued_at,
                $make_public
            );

            if ( is_array( $up_orig ) && ! empty( $up_orig['file_id'] ) ) {
                $storage_driver     = 'google_drive';
                $drive_orig_file_id = (string) $up_orig['file_id'];
                $drive_orig_url     = (string) ( $up_orig['view_url'] ?? '' );

                if ( $stamped_local !== '' && file_exists( $stamped_local ) ) {
                    $up_stamp = $drive->upload_file(
                        $stamped_local,
                        sanitize_file_name( $folio ) . '-certificado.pdf',
                        'application/pdf',
                        $drive_folder,
                        $issued_at,
                        $make_public
                    );
                    if ( is_array( $up_stamp ) && ! empty( $up_stamp['file_id'] ) ) {
                        $drive_stamped_file_id = (string) $up_stamp['file_id'];
                        $drive_stamped_url     = (string) ( $up_stamp['view_url'] ?? '' );
                    }
                }

                // Si no se desea copia local redundante y Drive guardó todo con éxito, limpiar PDFs locales
                if ( ! $keep_local && ( $stamped_local === '' || $drive_stamped_file_id !== '' ) ) {
                    @unlink( $orig_local );
                    $orig_local = '';
                    if ( $stamped_local !== '' ) {
                        @unlink( $stamped_local );
                        $stamped_local = '';
                    }
                }
            }
        }

        $table = $wpdb->prefix . 'aura_verified_documents';
        $inserted = $wpdb->insert(
            $table,
            [
                'folio'                  => $folio,
                'verification_code'      => $verification_code,
                'doc_type'               => $doc_type,
                'title_subject'          => $title_subject,
                'doc_language'           => $doc_payload['doc_language'],
                'recipient_entity'       => $doc_payload['recipient_entity'],
                'recipient_country'      => $doc_payload['recipient_country'],
                'purpose'                => $doc_payload['purpose'],
                'holder_name'            => $holder_name,
                'holder_doc_type'        => $doc_payload['holder_doc_type'],
                'holder_doc_masked'      => $holder_doc_masked,
                'holder_doc_hash'        => $holder_doc_hash,
                'travel_or_event_start'  => $doc_payload['travel_or_event_start'],
                'travel_or_event_end'    => $doc_payload['travel_or_event_end'],
                'stay_address'           => $doc_payload['stay_address'],
                'signer_id'              => $doc_payload['signer_id'],
                'signer_name'            => $doc_payload['signer_name'],
                'signer_title'           => $doc_payload['signer_title'],
                'signer_email'           => $doc_payload['signer_email'],
                'issued_at'              => $issued_at,
                'expires_at'             => $expires_at,
                'storage_driver'         => $storage_driver,
                'original_pdf_path'      => $orig_local,
                'drive_original_file_id' => $drive_orig_file_id,
                'drive_original_url'     => $drive_orig_url,
                'original_pdf_name'      => $orig_filename,
                'original_pdf_size'      => $orig_size,
                'sha256_original'        => $sha256_original,
                'stamped_pdf_path'       => $stamped_local,
                'drive_stamped_file_id'  => $drive_stamped_file_id,
                'drive_stamped_url'      => $drive_stamped_url,
                'sha256_stamped'         => $sha256_stamped,
                'stamp_mode'             => $doc_payload['stamp_mode'],
                'stamp_position'         => $stamp_pos,
                'qr_png_path'            => $qr_files['png_path'],
                'qr_svg_path'            => $qr_files['svg_path'],
                'verify_url'             => $verify_url,
                'privacy_mode'           => $privacy_mode,
                'access_pin_hash'        => $access_pin_hash,
                'allow_public_pdf'       => $allow_public_pdf,
                'status'                 => 'active',
                'internal_notes'         => sanitize_textarea_field( $data['internal_notes'] ?? '' ),
                'created_by'             => get_current_user_id(),
            ]
        );

        if ( ! $inserted ) {
            return new WP_Error( 'db_insert_failed', __( 'Error al guardar el registro documental en la base de datos.', 'aura-suite' ) );
        }

        $doc_id = (int) $wpdb->insert_id;
        self::log_event( $doc_id, $folio, 'created', 'ok', [
            'storage_driver' => $storage_driver,
            'stamp_mode'     => $doc_payload['stamp_mode'],
            'drive_file_id'  => $drive_orig_file_id,
        ] );

        return [
            'id'                => $doc_id,
            'folio'             => $folio,
            'verification_code' => $verification_code,
            'verify_url'        => $verify_url,
            'storage_driver'    => $storage_driver,
            'sha256_original'   => $sha256_original,
            'sha256_stamped'    => $sha256_stamped,
            'warning'           => $stamp_warning,
        ];
    }

    /**
     * Actualiza metadatos, vigencia y privacidad de un documento existente.
     *
     * @return true|WP_Error
     */
    public static function update_document( int $id, array $data ) {
        global $wpdb;
        $doc = self::get_document( $id );
        if ( ! $doc ) {
            return new WP_Error( 'not_found', __( 'Documento no encontrado.', 'aura-suite' ) );
        }

        $update = [
            'doc_type'              => sanitize_key( $data['doc_type'] ?? $doc->doc_type ),
            'title_subject'         => sanitize_text_field( $data['title_subject'] ?? $doc->title_subject ),
            'doc_language'          => sanitize_key( $data['doc_language'] ?? $doc->doc_language ),
            'recipient_entity'      => sanitize_text_field( $data['recipient_entity'] ?? '' ),
            'recipient_country'     => sanitize_text_field( $data['recipient_country'] ?? '' ),
            'purpose'               => sanitize_text_field( $data['purpose'] ?? '' ),
            'holder_name'           => sanitize_text_field( $data['holder_name'] ?? $doc->holder_name ),
            'holder_doc_type'       => sanitize_key( $data['holder_doc_type'] ?? $doc->holder_doc_type ),
            'travel_or_event_start' => self::sanitize_date( $data['travel_or_event_start'] ?? '' ),
            'travel_or_event_end'   => self::sanitize_date( $data['travel_or_event_end'] ?? '' ),
            'stay_address'          => sanitize_text_field( $data['stay_address'] ?? '' ),
            'signer_id'             => ! empty( $data['signer_id'] ) ? absint( $data['signer_id'] ) : null,
            'signer_name'           => sanitize_text_field( $data['signer_name'] ?? '' ),
            'signer_title'          => sanitize_text_field( $data['signer_title'] ?? '' ),
            'signer_email'          => sanitize_email( $data['signer_email'] ?? '' ),
            'issued_at'             => self::sanitize_date( $data['issued_at'] ?? '' ) ?: $doc->issued_at,
            'expires_at'            => self::sanitize_date( $data['expires_at'] ?? '' ),
            'privacy_mode'          => sanitize_key( $data['privacy_mode'] ?? $doc->privacy_mode ),
            'allow_public_pdf'      => ! empty( $data['allow_public_pdf'] ) ? 1 : 0,
            'internal_notes'        => sanitize_textarea_field( $data['internal_notes'] ?? '' ),
        ];

        $holder_doc_raw = trim( sanitize_text_field( $data['holder_doc_number'] ?? '' ) );
        if ( $holder_doc_raw !== '' ) {
            $update['holder_doc_masked'] = Aura_DocVerify_Folio::mask_document_number( $holder_doc_raw );
            $update['holder_doc_hash']   = Aura_DocVerify_Folio::hash_document_number( $holder_doc_raw );
        }

        $new_pin = trim( (string) ( $data['access_pin'] ?? '' ) );
        if ( $new_pin !== '' ) {
            $update['access_pin_hash'] = wp_hash_password( $new_pin );
        } elseif ( ! empty( $data['clear_access_pin'] ) ) {
            $update['access_pin_hash'] = '';
        }

        $table = $wpdb->prefix . 'aura_verified_documents';
        $wpdb->update( $table, $update, [ 'id' => $id ] );

        self::log_event( $id, $doc->folio, 'updated', 'ok', [ 'updated_by' => get_current_user_id() ] );
        return true;
    }

    /**
     * Revoca la autenticidad de un documento con motivo obligatorio.
     *
     * @return true|WP_Error
     */
    public static function revoke_document( int $id, string $reason ) {
        global $wpdb;
        $doc = self::get_document( $id );
        if ( ! $doc ) {
            return new WP_Error( 'not_found', __( 'Documento no encontrado.', 'aura-suite' ) );
        }
        $reason = trim( sanitize_text_field( $reason ) );
        if ( $reason === '' ) {
            return new WP_Error( 'missing_reason', __( 'Debes indicar el motivo de revocación.', 'aura-suite' ) );
        }

        $table = $wpdb->prefix . 'aura_verified_documents';
        $wpdb->update(
            $table,
            [
                'status'        => 'revoked',
                'revoked_at'    => current_time( 'mysql' ),
                'revoked_by'    => get_current_user_id(),
                'revoke_reason' => $reason,
            ],
            [ 'id' => $id ]
        );

        self::log_event( $id, $doc->folio, 'revoked', 'revoked', [ 'reason' => $reason ] );
        return true;
    }

    /**
     * Reactiva un documento previamente revocado.
     *
     * @return true|WP_Error
     */
    public static function reactivate_document( int $id ) {
        global $wpdb;
        $doc = self::get_document( $id );
        if ( ! $doc ) {
            return new WP_Error( 'not_found', __( 'Documento no encontrado.', 'aura-suite' ) );
        }

        $table = $wpdb->prefix . 'aura_verified_documents';
        $wpdb->update(
            $table,
            [
                'status'        => 'active',
                'revoked_at'    => null,
                'revoked_by'    => null,
                'revoke_reason' => null,
            ],
            [ 'id' => $id ]
        );

        self::log_event( $id, $doc->folio, 'reactivated', 'ok' );
        return true;
    }

    /**
     * Elimina un documento junto a sus archivos en Google Drive y locales.
     *
     * @return true|WP_Error
     */
    public static function delete_document( int $id ) {
        global $wpdb;
        $doc = self::get_document( $id );
        if ( ! $doc ) {
            return new WP_Error( 'not_found', __( 'Documento no encontrado.', 'aura-suite' ) );
        }

        if ( class_exists( 'Aura_Drive_Manager' ) ) {
            if ( ! empty( $doc->drive_original_file_id ) ) {
                Aura_Drive_Manager::delete_file( (string) $doc->drive_original_file_id );
            }
            if ( ! empty( $doc->drive_stamped_file_id ) ) {
                Aura_Drive_Manager::delete_file( (string) $doc->drive_stamped_file_id );
            }
        }

        foreach ( [ $doc->original_pdf_path, $doc->stamped_pdf_path, $doc->qr_png_path, $doc->qr_svg_path ] as $path ) {
            if ( ! empty( $path ) && file_exists( $path ) ) {
                @unlink( $path );
            }
        }

        $wpdb->delete( $wpdb->prefix . 'aura_docverify_logs', [ 'document_id' => $id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'aura_verified_documents', [ 'id' => $id ], [ '%d' ] );
        return true;
    }

    /**
     * Lee el binario del PDF (original o sellado) desde disco local o Google Drive vía Service Account.
     *
     * @param object $doc
     * @param string $variant 'stamped' | 'original'
     * @return array{content:string, filename:string}|WP_Error
     */
    public static function read_pdf_binary( $doc, string $variant = 'stamped' ) {
        if ( ! $doc ) {
            return new WP_Error( 'not_found', __( 'Documento no encontrado.', 'aura-suite' ) );
        }

        $use_stamped = ( $variant === 'stamped' ) && ( ! empty( $doc->stamped_pdf_path ) || ! empty( $doc->drive_stamped_file_id ) );
        $local_path  = $use_stamped ? (string) $doc->stamped_pdf_path : (string) $doc->original_pdf_path;
        $drive_id    = $use_stamped ? (string) $doc->drive_stamped_file_id : (string) $doc->drive_original_file_id;
        $suffix      = $use_stamped ? '-certificado.pdf' : '-original.pdf';
        $filename    = sanitize_file_name( (string) $doc->folio ) . $suffix;

        if ( $local_path !== '' && file_exists( $local_path ) && is_file( $local_path ) ) {
            $content = @file_get_contents( $local_path );
            if ( is_string( $content ) && $content !== '' ) {
                return [ 'content' => $content, 'filename' => $filename ];
            }
        }

        if ( $drive_id !== '' && class_exists( 'Aura_Drive_Manager' ) ) {
            $drive = new Aura_Drive_Manager();
            if ( $drive->is_ready() ) {
                $content = $drive->download_file_content( $drive_id );
                if ( is_string( $content ) && $content !== '' ) {
                    return [ 'content' => $content, 'filename' => $filename ];
                }
            }
        }

        return new WP_Error( 'pdf_unavailable', __( 'El archivo PDF no está disponible en almacenamiento local ni en Google Drive.', 'aura-suite' ) );
    }

    /**
     * Obtiene un documento por ID.
     */
    public static function get_document( int $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_verified_documents';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    /**
     * Obtiene un documento por folio.
     */
    public static function get_document_by_folio( string $folio ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_verified_documents';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE folio = %s", strtoupper( trim( $folio ) ) ) );
    }

    /**
     * Lista documentos paginados y filtrados para la vista de administración.
     */
    public static function list_documents( array $args = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_verified_documents';

        $page     = max( 1, absint( $args['page'] ?? 1 ) );
        $per_page = max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = trim( sanitize_text_field( $args['search'] ?? '' ) );
        $doc_type = sanitize_key( $args['doc_type'] ?? '' );
        $status   = sanitize_key( $args['status'] ?? '' );
        $driver   = sanitize_key( $args['storage_driver'] ?? '' );

        $where  = [ '1=1' ];
        $params = [];

        if ( $search !== '' ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(folio LIKE %s OR title_subject LIKE %s OR holder_name LIKE %s OR recipient_entity LIKE %s OR verification_code LIKE %s)';
            $params   = array_merge( $params, [ $like, $like, $like, $like, $like ] );
        }
        if ( $doc_type !== '' ) {
            $where[]  = 'doc_type = %s';
            $params[] = $doc_type;
        }
        if ( $driver !== '' ) {
            $where[]  = 'storage_driver = %s';
            $params[] = $driver;
        }

        $today = current_time( 'Y-m-d' );
        if ( $status === 'revoked' ) {
            $where[] = "status = 'revoked'";
        } elseif ( $status === 'expired' ) {
            $where[]  = "status = 'active' AND expires_at IS NOT NULL AND expires_at != '0000-00-00' AND expires_at < %s";
            $params[] = $today;
        } elseif ( $status === 'active' ) {
            $where[]  = "status = 'active' AND (expires_at IS NULL OR expires_at = '0000-00-00' OR expires_at >= %s)";
            $params[] = $today;
        }

        $where_sql = implode( ' AND ', $where );

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $count_sql );
        }

        $list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $list_params = array_merge( $params, [ $per_page, $offset ] );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) ) ?: [];

        return [
            'items'       => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
        ];
    }

    /**
     * Calcula KPIs del dashboard de Validación Documental.
     */
    public static function get_kpis(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_verified_documents';
        $logs  = $wpdb->prefix . 'aura_docverify_logs';
        $today = current_time( 'Y-m-d' );
        $soon  = wp_date( 'Y-m-d', strtotime( '+15 days' ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'active' AND (expires_at IS NULL OR expires_at = '0000-00-00' OR expires_at >= %s)", $today ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $expiring_soon = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at BETWEEN %s AND %s", $today, $soon ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $revoked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'revoked'" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $drive_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE storage_driver = 'google_drive'" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $verifications_30d = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$logs} WHERE event_type IN ('verify_qr', 'verify_manual', 'verify_hash_ok') AND created_at >= %s",
                wp_date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) )
            )
        );

        return [
            'total'             => $total,
            'active'            => $active,
            'expiring_soon'     => $expiring_soon,
            'revoked'           => $revoked,
            'drive_count'       => $drive_count,
            'verifications_30d' => $verifications_30d,
        ];
    }

    /**
     * Registra un evento en la tabla de auditoría con IP anonimizada.
     */
    public static function log_event( int $doc_id, string $folio, string $event_type, string $result = 'ok', array $meta = [] ): void {
        global $wpdb;
        $ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 175 ) : '';
        $ip_hash = $ip !== '' ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '';

        $wpdb->insert(
            $wpdb->prefix . 'aura_docverify_logs',
            [
                'document_id'      => $doc_id,
                'folio_queried'    => strtoupper( trim( $folio ) ),
                'event_type'       => sanitize_key( $event_type ),
                'result'           => sanitize_key( $result ),
                'ip_hash'          => $ip_hash,
                'user_agent_short' => $ua,
                'wp_user_id'       => get_current_user_id() ?: null,
                'meta_json'        => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
            ]
        );
    }

    /**
     * Lista eventos de auditoría paginados.
     */
    public static function list_logs( array $args = [] ): array {
        global $wpdb;
        $logs = $wpdb->prefix . 'aura_docverify_logs';
        $docs = $wpdb->prefix . 'aura_verified_documents';

        $page       = max( 1, absint( $args['page'] ?? 1 ) );
        $per_page   = max( 1, min( 100, absint( $args['per_page'] ?? 25 ) ) );
        $offset     = ( $page - 1 ) * $per_page;
        $event_type = sanitize_key( $args['event_type'] ?? '' );
        $search     = trim( sanitize_text_field( $args['search'] ?? '' ) );

        $where  = [ '1=1' ];
        $params = [];

        if ( $event_type !== '' ) {
            $where[]  = 'l.event_type = %s';
            $params[] = $event_type;
        }
        if ( $search !== '' ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(l.folio_queried LIKE %s OR d.title_subject LIKE %s OR d.holder_name LIKE %s)';
            $params   = array_merge( $params, [ $like, $like, $like ] );
        }

        $where_sql = implode( ' AND ', $where );
        $count_sql = "SELECT COUNT(*) FROM {$logs} l LEFT JOIN {$docs} d ON d.id = l.document_id WHERE {$where_sql}";

        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = (int) $wpdb->get_var( $count_sql );
        }

        $sql         = "SELECT l.*, d.title_subject, d.holder_name, d.doc_type FROM {$logs} l LEFT JOIN {$docs} d ON d.id = l.document_id WHERE {$where_sql} ORDER BY l.id DESC LIMIT %d OFFSET %d";
        $list_params = array_merge( $params, [ $per_page, $offset ] );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $list_params ) ) ?: [];

        return [
            'items'       => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
        ];
    }

    /**
     * Normaliza fecha Y-m-d.
     */
    private static function sanitize_date( string $raw ): ?string {
        $raw = trim( $raw );
        if ( $raw === '' ) {
            return null;
        }
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            return $raw;
        }
        $ts = strtotime( $raw );
        return $ts ? wp_date( 'Y-m-d', $ts ) : null;
    }

    /**
     * Garantiza .htaccess + index.php en subcarpetas de fecha.
     */
    private static function protect_subdir( string $dir ): void {
        if ( ! file_exists( $dir . '/index.php' ) ) {
            @file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
        }
        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            @file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n" );
        }
    }
}
