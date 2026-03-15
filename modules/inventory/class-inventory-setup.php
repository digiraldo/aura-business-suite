<?php
/**
 * Setup del Módulo de Inventario y Mantenimientos
 *
 * Responsabilidades:
 *  - Crear / migrar las 3 tablas de BD del módulo
 *  - Registrar CPTs: aura_equipo, aura_mantenimiento
 *  - Registrar taxonomías: categoria_equipo, tipo_mantenimiento, estado_equipo
 *  - Insertar categorías de equipo predeterminadas con sus intervalos de mantenimiento
 *
 * @package AuraBusinessSuite
 * @subpackage Inventory
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Inventory_Setup {

    /** Versión del esquema de BD del módulo */
    const DB_VERSION        = '1.2.0';
    const DB_VERSION_OPTION = 'aura_inventory_db_version';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init() {
        // Llamar directamente: este método ya es invocado desde el hook 'init'
        // (via Aura_Business_Suite::init()), por lo que usar add_action('init',...)
        // aquí resultaría en callbacks que nunca se ejecutan en la misma petición.
        self::register_post_types();
        self::register_taxonomies();

        // Migración automática si la versión cambió
        if ( self::needs_update() ) {
            add_action( 'admin_init', array( __CLASS__, 'create_tables' ) );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // BASE DE DATOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear (o actualizar) las 3 tablas del módulo.
     * Llamado desde activate() del plugin principal y en migraciones.
     * Es idempotente: dbDelta() solo agrega columnas/índices faltantes.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_equipment   = $wpdb->prefix . 'aura_inventory_equipment';
        $t_maintenance = $wpdb->prefix . 'aura_inventory_maintenance';
        $t_loans       = $wpdb->prefix . 'aura_inventory_loans';

        // ── Tabla: equipos ────────────────────────────────────────
        $sql_equipment = "CREATE TABLE {$t_equipment} (
            id                       BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name                     VARCHAR(255) NOT NULL,
            brand                    VARCHAR(100) DEFAULT NULL,
            model                    VARCHAR(100) DEFAULT NULL,
            serial_number            VARCHAR(100) DEFAULT NULL,
            internal_code            VARCHAR(50)  DEFAULT NULL,
            category                 VARCHAR(100) DEFAULT NULL,
            description              TEXT         DEFAULT NULL,
            photo                    VARCHAR(500) DEFAULT NULL,
            status                   ENUM('available','in_use','maintenance','repair','retired') NOT NULL DEFAULT 'available',
            location                 VARCHAR(255) DEFAULT NULL,
            acquisition_date         DATE         DEFAULT NULL,
            cost                     DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            estimated_value          DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            supplier                 VARCHAR(255) DEFAULT NULL,
            warranty_date            DATE         DEFAULT NULL,
            manual_file              VARCHAR(500) DEFAULT NULL,
            requires_maintenance     TINYINT(1)   NOT NULL DEFAULT 0,
            interval_type            ENUM('time','hours','both') DEFAULT 'time',
            interval_months          INT          DEFAULT NULL,
            interval_hours           INT          DEFAULT NULL,
            alert_days_before        INT          NOT NULL DEFAULT 7,
            last_maintenance_date    DATE         DEFAULT NULL,
            last_maintenance_hours   DECIMAL(10,2) DEFAULT NULL,
            next_maintenance_date    DATE         DEFAULT NULL,
            next_maintenance_hours   DECIMAL(10,2) DEFAULT NULL,
            total_maintenance_cost   DECIMAL(12,2) NOT NULL DEFAULT '0.00',
            oil_type                 VARCHAR(100) DEFAULT NULL,
            oil_capacity             DECIMAL(6,2) DEFAULT NULL,
            fuel_type                VARCHAR(100) DEFAULT NULL,
            voltage                  INT          DEFAULT NULL,
            hydraulic_pressure       VARCHAR(50)  DEFAULT NULL,
            accessories              TEXT         DEFAULT NULL,
            parent_equipment_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            responsible_user_id      BIGINT(20)   DEFAULT NULL,
            area_id                  BIGINT(20)   DEFAULT NULL,
            created_by               BIGINT(20)   NOT NULL DEFAULT 0,
            created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at               DATETIME     DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_status           (status),
            KEY idx_category         (category(50)),
            KEY idx_responsible      (responsible_user_id),
            KEY idx_area             (area_id),
            KEY idx_next_maintenance (next_maintenance_date),
            KEY idx_parent_equipment (parent_equipment_id),
            KEY idx_deleted          (deleted_at)
        ) {$charset_collate};";

        // ── Tabla: mantenimientos ─────────────────────────────────
        $sql_maintenance = "CREATE TABLE {$t_maintenance} (
            id                         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            equipment_id               BIGINT(20) UNSIGNED NOT NULL,
            type                       ENUM('preventive','corrective','oil_change','cleaning','inspection','major_repair') NOT NULL DEFAULT 'preventive',
            maintenance_date           DATE         NOT NULL,
            equipment_hours            DECIMAL(10,2) DEFAULT NULL,
            description                TEXT         DEFAULT NULL,
            parts_replaced             TEXT         DEFAULT NULL,
            parts_cost                 DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            labor_cost                 DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            total_cost                 DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            performed_by               ENUM('internal','external') NOT NULL DEFAULT 'internal',
            workshop_name              VARCHAR(255) DEFAULT NULL,
            internal_technician        BIGINT(20)   DEFAULT NULL,
            workshop_invoice           VARCHAR(500) DEFAULT NULL,
            invoice_number             VARCHAR(100) DEFAULT NULL,
            post_status                ENUM('operational','needs_followup','out_of_service') NOT NULL DEFAULT 'operational',
            next_action_date           DATE         DEFAULT NULL,
            observations               TEXT         DEFAULT NULL,
            create_finance_transaction TINYINT(1)   NOT NULL DEFAULT 0,
            finance_transaction_id     BIGINT(20)   DEFAULT NULL,
            registered_by              BIGINT(20)   NOT NULL DEFAULT 0,
            registered_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_by                BIGINT(20)   DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_equipment          (equipment_id),
            KEY idx_type               (type),
            KEY idx_date               (maintenance_date),
            KEY idx_performed_by       (performed_by)
        ) {$charset_collate};";

        // ── Tabla: préstamos ──────────────────────────────────────
        $sql_loans = "CREATE TABLE {$t_loans} (
            id                         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            equipment_id               BIGINT(20) UNSIGNED NOT NULL,
            borrowed_by_user_id        BIGINT(20)   NOT NULL,
            borrowed_to_name           VARCHAR(255) DEFAULT NULL,
            loan_date                  DATE         NOT NULL,
            expected_return_date       DATE         NOT NULL,
            project                    VARCHAR(255) DEFAULT NULL,
            equipment_state_out        ENUM('good','fair','poor') NOT NULL DEFAULT 'good',
            photo_out                  VARCHAR(500) DEFAULT NULL,
            actual_return_date         DATE         DEFAULT NULL,
            return_state               ENUM('good','fair','damaged') DEFAULT NULL,
            hours_used                 DECIMAL(10,2) DEFAULT NULL,
            return_photo               VARCHAR(500) DEFAULT NULL,
            requires_maintenance_after TINYINT(1)   NOT NULL DEFAULT 0,
            return_observations        TEXT         DEFAULT NULL,
            borrowed_to_phone          VARCHAR(30)  DEFAULT NULL,
            registered_by              BIGINT(20)   NOT NULL DEFAULT 0,
            created_at                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            returned_at                DATETIME     DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_equipment          (equipment_id),
            KEY idx_borrower           (borrowed_by_user_id),
            KEY idx_expected_return    (expected_return_date),
            KEY idx_returned           (actual_return_date)
        ) {$charset_collate};";

        dbDelta( $sql_equipment );
        dbDelta( $sql_maintenance );
        dbDelta( $sql_loans );

        // Guardar versión de BD
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

        // Insertar categorías predeterminadas (idempotente)
        self::install_default_categories();
    }

    /**
     * Verificar si el esquema necesita actualización
     */
    public static function needs_update() {
        return get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION;
    }

    // ─────────────────────────────────────────────────────────────
    // CUSTOM POST TYPES
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar CPTs del módulo: aura_equipo y aura_mantenimiento
     */
    public static function register_post_types() {

        // ── CPT: aura_equipo ──────────────────────────────────────
        register_post_type( 'aura_equipo', array(
            'labels' => array(
                'name'               => __( 'Equipos',          'aura-suite' ),
                'singular_name'      => __( 'Equipo',           'aura-suite' ),
                'add_new'            => __( 'Agregar equipo',   'aura-suite' ),
                'add_new_item'       => __( 'Agregar equipo',   'aura-suite' ),
                'edit_item'          => __( 'Editar equipo',    'aura-suite' ),
                'new_item'           => __( 'Nuevo equipo',     'aura-suite' ),
                'view_item'          => __( 'Ver equipo',       'aura-suite' ),
                'search_items'       => __( 'Buscar equipos',   'aura-suite' ),
                'not_found'          => __( 'No se encontraron equipos', 'aura-suite' ),
                'not_found_in_trash' => __( 'No hay equipos en la papelera', 'aura-suite' ),
            ),
            'public'             => false,
            'show_ui'            => false,   // UI propia; no exponer en menú de WP
            'show_in_menu'       => false,
            'show_in_rest'       => false,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => array( 'title', 'thumbnail' ),
            'taxonomies'         => array( 'categoria_equipo', 'estado_equipo' ),
            'has_archive'        => false,
            'rewrite'            => false,
        ) );

        // ── CPT: aura_mantenimiento ───────────────────────────────
        register_post_type( 'aura_mantenimiento', array(
            'labels' => array(
                'name'               => __( 'Mantenimientos',        'aura-suite' ),
                'singular_name'      => __( 'Mantenimiento',         'aura-suite' ),
                'add_new'            => __( 'Registrar mantenimiento', 'aura-suite' ),
                'add_new_item'       => __( 'Registrar mantenimiento', 'aura-suite' ),
                'edit_item'          => __( 'Editar mantenimiento',  'aura-suite' ),
                'view_item'          => __( 'Ver mantenimiento',     'aura-suite' ),
                'not_found'          => __( 'No se encontraron mantenimientos', 'aura-suite' ),
                'not_found_in_trash' => __( 'No hay mantenimientos en la papelera', 'aura-suite' ),
            ),
            'public'             => false,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'show_in_rest'       => false,
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'supports'           => array( 'title' ),
            'taxonomies'         => array( 'tipo_mantenimiento' ),
            'has_archive'        => false,
            'rewrite'            => false,
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // TAXONOMÍAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Registrar taxonomías del módulo
     */
    public static function register_taxonomies() {

        // ── Taxonomía: categoria_equipo ───────────────────────────
        register_taxonomy( 'categoria_equipo', array( 'aura_equipo' ), array(
            'labels' => array(
                'name'              => __( 'Categorías de Equipo',  'aura-suite' ),
                'singular_name'     => __( 'Categoría de Equipo',   'aura-suite' ),
                'search_items'      => __( 'Buscar categorías',     'aura-suite' ),
                'all_items'         => __( 'Todas las categorías',  'aura-suite' ),
                'edit_item'         => __( 'Editar categoría',      'aura-suite' ),
                'update_item'       => __( 'Actualizar categoría',  'aura-suite' ),
                'add_new_item'      => __( 'Agregar categoría',     'aura-suite' ),
                'new_item_name'     => __( 'Nueva categoría',       'aura-suite' ),
                'menu_name'         => __( 'Categorías',            'aura-suite' ),
            ),
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => false,
            'show_in_rest'      => false,
            'show_tagcloud'     => false,
            'rewrite'           => false,
        ) );

        // ── Taxonomía: estado_equipo ──────────────────────────────
        register_taxonomy( 'estado_equipo', array( 'aura_equipo' ), array(
            'labels' => array(
                'name'          => __( 'Estados de Equipo', 'aura-suite' ),
                'singular_name' => __( 'Estado de Equipo',  'aura-suite' ),
                'menu_name'     => __( 'Estados',           'aura-suite' ),
            ),
            'hierarchical'  => false,
            'public'        => false,
            'show_ui'       => false,
            'show_in_rest'  => false,
            'rewrite'       => false,
        ) );

        // ── Taxonomía: tipo_mantenimiento ─────────────────────────
        register_taxonomy( 'tipo_mantenimiento', array( 'aura_mantenimiento' ), array(
            'labels' => array(
                'name'          => __( 'Tipos de Mantenimiento', 'aura-suite' ),
                'singular_name' => __( 'Tipo de Mantenimiento',  'aura-suite' ),
                'menu_name'     => __( 'Tipos',                  'aura-suite' ),
            ),
            'hierarchical'  => false,
            'public'        => false,
            'show_ui'       => false,
            'show_in_rest'  => false,
            'rewrite'       => false,
        ) );
    }

    // ─────────────────────────────────────────────────────────────
    // CATEGORÍAS PREDETERMINADAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Insertar las categorías de equipo predeterminadas con sus
     * intervalos de mantenimiento recomendados.
     *
     * Es idempotente: usa term_exists() para no duplicar ninguna categoría.
     * Se puede llamar en cualquier momento; solo crea las que falten.
     */
    public static function install_default_categories() {
        $categories = array(
            array(
                'name'           => __( 'Motor 4 Tiempos',        'aura-suite' ),
                'slug'           => 'motor-4t',
                'interval_type'  => 'both',
                'interval_months'=> 3,
                'interval_hours' => 50,
                'description'    => __( 'Motores de 4 tiempos: cambio de aceite y filtro cada 3 meses o 50 horas, lo que ocurra primero.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Motor 2 Tiempos',        'aura-suite' ),
                'slug'           => 'motor-2t',
                'interval_type'  => 'both',
                'interval_months'=> 2,
                'interval_hours' => 25,
                'description'    => __( 'Motores de 2 tiempos: revisión carburador y bujía cada 2 meses o 25 horas.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Equipo Hidráulico',       'aura-suite' ),
                'slug'           => 'hidraulico',
                'interval_type'  => 'time',
                'interval_months'=> 6,
                'interval_hours' => null,
                'description'    => __( 'Equipos hidráulicos: revisión de fluidos y sellos cada 6 meses.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Compresor de Aire',       'aura-suite' ),
                'slug'           => 'compresor',
                'interval_type'  => 'time',
                'interval_months'=> 3,
                'interval_hours' => null,
                'description'    => __( 'Compresores de aire: drenaje de condensado, limpieza de filtros cada 3 meses.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Generador Eléctrico',     'aura-suite' ),
                'slug'           => 'generador',
                'interval_type'  => 'both',
                'interval_months'=> 6,
                'interval_hours' => 100,
                'description'    => __( 'Generadores eléctricos: cambio de aceite y revisión general cada 6 meses o 100 horas.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Herramienta Eléctrica',   'aura-suite' ),
                'slug'           => 'herramienta-electrica',
                'interval_type'  => 'time',
                'interval_months'=> 6,
                'interval_hours' => null,
                'description'    => __( 'Herramientas eléctricas: inspección de cables, escobillas y carcasa cada 6 meses.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Equipo de Sonido',        'aura-suite' ),
                'slug'           => 'sonido',
                'interval_type'  => 'time',
                'interval_months'=> 12,
                'interval_hours' => null,
                'description'    => __( 'Equipos de sonido: limpieza y calibración anual.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Herramienta de Batería',  'aura-suite' ),
                'slug'           => 'herramienta-bateria',
                'interval_type'  => 'time',
                'interval_months'=> 12,
                'interval_hours' => null,
                'description'    => __( 'Herramientas inalámbricas: revisión de baterías y cargadores anual.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Sistema de Riego',        'aura-suite' ),
                'slug'           => 'riego',
                'interval_type'  => 'time',
                'interval_months'=> 6,
                'interval_hours' => null,
                'description'    => __( 'Sistemas de riego: limpieza de filtros y revisión de aspersores cada 6 meses.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Mobiliario',              'aura-suite' ),
                'slug'           => 'mobiliario',
                'interval_type'  => 'time',
                'interval_months'=> 12,
                'interval_hours' => null,
                'description'    => __( 'Mobiliario: revisión de estado e inventario anual.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Equipo de Seguridad',     'aura-suite' ),
                'slug'           => 'seguridad',
                'interval_type'  => 'time',
                'interval_months'=> 12,
                'interval_hours' => null,
                'description'    => __( 'Equipos de seguridad (extintores, arneses, cascos): revisión y certificación anual.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Material de Limpieza',    'aura-suite' ),
                'slug'           => 'limpieza',
                'interval_type'  => 'none',
                'interval_months'=> null,
                'interval_hours' => null,
                'description'    => __( 'Materiales de limpieza: sin mantenimiento periódico programado.', 'aura-suite' ),
            ),
            array(
                'name'           => __( 'Accesorio / Componente',   'aura-suite' ),
                'slug'           => 'accesorio-componente',
                'interval_type'  => 'none',
                'interval_months'=> null,
                'interval_hours' => null,
                'description'    => __( 'Accesorios y componentes reemplazables (baterías, cargadores, piezas con vida útil propia). Se vinculan a un equipo padre mediante el campo Equipo padre.', 'aura-suite' ),
            ),
        );

        foreach ( $categories as $cat ) {
            // Verificar si ya existe el término
            if ( term_exists( $cat['slug'], 'categoria_equipo' ) ) {
                continue;
            }

            $result = wp_insert_term(
                $cat['name'],
                'categoria_equipo',
                array(
                    'slug'        => $cat['slug'],
                    'description' => $cat['description'],
                )
            );

            if ( ! is_wp_error( $result ) ) {
                $term_id = $result['term_id'];
                // Guardar metadatos de mantenimiento como term meta
                update_term_meta( $term_id, 'interval_type',   $cat['interval_type']   );
                update_term_meta( $term_id, 'interval_months', $cat['interval_months'] );
                update_term_meta( $term_id, 'interval_hours',  $cat['interval_hours']  );
            }
        }

        update_option( 'aura_inventory_categories_installed', AURA_VERSION );
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS PÚBLICOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener las 12 categorías con sus metadatos de mantenimiento.
     * Usado en formularios de equipos y en la página de configuración.
     *
     * @return array Array de objetos WP_Term con propiedades extra: interval_type, interval_months, interval_hours
     */
    public static function get_categories_with_intervals() {
        $terms = get_terms( array(
            'taxonomy'   => 'categoria_equipo',
            'hide_empty' => false,
            'orderby'    => 'name',
        ) );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        foreach ( $terms as &$term ) {
            $term->interval_type   = get_term_meta( $term->term_id, 'interval_type',   true );
            $term->interval_months = get_term_meta( $term->term_id, 'interval_months', true );
            $term->interval_hours  = get_term_meta( $term->term_id, 'interval_hours',  true );
        }

        return $terms;
    }

    /**
     * Obtener los nombres de las tablas del módulo
     *
     * @return array Asociativo con claves: equipment, maintenance, loans
     */
    public static function get_table_names() {
        global $wpdb;
        return array(
            'equipment'   => $wpdb->prefix . 'aura_inventory_equipment',
            'maintenance' => $wpdb->prefix . 'aura_inventory_maintenance',
            'loans'       => $wpdb->prefix . 'aura_inventory_loans',
        );
    }
}
