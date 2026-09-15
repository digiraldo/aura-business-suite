<?php
/**
 * Clase: Importación de Transacciones CSV/Excel
 * Fase 4, Item 4.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Import {

    private static $transient_prefix = 'aura_import_';
    private static $max_file_size    = 52428800; // 50 MB (soporte ZIP con comprobantes)
    private static $max_rows         = 5000;

    /* ------------------------------------------------------------------
     * Bootstrap
     * ------------------------------------------------------------------ */

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'setup' ] );

        add_action( 'wp_ajax_aura_upload_import_file',    [ __CLASS__, 'ajax_upload' ] );
        add_action( 'wp_ajax_aura_validate_import',        [ __CLASS__, 'ajax_validate' ] );
        add_action( 'wp_ajax_aura_execute_import',         [ __CLASS__, 'ajax_execute' ] );
        add_action( 'wp_ajax_aura_rollback_import',        [ __CLASS__, 'ajax_rollback' ] );
        add_action( 'wp_ajax_aura_download_import_template', [ __CLASS__, 'ajax_template' ] );
        add_action( 'wp_ajax_aura_import_log_list',        [ __CLASS__, 'ajax_log_list' ] );
    }

    public static function setup() {
        self::create_table();
        self::maybe_add_batch_column();
        self::ensure_upload_dir();
    }

    /* ------------------------------------------------------------------
     * DB / Tabla de log
     * ------------------------------------------------------------------ */

    public static function create_table() {
        global $wpdb;
        $table          = $wpdb->prefix . 'aura_finance_import_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id      VARCHAR(36) NOT NULL UNIQUE,
            filename      VARCHAR(255) NOT NULL,
            rows_total    INT UNSIGNED DEFAULT 0,
            rows_imported INT UNSIGNED DEFAULT 0,
            rows_failed   INT UNSIGNED DEFAULT 0,
            status        ENUM('completed','rolled_back') DEFAULT 'completed',
            error_log     LONGTEXT,
            imported_by   BIGINT UNSIGNED NOT NULL,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batch (batch_id),
            INDEX idx_user  (imported_by)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private static function maybe_add_batch_column() {
        global $wpdb;
        $table  = $wpdb->prefix . 'aura_finance_transactions';
        $column = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'import_batch_id'" );
        if ( empty( $column ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `import_batch_id` VARCHAR(36) NULL DEFAULT NULL AFTER `deleted_by`" );
        }
    }

    private static function ensure_upload_dir() {
        $dir = self::get_import_dir();
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            file_put_contents( $dir . '.htaccess', "Deny from all\n" );
        }
    }

    private static function get_import_dir() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'aura-imports/';
    }

    /* ------------------------------------------------------------------
     * AJAX – Paso 1: Subir archivo
     * ------------------------------------------------------------------ */

    public static function ajax_upload() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        if ( empty( $_FILES['import_file'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No se recibió archivo', 'aura-suite' ) ] );
        }

        $file = $_FILES['import_file'];

        // Validar tipo
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'csv', 'xlsx', 'zip' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato no soportado. Use CSV, XLSX o ZIP (con comprobantes).', 'aura-suite' ) ] );
        }

        // Validar tamaño
        if ( $file['size'] > self::$max_file_size ) {
            wp_send_json_error( [ 'message' => __( 'El archivo supera el límite permitido (50 MB).', 'aura-suite' ) ] );
        }

        // Mover a directorio seguro
        $dir      = self::get_import_dir();
        $token    = wp_generate_uuid4();
        $dest     = $dir . $token . '.' . $ext;

        if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
            wp_send_json_error( [ 'message' => __( 'Error al guardar el archivo.', 'aura-suite' ) ] );
        }

        $requested_type = sanitize_key( $_POST['import_type'] ?? 'transactions' );
        $valid_types    = [ 'transactions', 'accounts', 'categories', 'areas', 'third_parties' ];

        $unzipped_dir = null;
        $sheet_file   = $dest;
        $parse_ext    = $ext;

        if ( $ext === 'zip' ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $unzip_target = trailingslashit( $dir ) . 'unzipped_' . $token . '/';
            wp_mkdir_p( $unzip_target );

            if ( class_exists( 'ZipArchive' ) ) {
                $zip = new ZipArchive();
                if ( $zip->open( $dest ) === true ) {
                    $zip->extractTo( $unzip_target );
                    $zip->close();
                } else {
                    @unlink( $dest );
                    wp_send_json_error( [ 'message' => __( 'No se pudo abrir el archivo ZIP.', 'aura-suite' ) ] );
                }
            } else {
                $unzip_res = unzip_file( $dest, $unzip_target );
                if ( is_wp_error( $unzip_res ) ) {
                    @unlink( $dest );
                    wp_send_json_error( [ 'message' => __( 'Error al descomprimir ZIP: ', 'aura-suite' ) . $unzip_res->get_error_message() ] );
                }
            }

            // Buscar hoja de datos dentro del ZIP
            $found_data_file = self::find_data_file_in_dir( $unzip_target );
            if ( ! $found_data_file ) {
                self::delete_directory( $unzip_target );
                @unlink( $dest );
                wp_send_json_error( [ 'message' => __( 'El archivo ZIP no contiene ninguna hoja de cálculo (.csv o .xlsx).', 'aura-suite' ) ] );
            }

            $sheet_file   = $found_data_file;
            $parse_ext    = strtolower( pathinfo( $sheet_file, PATHINFO_EXTENSION ) );
            $unzipped_dir = $unzip_target;
        }

        // Parsear
        $rows = $parse_ext === 'xlsx' ? self::parse_excel( $sheet_file ) : self::parse_csv( $sheet_file );

        if ( is_wp_error( $rows ) ) {
            if ( $unzipped_dir ) {
                self::delete_directory( $unzipped_dir );
            }
            @unlink( $dest );
            wp_send_json_error( [ 'message' => $rows->get_error_message() ] );
        }

        if ( count( $rows ) < 2 ) {
            if ( $unzipped_dir ) {
                self::delete_directory( $unzipped_dir );
            }
            @unlink( $dest );
            wp_send_json_error( [ 'message' => __( 'El archivo está vacío o sólo tiene encabezado.', 'aura-suite' ) ] );
        }

        $headers       = array_shift( $rows );
        $total_rows    = count( $rows );
        $preview       = array_slice( $rows, 0, 5 );
        $detected_type = self::detect_import_type( $headers );

        // Si no detecta por headers, usar tipo solicitado (fallback por UX)
        $import_type = $detected_type ?: ( in_array( $requested_type, $valid_types, true ) ? $requested_type : 'transactions' );
        $mapping     = self::auto_detect_mapping( $headers, $import_type );

        // Guardar en transient (1 hora)
        set_transient( self::$transient_prefix . $token, [
            'filepath'      => $sheet_file,
            'zip_dest'      => ( $ext === 'zip' ? $dest : null ),
            'unzipped_dir'  => $unzipped_dir,
            'headers'       => $headers,
            'filename'      => sanitize_file_name( $file['name'] ),
            'import_type'   => $import_type,
            'detected_type' => $detected_type,
        ], HOUR_IN_SECONDS );

        wp_send_json_success( [
            'token'         => $token,
            'import_type'   => $import_type,
            'detected_type' => $detected_type,
            'headers'       => $headers,
            'preview'       => $preview,
            'total_rows'    => $total_rows,
            'auto_mapping'  => $mapping,
            'filename'      => sanitize_file_name( $file['name'] ),
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX – Paso 3: Validar datos
     * ------------------------------------------------------------------ */

    public static function ajax_validate() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $token        = sanitize_text_field( $_POST['token'] ?? '' );
        $mapping      = isset( $_POST['mapping'] ) ? (array) $_POST['mapping'] : [];
        $request_type = sanitize_key( $_POST['import_type'] ?? '' );

        $transient = get_transient( self::$transient_prefix . $token );
        if ( ! $transient ) {
            wp_send_json_error( [ 'message' => __( 'Sesión de importación expirada. Suba el archivo de nuevo.', 'aura-suite' ) ] );
        }

        $filepath = $transient['filepath'];
        $headers  = $transient['headers'];
        $stored_type = sanitize_key( $transient['import_type'] ?? '' );

        // Leer todas las filas
        $ext  = strtolower( pathinfo( $filepath, PATHINFO_EXTENSION ) );
        $rows = $ext === 'xlsx' ? self::parse_excel( $filepath ) : self::parse_csv( $filepath );

        if ( is_wp_error( $rows ) ) {
            wp_send_json_error( [ 'message' => $rows->get_error_message() ] );
        }

        array_shift( $rows ); // quitar encabezado

        $valid_types = [ 'transactions', 'accounts', 'categories', 'areas', 'third_parties' ];
        $import_type = $stored_type ?: self::detect_import_type( $headers );
        if ( ! $import_type && in_array( $request_type, $valid_types, true ) ) {
            $import_type = $request_type;
        }

        if ( ! $import_type ) {
            wp_send_json_error( [
                'message' => __( 'No se pudo determinar el tipo de importación (revise columnas).', 'aura-suite' )
            ] );
        }

        if ( empty( $mapping ) ) {
            $mapping = self::auto_detect_mapping( $headers, $import_type );
        }

        switch ( $import_type ) {
            case 'accounts':
                $result = self::validate_accounts( $rows, $headers, $mapping );
                break;
            case 'categories':
                $result = self::validate_categories( $rows, $headers, $mapping );
                break;
            case 'areas':
                $result = self::validate_areas( $rows, $headers, $mapping );
                break;
            case 'third_parties':
                $result = self::validate_third_parties( $rows, $headers, $mapping );
                break;
            case 'transactions':
            default:
                $result = self::validate_transactions( $rows, $headers, $mapping );
                break;
        }

        set_transient( self::$transient_prefix . $token, array_merge( $transient, [
            'import_type' => $import_type,
            'mapping'     => $mapping,
        ] ), HOUR_IN_SECONDS );

        wp_send_json_success( $result );
    }

    /* ------------------------------------------------------------------
     * AJAX – Paso 4: Ejecutar importación
     * ------------------------------------------------------------------ */

    public static function ajax_execute() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        @set_time_limit( 300 );
        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            wp_raise_memory_limit( 'admin' );
        }

        $token          = sanitize_text_field( $_POST['token'] ?? '' );
        $mapping        = isset( $_POST['mapping'] ) ? (array) $_POST['mapping'] : [];
        $options        = isset( $_POST['options'] ) ? (array) $_POST['options'] : [];
        $request_type   = sanitize_key( $_POST['import_type'] ?? '' );

        $transient = get_transient( self::$transient_prefix . $token );
        if ( ! $transient ) {
            wp_send_json_error( [ 'message' => __( 'Sesión expirada. Suba el archivo de nuevo.', 'aura-suite' ) ] );
        }

        $filepath = $transient['filepath'];
        $filename = $transient['filename'];
        $stored_type = sanitize_key( $transient['import_type'] ?? '' );
        $valid_types = [ 'transactions', 'accounts', 'categories', 'areas', 'third_parties' ];

        $ext  = strtolower( pathinfo( $filepath, PATHINFO_EXTENSION ) );
        $rows = $ext === 'xlsx' ? self::parse_excel( $filepath ) : self::parse_csv( $filepath );

        if ( is_wp_error( $rows ) ) {
            wp_send_json_error( [ 'message' => $rows->get_error_message() ] );
        }

        array_shift( $rows );

        $import_type = $stored_type ?: $request_type;
        if ( ! in_array( $import_type, $valid_types, true ) ) {
            $import_type = self::detect_import_type( $transient['headers'] ?? [] ) ?: 'transactions';
        }

        if ( empty( $mapping ) ) {
            $mapping = self::auto_detect_mapping( $transient['headers'] ?? [], $import_type );
        }

        switch ( $import_type ) {
            case 'accounts':
                $result = self::execute_accounts( $rows, $mapping, $options, $filename );
                break;
            case 'categories':
                $result = self::execute_categories( $rows, $mapping, $options, $filename );
                break;
            case 'areas':
                $result = self::execute_areas( $rows, $mapping, $options, $filename );
                break;
            case 'third_parties':
                $result = self::execute_third_parties( $rows, $mapping, $options, $filename );
                break;
            case 'transactions':
            default:
                $result = self::execute_transactions( $rows, $mapping, $options, $filename, $transient );
                break;
        }

        // Limpiar transient y archivos temporales
        delete_transient( self::$transient_prefix . $token );
        @unlink( $filepath );
        if ( ! empty( $transient['zip_dest'] ) ) {
            @unlink( $transient['zip_dest'] );
        }
        if ( ! empty( $transient['unzipped_dir'] ) ) {
            self::delete_directory( $transient['unzipped_dir'] );
        }

        do_action( 'aura_finance_import_executed', ( $result['imported'] + $result['failed'] ), $result['imported'], $result['failed'] );
        wp_send_json_success( $result );
    }

    /* ------------------------------------------------------------------
     * AJAX – Rollback
     * ------------------------------------------------------------------ */

    public static function ajax_rollback() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'aura_finance_delete_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para deshacer importaciones', 'aura-suite' ) ], 403 );
        }

        $batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );
        if ( ! $batch_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de lote no válido', 'aura-suite' ) ] );
        }

        global $wpdb;
        $tx_table  = $wpdb->prefix . 'aura_finance_transactions';
        $log_table = $wpdb->prefix . 'aura_finance_import_log';

        $log = $wpdb->get_row( $wpdb->prepare( "SELECT imported_by, created_at, status FROM {$log_table} WHERE batch_id = %s", $batch_id ) );
        if ( ! $log ) {
            wp_send_json_error( [ 'message' => __( 'Importación no encontrada', 'aura-suite' ) ] );
        }

        // Verificar que el lote pertenezca al usuario actual o sea admin
        if ( ! current_user_can( 'manage_options' ) && (int) $log->imported_by !== get_current_user_id() ) {
            wp_send_json_error( [ 'message' => __( 'No tienes permiso para deshacer esta importación', 'aura-suite' ) ] );
        }

        if ( $log->status === 'rolled_back' ) {
            wp_send_json_error( [ 'message' => __( 'Esta importación ya fue revertida', 'aura-suite' ) ] );
        }

        // Verificar que sea reciente (< 1 mes)
        $created_ts = strtotime( $log->created_at );
        if ( ! $created_ts ) {
            wp_send_json_error( [ 'message' => __( 'No se pudo validar la fecha de importación', 'aura-suite' ) ] );
        }

        $age = time() - $created_ts;
        if ( $age > MONTH_IN_SECONDS ) {
            wp_send_json_error( [ 'message' => __( 'Solo se puede revertir dentro del mes siguiente a la importación', 'aura-suite' ) ] );
        }

        $count = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$tx_table}
                 SET deleted_at = %s, deleted_by = %d
                 WHERE import_batch_id = %s",
                current_time( 'mysql' ),
                get_current_user_id(),
                $batch_id
            )
        );

        if ( false === $count ) {
            wp_send_json_error( [ 'message' => __( 'No se pudo revertir la importación', 'aura-suite' ) ] );
        }

        $wpdb->update( $log_table, [ 'status' => 'rolled_back' ], [ 'batch_id' => $batch_id ], [ '%s' ], [ '%s' ] );

        wp_send_json_success( [ 'reverted' => (int) $count ] );
    }

    /* ------------------------------------------------------------------
     * AJAX – Plantilla CSV
     * ------------------------------------------------------------------ */

    public static function ajax_template() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );

        $type = sanitize_key( $_REQUEST['type'] ?? 'transactions' );
        $templates = [
            'transactions'  => 'plantilla-transacciones.csv',
            'categories'    => 'plantilla-categorias-con-subcategorias.csv',
            'accounts'      => 'plantilla-cuentas-bancarias.csv',
            'areas'         => 'plantilla-areas.csv',
            'third_parties' => 'plantilla-terceros.csv',
        ];

        if ( ! isset( $templates[ $type ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Tipo inválido', 'aura-suite' ) ] );
        }

        $file = AURA_PLUGIN_DIR . 'documentacion/' . $templates[ $type ];

        if ( ! file_exists( $file ) ) {
            wp_send_json_error( [ 'message' => __( 'Plantilla no encontrada', 'aura-suite' ) ] );
        }

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        readfile( $file );
        exit;
    }

    /* ------------------------------------------------------------------
     * AJAX – Log de importaciones
     * ------------------------------------------------------------------ */

    public static function ajax_log_list() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );

        global $wpdb;
        $log_table = $wpdb->prefix . 'aura_finance_import_log';
        $user_id   = get_current_user_id();

        $where = current_user_can( 'manage_options' ) ? '' : $wpdb->prepare( 'WHERE imported_by = %d', $user_id );
        $logs  = $wpdb->get_results( "SELECT id, batch_id, filename, rows_total, rows_imported, rows_failed, status, created_at FROM {$log_table} {$where} ORDER BY created_at DESC LIMIT 20" );

        wp_send_json_success( [ 'logs' => $logs ] );
    }

    /* ------------------------------------------------------------------
     * Parsing CSV
     * ------------------------------------------------------------------ */

    private static function parse_csv( $filepath ) {
        $rows = [];

        // Detectar encoding y convertir a UTF-8
        $content = file_get_contents( $filepath );
        if ( str_starts_with( $content, "\xEF\xBB\xBF" ) ) {
            $content = substr( $content, 3 );
            file_put_contents( $filepath, $content );
        }
        if ( function_exists( 'mb_detect_encoding' ) ) {
            $enc = mb_detect_encoding( $content, [ 'UTF-8', 'ISO-8859-1', 'Windows-1252' ], true );
            if ( $enc && $enc !== 'UTF-8' ) {
                $content = mb_convert_encoding( $content, 'UTF-8', $enc );
                file_put_contents( $filepath, $content );
            }
        }

        // Intentar detectar delimitador
        $sample    = substr( $content, 0, 2000 );
        $delim     = ',';
        $counts    = [ ',' => substr_count( $sample, ',' ), ';' => substr_count( $sample, ';' ), "\t" => substr_count( $sample, "\t" ) ];
        arsort( $counts );
        $delim     = key( $counts );

        $handle = fopen( $filepath, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'file_read', __( 'No se pudo leer el archivo CSV', 'aura-suite' ) );
        }

        while ( ( $row = fgetcsv( $handle, 4096, $delim ) ) !== false ) {
            if ( array_filter( $row, fn( $v ) => $v !== '' ) ) {
                $rows[] = $row;
            }
        }

        fclose( $handle );

        if ( count( $rows ) > self::$max_rows + 1 ) {
            return new WP_Error( 'too_many', sprintf( __( 'El archivo supera %d registros', 'aura-suite' ), self::$max_rows ) );
        }

        return $rows;
    }

    /* ------------------------------------------------------------------
     * Parsing Excel
     * ------------------------------------------------------------------ */

    private static function parse_excel( $filepath ) {
        $autoload = AURA_PLUGIN_DIR . 'vendor/autoload.php';
        if ( ! file_exists( $autoload ) ) {
            return new WP_Error( 'no_vendor', __( 'PhpSpreadsheet no disponible', 'aura-suite' ) );
        }

        require_once $autoload;

        try {
            $reader    = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly( true );
            $spreadsheet = $reader->load( $filepath );
            $sheet       = $spreadsheet->getActiveSheet();
            $data        = $sheet->toArray( null, true, true, false );

            // Filtrar filas vacías
            $rows = array_filter( $data, function( $row ) {
                return ! empty( array_filter( $row, fn( $v ) => $v !== null && $v !== '' ) );
            } );

            $rows = array_values( $rows );

            if ( count( $rows ) > self::$max_rows + 1 ) {
                return new WP_Error( 'too_many', sprintf( __( 'El archivo supera %d registros', 'aura-suite' ), self::$max_rows ) );
            }

            // Convertir todos los valores a string
            $rows = array_map( function( $row ) {
                return array_map( fn( $v ) => $v !== null ? (string) $v : '', $row );
            }, $rows );

            return $rows;
        } catch ( \Exception $e ) {
            return new WP_Error( 'xlsx_parse', $e->getMessage() );
        }
    }

    /* ------------------------------------------------------------------
     * Auto-detección de mapeo de columnas
     * ------------------------------------------------------------------ */

    private static function auto_detect_mapping( $headers, $import_type = 'transactions' ) {
        $aliases_by_type = [
            'transactions' => [
                'transaction_date'       => [ 'fecha', 'date', 'transaction_date', 'fecha_transaccion', 'fecha transaccion', 'f. transaccion' ],
                'transaction_type'       => [ 'tipo', 'type', 'transaction_type', 'tipo_transaccion', 'tipo transaccion', 'class', 'clase' ],
                'category_id'            => [ 'categoria', 'category', 'category_name', 'nombre_categoria', 'category_id', 'category_slug', 'rubro', 'cuenta', 'account' ],
                'amount'                 => [ 'monto', 'amount', 'importe', 'valor', 'total', 'value', 'precio', 'price' ],
                'description'            => [ 'descripcion', 'description', 'concepto', 'concept', 'detalle', 'detail', 'nombre', 'name' ],
                'created_by'             => [ 'creado_por', 'created_by', 'creado por', 'autor', 'author', 'creador', 'usuario_creador', 'registrado_por', 'registrado por', 'creado' ],
                'approved_by'            => [ 'aprobado_por', 'approved_by', 'aprobado por', 'aprobador', 'approver', 'autorizado_por', 'autorizado por', 'aprobado' ],
                'notes'                  => [ 'notas', 'notes', 'observaciones', 'comentario', 'comments', 'remarks' ],
                'payment_method'         => [ 'metodo_pago', 'metodo de pago', 'payment_method', 'payment method', 'pago', 'medio_pago', 'forma_pago', 'method' ],
                'reference_number'       => [ 'referencia', 'n referencia', 'n° referencia', 'no referencia', 'reference', 'reference_number', 'numero_referencia', 'numero referencia', 'ref', 'numero', 'number', 'comprobante' ],
                'source_account_id'      => [ 'source_account_id', 'source_account', 'cuenta_origen_id', 'cuenta_origen', 'cuenta origen', 'origen' ],
                'destination_account_id' => [ 'destination_account_id', 'destination_account', 'cuenta_destino_id', 'cuenta_destino', 'cuenta destino', 'destino' ],
                'related_user_id'        => [ 'related_user_id', 'related_user', 'usuario_vinculado_id', 'usuario_vinculado', 'usuario vinculado', 'usuario_relacionado', 'usuario relacionado', 'user_id', 'usuario', 'user' ],
                'status'                 => [ 'status', 'estado' ],
                // Campos 100% compatibles
                'area_id'                => [ 'area_id', 'area', 'programa', 'area/programa', 'area_programa', 'area / programa' ],
                'recipient_payer'        => [ 'recipient_payer', 'beneficiario/pagador', 'beneficiario / pagador', 'beneficiario', 'pagador', 'destinatario' ],
                'receipt_file'           => [ 'receipt_file', 'recibo', 'archivo_recibo', 'comprobante_archivo' ],
                'tags'                   => [ 'tags', 'etiquetas' ],
                'related_module'         => [ 'related_module', 'modulo' ],
                'related_item_id'        => [ 'related_item_id', 'item_id' ],
                'related_action'         => [ 'related_action', 'accion' ],
                'related_user_concept'   => [ 'related_user_concept', 'concepto_usuario', 'concepto usuario', 'concepto usuario vinculado', 'concepto_usuario_vinculado' ],
                'expense_category_id'    => [ 'expense_category_id', 'cat_gasto' ],
            ],
            'accounts' => [
                'name'                  => [ 'name', 'nombre', 'nombre_cuenta', 'cuenta' ],
                'account_type'          => [ 'account_type', 'tipo', 'tipo_cuenta' ],
                'currency'              => [ 'currency', 'moneda' ],
                'institution'           => [ 'institution', 'institucion', 'banco', 'entidad' ],
                'account_number_masked' => [ 'account_number_masked', 'cuenta_enmascarada', 'numero_enmascarado' ],
                'initial_balance'       => [ 'initial_balance', 'saldo_inicial', 'balance_inicial' ],
                'is_active'             => [ 'is_active', 'activo', 'activa', 'estado' ],
                'meta_json'             => [ 'meta_json', 'meta', 'json' ],
            ],
            'categories' => [
                'name'                 => [ 'name', 'nombre', 'nombre_categoria', 'categoria' ],
                'slug'                 => [ 'slug', 'identificador' ],
                'type'                 => [ 'type', 'tipo', 'tipo_categoria' ],
                'parent_category_slug' => [ 'parent_category_slug', 'parent_slug', 'slug_padre', 'categoria_padre_slug', 'padre', 'parent' ],
                'color'                => [ 'color', 'hex_color', 'color_hex' ],
                'icon'                 => [ 'icon', 'icono', 'emoji', 'dashicon', 'dashicons' ],
                'description'          => [ 'description', 'descripcion', 'detalle' ],
                'is_active'            => [ 'is_active', 'activo', 'activa', 'estado' ],
                'display_order'        => [ 'display_order', 'orden', 'order', 'posicion' ],
                'is_capex'             => [ 'is_capex', 'capex', 'gasto_capital', 'es_capex' ],
            ],
            'areas' => [
                'name'                => [ 'name', 'nombre', 'nombre_area', 'area' ],
                'slug'                => [ 'slug', 'identificador', 'codigo' ],
                'type'                => [ 'type', 'tipo', 'tipo_area' ],
                'parent_area_slug'    => [ 'parent_area_slug', 'parent_slug', 'area_padre', 'area_padre_slug', 'padre', 'parent' ],
                'description'         => [ 'description', 'descripcion', 'detalle' ],
                'color'               => [ 'color', 'hex_color', 'color_hex' ],
                'icon'                => [ 'icon', 'icono', 'emoji', 'dashicon', 'dashicons' ],
                'responsible_user_id' => [ 'responsible_user_id', 'responsible_user', 'responsable', 'usuario_responsable', 'lider' ],
                'status'              => [ 'status', 'estado' ],
                'sort_order'          => [ 'sort_order', 'orden', 'order', 'posicion' ],
            ],
            'third_parties' => [
                'full_name'        => [ 'full_name', 'nombre', 'nombre_completo', 'razon_social', 'tercero', 'nombre_tercero' ],
                'commercial_name'  => [ 'commercial_name', 'nombre_comercial', 'comercial' ],
                'party_type'       => [ 'party_type', 'tipo', 'tipo_tercero', 'tipo_persona' ],
                'accounting_role'  => [ 'accounting_role', 'rol', 'rol_contable', 'clasificacion' ],
                'tax_id_type'      => [ 'tax_id_type', 'tipo_documento', 'tipo_id', 'tipo_doc' ],
                'document_id'      => [ 'document_id', 'documento', 'numero_documento', 'nit', 'cedula', 'rfc', 'identificacion' ],
                'phone'            => [ 'phone', 'telefono', 'tel', 'celular' ],
                'email'            => [ 'email', 'correo', 'correo_electronico' ],
                'website'          => [ 'website', 'sitio_web', 'web', 'url' ],
                'address'          => [ 'address', 'direccion', 'ubicacion' ],
                'notes'            => [ 'notes', 'notas', 'observaciones', 'comentarios' ],
                'is_active'        => [ 'is_active', 'activo', 'activa', 'estado' ],
            ],
        ];

        $system_fields = $aliases_by_type[ $import_type ] ?? $aliases_by_type['transactions'];
        $mapping = [];

        foreach ( $headers as $idx => $header ) {
            $cleaned = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header );
            $normalized = strtolower( trim( preg_replace( '/\s+/', ' ', $cleaned ) ) );
            if ( function_exists( 'remove_accents' ) ) {
                $normalized = remove_accents( $normalized );
            }
            foreach ( $system_fields as $field => $aliases ) {
                if ( isset( $mapping[ $field ] ) ) {
                    continue;
                }
                if ( in_array( $normalized, $aliases, true ) ) {
                    $mapping[ $field ] = $idx;
                    break;
                }
            }
        }

        return $mapping;
    }

    public static function detect_import_type( $headers ) {
        $h_lower = array_map( static function( $h ) {
            $cleaned = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h );
            return strtolower( trim( $cleaned ) );
        }, (array) $headers );

        $has = static function( $required ) use ( $h_lower ) {
            return count( array_intersect( $required, $h_lower ) ) === count( $required );
        };

        if ( $has( [ 'transaction_date', 'transaction_type', 'amount' ] ) || $has( [ 'fecha', 'tipo', 'monto' ] ) ) {
            return 'transactions';
        }

        if ( $has( [ 'name', 'account_type', 'currency' ] ) || $has( [ 'nombre', 'tipo_cuenta', 'moneda' ] ) ) {
            return 'accounts';
        }

        if ( in_array( 'parent_area_slug', $h_lower, true ) || in_array( 'area_padre', $h_lower, true ) || in_array( 'tipo_area', $h_lower, true ) ) {
            return 'areas';
        }

        if ( in_array( 'party_type', $h_lower, true ) || in_array( 'accounting_role', $h_lower, true ) || in_array( 'commercial_name', $h_lower, true ) || in_array( 'tax_id_type', $h_lower, true ) || in_array( 'rol_contable', $h_lower, true ) || in_array( 'tipo_tercero', $h_lower, true ) ) {
            return 'third_parties';
        }

        if ( $has( [ 'name', 'slug', 'type' ] ) || $has( [ 'nombre', 'slug', 'tipo' ] ) || in_array( 'parent_category_slug', $h_lower, true ) || in_array( 'slug_padre', $h_lower, true ) ) {
            return 'categories';
        }

        return null;
    }

    /* ------------------------------------------------------------------
     * Helpers de validación y normalización
     * ------------------------------------------------------------------ */

    private static function parse_date( $value ) {
        if ( empty( $value ) ) return false;

        $value = trim( $value );

        $formats = [
            'd/m/Y', 'd-m-Y', 'd.m.Y',
            'Y-m-d', 'Y/m/d',
            'm/d/Y', 'm-d-Y',
            'd/m/y', 'Y-m-d H:i:s',
        ];

        foreach ( $formats as $fmt ) {
            $dt = DateTime::createFromFormat( $fmt, $value );
            if ( $dt && $dt->format( 'Y' ) >= 2000 ) {
                return $dt->format( 'Y-m-d' );
            }
        }

        // Intentar strtotime como fallback
        $ts = strtotime( $value );
        if ( $ts && $ts > mktime( 0, 0, 0, 1, 1, 2000 ) ) {
            return date( 'Y-m-d', $ts );
        }

        return false;
    }

    private static function parse_amount( $value ) {
        if ( $value === '' || $value === null ) return false;
        $value = trim( $value );

        // Remover símbolo de moneda y espacios
        $value = preg_replace( '/[^\d.,\-]/', '', $value );
        if ( $value === '' ) return false;

        // Detectar formato: 1.500,00 (europeo) vs 1,500.00 (americano)
        if ( preg_match( '/^[\d]+(\.\d{3})+(,\d{1,2})?$/', $value ) ) {
            // Formato europeo: 1.500,00
            $value = str_replace( '.', '', $value );
            $value = str_replace( ',', '.', $value );
        } elseif ( preg_match( '/^[\d]+(,\d{3})+(\.?\d{1,2})?$/', $value ) ) {
            // Formato americano: 1,500.00
            $value = str_replace( ',', '', $value );
        } else {
            // Asumir coma como decimal
            $value = str_replace( ',', '.', $value );
        }

        if ( ! is_numeric( $value ) ) return false;

        $amount = (float) $value;
        return $amount >= 0 ? $amount : false;
    }

    private static function normalize_type( $value ) {
        if ( empty( $value ) ) return false;
        $v = function_exists('remove_accents') ? remove_accents(trim((string)$value)) : trim((string)$value);
        $v = strtolower(preg_replace('/\s+/', ' ', $v));

        if ( in_array( $v, [ 'income', 'ingreso', 'ingresos', 'i', 'in', 'entrada', 'entradas', 'credit', 'credito', 'abono', 'cobro' ], true ) ) {
            return 'income';
        }
        if ( in_array( $v, [ 'expense', 'egreso', 'egresos', 'gasto', 'gastos', 'e', 'ex', 'out', 'salida', 'salidas', 'debit', 'debito', 'pago', 'pagos' ], true ) ) {
            return 'expense';
        }
        // 🏗️ Gastos de Capital (CapEx) — financiados por donaciones externas o fondos de inversión
        if ( in_array( $v, [ 'capital', 'cap', 'capex', 'gastos capital', 'gasto capital', 'gastos de capital', 'gasto de capital', 'capital expense', 'capital expenses', 'inversion', 'inversiones', 'donacion', 'donaciones' ], true ) ) {
            return 'capital';
        }
        if ( in_array( $v, [ 'transfer', 'transferencia', 'transferencias', 'traspaso', 'traspasos', 't' ], true ) ) {
            return 'transfer';
        }
        return false;
    }

    private static function normalize_status( $value ) {
        if ( $value === null || $value === '' ) {
            return null;
        }

        $v = function_exists('remove_accents') ? remove_accents(trim((string)$value)) : trim((string)$value);
        $v = strtolower(preg_replace('/\s+/', ' ', $v));

        if ( in_array( $v, [ 'approved', 'aprobado', 'aprobada', 'aprobados', 'aprobadas', 'a', 'ok' ], true ) ) {
            return 'approved';
        }
        if ( in_array( $v, [ 'pending', 'pendiente', 'pendientes', 'p' ], true ) ) {
            return 'pending';
        }
        if ( in_array( $v, [ 'rejected', 'rechazado', 'rechazada', 'rechazados', 'rechazadas', 'r' ], true ) ) {
            return 'rejected';
        }
        if ( in_array( $v, [ 'draft', 'borrador', 'borradores', 'b', 'd' ], true ) ) {
            return 'draft';
        }
        if ( in_array( $v, [ 'void', 'anulado', 'anulada', 'anulados', 'anuladas', 'cancelado', 'cancelada', 'cancelados', 'canceladas', 'v' ], true ) ) {
            return 'void';
        }

        return false;
    }

    private static function normalize_payment_method( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $v = function_exists('remove_accents') ? remove_accents(trim((string)$value)) : trim((string)$value);
        $v = strtolower(preg_replace('/\s+/', ' ', $v));

        if ( in_array( $v, [ 'cash', 'efectivo', 'cash/efectivo', 'metalico' ], true ) ) {
            return 'cash';
        }
        if ( in_array( $v, [ 'transfer', 'transferencia', 'transferencia bancaria', 'bank_transfer', 'deposito', 'deposito bancario' ], true ) ) {
            return 'transfer';
        }
        if ( in_array( $v, [ 'check', 'cheque', 'cheque bancario' ], true ) ) {
            return 'check';
        }
        if ( in_array( $v, [ 'credit_card', 'tarjeta de credito', 'tarjeta credito', 't. credito', 't.credito', 'credito' ], true ) ) {
            return 'credit_card';
        }
        if ( in_array( $v, [ 'debit_card', 'tarjeta de debito', 'tarjeta debito', 't. debito', 't.debito', 'debito' ], true ) ) {
            return 'debit_card';
        }
        if ( in_array( $v, [ 'card', 'tarjeta', 'tarjeta bancaria', 'pos', 'datafono' ], true ) ) {
            return 'card';
        }
        if ( in_array( $v, [ 'other', 'otro', 'otra', 'otros', 'otras' ], true ) ) {
            return 'other';
        }

        return sanitize_text_field( $value );
    }

    private static function normalize_concept( $value, $type = '', $related_user_id = null ) {
        if ( empty( $value ) ) {
            if ( ! empty( $related_user_id ) && (int) $related_user_id > 0 ) {
                return ( $type === 'income' ) ? 'charge_to_user' : 'payment_to_user';
            }
            return 'unlinked';
        }

        $v = function_exists('remove_accents') ? remove_accents(trim((string)$value)) : trim((string)$value);
        $v = strtolower(preg_replace('/\s+/', ' ', $v));

        if ( in_array( $v, [ 'salary', 'pago de salario/nomina', 'pago de salario/nómina', 'pago de salario', 'pago de nomina', 'pago de nómina', 'sueldo', 'salario', 'nomina', 'pago sueldo', 'pago nómina', 'sueldos', 'salarios' ], true ) ) {
            return 'salary';
        }
        if ( in_array( $v, [ 'payment_to_user', 'pago realizado a un usuario', 'pago a usuario', 'pago a colaborador', 'pago a persona', 'pago a terceros' ], true ) ) {
            return 'payment_to_user';
        }
        if ( in_array( $v, [ 'charge_to_user', 'cobro realizado a un usuario', 'cobro a usuario', 'cobro a colaborador', 'cobro a persona', 'cobro' ], true ) ) {
            return 'charge_to_user';
        }
        if ( in_array( $v, [ 'scholarship', 'beca asignada', 'beca', 'becas', 'subsidio', 'auxilio', 'auxilio educativo' ], true ) ) {
            return 'scholarship';
        }
        if ( in_array( $v, [ 'loan_payment', 'pago de prestamo', 'prestamo', 'prestamos', 'credito', 'creditos', 'abono prestamo' ], true ) ) {
            return 'loan_payment';
        }
        if ( in_array( $v, [ 'refund', 'reembolso', 'devolucion', 'devolucion de dinero', 'reintegro' ], true ) ) {
            return 'refund';
        }
        if ( in_array( $v, [ 'expense_reimbursement', 'reembolso de gastos', 'reembolso de gasto', 'legalizacion de gastos', 'reintegro de gastos' ], true ) ) {
            return 'expense_reimbursement';
        }
        if ( in_array( $v, [ 'unlinked', 'sin vinculacion (general)', 'sin vinculacion', 'sin vinculo', 'general', 'institucional' ], true ) ) {
            return 'unlinked';
        }

        return sanitize_key( $value ) ?: 'unlinked';
    }

    public static function get_eligible_wp_users() {
        $users = [];
        if ( class_exists( 'Aura_Roles_Manager' ) && method_exists( 'Aura_Roles_Manager', 'get_aura_users' ) ) {
            $aura_users = Aura_Roles_Manager::get_aura_users();
            foreach ( (array) $aura_users as $u ) {
                $uid = is_object( $u ) ? (int) $u->ID : (int) ( $u['ID'] ?? 0 );
                $dname = is_object( $u ) ? $u->display_name : ( $u['display_name'] ?? '' );
                $login = is_object( $u ) ? $u->user_login : ( $u['user_login'] ?? '' );
                if ( $uid > 0 ) {
                    $users[ $uid ] = [
                        'id'           => $uid,
                        'display_name' => $dname ?: $login,
                        'user_login'   => $login,
                    ];
                }
            }
        }

        // Asegurar que administradores de WP siempre estén incluidos
        $admins = get_users( [ 'role' => 'administrator' ] );
        foreach ( (array) $admins as $admin ) {
            $uid = (int) $admin->ID;
            if ( ! isset( $users[ $uid ] ) ) {
                $users[ $uid ] = [
                    'id'           => $uid,
                    'display_name' => $admin->display_name ?: $admin->user_login,
                    'user_login'   => $admin->user_login,
                ];
            }
        }

        if ( empty( $users ) ) {
            $all = get_users( [ 'number' => 200 ] );
            foreach ( (array) $all as $u ) {
                $users[ (int) $u->ID ] = [
                    'id'           => (int) $u->ID,
                    'display_name' => $u->display_name ?: $u->user_login,
                    'user_login'   => $u->user_login,
                ];
            }
        }

        return array_values( $users );
    }

    public static function get_active_categories_list() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        $cats = $wpdb->get_results( "SELECT id, name, slug, type, color FROM {$table} WHERE is_active = 1 ORDER BY name ASC", ARRAY_A );
        return is_array( $cats ) ? $cats : [];
    }

    public static function match_wp_user( $name, $eligible_users = null ) {
        if ( empty( $name ) ) {
            return false;
        }
        $name_clean = trim( (string) $name );
        if ( is_numeric( $name_clean ) ) {
            $user = get_user_by( 'id', (int) $name_clean );
            return $user ? (int) $user->ID : false;
        }

        if ( $eligible_users === null ) {
            $eligible_users = self::get_eligible_wp_users();
        }

        $name_lower = strtolower( $name_clean );

        // 1. Coincidencia exacta por display_name o user_login
        foreach ( $eligible_users as $u ) {
            if ( strtolower( $u['display_name'] ) === $name_lower || strtolower( $u['user_login'] ) === $name_lower ) {
                return (int) $u['id'];
            }
        }

        // 2. Coincidencia parcial (subcadena)
        foreach ( $eligible_users as $u ) {
            if ( stripos( $u['display_name'], $name_clean ) !== false || stripos( $name_clean, $u['display_name'] ) !== false ) {
                return (int) $u['id'];
            }
        }

        // 3. Consulta directa en wp_users como fallback
        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE LOWER(display_name) = LOWER(%s) OR LOWER(user_login) = LOWER(%s) LIMIT 1",
            $name_clean, $name_clean
        ) );

        return $id ? (int) $id : false;
    }

    private static function find_category( $value ) {
        if ( empty( $value ) ) return false;
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        // Por ID numérico
        if ( is_numeric( $value ) ) {
            return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", (int) $value ) );
        }

        // Por nombre exacto
        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s", $value ) );
        if ( $id ) return $id;

        // Por nombre insensible a mayúsculas
        return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE LOWER(name) = LOWER(%s)", $value ) );
    }

    private static function find_account( $value ) {
        if ( $value === null || $value === '' ) return false;

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_accounts';
        $value = trim( (string) $value );

        if ( is_numeric( $value ) ) {
            $id = (int) $value;
            return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $id ) );
        }

        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s LIMIT 1", $value ) );
        if ( $id ) {
            return (int) $id;
        }

        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE LOWER(name) = LOWER(%s) LIMIT 1", $value ) );
        return $id ? (int) $id : false;
    }

    private static function find_related_user( $value ) {
        if ( $value === null || $value === '' ) return false;

        $value = trim( (string) $value );
        if ( is_numeric( $value ) ) {
            $user = get_user_by( 'id', (int) $value );
            return $user ? (int) $user->ID : false;
        }

        $user = get_user_by( 'email', $value );
        if ( $user ) return (int) $user->ID;

        $user = get_user_by( 'login', $value );
        if ( $user ) return (int) $user->ID;

        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE LOWER(display_name) = LOWER(%s) LIMIT 1",
            $value
        ) );

        return $id ? (int) $id : false;
    }

    private static function get_transaction_table_columns() {
        static $cols = null;

        if ( is_array( $cols ) ) {
            return $cols;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $rows = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
        $cols = is_array( $rows ) ? $rows : [];

        return $cols;
    }

    private static function create_category( $name, $type = 'expense' ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'aura_finance_categories';
        $slug   = sanitize_title( $name );
        // Asegurar slug único
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) );
        if ( $exists ) {
            $slug = $slug . '-' . time();
        }
        $user_id = get_current_user_id() ?: 1;
        $wpdb->insert( $table, [
            'name'        => sanitize_text_field( $name ),
            'slug'        => $slug,
            'type'        => in_array( $type, [ 'income', 'expense', 'capital' ], true ) ? $type : 'expense',
            'description' => __( 'Creada automáticamente al importar', 'aura-suite' ),
            'color'       => $type === 'capital' ? '#e67e22' : '#607D8B',
            'icon'        => $type === 'capital' ? 'dashicons-building' : 'dashicons-category',
            'is_active'   => 1,
            'created_by'  => $user_id,
            'created_at'  => current_time( 'mysql' ),
        ], [ '%s','%s','%s','%s','%s','%s','%d','%d','%s' ] );
        return $wpdb->insert_id ?: false;
    }

    private static function is_duplicate( $date, $amount, $description ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';

        $existing = $wpdb->get_results( $wpdb->prepare(
            "SELECT description FROM {$table} WHERE transaction_date = %s AND amount = %f AND deleted_at IS NULL",
            $date, $amount
        ) );

        foreach ( $existing as $row ) {
            similar_text( strtolower( $description ), strtolower( $row->description ), $pct );
            if ( $pct >= 80 ) return true;
        }

        return false;
    }

    private static function map_row_by_mapping( $row, $mapping ) {
        $row_data = [];
        foreach ( (array) $mapping as $field => $col_idx ) {
            $col_idx = (int) $col_idx;
            $row_data[ $field ] = isset( $row[ $col_idx ] ) ? trim( (string) $row[ $col_idx ] ) : '';
        }
        return $row_data;
    }

    private static function validate_transactions( $rows, $headers, $mapping ) {
        $valid    = [];
        $errors   = [];
        $warnings = [];

        $categories_found  = [];
        $created_by_found  = [];
        $approved_by_found = [];

        $eligible_users    = self::get_eligible_wp_users();
        $active_categories = self::get_active_categories_list();

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );
            $row_errs = [];
            $row_warn = [];

            // 0. Ignorar filas de resumen/total (ej: fila final de "TOTAL") o filas vacías
            $first_val = strtoupper( trim( (string) reset( $row ) ) );
            if ( $first_val === 'TOTAL' || ( empty( $row_data['transaction_date'] ) && empty( $row_data['transaction_type'] ) && empty( $row_data['amount'] ) ) ) {
                continue;
            }

            $date = self::parse_date( $row_data['transaction_date'] ?? '' );
            if ( ! $date ) {
                $row_errs[] = sprintf( __( 'Fecha inválida: "%s"', 'aura-suite' ), esc_html( $row_data['transaction_date'] ?? '' ) );
            } else {
                $row_data['transaction_date'] = $date;
            }

            $amount = self::parse_amount( $row_data['amount'] ?? '' );
            if ( $amount === false ) {
                $row_errs[] = sprintf( __( 'Monto inválido: "%s"', 'aura-suite' ), esc_html( $row_data['amount'] ?? '' ) );
            } else {
                $row_data['amount'] = $amount;
            }

            $type = self::normalize_type( $row_data['transaction_type'] ?? '' );

            // 🏗️ Detección automática de Capital desde notas: [Gastos Capital]
            if ( ! $type ) {
                $notes_raw = $row_data['notes'] ?? '';
                $desc_raw  = $row_data['description'] ?? '';
                if (
                    stripos( $notes_raw, '[Gastos Capital]' ) !== false ||
                    stripos( $notes_raw, 'Gastos Capital' ) !== false ||
                    stripos( $desc_raw,  'Gastos de CAPITAL' ) !== false
                ) {
                    $type = 'capital';
                }
            }

            if ( ! $type ) {
                $row_errs[] = sprintf( __( 'Tipo inválido: "%s" (use income/expense o ingreso/egreso)', 'aura-suite' ), esc_html( $row_data['transaction_type'] ?? '' ) );
            } else {
                $row_data['transaction_type'] = $type;
            }

            $status_val = $row_data['status'] ?? '';
            $status = self::normalize_status( $status_val );
            if ( $status === false ) {
                $row_errs[] = sprintf( __( 'Estado inválido: "%s" (use pending/approved)', 'aura-suite' ), esc_html( $status_val ) );
            } elseif ( $status !== null ) {
                $row_data['status'] = $status;
            }

            // Categoría (texto o ID)
            $cat_val = trim( (string) ( $row_data['category_id'] ?? '' ) );
            if ( ! empty( $cat_val ) ) {
                if ( ! isset( $categories_found[ $cat_val ] ) ) {
                    $categories_found[ $cat_val ] = 0;
                }
                $categories_found[ $cat_val ]++;
            }

            $cat_id = self::find_category( $cat_val );
            if ( ! $cat_id ) {
                $row_data['category_name_raw'] = $cat_val;
                if ( ! empty( $cat_val ) ) {
                    $row_warn[] = sprintf( __( 'Categoría "%s" no existe — podrá seleccionarla o crearla antes de importar', 'aura-suite' ), esc_html( $cat_val ) );
                } else {
                    $row_errs[] = __( 'Categoría vacía — campo obligatorio', 'aura-suite' );
                }
            } else {
                $row_data['category_id'] = $cat_id;
            }

            // Creado Por (Nombre WP o ID)
            $created_by_val = trim( (string) ( $row_data['created_by'] ?? '' ) );
            if ( ! empty( $created_by_val ) ) {
                if ( ! isset( $created_by_found[ $created_by_val ] ) ) {
                    $created_by_found[ $created_by_val ] = 0;
                }
                $created_by_found[ $created_by_val ]++;
            }

            // Aprobado Por (Nombre WP o ID)
            $approved_by_val = trim( (string) ( $row_data['approved_by'] ?? '' ) );
            if ( ! empty( $approved_by_val ) ) {
                if ( ! isset( $approved_by_found[ $approved_by_val ] ) ) {
                    $approved_by_found[ $approved_by_val ] = 0;
                }
                $approved_by_found[ $approved_by_val ]++;
            }

            if ( empty( $row_data['description'] ) ) {
                $row_data['description'] = __( 'Importado', 'aura-suite' );
                $row_warn[] = __( 'Descripción vacía, se usará "Importado"', 'aura-suite' );
            }

            $source_account_val = trim( (string) ( $row_data['source_account_id'] ?? '' ) );
            if ( $source_account_val !== '' ) {
                $source_account_id = self::find_account( $source_account_val );
                if ( ! $source_account_id ) {
                    $row_warn[] = sprintf( __( 'Cuenta origen "%s" no encontrada en catálogo (se omitirá la cuenta)', 'aura-suite' ), esc_html( $source_account_val ) );
                    $row_data['source_account_id'] = null;
                } else {
                    $row_data['source_account_id'] = $source_account_id;
                }
            }

            $destination_account_val = trim( (string) ( $row_data['destination_account_id'] ?? '' ) );
            if ( $destination_account_val !== '' ) {
                $destination_account_id = self::find_account( $destination_account_val );
                if ( ! $destination_account_id ) {
                    $row_warn[] = sprintf( __( 'Cuenta destino "%s" no encontrada en catálogo (se omitirá la cuenta)', 'aura-suite' ), esc_html( $destination_account_val ) );
                    $row_data['destination_account_id'] = null;
                } else {
                    $row_data['destination_account_id'] = $destination_account_id;
                }
            }

            $related_user_val = trim( (string) ( $row_data['related_user_id'] ?? '' ) );
            if ( $related_user_val !== '' ) {
                $related_user_id = self::find_related_user( $related_user_val );
                if ( ! $related_user_id ) {
                    $row_warn[] = sprintf( __( 'Usuario vinculado "%s" no encontrado (se omitirá el vínculo)', 'aura-suite' ), esc_html( $related_user_val ) );
                    $row_data['related_user_id'] = null;
                } else {
                    $row_data['related_user_id'] = $related_user_id;
                }
            }

            // Sanitizar nuevos campos 100% compatibles y normalizar
            $row_data['area_id'] = isset( $row_data['area_id'] ) ? (int) $row_data['area_id'] : null;
            $row_data['expense_category_id'] = isset( $row_data['expense_category_id'] ) ? (int) $row_data['expense_category_id'] : null;
            $row_data['recipient_payer'] = sanitize_text_field( $row_data['recipient_payer'] ?? '' );
            $row_data['receipt_file'] = sanitize_text_field( $row_data['receipt_file'] ?? '' );
            $row_data['tags'] = sanitize_text_field( $row_data['tags'] ?? '' );
            $row_data['related_module'] = sanitize_text_field( $row_data['related_module'] ?? '' );
            $row_data['related_item_id'] = isset( $row_data['related_item_id'] ) ? (int) $row_data['related_item_id'] : null;
            $row_data['related_action'] = sanitize_text_field( $row_data['related_action'] ?? '' );
            $row_data['payment_method'] = self::normalize_payment_method( $row_data['payment_method'] ?? '' );
            $row_data['related_user_concept'] = self::normalize_concept(
                $row_data['related_user_concept'] ?? '',
                $type,
                $row_data['related_user_id'] ?? null
            );

            if ( ! empty( $row_errs ) ) {
                $errors[] = [ 'row' => $row_num, 'data' => $row_data, 'errors' => $row_errs ];
            } else {
                if ( ! empty( $row_warn ) ) {
                    $warnings[] = [ 'row' => $row_num, 'warnings' => $row_warn ];
                }
                $valid[] = $row_data;
            }
        }

        // Estructurar análisis de categorías
        $categories_analysis = [];
        foreach ( $categories_found as $cat_name => $count ) {
            $db_id = self::find_category( $cat_name );
            $categories_analysis[] = [
                'name'         => $cat_name,
                'count'        => $count,
                'exists_in_db' => (bool) $db_id,
                'matched_id'   => $db_id ? (int) $db_id : null,
            ];
        }

        // Estructurar análisis de usuarios WP
        $users_analysis = [];
        foreach ( $created_by_found as $uname => $count ) {
            $matched_uid = self::match_wp_user( $uname, $eligible_users );
            $users_analysis[] = [
                'name'            => $uname,
                'field_type'      => 'created_by',
                'field_label'     => __( 'Creado por', 'aura-suite' ),
                'count'           => $count,
                'matched_user_id' => $matched_uid ? (int) $matched_uid : null,
            ];
        }
        foreach ( $approved_by_found as $uname => $count ) {
            $matched_uid = self::match_wp_user( $uname, $eligible_users );
            $users_analysis[] = [
                'name'            => $uname,
                'field_type'      => 'approved_by',
                'field_label'     => __( 'Aprobado por', 'aura-suite' ),
                'count'           => $count,
                'matched_user_id' => $matched_uid ? (int) $matched_uid : null,
            ];
        }

        return [
            'type'                 => 'transactions',
            'total'                => count( $rows ),
            'valid'                => count( $valid ),
            'invalid'              => count( $errors ),
            'warnings'             => count( $warnings ),
            'errors'               => $errors,
            'warn_list'            => $warnings,
            'categories_analysis'  => $categories_analysis,
            'users_analysis'       => $users_analysis,
            'available_categories' => $active_categories,
            'available_users'      => $eligible_users,
            'current_user_id'      => get_current_user_id() ?: 1,
        ];
    }

    private static function validate_accounts( $rows, $headers, $mapping ) {
        $valid    = [];
        $errors   = [];
        $warnings = [];
        $valid_types = [ 'bank_account', 'petty_cash', 'contributions_fund', 'usd_cash', 'custom' ];

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );
            $row_errs = [];
            $row_warn = [];

            $name = sanitize_text_field( $row_data['name'] ?? '' );
            if ( $name === '' ) {
                $row_errs[] = __( 'Nombre de cuenta es requerido', 'aura-suite' );
            } elseif ( strlen( $name ) > 191 ) {
                $row_errs[] = __( 'Nombre de cuenta muy largo (máx 191)', 'aura-suite' );
            } elseif ( self::account_name_exists( $name ) ) {
                $row_errs[] = sprintf( __( 'Ya existe una cuenta con nombre "%s"', 'aura-suite' ), esc_html( $name ) );
            }

            $account_type = sanitize_key( $row_data['account_type'] ?? '' );
            if ( ! in_array( $account_type, $valid_types, true ) ) {
                $row_errs[] = __( 'Tipo de cuenta inválido', 'aura-suite' );
            }

            $currency = strtoupper( sanitize_text_field( $row_data['currency'] ?? '' ) );
            if ( $currency === '' ) {
                $row_errs[] = __( 'Moneda es requerida', 'aura-suite' );
            } elseif ( ! self::is_valid_currency( $currency ) ) {
                $row_errs[] = sprintf( __( 'Código de moneda inválido: %s', 'aura-suite' ), esc_html( $currency ) );
            }

            $initial_balance = self::parse_amount( $row_data['initial_balance'] ?? '' );
            if ( $initial_balance === false || $initial_balance < 0 ) {
                $row_errs[] = __( 'Saldo inicial debe ser un número >= 0', 'aura-suite' );
            }

            $is_active = trim( (string) ( $row_data['is_active'] ?? '1' ) );
            if ( ! in_array( $is_active, [ '0', '1' ], true ) ) {
                $row_errs[] = __( 'is_active debe ser 0 o 1', 'aura-suite' );
            }

            $meta_json = trim( (string) ( $row_data['meta_json'] ?? '' ) );
            if ( $meta_json !== '' ) {
                json_decode( $meta_json, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    $row_errs[] = __( 'meta_json no es JSON válido', 'aura-suite' );
                }
            }

            $row_data['name'] = $name;
            $row_data['account_type'] = $account_type;
            $row_data['currency'] = $currency;
            $row_data['initial_balance'] = ( $initial_balance === false ) ? 0 : $initial_balance;
            $row_data['is_active'] = (int) ( $is_active === '0' ? 0 : 1 );

            if ( ! empty( $row_errs ) ) {
                $errors[] = [ 'row' => $row_num, 'data' => $row_data, 'errors' => $row_errs ];
            } else {
                if ( ! empty( $row_warn ) ) {
                    $warnings[] = [ 'row' => $row_num, 'warnings' => $row_warn ];
                }
                $valid[] = $row_data;
            }
        }

        return [
            'type'      => 'accounts',
            'total'     => count( $rows ),
            'valid'     => count( $valid ),
            'invalid'   => count( $errors ),
            'warnings'  => count( $warnings ),
            'errors'    => $errors,
            'warn_list' => $warnings,
        ];
    }

    private static function validate_categories( $rows, $headers, $mapping ) {
        $valid    = [];
        $errors   = [];
        $warnings = [];
        $valid_types = [ 'income', 'expense', 'both' ];
        $slugs_in_file = [];
        $types_by_slug = [];

        foreach ( $rows as $idx => $row ) {
            $r = self::map_row_by_mapping( $row, $mapping );
            $slug = sanitize_title( $r['slug'] ?? '' );
            if ( $slug !== '' ) {
                $slugs_in_file[] = $slug;
                $types_by_slug[ $slug ] = sanitize_key( $r['type'] ?? 'both' );
            }
        }

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );
            $row_errs = [];
            $row_warn = [];

            $name = sanitize_text_field( $row_data['name'] ?? '' );
            $slug = sanitize_title( $row_data['slug'] ?? '' );
            $type = sanitize_key( $row_data['type'] ?? 'both' );
            $parent_slug = sanitize_title( $row_data['parent_category_slug'] ?? '' );
            $color = strtoupper( trim( (string) ( $row_data['color'] ?? '' ) ) );
            $icon  = sanitize_text_field( $row_data['icon'] ?? '' );
            $display_order = isset( $row_data['display_order'] ) ? (int) $row_data['display_order'] : 0;
            $is_active = trim( (string) ( $row_data['is_active'] ?? '1' ) );
            $is_capex  = isset( $row_data['is_capex'] ) && in_array( trim( (string) $row_data['is_capex'] ), [ '1', 'true', 'si', 'yes' ], true ) ? 1 : 0;

            if ( $name === '' ) {
                $row_errs[] = __( 'Nombre es requerido', 'aura-suite' );
            }

            if ( $slug === '' ) {
                if ( $name !== '' ) {
                    $slug = sanitize_title( $name );
                } else {
                    $row_errs[] = __( 'Slug es requerido', 'aura-suite' );
                }
            }

            if ( self::category_slug_exists( $slug ) ) {
                $row_warn[] = sprintf( __( 'Slug "%s" ya existe en BD — se actualizarán sus datos.', 'aura-suite' ), esc_html( $slug ) );
            }

            if ( ! in_array( $type, $valid_types, true ) ) {
                $type = 'both';
            }

            if ( $color === '' || ! self::is_valid_hex_color( $color ) ) {
                $color = '#4F46E5';
            }

            if ( $icon === '' || ! self::is_valid_icon( $icon ) ) {
                $icon = '📁';
            }

            if ( ! in_array( $is_active, [ '0', '1' ], true ) ) {
                $is_active = '1';
            }

            if ( $parent_slug !== '' ) {
                if ( $parent_slug === $slug ) {
                    $row_errs[] = __( 'Jerarquía circular: parent_category_slug no puede ser igual al slug', 'aura-suite' );
                }

                $parent_exists = in_array( $parent_slug, $slugs_in_file, true ) || ( self::resolve_parent_category_id( $parent_slug ) !== false );
                if ( ! $parent_exists ) {
                    $row_errs[] = sprintf( __( 'parent_category_slug no existe: %s', 'aura-suite' ), esc_html( $parent_slug ) );
                }

                $parent_type = $types_by_slug[ $parent_slug ] ?? self::get_parent_category_type_by_slug( $parent_slug );
                if ( $parent_type && $parent_type !== 'both' && $type !== $parent_type && $type !== 'both' ) {
                    $row_warn[] = __( 'Tipo de subcategoría incompatible con categoría padre (se ajustará automáticamente)', 'aura-suite' );
                }
            }

            $row_data['name'] = $name;
            $row_data['slug'] = $slug;
            $row_data['type'] = $type;
            $row_data['parent_category_slug'] = $parent_slug;
            $row_data['color'] = $color;
            $row_data['icon'] = $icon;
            $row_data['display_order'] = $display_order;
            $row_data['is_active'] = (int) ( $is_active === '0' ? 0 : 1 );
            $row_data['is_capex'] = $is_capex;

            if ( ! empty( $row_errs ) ) {
                $errors[] = [ 'row' => $row_num, 'data' => $row_data, 'errors' => $row_errs ];
            } else {
                if ( ! empty( $row_warn ) ) {
                    $warnings[] = [ 'row' => $row_num, 'warnings' => $row_warn ];
                }
                $valid[] = $row_data;
            }
        }

        return [
            'type'      => 'categories',
            'total'     => count( $rows ),
            'valid'     => count( $valid ),
            'invalid'   => count( $errors ),
            'warnings'  => count( $warnings ),
            'errors'    => $errors,
            'warn_list' => $warnings,
        ];
    }

    private static function execute_transactions( $rows, $mapping, $options, $filename, $transient = [] ) {
        global $wpdb;

        $default_status = in_array( $options['default_status'] ?? '', [ 'pending', 'approved' ], true )
            ? $options['default_status']
            : 'pending';
        $auto_cat   = ! empty( $options['auto_create_category'] );
        $dup_action = $options['duplicate_action'] ?? 'ask';

        $category_mapping   = (array) ( $options['category_mapping'] ?? [] );
        $user_mapping       = (array) ( $options['user_mapping'] ?? [] );
        $default_created_by = ! empty( $options['default_created_by'] ) ? (int) $options['default_created_by'] : get_current_user_id();
        $default_approved_by = ! empty( $options['default_approved_by'] ) ? (int) $options['default_approved_by'] : 0;

        $eligible_users           = self::get_eligible_wp_users();
        $created_categories_cache = [];

        $batch_id   = wp_generate_uuid4();
        $imported   = 0;
        $failed     = 0;
        $error_log  = [];
        $current_uid = get_current_user_id() ?: 1;
        $tx_table   = $wpdb->prefix . 'aura_finance_transactions';
        $tx_columns = self::get_transaction_table_columns();

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );

            // Ignorar filas de resumen/total (ej: fila final de "TOTAL") o filas vacías
            $first_val = strtoupper( trim( (string) reset( $row ) ) );
            if ( $first_val === 'TOTAL' || ( empty( $row_data['transaction_date'] ) && empty( $row_data['transaction_type'] ) && empty( $row_data['amount'] ) ) ) {
                continue;
            }

            $date   = self::parse_date( $row_data['transaction_date'] ?? '' );
            $amount = self::parse_amount( $row_data['amount'] ?? '' );
            $type   = self::normalize_type( $row_data['transaction_type'] ?? '' );

            if ( ! $type ) {
                $notes_raw = $row_data['notes'] ?? '';
                $desc_raw  = $row_data['description'] ?? '';
                $cat_raw   = $row_data['category_id'] ?? '';
                if (
                    stripos( $notes_raw, '[Gastos Capital]' ) !== false ||
                    stripos( $notes_raw, 'Gastos Capital' ) !== false ||
                    stripos( $notes_raw, 'Capital' ) !== false ||
                    stripos( $desc_raw,  'Gastos de CAPITAL' ) !== false ||
                    stripos( $desc_raw,  'Capital' ) !== false ||
                    stripos( $cat_raw,   'Capital' ) !== false
                ) {
                    $type = 'capital';
                }
            }

            if ( ! $date || $amount === false || ! $type ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Datos inválidos (fecha, monto o tipo)', 'aura-suite' ) ];
                continue;
            }

            // 1. Resolución de Categoría (Texto o ID)
            $raw_cat = trim( (string) ( $row_data['category_id'] ?? '' ) );
            $cat_id  = null;

            // Revisar mapeo explícito
            if ( $raw_cat !== '' && isset( $category_mapping[ $raw_cat ] ) ) {
                $target = $category_mapping[ $raw_cat ];
                if ( is_numeric( $target ) && (int) $target > 0 ) {
                    $cat_id = (int) $target;
                } elseif ( $target === '__create__' ) {
                    if ( isset( $created_categories_cache[ $raw_cat ] ) ) {
                        $cat_id = $created_categories_cache[ $raw_cat ];
                    } else {
                        $cat_id = self::create_category( $raw_cat, $type );
                        if ( $cat_id ) {
                            $created_categories_cache[ $raw_cat ] = $cat_id;
                        }
                    }
                }
            }

            // Buscar en BD si aún no se resolvió
            if ( ! $cat_id && $raw_cat !== '' ) {
                $cat_id = self::find_category( $raw_cat );
            }

            // Auto-crear si la opción global está activa
            if ( ! $cat_id && $raw_cat !== '' && $auto_cat ) {
                if ( isset( $created_categories_cache[ $raw_cat ] ) ) {
                    $cat_id = $created_categories_cache[ $raw_cat ];
                } else {
                    $cat_id = self::create_category( $raw_cat, $type );
                    if ( $cat_id ) {
                        $created_categories_cache[ $raw_cat ] = $cat_id;
                    }
                }
            }

            if ( ! $cat_id ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => sprintf( __( 'Categoría no encontrada o no pudo crearse: "%s"', 'aura-suite' ), $raw_cat ) ];
                continue;
            }

            $description = ! empty( $row_data['description'] ) ? sanitize_text_field( $row_data['description'] ) : __( 'Importado', 'aura-suite' );
            $row_status  = self::normalize_status( $row_data['status'] ?? '' );
            if ( $row_status === false ) {
                $row_status = null;
            }
            $final_status = $row_status ?: $default_status;

            if ( $dup_action === 'ignore' && self::is_duplicate( $date, $amount, $description ) ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Posible duplicado, fila ignorada', 'aura-suite' ) ];
                continue;
            }

            // 2. Resolución de Creado Por (Nombre Usuario WP)
            $raw_created_by      = trim( (string) ( $row_data['created_by'] ?? '' ) );
            $resolved_created_by = 0;
            if ( $raw_created_by !== '' ) {
                if ( ! empty( $user_mapping[ 'created_by_' . $raw_created_by ] ) ) {
                    $resolved_created_by = (int) $user_mapping[ 'created_by_' . $raw_created_by ];
                } elseif ( ! empty( $user_mapping[ $raw_created_by ] ) ) {
                    $resolved_created_by = (int) $user_mapping[ $raw_created_by ];
                } else {
                    $resolved_created_by = self::match_wp_user( $raw_created_by, $eligible_users ) ?: 0;
                }
            }
            if ( ! $resolved_created_by ) {
                $resolved_created_by = $default_created_by ?: $current_uid;
            }

            // 3. Resolución de Aprobado Por (Nombre Usuario WP) y Fecha de Aprobación
            $resolved_approved_by = null;
            $approved_at          = null;
            if ( $final_status === 'approved' ) {
                $raw_approved_by = trim( (string) ( $row_data['approved_by'] ?? '' ) );
                if ( $raw_approved_by !== '' ) {
                    if ( ! empty( $user_mapping[ 'approved_by_' . $raw_approved_by ] ) ) {
                        $resolved_approved_by = (int) $user_mapping[ 'approved_by_' . $raw_approved_by ];
                    } elseif ( ! empty( $user_mapping[ $raw_approved_by ] ) ) {
                        $resolved_approved_by = (int) $user_mapping[ $raw_approved_by ];
                    } else {
                        $resolved_approved_by = self::match_wp_user( $raw_approved_by, $eligible_users ) ?: null;
                    }
                }
                if ( ! $resolved_approved_by ) {
                    $resolved_approved_by = $default_approved_by ?: $resolved_created_by;
                }
                $approved_at = $date . ' ' . current_time( 'H:i:s' );
            }

            $insert_data = [
                'transaction_type' => $type,
                'category_id'      => $cat_id,
                'amount'           => $amount,
                'transaction_date' => $date,
                'description'      => $description,
                'notes'            => sanitize_textarea_field( $row_data['notes'] ?? '' ),
                'status'           => $final_status,
                'payment_method'   => self::normalize_payment_method( $row_data['payment_method'] ?? '' ),
                'reference_number' => sanitize_text_field( $row_data['reference_number'] ?? '' ),
                'created_by'       => (int) $resolved_created_by,
                'import_batch_id'  => $batch_id,
            ];
            $insert_format = [ '%s','%d','%f','%s','%s','%s','%s','%s','%s','%d','%s' ];

            if ( $resolved_approved_by && in_array( 'approved_by', $tx_columns, true ) ) {
                $insert_data['approved_by'] = (int) $resolved_approved_by;
                $insert_format[] = '%d';
            }
            if ( ! empty( $approved_at ) && in_array( 'approved_at', $tx_columns, true ) ) {
                $insert_data['approved_at'] = $approved_at;
                $insert_format[] = '%s';
            }
            if ( ! empty( $row_data['source_account_id'] ) && in_array( 'source_account_id', $tx_columns, true ) ) {
                $insert_data['source_account_id'] = (int) $row_data['source_account_id'];
                $insert_format[] = '%d';
            }
            if ( ! empty( $row_data['destination_account_id'] ) && in_array( 'destination_account_id', $tx_columns, true ) ) {
                $insert_data['destination_account_id'] = (int) $row_data['destination_account_id'];
                $insert_format[] = '%d';
            }
            if ( ! empty( $row_data['related_user_id'] ) && in_array( 'related_user_id', $tx_columns, true ) ) {
                $rel_uid = is_numeric( $row_data['related_user_id'] ) ? (int) $row_data['related_user_id'] : self::find_related_user( $row_data['related_user_id'] );
                if ( $rel_uid > 0 ) {
                    $insert_data['related_user_id'] = (int) $rel_uid;
                    $insert_format[] = '%d';
                }
            }
            if ( ! empty( $row_data['area_id'] ) && in_array( 'area_id', $tx_columns, true ) ) {
                $insert_data['area_id'] = (int) $row_data['area_id'];
                $insert_format[] = '%d';
            }
            if ( ! empty( $row_data['expense_category_id'] ) && in_array( 'expense_category_id', $tx_columns, true ) ) {
                $insert_data['expense_category_id'] = (int) $row_data['expense_category_id'];
                $insert_format[] = '%d';
            }
            if ( ! empty( $row_data['recipient_payer'] ) && in_array( 'recipient_payer', $tx_columns, true ) ) {
                $insert_data['recipient_payer'] = $row_data['recipient_payer'];
                $insert_format[] = '%s';
            }
            if ( ! empty( $row_data['receipt_file'] ) && in_array( 'receipt_file', $tx_columns, true ) ) {
                $processed_receipt = self::process_receipt_attachment( $row_data['receipt_file'], $transient['unzipped_dir'] ?? null );
                if ( ! empty( $processed_receipt ) ) {
                    $insert_data['receipt_file'] = $processed_receipt;
                    $insert_format[] = '%s';
                }
            }
            if ( ! empty( $row_data['tags'] ) && in_array( 'tags', $tx_columns, true ) ) {
                $insert_data['tags'] = $row_data['tags'];
                $insert_format[] = '%s';
            }
            if ( ! empty( $row_data['related_module'] ) && in_array( 'related_module', $tx_columns, true ) ) {
                $insert_data['related_module'] = $row_data['related_module'];
                $insert_format[] = '%s';
            }
            if ( ! empty( $row_data['related_item_id'] ) && in_array( 'related_item_id', $tx_columns, true ) ) {
                $insert_data['related_item_id'] = (int) $row_data['related_item_id'];
                $insert_format[] = '%d';
            }
            if ( ! empty( $row_data['related_action'] ) && in_array( 'related_action', $tx_columns, true ) ) {
                $insert_data['related_action'] = $row_data['related_action'];
                $insert_format[] = '%s';
            }
            $final_user_concept = self::normalize_concept(
                $row_data['related_user_concept'] ?? '',
                $type,
                $insert_data['related_user_id'] ?? null
            );
            if ( in_array( 'related_user_concept', $tx_columns, true ) ) {
                $insert_data['related_user_concept'] = $final_user_concept;
                $insert_format[] = '%s';
            }

            $wpdb->insert( $tx_table, $insert_data, $insert_format );

            if ( $wpdb->last_error ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ];
            } else {
                $imported++;
            }
        }

        self::persist_import_log( $batch_id, $filename, count( $rows ), $imported, $failed, $error_log, $current_uid );

        return [
            'batch_id'    => $batch_id,
            'imported'    => $imported,
            'failed'      => $failed,
            'error_log'   => $error_log,
            'import_type' => 'transactions',
        ];
    }

    private static function execute_accounts( $rows, $mapping, $options, $filename ) {
        global $wpdb;

        $batch_id  = wp_generate_uuid4();
        $imported  = 0;
        $failed    = 0;
        $error_log = [];
        $user_id   = get_current_user_id();
        $accounts_table = $wpdb->prefix . 'aura_finance_accounts';

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );

            $name = sanitize_text_field( $row_data['name'] ?? '' );
            $account_type = sanitize_key( $row_data['account_type'] ?? '' );
            $currency = strtoupper( sanitize_text_field( $row_data['currency'] ?? '' ) );
            $initial_balance = self::parse_amount( $row_data['initial_balance'] ?? '' );
            $is_active = trim( (string) ( $row_data['is_active'] ?? '1' ) ) === '0' ? 0 : 1;
            $meta_json = trim( (string) ( $row_data['meta_json'] ?? '' ) );

            if ( $name === '' || self::account_name_exists( $name ) || ! self::is_valid_currency( $currency ) || $initial_balance === false ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Fila inválida para cuentas', 'aura-suite' ) ];
                continue;
            }

            if ( $meta_json !== '' ) {
                json_decode( $meta_json, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => __( 'meta_json no es JSON válido', 'aura-suite' ) ];
                    continue;
                }
            }

            $result = $wpdb->insert( $accounts_table, [
                'name'                  => $name,
                'account_type'          => $account_type,
                'currency'              => $currency,
                'institution'           => sanitize_text_field( $row_data['institution'] ?? '' ) ?: null,
                'account_number_masked' => sanitize_text_field( $row_data['account_number_masked'] ?? '' ) ?: null,
                'initial_balance'       => $initial_balance,
                'current_balance'       => $initial_balance,
                'is_active'             => $is_active,
                'meta_json'             => $meta_json !== '' ? $meta_json : null,
                'created_by'            => $user_id,
                'created_at'            => current_time( 'mysql' ),
            ], [ '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%d', '%s' ] );

            if ( $result ) {
                $imported++;
            } else {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ?: __( 'Error desconocido', 'aura-suite' ) ];
            }
        }

        self::persist_import_log( $batch_id, $filename, count( $rows ), $imported, $failed, $error_log, $user_id );

        return [
            'batch_id'  => $batch_id,
            'imported'  => $imported,
            'failed'    => $failed,
            'error_log' => $error_log,
            'import_type' => 'accounts',
        ];
    }

    private static function execute_categories( $rows, $mapping, $options, $filename ) {
        global $wpdb;
        $cat_table = $wpdb->prefix . 'aura_finance_categories';

        $batch_id  = wp_generate_uuid4();
        $imported  = 0;
        $failed    = 0;
        $error_log = [];
        $user_id   = get_current_user_id() ?: 1;

        $parents = [];
        $children = [];
        foreach ( $rows as $row ) {
            $row_data = self::map_row_by_mapping( $row, $mapping );
            $parent_slug = sanitize_title( $row_data['parent_category_slug'] ?? '' );
            if ( $parent_slug === '' ) {
                $parents[] = $row_data;
            } else {
                $children[] = $row_data;
            }
        }

        $ordered_rows = array_merge( $parents, $children );

        foreach ( $ordered_rows as $idx => $row_data ) {
            $row_num = $idx + 2;

            $name  = sanitize_text_field( $row_data['name'] ?? '' );
            $slug  = sanitize_title( $row_data['slug'] ?? '' );
            if ( $slug === '' && $name !== '' ) {
                $slug = sanitize_title( $name );
            }
            $type  = sanitize_key( $row_data['type'] ?? 'both' );
            if ( ! in_array( $type, [ 'income', 'expense', 'both' ], true ) ) {
                $type = 'both';
            }

            $parent_slug = sanitize_title( $row_data['parent_category_slug'] ?? '' );
            $parent_id = null;
            if ( $parent_slug !== '' ) {
                $parent_id = self::resolve_parent_category_id( $parent_slug );
                if ( $parent_id === false ) {
                    // Intentar buscar nuevamente por si se acaba de insertar
                    $parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$cat_table} WHERE slug = %s LIMIT 1", $parent_slug ) );
                }
            }

            if ( $name === '' || $slug === '' ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Nombre o slug inválido', 'aura-suite' ) ];
                continue;
            }

            $color         = strtoupper( trim( (string) ( $row_data['color'] ?? '#4F46E5' ) ) );
            if ( empty( $color ) || ! self::is_valid_hex_color( $color ) ) {
                $color = '#4F46E5';
            }

            $icon          = sanitize_text_field( $row_data['icon'] ?? '📁' );
            if ( empty( $icon ) ) {
                $icon = '📁';
            }

            $description   = sanitize_textarea_field( $row_data['description'] ?? '' );
            $is_active     = trim( (string) ( $row_data['is_active'] ?? '1' ) ) === '0' ? 0 : 1;
            $display_order = (int) ( $row_data['display_order'] ?? 0 );
            $is_capex      = isset( $row_data['is_capex'] ) && in_array( trim( (string) $row_data['is_capex'] ), [ '1', 'true', 'si', 'yes' ], true ) ? 1 : 0;

            $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$cat_table} WHERE slug = %s LIMIT 1", $slug ) );

            $data = [
                'name'          => $name,
                'slug'          => $slug,
                'type'          => $type,
                'parent_id'     => $parent_id ? (int) $parent_id : null,
                'color'         => $color,
                'icon'          => $icon,
                'description'   => $description,
                'is_active'     => $is_active,
                'display_order' => $display_order,
                'is_capex'      => $is_capex,
            ];

            if ( $existing_id ) {
                $result = $wpdb->update( $cat_table, $data, [ 'id' => (int) $existing_id ] );
                if ( $result !== false ) {
                    $imported++;
                } else {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ?: __( 'Error al actualizar categoría', 'aura-suite' ) ];
                }
            } else {
                $data['created_by'] = $user_id;
                $data['created_at'] = current_time( 'mysql' );
                $result = $wpdb->insert( $cat_table, $data );
                if ( $result ) {
                    $imported++;
                } else {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ?: __( 'Error al insertar categoría', 'aura-suite' ) ];
                }
            }
        }

        self::persist_import_log( $batch_id, $filename, count( $rows ), $imported, $failed, $error_log, $user_id );

        return [
            'batch_id'    => $batch_id,
            'imported'    => $imported,
            'failed'      => $failed,
            'error_log'   => $error_log,
            'import_type' => 'categories',
        ];
    }

    private static function persist_import_log( $batch_id, $filename, $rows_total, $rows_imported, $rows_failed, $error_log, $user_id ) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'aura_finance_import_log';
        $wpdb->insert( $log_table, [
            'batch_id'      => $batch_id,
            'filename'      => $filename,
            'rows_total'    => (int) $rows_total,
            'rows_imported' => (int) $rows_imported,
            'rows_failed'   => (int) $rows_failed,
            'error_log'     => wp_json_encode( $error_log ),
            'imported_by'   => (int) $user_id,
        ], [ '%s','%s','%d','%d','%d','%s','%d' ] );
    }

    private static function resolve_parent_category_id( $parent_slug ) {
        global $wpdb;
        $cat_table = $wpdb->prefix . 'aura_finance_categories';

        if ( empty( $parent_slug ) ) {
            return null;
        }

        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$cat_table} WHERE slug = %s LIMIT 1",
            $parent_slug
        ) );

        return $result ? (int) $result : false;
    }

    private static function get_parent_category_type_by_slug( $parent_slug ) {
        global $wpdb;
        $cat_table = $wpdb->prefix . 'aura_finance_categories';
        if ( empty( $parent_slug ) ) {
            return null;
        }
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT type FROM {$cat_table} WHERE slug = %s LIMIT 1",
            $parent_slug
        ) );
    }

    private static function is_valid_currency( $code ) {
        $valid_currencies = [
            'COP', 'USD', 'EUR', 'MXN', 'ARS', 'BRL', 'CLP', 'PEN',
            'VES', 'UYU', 'BOB', 'GTQ', 'HNL', 'NIO', 'CRC', 'PAB',
        ];
        return in_array( strtoupper( (string) $code ), $valid_currencies, true );
    }

    private static function account_name_exists( $name ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_accounts';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE name = %s AND deleted_at IS NULL LIMIT 1",
            $name
        ) );
    }

    private static function category_slug_exists( $slug, $exclude_id = null ) {
        global $wpdb;
        $cat_table = $wpdb->prefix . 'aura_finance_categories';
        $query = $wpdb->prepare( "SELECT id FROM {$cat_table} WHERE slug = %s", $slug );
        if ( $exclude_id ) {
            $query .= $wpdb->prepare( ' AND id != %d', (int) $exclude_id );
        }
        return (bool) $wpdb->get_var( $query );
    }

    private static function is_valid_hex_color( $color ) {
        return (bool) preg_match( '/^#[0-9A-F]{6}$/i', (string) $color );
    }

    private static function is_valid_dashicon( $icon ) {
        return (bool) preg_match( '/^dashicons-[\w-]+$/', (string) $icon );
    }

    private static function is_valid_icon( $icon ) {
        if ( empty( $icon ) ) {
            return true;
        }
        $icon = trim( (string) $icon );
        if ( str_starts_with( $icon, 'dashicons-' ) ) {
            return (bool) preg_match( '/^dashicons-[\w-]+$/', $icon );
        }
        return mb_strlen( $icon ) <= 50;
    }

    public static function find_data_file_in_dir( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return false;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ( $it as $file ) {
            if ( $file->isFile() ) {
                $ext = strtolower( $file->getExtension() );
                if ( in_array( $ext, [ 'csv', 'xlsx' ], true ) && ! str_starts_with( $file->getBasename(), '._' ) ) {
                    return $file->getPathname();
                }
            }
        }
        return false;
    }

    public static function delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $it as $file ) {
            if ( $file->isDir() ) {
                @rmdir( $file->getPathname() );
            } else {
                @unlink( $file->getPathname() );
            }
        }
        @rmdir( $dir );
    }

    public static function process_receipt_attachment( $raw_rf, $unzipped_dir = null ) {
        if ( empty( $raw_rf ) ) {
            return '';
        }

        $raw_rf = trim( (string) $raw_rf );
        $upload_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'aura-finance/receipts/';
        if ( ! file_exists( $upload_dir ) ) {
            wp_mkdir_p( $upload_dir );
        }

        $items = [];
        if ( str_starts_with( $raw_rf, '[' ) || str_starts_with( $raw_rf, '{' ) ) {
            $decoded = json_decode( $raw_rf, true );
            if ( is_array( $decoded ) ) {
                $items = isset( $decoded['url'] ) ? [ $decoded['url'] ] : $decoded;
            }
        }
        if ( empty( $items ) ) {
            $items = array_map( 'trim', explode( ',', $raw_rf ) );
        }

        $saved_names = [];

        foreach ( $items as $item ) {
            if ( is_array( $item ) ) {
                $item = $item['url'] ?? ( $item['file'] ?? '' );
            }
            $item = trim( (string) $item );
            if ( empty( $item ) ) {
                continue;
            }

            // Google Drive intacto
            if ( stripos( $item, 'drive.google.com' ) !== false ) {
                $saved_names[] = $item;
                continue;
            }

            // Descarga de URLs web remotas
            if ( preg_match( '#^https?://#i', $item ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $tmp = download_url( $item, 15 );
                if ( ! is_wp_error( $tmp ) ) {
                    $orig_name = basename( parse_url( $item, PHP_URL_PATH ) );
                    if ( empty( $orig_name ) ) {
                        $orig_name = 'comprobante_' . time() . '.bin';
                    }
                    $unique_name = wp_unique_filename( $upload_dir, $orig_name );
                    if ( @copy( $tmp, $upload_dir . $unique_name ) ) {
                        $saved_names[] = $unique_name;
                    } else {
                        $saved_names[] = $item;
                    }
                    @unlink( $tmp );
                } else {
                    $saved_names[] = $item;
                }
                continue;
            }

            // Si se descomprimió de un archivo ZIP
            if ( ! empty( $unzipped_dir ) && is_dir( $unzipped_dir ) ) {
                $candidate_paths = [
                    $unzipped_dir . '/' . ltrim( $item, '/\\' ),
                    $unzipped_dir . '/comprobantes/' . basename( $item ),
                    $unzipped_dir . '/' . basename( $item ),
                ];

                $found = false;
                foreach ( $candidate_paths as $cpath ) {
                    if ( file_exists( $cpath ) && ! is_dir( $cpath ) ) {
                        $unique_name = wp_unique_filename( $upload_dir, basename( $item ) );
                        if ( @copy( $cpath, $upload_dir . $unique_name ) ) {
                            $saved_names[] = $unique_name;
                            $found = true;
                            break;
                        }
                    }
                }

                if ( $found ) {
                    continue;
                }
            }

            // Si ya existe en la carpeta receipts
            if ( file_exists( $upload_dir . basename( $item ) ) ) {
                $saved_names[] = basename( $item );
                continue;
            }

            $saved_names[] = $item;
        }

        if ( empty( $saved_names ) ) {
            return '';
        }

        return count( $saved_names ) === 1 ? reset( $saved_names ) : implode( ',', $saved_names );
    }

    private static function validate_areas( $rows, $headers, $mapping ) {
        $valid    = [];
        $errors   = [];
        $warnings = [];
        $slugs_in_file = [];

        foreach ( $rows as $idx => $row ) {
            $r = self::map_row_by_mapping( $row, $mapping );
            $slug = sanitize_title( $r['slug'] ?? '' );
            if ( $slug !== '' ) {
                $slugs_in_file[] = $slug;
            }
        }

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );
            $row_errs = [];
            $row_warn = [];

            $name = sanitize_text_field( $row_data['name'] ?? '' );
            $slug = sanitize_title( $row_data['slug'] ?? '' );
            $type = sanitize_key( $row_data['type'] ?? 'department' );
            $parent_slug = sanitize_title( $row_data['parent_area_slug'] ?? '' );
            $color = strtoupper( trim( (string) ( $row_data['color'] ?? '' ) ) );
            $icon  = sanitize_text_field( $row_data['icon'] ?? '' );
            $status = in_array( strtolower( trim( (string) ( $row_data['status'] ?? 'active' ) ) ), [ 'active', 'archived' ], true )
                ? strtolower( trim( (string) ( $row_data['status'] ?? 'active' ) ) )
                : 'active';
            $sort_order = isset( $row_data['sort_order'] ) ? (int) $row_data['sort_order'] : 0;

            if ( $name === '' ) {
                $row_errs[] = __( 'Nombre es requerido', 'aura-suite' );
            }

            if ( $slug === '' ) {
                if ( $name !== '' ) {
                    $slug = sanitize_title( $name );
                } else {
                    $row_errs[] = __( 'Slug es requerido', 'aura-suite' );
                }
            }

            if ( self::area_slug_exists( $slug ) ) {
                $row_warn[] = sprintf( __( 'Slug "%s" ya existe en BD — se actualizarán sus datos.', 'aura-suite' ), esc_html( $slug ) );
            }

            if ( $color === '' || ! self::is_valid_hex_color( $color ) ) {
                $color = '#4F46E5';
            }

            if ( $parent_slug !== '' ) {
                if ( $parent_slug === $slug ) {
                    $row_errs[] = __( 'Jerarquía circular: parent_area_slug no puede ser igual al slug', 'aura-suite' );
                }

                $parent_exists = in_array( $parent_slug, $slugs_in_file, true ) || ( self::resolve_parent_area_id( $parent_slug ) !== false );
                if ( ! $parent_exists ) {
                    $row_errs[] = sprintf( __( 'parent_area_slug no existe: %s', 'aura-suite' ), esc_html( $parent_slug ) );
                }
            }

            $row_data['name'] = $name;
            $row_data['slug'] = $slug;
            $row_data['type'] = $type ?: 'department';
            $row_data['parent_area_slug'] = $parent_slug;
            $row_data['color'] = $color;
            $row_data['icon'] = $icon ?: 'dashicons-networking';
            $row_data['status'] = $status;
            $row_data['sort_order'] = $sort_order;

            if ( ! empty( $row_errs ) ) {
                $errors[] = [ 'row' => $row_num, 'data' => $row_data, 'errors' => $row_errs ];
            } else {
                if ( ! empty( $row_warn ) ) {
                    $warnings[] = [ 'row' => $row_num, 'warnings' => $row_warn ];
                }
                $valid[] = $row_data;
            }
        }

        return [
            'type'      => 'areas',
            'total'     => count( $rows ),
            'valid'     => count( $valid ),
            'invalid'   => count( $errors ),
            'warnings'  => count( $warnings ),
            'errors'    => $errors,
            'warn_list' => $warnings,
        ];
    }

    private static function execute_areas( $rows, $mapping, $options, $filename ) {
        global $wpdb;
        $table    = $wpdb->prefix . 'aura_areas';
        $user_id  = get_current_user_id() ?: 1;
        $batch_id = wp_generate_uuid4();
        $imported = 0;
        $failed   = 0;
        $error_log = [];

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );

            $name = sanitize_text_field( $row_data['name'] ?? '' );
            $slug = sanitize_title( $row_data['slug'] ?? '' );
            if ( $slug === '' && $name !== '' ) {
                $slug = sanitize_title( $name );
            }

            if ( $name === '' || $slug === '' ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Nombre o identificador inválido', 'aura-suite' ) ];
                continue;
            }

            $parent_slug = sanitize_title( $row_data['parent_area_slug'] ?? '' );
            $parent_id = null;
            if ( $parent_slug !== '' ) {
                $parent_id = self::resolve_parent_area_id( $parent_slug );
                if ( ! $parent_id ) {
                    $parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $parent_slug ) );
                }
            }

            $color = strtoupper( trim( (string) ( $row_data['color'] ?? '#4F46E5' ) ) );
            if ( empty( $color ) || ! self::is_valid_hex_color( $color ) ) {
                $color = '#4F46E5';
            }

            $icon = sanitize_text_field( $row_data['icon'] ?? 'dashicons-networking' );
            if ( empty( $icon ) ) {
                $icon = 'dashicons-networking';
            }

            $status = in_array( strtolower( trim( (string) ( $row_data['status'] ?? 'active' ) ) ), [ 'active', 'archived' ], true )
                ? strtolower( trim( (string) ( $row_data['status'] ?? 'active' ) ) )
                : 'active';

            $responsible_user_id = null;
            if ( ! empty( $row_data['responsible_user_id'] ) ) {
                $raw_resp = $row_data['responsible_user_id'];
                if ( is_numeric( $raw_resp ) ) {
                    $responsible_user_id = (int) $raw_resp;
                } else {
                    $matched = self::match_wp_user( $raw_resp );
                    if ( $matched ) {
                        $responsible_user_id = (int) $matched->ID;
                    }
                }
            }

            $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug ) );

            $data = [
                'name'                => $name,
                'slug'                => $slug,
                'type'                => sanitize_key( $row_data['type'] ?? 'department' ) ?: 'department',
                'description'         => sanitize_textarea_field( $row_data['description'] ?? '' ),
                'parent_area_id'      => $parent_id ? (int) $parent_id : null,
                'responsible_user_id' => $responsible_user_id,
                'color'               => $color,
                'icon'                => $icon,
                'status'              => $status,
                'sort_order'          => isset( $row_data['sort_order'] ) ? (int) $row_data['sort_order'] : 0,
                'updated_at'          => current_time( 'mysql' ),
            ];

            if ( $existing_id ) {
                $result = $wpdb->update( $table, $data, [ 'id' => (int) $existing_id ] );
                if ( $result !== false ) {
                    $imported++;
                } else {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ?: __( 'Error al actualizar área', 'aura-suite' ) ];
                }
            } else {
                $data['created_by'] = $user_id;
                $data['created_at'] = current_time( 'mysql' );
                $result = $wpdb->insert( $table, $data );
                if ( $result ) {
                    $imported++;
                } else {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ?: __( 'Error al insertar área', 'aura-suite' ) ];
                }
            }
        }

        self::persist_import_log( $batch_id, $filename, count( $rows ), $imported, $failed, $error_log, $user_id );

        return [
            'batch_id'    => $batch_id,
            'imported'    => $imported,
            'failed'      => $failed,
            'error_log'   => $error_log,
            'import_type' => 'areas',
        ];
    }

    private static function resolve_parent_area_id( $parent_slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_areas';
        if ( empty( $parent_slug ) ) {
            return null;
        }
        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
            $parent_slug
        ) );
        return $result ? (int) $result : false;
    }

    private static function area_slug_exists( $slug, $exclude_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_areas';
        $query = $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug );
        if ( $exclude_id ) {
            $query .= $wpdb->prepare( ' AND id != %d', (int) $exclude_id );
        }
        return (bool) $wpdb->get_var( $query );
    }

    private static function validate_third_parties( $rows, $headers, $mapping ) {
        $valid    = [];
        $errors   = [];
        $warnings = [];

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );
            $row_errs = [];
            $row_warn = [];

            $full_name   = sanitize_text_field( $row_data['full_name'] ?? '' );
            $document_id = sanitize_text_field( $row_data['document_id'] ?? '' );

            if ( $full_name === '' ) {
                $row_errs[] = __( 'Nombre completo / Razón social es requerido', 'aura-suite' );
            }

            $email = sanitize_email( $row_data['email'] ?? '' );
            if ( ! empty( $row_data['email'] ) && ! is_email( $email ) ) {
                $row_warn[] = __( 'Correo electrónico tiene un formato no estándar', 'aura-suite' );
            }

            $row_data['full_name']       = $full_name;
            $row_data['commercial_name'] = sanitize_text_field( $row_data['commercial_name'] ?? '' );
            $row_data['party_type']      = sanitize_key( $row_data['party_type'] ?? 'company' ) ?: 'company';
            $row_data['accounting_role'] = sanitize_key( $row_data['accounting_role'] ?? 'supplier' ) ?: 'supplier';
            $row_data['tax_id_type']     = strtoupper( sanitize_text_field( $row_data['tax_id_type'] ?? 'NIT' ) ) ?: 'NIT';
            $row_data['document_id']     = $document_id;
            $row_data['phone']           = sanitize_text_field( $row_data['phone'] ?? '' );
            $row_data['email']           = $email;
            $row_data['website']         = esc_url_raw( $row_data['website'] ?? '' );
            $row_data['address']         = sanitize_textarea_field( $row_data['address'] ?? '' );
            $row_data['notes']           = sanitize_textarea_field( $row_data['notes'] ?? '' );
            $row_data['is_active']       = trim( (string) ( $row_data['is_active'] ?? '1' ) ) === '0' ? 0 : 1;

            if ( ! empty( $row_errs ) ) {
                $errors[] = [ 'row' => $row_num, 'data' => $row_data, 'errors' => $row_errs ];
            } else {
                if ( ! empty( $row_warn ) ) {
                    $warnings[] = [ 'row' => $row_num, 'warnings' => $row_warn ];
                }
                $valid[] = $row_data;
            }
        }

        return [
            'type'      => 'third_parties',
            'total'     => count( $rows ),
            'valid'     => count( $valid ),
            'invalid'   => count( $errors ),
            'warnings'  => count( $warnings ),
            'errors'    => $errors,
            'warn_list' => $warnings,
        ];
    }

    private static function execute_third_parties( $rows, $mapping, $options, $filename ) {
        global $wpdb;
        $table    = $wpdb->prefix . 'aura_finance_third_parties';
        $user_id  = get_current_user_id() ?: 1;
        $batch_id = wp_generate_uuid4();
        $imported = 0;
        $failed   = 0;
        $error_log = [];

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = self::map_row_by_mapping( $row, $mapping );

            $full_name   = sanitize_text_field( $row_data['full_name'] ?? '' );
            $document_id = sanitize_text_field( $row_data['document_id'] ?? '' );

            if ( $full_name === '' ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Nombre o razón social vacío', 'aura-suite' ) ];
                continue;
            }

            $existing_id = null;
            if ( $document_id !== '' ) {
                $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE document_id = %s LIMIT 1", $document_id ) );
            }
            if ( ! $existing_id ) {
                $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE full_name = %s LIMIT 1", $full_name ) );
            }

            $email = sanitize_email( $row_data['email'] ?? '' );

            $data = [
                'full_name'       => $full_name,
                'commercial_name' => sanitize_text_field( $row_data['commercial_name'] ?? '' ),
                'party_type'      => sanitize_key( $row_data['party_type'] ?? 'company' ) ?: 'company',
                'accounting_role' => sanitize_key( $row_data['accounting_role'] ?? 'supplier' ) ?: 'supplier',
                'tax_id_type'     => strtoupper( sanitize_text_field( $row_data['tax_id_type'] ?? 'NIT' ) ) ?: 'NIT',
                'document_id'     => $document_id,
                'phone'           => sanitize_text_field( $row_data['phone'] ?? '' ),
                'email'           => $email,
                'website'         => esc_url_raw( $row_data['website'] ?? '' ),
                'address'         => sanitize_textarea_field( $row_data['address'] ?? '' ),
                'notes'           => sanitize_textarea_field( $row_data['notes'] ?? '' ),
                'is_active'       => trim( (string) ( $row_data['is_active'] ?? '1' ) ) === '0' ? 0 : 1,
                'updated_at'      => current_time( 'mysql' ),
            ];

            if ( $existing_id ) {
                $result = $wpdb->update( $table, $data, [ 'id' => (int) $existing_id ] );
                if ( $result !== false ) {
                    $imported++;
                } else {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ?: __( 'Error al actualizar tercero', 'aura-suite' ) ];
                }
            } else {
                $data['created_by'] = $user_id;
                $data['created_at'] = current_time( 'mysql' );
                $result = $wpdb->insert( $table, $data );
                if ( $result ) {
                    $imported++;
                } else {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ?: __( 'Error al insertar tercero', 'aura-suite' ) ];
                }
            }
        }

        self::persist_import_log( $batch_id, $filename, count( $rows ), $imported, $failed, $error_log, $user_id );

        return [
            'batch_id'    => $batch_id,
            'imported'    => $imported,
            'failed'      => $failed,
            'error_log'   => $error_log,
            'import_type' => 'third_parties',
        ];
    }

    /* ------------------------------------------------------------------
     * Renderizar página admin
     * ------------------------------------------------------------------ */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/import-page.php';
    }
}
