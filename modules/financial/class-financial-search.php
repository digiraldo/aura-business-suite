<?php
/**
 * Búsqueda Avanzada de Transacciones — Fase 5, Item 5.2
 *
 * Soporta: "frase exacta", AND, OR, -exclusión, campo:valor
 * Campos: tipo, estado, metodo, importe, categoria, referencia
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Search {

    /* ------------------------------------------------------------------
     * Init
     * ------------------------------------------------------------------ */

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_create_saved_searches_table' ] );
        add_action( 'admin_init', [ __CLASS__, 'maybe_add_fulltext_index' ] );

        add_action( 'wp_ajax_aura_advanced_search',        [ __CLASS__, 'ajax_search' ] );
        add_action( 'wp_ajax_aura_save_search',            [ __CLASS__, 'ajax_save_search' ] );
        add_action( 'wp_ajax_aura_get_saved_searches',     [ __CLASS__, 'ajax_get_saved_searches' ] );
        add_action( 'wp_ajax_aura_delete_saved_search',    [ __CLASS__, 'ajax_delete_saved_search' ] );
    }

    /* ------------------------------------------------------------------
     * Crear índice FULLTEXT en la tabla de transacciones (si no existe)
     * Se usa para MATCH AGAINST en búsquedas de texto libre.
     * ------------------------------------------------------------------ */

    public static function maybe_add_fulltext_index(): void {
        if ( get_option( 'aura_finance_fulltext_index_v1' ) ) {
            return;
        }
        global $wpdb;
        $table   = $wpdb->prefix . 'aura_finance_transactions';
        $indexes = $wpdb->get_col( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'ft_aura_search'" );
        if ( empty( $indexes ) ) {
            // Usamos dbDelta-safe ALTER; InnoDB soporta FULLTEXT desde MySQL 5.6
            $wpdb->query(
                "ALTER TABLE `{$table}` ADD FULLTEXT INDEX `ft_aura_search` (description, notes, tags)"
            );
        }
        update_option( 'aura_finance_fulltext_index_v1', true );
    }

    /* ------------------------------------------------------------------
     * Crear tabla de búsquedas guardadas
     * ------------------------------------------------------------------ */

    public static function maybe_create_saved_searches_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_saved_searches';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) return;

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(200) NOT NULL,
            filters TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ------------------------------------------------------------------
     * AJAX: Búsqueda avanzada
     * ------------------------------------------------------------------ */

    public static function ajax_search() {
        check_ajax_referer( 'aura_search_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_view_own' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        global $wpdb;
        $tx = $wpdb->prefix . 'aura_finance_transactions';
        $ct = $wpdb->prefix . 'aura_finance_categories';

        // — Parámetros —
        $text         = sanitize_text_field( $_POST['text']          ?? '' );
        $search_in    = array_map( 'sanitize_key', (array) ( $_POST['search_in'] ?? [ 'description', 'notes' ] ) );
        $date_from    = sanitize_text_field( $_POST['date_from']     ?? '' );
        $date_to      = sanitize_text_field( $_POST['date_to']       ?? '' );
        $types        = array_map( 'sanitize_key', (array) ( $_POST['types']     ?? [] ) );
        $categories   = array_map( 'absint',         (array) ( $_POST['categories'] ?? [] ) );
        $statuses     = array_map( 'sanitize_key',   (array) ( $_POST['statuses']   ?? [] ) );
        $amount_min   = strlen( $_POST['amount_min'] ?? '' ) ? floatval( $_POST['amount_min'] ) : null;
        $amount_max   = strlen( $_POST['amount_max'] ?? '' ) ? floatval( $_POST['amount_max'] ) : null;
        $methods      = array_map( 'sanitize_text_field', (array) ( $_POST['methods'] ?? [] ) );
        $tags_filter  = array_map( 'sanitize_text_field', (array) ( $_POST['tags']    ?? [] ) );
        $created_by   = absint( $_POST['created_by'] ?? 0 );
        $has_receipt  = sanitize_key( $_POST['has_receipt'] ?? '' ); // 'yes','no',''
        $page         = max( 1, absint( $_POST['page'] ?? 1 ) );
        $per_page     = 25;

        // Solo puede ver todas si tiene permiso
        $restrict_user = ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' );

        // — Construir WHERE —
        $where  = [ "t.deleted_at IS NULL" ];
        $params = [];

        if ( $restrict_user ) {
            $where[]  = "t.created_by = %d";
            $params[] = get_current_user_id();
        }

        // Texto libre — operadores: "frase", AND, OR, -excluir, campo:valor, monto:>X
        if ( $text !== '' ) {
            $text_conditions = self::parse_text_query( $text, $search_in, $tx );
            if ( $text_conditions['sql'] ) {
                $where[]  = $text_conditions['sql'];
                $params   = array_merge( $params, $text_conditions['params'] );
            }
        }

        if ( $date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
            $where[]  = "t.transaction_date >= %s";
            $params[] = $date_from;
        }
        if ( $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
            $where[]  = "t.transaction_date <= %s";
            $params[] = $date_to;
        }
        if ( ! empty( $types ) ) {
            $valid = array_intersect( $types, [ 'income', 'expense' ] );
            if ( $valid ) {
                $in       = implode( ',', array_fill( 0, count( $valid ), '%s' ) );
                $where[]  = "t.transaction_type IN ({$in})";
                $params   = array_merge( $params, $valid );
            }
        }
        if ( ! empty( $categories ) ) {
            $in       = implode( ',', array_fill( 0, count( $categories ), '%d' ) );
            $where[]  = "t.category_id IN ({$in})";
            $params   = array_merge( $params, $categories );
        }
        if ( ! empty( $statuses ) ) {
            $valid = array_intersect( $statuses, [ 'pending', 'approved', 'rejected' ] );
            if ( $valid ) {
                $in       = implode( ',', array_fill( 0, count( $valid ), '%s' ) );
                $where[]  = "t.status IN ({$in})";
                $params   = array_merge( $params, $valid );
            }
        }
        if ( $amount_min !== null ) {
            $where[]  = "t.amount >= %f";
            $params[] = $amount_min;
        }
        if ( $amount_max !== null ) {
            $where[]  = "t.amount <= %f";
            $params[] = $amount_max;
        }
        if ( ! empty( $methods ) ) {
            $in       = implode( ',', array_fill( 0, count( $methods ), '%s' ) );
            $where[]  = "t.payment_method IN ({$in})";
            $params   = array_merge( $params, $methods );
        }
        if ( ! empty( $tags_filter ) ) {
            $tag_parts = [];
            foreach ( $tags_filter as $tag ) {
                $tag_parts[] = "FIND_IN_SET(%s, REPLACE(t.tags, ', ', ',')) > 0";
                $params[]    = $tag;
            }
            $where[] = '(' . implode( ' OR ', $tag_parts ) . ')';
        }
        if ( $created_by > 0 ) {
            $where[]  = "t.created_by = %d";
            $params[] = $created_by;
        }
        if ( $has_receipt === 'yes' ) {
            $where[] = "(t.receipt_file IS NOT NULL AND t.receipt_file != '')";
        } elseif ( $has_receipt === 'no' ) {
            $where[] = "(t.receipt_file IS NULL OR t.receipt_file = '')";
        }

        $where_sql = implode( ' AND ', $where );

        // — Contar total —
        $count_sql    = empty( $params )
            ? "SELECT COUNT(*) FROM `{$tx}` t WHERE {$where_sql}"
            : $wpdb->prepare( "SELECT COUNT(*) FROM `{$tx}` t WHERE {$where_sql}", $params );
        $total        = (int) $wpdb->get_var( $count_sql );

        // — SUM total —
        $sum_sql    = empty( $params )
            ? "SELECT COALESCE(SUM(t.amount), 0) FROM `{$tx}` t WHERE {$where_sql}"
            : $wpdb->prepare( "SELECT COALESCE(SUM(t.amount), 0) FROM `{$tx}` t WHERE {$where_sql}", $params );
        $total_amount = (float) $wpdb->get_var( $sum_sql );

        // — Resultados paginados —
        $offset     = ( $page - 1 ) * $per_page;
        $result_sql = empty( $params )
            ? "SELECT t.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
                      u.display_name AS created_by_name
               FROM `{$tx}` t
               LEFT JOIN `{$ct}` c ON c.id = t.category_id
               LEFT JOIN `{$wpdb->users}` u ON u.ID = t.created_by
               WHERE {$where_sql}
               ORDER BY t.transaction_date DESC, t.id DESC
               LIMIT {$per_page} OFFSET {$offset}"
            : $wpdb->prepare(
                "SELECT t.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
                        u.display_name AS created_by_name
                 FROM `{$tx}` t
                 LEFT JOIN `{$ct}` c ON c.id = t.category_id
                 LEFT JOIN `{$wpdb->users}` u ON u.ID = t.created_by
                 WHERE {$where_sql}
                 ORDER BY t.transaction_date DESC, t.id DESC
                 LIMIT {$per_page} OFFSET {$offset}",
                $params
            );

        $results = $wpdb->get_results( $result_sql );

        // Resaltar texto en resultados
        if ( $text !== '' ) {
            $highlight = self::extract_highlight_terms( $text );
            foreach ( $results as &$row ) {
                $row->description_hl = self::highlight( $row->description, $highlight );
                $row->notes_hl       = $row->notes ? self::highlight( $row->notes, $highlight ) : '';
            }
            unset( $row );
        }

        wp_send_json_success( [
            'results'      => $results,
            'total'        => $total,
            'total_amount' => $total_amount,
            'pages'        => (int) ceil( $total / $per_page ),
            'page'         => $page,
            'per_page'     => $per_page,
        ] );
    }

    /* ------------------------------------------------------------------
     * Parsear consulta de texto con operadores avanzados:
     *   • "frase exacta"   → LIKE literal
     *   • AND / OR         → operadores booleanos entre términos
     *   • -exclusión       → NOT LIKE
     *   • campo:valor      → filtros de campo:
     *                          tipo, estado, metodo, importe(>,<,=),
     *                          categoria, referencia
     *   Términos restantes → MATCH AGAINST si FULLTEXT disponible, si no LIKE
     * ------------------------------------------------------------------ */

    private static function parse_text_query( string $text, array $search_in, string $table ): array {
        global $wpdb;
        $ct = $wpdb->prefix . 'aura_finance_categories';

        $valid_cols = [ 'description', 'notes', 'reference_number', 'recipient_payer', 'tags' ];
        $cols       = array_intersect( $search_in, $valid_cols );
        if ( empty( $cols ) ) $cols = [ 'description', 'notes' ];

        $conditions = [];
        $params     = [];

        /* -------- 1. Extraer campo:valor ------------------------------- */
        $field_map = [
            'tipo'      => 'tipo',  'type'      => 'tipo',
            'estado'    => 'estado','status'    => 'estado',
            'metodo'    => 'metodo','method'    => 'metodo',  'pago' => 'metodo',
            'importe'   => 'importe','monto'    => 'importe', 'amount' => 'importe',
            'categoria' => 'categoria','category' => 'categoria',
            'referencia'=> 'referencia','ref'   => 'referencia',
            // Fase 8.2
            'area'      => 'area', 'programa' => 'area',
        ];

        $text = preg_replace_callback(
            '/\b(\w+):([^\s"]+|"[^"]+")/u',
            function ( $m ) use ( &$conditions, &$params, $field_map, $wpdb, $ct ) {
                $key   = strtolower( $m[1] );
                $val   = trim( $m[2], '"' );
                $canon = $field_map[ $key ] ?? null;
                if ( ! $canon ) return $m[0]; // no reconocido: dejar en texto

                switch ( $canon ) {

                    case 'tipo':
                        $map = [
                            'ingreso' => 'income',  'egreso'  => 'expense',
                            'gasto'   => 'expense', 'income'  => 'income',
                            'expense' => 'expense',
                        ];
                        if ( $db_val = $map[ strtolower( $val ) ] ?? null ) {
                            $conditions[] = 't.transaction_type = %s';
                            $params[]     = $db_val;
                        }
                        break;

                    case 'estado':
                        $map = [
                            'pendiente' => 'pending',  'aprobado'  => 'approved',
                            'rechazado' => 'rejected', 'pending'   => 'pending',
                            'approved'  => 'approved', 'rejected'  => 'rejected',
                        ];
                        if ( $db_val = $map[ strtolower( $val ) ] ?? null ) {
                            $conditions[] = 't.status = %s';
                            $params[]     = $db_val;
                        }
                        break;

                    case 'metodo':
                        $map = [
                            'efectivo'      => 'cash',     'cash'     => 'cash',
                            'transferencia' => 'transfer', 'transfer' => 'transfer',
                            'tarjeta'       => 'card',     'card'     => 'card',
                            'cheque'        => 'check',    'check'    => 'check',
                            'otro'          => 'other',    'other'    => 'other',
                        ];
                        if ( $db_val = $map[ strtolower( $val ) ] ?? null ) {
                            $conditions[] = 't.payment_method = %s';
                            $params[]     = $db_val;
                        }
                        break;

                    case 'importe':
                        // Acepta: >500  <500  >=500  <=500  500
                        if ( preg_match( '/^(>=|<=|>|<|=)?(\d+(?:\.\d+)?)$/', $val, $op ) ) {
                            $operator  = $op[1] ?: '=';
                            $amount    = (float) $op[2];
                            $valid_ops = [ '>', '<', '>=', '<=', '=' ];
                            if ( in_array( $operator, $valid_ops, true ) ) {
                                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                                $conditions[] = "t.amount {$operator} %f";
                                $params[]     = $amount;
                            }
                        }
                        break;

                    case 'categoria':
                        $row = $wpdb->get_row( $wpdb->prepare(
                            "SELECT id FROM `{$ct}` WHERE name LIKE %s AND is_active = 1 LIMIT 1",
                            '%' . $wpdb->esc_like( $val ) . '%'
                        ) );
                        if ( $row ) {
                            $conditions[] = 't.category_id = %d';
                            $params[]     = (int) $row->id;
                        }
                        break;

                    case 'referencia':
                        $conditions[] = 't.reference_number LIKE %s';
                        $params[]     = '%' . $wpdb->esc_like( $val ) . '%';
                        break;

                    // Fase 8.2: operador area:nombre / programa:nombre
                    case 'area':
                        $at = $wpdb->prefix . 'aura_areas';
                        $area_row = $wpdb->get_row( $wpdb->prepare(
                            "SELECT id FROM `{$at}` WHERE name LIKE %s AND status = 'active' LIMIT 1",
                            '%' . $wpdb->esc_like( $val ) . '%'
                        ) );
                        if ( $area_row ) {
                            $conditions[] = 't.area_id = %d';
                            $params[]     = (int) $area_row->id;
                        }
                        break;
                }
                return ''; // retirar el token del texto libre
            },
            $text
        );
        $text = trim( $text );

        /* -------- 2. Extraer negativos: -palabra ----------------------- */
        $negatives = [];
        $text = preg_replace_callback( '/-(\S+)/', function ( $m ) use ( &$negatives ) {
            $negatives[] = $m[1];
            return '';
        }, $text );

        /* -------- 3. Extraer frases exactas: "frase" ------------------- */
        $phrases = [];
        $text = preg_replace_callback( '/"([^"]+)"/', function ( $m ) use ( &$phrases ) {
            $phrases[] = $m[1];
            return '';
        }, $text );

        $text = trim( $text );

        // Frases exactas → LIKE
        foreach ( $phrases as $phrase ) {
            $col_conds = [];
            foreach ( $cols as $col ) {
                $col_conds[] = "t.{$col} LIKE %s";
                $params[]    = '%' . $wpdb->esc_like( $phrase ) . '%';
            }
            $conditions[] = '(' . implode( ' OR ', $col_conds ) . ')';
        }

        /* -------- 4. Términos libres con AND/OR ------------------------ */
        if ( $text !== '' ) {
            $ft_table = $wpdb->prefix . 'aura_finance_transactions';
            $has_ft   = (bool) $wpdb->get_row(
                "SHOW INDEX FROM `{$ft_table}` WHERE Key_name = 'ft_aura_search'"
            );
            $ft_cols  = array_intersect( $cols, [ 'description', 'notes', 'tags' ] );

            $tokens      = preg_split( '/\s+(AND|OR)\s+/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
            $operator    = 'AND';
            $token_conds = [];

            foreach ( $tokens as $tok ) {
                $tok = trim( $tok );
                if ( strtoupper( $tok ) === 'AND' ) { $operator = 'AND'; continue; }
                if ( strtoupper( $tok ) === 'OR'  ) { $operator = 'OR';  continue; }
                if ( $tok === '' ) continue;

                if ( $has_ft && ! empty( $ft_cols ) ) {
                    $ft_cols_sql  = implode( ', ', array_map( fn( $c ) => "t.{$c}", $ft_cols ) );
                    $escaped_tok  = preg_replace( '/[+\-~<>()*"@]/', '', $tok );
                    // MATCH AGAINST + fallback LIKE para garantizar resultados
                    $token_conds[] = $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        "(MATCH({$ft_cols_sql}) AGAINST (%s IN BOOLEAN MODE) OR t.description LIKE %s)",
                        '+' . $escaped_tok . '*',
                        '%' . $wpdb->esc_like( $tok ) . '%'
                    );
                } else {
                    $col_conds = [];
                    foreach ( $cols as $col ) {
                        $col_conds[] = "t.{$col} LIKE %s";
                        $params[]    = '%' . $wpdb->esc_like( $tok ) . '%';
                    }
                    $token_conds[] = '(' . implode( ' OR ', $col_conds ) . ')';
                }
            }

            if ( ! empty( $token_conds ) ) {
                $conditions[] = '(' . implode( " {$operator} ", $token_conds ) . ')';
            }
        }

        /* -------- 5. Negativos → NOT LIKE ------------------------------ */
        foreach ( $negatives as $neg ) {
            foreach ( $cols as $col ) {
                $conditions[] = "t.{$col} NOT LIKE %s";
                $params[]     = '%' . $wpdb->esc_like( $neg ) . '%';
            }
        }

        return [
            'sql'    => empty( $conditions ) ? '' : '(' . implode( ' AND ', $conditions ) . ')',
            'params' => $params,
        ];
    }

    /* ------------------------------------------------------------------
     * Extraer términos para highlight
     * ------------------------------------------------------------------ */

    private static function extract_highlight_terms( $text ) {
        // Quitar operadores y separadores
        $text  = preg_replace( '/\b(AND|OR)\b/i', ' ', $text );
        $text  = preg_replace( '/[-"]/', ' ', $text );
        $terms = array_filter( array_map( 'trim', explode( ' ', $text ) ) );
        return array_unique( $terms );
    }

    /* ------------------------------------------------------------------
     * Resaltar términos en un string
     * ------------------------------------------------------------------ */

    private static function highlight( $text, array $terms ) {
        if ( empty( $terms ) || $text === null ) return esc_html( $text );
        $escaped = esc_html( $text );
        foreach ( $terms as $term ) {
            $escaped = preg_replace(
                '/(' . preg_quote( esc_html( $term ), '/' ) . ')/iu',
                '<mark class="aura-highlight">$1</mark>',
                $escaped
            );
        }
        return $escaped;
    }

    /* ------------------------------------------------------------------
     * AJAX: Guardar búsqueda
     * ------------------------------------------------------------------ */

    public static function ajax_save_search() {
        check_ajax_referer( 'aura_search_nonce', 'nonce' );

        $name    = sanitize_text_field( $_POST['name']    ?? '' );
        $filters = wp_unslash( $_POST['filters'] ?? '{}' );

        if ( $name === '' ) wp_send_json_error( [ 'message' => __( 'El nombre es requerido', 'aura-suite' ) ] );

        // Validar JSON
        $decoded = json_decode( $filters, true );
        if ( ! is_array( $decoded ) ) wp_send_json_error( [ 'message' => __( 'Filtros inválidos', 'aura-suite' ) ] );

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_saved_searches';

        $wpdb->insert( $table, [
            'user_id'    => get_current_user_id(),
            'name'       => $name,
            'filters'    => wp_json_encode( $decoded ),
            'created_at' => current_time( 'mysql' ),
        ] );

        wp_send_json_success( [
            'id'      => $wpdb->insert_id,
            'message' => __( 'Búsqueda guardada correctamente.', 'aura-suite' ),
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Obtener búsquedas guardadas del usuario
     * ------------------------------------------------------------------ */

    public static function ajax_get_saved_searches() {
        check_ajax_referer( 'aura_search_nonce', 'nonce' );

        global $wpdb;
        $table   = $wpdb->prefix . 'aura_finance_saved_searches';
        $user_id = get_current_user_id();

        $searches = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name, filters, created_at FROM `{$table}` WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );

        foreach ( $searches as &$s ) {
            $s->filters = json_decode( $s->filters, true );
        }
        unset( $s );

        wp_send_json_success( [ 'searches' => $searches ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Eliminar búsqueda guardada
     * ------------------------------------------------------------------ */

    public static function ajax_delete_saved_search() {
        check_ajax_referer( 'aura_search_nonce', 'nonce' );

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( [ 'message' => __( 'ID inválido', 'aura-suite' ) ] );

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_saved_searches';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM `{$table}` WHERE id = %d", $id ) );
        if ( ! $row ) wp_send_json_error( [ 'message' => __( 'No encontrado', 'aura-suite' ) ] );

        // Solo el dueño o admin puede eliminar
        if ( (int) $row->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $wpdb->delete( $table, [ 'id' => $id ] );
        wp_send_json_success( [ 'message' => __( 'Búsqueda eliminada.', 'aura-suite' ) ] );
    }

    /* ------------------------------------------------------------------
     * Render página de búsqueda avanzada
     * ------------------------------------------------------------------ */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/search-page.php';
    }
}
