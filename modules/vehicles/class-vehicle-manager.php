<?php
/**
 * Aura Vehicle Manager — Fase 2
 * Capa de negocio: CRUD completo de vehículos, asignación de áreas,
 * manejo de fotos y cambios de estado.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Manager {

    const TABLE       = 'aura_vehicles';
    const TABLE_AREA  = 'aura_vehicle_area';
    const TABLE_TRIPS = 'aura_vehicle_trips';

    // ─────────────────────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear un nuevo vehículo.
     *
     * @param  array $data Datos del vehículo (sin sanitizar).
     * @return int|WP_Error ID del nuevo vehículo o error.
     */
    public static function create( array $data ) {
        global $wpdb;

        if ( empty( $data['plate'] ) ) {
            return new WP_Error( 'missing_plate', __( 'La placa es obligatoria.', 'aura-suite' ) );
        }
        if ( empty( $data['brand'] ) ) {
            return new WP_Error( 'missing_brand', __( 'La marca es obligatoria.', 'aura-suite' ) );
        }
        if ( empty( $data['model'] ) ) {
            return new WP_Error( 'missing_model', __( 'El modelo es obligatorio.', 'aura-suite' ) );
        }

        $plate = strtoupper( sanitize_text_field( $data['plate'] ) );

        // Verificar unicidad de placa
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_vehicles WHERE plate = %s",
            $plate
        ) );
        if ( $exists ) {
            return new WP_Error( 'duplicate_plate', __( 'Ya existe un vehículo con esa placa.', 'aura-suite' ) );
        }

        $row               = self::sanitize_fields( $data );
        $row['plate']      = $plate;
        $row['active']     = 1;
        $row['created_at'] = current_time( 'mysql' );

        $result = $wpdb->insert( $wpdb->prefix . self::TABLE, $row );
        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Error al guardar el vehículo.', 'aura-suite' ) );
        }

        $id = (int) $wpdb->insert_id;

        Aura_Vehicle_Audit_Manager::log( 'vehicle_created', 'vehicle', $id, array(
            'plate' => $plate,
            'brand' => $row['brand'],
            'model' => $row['model'],
        ) );

        return $id;
    }

    // ─────────────────────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Actualizar un vehículo existente.
     *
     * @param  int   $id   ID del vehículo.
     * @param  array $data Datos a actualizar (parcial o completo).
     * @return bool|WP_Error
     */
    public static function update( int $id, array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, plate FROM {$table} WHERE id = %d AND active = 1", $id
        ) );
        if ( ! $vehicle ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ) );
        }

        // Si cambió la placa, verificar unicidad
        if ( ! empty( $data['plate'] ) ) {
            $new_plate = strtoupper( sanitize_text_field( $data['plate'] ) );
            if ( $new_plate !== $vehicle->plate ) {
                $conflict = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE plate = %s AND id != %d",
                    $new_plate, $id
                ) );
                if ( $conflict ) {
                    return new WP_Error( 'duplicate_plate', __( 'Ya existe un vehículo con esa placa.', 'aura-suite' ) );
                }
            }
            $data['plate'] = $new_plate;
        }

        $row               = self::sanitize_fields( $data );
        $row['updated_at'] = current_time( 'mysql' );

        $result = $wpdb->update( $table, $row, array( 'id' => $id ) );
        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Error al actualizar el vehículo.', 'aura-suite' ) );
        }

        Aura_Vehicle_Audit_Manager::log( 'vehicle_updated', 'vehicle', $id, array(
            'changed_fields' => array_keys( $row ),
        ) );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR (soft delete)
    // ─────────────────────────────────────────────────────────────

    /**
     * Soft delete: marca el vehículo como inactivo.
     * Verifica que no tenga salidas activas.
     *
     * @param  int $id ID del vehículo.
     * @return bool|WP_Error
     */
    public static function delete( int $id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE id = %d AND active = 1", $id
        ) );
        if ( ! $vehicle ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ) );
        }

        // Verificar que no tenga salidas activas
        $active_trips = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aura_vehicle_trips
             WHERE vehicle_id = %d AND status = 'active' AND deleted = 0",
            $id
        ) );
        if ( $active_trips > 0 ) {
            return new WP_Error(
                'active_trips',
                __( 'El vehículo tiene salidas activas. Registra el retorno antes de eliminarlo.', 'aura-suite' )
            );
        }

        $wpdb->update(
            $table,
            array( 'active' => 0, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $id )
        );

        Aura_Vehicle_Audit_Manager::log( 'vehicle_deleted', 'vehicle', $id );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // OBTENER UNO
    // ─────────────────────────────────────────────────────────────

    /**
     * Recuperar un vehículo con sus áreas asignadas y URLs de fotos.
     *
     * @param  int $id ID del vehículo.
     * @return object|null
     */
    public static function get( int $id ) {
        global $wpdb;

        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aura_vehicles WHERE id = %d AND active = 1", $id
        ) );
        if ( ! $vehicle ) {
            return null;
        }

        $vehicle->areas      = self::get_vehicle_areas( $id );
        $vehicle->photos_raw = $vehicle->photos ? json_decode( $vehicle->photos, true ) : array();
        $vehicle->photo_urls = self::build_photo_urls( $id, $vehicle->photos_raw );

        // Foto principal via Cropper.js (WP Media Library)
        $crop_urls = aura_get_equipment_photo_urls( $vehicle->photo ?? '' );
        $vehicle->photo_url       = $crop_urls['full'];
        $vehicle->photo_thumb_url = $crop_urls['thumb'];

        return $vehicle;
    }

    // ─────────────────────────────────────────────────────────────
    // LISTAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Listar vehículos con filtros y paginación.
     * Aplica filtro CBAC: sin aura_vehicles_view_all, solo ve los de sus áreas.
     *
     * @param  array $filters Claves: page, per_page, search, status, type, area_id, sort_by, sort_dir.
     * @return array { items, total, page, per_page, total_pages }
     */
    public static function get_list( array $filters = array() ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $page     = max( 1, (int) ( $filters['page']     ?? 1 ) );
        $per_page = min( 100, max( 10, (int) ( $filters['per_page'] ?? 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = sanitize_text_field( $filters['search']  ?? '' );
        $status   = sanitize_text_field( $filters['status']  ?? '' );
        $type     = sanitize_text_field( $filters['type']    ?? '' );
        $area_id  = (int) ( $filters['area_id'] ?? 0 );

        $valid_statuses = array( 'available', 'rented', 'maintenance', 'unavailable' );
        $valid_types    = array( 'sedan', 'suv', 'pickup', 'van', 'bus', 'motorcycle', 'truck', 'other' );

        $where  = array( 'v.active = 1' );
        $params = array();

        // CBAC: filtrar por áreas si no tiene view_all
        if ( ! current_user_can( 'aura_vehicles_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            $user_id  = get_current_user_id();
            // Subquery: vehículos cuyas áreas incluyan al usuario actual
            $where[] = "v.id IN (
                SELECT va.vehicle_id
                FROM {$wpdb->prefix}aura_vehicle_area va
                INNER JOIN {$wpdb->prefix}aura_area_users au ON au.area_id = va.area_id
                WHERE au.user_id = %d
            )";
            $params[] = $user_id;
        }

        if ( $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = '(v.plate LIKE %s OR v.brand LIKE %s OR v.model LIKE %s)';
            array_push( $params, $like, $like, $like );
        }
        if ( $status && in_array( $status, $valid_statuses, true ) ) {
            $where[] = 'v.status = %s';
            $params[] = $status;
        }
        if ( $type && in_array( $type, $valid_types, true ) ) {
            $where[] = 'v.type = %s';
            $params[] = $type;
        }
        if ( $area_id > 0 ) {
            $where[]  = "v.id IN (SELECT vehicle_id FROM {$wpdb->prefix}aura_vehicle_area WHERE area_id = %d)";
            $params[] = $area_id;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $sort_allowed = array( 'plate', 'brand', 'model', 'status', 'mileage', 'created_at' );
        $sort_by  = in_array( $filters['sort_by'] ?? '', $sort_allowed, true ) ? $filters['sort_by'] : 'created_at';
        $sort_dir = strtoupper( $filters['sort_dir'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

        // Total
        $count_sql = "SELECT COUNT(*) FROM {$table} v {$where_sql}";
        $total     = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) )
            : (int) $wpdb->get_var( $count_sql );

        // Datos paginados
        $data_params = array_merge( $params, array( $per_page, $offset ) );
        $data_sql    = "SELECT v.* FROM {$table} v {$where_sql} ORDER BY v.{$sort_by} {$sort_dir} LIMIT %d OFFSET %d";
        $rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_params ) );

        // Enriquecer registros
        $can_edit   = current_user_can( 'aura_vehicles_edit' )   || current_user_can( 'manage_options' );
        $can_delete = current_user_can( 'aura_vehicles_delete' ) || current_user_can( 'manage_options' );

        foreach ( $rows as &$row ) {
            $row->areas      = self::get_vehicle_areas( (int) $row->id );
            $photos_raw      = $row->photos ? json_decode( $row->photos, true ) : array();
            $row->photo_urls = self::build_photo_urls( (int) $row->id, $photos_raw );
            $row->can_edit   = $can_edit;
            $row->can_delete = $can_delete;

            // Foto principal via Cropper.js
            $crop_urls            = aura_get_equipment_photo_urls( $row->photo ?? '' );
            $row->photo_url       = $crop_urls['full'];
            $row->photo_thumb_url = $crop_urls['thumb'];
        }
        unset( $row );

        return array(
            'items'       => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        );
    }

    // ─────────────────────────────────────────────────────────────
    // GESTIÓN DE ÁREAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Asignar un área a un vehículo (idempotente).
     */
    public static function assign_area( int $vehicle_id, int $area_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_AREA;

        $exists = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE vehicle_id = %d AND area_id = %d",
            $vehicle_id, $area_id
        ) );
        if ( $exists ) {
            return true; // Ya asignada
        }

        $result = $wpdb->insert( $table, array(
            'vehicle_id'  => $vehicle_id,
            'area_id'     => $area_id,
            'assigned_at' => current_time( 'mysql' ),
            'assigned_by' => get_current_user_id(),
        ), array( '%d', '%d', '%s', '%d' ) );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Error al asignar el área.', 'aura-suite' ) );
        }

        Aura_Vehicle_Audit_Manager::log( 'vehicle_area_assigned', 'vehicle', $vehicle_id, array(
            'area_id' => $area_id,
        ) );

        return true;
    }

    /**
     * Desasignar un área de un vehículo.
     */
    public static function unassign_area( int $vehicle_id, int $area_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_AREA;

        $result = $wpdb->delete(
            $table,
            array( 'vehicle_id' => $vehicle_id, 'area_id' => $area_id ),
            array( '%d', '%d' )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Error al desasignar el área.', 'aura-suite' ) );
        }

        Aura_Vehicle_Audit_Manager::log( 'vehicle_area_unassigned', 'vehicle', $vehicle_id, array(
            'area_id' => $area_id,
        ) );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // CAMBIOS DE ESTADO
    // ─────────────────────────────────────────────────────────────

    /**
     * Dar de baja un vehículo (status → unavailable) registrando el motivo.
     */
    public static function mark_unavailable( int $id, array $info ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND active = 1", $id ) ) ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ) );
        }

        $sanitized_info = array(
            'reason'  => sanitize_text_field( $info['reason'] ?? '' ),
            'notes'   => sanitize_textarea_field( $info['notes'] ?? '' ),
            'date'    => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
        );

        $result = $wpdb->update( $table, array(
            'status'           => 'unavailable',
            'unavailable_info' => wp_json_encode( $sanitized_info, JSON_UNESCAPED_UNICODE ),
            'updated_at'       => current_time( 'mysql' ),
        ), array( 'id' => $id ) );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Error al actualizar el vehículo.', 'aura-suite' ) );
        }

        Aura_Vehicle_Audit_Manager::log( 'vehicle_marked_unavailable', 'vehicle', $id, $sanitized_info );

        return true;
    }

    /**
     * Restaurar un vehículo (status → available) y limpiar unavailable_info.
     */
    public static function restore( int $id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND active = 1", $id ) ) ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ) );
        }

        $result = $wpdb->update( $table, array(
            'status'           => 'available',
            'unavailable_info' => null,
            'updated_at'       => current_time( 'mysql' ),
        ), array( 'id' => $id ) );

        if ( false === $result ) {
            return new WP_Error( 'db_error', __( 'Error al restaurar el vehículo.', 'aura-suite' ) );
        }

        Aura_Vehicle_Audit_Manager::log( 'vehicle_restored', 'vehicle', $id );

        return true;
    }

    /**
     * Transferir un vehículo de un área a otra, registrando el historial.
     */
    public static function transfer( int $id, int $from_area, int $to_area ) {
        global $wpdb;
        $table      = $wpdb->prefix . self::TABLE;
        $area_table = $wpdb->prefix . self::TABLE_AREA;

        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, transfer_history FROM {$table} WHERE id = %d AND active = 1", $id
        ) );
        if ( ! $vehicle ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ) );
        }

        // Quitar del área origen
        $wpdb->delete( $area_table, array( 'vehicle_id' => $id, 'area_id' => $from_area ), array( '%d', '%d' ) );

        // Agregar al área destino si no está ya
        $exists = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$area_table} WHERE vehicle_id = %d AND area_id = %d", $id, $to_area
        ) );
        if ( ! $exists ) {
            $wpdb->insert( $area_table, array(
                'vehicle_id'  => $id,
                'area_id'     => $to_area,
                'assigned_at' => current_time( 'mysql' ),
                'assigned_by' => get_current_user_id(),
            ), array( '%d', '%d', '%s', '%d' ) );
        }

        // Actualizar transfer_history
        $history = array();
        if ( $vehicle->transfer_history ) {
            $decoded = json_decode( $vehicle->transfer_history, true );
            if ( is_array( $decoded ) ) {
                $history = $decoded;
            }
        }
        $history[] = array(
            'from_area' => $from_area,
            'to_area'   => $to_area,
            'date'      => current_time( 'mysql' ),
            'user_id'   => get_current_user_id(),
        );

        $wpdb->update( $table, array(
            'transfer_history' => wp_json_encode( $history, JSON_UNESCAPED_UNICODE ),
            'updated_at'       => current_time( 'mysql' ),
        ), array( 'id' => $id ) );

        Aura_Vehicle_Audit_Manager::log( 'vehicle_transferred', 'vehicle', $id, array(
            'from_area' => $from_area,
            'to_area'   => $to_area,
        ) );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // FOTOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Subir una foto al vehículo.
     * Valida MIME real con finfo, peso máx 2MB y máx 10 fotos.
     *
     * @param  int   $vehicle_id ID del vehículo.
     * @param  array $file       Entrada $_FILES normalizada.
     * @return string|WP_Error   URL pública de la foto o error.
     */
    public static function upload_photo( int $vehicle_id, array $file ) {
        // Validar MIME real con finfo
        if ( ! function_exists( 'finfo_open' ) ) {
            return new WP_Error( 'finfo_missing', __( 'La validación de archivos no está disponible en este servidor.', 'aura-suite' ) );
        }

        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' );
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime  = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        if ( ! in_array( $mime, $allowed_mimes, true ) ) {
            return new WP_Error( 'invalid_type', __( 'Solo se permiten imágenes JPG, PNG o WebP.', 'aura-suite' ) );
        }

        // Validar tamaño máximo (2 MB)
        if ( $file['size'] > 2 * 1024 * 1024 ) {
            return new WP_Error( 'file_too_large', __( 'La imagen no puede superar 2 MB.', 'aura-suite' ) );
        }

        // Verificar límite de 10 fotos
        global $wpdb;
        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT photos FROM {$wpdb->prefix}aura_vehicles WHERE id = %d", $vehicle_id
        ) );
        $photos = $current ? json_decode( $current, true ) : array();
        if ( ! is_array( $photos ) ) {
            $photos = array();
        }
        if ( count( $photos ) >= 10 ) {
            return new WP_Error( 'max_photos', __( 'El vehículo ya tiene el máximo de 10 fotos.', 'aura-suite' ) );
        }

        // Crear directorio de destino
        $upload_dir  = wp_upload_dir();
        $vehicle_dir = $upload_dir['basedir'] . '/aura/vehicles/' . $vehicle_id;
        if ( ! wp_mkdir_p( $vehicle_dir ) ) {
            return new WP_Error( 'dir_error', __( 'No se pudo crear el directorio de fotos.', 'aura-suite' ) );
        }

        // Proteger directorio contra índices y ejecución directa
        $htaccess = $vehicle_dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
            file_put_contents( $htaccess, "Options -Indexes\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>\n" );
        }

        // Generar nombre de archivo seguro y único
        $ext_map  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' );
        $ext      = $ext_map[ $mime ];
        $filename = sanitize_file_name(
            wp_unique_filename( $vehicle_dir, 'photo-' . time() . '.' . $ext )
        );
        $filepath = $vehicle_dir . '/' . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
            return new WP_Error( 'upload_error', __( 'Error al guardar la foto.', 'aura-suite' ) );
        }

        // Actualizar campo photos en la BD
        $photos[] = $filename;
        $wpdb->update(
            $wpdb->prefix . 'aura_vehicles',
            array(
                'photos'     => wp_json_encode( $photos, JSON_UNESCAPED_UNICODE ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $vehicle_id )
        );

        Aura_Vehicle_Audit_Manager::log( 'vehicle_photo_uploaded', 'vehicle', $vehicle_id, array(
            'filename' => $filename,
        ) );

        return $upload_dir['baseurl'] . '/aura/vehicles/' . $vehicle_id . '/' . $filename;
    }

    /**
     * Eliminar una foto del vehículo.
     *
     * @param  int    $vehicle_id ID del vehículo.
     * @param  string $filename   Nombre del archivo (sin ruta).
     * @return bool|WP_Error
     */
    public static function delete_photo( int $vehicle_id, string $filename ) {
        // Prevenir path traversal
        $filename = basename( $filename );
        if ( empty( $filename ) ) {
            return new WP_Error( 'invalid_filename', __( 'Nombre de archivo inválido.', 'aura-suite' ) );
        }

        global $wpdb;
        $vehicle = $wpdb->get_row( $wpdb->prepare(
            "SELECT photos FROM {$wpdb->prefix}aura_vehicles WHERE id = %d AND active = 1", $vehicle_id
        ) );
        if ( ! $vehicle ) {
            return new WP_Error( 'not_found', __( 'Vehículo no encontrado.', 'aura-suite' ) );
        }

        $photos = $vehicle->photos ? json_decode( $vehicle->photos, true ) : array();
        $photos = array_values( array_filter( $photos, function( $p ) use ( $filename ) {
            return $p !== $filename;
        } ) );

        // Borrar archivo físico
        $upload_dir = wp_upload_dir();
        $filepath   = $upload_dir['basedir'] . '/aura/vehicles/' . $vehicle_id . '/' . $filename;
        if ( file_exists( $filepath ) ) {
            wp_delete_file( $filepath );
        }

        $wpdb->update(
            $wpdb->prefix . 'aura_vehicles',
            array(
                'photos'     => wp_json_encode( $photos, JSON_UNESCAPED_UNICODE ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $vehicle_id )
        );

        Aura_Vehicle_Audit_Manager::log( 'vehicle_photo_deleted', 'vehicle', $vehicle_id, array(
            'filename' => $filename,
        ) );

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // FOTO PRINCIPAL — AJAX (Cropper.js)
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: recortar y guardar la foto principal de un vehículo.
     * Acción: aura_vehicle_crop_photo
     */
    public static function ajax_crop_vehicle_photo(): void {
        check_ajax_referer( 'aura_vehicles_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_vehicles_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        $crop_x        = (int) round( (float) ( $_POST['x']      ?? 0 ) );
        $crop_y        = (int) round( (float) ( $_POST['y']      ?? 0 ) );
        $crop_w        = (int) round( (float) ( $_POST['width']  ?? 0 ) );
        $crop_h        = (int) round( (float) ( $_POST['height'] ?? 0 ) );

        if ( ! $attachment_id || $crop_w < 10 || $crop_h < 10 ) {
            wp_send_json_error( array( 'message' => __( 'Datos de recorte inválidos.', 'aura-suite' ) ) );
        }
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'El archivo seleccionado no es una imagen.', 'aura-suite' ) ) );
        }

        $original_path = get_attached_file( $attachment_id );
        if ( ! $original_path || ! file_exists( $original_path ) ) {
            wp_send_json_error( array( 'message' => __( 'Archivo original no encontrado.', 'aura-suite' ) ) );
        }

        $editor = wp_get_image_editor( $original_path );
        if ( is_wp_error( $editor ) ) {
            wp_send_json_error( array( 'message' => $editor->get_error_message() ) );
        }

        $crop_result = $editor->crop( $crop_x, $crop_y, $crop_w, $crop_h );
        if ( is_wp_error( $crop_result ) ) {
            wp_send_json_error( array( 'message' => $crop_result->get_error_message() ) );
        }

        // Redimensionar solo si la imagen supera 800×600. No usar crop=true porque
        // Cropper.js ya recortó en espacio original. Con crop=true, WP retorna
        // WP_Error cuando el recorte es más pequeño que el destino (false de
        // image_resize_dimensions), lo que causaba "Error al redimensionar".
        $current_size = $editor->get_size();
        if ( $current_size['width'] > 800 || $current_size['height'] > 600 ) {
            $resize_result = $editor->resize( 800, 600 );
            if ( is_wp_error( $resize_result ) ) {
                wp_send_json_error( array( 'message' => $resize_result->get_error_message() ) );
            }
        }

        // Preservar PNG para mantener transparencia; el resto → JPEG q80.
        $orig_mime = (string) ( get_post_mime_type( $attachment_id ) ?: 'image/jpeg' );
        $is_png    = ( 'image/png' === $orig_mime );
        $save_mime = $is_png ? 'image/png' : 'image/jpeg';
        $save_ext  = $is_png ? 'png' : 'jpg';

        if ( ! $is_png ) {
            $editor->set_quality( 80 );
        }

        $upload_dir = wp_upload_dir();
        $filename   = 'vehiculo-' . time() . '-' . wp_rand( 1000, 9999 ) . '.' . $save_ext;
        $save_path  = trailingslashit( $upload_dir['path'] ) . $filename;
        $saved      = $editor->save( $save_path, $save_mime );

        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment = array(
            'post_mime_type' => $save_mime,
            'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $new_id = wp_insert_attachment( $attachment, $saved['path'] );
        if ( is_wp_error( $new_id ) ) {
            wp_send_json_error( array( 'message' => $new_id->get_error_message() ) );
        }

        $metadata = wp_generate_attachment_metadata( $new_id, $saved['path'] );
        wp_update_attachment_metadata( $new_id, $metadata );

        $urls = aura_get_equipment_photo_urls( (string) $new_id );
        wp_send_json_success( array(
            'attachment_id' => $new_id,
            'full_url'      => $urls['full'],
            'thumb_url'     => $urls['thumb'],
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Sanitizar y filtrar los campos permitidos de un vehículo.
     * Solo los campos que están en $data se incluyen en el resultado.
     */
    private static function sanitize_fields( array $data ): array {
        $valid_types    = array( 'sedan', 'suv', 'pickup', 'van', 'bus', 'motorcycle', 'truck', 'other' );
        $valid_statuses = array( 'available', 'rented', 'maintenance', 'unavailable' );
        $valid_fuels    = array( 'gasoline', 'diesel', 'electric', 'hybrid', 'gas' );
        $valid_trans    = array( 'manual', 'automatic' );

        $row = array();

        if ( array_key_exists( 'plate', $data ) ) {
            $row['plate'] = strtoupper( sanitize_text_field( $data['plate'] ) );
        }
        if ( array_key_exists( 'brand', $data ) ) {
            $row['brand'] = sanitize_text_field( $data['brand'] );
        }
        if ( array_key_exists( 'model', $data ) ) {
            $row['model'] = sanitize_text_field( $data['model'] );
        }
        if ( array_key_exists( 'year', $data ) ) {
            $row['year'] = ! empty( $data['year'] ) ? (int) $data['year'] : null;
        }
        if ( array_key_exists( 'color', $data ) ) {
            $row['color'] = sanitize_text_field( $data['color'] ) ?: null;
        }
        if ( array_key_exists( 'type', $data ) ) {
            $row['type'] = in_array( $data['type'], $valid_types, true ) ? $data['type'] : 'sedan';
        }
        if ( array_key_exists( 'vin', $data ) ) {
            $row['vin'] = sanitize_text_field( $data['vin'] ) ?: null;
        }
        if ( array_key_exists( 'status', $data ) ) {
            $row['status'] = in_array( $data['status'], $valid_statuses, true ) ? $data['status'] : 'available';
        }
        if ( array_key_exists( 'mileage', $data ) ) {
            $row['mileage'] = max( 0, (int) $data['mileage'] );
        }
        if ( array_key_exists( 'rate_per_km', $data ) ) {
            $row['rate_per_km'] = max( 0, (float) $data['rate_per_km'] );
        }
        if ( array_key_exists( 'fuel_type', $data ) ) {
            $row['fuel_type'] = in_array( $data['fuel_type'], $valid_fuels, true ) ? $data['fuel_type'] : 'gasoline';
        }
        if ( array_key_exists( 'transmission', $data ) ) {
            $row['transmission'] = in_array( $data['transmission'], $valid_trans, true ) ? $data['transmission'] : 'manual';
        }
        if ( array_key_exists( 'notes', $data ) ) {
            $row['notes'] = sanitize_textarea_field( $data['notes'] ) ?: null;
        }
        if ( array_key_exists( 'photo', $data ) ) {
            $row['photo'] = ! empty( $data['photo'] ) ? absint( $data['photo'] ) : null;
        }

        return $row;
    }

    /**
     * Obtener las áreas asignadas a un vehículo.
     */
    public static function get_vehicle_areas( int $vehicle_id ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT a.id, a.name, a.color
             FROM {$wpdb->prefix}aura_areas a
             INNER JOIN {$wpdb->prefix}aura_vehicle_area va ON va.area_id = a.id
             WHERE va.vehicle_id = %d
             ORDER BY a.name ASC",
            $vehicle_id
        ) ) ?: array();
    }

    /**
     * Construir array de URLs públicas de fotos.
     */
    public static function build_photo_urls( int $vehicle_id, array $filenames ): array {
        if ( empty( $filenames ) ) {
            return array();
        }
        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'] . '/aura/vehicles/' . $vehicle_id . '/';

        return array_map( function( $filename ) use ( $base_url ) {
            return array(
                'filename' => $filename,
                'url'      => $base_url . $filename,
            );
        }, $filenames );
    }
}
