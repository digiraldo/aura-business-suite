<?php
/**
 * Administración del Módulo de Validación Documental
 *
 * Responsabilidades:
 *  - Registrar menús propios en WordPress Admin (Validación Documental)
 *  - Encolar CSS/JS exclusivos del módulo con filemtime()
 *  - Manejar endpoints AJAX autenticados y descargas protegidas desde Google Drive / local
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_Admin {

    /**
     * Inicializar hooks de administración.
     */
    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ], 17 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ], 20 );

        // AJAX CRUD y operaciones administrativas
        add_action( 'wp_ajax_aura_docverify_save',         [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_aura_docverify_get_detail',   [ __CLASS__, 'ajax_get_detail' ] );
        add_action( 'wp_ajax_aura_docverify_revoke',       [ __CLASS__, 'ajax_revoke' ] );
        add_action( 'wp_ajax_aura_docverify_reactivate',   [ __CLASS__, 'ajax_reactivate' ] );
        add_action( 'wp_ajax_aura_docverify_delete',       [ __CLASS__, 'ajax_delete' ] );
        add_action( 'wp_ajax_aura_docverify_download_pdf', [ __CLASS__, 'ajax_download_pdf' ] );
        add_action( 'wp_ajax_aura_docverify_download_qr',  [ __CLASS__, 'ajax_download_qr' ] );
        add_action( 'wp_ajax_aura_docverify_qr_preview',   [ __CLASS__, 'ajax_qr_preview' ] );
        add_action( 'wp_ajax_aura_docverify_sync_urls',    [ __CLASS__, 'ajax_sync_urls' ] );
    }

    /**
     * Registra el menú principal de Validación Documental y sus subpáginas.
     */
    public static function register_menus(): void {
        $has_access = (
            current_user_can( 'aura_docverify_view_all' ) ||
            current_user_can( 'aura_docverify_create' ) ||
            current_user_can( 'aura_docverify_audit' ) ||
            current_user_can( 'aura_docverify_settings' ) ||
            current_user_can( 'manage_options' )
        );

        if ( ! $has_access ) {
            return;
        }

        add_menu_page(
            __( 'Validación Documental — AURA', 'aura-suite' ),
            __( 'Validación Documental', 'aura-suite' ),
            'read',
            'aura-docverify',
            [ __CLASS__, 'render_dashboard' ],
            'dashicons-shield',
            3.5
        );

        add_submenu_page(
            'aura-docverify',
            __( 'Dashboard — Validación Documental', 'aura-suite' ),
            __( 'Dashboard', 'aura-suite' ),
            'read',
            'aura-docverify',
            [ __CLASS__, 'render_dashboard' ]
        );

        if ( current_user_can( 'aura_docverify_view_all' ) || current_user_can( 'aura_docverify_create' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-docverify',
                __( 'Documentos Verificables', 'aura-suite' ),
                __( 'Documentos', 'aura-suite' ),
                'read',
                'aura-docverify-list',
                [ __CLASS__, 'render_list' ]
            );
        }

        if ( current_user_can( 'aura_docverify_create' ) || current_user_can( 'aura_docverify_edit' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-docverify',
                __( 'Registrar / Subir PDF Firmado', 'aura-suite' ),
                __( 'Registrar PDF', 'aura-suite' ),
                'read',
                'aura-docverify-register',
                [ __CLASS__, 'render_form' ]
            );
        }

        if ( current_user_can( 'aura_docverify_audit' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-docverify',
                __( 'Auditoría de Verificaciones', 'aura-suite' ),
                __( 'Auditoría', 'aura-suite' ),
                'read',
                'aura-docverify-audit',
                [ __CLASS__, 'render_audit' ]
            );
        }

        if ( current_user_can( 'aura_docverify_settings' ) || current_user_can( 'manage_options' ) ) {
            add_submenu_page(
                'aura-docverify',
                __( 'Configuración — Validación Documental', 'aura-suite' ),
                __( 'Configuración', 'aura-suite' ),
                'read',
                'aura-docverify-settings',
                [ __CLASS__, 'render_settings' ]
            );
        }
    }

    /**
     * Encola assets exclusivos del módulo solo en pantallas de Validación Documental.
     */
    public static function enqueue_assets( string $hook ): void {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( strpos( $page, 'aura-docverify' ) !== 0 && strpos( $hook, 'aura-docverify' ) === false ) {
            return;
        }

        $plugin_dir = defined( 'AURA_PLUGIN_DIR' ) ? AURA_PLUGIN_DIR : dirname( __DIR__, 2 ) . '/';
        $plugin_url = defined( 'AURA_PLUGIN_URL' ) ? AURA_PLUGIN_URL : plugin_dir_url( dirname( __DIR__, 2 ) . '/aura-business-suite.php' );
        $ver_base   = defined( 'AURA_VERSION' ) ? AURA_VERSION : '1.9.0';

        $css_path = $plugin_dir . 'assets/css/docverify-admin.css';
        $js_path  = $plugin_dir . 'assets/js/docverify-admin.js';

        if ( file_exists( $css_path ) ) {
            $css_deps = wp_style_is( 'aura-design-system', 'registered' ) ? [ 'aura-design-system' ] : [];
            wp_enqueue_style(
                'aura-docverify-admin',
                $plugin_url . 'assets/css/docverify-admin.css',
                $css_deps,
                $ver_base . '.' . filemtime( $css_path )
            );
        }

        if ( $page === 'aura-docverify-settings' ) {
            wp_enqueue_media();
        }

        if ( file_exists( $js_path ) ) {
            $js_deps = wp_script_is( 'aura-ui-core', 'registered' ) ? [ 'jquery', 'aura-ui-core' ] : [ 'jquery' ];
            wp_enqueue_script(
                'aura-docverify-admin',
                $plugin_url . 'assets/js/docverify-admin.js',
                $js_deps,
                $ver_base . '.' . filemtime( $js_path ),
                true
            );

            wp_localize_script( 'aura-docverify-admin', 'auraDocVerify', [
                'ajaxurl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'aura_docverify_nonce' ),
                'listUrl'  => admin_url( 'admin.php?page=aura-docverify-list' ),
                'formUrl'  => admin_url( 'admin.php?page=aura-docverify-register' ),
                'i18n'     => [
                    'saving'          => __( 'Procesando PDF y generando código QR…', 'aura-suite' ),
                    'saved'           => __( 'Documento registrado y sellado correctamente.', 'aura-suite' ),
                    'updated'         => __( 'Documento actualizado correctamente.', 'aura-suite' ),
                    'error'           => __( 'Ocurrió un error al procesar la solicitud.', 'aura-suite' ),
                    'copied'          => __( 'Enlace copiado al portapapeles.', 'aura-suite' ),
                    'copiedCode'      => __( 'Código de verificación copiado.', 'aura-suite' ),
                    'confirmDelete'    => __( '¿Eliminar definitivamente este documento y sus archivos en almacenamiento?', 'aura-suite' ),
                    'confirmReact'     => __( '¿Reactivar la validez de este documento?', 'aura-suite' ),
                    'needRevokeReason' => __( 'Escribe el motivo oficial de revocación.', 'aura-suite' ),
                    'needPdf'          => __( 'Selecciona primero el archivo PDF firmado.', 'aura-suite' ),
                    'statusActive'     => __( 'Vigente', 'aura-suite' ),
                    'statusExpired'    => __( 'Expirado', 'aura-suite' ),
                    'statusRevoked'    => __( 'Revocado', 'aura-suite' ),
                    'drivePrivate'     => __( 'Google Drive (Privado)', 'aura-suite' ),
                    'localVault'       => __( 'Almacenamiento Local', 'aura-suite' ),
                    'verifCode'        => __( 'Código de Verificación', 'aura-suite' ),
                    'holderLabel'      => __( 'Titular / Invitado', 'aura-suite' ),
                    'recipientLabel'   => __( 'Entidad Destinataria', 'aura-suite' ),
                    'stayLabel'        => __( 'Lugar de Estadía / Respaldo', 'aura-suite' ),
                    'signerLabel'      => __( 'Firmante Autorizado', 'aura-suite' ),
                    'validityLabel'    => __( 'Emisión / Vigencia', 'aura-suite' ),
                    'noExpiration'     => __( 'Sin vencimiento', 'aura-suite' ),
                    'publicQueries'    => __( 'Consultas Públicas', 'aura-suite' ),
                    'revokeReasonTitle'=> __( 'Motivo de revocación', 'aura-suite' ),
                    'publicUrlLabel'   => __( 'URL Pública de Verificación', 'aura-suite' ),
                    'copyUrl'          => __( 'Copiar URL', 'aura-suite' ),
                    'openLink'         => __( 'Abrir', 'aura-suite' ),
                    'shaOrigLabel'     => __( 'SHA-256 PDF Original', 'aura-suite' ),
                    'shaStampLabel'    => __( 'SHA-256 PDF Sellado / Anexo', 'aura-suite' ),
                    'dlOrigBtn'        => __( 'Descargar PDF Original', 'aura-suite' ),
                    'dlStampBtn'       => __( 'Descargar PDF Certificado / Anexo', 'aura-suite' ),
                    'calcInServer'     => __( 'Se calculará en servidor', 'aura-suite' ),
                    'phMigRecipient'   => __( 'Ej: Autoridad Migratoria / Control Fronterizo', 'aura-suite' ),
                    'phMigPurpose'     => __( 'Ej: Ingreso al país sin visa — Acreditación de estadía, evento y respaldo institucional', 'aura-suite' ),
                    'phInvRecipient'   => __( 'Ej: Embajada de los Estados Unidos / Sección Consular', 'aura-suite' ),
                    'phInvPurpose'     => __( 'Ej: Trámite de Visa de Visitante B1/B2 — Participación en Seminario Internacional', 'aura-suite' ),
                    'syncingUrls'      => __( 'Actualizando URLs y regenerando códigos QR…', 'aura-suite' ),
                    'confirmSyncUrls'  => __( '¿Guardar cambios y recalcular las URLs y códigos QR de todos los documentos creados usando la ruta actual?', 'aura-suite' ),
                ],
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDERIZADO DE PANTALLAS
    // ─────────────────────────────────────────────────────────────

    public static function render_dashboard(): void {
        self::render_template( 'dashboard.php' );
    }

    public static function render_list(): void {
        self::render_template( 'documents-list.php' );
    }

    public static function render_form(): void {
        self::render_template( 'document-form.php' );
    }

    public static function render_audit(): void {
        self::render_template( 'audit-log.php' );
    }

    public static function render_settings(): void {
        self::render_template( 'settings.php' );
    }

    private static function render_template( string $file ): void {
        $path = AURA_PLUGIN_DIR . 'templates/docverify/' . $file;
        if ( file_exists( $path ) ) {
            include $path;
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>' .
                 esc_html__( 'Plantilla de Validación Documental no encontrada.', 'aura-suite' ) .
                 '</p></div></div>';
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ENDPOINTS AJAX PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Alta de documento nuevo (subida PDF + QR + Google Drive/local) o edición de metadatos.
     */
    public static function ajax_save(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        $doc_id = absint( $_POST['id'] ?? 0 );
        if ( $doc_id > 0 ) {
            if ( ! current_user_can( 'aura_docverify_edit' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => __( 'Sin permisos para editar documentos.', 'aura-suite' ) ], 403 );
            }
        } elseif ( ! current_user_can( 'aura_docverify_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para registrar documentos.', 'aura-suite' ) ], 403 );
        }

        $fields = [
            'doc_type'              => wp_unslash( $_POST['doc_type'] ?? 'official_letter' ),
            'title_subject'         => wp_unslash( $_POST['title_subject'] ?? '' ),
            'doc_language'          => wp_unslash( $_POST['doc_language'] ?? 'es' ),
            'recipient_entity'      => wp_unslash( $_POST['recipient_entity'] ?? '' ),
            'recipient_country'     => wp_unslash( $_POST['recipient_country'] ?? '' ),
            'purpose'               => wp_unslash( $_POST['purpose'] ?? '' ),
            'holder_name'           => wp_unslash( $_POST['holder_name'] ?? '' ),
            'holder_doc_type'       => wp_unslash( $_POST['holder_doc_type'] ?? 'none' ),
            'holder_doc_number'     => wp_unslash( $_POST['holder_doc_number'] ?? '' ),
            'travel_or_event_start' => wp_unslash( $_POST['travel_or_event_start'] ?? '' ),
            'travel_or_event_end'   => wp_unslash( $_POST['travel_or_event_end'] ?? '' ),
            'stay_address'          => wp_unslash( $_POST['stay_address'] ?? '' ),
            'signer_id'             => wp_unslash( $_POST['signer_id'] ?? 0 ),
            'signer_name'           => wp_unslash( $_POST['signer_name'] ?? '' ),
            'signer_title'          => wp_unslash( $_POST['signer_title'] ?? '' ),
            'signer_email'          => wp_unslash( $_POST['signer_email'] ?? '' ),
            'issued_at'             => wp_unslash( $_POST['issued_at'] ?? '' ),
            'expires_at'            => wp_unslash( $_POST['expires_at'] ?? '' ),
            'stamp_mode'            => wp_unslash( $_POST['stamp_mode'] ?? 'annex_page' ),
            'stamp_position'        => wp_unslash( $_POST['stamp_position'] ?? 'bottom_right' ),
            'privacy_mode'          => wp_unslash( $_POST['privacy_mode'] ?? 'standard_masked' ),
            'access_pin'            => wp_unslash( $_POST['access_pin'] ?? '' ),
            'clear_access_pin'      => ! empty( $_POST['clear_access_pin'] ),
            'allow_public_pdf'      => ! empty( $_POST['allow_public_pdf'] ),
            'internal_notes'        => wp_unslash( $_POST['internal_notes'] ?? '' ),
        ];

        if ( $doc_id > 0 ) {
            $res = Aura_DocVerify_Manager::update_document( $doc_id, $fields );
            if ( is_wp_error( $res ) ) {
                wp_send_json_error( [ 'message' => $res->get_error_message() ], 400 );
            }
            wp_send_json_success( [
                'id'       => $doc_id,
                'message'  => __( 'Documento actualizado correctamente.', 'aura-suite' ),
                'redirect' => admin_url( 'admin.php?page=aura-docverify-list' ),
            ] );
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $pdf_file = $_FILES['signed_pdf'] ?? null;
        $res      = Aura_DocVerify_Manager::register_document( $fields, $pdf_file );

        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ], 400 );
        }

        wp_send_json_success( array_merge( $res, [
            'message'  => __( 'Documento registrado con QR y verificación criptográfica.', 'aura-suite' ),
            'redirect' => admin_url( 'admin.php?page=aura-docverify-list&created=' . $res['id'] ),
        ] ) );
    }

    /**
     * AJAX: Obtener ficha completa del documento para modal 360° / QR.
     */
    public static function ajax_get_detail(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_view_all' ) && ! current_user_can( 'aura_docverify_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ], 403 );
        }

        $id  = absint( $_POST['id'] ?? 0 );
        $doc = Aura_DocVerify_Manager::get_document( $id );
        if ( ! $doc ) {
            wp_send_json_error( [ 'message' => __( 'Documento no encontrado.', 'aura-suite' ) ], 404 );
        }

        $type_labels = Aura_DocVerify_Settings::get_document_type_labels( (string) $doc->doc_type );
        $eff_status  = Aura_DocVerify_Manager::get_effective_status( $doc );
        $nonce       = wp_create_nonce( 'aura_docverify_nonce' );

        $qr_preview_url = add_query_arg( [
            'action' => 'aura_docverify_qr_preview',
            'id'     => $doc->id,
            'nonce'  => $nonce,
        ], admin_url( 'admin-ajax.php' ) );

        $dl_base = admin_url( 'admin-ajax.php' );

        wp_send_json_success( [
            'id'                    => (int) $doc->id,
            'folio'                 => $doc->folio,
            'verification_code'     => $doc->verification_code,
            'doc_type'              => $doc->doc_type,
            'doc_type_label'        => $type_labels['es'],
            'title_subject'         => $doc->title_subject,
            'doc_language'          => $doc->doc_language,
            'recipient_entity'      => $doc->recipient_entity,
            'recipient_country'     => $doc->recipient_country,
            'purpose'               => $doc->purpose,
            'holder_name'           => $doc->holder_name,
            'holder_doc_type'       => $doc->holder_doc_type,
            'holder_doc_masked'     => $doc->holder_doc_masked,
            'travel_or_event_start' => $doc->travel_or_event_start,
            'travel_or_event_end'   => $doc->travel_or_event_end,
            'stay_address'          => $doc->stay_address ?? '',
            'signer_name'           => $doc->signer_name,
            'signer_title'          => $doc->signer_title,
            'signer_email'          => $doc->signer_email,
            'issued_at'             => $doc->issued_at,
            'expires_at'            => $doc->expires_at,
            'storage_driver'        => $doc->storage_driver,
            'drive_original_url'    => $doc->drive_original_url,
            'drive_stamped_url'     => $doc->drive_stamped_url,
            'sha256_original'       => $doc->sha256_original,
            'sha256_stamped'        => $doc->sha256_stamped,
            'stamp_mode'            => $doc->stamp_mode,
            'privacy_mode'          => $doc->privacy_mode,
            'allow_public_pdf'      => (int) $doc->allow_public_pdf,
            'has_pin'               => ! empty( $doc->access_pin_hash ),
            'effective_status'      => $eff_status,
            'revoke_reason'         => $doc->revoke_reason,
            'verified_count'        => (int) $doc->verified_count,
            'last_verified_at'      => $doc->last_verified_at,
            'verify_url'            => $doc->verify_url,
            'qr_preview_url'        => $qr_preview_url,
            'has_stamped_pdf'       => ! empty( $doc->stamped_pdf_path ) || ! empty( $doc->drive_stamped_file_id ),
            'download_original_url' => add_query_arg( [ 'action' => 'aura_docverify_download_pdf', 'id' => $doc->id, 'variant' => 'original', 'nonce' => $nonce ], $dl_base ),
            'download_stamped_url'  => add_query_arg( [ 'action' => 'aura_docverify_download_pdf', 'id' => $doc->id, 'variant' => 'stamped', 'nonce' => $nonce ], $dl_base ),
            'download_qr_png_url'   => add_query_arg( [ 'action' => 'aura_docverify_download_qr', 'id' => $doc->id, 'format' => 'png', 'nonce' => $nonce ], $dl_base ),
            'download_qr_svg_url'   => add_query_arg( [ 'action' => 'aura_docverify_download_qr', 'id' => $doc->id, 'format' => 'svg', 'nonce' => $nonce ], $dl_base ),
            'internal_notes'        => $doc->internal_notes,
        ] );
    }

    /**
     * AJAX: Revocar documento.
     */
    public static function ajax_revoke(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_revoke' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para revocar documentos.', 'aura-suite' ) ], 403 );
        }

        $id     = absint( $_POST['id'] ?? 0 );
        $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
        $res    = Aura_DocVerify_Manager::revoke_document( $id, $reason );

        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ], 400 );
        }
        wp_send_json_success( [ 'message' => __( 'Documento revocado correctamente.', 'aura-suite' ) ] );
    }

    /**
     * AJAX: Reactivar documento revocado.
     */
    public static function ajax_reactivate(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_revoke' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para reactivar documentos.', 'aura-suite' ) ], 403 );
        }

        $id  = absint( $_POST['id'] ?? 0 );
        $res = Aura_DocVerify_Manager::reactivate_document( $id );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ], 400 );
        }
        wp_send_json_success( [ 'message' => __( 'Documento reactivado.', 'aura-suite' ) ] );
    }

    /**
     * AJAX: Eliminar documento y archivos.
     */
    public static function ajax_delete(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para eliminar documentos.', 'aura-suite' ) ], 403 );
        }

        $id  = absint( $_POST['id'] ?? 0 );
        $res = Aura_DocVerify_Manager::delete_document( $id );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( [ 'message' => $res->get_error_message() ], 400 );
        }
        wp_send_json_success( [ 'message' => __( 'Documento eliminado definitivamente.', 'aura-suite' ) ] );
    }

    /**
     * Descarga autenticada vía proxy PHP del PDF (desde Google Drive o almacenamiento local).
     */
    public static function ajax_download_pdf(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_download_pdf' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos para descargar este documento.', 'aura-suite' ), 403 );
        }

        $id      = absint( $_GET['id'] ?? 0 );
        $variant = sanitize_key( wp_unslash( $_GET['variant'] ?? 'stamped' ) );
        $doc     = Aura_DocVerify_Manager::get_document( $id );
        $file    = Aura_DocVerify_Manager::read_pdf_binary( $doc, $variant === 'original' ? 'original' : 'stamped' );

        if ( is_wp_error( $file ) ) {
            wp_die( esc_html( $file->get_error_message() ), 404 );
        }

        Aura_DocVerify_Manager::log_event( $id, (string) $doc->folio, 'download_pdf', 'ok', [ 'variant' => $variant, 'context' => 'admin' ] );

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $file['filename'] . '"' );
        header( 'Content-Length: ' . strlen( $file['content'] ) );
        echo $file['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Descarga del código QR en PNG o SVG.
     */
    public static function ajax_download_qr(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_view_all' ) && ! current_user_can( 'aura_docverify_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos.', 'aura-suite' ), 403 );
        }

        $id     = absint( $_GET['id'] ?? 0 );
        $format = ( sanitize_key( wp_unslash( $_GET['format'] ?? 'png' ) ) === 'svg' ) ? 'svg' : 'png';
        $doc    = Aura_DocVerify_Manager::get_document( $id );

        if ( ! $doc ) {
            wp_die( esc_html__( 'Documento no encontrado.', 'aura-suite' ), 404 );
        }

        $path = ( $format === 'svg' ) ? (string) $doc->qr_svg_path : (string) $doc->qr_png_path;
        if ( $path === '' || ! file_exists( $path ) ) {
            $gen  = Aura_DocVerify_PDF::generate_qr_files( (string) $doc->folio, (string) $doc->verify_url );
            $path = ( $format === 'svg' ) ? $gen['svg_path'] : $gen['png_path'];
        }

        if ( $path === '' || ! file_exists( $path ) ) {
            wp_die( esc_html__( 'No se pudo generar la imagen QR.', 'aura-suite' ), 500 );
        }

        $mime = ( $format === 'svg' ) ? 'image/svg+xml' : 'image/png';
        $name = 'qr-' . sanitize_file_name( (string) $doc->folio ) . '.' . $format;

        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );
        readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }

    /**
     * Sirve la previsualización inline del QR para modales y miniaturas admin.
     */
    public static function ajax_qr_preview(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_view_all' ) && ! current_user_can( 'aura_docverify_create' ) && ! current_user_can( 'manage_options' ) ) {
            status_header( 403 );
            exit;
        }

        $id  = absint( $_GET['id'] ?? 0 );
        $doc = Aura_DocVerify_Manager::get_document( $id );
        if ( ! $doc ) {
            status_header( 404 );
            exit;
        }

        $path = (string) $doc->qr_png_path;
        if ( $path === '' || ! file_exists( $path ) ) {
            $gen  = Aura_DocVerify_PDF::generate_qr_files( (string) $doc->folio, (string) $doc->verify_url );
            $path = $gen['png_path'] ?: $gen['svg_path'];
        }

        if ( $path !== '' && file_exists( $path ) ) {
            $is_svg = substr( strtolower( $path ), -4 ) === '.svg';
            header( 'Content-Type: ' . ( $is_svg ? 'image/svg+xml' : 'image/png' ) );
            header( 'Cache-Control: private, max-age=3600' );
            readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
            exit;
        }
        status_header( 404 );
        exit;
    }

    /**
     * AJAX: Recalcula y sincroniza las URLs públicas y códigos QR (y opcionalmente PDFs sellados)
     * de todos los documentos registrados según la configuración vigente.
     */
    public static function ajax_sync_urls(): void {
        check_ajax_referer( 'aura_docverify_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_docverify_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para actualizar URLs de documentos.', 'aura-suite' ) ], 403 );
        }

        $restamp = ! empty( $_POST['restamp_pdfs'] );
        $stats   = Aura_DocVerify_Manager::rebuild_existing_urls( $restamp );

        $msg = sprintf(
            /* translators: %d = updated documents count */
            __( 'Se actualizaron las URLs y códigos QR de %d documentos.', 'aura-suite' ),
            (int) $stats['updated']
        );
        if ( $restamp ) {
            $msg .= ' ' . sprintf(
                /* translators: %d = re-stamped PDFs count */
                __( 'PDFs certificados re-estampados: %d.', 'aura-suite' ),
                (int) $stats['restamped']
            );
            if ( $stats['restamp_errors'] > 0 ) {
                $msg .= ' ' . sprintf(
                    /* translators: %d = errors count */
                    __( 'No se pudo re-estampar: %d.', 'aura-suite' ),
                    (int) $stats['restamp_errors']
                );
            }
        }

        wp_send_json_success( [
            'message' => $msg,
            'stats'   => $stats,
        ] );
    }
}
