<?php
/**
 * Template: Modal de Detalle de Transacción
 * 
 * Modal completo para visualizar información detallada de transacciones
 * con tabs de información, notas, comprobante y auditoría
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Modal de Detalle de Transacción -->
<div id="aura-transaction-modal" class="aura-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    
    <div class="aura-modal-container">
        <!-- Cabecera del Modal -->
        <div class="aura-modal-header">
            <div class="modal-header-content">
                <div class="transaction-status-badge">
                    <span class="status-label" id="modal-status-label"></span>
                </div>
                <div class="transaction-amount-display">
                    <span class="amount-value" id="modal-amount"></span>
                    <div class="transaction-meta">
                        <span class="transaction-type-icon" id="modal-type-icon"></span>
                        <span class="transaction-date" id="modal-date"></span>
                    </div>
                </div>
            </div>
            <button type="button" class="aura-modal-close" id="close-transaction-modal">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <!-- Contenido del Modal con Tabs -->
        <div class="aura-modal-body">
            <!-- Navegación de Tabs -->
            <nav class="modal-tabs-nav">
                <button type="button" class="tab-button active" data-tab="general">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php _e('Información General', 'aura-suite'); ?>
                </button>
                <button type="button" class="tab-button" data-tab="notes">
                    <span class="dashicons dashicons-edit-page"></span>
                    <?php _e('Notas', 'aura-suite'); ?>
                </button>
                <button type="button" class="tab-button" data-tab="receipt">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php _e('Comprobante', 'aura-suite'); ?>
                </button>
                <?php if (current_user_can('aura_finance_view_all') || current_user_can('manage_options')): ?>
                <button type="button" class="tab-button" data-tab="audit">
                    <span class="dashicons dashicons-shield"></span>
                    <?php _e('Auditoría', 'aura-suite'); ?>
                </button>
                <?php endif; ?>
            </nav>
            
            <!-- Contenido de Tabs -->
            <div class="modal-tabs-content">
                
                <!-- Tab 1: Información General -->
                <div class="tab-panel active" id="tab-general">
                    <div class="info-grid">
                        <div class="info-item">
                            <label><?php _e('Categoría', 'aura-suite'); ?></label>
                            <div id="modal-category" class="category-badge-container"></div>
                        </div>
                        
                        <div class="info-item full-width">
                            <label><?php _e('Descripción', 'aura-suite'); ?></label>
                            <div id="modal-description" class="description-text"></div>
                        </div>
                        
                        <div class="info-item">
                            <label><?php _e('Método de Pago', 'aura-suite'); ?></label>
                            <div id="modal-payment-method"></div>
                        </div>
                        
                        <div class="info-item">
                            <label><?php _e('Nº Referencia', 'aura-suite'); ?></label>
                            <div id="modal-reference-number"></div>
                        </div>
                        
                        <div class="info-item">
                            <label><?php _e('Beneficiario/Pagador', 'aura-suite'); ?></label>
                            <div id="modal-recipient-payer"></div>
                        </div>
                        
                        <div class="info-item">
                            <label><?php _e('Etiquetas', 'aura-suite'); ?></label>
                            <div id="modal-tags" class="tags-container"></div>
                        </div>
                        
                        <div class="info-item full-width creator-info">
                            <label><?php _e('Creado por', 'aura-suite'); ?></label>
                            <div id="modal-creator" class="user-details"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab 2: Notas y Observaciones -->
                <div class="tab-panel" id="tab-notes">
                    <div class="notes-section">
                        <h4><?php _e('Notas del Creador', 'aura-suite'); ?></h4>
                        <div id="modal-notes" class="notes-content"></div>
                    </div>
                    
                    <div class="history-section" style="display: none;" id="history-section">
                        <h4><?php _e('Historial de Cambios', 'aura-suite'); ?></h4>
                        <div id="modal-history" class="history-timeline"></div>
                    </div>
                    
                    <div class="rejection-section" style="display: none;" id="rejection-section">
                        <h4><?php _e('Motivo de Rechazo', 'aura-suite'); ?></h4>
                        <div id="modal-rejection-reason" class="rejection-content"></div>
                    </div>
                </div>
                
                <!-- Tab 3: Comprobante -->
                <div class="tab-panel" id="tab-receipt">
                    <div class="receipt-viewer">
                        <div id="receipt-container">
                            <!-- Se llenará con imagen o PDF viewer -->
                            <div class="no-receipt" id="no-receipt-message">
                                <span class="dashicons dashicons-media-default"></span>
                                <p><?php _e('No hay comprobante adjunto', 'aura-suite'); ?></p>
                                <?php if (current_user_can('aura_finance_edit_own') || current_user_can('aura_finance_edit_all')): ?>
                                <button type="button" class="button button-secondary" id="upload-receipt-btn">
                                    <?php _e('Subir Comprobante', 'aura-suite'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="receipt-actions" id="receipt-actions" style="display: none;">
                            <a href="#" class="button button-secondary" id="download-receipt" target="_blank">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Descargar', 'aura-suite'); ?>
                            </a>
                            <button type="button" class="button button-secondary" id="view-receipt-fullscreen">
                                <span class="dashicons dashicons-fullscreen-alt"></span>
                                <?php _e('Ampliar', 'aura-suite'); ?>
                            </button>
                            <?php if (current_user_can('aura_finance_delete_own') || current_user_can('aura_finance_delete_all')): ?>
                            <button type="button" class="button button-link-delete" id="delete-receipt">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Eliminar', 'aura-suite'); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tab 4: Auditoría -->
                <?php if (current_user_can('aura_finance_view_all') || current_user_can('manage_options')): ?>
                <div class="tab-panel" id="tab-audit">
                    <div class="audit-info-grid">
                        <div class="audit-item">
                            <label><?php _e('Creado el', 'aura-suite'); ?></label>
                            <div id="audit-created-at"></div>
                        </div>
                        
                        <div class="audit-item">
                            <label><?php _e('Creado por', 'aura-suite'); ?></label>
                            <div id="audit-created-by"></div>
                        </div>
                        
                        <div class="audit-item">
                            <label><?php _e('Última edición', 'aura-suite'); ?></label>
                            <div id="audit-updated-at"></div>
                        </div>
                        
                        <div class="audit-item">
                            <label><?php _e('Editado por', 'aura-suite'); ?></label>
                            <div id="audit-updated-by"></div>
                        </div>
                        
                        <div class="audit-item">
                            <label><?php _e('Aprobado por', 'aura-suite'); ?></label>
                            <div id="audit-approved-by"></div>
                        </div>
                        
                        <div class="audit-item">
                            <label><?php _e('Aprobado el', 'aura-suite'); ?></label>
                            <div id="audit-approved-at"></div>
                        </div>
                    </div>
                    
                    <div class="audit-changes-log">
                        <h4><?php _e('Registro de Cambios', 'aura-suite'); ?></h4>
                        <div id="audit-changes-list" class="changes-timeline"></div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        
        <!-- Pie del Modal (Acciones Rápidas) -->
        <div class="aura-modal-footer">
            <div class="modal-actions-left">
                <button type="button" class="button button-secondary" id="duplicate-transaction" style="display: none;">
                    <span class="dashicons dashicons-admin-page"></span>
                    <?php _e('Duplicar', 'aura-suite'); ?>
                </button>
                <button type="button" class="button button-secondary" id="export-pdf" style="display: none;">
                    <span class="dashicons dashicons-pdf"></span>
                    <?php _e('Exportar PDF', 'aura-suite'); ?>
                </button>
            </div>
            
            <div class="modal-actions-right">
                <?php if (current_user_can('aura_finance_edit_own') || current_user_can('aura_finance_edit_all')): ?>
                <button type="button" class="button button-secondary" id="edit-transaction" style="display: none;">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Editar', 'aura-suite'); ?>
                </button>
                <?php endif; ?>
                
                <?php if (current_user_can('aura_finance_approve')): ?>
                <button type="button" class="button button-primary" id="approve-transaction" style="display: none;">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Aprobar', 'aura-suite'); ?>
                </button>
                <button type="button" class="button button-secondary" id="reject-transaction" style="display: none;">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e('Rechazar', 'aura-suite'); ?>
                </button>
                <?php endif; ?>
                
                <?php if (current_user_can('aura_finance_delete_own') || current_user_can('aura_finance_delete_all')): ?>
                <button type="button" class="button button-link-delete" id="delete-transaction" style="display: none;">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Eliminar', 'aura-suite'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Rechazo -->
<div id="aura-rejection-modal" class="aura-modal aura-small-modal" style="display: none;">
    <div class="aura-modal-overlay"></div>
    <div class="aura-modal-container">
        <div class="aura-modal-header">
            <h3><?php _e('Rechazar Transacción', 'aura-suite'); ?></h3>
            <button type="button" class="aura-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="aura-modal-body">
            <p><?php _e('Por favor, indica el motivo del rechazo:', 'aura-suite'); ?></p>
            <textarea id="rejection-reason" rows="5" class="widefat"
                      placeholder="<?php esc_attr_e('Explica el motivo del rechazo (mínimo 20 caracteres)...', 'aura-suite'); ?>"></textarea>
            <p class="description" style="margin-top:6px;">
                <span id="rejection-char-count" style="font-weight:600;">0</span>
                <?php _e('/ 20 caracteres mínimos', 'aura-suite'); ?>
            </p>
        </div>
        <div class="aura-modal-footer">
            <button type="button" class="button button-secondary cancel-rejection">
                <?php _e('Cancelar', 'aura-suite'); ?>
            </button>
            <button type="button" class="button button-primary confirm-rejection" disabled>
                <?php _e('Confirmar Rechazo', 'aura-suite'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="aura-modal-loading" style="display: none;">
    <div class="spinner is-active"></div>
</div>
