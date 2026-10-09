<?php
/**
 * Herramienta de Importación y Exportación Masiva para Aura Contacts.
 *
 * Soporta:
 * - Exportación a CSV con BOM UTF-8 (compatible con Excel / Calc)
 * - Exportación a vCard estándar 3.0 (.vcf) (compatible con Google Contacts, Apple, Android, Outlook)
 * - Importación inteligente de archivos CSV con mapeo automático de columnas y estrategias de conflicto
 * - Importación de archivos vCard (.vcf) de múltiples tarjetas
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
        add_action( 'admin_post_aura_contacts_export_csv', array( __CLASS__, 'handle_export_csv' ) );
        add_action( 'admin_post_aura_contacts_export_vcf', array( __CLASS__, 'handle_export_vcf' ) );
        add_action( 'wp_ajax_aura_contacts_import_file', array( __CLASS__, 'ajax_import_file' ) );
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORTACIÓN A CSV
    // ─────────────────────────────────────────────────────────────

    /**
     * Manejador del hook admin_post para descarga de archivo CSV.
     */
    public static function handle_export_csv(): void {
        check_admin_referer( 'aura_contacts_export_action', 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para exportar contactos.', 'aura-suite' ) );
        }

        $category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
        self::generate_csv_download( $category );
    }

    /**
     * Genera la salida directa en streaming del archivo CSV.
     *
     * @param string $category_filter
     */
    public static function generate_csv_download( string $category_filter = '' ): void {
        global $wpdb;
        $t_contacts   = $wpdb->prefix . 'aura_contacts';
        $t_google_map = $wpdb->prefix . 'aura_contacts_google_map';

        $where = array( 'c.is_active = 1' );
        $args  = array();

        if ( ! empty( $category_filter ) ) {
            $where[] = 'c.category = %s';
            $args[]  = $category_filter;
        }

        $where_sql = implode( ' AND ', $where );
        $sql = "SELECT c.*, m.google_resource_name, m.last_synced_at 
                FROM {$t_contacts} c
                LEFT JOIN {$t_google_map} m ON (c.id = m.contact_id)
                WHERE {$where_sql}
                ORDER BY c.first_name ASC, c.last_name ASC";

        $rows = ! empty( $args ) ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        $filename = 'aura-contactos-' . ( $category_filter ? sanitize_title( $category_filter ) . '-' : '' ) . date( 'Y-m-d' ) . '.csv';

        // Headers HTTP
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        if ( ! $out ) {
            exit;
        }

        // BOM UTF-8 para garantizar apertura perfecta con acentos en Excel Windows
        fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        // Encabezados de columnas
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
            'Estado Sync',
            'Google Resource ID',
            'Fecha Creación',
        );
        fputcsv( $out, $headers );

        foreach ( (array) $rows as $r ) {
            $tags_str = '';
            if ( ! empty( $r['tags'] ) ) {
                $decoded = json_decode( $r['tags'], true );
                if ( is_array( $decoded ) ) {
                    $tags_str = implode( ', ', $decoded );
                } else {
                    $tags_str = (string) $r['tags'];
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
                $r['google_resource_name'],
                $r['created_at'],
            );
            fputcsv( $out, $line );
        }

        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORTACIÓN A vCARD (.vcf)
    // ─────────────────────────────────────────────────────────────

    /**
     * Manejador del hook admin_post para descarga de archivo vCard (.vcf).
     */
    public static function handle_export_vcf(): void {
        check_admin_referer( 'aura_contacts_export_action', 'nonce' );

        if ( ! current_user_can( 'aura_contacts_view' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para exportar contactos.', 'aura-suite' ) );
        }

        $category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
        self::generate_vcf_download( $category );
    }

    /**
     * Genera la salida de archivo vCard 3.0 estándar.
     *
     * @param string $category_filter
     */
    public static function generate_vcf_download( string $category_filter = '' ): void {
        global $wpdb;
        $t_contacts = $wpdb->prefix . 'aura_contacts';

        $where = array( 'is_active = 1' );
        $args  = array();

        if ( ! empty( $category_filter ) ) {
            $where[] = 'category = %s';
            $args[]  = $category_filter;
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT * FROM {$t_contacts} WHERE {$where_sql} ORDER BY first_name ASC, last_name ASC";
        $rows      = ! empty( $args ) ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

        $filename = 'aura-contactos-' . ( $category_filter ? sanitize_title( $category_filter ) . '-' : '' ) . date( 'Y-m-d' ) . '.vcf';

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

        // Dirección estructurada
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
    // IMPORTACIÓN DE ARCHIVOS (CSV & vCard)
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
            wp_send_json_error( array( 'message' => __( 'Por favor selecciona un archivo válido para importar.', 'aura-suite' ) ) );
        }

        $file             = $_FILES['import_file'];
        $strategy         = sanitize_key( $_POST['strategy'] ?? 'skip' );
        $default_category = sanitize_text_field( $_POST['default_category'] ?? 'other' );

        $filename = strtolower( (string) $file['name'] );
        $ext      = pathinfo( $filename, PATHINFO_EXTENSION );

        if ( 'csv' === $ext ) {
            $result = self::import_from_csv( $file['tmp_name'], $strategy, $default_category );
        } elseif ( in_array( $ext, array( 'vcf', 'vcard' ), true ) ) {
            $result = self::import_from_vcf( $file['tmp_name'], $strategy, $default_category );
        } else {
            wp_send_json_error( array( 'message' => __( 'Formato no soportado. El archivo debe ser .csv o .vcf (vCard).', 'aura-suite' ) ) );
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
     * @return array|WP_Error
     */
    public static function import_from_csv( string $filepath, string $strategy = 'skip', string $default_category = 'other' ) {
        $handle = fopen( $filepath, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_read_error', __( 'No se pudo leer el archivo CSV subido.', 'aura-suite' ) );
        }

        // Detectar y omitir UTF-8 BOM
        $bom = fread( $handle, 3 );
        if ( $bom !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
            rewind( $handle );
        }

        // Detectar delimitador leyendo la primera línea
        $first_line = fgets( $handle );
        rewind( $handle );
        if ( $bom === chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
            fread( $handle, 3 );
        }

        $delimiter = ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) ? ';' : ',';

        $header_raw = fgetcsv( $handle, 4096, $delimiter );
        if ( ! $header_raw || empty( $header_raw ) ) {
            fclose( $handle );
            return new WP_Error( 'csv_empty', __( 'El archivo CSV está vacío o no contiene encabezados.', 'aura-suite' ) );
        }

        $column_map = self::map_csv_headers( $header_raw );

        $stats = array(
            'total_rows' => 0,
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'errors'     => 0,
            'details'    => array(),
        );

        while ( ( $row = fgetcsv( $handle, 4096, $delimiter ) ) !== false ) {
            // Omitir líneas vacías
            if ( empty( array_filter( $row ) ) ) {
                continue;
            }

            $stats['total_rows']++;
            $contact_data = self::extract_csv_contact_data( $row, $column_map, $default_category );

            if ( empty( $contact_data['first_name'] ) && empty( $contact_data['organization'] ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Fila %d omitida: no tiene nombre ni organización.', 'aura-suite' ), $stats['total_rows'] );
                continue;
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
                    } else {
                        $stats['updated']++;
                    }
                    continue;
                }
            }

            // Crear nuevo
            $new_id = Aura_Contacts::create_contact( $contact_data );
            if ( is_wp_error( $new_id ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Error en fila %d: %s', 'aura-suite' ), $stats['total_rows'], $new_id->get_error_message() );
            } else {
                $stats['created']++;
            }
        }

        fclose( $handle );

        $stats['message'] = sprintf(
            __( 'Importación CSV finalizada: %d procesados (%d creados, %d actualizados, %d omitidos).', 'aura-suite' ),
            $stats['total_rows'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped']
        );

        return $stats;
    }

    /**
     * Mapeo de cabeceras de columnas del CSV a campos canónicos.
     *
     * @param array $headers
     * @return array
     */
    private static function map_csv_headers( array $headers ): array {
        $map = array();

        $dictionary = array(
            'first_name'      => array( 'nombre', 'nombres', 'first_name', 'firstname', 'given_name', 'primer nombre', 'name' ),
            'last_name'       => array( 'apellido', 'apellidos', 'last_name', 'lastname', 'family_name', 'segundo nombre', 'surname' ),
            'organization'    => array( 'organizacion', 'organización', 'empresa', 'company', 'organization', 'razon social', 'razón social' ),
            'job_title'       => array( 'cargo', 'puesto', 'job_title', 'title', 'cargo / puesto', 'ocupacion', 'ocupación' ),
            'email_primary'   => array( 'email', 'correo', 'correo electrónico', 'email_primary', 'e-mail', 'mail' ),
            'email_secondary' => array( 'email secundario', 'correo secundario', 'email_secondary' ),
            'phone_primary'   => array( 'telefono', 'teléfono', 'phone', 'movil', 'móvil', 'celular', 'phone_primary' ),
            'phone_secondary' => array( 'telefono secundario', 'teléfono secundario', 'phone_secondary' ),
            'whatsapp'        => array( 'whatsapp', 'wa', 'wsp' ),
            'category'        => array( 'categoria', 'categoría', 'category', 'tipo de contacto', 'rol' ),
            'address_street'  => array( 'direccion', 'dirección', 'calle', 'address', 'address_street' ),
            'address_city'    => array( 'ciudad', 'city', 'municipio' ),
            'address_state'   => array( 'estado', 'provincia', 'state' ),
            'address_country' => array( 'pais', 'país', 'country' ),
            'address_postal'  => array( 'codigo postal', 'código postal', 'cp', 'zip', 'postal_code' ),
            'tags'            => array( 'etiquetas', 'tags' ),
            'notes'           => array( 'notas', 'observaciones', 'notes', 'comentarios' ),
        );

        foreach ( $headers as $idx => $header_str ) {
            $clean = strtolower( trim( (string) $header_str ) );
            $clean = str_replace( array( '-', '_', '.' ), ' ', $clean );

            foreach ( $dictionary as $canonical_field => $synonyms ) {
                if ( in_array( $clean, $synonyms, true ) ) {
                    $map[ $canonical_field ] = $idx;
                    break;
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
        $data = array(
            'first_name'      => isset( $map['first_name'] ) ? sanitize_text_field( trim( $row[ $map['first_name'] ] ?? '' ) ) : '',
            'last_name'       => isset( $map['last_name'] ) ? sanitize_text_field( trim( $row[ $map['last_name'] ] ?? '' ) ) : '',
            'organization'    => isset( $map['organization'] ) ? sanitize_text_field( trim( $row[ $map['organization'] ] ?? '' ) ) : '',
            'job_title'       => isset( $map['job_title'] ) ? sanitize_text_field( trim( $row[ $map['job_title'] ] ?? '' ) ) : '',
            'email_primary'   => isset( $map['email_primary'] ) ? sanitize_email( trim( $row[ $map['email_primary'] ] ?? '' ) ) : '',
            'email_secondary' => isset( $map['email_secondary'] ) ? sanitize_email( trim( $row[ $map['email_secondary'] ] ?? '' ) ) : '',
            'phone_primary'   => isset( $map['phone_primary'] ) ? sanitize_text_field( trim( $row[ $map['phone_primary'] ] ?? '' ) ) : '',
            'phone_secondary' => isset( $map['phone_secondary'] ) ? sanitize_text_field( trim( $row[ $map['phone_secondary'] ] ?? '' ) ) : '',
            'whatsapp'        => isset( $map['whatsapp'] ) ? sanitize_text_field( trim( $row[ $map['whatsapp'] ] ?? '' ) ) : '',
            'address_street'  => isset( $map['address_street'] ) ? sanitize_textarea_field( trim( $row[ $map['address_street'] ] ?? '' ) ) : '',
            'address_city'    => isset( $map['address_city'] ) ? sanitize_text_field( trim( $row[ $map['address_city'] ] ?? '' ) ) : '',
            'address_state'   => isset( $map['address_state'] ) ? sanitize_text_field( trim( $row[ $map['address_state'] ] ?? '' ) ) : '',
            'address_country' => isset( $map['address_country'] ) ? sanitize_text_field( trim( $row[ $map['address_country'] ] ?? '' ) ) : '',
            'address_postal'  => isset( $map['address_postal'] ) ? sanitize_text_field( trim( $row[ $map['address_postal'] ] ?? '' ) ) : '',
            'category'        => isset( $map['category'] ) ? sanitize_text_field( trim( $row[ $map['category'] ] ?? '' ) ) : '',
            'tags'            => isset( $map['tags'] ) ? sanitize_text_field( trim( $row[ $map['tags'] ] ?? '' ) ) : '',
            'notes'           => isset( $map['notes'] ) ? sanitize_textarea_field( trim( $row[ $map['notes'] ] ?? '' ) ) : '',
        );

        if ( empty( $data['category'] ) ) {
            $data['category'] = $default_category;
        }

        // Si el nombre viene completo en la columna first_name y no hay last_name
        if ( ! empty( $data['first_name'] ) && empty( $data['last_name'] ) && strpos( $data['first_name'], ' ' ) !== false ) {
            $parts = explode( ' ', $data['first_name'], 2 );
            $data['first_name'] = $parts[0];
            $data['last_name']  = $parts[1];
        }

        return $data;
    }

    /**
     * Importa contactos desde un archivo vCard (.vcf).
     *
     * @param string $filepath
     * @param string $strategy
     * @param string $default_category
     * @return array|WP_Error
     */
    public static function import_from_vcf( string $filepath, string $strategy = 'skip', string $default_category = 'other' ) {
        $content = file_get_contents( $filepath );
        if ( false === $content ) {
            return new WP_Error( 'vcf_read_error', __( 'No se pudo leer el archivo vCard subido.', 'aura-suite' ) );
        }

        // Unfold multi-line entries (líneas continuadas con espacio o tabulador)
        $content = preg_replace( '/\r?\n[ \t]/', '', $content );

        // Extraer bloques BEGIN:VCARD ... END:VCARD
        preg_match_all( '/BEGIN:VCARD.*?END:VCARD/si', $content, $matches );
        $vcard_blocks = $matches[0] ?? array();

        if ( empty( $vcard_blocks ) ) {
            return new WP_Error( 'vcf_no_cards', __( 'No se encontraron bloques vCard válidos en el archivo.', 'aura-suite' ) );
        }

        $stats = array(
            'total_rows' => count( $vcard_blocks ),
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'errors'     => 0,
            'details'    => array(),
        );

        foreach ( $vcard_blocks as $block ) {
            $parsed = self::parse_single_vcard( $block, $default_category );
            if ( empty( $parsed['first_name'] ) && empty( $parsed['organization'] ) ) {
                $stats['errors']++;
                continue;
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
                    } else {
                        $stats['updated']++;
                    }
                    continue;
                }
            }

            $new_id = Aura_Contacts::create_contact( $parsed );
            if ( is_wp_error( $new_id ) ) {
                $stats['errors']++;
            } else {
                $stats['created']++;
            }
        }

        $stats['message'] = sprintf(
            __( 'Importación vCard finalizada: %d procesados (%d creados, %d actualizados, %d omitidos).', 'aura-suite' ),
            $stats['total_rows'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped']
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

            // Limpiar escapes vCard
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

        return $data;
    }
}
