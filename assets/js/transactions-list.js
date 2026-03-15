/**
 * JavaScript para Listado de Transacciones Financieras
 * 
 * Gestiona filtros avanzados, búsqueda en tiempo real,
 * acciones rápidas y modal de detalles
 *
 * @package AuraBusinessSuite
 * @subpackage Financial
 */

(function($) {
    'use strict';
    
    /**
     * Variables globales
     */
    let searchTimeout = null;
    let currentFilters = {};
    
    /**
     * Inicializar cuando el DOM esté listo
     */
    $(document).ready(function() {
        // IMPORTANTE: initFiltersSidebar primero — no depende de CDN externos
        initFiltersSidebar();
        // Dependencias de CDN externo: envolver en try/catch para que un fallo
        // no detenga el resto de la inicialización
        try { initDatepickers(); } catch(e) { console.warn('[Aura] Datepicker no disponible:', e.message); }
        try { initSelect2();     } catch(e) { console.warn('[Aura] Select2 no disponible:', e.message); }
        initSearch();
        initQuickActions();
        initBulkActions();
        initFilterPresets();
        initUserFilterAutocomplete();
    });
    
    /**
     * Inicializar datepickers para rangos de fecha
     */
    function initDatepickers() {
        $('.aura-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: '-10:+0',
            maxDate: 0, // No permitir fechas futuras
            onSelect: function() {
                // Auto-aplicar filtros al seleccionar fecha
                if ($('#auto-apply-filters').is(':checked')) {
                    $('#aura-filters-form').submit();
                }
            }
        });
    }
    
    /**
     * Inicializar Select2 para dropdowns mejorados
     */
    function initSelect2() {
        // Guard: Select2 puede no estar disponible si el CDN está bloqueado
        if (typeof $.fn.select2 !== 'function') {
            return;
        }
        $('.aura-select2').each(function() {
            $(this).select2({
                width: '100%',
                placeholder: $(this).data('placeholder') || '',
                allowClear: true
            });
        });
    }
    
    /**
     * Sidebar de filtros colapsable
     */
    function initFiltersSidebar() {
        // Toggle sidebar
        $('#toggle-filters').on('click', function() {
            const $sidebar = $('#aura-filters-sidebar');
            const $showBtn = $('#show-filters');
            const $toggleIcon = $(this).find('.dashicons');
            
            $sidebar.toggleClass('collapsed');
            
            if ($sidebar.hasClass('collapsed')) {
                $showBtn.show();
                $toggleIcon.removeClass('dashicons-arrow-left-alt2').addClass('dashicons-arrow-right-alt2');
                localStorage.setItem('aura_filters_collapsed', 'true');
            } else {
                $showBtn.hide();
                $toggleIcon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-left-alt2');
                localStorage.removeItem('aura_filters_collapsed');
            }
        });
        
        // Botón para mostrar filtros cuando están colapsados
        $('#show-filters').on('click', function() {
            $('#toggle-filters').click();
        });
        
        // Restaurar estado del sidebar
        if (localStorage.getItem('aura_filters_collapsed') === 'true') {
            const $sidebar = $('#aura-filters-sidebar');
            const $showBtn = $('#show-filters');
            const $toggleIcon = $('#toggle-filters .dashicons');
            
            $sidebar.addClass('collapsed');
            $showBtn.show();
            $toggleIcon.removeClass('dashicons-arrow-left-alt2').addClass('dashicons-arrow-right-alt2');
        }
        
        // Aplicar filtros con Enter
        $('#aura-filters-form input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#aura-filters-form').submit();
            }
        });
    }
    
    /**
     * Búsqueda en tiempo real
     */
    function initSearch() {
        let $searchInput = $('#transaction-search-input');
        let $resultsDropdown = $('#search-results-dropdown');
        
        // Búsqueda mientras escribe (con debounce)
        $searchInput.on('input', function() {
            const searchTerm = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 3) {
                $resultsDropdown.hide();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, 300);
        });
        
        // Cerrar dropdown al hacer click fuera
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.aura-search-bar').length) {
                $resultsDropdown.hide();
            }
        });
        
        // Навigación con teclado en resultados
        $searchInput.on('keydown', function(e) {
            let $results = $resultsDropdown.find('.search-result-item');
            let $active = $results.filter('.active');
            
            if (e.which === 40) { // Flecha abajo
                e.preventDefault();
                if ($active.length === 0) {
                    $results.first().addClass('active');
                } else {
                    $active.removeClass('active').next().addClass('active');
                }
            } else if (e.which === 38) { // Flecha arriba
                e.preventDefault();
                if ($active.length) {
                    $active.removeClass('active').prev().addClass('active');
                }
            } else if (e.which === 13 && $active.length) { // Enter
                e.preventDefault();
                $active.click();
            }
        });
    }
    
    /**
     * Realizar búsqueda AJAX
     */
    function performSearch(searchTerm) {
        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_search_transactions',
                nonce: auraTransactionsList.nonce,
                search: searchTerm
            },
            beforeSend: function() {
                $('#search-results-dropdown').html('<div class="search-loading">Buscando...</div>').show();
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data.results);
                } else {
                    $('#search-results-dropdown').html('<div class="search-error">Error en la búsqueda</div>');
                }
            },
            error: function() {
                $('#search-results-dropdown').html('<div class="search-error">Error de conexión</div>');
            }
        });
    }
    
    /**
     * Mostrar resultados de búsqueda
     */
    function displaySearchResults(results) {
        let $dropdown = $('#search-results-dropdown');
        
        if (results.length === 0) {
            $dropdown.html('<div class="search-no-results">No se encontraron resultados</div>').show();
            return;
        }
        
        let html = '<div class="search-results-list">';
        
        results.forEach(function(transaction) {
            const color = transaction.transaction_type === 'income' ? '#27ae60' : '#e74c3c';
            const sign = transaction.transaction_type === 'income' ? '+' : '-';
            const date = new Date(transaction.transaction_date).toLocaleDateString('es-ES');
            
            html += `
                <div class="search-result-item" data-id="${transaction.id}">
                    <div class="result-description">${escapeHtml(transaction.description)}</div>
                    <div class="result-meta">
                        <span class="result-date">${date}</span>
                        <span class="result-amount" style="color: ${color};">
                            ${sign}$${parseFloat(transaction.amount).toLocaleString('es-ES', {minimumFractionDigits: 2})}
                        </span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $dropdown.html(html).show();
        
        // Click en resultado
        $('.search-result-item').on('click', function() {
            const transactionId = $(this).data('id');
            viewTransactionDetail(transactionId);
        });
    }
    
    /**
     * Acciones rápidas (aprobar, rechazar, eliminar)
     */
    function initQuickActions() {
        // Aprobar
        $(document).on('click', '.aura-quick-approve', function(e) {
            e.preventDefault();
            
            const transactionId = $(this).data('id');
            
            if (!confirm(auraTransactionsList.messages.confirmApprove)) {
                return;
            }
            
            quickApprove(transactionId);
        });
        
        // Rechazar
        $(document).on('click', '.aura-quick-reject', function(e) {
            e.preventDefault();
            
            const transactionId = $(this).data('id');
            const reason = prompt(auraTransactionsList.messages.rejectReason);
            
            if (reason === null) {
                return; // Usuario canceló
            }
            
            quickReject(transactionId, reason);
        });
        
        // Eliminar
        $(document).on('click', '.aura-delete-transaction', function(e) {
            e.preventDefault();
            
            const transactionId = $(this).data('id');
            
            if (!confirm('¿Estás seguro de eliminar esta transacción?')) {
                return;
            }
            
            deleteTransaction(transactionId);
        });
        
        // Ver detalle
        $(document).on('click', '.aura-view-transaction', function(e) {
            e.preventDefault();
            const transactionId = $(this).data('id');
            viewTransactionDetail(transactionId);
        });
    }
    
    /**
     * Aprobar transacción rápidamente
     */
    function quickApprove(transactionId) {
        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_quick_approve',
                nonce: auraTransactionsList.nonce,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload();
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', 'Error de conexión');
            }
        });
    }
    
    /**
     * Rechazar transacción rápidamente
     */
    function quickReject(transactionId, reason) {
        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_quick_reject',
                nonce: auraTransactionsList.nonce,
                transaction_id: transactionId,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload();
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', 'Error de conexión');
            }
        });
    }
    
    /**
     * Eliminar transacción
     */
    function deleteTransaction(transactionId) {
        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_bulk_action_transactions',
                nonce: auraTransactionsList.nonce,
                action_type: 'bulk_delete',
                transaction_ids: [transactionId]
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload();
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', 'Error de conexión');
            }
        });
    }
    
    /**
     * Ver detalle de transacción (abre el modal de transaction-modal.js)
     */
    function viewTransactionDetail(transactionId) {
        // Disparar el mismo evento que captura transaction-modal.js
        // usando un elemento temporal con la clase correcta
        var $trigger = $('<button>')
            .addClass('view-transaction')
            .attr('data-transaction-id', transactionId)
            .hide()
            .appendTo('body');
        $trigger.trigger('click');
        $trigger.remove();
    }
    
    /**
     * Acciones masivas
     */
    function initBulkActions() {
        // Interceptar envío de formulario de acciones masivas
        $('#doaction, #doaction2').on('click', function(e) {
            const action = $(this).siblings('select').val();
            
            if (action === '-1') {
                return false;
            }
            
            e.preventDefault();
            
            const transactionIds = [];
            $('input[name="transaction_ids[]"]:checked').each(function() {
                transactionIds.push($(this).val());
            });
            
            if (transactionIds.length === 0) {
                alert('Selecciona al menos una transacción');
                return false;
            }
            
            if (action === 'bulk_delete' && !confirm(auraTransactionsList.messages.confirmBulkDelete)) {
                return false;
            }
            
            performBulkAction(action, transactionIds);
        });
    }
    
    /**
     * Ejecutar acción masiva
     */
    function performBulkAction(action, transactionIds) {
        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_bulk_action_transactions',
                nonce: auraTransactionsList.nonce,
                action_type: action,
                transaction_ids: transactionIds
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    
                    if (action === 'bulk_export_csv' || action === 'bulk_export_pdf') {
                        // TODO: Generar descarga de archivo
                    } else {
                        location.reload();
                    }
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', 'Error de conexión');
            }
        });
    }
    
    /**
     * Presets de filtros (guardar y cargar)
     */
    function initFilterPresets() {
        // Guardar preset
        $('#save-filter-preset').on('click', function() {
            $('#save-preset-modal').show();
        });
        
        // Confirmar guardado
        $('#confirm-save-preset').on('click', function() {
            const presetName = $('#preset-name-input').val().trim();
            
            if (!presetName) {
                alert('Ingresa un nombre para el filtro');
                return;
            }
            
            saveFilterPreset(presetName);
        });
        
        // Cancelar guardado
        $('#cancel-save-preset, .aura-modal-close').on('click', function() {
            $('#save-preset-modal').hide();
            $('#preset-name-input').val('');
        });
        
        // Cargar preset
        $('#load-filter-preset').on('change', function() {
            const presetName = $(this).val();
            
            if (!presetName) {
                return;
            }
            
            if (isPredefinedPreset(presetName)) {
                applyPredefinedPreset(presetName);
            } else {
                loadFilterPreset(presetName);
            }
        });
    }
    
    /**
     * Guardar preset de filtros
     */
    function saveFilterPreset(presetName) {
        // Recopilar valores actuales de filtros
        const filters = {};
        $('#aura-filters-form').find(':input').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            
            if (name && name !== 'page' && $input.val()) {
                if ($input.is(':checkbox')) {
                    if ($input.is(':checked')) {
                        filters[name] = $input.val();
                    }
                } else {
                    filters[name] = $input.val();
                }
            }
        });
        
        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_save_filter_preset',
                nonce: auraTransactionsList.nonce,
                preset_name: presetName,
                filters: filters
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', auraTransactionsList.messages.filterSaved);
                    $('#save-preset-modal').hide();
                    $('#preset-name-input').val('');
                    
                    // Agregar nuevo preset al select
                    $('#load-filter-preset').append(
                        `<option value="${presetName}">${presetName}</option>`
                    );
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', 'Error de conexión');
            }
        });
    }
    
    /**
     * Cargar preset de filtros
     */
    function loadFilterPreset(presetName) {
        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_load_filter_preset',
                nonce: auraTransactionsList.nonce,
                preset_name: presetName
            },
            success: function(response) {
                if (response.success) {
                    applyFilters(response.data.filters);
                    showNotice('success', auraTransactionsList.messages.filterLoaded);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', 'Error de conexión');
            }
        });
    }
    
    /**
     * Verificar si es preset predefinido
     */
    function isPredefinedPreset(presetName) {
        return ['this_month', 'pending', 'my_transactions', 'high_amount'].includes(presetName);
    }
    
    /**
     * Aplicar preset predefinido
     */
    function applyPredefinedPreset(presetName) {
        const filters = {};
        const today = new Date();
        
        switch (presetName) {
            case 'this_month':
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                filters['filter_date_from'] = formatDate(firstDay);
                filters['filter_date_to'] = formatDate(lastDay);
                break;
                
            case 'pending':
                filters['filter_status'] = 'pending';
                break;
                
            case 'my_transactions':
                // El backend filtrará automáticamente por usuario actual si no tiene view_all
                break;
                
            case 'high_amount':
                filters['filter_amount_min'] = 1000;
                break;
        }
        
        applyFilters(filters);
    }
    
    /**
     * Aplicar filtros al formulario y enviar
     */
    function applyFilters(filters) {
        // Limpiar filtros actuales
        $('#aura-filters-form').find(':input').not('[name="page"]').val('').prop('checked', false);
        
        // Aplicar nuevos filtros
        for (const [name, value] of Object.entries(filters)) {
            const $input = $(`[name="${name}"]`);
            
            if ($input.is(':checkbox') || $input.is(':radio')) {
                $input.filter(`[value="${value}"]`).prop('checked', true);
            } else {
                $input.val(value);
            }
        }
        
        // Actualizar Select2
        $('.aura-select2').trigger('change');
        
        // Enviar formulario
        $('#aura-filters-form').submit();
    }
    
    /**
     * Mostrar notificación
     */
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const $notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Descartar</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend($notice);
        
        // Auto-cerrar después de 5 segundos
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Botón de cerrar
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.remove();
        });
    }
    
    /**
     * Utilidades
     */
    /**
     * Autocomplete para filtro de usuario vinculado (Fase 6, Item 6.1)
     */
    function initUserFilterAutocomplete() {
        const $filterSearch = $('#filter_related_user_search');
        const $filterHidden = $('#filter_related_user');

        if (!$filterSearch.length) return;

        $filterSearch.autocomplete({
            minLength: 2,
            delay: 300,
            source: function(request, response) {
                $.ajax({
                    url: auraTransactionsList.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aura_search_users',
                        nonce: auraTransactionsList.transactionNonce || auraTransactionsList.nonce,
                        term: request.term
                    },
                    success: function(res) {
                        response(res.success && Array.isArray(res.data) ? res.data : []);
                    },
                    error: function() { response([]); }
                });
            },
            select: function(event, ui) {
                $filterSearch.val(ui.item.name);
                $filterHidden.val(ui.item.id);
                return false;
            }
        }).autocomplete('instance')._renderItem = function(ul, item) {
            return $('<li>')
                .append(
                    '<div style="display:flex;align-items:center;gap:6px;">' +
                    '<img src="' + item.avatar_url + '" width="24" height="24" style="border-radius:50%;">' +
                    '<div><strong>' + $('<span>').text(item.name).html() + '</strong>' +
                    ' <small style="color:#8c8f94">' + $('<span>').text(item.email).html() + '</small></div>' +
                    '</div>'
                )
                .appendTo(ul);
        };

        // Si se borra el texto, limpiar el hidden
        $filterSearch.on('input', function() {
            if ($(this).val() === '') {
                $filterHidden.val('');
            }
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
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
})(jQuery);
