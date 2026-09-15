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
        
        const raw = String(paymentMethod).trim().toLowerCase();
        
        const map = {
            'cash':           { label: 'Efectivo',               icon: 'dashicons-money-alt',  color: '#10b981' },
            'efectivo':       { label: 'Efectivo',               icon: 'dashicons-money-alt',  color: '#10b981' },
            'transfer':       { label: 'Transferencia',          icon: 'dashicons-bank',       color: '#3b82f6' },
            'transferencia':  { label: 'Transferencia',          icon: 'dashicons-bank',       color: '#3b82f6' },
            'bank_transfer':  { label: 'Transferencia Bancaria', icon: 'dashicons-bank',       color: '#3b82f6' },
            'card':           { label: 'Tarjeta',                icon: 'dashicons-id-alt',     color: '#8b5cf6' },
            'tarjeta':        { label: 'Tarjeta',                icon: 'dashicons-id-alt',     color: '#8b5cf6' },
            'credit_card':    { label: 'Tarjeta de Crédito',     icon: 'dashicons-id-alt',     color: '#8b5cf6' },
            'debit_card':     { label: 'Tarjeta de Débito',      icon: 'dashicons-id-alt',     color: '#8b5cf6' },
            'check':          { label: 'Cheque',                 icon: 'dashicons-media-text', color: '#6366f1' },
            'cheque':         { label: 'Cheque',                 icon: 'dashicons-media-text', color: '#6366f1' },
            'digital_wallet': { label: 'Billetera Digital',      icon: 'dashicons-smartphone', color: '#ec4899' },
            'nequi':          { label: 'Nequi',                  icon: 'dashicons-smartphone', color: '#ec4899' },
            'daviplata':      { label: 'DaviPlata',              icon: 'dashicons-smartphone', color: '#ec4899' },
            'other':          { label: 'Otro',                   icon: 'dashicons-ellipsis',   color: '#64748b' },
            'otro':           { label: 'Otro',                   icon: 'dashicons-ellipsis',   color: '#64748b' }
        };
        
        return map[raw] || { 
            label: paymentMethod.charAt(0).toUpperCase() + paymentMethod.slice(1), 
            icon: 'dashicons-money-alt', 
            color: '#64748b' 
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
        // Abrir modal desde listado (delegación de eventos para todas las variantes de botones)
        $(document).on('click', '.view-transaction, .view-transaction-details, .aura-view-transaction', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const transactionId = $(this).data('transaction-id') || $(this).data('id');
            if (transactionId) {
                openTransactionModal(transactionId);
            }
        });
        
        // Cerrar modal de rechazo
        $(document).on('click', '.cancel-rejection, #aura-rejection-modal .aura-modal-close, #aura-rejection-modal .aura-modal-overlay', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeRejectionModal();
        });

        // Cerrar modal principal de transacción
        $(document).on('click', '#close-transaction-modal, #btn-close-modal-footer, #aura-transaction-modal .aura-modal-close, #aura-transaction-modal .aura-modal-overlay, .aura-modal-btn-close', function(e) {
            e.preventDefault();
            closeTransactionModal();
        });
        
        // Cerrar con tecla ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                if ($('#aura-rejection-modal').is(':visible')) {
                    closeRejectionModal();
                } else if ($('#aura-transaction-modal').is(':visible')) {
                    closeTransactionModal();
                }
            }
        });
    }
    
    /**
     * Abrir modal y cargar datos de transacción
     */
    function openTransactionModal(transactionId) {
        if (!transactionId) return;
        currentTransactionId = transactionId;
        
        // Mostrar modal con loading
        $('#aura-transaction-modal').fadeIn(200);
        $('.aura-modal-loading').show();
        
        const nonceVal = (typeof auraTransactionModal !== 'undefined' && auraTransactionModal.nonce)
            ? auraTransactionModal.nonce
            : ((typeof auraTransactionsList !== 'undefined' && auraTransactionsList.nonce) ? auraTransactionsList.nonce : '');

        // Cargar datos vía AJAX
        $.ajax({
            url: (typeof auraTransactionModal !== 'undefined' && auraTransactionModal.ajaxUrl) ? auraTransactionModal.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action: 'aura_get_transaction_details',
                nonce: nonceVal,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response && response.success && response.data) {
                    currentTransactionData = response.data;
                    try {
                        renderTransactionData(response.data);
                        showActionButtons(response.data);
                    } catch (renderErr) {
                        console.error('Aura Modal render error:', renderErr);
                    }
                } else {
                    const msg = (response && response.data && response.data.message)
                        ? response.data.message
                        : ((typeof auraTransactionModal !== 'undefined' && auraTransactionModal.messages) ? auraTransactionModal.messages.error : 'Error al cargar detalles.');
                    alert(msg);
                    closeTransactionModal();
                }
            },
            error: function(xhr, status, error) {
                console.error('Aura Modal AJAX Error:', status, error, xhr.responseText);
                const msg = (typeof auraTransactionModal !== 'undefined' && auraTransactionModal.messages)
                    ? auraTransactionModal.messages.error
                    : 'Error de conexión al cargar la transacción.';
                alert(msg);
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
        $('#aura-transaction-modal').fadeOut(200);
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
        if (!data) return;

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
        
        const msgs = (typeof auraTransactionModal !== 'undefined' && auraTransactionModal.messages) ? auraTransactionModal.messages : {};
        const statusText = {
            'pending': msgs.statusPending || 'Pendiente',
            'approved': msgs.statusApproved || 'Aprobado',
            'rejected': msgs.statusRejected || 'Rechazado'
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
            .text(amountPrefix + ' $' + formatNumber(data.amount || 0));
        
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
        const cat = data.category || {};
        let catIconHtml = '';
        if (cat.icon) {
            const rawIcon = cat.icon.trim();
            if (rawIcon.startsWith('dashicons-') || rawIcon.includes('dashicons')) {
                catIconHtml = '<span class="dashicons ' + escapeHtml(rawIcon) + '" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span>';
            } else {
                catIconHtml = '<span class="aura-cat-emoji" style="font-size:14px;line-height:1;vertical-align:middle;margin-right:4px;">' + escapeHtml(rawIcon) + '</span>';
            }
        }
        
        // Categoría principal con badge de Naturaleza Contable
        const txType = (data.transaction_type || '').toLowerCase();
        const isCapex = (txType === 'capital') || (parseInt(cat.is_capex, 10) === 1) || (cat.type === 'capital');
        let capexTag = '';
        let natureLabel = '';

        if (isCapex) {
            capexTag = ' <span class="badge-capex" style="font-size:11px;padding:2px 7px;background:rgba(124,58,237,0.15);color:#c084fc;border:1px solid rgba(124,58,237,0.3);border-radius:10px;margin-left:6px;vertical-align:middle;font-weight:600;">🏛️ CapEx</span>';
            natureLabel = '<span style="color:#c084fc;font-weight:700;">🏛️ CapEx (Gasto de Capital)</span>';
        } else if (txType === 'income') {
            capexTag = ' <span class="badge-income" style="font-size:11px;padding:2px 7px;background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);border-radius:10px;margin-left:6px;vertical-align:middle;font-weight:600;">📈 Ingreso</span>';
            natureLabel = '<span style="color:#34d399;font-weight:700;">📈 Ingreso Operativo</span>';
        } else if (txType === 'transfer') {
            capexTag = ' <span class="badge-transfer" style="font-size:11px;padding:2px 7px;background:rgba(37,99,235,0.15);color:#93c5fd;border:1px solid rgba(37,99,235,0.3);border-radius:10px;margin-left:6px;vertical-align:middle;font-weight:600;">⇄ Transferencia</span>';
            natureLabel = '<span style="color:#93c5fd;font-weight:700;">⇄ Transferencia Interna</span>';
        } else {
            capexTag = ' <span class="badge-opex" style="font-size:11px;padding:2px 7px;background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);border-radius:10px;margin-left:6px;vertical-align:middle;font-weight:600;">💼 OpEx</span>';
            natureLabel = '<span style="color:#f87171;font-weight:600;">💼 OpEx (Gasto Operativo)</span>';
        }

        $('#modal-category').html(
            '<div style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:4px;">' +
            '<span class="category-badge" style="background-color:' + (cat.color || '#64748b') + ';display:inline-flex;align-items:center;padding:4px 10px;border-radius:6px;color:#fff;font-weight:600;font-size:13px;">' +
            catIconHtml + escapeHtml(cat.name || '—') +
            '</span>' +
            capexTag +
            '</div>'
        );

        // Cuadro de Detalles de Categorización Contable
        let catDetailsHtml = '';
        if (natureLabel) {
            catDetailsHtml += '<div class="aura-modal-cat-detail-row"><strong>Naturaleza Contable:</strong> <span>' + natureLabel + '</span></div>';
        }
        if (cat.parent_name) {
            let pIconHtml = '';
            if (cat.parent_icon) {
                if (cat.parent_icon.startsWith('dashicons-')) {
                    pIconHtml = '<span class="dashicons ' + escapeHtml(cat.parent_icon) + '" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:3px;"></span>';
                } else {
                    pIconHtml = '<span style="font-size:13px;margin-right:3px;">' + escapeHtml(cat.parent_icon) + '</span>';
                }
            }
            catDetailsHtml += '<div class="aura-modal-cat-detail-row"><strong style="color:var(--text-primary, #e2e8f0);"><span class="dashicons dashicons-networking" style="font-size:14px;vertical-align:middle;margin-right:4px;color:#818cf8;"></span>Categoría Superior / Padre:</strong> <span>' + pIconHtml + escapeHtml(cat.parent_name) + '</span></div>';
        }
        if (cat.slug) {
            catDetailsHtml += '<div class="aura-modal-cat-detail-row"><strong>Código / Slug:</strong> <code>' + escapeHtml(cat.slug) + '</code></div>';
        }
        if (data.expense_category && data.expense_category.name && data.expense_category.name !== cat.name) {
            catDetailsHtml += '<div class="aura-modal-cat-detail-row"><strong>Categoría de Gasto Específica:</strong> <span>' + escapeHtml(data.expense_category.name) + '</span></div>';
        }
        if (cat.description) {
            catDetailsHtml += '<div class="aura-modal-cat-detail-row" style="grid-column:1/-1;border-top:1px dashed rgba(255,255,255,0.08);padding-top:6px;margin-top:2px;"><strong>Descripción Contable:</strong> <span style="font-style:italic;color:var(--text-secondary, #94a3b8);">' + escapeHtml(cat.description) + '</span></div>';
        }

        if (catDetailsHtml) {
            $('#modal-category-details').html(catDetailsHtml);
            $('#modal-category-details-container').show();
        } else {
            $('#modal-category-details-container').hide();
        }

        // Área / Programa con detalles
        const area = data.area || {};
        if (area.name && area.name !== 'General (sin área)') {
            let areaIconHtml = '<span class="dashicons dashicons-building" style="font-size:14px;vertical-align:middle;margin-right:4px;color:#60a5fa;"></span>';
            if (area.icon) {
                if (area.icon.startsWith('dashicons-')) {
                    areaIconHtml = '<span class="dashicons ' + escapeHtml(area.icon) + '" style="font-size:14px;vertical-align:middle;margin-right:4px;color:' + (area.color || '#60a5fa') + ';"></span>';
                } else {
                    areaIconHtml = '<span style="font-size:14px;margin-right:4px;">' + escapeHtml(area.icon) + '</span>';
                }
            }
            let areaTypeBadge = area.type ? '<span class="aura-badge" style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.3);margin-left:6px;">' + escapeHtml(area.type) + '</span>' : '';
            let areaDesc = area.description ? '<div style="font-size:11px;color:var(--text-secondary, #94a3b8);margin-top:3px;line-height:1.3;">' + escapeHtml(area.description) + '</div>' : '';
            
            $('#modal-area').html(
                '<div style="line-height:1.4;">' +
                '<strong>' + areaIconHtml + escapeHtml(area.name) + '</strong>' + areaTypeBadge +
                areaDesc +
                '</div>'
            );
        } else {
            $('#modal-area').html('<em>General (sin área)</em>');
        }

        // Cuentas origen y destino con banco/caja e institución
        const src = (data.accounts && data.accounts.source) ? data.accounts.source : null;
        if (src && src.name) {
            let srcInst = src.institution ? '<span style="font-size:11px;color:var(--text-secondary, #94a3b8);display:block;">🏛️ ' + escapeHtml(src.institution) + (src.type ? ' · ' + escapeHtml(src.type) : '') + '</span>' : '';
            let srcCurr = src.currency ? ' <span style="font-size:11px;opacity:0.8;font-weight:600;">(' + escapeHtml(src.currency) + ')</span>' : '';
            $('#modal-source-account').html('<div><strong>' + escapeHtml(src.name) + '</strong>' + srcCurr + srcInst + '</div>');
        } else {
            $('#modal-source-account').html('<em>—</em>');
        }

        const dst = (data.accounts && data.accounts.destination) ? data.accounts.destination : null;
        if (dst && dst.name) {
            let dstInst = dst.institution ? '<span style="font-size:11px;color:var(--text-secondary, #94a3b8);display:block;">🏛️ ' + escapeHtml(dst.institution) + (dst.type ? ' · ' + escapeHtml(dst.type) : '') + '</span>' : '';
            let dstCurr = dst.currency ? ' <span style="font-size:11px;opacity:0.8;font-weight:600;">(' + escapeHtml(dst.currency) + ')</span>' : '';
            $('#modal-destination-account').html('<div><strong>' + escapeHtml(dst.name) + '</strong>' + dstCurr + dstInst + '</div>');
        } else {
            $('#modal-destination-account').html('<em>—</em>');
        }

        // Descripción
        $('#modal-description').html(data.description ? escapeHtml(data.description) : '<em>Sin descripción</em>');
        
        // Método de pago con icono
        const paymentInfo = getPaymentMethodInfo(data.payment_method);
        if (paymentInfo.icon) {
            $('#modal-payment-method').html(
                '<span class="dashicons ' + paymentInfo.icon + '" style="color:' + paymentInfo.color + ';font-size:16px;vertical-align:middle;margin-right:5px;"></span>' +
                '<span style="vertical-align:middle;font-weight:500;">' + escapeHtml(paymentInfo.label) + '</span>'
            );
        } else {
            $('#modal-payment-method').text(paymentInfo.label || '—');
        }
        
        // Número de referencia
        $('#modal-reference-number').text(data.reference_number || '—');
        
        // Beneficiario/Pagador / Tercero (Ficha 360°)
        if (data.third_party && data.third_party.id) {
            const tp = data.third_party;
            const logoHtml = tp.logo_url
                ? '<img src="' + escapeHtml(tp.logo_url) + '" alt="' + escapeHtml(tp.name) + '" class="aura-previewable-avatar aura-avatar-zoom-trigger" data-full-img="' + escapeHtml(tp.logo_url) + '" data-caption="' + escapeHtml(tp.name) + '" style="cursor:zoom-in;">'
                : '<div class="avatar-icon-placeholder"><span class="dashicons ' + (tp.icon || 'dashicons-building') + '" style="font-size:24px;width:24px;height:24px;"></span></div>';
            
            const badgeLabel = tp.accounting_role_label || tp.role_label || tp.type_label || 'Proveedores / Comercios';
            
            let contactDetails = [];
            if (tp.phone) {
                const cleanPhone = tp.phone.replace(/[^0-9+]/g, '');
                contactDetails.push('<a href="https://wa.me/' + cleanPhone.replace('+', '') + '" target="_blank" rel="noopener noreferrer" class="aura-modal-contact-link" style="color:#22c55e;text-decoration:none;display:inline-flex;align-items:center;gap:3px;font-weight:600;"><span class="dashicons dashicons-whatsapp" style="font-size:13px;width:13px;height:13px;"></span>' + escapeHtml(tp.phone) + '</a>');
            }
            if (tp.email) {
                contactDetails.push('<a href="mailto:' + escapeHtml(tp.email) + '" class="aura-modal-contact-link" style="color:#38bdf8;text-decoration:none;display:inline-flex;align-items:center;gap:3px;"><span class="dashicons dashicons-email-alt" style="font-size:13px;width:13px;height:13px;"></span>' + escapeHtml(tp.email) + '</a>');
            }
            if (tp.website) {
                let webHref = tp.website.startsWith('http') ? tp.website : 'https://' + tp.website;
                contactDetails.push('<a href="' + escapeHtml(webHref) + '" target="_blank" rel="noopener noreferrer" class="aura-modal-contact-link" style="color:#a855f7;text-decoration:none;display:inline-flex;align-items:center;gap:3px;"><span class="dashicons dashicons-admin-site" style="font-size:13px;width:13px;height:13px;"></span>' + escapeHtml(tp.website.replace(/^https?:\/\//, '')) + '</a>');
            }
            if (tp.address) {
                contactDetails.push('<span style="color:var(--text-secondary, #94a3b8);display:inline-flex;align-items:center;gap:3px;"><span class="dashicons dashicons-location" style="font-size:13px;width:13px;height:13px;"></span>' + escapeHtml(tp.address) + '</span>');
            }

            const contactRow = contactDetails.length > 0 
                ? '<div class="aura-modal-tp-contacts" style="display:flex;flex-wrap:wrap;gap:12px;margin-top:6px;font-size:12px;">' + contactDetails.join('<span style="color:rgba(255,255,255,0.2);">|</span>') + '</div>' 
                : '';

            const docText = tp.document_id 
                ? '<span class="user-date" style="font-size:12px;color:var(--text-secondary, #94a3b8);"><strong style="color:var(--text-primary, #e2e8f0);">' + escapeHtml(tp.tax_id_type || 'NIT') + ':</strong> ' + escapeHtml(tp.document_id) + (tp.full_name && tp.full_name !== tp.name ? ' &middot; <span style="font-style:italic;">' + escapeHtml(tp.full_name) + '</span>' : '') + '</span>' 
                : (tp.full_name && tp.full_name !== tp.name ? '<span class="user-date" style="font-size:12px;font-style:italic;">' + escapeHtml(tp.full_name) + '</span>' : '');

            const tpNotesHtml = tp.notes 
                ? '<div style="margin-top:6px;font-size:11px;color:var(--text-secondary, #94a3b8);background:rgba(0,0,0,0.15);padding:4px 8px;border-radius:4px;border-left:2px solid #3b82f6;"><strong>Nota de Ficha:</strong> ' + escapeHtml(tp.notes) + '</div>' 
                : '';

            $('#modal-recipient-payer').html(
                '<div class="user-avatar">' + logoHtml + '</div>' +
                '<div class="user-info" style="flex:1;">' +
                '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">' +
                '<strong style="font-size:14px;color:var(--text-primary, #f8fafc);">' + escapeHtml(tp.name) + '</strong>' +
                '<span class="aura-badge" style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:12px;background:rgba(59,130,246,0.2);color:#60a5fa;border:1px solid rgba(59,130,246,0.4);">' + escapeHtml(badgeLabel) + '</span>' +
                '</div>' +
                docText +
                contactRow +
                tpNotesHtml +
                '</div>'
            );
        } else if (data.related_user && data.related_user.id) {
            const userAvatar = data.related_user.avatar_url
                ? '<img src="' + escapeHtml(data.related_user.avatar_url) + '" alt="' + escapeHtml(data.related_user.name) + '">'
                : '<div class="avatar-icon-placeholder"><span class="dashicons dashicons-admin-users" style="font-size:24px;width:24px;height:24px;"></span></div>';
            
            const conceptMap = {
                'payment_to_user':       'Pago a usuario',
                'charge_to_user':        'Cobro a usuario',
                'salary':                'Pago de salario / nómina',
                'scholarship':           'Beca asignada',
                'loan_payment':          'Pago de préstamo',
                'refund':                'Reembolso',
                'expense_reimbursement': 'Reembolso de gastos',
                'unlinked':              'Desvinculado',
                'none':                  'Ninguno'
            };
            const conceptText = data.related_user_concept_label || (data.related_user && data.related_user.concept_label) || conceptMap[data.related_user_concept] || data.related_user_concept || 'Usuario Vinculado';
            const conceptLabel = '<span class="user-date" style="color:#60a5fa;font-weight:600;">' + escapeHtml(conceptText) + '</span>';

            let userEmailLink = data.related_user.email ? ' &middot; <a href="mailto:' + escapeHtml(data.related_user.email) + '" style="color:#38bdf8;text-decoration:none;">' + escapeHtml(data.related_user.email) + '</a>' : '';

            $('#modal-recipient-payer').html(
                '<div class="user-avatar">' + userAvatar + '</div>' +
                '<div class="user-info">' +
                '<strong>' + escapeHtml(data.related_user.name) + '</strong>' +
                '<div>' + conceptLabel + userEmailLink + '</div>' +
                '</div>'
            );
        } else if (data.recipient_payer) {
            $('#modal-recipient-payer').html(
                '<div class="user-avatar">' +
                '<div class="avatar-icon-placeholder"><span class="dashicons dashicons-businessman" style="font-size:24px;width:24px;height:24px;"></span></div>' +
                '</div>' +
                '<div class="user-info">' +
                '<strong>' + escapeHtml(data.recipient_payer) + '</strong>' +
                '<span class="user-date">Tercero Externo</span>' +
                '</div>'
            );
        } else {
            $('#modal-recipient-payer').html(
                '<div class="user-avatar">' +
                '<div class="avatar-icon-placeholder"><span class="dashicons dashicons-minus" style="font-size:24px;width:24px;height:24px;"></span></div>' +
                '</div>' +
                '<div class="user-info">' +
                '<strong>Sin beneficiario asignado</strong>' +
                '<span class="user-date">—</span>' +
                '</div>'
            );
        }
        
        // Módulo Vinculado
        if (data.related_module) {
            const moduleIcons = {
                'inventory': '📦 Inventario',
                'library':   '📚 Biblioteca',
                'vehicles':  '🚗 Flota / Vehículos',
                'students':  '🎓 Estudiantes / Matrículas',
                'forms':     '📝 Formularios'
            };
            const modLabel = moduleIcons[data.related_module] || ('📦 ' + data.related_module);
            let actionLabel = data.related_action ? (' &middot; <span style="font-weight:600;color:#f59e0b;">' + escapeHtml(data.related_action) + '</span>') : '';
            let itemLabel = data.related_item_id ? (' &middot; ID #' + data.related_item_id) : '';

            $('#modal-module-integration').html(
                '<span class="aura-badge" style="font-size:12px;padding:3px 10px;background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);border-radius:6px;display:inline-flex;align-items:center;">' +
                escapeHtml(modLabel) + itemLabel + actionLabel +
                '</span>'
            );
            $('#modal-integration-container').show();
        } else {
            $('#modal-integration-container').hide();
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
        if (data.notes) {
            const creatorAvatar = (data.creator && data.creator.avatar)
                ? '<img src="' + escapeHtml(data.creator.avatar) + '" alt="' + escapeHtml(data.creator.name) + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,0.15);">'
                : '<span class="dashicons dashicons-admin-users" style="font-size:18px;width:18px;height:18px;color:#94a3b8;"></span>';
            const creatorName = (data.creator && data.creator.name) ? data.creator.name : 'Autor';

            $('#modal-notes').html(
                '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,0.08);font-size:12px;">' +
                creatorAvatar +
                '<strong>' + escapeHtml(creatorName) + '</strong>' +
                '<span style="opacity:0.6;">—</span>' +
                '<span class="user-date" style="font-size:12px;">' + formatDateTime(data.created_at) + '</span>' +
                '</div>' +
                '<div style="white-space:pre-wrap;line-height:1.6;font-size:13px;">' + escapeHtml(data.notes) + '</div>'
            );
        } else {
            $('#modal-notes').html('<em>Sin notas adicionales</em>');
        }
        
        // Historial de cambios
        if (data.history && data.history.length > 0) {
            $('#history-section').show();
            const fieldLabels = {
                'category_id':            'Categoría del presupuesto',
                'expense_category_id':    'Categoría del gasto',
                'amount':                 'Monto',
                'transaction_date':       'Fecha',
                'description':            'Descripción',
                'source_account_id':      'Cuenta origen',
                'destination_account_id': 'Cuenta destino',
                'area_id':                'Área / Programa',
                'payment_method':         'Método de pago',
                'reference_number':       'Nº referencia',
                'recipient_payer':        'Destinatario / Pagador',
                'third_party_id':         'Beneficiario / Proveedor / Tercero',
                'related_user_id':        'Usuario Vinculado',
                'related_user_concept':   'Concepto de Vinculación',
                'notes':                  'Notas',
                'tags':                   'Etiquetas',
                'receipt_file':           'Comprobante',
                'status':                 'Estado',
                'status_deletion':        'Envío a papelera / Eliminación',
                'status_resubmitted':     'Re-enviada para aprobación',
                'rejection_reason':       'Motivo de rechazo',
                'category_merged':        'Fusión de categoría'
            };
            let historyHtml = '<ul class="changes-list">';
            data.history.forEach(change => {
                const fieldLabel = change.field_changed_label || fieldLabels[change.field_changed] || change.field_changed;
                const changeAvatar = change.changed_by_avatar
                    ? '<img src="' + escapeHtml(change.changed_by_avatar) + '" alt="' + escapeHtml(change.changed_by) + '" style="width:18px;height:18px;border-radius:50%;object-fit:cover;vertical-align:middle;display:inline-block;margin-right:5px;border:1px solid rgba(255,255,255,0.15);">'
                    : '<span class="dashicons dashicons-admin-users" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;opacity:0.7;"></span>';

                historyHtml += '<li class="change-item">';
                historyHtml += '<div class="change-header">';
                historyHtml += '<strong>' + fieldLabel + '</strong>';
                historyHtml += '<span class="change-date">' + formatDateTime(change.changed_at) + '</span>';
                historyHtml += '</div>';
                const oldLabel = change.old_value_label || change.old_value || '—';
                const newLabel = change.new_value_label || change.new_value || '—';
                historyHtml += '<div class="change-details">';
                historyHtml += '<div class="old-value">Anterior: ' + escapeHtml(String(oldLabel)) + '</div>';
                historyHtml += '<div class="new-value">Nuevo: ' + escapeHtml(String(newLabel)) + '</div>';
                historyHtml += '</div>';
                historyHtml += '<div class="change-user" style="display:flex;align-items:center;gap:4px;margin-top:6px;font-size:12px;">' + changeAvatar + '<span>Por: <strong>' + escapeHtml(change.changed_by) + '</strong></span></div>';
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
     * Renderizar comprobante con soporte multi-archivo interactivo
     */
    function renderReceipt(data, activeIndex = 0) {
        const hasList = data.receipts_list && data.receipts_list.length > 0;
        const hasLegacy = !!(data.receipt_file || data.receipt_url);

        if (hasList || hasLegacy) {
            $('#receipt-actions').show();

            const receipts = hasList ? data.receipts_list : [{
                url: data.receipt_preview_url || data.receipt_url,
                preview_url: data.receipt_preview_url || data.receipt_url,
                thumbnail_url: data.receipt_thumbnail_url || data.receipt_url,
                download_url: data.receipt_download_url || data.receipt_url,
                is_drive: !!data.receipt_is_drive || String(data.receipt_preview_url || data.receipt_url).indexOf('drive.google.com') !== -1,
                is_pdf: !!data.receipt_is_pdf,
                is_img: !!data.receipt_is_img && !data.receipt_is_pdf,
                filename: data.receipt_file ? data.receipt_file.split('/').pop() : 'Comprobante'
            }];

            if (activeIndex < 0 || activeIndex >= receipts.length) {
                activeIndex = 0;
            }

            const current = receipts[activeIndex];
            const previewUrl = current.preview_url || current.url;
            const thumbUrl = current.thumbnail_url || current.url;
            const downloadUrl = current.download_url || current.url;
            const isDrive = !!current.is_drive || String(previewUrl).indexOf('drive.google.com') !== -1;
            const isPdf = !!current.is_pdf || /\.pdf($|\?)/i.test(current.filename || '') || /\.pdf($|\?)/i.test(previewUrl || '');
            const isImg = !isPdf && (!!current.is_img || /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(current.filename || '') || /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(previewUrl || ''));

            // Selector de recibos si hay más de 1
            let multiTabsHtml = '';
            if (receipts.length > 1) {
                multiTabsHtml = '<div class="aura-receipt-tabs-bar">';
                receipts.forEach(function(item, idx) {
                    const isSelected = (idx === activeIndex);
                    const tabIcon = item.is_drive ? 'dashicons-cloud' : (item.is_pdf ? 'dashicons-pdf' : 'dashicons-media-default');
                    const tabStyle = isSelected
                        ? 'background:#2563eb;color:#ffffff;border-color:#1d4ed8;font-weight:600;'
                        : 'background:#f8fafc;color:#475569;border-color:#cbd5e1;';
                    const label = item.filename || ('Comprobante ' + (idx + 1));
                    multiTabsHtml += '<button type="button" class="button button-small aura-modal-switch-receipt" data-index="' + idx + '" style="' + tabStyle + 'border-radius:18px;display:inline-flex;align-items:center;gap:5px;font-size:12px;padding:3px 12px;cursor:pointer;">' +
                        '<span class="dashicons ' + tabIcon + '" style="font-size:14px;width:14px;height:14px;"></span> ' +
                        $('<div>').text(label).html() +
                        '</button>';
                });
                multiTabsHtml += '</div>';
            }

            let driveBadgeHtml = '';
            if (isDrive) {
                driveBadgeHtml = '<div class="aura-receipt-drive-banner">' +
                    '<div class="aura-receipt-drive-info">' +
                    '<span class="dashicons dashicons-cloud"></span>' +
                    '<div class="aura-receipt-drive-texts">' +
                    '<strong class="aura-receipt-drive-brand">Google Drive</strong>' +
                    '<span class="aura-receipt-drive-note">— Archivo almacenado en la nube</span>' +
                    '</div>' +
                    '</div>' +
                    '<a href="' + (current.url || previewUrl) + '" target="_blank" rel="noopener noreferrer" class="button button-small aura-receipt-drive-btn">' +
                    '<span class="dashicons dashicons-external"></span> Abrir en Google Drive' +
                    '</a>' +
                    '</div>';
            }

            let previewBodyHtml = '';
            if (isPdf || isDrive) {
                previewBodyHtml = '<div class="aura-receipt-preview-inner">' +
                    '<iframe src="' + previewUrl + '" class="receipt-pdf" allow="autoplay"></iframe>' +
                    '</div>';
            } else if (isImg) {
                previewBodyHtml = '<div class="aura-receipt-preview-inner">' +
                    '<div class="aura-receipt-preview-interactive" id="modal-receipt-img-trigger" title="Haz clic para ver en pantalla completa">' +
                    '<img src="' + thumbUrl + '" alt="Comprobante" class="receipt-image">' +
                    '<div class="aura-receipt-hover-overlay">' +
                    '<span class="dashicons dashicons-search"></span>' +
                    '<span>Haz clic para ver en pantalla completa</span>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            } else {
                previewBodyHtml = '<div class="aura-receipt-preview-inner">' +
                    '<div class="receipt-file-link" style="padding: 30px; text-align: center;">' +
                    '<span class="dashicons dashicons-media-document" style="font-size: 48px; height: 48px; width: 48px; color: #3b82f6;"></span>' +
                    '<p style="margin-top: 15px;">Archivo adjunto: <strong>' + (current.filename || 'Comprobante') + '</strong></p>' +
                    '<a href="' + downloadUrl + '" target="_blank" class="button button-primary button-large" style="margin-top: 10px;">' +
                    '<span class="dashicons dashicons-download"></span> Descargar Documento</a>' +
                    '</div>' +
                    '</div>';
            }

            const viewerHtml = multiTabsHtml + driveBadgeHtml + 
                '<div class="aura-receipt-preview-wrap">' +
                previewBodyHtml +
                '</div>';
            
            $('#receipt-container').html(viewerHtml);
            $('#download-receipt').attr('href', downloadUrl);

            // Handler para cambio de pestaña de recibo dentro del modal
            $('#receipt-container').off('click', '.aura-modal-switch-receipt').on('click', '.aura-modal-switch-receipt', function(e) {
                e.preventDefault();
                const newIdx = parseInt($(this).data('index'), 10);
                renderReceipt(data, newIdx);
            });

            // Ajustar texto del botón de ampliación
            if (receipts.length > 1) {
                $('#view-receipt-fullscreen').html('<span class="dashicons dashicons-images-alt2"></span> Ver ' + receipts.length + ' Recibos');
            } else {
                $('#view-receipt-fullscreen').html('<span class="dashicons dashicons-fullscreen-alt"></span> Ampliar');
            }
        } else {
            $('#receipt-actions').hide();
            const noReceiptHtml = '<div class="no-receipt" id="no-receipt-message" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:50px 20px;text-align:center;width:100%;margin:auto;">' +
                '<span class="dashicons dashicons-media-default" style="font-size:48px;width:48px;height:48px;opacity:0.35;margin-bottom:12px;display:inline-block;color:#94a3b8;"></span>' +
                '<p style="font-size:14px;color:#94a3b8;margin:0 0 14px;font-weight:500;">No hay comprobante adjunto para esta transacción</p>' +
                '</div>';
            $('#receipt-container').html(noReceiptHtml);
        }
    }
    
    /**
     * Renderizar información de auditoría
     */
    function renderAuditInfo(data) {
        $('#audit-created-at').text(formatDateTime(data.created_at));
        const creatorName = (data.creator && data.creator.name) ? data.creator.name : '—';
        $('#audit-created-by').text(creatorName);
        
        if (data.updated_at && data.updated_at !== data.created_at) {
            $('#audit-updated-at').text(formatDateTime(data.updated_at));
            $('#audit-updated-by').text(data.updated_by || '—');
        } else {
            $('#audit-updated-at').html('<em>No editado</em>');
            $('#audit-updated-by').html('<em>—</em>');
        }
        
        if (data.approver && data.approver.name) {
            $('#audit-approved-by').text(data.approver.name);
            $('#audit-approved-at').text(formatDateTime(data.approved_at));
        } else {
            $('#audit-approved-by').html('<em>Pendiente</em>');
            $('#audit-approved-at').html('<em>—</em>');
        }

        // Lote de importación
        if (data.import_batch_id) {
            $('#audit-import-batch').html('<code>' + escapeHtml(data.import_batch_id) + '</code>');
        } else {
            $('#audit-import-batch').html('<em>Registro Directo</em>');
        }

        // Origen de Integración
        if (data.related_module) {
            const modIcons = {
                'inventory': '📦 Módulo Inventario',
                'library':   '📚 Módulo Biblioteca',
                'vehicles':  '🚗 Módulo Vehículos',
                'students':  '🎓 Módulo Estudiantes',
                'forms':     '📝 Módulo Formularios'
            };
            const modText = modIcons[data.related_module] || ('📦 ' + data.related_module);
            $('#audit-integration-origin').html('<strong>' + escapeHtml(modText) + '</strong>' + (data.related_action ? ' (' + escapeHtml(data.related_action) + ')' : ''));
        } else {
            $('#audit-integration-origin').html('<em>Movimiento Manual</em>');
        }
        
        // Registro de cambios detallado (soporta audit_log o history)
        const logs = data.audit_log || data.history || [];
        if (logs.length > 0) {
            const iconMap = {
                'status':                 'yes-alt',
                'amount':                 'money-alt',
                'transaction_date':       'calendar-alt',
                'description':            'edit',
                'category_id':            'category',
                'expense_category_id':    'tag',
                'source_account_id':      'vault',
                'destination_account_id': 'vault',
                'area_id':                'building',
                'payment_method':         'money-alt',
                'reference_number':       'index-card',
                'recipient_payer':        'businessman',
                'third_party_id':         'groups',
                'related_user_id':        'admin-users',
                'related_user_concept':   'admin-links',
                'notes':                  'edit-page',
                'tags':                   'tag',
                'receipt_file':           'media-document',
                'rejection_reason':       'dismiss',
                'status_resubmitted':     'redo',
                'status_deletion':        'trash',
                'category_merged':        'randomize'
            };
            const fieldLabels = {
                'status':                 'Cambio de estado',
                'amount':                 'Cambio de monto',
                'transaction_date':       'Cambio de fecha',
                'description':            'Cambio de descripción',
                'category_id':            'Cambio de categoría presupuesto',
                'expense_category_id':    'Cambio de categoría del gasto',
                'source_account_id':      'Cambio de cuenta origen',
                'destination_account_id': 'Cambio de cuenta destino',
                'area_id':                'Cambio de área / programa',
                'payment_method':         'Cambio de método de pago',
                'reference_number':       'Cambio de referencia',
                'recipient_payer':        'Cambio de destinatario / pagador',
                'third_party_id':         'Cambio de tercero / beneficiario',
                'related_user_id':        'Cambio de usuario vinculado',
                'related_user_concept':   'Cambio de concepto de vinculación',
                'notes':                  'Cambio de notas',
                'tags':                   'Cambio de etiquetas',
                'receipt_file':           'Cambio de comprobante',
                'rejection_reason':       'Motivo de rechazo',
                'status_resubmitted':     'Re-enviada para aprobación',
                'status_deletion':        'Envío a papelera / Eliminación',
                'category_merged':        'Fusión de categoría'
            };
            let logHtml = '<div class="audit-timeline">';
            const logs = data.audit_log || data.history || [];
            logs.forEach(entry => {
                const icon   = iconMap[entry.field_changed]   || 'edit';
                const action = entry.action_label || fieldLabels[entry.field_changed] || entry.field_changed_label || entry.field_changed;
                const details = (entry.old_value_label || entry.old_value || '—') + ' → ' + (entry.new_value_label || entry.new_value || '—');
                const userAvatar = entry.changed_by_avatar
                    ? '<img src="' + escapeHtml(entry.changed_by_avatar) + '" alt="' + escapeHtml(entry.changed_by) + '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;vertical-align:middle;display:inline-block;margin-right:6px;border:1px solid rgba(255,255,255,0.15);">'
                    : '<span class="dashicons dashicons-admin-users" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;opacity:0.7;"></span>';

                logHtml += '<div class="audit-entry">';
                logHtml += '<div class="audit-icon"><span class="dashicons dashicons-' + icon + '"></span></div>';
                logHtml += '<div class="audit-content">';
                logHtml += '<strong>' + action + '</strong>';
                logHtml += '<p>' + details + '</p>';
                logHtml += '<span class="audit-meta" style="display:flex;align-items:center;gap:6px;margin-top:8px;">';
                logHtml += userAvatar;
                logHtml += '<span style="font-weight:600;color:inherit;">' + escapeHtml(entry.changed_by) + '</span>';
                logHtml += '<span style="opacity:0.6;">—</span>';
                logHtml += '<span>' + formatDateTime(entry.changed_at) + '</span>';
                logHtml += '</span>';
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
        // Ocultar botones condicionales primero (manteniendo el botón Cerrar siempre visible)
        $('.modal-actions-right button, .modal-actions-left button:not(#btn-close-modal-footer):not(.aura-modal-btn-close)').hide();
        $('#btn-close-modal-footer, .aura-modal-btn-close').show();
        
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
            const editUrl = (typeof auraTransactionModal !== 'undefined' && auraTransactionModal.editUrl)
                ? auraTransactionModal.editUrl
                : 'admin.php?page=aura-financial-edit-transaction';
            window.location.href = editUrl + '&id=' + currentTransactionId;
        });
        
        // Eliminar
        $('#delete-transaction').on('click', function() {
            if (confirm(auraTransactionModal.messages.confirmDelete || '¿Estás seguro de que deseas eliminar esta transacción?')) {
                deleteTransaction(currentTransactionId);
            }
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
        const $btn = $('#delete-transaction');
        $btn.prop('disabled', true).text('Eliminando...');

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
                    closeTransactionModal();
                    showPageNotice('success', response.data && response.data.message ? response.data.message : 'Transacción eliminada correctamente.');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    alert(response.data && response.data.message ? response.data.message : auraTransactionModal.messages.error);
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Eliminar');
                }
            },
            error: function() {
                alert(auraTransactionModal.messages.error);
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Eliminar');
            }
        });
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
        if (!dateString) return '—';
        try {
            // Reemplazar guiones si es necesario o manejar formato YYYY-MM-DD
            const parts = dateString.split(' ')[0].split('-');
            if (parts.length === 3) {
                const year = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10) - 1;
                const day = parseInt(parts[2], 10);
                const date = new Date(year, month, day);
                return date.toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (e) {
            return dateString;
        }
    }
    
    function formatDateTime(dateString) {
        if (!dateString) return '—';
        try {
            const date = new Date(dateString.replace(' ', 'T'));
            if (isNaN(date.getTime())) {
                const d = new Date(dateString);
                if (isNaN(d.getTime())) return dateString;
                return d.toLocaleString('es-ES', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            return date.toLocaleString('es-ES', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateString;
        }
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
    
    // ══════════════════════════════════════════════════════════════════
    // CONTROLADOR DEL VISOR UNIVERSAL DE COMPROBANTES (GOOGLE DRIVE & LOCAL)
    // ══════════════════════════════════════════════════════════════════
    window.AuraReceiptViewer = {
        receipts: [],
        currentIndex: 0,
        zoomLevel: 1,
        rotation: 0,
        title: '',

        open: function (items, startIndex, modalTitle) {
            if (!items) return;
            
            if (!Array.isArray(items)) {
                items = [items];
            }
            
            this.receipts = items.map(function (it) {
                if (!it) return null;
                if (typeof it === 'string') {
                    const isDrive = it.indexOf('drive.google') !== -1;
                    const isPdf = /\.pdf($|\?)/i.test(it);
                    return {
                        receipt_url: it,
                        receipt_preview_url: it,
                        receipt_thumbnail_url: it,
                        receipt_download_url: it,
                        receipt_is_drive: isDrive,
                        receipt_is_img: !isPdf,
                        receipt_is_pdf: isPdf,
                        receipt_file: it.split('/').pop()
                    };
                }
                const url = it.receipt_url || it.url || it.file_url || '';
                const previewUrl = it.receipt_preview_url || it.preview_url || url;
                const downloadUrl = it.receipt_download_url || it.download_url || url;
                const thumbUrl = it.receipt_thumbnail_url || it.thumbnail_url || previewUrl;
                const isDrive = !!it.receipt_is_drive || !!it.is_drive || (url && url.indexOf('drive.google.com') !== -1) || (previewUrl && previewUrl.indexOf('drive.google.com') !== -1);
                const isPdf = !!it.receipt_is_pdf || !!it.is_pdf || (url && /\.pdf($|\?)/i.test(url)) || (previewUrl && /\.pdf($|\?)/i.test(previewUrl)) || /\.pdf($|\?)/i.test(it.receipt_file || it.filename || it.name || '');
                const isImg = !!it.receipt_is_img || !!it.is_img || (!isPdf && url && /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(url));
                const name = it.receipt_file || it.name || it.filename || (isDrive ? 'Google Drive' : 'Comprobante');
                return {
                    receipt_url: url,
                    receipt_preview_url: previewUrl,
                    receipt_thumbnail_url: thumbUrl,
                    receipt_download_url: downloadUrl,
                    receipt_is_drive: isDrive,
                    receipt_is_img: isImg,
                    receipt_is_pdf: isPdf,
                    receipt_file: name
                };
            }).filter(Boolean);

            this.currentIndex = startIndex || 0;
            this.title = modalTitle || 'Comprobante';
            
            $('#aura-receipt-viewer-modal').fadeIn(200);
            $('body').addClass('aura-receipt-modal-open');
            this.render();
        },

        close: function () {
            $('#aura-receipt-viewer-modal').fadeOut(150);
            $('body').removeClass('aura-receipt-modal-open');
            $('#aura-receipt-stage').empty();
            $('#aura-receipt-thumbs-bar').empty().hide();
        },

        render: function () {
            if (!this.receipts.length) {
                this.close();
                return;
            }

            const item = this.receipts[this.currentIndex];
            this.zoomLevel = 1;
            this.rotation = 0;
            this.updateTransform();

            // Título
            let displayTitle = this.title;
            if (this.receipts.length > 1) {
                displayTitle += ' (' + (this.currentIndex + 1) + ' de ' + this.receipts.length + ')';
            }
            $('#aura-receipt-viewer-title').text(displayTitle);

            // Badge de origen
            const isDrive = !!item.receipt_is_drive || (item.receipt_preview_url && item.receipt_preview_url.indexOf('drive.google.com') !== -1) || (item.receipt_url && item.receipt_url.indexOf('drive.google.com') !== -1);
            const $badge = $('#aura-receipt-badge');
            if (isDrive) {
                $badge.attr('class', 'aura-receipt-meta-badge badge-drive').html('<span class="dashicons dashicons-cloud"></span> Google Drive');
                $('#aura-rc-open-drive').attr('href', item.receipt_url || item.receipt_preview_url).show();
            } else {
                $badge.attr('class', 'aura-receipt-meta-badge badge-local').html('<span class="dashicons dashicons-admin-home"></span> Local');
                $('#aura-rc-open-drive').hide();
            }

            // Descarga
            const downloadUrl = item.receipt_download_url || item.receipt_url;
            $('#aura-rc-download').attr('href', downloadUrl);

            // Navegación si hay múltiples recibos
            if (this.receipts.length > 1) {
                $('#aura-receipt-nav').show();
                $('#aura-rc-counter').text((this.currentIndex + 1) + ' / ' + this.receipts.length);
                $('#aura-rc-prev').prop('disabled', this.currentIndex === 0);
                $('#aura-rc-next').prop('disabled', this.currentIndex === this.receipts.length - 1);
                
                // Barra de miniaturas
                let thumbsHtml = '';
                const activeIdx = this.currentIndex;
                this.receipts.forEach(function (r, idx) {
                    const activeClass = idx === activeIdx ? 'active' : '';
                    const thumbSrc = r.receipt_thumbnail_url || r.receipt_url;
                    thumbsHtml += '<div class="aura-rc-thumb-item ' + activeClass + '" data-index="' + idx + '">';
                    if (r.receipt_is_img) {
                        thumbsHtml += '<img src="' + thumbSrc + '" alt="Recibo ' + (idx + 1) + '">';
                    } else {
                        thumbsHtml += '<span class="dashicons dashicons-media-document" style="font-size:24px;color:#94a3b8;"></span>';
                    }
                    thumbsHtml += '</div>';
                });
                $('#aura-receipt-thumbs-bar').html(thumbsHtml).show();
            } else {
                $('#aura-receipt-nav').hide();
                $('#aura-receipt-thumbs-bar').hide();
            }

            // Renderizar contenido en stage
            const $stage = $('#aura-receipt-stage').empty();
            const previewUrl = item.receipt_preview_url || item.receipt_url;
            const isPdf = !!item.receipt_is_pdf || /\.pdf($|\?)/i.test(previewUrl) || (item.receipt_file && /\.pdf($|\?)/i.test(item.receipt_file));

            if (isPdf || isDrive) {
                $('#aura-receipt-zoom-tools').hide();
                $stage.html('<iframe src="' + previewUrl + '" class="aura-rc-stage-iframe" allow="autoplay" style="width:100%;height:100%;min-height:550px;border:none;border-radius:4px;"></iframe>');
            } else {
                $('#aura-receipt-zoom-tools').show();
                const imgSrc = item.receipt_thumbnail_url || previewUrl;
                const $imgWrap = $('<div class="aura-rc-image-wrap" id="aura-rc-image-wrap"></div>');
                const $img = $('<img src="' + imgSrc + '" class="aura-rc-stage-img" alt="Comprobante">');
                
                $('#aura-receipt-spinner').show();
                $img.on('load', function () {
                    $('#aura-receipt-spinner').hide();
                }).on('error', function () {
                    $('#aura-receipt-spinner').hide();
                    $stage.html('<div style="color:#ef4444;text-align:center;padding:40px;"><span class="dashicons dashicons-warning" style="font-size:48px;"></span><p>No se pudo cargar la imagen del comprobante.</p><a href="' + (item.receipt_url || '#') + '" target="_blank" class="button button-primary">Abrir en enlace directo</a></div>');
                });

                $imgWrap.append($img);
                $stage.append($imgWrap);
            }
        },

        updateTransform: function () {
            $('#aura-rc-zoom-level').text(Math.round(this.zoomLevel * 100) + '%');
            $('#aura-rc-image-wrap').css({
                'transform': 'scale(' + this.zoomLevel + ') rotate(' + this.rotation + 'deg)'
            });
        },

        zoomIn: function () {
            if (this.zoomLevel < 3.5) {
                this.zoomLevel = +(this.zoomLevel + 0.25).toFixed(2);
                this.updateTransform();
            }
        },

        zoomOut: function () {
            if (this.zoomLevel > 0.5) {
                this.zoomLevel = +(this.zoomLevel - 0.25).toFixed(2);
                this.updateTransform();
            }
        },

        rotate: function () {
            this.rotation = (this.rotation + 90) % 360;
            this.updateTransform();
        },

        reset: function () {
            this.zoomLevel = 1;
            this.rotation = 0;
            this.updateTransform();
        },

        prev: function () {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.render();
            }
        },

        next: function () {
            if (this.currentIndex < this.receipts.length - 1) {
                this.currentIndex++;
                this.render();
            }
        },

        goTo: function (idx) {
            if (idx >= 0 && idx < this.receipts.length) {
                this.currentIndex = idx;
                this.render();
            }
        }
    };

    // Eventos del modal de recibos
    $(document).on('click', '#aura-rc-close, #aura-receipt-overlay-backdrop', function () {
        window.AuraReceiptViewer.close();
    });
    $(document).on('click', '#aura-rc-zoom-in', function () {
        window.AuraReceiptViewer.zoomIn();
    });
    $(document).on('click', '#aura-rc-zoom-out', function () {
        window.AuraReceiptViewer.zoomOut();
    });
    $(document).on('click', '#aura-rc-rotate', function () {
        window.AuraReceiptViewer.rotate();
    });
    $(document).on('click', '#aura-rc-reset', function () {
        window.AuraReceiptViewer.reset();
    });
    $(document).on('click', '#aura-rc-prev', function () {
        window.AuraReceiptViewer.prev();
    });
    $(document).on('click', '#aura-rc-next', function () {
        window.AuraReceiptViewer.next();
    });
    $(document).on('click', '.aura-rc-thumb-item', function () {
        var idx = parseInt($(this).data('index'), 10);
        window.AuraReceiptViewer.goTo(idx);
    });

    // Abrir visor desde el modal de transacción
    $(document).on('click', '#view-receipt-fullscreen, #modal-receipt-img-trigger', function (e) {
        e.preventDefault();
        if (currentTransactionData) {
            var list = (currentTransactionData.receipts_list && currentTransactionData.receipts_list.length)
                ? currentTransactionData.receipts_list
                : [currentTransactionData];
            var activeIdx = 0;
            var $activeTab = $('#receipt-container .aura-modal-switch-receipt[style*="background:#2563eb"], #receipt-container .aura-modal-switch-receipt.active');
            if ($activeTab.length) {
                activeIdx = parseInt($activeTab.data('index'), 10) || 0;
            }
            var txTitle = currentTransactionData.movement_id 
                ? ('Transacción ' + currentTransactionData.movement_id) 
                : ('Transacción #' + (currentTransactionData.id || '') + ' - Comprobante');
            window.AuraReceiptViewer.open(list, activeIdx, txTitle);
        }
    });

    // Abrir visor desde botones o miniaturas de la tabla
    $(document).on('click', '.aura-open-receipt-viewer', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);

        var rawFiles = $btn.attr('data-files') || $btn.data('files');
        var filesList = [];

        if (rawFiles) {
            try {
                if (typeof rawFiles === 'string') {
                    filesList = JSON.parse(rawFiles);
                } else if (Array.isArray(rawFiles)) {
                    filesList = rawFiles;
                }
            } catch (err) {
                console.warn('Aura: Error al parsear data-files', err);
            }
        }

        var title = $btn.data('title') || 'Comprobante';

        if (filesList && filesList.length > 0) {
            var formattedList = filesList.map(function (item) {
                var url = item.url || item.file_url || (typeof item === 'string' ? item : '');
                var previewUrl = item.preview_url || url;
                var downloadUrl = item.download_url || url;
                var thumbUrl = item.thumbnail_url || previewUrl;
                var isDrive = !!item.is_drive || (url && url.indexOf('drive.google.com') !== -1);
                var isPdf = !!item.is_pdf || (url && /\.pdf($|\?)/i.test(url));
                var isImg = !!item.is_img || (!isPdf && url && /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(url));
                var name = item.name || item.filename || (isDrive ? 'Google Drive' : 'Comprobante');
                return {
                    receipt_url: url,
                    receipt_preview_url: previewUrl,
                    receipt_thumbnail_url: thumbUrl,
                    receipt_download_url: downloadUrl,
                    receipt_is_drive: isDrive,
                    receipt_is_img: isImg,
                    receipt_is_pdf: isPdf,
                    receipt_file: name
                };
            });
            window.AuraReceiptViewer.open(formattedList, 0, title);
            return;
        }

        var url = $btn.data('receipt-url') || $btn.attr('href');
        var previewUrl = $btn.data('preview-url') || url;
        var downloadUrl = $btn.data('download-url') || url;
        var isDrive = $btn.data('is-drive') == 1 || $btn.data('is-drive') === true || (url && url.indexOf('drive.google') !== -1);
        var isPdf = $btn.data('is-pdf') == 1 || $btn.data('is-pdf') === true || (url && /\.pdf($|\?)/i.test(url));
        var isImg = $btn.data('is-img') == 1 || $btn.data('is-img') === true || (!isPdf && url && /\.(jpg|jpeg|png|webp|gif)($|\?)/i.test(url));

        window.AuraReceiptViewer.open([{
            receipt_url: url,
            receipt_preview_url: previewUrl,
            receipt_thumbnail_url: previewUrl,
            receipt_download_url: downloadUrl,
            receipt_is_drive: isDrive,
            receipt_is_img: isImg,
            receipt_is_pdf: isPdf,
            receipt_file: title
        }], 0, title);
    });

    // Atajos de teclado en el visor
    $(document).on('keydown', function (e) {
        if (!$('#aura-receipt-viewer-modal').is(':visible')) return;
        if (e.key === 'Escape') {
            window.AuraReceiptViewer.close();
        } else if (e.key === 'ArrowLeft') {
            window.AuraReceiptViewer.prev();
        } else if (e.key === 'ArrowRight') {
            window.AuraReceiptViewer.next();
        } else if (e.key === '+' || e.key === '=') {
            window.AuraReceiptViewer.zoomIn();
        } else if (e.key === '-') {
            window.AuraReceiptViewer.zoomOut();
        }
    });

})(jQuery);
