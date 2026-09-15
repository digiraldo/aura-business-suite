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
        var $wpTable = $('#transactions-filter .wp-list-table');
        if ($wpTable.length && !$wpTable.parent().hasClass('aura-table-scroll-wrap') && !$wpTable.parent().hasClass('aura-table-inner-scroll')) {
            $wpTable.wrap('<div class="aura-table-inner-scroll"></div>');
        }

        // Inicializar sólo lo que existe en esta página.
        if ($('#aura-filters-sidebar').length || $('#toggle-filters').length) {
            initFiltersSidebar();
            initFilterChips();
        }

        if ($('.aura-datepicker').length) {
            try { initDatepickers(); } catch (e) { console.warn('[Aura] Datepicker no disponible:', e.message); }
        }

        if ($('.aura-select2').length) {
            try { initSelect2(); } catch (e) { console.warn('[Aura] Select2 no disponible:', e.message); }
        }

        if ($('#aura-filters-form').length || $('#transactions-filter').length) {
            initSearch();
            initQuickActions();
            initBulkActions();
            initFilterPresets();
            initUserFilterAutocomplete();
        }

        initGlobalFloatingTooltips();
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
            console.error('[Aura] Select2 no está disponible. Verifica la carga del script.');
            return;
        }
        console.log('[Aura] Inicializando Select2...');
        $('.aura-select2').each(function() {
            $(this).select2({
                width: '100%',
                placeholder: $(this).data('placeholder') || '',
                allowClear: true
            });
        });
    }
    
    /**
     * Sidebar de filtros colapsable y Drawer Off-Canvas Móvil
     */
    function initFiltersSidebar() {
        function updateSidebarState(collapsed) {
            const $sidebar = $('#aura-filters-sidebar');
            const $showBtn = $('#show-filters');
            const isMobile = window.innerWidth <= 1024;

            if (isMobile) {
                if (collapsed) {
                    $sidebar.removeClass('is-drawer-open');
                    $('#aura-drawer-backdrop').removeClass('is-active');
                    $('body').removeClass('aura-drawer-body-locked');
                } else {
                    $sidebar.addClass('is-drawer-open');
                    $('#aura-drawer-backdrop').addClass('is-active');
                    $('body').addClass('aura-drawer-body-locked');
                }
                return;
            }

            if (collapsed) {
                $sidebar.addClass('collapsed');
                $showBtn.css('display', 'inline-flex');
                localStorage.setItem('aura_filters_collapsed', 'true');
            } else {
                $sidebar.removeClass('collapsed');
                $showBtn.hide();
                localStorage.removeItem('aura_filters_collapsed');
            }
            $('#aura-drawer-backdrop').removeClass('is-active');
            $('body').removeClass('aura-drawer-body-locked');
            $sidebar.removeClass('is-drawer-open');
        }

        // Toggle sidebar al hacer clic en el botón de cerrar
        $(document).on('click', '#toggle-filters, .aura-toggle-sidebar-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $sidebar = $('#aura-filters-sidebar');
            const isMobile = window.innerWidth <= 1024;
            if (isMobile) {
                updateSidebarState(true);
            } else {
                const willCollapse = !$sidebar.hasClass('collapsed');
                updateSidebarState(willCollapse);
            }
        });
        
        // Botón para mostrar filtros cuando están colapsados o en móvil
        $(document).on('click', '#show-filters', function(e) {
            e.preventDefault();
            e.stopPropagation();
            updateSidebarState(false);
        });
        
        // Restaurar estado del sidebar desde localStorage solo en desktop
        if (window.innerWidth > 1024) {
            if (localStorage.getItem('aura_filters_collapsed') === 'true') {
                updateSidebarState(true);
            } else {
                updateSidebarState(false);
            }
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
        let pendingBulkTransactionIds = [];

        // Actualizar contador y badge en botón de edición masiva
        function updateBulkSelectionUI() {
            const count = $('input[name="transaction_ids[]"]:checked').length;
            const $badge = $('#aura-bulk-edit-badge');
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.hide();
            }
        }

        $(document).on('change', 'input[name="transaction_ids[]"], #cb-select-all-1, #cb-select-all-2', function() {
            setTimeout(updateBulkSelectionUI, 50);
        });
        updateBulkSelectionUI();

        // Abrir modal de Edición Masiva
        function openBulkEditModal(transactionIds) {
            if (!transactionIds || transactionIds.length === 0) {
                transactionIds = [];
                $('input[name="transaction_ids[]"]:checked').each(function() {
                    transactionIds.push($(this).val());
                });
            }
            if (transactionIds.length === 0) {
                alert('Selecciona al menos una transacción para editar en lote.');
                return;
            }
            pendingBulkTransactionIds = transactionIds;
            $('#aura-bulk-edit-count-num').text(transactionIds.length);
            
            // Resetear estado del gestor de tags en modal
            bulkSelectedTags = [];
            renderBulkTagsChips();
            $('#aura-bulk-tag-new-input').val('');
            $('#aura-bulk-chk-tags').prop('checked', false).trigger('change');
            $('input[name="aura_bulk_tags_mode"][value="replace"]').prop('checked', true).trigger('change');

            $('#aura-bulk-edit-modal').css('display', 'flex').addClass('active');
        }

        // Click en el botón de Edición Masiva de la barra superior
        $('#aura-bulk-edit-btn').on('click', function(e) {
            e.preventDefault();
            openBulkEditModal();
        });

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

            if (action === 'bulk_edit') {
                openBulkEditModal(transactionIds);
                return false;
            }

            if (action === 'change_category') {
                pendingBulkTransactionIds = transactionIds;
                $('#aura-bulk-cat-count').html(`Vas a reasignar la categoría de <strong>${transactionIds.length} transacción(es)</strong>.`);
                $('#aura-bulk-new-category').val('');
                $('#aura-bulk-category-modal').css('display', 'flex').addClass('active');
                return false;
            }
            
            if (action === 'bulk_delete' && !confirm(auraTransactionsList.messages.confirmBulkDelete)) {
                return false;
            }
            
            performBulkAction(action, transactionIds);
        });

        // Control de activación de campos en Modal de Edición Masiva
        function bindBulkEditCheckbox(chkId, wrapId) {
            $(chkId).on('change', function() {
                const isChecked = $(this).is(':checked');
                if (isChecked) {
                    $(wrapId).css({ opacity: 1, 'pointer-events': 'auto' });
                } else {
                    $(wrapId).css({ opacity: 0.45, 'pointer-events': 'none' });
                }
            });
        }
        bindBulkEditCheckbox('#aura-bulk-chk-area', '#aura-bulk-wrap-area');
        bindBulkEditCheckbox('#aura-bulk-chk-payment', '#aura-bulk-wrap-payment');
        bindBulkEditCheckbox('#aura-bulk-chk-related', '#aura-bulk-wrap-related');
        bindBulkEditCheckbox('#aura-bulk-chk-creator', '#aura-bulk-wrap-creator');
        bindBulkEditCheckbox('#aura-bulk-chk-category', '#aura-bulk-wrap-category');
        bindBulkEditCheckbox('#aura-bulk-chk-tags', '#aura-bulk-wrap-tags');

        // ── Gestión interactiva de Tags en Modal Masivo ──
        let bulkSelectedTags = [];

        function escapeTagText(str) {
            if (!str) return '';
            return $('<div>').text(str).html();
        }

        function renderBulkTagsChips() {
            const $chips = $('#aura-bulk-selected-tags-chips');
            $chips.empty();
            if (bulkSelectedTags.length > 0) {
                bulkSelectedTags.forEach(function(tag) {
                    const clean = tag.replace(/^#/, '').trim();
                    const $chip = $('<span class="aura-selected-tag-chip">' +
                        '<span class="tag-hash">#</span><span class="tag-text">' + escapeTagText(clean) + '</span>' +
                        '<button type="button" class="btn-remove-tag" data-tag="' + escapeTagText(clean) + '" title="Quitar etiqueta">&times;</button>' +
                    '</span>');
                    $chips.append($chip);
                });
                $chips.show();
            } else {
                $chips.hide();
            }
            $('#aura-bulk-val-tags').val(bulkSelectedTags.join(', '));
            syncBulkAvailablePills();
        }

        function addBulkTag(tag) {
            if (!tag) return;
            const clean = tag.replace(/^#/, '').trim();
            if (!clean) return;
            const exists = bulkSelectedTags.some(t => t.toLowerCase() === clean.toLowerCase());
            if (!exists) {
                bulkSelectedTags.push(clean);
                renderBulkTagsChips();
            }
        }

        function removeBulkTag(tag) {
            const clean = tag.replace(/^#/, '').trim().toLowerCase();
            bulkSelectedTags = bulkSelectedTags.filter(t => t.toLowerCase() !== clean);
            renderBulkTagsChips();
        }

        function syncBulkAvailablePills() {
            const lowerSelected = bulkSelectedTags.map(t => t.toLowerCase());
            $('#aura-bulk-available-tags-pills .aura-tag-pill-btn').each(function() {
                const tag = $(this).data('tag');
                if (tag && lowerSelected.includes(tag.toString().toLowerCase())) {
                    $(this).addClass('is-selected');
                } else {
                    $(this).removeClass('is-selected');
                }
            });
        }

        $('#aura-bulk-tags-manager-box').on('click', '.btn-remove-tag', function(e) {
            e.preventDefault();
            e.stopPropagation();
            removeBulkTag($(this).data('tag'));
        });

        $('#aura-bulk-tag-new-input').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',' || e.keyCode === 13 || e.keyCode === 188) {
                e.preventDefault();
                const val = $(this).val().trim();
                if (val) {
                    addBulkTag(val);
                    $(this).val('');
                }
            } else if (e.key === 'Backspace' && !$(this).val() && bulkSelectedTags.length > 0) {
                bulkSelectedTags.pop();
                renderBulkTagsChips();
            }
        });

        $('#btn-bulk-add-tag-chip').on('click', function(e) {
            e.preventDefault();
            const val = $('#aura-bulk-tag-new-input').val().trim();
            if (val) {
                addBulkTag(val);
                $('#aura-bulk-tag-new-input').val('').focus();
            }
        });

        $('#aura-bulk-available-tags-pills').on('click', '.aura-tag-pill-btn', function(e) {
            e.preventDefault();
            const tag = $(this).data('tag');
            if ($(this).hasClass('is-selected')) {
                removeBulkTag(tag);
            } else {
                addBulkTag(tag);
            }
        });

        $('input[name="aura_bulk_tags_mode"]').on('change', function() {
            const mode = $(this).val();
            if (mode === 'clear') {
                $('#aura-bulk-tags-selector-wrap').slideUp(150);
            } else {
                $('#aura-bulk-tags-selector-wrap').slideDown(150);
            }
        });

        // Modos de Vinculado (Tercero vs Usuario vs Texto libre vs Desvincular)
        $('input[name="aura_bulk_related_mode"]').on('change', function() {
            const mode = $(this).val();
            $('#aura-bulk-related-sub-tp').toggle(mode === 'third_party');
            $('#aura-bulk-related-sub-user').toggle(mode === 'user');
            $('#aura-bulk-related-sub-text').toggle(mode === 'recipient_payer');
        });

        // Mostrar concepto si el Tercero seleccionado es Persona Natural
        $('#aura-bulk-val-third-party').on('change', function() {
            const partyType = $(this).find('option:selected').data('party-type');
            if (partyType === 'person') {
                $('#aura-bulk-tp-concept-wrapper').slideDown(150);
            } else {
                $('#aura-bulk-tp-concept-wrapper').slideUp(150);
                $('#aura-bulk-val-third-party-concept').val('');
            }
        });

        // Confirmar Guardar Cambios Masivos
        $('#aura-confirm-bulk-edit-btn').on('click', function(e) {
            e.preventDefault();
            if (pendingBulkTransactionIds.length === 0) {
                alert('No hay transacciones seleccionadas.');
                return;
            }

            const editArea = $('#aura-bulk-chk-area').is(':checked');
            const editPayment = $('#aura-bulk-chk-payment').is(':checked');
            const editRelated = $('#aura-bulk-chk-related').is(':checked');
            const editCreator = $('#aura-bulk-chk-creator').is(':checked');
            const editCategory = $('#aura-bulk-chk-category').is(':checked');
            const editTags = $('#aura-bulk-chk-tags').is(':checked');

            if (!editArea && !editPayment && !editRelated && !editCreator && !editCategory && !editTags) {
                alert('Por favor marca al menos una casilla de los campos que deseas modificar.');
                return;
            }

            const payload = {
                action: 'aura_bulk_action_transactions',
                nonce: auraTransactionsList.nonce,
                action_type: 'bulk_edit',
                transaction_ids: pendingBulkTransactionIds
            };

            if (editArea) {
                payload.edit_area = 1;
                payload.new_area_id = $('#aura-bulk-val-area').val();
            }

            if (editPayment) {
                payload.edit_payment_method = 1;
                payload.new_payment_method = $('#aura-bulk-val-payment').val();
            }

            if (editRelated) {
                payload.edit_related = 1;
                const mode = $('input[name="aura_bulk_related_mode"]:checked').val();
                payload.related_mode = mode;
                if (mode === 'third_party') {
                    payload.new_third_party_id = $('#aura-bulk-val-third-party').val();
                    payload.new_third_party_concept = $('#aura-bulk-val-third-party-concept').val() || '';
                } else if (mode === 'user') {
                    payload.new_related_user_id = $('#aura-bulk-val-related-user').val();
                    payload.new_related_concept = $('#aura-bulk-val-related-concept').val();
                } else if (mode === 'recipient_payer') {
                    payload.new_recipient_payer = $('#aura-bulk-val-recipient-payer').val();
                }
            }

            if (editCreator) {
                payload.edit_created_by = 1;
                payload.new_created_by = $('#aura-bulk-val-creator').val();
            }

            if (editCategory) {
                payload.edit_category = 1;
                payload.new_category_id = $('#aura-bulk-val-category').val();
            }

            if (editTags) {
                payload.edit_tags = 1;
                payload.tags_mode = $('input[name="aura_bulk_tags_mode"]:checked').val() || 'replace';
                payload.new_tags = $('#aura-bulk-val-tags').val();
            }

            const $btn = $(this).prop('disabled', true);
            const origHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; vertical-align: middle;"></span> Guardando...');

            $.ajax({
                url: auraTransactionsList.ajaxUrl,
                type: 'POST',
                data: payload,
                success: function(response) {
                    $btn.prop('disabled', false).html(origHtml);
                    if (response.success) {
                        $('#aura-bulk-edit-modal').hide().removeClass('active');
                        showNotice('success', response.data.message || 'Transacciones actualizadas correctamente.');
                        setTimeout(function() {
                            location.reload();
                        }, 600);
                    } else {
                        showNotice('error', response.data.message || 'Error al actualizar transacciones.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(origHtml);
                    showNotice('error', 'Error de conexión con el servidor.');
                }
            });
        });

        // Cerrar modal de edición masiva
        $('#aura-bulk-edit-modal [data-close-bulk-edit]').on('click', function(e) {
            e.preventDefault();
            $('#aura-bulk-edit-modal').hide().removeClass('active');
        });

        // Confirmar cambio masivo de categoría desde el modal
        $('#aura-confirm-bulk-category-btn').on('click', function(e) {
            e.preventDefault();
            const newCatId = $('#aura-bulk-new-category').val();
            if (!newCatId) {
                alert('Por favor selecciona una categoría.');
                return;
            }

            const $btn = $(this).prop('disabled', true);
            const origText = $btn.html();
            $btn.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; vertical-align: middle;"></span> Aplicando...');

            performBulkAction('change_category', pendingBulkTransactionIds, newCatId, function() {
                $btn.prop('disabled', false).html(origText);
                $('#aura-bulk-category-modal').hide().removeClass('active');
            });
        });

        // Cerrar modal de categoría masiva
        $('#aura-bulk-category-modal [data-close-modal]').on('click', function(e) {
            e.preventDefault();
            $('#aura-bulk-category-modal').hide().removeClass('active');
        });
    }
    
    /**
     * Ejecutar acción masiva
     */
    function performBulkAction(action, transactionIds, extraParam, callback) {
        const payload = {
            action: 'aura_bulk_action_transactions',
            nonce: auraTransactionsList.nonce,
            action_type: action,
            transaction_ids: transactionIds
        };

        if (action === 'change_category' && extraParam) {
            payload.new_category_id = extraParam;
        }

        $.ajax({
            url: auraTransactionsList.ajaxUrl,
            type: 'POST',
            data: payload,
            success: function(response) {
                if (typeof callback === 'function') {
                    callback();
                }
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
                if (typeof callback === 'function') {
                    callback();
                }
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
        if (window.AuraUI && typeof window.AuraUI.showToast === 'function') {
            window.AuraUI.showToast(message, type);
        } else {
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
        const $preview = $('#aura-filter-user-preview');
        const $previewAvatar = $('#aura-filter-user-avatar');
        const $previewName = $('#aura-filter-user-name');
        const $clearBtn = $('#aura-filter-user-clear');

        if (!$filterSearch.length) return;

        function renderSelectedUser(name, avatarUrl) {
            $previewName.text(name || '');
            if (avatarUrl) {
                $previewAvatar.attr('src', avatarUrl).show();
            } else {
                $previewAvatar.attr('src', '').hide();
            }
            $preview.show();
        }

        function clearSelectedUser() {
            $filterSearch.val('');
            $filterHidden.val('');
            $previewAvatar.attr('src', '').show();
            $previewName.text('');
            $preview.hide();
        }

        // Estado inicial cuando el filtro viene aplicado en la URL
        if ($filterHidden.val() && $filterSearch.val()) {
            renderSelectedUser($filterSearch.val(), $previewAvatar.attr('src'));
        }

        // Evitar que falle toda la página si jQuery UI Autocomplete no está cargado
        if (typeof $.fn.autocomplete !== 'function') {
            console.warn('[Aura] jQuery UI Autocomplete no está disponible para filtros de usuario.');
            return;
        }

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
                renderSelectedUser(ui.item.name, ui.item.avatar_url || '');
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
                clearSelectedUser();
            }
        });

        $clearBtn.on('click', function(e) {
            e.preventDefault();
            clearSelectedUser();
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

    /**
     * Chips interactivos de tipo/estado y selector rápido de fechas
     */
    function initFilterChips() {
        // Toggle active visual class on checkbox chips
        $('.aura-chip-checkbox input[type="checkbox"]').on('change', function() {
            const $parent = $(this).closest('.aura-chip-checkbox');
            if ($(this).is(':checked')) {
                $parent.addClass('active');
            } else {
                $parent.removeClass('active');
            }
        });

        // Quick date preset buttons
        $('.aura-date-preset-btn').on('click', function(e) {
            e.preventDefault();
            const range = $(this).data('range');
            const today = new Date();
            let fromStr = '';
            let toStr = formatDate(today);

            if (range === 'today') {
                fromStr = formatDate(today);
            } else if (range === 'this_month') {
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                fromStr = formatDate(firstDay);
            } else if (range === 'last_30') {
                const past30 = new Date();
                past30.setDate(today.getDate() - 30);
                fromStr = formatDate(past30);
            } else if (range === 'this_year') {
                const firstDayYear = new Date(today.getFullYear(), 0, 1);
                fromStr = formatDate(firstDayYear);
            }

            $('#filter_date_from').val(fromStr);
            $('#filter_date_to').val(toStr);

            $('.aura-date-preset-btn').removeClass('active');
            $(this).addClass('active');
        });
    }

    /**
     * Motor global de tooltips flotantes sobre document.body (z-index: 99999999)
     */
    function initGlobalFloatingTooltips() {
        if (typeof AuraUI !== 'undefined' && AuraUI.initTooltips) {
            AuraUI.initTooltips();
        }
    }
    
})(jQuery);
