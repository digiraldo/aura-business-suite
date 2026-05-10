<?php
/**
 * Áreas y Programas — Fase 7, Ítem 7.2
 *
 * CRUD Admin UI para gestión de Áreas: submenú, AJAX endpoints,
 * formulario de creación/edición y listado con filtros.
 *
 * @package AuraBusinessSuite
 * @subpackage Areas
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Areas_Admin {

    /** Acción nonce para todos los AJAX de este módulo */
    const NONCE = 'aura_areas_nonce';

    /* ======================================================================
     * INIT
     * ==================================================================== */

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // AJAX handlers (solo admin logueado)
        $ajax_actions = [ 'list', 'save', 'delete', 'get', 'users', 'areas_dropdown', 'assign_users', 'crop_logo', 'types_dropdown' ];
        foreach ( $ajax_actions as $action ) {
            add_action( 'wp_ajax_aura_areas_' . $action, [ __CLASS__, 'ajax_' . $action ] );
        }
        // Fase 8.3: Dashboard de área (endpoint independiente)
        add_action( 'wp_ajax_aura_area_dashboard_data', [ __CLASS__, 'ajax_area_dashboard_data' ] );
    }

    /* ======================================================================
     * MENÚ
     * ==================================================================== */

    public static function add_admin_menu(): void {
        add_submenu_page(
            'aura-suite',
            __( 'Áreas y Programas', 'aura-suite' ),
            '<span class="dashicons dashicons-networking" style="font-size:16px;line-height:1.4;vertical-align:text-bottom;margin-right:4px;"></span>' . __( 'Áreas', 'aura-suite' ),
            'aura_areas_manage',
            'aura-areas',
            [ __CLASS__, 'render_page' ]
        );
    }

    /* ======================================================================
     * ASSETS
     * ==================================================================== */

    public static function enqueue_assets( string $hook ): void {
        if ( 'aura-suite_page_aura-areas' !== $hook && 'aura-suite_page_aura-areas-tipos' !== $hook ) {
            return;
        }

        // WordPress Color Picker
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // WordPress media uploader (para logo de área)
        wp_enqueue_media();

        // DataTables core + Responsive
        wp_enqueue_style(
            'datatables-css',
            'https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css',
            [], '2.2.2'
        );
        wp_enqueue_style(
            'datatables-responsive-css',
            'https://cdn.datatables.net/responsive/3.0.4/css/responsive.dataTables.min.css',
            [ 'datatables-css' ], '3.0.4'
        );
        wp_enqueue_script(
            'datatables-js',
            'https://cdn.datatables.net/2.2.2/js/dataTables.min.js',
            [ 'jquery' ], '2.2.2', true
        );
        wp_enqueue_script(
            'datatables-responsive-js',
            'https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js',
            [ 'datatables-js' ], '3.0.4', true
        );

        // Los datos JS (ajaxUrl, nonce, strings) se inyectan directamente
        // en el template via wp_json_encode() para garantizar disponibilidad.
    }

    /* ======================================================================
     * RENDER
     * ==================================================================== */

    public static function render_page(): void {
        $view = sanitize_key( $_GET['view'] ?? '' );

        if ( 'dashboard' === $view ) {
            $can_access = current_user_can( 'manage_options' )
                       || current_user_can( 'aura_areas_manage' )
                       || current_user_can( 'aura_areas_view_all' )
                       || current_user_can( 'aura_areas_view_own' );
            if ( ! $can_access ) {
                wp_die( __( 'No tienes permisos para ver este dashboard.', 'aura-suite' ) );
            }
            $template = AURA_PLUGIN_DIR . 'templates/areas/area-dashboard.php';
        } else {
            if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
                wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
            }
            $template = AURA_PLUGIN_DIR . 'templates/areas/areas-page.php';
        }

        if ( file_exists( $template ) ) {
            require_once $template;
        }
    }

    /* ======================================================================
     * AJAX: LIST — lista paginada con filtros
     * ==================================================================== */

    public static function ajax_list(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . Aura_Areas_Setup::TABLE;

        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $type   = isset( $_POST['type'] )   ? sanitize_text_field( $_POST['type'] )   : '';
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $page   = isset( $_POST['paged'] )  ? max( 1, (int) $_POST['paged'] )         : 1;
        $per    = 20;
        $offset = ( $page - 1 ) * $per;

        $where = [ '1=1' ];

        if ( $status && in_array( $status, [ 'active', 'archived' ], true ) ) {
            $where[] = $wpdb->prepare( 'a.status = %s', $status );
        }

        if ( $type ) {
            $valid_slugs = array_keys( Aura_Areas_Setup::get_all_types() );
            if ( in_array( $type, $valid_slugs, true ) ) {
                $where[] = $wpdb->prepare( 'a.type = %s', $type );
            }
        }

        if ( $search ) {
            $like    = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = $wpdb->prepare( '(a.name LIKE %s OR a.description LIKE %s)', $like, $like );
        }

        $where_sql = implode( ' AND ', $where );

        // Total de registros
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} a WHERE {$where_sql}" );

        // Resultados con JOIN a users y auto-join para área padre
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*,
                    u.display_name  AS responsible_name,
                    par.name        AS parent_name
             FROM   {$table} a
             LEFT   JOIN {$wpdb->users} u   ON u.ID  = a.responsible_user_id
             LEFT   JOIN {$table}       par ON par.id = a.parent_area_id
             WHERE  {$where_sql}
             ORDER  BY a.sort_order ASC, a.name ASC
             LIMIT  %d OFFSET %d",
            $per,
            $offset
        ) );

        $areas = [];
        foreach ( $rows as $row ) {
            // Enriquecer con presupuesto asignado si existe la tabla
            $row->budget_assigned = self::get_budget_assigned( (int) $row->id );
            $areas[] = self::format_area( $row );
        }

        wp_send_json_success( [
            'areas' => $areas,
            'total' => $total,
            'pages' => (int) ceil( $total / $per ),
            'page'  => $page,
        ] );
    }

    /* ======================================================================
     * AJAX: GET — obtener área individual para modal de edición
     * ==================================================================== */

    public static function ajax_get(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . Aura_Areas_Setup::TABLE;
        $id    = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de área inválido.', 'aura-suite' ) ] );
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*,
                    u.display_name AS responsible_name,
                    par.name       AS parent_name
             FROM   {$table} a
             LEFT   JOIN {$wpdb->users} u   ON u.ID  = a.responsible_user_id
             LEFT   JOIN {$table}       par ON par.id = a.parent_area_id
             WHERE  a.id = %d",
            $id
        ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Área no encontrada.', 'aura-suite' ) ] );
        }

        wp_send_json_success( [ 'area' => self::format_area( $row ) ] );
    }

    /* ======================================================================
     * AJAX: SAVE — crear o editar un área
     * ==================================================================== */

    public static function ajax_save(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . Aura_Areas_Setup::TABLE;

        // ──── Validaciones ────────────────────────────────────────────────
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => __( 'El nombre del área es obligatorio.', 'aura-suite' ) ] );
        }

        $id             = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
        $type           = sanitize_text_field( $_POST['type'] ?? 'program' );
        $description    = sanitize_textarea_field( $_POST['description'] ?? '' );
        $color          = sanitize_hex_color( $_POST['color'] ?? '' ) ?: '#2271b1';
        $icon           = sanitize_text_field( $_POST['icon'] ?? 'dashicons-groups' );
        $logo_id        = absint( $_POST['logo_id'] ?? 0 );
        $sort_order     = absint( $_POST['sort_order'] ?? 0 );
        $responsible_id = absint( $_POST['responsible_user_id'] ?? 0 );
        $parent_id      = absint( $_POST['parent_area_id'] ?? 0 );

        // Validar tipo contra los slugs registrados en la tabla dinámica
        $valid_types = array_keys( Aura_Areas_Setup::get_all_types() );
        if ( ! in_array( $type, $valid_types, true ) ) {
            $type = ! empty( $valid_types ) ? $valid_types[0] : 'program';
        }

        // Evitar que un área sea su propio padre
        if ( $id && $parent_id === $id ) {
            $parent_id = 0;
        }

        // ──── Slug ────────────────────────────────────────────────────────
        if ( $id ) {
            // Al editar conservamos el slug original
            $existing_slug = $wpdb->get_var( $wpdb->prepare(
                "SELECT slug FROM {$table} WHERE id = %d",
                $id
            ) );
            $slug = $existing_slug ?: self::unique_slug( $name, $id );
        } else {
            $slug = self::unique_slug( $name );
        }

        // ──── Datos a guardar ─────────────────────────────────────────────
        $data    = [
            'name'                => $name,
            'type'                => $type,
            'description'         => $description,
            'color'               => $color,
            'icon'                => $icon,
            'logo_id'             => $logo_id ?: null,
            'sort_order'          => $sort_order,
            'responsible_user_id' => $responsible_id ?: null,
            'parent_area_id'      => $parent_id ?: null,
        ];
        $formats = [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ];

        if ( $id ) {
            // UPDATE
            $result = $wpdb->update( $table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
            if ( false === $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al actualizar el área.', 'aura-suite' ) ] );
            }
        } else {
            // INSERT
            $data['slug']       = $slug;
            $data['status']     = 'active';
            $data['created_by'] = get_current_user_id();
            $formats[]          = '%s'; // slug
            $formats[]          = '%s'; // status
            $formats[]          = '%d'; // created_by

            $result = $wpdb->insert( $table, $data, $formats );
            if ( ! $result ) {
                wp_send_json_error( [ 'message' => __( 'Error al crear el área.', 'aura-suite' ) ] );
            }
            $id = (int) $wpdb->insert_id;
        }

        // Devolver el área actualizada
        $row            = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        $row->budget_assigned = self::get_budget_assigned( $id );

        wp_send_json_success( [
            'area'    => self::format_area( $row ),
            'message' => __( 'Área guardada exitosamente.', 'aura-suite' ),
        ] );
    }

    /* ======================================================================
     * AJAX: DELETE — archivar (soft-delete)
     * ==================================================================== */

    public static function ajax_delete(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table  = $wpdb->prefix . Aura_Areas_Setup::TABLE;
        $id     = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
        $action = isset( $_POST['archive_action'] ) ? sanitize_text_field( $_POST['archive_action'] ) : 'archive';

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'ID de área inválido.', 'aura-suite' ) ] );
        }

        $new_status = ( 'reactivate' === $action ) ? 'active' : 'archived';

        $result = $wpdb->update(
            $table,
            [ 'status' => $new_status ],
            [ 'id'     => $id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( false === $result ) {
            wp_send_json_error( [ 'message' => __( 'Error al cambiar el estado del área.', 'aura-suite' ) ] );
        }

        $msg = ( 'active' === $new_status )
            ? __( 'Área reactivada.', 'aura-suite' )
            : __( 'Área archivada.', 'aura-suite' );

        wp_send_json_success( [ 'message' => $msg, 'new_status' => $new_status ] );
    }

    /* ======================================================================
     * AJAX: USERS — lista de usuarios WP para dropdown de responsable
     * ==================================================================== */

    public static function ajax_users(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $users = get_users( [
            'fields'  => [ 'ID', 'display_name', 'user_email' ],
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'number'  => 200,
        ] );

        $list = [];
        foreach ( $users as $u ) {
            $list[] = [
                'id'           => (int) $u->ID,
                'name'         => $u->display_name,
                'email'        => $u->user_email,
                'avatar_url'   => get_avatar_url( $u->ID, [ 'size' => 32 ] ),
            ];
        }

        wp_send_json_success( [ 'users' => $list ] );
    }
    
    /* ======================================================================
     * AJAX: ASSIGN_USERS — asignar múltiples usuarios a un área
     * ==================================================================== */

    public static function ajax_assign_users(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
        $user_ids = isset( $_POST['user_ids'] ) && is_array( $_POST['user_ids'] ) 
            ? array_map( 'absint', $_POST['user_ids'] ) 
            : [];

        if ( ! $area_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de área inválido.', 'aura-suite' ) ] );
        }

        // Asignar usuarios al área
        $result = Aura_Areas_Setup::assign_users_to_area( $area_id, $user_ids );

        if ( $result ) {
            // Devolver la lista actualizada de usuarios
            $assigned_users = Aura_Areas_Setup::get_area_users( $area_id );
            wp_send_json_success( [
                'message'        => __( 'Usuarios asignados exitosamente.', 'aura-suite' ),
                'assigned_users' => $assigned_users,
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Error al asignar usuarios.', 'aura-suite' ) ] );
        }
    }

    /* ======================================================================
     * AJAX: AREAS_DROPDOWN — lista de áreas activas para dropdown de padre
     * ==================================================================== */

    public static function ajax_areas_dropdown(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $table     = $wpdb->prefix . Aura_Areas_Setup::TABLE;
        $except_id = isset( $_POST['except_id'] ) ? absint( $_POST['except_id'] ) : 0;

        if ( $except_id ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, name, type FROM {$table}
                 WHERE  status = 'active' AND id != %d
                 ORDER  BY sort_order ASC, name ASC",
                $except_id
            ) );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results(
                "SELECT id, name, type FROM {$table}
                 WHERE  status = 'active'
                 ORDER  BY sort_order ASC, name ASC"
            );
        }

        $areas = [];
        foreach ( $rows as $r ) {
            $areas[] = [
                'id'   => (int) $r->id,
                'name' => $r->name,
                'type' => $r->type,
            ];
        }

        wp_send_json_success( [ 'areas' => $areas ] );
    }

    /* ======================================================================
     * AJAX: ÁREA DASHBOARD DATA — Fase 8.3
     * ==================================================================== */

    public static function ajax_area_dashboard_data(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $can_manage   = current_user_can( 'manage_options' ) || current_user_can( 'aura_areas_manage' );
        $can_view_all = $can_manage || current_user_can( 'aura_areas_view_all' );
        $can_view_own = current_user_can( 'aura_areas_view_own' );
        $can_budget   = $can_view_all || current_user_can( 'aura_areas_budget_view' );

        if ( ! $can_view_all && ! $can_view_own ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $areas_table = $wpdb->prefix . Aura_Areas_Setup::TABLE;

        $area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;

        // Usuarios con view_own solo pueden ver su área responsable
        if ( ! $can_view_all && $can_view_own ) {
            $user_area_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM `{$areas_table}` WHERE responsible_user_id = %d AND status = 'active' LIMIT 1",
                get_current_user_id()
            ) );
            if ( ! $user_area_id ) {
                wp_send_json_error( [ 'message' => __( 'No estás asignado como responsable de ningún área activa.', 'aura-suite' ) ] );
            }
            $area_id = $user_area_id;
        }

        if ( ! $area_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de área inválido.', 'aura-suite' ) ] );
        }

        // ── Datos del área ─────────────────────────────────────────────────
        $area = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, u.display_name AS responsible_name
             FROM `{$areas_table}` a
             LEFT JOIN `{$wpdb->users}` u ON u.ID = a.responsible_user_id
             WHERE a.id = %d",
            $area_id
        ), ARRAY_A );

        if ( ! $area ) {
            wp_send_json_error( [ 'message' => __( 'Área no encontrada.', 'aura-suite' ) ] );
        }

        $tx_table  = $wpdb->prefix . 'aura_finance_transactions';
        $bud_table = $wpdb->prefix . 'aura_finance_budgets';
        $cat_table = $wpdb->prefix . 'aura_finance_categories';

        // ── Verificar que existan las columnas area_id ─────────────────────
        $tx_has_area  = ! empty( $wpdb->get_results( $wpdb->prepare(
            'SHOW COLUMNS FROM `' . $tx_table  . '` LIKE %s', 'area_id'
        ) ) );
        $bud_has_area = ! empty( $wpdb->get_results( $wpdb->prepare(
            'SHOW COLUMNS FROM `' . $bud_table . '` LIKE %s', 'area_id'
        ) ) );

        // ── KPIs de presupuesto ────────────────────────────────────────────
        $kpis          = null;
        $budget_alerts = [];

        if ( $can_budget && $bud_has_area ) {
            $budgets = $wpdb->get_results( $wpdb->prepare(
                "SELECT b.*, c.name AS category_name
                 FROM `{$bud_table}` b
                 LEFT JOIN `{$cat_table}` c ON c.id = b.category_id
                 WHERE b.area_id = %d AND b.status = 'active'",
                $area_id
            ) );

            $total_budget = 0.0;

            foreach ( $budgets as $b ) {
                $total_budget += (float) $b->budget_amount;

                // Ejecutado dentro del período del presupuesto
                $executed_bud = (float) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0)
                     FROM `{$tx_table}`
                     WHERE category_id = %d
                       AND transaction_type = 'expense'
                       AND transaction_date BETWEEN %s AND %s
                       AND status = 'approved'
                       AND deleted_at IS NULL",
                    $b->category_id,
                    $b->start_date,
                    $b->end_date
                ) );

                $pct_bud   = $b->budget_amount > 0 ? round( $executed_bud / $b->budget_amount * 100, 1 ) : 0;
                $threshold = (float) ( $b->alert_threshold ?? 80 );

                if ( $pct_bud >= $threshold ) {
                    $budget_alerts[] = [
                        'budget_id'     => (int) $b->id,
                        'category_name' => $b->category_name ?: __( 'Sin categoría', 'aura-suite' ),
                        'budget_amount' => (float) $b->budget_amount,
                        'executed'      => $executed_bud,
                        'pct'           => $pct_bud,
                        'threshold'     => $threshold,
                        'is_exceeded'   => $pct_bud > 100,
                    ];
                }
            }

            // Ejecutado general del área (todas las transacciones expense aprobadas)
            $total_executed = $tx_has_area
                ? (float) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0)
                     FROM `{$tx_table}`
                     WHERE area_id = %d
                       AND transaction_type = 'expense'
                       AND status = 'approved'
                       AND deleted_at IS NULL",
                    $area_id
                  ) )
                : 0.0;

            $total_income = $tx_has_area
                ? (float) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0)
                     FROM `{$tx_table}`
                     WHERE area_id = %d
                       AND transaction_type = 'income'
                       AND status = 'approved'
                       AND deleted_at IS NULL",
                    $area_id
                  ) )
                : 0.0;

            $pct_global = $total_budget > 0 ? round( $total_executed / $total_budget * 100, 1 ) : 0;

            $kpis = [
                'total_budget'   => $total_budget,
                'total_executed' => $total_executed,
                'total_income'   => $total_income,
                'available'      => max( 0.0, $total_budget - $total_executed ),
                'overrun'        => max( 0.0, $total_executed - $total_budget ),
                'pct'            => $pct_global,
                'alerts'         => $budget_alerts,
            ];
        }

        // ── Gasto por categoría (gráfico de barras) ────────────────────────
        $chart_data = [];
        if ( $tx_has_area ) {
            $by_cat = $wpdb->get_results( $wpdb->prepare(
                "SELECT c.name, c.color, COALESCE(SUM(t.amount), 0) AS total
                 FROM `{$tx_table}` t
                 LEFT JOIN `{$cat_table}` c ON c.id = t.category_id
                 WHERE t.area_id = %d
                   AND t.transaction_type = 'expense'
                   AND t.status = 'approved'
                   AND t.deleted_at IS NULL
                 GROUP BY t.category_id
                 ORDER BY total DESC
                 LIMIT 10",
                $area_id
            ) );

            $max_amount = 0;
            foreach ( $by_cat as $row ) {
                $max_amount = max( $max_amount, (float) $row->total );
            }
            foreach ( $by_cat as $row ) {
                $chart_data[] = [
                    'name'  => $row->name ?: __( 'Sin categoría', 'aura-suite' ),
                    'color' => $row->color ?: '#607d8b',
                    'total' => (float) $row->total,
                    'pct'   => $max_amount > 0 ? round( (float) $row->total / $max_amount * 100, 1 ) : 0,
                ];
            }
        }

        // ── Últimas 10 transacciones ───────────────────────────────────────
        $recent_tx = [];
        $tx_count  = 0;
        if ( $tx_has_area ) {
            $recent_tx = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.id, t.transaction_type, t.amount, t.transaction_date,
                        t.description, t.status,
                        c.name AS category_name, c.color AS category_color,
                        u.display_name AS created_by_name
                 FROM `{$tx_table}` t
                 LEFT JOIN `{$cat_table}` c ON c.id = t.category_id
                 LEFT JOIN `{$wpdb->users}` u ON u.ID = t.created_by
                 WHERE t.area_id = %d
                   AND t.deleted_at IS NULL
                 ORDER BY t.transaction_date DESC, t.id DESC
                 LIMIT 10",
                $area_id
            ), ARRAY_A ) ?: [];

            $tx_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$tx_table}` WHERE area_id = %d AND deleted_at IS NULL",
                $area_id
            ) );
        }

        wp_send_json_success( [
            'area'       => $area,
            'kpis'       => $kpis,
            'chart_data' => $chart_data,
            'recent_tx'  => $recent_tx,
            'tx_count'   => $tx_count,
            'can_budget' => $can_budget,
        ] );
    }

    /* ======================================================================
     * AJAX: CROP_LOGO — subir/recortar logo de área desde Biblioteca de Medios
     * ==================================================================== */

    public static function ajax_crop_logo(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        $crop_x        = (int) round( (float) ( $_POST['x']      ?? 0 ) );
        $crop_y        = (int) round( (float) ( $_POST['y']      ?? 0 ) );
        $crop_w        = (int) round( (float) ( $_POST['width']  ?? 0 ) );
        $crop_h        = (int) round( (float) ( $_POST['height'] ?? 0 ) );

        if ( ! $attachment_id ) {
            wp_send_json_error( [ 'message' => __( 'Attachment inválido.', 'aura-suite' ) ] );
        }

        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => __( 'El archivo seleccionado no es una imagen.', 'aura-suite' ) ] );
        }

        // Si hay datos de recorte válidos, recortar; si no, solo registrar el attachment original
        if ( $crop_w >= 10 && $crop_h >= 10 ) {
            $original_path = get_attached_file( $attachment_id );
            if ( ! $original_path || ! file_exists( $original_path ) ) {
                wp_send_json_error( [ 'message' => __( 'Archivo original no encontrado.', 'aura-suite' ) ] );
            }

            $editor = wp_get_image_editor( $original_path );
            if ( is_wp_error( $editor ) ) {
                wp_send_json_error( [ 'message' => $editor->get_error_message() ] );
            }

            $crop_result = $editor->crop( $crop_x, $crop_y, $crop_w, $crop_h );
            if ( is_wp_error( $crop_result ) ) {
                wp_send_json_error( [ 'message' => $crop_result->get_error_message() ] );
            }

            // Redimensionar a máximo 600×600 solo si supera ese tamaño
            $current_size = $editor->get_size();
            if ( $current_size['width'] > 600 || $current_size['height'] > 600 ) {
                $resize_result = $editor->resize( 600, 600 );
                if ( is_wp_error( $resize_result ) ) {
                    wp_send_json_error( [ 'message' => $resize_result->get_error_message() ] );
                }
            }

            // Preservar PNG para mantener transparencia; el resto → JPEG q85.
            $orig_mime = (string) ( get_post_mime_type( $attachment_id ) ?: 'image/jpeg' );
            $is_png    = ( 'image/png' === $orig_mime );
            $save_mime = $is_png ? 'image/png' : 'image/jpeg';
            $save_ext  = $is_png ? 'png' : 'jpg';

            if ( ! $is_png ) {
                $editor->set_quality( 85 );
            }

            $upload_dir = wp_upload_dir();
            $filename   = 'area-logo-' . time() . '-' . wp_rand( 1000, 9999 ) . '.' . $save_ext;
            $save_path  = trailingslashit( $upload_dir['path'] ) . $filename;
            $saved      = $editor->save( $save_path, $save_mime );

            if ( is_wp_error( $saved ) ) {
                wp_send_json_error( [ 'message' => $saved->get_error_message() ] );
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_data = [
                'post_mime_type' => $save_mime,
                'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
            $new_id = wp_insert_attachment( $attachment_data, $saved['path'] );
            if ( is_wp_error( $new_id ) ) {
                wp_send_json_error( [ 'message' => $new_id->get_error_message() ] );
            }

            $metadata = wp_generate_attachment_metadata( $new_id, $saved['path'] );
            wp_update_attachment_metadata( $new_id, $metadata );
            $attachment_id = $new_id;
        }

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: wp_get_attachment_url( $attachment_id ),
            'thumb_url'     => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: wp_get_attachment_url( $attachment_id ),
        ] );
    }

    /* ======================================================================
     * HELPERS PRIVADOS
     * ==================================================================== */

    /**
     * Formatea una fila de DB como array para JSON.
     */
    private static function format_area( object $row ): array {
        $area_id = (int) ( $row->id ?? 0 );
        
        // Obtener usuarios asignados con avatares
        $assigned_users = Aura_Areas_Setup::get_area_users( $area_id );
        
        return [
            'id'                  => $area_id,
            'name'                => $row->name ?? '',
            'slug'                => $row->slug ?? '',
            'type'                => $row->type ?? 'program',
            'type_label'          => self::type_label( $row->type ?? 'program' ),
            'description'         => $row->description ?? '',
            'color'               => $row->color ?? '#2271b1',
            'icon'                => $row->icon ?? 'dashicons-groups',
            'logo_id'             => (int) ( $row->logo_id ?? 0 ),
            'logo_url'            => ( (int) ( $row->logo_id ?? 0 ) )
                                        ? ( wp_get_attachment_image_url( (int) $row->logo_id, 'medium' ) ?: '' )
                                        : '',
            'logo_thumb_url'      => ( (int) ( $row->logo_id ?? 0 ) )
                                        ? ( wp_get_attachment_image_url( (int) $row->logo_id, 'thumbnail' ) ?: '' )
                                        : '',
            'sort_order'          => (int) ( $row->sort_order ?? 0 ),
            'status'              => $row->status ?? 'active',
            'responsible_user_id' => (int) ( $row->responsible_user_id ?? 0 ),
            'responsible_name'    => $row->responsible_name ?? '',
            'assigned_users'      => $assigned_users, // Nuevo: múltiples responsables
            'parent_area_id'      => (int) ( $row->parent_area_id ?? 0 ),
            'parent_name'         => $row->parent_name ?? '',
            'budget_assigned'     => $row->budget_assigned ?? null,
        ];
    }

    /**
     * Etiqueta legible del tipo de área.
     */
    private static function type_label( string $type ): string {
        $types = Aura_Areas_Setup::get_all_types();
        return $types[ $type ]['name'] ?? ucfirst( $type );
    }

    /**
     * Genera un slug único en la tabla de áreas.
     */
    private static function unique_slug( string $name, int $exclude_id = 0 ): string {
        global $wpdb;
        $table = $wpdb->prefix . Aura_Areas_Setup::TABLE;
        $base  = sanitize_title( $name );
        $slug  = $base;
        $i     = 1;

        while ( true ) {
            if ( $exclude_id ) {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s AND id != %d",
                    $slug,
                    $exclude_id
                ) );
            } else {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE slug = %s",
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

    /**
     * Devuelve el presupuesto asignado a un área (FASE 8 lo utiliza plenamente).
     * En tanto, intenta leer de aura_finance_budgets si la columna area_id existe.
     */
    /* ======================================================================
     * AJAX: TYPES_DROPDOWN — lista tipos para select en formulario de áreas
     * ==================================================================== */

    public static function ajax_types_dropdown(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_areas_manage' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'aura-suite' ) ] );
        }

        $types  = Aura_Areas_Setup::get_all_types();
        $result = [];
        foreach ( $types as $slug => $info ) {
            $result[] = [
                'slug'       => $slug,
                'name'       => $info['name'],
                'color'      => $info['color'],
                'is_default' => $info['is_default'],
            ];
        }

        wp_send_json_success( $result );
    }

    private static function get_budget_assigned( int $area_id ): ?float {
        if ( ! $area_id ) {
            return null;
        }

        global $wpdb;
        $budgets_table = $wpdb->prefix . 'aura_finance_budgets';

        // Verificar que la tabla y la columna existen antes de consultar
        $column_exists = $wpdb->get_results( $wpdb->prepare(
            'SHOW COLUMNS FROM `' . $budgets_table . '` LIKE %s',
            'area_id'
        ) );

        if ( empty( $column_exists ) ) {
            return null;
        }

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM {$budgets_table} WHERE area_id = %d AND status = 'active'",
            $area_id
        ) );

        return $total !== null ? (float) $total : null;
    }
}
