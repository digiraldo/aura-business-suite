<?php
/**
 * Aura Contacts Setup — Fase 1
 * Crea y actualiza las tablas personalizadas de la base de datos para el Módulo de Contactos:
 *   1. wp_aura_contacts             — Directorio maestro de personas/empresas
 *   2. wp_aura_contacts_google_map  — Mapeo decoupled con Google People API por cuenta
 *   3. wp_aura_contacts_sync_log    — Registro y auditoría de sincronizaciones push/pull
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Contacts_Setup {

    const DB_VERSION        = '1.0.0';
    const DB_VERSION_OPTION = 'aura_contacts_db_version';

    /**
     * Enganchar en el ciclo de arranque de WordPress / Aura Suite.
     */
    public static function init() {
        if ( self::needs_update() ) {
            add_action( 'admin_init', array( __CLASS__, 'create_tables' ) );
        }
    }

    /**
     * Verifica si se requiere ejecutar dbDelta para actualizar esquemas.
     *
     * @return bool
     */
    public static function needs_update(): bool {
        return version_compare( get_option( self::DB_VERSION_OPTION, '0' ), self::DB_VERSION, '<' );
    }

    /**
     * Crea o actualiza las tablas del módulo usando dbDelta().
     * Idempotente y seguro de llamar en cualquier momento.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';
        $t_sync_log   = $wpdb->prefix . 'aura_contacts_sync_log';

        // ── 1. Tabla: Directorio Maestro de Contactos ──────────────────
        // dbDelta requiere exactamente 2 espacios antes de cada definición de columna
        // y dos espacios en PRIMARY KEY  (id)
        $sql_contacts = "CREATE TABLE {$t_contacts} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wp_user_id BIGINT UNSIGNED DEFAULT NULL,
  third_party_id BIGINT UNSIGNED DEFAULT NULL,
  student_id BIGINT UNSIGNED DEFAULT NULL,
  contact_type ENUM('person','company','organization') NOT NULL DEFAULT 'person',
  first_name VARCHAR(255) NOT NULL DEFAULT '',
  last_name VARCHAR(255) NOT NULL DEFAULT '',
  organization VARCHAR(255) DEFAULT NULL,
  job_title VARCHAR(255) DEFAULT NULL,
  email_primary VARCHAR(255) DEFAULT NULL,
  email_secondary VARCHAR(255) DEFAULT NULL,
  phone_primary VARCHAR(50) DEFAULT NULL,
  phone_secondary VARCHAR(50) DEFAULT NULL,
  whatsapp VARCHAR(50) DEFAULT NULL,
  address_street TEXT DEFAULT NULL,
  address_city VARCHAR(255) DEFAULT NULL,
  address_state VARCHAR(255) DEFAULT NULL,
  address_country VARCHAR(100) DEFAULT NULL,
  address_postal VARCHAR(20) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  tags TEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  avatar_url VARCHAR(500) DEFAULT NULL,
  sync_status ENUM('synced','pending','error','local_only') NOT NULL DEFAULT 'local_only',
  sync_error TEXT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY idx_wp_user (wp_user_id),
  KEY idx_third_party (third_party_id),
  KEY idx_student (student_id),
  KEY idx_email (email_primary),
  KEY idx_category (category),
  KEY idx_sync_status (sync_status),
  KEY idx_active (is_active)
) {$charset_collate};";

        // ── 2. Tabla: Mapeo de Google People API decoupled por cuenta ──
        $sql_google_map = "CREATE TABLE {$t_google_map} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id BIGINT UNSIGNED NOT NULL,
  google_account VARCHAR(255) NOT NULL DEFAULT '',
  resource_name VARCHAR(255) NOT NULL DEFAULT '',
  google_etag VARCHAR(255) DEFAULT NULL,
  last_synced_at DATETIME DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_contact_account (contact_id, google_account),
  UNIQUE KEY uq_resource_account (resource_name, google_account),
  KEY idx_account (google_account),
  KEY idx_contact (contact_id)
) {$charset_collate};";

        // ── 3. Tabla: Historial y Auditoría de Sincronización ──────────
        $sql_sync_log = "CREATE TABLE {$t_sync_log} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id BIGINT UNSIGNED DEFAULT NULL,
  google_account VARCHAR(255) DEFAULT NULL,
  direction ENUM('push','pull') NOT NULL,
  action ENUM('create','update','delete','skip') NOT NULL,
  status ENUM('success','error') NOT NULL,
  message TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_contact (contact_id),
  KEY idx_status (status),
  KEY idx_created (created_at)
) {$charset_collate};";

        dbDelta( $sql_contacts );
        dbDelta( $sql_google_map );
        dbDelta( $sql_sync_log );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Registra un evento en la tabla de log de sincronización.
     *
     * @param int|null    $contact_id
     * @param string|null $google_account
     * @param string      $direction 'push'|'pull'
     * @param string      $action    'create'|'update'|'delete'|'skip'
     * @param string      $status    'success'|'error'
     * @param string|null $message
     * @return int|false
     */
    public static function log_sync( ?int $contact_id, ?string $google_account, string $direction, string $action, string $status, ?string $message = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_contacts_sync_log';

        return $wpdb->insert(
            $table,
            array(
                'contact_id'     => $contact_id ?: null,
                'google_account' => $google_account,
                'direction'      => $direction,
                'action'         => $action,
                'status'         => $status,
                'message'        => $message,
                'created_at'     => current_time( 'mysql' ),
            )
        );
    }
}
