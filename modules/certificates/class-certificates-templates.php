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
     * Retorna array [ ['key' => '{variable}', 'label' => 'Descripción', 'group' => 'Grupo', 'example' => 'Ejemplo'] ]
     */
    public static function get_dynamic_variables_list(): array {
        return [
            // Destinatario
            [ 'group' => __( 'Destinatario', 'aura-suite' ), 'key' => '{nombre}',            'label' => __( 'Primer nombre', 'aura-suite' ),             'example' => 'María' ],
            [ 'group' => __( 'Destinatario', 'aura-suite' ), 'key' => '{apellido}',          'label' => __( 'Apellido(s)', 'aura-suite' ),               'example' => 'González' ],
            [ 'group' => __( 'Destinatario', 'aura-suite' ), 'key' => '{nombre_completo}',   'label' => __( 'Nombre completo', 'aura-suite' ),           'example' => 'María González Pérez' ],
            [ 'group' => __( 'Destinatario', 'aura-suite' ), 'key' => '{documento}',         'label' => __( 'Documento / Cédula / ID', 'aura-suite' ), 'example' => 'CC 1020304050' ],
            [ 'group' => __( 'Destinatario', 'aura-suite' ), 'key' => '{correo}',            'label' => __( 'Correo electrónico', 'aura-suite' ),        'example' => 'maria@ejemplo.com' ],
            [ 'group' => __( 'Destinatario', 'aura-suite' ), 'key' => '{tipo_destinatario}', 'label' => __( 'Tipo (Estudiante/Usuario/Tercero)', 'aura-suite' ), 'example' => 'Estudiante' ],

            // Curso / Programa
            [ 'group' => __( 'Programa y Logro', 'aura-suite' ), 'key' => '{curso}',         'label' => __( 'Nombre del curso / evento', 'aura-suite' ), 'example' => 'Liderazgo Ministerial' ],
            [ 'group' => __( 'Programa y Logro', 'aura-suite' ), 'key' => '{programa}',      'label' => __( 'Área / Programa', 'aura-suite' ),           'example' => 'Escuela de Formación' ],
            [ 'group' => __( 'Programa y Logro', 'aura-suite' ), 'key' => '{categoria}',     'label' => __( 'Categoría / Tipo de logro', 'aura-suite' ), 'example' => 'Diplomado' ],
            [ 'group' => __( 'Programa y Logro', 'aura-suite' ), 'key' => '{horas}',         'label' => __( 'Intensidad horaria', 'aura-suite' ),      'example' => '40 horas académicas' ],
            [ 'group' => __( 'Programa y Logro', 'aura-suite' ), 'key' => '{calificacion}',  'label' => __( 'Calificación / Mención', 'aura-suite' ),  'example' => 'Sobresaliente' ],
            [ 'group' => __( 'Programa y Logro', 'aura-suite' ), 'key' => '{instructor}',    'label' => __( 'Instructor / Docente', 'aura-suite' ),    'example' => 'Dr. Carlos Ruiz' ],
            [ 'group' => __( 'Programa y Logro', 'aura-suite' ), 'key' => '{descripcion}',   'label' => __( 'Descripción adicional', 'aura-suite' ),   'example' => 'Por su destacada participación y cumplimiento' ],

            // Institución y Fechas
            [ 'group' => __( 'Institución y Fechas', 'aura-suite' ), 'key' => '{organizacion}',        'label' => __( 'Nombre de la organización', 'aura-suite' ), 'example' => 'Centro Cristiano Mateo' ],
            [ 'group' => __( 'Institución y Fechas', 'aura-suite' ), 'key' => '{fecha_emision}',       'label' => __( 'Fecha de emisión (dd/mm/yyyy)', 'aura-suite' ), 'example' => '13/11/2026' ],
            [ 'group' => __( 'Institución y Fechas', 'aura-suite' ), 'key' => '{fecha_emision_larga}', 'label' => __( 'Fecha de emisión larga', 'aura-suite' ), 'example' => '13 de noviembre de 2026' ],
            [ 'group' => __( 'Institución y Fechas', 'aura-suite' ), 'key' => '{fecha_graduacion}',    'label' => __( 'Fecha de graduación / evento', 'aura-suite' ), 'example' => '10/11/2026' ],
            [ 'group' => __( 'Institución y Fechas', 'aura-suite' ), 'key' => '{ciudad}',              'label' => __( 'Ciudad / Lugar', 'aura-suite' ), 'example' => 'Bogotá D.C.' ],
            [ 'group' => __( 'Institución y Fechas', 'aura-suite' ), 'key' => '{año}',                 'label' => __( 'Año de emisión', 'aura-suite' ), 'example' => '2026' ],

            // Verificación y Firmas
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{folio}',               'label' => __( 'Folio único', 'aura-suite' ),          'example' => 'CEM-2026-0042' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{codigo_verificacion}', 'label' => __( 'Código de verificación', 'aura-suite' ), 'example' => 'A7F2-9C1B' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{url_verificacion}',    'label' => __( 'URL de verificación', 'aura-suite' ),  'example' => home_url( '/verificar-certificado/CEM-2026-0042' ) ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_1_nombre}',   'label' => __( 'Nombre firmante 1', 'aura-suite' ),    'example' => 'Director Académico' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_1_cargo}',    'label' => __( 'Cargo firmante 1', 'aura-suite' ),     'example' => 'Dirección General' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_2_nombre}',   'label' => __( 'Nombre firmante 2', 'aura-suite' ),    'example' => 'Coordinador de Área' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_2_cargo}',    'label' => __( 'Cargo firmante 2', 'aura-suite' ),     'example' => 'Coordinación Académica' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_3_nombre}',   'label' => __( 'Nombre firmante 3', 'aura-suite' ),    'example' => 'Decano / Pastor' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_3_cargo}',    'label' => __( 'Cargo firmante 3', 'aura-suite' ),     'example' => 'Decanatura' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_4_nombre}',   'label' => __( 'Nombre firmante 4', 'aura-suite' ),    'example' => 'Secretario General' ],
            [ 'group' => __( 'Verificación y Firmas', 'aura-suite' ), 'key' => '{firmante_4_cargo}',    'label' => __( 'Cargo firmante 4', 'aura-suite' ),     'example' => 'Secretaría Académica' ],
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
        $issued_ts     = ! empty( $data['issued_at'] ) ? strtotime( $data['issued_at'] ) : false;
        $grad_ts       = ! empty( $data['graduation_date'] ) ? strtotime( $data['graduation_date'] ) : false;
        $full_name     = ! empty( $data['full_name'] )
                            ? (string) $data['full_name']
                            : trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
        $first_name    = (string) ( $data['first_name'] ?? '' );
        $last_name     = (string) ( $data['last_name'] ?? '' );
        if ( $first_name === '' && $full_name !== '' ) {
            $parts      = preg_split( '/\s+/', trim( $full_name ), 2 );
            $first_name = $parts[0] ?? '';
            $last_name  = $parts[1] ?? $last_name;
        }

        $signers = $data['signers'] ?? null;
        if ( $signers === null && class_exists( 'Aura_Certificates_Signers' ) ) {
            $signers = Aura_Certificates_Signers::get_active_signers_for_js();
        }
        $s1 = is_array( $signers ) && isset( $signers[0] ) ? (array) $signers[0] : [];
        $s2 = is_array( $signers ) && isset( $signers[1] ) ? (array) $signers[1] : [];
        $s3 = is_array( $signers ) && isset( $signers[2] ) ? (array) $signers[2] : [];
        $s4 = is_array( $signers ) && isset( $signers[3] ) ? (array) $signers[3] : [];

        $map = [
            '{nombre}'              => $first_name,
            '{apellido}'            => $last_name,
            '{nombre_completo}'     => $full_name,
            '{nombre_estudiante}'   => $full_name,
            '{documento}'           => (string) ( $data['document_id'] ?? '' ),
            '{correo}'              => (string) ( $data['email'] ?? '' ),
            '{tipo_destinatario}'   => (string) ( $data['recipient_type_label'] ?? __( 'Estudiante', 'aura-suite' ) ),
            '{curso}'               => (string) ( $data['course_name'] ?? '' ),
            '{programa}'            => (string) ( $data['program_name'] ?? '' ),
            '{categoria}'           => (string) ( $data['category'] ?? '' ),
            '{horas}'               => (string) ( $data['hours'] ?? '' ),
            '{calificacion}'        => (string) ( $data['grade'] ?? '' ),
            '{instructor}'          => (string) ( $data['instructor_name'] ?? '' ),
            '{descripcion}'         => (string) ( $data['description'] ?? '' ),
            '{organizacion}'        => (string) ( $data['organization_name'] ?? '' ),
            '{fecha_emision}'       => $issued_ts ? date_i18n( 'd/m/Y', $issued_ts ) : '',
            '{fecha_emision_larga}' => $issued_ts ? date_i18n( 'j \d\e F \d\e Y', $issued_ts ) : '',
            '{fecha_graduacion}'    => $grad_ts ? date_i18n( 'd/m/Y', $grad_ts ) : '',
            '{ciudad}'              => (string) ( $data['city'] ?? '' ),
            '{año}'                 => $issued_ts ? date( 'Y', $issued_ts ) : (string) date( 'Y' ),
            '{anio_actual}'         => $issued_ts ? date( 'Y', $issued_ts ) : (string) date( 'Y' ),
            '{folio}'               => (string) ( $data['folio'] ?? '' ),
            '{codigo_verificacion}' => (string) ( $data['verification_code'] ?? '' ),
            '{url_verificacion}'    => (string) ( $data['verify_url'] ?? '' ),
            '{firmante_1_nombre}'   => (string) ( $s1['name'] ?? '' ),
            '{firmante_1_cargo}'    => (string) ( $s1['title'] ?? '' ),
            '{firmante_2_nombre}'   => (string) ( $s2['name'] ?? '' ),
            '{firmante_2_cargo}'    => (string) ( $s2['title'] ?? '' ),
            '{firmante_3_nombre}'   => (string) ( $s3['name'] ?? '' ),
            '{firmante_3_cargo}'    => (string) ( $s3['title'] ?? '' ),
            '{firmante_4_nombre}'   => (string) ( $s4['name'] ?? '' ),
            '{firmante_4_cargo}'    => (string) ( $s4['title'] ?? '' ),
        ];

        return str_replace( array_keys( $map ), array_values( $map ), $text );
    }

    /**
     * Dimensiones estándar en mm según orientación.
     */
    private static function orientation_mm( string $orientation ): array {
        return match ( $orientation ) {
            'portrait'         => [ 210.0, 297.0 ],
            'letter_landscape' => [ 279.4, 215.9 ],
            'letter_portrait'  => [ 215.9, 279.4 ],
            default            => [ 297.0, 210.0 ],
        };
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
        $allowed_ori = [ 'landscape', 'portrait', 'letter_landscape', 'letter_portrait' ];
        $raw_ori     = sanitize_key( $_POST['orientation'] ?? 'landscape' );
        $orientation = in_array( $raw_ori, $allowed_ori, true ) ? $raw_ori : 'landscape';
        [ $def_w, $def_h ] = self::orientation_mm( $orientation );
        $width_mm    = ! empty( $_POST['width_mm'] ) ? floatval( $_POST['width_mm'] ) : $def_w;
        $height_mm   = ! empty( $_POST['height_mm'] ) ? floatval( $_POST['height_mm'] ) : $def_h;
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

        $wpdb->update( $table, [ 'is_active' => $active ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );

        wp_send_json_success( [
            'is_active' => (bool) $active,
            'message'   => $active
                ? __( 'Plantilla activada.', 'aura-suite' )
                : __( 'Plantilla desactivada.', 'aura-suite' ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX: Guardar thumbnail (imagen preview del canvas)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_thumbnail(): void {
        check_ajax_referer( 'aura_certificates_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_cert_template_edit' ) && ! current_user_can( 'aura_cert_template_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'aura_certificate_templates';
        $template_id = absint( $_POST['template_id'] ?? ( $_POST['id'] ?? 0 ) );
        $data_url    = sanitize_text_field( wp_unslash( $_POST['data_url'] ?? ( $_POST['thumbnail'] ?? '' ) ) );

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
