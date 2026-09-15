<?php
/**
 * Auditoría del Módulo de Biblioteca — Fase 8
 *
 * Proporciona el endpoint AJAX para listar y exportar el log de auditoría.
 * El método estático Aura_Library_Setup::log() escribe los registros;
 * esta clase solo los expone y permite limpiarlos.
 *
 * @package Aura_Business_Suite
 * @subpackage Library
 * @since 1.7.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Library_Audit {

    const NONCE = 'aura_library_nonce';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_library_audit_list',   [ __CLASS__, 'ajax_list' ] );
        add_action( 'wp_ajax_aura_library_audit_export', [ __CLASS__, 'ajax_export_csv' ] );
        add_action( 'wp_ajax_aura_library_audit_clean',  [ __CLASS__, 'ajax_clean' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Listado paginado con filtros
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if (
            ! current_user_can( 'aura_library_audit' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_audit';

        $per_page  = 25;
        $page      = max( 1, absint( $_POST['page'] ?? 1 ) );
        $offset    = ( $page - 1 ) * $per_page;
        $action_f  = sanitize_key( $_POST['action_filter'] ?? '' );
        $entity_f  = sanitize_key( $_POST['entity_type'] ?? '' );
        $user_f    = absint( $_POST['user_id'] ?? 0 );
        $date_from = sanitize_text_field( $_POST['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $_POST['date_to'] ?? '' );
        $search    = sanitize_text_field( $_POST['search'] ?? '' );

        $where  = 'WHERE 1=1';
        $params = [];

        if ( $action_f ) {
            $where   .= ' AND a.action = %s';
            $params[] = $action_f;
        }
        if ( $entity_f ) {
            $where   .= ' AND a.entity_type = %s';
            $params[] = $entity_f;
        }
        if ( $user_f ) {
            $where   .= ' AND a.user_id = %d';
            $params[] = $user_f;
        }
        if ( $date_from ) {
            $where   .= ' AND DATE(a.created_at) >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where   .= ' AND DATE(a.created_at) <= %s';
            $params[] = $date_to;
        }
        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where   .= ' AND (a.action LIKE %s OR a.entity_type LIKE %s OR u.display_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $params[] = $per_page;
        $params[] = $offset;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id,
                    a.old_data, a.new_data, a.ip_address, a.created_at,
                    u.display_name AS user_name
             FROM {$table} a
             LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
             {$where}
             ORDER BY a.created_at DESC
             LIMIT %d OFFSET %d",
            ...$params
        ) );

        // Total (sin LIMIT/OFFSET)
        $count_params = array_slice( $params, 0, -2 );
        $total = (int) $wpdb->get_var(
            empty( $count_params )
                ? "SELECT COUNT(*) FROM {$table} a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id {$where}"
                : $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id {$where}",
                    ...$count_params
                )
        );
        // phpcs:enable

        // Etiquetas legibles para las acciones
        $action_labels = self::get_action_labels();

        $formatted = array_map( static function( $r ) use ( $action_labels ) {
            return [
                'id'          => (int) $r->id,
                'user_name'   => $r->user_name ?? __( '(usuario eliminado)', 'aura-business-suite' ),
                'action'      => $r->action,
                'action_label'=> $action_labels[ $r->action ] ?? $r->action,
                'entity_type' => $r->entity_type,
                'entity_id'   => (int) $r->entity_id,
                'old_data'    => $r->old_data ? json_decode( $r->old_data, true ) : null,
                'new_data'    => $r->new_data ? json_decode( $r->new_data, true ) : null,
                'ip_address'  => $r->ip_address ?? '',
                'created_at'  => $r->created_at,
            ];
        }, $rows );

        wp_send_json_success( [
            'rows'        => $formatted,
            'page'        => $page,
            'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
            'total'       => $total,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Exportar CSV
    // ─────────────────────────────────────────────────────────────

    public static function ajax_export_csv(): void {
        if (
            ! isset( $_POST['nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE )
        ) {
            wp_die( esc_html__( 'Solicitud no válida.', 'aura-business-suite' ) );
        }

        if (
            ! current_user_can( 'aura_library_audit' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( esc_html__( 'Sin permisos.', 'aura-business-suite' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_audit';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT a.id, a.user_id, u.display_name AS user_name,
                    a.action, a.entity_type, a.entity_id, a.ip_address, a.created_at
             FROM {$table} a
             LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
             ORDER BY a.created_at DESC
             LIMIT 5000"
        );
        // phpcs:enable

        $labels = self::get_action_labels();

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="auditoria-biblioteca-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        // BOM para Excel
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, [ 'ID', 'Fecha', 'Usuario', 'Acción', 'Entidad', 'ID Entidad', 'IP' ] );

        foreach ( $rows as $r ) {
            fputcsv( $out, [
                $r->id,
                $r->created_at,
                $r->user_name ?? '(eliminado)',
                $labels[ $r->action ] ?? $r->action,
                $r->entity_type,
                $r->entity_id,
                $r->ip_address ?? '',
            ] );
        }
        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Limpiar registros antiguos
    // ─────────────────────────────────────────────────────────────

    public static function ajax_clean(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if (
            ! current_user_can( 'aura_library_settings' ) &&
            ! current_user_can( 'manage_options' )
        ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-business-suite' ) ] );
        }

        $days = absint( $_POST['days'] ?? 90 );
        if ( $days < 7 ) {
            wp_send_json_error( [ 'message' => __( 'El mínimo es 7 días.', 'aura-business-suite' ) ] );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'aura_library_audit';
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $cutoff
        ) );

        wp_send_json_success( [
            'deleted' => (int) $deleted,
            'message' => sprintf(
                /* translators: %d = number of records deleted */
                __( '%d registros eliminados.', 'aura-business-suite' ),
                (int) $deleted
            ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Etiquetas legibles para las acciones de auditoría.
     *
     * @return array<string, string>
     */
    public static function get_action_labels(): array {
        return [
            'create_book'       => __( 'Crear libro', 'aura-business-suite' ),
            'update_book'       => __( 'Editar libro', 'aura-business-suite' ),
            'delete_book'       => __( 'Eliminar libro', 'aura-business-suite' ),
            'create_loan'       => __( 'Registrar préstamo', 'aura-business-suite' ),
            'return_book'       => __( 'Registrar devolución', 'aura-business-suite' ),
            'extend_loan'       => __( 'Extender préstamo', 'aura-business-suite' ),
            'create_reservation'=> __( 'Crear reserva', 'aura-business-suite' ),
            'cancel_reservation'=> __( 'Cancelar reserva', 'aura-business-suite' ),
            'register_fine'     => __( 'Registrar multa', 'aura-business-suite' ),
            'update_settings'   => __( 'Actualizar configuración', 'aura-business-suite' ),
        ];
    }

    /**
     * Obtener lista de tipos de entidad distintos (para filtros).
     *
     * @return string[]
     */
    public static function get_entity_types(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_audit';
        $rows  = $wpdb->get_col( "SELECT DISTINCT entity_type FROM {$table} ORDER BY entity_type ASC" ); // phpcs:ignore
        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Obtener lista de acciones distintas (para filtros).
     *
     * @return string[]
     */
    public static function get_distinct_actions(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_library_audit';
        $rows  = $wpdb->get_col( "SELECT DISTINCT action FROM {$table} ORDER BY action ASC" ); // phpcs:ignore
        return is_array( $rows ) ? $rows : [];
    }
}
