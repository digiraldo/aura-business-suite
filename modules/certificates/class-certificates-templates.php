<?php
/**
 * Gestión de Plantillas de Diseño de Certificados
 *
 * AJAX handlers para CRUD de plantillas (Builder Fabric.js).
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Templates {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_cert_save_template',   [ __CLASS__, 'ajax_save_template' ] );
        add_action( 'wp_ajax_aura_cert_load_template',   [ __CLASS__, 'ajax_load_template' ] );
        add_action( 'wp_ajax_aura_cert_delete_template', [ __CLASS__, 'ajax_delete_template' ] );
        add_action( 'wp_ajax_aura_cert_list_templates',  [ __CLASS__, 'ajax_list_templates' ] );
        add_action( 'wp_ajax_aura_cert_set_default',     [ __CLASS__, 'ajax_set_default' ] );
        add_action( 'wp_ajax_aura_cert_save_thumbnail',  [ __CLASS__, 'ajax_save_thumbnail' ] );
        add_action( 'wp_ajax_aura_cert_toggle_active',   [ __CLASS__, 'ajax_toggle_active' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // VARIABLES DINÁMICAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Lista de variables dinámicas disponibles para el editor.
     * Retorna array [ ['key' => '{variable}', 'label' => 'Descripción'] ]
     */
    public static function get_dynamic_variables_list(): array {
        return [
            [ 'key' => '{nombre}',           'label' => __( 'Primer nombre', 'aura-suite' ) ],
            [ 'key' => '{apellido}',          'label' => __( 'Apellido', 'aura-suite' ) ],
            [ 'key' => '{nombre_completo}',   'label' => __( 'Nombre y apellido completos', 'aura-suite' ) ],
            [ 'key' => '{curso}',             'label' => __( 'Nombre del curso', 'aura-suite' ) ],
            [ 'key' => '{programa}',          'label' => __( 'Nombre del área/programa', 'aura-suite' ) ],
            [ 'key' => '{fecha_emision}',     'label' => __( 'Fecha de emisión (dd/mm/yyyy)', 'aura-suite' ) ],
            [ 'key' => '{fecha_graduacion}',  'label' => __( 'Fecha de graduación', 'aura-suite' ) ],
            [ 'key' => '{instructor}',        'label' => __( 'Nombre del instructor', 'aura-suite' ) ],
            [ 'key' => '{organizacion}',      'label' => __( 'Nombre de la organización', 'aura-suite' ) ],
            [ 'key' => '{folio}',             'label' => __( 'Folio único (ej: CEM-2026-0042)', 'aura-suite' ) ],
            [ 'key' => '{año}',               'label' => __( 'Año de emisión', 'aura-suite' ) ],
            [ 'key' => '{descripcion}',       'label' => __( 'Descripción adicional', 'aura-suite' ) ],
        ];
    }

    /**
     * Reemplaza variables dinámicas en una cadena de texto.
     *
     * @param string $text Texto con marcadores {variable}.
     * @param array  $data Array con valores a sustituir.
     * @return string
     */
    public static function replace_vars( string $text, array $data ): string {
        $map = [
            '{nombre}'          => $data['first_name']      ?? '',
            '{apellido}'        => $data['last_name']        ?? '',
            '{nombre_completo}' => trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ),
            '{curso}'           => $data['course_name']     ?? '',
            '{programa}'        => $data['program_name']    ?? '',
            '{fecha_emision}'   => isset( $data['issued_at'] )
                                    ? date_i18n( 'd/m/Y', strtotime( $data['issued_at'] ) )
                                    : '',
            '{fecha_graduacion}'=> isset( $data['graduation_date'] )
                                    ? date_i18n( 'd/m/Y', strtotime( $data['graduation_date'] ) )
                                    : '',
            '{instructor}'      => $data['instructor_name'] ?? '',
            '{organizacion}'    => $data['organization_name'] ?? '',
            '{folio}'           => $data['folio']           ?? '',
            '{año}'             => isset( $data['issued_at'] )
                                    ? date( 'Y', strtotime( $data['issued_at'] ) )
                                    : (string) date( 'Y' ),
            '{descripcion}'     => $data['description']     ?? '',
        ];

        return str_replace( array_keys( $map ), array_values( $map ), $text );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Guardar plantilla (crear o actualizar)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_template(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        $can_create = current_user_can( 'aura_cert_template_create' ) || current_user_can( 'manage_options' );
        $can_edit   = current_user_can( 'aura_cert_template_edit' )   || current_user_can( 'manage_options' );

        if ( ! $can_create && ! $can_edit ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_templates';

        $id          = absint( $_POST['id'] ?? 0 );
        $name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
        $orientation = in_array( $_POST['orientation'] ?? 'landscape', [ 'landscape', 'portrait' ], true )
                        ? sanitize_text_field( $_POST['orientation'] )
                        : 'landscape';
        $width_mm    = floatval( $_POST['width_mm']  ?? 297 );
        $height_mm   = floatval( $_POST['height_mm'] ?? 210 );
        $design_json = wp_unslash( $_POST['design_json'] ?? '' );

        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => __( 'El nombre de la plantilla es obligatorio.', 'aura-suite' ) ], 400 );
        }

        // Validar y limpiar el JSON del canvas para evitar inyección
        $decoded = json_decode( $design_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( [ 'message' => __( 'El diseño del canvas no es JSON válido.', 'aura-suite' ) ], 400 );
        }

        // Re-encodificar limpio. Sanear URLs embebidas en objetos del canvas.
        $decoded = self::sanitize_canvas_json( $decoded );
        $clean_json = wp_json_encode( $decoded );

        // Slug único
        $slug = sanitize_title( $name );

        if ( $id > 0 ) {
            // Actualizar plantilla existente
            if ( ! $can_edit ) {
                wp_send_json_error( [ 'message' => __( 'Sin permisos para editar.', 'aura-suite' ) ], 403 );
            }

            // Verificar existencia
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) );
            if ( ! $existing ) {
                wp_send_json_error( [ 'message' => __( 'Plantilla no encontrada.', 'aura-suite' ) ], 404 );
            }

            $wpdb->update(
                $table,
                [
                    'name'        => $name,
                    'slug'        => $slug,
                    'description' => $description,
                    'orientation' => $orientation,
                    'width_mm'    => $width_mm,
                    'height_mm'   => $height_mm,
                    'design_json' => $clean_json,
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $id ],
                [ '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s' ],
                [ '%d' ]
            );

            wp_send_json_success( [ 'id' => $id, 'message' => __( 'Plantilla actualizada.', 'aura-suite' ) ] );

        } else {
            // Crear nueva plantilla
            if ( ! $can_create ) {
                wp_send_json_error( [ 'message' => __( 'Sin permisos para crear.', 'aura-suite' ) ], 403 );
            }

            // Slug único: si ya existe, añadir sufijo incremental
            $base_slug    = $slug;
            $counter      = 0;
            $unique_slug  = $base_slug;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $unique_slug ) ) ) {
                $counter++;
                $unique_slug = $base_slug . '-' . $counter;
            }

            $wpdb->insert(
                $table,
                [
                    'name'        => $name,
                    'slug'        => $unique_slug,
                    'description' => $description,
                    'orientation' => $orientation,
                    'width_mm'    => $width_mm,
                    'height_mm'   => $height_mm,
                    'design_json' => $clean_json,
                    'is_default'  => 0,
                    'is_active'   => 1,
                    'created_by'  => get_current_user_id(),
                    'created_at'  => current_time( 'mysql' ),
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%d', '%d', '%s', '%s' ]
            );

            $new_id = (int) $wpdb->insert_id;

            wp_send_json_success( [
                'id'      => $new_id,
                'message' => __( 'Plantilla creada correctamente.', 'aura-suite' ),
            ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Cargar plantilla (para el editor)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_load_template(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_template_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_templates';
        $id    = absint( $_POST['id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Plantilla no encontrada.', 'aura-suite' ) ], 404 );
        }

        wp_send_json_success( [
            'id'          => (int) $row->id,
            'name'        => $row->name,
            'description' => $row->description,
            'orientation' => $row->orientation,
            'width_mm'    => (float) $row->width_mm,
            'height_mm'   => (float) $row->height_mm,
            'design_json' => $row->design_json, // JSON crudo para Fabric.js
            'is_default'  => (bool) $row->is_default,
            'is_active'   => (bool) $row->is_active,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Listar plantillas
    // ─────────────────────────────────────────────────────────────

    public static function ajax_list_templates(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_template_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_templates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows  = $wpdb->get_results( "SELECT id, name, description, orientation, is_default, is_active, thumbnail_url, created_at FROM {$table} ORDER BY is_default DESC, name ASC" );

        $list = array_map( static function ( $r ) {
            return [
                'id'            => (int) $r->id,
                'name'          => $r->name,
                'description'   => $r->description,
                'orientation'   => $r->orientation,
                'is_default'    => (bool) $r->is_default,
                'is_active'     => (bool) $r->is_active,
                'thumbnail_url' => $r->thumbnail_url,
                'created_at'    => $r->created_at,
            ];
        }, $rows ?? [] );

        wp_send_json_success( [ 'templates' => $list, 'total' => count( $list ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Establecer como plantilla por defecto
    // ─────────────────────────────────────────────────────────────

    public static function ajax_set_default(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_template_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_templates';
        $id    = absint( $_POST['id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        // Quitar el default actual
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "UPDATE {$table} SET is_default = 0 WHERE is_default = 1" );

        // Establecer el nuevo
        $wpdb->update( $table, [ 'is_default' => 1 ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );

        wp_send_json_success( [ 'message' => __( 'Plantilla marcada como predeterminada.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Activar / desactivar plantilla
    // ─────────────────────────────────────────────────────────────

    public static function ajax_toggle_active(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_template_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'aura_certificate_templates';
        $id     = absint( $_POST['id'] ?? 0 );
        $active = absint( $_POST['active'] ?? 1 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        $wpdb->update( $table, [ 'is_active' => $active ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );

        wp_send_json_success( [ 'message' => $active
            ? __( 'Plantilla activada.', 'aura-suite' )
            : __( 'Plantilla desactivada.', 'aura-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Guardar thumbnail (imagen preview del canvas)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_thumbnail(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_template_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'aura_certificate_templates';
        $template_id = absint( $_POST['template_id'] ?? 0 );
        $data_url    = sanitize_text_field( wp_unslash( $_POST['data_url'] ?? '' ) );

        if ( ! $template_id || empty( $data_url ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos inválidos.', 'aura-suite' ) ], 400 );
        }

        // Validar formato data URL
        if ( ! preg_match( '/^data:image\/(png|jpeg|webp);base64,/', $data_url ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato de imagen inválido.', 'aura-suite' ) ], 400 );
        }

        // Decodificar base64 y guardar como archivo en el directorio uploads de WP
        $base64   = preg_replace( '/^data:image\/\w+;base64,/', '', $data_url );
        $img_data = base64_decode( $base64, true );

        if ( $img_data === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al decodificar la imagen.', 'aura-suite' ) ], 400 );
        }

        $upload_dir = wp_upload_dir();
        $dir        = $upload_dir['basedir'] . '/aura-cert-thumbnails';
        wp_mkdir_p( $dir );

        $filename = 'tmpl-' . $template_id . '-' . time() . '.png';
        $filepath = $dir . '/' . $filename;

        if ( file_put_contents( $filepath, $img_data ) === false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
            wp_send_json_error( [ 'message' => __( 'Error al guardar la imagen.', 'aura-suite' ) ], 500 );
        }

        $thumb_url = $upload_dir['baseurl'] . '/aura-cert-thumbnails/' . $filename;

        // Actualizar en BD
        $wpdb->update(
            $table,
            [ 'thumbnail_url' => $thumb_url ],
            [ 'id' => $template_id ],
            [ '%s' ],
            [ '%d' ]
        );

        wp_send_json_success( [ 'thumbnail_url' => $thumb_url ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Eliminar plantilla
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_template(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_template_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_templates';
        $id    = absint( $_POST['id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID inválido.', 'aura-suite' ) ], 400 );
        }

        // Verificar que no tenga certificados emitidos
        $certs_table = $wpdb->prefix . 'aura_certificates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$certs_table} WHERE template_id = %d", $id ) );

        if ( $used > 0 ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %d = número de certificados */
                    __( 'No se puede eliminar: tiene %d certificado(s) emitido(s) con esta plantilla.', 'aura-suite' ),
                    $used
                ),
            ], 409 );
        }

        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

        wp_send_json_success( [ 'message' => __( 'Plantilla eliminada.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener la plantilla marcada como predeterminada.
     *
     * @return object|null
     */
    public static function get_default_template(): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_templates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_row( "SELECT * FROM {$table} WHERE is_default = 1 AND is_active = 1 LIMIT 1" ) ?: null;
    }

    /**
     * Obtener listado de plantillas activas (para selects de emisión).
     *
     * @return array
     */
    public static function get_active_templates_for_select(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_certificate_templates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows  = $wpdb->get_results( "SELECT id, name, is_default, thumbnail_url, orientation FROM {$table} WHERE is_active = 1 ORDER BY is_default DESC, name ASC" );
        return $rows ? (array) $rows : [];
    }

    /**
     * Saneamiento recursivo del JSON del canvas.
     * Valida URLs dentro de objetos Fabric.js para evitar datos maliciosos.
     *
     * @param mixed $data Estructura decodificada del JSON.
     * @return mixed
     */
    private static function sanitize_canvas_json( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => &$value ) {
                if ( is_string( $value ) && in_array( $key, [ 'src', 'url' ], true ) ) {
                    // Permitir data URLs (imágenes base64 del canvas) y URLs del sitio
                    if ( ! str_starts_with( $value, 'data:image/' ) ) {
                        $value = esc_url_raw( $value );
                    }
                } elseif ( is_array( $value ) || is_object( $value ) ) {
                    $value = self::sanitize_canvas_json( $value );
                }
            }
            unset( $value );
        } elseif ( is_object( $data ) ) {
            foreach ( get_object_vars( $data ) as $key => $value ) {
                if ( is_string( $value ) && in_array( $key, [ 'src', 'url' ], true ) ) {
                    if ( ! str_starts_with( $value, 'data:image/' ) ) {
                        $data->$key = esc_url_raw( $value );
                    }
                } elseif ( is_array( $value ) || is_object( $value ) ) {
                    $data->$key = self::sanitize_canvas_json( $value );
                }
            }
        }
        return $data;
    }
}
