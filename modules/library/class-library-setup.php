<?php
/**
 * Aura Library Setup — Fase 1
 * Crea las tablas custom del módulo de Biblioteca usando dbDelta().
 *
 * Tablas:
 *   wp_aura_library_books         — Catálogo de libros
 *   wp_aura_library_loans         — Préstamos y devoluciones
 *   wp_aura_library_reservations  — Cola de reservas
 *   wp_aura_library_audit         — Log de auditoría
 *
 * @package    Aura_Business_Suite
 * @subpackage Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Library_Setup {

    const DB_VERSION        = '1.0.0';
    const DB_VERSION_OPTION = 'aura_library_db_version';

    // ─────────────────────────────────────────────────────────────
    // INICIALIZACIÓN
    // ─────────────────────────────────────────────────────────────

    /**
     * Enganchar en el ciclo de arranque del plugin.
     */
    public static function init() {
        if ( self::needs_update() ) {
            add_action( 'admin_init', array( __CLASS__, 'create_tables' ) );
        }
    }

    /**
     * Detectar si el esquema de BD necesita ser creado/actualizado.
     *
     * @return bool
     */
    public static function needs_update(): bool {
        return version_compare( get_option( self::DB_VERSION_OPTION, '0' ), self::DB_VERSION, '<' );
    }

    // ─────────────────────────────────────────────────────────────
    // CREACIÓN DE TABLAS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear (o actualizar) las 4 tablas del módulo.
     * Seguro de llamar múltiples veces — dbDelta() es idempotente.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_books         = $wpdb->prefix . 'aura_library_books';
        $t_loans         = $wpdb->prefix . 'aura_library_loans';
        $t_reservations  = $wpdb->prefix . 'aura_library_reservations';
        $t_audit         = $wpdb->prefix . 'aura_library_audit';

        // ── Tabla: libros ─────────────────────────────────────────
        // dbDelta requiere exactamente 2 espacios antes de los nombres de columna
        // y KEY separado de PRIMARY KEY.
        $sql_books = "CREATE TABLE {$t_books} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dewey_number VARCHAR(30) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255) DEFAULT NULL,
  author VARCHAR(255) NOT NULL,
  isbn VARCHAR(30) DEFAULT NULL,
  publisher VARCHAR(150) DEFAULT NULL,
  year_published YEAR DEFAULT NULL,
  edition VARCHAR(50) DEFAULT NULL,
  language VARCHAR(50) NOT NULL DEFAULT 'Español',
  pages SMALLINT UNSIGNED DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  subcategory VARCHAR(100) DEFAULT NULL,
  physical_location VARCHAR(100) DEFAULT NULL,
  shelf_code VARCHAR(50) DEFAULT NULL,
  total_copies TINYINT UNSIGNED NOT NULL DEFAULT 1,
  available_copies TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cover_image_id BIGINT UNSIGNED DEFAULT NULL,
  description TEXT DEFAULT NULL,
  keywords TEXT DEFAULT NULL,
  area_id BIGINT UNSIGNED DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'available',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY dewey_number (dewey_number),
  KEY isbn (isbn),
  KEY status (status),
  KEY area_id (area_id),
  KEY available_copies (available_copies)
) {$charset_collate};";

        // ── Tabla: préstamos ──────────────────────────────────────
        $sql_loans = "CREATE TABLE {$t_loans} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id BIGINT UNSIGNED NOT NULL,
  borrower_user_id BIGINT UNSIGNED NOT NULL,
  loan_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE DEFAULT NULL,
  extended_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  notes TEXT DEFAULT NULL,
  registered_by BIGINT UNSIGNED NOT NULL,
  return_registered_by BIGINT UNSIGNED DEFAULT NULL,
  fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  fine_paid TINYINT(1) NOT NULL DEFAULT 0,
  fine_transaction_id BIGINT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY book_id (book_id),
  KEY borrower_user_id (borrower_user_id),
  KEY status (status),
  KEY due_date (due_date)
) {$charset_collate};";

        // ── Tabla: reservas ───────────────────────────────────────
        $sql_reservations = "CREATE TABLE {$t_reservations} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  book_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reserved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notified_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'waiting',
  notes TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY book_id (book_id),
  KEY user_id (user_id),
  KEY status (status)
) {$charset_collate};";

        // ── Tabla: auditoría ──────────────────────────────────────
        $sql_audit = "CREATE TABLE {$t_audit} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  old_data LONGTEXT DEFAULT NULL,
  new_data LONGTEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY action (action),
  KEY created_at (created_at)
) {$charset_collate};";

        dbDelta( $sql_books );
        dbDelta( $sql_loans );
        dbDelta( $sql_reservations );
        dbDelta( $sql_audit );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    // ─────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────

    /**
     * Log de auditoría — inserta un registro en wp_aura_library_audit.
     *
     * @param string    $action       Acción realizada (ej. 'create_book').
     * @param string    $entity_type  Tipo de entidad ('book', 'loan', etc.).
     * @param int       $entity_id    ID del registro afectado.
     * @param array     $old_data     Datos anteriores (se codifican como JSON).
     * @param array     $new_data     Datos nuevos (se codifican como JSON).
     */
    public static function log(
        string $action,
        string $entity_type,
        int    $entity_id,
        array  $old_data = [],
        array  $new_data = []
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'aura_library_audit',
            array(
                'user_id'     => get_current_user_id(),
                'action'      => $action,
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'old_data'    => ! empty( $old_data ) ? wp_json_encode( $old_data ) : null,
                'new_data'    => ! empty( $new_data ) ? wp_json_encode( $new_data ) : null,
                'ip_address'  => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );
    }
}
