<?php
/**
 * Template: Papelera de Transacciones Financieras
 * 
 * Muestra transacciones eliminadas con opciones de restaurar o eliminar permanentemente
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('aura_finance_view_all') && !current_user_can('aura_finance_view_own')) {
    wp_die(__('No tienes permisos para acceder a esta página', 'aura-suite'));
}

// Crear instancia de la tabla
$trash_list = new Aura_Financial_Trash_List();
$trash_list->prepare_items();

// Obtener conteo
$trash_count = Aura_Financial_Transactions_Delete::get_trash_count();
?>

<div class="wrap aura-trash-page">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-trash"></span>
        <?php _e('Papelera de Transacciones', 'aura-suite'); ?>
    </h1>
    
    <?php if ($trash_count > 0): ?>
    <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="page-title-action">
        <span class="dashicons dashicons-arrow-left-alt2"></span>
        <?php _e('Volver a Transacciones', 'aura-suite'); ?>
    </a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <!-- Mensajes de estado -->
    <div id="aura-messages"></div>
    
    <?php if ($trash_count > 0): ?>
    
    <!-- Info box de papelera -->
    <div class="aura-trash-info-box">
        <div class="trash-info-content">
            <span class="dashicons dashicons-info"></span>
            <div>
                <strong><?php _e('Información de la Papelera', 'aura-suite'); ?></strong>
                <p>
                    <?php 
                    printf(
                        __('Las transacciones en la papelera se eliminarán automáticamente después de %d días. Puedes restaurarlas antes de ese tiempo.', 'aura-suite'),
                        Aura_Financial_Transactions_Delete::TRASH_RETENTION_DAYS
                    );
                    ?>
                </p>
            </div>
        </div>
        
        <?php if (current_user_can('manage_options')): ?>
        <div class="trash-info-actions">
            <button type="button" id="empty-trash-btn" class="button button-link-delete">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Vaciar Papelera', 'aura-suite'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Filtros -->
    <div class="aura-trash-filters">
        <form method="get">
            <input type="hidden" name="page" value="aura-financial-trash">
            
            <div class="filter-row">
                <!-- Búsqueda -->
                <div class="filter-item filter-search">
                    <input type="search" 
                           name="s" 
                           id="trash-search-input" 
                           value="<?php echo esc_attr(isset($_REQUEST['s']) ? $_REQUEST['s'] : ''); ?>"
                           placeholder="<?php _e('Buscar en papelera...', 'aura-suite'); ?>">
                </div>
                
                <!-- Filtro por tipo -->
                <div class="filter-item">
                    <select name="filter_type" id="filter-type">
                        <option value=""><?php _e('Todos los tipos', 'aura-suite'); ?></option>
                        <option value="income" <?php selected(isset($_REQUEST['filter_type']) ? $_REQUEST['filter_type'] : '', 'income'); ?>>
                            <?php _e('Ingresos', 'aura-suite'); ?>
                        </option>
                        <option value="expense" <?php selected(isset($_REQUEST['filter_type']) ? $_REQUEST['filter_type'] : '', 'expense'); ?>>
                            <?php _e('Egresos', 'aura-suite'); ?>
                        </option>
                    </select>
                </div>
                
                <!-- Filtro por estado -->
                <div class="filter-item">
                    <select name="filter_status" id="filter-status">
                        <option value=""><?php _e('Todos los estados', 'aura-suite'); ?></option>
                        <option value="pending" <?php selected(isset($_REQUEST['filter_status']) ? $_REQUEST['filter_status'] : '', 'pending'); ?>>
                            <?php _e('Pendiente', 'aura-suite'); ?>
                        </option>
                        <option value="approved" <?php selected(isset($_REQUEST['filter_status']) ? $_REQUEST['filter_status'] : '', 'approved'); ?>>
                            <?php _e('Aprobado', 'aura-suite'); ?>
                        </option>
                        <option value="rejected" <?php selected(isset($_REQUEST['filter_status']) ? $_REQUEST['filter_status'] : '', 'rejected'); ?>>
                            <?php _e('Rechazado', 'aura-suite'); ?>
                        </option>
                    </select>
                </div>
                
                <button type="submit" class="button">
                    <span class="dashicons dashicons-filter"></span>
                    <?php _e('Filtrar', 'aura-suite'); ?>
                </button>
                
                <?php if (!empty($_REQUEST['s']) || !empty($_REQUEST['filter_type']) || !empty($_REQUEST['filter_status'])): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-trash'); ?>" class="button">
                    <?php _e('Limpiar filtros', 'aura-suite'); ?>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Tabla de transacciones eliminadas -->
    <form method="post" id="trash-form">
        <?php
        $trash_list->views();
        $trash_list->display();
        ?>
    </form>
    
    <?php else: ?>
    
    <!-- Estado vacío -->
    <div class="aura-empty-state">
        <div class="empty-state-icon">
            <span class="dashicons dashicons-trash"></span>
        </div>
        <h2><?php _e('La papelera está vacía', 'aura-suite'); ?></h2>
        <p><?php _e('No hay transacciones eliminadas en este momento.', 'aura-suite'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=aura-financial-transactions'); ?>" class="button button-primary">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php _e('Volver a Transacciones', 'aura-suite'); ?>
        </a>
    </div>
    
    <?php endif; ?>
    
</div>

<!-- Modal de Confirmación de Eliminación Permanente -->
<div id="permanent-delete-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2><?php _e('Eliminar Permanentemente', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="warning-message">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php _e('⚠️ Esta acción no se puede deshacer', 'aura-suite'); ?></strong>
                    <p><?php _e('La transacción será eliminada permanentemente de la base de datos y no podrá ser recuperada.', 'aura-suite'); ?></p>
                </div>
            </div>
            
            <div class="transaction-details-preview" id="delete-preview">
                <!-- Se llenará con JavaScript -->
            </div>
            
            <label class="confirmation-checkbox">
                <input type="checkbox" id="confirm-permanent-delete">
                <span><?php _e('Entiendo que esta acción es permanente e irreversible', 'aura-suite'); ?></span>
            </label>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-modal-close">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" id="confirm-delete-btn" class="button button-danger" disabled>
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Eliminar Permanentemente', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal de Vaciar Papelera -->
<div id="empty-trash-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header">
            <h2><?php _e('Vaciar Papelera', 'aura-suite'); ?></h2>
            <button type="button" class="aura-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="warning-message">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php _e('⚠️ Eliminar todas las transacciones en papelera', 'aura-suite'); ?></strong>
                    <p>
                        <?php 
                        printf(
                            __('Se eliminarán permanentemente %d transacciones. Esta acción no se puede deshacer.', 'aura-suite'),
                            $trash_count
                        );
                        ?>
                    </p>
                </div>
            </div>
            
            <label class="confirmation-checkbox">
                <input type="checkbox" id="confirm-empty-trash">
                <span><?php _e('Entiendo que se eliminarán todas las transacciones permanentemente', 'aura-suite'); ?></span>
            </label>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-modal-close">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" id="confirm-empty-trash-btn" class="button button-danger" disabled>
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Vaciar Papelera', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Restaurar transacción
    $(document).on('click', '.restore-transaction', function(e) {
        e.preventDefault();
        
        const transactionId = $(this).data('id');
        const $row = $(this).closest('tr');
        
        if (!confirm('<?php _e('¿Restaurar esta transacción?', 'aura-suite'); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aura_restore_transaction',
                transaction_id: transactionId,
                nonce: '<?php echo wp_create_nonce('aura_transaction_delete_nonce'); ?>'
            },
            beforeSend: function() {
                $row.css('opacity', '0.5');
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(response.data.message, 'error');
                    $row.css('opacity', '1');
                }
            },
            error: function() {
                showMessage('<?php _e('Error al restaurar la transacción', 'aura-suite'); ?>', 'error');
                $row.css('opacity', '1');
            }
        });
    });
    
    // Eliminar permanentemente
    $(document).on('click', '.permanent-delete-transaction', function(e) {
        e.preventDefault();
        
        const transactionId = $(this).data('id');
        $('#permanent-delete-modal').data('transaction-id', transactionId).fadeIn(200);
        
        // Cargar preview de la transacción
        const $row = $(this).closest('tr');
        const description = $row.find('td.column-description strong').text();
        const amount = $row.find('td.column-amount').text();
        
        $('#delete-preview').html(`
            <p><strong><?php _e('Descripción:', 'aura-suite'); ?></strong> ${description}</p>
            <p><strong><?php _e('Monto:', 'aura-suite'); ?></strong> ${amount}</p>
        `);
    });
    
    // Checkbox de confirmación
    $('#confirm-permanent-delete').on('change', function() {
        $('#confirm-delete-btn').prop('disabled', !$(this).is(':checked'));
    });
    
    // Confirmar eliminación permanente
    $('#confirm-delete-btn').on('click', function() {
        const transactionId = $('#permanent-delete-modal').data('transaction-id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aura_permanent_delete_transaction',
                transaction_id: transactionId,
                nonce: '<?php echo wp_create_nonce('aura_transaction_delete_nonce'); ?>'
            },
            beforeSend: function() {
                $('#confirm-delete-btn').prop('disabled', true).text('<?php _e('Eliminando...', 'aura-suite'); ?>');
            },
            success: function(response) {
                if (response.success) {
                    $('#permanent-delete-modal').fadeOut(200);
                    showMessage(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(response.data.message, 'error');
                    $('#confirm-delete-btn').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Eliminar Permanentemente', 'aura-suite'); ?>');
                }
            },
            error: function() {
                showMessage('<?php _e('Error al eliminar la transacción', 'aura-suite'); ?>', 'error');
                $('#confirm-delete-btn').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Eliminar Permanentemente', 'aura-suite'); ?>');
            }
        });
    });
    
    // Vaciar papelera
    $('#empty-trash-btn').on('click', function() {
        $('#empty-trash-modal').fadeIn(200);
    });
    
    $('#confirm-empty-trash').on('change', function() {
        $('#confirm-empty-trash-btn').prop('disabled', !$(this).is(':checked'));
    });
    
    $('#confirm-empty-trash-btn').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aura_empty_trash',
                nonce: '<?php echo wp_create_nonce('aura_transaction_delete_nonce'); ?>'
            },
            beforeSend: function() {
                $('#confirm-empty-trash-btn').prop('disabled', true).text('<?php _e('Vaciando...', 'aura-suite'); ?>');
            },
            success: function(response) {
                if (response.success) {
                    $('#empty-trash-modal').fadeOut(200);
                    showMessage(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data.message, 'error');
                    $('#confirm-empty-trash-btn').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Vaciar Papelera', 'aura-suite'); ?>');
                }
            },
            error: function() {
                showMessage('<?php _e('Error al vaciar la papelera', 'aura-suite'); ?>', 'error');
                $('#confirm-empty-trash-btn').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> <?php _e('Vaciar Papelera', 'aura-suite'); ?>');
            }
        });
    });
    
    // Cerrar modales
    $('.aura-modal-close, .aura-modal-overlay').on('click', function() {
        $(this).closest('.aura-modal').fadeOut(200);
        $('#confirm-permanent-delete, #confirm-empty-trash').prop('checked', false);
        $('#confirm-delete-btn, #confirm-empty-trash-btn').prop('disabled', true);
    });
    
    // Acciones masivas
    $('#doaction, #doaction2').on('click', function(e) {
        const action = $(this).siblings('select').val();
        
        if (action === 'bulk_restore' || action === 'bulk_permanent_delete') {
            e.preventDefault();
            
            const $checked = $('#trash-form input[name="transaction_ids[]"]:checked');
            
            if ($checked.length === 0) {
                alert('<?php _e('Selecciona al menos una transacción', 'aura-suite'); ?>');
                return;
            }
            
            const transactionIds = $checked.map(function() {
                return $(this).val();
            }).get();
            
            if (action === 'bulk_restore') {
                bulkRestore(transactionIds);
            } else {
                if (!confirm('<?php _e('¿Eliminar permanentemente las transacciones seleccionadas? Esta acción no se puede deshacer.', 'aura-suite'); ?>')) {
                    return;
                }
                bulkPermanentDelete(transactionIds);
            }
        }
    });
    
    function bulkRestore(transactionIds) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aura_bulk_restore',
                transaction_ids: transactionIds,
                nonce: '<?php echo wp_create_nonce('aura_transaction_delete_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage('<?php _e('Error al restaurar las transacciones', 'aura-suite'); ?>', 'error');
            }
        });
    }
    
    function bulkPermanentDelete(transactionIds) {
        // Implementar si es necesario
        showMessage('<?php _e('Función en desarrollo', 'aura-suite'); ?>', 'info');
    }
    
    function showMessage(message, type) {
        const alertClass = 'notice-' + type;
        const html = `<div class="notice ${alertClass} is-dismissible"><p>${message}</p></div>`;
        $('#aura-messages').html(html);
        
        setTimeout(function() {
            $('#aura-messages .notice').fadeOut();
        }, 5000);
    }
});
</script>
