<?php
/**
 * Herramienta de Importación, Exportación y Migración Masiva para Aura Contacts.
 *
 * Soporta:
 * - Exportación a CSV Universal con BOM UTF-8 (compatible con Excel / Calc)
 * - Exportación a CSV oficial de Google Contacts (100% compatible con Google People API y contacts.google.com)
 * - Exportación a vCard estándar 3.0 (.vcf) (compatible con Apple, Android, Outlook)
 * - Exportación a JSON estructurado (Aura Portable Contacts Bundle para backups y restauraciones)
 * - Descarga directa de Plantilla CSV de ejemplo
 * - Importación inteligente de archivos CSV con auto-detección de delimitadores y mapeo inteligente multilingüe
 * - Importación de archivos vCard (.vcf / .vcard) individuales y por lotes
 * - Importación de archivos JSON estructurados
 *
 * @package    Aura_Business_Suite
 * @subpackage Contacts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aura_Contacts_Import_Export {

    /**
     * Inicializa los hooks de importación y exportación.
     */
    public static function init(): void {
        // Exportación CSV Universal
        add_action( 'admin_post_aura_contacts_export_csv', array( __CLASS__, 'handle_export_csv' ) );
        add_action( 'wp_ajax_aura_contacts_export_csv', array( __CLASS__, 'handle_export_csv' ) );

        // Exportación CSV Google Contacts
        add_action( 'admin_post_aura_contacts_export_google_csv', array( __CLASS__, 'handle_export_google_csv' ) );
        add_action( 'wp_ajax_aura_contacts_export_google_csv', array( __CLASS__, 'handle_export_google_csv' ) );

        // Exportación vCard (.vcf)
        add_action( 'admin_post_aura_contacts_export_vcf', array( __CLASS__, 'handle_export_vcf' ) );
        add_action( 'wp_ajax_aura_contacts_export_vcf', array( __CLASS__, 'handle_export_vcf' ) );

        // Exportación JSON Completo (Backup Aura)
        add_action( 'admin_post_aura_contacts_export_json', array( __CLASS__, 'handle_export_json' ) );
        add_action( 'wp_ajax_aura_contacts_export_json', array( __CLASS__, 'handle_export_json' ) );

        // Descarga de Plantilla CSV
        add_action( 'admin_post_aura_contacts_download_template', array( __CLASS__, 'handle_download_template' ) );
        add_action( 'wp_ajax_aura_contacts_download_template', array( __CLASS__, 'handle_download_template' ) );

        // Importación de Archivo (CSV / vCard / JSON)
        add_action( 'wp_ajax_aura_contacts_import_file', array( __CLASS__, 'ajax_import_file' ) );
    }

    /**
     * Verifica la validez del nonce y las capacidades del usuario para exportaciones.
     */
    private static function verify_export_request(): void {
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
        $valid = wp_verify_nonce( $nonce, 'aura_contacts_export_action' ) || wp_verify_nonce( $nonce, Aura_Contacts::NONCE );

        if ( ! $valid ) {
            wp_die(
                esc_html__( 'Enlace de exportación inválido o expirado. Por favor recarga la página e intenta de nuevo.', 'aura-suite' ),
                esc_html__( 'Seguridad de Aura Contacts', 'aura-suite' ),
                array( 'response' => 403 )
            );
        }

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'No tienes permisos para exportar contactos de este directorio.', 'aura-suite' ),
                esc_html__( 'Permiso Denegado', 'aura-suite' ),
                array( 'response' => 403 )
            );
        }
    }

    /**
     * Obtiene los contactos filtrados para exportación según categoría y estado de sincronización.
     *
     * @param string $category_filter
     * @param string $sync_filter
     * @return array
     */
    private static function get_export_rows( string $category_filter = '', string $sync_filter = '' ): array {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';

        $where = array( 'c.is_active = 1' );
        $args  = array();

        if ( ! empty( $category_filter ) ) {
            $where[] = 'c.category = %s';
            $args[]  = $category_filter;
        }

        if ( ! empty( $sync_filter ) ) {
            if ( in_array( $sync_filter, array( 'synced', 'pending', 'not_synced', 'error' ), true ) ) {
                $where[] = 'c.sync_status = %s';
                $args[]  = $sync_filter;
            }
        }

        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT c.*, m.google_resource_name, m.last_synced_at 
                FROM {$t_contacts} c
                LEFT JOIN {$t_google_map} m ON (c.id = m.contact_id)
                WHERE {$where_sql}
                ORDER BY c.first_name ASC, c.last_name ASC";

        return ! empty( $args ) ? (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : (array) $wpdb->get_results( $sql, ARRAY_A );
    }

    // ─────────────────────────────────────────────────────────────
    // 1. EXPORTACIÓN A CSV UNIVERSAL (EXCEL)
    // ─────────────────────────────────────────────────────────────

    /**
     * Manejador de descarga de archivo CSV Universal.
     */
    public static function handle_export_csv(): void {
        self::verify_export_request();

        $category    = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
        $sync_status = isset( $_GET['sync_status'] ) ? sanitize_text_field( wp_unslash( $_GET['sync_status'] ) ) : '';
        $delimiter   = isset( $_GET['delimiter'] ) && ';' === $_GET['delimiter'] ? ';' : ',';

        self::generate_csv_download( $category, $sync_status, $delimiter );
    }

    /**
     * Genera la salida directa en streaming del archivo CSV Universal con BOM UTF-8.
     *
     * @param string $category_filter
     * @param string $sync_filter
     * @param string $delimiter
     */
    public static function generate_csv_download( string $category_filter = '', string $sync_filter = '', string $delimiter = ',' ): void {
        $rows = self::get_export_rows( $category_filter, $sync_filter );

        $prefix   = 'aura-contactos';
        if ( $category_filter ) {
            $prefix .= '-' . sanitize_title( $category_filter );
        }
        if ( $sync_filter ) {
            $prefix .= '-' . sanitize_title( $sync_filter );
        }
        $filename = $prefix . '-' . date( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) {
            exit;
        }

        // BOM UTF-8 para garantizar apertura con acentos en Excel Windows
        fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        $headers = array(
            'ID',
            'Tipo',
            'Nombre(s)',
            'Apellido(s)',
            'Organización',
            'Cargo / Puesto',
            'Categoría',
            'Email Principal',
            'Email Secundario',
            'Teléfono Principal',
            'Teléfono Secundario',
            'WhatsApp',
            'Dirección',
            'Ciudad',
            'Estado / Provincia',
            'País',
            'Código Postal',
            'Etiquetas',
            'Notas',
            'Estado Sync Google',
            'Google Resource ID',
            'Fecha Creación',
            'Última Modificación',
        );
        fputcsv( $out, $headers, $delimiter );

        foreach ( (array) $rows as $r ) {
            $tags_str = '';
            if ( ! empty( $r['tags'] ) ) {
                if ( is_array( $r['tags'] ) ) {
                    $tags_str = implode( ', ', $r['tags'] );
                } elseif ( is_string( $r['tags'] ) ) {
                    $decoded = json_decode( $r['tags'], true );
                    if ( is_array( $decoded ) ) {
                        $tags_str = implode( ', ', $decoded );
                    } else {
                        $tags_str = (string) $r['tags'];
                    }
                }
            }

            $line = array(
                $r['id'],
                'person' === $r['contact_type'] ? 'Persona' : ( 'company' === $r['contact_type'] ? 'Empresa' : 'Organización' ),
                $r['first_name'],
                $r['last_name'],
                $r['organization'],
                $r['job_title'],
                $r['category'],
                $r['email_primary'],
                $r['email_secondary'],
                $r['phone_primary'],
                $r['phone_secondary'],
                $r['whatsapp'],
                $r['address_street'],
                $r['address_city'],
                $r['address_state'],
                $r['address_country'],
                $r['address_postal'],
                $tags_str,
                $r['notes'],
                $r['sync_status'],
                $r['google_resource_name'] ?? '',
                $r['created_at'],
                $r['updated_at'] ?? $r['created_at'],
            );
            fputcsv( $out, $line, $delimiter );
        }

        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. EXPORTACIÓN A CSV OFICIAL GOOGLE CONTACTS
    // ─────────────────────────────────────────────────────────────

    /**
     * Manejador de descarga de archivo CSV con formato oficial Google Contacts.
     */
    public static function handle_export_google_csv(): void {
        self::verify_export_request();

        $category    = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
        $sync_status = isset( $_GET['sync_status'] ) ? sanitize_text_field( wp_unslash( $_GET['sync_status'] ) ) : '';

        self::generate_google_csv_download( $category, $sync_status );
    }

    /**
     * Genera la salida directa en streaming de CSV con las columnas oficiales de Google Contacts.
     *
     * @param string $category_filter
     * @param string $sync_filter
     */
    public static function generate_google_csv_download( string $category_filter = '', string $sync_filter = '' ): void {
        $rows = self::get_export_rows( $category_filter, $sync_filter );

        $filename = 'google-contacts-aura-' . ( $category_filter ? sanitize_title( $category_filter ) . '-' : '' ) . date( 'Y-m-d' ) . '.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) {
            exit;
        }

        // BOM UTF-8
        fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Encabezados oficiales de exportación de Google Contacts
        $headers = array(
            'Name',
            'Given Name',
            'Additional Name',
            'Family Name',
            'Yomi Name',
            'Given Name Yomi',
            'Additional Name Yomi',
            'Family Name Yomi',
            'Name Prefix',
            'Name Suffix',
            'Initials',
            'Nickname',
            'Short Name',
            'Maiden Name',
            'Birthday',
            'Gender',
            'Location',
            'Billing Information',
            'Directory Server',
            'Mileage',
            'Occupation',
            'Hobby',
            'Sensitivity',
            'Priority',
            'Subject',
            'Notes',
            'Language',
            'Photo',
            'Group Membership',
            'E-mail 1 - Type',
            'E-mail 1 - Value',
            'E-mail 2 - Type',
            'E-mail 2 - Value',
            'Phone 1 - Type',
            'Phone 1 - Value',
            'Phone 2 - Type',
            'Phone 2 - Value',
            'Address 1 - Type',
            'Address 1 - Formatted',
            'Address 1 - Street',
            'Address 1 - City',
            'Address 1 - PO Box',
            'Address 1 - Region',
            'Address 1 - Postal Code',
            'Address 1 - Country',
            'Address 1 - Extended Address',
            'Organization 1 - Type',
            'Organization 1 - Name',
            'Organization 1 - Yomi Name',
            'Organization 1 - Title',
            'Organization 1 - Department',
            'Organization 1 - Symbol',
            'Organization 1 - Location',
            'Organization 1 - Job Description',
        );
        fputcsv( $out, $headers );

        foreach ( (array) $rows as $r ) {
            $first_name = trim( (string) ( $r['first_name'] ?? '' ) );
            $last_name  = trim( (string) ( $r['last_name'] ?? '' ) );
            $full_name  = trim( $first_name . ' ' . $last_name );
            if ( empty( $full_name ) && ! empty( $r['organization'] ) ) {
                $full_name = $r['organization'];
            }

            // Construcción del Group Membership de Google
            $groups = array( '* myContacts' );
            if ( ! empty( $r['category'] ) ) {
                $groups[] = ucfirst( $r['category'] );
            }
            if ( ! empty( $r['tags'] ) ) {
                if ( is_array( $r['tags'] ) ) {
                    foreach ( $r['tags'] as $t ) {
                        $groups[] = trim( $t );
                    }
                } elseif ( is_string( $r['tags'] ) ) {
                    $tags_arr = json_decode( $r['tags'], true );
                    if ( is_array( $tags_arr ) ) {
                        foreach ( $tags_arr as $t ) {
                            $groups[] = trim( $t );
                        }
                    }
                }
            }
            $group_membership = implode( ' ::: ', array_unique( $groups ) );

            $formatted_address = implode( ', ', array_filter( array(
                $r['address_street'] ?? '',
                $r['address_city'] ?? '',
                $r['address_state'] ?? '',
                $r['address_postal'] ?? '',
                $r['address_country'] ?? '',
            ) ) );

            $line = array(
                $full_name,
                $first_name,
                '', // Additional Name
                $last_name,
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', // campos yomi y auxiliares
                $r['job_title'] ?? '', // Occupation
                '', '', '', '', // Hobby, Sensitivity, Priority, Subject
                $r['notes'] ?? '', // Notes
                '', // Language
                $r['avatar_url'] ?? '', // Photo
                $group_membership,
                ! empty( $r['email_primary'] ) ? 'Work' : '',
                $r['email_primary'] ?? '',
                ! empty( $r['email_secondary'] ) ? 'Other' : '',
                $r['email_secondary'] ?? '',
                ! empty( $r['phone_primary'] ) ? 'Mobile' : '',
                $r['phone_primary'] ?? '',
                ! empty( $r['phone_secondary'] ) ? 'Work' : '',
                $r['phone_secondary'] ?? '',
                ! empty( $formatted_address ) ? 'Work' : '',
                $formatted_address,
                $r['address_street'] ?? '',
                $r['address_city'] ?? '',
                '', // PO Box
                $r['address_state'] ?? '',
                $r['address_postal'] ?? '',
                $r['address_country'] ?? '',
                '', // Extended Address
                ! empty( $r['organization'] ) ? 'Work' : '',
                $r['organization'] ?? '',
                '', // Org Yomi
                $r['job_title'] ?? '',
                '', '', '', '', // Dept, Symbol, Location, Job Description
            );

            fputcsv( $out, $line );
        }

        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 3. EXPORTACIÓN A vCARD 3.0 (.vcf)
    // ─────────────────────────────────────────────────────────────

    /**
     * Manejador de descarga de archivo vCard (.vcf).
     */
    public static function handle_export_vcf(): void {
        self::verify_export_request();

        $category    = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
        $sync_status = isset( $_GET['sync_status'] ) ? sanitize_text_field( wp_unslash( $_GET['sync_status'] ) ) : '';

        self::generate_vcf_download( $category, $sync_status );
    }

    /**
     * Genera la salida de archivo vCard 3.0 estándar en streaming.
     *
     * @param string $category_filter
     * @param string $sync_filter
     */
    public static function generate_vcf_download( string $category_filter = '', string $sync_filter = '' ): void {
        $rows = self::get_export_rows( $category_filter, $sync_filter );

        $prefix   = 'aura-contactos';
        if ( $category_filter ) {
            $prefix .= '-' . sanitize_title( $category_filter );
        }
        $filename = $prefix . '-' . date( 'Y-m-d' ) . '.vcf';

        nocache_headers();
        header( 'Content-Type: text/vcard; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        foreach ( (array) $rows as $r ) {
            echo self::build_vcard_entry( $r );
        }

        exit;
    }

    /**
     * Construye un bloque vCard 3.0 individual a partir de una fila de contacto.
     *
     * @param array $r
     * @return string
     */
    public static function build_vcard_entry( array $r ): string {
        $first_name = trim( (string) ( $r['first_name'] ?? '' ) );
        $last_name  = trim( (string) ( $r['last_name'] ?? '' ) );
        $full_name  = trim( $first_name . ' ' . $last_name );
        if ( empty( $full_name ) && ! empty( $r['organization'] ) ) {
            $full_name = $r['organization'];
        }

        $vcard  = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "PRODID:-//Aura Business Suite//Directorio Maestro 1.0//ES\r\n";
        $vcard .= "N:" . self::escape_vcard( $last_name ) . ';' . self::escape_vcard( $first_name ) . ";;;\r\n";
        $vcard .= "FN:" . self::escape_vcard( $full_name ) . "\r\n";

        if ( ! empty( $r['organization'] ) ) {
            $vcard .= "ORG:" . self::escape_vcard( $r['organization'] ) . "\r\n";
        }

        if ( ! empty( $r['job_title'] ) ) {
            $vcard .= "TITLE:" . self::escape_vcard( $r['job_title'] ) . "\r\n";
        }

        if ( ! empty( $r['email_primary'] ) ) {
            $vcard .= "EMAIL;TYPE=INTERNET,PREF:" . self::escape_vcard( $r['email_primary'] ) . "\r\n";
        }

        if ( ! empty( $r['email_secondary'] ) ) {
            $vcard .= "EMAIL;TYPE=INTERNET,WORK:" . self::escape_vcard( $r['email_secondary'] ) . "\r\n";
        }

        if ( ! empty( $r['phone_primary'] ) ) {
            $vcard .= "TEL;TYPE=CELL,PREF:" . self::escape_vcard( $r['phone_primary'] ) . "\r\n";
        }

        if ( ! empty( $r['phone_secondary'] ) ) {
            $vcard .= "TEL;TYPE=WORK:" . self::escape_vcard( $r['phone_secondary'] ) . "\r\n";
        }

        if ( ! empty( $r['whatsapp'] ) ) {
            $vcard .= "TEL;TYPE=WHATSAPP:" . self::escape_vcard( $r['whatsapp'] ) . "\r\n";
        }

        if ( ! empty( $r['address_street'] ) || ! empty( $r['address_city'] ) || ! empty( $r['address_country'] ) ) {
            $street  = self::escape_vcard( $r['address_street'] ?? '' );
            $city    = self::escape_vcard( $r['address_city'] ?? '' );
            $state   = self::escape_vcard( $r['address_state'] ?? '' );
            $postal  = self::escape_vcard( $r['address_postal'] ?? '' );
            $country = self::escape_vcard( $r['address_country'] ?? '' );
            $vcard  .= "ADR;TYPE=WORK:;;" . $street . ';' . $city . ';' . $state . ';' . $postal . ';' . $country . "\r\n";
        }

        if ( ! empty( $r['category'] ) ) {
            $vcard .= "CATEGORIES:" . self::escape_vcard( $r['category'] ) . "\r\n";
        }

        if ( ! empty( $r['notes'] ) ) {
            $vcard .= "NOTE:" . self::escape_vcard( $r['notes'] ) . "\r\n";
        }

        if ( ! empty( $r['avatar_url'] ) && filter_var( $r['avatar_url'], FILTER_VALIDATE_URL ) ) {
            $vcard .= "PHOTO;VALUE=URI:" . self::escape_vcard( $r['avatar_url'] ) . "\r\n";
        }

        if ( ! empty( $r['updated_at'] ) ) {
            $rev = gmdate( 'Ymd\THis\Z', strtotime( $r['updated_at'] ) );
            $vcard .= "REV:" . $rev . "\r\n";
        }

        $vcard .= "END:VCARD\r\n";

        return $vcard;
    }

    /**
     * Escapa caracteres especiales según estándar RFC 2426 vCard.
     *
     * @param string $val
     * @return string
     */
    private static function escape_vcard( string $val ): string {
        $val = str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $val );
        return trim( $val );
    }

    // ─────────────────────────────────────────────────────────────
    // 4. EXPORTACIÓN A JSON COMPLETO (PORTABILIDAD / BACKUP AURA)
    // ─────────────────────────────────────────────────────────────

    /**
     * Manejador de descarga de archivo JSON para respaldo o migración.
     */
    public static function handle_export_json(): void {
        self::verify_export_request();

        $category    = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
        $sync_status = isset( $_GET['sync_status'] ) ? sanitize_text_field( wp_unslash( $_GET['sync_status'] ) ) : '';

        self::generate_json_download( $category, $sync_status );
    }

    /**
     * Genera la salida directa en streaming de JSON de migración completa.
     *
     * @param string $category_filter
     * @param string $sync_filter
     */
    public static function generate_json_download( string $category_filter = '', string $sync_filter = '' ): void {
        $rows = self::get_export_rows( $category_filter, $sync_filter );

        $filename = 'aura-contactos-backup-' . ( $category_filter ? sanitize_title( $category_filter ) . '-' : '' ) . date( 'Y-m-d' ) . '.json';

        $payload = array(
            'bundle_type'       => 'aura_contacts_directory_backup',
            'version'           => '1.0.0',
            'exported_at'       => current_time( 'mysql' ),
            'site_url'          => get_site_url(),
            'total_contacts'    => count( $rows ),
            'filter_category'   => $category_filter ?: 'all',
            'filter_sync_status'=> $sync_filter ?: 'all',
            'contacts'          => array_map( function( $r ) {
                $tags_arr = array();
                if ( ! empty( $r['tags'] ) ) {
                    if ( is_array( $r['tags'] ) ) {
                        $tags_arr = $r['tags'];
                    } elseif ( is_string( $r['tags'] ) ) {
                        $decoded = json_decode( $r['tags'], true );
                        $tags_arr = is_array( $decoded ) ? $decoded : array( $r['tags'] );
                    }
                }
                return array(
                    'id'                   => (int) $r['id'],
                    'contact_type'         => $r['contact_type'],
                    'first_name'           => $r['first_name'],
                    'last_name'            => $r['last_name'],
                    'organization'         => $r['organization'],
                    'job_title'            => $r['job_title'],
                    'category'             => $r['category'],
                    'email_primary'        => $r['email_primary'],
                    'email_secondary'      => $r['email_secondary'],
                    'phone_primary'        => $r['phone_primary'],
                    'phone_secondary'      => $r['phone_secondary'],
                    'whatsapp'             => $r['whatsapp'],
                    'address_street'       => $r['address_street'],
                    'address_city'         => $r['address_city'],
                    'address_state'        => $r['address_state'],
                    'address_country'      => $r['address_country'],
                    'address_postal'       => $r['address_postal'],
                    'tags'                 => $tags_arr,
                    'notes'                => $r['notes'],
                    'avatar_url'           => $r['avatar_url'],
                    'sync_status'          => $r['sync_status'],
                    'google_resource_name' => $r['google_resource_name'] ?? null,
                    'created_at'           => $r['created_at'],
                    'updated_at'           => $r['updated_at'] ?? $r['created_at'],
                );
            }, $rows ),
        );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 5. DESCARGA DE PLANTILLA CSV DE EJEMPLO
    // ─────────────────────────────────────────────────────────────

    /**
     * Manejador de descarga de la plantilla oficial de contactos CSV.
     */
    public static function handle_download_template(): void {
        self::verify_export_request();

        $filename = 'plantilla-contactos-aura-suite.csv';

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) {
            exit;
        }

        // BOM UTF-8
        fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        $headers = array(
            'Nombre',
            'Apellido',
            'Organización',
            'Cargo',
            'Categoría',
            'Email Principal',
            'Email Secundario',
            'Teléfono Móvil',
            'Teléfono Secundario',
            'WhatsApp',
            'Dirección',
            'Ciudad',
            'Estado',
            'País',
            'Código Postal',
            'Etiquetas',
            'Notas',
        );
        fputcsv( $out, $headers );

        // 2 filas de ejemplo claras
        fputcsv( $out, array(
            'Carlos',
            'Mendoza Silva',
            'Innovación Digital S.A.',
            'Director de Proyectos',
            'supplier',
            'carlos.mendoza@innovacion.com',
            'contacto@innovacion.com',
            '+52 55 1234 5678',
            '+52 55 8765 4321',
            '+52 55 1234 5678',
            'Av. Reforma 222, Piso 8',
            'Ciudad de México',
            'CDMX',
            'México',
            '06600',
            'Proveedor Clave, Tecnología',
            'Contacto comercial preferente para compras anuales.',
        ) );

        fputcsv( $out, array(
            'Mariana',
            'López Treviño',
            'Fundación Crecer',
            'Coordinadora de Becas',
            'donor',
            'mariana.lopez@fundacion.org',
            '',
            '+52 81 2345 6789',
            '',
            '+52 81 2345 6789',
            'Calzada del Valle 400',
            'San Pedro Garza García',
            'Nuevo León',
            'México',
            '66220',
            'Donante Oro, Alianzas',
            'Gestiona las becas académicas de nivel superior.',
        ) );

        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 6. IMPORTACIÓN DE ARCHIVOS (CSV, vCard, JSON)
    // ─────────────────────────────────────────────────────────────

    /**
     * Endpoint AJAX para procesar archivos subidos de importación.
     */
    public static function ajax_import_file(): void {
        check_ajax_referer( Aura_Contacts::NONCE, 'nonce' );

        if ( ! current_user_can( 'aura_contacts_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No tienes permisos para importar contactos.', 'aura-suite' ) ) );
        }

        if ( empty( $_FILES['import_file'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Por favor selecciona un archivo válido (.csv, .vcf o .json) para importar.', 'aura-suite' ) ) );
        }

        $file             = $_FILES['import_file'];
        $strategy         = sanitize_key( $_POST['strategy'] ?? 'skip' );
        $default_category = sanitize_text_field( $_POST['default_category'] ?? 'other' );
        $mark_sync        = ! empty( $_POST['mark_for_google_sync'] ) && '1' === (string) $_POST['mark_for_google_sync'];

        $filename = strtolower( (string) $file['name'] );
        $ext      = pathinfo( $filename, PATHINFO_EXTENSION );

        if ( 'csv' === $ext ) {
            $result = self::import_from_csv( $file['tmp_name'], $strategy, $default_category, $mark_sync );
        } elseif ( in_array( $ext, array( 'vcf', 'vcard' ), true ) ) {
            $result = self::import_from_vcf( $file['tmp_name'], $strategy, $default_category, $mark_sync );
        } elseif ( 'json' === $ext ) {
            $result = self::import_from_json( $file['tmp_name'], $strategy, $default_category, $mark_sync );
        } else {
            wp_send_json_error( array( 'message' => __( 'Formato no soportado. El archivo debe ser .csv, .vcf (vCard) o .json (Aura Backup).', 'aura-suite' ) ) );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * Importa contactos desde un archivo CSV con detección inteligente de cabeceras.
     *
     * @param string $filepath
     * @param string $strategy 'skip' | 'update' | 'create'
     * @param string $default_category
     * @param bool   $mark_sync
     * @return array|WP_Error
     */
    public static function import_from_csv( string $filepath, string $strategy = 'skip', string $default_category = 'other', bool $mark_sync = true ) {
        if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
            return new WP_Error( 'file_read_error', __( 'No se pudo leer el archivo CSV subido.', 'aura-suite' ) );
        }

        $raw_content = file_get_contents( $filepath );
        if ( false === $raw_content || empty( $raw_content ) ) {
            return new WP_Error( 'file_empty', __( 'El archivo subido está vacío.', 'aura-suite' ) );
        }

        // Detección y conversión de codificación (UTF-16LE, UTF-16BE, Windows-1252 / ISO-8859-1)
        if ( substr( $raw_content, 0, 2 ) === "\xFF\xFE" ) {
            $raw_content = mb_convert_encoding( substr( $raw_content, 2 ), 'UTF-8', 'UTF-16LE' );
        } elseif ( substr( $raw_content, 0, 2 ) === "\xFE\xFF" ) {
            $raw_content = mb_convert_encoding( substr( $raw_content, 2 ), 'UTF-8', 'UTF-16BE' );
        } elseif ( substr( $raw_content, 0, 3 ) === "\xEF\xBB\xBF" ) {
            $raw_content = substr( $raw_content, 3 );
        } else {
            if ( ! mb_check_encoding( $raw_content, 'UTF-8' ) ) {
                $raw_content = mb_convert_encoding( $raw_content, 'UTF-8', 'Windows-1252' );
            }
        }

        // Normalizar saltos de línea a \n
        $raw_content = str_replace( array( "\r\n", "\r" ), "\n", $raw_content );

        // Escribir a stream en memoria
        $handle = fopen( 'php://temp', 'r+' );
        fwrite( $handle, $raw_content );
        rewind( $handle );

        // Detectar delimitador leyendo la primera línea
        $sample_line = fgets( $handle );
        rewind( $handle );

        $delimiter   = ',';
        $semi_count  = substr_count( $sample_line, ';' );
        $comma_count = substr_count( $sample_line, ',' );
        $tab_count   = substr_count( $sample_line, "\t" );

        if ( $semi_count > $comma_count && $semi_count > $tab_count ) {
            $delimiter = ';';
        } elseif ( $tab_count > $comma_count && $tab_count > $semi_count ) {
            $delimiter = "\t";
        }

        // Leer cabecera
        $header_raw = fgetcsv( $handle, 0, $delimiter );
        if ( ! $header_raw || empty( $header_raw ) ) {
            fclose( $handle );
            return new WP_Error( 'csv_empty', __( 'El archivo CSV está vacío o no contiene encabezados válidos.', 'aura-suite' ) );
        }

        $column_map = self::map_csv_headers( $header_raw );

        $stats = array(
            'total'      => 0,
            'total_rows' => 0,
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'errors'     => 0,
            'details'    => array(),
        );

        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            if ( empty( array_filter( $row ) ) ) {
                continue;
            }

            $stats['total_rows']++;
            $contact_data = self::extract_csv_contact_data( $row, $column_map, $default_category );

            if ( empty( $contact_data['first_name'] ) && empty( $contact_data['organization'] ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Fila %d omitida: no tiene nombre, organización, correo ni teléfono.', 'aura-suite' ), $stats['total_rows'] );
                continue;
            }

            if ( $mark_sync ) {
                $contact_data['sync_status'] = 'pending';
            }

            // Buscar si ya existe por email o teléfono
            $existing = null;
            if ( ! empty( $contact_data['email_primary'] ) ) {
                $existing = Aura_Contacts::get_contact_by_email( $contact_data['email_primary'] );
            }
            if ( ! $existing && ! empty( $contact_data['phone_primary'] ) ) {
                $existing = Aura_Contacts::get_contact_by_phone( $contact_data['phone_primary'] );
            }

            if ( $existing ) {
                if ( 'skip' === $strategy ) {
                    $stats['skipped']++;
                    continue;
                } elseif ( 'update' === $strategy ) {
                    $upd_res = Aura_Contacts::update_contact( (int) $existing['id'], array_filter( $contact_data ) );
                    if ( is_wp_error( $upd_res ) ) {
                        $stats['errors']++;
                        $stats['details'][] = sprintf( __( 'Error al actualizar fila %d: %s', 'aura-suite' ), $stats['total_rows'], $upd_res->get_error_message() );
                    } else {
                        $stats['updated']++;
                    }
                    continue;
                }
            }

            // Crear nuevo contacto
            $new_id = Aura_Contacts::create_contact( $contact_data );
            if ( is_wp_error( $new_id ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Error en fila %d: %s', 'aura-suite' ), $stats['total_rows'], $new_id->get_error_message() );
            } else {
                $stats['created']++;
            }
        }

        fclose( $handle );

        $stats['total']   = $stats['total_rows'];
        $stats['message'] = sprintf(
            __( 'Importación CSV finalizada: %d procesados (%d creados, %d actualizados, %d omitidos, %d incidencias).', 'aura-suite' ),
            $stats['total_rows'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors']
        );

        return $stats;
    }

    /**
     * Mapeo de cabeceras de columnas del CSV a campos canónicos con expresiones regulares inteligentes.
     *
     * @param array $headers
     * @return array
     */
    private static function map_csv_headers( array $headers ): array {
        $map = array();

        $normalized = array();
        foreach ( $headers as $idx => $h ) {
            $raw = (string) $h;
            // Remover BOMs invisibles y caracteres de control
            $raw = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw );
            $clean = mb_strtolower( trim( $raw ), 'UTF-8' );
            $clean = preg_replace( '/[_\-\.\:\/]+/', ' ', $clean );
            $clean = preg_replace( '/\s+/', ' ', $clean );
            $normalized[ $idx ] = trim( $clean );
        }

        $patterns = array(
            'first_name'      => array(
                '/^(first\s*name|given\s*name|primer\s*nombre|nombre\s*de\s*pila|nombres|given\s*name\s*yomi)$/ui',
                '/^(first\s*name|given\s*name|primer\s*nombre)$/ui',
            ),
            'full_name'       => array(
                '/^(name|nombre|nombre\s*completo|contacto|display\s*name)$/ui',
            ),
            'middle_name'     => array(
                '/^(additional\s*name|segundo\s*nombre|middle\s*name)$/ui',
            ),
            'last_name'       => array(
                '/^(last\s*name|family\s*name|apellido|apellidos|surname|family\s*name\s*yomi)$/ui',
            ),
            'organization'    => array(
                '/^(organization\s*1\s*name|organizacion\s*1\s*nombre|organización\s*1\s*nombre|company|empresa|organization|organizacion|organización|razon\s*social|razón\s*social|compania|compañía)$/ui',
                '/^(organization|organizacion|organización|company)\s*(\d+)?\s*(name|nombre)?$/ui',
            ),
            'job_title'       => array(
                '/^(organization\s*1\s*title|organizacion\s*1\s*titulo|organización\s*1\s*título|job\s*title|cargo\s*\/\s*puesto|cargo|puesto|title|occupation|ocupacion|ocupación|position|posicion|posición)$/ui',
            ),
            'email_primary'   => array(
                '/^(e\s*mail\s*1\s*value|correo\s*electronico\s*1\s*valor|correo\s*electrónico\s*1\s*valor|email\s*1|correo\s*1|e\s*mail\s*address|email\s*address|email\s*principal|correo\s*principal|primary\s*email)$/ui',
                '/^(email|e\s*mail|correo|correo\s*electronico|correo\s*electrónico)$/ui',
            ),
            'email_secondary' => array(
                '/^(e\s*mail\s*2\s*value|correo\s*electronico\s*2\s*valor|correo\s*electrónico\s*2\s*valor|email\s*2|correo\s*2|email\s*secundario|correo\s*secundario|e\s*mail\s*2\s*address|secondary\s*email)$/ui',
            ),
            'phone_primary'   => array(
                '/^(phone\s*1\s*value|telefono\s*1\s*valor|teléfono\s*1\s*valor|phone\s*1|telefono\s*1|teléfono\s*1|mobile\s*phone|celular|movil|móvil|primary\s*phone|telefono\s*principal|teléfono\s*principal|telefono\s*movil|teléfono\s*móvil|telefono\s*celular|teléfono\s*celular)$/ui',
                '/^(phone|telefono|teléfono)$/ui',
            ),
            'phone_secondary' => array(
                '/^(phone\s*2\s*value|telefono\s*2\s*valor|teléfono\s*2\s*valor|phone\s*2|telefono\s*2|teléfono\s*2|business\s*phone|home\s*phone|telefono\s*secundario|teléfono\s*secundario|secondary\s*phone|telefono\s*trabajo|teléfono\s*trabajo|telefono\s*oficina|teléfono\s*oficina|telefono\s*fijo|teléfono\s*fijo)$/ui',
            ),
            'whatsapp'        => array(
                '/^(whatsapp|wa|wsp)$/ui',
            ),
            'address_street'  => array(
                '/^(address\s*1\s*street|address\s*1\s*formatted|direccion\s*1\s*calle|dirección\s*1\s*calle|direccion\s*1\s*con\s*formato|dirección\s*1\s*con\s*formato|business\s*street|home\s*street|address|direccion|dirección|calle|street)$/ui',
            ),
            'address_city'    => array(
                '/^(address\s*1\s*city|direccion\s*1\s*ciudad|dirección\s*1\s*ciudad|business\s*city|home\s*city|city|ciudad|municipio)$/ui',
            ),
            'address_state'   => array(
                '/^(address\s*1\s*region|direccion\s*1\s*region|dirección\s*1\s*región|business\s*state|home\s*state|state|estado|provincia|region|región)$/ui',
            ),
            'address_postal'  => array(
                '/^(address\s*1\s*postal\s*code|direccion\s*1\s*codigo\s*postal|dirección\s*1\s*código\s*postal|business\s*postal\s*code|home\s*postal\s*code|postal\s*code|codigo\s*postal|código\s*postal|zip|cp)$/ui',
            ),
            'address_country' => array(
                '/^(address\s*1\s*country|direccion\s*1\s*pais|dirección\s*1\s*país|business\s*country|home\s*country|country|pais|país)$/ui',
            ),
            'tags'            => array(
                '/^(group\s*membership|pertenencia\s*a\s*grupos|tags|etiquetas|keywords)$/ui',
            ),
            'notes'           => array(
                '/^(notes|notas|observaciones|comentarios|description|descripcion|descripción)$/ui',
            ),
            'category'        => array(
                '/^(categories|categorias|categorías|category|categoria|categoría|tipo\s*de\s*contacto|rol)$/ui',
            ),
            'avatar_url'      => array(
                '/^(photo|foto|avatar|avatar\s*url|photo\s*url)$/ui',
            ),
        );

        foreach ( $patterns as $canonical => $regex_list ) {
            foreach ( $normalized as $idx => $header_clean ) {
                foreach ( $regex_list as $regex ) {
                    if ( preg_match( $regex, $header_clean ) ) {
                        if ( ! isset( $map[ $canonical ] ) ) {
                            $map[ $canonical ] = $idx;
                            break 2;
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Extrae array limpio de contacto a partir de una fila CSV.
     *
     * @param array  $row
     * @param array  $map
     * @param string $default_category
     * @return array
     */
    private static function extract_csv_contact_data( array $row, array $map, string $default_category ): array {
        $first_name  = isset( $map['first_name'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['first_name'] ] ?? '' ) ) ) : '';
        $middle_name = isset( $map['middle_name'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['middle_name'] ] ?? '' ) ) ) : '';
        $last_name   = isset( $map['last_name'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['last_name'] ] ?? '' ) ) ) : '';
        $full_name   = isset( $map['full_name'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['full_name'] ] ?? '' ) ) ) : '';

        if ( ! empty( $first_name ) && ! empty( $middle_name ) ) {
            $first_name = trim( $first_name . ' ' . $middle_name );
        }

        if ( empty( $first_name ) && ! empty( $full_name ) ) {
            if ( empty( $last_name ) && strpos( $full_name, ' ' ) !== false ) {
                $parts      = explode( ' ', $full_name, 2 );
                $first_name = $parts[0];
                $last_name  = $parts[1];
            } else {
                $first_name = $full_name;
            }
        } elseif ( ! empty( $first_name ) && empty( $last_name ) && strpos( $first_name, ' ' ) !== false ) {
            $parts      = explode( ' ', $first_name, 2 );
            $first_name = $parts[0];
            $last_name  = $parts[1];
        }

        $organization = isset( $map['organization'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['organization'] ] ?? '' ) ) ) : '';
        $job_title    = isset( $map['job_title'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['job_title'] ] ?? '' ) ) ) : '';

        $email_primary   = isset( $map['email_primary'] ) ? sanitize_email( trim( (string) ( $row[ $map['email_primary'] ] ?? '' ) ) ) : '';
        $email_secondary = isset( $map['email_secondary'] ) ? sanitize_email( trim( (string) ( $row[ $map['email_secondary'] ] ?? '' ) ) ) : '';

        if ( empty( $email_primary ) && ! empty( $email_secondary ) ) {
            $email_primary   = $email_secondary;
            $email_secondary = '';
        }

        $phone_primary   = isset( $map['phone_primary'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['phone_primary'] ] ?? '' ) ) ) : '';
        $phone_secondary = isset( $map['phone_secondary'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['phone_secondary'] ] ?? '' ) ) ) : '';
        if ( empty( $phone_primary ) && ! empty( $phone_secondary ) ) {
            $phone_primary   = $phone_secondary;
            $phone_secondary = '';
        }

        $whatsapp = isset( $map['whatsapp'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['whatsapp'] ] ?? '' ) ) ) : '';

        $contact_type = 'person';
        if ( empty( $first_name ) && empty( $last_name ) && ! empty( $organization ) ) {
            $contact_type = 'company';
            $first_name   = $organization;
        }

        if ( empty( $first_name ) && empty( $organization ) ) {
            if ( ! empty( $email_primary ) ) {
                $user_part  = explode( '@', $email_primary )[0];
                $first_name = ucfirst( preg_replace( '/[^a-zA-Z0-9]/', ' ', $user_part ) );
            } elseif ( ! empty( $phone_primary ) ) {
                $first_name = 'Contacto ' . substr( preg_replace( '/\D+/', '', $phone_primary ), -4 );
            }
        }

        $address_street  = isset( $map['address_street'] ) ? sanitize_textarea_field( trim( (string) ( $row[ $map['address_street'] ] ?? '' ) ) ) : '';
        $address_city    = isset( $map['address_city'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['address_city'] ] ?? '' ) ) ) : '';
        $address_state   = isset( $map['address_state'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['address_state'] ] ?? '' ) ) ) : '';
        $address_country = isset( $map['address_country'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['address_country'] ] ?? '' ) ) ) : '';
        $address_postal  = isset( $map['address_postal'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['address_postal'] ] ?? '' ) ) ) : '';

        $notes = isset( $map['notes'] ) ? sanitize_textarea_field( trim( (string) ( $row[ $map['notes'] ] ?? '' ) ) ) : '';

        $tags_raw = isset( $map['tags'] ) ? trim( (string) ( $row[ $map['tags'] ] ?? '' ) ) : '';
        $tags     = array();
        if ( ! empty( $tags_raw ) ) {
            $parts = preg_split( '/(:::|,|;)/', $tags_raw );
            foreach ( (array) $parts as $p ) {
                $p = trim( $p );
                if ( empty( $p ) || 0 === strpos( $p, '* ' ) || in_array( strtolower( $p ), array( 'mycontacts', 'mis contactos', 'starred', 'destacados' ), true ) ) {
                    continue;
                }
                $tags[] = sanitize_text_field( $p );
            }
            $tags = array_values( array_unique( $tags ) );
        }

        $category = isset( $map['category'] ) ? sanitize_text_field( trim( (string) ( $row[ $map['category'] ] ?? '' ) ) ) : '';
        if ( empty( $category ) ) {
            $category = $default_category;
        }

        $avatar_url = isset( $map['avatar_url'] ) ? esc_url_raw( trim( (string) ( $row[ $map['avatar_url'] ] ?? '' ) ) ) : '';

        return array(
            'contact_type'    => $contact_type,
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'organization'    => $organization,
            'job_title'       => $job_title,
            'email_primary'   => $email_primary,
            'email_secondary' => $email_secondary,
            'phone_primary'   => $phone_primary,
            'phone_secondary' => $phone_secondary,
            'whatsapp'        => $whatsapp,
            'address_street'  => $address_street,
            'address_city'    => $address_city,
            'address_state'   => $address_state,
            'address_country' => $address_country ?: 'México',
            'address_postal'  => $address_postal,
            'category'        => $category,
            'tags'            => $tags,
            'notes'           => $notes,
            'avatar_url'      => $avatar_url,
        );
    }

    /**
     * Importa contactos desde un archivo vCard (.vcf / .vcard).
     *
     * @param string $filepath
     * @param string $strategy
     * @param string $default_category
     * @param bool   $mark_sync
     * @return array|WP_Error
     */
    public static function import_from_vcf( string $filepath, string $strategy = 'skip', string $default_category = 'other', bool $mark_sync = true ) {
        $content = file_get_contents( $filepath );
        if ( false === $content ) {
            return new WP_Error( 'vcf_read_error', __( 'No se pudo leer el archivo vCard subido.', 'aura-suite' ) );
        }

        // Unfold multi-line entries
        $content = preg_replace( '/\r?\n[ \t]/', '', $content );

        preg_match_all( '/BEGIN:VCARD.*?END:VCARD/si', $content, $matches );
        $vcard_blocks = $matches[0] ?? array();

        if ( empty( $vcard_blocks ) ) {
            return new WP_Error( 'vcf_no_cards', __( 'No se encontraron bloques vCard válidos en el archivo.', 'aura-suite' ) );
        }

        $stats = array(
            'total'      => count( $vcard_blocks ),
            'total_rows' => count( $vcard_blocks ),
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'errors'     => 0,
            'details'    => array(),
        );

        foreach ( $vcard_blocks as $v_idx => $block ) {
            $parsed = self::parse_single_vcard( $block, $default_category );
            if ( empty( $parsed['first_name'] ) && empty( $parsed['organization'] ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Tarjeta #%d omitida: sin nombre ni organización.', 'aura-suite' ), $v_idx + 1 );
                continue;
            }

            if ( $mark_sync ) {
                $parsed['sync_status'] = 'pending';
            }

            $existing = null;
            if ( ! empty( $parsed['email_primary'] ) ) {
                $existing = Aura_Contacts::get_contact_by_email( $parsed['email_primary'] );
            }
            if ( ! $existing && ! empty( $parsed['phone_primary'] ) ) {
                $existing = Aura_Contacts::get_contact_by_phone( $parsed['phone_primary'] );
            }

            if ( $existing ) {
                if ( 'skip' === $strategy ) {
                    $stats['skipped']++;
                    continue;
                } elseif ( 'update' === $strategy ) {
                    $upd_res = Aura_Contacts::update_contact( (int) $existing['id'], array_filter( $parsed ) );
                    if ( is_wp_error( $upd_res ) ) {
                        $stats['errors']++;
                        $stats['details'][] = sprintf( __( 'Error al actualizar tarjeta #%d: %s', 'aura-suite' ), $v_idx + 1, $upd_res->get_error_message() );
                    } else {
                        $stats['updated']++;
                    }
                    continue;
                }
            }

            $new_id = Aura_Contacts::create_contact( $parsed );
            if ( is_wp_error( $new_id ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Error en tarjeta #%d: %s', 'aura-suite' ), $v_idx + 1, $new_id->get_error_message() );
            } else {
                $stats['created']++;
            }
        }

        $stats['total']   = $stats['total_rows'];
        $stats['message'] = sprintf(
            __( 'Importación vCard finalizada: %d procesados (%d creados, %d actualizados, %d omitidos, %d incidencias).', 'aura-suite' ),
            $stats['total_rows'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors']
        );

        return $stats;
    }

    /**
     * Parsea un bloque individual de texto vCard.
     *
     * @param string $block
     * @param string $default_category
     * @return array
     */
    private static function parse_single_vcard( string $block, string $default_category ): array {
        $lines = preg_split( '/\r?\n/', $block );
        $data  = array(
            'contact_type'    => 'person',
            'first_name'      => '',
            'last_name'       => '',
            'organization'    => '',
            'job_title'       => '',
            'email_primary'   => '',
            'email_secondary' => '',
            'phone_primary'   => '',
            'phone_secondary' => '',
            'whatsapp'        => '',
            'address_street'  => '',
            'address_city'    => '',
            'address_state'   => '',
            'address_country' => 'México',
            'address_postal'  => '',
            'category'        => $default_category,
            'notes'           => '',
            'avatar_url'      => '',
        );

        $emails = array();
        $phones = array();

        foreach ( (array) $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) || strpos( $line, ':' ) === false ) {
                continue;
            }

            list( $raw_key, $val ) = explode( ':', $line, 2 );
            $raw_key = strtoupper( trim( $raw_key ) );
            $val     = trim( $val );

            $clean_val = str_replace( array( '\;', '\,', '\n', '\\\\' ), array( ';', ',', "\n", '\\' ), $val );

            if ( 0 === strpos( $raw_key, 'FN' ) ) {
                if ( empty( $data['first_name'] ) ) {
                    $parts = explode( ' ', $clean_val, 2 );
                    $data['first_name'] = $parts[0] ?? $clean_val;
                    $data['last_name']  = $parts[1] ?? '';
                }
            } elseif ( 0 === strpos( $raw_key, 'N;' ) || 'N' === $raw_key ) {
                $n_parts = explode( ';', $clean_val );
                if ( ! empty( $n_parts[1] ) ) {
                    $data['first_name'] = $n_parts[1];
                }
                if ( ! empty( $n_parts[0] ) ) {
                    $data['last_name'] = $n_parts[0];
                }
            } elseif ( 0 === strpos( $raw_key, 'ORG' ) ) {
                $org_parts = explode( ';', $clean_val );
                $data['organization'] = trim( $org_parts[0] );
            } elseif ( 0 === strpos( $raw_key, 'TITLE' ) ) {
                $data['job_title'] = $clean_val;
            } elseif ( 0 === strpos( $raw_key, 'EMAIL' ) ) {
                $emails[] = sanitize_email( $clean_val );
            } elseif ( 0 === strpos( $raw_key, 'TEL' ) ) {
                $phones[] = sanitize_text_field( $clean_val );
            } elseif ( 0 === strpos( $raw_key, 'CATEGORIES' ) ) {
                $data['category'] = sanitize_text_field( $clean_val );
            } elseif ( 0 === strpos( $raw_key, 'NOTE' ) ) {
                $data['notes'] = sanitize_textarea_field( $clean_val );
            } elseif ( 0 === strpos( $raw_key, 'PHOTO' ) && filter_var( $clean_val, FILTER_VALIDATE_URL ) ) {
                $data['avatar_url'] = esc_url_raw( $clean_val );
            } elseif ( 0 === strpos( $raw_key, 'ADR' ) ) {
                $adr_parts = explode( ';', $clean_val );
                if ( ! empty( $adr_parts[2] ) ) {
                    $data['address_street'] = sanitize_textarea_field( $adr_parts[2] );
                }
                if ( ! empty( $adr_parts[3] ) ) {
                    $data['address_city'] = sanitize_text_field( $adr_parts[3] );
                }
                if ( ! empty( $adr_parts[4] ) ) {
                    $data['address_state'] = sanitize_text_field( $adr_parts[4] );
                }
                if ( ! empty( $adr_parts[5] ) ) {
                    $data['address_postal'] = sanitize_text_field( $adr_parts[5] );
                }
                if ( ! empty( $adr_parts[6] ) ) {
                    $data['address_country'] = sanitize_text_field( $adr_parts[6] );
                }
            }
        }

        if ( ! empty( $emails[0] ) ) {
            $data['email_primary'] = $emails[0];
        }
        if ( ! empty( $emails[1] ) ) {
            $data['email_secondary'] = $emails[1];
        }

        if ( ! empty( $phones[0] ) ) {
            $data['phone_primary'] = $phones[0];
        }
        if ( ! empty( $phones[1] ) ) {
            $data['phone_secondary'] = $phones[1];
        }

        if ( empty( $data['first_name'] ) && empty( $data['last_name'] ) && ! empty( $data['organization'] ) ) {
            $data['contact_type'] = 'company';
            $data['first_name']   = $data['organization'];
        }

        return $data;
    }

    /**
     * Importa contactos desde un archivo JSON estructurado (Aura Backup).
     *
     * @param string $filepath
     * @param string $strategy
     * @param string $default_category
     * @param bool   $mark_sync
     * @return array|WP_Error
     */
    public static function import_from_json( string $filepath, string $strategy = 'skip', string $default_category = 'other', bool $mark_sync = true ) {
        $raw = file_get_contents( $filepath );
        if ( false === $raw || empty( $raw ) ) {
            return new WP_Error( 'file_empty', __( 'El archivo JSON está vacío o no se pudo leer.', 'aura-suite' ) );
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'json_invalid', __( 'El formato del archivo JSON no es válido.', 'aura-suite' ) );
        }

        $contacts = isset( $data['contacts'] ) && is_array( $data['contacts'] ) ? $data['contacts'] : ( isset( $data[0] ) && is_array( $data[0] ) ? $data : array() );

        if ( empty( $contacts ) ) {
            return new WP_Error( 'no_contacts', __( 'No se encontraron contactos para importar dentro del archivo JSON.', 'aura-suite' ) );
        }

        $stats = array(
            'total'      => count( $contacts ),
            'total_rows' => count( $contacts ),
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'errors'     => 0,
            'details'    => array(),
        );

        foreach ( $contacts as $idx => $c ) {
            if ( ! is_array( $c ) ) {
                continue;
            }

            $first_name   = sanitize_text_field( $c['first_name'] ?? '' );
            $last_name    = sanitize_text_field( $c['last_name'] ?? '' );
            $organization = sanitize_text_field( $c['organization'] ?? '' );

            if ( empty( $first_name ) && empty( $organization ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Elemento #%d omitido: sin nombre ni organización.', 'aura-suite' ), $idx + 1 );
                continue;
            }

            $email_primary = sanitize_email( $c['email_primary'] ?? '' );
            $phone_primary = sanitize_text_field( $c['phone_primary'] ?? '' );

            $existing = null;
            if ( ! empty( $email_primary ) ) {
                $existing = Aura_Contacts::get_contact_by_email( $email_primary );
            }
            if ( ! $existing && ! empty( $phone_primary ) ) {
                $existing = Aura_Contacts::get_contact_by_phone( $phone_primary );
            }

            $clean_data = array(
                'contact_type'    => in_array( $c['contact_type'] ?? '', array( 'person', 'company', 'organization' ), true ) ? $c['contact_type'] : 'person',
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'organization'    => $organization,
                'job_title'       => sanitize_text_field( $c['job_title'] ?? '' ),
                'category'        => ! empty( $c['category'] ) ? sanitize_text_field( $c['category'] ) : $default_category,
                'email_primary'   => $email_primary,
                'email_secondary' => sanitize_email( $c['email_secondary'] ?? '' ),
                'phone_primary'   => $phone_primary,
                'phone_secondary' => sanitize_text_field( $c['phone_secondary'] ?? '' ),
                'whatsapp'        => sanitize_text_field( $c['whatsapp'] ?? '' ),
                'address_street'  => sanitize_textarea_field( $c['address_street'] ?? '' ),
                'address_city'    => sanitize_text_field( $c['address_city'] ?? '' ),
                'address_state'   => sanitize_text_field( $c['address_state'] ?? '' ),
                'address_country' => sanitize_text_field( $c['address_country'] ?? 'México' ),
                'address_postal'  => sanitize_text_field( $c['address_postal'] ?? '' ),
                'tags'            => is_array( $c['tags'] ?? null ) ? $c['tags'] : ( ! empty( $c['tags'] ) ? array( sanitize_text_field( $c['tags'] ) ) : array() ),
                'notes'           => sanitize_textarea_field( $c['notes'] ?? '' ),
                'avatar_url'      => esc_url_raw( $c['avatar_url'] ?? '' ),
                'is_active'       => 1,
            );

            if ( $mark_sync ) {
                $clean_data['sync_status'] = 'pending';
            }

            if ( $existing ) {
                if ( 'skip' === $strategy ) {
                    $stats['skipped']++;
                    continue;
                } elseif ( 'update' === $strategy ) {
                    $upd_res = Aura_Contacts::update_contact( (int) $existing['id'], array_filter( $clean_data ) );
                    if ( is_wp_error( $upd_res ) ) {
                        $stats['errors']++;
                        $stats['details'][] = sprintf( __( 'Error al actualizar elemento #%d (%s): %s', 'aura-suite' ), $idx + 1, $first_name, $upd_res->get_error_message() );
                    } else {
                        $stats['updated']++;
                    }
                    continue;
                }
            }

            $new_id = Aura_Contacts::create_contact( $clean_data );
            if ( is_wp_error( $new_id ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Error al crear elemento #%d (%s): %s', 'aura-suite' ), $idx + 1, $first_name, $new_id->get_error_message() );
            } else {
                $stats['created']++;
            }
        }

        $stats['message'] = sprintf(
            __( 'Importación JSON finalizada: %d procesados (%d creados, %d actualizados, %d omitidos, %d incidencias).', 'aura-suite' ),
            $stats['total'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors']
        );

        return $stats;
    }
}
