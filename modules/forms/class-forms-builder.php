<?php
/**
 * Builder del Módulo de Formularios — AJAX CRUD
 *
 * Gestiona la creación, edición, eliminación y duplicación de formularios
 * y sus campos desde el editor visual (builder).
 *
 * AJAX actions registradas:
 *  - aura_forms_save              — Crear / actualizar metadatos de formulario
 *  - aura_forms_get               — Obtener formulario completo + campos
 *  - aura_forms_delete            — Soft delete de formulario
 *  - aura_forms_duplicate         — Clonar formulario + campos
 *  - aura_forms_field_save        — Crear / actualizar un campo individual
 *  - aura_forms_field_delete      — Eliminar campo
 *  - aura_forms_field_reorder     — Guardar nuevo orden de campos
 *  - aura_forms_get_courses       — Cursos por área_id (selector dinámico)
 *
 * @package AuraBusinessSuite
 * @subpackage Forms
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Forms_Builder {

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        add_action( 'wp_ajax_aura_forms_save',         [ __CLASS__, 'ajax_save_form' ] );
        add_action( 'wp_ajax_aura_forms_get',          [ __CLASS__, 'ajax_get_form' ] );
        add_action( 'wp_ajax_aura_forms_delete',       [ __CLASS__, 'ajax_delete_form' ] );
        add_action( 'wp_ajax_aura_forms_duplicate',    [ __CLASS__, 'ajax_duplicate_form' ] );
        add_action( 'wp_ajax_aura_forms_field_save',   [ __CLASS__, 'ajax_save_field' ] );
        add_action( 'wp_ajax_aura_forms_field_delete', [ __CLASS__, 'ajax_delete_field' ] );
        add_action( 'wp_ajax_aura_forms_field_reorder',              [ __CLASS__, 'ajax_reorder_fields' ] );
        add_action( 'wp_ajax_aura_forms_get_courses',                 [ __CLASS__, 'ajax_get_courses' ] );
        add_action( 'wp_ajax_aura_forms_insert_enrollment_defaults',  [ __CLASS__, 'ajax_insert_enrollment_defaults' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — FORMULARIOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea o actualiza los metadatos de un formulario.
     * Si se pasa `id` en el POST, actualiza; de lo contrario crea.
     */
    public static function ajax_save_form(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        $form_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( $form_id ) {
            if ( ! current_user_can( 'aura_forms_edit' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => __( 'Sin permisos para editar formularios.', 'aura-suite' ) ] );
            }
        } else {
            if ( ! current_user_can( 'aura_forms_create' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( [ 'message' => __( 'Sin permisos para crear formularios.', 'aura-suite' ) ] );
            }
        }

        global $wpdb;

        // Sanitizar metadatos del formulario
        $title         = sanitize_text_field( $_POST['title']          ?? '' );
        $type          = sanitize_key( $_POST['type']                  ?? 'generic' );
        $description   = wp_kses_post( $_POST['description']           ?? '' );
        $slug_input    = sanitize_title( $_POST['slug']                ?? '' );
        $submit_label  = sanitize_text_field( $_POST['submit_button_label'] ?? __( 'Enviar', 'aura-suite' ) );
        $success_msg   = wp_kses_post( $_POST['success_message']       ?? '' );
        $redirect_url  = esc_url_raw( $_POST['redirect_url']           ?? '' );
        $is_active     = isset( $_POST['is_active'] )     ? 1 : 0;
        $requires_login= isset( $_POST['requires_login'] ) ? 1 : 0;
        $accept_multiple = isset( $_POST['accept_multiple'] ) ? 1 : 0;
        $max_submissions = isset( $_POST['max_submissions'] ) && $_POST['max_submissions'] !== ''
            ? absint( $_POST['max_submissions'] ) : null;
        $close_date    = sanitize_text_field( $_POST['close_date']     ?? '' );
        $primary_color = sanitize_hex_color( $_POST['primary_color']   ?? '#2563eb' ) ?: '#2563eb';
        $logo_url      = esc_url_raw( $_POST['logo_url']               ?? '' );
        $company_name  = sanitize_text_field( $_POST['company_name']   ?? '' );
        $notify_emails = sanitize_text_field( $_POST['notify_admin_emails'] ?? '' );
        $notify_submitter = isset( $_POST['notify_submitter'] ) ? 1 : 0;
        $course_id     = isset( $_POST['course_id'] ) && $_POST['course_id'] !== ''
            ? absint( $_POST['course_id'] ) : null;
        $area_id       = isset( $_POST['area_id'] ) && $_POST['area_id'] !== ''
            ? absint( $_POST['area_id'] ) : null;

        // Valores del auto-assign (solo para type = feedback)
        $allowed_triggers = [ 'none', 'on_enrollment_approved', 'on_course_complete', 'scheduled' ];
        $auto_trigger = sanitize_key( $_POST['auto_assign_trigger'] ?? 'none' );
        if ( ! in_array( $auto_trigger, $allowed_triggers, true ) ) {
            $auto_trigger = 'none';
        }
        $auto_days = absint( $_POST['auto_assign_days'] ?? 0 );

        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => __( 'El título del formulario es obligatorio.', 'aura-suite' ) ] );
        }

        $allowed_types = [ 'generic', 'enrollment', 'survey', 'feedback' ];
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'generic';
        }

        // Generar slug único
        if ( empty( $slug_input ) ) {
            $slug_input = sanitize_title( $title );
        }
        $slug = self::generate_unique_slug( $slug_input, $form_id );

        $data = [
            'title'               => $title,
            'slug'                => $slug,
            'type'                => $type,
            'description'         => $description,
            'submit_button_label' => $slug_input !== '' ? $submit_label : __( 'Enviar', 'aura-suite' ),
            'success_message'     => $success_msg,
            'redirect_url'        => $redirect_url,
            'is_active'           => $is_active,
            'requires_login'      => $requires_login,
            'accept_multiple'     => $accept_multiple,
            'max_submissions'     => $max_submissions,
            'close_date'          => $close_date ?: null,
            'primary_color'       => $primary_color,
            'logo_url'            => $logo_url,
            'company_name'        => $company_name,
            'notify_admin_emails' => $notify_emails,
            'notify_submitter'    => $notify_submitter,
            'course_id'           => $course_id,
            'area_id'             => $area_id,
            'auto_assign_trigger' => $auto_trigger,
            'auto_assign_days'    => $auto_days,
            'updated_by'          => get_current_user_id(),
        ];

        $table = $wpdb->prefix . 'aura_forms';

        if ( $form_id ) {
            // Verificar que el formulario existe y no ha sido eliminado
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND deleted_at IS NULL",
                $form_id
            ) );
            if ( ! $exists ) {
                wp_send_json_error( [ 'message' => __( 'Formulario no encontrado.', 'aura-suite' ) ] );
            }

            $result = $wpdb->update( $table, $data, [ 'id' => $form_id ] );
            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el formulario.', 'aura-suite' ) ] );
            }
        } else {
            $data['created_by'] = get_current_user_id();
            $result = $wpdb->insert( $table, $data );
            if ( ! $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al crear el formulario.', 'aura-suite' ) ] );
            }
            $form_id = (int) $wpdb->insert_id;
        }

        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $form_id
        ) );

        wp_send_json_success( [
            'message' => __( 'Formulario guardado correctamente.', 'aura-suite' ),
            'form'    => $form,
        ] );
    }

    /**
     * Devuelve datos completos de un formulario junto con todos sus campos.
     */
    public static function ajax_get_form(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $form_id = absint( $_GET['id'] ?? 0 );
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de formulario requerido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aura_forms WHERE id = %d AND deleted_at IS NULL",
            $form_id
        ) );

        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Formulario no encontrado.', 'aura-suite' ) ] );
        }

        $fields = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aura_form_fields WHERE form_id = %d ORDER BY sort_order ASC, id ASC",
            $form_id
        ) );

        // Decodificar options_json en cada campo
        foreach ( $fields as $field ) {
            if ( ! empty( $field->options_json ) ) {
                $decoded = json_decode( $field->options_json, true );
                $field->options = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : [];
            } else {
                $field->options = [];
            }
        }

        wp_send_json_success( [
            'form'   => $form,
            'fields' => $fields,
        ] );
    }

    /**
     * Realiza el soft delete de un formulario (deleted_at = NOW()).
     */
    public static function ajax_delete_form(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para eliminar formularios.', 'aura-suite' ) ] );
        }

        $form_id = absint( $_POST['id'] ?? 0 );
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de formulario requerido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_forms';

        $result = $wpdb->update(
            $table,
            [ 'deleted_at' => current_time( 'mysql' ) ],
            [ 'id' => $form_id, 'deleted_at' => null ]
        );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar el formulario.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Formulario eliminado correctamente.', 'aura-suite' ) ] );
    }

    /**
     * Duplica un formulario completo (metadatos + campos) generando un nuevo slug.
     */
    public static function ajax_duplicate_form(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para duplicar formularios.', 'aura-suite' ) ] );
        }

        $form_id = absint( $_POST['id'] ?? 0 );
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de formulario requerido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table_forms  = $wpdb->prefix . 'aura_forms';
        $table_fields = $wpdb->prefix . 'aura_form_fields';

        $original = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_forms} WHERE id = %d AND deleted_at IS NULL",
            $form_id
        ) );

        if ( ! $original ) {
            wp_send_json_error( [ 'message' => __( 'Formulario no encontrado.', 'aura-suite' ) ] );
        }

        // Generar nuevo slug y título
        $new_slug  = self::generate_unique_slug( $original->slug . '-copia' );
        $new_title = $original->title . ' (Copia)';

        $new_data = (array) $original;
        unset( $new_data['id'], $new_data['created_at'], $new_data['updated_at'], $new_data['deleted_at'] );
        $new_data['title']      = $new_title;
        $new_data['slug']       = $new_slug;
        $new_data['created_by'] = get_current_user_id();
        $new_data['updated_by'] = null;

        $result = $wpdb->insert( $table_forms, $new_data );
        if ( ! $result ) {
            wp_send_json_error( [ 'message' => __( 'Error al duplicar el formulario.', 'aura-suite' ) ] );
        }
        $new_form_id = (int) $wpdb->insert_id;

        // Duplicar campos
        $fields = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_fields} WHERE form_id = %d ORDER BY sort_order ASC",
            $form_id
        ) );

        foreach ( $fields as $field ) {
            $field_data = (array) $field;
            unset( $field_data['id'], $field_data['created_at'], $field_data['updated_at'] );
            $field_data['form_id']   = $new_form_id;
            $field_data['field_uid'] = self::generate_field_uid();
            $wpdb->insert( $table_fields, $field_data );
        }

        $new_form = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_forms} WHERE id = %d",
            $new_form_id
        ) );

        wp_send_json_success( [
            'message'    => __( 'Formulario duplicado correctamente.', 'aura-suite' ),
            'form'       => $new_form,
            'new_form_id'=> $new_form_id,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — CAMPOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea o actualiza un campo individual del formulario.
     * Genera field_uid automáticamente si es nuevo.
     */
    public static function ajax_save_field(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para editar campos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_form_fields';

        $field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;
        $form_id  = absint( $_POST['form_id'] ?? 0 );

        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de formulario requerido.', 'aura-suite' ) ] );
        }

        // Verificar que el formulario existe
        $form_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aura_forms WHERE id = %d AND deleted_at IS NULL",
            $form_id
        ) );
        if ( ! $form_exists ) {
            wp_send_json_error( [ 'message' => __( 'Formulario no encontrado.', 'aura-suite' ) ] );
        }

        // Tipos permitidos
        $allowed_types = [
            'text', 'email', 'tel', 'number', 'date', 'time', 'textarea',
            'select', 'radio', 'checkbox', 'scale', 'file', 'hidden',
            'section_title', 'paragraph', 'birthdate', 'image', 'downloadable',
            'terms', 'accept_only_terms',
        ];

        $field_type = sanitize_key( $_POST['field_type'] ?? 'text' );
        if ( ! in_array( $field_type, $allowed_types, true ) ) {
            $field_type = 'text';
        }

        // Sanitización de opciones JSON
        $options_raw = $_POST['options_json'] ?? '';
        $options_json = null;
        if ( ! empty( $options_raw ) ) {
            // Puede venir como JSON string o como array
            if ( is_array( $options_raw ) ) {
                $options_json = wp_json_encode( array_map( 'sanitize_text_field', $options_raw ) );
            } else {
                $decoded = json_decode( stripslashes( $options_raw ), true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                    $options_json = wp_json_encode( array_map( 'sanitize_text_field', $decoded ) );
                } else {
                    // Puede ser texto plano (una opción por línea)
                    $lines = array_filter( array_map( 'sanitize_text_field', explode( "\n", $options_raw ) ) );
                    $options_json = wp_json_encode( array_values( $lines ) );
                }
            }
        }

        $data = [
            'form_id'            => $form_id,
            'label'              => sanitize_text_field( $_POST['label'] ?? '' ),
            'description'        => sanitize_text_field( $_POST['description'] ?? '' ),
            'field_type'         => $field_type,
            'options_json'       => $options_json,
            'multiple_select'    => (int) ( $_POST['multiple_select'] ?? 0 ),
            'has_other'          => (int) ( $_POST['has_other'] ?? 0 ),
            'image_url'          => esc_url_raw( $_POST['image_url'] ?? '' ),
            'file_uploaded'      => sanitize_text_field( $_POST['file_uploaded'] ?? '' ),
            'file_url'           => esc_url_raw( $_POST['file_url'] ?? '' ),
            'instructions'       => wp_kses_post( $_POST['instructions'] ?? '' ),
            'terms_text'         => wp_kses_post( $_POST['terms_text'] ?? '' ),
            'disagreement_message' => sanitize_text_field( $_POST['disagreement_message'] ?? '' ),
            'min_value'          => isset( $_POST['min_value'] ) && $_POST['min_value'] !== ''
                ? (float) $_POST['min_value'] : null,
            'max_value'          => isset( $_POST['max_value'] ) && $_POST['max_value'] !== ''
                ? (float) $_POST['max_value'] : null,
            'allowed_extensions' => sanitize_text_field( $_POST['allowed_extensions'] ?? '' ),
            'max_file_size_kb'   => isset( $_POST['max_file_size_kb'] ) && $_POST['max_file_size_kb'] !== ''
                ? absint( $_POST['max_file_size_kb'] ) : null,
            'placeholder'        => sanitize_text_field( $_POST['placeholder'] ?? '' ),
            'default_value'      => sanitize_text_field( $_POST['default_value'] ?? '' ),
            'is_required'        => (int) ( $_POST['is_required'] ?? 0 ),
            'mapping_key'        => sanitize_key( $_POST['mapping_key'] ?? '' ) ?: null,
            'sort_order'         => absint( $_POST['sort_order'] ?? 0 ),
        ];

        if ( empty( $data['label'] ) && ! in_array( $field_type, [ 'section_title', 'paragraph', 'image', 'downloadable' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'La etiqueta del campo es obligatoria.', 'aura-suite' ) ] );
        }

        if ( $field_id ) {
            // Verificar que el campo pertenece al formulario
            $belongs = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %d AND form_id = %d",
                $field_id, $form_id
            ) );
            if ( ! $belongs ) {
                wp_send_json_error( [ 'message' => __( 'Campo no encontrado.', 'aura-suite' ) ] );
            }

            $result = $wpdb->update( $table, $data, [ 'id' => $field_id ] );
            if ( $result === false ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el campo.', 'aura-suite' ) ] );
            }
        } else {
            // Generar field_uid estable
            $data['field_uid'] = self::generate_field_uid();

            // Asignar sort_order al final si no se especificó
            if ( ! isset( $_POST['sort_order'] ) ) {
                $max_order = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT MAX(sort_order) FROM {$table} WHERE form_id = %d",
                    $form_id
                ) );
                $data['sort_order'] = $max_order + 10;
            }

            $result = $wpdb->insert( $table, $data );
            if ( ! $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al crear el campo.', 'aura-suite' ) ] );
            }
            $field_id = (int) $wpdb->insert_id;
        }

        $field = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $field_id
        ) );

        wp_send_json_success( [
            'message' => __( 'Campo guardado correctamente.', 'aura-suite' ),
            'field'   => $field,
        ] );
    }

    /**
     * Elimina un campo del formulario.
     */
    public static function ajax_delete_field(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $field_id = absint( $_POST['field_id'] ?? 0 );
        $form_id  = absint( $_POST['form_id']  ?? 0 );

        if ( ! $field_id || ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'IDs requeridos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'aura_form_fields',
            [ 'id' => $field_id, 'form_id' => $form_id ]
        );

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Error al eliminar el campo.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Campo eliminado.', 'aura-suite' ) ] );
    }

    /**
     * Actualiza el sort_order de los campos según el array de IDs enviado.
     * Recibe: form_id, order (array de field IDs en el nuevo orden)
     */
    public static function ajax_reorder_fields(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $order   = isset( $_POST['order'] ) ? (array) $_POST['order'] : [];

        if ( ! $form_id || empty( $order ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos requeridos.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_form_fields';

        foreach ( $order as $position => $field_id ) {
            $wpdb->update(
                $table,
                [ 'sort_order' => (int) $position * 10 ],
                [ 'id' => absint( $field_id ), 'form_id' => $form_id ]
            );
        }

        wp_send_json_success( [ 'message' => __( 'Orden actualizado.', 'aura-suite' ) ] );
    }

    /**
     * Devuelve los cursos activos de un área dada.
     * Usado para el select dinámico Área → Cursos en el builder.
     */
    public static function ajax_get_courses(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $area_id = absint( $_GET['area_id'] ?? $_POST['area_id'] ?? 0 );

        global $wpdb;

        if ( $area_id ) {
            $courses = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}aura_student_courses
                 WHERE area_id = %d AND status = 'active'
                 ORDER BY name ASC",
                $area_id
            ) );
        } else {
            $courses = $wpdb->get_results(
                "SELECT id, name FROM {$wpdb->prefix}aura_student_courses
                 WHERE status = 'active'
                 ORDER BY name ASC"
            );
        }

        wp_send_json_success( [ 'courses' => $courses ?: [] ] );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS ESTÁTICOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Genera un slug único para la tabla aura_forms.
     * Si hay colisión añade sufijo numérico (-2, -3, …).
     *
     * @param string   $base    Slug base (ya sanitizado con sanitize_title).
     * @param int|null $exclude ID del formulario actual para excluir en UPDATE.
     */
    public static function generate_unique_slug( string $base, int $exclude = 0 ): string {
        global $wpdb;
        $table    = $wpdb->prefix . 'aura_forms';
        $slug     = $base;
        $counter  = 2;

        while ( true ) {
            if ( $exclude ) {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s AND id != %d AND deleted_at IS NULL",
                    $slug, $exclude
                ) );
            } else {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s AND deleted_at IS NULL",
                    $slug
                ) );
            }

            if ( ! $exists ) {
                break;
            }
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Genera un field_uid estable de la forma fu_xxxxxxxxxx (10 chars aleatorios).
     */
    public static function generate_field_uid(): string {
        return 'fu_' . substr( str_shuffle( '0123456789abcdefghijklmnopqrstuvwxyz' ), 0, 10 );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — INSERTAR CAMPOS PREDETERMINADOS DE INSCRIPCIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Inserta los campos predeterminados de un formulario de inscripción
     * (Datos Personales + Postulación) con sus mapping_keys ya asignados.
     *
     * Solo actúa sobre formularios de tipo 'enrollment'.
     * Omite campos cuyo mapping_key ya existe en este formulario.
     */
    public static function ajax_insert_enrollment_defaults(): void {
        check_ajax_referer( 'aura_forms_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_forms_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para editar formularios.', 'aura-suite' ) ] );
        }

        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        if ( ! $form_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de formulario requerido.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_form_fields';

        // Verificar que el formulario existe y es de tipo enrollment
        $form = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, type FROM {$wpdb->prefix}aura_forms WHERE id = %d AND deleted_at IS NULL",
            $form_id
        ) );

        if ( ! $form || $form->type !== 'enrollment' ) {
            wp_send_json_error( [ 'message' => __( 'El formulario no es de tipo Inscripción.', 'aura-suite' ) ] );
        }

        // Obtener el mapping_key de todos los campos actuales del formulario
        $existing_keys = $wpdb->get_col( $wpdb->prepare(
            "SELECT mapping_key FROM {$table}
              WHERE form_id = %d AND mapping_key IS NOT NULL AND mapping_key != ''",
            $form_id
        ) );
        $existing_keys = array_filter( $existing_keys );

        // Calcular el sort_order máximo existente
        $max_order = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$table} WHERE form_id = %d",
            $form_id
        ) );
        $sort_order = $max_order > 0 ? $max_order + 10 : 10;

        // Definición de los campos predeterminados de inscripción
        $defaults = [
            // ── Sección: Datos Personales ─────────────────────────────────
            [
                'field_type'  => 'section_title',
                'label'       => __( 'Datos Personales', 'aura-suite' ),
                'description' => '',
                'mapping_key' => null,
                'is_required' => 0,
                'options'     => null,
            ],
            [
                'field_type'  => 'text',
                'label'       => __( 'Nombre(s)', 'aura-suite' ),
                'description' => '',
                'mapping_key' => 'first_name',
                'is_required' => 1,
                'options'     => null,
            ],
            [
                'field_type'  => 'text',
                'label'       => __( 'Apellido(s)', 'aura-suite' ),
                'description' => '',
                'mapping_key' => 'last_name',
                'is_required' => 1,
                'options'     => null,
            ],
            [
                'field_type'  => 'email',
                'label'       => __( 'Correo electrónico', 'aura-suite' ),
                'description' => __( 'Ingresa un correo válido', 'aura-suite' ),
                'mapping_key' => 'email',
                'is_required' => 1,
                'options'     => null,
            ],
            [
                'field_type'  => 'tel',
                'label'       => __( 'Teléfono', 'aura-suite' ),
                'description' => '',
                'mapping_key' => 'phone',
                'is_required' => 0,
                'options'     => null,
            ],
            [
                'field_type'  => 'birthdate',
                'label'       => __( 'Fecha de Nacimiento', 'aura-suite' ),
                'description' => '',
                'mapping_key' => 'birthdate',
                'is_required' => 0,
                'options'     => null,
            ],
            [
                'field_type'  => 'radio',
                'label'       => __( 'Sexo', 'aura-suite' ),
                'description' => '',
                'mapping_key' => 'gender',
                'is_required' => 0,
                'options'     => [ __( 'Hombre', 'aura-suite' ), __( 'Mujer', 'aura-suite' ) ],
            ],
            [
                'field_type'  => 'text',
                'label'       => __( 'Ciudad', 'aura-suite' ),
                'description' => '',
                'mapping_key' => 'city',
                'is_required' => 0,
                'options'     => null,
            ],
            [
                'field_type'  => 'text',
                'label'       => __( 'País', 'aura-suite' ),
                'description' => '',
                'mapping_key' => 'country',
                'is_required' => 0,
                'options'     => null,
            ],
            // ── Sección: Información de Postulación ───────────────────────
            [
                'field_type'  => 'section_title',
                'label'       => __( 'Información de Postulación', 'aura-suite' ),
                'description' => '',
                'mapping_key' => null,
                'is_required' => 0,
                'options'     => null,
            ],
            [
                'field_type'  => 'textarea',
                'label'       => __( '¿Por qué deseas postularte?', 'aura-suite' ),
                'description' => __( 'Cuéntanos tu motivación para unirte a este programa.', 'aura-suite' ),
                'mapping_key' => 'motivation',
                'is_required' => 0,
                'options'     => null,
            ],
        ];

        $inserted_fields = [];

        foreach ( $defaults as $def ) {
            // Omitir si el mapping_key ya existe en el formulario
            if ( $def['mapping_key'] && in_array( $def['mapping_key'], $existing_keys, true ) ) {
                $sort_order += 10;
                continue;
            }

            $options_json = null;
            if ( ! empty( $def['options'] ) && is_array( $def['options'] ) ) {
                $options_json = wp_json_encode( array_map( 'sanitize_text_field', $def['options'] ) );
            }

            $row = [
                'form_id'     => $form_id,
                'field_uid'   => self::generate_field_uid(),
                'field_type'  => $def['field_type'],
                'label'       => $def['label'],
                'description' => $def['description'],
                'is_required' => $def['is_required'],
                'mapping_key' => $def['mapping_key'],
                'options_json' => $options_json,
                'sort_order'  => $sort_order,
            ];

            $result = $wpdb->insert( $table, $row );
            if ( $result ) {
                $inserted_fields[] = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d",
                    $wpdb->insert_id
                ) );
            }

            $sort_order += 10;
        }

        $count = count( $inserted_fields );
        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: number of fields inserted */
                _n( '%d campo insertado correctamente.', '%d campos insertados correctamente.', $count, 'aura-suite' ),
                $count
            ),
            'fields'  => $inserted_fields,
        ] );
    }
}
