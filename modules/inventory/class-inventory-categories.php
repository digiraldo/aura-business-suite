<?php
/**
 * Inventario — Categorías y Configuración (FASE 5 / Settings)
 *
 * Gestiona las categorías de equipo (taxonomía `categoria_equipo`) y
 * las opciones generales del módulo de inventario.
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Categories {

    const NONCE       = 'aura_inventory_nonce';
    const OPTION_KEY  = 'aura_inventory_settings';
    const TAXONOMY    = 'categoria_equipo';

    // ─────────────────────────────────────────────────────────────
    // INIT — registrar AJAX actions
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        $ajax_actions = [
            'add_category'         => 'ajax_add_category',
            'update_category'      => 'ajax_update_category',
            'delete_category'      => 'ajax_delete_category',
            'install_defaults'     => 'ajax_install_defaults',
            'save_settings'        => 'ajax_save_settings',
            'save_finance_settings' => 'ajax_save_finance_settings',
            'get_categories'       => 'ajax_get_categories',
        ];

        foreach ( $ajax_actions as $action => $handler ) {
            add_action( 'wp_ajax_aura_inventory_settings_' . $action, [ __CLASS__, $handler ] );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // RENDER — página de configuración
    // ─────────────────────────────────────────────────────────────

    public static function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'aura-suite' ) );
        }
        include AURA_PLUGIN_DIR . 'templates/inventory/settings.php';
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PÚBLICOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve todas las categorías de equipo con sus metadatos de mantenimiento.
     *
     * @return WP_Term[]
     */
    public static function get_all(): array {
        // Garantizar que la taxonomía esté registrada (fallback defensivo)
        if ( ! taxonomy_exists( self::TAXONOMY ) ) {
            Aura_Inventory_Setup::register_taxonomies();
        }

        $terms = get_terms( [
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        foreach ( $terms as &$term ) {
            $term->interval_type   = get_term_meta( $term->term_id, 'interval_type',   true ) ?: 'none';
            $term->interval_months = get_term_meta( $term->term_id, 'interval_months', true );
            $term->interval_hours  = get_term_meta( $term->term_id, 'interval_hours',  true );
        }
        unset( $term );

        return $terms;
    }

    /**
     * Devuelve la configuración general del módulo (con valores por defecto).
     *
     * @return array
     */
    public static function get_settings(): array {
        $defaults = [
            'items_per_page'           => 20,
            'alert_days_before'        => 7,
            'show_retired'             => false,
            'email_alerts'             => true,
            'email_extra'              => '',
            'loan_max_days'            => 30,
            'currency_symbol'          => '$',
            'currency_position'        => 'before',
            'finance_category_id'      => 0,
            'auto_approve_transactions' => 'approved',
        ];

        $saved = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $saved, $defaults );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Agregar categoría
    // ─────────────────────────────────────────────────────────────

    public static function ajax_add_category(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $name   = sanitize_text_field( trim( $_POST['name']   ?? '' ) );
        $slug   = sanitize_title(      trim( $_POST['slug']   ?? $name ) );
        $desc   = sanitize_textarea_field( $_POST['description'] ?? '' );
        $itype  = in_array( $_POST['interval_type'] ?? '', [ 'time', 'hours', 'both', 'none' ] )
                  ? sanitize_key( $_POST['interval_type'] ) : 'none';
        $imon   = intval( $_POST['interval_months'] ?? 0 ) ?: null;
        $ihrs   = intval( $_POST['interval_hours']  ?? 0 ) ?: null;

        if ( $name === '' ) {
            wp_send_json_error( [ 'message' => __( 'El nombre de la categoría es obligatorio.', 'aura-suite' ) ] );
        }

        if ( term_exists( $slug, self::TAXONOMY ) || term_exists( $name, self::TAXONOMY ) ) {
            wp_send_json_error( [ 'message' => __( 'Ya existe una categoría con ese nombre o slug.', 'aura-suite' ) ] );
        }

        $result = wp_insert_term( $name, self::TAXONOMY, [
            'slug'        => $slug,
            'description' => $desc,
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $term_id = $result['term_id'];
        update_term_meta( $term_id, 'interval_type',   $itype );
        update_term_meta( $term_id, 'interval_months', $imon  );
        update_term_meta( $term_id, 'interval_hours',  $ihrs  );

        $term = get_term( $term_id, self::TAXONOMY );

        wp_send_json_success( [
            'message'        => __( 'Categoría creada correctamente.', 'aura-suite' ),
            'term_id'        => $term_id,
            'name'           => $term->name,
            'slug'           => $term->slug,
            'count'          => $term->count,
            'interval_type'  => $itype,
            'interval_months'=> $imon,
            'interval_hours' => $ihrs,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Actualizar categoría
    // ─────────────────────────────────────────────────────────────

    public static function ajax_update_category(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $term_id = intval( $_POST['term_id'] ?? 0 );
        $name    = sanitize_text_field( trim( $_POST['name']   ?? '' ) );
        $slug    = sanitize_title(      trim( $_POST['slug']   ?? $name ) );
        $desc    = sanitize_textarea_field( $_POST['description'] ?? '' );
        $itype   = in_array( $_POST['interval_type'] ?? '', [ 'time', 'hours', 'both', 'none' ] )
                   ? sanitize_key( $_POST['interval_type'] ) : 'none';
        $imon    = intval( $_POST['interval_months'] ?? 0 ) ?: null;
        $ihrs    = intval( $_POST['interval_hours']  ?? 0 ) ?: null;

        if ( $term_id <= 0 || $name === '' ) {
            wp_send_json_error( [ 'message' => __( 'Datos inválidos.', 'aura-suite' ) ] );
        }

        // Garantizar taxonomía registrada
        if ( ! taxonomy_exists( self::TAXONOMY ) ) {
            Aura_Inventory_Setup::register_taxonomies();
        }

        $result = wp_update_term( $term_id, self::TAXONOMY, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        update_term_meta( $term_id, 'interval_type',   $itype );
        update_term_meta( $term_id, 'interval_months', $imon  );
        update_term_meta( $term_id, 'interval_hours',  $ihrs  );

        $term = get_term( $term_id, self::TAXONOMY );

        wp_send_json_success( [
            'message'         => __( 'Categoría actualizada correctamente.', 'aura-suite' ),
            'term_id'         => $term_id,
            'name'            => $term->name,
            'slug'            => $term->slug,
            'interval_type'   => $itype,
            'interval_months' => $imon,
            'interval_hours'  => $ihrs,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Eliminar categoría
    // ─────────────────────────────────────────────────────────────

    public static function ajax_delete_category(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $term_id = intval( $_POST['term_id'] ?? 0 );
        if ( $term_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'ID de categoría inválido.', 'aura-suite' ) ] );
        }

        $term = get_term( $term_id, self::TAXONOMY );
        if ( ! $term || is_wp_error( $term ) ) {
            wp_send_json_error( [ 'message' => __( 'Categoría no encontrada.', 'aura-suite' ) ] );
        }

        // Verificar si hay equipos con este slug
        global $wpdb;
        $used = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aura_inventory_equipment
             WHERE category = %s AND deleted_at IS NULL",
            $term->slug
        ) );

        if ( $used > 0 ) {
            wp_send_json_error( [
                'message' => sprintf(
                    _n(
                        'No puedes eliminar esta categoría: hay %d equipo que la usa.',
                        'No puedes eliminar esta categoría: hay %d equipos que la usan.',
                        (int) $used,
                        'aura-suite'
                    ),
                    (int) $used
                ),
            ] );
        }

        $deleted = wp_delete_term( $term_id, self::TAXONOMY );
        if ( is_wp_error( $deleted ) ) {
            wp_send_json_error( [ 'message' => $deleted->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => __( 'Categoría eliminada.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Instalar categorías predeterminadas
    // ─────────────────────────────────────────────────────────────

    public static function ajax_install_defaults(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        // Garantizar que la taxonomía esté registrada en este contexto AJAX.
        // Puede no estarlo si el hook 'init' de Aura_Inventory_Setup se añadió
        // después de que 'init' ya había comenzado a ejecutarse.
        if ( ! taxonomy_exists( self::TAXONOMY ) ) {
            Aura_Inventory_Setup::register_taxonomies();
        }

        $defaults = self::get_default_categories();
        $created  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ( $defaults as $cat ) {
            // Verificar por slug Y por nombre para evitar duplicados
            $existing = get_term_by( 'slug', $cat['slug'], self::TAXONOMY );
            if ( $existing ) {
                $skipped++;
                continue;
            }

            $result = wp_insert_term( $cat['name'], self::TAXONOMY, [
                'slug'        => $cat['slug'],
                'description' => $cat['description'] ?? '',
            ] );

            if ( ! is_wp_error( $result ) ) {
                $term_id = $result['term_id'];
                update_term_meta( $term_id, 'interval_type',   $cat['interval_type']   );
                update_term_meta( $term_id, 'interval_months', $cat['interval_months'] );
                update_term_meta( $term_id, 'interval_hours',  $cat['interval_hours']  );
                $created++;
            } else {
                $errors[] = $cat['slug'] . ': ' . $result->get_error_message();
            }
        }

        $message = sprintf(
            __( '%d categorías instaladas, %d ya existían.', 'aura-suite' ),
            $created,
            $skipped
        );

        if ( ! empty( $errors ) ) {
            $message .= ' ' . sprintf(
                __( '%d errores.', 'aura-suite' ),
                count( $errors )
            );
        }

        wp_send_json_success( [
            'message' => $message,
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Guardar configuración general
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_settings(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $settings = [
            'items_per_page'    => max( 5, min( 100, intval( $_POST['items_per_page']    ?? 20 ) ) ),
            'alert_days_before' => max( 1, min( 60,  intval( $_POST['alert_days_before'] ?? 7  ) ) ),
            'loan_max_days'     => max( 1, min( 365, intval( $_POST['loan_max_days']     ?? 30 ) ) ),
            'show_retired'      => ! empty( $_POST['show_retired'] ),
            'email_alerts'      => ! empty( $_POST['email_alerts'] ),
            'email_extra'       => sanitize_textarea_field( $_POST['email_extra'] ?? '' ),
            'currency_symbol'   => sanitize_text_field( $_POST['currency_symbol']   ?? '$' ),
            'currency_position' => in_array( $_POST['currency_position'] ?? '', [ 'before', 'after' ] )
                                   ? sanitize_key( $_POST['currency_position'] ) : 'before',
        ];

        update_option( self::OPTION_KEY, $settings );

        wp_send_json_success( [ 'message' => __( 'Configuración guardada correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Guardar configuración de Finanzas
    // ─────────────────────────────────────────────────────────────

    public static function ajax_save_finance_settings(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'aura_inventory_categories' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos.', 'aura-suite' ) ] );
        }

        $existing = self::get_settings();

        $updated = array_merge( $existing, [
            'finance_category_id'       => intval( $_POST['finance_category_id'] ?? 0 ),
            'auto_approve_transactions' => in_array( $_POST['auto_approve_transactions'] ?? '', [ 'approved', 'pending' ] )
                                           ? sanitize_key( $_POST['auto_approve_transactions'] ) : 'approved',
        ] );

        update_option( self::OPTION_KEY, $updated );

        wp_send_json_success( [ 'message' => __( 'Configuración de finanzas guardada correctamente.', 'aura-suite' ) ] );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX — Obtener todas las categorías (para formularios)
    // ─────────────────────────────────────────────────────────────

    public static function ajax_get_categories(): void {
        check_ajax_referer( self::NONCE, 'nonce' );

        $cats = self::get_all();
        $data = array_map( function( $t ) {
            return [
                'term_id'        => $t->term_id,
                'name'           => $t->name,
                'slug'           => $t->slug,
                'count'          => $t->count,
                'interval_type'  => $t->interval_type,
                'interval_months'=> $t->interval_months,
                'interval_hours' => $t->interval_hours,
            ];
        }, $cats );

        wp_send_json_success( $data );
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE — Categorías predeterminadas (idénticas a Setup)
    // ─────────────────────────────────────────────────────────────

    private static function get_default_categories(): array {
        return [
            [ 'name' => 'Vehículos y Maquinaria Pesada',    'slug' => 'vehiculos',       'interval_type' => 'hours', 'interval_months' => 3,  'interval_hours' => 250, 'description' => 'Vehículos, tractores, retroexcavadoras, compresores.' ],
            [ 'name' => 'Herramientas Eléctricas',           'slug' => 'electronicas',    'interval_type' => 'time',  'interval_months' => 6,  'interval_hours' => null, 'description' => 'Taladros, sierras, amoladoras, pulidoras.' ],
            [ 'name' => 'Equipos de Cómputo y Electrónica',  'slug' => 'computo',         'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Computadoras, laptops, impresoras, proyectores.' ],
            [ 'name' => 'Equipos de Audio y Video',          'slug' => 'audio-video',     'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Micrófonos, parlantes, cámaras, mezcladores.' ],
            [ 'name' => 'Equipos de Cocina e Industrial',    'slug' => 'cocina',          'interval_type' => 'time',  'interval_months' => 6,  'interval_hours' => null, 'description' => 'Estufas industriales, hornos, freidoras, refrigeración.' ],
            [ 'name' => 'Generadores y Energía',             'slug' => 'generadores',     'interval_type' => 'hours', 'interval_months' => 6,  'interval_hours' => 200, 'description' => 'Generadores eléctricos, UPS, paneles solares.' ],
            [ 'name' => 'Equipos Deportivos',                'slug' => 'deportivo',       'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Mesas de ping-pong, pesas, colchonetas, arco fútbol.' ],
            [ 'name' => 'Herramientas de Mano',              'slug' => 'herramientas',    'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Llaves, destornilladores, martillos, cinceles.' ],
            [ 'name' => 'Herramientas Inalámbricas',         'slug' => 'inalambricas',    'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Herramientas con batería: revisión y carga anual.' ],
            [ 'name' => 'Sistema de Riego',                  'slug' => 'riego',           'interval_type' => 'time',  'interval_months' => 6,  'interval_hours' => null, 'description' => 'Aspersores, bombas de riego, filtros y mangueras.' ],
            [ 'name' => 'Mobiliario',                        'slug' => 'mobiliario',      'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Mesas, sillas, estantes, archivadores.' ],
            [ 'name' => 'Equipo de Seguridad',               'slug' => 'seguridad',       'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Extintores, arneses, cascos, detectores de humo.' ],
            [ 'name' => 'Material de Limpieza',              'slug' => 'limpieza',        'interval_type' => 'none',  'interval_months' => null,'interval_hours' => null, 'description' => 'Aspiradoras, pulidoras de piso, mopas industriales.' ],
            [ 'name' => 'Equipos de Camping y Exterior',     'slug' => 'camping',         'interval_type' => 'time',  'interval_months' => 6,  'interval_hours' => null, 'description' => 'Carpas, sleeping bags, cocinas de gas, linternas.' ],
            [ 'name' => 'Instrumentos Musicales',            'slug' => 'musicales',       'interval_type' => 'time',  'interval_months' => 6,  'interval_hours' => null, 'description' => 'Guitarras, piano, batería, instrumentos de viento.' ],
            [ 'name' => 'Comunicaciones',                    'slug' => 'comunicaciones',  'interval_type' => 'time',  'interval_months' => 12, 'interval_hours' => null, 'description' => 'Radios, walkie-talkies, routers, switches.' ],
            [ 'name' => 'Impresión y Señalética',            'slug' => 'impresion',       'interval_type' => 'time',  'interval_months' => 6,  'interval_hours' => null, 'description' => 'Impresoras, plotters, laminadoras, selladoras.' ],
            [ 'name' => 'Otros / Sin categoría',             'slug' => 'otros',           'interval_type' => 'none',  'interval_months' => null,'interval_hours' => null, 'description' => 'Equipos que no encajan en otra categoría.' ],
        ];
    }
}
