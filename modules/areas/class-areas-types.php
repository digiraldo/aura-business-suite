<?php
/**
 * Áreas y Programas — Tipos de Área
 *
 * CRUD Admin UI para gestión de Tipos de Área: submenú, AJAX endpoints,
 * formulario de creación/edición y listado con DataTables.
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Areas_Types {

    /** Acción nonce para todos los AJAX de este módulo */
    const NONCE = 'aura_areas_types_nonce';

    /* ======================================================================
     * INIT
     * ==================================================================== */

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );

        $ajax_actions = [ 'list', 'save', 'delete', 'get' ];
        foreach ( $ajax_actions as $action ) {
            add_action( 'wp_ajax_aura_areas_types_' . $action, [ __CLASS__, 'ajax_' . $action ] );
        }
    }

    /* ======================================================================
     * MENÚ
     * ==================================================================== */

    public static function add_admin_menu(): void {
        add_submenu_page(
            'aura-suite',
            __( 'Tipos de Área', 'aura-suite' ),
            '<span class="dashicons dashicons-tag" style="font-size:16px;line-height:1.4;vertical-align:text-bottom;margin-right:4px;"></span>' . __( 'Tipos de Área', 'aura-suite' ),
            'aura_areas_types_manage',
            'aura-areas-tipos',
            [ __CLASS__, 'render_page' ]
        );
    }

    /* ======================================================================
     * RENDER
     * ==================================================================== */

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_types_manage' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        $template = AURA_PLUGIN_DIR . 'templates/areas/area-types-page.php';
        if ( file_exists( $template ) ) {
            require_once $template;
        }
    }

    /* ======================================================================
     * AJAX: LIST
     * ==================================================================== */

    public static function ajax_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_types_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table       = $wpdb->prefix . Aura_Areas_Setup::AREA_TYPES_TABLE;
        $areas_table = $wpdb->prefix . Aura_Areas_Setup::TABLE;

        $rows = $wpdb->get_results(
            "SELECT t.*,
                    COALESCE(ac.cnt, 0) AS areas_count
               FROM `{$table}` t
          LEFT JOIN (
                    SELECT `type`, COUNT(*) AS cnt
                      FROM `{$areas_table}`
                  GROUP BY `type`
                    ) ac ON ac.`type` = t.slug
              ORDER BY t.is_default DESC, t.sort_order ASC, t.name ASC"
        );

        $data = array_map( function ( $row ) {
            return [
                'id'          => (int) $row->id,
                'name'        => $row->name,
                'slug'        => $row->slug,
                'description' => $row->description ?? '',
                'color'       => $row->color,
                'sort_order'  => (int) $row->sort_order,
                'is_default'  => (bool) $row->is_default,
                'areas_count' => (int) $row->areas_count,
                'created_at'  => $row->created_at,
            ];
        }, $rows );

        wp_send_json_success( [ 'data' => $data ] );
    }

    /* ======================================================================
     * AJAX: GET (single)
     * ==================================================================== */

    public static function ajax_get(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_types_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . Aura_Areas_Setup::AREA_TYPES_TABLE;
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Tipo no encontrado.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [
            'id'          => (int) $row->id,
            'name'        => $row->name,
            'slug'        => $row->slug,
            'description' => $row->description ?? '',
            'color'       => $row->color,
            'sort_order'  => (int) $row->sort_order,
            'is_default'  => (bool) $row->is_default,
        ] );
    }

    /* ======================================================================
     * AJAX: SAVE (create/update)
     * ==================================================================== */

    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_types_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id          = absint( $_POST['id'] ?? 0 );
        $name        = sanitize_text_field( $_POST['name'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $color       = sanitize_hex_color( $_POST['color'] ?? '' ) ?: '#e0e7ff';
        $sort_order  = absint( $_POST['sort_order'] ?? 0 );
        $is_default  = ! empty( $_POST['is_default'] ) ? 1 : 0;

        if ( ! $name ) {
            wp_send_json_error( [ 'message' => __( 'El nombre es requerido.', 'aura-suite' ) ] );
        }

        $slug = self::unique_slug( $name, $id );

        global $wpdb;
        $table = $wpdb->prefix . Aura_Areas_Setup::AREA_TYPES_TABLE;

        // Si se marca como predeterminado, quitar ese flag a los demás
        if ( $is_default ) {
            $wpdb->update( $table, [ 'is_default' => 0 ], [ 'is_default' => 1 ], [ '%d' ], [ '%d' ] );
        }

        if ( $id ) {
            $old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
            if ( ! $old ) {
                wp_send_json_error( [ 'message' => __( 'Tipo no encontrado.', 'aura-suite' ) ] );
            }

            $result = $wpdb->update(
                $table,
                [
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                    'color'       => $color,
                    'sort_order'  => $sort_order,
                    'is_default'  => $is_default,
                ],
                [ 'id' => $id ],
                [ '%s', '%s', '%s', '%s', '%d', '%d' ],
                [ '%d' ]
            );

            if ( false === $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el tipo.', 'aura-suite' ) ] );
            }

            // Si el slug cambió, actualizar todas las áreas que lo usaban
            if ( $old->slug !== $slug ) {
                $areas_table = $wpdb->prefix . Aura_Areas_Setup::TABLE;
                $wpdb->update(
                    $areas_table,
                    [ 'type' => $slug ],
                    [ 'type' => $old->slug ],
                    [ '%s' ],
                    [ '%s' ]
                );
            }

            wp_send_json_success( [ 'message' => __( 'Tipo actualizado.', 'aura-suite' ), 'id' => $id ] );
        } else {
            $result = $wpdb->insert(
                $table,
                [
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                    'color'       => $color,
                    'sort_order'  => $sort_order,
                    'is_default'  => $is_default,
                ],
                [ '%s', '%s', '%s', '%s', '%d', '%d' ]
            );

            if ( ! $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al crear el tipo.', 'aura-suite' ) ] );
            }

            wp_send_json_success( [ 'message' => __( 'Tipo creado.', 'aura-suite' ), 'id' => $wpdb->insert_id ] );
        }
    }

    /* ======================================================================
     * AJAX: DELETE
     * ==================================================================== */

    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_types_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table       = $wpdb->prefix . Aura_Areas_Setup::AREA_TYPES_TABLE;
        $areas_table = $wpdb->prefix . Aura_Areas_Setup::TABLE;

        $type_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
        if ( ! $type_row ) {
            wp_send_json_error( [ 'message' => __( 'Tipo no encontrado.', 'aura-suite' ) ] );
        }

        // Bloquear si algún área usa este tipo
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$areas_table}` WHERE `type` = %s",
            $type_row->slug
        ) );

        if ( $count > 0 ) {
            wp_send_json_error( [
                'message' => sprintf(
                    _n(
                        'No se puede eliminar: %d área usa este tipo.',
                        'No se puede eliminar: %d áreas usan este tipo.',
                        $count,
                        'aura-suite'
                    ),
                    $count
                ),
            ] );
        }

        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        wp_send_json_success( [ 'message' => __( 'Tipo eliminado.', 'aura-suite' ) ] );
    }

    /* ======================================================================
     * HELPER: slug único en tabla de tipos
     * ==================================================================== */

    private static function unique_slug( string $name, int $exclude_id = 0 ): string {
        global $wpdb;
        $table = $wpdb->prefix . Aura_Areas_Setup::AREA_TYPES_TABLE;
        $base  = sanitize_title( $name );
        $slug  = $base;
        $i     = 1;

        while ( true ) {
            if ( $exclude_id ) {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM `{$table}` WHERE slug = %s AND id != %d",
                    $slug,
                    $exclude_id
                ) );
            } else {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM `{$table}` WHERE slug = %s",
                    $slug
                ) );
            }

            if ( ! $exists ) {
                break;
            }

            $slug = $base . '-' . ( ++$i );
        }

        return $slug;
    }
}


