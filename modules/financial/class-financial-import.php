<?php
/**
 * Clase: Importación de Transacciones CSV/Excel
 * Fase 4, Item 4.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Aura_Financial_Import {

    private static $transient_prefix = 'aura_import_';
    private static $max_file_size    = 5242880; // 5 MB
    private static $max_rows         = 1000;

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
        if ( ! in_array( $ext, [ 'csv', 'xlsx' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Formato no soportado. Use CSV o XLSX.', 'aura-suite' ) ] );
        }

        // Validar tamaño
        if ( $file['size'] > self::$max_file_size ) {
            wp_send_json_error( [ 'message' => __( 'El archivo supera 5 MB.', 'aura-suite' ) ] );
        }

        // Mover a directorio seguro
        $dir      = self::get_import_dir();
        $token    = wp_generate_uuid4();
        $dest     = $dir . $token . '.' . $ext;

        if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
            wp_send_json_error( [ 'message' => __( 'Error al guardar el archivo.', 'aura-suite' ) ] );
        }

        // Parsear
        $rows = $ext === 'xlsx' ? self::parse_excel( $dest ) : self::parse_csv( $dest );

        if ( is_wp_error( $rows ) ) {
            @unlink( $dest );
            wp_send_json_error( [ 'message' => $rows->get_error_message() ] );
        }

        if ( count( $rows ) < 2 ) {
            @unlink( $dest );
            wp_send_json_error( [ 'message' => __( 'El archivo está vacío o sólo tiene encabezado.', 'aura-suite' ) ] );
        }

        $headers     = array_shift( $rows );
        $total_rows  = count( $rows );
        $preview     = array_slice( $rows, 0, 5 );
        $mapping     = self::auto_detect_mapping( $headers );

        // Guardar en transient (1 hora)
        set_transient( self::$transient_prefix . $token, [
            'filepath' => $dest,
            'headers'  => $headers,
            'filename' => sanitize_file_name( $file['name'] ),
        ], HOUR_IN_SECONDS );

        wp_send_json_success( [
            'token'       => $token,
            'headers'     => $headers,
            'preview'     => $preview,
            'total_rows'  => $total_rows,
            'auto_mapping'=> $mapping,
            'filename'    => sanitize_file_name( $file['name'] ),
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

        $token   = sanitize_text_field( $_POST['token'] ?? '' );
        $mapping = isset( $_POST['mapping'] ) ? (array) $_POST['mapping'] : [];

        $transient = get_transient( self::$transient_prefix . $token );
        if ( ! $transient ) {
            wp_send_json_error( [ 'message' => __( 'Sesión de importación expirada. Suba el archivo de nuevo.', 'aura-suite' ) ] );
        }

        $filepath = $transient['filepath'];
        $headers  = $transient['headers'];

        // Leer todas las filas
        $ext  = strtolower( pathinfo( $filepath, PATHINFO_EXTENSION ) );
        $rows = $ext === 'xlsx' ? self::parse_excel( $filepath ) : self::parse_csv( $filepath );

        if ( is_wp_error( $rows ) ) {
            wp_send_json_error( [ 'message' => $rows->get_error_message() ] );
        }

        array_shift( $rows ); // quitar encabezado

        $valid    = [];
        $errors   = [];
        $warnings = [];

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2; // 1-based + header
            $row_data = [];
            $row_errs = [];
            $row_warn = [];

            // Mapear columnas
            foreach ( $mapping as $field => $col_idx ) {
                $col_idx = (int) $col_idx;
                $row_data[ $field ] = isset( $row[ $col_idx ] ) ? trim( $row[ $col_idx ] ) : '';
            }

            // Validar fecha
            $date = self::parse_date( $row_data['transaction_date'] ?? '' );
            if ( ! $date ) {
                $row_errs[] = sprintf( __( 'Fecha inválida: "%s"', 'aura-suite' ), esc_html( $row_data['transaction_date'] ?? '' ) );
            } else {
                $row_data['transaction_date'] = $date;
            }

            // Validar monto
            $amount = self::parse_amount( $row_data['amount'] ?? '' );
            if ( $amount === false ) {
                $row_errs[] = sprintf( __( 'Monto inválido: "%s"', 'aura-suite' ), esc_html( $row_data['amount'] ?? '' ) );
            } else {
                $row_data['amount'] = $amount;
            }

            // Validar tipo
            $type = self::normalize_type( $row_data['transaction_type'] ?? '' );
            if ( ! $type ) {
                $row_errs[] = sprintf( __( 'Tipo inválido: "%s" (use income/expense o ingreso/egreso)', 'aura-suite' ), esc_html( $row_data['transaction_type'] ?? '' ) );
            } else {
                $row_data['transaction_type'] = $type;
            }

            // Validar categoría
            $cat_val = $row_data['category_id'] ?? '';
            $cat_id  = self::find_category( $cat_val );
            if ( ! $cat_id ) {
                $row_data['category_name_raw'] = $cat_val;
                if ( ! empty( $cat_val ) ) {
                    // Si hay un nombre, puede crearse automáticamente en el paso de ejecución → advertencia
                    $row_warn[] = sprintf( __( 'Categoría "%s" no existe — se creará automáticamente si está habilitado', 'aura-suite' ), esc_html( $cat_val ) );
                } else {
                    $row_errs[] = __( 'Categoría vacía — campo obligatorio', 'aura-suite' );
                }
            } else {
                $row_data['category_id'] = $cat_id;
            }

            // Descripción
            if ( empty( $row_data['description'] ) ) {
                $row_data['description'] = __( 'Importado', 'aura-suite' );
                $row_warn[] = __( 'Descripción vacía, se usará "Importado"', 'aura-suite' );
            }

            if ( ! empty( $row_errs ) ) {
                $errors[]  = [ 'row' => $row_num, 'data' => $row_data, 'errors' => $row_errs ];
            } else {
                if ( ! empty( $row_warn ) ) {
                    $warnings[] = [ 'row' => $row_num, 'warnings' => $row_warn ];
                }
                $valid[] = $row_data;
            }
        }

        wp_send_json_success( [
            'total'    => count( $rows ),
            'valid'    => count( $valid ),
            'invalid'  => count( $errors ),
            'warnings' => count( $warnings ),
            'errors'   => $errors,
            'warn_list'=> $warnings,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX – Paso 4: Ejecutar importación
     * ------------------------------------------------------------------ */

    public static function ajax_execute() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_create' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos', 'aura-suite' ) ], 403 );
        }

        $token          = sanitize_text_field( $_POST['token'] ?? '' );
        $mapping        = isset( $_POST['mapping'] ) ? (array) $_POST['mapping'] : [];
        $options        = isset( $_POST['options'] ) ? (array) $_POST['options'] : [];
        $default_status = in_array( $options['default_status'] ?? '', [ 'pending', 'approved' ], true )
                            ? $options['default_status']
                            : 'pending';
        $auto_cat       = ! empty( $options['auto_create_category'] );
        $dup_action     = $options['duplicate_action'] ?? 'ask'; // ignore | import | ask

        $transient = get_transient( self::$transient_prefix . $token );
        if ( ! $transient ) {
            wp_send_json_error( [ 'message' => __( 'Sesión expirada. Suba el archivo de nuevo.', 'aura-suite' ) ] );
        }

        global $wpdb;
        $filepath = $transient['filepath'];
        $filename = $transient['filename'];

        $ext  = strtolower( pathinfo( $filepath, PATHINFO_EXTENSION ) );
        $rows = $ext === 'xlsx' ? self::parse_excel( $filepath ) : self::parse_csv( $filepath );

        if ( is_wp_error( $rows ) ) {
            wp_send_json_error( [ 'message' => $rows->get_error_message() ] );
        }

        array_shift( $rows );

        $batch_id  = wp_generate_uuid4();
        $imported  = 0;
        $failed    = 0;
        $error_log = [];
        $user_id   = get_current_user_id();
        $tx_table  = $wpdb->prefix . 'aura_finance_transactions';

        foreach ( $rows as $idx => $row ) {
            $row_num  = $idx + 2;
            $row_data = [];

            foreach ( $mapping as $field => $col_idx ) {
                $col_idx = (int) $col_idx;
                $row_data[ $field ] = isset( $row[ $col_idx ] ) ? trim( $row[ $col_idx ] ) : '';
            }

            // Parsear y validar
            $date   = self::parse_date( $row_data['transaction_date'] ?? '' );
            $amount = self::parse_amount( $row_data['amount'] ?? '' );
            $type   = self::normalize_type( $row_data['transaction_type'] ?? '' );

            if ( ! $date || $amount === false || ! $type ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Datos inválidos (fecha, monto o tipo)', 'aura-suite' ) ];
                continue;
            }

            // Categoría
            $cat_id = self::find_category( $row_data['category_id'] ?? '' );
            if ( ! $cat_id ) {
                if ( $auto_cat && ! empty( $row_data['category_id'] ) ) {
                    $cat_id = self::create_category( $row_data['category_id'], $type );
                }
                if ( ! $cat_id ) {
                    $failed++;
                    $error_log[] = [ 'row' => $row_num, 'reason' => sprintf( __( 'Categoría no encontrada: "%s"', 'aura-suite' ), $row_data['category_id'] ?? '' ) ];
                    continue;
                }
            }

            $description = ! empty( $row_data['description'] ) ? sanitize_text_field( $row_data['description'] ) : __( 'Importado', 'aura-suite' );

            // Detección de duplicados
            if ( $dup_action === 'ignore' && self::is_duplicate( $date, $amount, $description ) ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => __( 'Posible duplicado, fila ignorada', 'aura-suite' ) ];
                continue;
            }

            $wpdb->insert( $tx_table, [
                'transaction_type' => $type,
                'category_id'      => $cat_id,
                'amount'           => $amount,
                'transaction_date' => $date,
                'description'      => $description,
                'notes'            => sanitize_textarea_field( $row_data['notes'] ?? '' ),
                'status'           => $default_status,
                'payment_method'   => sanitize_text_field( $row_data['payment_method'] ?? '' ),
                'reference_number' => sanitize_text_field( $row_data['reference_number'] ?? '' ),
                'created_by'       => $user_id,
                'import_batch_id'  => $batch_id,
            ], [ '%s','%d','%f','%s','%s','%s','%s','%s','%s','%d','%s' ] );

            if ( $wpdb->last_error ) {
                $failed++;
                $error_log[] = [ 'row' => $row_num, 'reason' => $wpdb->last_error ];
            } else {
                $imported++;
            }
        }

        // Guardar log
        $log_table = $wpdb->prefix . 'aura_finance_import_log';
        $wpdb->insert( $log_table, [
            'batch_id'      => $batch_id,
            'filename'      => $filename,
            'rows_total'    => count( $rows ),
            'rows_imported' => $imported,
            'rows_failed'   => $failed,
            'error_log'     => wp_json_encode( $error_log ),
            'imported_by'   => $user_id,
        ], [ '%s','%s','%d','%d','%d','%s','%d' ] );

        // Limpiar transient y archivo
        delete_transient( self::$transient_prefix . $token );
        @unlink( $filepath );

        do_action( 'aura_finance_import_executed', $imported + $failed, $imported, $failed );
        wp_send_json_success( [
            'batch_id' => $batch_id,
            'imported' => $imported,
            'failed'   => $failed,
            'error_log'=> $error_log,
        ] );
    }

    /* ------------------------------------------------------------------
     * AJAX – Rollback
     * ------------------------------------------------------------------ */

    public static function ajax_rollback() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );
        if ( ! current_user_can( 'aura_finance_delete_all' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sin permisos para deshacer importaciones', 'aura-suite' ) ], 403 );
        }

        $batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );
        if ( ! $batch_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de lote no válido', 'aura-suite' ) ] );
        }

        global $wpdb;
        $tx_table  = $wpdb->prefix . 'aura_finance_transactions';
        $log_table = $wpdb->prefix . 'aura_finance_import_log';

        // Verificar que el lote pertenezca al usuario actual o sea admin
        if ( ! current_user_can( 'manage_options' ) ) {
            $log = $wpdb->get_row( $wpdb->prepare( "SELECT imported_by FROM {$log_table} WHERE batch_id = %s", $batch_id ) );
            if ( ! $log || (int) $log->imported_by !== get_current_user_id() ) {
                wp_send_json_error( [ 'message' => __( 'No tienes permiso para deshacer esta importación', 'aura-suite' ) ] );
            }
        }

        // Verificar que sea reciente (< 24h)
        $log = $wpdb->get_row( $wpdb->prepare( "SELECT created_at, status FROM {$log_table} WHERE batch_id = %s", $batch_id ) );
        if ( ! $log ) {
            wp_send_json_error( [ 'message' => __( 'Importación no encontrada', 'aura-suite' ) ] );
        }
        if ( $log->status === 'rolled_back' ) {
            wp_send_json_error( [ 'message' => __( 'Esta importación ya fue revertida', 'aura-suite' ) ] );
        }
        $age = time() - strtotime( $log->created_at );
        if ( $age > 86400 ) {
            wp_send_json_error( [ 'message' => __( 'Solo se puede revertir dentro de las 24 horas siguientes a la importación', 'aura-suite' ) ] );
        }

        $count = $wpdb->update(
            $tx_table,
            [ 'deleted_at' => current_time( 'mysql' ), 'deleted_by' => get_current_user_id() ],
            [ 'import_batch_id' => $batch_id, 'deleted_at' => null ],
            [ '%s', '%d' ],
            [ '%s', null ]
        );

        $wpdb->update( $log_table, [ 'status' => 'rolled_back' ], [ 'batch_id' => $batch_id ], [ '%s' ], [ '%s' ] );

        wp_send_json_success( [ 'reverted' => (int) $count ] );
    }

    /* ------------------------------------------------------------------
     * AJAX – Plantilla CSV
     * ------------------------------------------------------------------ */

    public static function ajax_template() {
        check_ajax_referer( 'aura_import_nonce', 'nonce' );

        $csv  = "\xEF\xBB\xBF"; // BOM UTF-8
        $csv .= "transaction_date,transaction_type,category,amount,description,notes,payment_method,reference_number\n";
        $csv .= "01/01/2026,income,Ventas,1500.00,Venta de productos,Factura #001,transferencia,REF-001\n";
        $csv .= "15/01/2026,expense,Suministros,350.50,Compra de materiales,,efectivo,\n";
        $csv .= "31/01/2026,income,Servicios,800.00,Servicio de consultoría,,tarjeta,REF-003\n";

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="plantilla-importacion-transacciones.csv"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        echo $csv;
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

    private static function auto_detect_mapping( $headers ) {
        $system_fields = [
            'transaction_date' => [ 'fecha', 'date', 'transaction_date', 'fecha_transaccion', 'fecha transaccion', 'f. transaccion' ],
            'transaction_type' => [ 'tipo', 'type', 'transaction_type', 'tipo_transaccion', 'tipo transaccion', 'class', 'clase' ],
            'category_id'      => [ 'categoria', 'category', 'category_id', 'rubro', 'cuenta', 'account' ],
            'amount'           => [ 'monto', 'amount', 'importe', 'valor', 'total', 'value', 'precio', 'price' ],
            'description'      => [ 'descripcion', 'description', 'concepto', 'concept', 'detalle', 'detail', 'nombre', 'name' ],
            'notes'            => [ 'notas', 'notes', 'observaciones', 'comentario', 'comments', 'remarks' ],
            'payment_method'   => [ 'metodo_pago', 'payment_method', 'pago', 'medio_pago', 'forma_pago', 'method' ],
            'reference_number' => [ 'referencia', 'reference', 'reference_number', 'numero_referencia', 'ref', 'numero', 'number', 'comprobante' ],
        ];

        $mapping = [];
        foreach ( $headers as $idx => $header ) {
            $normalized = strtolower( trim( preg_replace( '/\s+/', ' ', $header ) ) );
            foreach ( $system_fields as $field => $aliases ) {
                if ( isset( $mapping[ $field ] ) ) continue;
                if ( in_array( $normalized, $aliases, true ) ) {
                    $mapping[ $field ] = $idx;
                    break;
                }
            }
        }
        return $mapping;
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
        $v = strtolower( trim( $value ) );
        if ( in_array( $v, [ 'income', 'ingreso', 'ingresos', 'i', 'in', 'entrada', 'credit', 'credito' ], true ) ) return 'income';
        if ( in_array( $v, [ 'expense', 'egreso', 'egresos', 'e', 'ex', 'salida', 'debit', 'debito', 'gasto', 'gastos' ], true ) ) return 'expense';
        return false;
    }

    private static function find_category( $value ) {
        if ( empty( $value ) ) return false;
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';

        // Por ID numérico
        if ( is_numeric( $value ) ) {
            return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND deleted_at IS NULL", (int) $value ) );
        }

        // Por nombre exacto
        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s AND deleted_at IS NULL", $value ) );
        if ( $id ) return $id;

        // Por nombre insensible a mayúsculas
        return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE LOWER(name) = LOWER(%s) AND deleted_at IS NULL", $value ) );
    }

    private static function create_category( $name, $type = 'expense' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_categories';
        $wpdb->insert( $table, [
            'name'             => sanitize_text_field( $name ),
            'type'             => in_array( $type, [ 'income', 'expense' ], true ) ? $type : 'expense',
            'description'      => __( 'Creada automáticamente al importar', 'aura-suite' ),
            'color'            => '#607D8B',
            'is_active'        => 1,
            'created_at'       => current_time( 'mysql' ),
        ], [ '%s','%s','%s','%s','%d','%s' ] );
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

    /* ------------------------------------------------------------------
     * Renderizar página admin
     * ------------------------------------------------------------------ */

    public static function render() {
        include AURA_PLUGIN_DIR . 'templates/financial/import-page.php';
    }
}
