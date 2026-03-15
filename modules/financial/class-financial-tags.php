<?php
/**
 * Gestión de Etiquetas (Tags) — Fase 5, Item 5.2
 *
 * Tags se almacenan como CSV en wp_aura_finance_transactions.tags.
 * Esta clase centraliza CRUD sobre esos valores y agrega FULLTEXT
 * para búsqueda avanzada.
 *
 * @package AuraBusinessSuite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Tags {

    /* ------------------------------------------------------------------
     * Init
     * ------------------------------------------------------------------ */

    public static function init() {
        add_action( 'admin_init',  [ __CLASS__, 'maybe_add_fulltext_index' ] );

        add_action( 'wp_ajax_aura_tags_autocomplete',   [ __CLASS__, 'ajax_autocomplete' ] );
        add_action( 'wp_ajax_aura_tags_get_all',        [ __CLASS__, 'ajax_get_all' ] );
        add_action( 'wp_ajax_aura_tags_rename',         [ __CLASS__, 'ajax_rename' ] );
        add_action( 'wp_ajax_aura_tags_merge',          [ __CLASS__, 'ajax_merge' ] );
        add_action( 'wp_ajax_aura_tags_delete',         [ __CLASS__, 'ajax_delete' ] );
        add_action( 'wp_ajax_aura_tags_cloud',          [ __CLASS__, 'ajax_cloud' ] );
    }

    /* ------------------------------------------------------------------
     * FULLTEXT index en columnas de búsqueda
     * ------------------------------------------------------------------ */

    public static function maybe_add_fulltext_index() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Verificar si ya existe el índice FULLTEXT
        $idx = $wpdb->get_var( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'ft_search'" );
        if ( ! $idx ) {
            $wpdb->query(
                "ALTER TABLE `{$table}` ADD FULLTEXT INDEX `ft_search`
                 (`description`, `notes`, `reference_number`, `recipient_payer`, `tags`)"
            );
        }
    }

    /* ------------------------------------------------------------------
     * Helper: normalizar tag
     * ------------------------------------------------------------------ */

    private static function normalize( $tag ) {
        return mb_strtolower( trim( $tag ) );
    }

    /* ------------------------------------------------------------------
     * Helper: obtener todos los tags únicos con conteos
     * ------------------------------------------------------------------ */

    public static function get_all_tags() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $rows = $wpdb->get_col(
            "SELECT tags FROM `{$table}`
             WHERE tags IS NOT NULL AND tags != '' AND deleted_at IS NULL"
        );

        $counts = [];
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

    /* ------------------------------------------------------------------
     * AJAX: Autocompletar tags
     * ------------------------------------------------------------------ */

    public static function ajax_autocomplete() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );

        $term = self::normalize( sanitize_text_field( $_GET['term'] ?? '' ) );
        if ( strlen( $term ) < 1 ) wp_send_json_success( [] );

        $all  = self::get_all_tags();
        $matches = [];
        foreach ( $all as $tag => $count ) {
            if ( str_contains( $tag, $term ) ) {
                $matches[] = [ 'label' => $tag . ' (' . $count . ')', 'value' => $tag ];
            }
            if ( count( $matches ) >= 10 ) break;
        }

        wp_send_json_success( $matches );
    }

    /* ------------------------------------------------------------------
     * AJAX: Listar todos los tags con conteos
     * ------------------------------------------------------------------ */

    public static function ajax_get_all() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_view_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $counts = self::get_all_tags();
        $result = [];
        foreach ( $counts as $tag => $count ) {
            $result[] = [ 'name' => $tag, 'count' => $count ];
        }

        wp_send_json_success( [ 'tags' => $result ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Renombrar tag (actualiza todas las transacciones)
     * ------------------------------------------------------------------ */

    public static function ajax_rename() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_edit_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $old = self::normalize( sanitize_text_field( $_POST['old_name'] ?? '' ) );
        $new = self::normalize( sanitize_text_field( $_POST['new_name'] ?? '' ) );

        if ( $old === '' || $new === '' ) {
            wp_send_json_error( [ 'message' => __( 'Nombre inválido', 'aura-suite' ) ] );
        }
        if ( $old === $new ) {
            wp_send_json_error( [ 'message' => __( 'Los nombres son iguales', 'aura-suite' ) ] );
        }

        $updated = self::replace_tag_in_all( $old, $new );
        wp_send_json_success( [
            'message' => sprintf( __( 'Etiqueta renombrada. %d transacciones actualizadas.', 'aura-suite' ), $updated ),
            'updated' => $updated,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Fusionar dos tags
     * ------------------------------------------------------------------ */

    public static function ajax_merge() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_edit_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $source = self::normalize( sanitize_text_field( $_POST['source'] ?? '' ) );
        $target = self::normalize( sanitize_text_field( $_POST['target'] ?? '' ) );

        if ( $source === '' || $target === '' || $source === $target ) {
            wp_send_json_error( [ 'message' => __( 'Tags inválidos para fusión', 'aura-suite' ) ] );
        }

        // Renombrar source → target (el helper también elimina duplicados)
        $updated = self::replace_tag_in_all( $source, $target, true );
        wp_send_json_success( [
            'message' => sprintf( __( 'Etiqueta "%s" fusionada en "%s". %d transacciones actualizadas.', 'aura-suite' ), $source, $target, $updated ),
            'updated' => $updated,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Eliminar tag de todas las transacciones
     * ------------------------------------------------------------------ */

    public static function ajax_delete() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );

        if ( ! current_user_can( 'aura_finance_delete_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $tag = self::normalize( sanitize_text_field( $_POST['name'] ?? '' ) );
        if ( $tag === '' ) wp_send_json_error( [ 'message' => __( 'Nombre inválido', 'aura-suite' ) ] );

        // Eliminar el tag (replace por cadena vacía y limpiar)
        $updated = self::replace_tag_in_all( $tag, '' );
        wp_send_json_success( [
            'message' => sprintf( __( 'Etiqueta eliminada. %d transacciones actualizadas.', 'aura-suite' ), $updated ),
            'updated' => $updated,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX: Nube de tags
     * ------------------------------------------------------------------ */

    public static function ajax_cloud() {
        check_ajax_referer( 'aura_tags_nonce', 'nonce' );

        $counts = self::get_all_tags();
        if ( empty( $counts ) ) {
            wp_send_json_success( [ 'tags' => [] ] );
        }

        $max = max( $counts );
        $min = min( $counts );

        $cloud = [];
        foreach ( $counts as $tag => $count ) {
            // Escala del tamaño de fuente entre 12px–28px
            $size = $min === $max
                ? 18
                : (int) ( 12 + ( ( $count - $min ) / ( $max - $min ) ) * 16 );

            $cloud[] = [ 'name' => $tag, 'count' => $count, 'size' => $size ];
        }

        wp_send_json_success( [ 'tags' => $cloud ] );
    }

    /* ------------------------------------------------------------------
     * Helper: reemplazar/eliminar tag en todas las transacciones
     * Devuelve cantidad de filas afectadas.
     * ------------------------------------------------------------------ */

    private static function replace_tag_in_all( $old, $new, $merge = false ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        // Traemos solo las filas que contienen el tag
        $rows = $wpdb->get_results(
            "SELECT id, tags FROM `{$table}`
             WHERE tags LIKE '%" . esc_sql( $old ) . "%' AND deleted_at IS NULL"
        );

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
                        // Para merge: evitar duplicados
                        if ( ! in_array( $new, $new_tags, true ) ) {
                            $new_tags[] = $new;
                        }
                    }
                    // Si $new === '' → simplemente no se agrega (eliminado)
                } else {
                    $new_tags[] = $t;
                }
            }

            if ( ! $changed ) continue;

            $wpdb->update(
                $table,
                [ 'tags' => implode( ', ', $new_tags ) ],
                [ 'id'   => $row->id ],
                [ '%s'  ],
                [ '%d'  ]
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
