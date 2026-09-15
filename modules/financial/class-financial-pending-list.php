<?php
/**
 * Clase para gestionar el Listado de Transacciones Pendientes de Aprobación
 * Extiende Aura_List_Table para soporte nativo de Filas Expandibles (Child Rows) y diseño Responsive
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar clase base de Aura si no está disponible
if (!class_exists('Aura_List_Table')) {
    require_once AURA_PLUGIN_DIR . 'modules/common/class-aura-list-table.php';
}

/**
 * Clase Aura_Financial_Pending_List
 * 
 * Gestiona el listado de transacciones pendientes de aprobación con Child Rows y Responsive
 */
class Aura_Financial_Pending_List extends Aura_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'transacción',
            'plural'   => 'transacciones',
            'ajax'     => false
        ));
    }
    
    /**
     * Obtener columnas de la tabla con ayudas contextuales estándar
     */
    public function get_columns() {
        $columns = array(
            'cb'              => '<input type="checkbox" />',
            'id'              => __('#', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Identificador único y botón para desplegar detalles adicionales de la transacción.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'transaction_date'=> __('Fecha', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Fecha en que se efectuó contablemente el movimiento financiero.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'type'            => __('Tipo', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Clasificación contable: Entrada (+) o Salida (-) de fondos.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'category'        => __('Categoría', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Rubro o clasificación presupuestaria vinculada al movimiento.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'description'     => __('Descripción', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Concepto u objeto detallado de la transacción financiera.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'amount'          => __('Monto', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Importe total monetario registrado para esta transacción.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'created_by'      => __('Creado por', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Usuario que registró inicialmente la transacción en la plataforma.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'created_at'      => __('Registrado el', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Fecha y hora exacta de creación del registro en el sistema.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>',
            'acciones'        => __('Acciones', 'aura-suite') . ' <span class="aura-help-icon" data-tooltip="' . esc_attr__('Herramientas de auditoría: ver detalles, comprobantes y aprobar o rechazar.', 'aura-suite') . '"><span class="dashicons dashicons-editor-help"></span></span>'
        );
        
        return $columns;
    }
    
    /**
     * Columnas sortables
     */
    public function get_sortable_columns() {
        return array(
            'transaction_date' => array('transaction_date', true),
            'amount'           => array('amount', false),
            'created_at'       => array('created_at', true)
        );
    }
    
    /**
     * Acciones masivas
     */
    public function get_bulk_actions() {
        if (!current_user_can('aura_finance_approve')) {
            return array();
        }
        
        return array(
            'bulk_approve' => __('Aprobar seleccionadas', 'aura-suite'),
            'bulk_reject'  => __('Rechazar seleccionadas', 'aura-suite')
        );
    }
    
    /**
     * Checkbox para selección
     */
    public function column_cb($item) {
        // Solo permitir checkbox si el usuario puede aprobar
        if (!current_user_can('aura_finance_approve')) {
            return '';
        }
        
        // No mostrar checkbox para propias transacciones
        if ($item['created_by'] == get_current_user_id()) {
            return '';
        }
        
        return sprintf(
            '<input type="checkbox" name="transaction_ids[]" value="%d" />',
            $item['id']
        );
    }
    
    /**
     * Columna Número (#ID) con distintivo visual compacto de tipo, tooltip descriptivo interactivo y botón Toggle
     */
    public function column_id( $item ) {
        $type = strtolower( (string) ($item['transaction_type'] ?? 'expense') );

        if ( $type === 'income' ) {
            $icon       = '↑';
            $color      = '#059669'; // Emerald
            $bg         = '#ecfdf5';
            $border     = '#a7f3d0';
            $badge_text = __('Ingreso', 'aura-suite');
            $type_label = __('Ingreso Financiero (+)', 'aura-suite');
        } elseif ( $type === 'capital' ) {
            $icon       = '🏛️';
            $color      = '#7c3aed'; // Violet
            $bg         = '#f5f3ff';
            $border     = '#ddd6fe';
            $badge_text = __('Capital', 'aura-suite');
            $type_label = __('Gasto de Capital / CapEx', 'aura-suite');
        } elseif ( $type === 'transfer' ) {
            $icon       = '⇄';
            $color      = '#2563eb'; // Blue
            $bg         = '#eff6ff';
            $border     = '#bfdbfe';
            $badge_text = __('Transfer', 'aura-suite');
            $type_label = __('Transferencia entre cuentas', 'aura-suite');
        } else {
            $icon       = '↓';
            $color      = '#dc2626'; // Red
            $bg         = '#fef2f2';
            $border     = '#fecaca';
            $badge_text = __('Egreso', 'aura-suite');
            $type_label = __('Egreso Financiero (-)', 'aura-suite');
        }

        $desc = ! empty( $item['description'] ) ? $item['description'] : __( 'Sin descripción registrada', 'aura-suite' );
        $cat  = ! empty( $item['category_name'] ) ? $item['category_name'] : '';

        // Texto plano para atributo data-tooltip
        $tooltip_text = sprintf(
            "#%d · %s — %s%s",
            $item['id'],
            $type_label,
            $desc,
            $cat ? " (" . $cat . ")" : ""
        );

        $toggle_btn = $this->render_row_toggle( $item['id'] );

        return sprintf(
            '<div class="aura-id-cell-wrapper" style="display:inline-flex;align-items:center;gap:4px;">'
            . '%s'
            . '<div class="aura-tooltip-wrap aura-txn-pill-tooltip-wrap" tabindex="0">'
            . '<div class="aura-txn-id-pill aura-has-tooltip" data-tooltip="%s" style="background:%s;border:1px solid %s;">'
            . '<span class="txn-type-icon" style="color:%s;">%s</span>'
            . '<span class="txn-id-num">#%d</span>'
            . '<span class="txn-type-tag" style="color:%s;">%s</span>'
            . '</div>'
            . '<div class="aura-tooltip-box aura-txn-id-tooltip-box">'
            . '<div class="aura-txn-tt-header"><span style="color:%s;font-weight:bold;">%s</span> #%d · %s</div>'
            . '<div class="aura-txn-tt-desc">%s</div>'
            . ( $cat ? '<div class="aura-txn-tt-cat"><span class="dashicons dashicons-tag"></span> ' . esc_html( $cat ) . '</div>' : '' )
            . '</div>'
            . '</div>'
            . '</div>',
            $toggle_btn,
            esc_attr( $tooltip_text ),
            esc_attr( $bg ),
            esc_attr( $border ),
            esc_attr( $color ),
            $icon,
            $item['id'],
            esc_attr( $color ),
            esc_html( $badge_text ),
            esc_attr( $color ),
            $icon,
            $item['id'],
            esc_html( $badge_text ),
            esc_html( $desc )
        );
    }

    /**
     * Columna: Fecha de transacción
     */
    public function column_transaction_date($item) {
        $date = new DateTime($item['transaction_date']);
        $now  = new DateTime();
        $diff = $now->diff($date);
        
        $date_str = '<strong>' . $date->format('d/m/Y') . '</strong>';
        
        // Indicador de antigüedad con tooltip
        if ($diff->days > 30) {
            $date_str .= ' <span class="aura-tooltip-wrap"><span class="old-pending aura-help-icon" style="color:#ef4444;"><span class="dashicons dashicons-warning"></span></span><span class="aura-tooltip-box">' . esc_html__('Más de 30 días pendiente', 'aura-suite') . '</span></span>';
        } elseif ($diff->days > 7) {
            $date_str .= ' <span class="aura-tooltip-wrap"><span class="pending-warning aura-help-icon" style="color:#f59e0b;"><span class="dashicons dashicons-clock"></span></span><span class="aura-tooltip-box">' . sprintf(esc_html__('%d días pendiente', 'aura-suite'), $diff->days) . '</span></span>';
        }
        
        return $date_str;
    }
    
    /**
     * Columna: Tipo con Tooltip Descriptivo Interactivo
     */
    public function column_type($item) {
        $is_income   = ($item['transaction_type'] === 'income');
        $type_label  = $is_income ? __('Ingreso', 'aura-suite') : __('Egreso', 'aura-suite');
        $type_class  = $is_income ? 'type-income' : 'type-expense';
        $icon        = $is_income ? 'arrow-down-alt' : 'arrow-up-alt';
        
        $type_title  = $is_income ? __('Ingreso Financiero', 'aura-suite') : __('Egreso Financiero', 'aura-suite');
        $type_nature = $is_income ? __('Entrada de fondos al flujo de caja contable.', 'aura-suite') : __('Salida o desembolso monetario a justificar.', 'aura-suite');
        $description = !empty($item['description']) ? esc_html($item['description']) : __('Sin descripción registrada', 'aura-suite');
        
        $tooltip_content = '<div class="aura-type-tooltip-content">'
            . '<div class="aura-type-tooltip-header">'
            . '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span> '
            . '<strong>' . esc_html($type_title) . '</strong>'
            . '</div>'
            . '<div class="aura-type-tooltip-desc"><strong>' . esc_html__('Descripción:', 'aura-suite') . '</strong> ' . $description . '</div>'
            . '<div class="aura-type-tooltip-nature">' . esc_html($type_nature) . '</div>'
            . '</div>';

        return sprintf(
            '<div class="aura-tooltip-wrap aura-type-wrap" tabindex="0">'
            . '<span class="transaction-type %s"><span class="dashicons dashicons-%s"></span> %s</span>'
            . '<div class="aura-tooltip-box aura-type-tooltip-box">%s</div>'
            . '</div>',
            esc_attr($type_class),
            esc_attr($icon),
            esc_html($type_label),
            $tooltip_content
        );
    }
    
    /**
     * Columna: Categoría
     */
    public function column_category($item) {
        if (empty($item['category_name'])) {
            return '<em>' . __('Sin categoría', 'aura-suite') . '</em>';
        }
        
        $color = !empty($item['category_color']) ? $item['category_color'] : '#64748b';
        $icon  = !empty($item['category_icon']) ? $item['category_icon'] : '📁';
        $icon_html = class_exists('Aura_Financial_Categories') ? Aura_Financial_Categories::render_icon_html($icon, $color) : '';
        
        return sprintf(
            '<span class="category-badge" style="display:inline-flex; align-items:center; gap:4px;">
                %s <span>%s</span>
            </span>',
            $icon_html,
            esc_html($item['category_name'])
        );
    }
    
    /**
     * Columna: Descripción
     */
    public function column_description($item) {
        $description = esc_html($item['description']);
        
        // Acciones de fila
        $actions = array();
        $is_own_transaction = ($item['created_by'] == get_current_user_id());
        
        if (current_user_can('aura_finance_approve') && !$is_own_transaction) {
            $actions['approve'] = sprintf(
                '<a href="#" class="approve-transaction" data-id="%d">%s</a>',
                $item['id'],
                __('Aprobar', 'aura-suite')
            );
            
            $actions['reject'] = sprintf(
                '<a href="#" class="reject-transaction" data-id="%d">%s</a>',
                $item['id'],
                __('Rechazar', 'aura-suite')
            );
        }
        
        $actions['view'] = sprintf(
            '<a href="#" class="view-transaction" data-transaction-id="%d">%s</a>',
            $item['id'],
            __('Ver detalles', 'aura-suite')
        );
        
        if ($is_own_transaction && current_user_can('aura_finance_edit_own')) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=aura-financial-edit-transaction&id=' . $item['id']),
                __('Editar', 'aura-suite')
            );
        }
        
        $own_tag = '';
        if ($is_own_transaction) {
            $own_tag = ' <span class="aura-tooltip-wrap"><span class="own-transaction-label aura-help-icon" style="color:#2563eb;"><span class="dashicons dashicons-admin-users"></span></span><span class="aura-tooltip-box">' . esc_html__('Tu transacción (no puedes auto-aprobarte)', 'aura-suite') . '</span></span>';
        }
        
        return '<strong>' . $description . '</strong>' . $own_tag . $this->row_actions($actions);
    }
    
    /**
     * Columna: Acciones
     */
    public function column_acciones($item) {
        $is_own_transaction = ($item['created_by'] == get_current_user_id());
        $html = '<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">';
        
        $html .= sprintf(
            '<button type="button" class="button button-small view-transaction aura-view-transaction-btn" data-transaction-id="%d">'
            . '<span class="dashicons dashicons-visibility aura-view-transaction-btn__icon"></span> %s'
            . '</button>',
            $item['id'],
            __('Detalles', 'aura-suite')
        );

        if ( ! empty( $item['receipt_file'] ) ) {
            $voucher_raw = $item['receipt_file'];
            $voucher_files = [];
            if ( is_string( $voucher_raw ) && ( str_starts_with( trim( $voucher_raw ), '[' ) || str_starts_with( trim( $voucher_raw ), '{' ) ) ) {
                $decoded = json_decode( $voucher_raw, true );
                if ( is_array( $decoded ) ) {
                    $voucher_files = $decoded;
                }
            }
            if ( empty( $voucher_files ) ) {
                $voucher_files = [ $voucher_raw ];
            }
            $first_item = $voucher_files[0];
            $voucher_file = is_array( $first_item ) ? ( $first_item['url'] ?? ( $first_item['file_url'] ?? '' ) ) : (string) $first_item;

            $is_drive = class_exists( 'Aura_Drive_Manager' ) && Aura_Drive_Manager::is_drive_url( $first_item );
            if ( $is_drive ) {
                $file_id = Aura_Drive_Manager::extract_file_id( $first_item );
                $preview_url = Aura_Drive_Manager::get_preview_url( $file_id );
                $thumb_url = Aura_Drive_Manager::get_thumbnail_url( $file_id );
                $download_url = Aura_Drive_Manager::get_download_url( $file_id );
                $html .= sprintf(
                    '<button type="button" class="button button-small aura-open-receipt-viewer" data-receipt-url="%s" data-preview-url="%s" data-download-url="%s" data-is-drive="1" data-is-img="1" data-title="%s" title="%s" style="color:#2563eb;border-color:#bfdbfe;background:#eff6ff;"><span class="dashicons dashicons-cloud"></span></button>',
                    esc_url( $voucher_file ),
                    esc_url( $preview_url ),
                    esc_url( $download_url ),
                    esc_attr( sprintf( __( 'Transacción #%d - Comprobante Google Drive', 'aura-suite' ), $item['id'] ) ),
                    esc_attr__( 'Ver comprobante en Google Drive', 'aura-suite' )
                );
            } else {
                $is_pdf = preg_match( '/\.pdf($|\?)/i', $voucher_file );
                $file_url = content_url( 'uploads/aura-finance/receipts/' . $voucher_file );
                if ( ! file_exists( WP_CONTENT_DIR . '/uploads/aura-finance/receipts/' . $voucher_file ) ) {
                    $file_url = content_url( 'uploads/aura-receipts/' . $voucher_file );
                }
                $html .= sprintf(
                    '<button type="button" class="button button-small aura-open-receipt-viewer" data-receipt-url="%s" data-is-drive="0" data-is-pdf="%d" data-is-img="%d" data-title="%s" title="%s" style="color:#059669;border-color:#a7f3d0;background:#ecfdf5;"><span class="dashicons %s"></span></button>',
                    esc_url( $file_url ),
                    $is_pdf ? 1 : 0,
                    $is_pdf ? 0 : 1,
                    esc_attr( sprintf( __( 'Transacción #%d - Comprobante', 'aura-suite' ), $item['id'] ) ),
                    esc_attr__( 'Ver comprobante adjunto', 'aura-suite' ),
                    $is_pdf ? 'dashicons-media-document' : 'dashicons-paperclip'
                );
            }
        }

        if (current_user_can('aura_finance_approve') && !$is_own_transaction) {
            $html .= sprintf(
                '<button type="button" class="button button-small button-primary approve-transaction" data-id="%d" title="%s">'
                . '<span class="dashicons dashicons-yes-alt" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:2px;"></span> %s'
                . '</button>',
                $item['id'],
                esc_attr__('Aprobar Transacción', 'aura-suite'),
                __('Aprobar', 'aura-suite')
            );
            $html .= sprintf(
                '<button type="button" class="button button-small reject-transaction" data-id="%d" title="%s" style="color:#dc2626;border-color:#fecaca;">'
                . '<span class="dashicons dashicons-dismiss" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:2px;"></span> %s'
                . '</button>',
                $item['id'],
                esc_attr__('Rechazar Transacción', 'aura-suite'),
                __('Rechazar', 'aura-suite')
            );
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Columna: Monto
     */
    public function column_amount($item) {
        $amount = floatval($item['amount']);
        $formatted = number_format($amount, 2, ',', '.');
        $class = $item['transaction_type'] === 'income' ? 'amount-income' : 'amount-expense';
        
        // Indicador de monto alto (>10,000)
        $high_amount_threshold = get_option('aura_finance_high_amount_threshold', 10000);
        $is_high_amount = $amount >= $high_amount_threshold;
        
        $high_tag = '';
        if ($is_high_amount) {
            $high_tag = ' <span class="aura-tooltip-wrap"><span class="high-amount-indicator aura-help-icon" style="color:#d97706;"><span class="dashicons dashicons-money-alt"></span></span><span class="aura-tooltip-box">' . esc_html__('Monto relevante alto', 'aura-suite') . '</span></span>';
        }

        return sprintf(
            '<span class="transaction-amount %s">$%s</span>%s',
            $class,
            $formatted,
            $high_tag
        );
    }
    
    /**
     * Columna: Creado por
     */
    public function column_created_by($item) {
        $user = get_userdata($item['created_by']);
        
        if (!$user) {
            return '<em>' . __('Usuario desconocido', 'aura-suite') . '</em>';
        }
        
        $is_current_user = ($item['created_by'] == get_current_user_id());
        $avatar_url = get_avatar_url($user->ID, array('size' => 24));
        
        return sprintf(
            '<div class="aura-user-badge-card" style="display:inline-flex;align-items:center;gap:6px;">
                <img src="%s" class="aura-user-avatar" width="22" height="22" style="border-radius:50%%;" />
                <span class="creator-name %s">%s</span>
                %s
            </div>',
            esc_url($avatar_url),
            $is_current_user ? 'current-user' : '',
            esc_html($user->display_name),
            $is_current_user ? ' <span class="you-label" style="font-size:10px;background:#eff6ff;color:#2563eb;padding:1px 5px;border-radius:10px;font-weight:700;">' . __('Tú', 'aura-suite') . '</span>' : ''
        );
    }
    
    /**
     * Columna: Registrado el
     */
    public function column_created_at($item) {
        $date = new DateTime($item['created_at']);
        return $date->format('d/m/Y H:i');
    }
    
    /**
     * Fila Hija Expandible (Child Row) con diseño WOW
     *
     * @param object|array $item
     */
    protected function render_child_row_content( $item ) {
        $is_income     = ( $item['transaction_type'] === 'income' );
        $is_own        = ( $item['created_by'] == get_current_user_id() );
        $date          = new DateTime( $item['transaction_date'] );
        $created_date  = new DateTime( $item['created_at'] );
        $now           = new DateTime();
        $diff          = $now->diff( $date );
        $amount_fmt    = '$' . number_format( (float) $item['amount'], 2, ',', '.' );
        $user          = get_userdata( $item['created_by'] );
        $creator_name  = $user ? $user->display_name : 'Sistema';
        $creator_email = $user ? $user->user_email : '';
        $avatar_url    = $user ? get_avatar_url( $user->ID, array( 'size' => 36 ) ) : '';
        ?>
        <!-- Hero Bar Superior -->
        <div class="aura-child-hero-bar">
            <div class="aura-child-hero-left">
                <div class="aura-type-pill <?php echo $is_income ? 'income' : 'expense'; ?>">
                    <?php echo $is_income ? '↙' : '↗'; ?>
                </div>
                <div class="aura-child-hero-titles">
                    <h3 class="aura-child-hero-title">
                        <?php echo esc_html( $item['description'] ); ?>
                        <span class="aura-code-pill">#<?php echo esc_html( $item['id'] ); ?></span>
                    </h3>
                    <div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap;">
                        <span class="aura-pill-badge" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;">
                            <span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html( $date->format('d/m/Y') ); ?>
                        </span>
                        <?php if ( ! empty( $item['category_name'] ) ) : ?>
                        <span class="aura-pill-badge" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
                            <span class="dashicons dashicons-tag"></span> <?php echo esc_html( $item['category_name'] ); ?>
                        </span>
                        <?php endif; ?>
                        <span class="aura-pill-badge" style="background:#fffbeb;color:#b45309;border:1px solid #fde68a;">
                            <span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Pendiente de Validación', 'aura-suite' ); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="aura-child-hero-right">
                <div class="aura-child-amount-card" style="text-align:right;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">
                        <?php echo $is_income ? esc_html__( 'Monto a Recibir', 'aura-suite' ) : esc_html__( 'Monto a Egresar', 'aura-suite' ); ?>
                    </span>
                    <span class="aura-ud-amount <?php echo $is_income ? 'income' : 'expense'; ?>" style="font-size:18px;font-weight:800;">
                        <?php echo $is_income ? '+' : '-'; ?><?php echo esc_html( $amount_fmt ); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Grid de Sub-Tarjetas -->
        <div class="aura-child-sections-grid">

            <!-- Sub-Card 1: Datos Principales -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-info aura-subcard-icon"></span>
                    <h4><?php esc_html_e( 'Detalles Financieros', 'aura-suite' ); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <?php echo self::render_meta_item( __( 'Tipo de Operación', 'aura-suite' ), $is_income ? __( 'Ingreso Financiero', 'aura-suite' ) : __( 'Egreso Financiero', 'aura-suite' ), 'dashicons-randomize' ); ?>
                    <?php echo self::render_meta_item( __( 'Categoría', 'aura-suite' ), ! empty( $item['category_name'] ) ? esc_html( $item['category_name'] ) : __( 'Sin categoría', 'aura-suite' ), 'dashicons-category' ); ?>
                    <?php if ( ! empty( $item['reference_number'] ) ) : ?>
                    <?php echo self::render_meta_item( __( 'Referencia / Folio', 'aura-suite' ), '<span class="aura-code-pill">' . esc_html( $item['reference_number'] ) . '</span>', 'dashicons-media-document' ); ?>
                    <?php endif; ?>
                    <?php if ( ! empty( $item['recipient_payer'] ) ) : ?>
                    <?php echo self::render_meta_item( __( 'Tercero Relacionado', 'aura-suite' ), esc_html( $item['recipient_payer'] ), 'dashicons-businessperson' ); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sub-Card 2: Creador / Responsables -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-admin-users aura-subcard-icon"></span>
                    <h4><?php esc_html_e( 'Registrado Por', 'aura-suite' ); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <div class="aura-user-badge-card" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <?php if ( $avatar_url ) : ?>
                        <img src="<?php echo esc_url( $avatar_url ); ?>" width="32" height="32" class="aura-user-avatar" style="border-radius:50%;" />
                        <?php endif; ?>
                        <div class="aura-user-info">
                            <strong class="aura-user-name"><?php echo esc_html( $creator_name ); ?></strong>
                            <div class="aura-user-sub" style="font-size:11px;color:#64748b;"><?php echo esc_html( $creator_email ); ?></div>
                        </div>
                    </div>
                    <?php echo self::render_meta_item( __( 'Fecha de Registro', 'aura-suite' ), esc_html( $created_date->format('d/m/Y H:i') ), 'dashicons-clock' ); ?>
                </div>
            </div>

            <!-- Sub-Card 3: Auditoría y Tiempos -->
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-shield aura-subcard-icon"></span>
                    <h4><?php esc_html_e( 'Estado de Auditoría', 'aura-suite' ); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <div class="aura-audit-status-badge is-pending" style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:8px;background:#fffbeb;color:#b45309;border:1px solid #fde68a;font-weight:700;font-size:12px;">
                        <span class="dashicons dashicons-clock"></span>
                        <span><?php esc_html_e( 'En espera de aprobación', 'aura-suite' ); ?></span>
                    </div>
                    <div style="margin-top:8px;font-size:12px;color:#64748b;">
                        <?php printf( esc_html__( 'Antigüedad: %d días transcurridos.', 'aura-suite' ), $diff->days ); ?>
                    </div>
                    <?php if ( $is_own ) : ?>
                    <p style="margin:6px 0 0 0;font-size:11px;color:#2563eb;font-weight:600;">
                        <?php esc_html_e( 'ℹ️ Es tu propia transacción; otro usuario autorizado debe aprobarla.', 'aura-suite' ); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sub-Card: Comprobante / Respaldo Digital -->
            <?php if ( ! empty( $item['receipt_file'] ) ) : 
                $voucher_raw = $item['receipt_file'];
                $voucher_files = [];
                if ( is_string( $voucher_raw ) && ( str_starts_with( trim( $voucher_raw ), '[' ) || str_starts_with( trim( $voucher_raw ), '{' ) ) ) {
                    $decoded = json_decode( $voucher_raw, true );
                    if ( is_array( $decoded ) ) {
                        $voucher_files = $decoded;
                    }
                }
                if ( empty( $voucher_files ) ) {
                    $voucher_files = [ $voucher_raw ];
                }
                $first_item = $voucher_files[0];
                $voucher_file = is_array( $first_item ) ? ( $first_item['url'] ?? ( $first_item['file_url'] ?? '' ) ) : (string) $first_item;
                $is_drive = class_exists( 'Aura_Drive_Manager' ) && Aura_Drive_Manager::is_drive_url( $first_item );
                if ( $is_drive ) {
                    $file_id = Aura_Drive_Manager::extract_file_id( $first_item );
                    $preview_url = Aura_Drive_Manager::get_preview_url( $file_id );
                    $thumb_url = Aura_Drive_Manager::get_thumbnail_url( $file_id );
                    $download_url = Aura_Drive_Manager::get_download_url( $file_id );
                } else {
                    $is_pdf = preg_match( '/\.pdf($|\?)/i', $voucher_file );
                    $preview_url = content_url( 'uploads/aura-finance/receipts/' . $voucher_file );
                    if ( ! file_exists( WP_CONTENT_DIR . '/uploads/aura-finance/receipts/' . $voucher_file ) ) {
                        $preview_url = content_url( 'uploads/aura-receipts/' . $voucher_file );
                    }
                    $thumb_url = $preview_url;
                    $download_url = $preview_url;
                }
            ?>
            <div class="aura-child-subcard">
                <div class="aura-subcard-header">
                    <span class="dashicons <?php echo $is_drive ? 'dashicons-cloud' : 'dashicons-media-document'; ?> aura-subcard-icon"></span>
                    <h4><?php esc_html_e( 'Comprobante Adjunto', 'aura-suite' ); ?></h4>
                </div>
                <div class="aura-subcard-body" style="display:flex;align-items:center;gap:12px;">
                    <?php if ( ! empty( $is_drive ) || empty( $is_pdf ) ) : ?>
                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt="Comprobante" class="aura-open-receipt-viewer" data-receipt-url="<?php echo esc_url( $voucher_file ); ?>" data-preview-url="<?php echo esc_url( $preview_url ); ?>" data-download-url="<?php echo esc_url( $download_url ); ?>" data-is-drive="<?php echo $is_drive ? '1' : '0'; ?>" data-is-pdf="0" data-is-img="1" data-title="<?php echo esc_attr( sprintf( __( 'Transacción #%d - Comprobante', 'aura-suite' ), $item['id'] ) ); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #cbd5e1;cursor:pointer;" title="<?php esc_attr_e( 'Haz clic para ampliar', 'aura-suite' ); ?>">
                    <?php endif; ?>
                    <div>
                        <button type="button" class="button button-small aura-open-receipt-viewer" data-receipt-url="<?php echo esc_url( $voucher_file ); ?>" data-preview-url="<?php echo esc_url( $preview_url ); ?>" data-download-url="<?php echo esc_url( $download_url ); ?>" data-is-drive="<?php echo $is_drive ? '1' : '0'; ?>" data-is-pdf="<?php echo !empty($is_pdf) ? '1' : '0'; ?>" data-is-img="<?php echo empty($is_pdf) ? '1' : '0'; ?>" data-title="<?php echo esc_attr( sprintf( __( 'Transacción #%d - Comprobante', 'aura-suite' ), $item['id'] ) ); ?>" style="margin-bottom:4px;">
                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Ver en Pantalla Completa', 'aura-suite' ); ?>
                        </button>
                        <div style="font-size:11px;color:#64748b;">
                            <?php echo $is_drive ? esc_html__( 'Almacenado en Google Drive ☁️', 'aura-suite' ) : esc_html__( 'Almacenado localmente', 'aura-suite' ); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sub-Card 4 (Full Width si hay notas): Notas / Descripción -->
            <?php if ( ! empty( $item['notes'] ) ) : ?>
            <div class="aura-child-subcard aura-subcard-full" style="grid-column: 1 / -1;">
                <div class="aura-subcard-header">
                    <span class="dashicons dashicons-edit aura-subcard-icon"></span>
                    <h4><?php esc_html_e( 'Notas u Observaciones', 'aura-suite' ); ?></h4>
                </div>
                <div class="aura-subcard-body">
                    <div class="aura-child-notes-box" style="background:#ffffff;padding:10px 14px;border-radius:6px;border-left:3px solid #3b82f6;font-size:12.5px;">
                        <?php echo nl2br( esc_html( $item['notes'] ) ); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Dock de Acciones Rápidas -->
        <div class="aura-child-action-dock">
            <div class="aura-action-dock-left">
                <button type="button" class="button button-secondary view-transaction aura-btn-dock aura-btn-dock--view" data-transaction-id="<?php echo esc_attr( $item['id'] ); ?>">
                    <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Ver Modal Completo', 'aura-suite' ); ?>
                </button>
            </div>
            <div class="aura-action-dock-right">
                <?php if ( current_user_can( 'aura_finance_approve' ) && ! $is_own ) : ?>
                <button type="button" class="button button-primary approve-transaction aura-btn-dock aura-btn-dock--approve" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                    <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Aprobar Transacción', 'aura-suite' ); ?>
                </button>
                <button type="button" class="button reject-transaction aura-btn-dock aura-btn-dock--reject" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                    <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Rechazar Transacción', 'aura-suite' ); ?>
                </button>
                <?php endif; ?>

                <?php if ( $is_own && current_user_can( 'aura_finance_edit_own' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aura-financial-edit-transaction&id=' . $item['id'] ) ); ?>" class="button aura-btn-dock aura-btn-dock--edit">
                    <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Editar mi transacción', 'aura-suite' ); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Preparar items para mostrar
     */
    public function prepare_items() {
        global $wpdb;
        
        // Configurar columnas
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Parámetros de paginación y ordenamiento
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'transaction_date';
        $order = isset($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), array('ASC', 'DESC')) 
                 ? strtoupper($_REQUEST['order']) 
                 : 'DESC';
        
        // Filtros
        $where_clauses = array(
            "t.status = 'pending'",
            't.deleted_at IS NULL'
        );
        
        // Filtro de vista (tabs): Para aprobar vs Mis pendientes
        $filter_view = isset($_REQUEST['filter_view']) ? sanitize_text_field($_REQUEST['filter_view']) : 'all';
        if ($filter_view === 'others') {
            $where_clauses[] = $wpdb->prepare('t.created_by != %d', get_current_user_id());
        } elseif ($filter_view === 'mine') {
            $where_clauses[] = $wpdb->prepare('t.created_by = %d', get_current_user_id());
        }
        
        // Filtro por tipo
        if (!empty($_REQUEST['filter_type'])) {
            $type = sanitize_text_field($_REQUEST['filter_type']);
            if (in_array($type, array('income', 'expense'))) {
                $where_clauses[] = $wpdb->prepare('t.transaction_type = %s', $type);
            }
        }
        
        // Filtro por categoría
        if (!empty($_REQUEST['filter_category'])) {
            $category_id = absint($_REQUEST['filter_category']);
            $where_clauses[] = $wpdb->prepare('t.category_id = %d', $category_id);
        }
        
        // Filtro por creador
        if (!empty($_REQUEST['filter_creator'])) {
            $creator_id = absint($_REQUEST['filter_creator']);
            $where_clauses[] = $wpdb->prepare('t.created_by = %d', $creator_id);
        }
        
        // Filtro por rango de monto
        if (!empty($_REQUEST['filter_amount_min'])) {
            $where_clauses[] = $wpdb->prepare('t.amount >= %f', floatval($_REQUEST['filter_amount_min']));
        }
        if (!empty($_REQUEST['filter_amount_max'])) {
            $where_clauses[] = $wpdb->prepare('t.amount <= %f', floatval($_REQUEST['filter_amount_max']));
        }
        
        // Búsqueda
        if (!empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_clauses[] = $wpdb->prepare(
                '(t.description LIKE %s OR t.reference_number LIKE %s OR t.recipient_payer LIKE %s)',
                $search, $search, $search
            );
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Tabla de transacciones
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $categories_table = $wpdb->prefix . 'aura_finance_categories';
        
        // Contar total de items
        $total_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table t WHERE $where_sql"
        );
        
        // Obtener items
        $offset = ($current_page - 1) * $per_page;
        
        $query = "SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon
                  FROM $table t
                  LEFT JOIN $categories_table c ON t.category_id = c.id
                  WHERE $where_sql
                  ORDER BY t.$orderby $order
                  LIMIT %d OFFSET %d";
        
        $this->items = $wpdb->get_results(
            $wpdb->prepare($query, $per_page, $offset),
            ARRAY_A
        );
        
        // Configurar paginación
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
    
    /**
     * Mensaje cuando no hay items
     */
    public function no_items() {
        _e('No hay transacciones pendientes de aprobación', 'aura-suite');
    }
    
    /**
     * Renderizar vistas de filtro (tabs)
     */
    public function get_views() {
        global $wpdb;
        $table = $wpdb->prefix . 'aura_finance_transactions';
        $current_user_id = get_current_user_id();
        
        // Contar transacciones pendientes
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending' AND deleted_at IS NULL"
        );
        
        $others = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending' AND deleted_at IS NULL AND created_by != %d",
            $current_user_id
        ));
        
        $mine = $total - $others;
        
        $views = array();
        $current = isset($_REQUEST['filter_view']) ? $_REQUEST['filter_view'] : 'all';
        
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=aura-financial-pending&filter_view=all'),
            $current === 'all' ? 'current' : '',
            __('Todas', 'aura-suite'),
            $total
        );
        
        if (current_user_can('aura_finance_approve')) {
            $views['others'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=aura-financial-pending&filter_view=others'),
                $current === 'others' ? 'current' : '',
                __('Para aprobar', 'aura-suite'),
                $others
            );
        }
        
        $views['mine'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=aura-financial-pending&filter_view=mine'),
            $current === 'mine' ? 'current' : '',
            __('Mis pendientes', 'aura-suite'),
            $mine
        );
        
        return $views;
    }
}
