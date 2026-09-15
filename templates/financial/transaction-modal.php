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
            <button type="button" class="aura-modal-close" id="close-transaction-modal" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>" title="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">
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
                            <label><?php _e('Categoría Financiera', 'aura-suite'); ?></label>
                            <div id="modal-category" class="category-badge-container"></div>
                        </div>

                        <div class="info-item">
                            <label><?php _e('Área / Programa', 'aura-suite'); ?></label>
                            <div id="modal-area"></div>
                        </div>

                        <div class="info-item full-width category-details-item" id="modal-category-details-container" style="display: none;">
                            <label><?php _e('Detalles de Categorización Contable', 'aura-suite'); ?></label>
                            <div id="modal-category-details" class="aura-modal-cat-details-box"></div>
                        </div>

                        <div class="info-item">
                            <label><?php _e('Cuenta / Caja Origen', 'aura-suite'); ?></label>
                            <div id="modal-source-account"></div>
                        </div>

                        <div class="info-item">
                            <label><?php _e('Cuenta / Caja Destino', 'aura-suite'); ?></label>
                            <div id="modal-destination-account"></div>
                        </div>
                        
                        <div class="info-item full-width">
                            <label><?php _e('Descripción del Movimiento', 'aura-suite'); ?></label>
                            <div id="modal-description" class="description-text"></div>
                        </div>
                        
                        <div class="info-item">
                            <label><?php _e('Método de Pago', 'aura-suite'); ?></label>
                            <div id="modal-payment-method"></div>
                        </div>
                        
                        <div class="info-item">
                            <label><?php _e('Nº Referencia / Folio', 'aura-suite'); ?></label>
                            <div id="modal-reference-number"></div>
                        </div>
                        
                        <div class="info-item full-width recipient-info">
                            <label><?php _e('Beneficiario / Proveedor / Tercero (Ficha 360°)', 'aura-suite'); ?></label>
                            <div id="modal-recipient-payer" class="user-details"></div>
                        </div>
                        
                        <div class="info-item">
                            <label><?php _e('Etiquetas (#tags)', 'aura-suite'); ?></label>
                            <div id="modal-tags" class="tags-container"></div>
                        </div>

                        <div class="info-item" id="modal-integration-container" style="display: none;">
                            <label><?php _e('Módulo Vinculado', 'aura-suite'); ?></label>
                            <div id="modal-module-integration"></div>
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

                        <div class="audit-item">
                            <label><?php _e('Lote de Importación', 'aura-suite'); ?></label>
                            <div id="audit-import-batch"></div>
                        </div>

                        <div class="audit-item">
                            <label><?php _e('Origen de Integración', 'aura-suite'); ?></label>
                            <div id="audit-integration-origin"></div>
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
                <button type="button" class="button button-secondary aura-modal-btn-close" id="btn-close-modal-footer">
                    <span class="dashicons dashicons-no-alt"></span>
                    <?php _e('Cerrar', 'aura-suite'); ?>
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
            <button type="button" class="aura-modal-close" aria-label="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>" title="<?php esc_attr_e('Cerrar', 'aura-suite'); ?>">
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

<!-- Modal Visor Universal de Recibos y Comprobantes (Google Drive e Imágenes/PDFs Locales) -->
<div id="aura-receipt-viewer-modal" class="aura-receipt-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="aura-receipt-viewer-title">
    <div class="aura-receipt-overlay" id="aura-receipt-overlay-backdrop"></div>
    <div class="aura-receipt-window">
        <div class="aura-receipt-header">
            <div class="aura-receipt-title-wrap">
                <span class="aura-receipt-icon dashicons dashicons-media-document"></span>
                <div>
                    <h3 id="aura-receipt-viewer-title" class="aura-receipt-title"><?php _e('Comprobante de Transacción', 'aura-suite'); ?></h3>
                    <div id="aura-receipt-badge" class="aura-receipt-meta-badge"></div>
                </div>
            </div>
            
            <div class="aura-receipt-toolbar">
                <div class="aura-receipt-nav-group" id="aura-receipt-nav" style="display: none;">
                    <button type="button" class="aura-rc-btn" id="aura-rc-prev" title="<?php esc_attr_e('Comprobante anterior', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Anterior', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <span id="aura-rc-counter" class="aura-rc-counter">1 / 1</span>
                    <button type="button" class="aura-rc-btn" id="aura-rc-next" title="<?php esc_attr_e('Comprobante siguiente', 'aura-suite'); ?>" aria-label="<?php esc_attr_e('Siguiente', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>

                <div class="aura-receipt-zoom-group" id="aura-receipt-zoom-tools">
                    <button type="button" class="aura-rc-btn" id="aura-rc-zoom-out" title="<?php esc_attr_e('Reducir zoom', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-minus"></span>
                    </button>
                    <span id="aura-rc-zoom-level" class="aura-rc-zoom-text">100%</span>
                    <button type="button" class="aura-rc-btn" id="aura-rc-zoom-in" title="<?php esc_attr_e('Aumentar zoom', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-plus-alt2"></span>
                    </button>
                    <button type="button" class="aura-rc-btn" id="aura-rc-rotate" title="<?php esc_attr_e('Girar 90°', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-image-rotate"></span>
                    </button>
                    <button type="button" class="aura-rc-btn" id="aura-rc-reset" title="<?php esc_attr_e('Restablecer', 'aura-suite'); ?>">
                        <span class="dashicons dashicons-image-filter"></span>
                    </button>
                </div>

                <a href="#" target="_blank" class="aura-rc-btn aura-rc-btn-accent" id="aura-rc-open-drive" title="<?php esc_attr_e('Abrir en Google Drive', 'aura-suite'); ?>" style="display: none;">
                    <span class="dashicons dashicons-cloud"></span>
                    <span class="aura-rc-btn-label"><?php _e('Drive', 'aura-suite'); ?></span>
                </a>

                <a href="#" download class="aura-rc-btn" id="aura-rc-download" title="<?php esc_attr_e('Descargar comprobante', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-download"></span>
                </a>

                <button type="button" class="aura-rc-btn aura-rc-close-btn" id="aura-rc-close" title="<?php esc_attr_e('Cerrar (Esc)', 'aura-suite'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
        </div>

        <div class="aura-receipt-body" id="aura-receipt-body">
            <div class="aura-receipt-spinner" id="aura-receipt-spinner" style="display: none;">
                <div class="spinner is-active"></div>
                <span><?php _e('Cargando comprobante...', 'aura-suite'); ?></span>
            </div>
            <div class="aura-receipt-stage" id="aura-receipt-stage"></div>
            <div class="aura-rc-thumbs-bar" id="aura-receipt-thumbs-bar" style="display: none;"></div>
        </div>
    </div>
</div>

