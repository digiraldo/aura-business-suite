<?php
/**
 * Setup del Módulo de Certificados y Diplomas
 *
 * Responsabilidades:
 *  - Crear / migrar las 4 tablas de BD del módulo con dbDelta()
 *  - Registrar rewrite rules para verificación pública
 *  - Registrar cron de procesamiento de emisión masiva
 *  - Escuchar el hook aura_student_graduated para activar el botón "Emitir"
 *
 * @package AuraBusinessSuite
 * @subpackage Certificates
 * @since 1.7.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Certificates_Setup {

    /** Versión del esquema de BD del módulo */
    const DB_VERSION        = '1.1.0';
    const DB_VERSION_OPTION = 'aura_certificates_db_version';

    // ─────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────

    public static function init(): void {
        // Migración automática si la versión cambió
        if ( self::needs_update() ) {
            add_action( 'admin_init', [ __CLASS__, 'create_tables' ] );
        }

        // Rewrite rules para verificación pública
        add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );

        // Cron de procesamiento de cola masiva (cada 2 minutos aprox.)
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );

        if ( ! wp_next_scheduled( 'aura_certs_bulk_process' ) ) {
            wp_schedule_event( time(), 'aura_two_minutes', 'aura_certs_bulk_process' );
        }

        // Escuchar graduación de estudiante
        add_action( 'aura_student_graduated', [ __CLASS__, 'on_student_graduated' ], 10, 2 );
    }

    // ─────────────────────────────────────────────────────────────
    // COMPROBACIÓN DE VERSIÓN
    // ─────────────────────────────────────────────────────────────

    public static function needs_update(): bool {
        return version_compare(
            get_option( self::DB_VERSION_OPTION, '0' ),
            self::DB_VERSION,
            '<'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // BASE DE DATOS
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear o actualizar las 4 tablas del módulo.
     * Idempotente: dbDelta() solo agrega columnas/índices faltantes.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t_templates  = $wpdb->prefix . 'aura_certificate_templates';
        $t_signers    = $wpdb->prefix . 'aura_certificate_signers';
        $t_folio_seq  = $wpdb->prefix . 'aura_certificate_folio_seq';
        $t_certs      = $wpdb->prefix . 'aura_certificates';

        // ── 1. Plantillas de diseño ───────────────────────────────
        $sql_templates = "CREATE TABLE {$t_templates} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name            VARCHAR(200) NOT NULL,
            slug            VARCHAR(200) NOT NULL,
            description     TEXT DEFAULT NULL,
            orientation     VARCHAR(30) NOT NULL DEFAULT 'landscape',
            width_mm        DECIMAL(6,2) NOT NULL DEFAULT '297.00',
            height_mm       DECIMAL(6,2) NOT NULL DEFAULT '210.00',
            design_json     LONGTEXT NOT NULL,
            thumbnail_url   VARCHAR(500) DEFAULT NULL,
            is_default      TINYINT(1) NOT NULL DEFAULT 0,
            is_active       TINYINT(1) NOT NULL DEFAULT 1,
            created_by      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_slug (slug),
            KEY idx_active  (is_active),
            KEY idx_default (is_default)
        ) ENGINE=InnoDB {$charset_collate};";

        // ── 2. Firmantes registrados ──────────────────────────────
        $sql_signers = "CREATE TABLE {$t_signers} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name            VARCHAR(200) NOT NULL,
            title           VARCHAR(200) NOT NULL,
            signature_url   VARCHAR(500) DEFAULT NULL,
            attachment_id   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            wp_user_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            third_party_id  BIGINT(20) UNSIGNED DEFAULT NULL,
            is_active       TINYINT(1) NOT NULL DEFAULT 1,
            sort_order      TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            created_by      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_wp_user (wp_user_id),
            KEY idx_third_party (third_party_id)
        ) ENGINE=InnoDB {$charset_collate};";

        // ── 3. Secuencia de folios ────────────────────────────────
        $sql_folio_seq = "CREATE TABLE {$t_folio_seq} (
            year            YEAR NOT NULL,
            last_seq        INT(10) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (year)
        ) ENGINE=InnoDB {$charset_collate};";

        // ── 4. Certificados emitidos ──────────────────────────────
        $sql_certs = "CREATE TABLE {$t_certs} (
            id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id          BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            enrollment_id       BIGINT(20) UNSIGNED DEFAULT NULL,
            recipient_type      VARCHAR(20) NOT NULL DEFAULT 'student',
            wp_user_id          BIGINT(20) UNSIGNED DEFAULT NULL,
            third_party_id      BIGINT(20) UNSIGNED DEFAULT NULL,
            recipient_email     VARCHAR(200) DEFAULT NULL,
            recipient_document  VARCHAR(100) DEFAULT NULL,
            template_id         BIGINT(20) UNSIGNED NOT NULL,
            folio               VARCHAR(50) NOT NULL,
            uuid                VARCHAR(36) DEFAULT NULL,
            verification_code   VARCHAR(36) NOT NULL DEFAULT '',
            student_name        VARCHAR(300) NOT NULL DEFAULT '',
            course_name         VARCHAR(300) NOT NULL,
            program_name        VARCHAR(300) DEFAULT NULL,
            category            VARCHAR(150) DEFAULT NULL,
            hours               VARCHAR(100) DEFAULT NULL,
            grade               VARCHAR(100) DEFAULT NULL,
            city                VARCHAR(150) DEFAULT NULL,
            instructor_name     VARCHAR(300) DEFAULT NULL,
            organization_name   VARCHAR(300) DEFAULT NULL,
            graduation_date     DATE DEFAULT NULL,
            issued_at           DATETIME NOT NULL,
            pdf_path            VARCHAR(500) DEFAULT NULL,
            qr_path             VARCHAR(500) DEFAULT NULL,
            verify_url          VARCHAR(500) DEFAULT NULL,
            send_email          TINYINT(1) NOT NULL DEFAULT 0,
            send_whatsapp       TINYINT(1) NOT NULL DEFAULT 0,
            include_signatures  TINYINT(1) NOT NULL DEFAULT 1,
            signers_json        TEXT DEFAULT NULL,
            description         TEXT DEFAULT NULL,
            issued_by           BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status              VARCHAR(10) NOT NULL DEFAULT 'active',
            revoked_at          DATETIME DEFAULT NULL,
            revoked_by          BIGINT(20) UNSIGNED DEFAULT NULL,
            revoke_reason       VARCHAR(500) DEFAULT NULL,
            notes               TEXT DEFAULT NULL,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_folio         (folio),
            UNIQUE KEY uq_verify_code   (verification_code),
            KEY idx_uuid        (uuid),
            KEY idx_student     (student_id),
            KEY idx_wp_user     (wp_user_id),
            KEY idx_third_party (third_party_id),
            KEY idx_enrollment  (enrollment_id),
            KEY idx_template    (template_id),
            KEY idx_status      (status),
            KEY idx_issued_at   (issued_at)
        ) ENGINE=InnoDB {$charset_collate};";

        dbDelta( $sql_templates );
        dbDelta( $sql_signers );
        dbDelta( $sql_folio_seq );
        dbDelta( $sql_certs );

        self::ensure_migrated_columns( $t_templates, $t_signers, $t_certs );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Garantiza columnas y longitudes exactas en instalaciones previas.
     */
    private static function ensure_migrated_columns( string $t_templates, string $t_signers, string $t_certs ): void {
        global $wpdb;

        // Ampliar orientation para soportar letter_landscape / letter_portrait
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "ALTER TABLE {$t_templates} MODIFY COLUMN orientation VARCHAR(30) NOT NULL DEFAULT 'landscape'" );

        // Signers: asegurar attachment_id, wp_user_id, third_party_id y default en created_by
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $signer_cols = $wpdb->get_col( "DESCRIBE {$t_signers}", 0 ) ?: [];
        if ( ! in_array( 'attachment_id', $signer_cols, true ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE {$t_signers} ADD COLUMN attachment_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER signature_url" );
        }
        if ( ! in_array( 'wp_user_id', $signer_cols, true ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE {$t_signers} ADD COLUMN wp_user_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER attachment_id" );
        }
        if ( ! in_array( 'third_party_id', $signer_cols, true ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE {$t_signers} ADD COLUMN third_party_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER wp_user_id" );
        }

        // Certs: asegurar columnas usadas por Issuer + Terceros/Usuarios WP + variables extras
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cert_cols = $wpdb->get_col( "DESCRIBE {$t_certs}", 0 ) ?: [];
        $alter_certs = [
            'recipient_type'     => "ADD COLUMN recipient_type VARCHAR(20) NOT NULL DEFAULT 'student' AFTER enrollment_id",
            'wp_user_id'         => "ADD COLUMN wp_user_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER recipient_type",
            'third_party_id'     => "ADD COLUMN third_party_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER wp_user_id",
            'recipient_email'    => "ADD COLUMN recipient_email VARCHAR(200) DEFAULT NULL AFTER third_party_id",
            'recipient_document' => "ADD COLUMN recipient_document VARCHAR(100) DEFAULT NULL AFTER recipient_email",
            'uuid'               => "ADD COLUMN uuid VARCHAR(36) DEFAULT NULL AFTER folio",
            'category'           => "ADD COLUMN category VARCHAR(150) DEFAULT NULL AFTER program_name",
            'hours'              => "ADD COLUMN hours VARCHAR(100) DEFAULT NULL AFTER category",
            'grade'              => "ADD COLUMN grade VARCHAR(100) DEFAULT NULL AFTER hours",
            'city'               => "ADD COLUMN city VARCHAR(150) DEFAULT NULL AFTER grade",
            'qr_path'            => "ADD COLUMN qr_path VARCHAR(500) DEFAULT NULL AFTER pdf_path",
            'verify_url'         => "ADD COLUMN verify_url VARCHAR(500) DEFAULT NULL AFTER qr_path",
            'send_email'         => "ADD COLUMN send_email TINYINT(1) NOT NULL DEFAULT 0 AFTER verify_url",
            'send_whatsapp'      => "ADD COLUMN send_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER send_email",
        ];
        foreach ( $alter_certs as $col => $ddl ) {
            if ( ! in_array( $col, $cert_cols, true ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query( "ALTER TABLE {$t_certs} {$ddl}" );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REWRITE RULES — Verificación pública
    // ─────────────────────────────────────────────────────────────

    public static function register_rewrite_rules(): void {
        $slug = Aura_Certificates_Settings::get( 'verify_slug', 'verificar-certificado' );
        $slug = sanitize_title( $slug );

        add_rewrite_rule(
            '^' . preg_quote( $slug, '^' ) . '/([A-Za-z0-9\-]+)/?$',
            'index.php?aura_cert_verify=$matches[1]',
            'top'
        );
        add_rewrite_tag( '%aura_cert_verify%', '([A-Za-z0-9\-]+)' );
    }

    // ─────────────────────────────────────────────────────────────
    // CRON — Intervalo personalizado de 2 minutos
    // ─────────────────────────────────────────────────────────────

    public static function add_cron_interval( array $schedules ): array {
        if ( ! isset( $schedules['aura_two_minutes'] ) ) {
            $schedules['aura_two_minutes'] = [
                'interval' => 120,
                'display'  => __( 'Cada 2 minutos (AURA Certificados)', 'aura-suite' ),
            ];
        }
        return $schedules;
    }

    // ─────────────────────────────────────────────────────────────
    // HOOK: Estudiante graduado
    // ─────────────────────────────────────────────────────────────

    /**
     * Al graduarse un estudiante, guarda un transient para que el perfil admin
     * muestre el botón "Emitir Certificado" durante 30 días.
     *
     * @param int $student_id    ID en aura_students
     * @param int $enrollment_id ID en aura_student_enrollments
     */
    public static function on_student_graduated( int $student_id, int $enrollment_id ): void {
        set_transient(
            'aura_cert_ready_' . $student_id,
            $enrollment_id,
            30 * DAY_IN_SECONDS
        );
    }
}
