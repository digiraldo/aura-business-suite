<?php
/**
 * Clase para gestionar el listado de transacciones financieras
 * Extiende WP_List_Table para crear una tabla administrativa completa
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 2.1.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar WP_List_Table si no está disponible
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Clase Aura_Financial_Transactions_List
 * 
 * Gestiona el listado de transacciones con filtros avanzados,
 * búsqueda, paginación, filas expandibles (Child Rows) y diseño responsive.
 */
class Aura_Financial_Transactions_List extends Aura_List_Table {
    
    /**
     * Estadísticas de transacciones filtradas
     *
     * @var array
     */
    private $stats = array(
        'total_income'  => 0,
        'total_expense' => 0,
        'total_capital' => 0,
        'balance'       => 0,
        'count'         => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'transacción',
            'plural'   => 'transacciones',
            'ajax'     => true
        ));
    }
    
    /**
     * Proxy público para display_tablenav() (protected en WP_List_Table).
     * Necesario para renderizar los controles de paginación de forma separada
     * del scroll horizontal de la tabla en el template.
     *
     * @param string $which 'top' o 'bottom'
     */
    public function render_tablenav( $which ) {
        $this->display_tablenav( $which );
    }

    /**
     * Proxy público para get_table_classes() (protected en WP_List_Table).
     * Devuelve las clases CSS que WP asigna a la tabla.
     *
     * @return string[]
     */
    public function get_classes() {
        return $this->get_table_classes();
    }


    /**
     * Inicializar la clase y hooks
     */
    public static function init() {
        // AJAX handlers para filtros y búsqueda
        add_action('wp_ajax_aura_filter_transactions', array(__CLASS__, 'ajax_filter_transactions'));
        add_action('wp_ajax_aura_search_transactions', array(__CLASS__, 'ajax_search_transactions'));
        add_action('wp_ajax_aura_bulk_action_transactions', array(__CLASS__, 'ajax_bulk_action'));
        add_action('wp_ajax_aura_quick_approve', array(__CLASS__, 'ajax_quick_approve'));
        add_action('wp_ajax_aura_quick_reject', array(__CLASS__, 'ajax_quick_reject'));
        add_action('wp_ajax_aura_save_filter_preset', array(__CLASS__, 'ajax_save_filter_preset'));
        add_action('wp_ajax_aura_load_filter_preset', array(__CLASS__, 'ajax_load_filter_preset'));
        
        // Enqueue scripts y styles
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue scripts y styles
     */
    public static function enqueue_scripts($hook) {
        if (strpos($hook, '_page_aura-financial-transactions') === false) {
            return;
        }
        
        // jQuery UI Datepicker para rango de fechas
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');
        
        // Select2 para dropdowns múltiples
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));

        $tx_list_js_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/transactions-list.js');
        $tx_modal_js_ver = AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/js/transaction-modal.js');
        
        // Styles personalizados
        wp_enqueue_style(
            'aura-transactions-list',
            AURA_PLUGIN_URL . 'assets/css/transactions-list.css',
            array('aura-admin-styles'),
            AURA_VERSION
        );

        wp_enqueue_style(
            'aura-transaction-modal',
            AURA_PLUGIN_URL . 'assets/css/transaction-modal.css',
            array('aura-transactions-list'),
            AURA_VERSION
        );

        // Scripts personalizados
        wp_enqueue_script(
            'aura-transactions-list',
            AURA_PLUGIN_URL . 'assets/js/transactions-list.js',
            array('jquery', 'jquery-ui-datepicker', 'jquery-ui-autocomplete', 'select2'),
            $tx_list_js_ver,
            true
        );

        wp_enqueue_script(
            'aura-transaction-modal',
            AURA_PLUGIN_URL . 'assets/js/transaction-modal.js',
            array('jquery', 'aura-transactions-list'),
            $tx_modal_js_ver,
            true
        );
        
        // Localizar script del listado
        wp_localize_script('aura-transactions-list', 'auraTransactionsList', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aura_transactions_list_nonce'),
            'messages' => array(
                'confirmBulkDelete' => __('¿Estás seguro de eliminar las transacciones seleccionadas?', 'aura-suite'),
                'confirmApprove' => __('¿Aprobar esta transacción?', 'aura-suite'),
                'confirmReject' => __('¿Rechazar esta transacción?', 'aura-suite'),
                'rejectReason' => __('Ingresa el motivo del rechazo (mínimo 10 caracteres):', 'aura-suite'),
                'filterSaved' => __('Filtro guardado exitosamente', 'aura-suite'),
                'filterLoaded' => __('Filtro cargado', 'aura-suite'),
                'error' => __('Error al procesar la solicitud', 'aura-suite'),
            ),
            'userCan' => array(
                'view_all' => current_user_can('aura_finance_view_all'),
                'edit_all' => current_user_can('aura_finance_edit_all'),
                'delete_all' => current_user_can('aura_finance_delete_all'),
                'approve' => current_user_can('aura_finance_approve'),
            ),
        ));

        // Localizar script del modal de detalles
        wp_localize_script('aura-transaction-modal', 'auraTransactionModal', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('aura_transaction_modal_nonce'),
            'approvalNonce'  => wp_create_nonce('aura_transactions_list_nonce'),
            'currentUserId'  => get_current_user_id(),
            'editUrl'        => admin_url('admin.php?page=aura-financial-edit-transaction'),
            'permissions'    => array(
                'canCreate'    => current_user_can('aura_finance_create'),
                'canEditOwn'   => current_user_can('aura_finance_edit_own'),
                'canEditAll'   => current_user_can('aura_finance_edit_all'),
                'canDeleteOwn' => current_user_can('aura_finance_delete_own'),
                'canDeleteAll' => current_user_can('aura_finance_delete_all'),
                'canApprove'   => current_user_can('aura_finance_approve'),
                'canViewOwn'   => current_user_can('aura_finance_view_own'),
                'canViewAll'   => current_user_can('aura_finance_view_all')
            ),
            'messages' => array(
                'confirmApprove' => __('¿Aprobar esta transacción?', 'aura-suite'),
                'confirmReject'  => __('¿Rechazar esta transacción? Debes proporcionar un motivo.', 'aura-suite'),
                'confirmDelete'  => __('¿Eliminar esta transacción? Esta acción moverá el registro a la papelera.', 'aura-suite'),
                'error'          => __('Error al procesar la solicitud', 'aura-suite'),
                'statusPending'  => __('Pendiente', 'aura-suite'),
                'statusApproved' => __('Aprobado', 'aura-suite'),
                'statusRejected' => __('Rechazado', 'aura-suite'),
            )
        ));
    }
    
    /**
     * Definir columnas de la tabla
     */
    public function get_columns() {
        $columns = array(
            'cb'              => '<input type="checkbox" />',
            'id'              => __('#', 'aura-suite'),           // # + flecha tipo
            'status'          => __('Estado', 'aura-suite'),      // badge + aprobación inline
            'transaction_date'=> __('Fecha', 'aura-suite'),
            'category'        => __('Categoría', 'aura-suite'),   // badge con tooltip descripción
            'area'            => __('Área/Programa', 'aura-suite'),
            'amount'          => __('Monto', 'aura-suite'),
            'payment_method'  => __('Pago', 'aura-suite'),        // solo ícono
            'related_user'    => __('Vinculado', 'aura-suite'),
            'created_by'      => __('Crea', 'aura-suite'),        // avatar + nombre
            'actions'         => __('', 'aura-suite'),            // solo íconos
        );
        
        return $columns;
    }
    
    /**
     * Definir columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'transaction_date' => array('transaction_date', true),
            'amount'           => array('amount', false),
            'status'           => array('status', false),
        );
    }
    
    /**
     * Definir acciones masivas disponibles en la tabla
     */
    public function get_bulk_actions() {
        $actions = array();
        
        if (current_user_can('aura_finance_bulk_edit') || current_user_can('manage_options')) {
            $actions['bulk_edit'] = __('✏️ Edición Masiva (Área, Pago, Vinculado, Crea)', 'aura-suite');
        }
        
        if (current_user_can('aura_finance_edit_all') || current_user_can('aura_finance_edit_own')) {
            $actions['change_category'] = __('🏷️ Cambiar Categoría', 'aura-suite');
        }
        
        if (current_user_can('aura_finance_approve')) {
            $actions['bulk_approve'] = __('✓ Aprobar Seleccionadas', 'aura-suite');
        }
        
        if (current_user_can('aura_finance_delete_all') || current_user_can('aura_finance_delete_own')) {
            $actions['bulk_delete'] = __('🗑️ Eliminar Seleccionadas', 'aura-suite');
        }
        
        $actions['bulk_export_csv'] = __('Exportar a CSV', 'aura-suite');
        $actions['bulk_export_pdf'] = __('Exportar a PDF', 'aura-suite');
        
        return $actions;
    }
    
    /**
     * Checkbox para selección masiva
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="transaction_ids[]" value="%d" />',
            $item->id
        );
    }
    
    /**
     * Helper para extraer y normalizar la lista de comprobantes de una transacción
     *
     * @param object|array $item
     * @return array
     */
    public static function get_transaction_receipt_files( $item ) {
        $voucher_raw = is_object( $item )
            ? ( ! empty( $item->receipt_file ) ? $item->receipt_file : ( ! empty( $item->voucher_url ) ? $item->voucher_url : '' ) )
            : ( ! empty( $item['receipt_file'] ) ? $item['receipt_file'] : ( ! empty( $item['voucher_url'] ) ? $item['voucher_url'] : '' ) );

        if ( empty( $voucher_raw ) ) {
            return [];
        }

        $raw_files = [];
        if ( is_string( $voucher_raw ) && ( str_starts_with( trim( $voucher_raw ), '[' ) || str_starts_with( trim( $voucher_raw ), '{' ) ) ) {
            $decoded = json_decode( $voucher_raw, true );
            if ( is_array( $decoded ) ) {
                $raw_files = isset( $decoded['url'] ) ? [ $decoded ] : $decoded;
            }
        }
        if ( empty( $raw_files ) ) {
            $raw_files = [ $voucher_raw ];
        }

        $normalized = [];
        foreach ( $raw_files as $idx => $rf ) {
            $url = is_array( $rf ) ? ( $rf['url'] ?? ( $rf['file_url'] ?? '' ) ) : (string) $rf;
            if ( empty( $url ) ) {
                continue;
            }
            $name = is_array( $rf ) ? ( $rf['name'] ?? ( $rf['filename'] ?? basename( $url ) ) ) : basename( $url );
            $is_drive = class_exists( 'Aura_Drive_Manager' ) && Aura_Drive_Manager::is_drive_url( $rf );
            $is_pdf   = (bool) ( preg_match( '/\.pdf($|\?)/i', $url ) || preg_match( '/\.pdf($|\?)/i', $name ) );
            $is_img   = ! $is_pdf && (bool) ( preg_match( '/\.(jpg|jpeg|png|webp|gif)($|\?)/i', $url ) || preg_match( '/\.(jpg|jpeg|png|webp|gif)($|\?)/i', $name ) );

            if ( $is_drive ) {
                $file_id      = Aura_Drive_Manager::extract_file_id( $rf );
                $preview_url  = Aura_Drive_Manager::get_preview_url( $file_id );
                $download_url = Aura_Drive_Manager::get_download_url( $file_id );
                $thumb_url    = Aura_Drive_Manager::get_thumbnail_url( $file_id );
            } else {
                $preview_url  = esc_url( $url );
                $download_url = esc_url( $url );
                $thumb_url    = esc_url( $url );
            }

            $normalized[] = [
                'index'         => $idx,
                'url'           => $url,
                'name'          => $name,
                'preview_url'   => $preview_url,
                'download_url'  => $download_url,
                'thumbnail_url' => $thumb_url,
                'is_drive'      => $is_drive,
                'is_pdf'        => $is_pdf,
                'is_img'        => $is_img,
            ];
        }

        return $normalized;
    }

    /**
     * Columna #ID — distintivo visual ultra-moderno de tipo con chevron toggle, clip de comprobante y tooltip enriquecido
     */
    public function column_id( $item ) {
        $type = strtolower( (string) $item->transaction_type );

        if ( $type === 'income' ) {
            $icon       = '↑';
            $color      = '#059669'; // Emerald
            $bg         = '#ecfdf5';
            $border     = '#a7f3d0';
            $badge_text = __('Ingreso', 'aura-suite');
            $type_desc  = __('Ingreso Operacional (+) a balance contable', 'aura-suite');
        } elseif ( $type === 'capital' ) {
            $icon       = '🏛️';
            $color      = '#7c3aed'; // Violet / CapEx
            $bg         = '#f5f3ff';
            $border     = '#ddd6fe';
            $badge_text = __('Capital', 'aura-suite');
            $type_desc  = __('Gasto de Capital (Inversión en Activos / CapEx)', 'aura-suite');
        } elseif ( $type === 'transfer' ) {
            $icon       = '⇄';
            $color      = '#2563eb'; // Blue
            $bg         = '#eff6ff';
            $border     = '#bfdbfe';
            $badge_text = __('Transfer', 'aura-suite');
            $type_desc  = __('Transferencia entre Cuentas Internas', 'aura-suite');
        } else {
            $icon       = '↓';
            $color      = '#dc2626'; // Red
            $bg         = '#fef2f2';
            $border     = '#fecaca';
            $badge_text = __('Egreso', 'aura-suite');
            $type_desc  = __('Egreso Operacional (-) de balance contable', 'aura-suite');
        }

        $tip_html = '<div class="aura-tip-card">'
            . '<div class="aura-tip-card-header">'
            . '<div class="aura-tip-avatar-large" style="background:' . esc_attr($color) . '25;border-color:' . esc_attr($color) . '55;color:' . esc_attr($color) . ';font-size:22px;font-weight:900;">'
            . esc_html($icon)
            . '</div>'
            . '<div class="aura-tip-info">'
            . '<div class="aura-tip-title" style="color:#ffffff;">' . sprintf(__('Transacción #%d', 'aura-suite'), $item->id) . '</div>'
            . '<div class="aura-tip-subtitle">' . esc_html($type_desc) . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';

        $toggle_btn = $this->render_row_toggle( $item->id );

        // Botón de comprobante / adjunto (Opción A)
        $receipt_files = self::get_transaction_receipt_files( $item );
        $receipt_badge_html = '';
        if ( ! empty( $receipt_files ) ) {
            $count_files = count( $receipt_files );
            $first_file  = $receipt_files[0];
            $is_drive    = $first_file['is_drive'];

            // Preparar items formateados para data-files
            $data_files = array_map( function( $rf ) {
                return [
                    'url'           => $rf['url'],
                    'name'          => $rf['name'],
                    'preview_url'   => $rf['preview_url'],
                    'download_url'  => $rf['download_url'],
                    'thumbnail_url' => $rf['thumbnail_url'],
                    'is_drive'      => $rf['is_drive'],
                    'is_pdf'        => $rf['is_pdf'],
                    'is_img'        => $rf['is_img'],
                ];
            }, $receipt_files );

            $icon_badge = $is_drive
                ? '<span class="dashicons dashicons-cloud" style="font-size:12.5px;width:12.5px;height:12.5px;line-height:12.5px;"></span>'
                : '<span class="dashicons dashicons-paperclip" style="font-size:12.5px;width:12.5px;height:12.5px;line-height:12.5px;"></span>';

            $count_label = $count_files > 1 ? '<span style="font-size:10px;font-weight:700;line-height:1;margin-left:2px;">' . $count_files . '</span>' : '';

            $tip_avatar_bg     = $is_drive ? 'rgba(37,99,235,0.2)' : 'rgba(100,116,139,0.2)';
            $tip_avatar_border = $is_drive ? 'rgba(59,130,246,0.45)' : 'rgba(148,163,184,0.45)';
            $tip_avatar_color  = $is_drive ? '#60a5fa' : '#cbd5e1';
            $tip_icon_char     = $is_drive ? '☁️' : '📎';
            $tip_title         = $count_files > 1 
                ? sprintf( __( '%d Comprobantes Adjuntos', 'aura-suite' ), $count_files )
                : __( 'Respaldo Digital', 'aura-suite' );
            $tip_sub           = esc_html( $first_file['name'] ) . ( $count_files > 1 ? ' (+' . ( $count_files - 1 ) . ' más)' : '' );
            $badge_origin_tip  = $is_drive
                ? '<span class="aura-tip-badge aura-tip-badge--company"><span class="dashicons dashicons-cloud" style="font-size:10px;width:10px;height:10px;line-height:10px;"></span> Google Drive</span>'
                : '<span class="aura-tip-badge aura-tip-badge--store"><span class="dashicons dashicons-admin-home" style="font-size:10px;width:10px;height:10px;line-height:10px;"></span> Local</span>';
            $badge_ext_tip     = $first_file['is_pdf'] ? 'PDF' : ( $first_file['is_img'] ? 'Imagen' : 'Documento' );

            $clip_tip_html = '<div class="aura-tip-card">'
                . '<div class="aura-tip-card-header">'
                . '<div class="aura-tip-avatar-large" style="background:' . esc_attr( $tip_avatar_bg ) . ';border-color:' . esc_attr( $tip_avatar_border ) . ';color:' . esc_attr( $tip_avatar_color ) . ';font-size:20px;">'
                . $tip_icon_char
                . '</div>'
                . '<div class="aura-tip-info">'
                . '<div class="aura-tip-title" style="color:#ffffff;">' . esc_html( $tip_title ) . '</div>'
                . '<div class="aura-tip-subtitle" title="' . esc_attr( $first_file['name'] ) . '">' . $tip_sub . '</div>'
                . '<div class="aura-tip-badges">'
                . $badge_origin_tip
                . '<span class="aura-tip-badge aura-tip-badge--role">' . esc_html( $badge_ext_tip ) . '</span>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '<div class="aura-tip-card-body">'
                . '<div class="aura-tip-row"><span>' . __( 'Almacenamiento:', 'aura-suite' ) . '</span><strong style="color:#e2e8f0;">' . ( $is_drive ? __( 'Unidad Compartida', 'aura-suite' ) : __( 'Servidor Local', 'aura-suite' ) ) . '</strong></div>'
                . '<div class="aura-tip-row" style="margin-top:4px;padding-top:6px;border-top:1px solid rgba(255,255,255,0.08);color:#60a5fa;font-weight:600;"><span style="color:#60a5fa;">💡 ' . __( 'Clic para previsualizar en el visor modal', 'aura-suite' ) . '</span></div>'
                . '</div>'
                . '</div>';

            $aria_label = $count_files > 1 
                ? sprintf( __( '%d comprobantes adjuntos', 'aura-suite' ), $count_files )
                : sprintf( __( 'Comprobante: %s', 'aura-suite' ), esc_attr( $first_file['name'] ) );

            $receipt_badge_html = sprintf(
                '<button type="button" class="aura-id-clip-btn %s aura-open-receipt-viewer aura-has-tooltip" '
                . 'data-receipt-url="%s" data-preview-url="%s" data-download-url="%s" data-is-drive="%d" data-is-pdf="%d" data-is-img="%d" '
                . 'data-title="%s" data-files="%s" data-tooltip="%s" aria-label="%s" '
                . 'style="margin-left:2px;">'
                . '%s%s'
                . '</button>',
                $is_drive ? 'is-drive' : '',
                esc_url( $first_file['url'] ),
                esc_url( $first_file['preview_url'] ),
                esc_url( $first_file['download_url'] ),
                $is_drive ? 1 : 0,
                $first_file['is_pdf'] ? 1 : 0,
                $first_file['is_img'] ? 1 : 0,
                esc_attr( sprintf( __( 'Transacción #%d - Comprobante', 'aura-suite' ), $item->id ) ),
                esc_attr( wp_json_encode( $data_files ) ),
                esc_attr( $clip_tip_html ),
                esc_attr( $aria_label ),
                $icon_badge,
                $count_label
            );
        }

        // Badge de origen de flujo (Caja Chica / Reembolso)
        $origin_badge_html = '';
        $concept_raw = ! empty( $item->related_user_concept ) ? (string) $item->related_user_concept : '';
        if ( $concept_raw === 'petty_cash_settlement' || strpos( strtolower( (string) $item->description ), 'rendición caja chica' ) !== false ) {
            // Extraer ID si existe en description (ej: #4)
            $settle_ref = '';
            if ( preg_match( '/#(\d+)/', (string) $item->description, $m_id ) ) {
                $settle_ref = ' #' . $m_id[1];
            }
            $origin_badge_html = sprintf(
                '<a href="%s" class="aura-txn-origin-badge aura-has-tooltip" data-tooltip="%s" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:2px 6px;border-radius:6px;font-size:10.5px;font-weight:700;display:inline-flex;align-items:center;gap:3px;text-decoration:none;margin-left:2px;">'
                . '<span class="dashicons dashicons-archive" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span>'
                . '<span>Caja%s</span>'
                . '</a>',
                esc_url( admin_url( 'admin.php?page=aura-financial-accounts#petty-cash' ) ),
                esc_attr( __( 'Transacción originada automáticamente por Rendición de Caja Chica aprobada.', 'aura-suite' ) ),
                esc_html( $settle_ref )
            );
        } elseif ( $concept_raw === 'expense_reimbursement' || strpos( strtolower( (string) $item->description ), 'reembolso' ) !== false ) {
            $reimb_ref = '';
            if ( preg_match( '/#(\d+)/', (string) $item->description, $m_id ) ) {
                $reimb_ref = ' #' . $m_id[1];
            }
            $origin_badge_html = sprintf(
                '<a href="%s" class="aura-txn-origin-badge aura-has-tooltip" data-tooltip="%s" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;padding:2px 6px;border-radius:6px;font-size:10.5px;font-weight:700;display:inline-flex;align-items:center;gap:3px;text-decoration:none;margin-left:2px;">'
                . '<span class="dashicons dashicons-businessman" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span>'
                . '<span>Reemb%s</span>'
                . '</a>',
                esc_url( admin_url( 'admin.php?page=aura-financial-accounts#reimbursements' ) ),
                esc_attr( __( 'Transacción originada por pago de Reembolso a Persona.', 'aura-suite' ) ),
                esc_html( $reimb_ref )
            );
        }

        return sprintf(
            '<div class="aura-id-cell-wrapper" style="display:inline-flex;align-items:center;gap:4px;">'
            . '%s'
            . '<div class="aura-txn-id-pill aura-has-tooltip" data-tooltip="%s" style="background:%s;border:1px solid %s;">'
            . '<span class="txn-type-icon" style="color:%s;">%s</span>'
            . '<span class="txn-id-num">#%d</span>'
            . '<span class="txn-type-tag" style="color:%s;">%s</span>'
            . '</div>'
            . '%s'
            . '%s'
            . '</div>',
            $toggle_btn,
            esc_attr( $tip_html ),
            esc_attr( $bg ),
            esc_attr( $border ),
            esc_attr( $color ),
            $icon,
            $item->id,
            esc_attr( $color ),
            esc_html( $badge_text ),
            $receipt_badge_html,
            $origin_badge_html
        );
    }

    /**
     * Columna de estado — incluye indicador de método de aprobación y data-tooltip tipo tarjeta
     */
    public function column_status( $item ) {
        $status_map = array(
            'pending'  => array( 'label' => __('Pendiente', 'aura-suite'),  'color' => '#f59e0b', 'dashicon' => 'dashicons-clock' ),
            'approved' => array( 'label' => __('Aprobado', 'aura-suite'),   'color' => '#10b981', 'dashicon' => 'dashicons-yes-alt' ),
            'rejected' => array( 'label' => __('Rechazado', 'aura-suite'),  'color' => '#ef4444', 'dashicon' => 'dashicons-dismiss' ),
        );

        $s = $status_map[ $item->status ] ?? $status_map['pending'];

        $tip_html = '<div class="aura-tip-card">'
            . '<div class="aura-tip-card-header">'
            . '<div class="aura-tip-avatar-large" style="background:' . esc_attr($s['color']) . '25;border-color:' . esc_attr($s['color']) . '55;color:' . esc_attr($s['color']) . ';">'
            . '<span class="dashicons ' . esc_attr($s['dashicon']) . '" style="font-size:24px;width:24px;height:24px;"></span>'
            . '</div>'
            . '<div class="aura-tip-info">'
            . '<div class="aura-tip-title" style="color:#ffffff;">' . sprintf(__('Estado: %s', 'aura-suite'), esc_html($s['label'])) . '</div>'
            . '<div class="aura-tip-subtitle">' . esc_html__('Flujo de Aprobación Financiera', 'aura-suite') . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="aura-tip-card-body">';

        if ( $item->status === 'rejected' && ! empty( $item->rejection_reason ) ) {
            $tip_html .= '<div class="aura-tip-row"><span>⚠️ Motivo de Rechazo:</span><strong style="color:#fca5a5;">' . esc_html( $item->rejection_reason ) . '</strong></div>';
        } elseif ( $item->status === 'approved' && ! empty( $item->approved_by ) ) {
            $is_auto = ( $item->approved_by == $item->created_by );
            if ( $is_auto ) {
                $threshold = (float) get_option('aura_finance_auto_approval_threshold', 0);
                $tip_html .= '<div class="aura-tip-row"><span>⚡ Tipo de Aprobación:</span><strong>' 
                    . ( $threshold > 0 ? sprintf( __('Auto-aprobada (monto < $%s)', 'aura-suite'), number_format($threshold, 0, '.', ',') ) : __('Auto-aprobada por el sistema', 'aura-suite') )
                    . '</strong></div>';
            } else {
                $approver = get_userdata( $item->approved_by );
                $approver_name = $approver ? $approver->display_name : __('N/D', 'aura-suite');
                $tip_html .= '<div class="aura-tip-row"><span>👤 Aprobada por:</span><strong>' . esc_html( $approver_name ) . '</strong></div>';
            }
            if ( ! empty($item->approved_at) ) {
                $app_date = new DateTime($item->approved_at);
                $tip_html .= '<div class="aura-tip-row"><span>🕒 Fecha Aprobación:</span><strong>' . esc_html($app_date->format('d/m/Y h:i A')) . '</strong></div>';
            }
        } elseif ( $item->status === 'pending' ) {
            $tip_html .= '<div class="aura-tip-row"><span>⏳ Estado:</span><strong>' . esc_html__('Esperando revisión por auditor/administrador', 'aura-suite') . '</strong></div>';
        }

        $tip_html .= '</div></div>';

        $badge = sprintf(
            '<span class="aura-status-badge aura-has-tooltip" data-tooltip="%s" style="display:inline-flex;align-items:center;gap:4px;background:%s;color:#fff;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap;cursor:help;">'
            . '<span class="dashicons %s" style="font-size:13px;width:13px;height:13px;line-height:13px;margin:0;"></span>%s'
            . '</span>',
            esc_attr( $tip_html ),
            esc_attr( $s['color'] ),
            esc_attr( $s['dashicon'] ),
            esc_html( $s['label'] )
        );

        return $badge;
    }
    
    /**
     * @deprecated Fusionada dentro de column_status()
     */
    public function column_approval_method($item) {
        return '';
    }
    
    /**
     * Columna de fecha con tooltip de fecha completa
     */
    public function column_transaction_date($item) {
        $date = new DateTime($item->transaction_date);
        $formatted = $date->format('d/m/Y');
        $full_tip  = sprintf( __('Fecha de Operación: %s', 'aura-suite'), $date->format('l, d \d\e F \d\e Y') );

        return sprintf(
            '<span class="aura-date-cell aura-has-tooltip" data-tooltip="%s" style="font-weight:500;color:#334155;cursor:help;white-space:nowrap;">%s</span>',
            esc_attr( $full_tip ),
            esc_html( $formatted )
        );
    }
    
    /**
     * @deprecated El tipo ahora va dentro de column_id()
     */
    public function column_type($item) {
        return '';
    }
    
    /**
     * Columna de categoría con tooltip enriquecido y estructurado tipo tarjeta (Soporta Emojis y Dashicons)
     */
    public function column_category($item) {
        $description = ! empty( $item->description ) ? (string) $item->description : '';
        $cat_name    = ! empty( $item->category_name ) ? (string) $item->category_name : __( 'Sin categoría', 'aura-suite' );
        $cat_color   = $item->category_color ?: '#64748b';
        $cat_icon    = ! empty( $item->category_icon ) ? (string) $item->category_icon : 'dashicons-tag';
        $type        = strtolower( (string) $item->transaction_type );
        $type_labels = array(
            'income'   => __('Ingreso (+)', 'aura-suite'),
            'expense'  => __('Egreso (-)', 'aura-suite'),
            'capital'  => __('Gasto de Capital (CapEx)', 'aura-suite'),
            'transfer' => __('Transferencia', 'aura-suite'),
        );
        $type_label  = $type_labels[$type] ?? ucfirst($type);
        
        $icon_html = '';
        if ( class_exists( 'Aura_Financial_Categories' ) ) {
            $icon_html = Aura_Financial_Categories::render_icon_html( $cat_icon, '#ffffff' ) . ' ';
        } elseif ( strpos( $cat_icon, 'dashicons-' ) !== false || strpos( $cat_icon, 'dashicons' ) !== false ) {
            $icon_html = '<span class="dashicons ' . esc_attr($cat_icon) . '"></span> ';
        } else {
            $icon_html = '<span class="aura-cat-emoji" style="font-size:13px;line-height:1;margin-right:2px;">' . esc_html($cat_icon) . '</span> ';
        }

        // Renderizado del ícono/emoji para el Tooltip Flotante
        $tip_icon_render = '';
        if ( class_exists( 'Aura_Financial_Categories' ) ) {
            $tip_icon_render = Aura_Financial_Categories::render_icon_html( $cat_icon, $cat_color );
        } elseif ( strpos( $cat_icon, 'dashicons-' ) !== false || strpos( $cat_icon, 'dashicons' ) !== false ) {
            $tip_icon_render = '<span class="dashicons ' . esc_attr($cat_icon) . '" style="font-size:24px;width:24px;height:24px;"></span>';
        } else {
            $tip_icon_render = '<span class="aura-cat-emoji" style="font-size:22px;line-height:1;">' . esc_html($cat_icon ?: '🏷️') . '</span>';
        }

        // Tooltip Card Organizado
        $tip_html = '<div class="aura-tip-card">'
            . '<div class="aura-tip-card-header">'
            . '<div class="aura-tip-avatar-large" style="background:' . esc_attr($cat_color) . '25;border-color:' . esc_attr($cat_color) . '55;color:' . esc_attr($cat_color) . ';font-size:22px;display:inline-flex;align-items:center;justify-content:center;">'
            . $tip_icon_render
            . '</div>'
            . '<div class="aura-tip-info">'
            . '<div class="aura-tip-title" style="color:#ffffff;">' . esc_html($cat_name) . '</div>'
            . '<div class="aura-tip-subtitle">' . esc_html($type_label) . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="aura-tip-card-body">';

        if ( $description ) {
            $tip_html .= '<div class="aura-tip-row"><span>📝 Detalle:</span><strong>' . esc_html($description) . '</strong></div>';
        }
        if ( ! empty( $item->area_name ) ) {
            $tip_html .= '<div class="aura-tip-row"><span>🏢 Área:</span><strong>' . esc_html($item->area_name) . '</strong></div>';
        }
        if ( ! empty( $item->notes ) ) {
            $tip_html .= '<div class="aura-tip-row"><span>💬 Notas:</span><strong>' . esc_html($item->notes) . '</strong></div>';
        }
        if ( ! empty( $item->reference_number ) ) {
            $tip_html .= '<div class="aura-tip-row"><span>🔖 Ref:</span><strong>' . esc_html($item->reference_number) . '</strong></div>';
        }
        if ( ! empty( $item->tags ) ) {
            $tags_arr = array_filter( array_map( 'trim', explode( ',', $item->tags ) ) );
            if ( ! empty( $tags_arr ) ) {
                $tags_badges = array_map( function( $t ) {
                    return '<span style="background:rgba(37,99,235,0.22);border:1px solid rgba(59,130,246,0.45);color:#93c5fd;padding:1px 6px;border-radius:4px;font-size:10.5px;font-weight:600;display:inline-block;margin:1px 2px;">#' . esc_html( ltrim($t, '#') ) . '</span>';
                }, $tags_arr );
                $tip_html .= '<div class="aura-tip-row"><span>🏷️ ' . esc_html__('Etiquetas:', 'aura-suite') . '</span><strong style="display:flex;flex-wrap:wrap;gap:3px;justify-content:flex-end;">' . implode('', $tags_badges) . '</strong></div>';
            }
        }
        $tip_html .= '</div></div>';

        return sprintf(
            '<span class="aura-category-badge aura-has-tooltip" data-tooltip="%s" style="background-color:%s;color:#fff;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;cursor:help;display:inline-flex;align-items:center;gap:4px;">%s%s</span>',
            esc_attr( $tip_html ),
            esc_attr( $cat_color ),
            $icon_html,
            esc_html( $cat_name )
        );
    }

    /**
     * Columna de área / programa con logo/icono/emoji y tooltip tipo tarjeta
     */
    public function column_area($item) {
        if ( ! empty( $item->area_name ) ) {
            $color    = $item->area_color ?: '#64748b';
            $icon     = $item->area_icon  ?: 'dashicons-category';
            $logo_id  = (int) ( $item->area_logo_id ?? 0 );
            $logo_url = $logo_id ? ( wp_get_attachment_image_url( $logo_id, 'medium' ) ?: wp_get_attachment_url( $logo_id ) ?: '' ) : '';

            $icon_or_logo_html = '';
            $tip_logo_html     = '';
            if ( ! empty( $logo_url ) ) {
                $icon_or_logo_html = sprintf(
                    '<span class="aura-area-logo-wrap">'
                    . '<img src="%s" class="aura-area-logo-img" alt="%s" onerror="this.style.display=\'none\'; if(this.nextElementSibling){ this.nextElementSibling.style.display=\'inline-flex\'; }">'
                    . '<span class="aura-area-icon-fallback" style="display:none;color:%s;"><span class="dashicons %s"></span></span>'
                    . '</span>',
                    esc_url( $logo_url ),
                    esc_attr( $item->area_name ),
                    esc_attr( $color ),
                    esc_attr( $icon )
                );
                $tip_logo_html = '<img src="' . esc_url($logo_url) . '" class="aura-tip-avatar-large" alt="' . esc_attr($item->area_name) . '">';
            } else {
                if ( class_exists( 'Aura_Financial_Categories' ) ) {
                    $icon_or_logo_html = Aura_Financial_Categories::render_icon_html( $icon, $color );
                    $tip_logo_html = '<div class="aura-tip-avatar-large" style="background:' . esc_attr($color) . '25;border-color:' . esc_attr($color) . '55;color:' . esc_attr($color) . ';font-size:22px;display:inline-flex;align-items:center;justify-content:center;">'
                        . Aura_Financial_Categories::render_icon_html( $icon, $color )
                        . '</div>';
                } elseif ( strpos( $icon, 'dashicons-' ) !== false || strpos( $icon, 'dashicons' ) !== false ) {
                    $icon_or_logo_html = '<span class="dashicons ' . esc_attr( $icon ) . '" style="color:' . esc_attr( $color ) . ';font-size:14px;width:14px;height:14px;line-height:1;"></span>';
                    $tip_logo_html = '<div class="aura-tip-avatar-large" style="background:' . esc_attr($color) . '25;border-color:' . esc_attr($color) . '55;color:' . esc_attr($color) . ';font-size:22px;display:inline-flex;align-items:center;justify-content:center;">'
                        . '<span class="dashicons ' . esc_attr($icon) . '" style="font-size:24px;width:24px;height:24px;"></span>'
                        . '</div>';
                } else {
                    $icon_or_logo_html = '<span class="aura-cat-emoji" style="font-size:13px;line-height:1;">' . esc_html($icon) . '</span>';
                    $tip_logo_html = '<div class="aura-tip-avatar-large" style="background:' . esc_attr($color) . '25;border-color:' . esc_attr($color) . '55;color:' . esc_attr($color) . ';font-size:22px;display:inline-flex;align-items:center;justify-content:center;">'
                        . '<span class="aura-cat-emoji" style="font-size:22px;line-height:1;">' . esc_html($icon ?: '🏢') . '</span>'
                        . '</div>';
                }
            }

            // Rich Card Tooltip
            $tip_html = '<div class="aura-tip-card">'
                . '<div class="aura-tip-card-header">'
                . $tip_logo_html
                . '<div class="aura-tip-info">'
                . '<div class="aura-tip-title" style="color:#ffffff;">' . esc_html($item->area_name) . '</div>'
                . '<div class="aura-tip-subtitle">' . esc_html__('Área / Centro de Costos / Programa', 'aura-suite') . '</div>'
                . '<div class="aura-tip-badges">'
                . '<span class="aura-tip-badge" style="background:' . esc_attr($color) . '30;color:#ffffff;border-color:' . esc_attr($color) . '60;">' . esc_html($item->area_name) . '</span>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '</div>';

            return sprintf(
                '<span class="aura-area-badge aura-has-tooltip" data-tooltip="%s" style="--area-color:%s;cursor:help;display:inline-flex;align-items:center;gap:5px;">'
                . '%s'
                . '<span class="aura-area-badge__name">%s</span>'
                . '</span>',
                esc_attr( $tip_html ),
                esc_attr( $color ),
                $icon_or_logo_html,
                esc_html( $item->area_name )
            );
        }

        $no_area_tip = '<div class="aura-tip-card"><div class="aura-tip-card-body" style="text-align:center;color:#94a3b8;">' . esc_html__('Sin área específica asignada (Transacción General)', 'aura-suite') . '</div></div>';
        return '<span class="aura-has-tooltip" data-tooltip="' . esc_attr( $no_area_tip ) . '" style="color:#94a3b8;font-style:italic;cursor:help;">' . __( 'General', 'aura-suite' ) . '</span>';
    }
    
    /**
     * Columna de descripción con tooltip completo
     */
    public function column_description($item) {
        $description = (string) $item->description;
        $notes       = (string) ($item->notes ?? '');
        
        $tooltip_text = $description;
        if ( $notes ) {
            $tooltip_text .= ' | ' . sprintf( __('Notas: %s', 'aura-suite'), $notes );
        }
        if ( ! empty( $item->reference_number ) ) {
            $tooltip_text .= ' | ' . sprintf( __('Ref: %s', 'aura-suite'), $item->reference_number );
        }

        if ( mb_strlen($description) > 45 ) {
            $truncated = mb_substr($description, 0, 45) . '...';
        } else {
            $truncated = $description ?: '<span style="color:#94a3b8;font-style:italic;">' . __('Sin descripción', 'aura-suite') . '</span>';
        }
        
        return sprintf(
            '<span class="aura-has-tooltip" data-tooltip="%s" style="white-space:normal;word-break:break-word;color:#1e293b;cursor:help;font-weight:500;">%s</span>',
            esc_attr( $tooltip_text ),
            $truncated
        );
    }
    
    /**
     * Columna de monto con formato e indicador de impacto tipo tarjeta
     */
    public function column_amount($item) {
        $type = strtolower( (string) $item->transaction_type );

        if ( $type === 'income' ) {
            $color     = '#059669';
            $sign      = '+';
            $type_desc = __('Ingreso (+) a balance contable', 'aura-suite');
        } elseif ( $type === 'capital' ) {
            $color     = '#7c3aed';
            $sign      = '●';
            $type_desc = __('Gasto de Capital (CapEx / Activo)', 'aura-suite');
        } elseif ( $type === 'transfer' ) {
            $color     = '#2563eb';
            $sign      = '⇄';
            $type_desc = __('Transferencia entre cuentas bancarias', 'aura-suite');
        } else {
            $color     = '#dc2626';
            $sign      = '-';
            $type_desc = __('Egreso (-) de balance contable', 'aura-suite');
        }

        $formatted_amt = number_format((float) $item->amount, 2, '.', ',');
        
        $tip_html = '<div class="aura-tip-card">'
            . '<div class="aura-tip-card-header">'
            . '<div class="aura-tip-avatar-large" style="background:' . esc_attr($color) . '25;border-color:' . esc_attr($color) . '55;color:' . esc_attr($color) . ';font-size:20px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;">'
            . esc_html($sign) . '$'
            . '</div>'
            . '<div class="aura-tip-info">'
            . '<div class="aura-tip-title" style="color:#ffffff;font-size:15px;">' . esc_html($sign) . '$' . esc_html($formatted_amt) . '</div>'
            . '<div class="aura-tip-subtitle">' . esc_html($type_desc) . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
        
        return sprintf(
            '<strong class="aura-has-tooltip" data-tooltip="%s" style="color:%s;font-weight:700;white-space:nowrap;cursor:help;font-size:13px;">%s$%s</strong>',
            esc_attr( $tip_html ),
            esc_attr( $color ),
            esc_html( $sign ),
            $formatted_amt
        );
    }
    
    /**
     * Obtener información de método de pago (icono, color y texto traducido)
     */
    public static function get_payment_method_info( $payment_method ) {
        $raw = strtolower( trim( (string) $payment_method ) );
        
        $map = array(
            'cash'             => array( 'label' => __('Efectivo', 'aura-suite'),               'icon' => 'dashicons-money-alt',  'color' => '#10b981' ),
            'efectivo'         => array( 'label' => __('Efectivo', 'aura-suite'),               'icon' => 'dashicons-money-alt',  'color' => '#10b981' ),
            'transfer'         => array( 'label' => __('Transferencia', 'aura-suite'),          'icon' => 'dashicons-bank',       'color' => '#2563eb' ),
            'transferencia'    => array( 'label' => __('Transferencia', 'aura-suite'),          'icon' => 'dashicons-bank',       'color' => '#2563eb' ),
            'bank_transfer'    => array( 'label' => __('Transferencia Bancaria', 'aura-suite'), 'icon' => 'dashicons-bank',       'color' => '#2563eb' ),
            'card'             => array( 'label' => __('Tarjeta', 'aura-suite'),                'icon' => 'dashicons-id-alt',     'color' => '#8b5cf6' ),
            'tarjeta'          => array( 'label' => __('Tarjeta', 'aura-suite'),                'icon' => 'dashicons-id-alt',     'color' => '#8b5cf6' ),
            'credit_card'      => array( 'label' => __('Tarjeta de Crédito', 'aura-suite'),     'icon' => 'dashicons-id-alt',     'color' => '#8b5cf6' ),
            'debit_card'       => array( 'label' => __('Tarjeta de Débito', 'aura-suite'),      'icon' => 'dashicons-id-alt',     'color' => '#8b5cf6' ),
            'check'            => array( 'label' => __('Cheque', 'aura-suite'),                 'icon' => 'dashicons-media-text', 'color' => '#6366f1' ),
            'cheque'           => array( 'label' => __('Cheque', 'aura-suite'),                 'icon' => 'dashicons-media-text', 'color' => '#6366f1' ),
            'digital_wallet'   => array( 'label' => __('Billetera Digital', 'aura-suite'),      'icon' => 'dashicons-smartphone', 'color' => '#ec4899' ),
            'nequi'            => array( 'label' => __('Nequi', 'aura-suite'),                  'icon' => 'dashicons-smartphone', 'color' => '#ec4899' ),
            'daviplata'        => array( 'label' => __('DaviPlata', 'aura-suite'),              'icon' => 'dashicons-smartphone', 'color' => '#ec4899' ),
            'other'            => array( 'label' => __('Otro', 'aura-suite'),                   'icon' => 'dashicons-ellipsis',   'color' => '#64748b' ),
            'otro'             => array( 'label' => __('Otro', 'aura-suite'),                   'icon' => 'dashicons-ellipsis',   'color' => '#64748b' ),
        );
        
        if ( isset( $map[ $raw ] ) ) {
            return $map[ $raw ];
        }
        
        if ( empty( $raw ) ) {
            return array( 'label' => __('Sin método especificado', 'aura-suite'), 'icon' => 'dashicons-minus', 'color' => '#94a3b8' );
        }
        
        return array( 'label' => ucfirst( $raw ), 'icon' => 'dashicons-money-alt', 'color' => '#64748b' );
    }
    
    /**
     * Columna de método de pago — ícono con tooltip enriquecido tipo tarjeta
     */
    public function column_payment_method( $item ) {
        $raw  = (string) ( $item->payment_method ?? '' );
        $info = self::get_payment_method_info( $raw );
        $ref  = ! empty( $item->reference_number ) ? esc_html( $item->reference_number ) : '';

        // Tooltip tipo tarjeta enriquecida
        $tip_html = '<div class="aura-tip-card">'
            . '<div class="aura-tip-card-header">'
            . '<div class="aura-tip-avatar-large" style="background:' . esc_attr($info['color']) . '22;border-color:' . esc_attr($info['color']) . '55;color:' . esc_attr($info['color']) . ';">'
            . '<span class="dashicons ' . esc_attr($info['icon']) . '" style="font-size:24px;width:24px;height:24px;"></span>'
            . '</div>'
            . '<div class="aura-tip-info">'
            . '<div class="aura-tip-title">' . esc_html($info['label']) . '</div>'
            . '<div class="aura-tip-subtitle">' . esc_html__('Método de Pago Utilizado', 'aura-suite') . '</div>'
            . '</div>'
            . '</div>';

        if ( ! empty($ref) ) {
            $tip_html .= '<div class="aura-tip-card-body">'
                . '<div class="aura-tip-row"><span>🔖 N° Referencia / Comp:</span><strong>' . $ref . '</strong></div>'
                . '</div>';
        }
        $tip_html .= '</div>';

        return sprintf(
            '<span class="aura-payment-method-pill aura-has-tooltip" data-tooltip="%s" style="background:%s18;border:1px solid %s35;color:%s;" title="%s">'
            . '<span class="dashicons %s" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>'
            . '</span>',
            esc_attr( $tip_html ),
            esc_attr( $info['color'] ),
            esc_attr( $info['color'] ),
            esc_attr( $info['color'] ),
            esc_attr( $info['label'] ),
            esc_attr( $info['icon'] )
        );
    }
    
    /**
     * Columna: Usuario / Tercero / Empresa Vinculado — UX/UI tipo tarjeta enriquecida con avatar ampliado
     */
    public function column_related_user( $item ) {
        // 1. Si tiene Tercero / Empresa / Tienda / Fundación vinculada directamente
        if ( ! empty( $item->third_party_id ) && ( ! empty( $item->tp_full_name ) || ! empty( $item->tp_commercial_name ) ) ) {
            $ptype = $item->tp_party_type ?: 'company';
            $type_labels = array(
                'company'                 => __('Empresa / Sociedad', 'aura-suite'),
                'store'                   => __('Tienda / Comercio', 'aura-suite'),
                'organization_foundation' => __('Fundación / ONG', 'aura-suite'),
                'person'                  => __('Persona Natural', 'aura-suite'),
                'religious'               => __('Entidad Religiosa', 'aura-suite'),
                'other'                   => __('Otra Entidad', 'aura-suite'),
            );
            $type_icons = array(
                'company'                 => 'dashicons-building',
                'store'                   => 'dashicons-cart',
                'organization_foundation' => 'dashicons-heart',
                'person'                  => 'dashicons-businessman',
                'religious'               => 'dashicons-location-alt',
                'other'                   => 'dashicons-category',
            );
            $type_emojis = array(
                'company'                 => '🏢',
                'store'                   => '🛒',
                'organization_foundation' => '🏛️',
                'person'                  => '👤',
                'religious'               => '⛪',
                'other'                   => '🏷️',
            );
            $type_badges = array(
                'company'                 => 'aura-tip-badge--company',
                'store'                   => 'aura-tip-badge--store',
                'organization_foundation' => 'aura-tip-badge--foundation',
                'person'                  => 'aura-tip-badge--person',
                'religious'               => 'aura-tip-badge--religious',
                'other'                   => 'aura-tip-badge--other',
            );

            $type_label = $type_labels[$ptype] ?? $type_labels['company'];
            $type_icon  = $type_icons[$ptype] ?? 'dashicons-building';
            $type_emoji = $type_emojis[$ptype] ?? '🏢';
            $type_badge = $type_badges[$ptype] ?? 'aura-tip-badge--company';

            $main_name   = !empty($item->tp_commercial_name) ? $item->tp_commercial_name : ( !empty($item->tp_full_name) ? $item->tp_full_name : $item->recipient_payer );
            $legal_name  = !empty($item->tp_full_name) ? $item->tp_full_name : '';
            $doc_type    = $item->tp_tax_id_type ?: 'NIT';
            $doc_id      = $item->tp_document_id ?: '';
            $phone       = $item->tp_phone ?? '';
            $email       = $item->tp_email ?? '';
            $role_slug   = $item->tp_accounting_role ?? 'supplier';

            $roles_map = class_exists('Aura_Third_Parties') ? Aura_Third_Parties::get_accounting_roles() : array();
            $role_label = $roles_map[$role_slug]['label'] ?? ucfirst($role_slug);

            $logo_id = (int) ( $item->tp_logo_id ?? 0 );
            $logo_url = $logo_id ? ( wp_get_attachment_image_url( $logo_id, 'medium' ) ?: wp_get_attachment_url( $logo_id ) ?: '' ) : '';

            // Tooltip Card Enriquecida
            $avatar_tip_html = $logo_url
                ? '<img src="' . esc_url($logo_url) . '" class="aura-tip-avatar-large" alt="' . esc_attr($main_name) . '">'
                : '<div class="aura-tip-avatar-large is-emoji" style="display:flex;align-items:center;justify-content:center;font-size:24px;line-height:1;"><span class="aura-tp-emoji">' . esc_html($type_emoji) . '</span></div>';

            $tip_html = '<div class="aura-tip-card">'
                . '<div class="aura-tip-card-header">'
                . $avatar_tip_html
                . '<div class="aura-tip-info">'
                . '<div class="aura-tip-title">' . esc_html($main_name) . '</div>'
                . ( ($legal_name && $legal_name !== $main_name) ? '<div class="aura-tip-subtitle">' . esc_html($legal_name) . '</div>' : '' )
                . '<div class="aura-tip-badges">'
                . '<span class="aura-tip-badge ' . esc_attr($type_badge) . '"><span class="aura-tp-pill-emoji" style="margin-right:4px;">' . esc_html($type_emoji) . '</span> ' . esc_html($type_label) . '</span>'
                . '<span class="aura-tip-badge aura-tip-badge--role">' . esc_html($role_label) . '</span>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '<div class="aura-tip-card-body">';

            if ( $doc_id ) {
                $tip_html .= '<div class="aura-tip-row"><span>📄 ' . esc_html($doc_type) . ':</span><strong>' . esc_html($doc_id) . '</strong></div>';
            }
            if ( $email ) {
                $tip_html .= '<div class="aura-tip-row"><span>✉️ Correo:</span><strong>' . esc_html($email) . '</strong></div>';
            }
            if ( $phone ) {
                $tip_html .= '<div class="aura-tip-row"><span>📞 Teléfono:</span><strong>' . esc_html($phone) . '</strong></div>';
            }
            if ( ! empty($item->related_user_concept) ) {
                $concepts = array(
                    'payment_to_user'       => __('Pago a usuario', 'aura-suite'),
                    'charge_to_user'        => __('Cobro a usuario', 'aura-suite'),
                    'salary'                => __('Pago de Salario / Nómina', 'aura-suite'),
                    'scholarship'           => __('Beca Asignada', 'aura-suite'),
                    'loan_payment'          => __('Pago de Préstamo', 'aura-suite'),
                    'refund'                => __('Reembolso', 'aura-suite'),
                    'expense_reimbursement' => __('Reembolso de Gastos', 'aura-suite'),
                );
                $concept_lbl = $concepts[$item->related_user_concept] ?? ucfirst($item->related_user_concept);
                $tip_html .= '<div class="aura-tip-row"><span>📌 Concepto:</span><strong>' . esc_html($concept_lbl) . '</strong></div>';
            }
            $tip_html .= '</div></div>';

            $pill_avatar = $logo_url
                ? sprintf(
                    '<span class="aura-user-avatar-wrap is-third-party-logo">'
                    . '<img src="%s" class="aura-avatar-img" alt="%s" onerror="this.style.display=\'none\'; if(this.nextElementSibling){ this.nextElementSibling.style.display=\'inline-flex\'; }">'
                    . '<span class="aura-avatar-fallback is-emoji" style="display:none;font-size:13px;line-height:1;align-items:center;justify-content:center;">%s</span>'
                    . '</span>',
                    esc_url( $logo_url ),
                    esc_attr( $main_name ),
                    esc_html( $type_emoji )
                )
                : sprintf(
                    '<span class="aura-user-avatar-wrap is-third-party is-emoji" style="font-size:13px;line-height:1;display:inline-flex;align-items:center;justify-content:center;">%s</span>',
                    esc_html( $type_emoji )
                );

            return sprintf(
                '<span class="aura-related-user-pill is-third-party is-party-%s aura-has-tooltip" data-tooltip="%s">'
                . '%s'
                . '<span class="aura-user-name-label">%s</span>'
                . '</span>',
                esc_attr( $ptype ),
                esc_attr( $tip_html ),
                $pill_avatar,
                esc_html( $main_name )
            );
        }

        // 2. Si tiene Usuario del Sistema (WordPress) vinculado
        if ( ! empty( $item->related_user_id ) ) {
            $user = get_userdata( $item->related_user_id );
            if ( $user ) {
                $concepts = array(
                    'payment_to_user'       => __('Pago a usuario', 'aura-suite'),
                    'charge_to_user'        => __('Cobro a usuario', 'aura-suite'),
                    'salary'                => __('Pago de Salario / Nómina', 'aura-suite'),
                    'scholarship'           => __('Beca Asignada', 'aura-suite'),
                    'loan_payment'          => __('Pago de Préstamo', 'aura-suite'),
                    'refund'                => __('Reembolso', 'aura-suite'),
                    'expense_reimbursement' => __('Reembolso de Gastos', 'aura-suite'),
                );
                $concept_label = ! empty( $item->related_user_concept )
                    ? ( $concepts[ $item->related_user_concept ] ?? ucfirst($item->related_user_concept) )
                    : '';

                global $wp_roles;
                $roles_list = array();
                foreach ( $user->roles as $role ) {
                    $roles_list[] = isset( $wp_roles->roles[ $role ] )
                        ? translate_user_role( $wp_roles->roles[ $role ]['name'] )
                        : ucfirst($role);
                }
                $role_str = implode( ', ', $roles_list );

                $avatar_url = get_avatar_url( $item->related_user_id, array( 'size' => 96, 'default' => '404' ) );
                $palette    = ['#2563eb', '#7c3aed', '#059669', '#d97706', '#dc2626', '#0891b2'];
                $bg_color   = $palette[ $item->related_user_id % count($palette) ];
                $initial    = mb_strtoupper( mb_substr( $user->display_name ?: $user->user_login, 0, 1, 'UTF-8' ), 'UTF-8' );

                $avatar_tip_html = sprintf(
                    '<img src="%s" class="aura-tip-avatar-large" alt="%s" onerror="this.style.display=\'none\'; if(this.nextElementSibling){ this.nextElementSibling.style.display=\'flex\'; }">'
                    . '<div class="aura-tip-avatar-large" style="display:none;background:%s;color:#fff;">%s</div>',
                    esc_url( $avatar_url ),
                    esc_attr( $user->display_name ),
                    esc_attr( $bg_color ),
                    esc_html( $initial )
                );

                $tip_html = '<div class="aura-tip-card">'
                    . '<div class="aura-tip-card-header">'
                    . $avatar_tip_html
                    . '<div class="aura-tip-info">'
                    . '<div class="aura-tip-title">' . esc_html($user->display_name) . '</div>'
                    . '<div class="aura-tip-subtitle">@' . esc_html($user->user_login) . '</div>'
                    . '<div class="aura-tip-badges">'
                    . '<span class="aura-tip-badge aura-tip-badge--user"><span class="dashicons dashicons-admin-users" style="font-size:12px;width:12px;height:12px;line-height:12px;"></span> ' . esc_html__('Usuario Sistema', 'aura-suite') . '</span>'
                    . ( $role_str ? '<span class="aura-tip-badge aura-tip-badge--role">' . esc_html($role_str) . '</span>' : '' )
                    . '</div>'
                    . '</div>'
                    . '</div>'
                    . '<div class="aura-tip-card-body">';

                if ( $user->user_email ) {
                    $tip_html .= '<div class="aura-tip-row"><span>✉️ Correo:</span><strong>' . esc_html($user->user_email) . '</strong></div>';
                }
                if ( $concept_label ) {
                    $tip_html .= '<div class="aura-tip-row"><span>📌 Concepto:</span><strong>' . esc_html($concept_label) . '</strong></div>';
                }
                $tip_html .= '</div></div>';

                return sprintf(
                    '<span class="aura-related-user-pill aura-has-tooltip" data-tooltip="%s">'
                    . '<span class="aura-user-avatar-wrap" data-bg="%s">'
                    . '<img src="%s" class="aura-avatar-img" alt="%s" onerror="this.style.display=\'none\'; if(this.nextElementSibling){ this.nextElementSibling.style.display=\'inline-flex\'; } this.parentElement.style.backgroundColor=this.parentElement.dataset.bg;">'
                    . '<span class="aura-avatar-fallback" style="display:none;">%s</span>'
                    . '</span>'
                    . '<span class="aura-user-name-label">%s</span>'
                    . '</span>',
                    esc_attr( $tip_html ),
                    esc_attr( $bg_color ),
                    esc_url( $avatar_url ),
                    esc_attr( $user->display_name ),
                    esc_html( $initial ),
                    esc_html( $user->display_name )
                );
            }
        }

        // 3. Fallback a texto libre de recipient_payer
        if ( ! empty( $item->recipient_payer ) ) {
            $tip_html = '<div class="aura-tip-card">'
                . '<div class="aura-tip-card-header">'
                . '<div class="aura-tip-avatar-large"><span class="dashicons dashicons-businessman" style="font-size:24px;width:24px;height:24px;"></span></div>'
                . '<div class="aura-tip-info">'
                . '<div class="aura-tip-title">' . esc_html($item->recipient_payer) . '</div>'
                . '<div class="aura-tip-subtitle">' . esc_html__('Tercero / Beneficiario registrado libremente', 'aura-suite') . '</div>'
                . '</div>'
                . '</div></div>';

            return sprintf(
                '<span class="aura-related-user-pill is-third-party aura-has-tooltip" data-tooltip="%s">'
                . '<span class="aura-user-avatar-wrap is-third-party">'
                . '<span class="dashicons dashicons-businessman"></span>'
                . '</span>'
                . '<span class="aura-user-name-label">%s</span>'
                . '</span>',
                esc_attr( $tip_html ),
                esc_html( $item->recipient_payer )
            );
        }
        $no_tip = '<div class="aura-tip-card"><div class="aura-tip-card-body" style="text-align:center;color:#94a3b8;">' . esc_html__( 'Sin usuario ni tercero vinculado', 'aura-suite' ) . '</div></div>';
        return '<span class="aura-unlinked-pill aura-has-tooltip" data-tooltip="' . esc_attr( $no_tip ) . '"><span class="dashicons dashicons-minus"></span></span>';
    }

    /**
     * Columna de creado por — avatar compacto con inicial fallback y tooltip enriquecido tipo tarjeta
     */
    public function column_created_by( $item ) {
        $user = get_userdata( $item->created_by );
        if ( $user ) {
            global $wp_roles;
            $roles_list = array();
            foreach ( $user->roles as $role ) {
                $roles_list[] = isset( $wp_roles->roles[ $role ] )
                    ? translate_user_role( $wp_roles->roles[ $role ]['name'] )
                    : ucfirst($role);
            }
            $role_str = implode( ', ', $roles_list );

            $avatar_url = get_avatar_url( $item->created_by, array( 'size' => 96, 'default' => '404' ) );
            $palette    = ['#475569', '#6366f1', '#059669', '#d97706', '#2563eb', '#0891b2'];
            $bg_color   = $palette[ $item->created_by % count($palette) ];
            $initial    = mb_strtoupper( mb_substr( $user->display_name ?: $user->user_login, 0, 1, 'UTF-8' ), 'UTF-8' );

            $avatar_tip_html = sprintf(
                '<img src="%s" class="aura-tip-avatar-large" alt="%s" onerror="this.style.display=\'none\'; if(this.nextElementSibling){ this.nextElementSibling.style.display=\'flex\'; }">'
                . '<div class="aura-tip-avatar-large" style="display:none;background:%s;color:#fff;">%s</div>',
                esc_url( $avatar_url ),
                esc_attr( $user->display_name ),
                esc_attr( $bg_color ),
                esc_html( $initial )
            );

            $tip_html = '<div class="aura-tip-card">'
                . '<div class="aura-tip-card-header">'
                . $avatar_tip_html
                . '<div class="aura-tip-info">'
                . '<div class="aura-tip-title">' . esc_html($user->display_name) . '</div>'
                . '<div class="aura-tip-subtitle">@' . esc_html($user->user_login) . '</div>'
                . '<div class="aura-tip-badges">'
                . '<span class="aura-tip-badge aura-tip-badge--role">' . esc_html($role_str ?: __('Usuario', 'aura-suite')) . '</span>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '<div class="aura-tip-card-body">';

            if ( $user->user_email ) {
                $tip_html .= '<div class="aura-tip-row"><span>✉️ Correo:</span><strong>' . esc_html($user->user_email) . '</strong></div>';
            }
            if ( ! empty($item->created_at) ) {
                $created_date = new DateTime($item->created_at);
                $tip_html .= '<div class="aura-tip-row"><span>🕒 Creado el:</span><strong>' . esc_html($created_date->format('d/m/Y h:i A')) . '</strong></div>';
            }
            $tip_html .= '</div></div>';

            return sprintf(
                '<span class="aura-related-user-pill is-created-by aura-has-tooltip" data-tooltip="%s">'
                . '<span class="aura-user-avatar-wrap" data-bg="%s">'
                . '<img src="%s" class="aura-avatar-img" alt="%s" onerror="this.style.display=\'none\'; if(this.nextElementSibling){ this.nextElementSibling.style.display=\'inline-flex\'; } this.parentElement.style.backgroundColor=this.parentElement.dataset.bg;">'
                . '<span class="aura-avatar-fallback" style="display:none;">%s</span>'
                . '</span>'
                . '<span class="aura-user-name-label">%s</span>'
                . '</span>',
                esc_attr( $tip_html ),
                esc_attr( $bg_color ),
                esc_url( $avatar_url ),
                esc_attr( $user->display_name ),
                esc_html( $initial ),
                esc_html( $user->display_name )
            );
        }
        $no_tip = '<div class="aura-tip-card"><div class="aura-tip-card-body" style="text-align:center;color:#94a3b8;">' . esc_html__( 'Sistema', 'aura-suite' ) . '</div></div>';
        return '<span class="aura-unlinked-pill aura-has-tooltip" data-tooltip="' . esc_attr( $no_tip ) . '"><span class="dashicons dashicons-admin-generic"></span></span>';
    }
    
    /**
     * Columna de acciones — íconos con tooltips flotantes
     */
    public function column_actions( $item ) {
        $current_uid = get_current_user_id();
        $is_owner    = ( (int) $item->created_by === $current_uid );
        $btns        = array();

        // ── Ver detalle ──────────────────────────────────────────
        if ( current_user_can('aura_finance_view_all') ||
             ( current_user_can('aura_finance_view_own') && $is_owner ) ) {
            $btns[] = sprintf(
                '<button type="button" class="aura-icon-btn view-transaction aura-has-tooltip" data-transaction-id="%d" data-tooltip="%s" aria-label="%s">'
                . '<span class="dashicons dashicons-visibility"></span></button>',
                $item->id,
                esc_attr__('Ver detalles completos', 'aura-suite'),
                esc_attr__('Ver detalles', 'aura-suite')
            );
        }

        // ── Editar ───────────────────────────────────────────────
        if ( current_user_can('aura_finance_edit_all') ||
             ( current_user_can('aura_finance_edit_own') && $is_owner && in_array( $item->status, array('pending','rejected') ) ) ) {
            $is_rejected  = ( $item->status === 'rejected' );
            $edit_title   = $is_rejected
                ? sprintf( __('Corregir y reenviar (Motivo: %s)', 'aura-suite'), $item->rejection_reason ?? __('N/D', 'aura-suite') )
                : __('Editar transacción', 'aura-suite');
            $edit_icon    = $is_rejected ? 'dashicons-update' : 'dashicons-edit';
            $edit_color   = $is_rejected ? '#f59e0b' : '#3b82f6';
            $btns[] = sprintf(
                '<a href="%s" class="aura-icon-btn aura-has-tooltip" data-tooltip="%s" aria-label="%s" style="color:%s;">'
                . '<span class="dashicons %s"></span></a>',
                esc_url( admin_url('admin.php?page=aura-financial-edit-transaction&id=' . $item->id) ),
                esc_attr( $edit_title ),
                esc_attr( $edit_title ),
                esc_attr( $edit_color ),
                esc_attr( $edit_icon )
            );
        }

        // ── Aprobar ──────────────────────────────────────────────
        if ( current_user_can('aura_finance_approve') && $item->status === 'pending' ) {
            $btns[] = sprintf(
                '<a href="#" class="aura-icon-btn aura-quick-approve aura-has-tooltip" data-id="%d" data-tooltip="%s" aria-label="%s" style="color:#10b981;">'
                . '<span class="dashicons dashicons-yes-alt"></span></a>',
                $item->id,
                esc_attr__('Aprobar transacción', 'aura-suite'),
                esc_attr__('Aprobar', 'aura-suite')
            );

            // ── Rechazar ─────────────────────────────────────────
            $btns[] = sprintf(
                '<a href="#" class="aura-icon-btn aura-quick-reject aura-has-tooltip" data-id="%d" data-tooltip="%s" aria-label="%s" style="color:#ef4444;">'
                . '<span class="dashicons dashicons-dismiss"></span></a>',
                $item->id,
                esc_attr__('Rechazar transacción', 'aura-suite'),
                esc_attr__('Rechazar', 'aura-suite')
            );
        }

        // ── Eliminar ─────────────────────────────────────────────
        if ( current_user_can('aura_finance_delete_all') ||
             ( current_user_can('aura_finance_delete_own') && $is_owner ) ) {
            $btns[] = sprintf(
                '<a href="#" class="aura-icon-btn aura-delete-transaction aura-has-tooltip" data-id="%d" data-tooltip="%s" aria-label="%s" style="color:#ef4444;">'
                . '<span class="dashicons dashicons-trash"></span></a>',
                $item->id,
                esc_attr__('Eliminar transacción', 'aura-suite'),
                esc_attr__('Eliminar', 'aura-suite')
            );
        }

        return '<div class="aura-transaction-actions-row aura-row-actions-icons" style="display:inline-flex;align-items:center;gap:4px;">' . implode( '', $btns ) . '</div>';
    }
    
    /**
     * Acciones en bloque
     */
    
    /**
     * Preparar items para la tabla
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = $this->get_items_per_page('transactions_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Construir WHERE clause con filtros
        // IMPORTANTE: Calificar todos los campos con t. para evitar ambigüedad en LEFT JOINs
        $where_clauses = array('t.deleted_at IS NULL');
        $where_values = array();
        
        // Filtro por tipo
        if (!empty($_REQUEST['filter_type'])) {
            $types = is_array($_REQUEST['filter_type']) ? $_REQUEST['filter_type'] : array($_REQUEST['filter_type']);
            $types = array_filter(array_map('sanitize_text_field', $types));
            if (!empty($types)) {
                $placeholders = implode(',', array_fill(0, count($types), '%s'));
                $where_clauses[] = "t.transaction_type IN ($placeholders)";
                foreach ($types as $stype) {
                    $where_values[] = $stype;
                }
            }
        }
        
        // Filtro por estado
        if (!empty($_REQUEST['filter_status'])) {
            $statuses = is_array($_REQUEST['filter_status']) ? $_REQUEST['filter_status'] : array($_REQUEST['filter_status']);
            $statuses = array_filter(array_map('sanitize_text_field', $statuses));
            if (!empty($statuses)) {
                $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
                $where_clauses[] = "t.status IN ($placeholders)";
                foreach ($statuses as $sstatus) {
                    $where_values[] = $sstatus;
                }
            }
        }
        
        // Filtro por categoría (admite filter_category y category_id, e incluye category_id, expense_category_id y subcategorías)
        $filter_cat = !empty($_REQUEST['filter_category']) ? intval($_REQUEST['filter_category']) : (!empty($_REQUEST['category_id']) ? intval($_REQUEST['category_id']) : 0);
        if ($filter_cat > 0) {
            $cat_table = $wpdb->prefix . 'aura_finance_categories';
            $subcats = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$cat_table} WHERE parent_id = %d",
                $filter_cat
            ));
            $all_cat_ids = array_merge(array($filter_cat), array_map('intval', (array) $subcats));
            $cat_placeholders = implode(',', array_fill(0, count($all_cat_ids), '%d'));
            
            $has_expense_cat = (bool) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'expense_category_id'",
                    DB_NAME,
                    $table_name
                )
            );
            
            if ($has_expense_cat) {
                $where_clauses[] = "(t.category_id IN ($cat_placeholders) OR t.expense_category_id IN ($cat_placeholders))";
                foreach ($all_cat_ids as $cid) {
                    $where_values[] = $cid;
                }
                foreach ($all_cat_ids as $cid) {
                    $where_values[] = $cid;
                }
            } else {
                $where_clauses[] = "t.category_id IN ($cat_placeholders)";
                foreach ($all_cat_ids as $cid) {
                    $where_values[] = $cid;
                }
            }
        }

        // Filtro por método de pago
        if (!empty($_REQUEST['filter_payment_method'])) {
            $where_clauses[] = 't.payment_method = %s';
            $where_values[] = sanitize_text_field($_REQUEST['filter_payment_method']);
        }
        
        // Filtro por rango de fechas
        if (!empty($_REQUEST['filter_date_from'])) {
            $where_clauses[] = 't.transaction_date >= %s';
            $where_values[] = sanitize_text_field($_REQUEST['filter_date_from']);
        }
        if (!empty($_REQUEST['filter_date_to'])) {
            $where_clauses[] = 't.transaction_date <= %s';
            $where_values[] = sanitize_text_field($_REQUEST['filter_date_to']);
        }
        
        // Filtro por rango de monto
        if (!empty($_REQUEST['filter_amount_min'])) {
            $where_clauses[] = 't.amount >= %f';
            $where_values[] = floatval($_REQUEST['filter_amount_min']);
        }
        if (!empty($_REQUEST['filter_amount_max'])) {
            $where_clauses[] = 't.amount <= %f';
            $where_values[] = floatval($_REQUEST['filter_amount_max']);
        }
        
        // Filtro por creador (manejo de permisos)
        if (!empty($_REQUEST['filter_user']) && current_user_can('aura_finance_view_all')) {
            // Admin/Contador pueden filtrar por cualquier usuario
            $where_clauses[] = 't.created_by = %d';
            $where_values[] = intval($_REQUEST['filter_user']);
        } elseif (current_user_can('aura_finance_view_own') && !current_user_can('aura_finance_view_all')) {
            // Usuario con permiso view_own solo ve sus propias transacciones
            $where_clauses[] = 't.created_by = %d';
            $where_values[] = get_current_user_id();
        }

        // Fase 6, Item 6.1: Filtro por usuario vinculado (related_user_id)
        if ( ! empty( $_REQUEST['filter_related_user'] )
             && ( current_user_can( 'aura_finance_view_all' ) || current_user_can( 'aura_finance_user_ledger' ) ) ) {
            $where_clauses[] = 't.related_user_id = %d';
            $where_values[]  = intval( $_REQUEST['filter_related_user'] );
        }

        // Filtro y scoping por áreas asignadas al usuario (Soporte Multi-Área)
        if ( current_user_can( 'aura_areas_view_own' )
             && ! current_user_can( 'aura_areas_view_all' )
             && ! current_user_can( 'manage_options' ) ) {
            $user_area_ids = Aura_Areas_Setup::get_user_area_ids( get_current_user_id() );
            if ( ! empty( $user_area_ids ) ) {
                if ( ! empty( $_REQUEST['filter_area'] ) && in_array( intval( $_REQUEST['filter_area'] ), $user_area_ids, true ) ) {
                    $where_clauses[] = 't.area_id = %d';
                    $where_values[]  = intval( $_REQUEST['filter_area'] );
                } else {
                    $placeholders    = implode( ',', array_fill( 0, count( $user_area_ids ), '%d' ) );
                    $where_clauses[] = "t.area_id IN ($placeholders)";
                    foreach ( $user_area_ids as $aid ) {
                        $where_values[] = $aid;
                    }
                }
            } else {
                // Usuario restringido a áreas pero sin áreas asignadas
                $where_clauses[] = '1 = 0';
            }
        } elseif ( ! empty( $_REQUEST['filter_area'] ) && ( current_user_can( 'aura_finance_view_all' ) || current_user_can( 'manage_options' ) ) ) {
            $where_clauses[] = 't.area_id = %d';
            $where_values[]  = intval( $_REQUEST['filter_area'] );
        }
        
        // Búsqueda global
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_clauses[] = '(t.description LIKE %s OR t.notes LIKE %s OR t.reference_number LIKE %s OR t.recipient_payer LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }
        
        // Construir WHERE final
        $where_sql = implode(' AND ', $where_clauses);
        if (!empty($where_values)) {
            $where_sql = $wpdb->prepare($where_sql, $where_values);
        }
        
        // Ordenamiento con validación segura
        $allowed_orderby = array('id', 'transaction_date', 'amount', 'category_id', 'status', 'created_by', 'transaction_type');
        $raw_orderby     = !empty($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'transaction_date';
        $orderby         = in_array($raw_orderby, $allowed_orderby, true) ? $raw_orderby : 'transaction_date';
        $order           = (!empty($_REQUEST['order']) && strtoupper($_REQUEST['order']) === 'ASC') ? 'ASC' : 'DESC';
        
        $table_name  = $wpdb->prefix . 'aura_finance_transactions';
        $cat_table   = $wpdb->prefix . 'aura_finance_categories';
        $area_table  = $wpdb->prefix . 'aura_areas';
        $acc_table   = $wpdb->prefix . 'aura_finance_accounts';
        $tp_table    = $wpdb->prefix . 'aura_finance_third_parties';

        // Obtener total de items
        $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} t WHERE {$where_sql}");

        // Obtener items con JOIN a categorías (incluyendo padre), cuentas, áreas y terceros (evita N+1 queries)
        $limit_offset = sprintf('LIMIT %d OFFSET %d', max(1, intval($per_page)), max(0, intval($offset)));
        $this->items = $wpdb->get_results(
            "SELECT t.*,
                    COALESCE(ec.name, c.name)               AS category_name,
                    COALESCE(ec.color, c.color)             AS category_color,
                    COALESCE(ec.icon, c.icon)               AS category_icon,
                    COALESCE(ec.slug, c.slug)               AS category_slug,
                    COALESCE(ec.type, c.type)               AS category_type,
                    COALESCE(ec.is_capex, c.is_capex)       AS category_is_capex,
                    COALESCE(ec.description, c.description) AS category_description,
                    COALESCE(ec.parent_id, c.parent_id)     AS category_parent_id,
                    pc.name                                 AS parent_category_name,
                    pc.slug                                 AS parent_category_slug,
                    pc.icon                                 AS parent_category_icon,
                    pc.color                                AS parent_category_color,
                    a.name                                  AS area_name,
                    a.slug                                  AS area_slug,
                    a.type                                  AS area_type,
                    a.description                           AS area_description,
                    a.color                                 AS area_color,
                    a.icon                                  AS area_icon,
                    a.logo_id                               AS area_logo_id,
                    sa.name                                 AS source_account_name,
                    sa.currency                             AS source_account_currency,
                    sa.account_type                         AS source_account_type,
                    sa.institution                          AS source_account_institution,
                    da.name                                 AS destination_account_name,
                    da.currency                             AS destination_account_currency,
                    da.account_type                         AS destination_account_type,
                    da.institution                          AS destination_account_institution,
                    tp.full_name                            AS tp_full_name,
                    tp.commercial_name                      AS tp_commercial_name,
                    tp.party_type                           AS tp_party_type,
                    tp.accounting_role                      AS tp_accounting_role,
                    tp.tax_id_type                          AS tp_tax_id_type,
                    tp.document_id                          AS tp_document_id,
                    tp.phone                                AS tp_phone,
                    tp.email                                AS tp_email,
                    tp.website                              AS tp_website,
                    tp.address                              AS tp_address,
                    tp.notes                                AS tp_notes,
                    tp.logo_id                              AS tp_logo_id
             FROM {$table_name} t
             LEFT JOIN {$cat_table}  c   ON c.id = t.category_id
             LEFT JOIN {$cat_table}  ec  ON ec.id = t.expense_category_id
             LEFT JOIN {$cat_table}  pc  ON pc.id = COALESCE(ec.parent_id, c.parent_id)
             LEFT JOIN {$area_table} a   ON a.id = t.area_id
             LEFT JOIN {$acc_table}  sa  ON sa.id = t.source_account_id
             LEFT JOIN {$acc_table}  da  ON da.id = t.destination_account_id
             LEFT JOIN {$tp_table}   tp  ON tp.id = t.third_party_id
             WHERE {$where_sql}
             ORDER BY t.{$orderby} {$order}
             {$limit_offset}"
        );
        
        // Calcular estadísticas
        $this->calculate_stats($where_sql, $table_name);
        
        // Configurar paginación
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Headers de columnas
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
    }
    
    /**
     * Calcular estadísticas de transacciones filtradas
     */
    private function calculate_stats($where_sql, $table_name = '') {
        global $wpdb;

        if ( empty( $table_name ) ) {
            $table_name = $wpdb->prefix . 'aura_finance_transactions';
        }
        
        // Solo se contabilizan transacciones aprobadas en los totales
        // (pending y rejected no afectan la situación financiera real).
        $stats = $wpdb->get_row("
            SELECT 
                SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expense,
                SUM(CASE WHEN transaction_type = 'capital' THEN amount ELSE 0 END) as total_capital,
                COUNT(*) as count
            FROM $table_name t
            WHERE $where_sql
              AND t.status NOT IN ('pending', 'rejected')
        ");
        
        if ($stats) {
            $this->stats['total_income'] = floatval($stats->total_income);
            $this->stats['total_expense'] = floatval($stats->total_expense);
            $this->stats['total_capital'] = floatval($stats->total_capital);
            $this->stats['balance'] = $this->stats['total_income'] - $this->stats['total_expense'];
            $this->stats['count'] = intval($stats->count);
        }
    }
    
    /**
     * Obtener estadísticas
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * AJAX: Filtrar transacciones
     */
    public static function ajax_filter_transactions() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        // Los filtros se pasan como parámetros GET
        // Redirigir a la página con los filtros
        $filters = array();
        $allowed_filters = array(
            'filter_type', 'filter_status', 'filter_category',
            'filter_date_from', 'filter_date_to',
            'filter_amount_min', 'filter_amount_max',
            'filter_user', 'filter_payment_method',
            'filter_related_user', 'filter_area',
        );
        
        foreach ($allowed_filters as $filter) {
            if (!empty($_POST[$filter])) {
                $filters[$filter] = sanitize_text_field($_POST[$filter]);
            }
        }
        
        wp_send_json_success(array(
            'redirect_url' => add_query_arg($filters, admin_url('admin.php?page=aura-financial-transactions'))
        ));
    }
    
    /**
     * AJAX: Búsqueda en tiempo real
     */
    public static function ajax_search_transactions() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        global $wpdb;
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        
        if (empty($search_term)) {
            wp_send_json_success(array('results' => array()));
        }
        
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT id, transaction_type, description, amount, transaction_date
            FROM $table_name
            WHERE deleted_at IS NULL
            AND (description LIKE %s OR notes LIKE %s OR reference_number LIKE %s)
            ORDER BY transaction_date DESC
            LIMIT 10
        ", $search, $search, $search));
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * AJAX: Aprobar rápidamente
     */
    public static function ajax_quick_approve() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array('message' => __('No tienes permisos', 'aura-suite')));
        }
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        
        if ($transaction_id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido', 'aura-suite')));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'aura_finance_transactions',
            array(
                'status' => 'approved',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql')
            ),
            array('id' => $transaction_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Error al aprobar', 'aura-suite')));
        }
        
        do_action('aura_finance_transaction_approved', $transaction_id);
        
        wp_send_json_success(array('message' => __('Transacción aprobada', 'aura-suite')));
    }
    
    /**
     * AJAX: Rechazar rápidamente
     */
    public static function ajax_quick_reject() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        if (!current_user_can('aura_finance_approve')) {
            wp_send_json_error(array('message' => __('No tienes permisos', 'aura-suite')));
        }
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if ($transaction_id <= 0) {
            wp_send_json_error(array('message' => __('ID inválido', 'aura-suite')));
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'aura_finance_transactions',
            array(
                'status' => 'rejected',
                'rejection_reason' => $reason
            ),
            array('id' => $transaction_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Error al rechazar', 'aura-suite')));
        }
        
        do_action('aura_finance_transaction_rejected', $transaction_id, $reason);
        
        wp_send_json_success(array('message' => __('Transacción rechazada', 'aura-suite')));
    }
    
    /**
     * AJAX: Acciones masivas
     */
    public static function ajax_bulk_action() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        $transaction_ids = array_map('intval', $_POST['transaction_ids'] ?? array());
        
        if (empty($transaction_ids)) {
            wp_send_json_error(array('message' => __('No se seleccionaron transacciones', 'aura-suite')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aura_finance_transactions';
        
        switch ($action) {
            case 'bulk_edit':
                $can_bulk = current_user_can('aura_finance_bulk_edit') || current_user_can('manage_options');
                if (!$can_bulk) {
                    wp_send_json_error(array('message' => __('No tienes permisos para la edición masiva de transacciones.', 'aura-suite')));
                }

                $current_user_id = get_current_user_id();
                $update_sets = array();
                $update_vals = array();
                $history_logs = array();

                // 1. Área / Programa
                if (!empty($_POST['edit_area'])) {
                    $new_area_id = intval($_POST['new_area_id'] ?? 0);
                    $update_sets[] = "area_id = %d";
                    $update_vals[] = $new_area_id;

                    $area_name = __('General (sin área)', 'aura-suite');
                    if ($new_area_id > 0) {
                        $a_table = $wpdb->prefix . 'aura_areas';
                        $area_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$a_table} WHERE id = %d", $new_area_id)) ?: sprintf(__('Área #%d', 'aura-suite'), $new_area_id);
                    }
                    $history_logs[] = array(
                        'field'  => 'area_id',
                        'value'  => (string) $new_area_id,
                        'reason' => sprintf(__('Reasignación masiva de área a "%s"', 'aura-suite'), $area_name),
                    );
                }

                // 2. Método de Pago
                if (!empty($_POST['edit_payment_method'])) {
                    $new_pm = sanitize_text_field($_POST['new_payment_method'] ?? 'transfer');
                    $pinfo = self::get_payment_method_info($new_pm);
                    $update_sets[] = "payment_method = %s";
                    $update_vals[] = $new_pm;

                    $history_logs[] = array(
                        'field'  => 'payment_method',
                        'value'  => $new_pm,
                        'reason' => sprintf(__('Cambio masivo de método de pago a "%s"', 'aura-suite'), $pinfo['label']),
                    );
                }

                // 3. Vinculado / Tercero / Beneficiario
                if (!empty($_POST['edit_related'])) {
                    $related_mode = sanitize_text_field($_POST['related_mode'] ?? 'none');

                    if ($related_mode === 'third_party') {
                        $tp_id = intval($_POST['new_third_party_id'] ?? 0);
                        $tp_concept = sanitize_text_field($_POST['new_third_party_concept'] ?? '');

                        $tp_table = $wpdb->prefix . 'aura_finance_third_parties';
                        $tp_obj = $wpdb->get_row($wpdb->prepare("SELECT COALESCE(NULLIF(commercial_name, ''), full_name) as display_name, party_type FROM {$tp_table} WHERE id = %d", $tp_id));
                        $tp_name = $tp_obj ? $tp_obj->display_name : sprintf(__('Tercero #%d', 'aura-suite'), $tp_id);

                        $update_sets[] = "third_party_id = %d";
                        $update_vals[] = $tp_id;
                        $update_sets[] = "related_user_id = %d";
                        $update_vals[] = 0;
                        $update_sets[] = "related_user_concept = %s";
                        $update_vals[] = $tp_concept;
                        $update_sets[] = "recipient_payer = %s";
                        $update_vals[] = $tp_name;

                        $concept_labels = array(
                            'salary'                => __('Salario / Nómina / Honorarios', 'aura-suite'),
                            'expense_reimbursement' => __('Reembolso de Gastos', 'aura-suite'),
                            'loan_payment'          => __('Pago / Abono Préstamo', 'aura-suite'),
                            'scholarship'           => __('Beca Asignada', 'aura-suite'),
                            'refund'                => __('Reembolso / Devolución', 'aura-suite'),
                            'payment_to_user'       => __('Pago Realizado', 'aura-suite'),
                            'charge_to_user'        => __('Cobro Realizado', 'aura-suite'),
                            'other'                 => __('Otro Concepto', 'aura-suite'),
                        );
                        $concept_str = !empty($tp_concept) ? ' (' . ($concept_labels[$tp_concept] ?? $tp_concept) . ')' : '';

                        $history_logs[] = array(
                            'field'  => 'third_party_id',
                            'value'  => (string) $tp_id,
                            'reason' => sprintf(__('Vinculación masiva a tercero/persona "%s"%s', 'aura-suite'), $tp_name, $concept_str),
                        );
                    } elseif ($related_mode === 'user') {
                        $ru_id = intval($_POST['new_related_user_id'] ?? 0);
                        $ru_concept = sanitize_text_field($_POST['new_related_concept'] ?? 'none');

                        $u = get_userdata($ru_id);
                        $u_name = $u ? $u->display_name : sprintf(__('Usuario #%d', 'aura-suite'), $ru_id);

                        $update_sets[] = "related_user_id = %d";
                        $update_vals[] = $ru_id;
                        $update_sets[] = "related_user_concept = %s";
                        $update_vals[] = $ru_concept;
                        $update_sets[] = "third_party_id = %d";
                        $update_vals[] = 0;
                        $update_sets[] = "recipient_payer = %s";
                        $update_vals[] = $u_name;

                        $history_logs[] = array(
                            'field'  => 'related_user_id',
                            'value'  => (string) $ru_id,
                            'reason' => sprintf(__('Vinculación masiva a usuario "%s" (%s)', 'aura-suite'), $u_name, $ru_concept),
                        );
                    } elseif ($related_mode === 'recipient_payer') {
                        $rec_name = sanitize_text_field($_POST['new_recipient_payer'] ?? '');
                        $update_sets[] = "recipient_payer = %s";
                        $update_vals[] = $rec_name;
                        $update_sets[] = "third_party_id = %d";
                        $update_vals[] = 0;
                        $update_sets[] = "related_user_id = %d";
                        $update_vals[] = 0;
                        $update_sets[] = "related_user_concept = %s";
                        $update_vals[] = '';

                        $history_logs[] = array(
                            'field'  => 'recipient_payer',
                            'value'  => $rec_name,
                            'reason' => sprintf(__('Beneficiario asignado masivamente: "%s"', 'aura-suite'), $rec_name),
                        );
                    } else {
                        // Desvincular todo
                        $update_sets[] = "third_party_id = %d";
                        $update_vals[] = 0;
                        $update_sets[] = "related_user_id = %d";
                        $update_vals[] = 0;
                        $update_sets[] = "related_user_concept = %s";
                        $update_vals[] = '';
                        $update_sets[] = "recipient_payer = %s";
                        $update_vals[] = '';

                        $history_logs[] = array(
                            'field'  => 'related_user_id',
                            'value'  => '0',
                            'reason' => __('Desvinculación masiva de contraparte/tercero', 'aura-suite'),
                        );
                    }
                }

                // 4. Creado por (Autor)
                if (!empty($_POST['edit_created_by'])) {
                    $new_creator_id = intval($_POST['new_created_by'] ?? 0);
                    $creator_user = get_userdata($new_creator_id);
                    if ($creator_user) {
                        $update_sets[] = "created_by = %d";
                        $update_vals[] = $new_creator_id;

                        $history_logs[] = array(
                            'field'  => 'created_by',
                            'value'  => (string) $new_creator_id,
                            'reason' => sprintf(__('Reasignación masiva de creador a "%s"', 'aura-suite'), $creator_user->display_name),
                        );
                    }
                }

                // 5. Categoría
                if (!empty($_POST['edit_category'])) {
                    $new_cat_id = intval($_POST['new_category_id'] ?? 0);
                    if ($new_cat_id > 0) {
                        $cat_table = $wpdb->prefix . 'aura_finance_categories';
                        $cat_exists = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM {$cat_table} WHERE id = %d", $new_cat_id));
                        if ($cat_exists) {
                            $update_sets[] = "category_id = %d";
                            $update_vals[] = $new_cat_id;

                            $has_expense_col = (bool) $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'expense_category_id'",
                                    DB_NAME, $table_name
                                )
                            );
                            if ($has_expense_col) {
                                $update_sets[] = "expense_category_id = %d";
                                $update_vals[] = $new_cat_id;
                            }

                            $history_logs[] = array(
                                'field'  => 'category_id',
                                'value'  => (string) $new_cat_id,
                                'reason' => sprintf(__('Reasignación masiva de categoría a "%s"', 'aura-suite'), $cat_exists->name),
                            );
                        }
                    }
                }

                // 6. Campo: Etiquetas (#tags)
                if (!empty($_POST['edit_tags'])) {
                    $tags_mode  = sanitize_text_field($_POST['tags_mode'] ?? 'replace');
                    $raw_tags   = sanitize_text_field($_POST['new_tags'] ?? '');
                    $input_tags = array_filter(array_map('trim', explode(',', $raw_tags)));

                    if ($tags_mode === 'clear') {
                        $update_sets[] = "tags = %s";
                        $update_vals[] = '';
                        $history_logs[] = array(
                            'field'  => 'tags',
                            'value'  => '',
                            'reason' => __('Eliminación masiva de todas las etiquetas', 'aura-suite'),
                        );
                    } elseif ($tags_mode === 'replace') {
                        $clean_tags_str = implode(', ', array_map(function($t) { return ltrim($t, '#'); }, $input_tags));
                        $update_sets[] = "tags = %s";
                        $update_vals[] = $clean_tags_str;
                        $history_logs[] = array(
                            'field'  => 'tags',
                            'value'  => $clean_tags_str,
                            'reason' => sprintf(__('Reemplazo masivo de etiquetas a "%s"', 'aura-suite'), $clean_tags_str),
                        );
                    } elseif ($tags_mode === 'append' || $tags_mode === 'remove') {
                        if (!empty($input_tags)) {
                            $placeholders_for_tags = implode(',', array_fill(0, count($transaction_ids), '%d'));
                            $tx_rows = $wpdb->get_results(
                                $wpdb->prepare("SELECT id, tags FROM {$table_name} WHERE id IN ({$placeholders_for_tags})", ...$transaction_ids)
                            );
                            foreach ($tx_rows as $row) {
                                $curr_tags = array_filter(array_map('trim', explode(',', (string)$row->tags)));
                                if ($tags_mode === 'append') {
                                    $merged = $curr_tags;
                                    foreach ($input_tags as $it) {
                                        $it_clean = ltrim($it, '#');
                                        if (!in_array($it_clean, $merged, true)) {
                                            $merged[] = $it_clean;
                                        }
                                    }
                                    $final_tags_str = implode(', ', $merged);
                                    $action_label   = sprintf(__('Etiquetas añadidas masivamente: "%s"', 'aura-suite'), implode(', ', $input_tags));
                                } else { // remove
                                    $lower_remove = array_map('strtolower', array_map(function($t){ return ltrim($t, '#'); }, $input_tags));
                                    $filtered = array_filter($curr_tags, function($t) use ($lower_remove) {
                                        return !in_array(strtolower(ltrim($t, '#')), $lower_remove, true);
                                    });
                                    $final_tags_str = implode(', ', $filtered);
                                    $action_label   = sprintf(__('Etiquetas eliminadas masivamente: "%s"', 'aura-suite'), implode(', ', $input_tags));
                                }
                                $wpdb->update(
                                    $table_name,
                                    array('tags' => $final_tags_str, 'updated_at' => current_time('mysql')),
                                    array('id' => $row->id),
                                    array('%s', '%s'),
                                    array('%d')
                                );
                            }
                            $history_logs[] = array(
                                'field'  => 'tags',
                                'value'  => implode(', ', $input_tags),
                                'reason' => $action_label,
                            );
                        }
                    }
                }

                $individually_updated = (!empty($_POST['edit_tags']) && in_array($_POST['tags_mode'] ?? '', array('append', 'remove'), true));
                if (empty($update_sets) && !$individually_updated) {
                    wp_send_json_error(array('message' => __('No seleccionaste ningún campo para modificar.', 'aura-suite')));
                }

                if (!empty($update_sets)) {
                    // Fecha de modificación (en wp_aura_finance_transactions existe updated_at)
                    $update_sets[] = "updated_at = %s";
                    $update_vals[] = current_time('mysql');

                    $set_sql = implode(', ', $update_sets);
                    $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                    $prepare_args = array_merge($update_vals, $transaction_ids);

                    $res = $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_name} SET {$set_sql} WHERE id IN ({$ids_placeholders})",
                        ...$prepare_args
                    ));

                    if ($res === false) {
                        wp_send_json_error(array(
                            'message' => sprintf(__('Error en base de datos al actualizar transacciones: %s', 'aura-suite'), $wpdb->last_error)
                        ));
                    }
                }

                // Registrar auditoría en historial
                $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$history_table}'") === $history_table) {
                    $now = current_time('mysql');
                    foreach ($transaction_ids as $tid) {
                        foreach ($history_logs as $hlog) {
                            $wpdb->insert(
                                $history_table,
                                array(
                                    'transaction_id' => $tid,
                                    'field_changed'  => $hlog['field'],
                                    'old_value'      => '',
                                    'new_value'      => $hlog['value'],
                                    'change_reason'  => $hlog['reason'],
                                    'changed_by'     => $current_user_id,
                                    'changed_at'     => $now,
                                ),
                                array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                            );
                        }
                    }
                }

                wp_send_json_success(array(
                    'message' => sprintf(__('Edición masiva completada con éxito en %d transacción(es).', 'aura-suite'), count($transaction_ids)),
                    'count'   => count($transaction_ids),
                ));
                break;

            case 'change_category':
                $can_edit_all = current_user_can('aura_finance_edit_all');
                $can_edit_own = current_user_can('aura_finance_edit_own');

                if (!$can_edit_all && !$can_edit_own) {
                    wp_send_json_error(array('message' => __('No tienes permisos para editar transacciones.', 'aura-suite')));
                }

                $new_cat_id = isset($_POST['new_category_id']) ? absint($_POST['new_category_id']) : 0;
                if (!$new_cat_id) {
                    wp_send_json_error(array('message' => __('Debes seleccionar una categoría válida.', 'aura-suite')));
                }

                $cat_table = $wpdb->prefix . 'aura_finance_categories';
                $cat_exists = $wpdb->get_row($wpdb->prepare("SELECT id, name, type FROM {$cat_table} WHERE id = %d", $new_cat_id));
                if (!$cat_exists) {
                    wp_send_json_error(array('message' => __('La categoría seleccionada no existe.', 'aura-suite')));
                }

                $current_user_id = get_current_user_id();

                // Si solo puede editar propias, verificar pertenencia
                if (!$can_edit_all && $can_edit_own) {
                    $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                    $prepare_args = array_merge($transaction_ids, array($current_user_id));
                    $owned_transactions = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM {$table_name} WHERE id IN ($ids_placeholders) AND created_by = %d",
                        ...$prepare_args
                    ));
                    if (count($owned_transactions) !== count($transaction_ids)) {
                        wp_send_json_error(array('message' => __('Solo puedes reasignar categorías en tus propias transacciones.', 'aura-suite')));
                    }
                    $transaction_ids = array_map('intval', $owned_transactions);
                }

                if (empty($transaction_ids)) {
                    wp_send_json_error(array('message' => __('No hay transacciones válidas seleccionadas.', 'aura-suite')));
                }

                $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));

                $has_expense_col = (bool) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'expense_category_id'",
                        DB_NAME, $table_name
                    )
                );

                if ($has_expense_col) {
                    $prepare_args = array_merge(array($new_cat_id, $new_cat_id, current_time('mysql'), $current_user_id), $transaction_ids);
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_name} SET category_id = %d, expense_category_id = %d, updated_at = %s, updated_by = %d WHERE id IN ($ids_placeholders)",
                        ...$prepare_args
                    ));
                } else {
                    $prepare_args = array_merge(array($new_cat_id, current_time('mysql'), $current_user_id), $transaction_ids);
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table_name} SET category_id = %d, updated_at = %s, updated_by = %d WHERE id IN ($ids_placeholders)",
                        ...$prepare_args
                    ));
                }

                // Registrar en historial
                $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$history_table}'") === $history_table) {
                    foreach ($transaction_ids as $tid) {
                        $wpdb->insert(
                            $history_table,
                            array(
                                'transaction_id' => $tid,
                                'field_changed'  => 'category_id',
                                'old_value'      => '',
                                'new_value'      => (string) $new_cat_id,
                                'change_reason'  => sprintf(__('Reasignación masiva a "%s"', 'aura-suite'), $cat_exists->name),
                                'changed_by'     => $current_user_id,
                                'changed_at'     => current_time('mysql'),
                            ),
                            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                        );
                    }
                }

                wp_send_json_success(array(
                    'message' => sprintf(__('Se actualizó la categoría a "%s" en %d transacción(es).', 'aura-suite'), $cat_exists->name, count($transaction_ids))
                ));
                break;

            case 'bulk_approve':
                if (!current_user_can('aura_finance_approve')) {
                    wp_send_json_error(array('message' => __('No tienes permisos', 'aura-suite')));
                }
                
                $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET status = 'approved', approved_by = %d, approved_at = %s WHERE id IN ($ids_placeholders)",
                    get_current_user_id(),
                    current_time('mysql'),
                    ...$transaction_ids
                ));
                
                wp_send_json_success(array('message' => __('Transacciones aprobadas', 'aura-suite')));
                break;
                
            case 'bulk_delete':
                $current_user_id = get_current_user_id();
                $can_delete_all = current_user_can('aura_finance_delete_all');
                $can_delete_own = current_user_can('aura_finance_delete_own');
                
                if (!$can_delete_all && !$can_delete_own) {
                    wp_send_json_error(array('message' => __('No tienes permisos para eliminar transacciones', 'aura-suite')));
                }
                
                // Si solo tiene permiso para eliminar propias, filtrar solo las suyas
                if (!$can_delete_all && $can_delete_own) {
                    // Verificar que todas las transacciones sean del usuario actual
                    $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                    
                    // Preparar argumentos: IDs + current_user_id
                    $prepare_args = array_merge($transaction_ids, array($current_user_id));
                    
                    $owned_transactions = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM $table_name WHERE id IN ($ids_placeholders) AND created_by = %d",
                        ...$prepare_args
                    ));
                    
                    if (count($owned_transactions) !== count($transaction_ids)) {
                        wp_send_json_error(array('message' => __('Solo puedes eliminar tus propias transacciones', 'aura-suite')));
                    }
                    
                    $transaction_ids = $owned_transactions;
                }
                
                if (empty($transaction_ids)) {
                    wp_send_json_error(array('message' => __('No hay transacciones para eliminar', 'aura-suite')));
                }
                
                $ids_placeholders = implode(',', array_fill(0, count($transaction_ids), '%d'));
                $affected = $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET deleted_at = %s, deleted_by = %d WHERE id IN ($ids_placeholders)",
                    current_time('mysql'),
                    $current_user_id,
                    ...$transaction_ids
                ));
                
                // Registrar en historial quién eliminó cada transacción
                if ($affected) {
                    $history_table = $wpdb->prefix . 'aura_finance_transaction_history';
                    $deleted_by = $current_user_id;
                    $deleted_at = current_time('mysql');
                    foreach ($transaction_ids as $tid) {
                        $wpdb->insert(
                            $history_table,
                            array(
                                'transaction_id' => $tid,
                                'field_changed'  => 'status_deletion',
                                'old_value'      => 'active',
                                'new_value'      => 'soft_delete',
                                'change_reason'  => __('Enviado a papelera', 'aura-suite'),
                                'changed_by'     => $deleted_by,
                                'changed_at'     => $deleted_at,
                            ),
                            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
                        );
                    }
                }
                
                wp_send_json_success(array(
                    'message' => sprintf(
                        _n('%d transacción eliminada', '%d transacciones eliminadas', $affected, 'aura-suite'),
                        $affected
                    )
                ));
                break;
                
            default:
                wp_send_json_error(array('message' => __('Acción no válida', 'aura-suite')));
        }
    }
    
    /**
     * AJAX: Guardar preset de filtros
     */
    public static function ajax_save_filter_preset() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        $preset_name = sanitize_text_field($_POST['preset_name'] ?? '');
        $filters = $_POST['filters'] ?? array();
        
        if (empty($preset_name)) {
            wp_send_json_error(array('message' => __('Nombre requerido', 'aura-suite')));
        }
        
        $user_presets = get_user_meta(get_current_user_id(), 'aura_finance_filter_presets', true);
        if (!is_array($user_presets)) {
            $user_presets = array();
        }
        
        $user_presets[$preset_name] = $filters;
        update_user_meta(get_current_user_id(), 'aura_finance_filter_presets', $user_presets);
        
        wp_send_json_success(array('message' => __('Filtro guardado', 'aura-suite')));
    }
    
    /**
     * AJAX: Cargar preset de filtros
     */
    public static function ajax_load_filter_preset() {
        check_ajax_referer('aura_transactions_list_nonce', 'nonce');
        
        $preset_name = sanitize_text_field($_POST['preset_name'] ?? '');
        
        $user_presets = get_user_meta(get_current_user_id(), 'aura_finance_filter_presets', true);
        
        if (!is_array($user_presets) || !isset($user_presets[$preset_name])) {
            wp_send_json_error(array('message' => __('Filtro no encontrado', 'aura-suite')));
        }
        
        wp_send_json_success(array('filters' => $user_presets[$preset_name]));
    }

    /**
     * Renderiza la tarjeta de detalles completa dentro de la Fila Hija Expandible (Child Row).
     *
     * @param object $item
     */
    protected function render_child_row_content( $item ) {
        $type = strtolower( (string) $item->transaction_type );
        $type_map = array(
            'income'   => array('label' => __('Ingreso Operativo', 'aura-suite'), 'color' => '#059669', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'icon' => '↑'),
            'expense'  => array('label' => __('Egreso Operacional', 'aura-suite'), 'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca', 'icon' => '↓'),
            'capital'  => array('label' => __('Gasto Capital (CapEx)', 'aura-suite'), 'color' => '#7c3aed', 'bg' => '#f5f3ff', 'border' => '#ddd6fe', 'icon' => '🏛️'),
            'transfer' => array('label' => __('Transferencia', 'aura-suite'), 'color' => '#2563eb', 'bg' => '#eff6ff', 'border' => '#bfdbfe', 'icon' => '⇄'),
        );
        $t_info = $type_map[ $type ] ?? $type_map['expense'];

        $status_map = array(
            'pending'  => array('label' => __('Pendiente', 'aura-suite'), 'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a', 'icon' => 'dashicons-clock'),
            'approved' => array('label' => __('Aprobado', 'aura-suite'), 'color' => '#059669', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'icon' => 'dashicons-yes-alt'),
            'rejected' => array('label' => __('Rechazado', 'aura-suite'), 'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca', 'icon' => 'dashicons-dismiss'),
        );
        $s_info = $status_map[ $item->status ] ?? $status_map['pending'];

        $date_formatted = $item->transaction_date;
        if ( ! empty( $item->transaction_date ) ) {
            $ts = strtotime( $item->transaction_date );
            if ( $ts ) {
                $date_formatted = date_i18n( get_option( 'date_format', 'd/m/Y' ), $ts );
            }
        }

        $user_creator   = ! empty( $item->created_by ) ? get_userdata( $item->created_by ) : null;
        $creator_name   = $user_creator ? $user_creator->display_name : ( ! empty( $item->created_by ) ? __( 'Usuario del sistema', 'aura-suite' ) : __( 'Sistema', 'aura-suite' ) );
        $creator_avatar = ! empty( $item->created_by ) ? get_avatar_url( $item->created_by, array( 'size' => 32 ) ) : '';

        $related_user_name   = __( 'Ninguno', 'aura-suite' );
        $related_user_avatar = '';
        if ( ! empty( $item->related_user_id ) ) {
            $ru = get_userdata( $item->related_user_id );
            if ( $ru ) {
                $related_user_name   = $ru->display_name;
                $related_user_avatar = get_avatar_url( $ru->ID, array( 'size' => 32 ) );
            }
        }

        $formatted_amount = '$' . number_format( (float) $item->amount, 2, '.', ',' );
        $sign = ( $type === 'income' ) ? '+' : ( ( $type === 'capital' || $type === 'expense' ) ? '-' : '' );
        ?>
        <!-- Hero Header de la Fila Hija -->
        <div class="aura-child-hero-bar">
            <div class="aura-child-hero-left">
                <div class="aura-child-icon-box" style="background:<?php echo esc_attr($t_info['bg']); ?>;color:<?php echo esc_attr($t_info['color']); ?>;border:1px solid <?php echo esc_attr($t_info['border']); ?>;">
                    <span class="dashicons dashicons-media-document"></span>
                </div>
                <div class="aura-child-hero-titles">
                    <h3 class="aura-child-hero-title">
                        <?php printf( __( 'Detalles de la Transacción #%d', 'aura-suite' ), $item->id ); ?>
                    </h3>
                    <div class="aura-child-hero-badges">
                        <span class="aura-pill-badge" style="background:<?php echo esc_attr($t_info['bg']); ?>;color:<?php echo esc_attr($t_info['color']); ?>;border:1px solid <?php echo esc_attr($t_info['border']); ?>;">
                            <span><?php echo esc_html($t_info['icon']); ?></span> <?php echo esc_html($t_info['label']); ?>
                        </span>
                        <span class="aura-pill-badge" style="background:<?php echo esc_attr($s_info['bg']); ?>;color:<?php echo esc_attr($s_info['color']); ?>;border:1px solid <?php echo esc_attr($s_info['border']); ?>;">
                            <span class="dashicons <?php echo esc_attr($s_info['icon']); ?>"></span>
                            <?php echo esc_html($s_info['label']); ?>
                        </span>
                        <span class="aura-pill-badge aura-pill-date">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html($date_formatted); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="aura-child-hero-right">
                <div class="aura-child-amount-card" style="background:<?php echo esc_attr($t_info['bg']); ?>;border:1px solid <?php echo esc_attr($t_info['border']); ?>;">
                    <span class="aura-amount-label"><?php _e('Monto Total', 'aura-suite'); ?></span>
                    <span class="aura-amount-val" style="color:<?php echo esc_attr($t_info['color']); ?>;">
                        <?php echo esc_html($sign . $formatted_amount); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Grid de Sub-Tarjetas Modulares -->
        <div class="aura-child-sections-grid">
            
            <!-- Sub-Card 1: Datos Financieros & Categorización Completa -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-money-alt aura-subcard-icon"></span>
                    <h4><?php _e('Datos Contables & Categorización', 'aura-suite'); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <?php
                    $cat_val   = ! empty( $item->category_name ) ? esc_html( $item->category_name ) : __( 'Sin categoría', 'aura-suite' );
                    $cat_color = ! empty( $item->category_color ) ? $item->category_color : '#64748b';
                    $cat_icon  = ! empty( $item->category_icon ) ? $item->category_icon : 'dashicons-tag';
                    
                    $cat_icon_html = '';
                    if ( class_exists( 'Aura_Financial_Categories' ) ) {
                        $cat_icon_html = Aura_Financial_Categories::render_icon_html( $cat_icon, '#ffffff' ) . ' ';
                    } elseif ( strpos( $cat_icon, 'dashicons-' ) !== false || strpos( $cat_icon, 'dashicons' ) !== false ) {
                        $cat_icon_html = '<span class="dashicons ' . esc_attr($cat_icon) . '"></span> ';
                    } else {
                        $cat_icon_html = '<span class="aura-cat-emoji" style="font-size:13px;line-height:1;margin-right:2px;">' . esc_html($cat_icon) . '</span> ';
                    }
                    $cat_badge = sprintf(
                        '<span class="aura-category-badge" style="background-color:%s;color:#fff;padding:3px 9px;border-radius:6px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;">%s%s</span>',
                        esc_attr( $cat_color ),
                        $cat_icon_html,
                        $cat_val
                    );
                    echo self::render_meta_item( __( 'Categoría Financiera', 'aura-suite' ), $cat_badge, 'dashicons-category' );

                    // Detalle Extendido de la Categoría
                    $cat_extra_details = array();
                    if ( ! empty( $item->parent_category_name ) ) {
                        $parent_icon_html = '';
                        if ( ! empty( $item->parent_category_icon ) ) {
                            if ( strpos( $item->parent_category_icon, 'dashicons-' ) !== false ) {
                                $parent_icon_html = '<span class="dashicons ' . esc_attr( $item->parent_category_icon ) . '" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:2px;"></span> ';
                            } else {
                                $parent_icon_html = '<span class="aura-cat-emoji" style="font-size:12px;vertical-align:middle;margin-right:2px;">' . esc_html( $item->parent_category_icon ) . '</span> ';
                            }
                        }
                        $cat_extra_details[] = '<span><strong>' . __( 'Categoría Padre:', 'aura-suite' ) . '</strong> ' . $parent_icon_html . esc_html( $item->parent_category_name ) . '</span>';
                    }
                    if ( ! empty( $item->category_slug ) ) {
                        $cat_extra_details[] = '<span><strong>' . __( 'Slug / Código:', 'aura-suite' ) . '</strong> <code>' . esc_html( $item->category_slug ) . '</code></span>';
                    }
                    $is_capex = ( $type === 'capital' ) 
                        || ( ! empty( $item->category_is_capex ) && (int) $item->category_is_capex === 1 ) 
                        || ( ! empty( $item->category_type ) && $item->category_type === 'capital' );

                    if ( $is_capex ) {
                        $nature_badge = '<span style="color:#7c3aed;font-weight:700;">🏛️ CapEx (Gasto de Capital)</span>';
                    } elseif ( $type === 'income' ) {
                        $nature_badge = '<span style="color:#059669;font-weight:700;">📈 Ingreso Operativo</span>';
                    } elseif ( $type === 'transfer' ) {
                        $nature_badge = '<span style="color:#2563eb;font-weight:700;">⇄ Transferencia Interna</span>';
                    } else {
                        $nature_badge = '<span style="color:#dc2626;font-weight:600;">💼 OpEx (Gasto Operativo)</span>';
                    }
                    $cat_extra_details[] = '<span><strong>' . __( 'Naturaleza:', 'aura-suite' ) . '</strong> ' . $nature_badge . '</span>';
                    if ( ! empty( $item->category_description ) ) {
                        $cat_extra_details[] = '<span style="display:block;margin-top:2px;"><strong>' . __( 'Descripción:', 'aura-suite' ) . '</strong> ' . esc_html( $item->category_description ) . '</span>';
                    }
                    if ( ! empty( $cat_extra_details ) ) {
                        $cat_details_box = '<div class="aura-subcard-cat-details" style="background:rgba(241,245,249,0.7);dark:background:rgba(30,41,59,0.7);padding:6px 10px;border-radius:6px;font-size:11.5px;color:#334155;border-left:3px solid ' . esc_attr($cat_color) . ';display:flex;flex-direction:column;gap:3px;margin-top:2px;">' . implode('', $cat_extra_details) . '</div>';
                        echo self::render_meta_item( __( 'Detalle de Categoría', 'aura-suite' ), $cat_details_box, 'dashicons-info' );
                    }

                    // Cuentas Origen y Destino
                    if ( ! empty( $item->source_account_name ) ) {
                        $acc_inst = ! empty( $item->source_account_institution ) ? ' · ' . esc_html( $item->source_account_institution ) : '';
                        $acc_curr = ! empty( $item->source_account_currency ) ? ' (' . esc_html( $item->source_account_currency ) . ')' : '';
                        $acc_val  = '<span style="font-weight:600;color:#0f172a;"><span class="dashicons dashicons-vault" style="color:#3b82f6;font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:3px;"></span>' . esc_html( $item->source_account_name ) . $acc_inst . $acc_curr . '</span>';
                        echo self::render_meta_item( __( 'Cuenta / Caja Origen', 'aura-suite' ), $acc_val, 'dashicons-vault' );
                    }

                    if ( ! empty( $item->destination_account_name ) ) {
                        $d_inst = ! empty( $item->destination_account_institution ) ? ' · ' . esc_html( $item->destination_account_institution ) : '';
                        $d_curr = ! empty( $item->destination_account_currency ) ? ' (' . esc_html( $item->destination_account_currency ) . ')' : '';
                        $d_val  = '<span style="font-weight:600;color:#0f172a;"><span class="dashicons dashicons-randomize" style="color:#10b981;font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:3px;"></span>' . esc_html( $item->destination_account_name ) . $d_inst . $d_curr . '</span>';
                        echo self::render_meta_item( __( 'Cuenta / Caja Destino', 'aura-suite' ), $d_val, 'dashicons-randomize' );
                    }

                    $pinfo = self::get_payment_method_info( $item->payment_method ?? '' );
                    $payment_val = sprintf(
                        '<span class="aura-meta-badge" style="display:inline-flex;align-items:center;gap:6px;background:%s18;color:%s;padding:3px 8px;border-radius:6px;font-weight:600;font-size:12px;border:1px solid %s35;">'
                        . '<span class="dashicons %s" style="font-size:14px;width:14px;height:14px;line-height:14px;"></span> %s'
                        . '</span>',
                        esc_attr( $pinfo['color'] ),
                        esc_attr( $pinfo['color'] ),
                        esc_attr( $pinfo['color'] ),
                        esc_attr( $pinfo['icon'] ),
                        esc_html( $pinfo['label'] )
                    );
                    echo self::render_meta_item( __( 'Método de Pago', 'aura-suite' ), $payment_val, 'dashicons-money-alt' );

                    $ref_val = ! empty( $item->reference_number ) ? '<span class="aura-code-pill">' . esc_html( $item->reference_number ) . '</span>' : '<span style="color:#94a3b8;">—</span>';
                    echo self::render_meta_item( __( 'N° Referencia', 'aura-suite' ), $ref_val, 'dashicons-tag' );

                    if ( ! empty( $item->tags ) ) {
                        $tags_list = array_filter( array_map( 'trim', explode( ',', $item->tags ) ) );
                        $tags_html = '';
                        foreach ( $tags_list as $t ) {
                            $clean_t = ltrim( $t, '#' );
                            $tags_html .= '<span class="aura-meta-tag-chip" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:700;display:inline-flex;align-items:center;gap:2px;margin-right:4px;margin-bottom:3px;"><span style="color:#3b82f6;font-weight:800;">#</span>' . esc_html( $clean_t ) . '</span>';
                        }
                        echo self::render_meta_item( __( 'Etiquetas (#tags)', 'aura-suite' ), $tags_html, 'dashicons-tag' );
                    } else {
                        echo self::render_meta_item( __( 'Etiquetas (#tags)', 'aura-suite' ), '<span style="color:#94a3b8;font-style:italic;">' . __( 'Sin etiquetas', 'aura-suite' ) . '</span>', 'dashicons-tag' );
                    }
                    ?>
                </div>
            </div>

            <!-- Sub-Card 2: Personas, Entidades y Terceros (Ficha Completa) -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-groups aura-subcard-icon"></span>
                    <h4><?php _e('Personas, Entidades y Terceros', 'aura-suite'); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <?php
                    $creator_img = $creator_avatar 
                        ? '<img src="' . esc_url( $creator_avatar ) . '" alt="" width="34" height="34" class="aura-user-avatar" style="width:34px;height:34px;border-radius:999px;object-fit:cover;flex-shrink:0;">' 
                        : '<span class="dashicons dashicons-admin-users" style="color:#64748b;font-size:24px;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;"></span>';

                    $creator_html = '<div class="aura-user-badge-card">'
                        . $creator_img
                        . '<div class="aura-user-info">'
                        . '<span class="aura-user-name">' . esc_html( $creator_name ) . '</span>'
                        . '<span class="aura-user-sub">' . sprintf( __( 'Registrado el %s', 'aura-suite' ), ! empty($item->created_at) ? date_i18n('d/m/Y H:i', strtotime($item->created_at)) : $date_formatted ) . '</span>'
                        . '</div></div>';
                    echo self::render_meta_item( __( 'Creado por', 'aura-suite' ), $creator_html, 'dashicons-admin-users' );

                    if ( ! empty( $item->third_party_id ) && ( ! empty( $item->tp_full_name ) || ! empty( $item->tp_commercial_name ) ) ) {
                        $ptype = $item->tp_party_type ?: 'company';
                        $type_labels = array(
                            'company'                 => __('Empresa / Sociedad', 'aura-suite'),
                            'store'                   => __('Tienda / Comercio', 'aura-suite'),
                            'organization_foundation' => __('Fundación / ONG', 'aura-suite'),
                            'person'                  => __('Persona Natural', 'aura-suite'),
                            'religious'               => __('Entidad Religiosa', 'aura-suite'),
                            'other'                   => __('Otra Entidad', 'aura-suite'),
                        );
                        $type_icons = array(
                            'company'                 => 'dashicons-building',
                            'store'                   => 'dashicons-cart',
                            'organization_foundation' => 'dashicons-heart',
                            'person'                  => 'dashicons-businessman',
                            'religious'               => 'dashicons-location-alt',
                            'other'                   => 'dashicons-category',
                        );
                        $type_emojis = array(
                            'company'                 => '🏢',
                            'store'                   => '🛒',
                            'organization_foundation' => '🏛️',
                            'person'                  => '👤',
                            'religious'               => '⛪',
                            'other'                   => '🏷️',
                        );
                        $main_name = !empty($item->tp_commercial_name) ? $item->tp_commercial_name : $item->tp_full_name;
                        $logo_id = (int) ($item->tp_logo_id ?? 0);
                        $logo_url = $logo_id ? ( wp_get_attachment_image_url($logo_id, 'medium') ?: wp_get_attachment_url($logo_id) ?: '' ) : '';
                        
                        $sub_parts = array();
                        if ( ! empty( $item->tp_document_id ) ) {
                            $sub_parts[] = ($item->tp_tax_id_type ?: 'NIT') . ': ' . $item->tp_document_id;
                        }
                        if ( ! empty($type_labels[$ptype]) ) {
                            $sub_parts[] = $type_labels[$ptype];
                        }
                        $sub_txt = implode(' · ', $sub_parts);
                        
                        // Ficha 360 del tercero dentro del subcard
                        $tp_extra_rows = array();
                        if ( ! empty( $item->tp_commercial_name ) && ! empty( $item->tp_full_name ) && $item->tp_commercial_name !== $item->tp_full_name ) {
                            $tp_extra_rows[] = '<div><strong>' . __( 'Razón Social:', 'aura-suite' ) . '</strong> ' . esc_html( $item->tp_full_name ) . '</div>';
                        }
                        if ( ! empty( $item->tp_phone ) ) {
                            $clean_p = preg_replace( '/[^0-9+]/', '', $item->tp_phone );
                            $tp_extra_rows[] = '<div><strong>' . __( 'Teléfono:', 'aura-suite' ) . '</strong> <a href="https://wa.me/' . esc_attr( ltrim( $clean_p, '+' ) ) . '" target="_blank" style="color:#059669;font-weight:600;text-decoration:underline;"><span class="dashicons dashicons-whatsapp" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span> ' . esc_html( $item->tp_phone ) . '</a></div>';
                        }
                        if ( ! empty( $item->tp_email ) ) {
                            $tp_extra_rows[] = '<div><strong>' . __( 'Correo:', 'aura-suite' ) . '</strong> <a href="mailto:' . esc_attr( $item->tp_email ) . '" style="color:#2563eb;text-decoration:underline;"><span class="dashicons dashicons-email-alt" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span> ' . esc_html( $item->tp_email ) . '</a></div>';
                        }
                        if ( ! empty( $item->tp_website ) ) {
                            $tp_extra_rows[] = '<div><strong>' . __( 'Sitio Web:', 'aura-suite' ) . '</strong> <a href="' . esc_url( $item->tp_website ) . '" target="_blank" style="color:#2563eb;text-decoration:underline;"><span class="dashicons dashicons-admin-site-alt3" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span> ' . esc_html( $item->tp_website ) . '</a></div>';
                        }
                        if ( ! empty( $item->tp_address ) ) {
                            $tp_extra_rows[] = '<div><strong>' . __( 'Dirección:', 'aura-suite' ) . '</strong> <span class="dashicons dashicons-location" style="font-size:13px;width:13px;height:13px;vertical-align:middle;color:#dc2626;"></span> ' . esc_html( $item->tp_address ) . '</div>';
                        }

                        if ( $logo_url ) {
                            $tp_media = '<div class="aura-table-thumb-preview aura-img-preview-trigger" data-img-url="' . esc_url( $logo_url ) . '" data-img-title="' . esc_attr( $main_name ) . '" style="width:34px;height:34px;border-radius:6px;overflow:hidden;flex-shrink:0;"><img src="' . esc_url( $logo_url ) . '" alt="" width="34" height="34" style="object-fit:cover;width:100%;height:100%;"></div>';
                        } else {
                            $tp_media = '<span class="aura-tp-media-emoji" style="margin-right:6px;font-size:22px;line-height:1;display:inline-flex;align-items:center;justify-content:center;">' . esc_html( $type_emojis[$ptype] ?? '🏢' ) . '</span>';
                        }

                        $related_html = '<div class="aura-user-badge-card" style="margin-bottom:6px;">'
                            . $tp_media
                            . '<div class="aura-user-info">'
                            . '<span class="aura-user-name" style="font-size:13px;font-weight:700;">' . esc_html( $main_name ) . '</span>'
                            . '<span class="aura-user-sub">' . esc_html( $sub_txt ) . '</span>'
                            . '</div></div>';

                        if ( ! empty( $tp_extra_rows ) ) {
                            $related_html .= '<div class="aura-subcard-tp-details" style="background:rgba(241,245,249,0.7);dark:background:rgba(30,41,59,0.7);padding:6px 10px;border-radius:6px;font-size:11.5px;color:#334155;display:flex;flex-direction:column;gap:3px;">' . implode('', $tp_extra_rows) . '</div>';
                        }
                    } elseif ( $related_user_avatar ) {
                        $concept_map = array(
                            'payment_to_user'       => __( 'Pago a usuario', 'aura-suite' ),
                            'charge_to_user'        => __( 'Cobro a usuario', 'aura-suite' ),
                            'salary'                => __( 'Pago de salario / nómina', 'aura-suite' ),
                            'scholarship'           => __( 'Beca asignada', 'aura-suite' ),
                            'loan_payment'          => __( 'Pago de préstamo', 'aura-suite' ),
                            'refund'                => __( 'Reembolso', 'aura-suite' ),
                            'expense_reimbursement' => __( 'Reembolso de gastos', 'aura-suite' ),
                            'unlinked'              => __( 'Desvinculado', 'aura-suite' ),
                            'none'                  => __( 'Ninguno', 'aura-suite' ),
                        );
                        $concept_label = ! empty( $item->related_user_concept ) ? ( $concept_map[ $item->related_user_concept ] ?? $item->related_user_concept ) : __( 'Usuario Vinculado', 'aura-suite' );

                        $related_html = '<div class="aura-user-badge-card">'
                            . '<img src="' . esc_url( $related_user_avatar ) . '" alt="" width="34" height="34" class="aura-user-avatar" style="width:34px;height:34px;border-radius:999px;object-fit:cover;flex-shrink:0;">'
                            . '<div class="aura-user-info">'
                            . '<span class="aura-user-name">' . esc_html( $related_user_name ) . '</span>'
                            . '<span class="aura-user-sub" style="color:#2563eb;font-weight:600;">' . esc_html( $concept_label ) . '</span>'
                            . '</div></div>';
                    } elseif ( ! empty( $item->recipient_payer ) ) {
                        $related_html = '<div class="aura-user-badge-card"><span class="dashicons dashicons-businessman" style="color:#64748b;margin-right:6px;font-size:24px;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;"></span><span class="aura-user-name">' . esc_html( $item->recipient_payer ) . '</span></div>';
                    } else {
                        $related_html = '<span style="color:#94a3b8;">' . __( 'No especificado', 'aura-suite' ) . '</span>';
                    }
                    echo self::render_meta_item( __( 'Beneficiario / Cliente / Proveedor', 'aura-suite' ), $related_html, 'dashicons-businessperson' );

                    // Área / Programa de la Organización
                    if ( ! empty( $item->area_name ) ) {
                        $a_color   = ! empty( $item->area_color ) ? $item->area_color : '#64748b';
                        $a_icon    = ! empty( $item->area_icon )  ? $item->area_icon  : 'dashicons-grid-view';
                        $a_logo_id = (int) ( $item->area_logo_id ?? 0 );
                        $a_logo_url = $a_logo_id ? ( wp_get_attachment_image_url( $a_logo_id, 'medium' ) ?: wp_get_attachment_url( $a_logo_id ) ?: '' ) : '';

                        if ( $a_logo_url ) {
                            $area_media_html = '<div class="aura-table-thumb-preview aura-img-preview-trigger" data-img-url="' . esc_url( $a_logo_url ) . '" data-img-title="' . esc_attr( $item->area_name ) . '" style="width:34px;height:34px;border-radius:6px;overflow:hidden;flex-shrink:0;box-shadow:0 1px 3px rgba(0,0,0,0.1);"><img src="' . esc_url( $a_logo_url ) . '" alt="" width="34" height="34" style="object-fit:cover;width:100%;height:100%;"></div>';
                        } else {
                            $area_icon_render = '';
                            if ( class_exists( 'Aura_Financial_Categories' ) ) {
                                $area_icon_render = Aura_Financial_Categories::render_icon_html( $a_icon, '#ffffff' );
                            } elseif ( strpos( $a_icon, 'dashicons-' ) !== false || strpos( $a_icon, 'dashicons' ) !== false ) {
                                $area_icon_render = '<span class="dashicons ' . esc_attr( $a_icon ) . '" style="color:#ffffff;font-size:18px;width:18px;height:18px;"></span>';
                            } else {
                                $area_icon_render = '<span class="aura-cat-emoji" style="font-size:18px;line-height:1;">' . esc_html( $a_icon ) . '</span>';
                            }

                            $area_media_html = '<div style="width:34px;height:34px;border-radius:6px;background:' . esc_attr( $a_color ) . ';display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 1px 3px rgba(0,0,0,0.15);">' . $area_icon_render . '</div>';
                        }

                        $area_sub_parts = array();
                        if ( ! empty( $item->area_type ) ) {
                            $area_sub_parts[] = ucfirst( $item->area_type );
                        }
                        if ( ! empty( $item->area_slug ) ) {
                            $area_sub_parts[] = '<code>' . esc_html( $item->area_slug ) . '</code>';
                        }
                        $area_sub_text = ! empty( $area_sub_parts ) ? implode( ' · ', $area_sub_parts ) : __( 'Área Organizacional', 'aura-suite' );

                        $area_html = '<div class="aura-user-badge-card" style="margin-bottom:4px;">'
                            . $area_media_html
                            . '<div class="aura-user-info">'
                            . '<span class="aura-user-name" style="font-size:13px;font-weight:700;color:' . esc_attr( $a_color ) . ';">' . esc_html( $item->area_name ) . '</span>'
                            . '<span class="aura-user-sub">' . $area_sub_text . '</span>'
                            . '</div></div>';

                        if ( ! empty( $item->area_description ) ) {
                            $area_html .= '<div class="aura-subcard-area-desc" style="background:rgba(241,245,249,0.7);dark:background:rgba(30,41,59,0.7);padding:4px 8px;border-radius:5px;font-size:11px;color:#64748b;margin-top:2px;font-style:italic;">' . esc_html( $item->area_description ) . '</div>';
                        }
                    } else {
                        $area_html = '<div class="aura-user-badge-card">'
                            . '<div style="width:34px;height:34px;border-radius:6px;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><span class="dashicons dashicons-grid-view" style="color:#94a3b8;font-size:18px;width:18px;height:18px;"></span></div>'
                            . '<div class="aura-user-info">'
                            . '<span class="aura-user-name" style="color:#94a3b8;font-weight:600;">' . esc_html__( 'General (sin área)', 'aura-suite' ) . '</span>'
                            . '<span class="aura-user-sub">' . esc_html__( 'Asignación global de la organización', 'aura-suite' ) . '</span>'
                            . '</div></div>';
                    }
                    echo self::render_meta_item( __( 'Área / Programa', 'aura-suite' ), $area_html, 'dashicons-grid-view' );
                    ?>
                </div>
            </div>

            <!-- Sub-Card 3: Trazabilidad y Auditoría -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-shield aura-subcard-icon"></span>
                    <h4><?php _e('Auditoría & Trazabilidad', 'aura-suite'); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <?php
                    $audit_val = '<div class="aura-audit-status-badge is-pending"><span class="dashicons dashicons-clock"></span> <span>' . __( 'Pendiente de aprobación', 'aura-suite' ) . '</span></div>';
                    if ( $item->status === 'approved' ) {
                        $approver_name = __( 'Sistema', 'aura-suite' );
                        if ( ! empty( $item->approved_by ) ) {
                            $approver = get_userdata( $item->approved_by );
                            $approver_name = $approver ? $approver->display_name : ( $item->approved_by == $item->created_by ? __( 'Auto-aprobada', 'aura-suite' ) : __( 'Auditor / Administrador', 'aura-suite' ) );
                        }
                        $approved_time_txt = ! empty( $item->approved_at ) ? ' · ' . date_i18n( 'd/m/Y H:i', strtotime( $item->approved_at ) ) : '';
                        $audit_val = '<div class="aura-audit-status-badge is-approved"><span class="dashicons dashicons-yes-alt"></span> <span>' . sprintf( __( 'Aprobado por %s%s', 'aura-suite' ), esc_html( $approver_name ), esc_html( $approved_time_txt ) ) . '</span></div>';
                    } elseif ( $item->status === 'rejected' ) {
                        $reason = ! empty( $item->rejection_reason ) ? esc_html( $item->rejection_reason ) : __( 'No especificado', 'aura-suite' );
                        $audit_val = '<div class="aura-audit-status-badge is-rejected"><span class="dashicons dashicons-dismiss"></span> <span>' . sprintf( __( 'Rechazado: %s', 'aura-suite' ), $reason ) . '</span></div>';
                    }
                    echo self::render_meta_item( __( 'Estado del Flujo', 'aura-suite' ), $audit_val, 'dashicons-marker' );

                    // Timestamps
                    if ( ! empty( $item->created_at ) ) {
                        $created_ts_val = '<span style="font-size:12px;color:#475569;">' . date_i18n( 'd/m/Y H:i:s', strtotime( $item->created_at ) ) . '</span>';
                        echo self::render_meta_item( __( 'Fecha de Registro', 'aura-suite' ), $created_ts_val, 'dashicons-clock' );
                    }
                    if ( ! empty( $item->updated_at ) && $item->updated_at !== $item->created_at ) {
                        $updated_ts_val = '<span style="font-size:12px;color:#475569;">' . date_i18n( 'd/m/Y H:i:s', strtotime( $item->updated_at ) ) . '</span>';
                        echo self::render_meta_item( __( 'Última Edición', 'aura-suite' ), $updated_ts_val, 'dashicons-update' );
                    }

                    // Integración de Módulos
                    if ( ! empty( $item->related_module ) ) {
                        $mod_names = array(
                            'inventory' => __( '📦 Inventario de Equipos', 'aura-suite' ),
                            'library'   => __( '📚 Biblioteca', 'aura-suite' ),
                            'vehicles'  => __( '🚗 Vehículos / Flota', 'aura-suite' ),
                            'forms'     => __( '📝 Formularios', 'aura-suite' ),
                            'students'  => __( '🎓 Estudiantes', 'aura-suite' ),
                        );
                        $mod_label = $mod_names[ $item->related_module ] ?? ucfirst( $item->related_module );
                        $mod_detail = ! empty( $item->related_item_id ) ? ' (ID #' . intval( $item->related_item_id ) . ')' : '';
                        $mod_action = ! empty( $item->related_action ) ? ' · ' . esc_html( $item->related_action ) : '';
                        $mod_val = '<span class="aura-meta-badge" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:700;">' . esc_html( $mod_label . $mod_detail . $mod_action ) . '</span>';
                        echo self::render_meta_item( __( 'Módulo Vinculado', 'aura-suite' ), $mod_val, 'dashicons-networking' );
                    }

                    if ( ! empty( $item->import_batch_id ) ) {
                        $batch_val = '<code style="font-size:11px;">' . esc_html( $item->import_batch_id ) . '</code>';
                        echo self::render_meta_item( __( 'Lote de Importación', 'aura-suite' ), $batch_val, 'dashicons-upload' );
                    }
                    ?>
                </div>
            </div>

            <!-- Sub-Card 4: Respaldo Digital / Comprobante -->
            <?php
            $child_receipts = self::get_transaction_receipt_files( $item );
            $child_receipts_count = count( $child_receipts );
            $child_data_files = array_map( function( $rf ) {
                return [
                    'url'           => $rf['url'],
                    'name'          => $rf['name'],
                    'preview_url'   => $rf['preview_url'],
                    'download_url'  => $rf['download_url'],
                    'thumbnail_url' => $rf['thumbnail_url'],
                    'is_drive'      => $rf['is_drive'],
                    'is_pdf'        => $rf['is_pdf'],
                    'is_img'        => $rf['is_img'],
                ];
            }, $child_receipts );
            ?>
            <div class="aura-child-subcard aura-subcard-receipts">
                <div class="aura-subcard-header aura-subcard-receipts-header">
                    <div class="aura-subcard-title-wrap">
                        <span class="dashicons dashicons-media-document aura-subcard-icon" style="color:#2563eb;"></span>
                        <h4 title="<?php esc_attr_e( 'Respaldo Digital / Comprobante', 'aura-suite' ); ?>"><?php _e('Respaldo Digital', 'aura-suite'); ?></h4>
                    </div>
                    <?php if ( $child_receipts_count > 0 ) : ?>
                        <span class="aura-receipts-count-badge" title="<?php echo sprintf( _n( '%d comprobante adjunto', '%d comprobantes adjuntos', $child_receipts_count, 'aura-suite' ), $child_receipts_count ); ?>">
                            <?php echo sprintf( _n( '%d comprobante', '%d comprobantes', $child_receipts_count, 'aura-suite' ), $child_receipts_count ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="aura-subcard-body">
                    <?php if ( $child_receipts_count > 0 ) : ?>
                        <div class="aura-receipts-child-list">
                            <?php foreach ( $child_receipts as $rf ) : 
                                $rf_name = $rf['name'];
                                $rf_is_drive = $rf['is_drive'];
                                $rf_is_pdf = $rf['is_pdf'];
                                $rf_is_img = $rf['is_img'];
                                $type_label = $rf_is_pdf ? 'PDF' : ( $rf_is_img ? 'Imagen' : 'Doc' );
                            ?>
                                <div class="aura-child-receipt-item">
                                    <div class="aura-child-receipt-top">
                                        <div class="aura-child-receipt-thumb aura-open-receipt-viewer" 
                                             data-receipt-url="<?php echo esc_url( $rf['url'] ); ?>" 
                                             data-preview-url="<?php echo esc_url( $rf['preview_url'] ); ?>" 
                                             data-download-url="<?php echo esc_url( $rf['download_url'] ); ?>" 
                                             data-is-drive="<?php echo $rf_is_drive ? '1' : '0'; ?>" 
                                             data-is-pdf="<?php echo $rf_is_pdf ? '1' : '0'; ?>" 
                                             data-is-img="<?php echo $rf_is_img ? '1' : '0'; ?>" 
                                             data-title="<?php echo esc_attr( $rf_name ); ?>"
                                             title="<?php esc_attr_e( 'Clic para previsualizar en visor modal', 'aura-suite' ); ?>">
                                            <?php if ( $rf_is_img || $rf_is_drive ) : ?>
                                                <img src="<?php echo esc_url( $rf['thumbnail_url'] ); ?>" alt="" class="aura-receipt-thumb-img">
                                                <?php if ( $rf_is_drive ) : ?>
                                                    <span class="aura-thumb-cloud-badge" title="Almacenado en Google Drive">☁️</span>
                                                <?php endif; ?>
                                            <?php elseif ( $rf_is_pdf ) : ?>
                                                <div class="aura-thumb-fallback-icon fallback-pdf">
                                                    <span class="dashicons dashicons-media-document"></span>
                                                    <span class="aura-thumb-label">PDF</span>
                                                </div>
                                            <?php else : ?>
                                                <div class="aura-thumb-fallback-icon fallback-doc">
                                                    <span class="dashicons dashicons-paperclip"></span>
                                                    <span class="aura-thumb-label">DOC</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="aura-thumb-hover-overlay">
                                                <span class="dashicons dashicons-search"></span>
                                            </div>
                                        </div>
                                        <div class="aura-child-receipt-info">
                                            <div class="aura-child-receipt-title" title="<?php echo esc_attr( $rf_name ); ?>">
                                                <?php echo esc_html( $rf_name ); ?>
                                            </div>
                                            <div class="aura-child-receipt-badges">
                                                <?php if ( $rf_is_drive ) : ?>
                                                    <span class="aura-receipt-badge-pill badge-drive">
                                                        <span class="dashicons dashicons-cloud"></span> Google Drive
                                                    </span>
                                                <?php else : ?>
                                                    <span class="aura-receipt-badge-pill badge-local">
                                                        <span class="dashicons dashicons-admin-home"></span> Local
                                                    </span>
                                                <?php endif; ?>
                                                <span class="aura-receipt-badge-pill badge-format format-<?php echo strtolower( $type_label ); ?>">
                                                    <?php echo esc_html( $type_label ); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="aura-child-receipt-actions">
                                        <button type="button" class="button button-primary aura-open-receipt-viewer aura-btn-preview" 
                                                data-receipt-url="<?php echo esc_url( $rf['url'] ); ?>" 
                                                data-preview-url="<?php echo esc_url( $rf['preview_url'] ); ?>" 
                                                data-download-url="<?php echo esc_url( $rf['download_url'] ); ?>" 
                                                data-is-drive="<?php echo $rf_is_drive ? '1' : '0'; ?>" 
                                                data-is-pdf="<?php echo $rf_is_pdf ? '1' : '0'; ?>" 
                                                data-is-img="<?php echo $rf_is_img ? '1' : '0'; ?>" 
                                                data-title="<?php echo esc_attr( $rf_name ); ?>"
                                                title="<?php esc_attr_e( 'Previsualizar en Visor Modal', 'aura-suite' ); ?>">
                                            <span class="dashicons dashicons-visibility"></span> <?php _e( 'Ver', 'aura-suite' ); ?>
                                        </button>
                                        <?php if ( $rf_is_drive ) : ?>
                                            <a href="<?php echo esc_url( $rf['url'] ); ?>" target="_blank" class="button button-secondary aura-btn-secondary" title="<?php esc_attr_e( 'Abrir directamente en Google Drive', 'aura-suite' ); ?>">
                                                <span class="dashicons dashicons-cloud"></span> Drive <span class="dashicons dashicons-external" style="font-size:11px;width:11px;height:11px;line-height:11px;"></span>
                                            </a>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url( $rf['download_url'] ); ?>" download class="button button-secondary aura-btn-secondary" title="<?php esc_attr_e( 'Descargar comprobante local', 'aura-suite' ); ?>">
                                                <span class="dashicons dashicons-download"></span> <?php _e( 'Descargar', 'aura-suite' ); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ( $child_receipts_count > 1 ) : ?>
                                <button type="button" class="button button-secondary aura-open-receipt-viewer aura-btn-gallery" 
                                        data-files="<?php echo esc_attr( wp_json_encode( $child_data_files ) ); ?>"
                                        data-title="<?php echo esc_attr( sprintf( __( 'Transacción #%d - Galería de Comprobantes', 'aura-suite' ), $item->id ) ); ?>">
                                    <span class="dashicons dashicons-images-alt2"></span>
                                    <?php printf( __( 'Ver los %d comprobantes en galería', 'aura-suite' ), $child_receipts_count ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="aura-empty-receipt-card">
                            <div class="aura-empty-receipt-icon">
                                <span class="dashicons dashicons-media-default"></span>
                            </div>
                            <div class="aura-empty-receipt-info">
                                <div class="aura-empty-receipt-title"><?php _e( 'Sin comprobante adjunto', 'aura-suite' ); ?></div>
                                <div class="aura-empty-receipt-sub"><?php _e( 'No se ha cargado recibo ni factura para este movimiento.', 'aura-suite' ); ?></div>
                            </div>
                            <?php if ( current_user_can( 'aura_finance_edit_all' ) || ( current_user_can( 'aura_finance_edit_own' ) && $item->created_by == get_current_user_id() ) ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-edit-transaction&id=' . $item->id ) ); ?>" class="button button-small aura-btn-attach">
                                    <span class="dashicons dashicons-upload"></span> <?php _e( 'Adjuntar', 'aura-suite' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sub-Card 4 (Full Width): Concepto, Descripción Detallada y Notas -->
            <div class="aura-child-subcard aura-subcard-full">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-edit aura-subcard-icon"></span>
                    <h4><?php _e('Concepto, Descripción y Observaciones Internas', 'aura-suite'); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:14px;">
                        <div>
                            <strong style="display:block;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <span class="dashicons dashicons-media-text" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> <?php _e('Descripción del Movimiento', 'aura-suite'); ?>
                            </strong>
                            <div class="aura-child-notes-box">
                                <?php 
                                echo ! empty( $item->description ) ? nl2br( esc_html( $item->description ) ) : '<em class="aura-text-muted">' . __( 'Sin descripción registrada.', 'aura-suite' ) . '</em>';
                                ?>
                            </div>
                        </div>
                        <div>
                            <strong style="display:block;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                                <span class="dashicons dashicons-admin-comments" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> <?php _e('Notas y Observaciones Internas', 'aura-suite'); ?>
                            </strong>
                            <div class="aura-child-notes-box">
                                <?php 
                                echo ! empty( $item->notes ) ? nl2br( esc_html( $item->notes ) ) : '<em class="aura-text-muted">' . __( 'Sin notas adicionales.', 'aura-suite' ) . '</em>';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Dock de Acciones Rápidas Inferior -->
        <div class="aura-child-action-dock">
            <div class="aura-action-dock-left">
                <button type="button" class="button button-secondary aura-btn-dock view-transaction view-transaction-details" data-transaction-id="<?php echo esc_attr( $item->id ); ?>" data-id="<?php echo esc_attr( $item->id ); ?>">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e( 'Ver Detalles Completos', 'aura-suite' ); ?>
                </button>

                <?php if ( current_user_can( 'aura_finance_edit_all' ) || ( current_user_can( 'aura_finance_edit_own' ) && $item->created_by == get_current_user_id() ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-edit-transaction&id=' . $item->id ) ); ?>" class="button button-secondary aura-btn-dock">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e( 'Editar', 'aura-suite' ); ?>
                </a>
                <?php endif; ?>
            </div>

            <div class="aura-action-dock-right">
                <?php if ( $item->status === 'pending' && current_user_can( 'aura_finance_approve' ) ) : ?>
                <button type="button" class="button button-primary aura-btn-dock aura-btn-dock-approve aura-quick-approve" data-id="<?php echo esc_attr( $item->id ); ?>">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e( 'Aprobar', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button button-secondary aura-btn-dock aura-btn-dock-reject aura-quick-reject" data-id="<?php echo esc_attr( $item->id ); ?>">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e( 'Rechazar', 'aura-suite' ); ?>
                </button>
                <?php endif; ?>

                <?php if ( current_user_can( 'aura_finance_delete_all' ) || ( current_user_can( 'aura_finance_delete_own' ) && $item->created_by == get_current_user_id() ) ) : ?>
                <button type="button" class="button button-link-delete aura-btn-dock aura-btn-dock-delete aura-delete-transaction" data-id="<?php echo esc_attr( $item->id ); ?>">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e( 'Eliminar', 'aura-suite' ); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

// Inicializar
Aura_Financial_Transactions_List::init();
