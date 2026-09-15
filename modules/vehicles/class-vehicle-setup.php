<?php
/**
 * Aura Vehicle Setup — Fase 1
 * Crea las tablas custom del módulo de Vehículos y siembra los catálogos por defecto.
 *
 * @package    Aura_Business_Suite
 * @subpackage Vehicles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Vehicle_Setup {

    const DB_VERSION        = '1.3.0';
    const DB_VERSION_OPTION = 'aura_vehicles_db_version';

    // ─────────────────────────────────────────────────────────────
    // INICIALIZACIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Enganchar en el ciclo de arranque del plugin.
     * Se llama desde el método init() del plugin principal.
     */
    public static function init() {
        if ( self::needs_update() ) {
            add_action( 'admin_init', array( __CLASS__, 'create_tables' ) );
        }
        // Registrar tamaños de imagen para la foto principal del vehículo
        if ( ! has_image_size( 'aura-equipment-full' ) ) {
            add_image_size( 'aura-equipment-full', 800, 600, true );
        }
        if ( ! has_image_size( 'aura-equipment-thumb' ) ) {
            add_image_size( 'aura-equipment-thumb', 220, 165, true );
        }
    }

    /**
     * Detectar si el esquema de BD necesita actualización.
     *
     * @return bool
     */
    public static function needs_update() {
        return version_compare( get_option( self::DB_VERSION_OPTION, '0' ), self::DB_VERSION, '<' );
    }

    // ─────────────────────────────────────────────────────────────
    // CREACIÓN DE TABLAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear (o actualizar) las 5 tablas del módulo de vehículos.
     * Seguro de llamar múltiples veces – dbDelta() es idempotente.
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_vehicles  = $wpdb->prefix . 'aura_vehicles';
        $t_area      = $wpdb->prefix . 'aura_vehicle_area';
        $t_trips     = $wpdb->prefix . 'aura_vehicle_trips';
        $t_catalogs  = $wpdb->prefix . 'aura_vehicle_catalogs';
        $t_audit     = $wpdb->prefix . 'aura_vehicle_audit';

        // ── Tabla: vehículos ──────────────────────────────────────
        $sql_vehicles = "CREATE TABLE {$t_vehicles} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            plate               VARCHAR(20)  NOT NULL,
            brand               VARCHAR(50)  NOT NULL,
            model               VARCHAR(50)  NOT NULL,
            year                SMALLINT UNSIGNED DEFAULT NULL,
            color               VARCHAR(30)  DEFAULT NULL,
            type                ENUM('sedan','suv','pickup','van','bus','motorcycle','truck','other') NOT NULL DEFAULT 'sedan',
            vin                 VARCHAR(17)  DEFAULT NULL,
            status              ENUM('available','rented','maintenance','unavailable') NOT NULL DEFAULT 'available',
            mileage             INT UNSIGNED NOT NULL DEFAULT 0,
            rate_per_km         DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            fuel_type           ENUM('gasoline','diesel','electric','hybrid','gas') NOT NULL DEFAULT 'gasoline',
            transmission        ENUM('manual','automatic') NOT NULL DEFAULT 'manual',
            notes               TEXT         DEFAULT NULL,
            photo               BIGINT(20) UNSIGNED DEFAULT NULL,
            photos              LONGTEXT     DEFAULT NULL,
            qr_token            VARCHAR(16)  DEFAULT NULL,
            qr_generated_at     DATETIME     DEFAULT NULL,
            next_maintenance_km INT UNSIGNED DEFAULT NULL,
            maintenance_alert_sent TINYINT(1) NOT NULL DEFAULT 0,
            unavailable_info    LONGTEXT     DEFAULT NULL,
            transfer_history    LONGTEXT     DEFAULT NULL,
            active              TINYINT(1)   NOT NULL DEFAULT 1,
            created_at          DATETIME     NOT NULL,
            updated_at          DATETIME     DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_plate  (plate),
            KEY idx_status       (status),
            KEY idx_active       (active)
        ) {$charset_collate};";

        // ── Tabla: pivote vehículo ↔ área ─────────────────────────
        $sql_area = "CREATE TABLE {$t_area} (
            vehicle_id          BIGINT(20) UNSIGNED NOT NULL,
            area_id             BIGINT(20) UNSIGNED NOT NULL,
            assigned_at         DATETIME     NOT NULL,
            assigned_by         BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (vehicle_id, area_id),
            KEY idx_area_id     (area_id),
            KEY idx_vehicle_id  (vehicle_id)
        ) {$charset_collate};";

        // ── Tabla: salidas / trips ────────────────────────────────
        $sql_trips = "CREATE TABLE {$t_trips} (
            id                      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vehicle_id              BIGINT(20) UNSIGNED NOT NULL,
            area_id                 BIGINT(20) UNSIGNED DEFAULT NULL,
            trip_type               ENUM('rental','errand','maintenance','other') NOT NULL DEFAULT 'errand',
            status                  ENUM('active','returned','cancelled') NOT NULL DEFAULT 'active',
            client_name             VARCHAR(150) DEFAULT NULL,
            client_phone            VARCHAR(30)  DEFAULT NULL,
            client_email            VARCHAR(150) DEFAULT NULL,
            client_document         VARCHAR(50)  DEFAULT NULL,
            rate_per_km             DECIMAL(10,2) DEFAULT NULL,
            responsible_name        VARCHAR(150) DEFAULT NULL,
            destination             VARCHAR(200) DEFAULT NULL,
            purpose                 VARCHAR(200) DEFAULT NULL,
            trip_description        TEXT         DEFAULT NULL,
            maint_subtype           VARCHAR(80)  DEFAULT NULL,
            maint_priority          ENUM('low','medium','high','critical') DEFAULT NULL,
            maint_description       TEXT         DEFAULT NULL,
            maint_provider          VARCHAR(150) DEFAULT NULL,
            maint_contact           VARCHAR(30)  DEFAULT NULL,
            maint_estimated_cost    DECIMAL(10,2) DEFAULT NULL,
            maint_actual_cost       DECIMAL(10,2) DEFAULT NULL,
            maint_completion_notes  TEXT         DEFAULT NULL,
            departure_datetime      DATETIME     NOT NULL,
            departure_odometer      INT UNSIGNED NOT NULL DEFAULT 0,
            departure_fuel          TINYINT UNSIGNED NOT NULL DEFAULT 100,
            return_datetime         DATETIME     DEFAULT NULL,
            return_odometer         INT UNSIGNED NOT NULL DEFAULT 0,
            return_fuel             TINYINT UNSIGNED DEFAULT NULL,
            km_traveled             INT UNSIGNED NOT NULL DEFAULT 0,
            total_amount            DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            additional_charges      DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            discounts               DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            total_expenses          DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            expenses_detail         LONGTEXT     DEFAULT NULL,
            cancellation_reason     TEXT         DEFAULT NULL,
            assigned_to             BIGINT(20) UNSIGNED DEFAULT NULL,
            created_by              BIGINT(20) UNSIGNED NOT NULL,
            created_at              DATETIME     NOT NULL,
            updated_at              DATETIME     DEFAULT NULL,
            deleted                 TINYINT(1)   NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_vehicle         (vehicle_id),
            KEY idx_area            (area_id),
            KEY idx_status          (status),
            KEY idx_trip_type       (trip_type),
            KEY idx_departure       (departure_datetime),
            KEY idx_deleted         (deleted)
        ) {$charset_collate};";

        // ── Tabla: catálogos configurables ────────────────────────
        $sql_catalogs = "CREATE TABLE {$t_catalogs} (
            id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type                ENUM('destination','purpose','expense') NOT NULL,
            name                VARCHAR(150) NOT NULL,
            description         VARCHAR(300) DEFAULT NULL,
            icon                VARCHAR(50)  DEFAULT NULL,
            active              TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order          SMALLINT     NOT NULL DEFAULT 0,
            area_id             BIGINT(20) UNSIGNED DEFAULT NULL,
            created_by          BIGINT(20) UNSIGNED NOT NULL,
            created_at          DATETIME     NOT NULL,
            updated_at          DATETIME     DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_type        (type),
            KEY idx_area        (area_id),
            KEY idx_active      (active)
        ) {$charset_collate};";

        // ── Tabla: auditoría del módulo ───────────────────────────
        $sql_audit = "CREATE TABLE {$t_audit} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            operation           VARCHAR(60)  NOT NULL,
            entity_type         VARCHAR(30)  DEFAULT NULL,
            entity_id           BIGINT(20) UNSIGNED DEFAULT NULL,
            user_id             BIGINT(20) UNSIGNED DEFAULT NULL,
            ip_address          VARCHAR(45)  DEFAULT NULL,
            user_agent          VARCHAR(300) DEFAULT NULL,
            details             LONGTEXT     DEFAULT NULL,
            created_at          DATETIME     NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_operation   (operation),
            KEY idx_entity      (entity_type, entity_id),
            KEY idx_user        (user_id),
            KEY idx_created     (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_vehicles );
        dbDelta( $sql_area );
        dbDelta( $sql_trips );
        dbDelta( $sql_catalogs );
        dbDelta( $sql_audit );

        // Guardar versión de BD
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

        // Sembrar catálogos globales si la tabla está vacía
        self::seed_default_catalogs();
    }

    // ─────────────────────────────────────────────────────────────
    // DATOS SEMILLA
    // ─────────────────────────────────────────────────────────────

    /**
     * Insertar catálogos globales por defecto.
     * Idempotente: no inserta nada si la tabla ya tiene registros.
     */
    public static function seed_default_catalogs() {
        global $wpdb;

        $table = $wpdb->prefix . 'aura_vehicle_catalogs';

        // Solo sembrar si la tabla está vacía
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $count > 0 ) {
            return;
        }

        $now        = current_time( 'mysql' );
        $admin_id   = (int) get_current_user_id();
        if ( $admin_id === 0 ) {
            $admin_id = 1;
        }

        $items = array(
            // Destinos
            array( 'destination', 'Sede Central',              1 ),
            array( 'destination', 'Aeropuerto',                2 ),
            array( 'destination', 'Taller',                    3 ),
            array( 'destination', 'Puerto',                    4 ),
            array( 'destination', 'Terminal',                  5 ),
            array( 'destination', 'Hospital',                  6 ),
            array( 'destination', 'Otro',                      7 ),
            // Propósitos
            array( 'purpose',     'Reunión de trabajo',        1 ),
            array( 'purpose',     'Entrega de documentos',     2 ),
            array( 'purpose',     'Comisión oficial',          3 ),
            array( 'purpose',     'Transporte de personal',    4 ),
            array( 'purpose',     'Diligencias administrativas', 5 ),
            array( 'purpose',     'Otro',                      6 ),
            // Gastos
            array( 'expense',     'Combustible',               1 ),
            array( 'expense',     'Peaje',                     2 ),
            array( 'expense',     'Parqueadero',               3 ),
            array( 'expense',     'Lavado',                    4 ),
            array( 'expense',     'Aceite/Fluidos',            5 ),
            array( 'expense',     'Reparación imprevista',     6 ),
            array( 'expense',     'Otro',                      7 ),
        );

        foreach ( $items as $item ) {
            $wpdb->insert(
                $table,
                array(
                    'type'       => $item[0],
                    'name'       => $item[1],
                    'sort_order' => $item[2],
                    'active'     => 1,
                    'area_id'    => null,
                    'created_by' => $admin_id,
                    'created_at' => $now,
                ),
                array( '%s', '%s', '%d', '%d', null, '%d', '%s' )
            );
        }
    }
}
