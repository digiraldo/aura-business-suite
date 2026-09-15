<?php
/**
 * Aura Vehicle Catalog Manager — Fase 4
 * CRUD de los catálogos configurables del módulo de vehículos:
 * destinos, propósitos y gastos.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Catalog_Manager {

    // ─────────────────────────────────────────────────────────────
    // LISTAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener listado de catálogos con filtros opcionales.
     *
     * @param array $filters {
     *   @type string   $type             'destination' | 'purpose' | 'expense'
     *   @type int|null $area_id          NULL = globales, ID = específico del área
     *   @type bool     $include_inactive Incluir registros con active=0 (default false)
     *   @type bool     $include_global   Incluir globales junto con los del área (default true)
     * }
     * @return array
     */
    public static function get_list( array $filters = array() ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_vehicle_catalogs';

        $where   = array( '1=1' );
        $params  = array();

        // Filtro por tipo
        if ( ! empty( $filters['type'] ) ) {
            $valid_types = array( 'destination', 'purpose', 'expense' );
            if ( in_array( $filters['type'], $valid_types, true ) ) {
                $where[]  = 'type = %s';
                $params[] = $filters['type'];
            }
        }

        // Filtro por área: globales (NULL) + del área indicada, o solo globales
        if ( isset( $filters['area_id'] ) && $filters['area_id'] ) {
            $area_id          = (int) $filters['area_id'];
            $include_global   = isset( $filters['include_global'] ) ? (bool) $filters['include_global'] : true;
            if ( $include_global ) {
                $where[]  = '(area_id IS NULL OR area_id = %d)';
                $params[] = $area_id;
            } else {
                $where[]  = 'area_id = %d';
                $params[] = $area_id;
            }
        } elseif ( array_key_exists( 'area_id', $filters ) && null === $filters['area_id'] ) {
            // Solo globales
            $where[] = 'area_id IS NULL';
        }

        // Incluir inactivos
        if ( empty( $filters['include_inactive'] ) ) {
            $where[] = 'active = 1';
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY type, sort_order, id';

        if ( $params ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results( $sql, ARRAY_A );
        }

        return is_array( $rows ) ? self::format_rows( $rows ) : array();
    }

    // ─────────────────────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear un nuevo ítem de catálogo.
     *
     * @param array $data
     * @return int|WP_Error  ID del nuevo registro o error.
     */
    public static function create( array $data ) {
        global $wpdb;

        $validated = self::validate_and_sanitize( $data );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        // Detectar duplicados (mismo tipo + nombre + area_id)
        if ( self::exists( $validated['type'], $validated['name'], $validated['area_id'] ) ) {
            return new WP_Error(
                'catalog_duplicate',
                __( 'Ya existe un ítem con ese nombre en este tipo y área.', 'aura-suite' )
            );
        }

        // Calcular sort_order: max actual + 1
        $table      = $wpdb->prefix . 'aura_vehicle_catalogs';
        $max_order  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(MAX(sort_order),0) FROM {$table} WHERE type = %s",
            $validated['type']
        ) );

        $validated['sort_order'] = $max_order + 1;
        $validated['created_by'] = get_current_user_id();
        $validated['created_at'] = current_time( 'mysql' );

        $result = $wpdb->insert( $table, $validated, self::get_formats( $validated ) );

        if ( false === $result ) {
            return new WP_Error( 'catalog_db_error', __( 'Error al guardar en la base de datos.', 'aura-suite' ) );
        }

        return (int) $wpdb->insert_id;
    }

    // ─────────────────────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Actualizar un ítem de catálogo existente.
     *
     * @param int   $id
     * @param array $data
     * @return bool|WP_Error
     */
    public static function update( int $id, array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_vehicle_catalogs';

        // Verificar que existe
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $existing ) {
            return new WP_Error( 'catalog_not_found', __( 'Ítem de catálogo no encontrado.', 'aura-suite' ) );
        }

        $validated = self::validate_and_sanitize( $data, false );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        // Detectar duplicados excluyendo el registro actual
        if ( isset( $validated['name'] ) || isset( $validated['type'] ) || array_key_exists( 'area_id', $validated ) ) {
            $check_name    = $validated['name']    ?? $existing['name'];
            $check_type    = $validated['type']    ?? $existing['type'];
            $check_area_id = array_key_exists( 'area_id', $validated ) ? $validated['area_id'] : ( ! empty( $existing['area_id'] ) ? (int) $existing['area_id'] : null );

            if ( self::exists( $check_type, $check_name, $check_area_id, $id ) ) {
                return new WP_Error(
                    'catalog_duplicate',
                    __( 'Ya existe un ítem con ese nombre en este tipo y área.', 'aura-suite' )
                );
            }
        }

        $validated['updated_at'] = current_time( 'mysql' );

        $result = $wpdb->update( $table, $validated, array( 'id' => $id ), self::get_formats( $validated ), array( '%d' ) );

        if ( false === $result ) {
            return new WP_Error( 'catalog_db_error', __( 'Error al actualizar en la base de datos.', 'aura-suite' ) );
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // ELIMINAR / DESACTIVAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Eliminar un ítem. Si tiene trips asociados se desactiva (soft delete);
     * si no tiene trips, se elimina físicamente.
     *
     * @param int $id
     * @return bool|WP_Error
     */
    public static function delete( int $id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_vehicle_catalogs';

        // Verificar que existe
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        if ( ! $row ) {
            return new WP_Error( 'catalog_not_found', __( 'Ítem de catálogo no encontrado.', 'aura-suite' ) );
        }

        if ( self::has_trips( $id, $row['type'], $row['name'] ) ) {
            // Solo desactivar
            $wpdb->update(
                $table,
                array( 'active' => 0, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
            return 'deactivated';
        }

        // Eliminar físicamente
        $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        if ( false === $deleted ) {
            return new WP_Error( 'catalog_db_error', __( 'Error al eliminar el ítem.', 'aura-suite' ) );
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // REORDENAR
    // ─────────────────────────────────────────────────────────────

    /**
     * Persistir el orden de arrastrar y soltar.
     *
     * @param array $ids  Array de IDs en el nuevo orden.
     * @return bool|WP_Error
     */
    public static function reorder( array $ids ) {
        global $wpdb;

        if ( empty( $ids ) ) {
            return new WP_Error( 'catalog_invalid', __( 'Lista de IDs vacía.', 'aura-suite' ) );
        }

        $table = $wpdb->prefix . 'aura_vehicle_catalogs';
        $now   = current_time( 'mysql' );

        foreach ( $ids as $position => $id ) {
            $wpdb->update(
                $table,
                array(
                    'sort_order' => (int) $position + 1,
                    'updated_at' => $now,
                ),
                array( 'id' => (int) $id ),
                array( '%d', '%s' ),
                array( '%d' )
            );
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Verificar si ya existe un ítem con el mismo tipo + nombre + área.
     */
    private static function exists( string $type, string $name, $area_id, int $exclude_id = 0 ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_vehicle_catalogs';

        if ( $area_id ) {
            $row = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE type = %s AND name = %s AND area_id = %d AND id != %d LIMIT 1",
                $type, $name, (int) $area_id, $exclude_id
            ) );
        } else {
            $row = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE type = %s AND name = %s AND area_id IS NULL AND id != %d LIMIT 1",
                $type, $name, $exclude_id
            ) );
        }

        return ! empty( $row );
    }

    /**
     * Verificar si el ítem tiene salidas (trips) asociadas.
     * Se chequea en los campos destination, purpose y expenses_detail según el tipo.
     */
    private static function has_trips( int $id, string $type, string $name ): bool {
        global $wpdb;

        $t_trips = $wpdb->prefix . 'aura_vehicle_trips';

        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$t_trips}'" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return false;
        }

        switch ( $type ) {
            case 'destination':
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t_trips} WHERE destination = %s AND deleted = 0",
                    $name
                ) );
                break;

            case 'purpose':
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t_trips} WHERE purpose = %s AND deleted = 0",
                    $name
                ) );
                break;

            case 'expense':
                // expenses_detail es JSON; buscamos el id del catálogo
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$t_trips} WHERE expenses_detail LIKE %s AND deleted = 0",
                    '%"' . esc_sql( (string) $id ) . '"%'
                ) );
                break;

            default:
                $count = 0;
        }

        return $count > 0;
    }

    /**
     * Validar y sanear los datos de entrada.
     *
     * @param array $data
     * @param bool  $require_name_type True en creación, false en edición (permite parciales).
     * @return array|WP_Error
     */
    private static function validate_and_sanitize( array $data, bool $require_name_type = true ) {
        $out        = array();
        $valid_types = array( 'destination', 'purpose', 'expense' );

        // type
        if ( isset( $data['type'] ) ) {
            if ( ! in_array( $data['type'], $valid_types, true ) ) {
                return new WP_Error( 'catalog_invalid', __( 'Tipo de catálogo no válido.', 'aura-suite' ) );
            }
            $out['type'] = $data['type'];
        } elseif ( $require_name_type ) {
            return new WP_Error( 'catalog_invalid', __( 'El tipo es obligatorio.', 'aura-suite' ) );
        }

        // name
        if ( isset( $data['name'] ) ) {
            $name = sanitize_text_field( $data['name'] );
            if ( '' === $name ) {
                return new WP_Error( 'catalog_invalid', __( 'El nombre no puede estar vacío.', 'aura-suite' ) );
            }
            if ( strlen( $name ) > 150 ) {
                return new WP_Error( 'catalog_invalid', __( 'El nombre no puede superar 150 caracteres.', 'aura-suite' ) );
            }
            $out['name'] = $name;
        } elseif ( $require_name_type ) {
            return new WP_Error( 'catalog_invalid', __( 'El nombre es obligatorio.', 'aura-suite' ) );
        }

        // description
        if ( isset( $data['description'] ) ) {
            $out['description'] = sanitize_text_field( substr( (string) $data['description'], 0, 300 ) );
        }

        // icon
        if ( isset( $data['icon'] ) ) {
            $out['icon'] = sanitize_text_field( substr( (string) $data['icon'], 0, 50 ) );
        }

        // active
        if ( isset( $data['active'] ) ) {
            $out['active'] = (int) (bool) $data['active'];
        }

        // area_id (NULL = global)
        if ( array_key_exists( 'area_id', $data ) ) {
            $out['area_id'] = ! empty( $data['area_id'] ) ? absint( $data['area_id'] ) : null;
        }

        return $out;
    }

    /**
     * Devolver el array de formatos para wpdb según las claves presentes.
     */
    private static function get_formats( array $data ): array {
        $map = array(
            'type'        => '%s',
            'name'        => '%s',
            'description' => '%s',
            'icon'        => '%s',
            'active'      => '%d',
            'sort_order'  => '%d',
            'area_id'     => '%d',
            'created_by'  => '%d',
            'created_at'  => '%s',
            'updated_at'  => '%s',
        );

        $formats = array();
        foreach ( $data as $key => $value ) {
            $formats[] = isset( $map[ $key ] ) ? ( null === $value ? null : $map[ $key ] ) : '%s';
        }

        return $formats;
    }

    /**
     * Normalizar filas de la BD para la respuesta.
     */
    private static function format_rows( array $rows ): array {
        return array_map( function ( $row ) {
            return array(
                'id'          => (int)    $row['id'],
                'type'        =>          $row['type'],
                'name'        =>          $row['name'],
                'description' =>          $row['description'] ?? '',
                'icon'        =>          $row['icon']        ?? '',
                'active'      => (bool)   $row['active'],
                'sort_order'  => (int)    $row['sort_order'],
                'area_id'     => ! empty( $row['area_id'] ) ? (int) $row['area_id'] : null,
                'created_by'  => (int)    $row['created_by'],
                'created_at'  =>          $row['created_at'],
                'updated_at'  =>          $row['updated_at'] ?? null,
            );
        }, $rows );
    }
}
