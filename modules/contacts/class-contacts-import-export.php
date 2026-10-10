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

        // Escribir a un stream en memoria para fgetcsv
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

        // Leer cabecera (longitud 0 = sin límite de longitud por línea)
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
            __( 'Importación CSV finalizada: %d procesados (%d creados, %d actualizados, %d omitidos, %d errores).', 'aura-suite' ),
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
     * Soporta: Google Contacts (ES/EN), Outlook CSV, Apple Contacts CSV, y CSV genérico.
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
            // Normalizar puntuación y espacios
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
                '/^(organization\s*1\s*name|organizacion\s*1\s*nombre|organización\s*1\s*nombre|company|empresa|organization|organizacion|organización|razon\s*social|razón\s*social)$/ui',
                '/^(organization|organizacion|organización|company)\s*(\d+)?\s*(name|nombre)?$/ui',
            ),
            'job_title'       => array(
                '/^(organization\s*1\s*title|organizacion\s*1\s*titulo|organización\s*1\s*título|job\s*title|cargo\s*\/\s*puesto|cargo|puesto|title|occupation|ocupacion|ocupación|position|posicion)$/ui',
            ),
            'email_primary'   => array(
                '/^(e\s*mail\s*1\s*value|correo\s*electronico\s*1\s*valor|correo\s*electrónico\s*1\s*valor|email\s*1|correo\s*1|e\s*mail\s*address|email\s*address|email\s*principal|correo\s*principal|primary\s*email)$/ui',
                '/^(email|e\s*mail|correo|correo\s*electronico|correo\s*electrónico)$/ui',
            ),
            'email_secondary' => array(
                '/^(e\s*mail\s*2\s*value|correo\s*electronico\s*2\s*valor|correo\s*electrónico\s*2\s*valor|email\s*2|correo\s*2|email\s*secundario|correo\s*secundario|e\s*mail\s*2\s*address|secondary\s*email)$/ui',
            ),
            'phone_primary'   => array(
                '/^(phone\s*1\s*value|telefono\s*1\s*valor|teléfono\s*1\s*valor|phone\s*1|telefono\s*1|teléfono\s*1|mobile\s*phone|celular|movil|móvil|primary\s*phone|telefono\s*principal|teléfono\s*principal)$/ui',
                '/^(phone|telefono|teléfono)$/ui',
            ),
            'phone_secondary' => array(
                '/^(phone\s*2\s*value|telefono\s*2\s*valor|teléfono\s*2\s*valor|phone\s*2|telefono\s*2|teléfono\s*2|business\s*phone|home\s*phone|telefono\s*secundario|teléfono\s*secundario|secondary\s*phone)$/ui',
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

        // Si hay nombre y segundo nombre
        if ( ! empty( $first_name ) && ! empty( $middle_name ) ) {
            $first_name = trim( $first_name . ' ' . $middle_name );
        }

        // Si first_name está vacío pero tenemos full_name
        if ( empty( $first_name ) && ! empty( $full_name ) ) {
            if ( empty( $last_name ) && strpos( $full_name, ' ' ) !== false ) {
                $parts      = explode( ' ', $full_name, 2 );
                $first_name = $parts[0];
                $last_name  = $parts[1];
            } else {
                $first_name = $full_name;
            }
        } elseif ( ! empty( $first_name ) && empty( $last_name ) && strpos( $first_name, ' ' ) !== false ) {
            // Si first_name vino compuesto y no hay last_name
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

        // Determinar tipo de contacto
        $contact_type = 'person';
        if ( empty( $first_name ) && empty( $last_name ) && ! empty( $organization ) ) {
            $contact_type = 'company';
            $first_name   = $organization;
        }

        // Fallback: Si no hay nombre ni organización, pero hay email o teléfono
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

        // Procesar Tags / Grupos de Google
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
                $stats['details'][] = sprintf( __( 'Tarjeta %d omitida: sin nombre ni organización.', 'aura-suite' ), $v_idx + 1 );
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
                        $stats['details'][] = sprintf( __( 'Error al actualizar tarjeta %d: %s', 'aura-suite' ), $v_idx + 1, $upd_res->get_error_message() );
                    } else {
                        $stats['updated']++;
                    }
                    continue;
                }
            }

            $new_id = Aura_Contacts::create_contact( $parsed );
            if ( is_wp_error( $new_id ) ) {
                $stats['errors']++;
                $stats['details'][] = sprintf( __( 'Error en tarjeta %d: %s', 'aura-suite' ), $v_idx + 1, $new_id->get_error_message() );
            } else {
                $stats['created']++;
            }
        }

        $stats['total']   = $stats['total_rows'];
        $stats['message'] = sprintf(
            __( 'Importación vCard finalizada: %d procesados (%d creados, %d actualizados, %d omitidos, %d errores).', 'aura-suite' ),
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
