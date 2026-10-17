<?php
/**
 * Gestión de Etiquetas (Tags) — Fase 5, Item 5.2
 *
 * Módulo CRUD robusto de etiquetas financieras.
 * Almacena metadatos (color, descripción, fechas) en wp_aura_finance_tags
 * y mantiene sincronización bidireccional e integridad con los valores CSV
 * en wp_aura_finance_transactions.tags.
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Financial_Tags {

    /**
     * Paleta predeterminada para asignación armónica de colores a etiquetas
     */
    const DEFAULT_COLORS = [
        '#3b82f6', // Azul primario
        '#10b981', // Esmeralda
        '#6366f1', // Índigo
        '#8b5cf6', // Violeta
        '#ec4899', // Rosa
        '#f59e0b', // Ámbar
        '#06b6d4', // Cian
        '#14b8a6', // Teal
        '#f97316', // Naranja
        '#64748b', // Slate
    ];

    /* ------------------------------------------------------------------
     * Inicialización de Hooks y Endpoints
     * ------------------------------------------------------------------ */

    public static function init() {
        add_action( 'admin_init',  [ __CLASS__, 'maybe_create_tags_table' ] );
        add_action( 'admin_init',  [ __CLASS__, 'maybe_add_fulltext_index' ] );

        // AJAX endpoints
        add_action( 'wp_ajax_aura_tags_autocomplete',        [ __CLASS__, 'ajax_autocomplete' ] );
        add_action( 'wp_ajax_aura_tags_get_all',             [ __CLASS__, 'ajax_get_all' ] );
        add_action( 'wp_ajax_aura_tags_create',              [ __CLASS__, 'ajax_create' ] );
        add_action( 'wp_ajax_aura_tags_update',              [ __CLASS__, 'ajax_update' ] );
        add_action( 'wp_ajax_aura_tags_rename',              [ __CLASS__, 'ajax_rename' ] );
        add_action( 'wp_ajax_aura_tags_merge',               [ __CLASS__, 'ajax_merge' ] );
        add_action( 'wp_ajax_aura_tags_bulk_merge',          [ __CLASS__, 'ajax_bulk_merge' ] );
        add_action( 'wp_ajax_aura_tags_delete',              [ __CLASS__, 'ajax_delete' ] );
        add_action( 'wp_ajax_aura_tags_bulk_delete',         [ __CLASS__, 'ajax_bulk_delete' ] );
        add_action( 'wp_ajax_aura_tags_cloud',               [ __CLASS__, 'ajax_cloud' ] );
        add_action( 'wp_ajax_aura_tags_get_transactions',    [ __CLASS__, 'ajax_get_transactions' ] );
    }

    /**
     * Nombre de la tabla de catálogo de etiquetas
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'aura_finance_tags';
    }

    /**
     * Crear tabla de catálogo de etiquetas si no existe
     */
    public static function maybe_create_tags_table() {
        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE `{$table}` (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            color varchar(20) DEFAULT '#3b82f6',
            description text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uk_name (name),
            KEY idx_slug (slug)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * FULLTEXT index en columnas de búsqueda de transacciones
     */
    public static function maybe_add_fulltext_index() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Validar existencia de la tabla antes de comprobar índices
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( ! $table_exists ) {
            return;
        }

        $idx = $wpdb->get_var( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'ft_search'" );
        if ( ! $idx ) {
            $wpdb->query(
                "ALTER TABLE `{$table}` ADD FULLTEXT INDEX `ft_search`
                 (`description`, `notes`, `reference_number`, `recipient_payer`, `tags`)"
            );
        }
    }

    /* ------------------------------------------------------------------
     * Helpers de Formato y Normalización
     * ------------------------------------------------------------------ */

    public static function normalize( $tag ) {
        $tag = trim( $tag );
        $tag = ltrim( $tag, '#' );
        return mb_strtolower( trim( $tag ) );
    }

    public static function slugify( $tag ) {
        return sanitize_title( self::normalize( $tag ) );
    }

    /**
     * Sincronizar etiquetas de transacciones hacia la tabla de catálogo
     */
    public static function sync_catalog_from_transactions() {
        global $wpdb;
        $t_tags = self::get_table_name();
        $t_txns = $wpdb->prefix . 'aura_finance_transactions';

        $txn_tags_raw = $wpdb->get_col(
            "SELECT tags FROM `{$t_txns}`
             WHERE tags IS NOT NULL AND tags != '' AND deleted_at IS NULL"
        );

        if ( empty( $txn_tags_raw ) ) {
            return;
        }

        $existing_catalog = $wpdb->get_col( "SELECT name FROM `{$t_tags}`" );
        $catalog_map = array_flip( array_map( [ __CLASS__, 'normalize' ], $existing_catalog ) );

        $new_tags = [];
        foreach ( $txn_tags_raw as $raw_row ) {
            $parts = array_filter( array_map( 'trim', explode( ',', $raw_row ) ) );
            foreach ( $parts as $p ) {
                $norm = self::normalize( $p );
                if ( $norm === '' || isset( $catalog_map[ $norm ] ) || isset( $new_tags[ $norm ] ) ) {
                    continue;
                }
                $new_tags[ $norm ] = true;
            }
        }

        if ( empty( $new_tags ) ) {
            return;
        }

        $colors = self::DEFAULT_COLORS;
        $color_count = count( $colors );
        $idx = 0;

        foreach ( array_keys( $new_tags ) as $tag_name ) {
            $color = $colors[ $idx % $color_count ];
            $wpdb->insert(
                $t_tags,
                [
                    'name'        => $tag_name,
                    'slug'        => self::slugify( $tag_name ),
                    'color'       => $color,
                    'description' => '',
                    'created_by'  => get_current_user_id() ?: null,
                    'created_at'  => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%d', '%s' ]
            );
            $idx++;
        }
    }

    /* ------------------------------------------------------------------
     * Obtener Tags y Estadísticas Contables
     * ------------------------------------------------------------------ */

    /**
     * Obtener todos los tags únicos con su conteo de usos (usado en selects/chips)
     *
     * @return array [ 'nombre_tag' => cantidad_usos ]
     */
    public static function get_all_tags() {
        global $wpdb;
        $t_tags = self::get_table_name();
        $t_txns = $wpdb->prefix . 'aura_finance_transactions';

        $counts = [];

        // 1. Obtener todas las etiquetas registradas en catálogo (base inicial con 0)
        $catalog_tags = $wpdb->get_results( "SELECT name FROM `{$t_tags}` ORDER BY name ASC" );
        if ( ! empty( $catalog_tags ) ) {
            foreach ( $catalog_tags as $ct ) {
                $norm = self::normalize( $ct->name );
                if ( $norm !== '' ) {
                    $counts[ $norm ] = 0;
                }
            }
        }

        // 2. Sumar usos reales en transacciones activas
        $rows = $wpdb->get_col(
            "SELECT tags FROM `{$t_txns}`
             WHERE tags IS NOT NULL AND tags != '' AND deleted_at IS NULL"
        );

        foreach ( $rows as $row ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', $row ) ) ) as $tag ) {
                $t = self::normalize( $tag );
                if ( $t === '' ) continue;
                $counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
            }
        }

        arsort( $counts );
        return $counts;
    }

    /**
     * Obtener desglose contable, metadatos y estadísticas completas por etiqueta
     *
     * @return array
     */
    public static function get_all_tags_details() {
        global $wpdb;
        $t_tags = self::get_table_name();
        $t_txns = $wpdb->prefix . 'aura_finance_transactions';

        // Sincronizar catálogo primero para incluir etiquetas huérfanas en transacciones
        self::sync_catalog_from_transactions();

        // 1. Cargar catálogo con metadatos
        $catalog_rows = $wpdb->get_results(
            "SELECT id, name, slug, color, description, created_at, updated_at FROM `{$t_tags}`"
        );

        $tags_data = [];
        $color_palette = self::DEFAULT_COLORS;

        if ( ! empty( $catalog_rows ) ) {
            foreach ( $catalog_rows as $i => $row ) {
                $norm = self::normalize( $row->name );
                if ( $norm === '' ) continue;

                $tags_data[ $norm ] = [
                    'id'             => (int) $row->id,
                    'name'           => $norm,
                    'slug'           => $row->slug ?: self::slugify( $norm ),
                    'color'          => $row->color ?: $color_palette[ $i % count( $color_palette ) ],
                    'description'    => $row->description ?: '',
                    'count'          => 0,
                    'income_count'   => 0,
                    'expense_count'  => 0,
                    'income_amount'  => 0.0,
                    'expense_amount' => 0.0,
                    'total_amount'   => 0.0,
                    'net_balance'    => 0.0,
                    'first_date'     => '',
                    'latest_date'    => '',
                    'created_at'     => $row->created_at ?: '',
                ];
            }
        }

        // 2. Analizar transacciones contables
        $txn_rows = $wpdb->get_results(
            "SELECT tags, transaction_type, amount, transaction_date FROM `{$t_txns}`
             WHERE tags IS NOT NULL AND tags != '' AND deleted_at IS NULL
             ORDER BY transaction_date DESC"
        );

        if ( ! empty( $txn_rows ) ) {
            foreach ( $txn_rows as $row ) {
                $parsed_tags = array_filter( array_map( 'trim', explode( ',', $row->tags ) ) );
                $amt  = floatval( $row->amount ?? 0 );
                $type = strtolower( trim( $row->transaction_type ?? '' ) );
                $date = ! empty( $row->transaction_date ) ? $row->transaction_date : '';

                foreach ( $parsed_tags as $raw_tag ) {
                    $t = self::normalize( $raw_tag );
                    if ( $t === '' ) continue;

                    if ( ! isset( $tags_data[ $t ] ) ) {
                        $tags_data[ $t ] = [
                            'id'             => 0,
                            'name'           => $t,
                            'slug'           => self::slugify( $t ),
                            'color'          => '#3b82f6',
                            'description'    => '',
                            'count'          => 0,
                            'income_count'   => 0,
                            'expense_count'  => 0,
                            'income_amount'  => 0.0,
                            'expense_amount' => 0.0,
                            'total_amount'   => 0.0,
                            'net_balance'    => 0.0,
                            'first_date'     => $date,
                            'latest_date'    => $date,
                            'created_at'     => '',
                        ];
                    }

                    $tags_data[ $t ]['count']++;
                    if ( $type === 'income' ) {
                        $tags_data[ $t ]['income_count']++;
                        $tags_data[ $t ]['income_amount'] += $amt;
                    } else {
                        $tags_data[ $t ]['expense_count']++;
                        $tags_data[ $t ]['expense_amount'] += $amt;
                    }

                    $tags_data[ $t ]['total_amount'] += $amt;
                    $tags_data[ $t ]['net_balance'] = $tags_data[ $t ]['income_amount'] - $tags_data[ $t ]['expense_amount'];

                    if ( ! empty( $date ) ) {
                        if ( empty( $tags_data[ $t ]['latest_date'] ) || $date > $tags_data[ $t ]['latest_date'] ) {
                            $tags_data[ $t ]['latest_date'] = $date;
                        }
                        if ( empty( $tags_data[ $t ]['first_date'] ) || $date < $tags_data[ $t ]['first_date'] ) {
                            $tags_data[ $t ]['first_date'] = $date;
                        }
                    }
                }
            }
        }

        // Ordenar por cantidad de usos descendente
        uasort( $tags_data, function( $a, $b ) {
            if ( $b['count'] === $a['count'] ) {
                return strcasecmp( $a['name'], $b['name'] );
            }
            return $b['count'] <=> $a['count'];
        } );

        return array_values( $tags_data );
    }

    /* ------------------------------------------------------------------
     * Verificación de Permisos
     * ------------------------------------------------------------------ */

    private static function check_read_permission() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_view_all' ) &&
             ! current_user_can( 'aura_finance_tags' ) &&
             ! current_user_can( 'manage_options' ) &&
             ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes para consultar etiquetas.', 'aura-suite' ) ], 403 );
        }
    }

    private static function check_write_permission() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_edit_all' ) &&
             ! current_user_can( 'aura_finance_tags' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes para modificar etiquetas.', 'aura-suite' ) ], 403 );
        }
    }

    private static function check_delete_permission() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_delete_all' ) &&
             ! current_user_can( 'aura_finance_tags' ) &&
             ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos suficientes para eliminar etiquetas.', 'aura-suite' ) ], 403 );
        }
    }

    /* ------------------------------------------------------------------
     * AJAX: Autocompletar tags
     * ------------------------------------------------------------------ */

    public static function ajax_autocomplete() {
        self::check_read_permission();

        $term = self::normalize( sanitize_text_field( $_GET['term'] ?? '' ) );
        if ( strlen( $term ) < 1 ) {
            wp_send_json_success( [] );
        }

        $all  = self::get_all_tags();
        $matches = [];
        foreach ( $all as $tag => $count ) {
            if ( str_contains( $tag, $term ) ) {
                $matches[] = [
                    'label' => '#' . $tag . ' (' . $count . ')',
                    'value' => $tag,
                ];
            }
            if ( count( $matches ) >= 15 ) {
                break;
            }
        }

        wp_send_json_success( $matches );
    }

    /* ------------------------------------------------------------------
     * AJAX: Listar todos los tags con desglose y conteos
     * ------------------------------------------------------------------ */

    public static function ajax_get_all() {
        self::check_read_permission();

        $currency = get_option( 'aura_finance_currency', 'USD' );
        $tags_details = self::get_all_tags_details();

        wp_send_json_success( [
            'tags'     => $tags_details,
            'currency' => $currency,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Crear Etiqueta en Catálogo (CREATE)
     * ------------------------------------------------------------------ */

    public static function ajax_create() {
        self::check_write_permission();
        global $wpdb;

        $name = self::normalize( sanitize_text_field( $_POST['name'] ?? '' ) );
        $color = sanitize_hex_color( sanitize_text_field( $_POST['color'] ?? '' ) ) ?: '#3b82f6';
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );

        if ( $name === '' ) {
            wp_send_json_error( [ 'message' => __( 'El nombre de la etiqueta no puede estar vacío.', 'aura-suite' ) ] );
        }

        if ( strpos( $name, ',' ) !== false ) {
            wp_send_json_error( [ 'message' => __( 'El nombre de la etiqueta no puede contener comas.', 'aura-suite' ) ] );
        }

        $t_tags = self::get_table_name();

        // Verificar si ya existe en catálogo
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t_tags}` WHERE name = %s", $name ) );
        if ( $existing ) {
            wp_send_json_error( [ 'message' => sprintf( __( 'La etiqueta "%s" ya existe en el catálogo.', 'aura-suite' ), $name ) ] );
        }

        $inserted = $wpdb->insert(
            $t_tags,
            [
                'name'        => $name,
                'slug'        => self::slugify( $name ),
                'color'       => $color,
                'description' => $description,
                'created_by'  => get_current_user_id() ?: null,
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( false === $inserted ) {
            wp_send_json_error( [ 'message' => __( 'Error al guardar la etiqueta en la base de datos.', 'aura-suite' ) ] );
        }

        $new_id = $wpdb->insert_id;

        wp_send_json_success( [
            'message' => sprintf( __( 'Etiqueta "#%s" creada exitosamente.', 'aura-suite' ), $name ),
            'tag'     => [
                'id'          => $new_id,
                'name'        => $name,
                'color'       => $color,
                'description' => $description,
            ],
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Actualizar Etiqueta (UPDATE: Nombre, Color, Descripción)
     * ------------------------------------------------------------------ */

    public static function ajax_update() {
        self::check_write_permission();
        global $wpdb;

        $id                  = (int) ( $_POST['id'] ?? 0 );
        $old_name            = self::normalize( sanitize_text_field( $_POST['old_name'] ?? '' ) );
        $new_name            = self::normalize( sanitize_text_field( $_POST['new_name'] ?? '' ) );
        $color               = sanitize_hex_color( sanitize_text_field( $_POST['color'] ?? '' ) ) ?: '#3b82f6';
        $description         = sanitize_textarea_field( $_POST['description'] ?? '' );
        $update_transactions = ! empty( $_POST['update_transactions'] ) && $_POST['update_transactions'] !== 'false';

        if ( $old_name === '' && $id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Etiqueta a editar no especificada.', 'aura-suite' ) ] );
        }

        $t_tags = self::get_table_name();

        // Localizar registro en catálogo
        if ( $id > 0 ) {
            $tag_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t_tags}` WHERE id = %d", $id ) );
            if ( $tag_row ) {
                $old_name = self::normalize( $tag_row->name );
            }
        } else {
            $tag_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t_tags}` WHERE name = %s", $old_name ) );
        }

        if ( $new_name === '' ) {
            $new_name = $old_name;
        }

        if ( strpos( $new_name, ',' ) !== false ) {
            wp_send_json_error( [ 'message' => __( 'El nuevo nombre no puede contener comas.', 'aura-suite' ) ] );
        }

        // Si cambió el nombre, validar que no colisione con otro
        if ( $old_name !== $new_name ) {
            $collision = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t_tags}` WHERE name = %s AND name != %s", $new_name, $old_name ) );
            if ( $collision ) {
                wp_send_json_error( [ 'message' => sprintf( __( 'Ya existe otra etiqueta registrada con el nombre "%s". Si deseas unificarlas, utiliza la opción Fusionar.', 'aura-suite' ), $new_name ) ] );
            }
        }

        // Actualizar o crear en catálogo
        if ( $tag_row ) {
            $wpdb->update(
                $t_tags,
                [
                    'name'        => $new_name,
                    'slug'        => self::slugify( $new_name ),
                    'color'       => $color,
                    'description' => $description,
                    'updated_at'  => current_time( 'mysql' ),
                ],
                [ 'id' => $tag_row->id ],
                [ '%s', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $t_tags,
                [
                    'name'        => $new_name,
                    'slug'        => self::slugify( $new_name ),
                    'color'       => $color,
                    'description' => $description,
                    'created_by'  => get_current_user_id() ?: null,
                    'created_at'  => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%d', '%s' ]
            );
        }

        // Si cambió el nombre y se solicitó actualizar transacciones
        $affected_txns = 0;
        if ( $old_name !== $new_name && $update_transactions ) {
            $affected_txns = self::replace_tag_in_all( $old_name, $new_name );
        }

        wp_send_json_success( [
            'message'       => sprintf( __( 'Etiqueta "#%s" actualizada correctamente (%d transacciones sincronizadas).', 'aura-suite' ), $new_name, $affected_txns ),
            'name'          => $new_name,
            'color'         => $color,
            'description'   => $description,
            'affected_txns' => $affected_txns,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Renombrar tag (Retrocompatibilidad)
     * ------------------------------------------------------------------ */

    public static function ajax_rename() {
        self::check_write_permission();
        global $wpdb;

        $old = self::normalize( sanitize_text_field( $_POST['old_name'] ?? '' ) );
        $new = self::normalize( sanitize_text_field( $_POST['new_name'] ?? '' ) );

        if ( $old === '' || $new === '' ) {
            wp_send_json_error( [ 'message' => __( 'Nombre inválido para renombrar.', 'aura-suite' ) ] );
        }
        if ( $old === $new ) {
            wp_send_json_error( [ 'message' => __( 'El nombre actual y el nuevo son idénticos.', 'aura-suite' ) ] );
        }

        $t_tags = self::get_table_name();

        // Actualizar en catálogo
        $existing_target = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t_tags}` WHERE name = %s", $new ) );
        if ( $existing_target ) {
            wp_send_json_error( [ 'message' => sprintf( __( 'La etiqueta "%s" ya existe. Usa la herramienta de fusión.', 'aura-suite' ), $new ) ] );
        }

        $wpdb->update(
            $t_tags,
            [
                'name' => $new,
                'slug' => self::slugify( $new ),
            ],
            [ 'name' => $old ],
            [ '%s', '%s' ],
            [ '%s' ]
        );

        $updated = self::replace_tag_in_all( $old, $new );

        wp_send_json_success( [
            'message' => sprintf( __( 'Etiqueta renombrada con éxito. %d transacciones actualizadas.', 'aura-suite' ), $updated ),
            'updated' => $updated,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Fusionar dos tags (MERGE)
     * ------------------------------------------------------------------ */

    public static function ajax_merge() {
        self::check_write_permission();
        global $wpdb;

        $source = self::normalize( sanitize_text_field( $_POST['source'] ?? '' ) );
        $target = self::normalize( sanitize_text_field( $_POST['target'] ?? '' ) );

        if ( $source === '' || $target === '' || $source === $target ) {
            wp_send_json_error( [ 'message' => __( 'Etiquetas inválidas para realizar la fusión.', 'aura-suite' ) ] );
        }

        $t_tags = self::get_table_name();

        // Asegurar que destino exista en catálogo
        $target_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t_tags}` WHERE name = %s", $target ) );
        if ( ! $target_exists ) {
            $wpdb->insert(
                $t_tags,
                [
                    'name'       => $target,
                    'slug'       => self::slugify( $target ),
                    'color'      => '#3b82f6',
                    'created_by' => get_current_user_id() ?: null,
                    'created_at' => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%d', '%s' ]
            );
        }

        // Eliminar origen del catálogo
        $wpdb->delete( $t_tags, [ 'name' => $source ], [ '%s' ] );

        // Reemplazar source por target en transacciones (evita duplicados en CSV)
        $updated = self::replace_tag_in_all( $source, $target, true );

        wp_send_json_success( [
            'message' => sprintf( __( 'Etiqueta "#%s" fusionada satisfactoriamente en "#%s". %d transacciones actualizadas.', 'aura-suite' ), $source, $target, $updated ),
            'updated' => $updated,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Fusión masiva de etiquetas en lote (BULK MERGE)
     * ------------------------------------------------------------------ */

    public static function ajax_bulk_merge() {
        self::check_write_permission();
        global $wpdb;

        $raw_sources = $_POST['sources'] ?? [];
        if ( ! is_array( $raw_sources ) ) {
            $raw_sources = explode( ',', (string) $raw_sources );
        }

        $sources = array_filter( array_map( [ __CLASS__, 'normalize' ], $raw_sources ) );
        $target  = self::normalize( sanitize_text_field( $_POST['target'] ?? '' ) );

        if ( empty( $sources ) || $target === '' ) {
            wp_send_json_error( [ 'message' => __( 'Debes seleccionar al menos una etiqueta origen y definir la etiqueta destino.', 'aura-suite' ) ] );
        }

        // Remover target de sources si estuviera presente
        $sources = array_values( array_diff( $sources, [ $target ] ) );
        if ( empty( $sources ) ) {
            wp_send_json_error( [ 'message' => __( 'La etiqueta destino no puede ser la única etiqueta seleccionada.', 'aura-suite' ) ] );
        }

        $t_tags = self::get_table_name();

        // Asegurar que destino exista en catálogo
        $target_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t_tags}` WHERE name = %s", $target ) );
        if ( ! $target_exists ) {
            $wpdb->insert(
                $t_tags,
                [
                    'name'       => $target,
                    'slug'       => self::slugify( $target ),
                    'color'      => '#3b82f6',
                    'created_by' => get_current_user_id() ?: null,
                    'created_at' => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%d', '%s' ]
            );
        }

        $total_updated_txns = 0;
        foreach ( $sources as $src ) {
            $wpdb->delete( $t_tags, [ 'name' => $src ], [ '%s' ] );
            $total_updated_txns += self::replace_tag_in_all( $src, $target, true );
        }

        wp_send_json_success( [
            'message' => sprintf( __( '%d etiquetas fusionadas en "#%s". %d transacciones históricas actualizadas.', 'aura-suite' ), count( $sources ), $target, $total_updated_txns ),
            'updated' => $total_updated_txns,
            'count'   => count( $sources ),
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Eliminar tag (DELETE individual)
     * ------------------------------------------------------------------ */

    public static function ajax_delete() {
        self::check_delete_permission();
        global $wpdb;

        $tag = self::normalize( sanitize_text_field( $_POST['name'] ?? '' ) );
        if ( $tag === '' ) {
            wp_send_json_error( [ 'message' => __( 'Nombre de etiqueta no proporcionado.', 'aura-suite' ) ] );
        }

        $t_tags = self::get_table_name();

        // Eliminar del catálogo
        $wpdb->delete( $t_tags, [ 'name' => $tag ], [ '%s' ] );

        // Eliminar de las transacciones
        $updated = self::replace_tag_in_all( $tag, '' );

        wp_send_json_success( [
            'message' => sprintf( __( 'Etiqueta "#%s" eliminada permanentemente. %d transacciones desvinculadas.', 'aura-suite' ), $tag, $updated ),
            'updated' => $updated,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Eliminación masiva en lote (BULK DELETE)
     * ------------------------------------------------------------------ */

    public static function ajax_bulk_delete() {
        self::check_delete_permission();
        global $wpdb;

        $raw_tags = $_POST['names'] ?? [];
        if ( ! is_array( $raw_tags ) ) {
            $raw_tags = explode( ',', (string) $raw_tags );
        }

        $tags = array_filter( array_map( [ __CLASS__, 'normalize' ], $raw_tags ) );

        if ( empty( $tags ) ) {
            wp_send_json_error( [ 'message' => __( 'No se seleccionaron etiquetas para eliminar.', 'aura-suite' ) ] );
        }

        $t_tags = self::get_table_name();
        $total_updated = 0;

        foreach ( $tags as $tag ) {
            $wpdb->delete( $t_tags, [ 'name' => $tag ], [ '%s' ] );
            $total_updated += self::replace_tag_in_all( $tag, '' );
        }

        wp_send_json_success( [
            'message' => sprintf( __( '%d etiquetas eliminadas. %d transacciones desvinculadas en total.', 'aura-suite' ), count( $tags ), $total_updated ),
            'updated' => $total_updated,
            'count'   => count( $tags ),
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Nube de tags interactiva
     * ------------------------------------------------------------------ */

    public static function ajax_cloud() {
        self::check_read_permission();

        $details = self::get_all_tags_details();
        if ( empty( $details ) ) {
            wp_send_json_success( [ 'tags' => [] ] );
        }

        $counts = array_column( $details, 'count' );
        $max = ! empty( $counts ) ? max( $counts ) : 1;
        $min = ! empty( $counts ) ? min( $counts ) : 0;

        $cloud = [];
        foreach ( $details as $item ) {
            $count = (int) $item['count'];
            $size = ( $min === $max )
                ? 16
                : (int) ( 12 + ( ( $count - $min ) / ( ( $max - $min ) ?: 1 ) ) * 16 );

            $cloud[] = [
                'name'        => $item['name'],
                'count'       => $count,
                'size'        => $size,
                'color'       => $item['color'],
                'description' => $item['description'],
                'total_amount'=> $item['total_amount'],
                'net_balance' => $item['net_balance'],
            ];
        }

        wp_send_json_success( [ 'tags' => $cloud ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Obtener Transacciones Asociadas a una Etiqueta
     * ------------------------------------------------------------------ */

    public static function ajax_get_transactions() {
        self::check_read_permission();
        global $wpdb;

        $tag = self::normalize( sanitize_text_field( $_POST['tag'] ?? '' ) );
        if ( $tag === '' ) {
            wp_send_json_error( [ 'message' => __( 'Etiqueta no válida.', 'aura-suite' ) ] );
        }

        $t_txns = $wpdb->prefix . 'aura_finance_transactions';
        $t_cats = $wpdb->prefix . 'aura_finance_categories';

        // Buscar transacciones con este tag
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.transaction_date, t.description, t.recipient_payer,
                    t.transaction_type, t.amount, t.status, t.payment_method, t.tags,
                    c.name AS category_name
             FROM `{$t_txns}` t
             LEFT JOIN `{$t_cats}` c ON t.category_id = c.id
             WHERE t.tags LIKE %s AND t.deleted_at IS NULL
             ORDER BY t.transaction_date DESC, t.id DESC
             LIMIT 100",
            '%' . $wpdb->esc_like( $tag ) . '%'
        ) );

        $currency = get_option( 'aura_finance_currency', 'USD' );
        $filtered = [];

        // Filtro exacto para evitar falsos positivos de subcadenas (ej. 'viaje' dentro de 'viajes')
        foreach ( $rows as $row ) {
            $parsed = array_filter( array_map( 'trim', explode( ',', $row->tags ?? '' ) ) );
            $normalized_list = array_map( [ __CLASS__, 'normalize' ], $parsed );

            if ( in_array( $tag, $normalized_list, true ) ) {
                $edit_url = admin_url( 'admin.php?page=aura-financial-edit-transaction&id=' . (int) $row->id );
                $filtered[] = [
                    'id'               => (int) $row->id,
                    'transaction_date' => $row->transaction_date ?: '—',
                    'description'      => $row->description ?: '—',
                    'recipient_payer'  => $row->recipient_payer ?: '—',
                    'category_name'    => $row->category_name ?: __( 'Sin Categoría', 'aura-suite' ),
                    'transaction_type' => $row->transaction_type,
                    'amount'           => floatval( $row->amount ),
                    'status'           => $row->status ?: 'pending',
                    'payment_method'   => $row->payment_method ?: '',
                    'edit_url'         => $edit_url,
                ];
            }
        }

        wp_send_json_success( [
            'tag'          => $tag,
            'total'        => count( $filtered ),
            'currency'     => $currency,
            'transactions' => $filtered,
        ] );
    }

    /* ------------------------------------------------------------------
     * Helper: Reemplazar o eliminar etiqueta en todas las transacciones
     * Devuelve cantidad de filas actualizadas en la base de datos.
     * ------------------------------------------------------------------ */

    private static function replace_tag_in_all( $old, $new, $merge = false ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Traer filas que contengan la subcadena
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, tags FROM `{$table}`
             WHERE tags LIKE %s AND deleted_at IS NULL",
            '%' . $wpdb->esc_like( $old ) . '%'
        ) );

        $affected = 0;
        foreach ( $rows as $row ) {
            $tags = array_filter( array_map( 'trim', explode( ',', $row->tags ) ) );
            $tags = array_map( [ __CLASS__, 'normalize' ], $tags );

            $new_tags = [];
            $changed  = false;

            foreach ( $tags as $t ) {
                if ( $t === $old ) {
                    $changed = true;
                    if ( $new !== '' ) {
                        // Para merge/renombrado: evitar duplicados
                        if ( ! in_array( $new, $new_tags, true ) ) {
                            $new_tags[] = $new;
                        }
                    }
                    // Si $new === '' se descarta (eliminado)
                } else {
                    if ( ! in_array( $t, $new_tags, true ) ) {
                        $new_tags[] = $t;
                    }
                }
            }

            if ( ! $changed ) {
                continue;
            }

            $wpdb->update(
                $table,
                [ 'tags' => implode( ', ', $new_tags ) ],
                [ 'id'   => $row->id ],
                [ '%s' ],
                [ '%d' ]
            );
            $affected++;
        }

        return $affected;
    }

    /* ------------------------------------------------------------------
     * Render página de gestión de etiquetas
     * ------------------------------------------------------------------ */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/tags-page.php';
    }
}
