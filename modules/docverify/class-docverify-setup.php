<?php
/**
 * Setup del Módulo de Validación Documental (QR, SHA-256 y Verificación Consular)
 *
 * Responsabilidades:
 *  - Crear / migrar las 3 tablas de BD con dbDelta()
 *  - Proteger el directorio local de almacenamiento en uploads/aura-docverify/
 *  - Registrar rewrite rules para verificación pública (/verificar-documento/{folio})
 *
 * @package AuraBusinessSuite
 * @subpackage DocVerify
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_DocVerify_Setup {

    /** Versión del esquema de BD del módulo */
    const DB_VERSION        = '1.0.0';
    const DB_VERSION_OPTION = 'aura_docverify_db_version';

    /**
     * Inicializar hooks del setup.
     */
    public static function init(): void {
        if ( self::needs_update() ) {
            add_action( 'admin_init', [ __CLASS__, 'create_tables' ] );
        }

        add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
    }

    /**
     * Comprobar si el esquema requiere actualización.
     */
    public static function needs_update(): bool {
        return version_compare(
            (string) get_option( self::DB_VERSION_OPTION, '0' ),
            self::DB_VERSION,
            '<'
        );
    }

    /**
     * Crear o actualizar las 3 tablas del módulo y el directorio privado local.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_docs = $wpdb->prefix . 'aura_verified_documents';
        $t_seq  = $wpdb->prefix . 'aura_docverify_folio_seq';
        $t_logs = $wpdb->prefix . 'aura_docverify_logs';

        $sql_docs = "CREATE TABLE {$t_docs} (
            id                      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            folio                   VARCHAR(50) NOT NULL,
            verification_code       VARCHAR(32) NOT NULL,
            doc_type                VARCHAR(40) NOT NULL DEFAULT 'official_letter',
            title_subject           VARCHAR(300) NOT NULL,
            doc_language            VARCHAR(15) NOT NULL DEFAULT 'es',
            recipient_entity        VARCHAR(250) DEFAULT NULL,
            recipient_country       VARCHAR(120) DEFAULT NULL,
            purpose                 VARCHAR(300) DEFAULT NULL,
            holder_name             VARCHAR(250) NOT NULL DEFAULT '',
            holder_doc_type         VARCHAR(40) NOT NULL DEFAULT 'none',
            holder_doc_masked       VARCHAR(80) DEFAULT NULL,
            holder_doc_hash         CHAR(64) DEFAULT NULL,
            travel_or_event_start   DATE DEFAULT NULL,
            travel_or_event_end     DATE DEFAULT NULL,
            signer_id               BIGINT(20) UNSIGNED DEFAULT NULL,
            signer_name             VARCHAR(200) NOT NULL DEFAULT '',
            signer_title            VARCHAR(200) NOT NULL DEFAULT '',
            signer_email            VARCHAR(200) DEFAULT NULL,
            issued_at               DATE NOT NULL,
            expires_at              DATE DEFAULT NULL,
            storage_driver          VARCHAR(20) NOT NULL DEFAULT 'local',
            original_pdf_path       VARCHAR(500) DEFAULT NULL,
            drive_original_file_id  VARCHAR(150) DEFAULT NULL,
            drive_original_url      VARCHAR(500) DEFAULT NULL,
            original_pdf_name       VARCHAR(255) DEFAULT NULL,
            original_pdf_size       BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            sha256_original         CHAR(64) NOT NULL DEFAULT '',
            stamped_pdf_path        VARCHAR(500) DEFAULT NULL,
            drive_stamped_file_id   VARCHAR(150) DEFAULT NULL,
            drive_stamped_url       VARCHAR(500) DEFAULT NULL,
            sha256_stamped          CHAR(64) DEFAULT NULL,
            stamp_mode              VARCHAR(25) NOT NULL DEFAULT 'annex_page',
            stamp_position          VARCHAR(25) NOT NULL DEFAULT 'bottom_right',
            qr_png_path             VARCHAR(500) DEFAULT NULL,
            qr_svg_path             VARCHAR(500) DEFAULT NULL,
            verify_url              VARCHAR(500) DEFAULT NULL,
            privacy_mode            VARCHAR(25) NOT NULL DEFAULT 'standard_masked',
            access_pin_hash         VARCHAR(255) DEFAULT NULL,
            allow_public_pdf        TINYINT(1) NOT NULL DEFAULT 0,
            status                  VARCHAR(20) NOT NULL DEFAULT 'active',
            revoked_at              DATETIME DEFAULT NULL,
            revoked_by              BIGINT(20) UNSIGNED DEFAULT NULL,
            revoke_reason           VARCHAR(500) DEFAULT NULL,
            verified_count          INT(10) UNSIGNED NOT NULL DEFAULT 0,
            last_verified_at        DATETIME DEFAULT NULL,
            internal_notes          TEXT DEFAULT NULL,
            created_by              BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_folio             (folio),
            UNIQUE KEY uq_verification_code (verification_code),
            KEY idx_doc_type                (doc_type),
            KEY idx_status                  (status),
            KEY idx_issued_at               (issued_at),
            KEY idx_expires_at              (expires_at),
            KEY idx_sha256_orig             (sha256_original),
            KEY idx_sha256_stamp            (sha256_stamped),
            KEY idx_storage_driver          (storage_driver)
        ) ENGINE=InnoDB {$charset_collate};";

        $sql_seq = "CREATE TABLE {$t_seq} (
            seq_key     VARCHAR(32) NOT NULL,
            last_seq    INT(10) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (seq_key)
        ) ENGINE=InnoDB {$charset_collate};";

        $sql_logs = "CREATE TABLE {$t_logs} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id         BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            folio_queried       VARCHAR(50) DEFAULT NULL,
            event_type          VARCHAR(40) NOT NULL,
            result              VARCHAR(30) NOT NULL DEFAULT 'ok',
            ip_hash             CHAR(64) DEFAULT NULL,
            user_agent_short    VARCHAR(180) DEFAULT NULL,
            wp_user_id          BIGINT(20) UNSIGNED DEFAULT NULL,
            meta_json           TEXT DEFAULT NULL,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_document_id     (document_id),
            KEY idx_event_type      (event_type),
            KEY idx_created_at      (created_at)
        ) ENGINE=InnoDB {$charset_collate};";

        dbDelta( $sql_docs );
        dbDelta( $sql_seq );
        dbDelta( $sql_logs );

        self::ensure_protected_storage_dir();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Garantiza el directorio base local de Validación Documental con .htaccess restrictivo.
     */
    public static function get_storage_base_dir(): string {
        $upload_dir = wp_upload_dir();
        $base       = trailingslashit( $upload_dir['basedir'] ) . 'aura-docverify';
        return $base;
    }

    /**
     * Crea y protege el directorio privado local (para fallback y cachés QR).
     */
    public static function ensure_protected_storage_dir(): string {
        $base = self::get_storage_base_dir();
        $dirs = [
            $base,
            $base . '/pdf',
            $base . '/qr',
            $base . '/tmp',
        ];

        foreach ( $dirs as $d ) {
            if ( ! file_exists( $d ) ) {
                wp_mkdir_p( $d );
            }
            $index = $d . '/index.php';
            if ( ! file_exists( $index ) ) {
                @file_put_contents( $index, "<?php // Silence is golden.\n" );
            }
        }

        // Bloquear acceso HTTP directo a PDFs privados y temporales
        $deny_rules = "# Aura DocVerify Private Storage\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\nOptions -Indexes\n";
        foreach ( [ $base . '/pdf', $base . '/tmp' ] as $priv_dir ) {
            $htaccess = $priv_dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                @file_put_contents( $htaccess, $deny_rules );
            }
        }

        // El directorio base y /qr solo deshabilitan listado de directorios
        $qr_htaccess = $base . '/qr/.htaccess';
        if ( ! file_exists( $qr_htaccess ) ) {
            @file_put_contents( $qr_htaccess, "Options -Indexes\n" );
        }

        return $base;
    }

    /**
     * Registrar reglas de reescritura para URL limpia /{verify_slug}/{folio}.
     */
    public static function register_rewrite_rules(): void {
        $slug = class_exists( 'Aura_DocVerify_Settings' )
            ? Aura_DocVerify_Settings::get( 'verify_slug', 'verificar-documento' )
            : 'verificar-documento';
        $slug = sanitize_title( (string) $slug ) ?: 'verificar-documento';

        add_rewrite_rule(
            '^' . preg_quote( $slug, '^' ) . '/([A-Za-z0-9\-]+)/?$',
            'index.php?aura_doc_verify=$matches[1]',
            'top'
        );
        add_rewrite_tag( '%aura_doc_verify%', '([A-Za-z0-9\-]+)' );
    }
}
