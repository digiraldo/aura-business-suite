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
$total_items = $trash_list->get_pagination_arg('total_items');
?>

<div class="aura-app-wrapper">
<div class="wrap aura-app-context aura-transactions-list-page aura-trash-page">

    <!-- ====================================================
         Cabecera de Impresión Oficial (@media print)
    ===================================================== -->
    <div class="aura-print-header">
        <div class="aura-print-header__brand-box">
            <?php
            $logo_url = get_site_icon_url(80);
            if ($logo_url) {
                echo '<img src="' . esc_url($logo_url) . '" alt="" class="aura-print-logo" loading="eager">';
            }
            ?>
            <div class="aura-print-header__brand-text">
                <h2><?php echo esc_html(get_bloginfo('name')); ?></h2>
                <span class="aura-print-header__tagline"><?php esc_html_e('MÓDULO FINANCIERO — AUDITORÍA DE PAPELERA', 'aura-suite'); ?></span>
            </div>
        </div>
        <div class="aura-print-header__report-card">
            <h1><?php esc_html_e('Transacciones en Papelera de Reciclaje', 'aura-suite'); ?></h1>
            <div class="aura-print-period-pill">
                <span class="dashicons dashicons-trash"></span>
                <span><?php printf(esc_html__('%d registros eliminados en custodia temporal', 'aura-suite'), $total_items); ?></span>
            </div>
        </div>
        <div class="aura-print-header__meta-box">
            <div class="aura-print-meta-item">
                <span><?php esc_html_e('Generado por', 'aura-suite'); ?>:</span>
                <strong class="aura-print-meta-value"><?php echo esc_html(wp_get_current_user()->display_name); ?></strong>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e('Fecha de Emisión', 'aura-suite'); ?>:</span>
                <span class="aura-print-meta-value"><?php echo esc_html(date_i18n('d/m/Y H:i')); ?></span>
            </div>
            <div class="aura-print-meta-item">
                <span><?php esc_html_e('Carácter', 'aura-suite'); ?>:</span>
                <span class="aura-print-badge-official"><?php esc_html_e('Auditoría Interna', 'aura-suite'); ?></span>
            </div>
        </div>
    </div>

    <!-- ====================================================
         Cabecera de Pantalla
    ===================================================== -->
    <div class="aura-layout">
    <main class="aura-content">
        <header class="aura-trash-hero aura-glass-card">
            <div class="aura-trash-hero__main">
                <div class="aura-trash-hero__icon">
                    <span class="dashicons dashicons-trash"></span>
                </div>
                <div class="aura-trash-hero__copy">
                    <p class="aura-trash-eyebrow"><?php _e('MÓDULO FINANCIERO', 'aura-suite'); ?></p>
                    <h1 class="wp-heading-inline">
                        <?php _e('Papelera de Transacciones', 'aura-suite'); ?>
                        <?php if ($total_items > 0): ?>
                        <span class="trash-count-badge"><?php echo esc_html($total_items); ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="aura-trash-intro">
                        <?php _e('Consulta las transacciones eliminadas, restáuralas a su estado activo o elimínalas definitivamente.', 'aura-suite'); ?>
                    </p>
                </div>
            </div>
            <div class="aura-trash-hero__actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=aura-financial-transactions')); ?>" class="button button-secondary aura-ud-btn">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php _e('Volver a Transacciones', 'aura-suite'); ?>
                </a>
                <?php if ($total_items > 0): ?>
                <button type="button" id="aura-print-trash-btn" class="button aura-ud-btn aura-ud-btn--amber" style="background:#f59e0b;color:#fff;border:none;">
                    <span class="dashicons dashicons-printer"></span>
                    <?php _e('Imprimir / PDF', 'aura-suite'); ?>
                </button>
                <?php endif; ?>
            </div>
        </header>

        <hr class="wp-header-end">
        
        <!-- Mensajes de estado AJAX -->
        <div id="aura-messages"></div>

        <?php if ($total_items > 0): ?>
        
        <!-- Tarjeta Informativa de Retención -->
        <div class="aura-trash-info-box aura-glass-card">
            <div class="trash-info-content">
                <span class="dashicons dashicons-info"></span>
                <div>
                    <strong><?php _e('Custodia Temporal de Papelera', 'aura-suite'); ?></strong>
                    <p>
                        <?php 
                        printf(
                            __('Las transacciones en la papelera se conservan durante %d días. Puedes restaurarlas a su estado operativo o eliminarlas definitivamente.', 'aura-suite'),
                            Aura_Financial_Transactions_Delete::TRASH_RETENTION_DAYS
                        );
                        ?>
                    </p>
                </div>
            </div>
            
            <?php if (current_user_can('manage_options')): ?>
            <div class="trash-info-actions">
                <button type="button" id="empty-trash-btn" class="button button-link-delete" style="color:#dc2626;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Vaciar Papelera', 'aura-suite'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Filtros -->
        <?php
        $active_filters = 0;
        if (!empty($_GET['filter_type'])) $active_filters++;
        if (!empty($_GET['filter_status'])) $active_filters++;
        if (!empty($_GET['s'])) $active_filters++;
        $filters_open = ($active_filters > 0) ? 'true' : 'false';
        ?>
        <div class="aura-filters-bar aura-glass-card">
            <div class="aura-filters-bar__header">
                <button type="button" class="aura-filters-toggle" aria-expanded="<?php echo $filters_open; ?>" aria-controls="trash-filters-body">
                    <span class="dashicons dashicons-filter"></span>
                    <?php _e('Filtros y Búsqueda', 'aura-suite'); ?>
                    <?php if ($active_filters > 0): ?>
                    <span class="active-filters-badge"><?php echo $active_filters; ?></span>
                    <?php endif; ?>
                    <span class="toggle-chevron dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <?php if ($active_filters > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=aura-financial-trash'); ?>" class="clear-filters-link">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e('Limpiar filtros', 'aura-suite'); ?>
                </a>
                <?php endif; ?>
            </div>
            
            <div id="trash-filters-body" class="aura-filters-bar__body" <?php echo $filters_open === 'false' ? 'hidden' : ''; ?>>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" id="trash-filters-form">
                    <input type="hidden" name="page" value="aura-financial-trash">
                    
                    <div class="filters-inline">
                        <!-- Búsqueda -->
                        <div class="fi-group fi-group--search" style="flex:1;min-width:200px;">
                            <label for="trash-search-input"><?php _e('Búsqueda por texto', 'aura-suite'); ?></label>
                            <input type="search" 
                                   name="s" 
                                   id="trash-search-input" 
                                   value="<?php echo esc_attr(isset($_REQUEST['s']) ? $_REQUEST['s'] : ''); ?>"
                                   placeholder="<?php _e('Buscar por descripción, referencia...', 'aura-suite'); ?>">
                        </div>
                        
                        <!-- Filtro por tipo -->
                        <div class="fi-group">
                            <label for="filter-type"><?php _e('Tipo', 'aura-suite'); ?></label>
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
                        <div class="fi-group">
                            <label for="filter-status"><?php _e('Estado Original', 'aura-suite'); ?></label>
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
                        
                        <div class="fi-actions">
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('Aplicar Filtros', 'aura-suite'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabla de transacciones eliminadas con Responsive Wrap y Child Rows -->
        <section class="aura-trash-table-card aura-glass-card">
            <form method="post" id="trash-form">
                <?php wp_nonce_field('bulk_action_trash_transactions', 'aura_trash_nonce'); ?>
                
                <div class="aura-table-responsive-wrap">
                    <?php
                    $trash_list->views();
                    $trash_list->display();
                    ?>
                </div>
            </form>
        </section>
        
        <?php else: ?>
        
        <!-- Control de Estado Vacío ("¡Todo al día!") -->
        <div class="aura-empty-state aura-glass-card" style="text-align:center;padding:56px 20px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;margin:24px 0;">
            <div class="empty-state-icon" style="margin-bottom:16px;">
                <span class="dashicons dashicons-yes-alt" style="font-size:54px;width:54px;height:54px;color:#10b981;"></span>
            </div>
            <h2 style="margin:0 0 8px 0;font-size:20px;font-weight:700;color:#0f172a;"><?php _e('¡La papelera está vacía!', 'aura-suite'); ?></h2>
            <p style="margin:0 0 20px 0;font-size:14px;color:#64748b;"><?php _e('No hay transacciones eliminadas en este momento.', 'aura-suite'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aura-financial-transactions')); ?>" class="button button-primary aura-ud-btn" style="background:#2563eb;color:#fff;">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Volver a Transacciones', 'aura-suite'); ?>
            </a>
        </div>
        
        <?php endif; ?>

        <!-- Pie de Impresión Oficial (@media print) -->
        <div class="aura-print-footer">
            <div class="aura-print-footer__left">
                <span class="dashicons dashicons-shield-alt"></span>
                <?php esc_html_e('Documento oficial de control interno emitido por Aura Business Suite.', 'aura-suite'); ?>
            </div>
            <div class="aura-print-footer__right">
                <?php echo esc_html(get_bloginfo('name')); ?> &bull; <?php esc_html_e('Página 1', 'aura-suite'); ?>
            </div>
        </div>

    </main>
    </div>
</div>
</div>

<!-- Modal de Confirmación de Eliminación Permanente -->
<div id="permanent-delete-modal" class="aura-modal aura-modal-hidden" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header" style="background:#991b1b;">
            <h2>
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Eliminar Permanentemente', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="warning-message" style="display:flex;gap:12px;align-items:flex-start;background:#fef2f2;padding:12px 16px;border-radius:8px;border:1px solid #fecaca;margin-bottom:16px;">
                <span class="dashicons dashicons-warning" style="font-size:24px;width:24px;height:24px;color:#dc2626;flex-shrink:0;"></span>
                <div>
                    <strong style="color:#991b1b;"><?php _e('⚠️ Esta acción es irreversible', 'aura-suite'); ?></strong>
                    <p style="margin:4px 0 0 0;font-size:13px;color:#450a0a;"><?php _e('La transacción será eliminada definitivamente de la base de datos y no podrá ser recuperada.', 'aura-suite'); ?></p>
                </div>
            </div>
            
            <div class="transaction-details-preview" id="delete-preview" style="background:#f8fafc;padding:10px 14px;border-radius:6px;margin-bottom:14px;border:1px solid #e2e8f0;font-size:13px;">
                <!-- Se llena con JavaScript -->
            </div>
            
            <label class="confirmation-checkbox" style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1e293b;font-weight:600;cursor:pointer;">
                <input type="checkbox" id="confirm-permanent-delete">
                <span><?php _e('Entiendo que esta acción es permanente e irreversible', 'aura-suite'); ?></span>
            </label>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-modal-close">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" id="confirm-delete-btn" class="button button-primary button-danger" disabled style="background:#dc2626;border-color:#b91c1c;">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Eliminar Permanentemente', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal de Vaciar Papelera -->
<div id="empty-trash-modal" class="aura-modal aura-modal-hidden" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-content">
        <div class="aura-modal-header" style="background:#991b1b;">
            <h2>
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Vaciar Papelera', 'aura-suite'); ?>
            </h2>
            <button type="button" class="aura-modal-close" aria-label="<?php _e('Cerrar', 'aura-suite'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="aura-modal-body">
            <div class="warning-message" style="display:flex;gap:12px;align-items:flex-start;background:#fef2f2;padding:12px 16px;border-radius:8px;border:1px solid #fecaca;margin-bottom:16px;">
                <span class="dashicons dashicons-warning" style="font-size:24px;width:24px;height:24px;color:#dc2626;flex-shrink:0;"></span>
                <div>
                    <strong style="color:#991b1b;"><?php _e('⚠️ Eliminar todas las transacciones en papelera', 'aura-suite'); ?></strong>
                    <p style="margin:4px 0 0 0;font-size:13px;color:#450a0a;">
                        <?php 
                        printf(
                            __('Se eliminarán permanentemente %d registros en papelera. Esta acción no se puede deshacer.', 'aura-suite'),
                            $trash_count
                        );
                        ?>
                    </p>
                </div>
            </div>
            
            <label class="confirmation-checkbox" style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1e293b;font-weight:600;cursor:pointer;">
                <input type="checkbox" id="confirm-empty-trash">
                <span><?php _e('Entiendo que se eliminarán todas las transacciones permanentemente', 'aura-suite'); ?></span>
            </label>
        </div>
        
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary aura-modal-close">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" id="confirm-empty-trash-btn" class="button button-primary button-danger" disabled style="background:#dc2626;border-color:#b91c1c;">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Vaciar Papelera', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<?php include AURA_PLUGIN_DIR . 'templates/financial/transaction-modal.php'; ?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    let transactionToDelete = null;

    // === BOTÓN IMPRIMIR / PDF ===
    $('#aura-print-trash-btn').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    // === TOGGLE DE FILAS EXPANDIBLES (CHILD ROWS) ===
    $(document).on('click', '.aura-row-toggle', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $parentRow = $btn.closest('tr.aura-parent-row');
        const $childRow = $parentRow.next('tr.aura-child-row');
        const $wrapper = $childRow.find('.aura-child-card-wrapper');

        const isExpanded = $btn.attr('aria-expanded') === 'true';

        if (isExpanded) {
            $wrapper.slideUp(180, function() {
                $childRow.hide();
                $btn.attr('aria-expanded', 'false').removeClass('is-active');
            });
        } else {
            $childRow.show();
            $wrapper.hide().slideDown(220);
            $btn.attr('aria-expanded', 'true').addClass('is-active');
        }
    });

    // === TOGGLE FILTROS ===
    $('.aura-filters-toggle').on('click', function() {
        const $body = $('#trash-filters-body');
        const expanded = $(this).attr('aria-expanded') === 'true';
        if (expanded) {
            $body.slideUp(180, function() {
                $body.attr('hidden', '');
            });
            $(this).attr('aria-expanded', 'false');
        } else {
            $body.removeAttr('hidden').hide().slideDown(200);
            $(this).attr('aria-expanded', 'true');
        }
    });
    
    // === RESTAURAR TRANSACCIÓN ===
    $(document).on('click', '.restore-transaction', function(e) {
        e.preventDefault();
        
        const transactionId = $(this).data('id');
        const $button = $(this);
        
        if (!confirm('<?php _e('¿Estás seguro de que deseas restaurar esta transacción?', 'aura-suite'); ?>')) {
            return;
        }
        
        $button.addClass('loading').prop('disabled', true);
        
        $.ajax({
            url: auraTrashSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_restore_transaction',
                nonce: auraTrashSettings.nonce,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                } else {
                    showMessage('error', response.data.message);
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                showMessage('error', '<?php _e('Error al procesar la solicitud', 'aura-suite'); ?>');
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // === ELIMINAR PERMANENTEMENTE ===
    $(document).on('click', '.permanent-delete-transaction', function(e) {
        e.preventDefault();
        
        transactionToDelete = $(this).data('id');
        const $row = $(this).closest('tr.aura-parent-row');
        const desc = $row.find('.column-description strong').text();
        const amount = $row.find('.column-amount').text();
        
        $('#delete-preview').html('<strong>' + desc + '</strong> - ' + amount);
        $('#confirm-permanent-delete').prop('checked', false);
        $('#confirm-delete-btn').prop('disabled', true);
        $('#permanent-delete-modal').removeClass('aura-modal-hidden').fadeIn(200);
    });
    
    $('#confirm-permanent-delete').on('change', function() {
        $('#confirm-delete-btn').prop('disabled', !$(this).is(':checked'));
    });
    
    $('#confirm-delete-btn').on('click', function() {
        if (!transactionToDelete) return;
        
        const $button = $(this);
        $button.addClass('loading').prop('disabled', true);
        
        $.ajax({
            url: auraTrashSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_permanent_delete_transaction',
                nonce: auraTrashSettings.nonce,
                transaction_id: transactionToDelete
            },
            success: function(response) {
                if (response.success) {
                    $('#permanent-delete-modal').fadeOut(200);
                    showMessage('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                } else {
                    showMessage('error', response.data.message);
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                showMessage('error', '<?php _e('Error al procesar la solicitud', 'aura-suite'); ?>');
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // === VACIAR PAPELERA ===
    $('#empty-trash-btn').on('click', function() {
        $('#confirm-empty-trash').prop('checked', false);
        $('#confirm-empty-trash-btn').prop('disabled', true);
        $('#empty-trash-modal').removeClass('aura-modal-hidden').fadeIn(200);
    });
    
    $('#confirm-empty-trash').on('change', function() {
        $('#confirm-empty-trash-btn').prop('disabled', !$(this).is(':checked'));
    });
    
    $('#confirm-empty-trash-btn').on('click', function() {
        const $button = $(this);
        $button.addClass('loading').prop('disabled', true);
        
        $.ajax({
            url: auraTrashSettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_empty_trash',
                nonce: auraTrashSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#empty-trash-modal').fadeOut(200);
                    showMessage('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                } else {
                    showMessage('error', response.data.message);
                    $button.removeClass('loading').prop('disabled', false);
                }
            },
            error: function() {
                showMessage('error', '<?php _e('Error al procesar la solicitud', 'aura-suite'); ?>');
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // === CERRAR MODALES ===
    $('.aura-modal-close, .aura-modal-overlay').on('click', function() {
        $('.aura-modal').fadeOut(200, function() {
            $(this).addClass('aura-modal-hidden');
        });
        transactionToDelete = null;
    });
    
    // === MOSTRAR MENSAJES ===
    function showMessage(type, message) {
        const messageHtml = '<div class="notice notice-' + type + ' is-dismissible" style="animation:auraNoticeSlide 0.3s ease-out;"><p>' + message + '</p></div>';
        $('#aura-messages').html(messageHtml);
        
        setTimeout(function() {
            $('#aura-messages .notice').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
});
</script>
