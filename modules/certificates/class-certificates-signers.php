<?php
/**
 * Gestión de Firmantes de Certificados
 *
 * Administra las imágenes de firma y datos de firmantes.
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Signers {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_cert_save_signer',    [ __CLASS__, 'ajax_save_signer' ] );
        add_action( 'wp_ajax_aura_cert_delete_signer',  [ __CLASS__, 'ajax_delete_signer' ] );
        add_action( 'wp_ajax_aura_cert_list_signers',   [ __CLASS__, 'ajax_list_signers' ] );
        add_action( 'wp_ajax_aura_cert_toggle_signer',  [ __CLASS__, 'ajax_toggle_signer' ] );
        add_action( 'wp_ajax_aura_cert_reorder_signers',[ __CLASS__, 'ajax_reorder_signers' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Guardar firmante
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_signer(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_signatures_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_signers';

        $id             = absint( $_POST['id'] ?? 0 );
        $name           = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $title          = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $is_active      = absint( $_POST['is_active'] ?? 1 ) ? 1 : 0;
        $attachment_id  = absint( $_POST['attachment_id'] ?? 0 );
        $signature_url  = esc_url_raw( wp_unslash( $_POST['signature_url'] ?? '' ) );
        $wp_user_id     = absint( $_POST['wp_user_id'] ?? 0 );
        $third_party_id = absint( $_POST['third_party_id'] ?? 0 );

        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => __( 'El nombre del firmante es obligatorio.', 'aura-suite' ) ], 400 );
        }

        // Validar adjunto si se seleccionó uno nuevo
        if ( $attachment_id > 0 ) {
            $mime = get_post_mime_type( $attachment_id );
            if ( ! in_array( $mime, [ 'image/png', 'image/webp', 'image/jpeg' ], true ) ) {
                wp_send_json_error( [
                    'message' => __( 'La imagen de firma debe ser PNG, WebP o JPG.', 'aura-suite' ),
                ], 400 );
            }
            $resolved_url = wp_get_attachment_url( $attachment_id );
            if ( ! $resolved_url ) {
                wp_send_json_error( [ 'message' => __( 'Imagen no encontrada en la Biblioteca de Medios.', 'aura-suite' ) ], 400 );
            }
            $signature_url = $resolved_url;
        }

        // Límite de firmantes activos
        if ( $is_active ) {
            $max = (int) Aura_Certificates_Settings::get( 'max_active_signers', 4 );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $current_active = (int) $wpdb->get_var(
                $id > 0
                    ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND id != %d", $id )
                    : "SELECT COUNT(*) FROM {$table} WHERE is_active = 1"
            );

            if ( $current_active >= $max ) {
                wp_send_json_error( [
                    'message' => sprintf(
                        /* translators: %d = máximo de firmantes */
                        __( 'El máximo de firmantes activos es %d. Desactive otro firmante primero.', 'aura-suite' ),
                        $max
                    ),
                ], 409 );
            }
        }

        if ( $id > 0 ) {
            // Actualizar
            $data = [
                'name'           => $name,
                'title'          => $title,
                'is_active'      => $is_active,
                'wp_user_id'     => $wp_user_id > 0 ? $wp_user_id : null,
                'third_party_id' => $third_party_id > 0 ? $third_party_id : null,
            ];
            $format = [ '%s', '%s', '%d', '%d', '%d' ];

            if ( $attachment_id > 0 || $signature_url !== '' ) {
                $data['signature_url'] = $signature_url;
                $data['attachment_id'] = $attachment_id;
                $format[]              = '%s';
                $format[]              = '%d';
            }

            $wpdb->update( $table, $data, [ 'id' => $id ], $format, [ '%d' ] );

            wp_send_json_success( [ 'id' => $id, 'message' => __( 'Firmante actualizado.', 'aura-suite' ) ] );

        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sort_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {$table}" );

            $wpdb->insert(
                $table,
                [
                    'name'           => $name,
                    'title'          => $title,
                    'signature_url'  => $signature_url,
                    'attachment_id'  => $attachment_id,
                    'wp_user_id'     => $wp_user_id > 0 ? $wp_user_id : null,
                    'third_party_id' => $third_party_id > 0 ? $third_party_id : null,
                    'is_active'      => $is_active,
                    'sort_order'     => $sort_order,
                    'created_by'     => get_current_user_id(),
                    'created_at'     => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ]
            );

            $new_id = (int) $wpdb->insert_id;

            wp_send_json_success( [
                'id'      => $new_id,
                'message' => __( 'Firmante creado.', 'aura-suite' ),
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Eliminar firmante
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_signer(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_signatures_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_signers';
        $id    = absint( $_POST['id'] ?? $_REQUEST['id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        if ( false === $deleted ) {
            wp_send_json_error( [ 'message' => __( 'No se pudo eliminar el firmante en la base de datos.', 'aura-suite' ) ], 500 );
        }

        wp_send_json_success( [
            'message' => __( 'Firmante eliminado.', 'aura-suite' ),
            'id'      => $id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Listar firmantes
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_signers(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_signatures_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        $signers = self::get_all_signers();

        wp_send_json_success( [ 'signers' => $signers, 'total' => count( $signers ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Activar / desactivar firmante
    // ─────────────────────────────────────────────────────────────

    public static function ajax_toggle_signer(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_signatures_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'aura_certificate_signers';
        $id     = absint( $_POST['id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        if ( isset( $_POST['active'] ) ) {
            $active = absint( $_POST['active'] ) ? 1 : 0;
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $id ) );
            $active  = $current ? 0 : 1;
        }

        // Verificar límite al activar
        if ( $active ) {
            $max            = (int) Aura_Certificates_Settings::get( 'max_active_signers', 4 );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $current_active = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND id != %d", $id )
            );

            if ( $current_active >= $max ) {
                wp_send_json_error( [
                    'message' => sprintf(
                        /* translators: %d = máximo de firmantes */
                        __( 'El máximo de firmantes activos es %d.', 'aura-suite' ),
                        $max
                    ),
                ], 409 );
            }
        }

        $wpdb->update( $table, [ 'is_active' => $active ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );

        wp_send_json_success( [ 'message' => $active
            ? __( 'Firmante activado.', 'aura-suite' )
            : __( 'Firmante desactivado.', 'aura-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Reordenar firmantes (drag & drop)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_reorder_signers(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_signatures_manage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'aura_certificate_signers';
        // Array de IDs en el nuevo orden
        $ids_raw = $_POST['ids'] ?? [];

        if ( ! is_array( $ids_raw ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos inválidos.', 'aura-suite' ) ], 400 );
        }

        $ids = array_map( 'absint', $ids_raw );

        foreach ( $ids as $order => $id ) {
            $wpdb->update( $table, [ 'sort_order' => $order + 1 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
        }

        wp_send_json_success( [ 'message' => __( 'Orden actualizado.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES ESTÁTICAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener todos los firmantes, ordenados por sort_order.
     */
    public static function get_all_signers(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_signers';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, created_at ASC" );
        return $rows ? array_map( [ __CLASS__, 'format_signer_row' ], $rows ) : [];
    }

    /**
     * Obtiene firmantes activos en formato para el editor JS (auraCertBuilder).
     *
     * @return array [ ['id', 'name', 'title', 'signature_url'] ]
     */
    public static function get_active_signers_for_js(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_signers';
        $max   = max( 1, min( 4, (int) Aura_Certificates_Settings::get( 'max_active_signers', 4 ) ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, title, signature_url FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT %d",
            $max
        ) );
        if ( ! $rows ) {
            return [];
        }
        return array_map( static function ( $r ) {
            return [
                'id'            => (int) $r->id,
                'name'          => $r->name,
                'title'         => $r->title,
                'signature_url' => $r->signature_url,
            ];
        }, $rows );
    }

    /**
     * Formatea una fila de BD para devolver en respuestas AJAX.
     */
    private static function format_signer_row( object $r ): array {
        $wp_user_id     = (int) ( $r->wp_user_id ?? 0 );
        $third_party_id = (int) ( $r->third_party_id ?? 0 );
        $linked_label   = '';

        if ( $wp_user_id > 0 ) {
            $u = get_user_by( 'id', $wp_user_id );
            $linked_label = $u ? $u->display_name : '#' . $wp_user_id;
        } elseif ( $third_party_id > 0 && class_exists( 'Aura_Third_Parties' ) ) {
            $tp = Aura_Third_Parties::get_third_party( $third_party_id );
            if ( $tp ) {
                $linked_label = $tp->commercial_name ?: $tp->full_name;
            }
        }

        return [
            'id'             => (int) $r->id,
            'name'           => $r->name,
            'title'          => $r->title,
            'signature_url'  => $r->signature_url,
            'attachment_id'  => (int) ( $r->attachment_id ?? 0 ),
            'wp_user_id'     => $wp_user_id,
            'third_party_id' => $third_party_id,
            'linked_label'   => $linked_label,
            'is_active'      => (bool) $r->is_active,
            'sort_order'     => (int) $r->sort_order,
            'created_at'     => $r->created_at,
        ];
    }
}
