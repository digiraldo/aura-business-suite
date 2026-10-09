<?php
/**
 * Módulo de Contactos — Directorio Maestro y CRUD Backend
 *
 * Administra el directorio unificado de personas y entidades de la organización,
 * vincula contactos con Terceros, Usuarios WP y Estudiantes, y prepara los registros
 * para su sincronización bidireccional con Google Contacts (People API).
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Contacts {

    const NONCE = 'aura_contacts_nonce';

    const CATEGORIES = array(
        'student'     => 'Estudiante',
        'teacher'     => 'Docente / Profesor',
        'staff'       => 'Personal / Staff',
        'donor'       => 'Donante / Benefactor',
        'supplier'    => 'Proveedor / Contratista',
        'member'      => 'Miembro / Afiliado',
        'volunteer'   => 'Voluntario',
        'facilitator' => 'Facilitador / Tallerista',
        'other'       => 'Otro / General',
    );

    /**
     * Inicializar los hooks y endpoints AJAX del módulo.
     */
    public static function init(): void {
        $ajax_actions = array(
            'get_list'               => 'ajax_get_list',
            'get_detail'             => 'ajax_get_detail',
            'save'                   => 'ajax_save',
            'delete'                 => 'ajax_delete',
            'restore'                => 'ajax_restore',
            'get_stats'              => 'ajax_get_stats',
            'search_third_parties'   => 'ajax_search_third_parties',
            'search_wp_users'        => 'ajax_search_wp_users',
            'link_third_party'       => 'ajax_link_third_party',
            'link_wp_user'           => 'ajax_link_wp_user',
            'save_google_settings'   => 'ajax_save_google_settings',
            'disconnect_google'      => 'ajax_disconnect_google',
            'sync_wp_users'          => 'ajax_sync_wp_users',
            'sync_third_parties'     => 'ajax_sync_third_parties',
            'sync_students'          => 'ajax_sync_students',
        );

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_contacts_' . $action, array( __CLASS__, $handler ) );
        }

        // ── Hooks en tiempo real: Usuarios de WordPress (Prioridad 2) ──
        add_action( 'user_register', array( __CLASS__, 'on_wp_user_register' ), 10, 2 );
        add_action( 'profile_update', array( __CLASS__, 'on_wp_user_profile_update' ), 10, 2 );
        add_action( 'delete_user', array( __CLASS__, 'on_wp_user_delete' ), 10, 3 );

        // ── Hooks en tiempo real: Terceros de Aura (Prioridad 2) ──
        add_action( 'aura_third_party_saved', array( __CLASS__, 'on_third_party_saved' ), 10, 2 );
        add_action( 'aura_third_party_deleted', array( __CLASS__, 'on_third_party_deleted' ), 10, 1 );

        // ── Hooks en tiempo real: Estudiantes de Aura (Prioridad 3) ──
        add_action( 'aura_student_saved', array( __CLASS__, 'on_student_saved' ), 10, 2 );
        add_action( 'aura_student_deleted', array( __CLASS__, 'on_student_deleted' ), 10, 1 );

        // ── Hooks en tiempo real: Formularios y Lead Capture (Prioridad 3) ──
        add_action( 'aura_form_submission_saved', array( __CLASS__, 'on_form_submission_saved' ), 20, 2 );
    }

    // ─────────────────────────────────────────────────────────────
    // MODELO CRUD (PHP / Reutilizable en Hooks e Integraciones)
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene un contacto por su ID con sus metadatos y mapeo de Google.
     *
     * @param int $id
     * @return array|null
     */
    public static function get_contact( int $id ): ?array {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';

        $query = $wpdb->prepare(
            "SELECT c.*, 
                    m.resource_name, m.google_etag, m.google_account, m.last_synced_at as google_synced_at
             FROM {$t_contacts} c
             LEFT JOIN {$t_google_map} m ON (c.id = m.contact_id)
             WHERE c.id = %d LIMIT 1",
            $id
        );

        $row = $wpdb->get_row( $query, ARRAY_A );
        if ( ! $row ) {
            return null;
        }

        return self::format_contact_row( $row );
    }

    /**
     * Obtiene un contacto por su email principal o secundario.
     *
     * @param string $email
     * @return array|null
     */
    public static function get_contact_by_email( string $email ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';
        $email = sanitize_email( $email );

        if ( empty( $email ) ) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE email_primary = %s OR email_secondary = %s LIMIT 1",
                $email,
                $email
            ),
            ARRAY_A
        );

        return $row ? self::format_contact_row( $row ) : null;
    }

    /**
     * Obtiene un contacto vinculado a un Tercero.
     *
     * @param int $third_party_id
     * @return array|null
     */
    public static function get_contact_by_third_party( int $third_party_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE third_party_id = %d LIMIT 1", $third_party_id ),
            ARRAY_A
        );

        return $row ? self::format_contact_row( $row ) : null;
    }

    /**
     * Obtiene un contacto vinculado a un usuario de WordPress.
     *
     * @param int $wp_user_id
     * @return array|null
     */
    public static function get_contact_by_wp_user( int $wp_user_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d LIMIT 1", $wp_user_id ),
            ARRAY_A
        );

        return $row ? self::format_contact_row( $row ) : null;
    }

    /**
     * Obtiene un contacto vinculado a un Estudiante por student_id.
     *
     * @param int $student_id
     * @return array|null
     */
    public static function get_contact_by_student( int $student_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE student_id = %d LIMIT 1", $student_id ),
            ARRAY_A
        );

        return $row ? self::format_contact_row( $row ) : null;
    }

    /**
     * Obtiene un listado paginado y filtrado de contactos.
     *
     * @param array $args
     * @return array { items: array, total: int, page: int, per_page: int, total_pages: int }
     */
    public static function get_contacts( array $args = array() ): array {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';

        $defaults = array(
            'search'       => '',
            'category'     => '',
            'contact_type' => '',
            'sync_status'  => '',
            'is_active'    => 1,
            'orderby'      => 'first_name',
            'order'        => 'ASC',
            'page'         => 1,
            'per_page'     => 20,
        );

        $params = wp_parse_args( $args, $defaults );

        $where   = array( '1=1' );
        $values  = array();

        if ( null !== $params['is_active'] && '' !== $params['is_active'] ) {
            $where[]  = 'c.is_active = %d';
            $values[] = (int) $params['is_active'];
        }

        if ( ! empty( $params['category'] ) ) {
            $where[]  = 'c.category = %s';
            $values[] = sanitize_text_field( $params['category'] );
        }

        if ( ! empty( $params['contact_type'] ) ) {
            $where[]  = 'c.contact_type = %s';
            $values[] = sanitize_text_field( $params['contact_type'] );
        }

        if ( ! empty( $params['sync_status'] ) ) {
            $where[]  = 'c.sync_status = %s';
            $values[] = sanitize_text_field( $params['sync_status'] );
        }

        if ( ! empty( $params['search'] ) ) {
            $term     = '%' . $wpdb->esc_like( sanitize_text_field( $params['search'] ) ) . '%';
            $where[]  = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.organization LIKE %s OR c.email_primary LIKE %s OR c.phone_primary LIKE %s OR c.tags LIKE %s)';
            $values[] = $term;
            $values[] = $term;
            $values[] = $term;
            $values[] = $term;
            $values[] = $term;
            $values[] = $term;
        }

        $allowed_orderby = array( 'first_name', 'last_name', 'organization', 'email_primary', 'category', 'sync_status', 'created_at', 'updated_at' );
        $orderby         = in_array( $params['orderby'], $allowed_orderby, true ) ? $params['orderby'] : 'first_name';
        $order           = strtoupper( $params['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $page     = max( 1, (int) $params['page'] );
        $per_page = min( 100, max( 5, (int) $params['per_page'] ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where_sql = implode( ' AND ', $where );

        // Consulta de conteo total
        $count_sql = "SELECT COUNT(DISTINCT c.id) FROM {$t_contacts} c WHERE {$where_sql}";
        if ( ! empty( $values ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );
        } else {
            $total = (int) $wpdb->get_var( $count_sql );
        }

        // Consulta de datos con LEFT JOIN para Google Map
        $data_sql = "SELECT c.*, 
                            m.resource_name, m.google_etag, m.google_account, m.last_synced_at as google_synced_at
                     FROM {$t_contacts} c
                     LEFT JOIN {$t_google_map} m ON (c.id = m.contact_id)
                     WHERE {$where_sql}
                     ORDER BY c.{$orderby} {$order}
                     LIMIT %d OFFSET %d";

        $query_values   = array_merge( $values, array( $per_page, $offset ) );
        $raw_items      = $wpdb->get_results( $wpdb->prepare( $data_sql, $query_values ), ARRAY_A );

        $items = array();
        foreach ( (array) $raw_items as $row ) {
            $items[] = self::format_contact_row( $row );
        }

        return array(
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
            'pages'       => (int) ceil( $total / $per_page ),
        );
    }

    /**
     * Crea un nuevo contacto en el directorio maestro de WordPress.
     *
     * @param array $data
     * @return int|WP_Error ID del contacto creado o WP_Error en caso de fallo.
     */
    public static function create_contact( array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $sanitized = self::sanitize_contact_data( $data );
        if ( is_wp_error( $sanitized ) ) {
            return $sanitized;
        }

        $sanitized['created_by']  = get_current_user_id() ?: 1;
        $sanitized['created_at']  = current_time( 'mysql' );
        $sanitized['updated_at']  = current_time( 'mysql' );
        $sanitized['sync_status'] = 'pending'; // Listo para ser enviado a Google

        $inserted = $wpdb->insert( $table, $sanitized );
        if ( false === $inserted ) {
            return new WP_Error( 'db_insert_error', __( 'Error al guardar el contacto en la base de datos.', 'aura-suite' ) );
        }

        $contact_id = (int) $wpdb->insert_id;

        /**
         * Disparar acción tras crear un contacto en Aura Suite.
         */
        do_action( 'aura_contact_created', $contact_id, $sanitized );

        return $contact_id;
    }

    /**
     * Actualiza un contacto existente.
     *
     * @param int   $id
     * @param array $data
     * @return bool|WP_Error
     */
    public static function update_contact( int $id, array $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $existing = self::get_contact( $id );
        if ( ! $existing ) {
            return new WP_Error( 'contact_not_found', __( 'El contacto que intentas actualizar no existe.', 'aura-suite' ) );
        }

        // Combinar datos entrantes con existentes para soportar actualizaciones parciales
        $merged_data = array_merge( $existing, $data );

        $sanitized = self::sanitize_contact_data( $merged_data, $id );
        if ( is_wp_error( $sanitized ) ) {
            return $sanitized;
        }

        $sanitized['updated_by']  = get_current_user_id() ?: 1;
        $sanitized['updated_at']  = current_time( 'mysql' );
        $sanitized['sync_status'] = 'pending'; // Marca cambio pendiente para sync push a Google

        $updated = $wpdb->update(
            $table,
            $sanitized,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );

        if ( false === $updated ) {
            return new WP_Error( 'db_update_error', __( 'Error al actualizar el contacto en la base de datos.', 'aura-suite' ) );
        }

        /**
         * Disparar acción tras actualizar un contacto en Aura Suite.
         */
        do_action( 'aura_contact_updated', $id, $sanitized, $existing );

        return true;
    }

    /**
     * Elimina o archiva (soft-delete) un contacto.
     *
     * @param int  $id
     * @param bool $force Si es true, ejecuta DELETE físico; si es false, marca is_active = 0.
     * @return bool|WP_Error
     */
    public static function delete_contact( int $id, bool $force = false ) {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';

        $existing = self::get_contact( $id );
        if ( ! $existing ) {
            return new WP_Error( 'not_found', __( 'El contacto no existe.', 'aura-suite' ) );
        }

        if ( $force ) {
            $wpdb->delete( $t_google_map, array( 'contact_id' => $id ), array( '%d' ) );
            $wpdb->delete( $t_contacts, array( 'id' => $id ), array( '%d' ) );
        } else {
            $wpdb->update(
                $t_contacts,
                array(
                    'is_active'   => 0,
                    'sync_status' => 'pending', // Podrá notificar a Google para archivar/eliminar
                    'updated_at'  => current_time( 'mysql' ),
                    'updated_by'  => get_current_user_id() ?: 1,
                ),
                array( 'id' => $id ),
                array( '%d', '%s', '%s', '%d' ),
                array( '%d' )
            );
        }

        do_action( 'aura_contact_deleted', $id, $force, $existing );

        return true;
    }

    /**
     * Restaura un contacto previamente archivado.
     *
     * @param int $id
     * @return bool|WP_Error
     */
    public static function restore_contact( int $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $updated = $wpdb->update(
            $table,
            array(
                'is_active'   => 1,
                'sync_status' => 'pending',
                'updated_at'  => current_time( 'mysql' ),
                'updated_by'  => get_current_user_id() ?: 1,
            ),
            array( 'id' => $id ),
            array( '%d', '%s', '%s', '%d' ),
            array( '%d' )
        );

        return false !== $updated;
    }

    /**
     * Obtiene métricas y KPIs para los tableros del módulo.
     *
     * @return array
     */
    public static function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $total_active   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1" );
        $total_archived = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 0" );
        $synced         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND sync_status = 'synced'" );
        $pending        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND sync_status = 'pending'" );
        $errors         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND sync_status = 'error'" );
        $with_tp        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND third_party_id IS NOT NULL AND third_party_id > 0" );
        $with_wp        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND wp_user_id IS NOT NULL AND wp_user_id > 0" );
        $with_student   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND student_id IS NOT NULL AND student_id > 0" );

        $by_type = $wpdb->get_results(
            "SELECT contact_type, COUNT(*) as count FROM {$table} WHERE is_active = 1 GROUP BY contact_type",
            OBJECT_K
        );

        // Agrupación reactiva por categorías del sistema
        $cat_counts = $wpdb->get_results(
            "SELECT category, COUNT(*) as count FROM {$table} WHERE is_active = 1 GROUP BY category",
            ARRAY_A
        );
        $by_category = array(
            'student'     => 0,
            'teacher'     => 0,
            'staff'       => 0,
            'donor'       => 0,
            'supplier'    => 0,
            'member'      => 0,
            'volunteer'   => 0,
            'facilitator' => 0,
            'other'       => 0,
        );
        foreach ( (array) $cat_counts as $c_row ) {
            $c_key = sanitize_key( $c_row['category'] ?? '' );
            if ( ! empty( $c_key ) ) {
                $by_category[ $c_key ] = (int) $c_row['count'];
            }
        }

        $auth_provider = Aura_Google_Auth_Factory::get_provider();

        return array(
            'total'            => $total_active,
            'total_active'     => $total_active,
            'total_archived'   => $total_archived,
            'synced'           => $synced,
            'pending'          => $pending,
            'error'            => $errors,
            'errors'           => $errors,
            'with_third_party' => $with_tp,
            'with_wp_user'     => $with_wp,
            'with_student'     => $with_student,
            'by_category'      => $by_category,
            'persons'          => isset( $by_type['person'] ) ? (int) $by_type['person']->count : 0,
            'companies'        => isset( $by_type['company'] ) ? (int) $by_type['company']->count : 0,
            'organizations'    => isset( $by_type['organization'] ) ? (int) $by_type['organization']->count : 0,
            'google_connected' => $auth_provider->is_connected(),
            'google_email'     => $auth_provider->get_account_email(),
            'auth_mode'        => $auth_provider->get_auth_mode(),
            'auth_label'       => $auth_provider->get_label(),
        );
    }

    /**
     * Lista de categorías predeterminadas y sugeridas.
     *
     * @return array
     */
    public static function get_available_categories(): array {
        return array(
            'Estudiante'   => __( 'Estudiante', 'aura-suite' ),
            'Proveedor'    => __( 'Proveedor', 'aura-suite' ),
            'Colaborador'  => __( 'Colaborador / Personal', 'aura-suite' ),
            'Donante'      => __( 'Donante / Benefactor', 'aura-suite' ),
            'Cliente'      => __( 'Cliente', 'aura-suite' ),
            'Organización' => __( 'Organización Aliada', 'aura-suite' ),
            'Postulante'   => __( 'Postulante', 'aura-suite' ),
            'Personal'     => __( 'Contacto Personal', 'aura-suite' ),
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CONTROLADORES AJAX (Endpoints del Administrador)
    // ─────────────────────────────────────────────────────────────

    /**
     * AJAX: Listado paginado de contactos con filtros.
     */
    public static function ajax_get_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No tienes permisos para ver el directorio de contactos.', 'aura-suite' ) ) );
        }

        $args = array(
            'search'       => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
            'category'     => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
            'contact_type' => sanitize_text_field( wp_unslash( $_POST['contact_type'] ?? '' ) ),
            'sync_status'  => sanitize_text_field( wp_unslash( $_POST['sync_status'] ?? '' ) ),
            'is_active'    => isset( $_POST['is_active'] ) ? intval( $_POST['is_active'] ) : 1,
            'orderby'      => sanitize_text_field( wp_unslash( $_POST['orderby'] ?? 'first_name' ) ),
            'order'        => sanitize_text_field( wp_unslash( $_POST['order'] ?? 'ASC' ) ),
            'page'         => max( 1, intval( $_POST['page'] ?? 1 ) ),
            'per_page'     => min( 100, max( 5, intval( $_POST['per_page'] ?? 20 ) ) ),
        );

        $result = self::get_contacts( $args );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Detalle completo de un contacto.
     */
    public static function ajax_get_detail(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $id = intval( $_POST['id'] ?? 0 );
        $contact = self::get_contact( $id );

        if ( ! $contact ) {
            wp_send_json_error( array( 'message' => __( 'Contacto no encontrado.', 'aura-suite' ) ) );
        }

        wp_send_json_success( array( 'contact' => $contact ) );
    }

    /**
     * AJAX: Guardar o actualizar contacto.
     */
    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $id = intval( $_POST['id'] ?? 0 );

        if ( $id > 0 ) {
            if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array( 'message' => __( 'No tienes permisos para editar contactos.', 'aura-suite' ) ) );
            }
            $result = self::update_contact( $id, $_POST );
        } else {
            if ( ! current_user_can( 'aura_contacts_create' ) && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array( 'message' => __( 'No tienes permisos para crear contactos.', 'aura-suite' ) ) );
            }
            $result = self::create_contact( $_POST );
            if ( ! is_wp_error( $result ) ) {
                $id = $result;
                $result = true;
            }
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Contacto guardado exitosamente.', 'aura-suite' ),
            'id'      => $id,
            'contact' => self::get_contact( $id ),
        ) );
    }

    /**
     * AJAX: Archivar o eliminar contacto.
     */
    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_delete' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No tienes permisos para eliminar contactos.', 'aura-suite' ) ) );
        }

        $id    = intval( $_POST['id'] ?? 0 );
        $force = ! empty( $_POST['force'] ) && current_user_can( 'manage_options' );

        $result = self::delete_contact( $id, $force );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => $force ? __( 'Contacto eliminado permanentemente.', 'aura-suite' ) : __( 'Contacto archivado.', 'aura-suite' ),
        ) );
    }

    /**
     * AJAX: Restaurar contacto archivado.
     */
    public static function ajax_restore(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $id     = intval( $_POST['id'] ?? 0 );
        $result = self::restore_contact( $id );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo restaurar el contacto.', 'aura-suite' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Contacto restaurado con éxito.', 'aura-suite' ) ) );
    }

    /**
     * AJAX: Obtener estadísticas y KPIs para el dashboard.
     */
    public static function ajax_get_stats(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        wp_send_json_success( self::get_stats() );
    }

    /**
     * AJAX: Buscar terceros para autocompletado en vinculación.
     */
    public static function ajax_search_third_parties(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_third_parties';

        // Si la tabla de terceros no existe aún
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            wp_send_json_success( array( 'items' => array() ) );
        }

        $q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
        $term = '%' . $wpdb->esc_like( $q ) . '%';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, legal_name, trade_name, identification_number, email, phone 
                 FROM {$table} 
                 WHERE is_active = 1 AND (legal_name LIKE %s OR trade_name LIKE %s OR identification_number LIKE %s OR email LIKE %s)
                 ORDER BY legal_name ASC LIMIT 15",
                $term, $term, $term, $term
            ),
            ARRAY_A
        );

        wp_send_json_success( array( 'items' => $results ?: array() ) );
    }

    /**
     * AJAX: Buscar usuarios de WordPress para vincular.
     */
    public static function ajax_search_wp_users(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $search = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
        $users  = get_users( array(
            'search'         => '*' . $search . '*',
            'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
            'number'         => 15,
            'fields'         => array( 'ID', 'display_name', 'user_email', 'user_login' ),
        ) );

        $items = array();
        foreach ( $users as $u ) {
            $items[] = array(
                'id'           => $u->ID,
                'display_name' => $u->display_name,
                'email'        => $u->user_email,
                'login'        => $u->user_login,
            );
        }

        wp_send_json_success( array( 'items' => $items ) );
    }

    /**
     * AJAX: Vincular contacto con un Tercero.
     */
    public static function ajax_link_third_party(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $contact_id     = intval( $_POST['contact_id'] ?? 0 );
        $third_party_id = intval( $_POST['third_party_id'] ?? 0 );

        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $wpdb->update(
            $table,
            array(
                'third_party_id' => $third_party_id ?: null,
                'updated_at'     => current_time( 'mysql' ),
                'sync_status'    => 'pending',
            ),
            array( 'id' => $contact_id )
        );

        wp_send_json_success( array( 'message' => __( 'Vinculación con Tercero actualizada.', 'aura-suite' ) ) );
    }

    /**
     * AJAX: Vincular contacto con un usuario de WordPress.
     */
    public static function ajax_link_wp_user(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $contact_id = intval( $_POST['contact_id'] ?? 0 );
        $wp_user_id = intval( $_POST['wp_user_id'] ?? 0 );

        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $wpdb->update(
            $table,
            array(
                'wp_user_id'  => $wp_user_id ?: null,
                'updated_at'  => current_time( 'mysql' ),
                'sync_status' => 'pending',
            ),
            array( 'id' => $contact_id )
        );

        wp_send_json_success( array( 'message' => __( 'Vinculación con Usuario WordPress actualizada.', 'aura-suite' ) ) );
    }

    /**
     * AJAX: Guardar credenciales de Google OAuth2 y modo de autenticación.
     */
    public static function ajax_save_google_settings(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_manage_google' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No tienes permisos para administrar la configuración de Google.', 'aura-suite' ) ) );
        }

        $auth_mode     = sanitize_text_field( wp_unslash( $_POST['auth_mode'] ?? 'oauth_user' ) );
        $client_id     = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
        $client_secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) );

        Aura_Google_Auth_Factory::set_active_mode( $auth_mode );

        $provider = new Aura_Google_Auth_OAuth_User();
        $provider->save_credentials( $client_id, $client_secret );

        wp_send_json_success( array(
            'message'    => __( 'Credenciales de Google OAuth guardadas exitosamente.', 'aura-suite' ),
            'auth_url'   => $provider->get_auth_url(),
            'connected'  => $provider->is_connected(),
            'account'    => $provider->get_account_email(),
        ) );
    }

    /**
     * AJAX: Desconectar cuenta de Google Contacts.
     */
    public static function ajax_disconnect_google(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_manage_google' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'aura-suite' ) ) );
        }

        $provider = Aura_Google_Auth_Factory::get_provider();
        $provider->disconnect();

        wp_send_json_success( array(
            'message' => __( 'Cuenta de Google desconectada correctamente.', 'aura-suite' ),
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS DE SANITIZACIÓN Y FORMATEO
    // ─────────────────────────────────────────────────────────────

    /**
     * Sanitiza y valida datos de entrada antes de guardarlos.
     *
     * @param array    $data
     * @param int|null $id ID del contacto si es edición
     * @return array|WP_Error
     */
    public static function sanitize_contact_data( array $data, ?int $id = null ) {
        $first_name   = sanitize_text_field( wp_unslash( $data['first_name'] ?? '' ) );
        $last_name    = sanitize_text_field( wp_unslash( $data['last_name'] ?? '' ) );
        $organization = sanitize_text_field( wp_unslash( $data['organization'] ?? '' ) );
        $contact_type = sanitize_key( $data['contact_type'] ?? 'person' );

        if ( ! in_array( $contact_type, array( 'person', 'company', 'organization' ), true ) ) {
            $contact_type = 'person';
        }

        // Validación: se requiere al menos un nombre o el nombre de la organización
        if ( empty( $first_name ) && empty( $organization ) ) {
            return new WP_Error(
                'validation_error',
                __( 'Debes ingresar al menos el Nombre o la Organización del contacto.', 'aura-suite' )
            );
        }

        // Soportar aliases de formulario: email -> email_primary
        $raw_email_primary = $data['email_primary'] ?? ( $data['email'] ?? '' );
        $email_primary     = sanitize_email( wp_unslash( $raw_email_primary ) );
        $email_secondary   = sanitize_email( wp_unslash( $data['email_secondary'] ?? '' ) );

        // Si se proporciona email primario, validar formato
        if ( ! empty( $raw_email_primary ) && ! is_email( $email_primary ) ) {
            return new WP_Error( 'invalid_email', __( 'El correo electrónico principal no tiene un formato válido.', 'aura-suite' ) );
        }

        // Soportar aliases de formulario: phone -> phone_primary, address -> address_street
        $raw_phone_primary = $data['phone_primary'] ?? ( $data['phone'] ?? '' );
        $phone_primary     = sanitize_text_field( wp_unslash( $raw_phone_primary ) );
        $phone_secondary   = sanitize_text_field( wp_unslash( $data['phone_secondary'] ?? '' ) );
        $whatsapp          = sanitize_text_field( wp_unslash( $data['whatsapp'] ?? '' ) );

        $raw_address    = $data['address_street'] ?? ( $data['address'] ?? '' );
        $address_street = sanitize_textarea_field( wp_unslash( $raw_address ) );

        $category = sanitize_text_field( wp_unslash( $data['category'] ?? 'other' ) );
        if ( ! array_key_exists( $category, self::CATEGORIES ) && ! in_array( $category, self::CATEGORIES, true ) ) {
            $category = 'other';
        }

        $wp_user_id     = ! empty( $data['wp_user_id'] ) ? absint( $data['wp_user_id'] ) : null;
        $third_party_id = ! empty( $data['third_party_id'] ) ? absint( $data['third_party_id'] ) : null;
        $student_id     = ! empty( $data['student_id'] ) ? absint( $data['student_id'] ) : null;

        // Tags en formato JSON
        $tags = array();
        if ( ! empty( $data['tags'] ) ) {
            if ( is_array( $data['tags'] ) ) {
                $tags = array_map( 'sanitize_text_field', $data['tags'] );
            } else {
                $raw = is_string( $data['tags'] ) ? $data['tags'] : '';
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) {
                    $tags = array_map( 'sanitize_text_field', $decoded );
                } else {
                    $raw_tags = explode( ',', sanitize_text_field( wp_unslash( $raw ) ) );
                    $tags     = array_filter( array_map( 'trim', $raw_tags ) );
                }
            }
        }

        // Avatar: si no viene explícito, resolver avatar desde WP User o Gravatar
        $avatar_url = esc_url_raw( wp_unslash( $data['avatar_url'] ?? ( $data['photo_url'] ?? '' ) ) );
        if ( empty( $avatar_url ) ) {
            if ( $wp_user_id ) {
                $avatar_url = get_avatar_url( $wp_user_id, array( 'size' => 256, 'default' => 'identicon' ) );
            } elseif ( ! empty( $email_primary ) ) {
                $avatar_url = get_avatar_url( $email_primary, array( 'size' => 256, 'default' => 'identicon' ) );
            }
        }

        return array(
            'wp_user_id'      => $wp_user_id,
            'third_party_id'  => $third_party_id,
            'student_id'      => $student_id,
            'contact_type'    => $contact_type,
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'organization'    => $organization,
            'job_title'       => sanitize_text_field( wp_unslash( $data['job_title'] ?? '' ) ),
            'email_primary'   => $email_primary ?: null,
            'email_secondary' => $email_secondary ?: null,
            'phone_primary'   => $phone_primary ?: null,
            'phone_secondary' => $phone_secondary ?: null,
            'whatsapp'        => $whatsapp ?: null,
            'address_street'  => $address_street ?: null,
            'address_city'    => sanitize_text_field( wp_unslash( $data['address_city'] ?? '' ) ),
            'address_state'   => sanitize_text_field( wp_unslash( $data['address_state'] ?? '' ) ),
            'address_country' => sanitize_text_field( wp_unslash( $data['address_country'] ?? 'México' ) ),
            'address_postal'  => sanitize_text_field( wp_unslash( $data['address_postal'] ?? '' ) ),
            'category'        => $category,
            'tags'            => ! empty( $tags ) ? wp_json_encode( array_values( $tags ) ) : null,
            'notes'           => sanitize_textarea_field( wp_unslash( $data['notes'] ?? '' ) ),
            'avatar_url'      => $avatar_url ?: null,
        );
    }

    /**
     * Formatea una fila de base de datos para consumo limpio en PHP y JavaScript.
     *
     * @param array $row
     * @return array
     */
    private static function format_contact_row( array $row ): array {
        $first_name = $row['first_name'] ?? '';
        $last_name  = $row['last_name'] ?? '';
        $full_name  = trim( $first_name . ' ' . $last_name );

        if ( empty( $full_name ) && ! empty( $row['organization'] ) ) {
            $full_name = $row['organization'];
        }

        $tags = array();
        if ( ! empty( $row['tags'] ) ) {
            $decoded = json_decode( $row['tags'], true );
            if ( is_array( $decoded ) ) {
                $tags = $decoded;
            }
        }

        $email = $row['email_primary'] ?? ( $row['email_secondary'] ?? '' );
        $phone = $row['phone_primary'] ?? ( $row['whatsapp'] ?? ( $row['phone_secondary'] ?? '' ) );

        $avatar = $row['avatar_url'] ?? '';

        if ( empty( $avatar ) && ! empty( $row['wp_user_id'] ) ) {
            $user_avatar = get_user_meta( (int) $row['wp_user_id'], 'aura_avatar_url', true );
            if ( ! empty( $user_avatar ) && strpos( $user_avatar, 'http' ) === 0 ) {
                $avatar = $user_avatar;
            } else {
                $avatar = get_avatar_url( (int) $row['wp_user_id'], array( 'size' => 128 ) );
            }
        }
        if ( empty( $avatar ) && ! empty( $email ) ) {
            $avatar = get_avatar_url( $email, array( 'size' => 128, 'default' => 'identicon' ) );
        }

        // Sanitización canónica contra prefijos residuales
        if ( ! empty( $avatar ) ) {
            if ( strpos( $avatar, '/:/' ) !== false ) {
                $avatar = str_replace( '/:/', '/', $avatar );
            }
            if ( strpos( $avatar, ':/wp-content' ) !== false ) {
                $avatar = str_replace( ':/wp-content', site_url( '/wp-content' ), $avatar );
            }
            if ( strpos( $avatar, ':/' ) === 0 ) {
                $avatar = site_url( '/' . ltrim( $avatar, ':/' ) );
            }
        }

        $row['id']               = (int) $row['id'];
        $row['wp_user_id']       = $row['wp_user_id'] ? (int) $row['wp_user_id'] : null;
        $row['third_party_id']   = $row['third_party_id'] ? (int) $row['third_party_id'] : null;
        $row['student_id']       = $row['student_id'] ? (int) $row['student_id'] : null;
        $row['is_active']        = (int) ( $row['is_active'] ?? 1 );
        $row['display_name']     = $full_name;
        $row['email']            = $email ?: '';
        $row['phone']            = $phone ?: '';
        $row['avatar_url']       = $avatar ?: '';
        $row['photo_url']        = $avatar ?: '';
        $row['tags']             = $tags;
        $row['tags_list']        = $tags;

        // Metadatos de Google
        $row['is_synced_google'] = ! empty( $row['resource_name'] );

        return $row;
    }

    // ─────────────────────────────────────────────────────────────
    // SINCRONIZACIÓN AUTOMÁTICA (USUARIOS WP & TERCEROS — FASE 4 P2)
    // ─────────────────────────────────────────────────────────────

    /**
     * Hook al registrar un nuevo usuario en WordPress.
     *
     * @param int   $user_id
     * @param array $userdata
     */
    public static function on_wp_user_register( int $user_id, array $userdata = array() ): void {
        self::sync_from_wp_user( $user_id );
    }

    /**
     * Hook al actualizar el perfil de un usuario en WordPress.
     *
     * @param int     $user_id
     * @param WP_User $old_user_data
     */
    public static function on_wp_user_profile_update( int $user_id, $old_user_data = null ): void {
        self::sync_from_wp_user( $user_id );
    }

    /**
     * Hook al eliminar un usuario de WordPress.
     * Desvincula el usuario del contacto sin eliminar el contacto físico.
     *
     * @param int      $user_id
     * @param int|null $reassign
     * @param WP_User  $user
     */
    public static function on_wp_user_delete( int $user_id, $reassign = null, $user = null ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $wpdb->update(
            $table,
            array(
                'wp_user_id'  => null,
                'updated_at'  => current_time( 'mysql' ),
                'sync_status' => 'pending',
            ),
            array( 'wp_user_id' => $user_id )
        );
    }

    /**
     * Sincroniza un usuario individual de WordPress hacia el directorio maestro de contactos.
     *
     * @param int $user_id
     * @return int|null ID del contacto o null si el usuario no existe.
     */
    public static function sync_from_wp_user( int $user_id ): ?int {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return null;
        }

        $first_name = trim( (string) $user->first_name );
        $last_name  = trim( (string) $user->last_name );
        $email      = sanitize_email( (string) $user->user_email );

        if ( empty( $first_name ) && empty( $last_name ) ) {
            $display = trim( (string) ( $user->display_name ?: $user->user_login ) );
            $parts   = explode( ' ', $display, 2 );
            $first_name = $parts[0] ?? $user->user_login;
            $last_name  = $parts[1] ?? '';
        }

        // Buscar teléfono en usermeta habituales
        $phone = '';
        $phone_keys = array( 'billing_phone', 'phone', 'aura_phone', 'phone_number', 'mobile', 'celular' );
        foreach ( $phone_keys as $k ) {
            $val = get_user_meta( $user_id, $k, true );
            if ( ! empty( $val ) && is_string( $val ) ) {
                $phone = sanitize_text_field( $val );
                break;
            }
        }

        // Determinar categoría según roles
        $category = 'staff';
        $roles = (array) $user->roles;
        if ( array_intersect( array( 'teacher', 'instructor', 'docente', 'facilitator' ), $roles ) ) {
            $category = 'teacher';
        } elseif ( in_array( 'student', $roles, true ) || in_array( 'alumno', $roles, true ) ) {
            $category = 'student';
        } elseif ( in_array( 'subscriber', $roles, true ) ) {
            $category = 'member';
        } elseif ( array_intersect( array( 'administrator', 'editor', 'aura_director', 'aura_coordinator', 'aura_assistant' ), $roles ) ) {
            $category = 'staff';
        }

        // Buscar contacto existente por wp_user_id o email
        $existing = self::get_contact_by_wp_user( $user_id );
        if ( ! $existing && ! empty( $email ) ) {
            $existing = self::get_contact_by_email( $email );
        }

        $contact_data = array(
            'wp_user_id'    => $user_id,
            'contact_type'  => 'person',
            'first_name'    => $first_name ?: $user->user_login,
            'last_name'     => $last_name,
            'email_primary' => $email ?: null,
            'category'      => $category,
            'is_active'     => 1,
        );

        if ( ! empty( $phone ) ) {
            $contact_data['phone_primary'] = $phone;
        }

        // Avatar
        $avatar_url = get_avatar_url( $user_id, array( 'size' => 256 ) );
        if ( ! empty( $avatar_url ) && false === strpos( $avatar_url, 'gravatar.com/avatar/?d=' ) ) {
            $contact_data['avatar_url'] = $avatar_url;
        }

        if ( $existing ) {
            // Si el contacto ya tenía una categoría personalizada en la suite (ej. donante), conservarla
            if ( ! empty( $existing['category'] ) && ! in_array( $existing['category'], array( 'other', 'staff', 'member' ), true ) ) {
                unset( $contact_data['category'] );
            }
            self::update_contact( (int) $existing['id'], $contact_data );
            return (int) $existing['id'];
        } else {
            $created_id = self::create_contact( $contact_data );
            return is_wp_error( $created_id ) ? null : (int) $created_id;
        }
    }

    /**
     * Sincroniza en lote todos los usuarios de WordPress hacia Contactos.
     *
     * @return array { total: int, created: int, updated: int }
     */
    public static function sync_all_wp_users(): array {
        $users   = get_users( array( 'number' => 1000 ) );
        $created = 0;
        $updated = 0;

        foreach ( (array) $users as $u ) {
            $uid = (int) $u->ID;
            $existing = self::get_contact_by_wp_user( $uid );
            $res = self::sync_from_wp_user( $uid );
            if ( $res ) {
                if ( $existing ) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        }

        return array(
            'total'   => count( (array) $users ),
            'created' => $created,
            'updated' => $updated,
        );
    }

    /**
     * AJAX: Sincronizar todos los usuarios de WordPress con el directorio de contactos.
     */
    public static function ajax_sync_wp_users(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ) );
        }

        $stats = self::sync_all_wp_users();

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Sincronización de Usuarios WP completada: %d procesados (%d creados, %d actualizados).', 'aura-suite' ),
                $stats['total'],
                $stats['created'],
                $stats['updated']
            ),
            'stats'   => $stats,
        ) );
    }

    /**
     * Hook al guardar (crear o actualizar) un Tercero en Aura Suite.
     *
     * @param int   $third_party_id
     * @param array $data
     */
    public static function on_third_party_saved( int $third_party_id, array $data = array() ): void {
        self::sync_from_third_party( $third_party_id );
    }

    /**
     * Hook al eliminar definitivamente un Tercero en Aura Suite.
     *
     * @param int $third_party_id
     */
    public static function on_third_party_deleted( int $third_party_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts';

        $wpdb->update(
            $table,
            array(
                'third_party_id' => null,
                'updated_at'     => current_time( 'mysql' ),
                'sync_status'    => 'pending',
            ),
            array( 'third_party_id' => $third_party_id )
        );
    }

    /**
     * Sincroniza un Tercero individual de Finanzas/Comercial hacia el directorio maestro de contactos.
     *
     * @param int $third_party_id
     * @return int|null ID del contacto o null si falló.
     */
    public static function sync_from_third_party( int $third_party_id ): ?int {
        global $wpdb;
        $table_tp = $wpdb->prefix . 'aura_finance_third_parties';

        $tp = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_tp} WHERE id = %d LIMIT 1", $third_party_id ), ARRAY_A );
        if ( ! $tp ) {
            return null;
        }

        $full_name       = trim( (string) ( $tp['full_name'] ?? '' ) );
        $commercial_name = trim( (string) ( $tp['commercial_name'] ?? '' ) );
        $party_type      = (string) ( $tp['party_type'] ?? 'company' );
        $accounting_role = (string) ( $tp['accounting_role'] ?? 'supplier' );
        $email           = sanitize_email( (string) ( $tp['email'] ?? '' ) );
        $phone           = sanitize_text_field( (string) ( $tp['phone'] ?? '' ) );
        $address         = sanitize_textarea_field( (string) ( $tp['address'] ?? '' ) );
        $notes           = sanitize_textarea_field( (string) ( $tp['notes'] ?? '' ) );
        $wp_user_id      = absint( $tp['wp_user_id'] ?? 0 );
        $is_active       = (int) ( $tp['is_active'] ?? 1 );

        // Mapeo de contact_type
        $contact_type = 'company';
        if ( 'person' === $party_type ) {
            $contact_type = 'person';
        } elseif ( in_array( $party_type, array( 'organization_foundation', 'religious' ), true ) ) {
            $contact_type = 'organization';
        }

        // Separación de nombres
        if ( 'person' === $contact_type ) {
            $parts = explode( ' ', $full_name, 2 );
            $first_name = $parts[0] ?? $full_name;
            $last_name  = $parts[1] ?? '';
            $org = $commercial_name ?: null;
        } else {
            $first_name = ! empty( $commercial_name ) ? $commercial_name : $full_name;
            $last_name  = '';
            $org = $full_name;
        }

        // Mapeo de categoría por rol contable
        $category_map = array(
            'supplier'      => 'supplier',
            'customer'      => 'member',
            'employee'      => 'staff',
            'bank_creditor' => 'other',
            'tax_authority' => 'other',
            'other'         => 'other',
        );
        $category = $category_map[ $accounting_role ] ?? 'supplier';

        $existing = self::get_contact_by_third_party( $third_party_id );
        if ( ! $existing && ! empty( $email ) ) {
            $existing = self::get_contact_by_email( $email );
        }

        $contact_data = array(
            'third_party_id' => $third_party_id,
            'contact_type'   => $contact_type,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'organization'   => $org,
            'email_primary'  => $email ?: null,
            'phone_primary'  => $phone ?: null,
            'address_street' => $address ?: null,
            'notes'          => $notes ?: null,
            'category'       => $category,
            'is_active'      => $is_active,
        );

        if ( $wp_user_id > 0 ) {
            $contact_data['wp_user_id'] = $wp_user_id;
        }

        // Avatar: resolver desde logo_id de Tercero, usuario WP o Gravatar
        if ( ! empty( $tp['logo_id'] ) ) {
            $logo_url = wp_get_attachment_image_url( (int) $tp['logo_id'], 'medium' );
            if ( $logo_url ) {
                $contact_data['avatar_url'] = $logo_url;
            }
        } elseif ( $wp_user_id > 0 ) {
            $contact_data['avatar_url'] = get_avatar_url( $wp_user_id, array( 'size' => 256 ) );
        } elseif ( ! empty( $email ) ) {
            $contact_data['avatar_url'] = get_avatar_url( $email, array( 'size' => 256, 'default' => 'identicon' ) );
        }

        if ( $existing ) {
            // Preservar categoría si fue personalizada en la libreta
            if ( ! empty( $existing['category'] ) && ! in_array( $existing['category'], array( 'other', 'supplier', 'member' ), true ) ) {
                unset( $contact_data['category'] );
            }
            self::update_contact( (int) $existing['id'], $contact_data );
            return (int) $existing['id'];
        } else {
            $created_id = self::create_contact( $contact_data );
            return is_wp_error( $created_id ) ? null : (int) $created_id;
        }
    }

    /**
     * Sincroniza en lote todos los Terceros hacia Contactos.
     *
     * @return array { total: int, created: int, updated: int }
     */
    public static function sync_all_third_parties(): array {
        global $wpdb;
        $table_tp = $wpdb->prefix . 'aura_finance_third_parties';

        $tp_ids  = $wpdb->get_col( "SELECT id FROM {$table_tp} WHERE is_active = 1" );
        $created = 0;
        $updated = 0;

        foreach ( (array) $tp_ids as $id ) {
            $id = (int) $id;
            $existing = self::get_contact_by_third_party( $id );
            $res = self::sync_from_third_party( $id );
            if ( $res ) {
                if ( $existing ) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        }

        return array(
            'total'   => count( (array) $tp_ids ),
            'created' => $created,
            'updated' => $updated,
        );
    }

    /**
     * AJAX: Sincronizar todos los Terceros con el directorio de contactos.
     */
    public static function ajax_sync_third_parties(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ) );
        }

        $stats = self::sync_all_third_parties();

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Sincronización de Terceros completada: %d procesados (%d creados, %d actualizados).', 'aura-suite' ),
                $stats['total'],
                $stats['created'],
                $stats['updated']
            ),
            'stats'   => $stats,
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // INTEGRACIÓN CON ESTUDIANTES (FASE 4 - PRIORIDAD 3)
    // ─────────────────────────────────────────────────────────────

    /**
     * Hook: Cuando se guarda o actualiza un estudiante en aura_students.
     *
     * @param int   $student_id
     * @param mixed $data
     */
    public static function on_student_saved( int $student_id, $data = null ): void {
        self::sync_from_student( $student_id );
    }

    /**
     * Hook: Cuando se elimina (soft-delete) un estudiante en aura_students.
     *
     * @param int $student_id
     */
    public static function on_student_deleted( int $student_id ): void {
        $existing = self::get_contact_by_student( $student_id );
        if ( $existing ) {
            $raw_tags = $existing['tags'] ?? array();
            $current_tags = is_array( $raw_tags ) ? $raw_tags : ( json_decode( (string) $raw_tags, true ) ?: array() );
            if ( ! in_array( 'estudiante:archivado', $current_tags, true ) ) {
                $current_tags[] = 'estudiante:archivado';
            }
            self::update_contact( (int) $existing['id'], array(
                'student_id' => null,
                'tags'       => $current_tags,
                'notes'      => trim( ( $existing['notes'] ?? '' ) . ' [Estudiante archivado/eliminado en aura_students]' ),
            ) );
        }
    }

    /**
     * Sincroniza un Estudiante individual hacia el Directorio de Contactos.
     *
     * @param int $student_id
     * @return int|null ID del contacto creado o actualizado
     */
    public static function sync_from_student( int $student_id ): ?int {
        global $wpdb;
        $table_s = $wpdb->prefix . 'aura_students';

        $student = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_s} WHERE id = %d LIMIT 1", $student_id ),
            ARRAY_A
        );

        if ( ! $student ) {
            return null;
        }

        if ( ! empty( $student['deleted_at'] ) ) {
            self::on_student_deleted( $student_id );
            return null;
        }

        $email        = sanitize_email( $student['email'] ?? '' );
        $phone        = sanitize_text_field( $student['phone'] ?? '' );
        $first_name   = sanitize_text_field( $student['first_name'] ?? '' );
        $last_name    = sanitize_text_field( $student['last_name'] ?? '' );
        $wp_user_id   = ! empty( $student['wp_user_id'] ) ? absint( $student['wp_user_id'] ) : null;
        $status       = sanitize_text_field( $student['status'] ?? 'applicant' );
        $profile_type = sanitize_text_field( $student['profile_type'] ?? '' );
        $address      = sanitize_text_field( $student['address'] ?? '' );
        $city         = sanitize_text_field( $student['city'] ?? '' );
        $country      = sanitize_text_field( $student['country'] ?? '' );
        $photo_url    = esc_url_raw( $student['photo_url'] ?? '' );
        $notes        = sanitize_textarea_field( $student['notes'] ?? '' );

        // Buscar contacto existente: por student_id, luego por email, luego por wp_user_id
        $existing = self::get_contact_by_student( $student_id );
        if ( ! $existing && ! empty( $email ) ) {
            $existing = self::get_contact_by_email( $email );
        }
        if ( ! $existing && $wp_user_id ) {
            $existing = self::get_contact_by_wp_user( $wp_user_id );
        }

        // Construir tags
        $tags = array( 'estudiante' );
        if ( ! empty( $status ) ) {
            $tags[] = 'estado:' . $status;
        }
        if ( ! empty( $profile_type ) ) {
            $tags[] = 'perfil:' . $profile_type;
        }

        if ( $existing && ! empty( $existing['tags'] ) ) {
            $prev_tags = is_array( $existing['tags'] ) ? $existing['tags'] : ( json_decode( (string) $existing['tags'], true ) ?: array() );
            $tags = array_values( array_unique( array_merge( $prev_tags, $tags ) ) );
        }

        $contact_data = array(
            'student_id'      => $student_id,
            'contact_type'    => 'person',
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'email_primary'   => $email ?: null,
            'phone_primary'   => $phone ?: null,
            'address_street'  => $address ?: null,
            'address_city'    => $city ?: null,
            'address_country' => $country ?: null,
            'category'        => 'student',
            'avatar_url'      => $photo_url ?: null,
            'tags'            => $tags,
            'is_active'       => 1,
        );

        if ( $wp_user_id ) {
            $contact_data['wp_user_id'] = $wp_user_id;
        }

        if ( ! empty( $notes ) ) {
            $contact_data['notes'] = $notes;
        }

        if ( $existing ) {
            self::update_contact( (int) $existing['id'], $contact_data );
            return (int) $existing['id'];
        } else {
            $created_id = self::create_contact( $contact_data );
            return is_wp_error( $created_id ) ? null : (int) $created_id;
        }
    }

    /**
     * Sincroniza en lote todos los estudiantes activos hacia Contactos.
     *
     * @return array { total: int, created: int, updated: int }
     */
    public static function sync_all_students(): array {
        global $wpdb;
        $table_s = $wpdb->prefix . 'aura_students';

        $student_ids = $wpdb->get_col( "SELECT id FROM {$table_s} WHERE deleted_at IS NULL" );
        $created     = 0;
        $updated     = 0;

        foreach ( (array) $student_ids as $sid ) {
            $sid      = (int) $sid;
            $existing = self::get_contact_by_student( $sid );
            $res      = self::sync_from_student( $sid );
            if ( $res ) {
                if ( $existing ) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        }

        return array(
            'total'   => count( (array) $student_ids ),
            'created' => $created,
            'updated' => $updated,
        );
    }

    /**
     * AJAX: Sincronizar todos los estudiantes con el directorio de contactos.
     */
    public static function ajax_sync_students(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_edit' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes.', 'aura-suite' ) ) );
        }

        $stats = self::sync_all_students();

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Sincronización de Estudiantes completada: %d procesados (%d creados, %d actualizados).', 'aura-suite' ),
                $stats['total'],
                $stats['created'],
                $stats['updated']
            ),
            'stats'   => $stats,
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // INTEGRACIÓN CON FORMULARIOS (LEAD CAPTURE - PRIORIDAD 3)
    // ─────────────────────────────────────────────────────────────

    /**
     * Hook: Captura de contacto tras sumisión de cualquier formulario de Aura.
     *
     * @param int $submission_id
     * @param int $form_id
     */
    public static function on_form_submission_saved( int $submission_id, int $form_id ): void {
        global $wpdb;
        $t_sub  = $wpdb->prefix . 'aura_form_submissions';
        $t_form = $wpdb->prefix . 'aura_forms';

        $sub = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$t_sub} WHERE id = %d LIMIT 1", $submission_id ),
            ARRAY_A
        );
        if ( ! $sub ) {
            return;
        }

        $form = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$t_form} WHERE id = %d LIMIT 1", $form_id ),
            ARRAY_A
        );

        $submitted_email = sanitize_email( $sub['submitted_email'] ?? '' );
        $submitted_name  = sanitize_text_field( $sub['submitted_name'] ?? '' );
        $data_json       = json_decode( $sub['data_json'] ?? '{}', true ) ?: array();

        // Si no hay email en las columnas directas, intentar extraer de data_json
        if ( empty( $submitted_email ) ) {
            foreach ( $data_json as $val ) {
                if ( is_string( $val ) && is_email( $val ) ) {
                    $submitted_email = sanitize_email( $val );
                    break;
                }
            }
        }

        if ( empty( $submitted_email ) || ! is_email( $submitted_email ) ) {
            return;
        }

        // Extraer teléfono si está en el json
        $phone = '';
        foreach ( $data_json as $key => $val ) {
            if ( ! is_string( $val ) || empty( $val ) ) {
                continue;
            }
            $lkey = strtolower( (string) $key );
            if ( false !== strpos( $lkey, 'phone' ) || false !== strpos( $lkey, 'tel' ) || false !== strpos( $lkey, 'cel' ) || false !== strpos( $lkey, 'whatsapp' ) ) {
                $phone = sanitize_text_field( $val );
                break;
            }
        }

        // Dividir nombre
        if ( ! empty( $submitted_name ) ) {
            $parts      = explode( ' ', $submitted_name, 2 );
            $first_name = $parts[0] ?? $submitted_name;
            $last_name  = $parts[1] ?? '';
        } else {
            $parts      = explode( '@', $submitted_email );
            $first_name = ucfirst( $parts[0] );
            $last_name  = '';
        }

        // Categoría y etiquetas
        $form_type  = $form['type'] ?? 'general';
        $form_title = $form['title'] ?? ( 'Formulario #' . $form_id );

        $category = 'other';
        if ( 'enrollment' === $form_type ) {
            $category = 'student';
        } elseif ( false !== strpos( strtolower( $form_title ), 'voluntari' ) ) {
            $category = 'volunteer';
        } elseif ( false !== strpos( strtolower( $form_title ), 'donan' ) || false !== strpos( strtolower( $form_title ), 'donar' ) ) {
            $category = 'donor';
        }

        $form_tag = 'form:' . sanitize_title( $form_title );
        $new_tags = array( 'lead', 'origen:formulario', $form_tag );

        $existing = self::get_contact_by_email( $submitted_email );

        if ( $existing ) {
            $prev_tags   = is_array( $existing['tags'] ?? null ) ? $existing['tags'] : ( json_decode( (string) ( $existing['tags'] ?? '' ), true ) ?: array() );
            $merged_tags = array_values( array_unique( array_merge( $prev_tags, $new_tags ) ) );
            $update_data = array(
                'tags' => $merged_tags,
            );
            if ( empty( $existing['phone_primary'] ) && ! empty( $phone ) ) {
                $update_data['phone_primary'] = $phone;
            }
            if ( ! empty( $sub['wp_user_id'] ) && empty( $existing['wp_user_id'] ) ) {
                $update_data['wp_user_id'] = (int) $sub['wp_user_id'];
            }
            self::update_contact( (int) $existing['id'], $update_data );
        } else {
            $note = sprintf( 'Capturado desde formulario: "%s" (Envío #%d, IP: %s)', $form_title, $submission_id, $sub['ip_address'] ?? 'N/A' );
            $contact_data = array(
                'contact_type'  => 'person',
                'first_name'    => $first_name,
                'last_name'     => $last_name,
                'email_primary' => $submitted_email,
                'phone_primary' => $phone ?: null,
                'category'      => $category,
                'tags'          => $new_tags,
                'notes'         => $note,
                'is_active'     => 1,
            );
            if ( ! empty( $sub['wp_user_id'] ) ) {
                $contact_data['wp_user_id'] = (int) $sub['wp_user_id'];
            }
            self::create_contact( $contact_data );
        }
    }

    /**
     * Renderiza la página principal del Módulo de Contactos.
     */
    public static function render_admin_page(): void {
        include AURA_PLUGIN_DIR . 'templates/contacts/contacts-page.php';
    }
}
