<?php
/**
 * Template: Página de Transacciones Pendientes de Aprobación
 * Muestra listado de transacciones con status=pending y permite aprobar/rechazar
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos (solo usuarios que pueden aprobar deberían ver esta página)
if (!current_user_can('aura_finance_approve')) {
    wp_die(__('No tienes permisos para acceder a esta página.', 'aura-suite'));
}

// Crear instancia de la tabla
$pending_list = new Aura_Financial_Pending_List();
$pending_list->prepare_items();

// Obtener categorías para filtros
global $wpdb;
$categories_table = $wpdb->prefix . 'aura_finance_categories';
$categories = $wpdb->get_results(
    "SELECT id, name, type FROM $categories_table WHERE deleted_at IS NULL ORDER BY name ASC",
    ARRAY_A
);

// Obtener usuarios creadores para filtro
$transactions_table = $wpdb->prefix . 'aura_finance_transactions';
$creators = $wpdb->get_results(
    "SELECT DISTINCT u.ID, u.display_name 
     FROM {$wpdb->users} u
     INNER JOIN $transactions_table t ON u.ID = t.created_by
     WHERE t.status = 'pending' AND t.deleted_at IS NULL
     ORDER BY u.display_name ASC",
    ARRAY_A
);

// Contar pendientes para aprobar (excluir propias)
$current_user_id = get_current_user_id();
$approvable_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $transactions_table 
     WHERE status = 'pending' AND deleted_at IS NULL AND created_by != %d",
    $current_user_id
));
?>

<div class="wrap aura-pending-transactions-page">
    <h1 class="wp-heading-inline">
        <?php _e('Aprobaciones Pendientes', 'aura-suite'); ?>
        <span class="pending-count-badge"><?php echo $pending_list->get_pagination_arg('total_items'); ?></span>
    </h1>
    
    <?php if (current_user_can('aura_finance_approve') && $approvable_count > 0): ?>
    <div class="notice notice-info inline" style="margin: 15px 0;">
        <p>
            <span class="dashicons dashicons-info"></span>
            <strong><?php _e('Tienes', 'aura-suite'); ?> <?php echo $approvable_count; ?> <?php _e('transacciones esperando tu aprobación.', 'aura-suite'); ?></strong>
            <?php _e('No puedes aprobar tus propias transacciones.', 'aura-suite'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <!-- Filtros -->
    <?php
    // Contar filtros activos para el badge
    $active_filters = 0;
    if (!empty($_GET['filter_type'])) $active_filters++;
    if (!empty($_GET['filter_category'])) $active_filters++;
    if (!empty($_GET['filter_creator'])) $active_filters++;
    if (!empty($_GET['filter_amount_min']) || !empty($_GET['filter_amount_max'])) $active_filters++;
    $filters_open = ($active_filters > 0) ? 'true' : 'false';
    ?>
    <div class="aura-filters-bar">
        <div class="aura-filters-bar__header">
            <button type="button" class="aura-filters-toggle" aria-expanded="<?php echo $filters_open; ?>" aria-controls="pending-filters-body">
                <span class="dashicons dashicons-filter"></span>
                <?php _e('Filtros', 'aura-suite'); ?>
                <?php if ($active_filters > 0): ?>
                <span class="active-filters-badge"><?php echo $active_filters; ?></span>
                <?php endif; ?>
                <span class="toggle-chevron dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <?php if ($active_filters > 0): ?>
            <a href="<?php echo admin_url('admin.php?page=aura-financial-pending'); ?>" class="clear-filters-link">
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Limpiar filtros', 'aura-suite'); ?>
            </a>
            <?php endif; ?>
        </div>
        
        <div id="pending-filters-body" class="aura-filters-bar__body" <?php echo $filters_open === 'false' ? 'hidden' : ''; ?>>
            <form method="get" id="pending-filters-form">
                <input type="hidden" name="page" value="aura-financial-pending" />
                <div class="filters-inline">
                    <!-- Tipo -->
                    <div class="fi-group">
                        <label for="filter-type"><?php _e('Tipo', 'aura-suite'); ?></label>
                        <select name="filter_type" id="filter-type">
                            <option value=""><?php _e('Todos', 'aura-suite'); ?></option>
                            <option value="income" <?php selected(isset($_GET['filter_type']) && $_GET['filter_type'] === 'income'); ?>><?php _e('Ingresos', 'aura-suite'); ?></option>
                            <option value="expense" <?php selected(isset($_GET['filter_type']) && $_GET['filter_type'] === 'expense'); ?>><?php _e('Egresos', 'aura-suite'); ?></option>
                        </select>
                    </div>
                    
                    <!-- Categoría -->
                    <div class="fi-group">
                        <label for="filter-category"><?php _e('Categoría', 'aura-suite'); ?></label>
                        <select name="filter_category" id="filter-category">
                            <option value=""><?php _e('Todas', 'aura-suite'); ?></option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category['id']); ?>" <?php selected(isset($_GET['filter_category']) && $_GET['filter_category'] == $category['id']); ?>>
                                <?php echo esc_html($category['name']); ?> (<?php echo $category['type'] === 'income' ? __('Ing.', 'aura-suite') : __('Egr.', 'aura-suite'); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Creador -->
                    <div class="fi-group">
                        <label for="filter-creator"><?php _e('Creado por', 'aura-suite'); ?></label>
                        <select name="filter_creator" id="filter-creator">
                            <option value=""><?php _e('Todos', 'aura-suite'); ?></option>
                            <?php foreach ($creators as $creator): ?>
                            <option value="<?php echo esc_attr($creator['ID']); ?>" <?php selected(isset($_GET['filter_creator']) && $_GET['filter_creator'] == $creator['ID']); ?>>
                                <?php echo esc_html($creator['display_name']); ?><?php echo ($creator['ID'] == $current_user_id) ? ' (Tú)' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Monto -->
                    <div class="fi-group fi-group--range">
                        <label><?php _e('Monto', 'aura-suite'); ?></label>
                        <div class="fi-range">
                            <input type="number" name="filter_amount_min" placeholder="<?php _e('Mín', 'aura-suite'); ?>" step="0.01" min="0" value="<?php echo isset($_GET['filter_amount_min']) ? esc_attr($_GET['filter_amount_min']) : ''; ?>" />
                            <span class="fi-range-sep">—</span>
                            <input type="number" name="filter_amount_max" placeholder="<?php _e('Máx', 'aura-suite'); ?>" step="0.01" min="0" value="<?php echo isset($_GET['filter_amount_max']) ? esc_attr($_GET['filter_amount_max']) : ''; ?>" />
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="fi-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-search"></span>
                            <?php _e('Aplicar', 'aura-suite'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tabla de transacciones -->
    <form method="post" id="pending-transactions-form">
        <?php wp_nonce_field('bulk_action_pending_transactions', 'aura_pending_nonce'); ?>
        <?php $pending_list->views(); ?>
        <?php $pending_list->display(); ?>
    </form>
    
    <!-- Estado vacío -->
    <?php if ($pending_list->get_pagination_arg('total_items') == 0): ?>
    <div class="aura-empty-state">
        <span class="dashicons dashicons-yes-alt"></span>
        <h2><?php _e('¡Todo al día!', 'aura-suite'); ?></h2>
        <p><?php _e('No hay transacciones pendientes de aprobación.', 'aura-suite'); ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Aprobar Transacción -->
<div id="approve-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2><?php _e('Aprobar Transacción', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="approval-info">
                <span class="dashicons dashicons-saved"></span>
                <p><?php _e('¿Estás seguro de que deseas aprobar esta transacción?', 'aura-suite'); ?></p>
                <p class="transaction-summary">
                    <strong><?php _e('Transacción:', 'aura-suite'); ?></strong>
                    <span id="approve-transaction-desc"></span>
                </p>
            </div>
            
            <div class="form-field">
                <label for="approve-note"><?php _e('Nota de aprobación (opcional):', 'aura-suite'); ?></label>
                <textarea id="approve-note" rows="3" 
                          placeholder="<?php _e('Agrega un comentario sobre esta aprobación...', 'aura-suite'); ?>"></textarea>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button" id="cancel-approve">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary" id="confirm-approve">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Aprobar Transacción', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Rechazar Transacción -->
<div id="reject-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2><?php _e('Rechazar Transacción', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="rejection-info">
                <span class="dashicons dashicons-dismiss"></span>
                <p><?php _e('Indica el motivo por el cual rechazas esta transacción:', 'aura-suite'); ?></p>
                <p class="transaction-summary">
                    <strong><?php _e('Transacción:', 'aura-suite'); ?></strong>
                    <span id="reject-transaction-desc"></span>
                </p>
            </div>
            
            <div class="form-field">
                <label for="reject-reason">
                    <?php _e('Motivo de rechazo:', 'aura-suite'); ?>
                    <span class="required">*</span>
                </label>
                <textarea id="reject-reason" rows="4" 
                          placeholder="<?php _e('Explica por qué rechazas esta transacción (mínimo 20 caracteres)...', 'aura-suite'); ?>" 
                          required></textarea>
                <p class="description">
                    <span id="reason-char-count">0</span> / 20 <?php _e('caracteres mínimos', 'aura-suite'); ?>
                </p>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button" id="cancel-reject">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary button-danger" id="confirm-reject" disabled>
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Rechazar Transacción', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Aprobación Masiva -->
<div id="bulk-approve-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2><?php _e('Aprobación Masiva', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="bulk-info">
                <span class="dashicons dashicons-saved"></span>
                <p>
                    <?php _e('¿Aprobar', 'aura-suite'); ?>
                    <strong><span id="bulk-approve-count">0</span></strong>
                    <?php _e('transacciones seleccionadas?', 'aura-suite'); ?>
                </p>
            </div>
            
            <div class="form-field">
                <label for="bulk-approve-note"><?php _e('Nota de aprobación (opcional):', 'aura-suite'); ?></label>
                <textarea id="bulk-approve-note" rows="3" 
                          placeholder="<?php _e('Comentario aplicable a todas las aprobaciones...', 'aura-suite'); ?>"></textarea>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button" id="cancel-bulk-approve">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary" id="confirm-bulk-approve">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Aprobar Seleccionadas', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal: Rechazo Masivo -->
<div id="bulk-reject-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2><?php _e('Rechazo Masivo', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="bulk-info danger">
                <span class="dashicons dashicons-dismiss"></span>
                <p>
                    <?php _e('¿Rechazar', 'aura-suite'); ?>
                    <strong><span id="bulk-reject-count">0</span></strong>
                    <?php _e('transacciones seleccionadas?', 'aura-suite'); ?>
                </p>
            </div>
            
            <div class="form-field">
                <label for="bulk-reject-reason">
                    <?php _e('Motivo de rechazo:', 'aura-suite'); ?>
                    <span class="required">*</span>
                </label>
                <textarea id="bulk-reject-reason" rows="4" 
                          placeholder="<?php _e('Motivo aplicable a todas las transacciones rechazadas (mínimo 20 caracteres)...', 'aura-suite'); ?>" 
                          required></textarea>
                <p class="description">
                    <span id="bulk-reason-char-count">0</span> / 20 <?php _e('caracteres mínimos', 'aura-suite'); ?>
                </p>
            </div>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button" id="cancel-bulk-reject">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary button-danger" id="confirm-bulk-reject" disabled>
                <span class="dashicons dashicons-dismiss"></span>
                <?php _e('Rechazar Seleccionadas', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<?php include AURA_PLUGIN_DIR . 'templates/financial/transaction-modal.php'; ?>

<script>
jQuery(document).ready(function($) {
    let currentTransactionId = null;
    let selectedTransactionIds = [];

    // === TOGGLE FILTROS ===
    $('.aura-filters-toggle').on('click', function() {
        const $body = $('#pending-filters-body');
        const expanded = $(this).attr('aria-expanded') === 'true';
        if (expanded) {
            $body.attr('hidden', '');
            $(this).attr('aria-expanded', 'false');
        } else {
            $body.removeAttr('hidden');
            $(this).attr('aria-expanded', 'true');
        }
    });

    // === APROBAR ÚNICA ===
    $('.approve-transaction').on('click', function(e) {
        e.preventDefault();
        currentTransactionId = $(this).data('id');
        const desc = $(this).closest('td').find('strong').first().text();
        $('#approve-transaction-desc').text(desc);
        $('#approve-note').val('');
        $('#approve-modal').fadeIn(200);
    });
    
    $('#confirm-approve').on('click', function() {
        const note = $('#approve-note').val().trim();
        approveTransaction(currentTransactionId, note);
    });
    
    $('#cancel-approve, #approve-modal .aura-modal-close, #approve-modal .aura-modal-overlay').on('click', function() {
        $('#approve-modal').fadeOut(200);
    });
    
    // === RECHAZAR ÚNICA ===
    $('.reject-transaction').on('click', function(e) {
        e.preventDefault();
        currentTransactionId = $(this).data('id');
        const desc = $(this).closest('td').find('strong').first().text();
        $('#reject-transaction-desc').text(desc);
        $('#reject-reason').val('');
        $('#reason-char-count').text('0');
        $('#confirm-reject').prop('disabled', true);
        $('#reject-modal').fadeIn(200);
    });
    
    $('#reject-reason').on('input', function() {
        const length = $(this).val().trim().length;
        $('#reason-char-count').text(length);
        $('#confirm-reject').prop('disabled', length < 20);
    });
    
    $('#confirm-reject').on('click', function() {
        const reason = $('#reject-reason').val().trim();
        if (reason.length >= 20) {
            rejectTransaction(currentTransactionId, reason);
        }
    });
    
    $('#cancel-reject, #reject-modal .aura-modal-close, #reject-modal .aura-modal-overlay').on('click', function() {
        $('#reject-modal').fadeOut(200);
    });
    
    // === ACCIONES MASIVAS ===
    $('#doaction, #doaction2').on('click', function(e) {
        const action = $(this).siblings('select').val();
        selectedTransactionIds = [];
        
        $('input[name="transaction_ids[]"]:checked').each(function() {
            selectedTransactionIds.push($(this).val());
        });
        
        if (selectedTransactionIds.length === 0) {
            return; // Dejar que WordPress muestre su mensaje
        }
        
        e.preventDefault();
        
        if (action === 'bulk_approve') {
            $('#bulk-approve-count').text(selectedTransactionIds.length);
            $('#bulk-approve-note').val('');
            $('#bulk-approve-modal').fadeIn(200);
        } else if (action === 'bulk_reject') {
            $('#bulk-reject-count').text(selectedTransactionIds.length);
            $('#bulk-reject-reason').val('');
            $('#bulk-reason-char-count').text('0');
            $('#confirm-bulk-reject').prop('disabled', true);
            $('#bulk-reject-modal').fadeIn(200);
        }
    });
    
    $('#bulk-reject-reason').on('input', function() {
        const length = $(this).val().trim().length;
        $('#bulk-reason-char-count').text(length);
        $('#confirm-bulk-reject').prop('disabled', length < 20);
    });
    
    $('#confirm-bulk-approve').on('click', function() {
        const note = $('#bulk-approve-note').val().trim();
        bulkApproveTransactions(selectedTransactionIds, note);
    });
    
    $('#confirm-bulk-reject').on('click', function() {
        const reason = $('#bulk-reject-reason').val().trim();
        if (reason.length >= 20) {
            bulkRejectTransactions(selectedTransactionIds, reason);
        }
    });
    
    $('#cancel-bulk-approve, #bulk-approve-modal .aura-modal-close, #bulk-approve-modal .aura-modal-overlay').on('click', function() {
        $('#bulk-approve-modal').fadeOut(200);
    });
    
    $('#cancel-bulk-reject, #bulk-reject-modal .aura-modal-close, #bulk-reject-modal .aura-modal-overlay').on('click', function() {
        $('#bulk-reject-modal').fadeOut(200);
    });
    
    // === AJAX: APROBAR ===
    function approveTransaction(transactionId, note) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_approve_transaction',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_id: transactionId,
                approval_note: note
            },
            beforeSend: function() {
                $('#confirm-approve').prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span> Aprobando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#approve-modal').fadeOut(200);
                    showNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Transacción');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Transacción');
            }
        });
    }
    
    // === AJAX: RECHAZAR ===
    function rejectTransaction(transactionId, reason) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_reject_transaction',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_id: transactionId,
                rejection_reason: reason
            },
            beforeSend: function() {
                $('#confirm-reject').prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span> Rechazando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#reject-modal').fadeOut(200);
                    showNotice('warning', response.data.message);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Transacción');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Transacción');
            }
        });
    }
    
    // === AJAX: APROBACIÓN MASIVA ===
    function bulkApproveTransactions(transactionIds, note) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_bulk_approve',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_ids: transactionIds,
                approval_note: note
            },
            beforeSend: function() {
                $('#confirm-bulk-approve').prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span> Aprobando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#bulk-approve-modal').fadeOut(200);
                    showNotice('success', response.data.message);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-bulk-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Seleccionadas');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-bulk-approve').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Aprobar Seleccionadas');
            }
        });
    }
    
    // === AJAX: RECHAZO MASIVO ===
    function bulkRejectTransactions(transactionIds, reason) {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aura_bulk_reject',
                nonce: '<?php echo wp_create_nonce("aura_approval_nonce"); ?>',
                transaction_ids: transactionIds,
                rejection_reason: reason
            },
            beforeSend: function() {
                $('#confirm-bulk-reject').prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0;"></span> Rechazando...');
            },
            success: function(response) {
                if (response.success) {
                    $('#bulk-reject-modal').fadeOut(200);
                    showNotice('warning', response.data.message);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotice('error', response.data.message);
                    $('#confirm-bulk-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Seleccionadas');
                }
            },
            error: function() {
                showNotice('error', '<?php _e('Error de conexión. Intenta nuevamente.', 'aura-suite'); ?>');
                $('#confirm-bulk-reject').prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Rechazar Seleccionadas');
            }
        });
    }
    
    // === NOTIFICACIÓN TOAST ===
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 
                           type === 'warning' ? 'notice-warning' : 'notice-error';
        
        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').prepend($notice);
        
        setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }
});
</script>
