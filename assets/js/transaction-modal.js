/**
 * JavaScript para Modal de Detalle de Transacción
 * 
 * Gestiona la visualización completa de transacciones,
 * tabs, acciones rápidas y actualización en tiempo real
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

(function($) {
    'use strict';
    
    /**
     * Variables globales del modal
     */
    let currentTransactionId = null;
    let currentTransactionData = null;
    
    /**
     * Obtener información de método de pago (traducción, icono y color)
     * 
     * @param {string} paymentMethod Método de pago en inglés o español
     * @return {object} Objeto con label, icon y color
     */
    function getPaymentMethodInfo(paymentMethod) {
        if (!paymentMethod) {
            return { label: '—', icon: '', color: '#8c8f94' };
        }
        
        // Mapeo de métodos de pago: inglés => español
        const translations = {
            'cash': 'Efectivo',
            'transfer': 'Transferencia',
            'check': 'Cheque',
            'card': 'Tarjeta',
            'other': 'Otro'
        };
        
        // Si viene en inglés, traducir a español
        const methodSpanish = translations[paymentMethod] || paymentMethod;
        
        // Mapeo de iconos y colores
        const map = {
            'Efectivo': { icon: 'dashicons-money-alt', color: '#27ae60' },
            'Transferencia': { icon: 'dashicons-bank', color: '#3b82f6' },
            'Cheque': { icon: 'dashicons-media-text', color: '#6366f1' },
            'Tarjeta': { icon: 'dashicons-id-alt', color: '#8b5cf6' },
            'Otro': { icon: 'dashicons-money', color: '#8c8f94' }
        };
        
        const info = map[methodSpanish] || { icon: 'dashicons-money-alt', color: '#8c8f94' };
        return {
            label: methodSpanish,
            icon: info.icon,
            color: info.color
        };
    }
    
    /**
     * Inicializar cuando el DOM esté listo
     */
    $(document).ready(function() {
        initModalTriggers();
        initModalNavigation();
        initModalActions();
        initReceiptUploader();
    });
    
    /**
     * Inicializar triggers para abrir el modal
     */
    function initModalTriggers() {
        // Abrir modal desde listado (delegación de eventos)
        $(document).on('click', '.view-transaction', function(e) {
            e.preventDefault();
            const transactionId = $(this).data('transaction-id');
            openTransactionModal(transactionId);
        });
        
        // Cerrar modal
        $('#close-transaction-modal, .aura-modal-overlay').on('click', function() {
            closeTransactionModal();
        });
        
        // Cerrar con tecla ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#aura-transaction-modal').is(':visible')) {
                closeTransactionModal();
            }
        });
    }
    
    /**
     * Abrir modal y cargar datos de transacción
     */
    function openTransactionModal(transactionId) {
        currentTransactionId = transactionId;
        
        // Mostrar modal con loading
        $('#aura-transaction-modal').fadeIn(300);
        $('.aura-modal-loading').show();
        
        // Cargar datos vía AJAX
        $.ajax({
            url: auraTransactionModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_get_transaction_details',
                nonce: auraTransactionModal.nonce,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    currentTransactionData = response.data;
                    renderTransactionData(response.data);
                    showActionButtons(response.data);
                } else {
                    alert(response.data.message || auraTransactionModal.messages.error);
                    closeTransactionModal();
                }
            },
            error: function() {
                alert(auraTransactionModal.messages.error);
                closeTransactionModal();
            },
            complete: function() {
                $('.aura-modal-loading').hide();
            }
        });
    }
    
    /**
     * Cerrar modal
     */
    function closeTransactionModal() {
        $('#aura-transaction-modal').fadeOut(300);
        currentTransactionId = null;
        currentTransactionData = null;
        
        // Resetear a primera tab
        $('.tab-button').removeClass('active');
        $('.tab-button[data-tab="general"]').addClass('active');
        $('.tab-panel').removeClass('active');
        $('#tab-general').addClass('active');
    }
    
    /**
     * Renderizar datos de la transacción en el modal
     */
    function renderTransactionData(data) {
        // Cabecera
        renderHeader(data);
        
        // Tab 1: Información General
        renderGeneralInfo(data);
        
        // Tab 2: Notas
        renderNotes(data);
        
        // Tab 3: Comprobante
        renderReceipt(data);
        
        // Tab 4: Auditoría
        if ($('#tab-audit').length) {
            renderAuditInfo(data);
        }
    }
    
    /**
     * Renderizar cabecera del modal
     */
    function renderHeader(data) {
        // Badge de estado
        const statusColor = {
            'pending': 'warning',
            'approved': 'success',
            'rejected': 'danger'
        }[data.status] || 'default';
        
        const statusText = {
            'pending': auraTransactionModal.messages.statusPending || 'Pendiente',
            'approved': auraTransactionModal.messages.statusApproved || 'Aprobado',
            'rejected': auraTransactionModal.messages.statusRejected || 'Rechazado'
        }[data.status] || data.status;
        
        $('#modal-status-label')
            .removeClass()
            .addClass('status-label status-' + statusColor)
            .text(statusText);
        
        // Monto
        const amountPrefix = data.transaction_type === 'income' ? '+' : '-';
        const amountClass = data.transaction_type === 'income' ? 'income' : 'expense';
        $('#modal-amount')
            .removeClass()
            .addClass('amount-value amount-' + amountClass)
            .text(amountPrefix + ' $' + formatNumber(data.amount));
        
        // Icono de tipo
        const typeIcon = data.transaction_type === 'income' ? '💰' : '💸';
        const typeText = data.transaction_type === 'income' ? 'Ingreso' : 'Egreso';
        $('#modal-type-icon').html(typeIcon + ' ' + typeText);
        
        // Fecha
        $('#modal-date').text(formatDate(data.transaction_date));
    }
    
    /**
     * Renderizar información general
     */
    function renderGeneralInfo(data) {
        // Categoría
        $('#modal-category').html(
            '<span class="category-badge" style="background-color:' + (data.category.color || '#888') + '">' +
            data.category.name +
            '</span>'
        );
        
        // Descripción
        $('#modal-description').html(escapeHtml(data.description) || '<em>Sin descripción</em>');
        
        // Método de pago con icono
        const paymentInfo = getPaymentMethodInfo(data.payment_method);
        if (paymentInfo.icon) {
            $('#modal-payment-method').html(
                '<span class="dashicons ' + paymentInfo.icon + '" style="color:' + paymentInfo.color + ';font-size:16px;vertical-align:middle;margin-right:5px;"></span>' +
                '<span style="vertical-align:middle;">' + escapeHtml(paymentInfo.label) + '</span>'
            );
        } else {
            $('#modal-payment-method').text(paymentInfo.label);
        }
        
        // Número de referencia
        $('#modal-reference-number').text(data.reference_number || '—');
        
        // Beneficiario/Pagador
        if (data.related_user && data.related_user.id) {
            $('#modal-recipient-payer').html(
                '<img src="' + data.related_user.avatar_url + '" width="20" height="20" ' +
                'style="border-radius:50%;vertical-align:middle;margin-right:5px;">' +
                escapeHtml(data.related_user.name) +
                (data.related_user_concept
                    ? ' <small style="color:#8c8f94">('+escapeHtml(data.related_user_concept)+')</small>'
                    : '')
            );
        } else {
            $('#modal-recipient-payer').text(data.recipient_payer || '—');
        }
        
        // Etiquetas
        if (data.tags && data.tags.length > 0) {
            const tagsHtml = data.tags.map(tag => 
                '<span class="tag-badge">' + escapeHtml(tag) + '</span>'
            ).join('');
            $('#modal-tags').html(tagsHtml);
        } else {
            $('#modal-tags').html('<em>Sin etiquetas</em>');
        }
        
        // Creador
        $('#modal-creator').html(
            '<div class="user-avatar">' +
            '<img src="' + data.creator.avatar + '" alt="' + data.creator.name + '">' +
            '</div>' +
            '<div class="user-info">' +
            '<strong>' + data.creator.name + '</strong>' +
            '<span class="user-date">' + formatDateTime(data.created_at) + '</span>' +
            '</div>'
        );
    }
    
    /**
     * Renderizar notas y observaciones
     */
    function renderNotes(data) {
        // Notas del creador
        $('#modal-notes').html(data.notes ? escapeHtml(data.notes) : '<em>Sin notas adicionales</em>');
        
        // Historial de cambios
        if (data.history && data.history.length > 0) {
            $('#history-section').show();
            const fieldLabels = {
                'status':           'Estado',
                'amount':           'Monto',
                'transaction_date': 'Fecha',
                'description':      'Descripción',
                'category_id':      'Categoría',
                'payment_method':   'Método de pago',
                'reference_number': 'Nº referencia',
                'recipient_payer':  'Beneficiario/Pagador',
                'related_user_id':  'Usuario Vinculado',
                'related_user_concept': 'Concepto de Vinculación',
                'notes':            'Notas',
                'rejection_reason': 'Motivo de rechazo',
                'status_resubmitted': 'Re-enviada',
                'tags':             'Etiquetas'
            };
            let historyHtml = '<ul class="changes-list">';
            data.history.forEach(change => {
                const fieldLabel = fieldLabels[change.field_changed] || change.field_changed;
                historyHtml += '<li class="change-item">';
                historyHtml += '<div class="change-header">';
                historyHtml += '<strong>' + fieldLabel + '</strong>';
                historyHtml += '<span class="change-date">' + formatDateTime(change.changed_at) + '</span>';
                historyHtml += '</div>';
                historyHtml += '<div class="change-details">';
                historyHtml += '<div class="old-value">Anterior: ' + (change.old_value || '—') + '</div>';
                historyHtml += '<div class="new-value">Nuevo: ' + (change.new_value || '—') + '</div>';
                historyHtml += '</div>';
                historyHtml += '<div class="change-user">Por: ' + change.changed_by + '</div>';
                historyHtml += '</li>';
            });
            historyHtml += '</ul>';
            $('#modal-history').html(historyHtml);
        } else {
            $('#history-section').hide();
        }
        
        // Motivo de rechazo
        if (data.status === 'rejected' && data.rejection_reason) {
            $('#rejection-section').show();
            $('#modal-rejection-reason').html(escapeHtml(data.rejection_reason));
        } else {
            $('#rejection-section').hide();
        }
    }
    
    /**
     * Renderizar comprobante
     */
    function renderReceipt(data) {
        if (data.receipt_file) {
            $('#no-receipt-message').hide();
            $('#receipt-actions').show();
            
            const fileExt = data.receipt_file.split('.').pop().toLowerCase();
            let viewerHtml = '';
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                // Es imagen
                viewerHtml = '<img src="' + data.receipt_url + '" alt="Comprobante" class="receipt-image">';
            } else if (fileExt === 'pdf') {
                // Es PDF
                viewerHtml = '<iframe src="' + data.receipt_url + '" class="receipt-pdf"></iframe>';
            } else {
                viewerHtml = '<div class="receipt-file-link">';
                viewerHtml += '<span class="dashicons dashicons-media-document"></span>';
                viewerHtml += '<p>Archivo: ' + data.receipt_file + '</p>';
                viewerHtml += '</div>';
            }
            
            $('#receipt-container').html(viewerHtml);
            $('#download-receipt').attr('href', data.receipt_url);
        } else {
            $('#no-receipt-message').show();
            $('#receipt-actions').hide();
            $('#receipt-container').html('');
        }
    }
    
    /**
     * Renderizar información de auditoría
     */
    function renderAuditInfo(data) {
        $('#audit-created-at').text(formatDateTime(data.created_at));
        $('#audit-created-by').text(data.creator.name);
        
        if (data.updated_at && data.updated_at !== data.created_at) {
            $('#audit-updated-at').text(formatDateTime(data.updated_at));
            $('#audit-updated-by').text(data.updated_by || '—');
        } else {
            $('#audit-updated-at').html('<em>No editado</em>');
            $('#audit-updated-by').html('<em>—</em>');
        }
        
        if (data.approved_by) {
            $('#audit-approved-by').text(data.approved_by);
            $('#audit-approved-at').text(formatDateTime(data.approved_at));
        } else {
            $('#audit-approved-by').html('<em>Pendiente</em>');
            $('#audit-approved-at').html('<em>—</em>');
        }
        
        // Registro de cambios detallado
        if (data.audit_log && data.audit_log.length > 0) {
            const iconMap = {
                'status':           'update',
                'amount':           'cart',
                'transaction_date': 'calendar-alt',
                'description':      'edit',
                'category_id':      'tag',
                'payment_method':   'money-alt',
                'reference_number': 'index-card',
                'rejection_reason': 'dismiss',
                'status_resubmitted': 'redo'
            };
            const fieldLabels = {
                'status':             'Cambio de estado',
                'amount':             'Cambio de monto',
                'transaction_date':   'Cambio de fecha',
                'description':        'Cambio de descripción',
                'category_id':        'Cambio de categoría',
                'payment_method':     'Cambio de método de pago',
                'reference_number':   'Cambio de referencia',
                'rejection_reason':   'Motivo de rechazo',
                'status_resubmitted': 'Re-enviada para aprobación'
            };
            let logHtml = '<div class="audit-timeline">';
            data.audit_log.forEach(entry => {
                const icon   = iconMap[entry.field_changed]   || 'edit';
                const action = fieldLabels[entry.field_changed] || entry.field_changed;
                const details = (entry.old_value || '—') + ' → ' + (entry.new_value || '—');
                logHtml += '<div class="audit-entry">';
                logHtml += '<div class="audit-icon"><span class="dashicons dashicons-' + icon + '"></span></div>';
                logHtml += '<div class="audit-content">';
                logHtml += '<strong>' + action + '</strong>';
                logHtml += '<p>' + details + '</p>';
                logHtml += '<span class="audit-meta">' + entry.changed_by + ' — ' + formatDateTime(entry.changed_at) + '</span>';
                logHtml += '</div>';
                logHtml += '</div>';
            });
            logHtml += '</div>';
            $('#audit-changes-list').html(logHtml);
        } else {
            $('#audit-changes-list').html('<p><em>No hay cambios registrados</em></p>');
        }
    }
    
    /**
     * Mostrar botones de acción según permisos y estado
     */
    function showActionButtons(data) {
        // Ocultar todos primero
        $('.modal-actions-right button, .modal-actions-left button').hide();
        
        // Duplicar (siempre visible si puede crear)
        if (auraTransactionModal.permissions.canCreate) {
            $('#duplicate-transaction').show();
        }
        
        // Exportar PDF (siempre visible)
        $('#export-pdf').show();
        
        // Editar
        if (canEditTransaction(data)) {
            $('#edit-transaction').show();
        }
        
        // Aprobar y Rechazar (solo si está pendiente)
        if (auraTransactionModal.permissions.canApprove && data.status === 'pending') {
            // No puede aprobar sus propias transacciones
            if (data.created_by !== auraTransactionModal.currentUserId) {
                $('#approve-transaction').show();
                $('#reject-transaction').show();
            }
        }
        
        // Eliminar
        if (canDeleteTransaction(data)) {
            $('#delete-transaction').show();
        }
    }
    
    /**
     * Determinar si puede editar la transacción
     */
    function canEditTransaction(data) {
        if (auraTransactionModal.permissions.canEditAll) {
            return true;
        }
        
        if (auraTransactionModal.permissions.canEditOwn) {
            // Solo si es el creador y está pendiente
            return data.created_by === auraTransactionModal.currentUserId && data.status === 'pending';
        }
        
        return false;
    }
    
    /**
     * Determinar si puede eliminar la transacción
     */
    function canDeleteTransaction(data) {
        if (auraTransactionModal.permissions.canDeleteAll) {
            return true;
        }
        
        if (auraTransactionModal.permissions.canDeleteOwn) {
            return data.created_by === auraTransactionModal.currentUserId;
        }
        
        return false;
    }
    
    /**
     * Navegación entre tabs
     */
    function initModalNavigation() {
        $('.tab-button').on('click', function() {
            const targetTab = $(this).data('tab');
            
            // Actualizar botones
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Actualizar paneles
            $('.tab-panel').removeClass('active');
            $('#tab-' + targetTab).addClass('active');
        });
    }
    
    /**
     * Inicializar acciones del modal
     */
    function initModalActions() {
        // Aprobar transacción
        $('#approve-transaction').on('click', function() {
            if (confirm(auraTransactionModal.messages.confirmApprove)) {
                approveTransaction(currentTransactionId);
            }
        });
        
        // Rechazar transacción
        $('#reject-transaction').on('click', function() {
            openRejectionModal();
        });
        
        // Contador de caracteres en motivo de rechazo
        $(document).on('input', '#rejection-reason', function() {
            const length = $(this).val().trim().length;
            const $count = $('#rejection-char-count');
            $count.text(length);
            $count.css('color', length >= 20 ? '#10b981' : '#e74c3c');
            $('.confirm-rejection').prop('disabled', length < 20);
        });
        
        // Confirmar rechazo
        $('.confirm-rejection').on('click', function() {
            const reason = $('#rejection-reason').val().trim();
            if (reason.length < 20) {
                $('#rejection-char-count').css('color', '#e74c3c');
                $('#rejection-reason').focus();
                return;
            }
            rejectTransaction(currentTransactionId, reason);
        });
        
        // Cancelar rechazo
        $('.cancel-rejection').on('click', function() {
            closeRejectionModal();
        });
        
        // Editar
        $('#edit-transaction').on('click', function() {
            window.location.href = auraTransactionModal.editUrl + '&transaction_id=' + currentTransactionId;
        });
        
        // Eliminar
        $('#delete-transaction').on('click', function() {
            if (confirm(auraTransactionModal.messages.confirmDelete)) {
                deleteTransaction(currentTransactionId);
            }
        });
        
        // Duplicar
        $('#duplicate-transaction').on('click', function() {
            duplicateTransaction(currentTransactionId);
        });
        
        // Exportar PDF
        $('#export-pdf').on('click', function() {
            exportTransactionPDF(currentTransactionId);
        });
    }
    
    /**
     * Aprobar transacción
     */
    function approveTransaction(transactionId) {
        const $btn = $('#approve-transaction');
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> Aprobando...');
        
        $.ajax({
            url: auraTransactionModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_approve_transaction',
                nonce: auraTransactionModal.approvalNonce || auraTransactionModal.nonce,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    closeTransactionModal();
                    showPageNotice('success', auraTransactionModal.messages.approveSuccess || 'Transacción aprobada.');
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    alert(response.data.message || auraTransactionModal.messages.error);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Aprobar');
                }
            },
            error: function() {
                alert(auraTransactionModal.messages.error);
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> Aprobar');
            }
        });
    }
    
    /**
     * Abrir modal de rechazo
     */
    function openRejectionModal() {
        $('#rejection-reason').val('');
        $('#rejection-char-count').text('0').css('color', '#e74c3c');
        $('.confirm-rejection').prop('disabled', true);
        $('#aura-rejection-modal').fadeIn(300);
    }
    
    /**
     * Cerrar modal de rechazo
     */
    function closeRejectionModal() {
        $('#aura-rejection-modal').fadeOut(300);
    }
    
    /**
     * Rechazar transacción
     */
    function rejectTransaction(transactionId, reason) {
        const $btn = $('.confirm-rejection');
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> Rechazando...');
        
        $.ajax({
            url: auraTransactionModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_reject_transaction',
                nonce: auraTransactionModal.approvalNonce || auraTransactionModal.nonce,
                transaction_id: transactionId,
                rejection_reason: reason
            },
            success: function(response) {
                if (response.success) {
                    closeRejectionModal();
                    closeTransactionModal();
                    showPageNotice('warning', auraTransactionModal.messages.rejectSuccess || 'Transacción rechazada.');
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    alert(response.data.message || auraTransactionModal.messages.error);
                    $btn.prop('disabled', false).html('Confirmar Rechazo');
                }
            },
            error: function() {
                alert(auraTransactionModal.messages.error);
                $btn.prop('disabled', false).html('Confirmar Rechazo');
            }
        });
    }
    
    /**
     * Mostrar notificación en la página
     */
    function showPageNotice(type, message) {
        const cssClass = type === 'success' ? 'notice-success' : type === 'warning' ? 'notice-warning' : 'notice-error';
        const $notice = $('<div class="notice ' + cssClass + ' is-dismissible" style="margin:15px 0;"><p>' + message + '</p></div>');
        $('.wrap h1, .wrap h2').first().after($notice);
    }
    
    /**
     * Eliminar transacción
     */
    function deleteTransaction(transactionId) {
        $.ajax({
            url: auraTransactionModal.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_delete_transaction',
                nonce: auraTransactionModal.nonce,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    alert('Transacción eliminada');
                    closeTransactionModal();
                    if (typeof reloadTransactionsList === 'function') {
                        reloadTransactionsList();
                    }
                } else {
                    alert(response.data.message || auraTransactionModal.messages.error);
                }
            },
            error: function() {
                alert(auraTransactionModal.messages.error);
            }
        });
    }
    
    /**
     * Duplicar transacción
     */
    function duplicateTransaction(transactionId) {
        window.location.href = auraTransactionModal.newUrl + '&duplicate=' + transactionId;
    }
    
    /**
     * Exportar a PDF
     */
    function exportTransactionPDF(transactionId) {
        window.open(auraTransactionModal.exportUrl + '&transaction_id=' + transactionId, '_blank');
    }
    
    /**
     * Inicializar uploader de comprobantes
     */
    function initReceiptUploader() {
        // Implementar con WordPress Media Uploader en futuro
        $('#upload-receipt-btn').on('click', function() {
            alert('Funcionalidad de upload en desarrollo');
        });
    }
    
    /**
     * Utilidades de formato
     */
    function formatNumber(number) {
        return parseFloat(number).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
})(jQuery);
