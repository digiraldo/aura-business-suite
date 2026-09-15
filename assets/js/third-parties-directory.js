/**
 * Directorio de Terceros y Entidades Maestras (Aura Suite)
 * 
 * Cumple rigurosamente con el Prompt Maestro:
 * - Cabecera Hero y Barra de Navegación de Pestañas con sincronización en URL (?type=...)
 * - Previsualización Flotante HD de Logos/Fotografías en hover y touch (cero overflow clipping)
 * - Tooltips Enriquecidos con HTML (.aura-tip-card y .aura-floating-img-card)
 * - Filas Expandibles (Child Rows) responsivas en móviles
 * - Modales Blindados y Ficha 360° instantánea
 * - Modo Oscuro 100% Nativo
 * 
 * @package AuraBusinessSuite
 * @subpackage Common
 * @since 1.8.4
 */

(function($) {
    'use strict';

    let dataTable = null;
    let currentFilterType = '';
    let currentFilterStatus = 'all';
    let mediaUploader = null;
    let thirdPartiesMap = {};

    const partyTypeLabels = {
        'company': 'Empresa',
        'store': 'Tienda',
        'organization_foundation': 'Fundación',
        'person': 'Persona Natural',
        'religious': 'Entidad Religiosa',
        'other': 'Otra Entidad'
    };

    const partyTypeIcons = {
        'company': 'dashicons-building',
        'store': 'dashicons-cart',
        'organization_foundation': 'dashicons-heart',
        'person': 'dashicons-businessman',
        'religious': 'dashicons-location-alt',
        'other': 'dashicons-category'
    };

    const partyTypeEmojis = {
        'company': '🏢',
        'store': '🛒',
        'organization_foundation': '🏛️',
        'person': '👤',
        'religious': '⛪',
        'other': '🏷️'
    };

    const partyTypeDescriptions = {
        'company': 'Empresa / Sociedad Jurídica: Sociedades comerciales, corporaciones y personas jurídicas con registro mercantil.',
        'store': 'Tienda / Local Comercial: Establecimientos comerciales minoristas o mayoristas, locales y proveedores de insumos.',
        'organization_foundation': 'Fundación / ONG: Organizaciones no gubernamentales, fundaciones e instituciones benéficas sin ánimo de lucro.',
        'person': 'Persona Natural: Contratistas, clientes, prestatarios, lectores de biblioteca o particulares.',
        'religious': 'Entidad Religiosa: Parroquias, diócesis, templos, iglesias y congregaciones de culto.',
        'other': 'Otra Entidad: Prestaciones de servicios públicos, entidades municipales o instituciones no catalogadas.'
    };

    $(document).ready(function() {
        // Leer filtro inicial desde la URL si existe
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const initType = urlParams.get('type') || '';
            if (initType === 'inactive') {
                currentFilterType = '';
                currentFilterStatus = '0';
                $('.aura-tp-chip, .aura-tab-btn, .aura-navbar-item').removeClass('is-active active');
                $('.aura-tab-btn--inactive').addClass('is-active active');
                $('.aura-tp-status-btn').removeClass('is-active');
                $('.aura-tp-status-btn[data-status="0"]').addClass('is-active');
                $('#aura-tp-inactive-alert').show();
            } else if (initType) {
                currentFilterType = initType;
                currentFilterStatus = 'all';
                $('.aura-tp-chip, .aura-tab-btn, .aura-navbar-item').removeClass('is-active active');
                $('.aura-tp-chip[data-type="' + initType + '"], .aura-tab-btn[data-type="' + initType + '"], .aura-navbar-item[data-type="' + initType + '"]').addClass('is-active active');
                $('.aura-tp-status-btn').removeClass('is-active');
                $('.aura-tp-status-btn[data-status="all"]').addClass('is-active');
                $('#aura-tp-inactive-alert').hide();
            }
        } catch (e) {}

        initGlobalTooltips();
        bindEvents();
        initDataTable();
        loadThirdPartiesDataOnly();
    });

    /**
     * Motor Universal de Tooltips Globales Flotantes y Previsualización HD de Imágenes
     * Resuelve de forma definitiva que los tooltips queden recortados por
     * overflow:hidden, tablas, modales o stacking contexts, con comportamiento
     * 100% fluido en hover (cero parpadeo) y soporte táctil interactivo (touch pinning).
     */
    function initGlobalTooltips() {
        let tooltipEl = document.getElementById('aura-global-tooltip');
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'aura-global-tooltip';
            tooltipEl.className = 'aura-global-tooltip';
            tooltipEl.innerHTML = '<div id="aura-global-tooltip-inner" class="aura-global-tooltip-inner"></div><div id="aura-global-tooltip-arrow" class="aura-global-tooltip-arrow"></div>';
            document.body.appendChild(tooltipEl);
        }

        const innerEl = document.getElementById('aura-global-tooltip-inner') || tooltipEl.querySelector('.aura-global-tooltip-inner');
        const arrowEl = document.getElementById('aura-global-tooltip-arrow') || tooltipEl.querySelector('.aura-global-tooltip-arrow');
        let currentTarget = null;
        let showTimeout = null;
        let hideTimeout = null;
        let pinnedByTouch = false;

        const selector = '.aura-tooltip-wrap, .aura-table-thumb-wrap, .aura-table-thumb-preview, .aura-img-preview-trigger, [data-tooltip], .aura-help-icon, .aura-help-tip, td.aura-table-logo-cell';

        const getContainer = (el) => {
            if (!el) return null;
            return el.closest('.aura-tooltip-wrap, [data-tooltip], .aura-table-thumb-wrap, .aura-img-preview-trigger, .aura-table-thumb-preview, td.aura-table-logo-cell') || el.closest('.aura-help-icon') || el;
        };

        const showTooltip = (el, isTouch = false) => {
            const container = getContainer(el);
            if (!container) return;

            // Si ya está visible para el mismo contenedor, evitar parpadeo o reinicio brusco
            if (currentTarget === container && tooltipEl && tooltipEl.classList.contains('is-visible')) {
                if (hideTimeout) {
                    clearTimeout(hideTimeout);
                    hideTimeout = null;
                }
                if (isTouch) {
                    pinnedByTouch = true;
                }
                return;
            }

            if (hideTimeout) {
                clearTimeout(hideTimeout);
                hideTimeout = null;
            }
            
            let htmlContent = '';
            let isCard = false;

            // 1. Contenido enriquecido inline estándar (.aura-tooltip-content o .aura-tooltip-box)
            let contentBox = container.querySelector ? container.querySelector('.aura-tooltip-content, .aura-tooltip-box') : null;
            if (!contentBox && container.parentElement) {
                contentBox = container.parentElement.querySelector ? container.parentElement.querySelector('.aura-tooltip-content, .aura-tooltip-box') : null;
            }
            if (!contentBox && container.nextElementSibling && container.nextElementSibling.matches && container.nextElementSibling.matches('.aura-tooltip-content, .aura-tooltip-box')) {
                contentBox = container.nextElementSibling;
            }
            if (contentBox) {
                htmlContent = contentBox.innerHTML;
                isCard = true;
            }

            // 2. Soporte para Previsualización Flotante HD de Logos/Fotos
            if (!htmlContent && (container.classList.contains('aura-img-preview-trigger') || container.classList.contains('aura-table-thumb-preview') || container.hasAttribute('data-img-url') || (el.tagName === 'IMG') || el.classList.contains('aura-table-thumb-preview'))) {
                const imgEl = el.tagName === 'IMG' ? el : (container.querySelector ? container.querySelector('img') : null);
                const imgUrl = container.getAttribute('data-img-url') || (imgEl ? imgEl.src : '');
                const row = el.closest ? el.closest('tr') : null;
                const rowName = row ? ($(row).find('.aura-tp-fullname').text() || $(row).find('strong').text()) : '';
                const imgTitle = container.getAttribute('data-img-title') || container.getAttribute('data-tooltip') || (imgEl ? imgEl.getAttribute('alt') : '') || rowName || 'Previsualización';

                if (imgUrl) {
                    htmlContent = '<div class="aura-floating-img-card">' +
                                  '<img src="' + imgUrl + '" alt="' + $('<span>').text(imgTitle).html() + '">' +
                                  '<span class="aura-floating-img-title">' + $('<span>').text(imgTitle).html() + '</span>' +
                                  '</div>';
                    isCard = true;
                }
            }

            // 3. Fallback a texto o código en data-tooltip
            if (!htmlContent) {
                htmlContent = container.getAttribute('data-tooltip') || 
                              (el.getAttribute ? el.getAttribute('data-tooltip') : '') ||
                              (container.parentElement && container.parentElement.getAttribute ? container.parentElement.getAttribute('data-tooltip') : '') ||
                              container.getAttribute('title') || 
                              container.getAttribute('data-original-title') || 
                              (el.getAttribute ? el.getAttribute('title') : '');
                if (htmlContent && (htmlContent.indexOf('<div') !== -1 || htmlContent.indexOf('<span') !== -1)) {
                    isCard = true;
                }
            }

            if (!htmlContent || !htmlContent.trim()) return;

            // Prevenir tooltip nativo del navegador
            if (container.hasAttribute && container.hasAttribute('title')) {
                container.setAttribute('data-original-title', container.getAttribute('title'));
                container.removeAttribute('title');
            }
            if (el.hasAttribute && el.hasAttribute('title')) {
                el.setAttribute('data-original-title', el.getAttribute('title'));
                el.removeAttribute('title');
            }

            currentTarget = container;
            pinnedByTouch = isTouch;
            innerEl.innerHTML = htmlContent;

            if (isCard) {
                tooltipEl.classList.add('has-card-content');
            } else {
                tooltipEl.classList.remove('has-card-content');
            }

            tooltipEl.style.display = 'block';
            tooltipEl.style.visibility = 'hidden';
            tooltipEl.classList.remove('is-visible', 'is-top', 'is-bottom');

            // Medir dimensiones
            const rect = container.getBoundingClientRect();
            const tipRect = tooltipEl.getBoundingClientRect();
            const gap = 10;
            const arrowSize = 6;
            const winWidth = window.innerWidth;

            let placeAbove = true;
            if (rect.top < tipRect.height + gap + 15) {
                placeAbove = false;
            }

            let top = placeAbove 
                ? (rect.top - tipRect.height - gap)
                : (rect.bottom + gap);

            const triggerCenter = rect.left + (rect.width / 2);
            let left = triggerCenter - (tipRect.width / 2);

            // Ajustar bordes
            if (left < 10) left = 10;
            if (left + tipRect.width > winWidth - 10) {
                left = winWidth - tipRect.width - 10;
            }

            const arrowLeft = triggerCenter - left - arrowSize;
            const clampedArrow = Math.max(8, Math.min(arrowLeft, tipRect.width - 18));

            if (arrowEl) {
                arrowEl.style.left = clampedArrow + 'px';
            }
            tooltipEl.style.top = top + 'px';
            tooltipEl.style.left = left + 'px';

            if (placeAbove) {
                tooltipEl.classList.add('is-top');
            } else {
                tooltipEl.classList.add('is-bottom');
            }

            tooltipEl.style.visibility = 'visible';
            void tooltipEl.offsetWidth; // forzar reflow
            tooltipEl.classList.add('is-visible');
        };

        const hideTooltip = (force = false) => {
            if (showTimeout) {
                clearTimeout(showTimeout);
                showTimeout = null;
            }
            if (hideTimeout) {
                clearTimeout(hideTimeout);
                hideTimeout = null;
            }

            if (pinnedByTouch && !force) {
                return;
            }

            currentTarget = null;
            pinnedByTouch = false;

            if (tooltipEl) {
                tooltipEl.classList.remove('is-visible');
                setTimeout(() => {
                    if (!currentTarget && tooltipEl) {
                        tooltipEl.style.display = 'none';
                    }
                }, 150);
            }
        };

        // Eventos Hover: Delegación limpia sin useCapture destructivo y con validación de relatedTarget
        document.addEventListener('mouseover', (e) => {
            const target = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (target) {
                const container = getContainer(target);
                if (container === currentTarget && tooltipEl && tooltipEl.classList.contains('is-visible')) {
                    if (hideTimeout) {
                        clearTimeout(hideTimeout);
                        hideTimeout = null;
                    }
                    return;
                }
                if (hideTimeout) {
                    clearTimeout(hideTimeout);
                    hideTimeout = null;
                }
                if (showTimeout) clearTimeout(showTimeout);
                showTimeout = setTimeout(() => showTooltip(container, false), 25);
            }
        });

        document.addEventListener('mouseout', (e) => {
            const target = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (target) {
                const container = getContainer(target);
                const related = e.relatedTarget;
                // Si el puntero se mueve hacia un elemento hijo del mismo contenedor o entra al tooltip, NO ocultar
                if (related && (container.contains(related) || (tooltipEl && tooltipEl.contains(related)))) {
                    return;
                }
                if (showTimeout) {
                    clearTimeout(showTimeout);
                    showTimeout = null;
                }
                if (hideTimeout) clearTimeout(hideTimeout);
                hideTimeout = setTimeout(() => {
                    hideTooltip(false);
                }, 120);
            }
        });

        // Permitir que el cursor pase sobre el tooltip flotante sin cerrarlo
        tooltipEl.addEventListener('mouseenter', () => {
            if (hideTimeout) {
                clearTimeout(hideTimeout);
                hideTimeout = null;
            }
        });

        tooltipEl.addEventListener('mouseleave', (e) => {
            const related = e.relatedTarget;
            if (related && currentTarget && currentTarget.contains(related)) {
                return;
            }
            if (hideTimeout) clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
                hideTooltip(false);
            }, 100);
        });

        // Soporte Touch y Clic en móviles y escritorios (Pinning / Unpinning)
        document.addEventListener('click', (e) => {
            const target = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (target) {
                const container = getContainer(target);
                // Si se hace clic en una miniatura o trigger
                if (container) {
                    if (currentTarget === container && tooltipEl && tooltipEl.classList.contains('is-visible')) {
                        hideTooltip(true);
                    } else {
                        showTooltip(container, true);
                    }
                }
            } else if (!e.target.closest('#aura-global-tooltip')) {
                hideTooltip(true);
            }
        });

        document.addEventListener('focusin', (e) => {
            const target = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (target) {
                const container = getContainer(target);
                showTooltip(container, false);
            }
        });

        document.addEventListener('focusout', (e) => {
            const target = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (target) {
                hideTooltip(false);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideTooltip(true);
            }
        });

        let lastScrollY = window.scrollY;
        window.addEventListener('scroll', () => {
            if (currentTarget && Math.abs(window.scrollY - lastScrollY) > 30) {
                hideTooltip(true);
                lastScrollY = window.scrollY;
            }
        }, { passive: true });
    }

    /**
     * Formatear Fila Hija (Child Row) Responsiva para Pantallas Móviles
     */
    function formatChildRow(tp) {
        if (!tp) return '';
        const ptype = tp.party_type || 'company';
        const ptypeLabel = partyTypeLabels[ptype] || 'Empresa';
        const ptypeIcon = partyTypeIcons[ptype] || 'dashicons-building';
        const ptypeEmoji = partyTypeEmojis[ptype] || '🏢';
        const isActive = parseInt(tp.is_active, 10) === 1;

        let logoHtml = '';
        if (tp.logo_url) {
            logoHtml = '<div class="aura-table-thumb-preview" style="width:44px;height:44px;">' +
                       '<img src="' + tp.logo_url + '" width="44" height="44" alt="' + $('<span>').text(tp.full_name).html() + '">' +
                       '</div>';
        } else {
            logoHtml = '<div class="aura-table-thumb-placeholder is-emoji" style="width:44px;height:44px;font-size:24px;display:flex;align-items:center;justify-content:center;">' +
                       '<span class="aura-tp-emoji">' + ptypeEmoji + '</span>' +
                       '</div>';
        }

        let contactArr = [];
        if (tp.phone) {
            const cleanPhone = tp.phone.replace(/[^0-9+]/g, '');
            contactArr.push('<a href="https://wa.me/' + cleanPhone.replace('+', '') + '" target="_blank" class="aura-contact-link aura-contact-link--wa" title="WhatsApp"><span class="dashicons dashicons-whatsapp"></span> ' + $('<span>').text(tp.phone).html() + '</a>');
        }
        if (tp.email) {
            contactArr.push('<a href="mailto:' + tp.email + '" class="aura-contact-link aura-contact-link--email" title="Email"><span class="dashicons dashicons-email-alt"></span> ' + $('<span>').text(tp.email).html() + '</a>');
        }
        const contactHtml = contactArr.length ? contactArr.join('<br>') : '<em class="aura-text-muted">Sin datos de contacto</em>';

        const statusPill = isActive
            ? '<span class="aura-pill is-active">Activo</span>'
            : '<span class="aura-pill is-inactive">Inactivo</span>';

        const typePill = '<span class="aura-pill is-party-' + ptype + '"><span class="aura-tp-pill-emoji" style="margin-right:4px;">' + ptypeEmoji + '</span> ' + ptypeLabel + '</span>';

        let html = '<div class="aura-child-card-wrapper">' +
                   '<div class="aura-child-card">' +
                   '<div class="aura-child-hero-bar">' +
                   '<div class="aura-child-hero-left">' +
                   logoHtml +
                   '<div>' +
                   '<strong style="font-size:14px;color:#0f172a;display:block;">' + $('<span>').text(tp.full_name).html() + '</strong>' +
                   (tp.commercial_name && tp.commercial_name !== tp.full_name ? '<div style="font-size:12px;color:#64748b;">' + $('<span>').text(tp.commercial_name).html() + '</div>' : '') +
                   '</div>' +
                   '</div>' +
                   '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">' +
                   typePill +
                   statusPill +
                   '</div>' +
                   '</div>' +
                   '<div class="aura-child-sections-grid">' +
                   '<div class="aura-child-subcard">' +
                   '<div class="aura-child-subcard-title"><span class="dashicons dashicons-id"></span> Documento Fiscal</div>' +
                   '<div>' + (tp.document_id ? '<span class="aura-doc-badge"><strong>' + (tp.tax_id_type || 'NIT') + ':</strong> ' + $('<span>').text(tp.document_id).html() + '</span>' : '<em class="aura-text-muted">Sin documento</em>') + '</div>' +
                   '</div>' +
                   '<div class="aura-child-subcard">' +
                   '<div class="aura-child-subcard-title"><span class="dashicons dashicons-phone"></span> Contacto Directo</div>' +
                   '<div>' + contactHtml + '</div>' +
                   '</div>' +
                   (tp.address ? '<div class="aura-child-subcard"><div class="aura-child-subcard-title"><span class="dashicons dashicons-location"></span> Dirección</div><div>' + $('<span>').text(tp.address).html() + '</div></div>' : '') +
                   (tp.website ? '<div class="aura-child-subcard"><div class="aura-child-subcard-title"><span class="dashicons dashicons-admin-site-alt3"></span> Sitio Web</div><div><a href="' + tp.website + '" target="_blank" style="color:#2563eb;text-decoration:underline;">' + $('<span>').text(tp.website).html() + '</a></div></div>' : '') +
                   (tp.notes ? '<div class="aura-child-subcard" style="grid-column: 1 / -1;"><div class="aura-child-subcard-title"><span class="dashicons dashicons-edit"></span> Notas</div><div>' + $('<span>').text(tp.notes).html() + '</div></div>' : '') +
                   '</div>' +
                   '<div class="aura-child-action-dock">' +
                   '<button type="button" class="aura-btn-action aura-btn-action--view aura-btn-360" data-id="' + tp.id + '" title="Ver Ficha 360°" style="width:auto;padding:0 12px;gap:6px;"><span class="dashicons dashicons-visibility"></span> Ver Ficha 360°</button>';

        if (typeof auraThirdPartiesData !== 'undefined' && auraThirdPartiesData.canEdit) {
            html += '<button type="button" class="aura-btn-action aura-btn-action--edit aura-btn-edit" data-id="' + tp.id + '" title="Editar" style="width:auto;padding:0 12px;gap:6px;"><span class="dashicons dashicons-edit"></span> Editar</button>';
        }
        if (typeof auraThirdPartiesData !== 'undefined' && auraThirdPartiesData.canDelete) {
            html += '<button type="button" class="aura-btn-action aura-btn-action--toggle aura-btn-toggle" data-id="' + tp.id + '" title="' + (isActive ? 'Desactivar' : 'Activar') + '" style="width:auto;padding:0 12px;gap:6px;"><span class="dashicons ' + (isActive ? 'dashicons-hidden' : 'dashicons-visibility') + '"></span> ' + (isActive ? 'Desactivar' : 'Activar') + '</button>';
        }

        html += '</div></div></div>';
        return html;
    }

    /**
     * Inicializar DataTables sobre la tabla HTML existente de forma segura
     */
    function initDataTable() {
        const $table = $('#aura-third-parties-table');
        if (!$table.length) return;

        if (typeof $.fn.DataTable === 'undefined') {
            console.warn('[Aura Terceros] DataTables no está cargado. Se utilizará filtrado DOM nativo.');
            return;
        }

        try {
            // Filtro por tipo de tercero y buscador personalizado
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (!settings || !settings.nTable || settings.nTable.id !== 'aura-third-parties-table') {
                    return true;
                }
                const tr = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                const rowType = tr ? ($(tr).attr('data-type') || tr.getAttribute('data-type') || '') : '';
                const rowActive = tr ? ($(tr).attr('data-active') || tr.getAttribute('data-active') || '1') : '1';

                // Filtro por estado (todos / activos / desactivados)
                if (currentFilterStatus !== 'all' && rowActive !== currentFilterStatus) {
                    return false;
                }

                // Filtro por tipo
                if (currentFilterType && currentFilterType !== 'inactive' && rowType !== currentFilterType) {
                    return false;
                }

                // Filtro por término de búsqueda en input
                const term = ($('#aura-tp-search-input').val() || '').toLowerCase().trim();
                if (term) {
                    const rowText = tr ? tr.textContent.toLowerCase() : data.join(' ').toLowerCase();
                    if (rowText.indexOf(term) === -1) {
                        return false;
                    }
                }

                return true;
            });

            dataTable = $table.DataTable({
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Filtrar resultados...",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ terceros",
                    infoEmpty: "Mostrando 0 a 0 de 0 terceros",
                    infoFiltered: "(filtrado de _MAX_ totales)",
                    zeroRecords: "No se encontraron terceros registrados",
                    paginate: {
                        first: "Primero",
                        previous: "Anterior",
                        next: "Siguiente",
                        last: "Último"
                    }
                },
                order: [[1, 'asc']],
                pageLength: 25,
                dom: '<"top"r>t<"bottom"lip><"clear">',
                columnDefs: [
                    { orderable: false, targets: [0, 5, 7] },
                    { className: 'text-center', targets: [0, 6] },
                    { className: 'text-right', targets: [7] }
                ]
            });

            // Expandir / colapsar Fila Hija en dispositivos móviles
            $('#aura-third-parties-table tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.aura-btn-action, a, input, button, select, .aura-table-thumb-wrap, .aura-table-thumb-preview, .aura-img-preview-trigger, .aura-tooltip-wrap, td.aura-table-logo-cell').length) return;
                if (window.innerWidth > 768) return;

                const tr = $(this);
                if (!dataTable) return;
                const row = dataTable.row(tr);

                if (row.child && row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown is-expanded');
                } else if (row.child) {
                    const id = tr.attr('data-id') || tr.data('id');
                    const item = thirdPartiesMap[id];
                    if (item) {
                        row.child(formatChildRow(item)).show();
                        tr.addClass('shown is-expanded');
                    }
                }
            });

        } catch (err) {
            console.error('[Aura Terceros] Error al inicializar DataTables:', err);
        }
    }

    /**
     * Filtrado general (DataTables o DOM nativo)
     */
    function applyNativeFilter() {
        if (dataTable) {
            dataTable.draw();
            return;
        }
        const term = ($('#aura-tp-search-input').val() || '').toLowerCase().trim();
        $('#aura-third-parties-table tbody tr').each(function() {
            const $tr = $(this);
            const type = $tr.attr('data-type') || '';
            const active = $tr.attr('data-active') || '1';
            const text = $tr.text().toLowerCase();

            const matchStatus = currentFilterStatus === 'all' || active === currentFilterStatus;
            const matchType = !currentFilterType || currentFilterType === 'inactive' || type === currentFilterType;
            const matchSearch = !term || text.indexOf(term) !== -1;

            if (matchStatus && matchType && matchSearch) {
                $tr.show();
            } else {
                $tr.hide();
            }
        });
    }

    /**
     * Sincronizar parámetro en la URL sin recargar
     */
    function syncUrlParam(key, val) {
        try {
            const url = new URL(window.location.href);
            if (val) {
                url.searchParams.set(key, val);
            } else {
                url.searchParams.delete(key);
            }
            window.history.replaceState(null, '', url.toString());
        } catch(err) {}
    }

    /**
     * Cargar mapa de datos en segundo plano
     */
    function loadThirdPartiesDataOnly() {
        if (typeof auraThirdPartiesData === 'undefined') return;

        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_list',
                nonce: auraThirdPartiesData.nonce,
                party_type: ''
            },
            success: function(res) {
                if (res.success && res.data) {
                    thirdPartiesMap = {};
                    (res.data.items || []).forEach(function(item) {
                        thirdPartiesMap[item.id] = item;
                    });

                    if (res.data.kpis) {
                        updateKpisUI(res.data.kpis);
                    }
                }
            }
        });
    }

    /**
     * Actualizar métricas KPI en pantalla y contadores en pestañas
     */
    function updateKpisUI(kpis) {
        if (!kpis) return;
        const total = parseInt(kpis.total || 0, 10);
        const inactive = parseInt(kpis.inactive || 0, 10);
        const active = Math.max(0, total - inactive);

        $('#kpi-tp-total, #header-tp-total, #tab-count-all').text(total);
        $('#kpi-tp-company, #header-tp-company, #tab-count-company').text(kpis.company || 0);
        $('#kpi-tp-store, #tab-count-store').text(kpis.store || 0);
        $('#kpi-tp-foundation, #tab-count-foundation').text(kpis.organization_foundation || 0);
        $('#kpi-tp-person, #tab-count-person').text(kpis.person || 0);
        $('#kpi-tp-religious, #tab-count-religious').text(kpis.religious || 0);
        $('#kpi-tp-other, #tab-count-other').text(kpis.other || 0);

        $('#kpi-tp-inactive, #tab-count-inactive').text(inactive);
        $('#status-badge-all').text(total);
        $('#status-badge-active').text(active);
        $('#status-badge-inactive').text(inactive);
    }

    /**
     * Recargar tabla completamente vía AJAX con Tooltips Enriquecidos y Previsualización HD
     */
    function reloadTableFull() {
        if (typeof auraThirdPartiesData === 'undefined') {
            location.reload();
            return;
        }

        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_list',
                nonce: auraThirdPartiesData.nonce,
                party_type: ''
            },
            success: function(res) {
                if (res.success && res.data) {
                    thirdPartiesMap = {};
                    (res.data.items || []).forEach(function(item) {
                        thirdPartiesMap[item.id] = item;
                    });

                    if (res.data.kpis) {
                        updateKpisUI(res.data.kpis);
                    }

                    if (dataTable) {
                        dataTable.clear();
                        (res.data.items || []).forEach(function(tp) {
                            const ptype = tp.party_type || 'company';
                            const ptypeLabel = partyTypeLabels[ptype] || 'Empresa';
                            const ptypeIcon = partyTypeIcons[ptype] || 'dashicons-building';
                            const ptypeEmoji = partyTypeEmojis[ptype] || '🏢';
                            const isActive = parseInt(tp.is_active, 10) === 1;
                            const displayName = tp.commercial_name && tp.commercial_name !== tp.full_name ? tp.commercial_name : tp.full_name;

                            const ptypeDesc = partyTypeDescriptions[ptype] || partyTypeDescriptions['company'];

                            // 1. Logo con Tarjeta Enriquecida
                            let logoTooltipHtml = '';
                            if (tp.logo_url) {
                                logoTooltipHtml = '<div class="aura-floating-img-card">' +
                                                  '<img src="' + tp.logo_url + '" alt="' + $('<span>').text(displayName).html() + '">' +
                                                  '<span class="aura-floating-img-title">' + $('<span>').text(displayName).html() + '</span>' +
                                                  (tp.commercial_name && tp.commercial_name !== tp.full_name ? '<span class="aura-floating-img-sub">' + $('<span>').text(tp.full_name).html() + '</span>' : '') +
                                                  '<div class="aura-tip-badges" style="margin-top:6px;justify-content:center;">' +
                                                  '<span class="aura-tip-badge aura-tip-badge--' + ptype + '"><span class="aura-tp-pill-emoji">' + ptypeEmoji + '</span> ' + ptypeLabel + '</span>' +
                                                  '<span class="aura-tip-badge aura-tip-badge--' + (isActive ? 'active' : 'inactive') + '">' + (isActive ? 'Activo' : 'Inactivo') + '</span>' +
                                                  '</div>' +
                                                  '</div>';
                            } else {
                                logoTooltipHtml = '<div class="aura-tip-card">' +
                                                  '<div class="aura-tip-card-header">' +
                                                  '<div class="aura-tip-avatar-large is-emoji"><span class="aura-tp-emoji">' + ptypeEmoji + '</span></div>' +
                                                  '<div class="aura-tip-info">' +
                                                  '<div class="aura-tip-title">' + $('<span>').text(displayName).html() + '</div>' +
                                                  (tp.commercial_name && tp.commercial_name !== tp.full_name ? '<div class="aura-tip-subtitle">' + $('<span>').text(tp.full_name).html() + '</div>' : '') +
                                                  '<div class="aura-tip-badges">' +
                                                  '<span class="aura-tip-badge aura-tip-badge--' + ptype + '"><span class="aura-tp-pill-emoji">' + ptypeEmoji + '</span> ' + ptypeLabel + '</span>' +
                                                  '<span class="aura-tip-badge aura-tip-badge--' + (isActive ? 'active' : 'inactive') + '">' + (isActive ? 'Activo' : 'Inactivo') + '</span>' +
                                                  '</div>' +
                                                  '</div></div></div>';
                            }

                            const logoHtml = '<div class="aura-tooltip-wrap aura-table-thumb-wrap">' +
                                             (tp.logo_url
                                                ? '<div class="aura-table-thumb-preview aura-img-preview-trigger" data-img-url="' + tp.logo_url + '" data-img-title="' + $('<span>').text(displayName).html() + '"><img src="' + tp.logo_url + '" width="40" height="40" alt="' + $('<span>').text(tp.full_name).html() + '"></div>'
                                                : '<div class="aura-table-thumb-placeholder is-emoji" title="' + ptypeLabel + '"><span class="aura-tp-emoji">' + ptypeEmoji + '</span></div>') +
                                             '<div class="aura-tooltip-content">' + logoTooltipHtml + '</div>' +
                                             '</div>';

                            // 2. Nombre con Tarjeta Enriquecida Ficha
                            const nameTooltipHtml = '<div class="aura-tip-card">' +
                                                    '<div class="aura-tip-card-header">' +
                                                    '<div class="aura-tip-avatar-large' + (tp.logo_url ? '' : ' is-emoji') + '">' + (tp.logo_url ? '<img src="' + tp.logo_url + '" alt="' + $('<span>').text(displayName).html() + '">' : '<span class="aura-tp-emoji">' + ptypeEmoji + '</span>') + '</div>' +
                                                    '<div class="aura-tip-info">' +
                                                    '<div class="aura-tip-title">' + $('<span>').text(displayName).html() + '</div>' +
                                                    (tp.commercial_name && tp.commercial_name !== tp.full_name ? '<div class="aura-tip-subtitle">' + $('<span>').text(tp.full_name).html() + '</div>' : '') +
                                                    '<div class="aura-tip-badges">' +
                                                    '<span class="aura-tip-badge aura-tip-badge--' + ptype + '"><span class="aura-tp-pill-emoji">' + ptypeEmoji + '</span> ' + ptypeLabel + '</span>' +
                                                    '<span class="aura-tip-badge aura-tip-badge--' + (isActive ? 'active' : 'inactive') + '">' + (isActive ? 'Activo' : 'Inactivo') + '</span>' +
                                                    '</div></div></div>' +
                                                    '<div class="aura-tip-card-body">' +
                                                    '<div class="aura-tip-row"><span>🆔 Código / ID:</span><strong>#TP-' + $('<span>').text(tp.id).html() + '</strong></div>' +
                                                    (tp.wp_user_id ? '<div class="aura-tip-row"><span>👤 Usuario WP:</span><strong>@' + $('<span>').text(tp.wp_user_login || '').html() + '</strong></div>' : '') +
                                                    (tp.document_id ? '<div class="aura-tip-row"><span>📄 ' + (tp.tax_id_type || 'NIT') + ':</span><strong>' + $('<span>').text(tp.document_id).html() + '</strong></div>' : '') +
                                                    (tp.phone ? '<div class="aura-tip-row"><span>📞 Teléfono:</span><strong>' + $('<span>').text(tp.phone).html() + '</strong></div>' : '') +
                                                    (tp.email ? '<div class="aura-tip-row"><span>✉️ Correo:</span><strong>' + $('<span>').text(tp.email).html() + '</strong></div>' : '') +
                                                    (tp.address ? '<div class="aura-tip-row"><span>📍 Dirección:</span><strong>' + $('<span>').text(tp.address).html() + '</strong></div>' : '') +
                                                    (tp.website ? '<div class="aura-tip-row"><span>🌐 Sitio Web:</span><strong>' + $('<span>').text(tp.website).html() + '</strong></div>' : '') +
                                                    '</div></div>';

                            const wpUserBadge = tp.wp_user_id ? ' <span class="aura-badge-wp-user" data-tooltip="' + $('<span>').text('Usuario WP vinculado: @' + (tp.wp_user_login || '') + ' (' + (tp.wp_user_email || '') + ')').html() + '"><span class="dashicons dashicons-admin-users"></span> WP</span>' : '';

                            const nameHtml = '<div class="aura-tooltip-wrap aura-tp-name-wrap">' +
                                             '<div class="aura-tp-name-block"><strong class="aura-tp-fullname">' + $('<span>').text(tp.full_name || '').html() + '</strong>' + wpUserBadge + '</div>' +
                                             '<div class="aura-tooltip-content">' + nameTooltipHtml + '</div>' +
                                             '</div>';

                            // 3. Nombre Comercial con Tooltip
                            let commHtml = '<em class="aura-text-muted">-</em>';
                            if (tp.commercial_name && tp.commercial_name !== tp.full_name) {
                                const commTipHtml = '<div class="aura-tip-card">' +
                                                    '<div class="aura-tip-card-header">' +
                                                    '<div class="aura-tip-avatar-large"><span class="dashicons dashicons-store"></span></div>' +
                                                    '<div class="aura-tip-info">' +
                                                    '<div class="aura-tip-title">' + $('<span>').text(tp.commercial_name).html() + '</div>' +
                                                    '<div class="aura-tip-subtitle">' + $('<span>').text(tp.full_name).html() + '</div>' +
                                                    '<div class="aura-tip-badges">' +
                                                    '<span class="aura-tip-badge aura-tip-badge--' + ptype + '"><span class="aura-tp-pill-emoji">' + ptypeEmoji + '</span> ' + ptypeLabel + '</span>' +
                                                    '</div></div></div>' +
                                                    '<div class="aura-tip-card-body">' +
                                                    '<div class="aura-tip-row"><span>🏷️ Rótulo Comercial:</span><strong>' + $('<span>').text(tp.commercial_name).html() + '</strong></div>' +
                                                    '<div class="aura-tip-row"><span>🏢 Razón Social Formal:</span><strong>' + $('<span>').text(tp.full_name).html() + '</strong></div>' +
                                                    '</div></div>';

                                commHtml = '<div class="aura-tooltip-wrap">' +
                                           '<span class="aura-tp-commname">' + $('<span>').text(tp.commercial_name).html() + '</span>' +
                                           '<div class="aura-tooltip-content">' + commTipHtml + '</div>' +
                                           '</div>';
                            }

                            // 4. Tipo de Entidad con Tooltip Explicativo
                            const typeTipHtml = '<div class="aura-tip-card">' +
                                                '<div class="aura-tip-card-header">' +
                                                '<div class="aura-tip-avatar-large is-emoji"><span class="aura-tp-emoji">' + ptypeEmoji + '</span></div>' +
                                                '<div class="aura-tip-info">' +
                                                '<div class="aura-tip-title">' + $('<span>').text(ptypeLabel).html() + '</div>' +
                                                '<div class="aura-tip-subtitle">Clasificación Maestro</div>' +
                                                '</div></div>' +
                                                '<div class="aura-tip-card-body">' +
                                                '<div style="color:#cbd5e1;line-height:1.5;">' + $('<span>').text(ptypeDesc).html() + '</div>' +
                                                '</div></div>';

                            const typeHtml = '<div class="aura-tooltip-wrap">' +
                                             '<span class="aura-pill is-party-' + ptype + '"><span class="aura-tp-pill-emoji" style="margin-right:4px;">' + ptypeEmoji + '</span> ' + ptypeLabel + '</span>' +
                                             '<div class="aura-tooltip-content">' + typeTipHtml + '</div>' +
                                             '</div>';

                            // 5. Documento / NIT con Tooltip
                            let docHtml = '<em class="aura-text-muted">-</em>';
                            if (tp.document_id) {
                                const docTipHtml = '<div class="aura-tip-card">' +
                                                   '<div class="aura-tip-card-header">' +
                                                   '<div class="aura-tip-avatar-large"><span class="dashicons dashicons-id"></span></div>' +
                                                   '<div class="aura-tip-info">' +
                                                   '<div class="aura-tip-title">' + (tp.tax_id_type || 'NIT') + ': ' + $('<span>').text(tp.document_id).html() + '</div>' +
                                                   '<div class="aura-tip-subtitle">' + $('<span>').text(displayName).html() + '</div>' +
                                                   '</div></div>' +
                                                   '<div class="aura-tip-card-body">' +
                                                   '<div class="aura-tip-row"><span>📄 Tipo de Documento:</span><strong>' + (tp.tax_id_type || 'NIT') + '</strong></div>' +
                                                   '<div class="aura-tip-row"><span>🔢 Número Identificación:</span><strong>' + $('<span>').text(tp.document_id).html() + '</strong></div>' +
                                                   '<div class="aura-tip-row"><span>🏛️ Titular Registrado:</span><strong>' + $('<span>').text(tp.full_name).html() + '</strong></div>' +
                                                   '</div></div>';

                                docHtml = '<div class="aura-tooltip-wrap">' +
                                          '<span class="aura-doc-badge"><strong>' + (tp.tax_id_type || 'NIT') + ':</strong> ' + $('<span>').text(tp.document_id).html() + '</span>' +
                                          '<div class="aura-tooltip-content">' + docTipHtml + '</div>' +
                                          '</div>';
                            }

                            // 6. Contactos con data-tooltip
                            let contactArr = [];
                            if (tp.phone) {
                                const cleanPhone = tp.phone.replace(/[^0-9+]/g, '');
                                const waTip = '💬 Abrir chat directo de WhatsApp con ' + displayName + ' (' + tp.phone + ')';
                                contactArr.push('<a href="https://wa.me/' + cleanPhone.replace('+', '') + '" target="_blank" class="aura-contact-link aura-contact-link--wa" data-tooltip="' + $('<span>').text(waTip).html() + '"><span class="dashicons dashicons-whatsapp"></span> ' + $('<span>').text(tp.phone).html() + '</a>');
                            }
                            if (tp.email) {
                                const emailTip = '✉️ Enviar correo electrónico a ' + displayName + ' (' + tp.email + ')';
                                contactArr.push('<a href="mailto:' + tp.email + '" class="aura-contact-link aura-contact-link--email" data-tooltip="' + $('<span>').text(emailTip).html() + '"><span class="dashicons dashicons-email-alt"></span> ' + $('<span>').text(tp.email).html() + '</a>');
                            }
                            const contactHtml = contactArr.length ? contactArr.join('<br>') : '<em class="aura-text-muted">-</em>';

                            // 7. Estado con Tooltip
                            const statusDesc = isActive
                                ? '✅ Entidad Operativa: Habilitada para transacciones financieras, presupuestos, contratos y biblioteca.'
                                : '⛔ Entidad Deshabilitada: Bloqueada temporalmente para nuevas operaciones en el sistema.';

                            const statusTipHtml = '<div class="aura-tip-card">' +
                                                  '<div class="aura-tip-card-header">' +
                                                  '<div class="aura-tip-avatar-large"><span class="dashicons ' + (isActive ? 'dashicons-yes-alt' : 'dashicons-dismiss') + '"></span></div>' +
                                                  '<div class="aura-tip-info">' +
                                                  '<div class="aura-tip-title">' + (isActive ? 'Entidad Activa' : 'Entidad Inactiva') + '</div>' +
                                                  '<div class="aura-tip-subtitle">' + $('<span>').text(displayName).html() + '</div>' +
                                                  '</div></div>' +
                                                  '<div class="aura-tip-card-body">' +
                                                  '<div style="color:#cbd5e1;line-height:1.5;">' + statusDesc + '</div>' +
                                                  '</div></div>';

                            const statusHtml = '<div class="aura-tooltip-wrap">' +
                                               (isActive ? '<span class="aura-pill is-active">Activo</span>' : '<span class="aura-pill is-inactive">Inactivo</span>') +
                                               '<div class="aura-tooltip-content">' + statusTipHtml + '</div>' +
                                               '</div>';

                            // 8. Acciones con data-tooltip
                            let actionsHtml = '<div class="aura-table-actions">' +
                                              '<button type="button" class="aura-btn-action aura-btn-action--view aura-btn-360" data-id="' + tp.id + '" data-tooltip="👁️ Ficha 360°: Resumen integral, balance contable y movimientos vinculados"><span class="dashicons dashicons-visibility"></span></button>';
                            if (tp.wp_user_id) {
                                actionsHtml += '<a href="user-edit.php?user_id=' + tp.wp_user_id + '" class="aura-btn-action aura-btn-action--wp-user" target="_blank" data-tooltip="' + $('<span>').text('👤 Usuario WordPress: @' + (tp.wp_user_login || '') + ' (Ver perfil)').html() + '"><span class="dashicons dashicons-admin-users"></span></a>';
                            } else if (auraThirdPartiesData.canCreateUser) {
                                actionsHtml += '<button type="button" class="aura-btn-action aura-btn-action--create-wp aura-btn-create-wp" data-id="' + tp.id + '" data-tooltip="👤 Crear usuario de WordPress (Suscriptor)"><span class="dashicons dashicons-admin-users"></span></button>';
                            }
                            if (auraThirdPartiesData.canEdit) {
                                actionsHtml += '<button type="button" class="aura-btn-action aura-btn-action--edit aura-btn-edit" data-id="' + tp.id + '" data-tooltip="✏️ Editar Tercero: Actualizar razón social, contacto, identificación y logotipo"><span class="dashicons dashicons-edit"></span></button>';
                            }
                            if (auraThirdPartiesData.canDelete) {
                                actionsHtml += '<button type="button" class="aura-btn-action aura-btn-action--toggle ' + (isActive ? 'aura-btn-action--deactivate' : 'aura-btn-action--activate') + ' aura-btn-toggle" data-id="' + tp.id + '" data-tooltip="' + (isActive ? '⛔ Desactivar Tercero: Ocultar de nuevos registros manteniendo historial' : '✅ Reactivar Tercero: Restablecer operatividad en la suite') + '"><span class="dashicons ' + (isActive ? 'dashicons-hidden' : 'dashicons-undo') + '"></span></button>';
                                actionsHtml += '<button type="button" class="aura-btn-action aura-btn-action--delete aura-btn-hard-delete" data-id="' + tp.id + '" data-name="' + $('<span>').text(tp.commercial_name || tp.full_name).html() + '" data-tooltip="🗑️ Eliminar Tercero: Borrar definitivamente de la base de datos sin dejar rastro"><span class="dashicons dashicons-trash"></span></button>';
                            }
                            actionsHtml += '</div>';

                            const rowNode = dataTable.row.add([
                                logoHtml,
                                nameHtml,
                                commHtml,
                                typeHtml,
                                docHtml,
                                contactHtml,
                                statusHtml,
                                actionsHtml
                            ]).node();

                            $(rowNode).attr('data-id', tp.id).attr('data-type', ptype).attr('data-active', isActive ? '1' : '0');
                        });
                        dataTable.draw();
                    } else {
                        location.reload();
                    }
                }
            }
        });
    }

    /**
     * Eventos de la interfaz con delegación sobre el documento
     */
    function bindEvents() {
        // Pestañas / Barra de navegación de filtros
        $(document).on('click', '.aura-tp-chip, .aura-tab-btn, .aura-navbar-item', function(e) {
            e.preventDefault();
            const clickedType = $(this).attr('data-type') || $(this).data('type') || '';

            if (clickedType === 'inactive' || $(this).hasClass('aura-tab-btn--inactive')) {
                // Activar modo Desactivados
                currentFilterType = '';
                currentFilterStatus = '0';
                $('.aura-tp-chip, .aura-tab-btn, .aura-navbar-item').removeClass('is-active active button-primary');
                $('.aura-tab-btn--inactive').addClass('is-active active');
                $('.aura-tp-status-btn').removeClass('is-active');
                $('.aura-tp-status-btn[data-status="0"]').addClass('is-active');
                $('#aura-tp-inactive-alert').slideDown(150);
                syncUrlParam('type', 'inactive');
                applyNativeFilter();
                return;
            }

            // Pestaña de tipo o "Todos"
            currentFilterType = clickedType;
            $('.aura-tp-chip, .aura-tab-btn, .aura-navbar-item').removeClass('is-active active button-primary');
            $(this).addClass('is-active active');

            // Si venía de desactivados, restablecer el botón segmentado a Todos ('all')
            if (currentFilterStatus === '0') {
                currentFilterStatus = 'all';
                $('.aura-tp-status-btn').removeClass('is-active');
                $('.aura-tp-status-btn[data-status="all"]').addClass('is-active');
                $('#aura-tp-inactive-alert').slideUp(150);
            }

            syncUrlParam('type', currentFilterType || null);
            applyNativeFilter();
        });

        // Búsqueda en tiempo real
        $(document).on('keyup input search', '#aura-tp-search-input', function() {
            applyNativeFilter();
        });

        // Botón recargar
        $(document).on('click', '#aura-tp-refresh-btn', function(e) {
            e.preventDefault();
            reloadTableFull();
        });

        // Botón nuevo tercero (abrir modal)
        $(document).on('click', '#aura-tp-btn-new', function(e) {
            e.preventDefault();
            resetModalForm();
            $('#aura-tp-modal-title span:last-child').text('Registrar Tercero / Empresa');
            $('#aura-third-party-modal').addClass('is-active').show();
        });

        // Cerrar modal de edición / creación
        $(document).on('click', '#aura-tp-modal-close, #aura-tp-modal-cancel', function(e) {
            e.preventDefault();
            $('#aura-third-party-modal').removeClass('is-active').hide();
        });

        // Cerrar modal de ficha 360°
        $(document).on('click', '#aura-tp-360-close, #aura-tp-360-btn-close', function(e) {
            e.preventDefault();
            $('#aura-tp-modal-360').removeClass('is-active').hide();
        });

        // Crear usuario WordPress desde Tercero
        $(document).on('click', '.aura-btn-create-wp', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).closest('tr').data('id');
            if (id) {
                openCreateWpUserModal(id);
            }
        });

        // Cerrar modal de creación de usuario WP
        $(document).on('click', '#aura-wp-user-modal-close, #aura-wp-user-modal-cancel', function(e) {
            e.preventDefault();
            $('#aura-modal-create-wp-user').removeClass('is-active').hide();
        });

        // Guardar/Crear usuario WP
        $(document).on('submit', '#aura-form-create-wp-user', function(e) {
            e.preventDefault();
            submitCreateWpUser();
        });

        // Cerrar modales con tecla ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('#aura-third-party-modal, #aura-tp-modal-360, #aura-modal-create-wp-user').removeClass('is-active').hide();
            }
        });

        // Guardar formulario
        $(document).on('submit', '#aura-tp-form', function(e) {
            e.preventDefault();
            saveThirdParty();
        });

        // Editar tercero
        $(document).on('click', '.aura-btn-edit', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).closest('tr').data('id');
            if (id) {
                editThirdParty(id);
            }
        });

        // Activar / desactivar (Soft Delete)
        $(document).on('click', '.aura-btn-toggle', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).closest('tr').data('id');
            if (id) {
                toggleThirdParty(id);
            }
        });

        // Eliminar definitivamente de la base de datos (Hard Delete)
        $(document).on('click', '.aura-btn-hard-delete, .aura-btn-delete', function(e) {
            e.preventDefault();
            const id = $(this).data('id') || $(this).closest('tr').data('id');
            const name = $(this).data('name') || $(this).closest('tr').find('.aura-tp-fullname').text() || 'este tercero';
            if (!id) return;
            const confirmMsg = '¿Estás seguro de que deseas ELIMINAR DEFINITIVAMENTE a "' + name.trim() + '" de la base de datos?\n\n' +
                               '⚠️ Esta acción borrará todo rastro del tercero en el sistema y desvinculará cualquier historial.\n\n' +
                               'Podrás volver a registrar a esta persona o empresa en cualquier momento con el mismo correo, NIT o teléfono sin ningún error.';
            if (confirm(confirmMsg)) {
                deleteThirdParty(id);
            }
        });

        // Filtro de Estado (Todos / Activos / Desactivados)
        $(document).on('click', '.aura-tp-status-btn', function(e) {
            e.preventDefault();
            $('.aura-tp-status-btn').removeClass('is-active');
            $(this).addClass('is-active');
            currentFilterStatus = $(this).attr('data-status') || 'all';

            if (currentFilterStatus === '0') {
                $('#aura-tp-inactive-alert').slideDown(150);
                $('.aura-tp-chip, .aura-tab-btn, .aura-navbar-item').removeClass('is-active active button-primary');
                $('.aura-tab-btn--inactive').addClass('is-active active');
                currentFilterType = '';
                syncUrlParam('type', 'inactive');
            } else if (currentFilterStatus === '1') {
                $('#aura-tp-inactive-alert').slideUp(150);
                $('.aura-tab-btn--inactive').removeClass('is-active active');
                if (currentFilterType === 'inactive') {
                    currentFilterType = '';
                }
                if (!currentFilterType) {
                    $('.aura-tab-btn[data-type=""]').addClass('is-active active');
                    syncUrlParam('type', null);
                } else {
                    $('.aura-tab-btn[data-type="' + currentFilterType + '"]').addClass('is-active active');
                    syncUrlParam('type', currentFilterType);
                }
            } else {
                // 'all'
                $('#aura-tp-inactive-alert').slideUp(150);
                $('.aura-tab-btn--inactive').removeClass('is-active active');
                if (currentFilterType === 'inactive') {
                    currentFilterType = '';
                }
                if (!currentFilterType) {
                    $('.aura-tab-btn[data-type=""]').addClass('is-active active');
                    syncUrlParam('type', null);
                } else {
                    $('.aura-tab-btn[data-type="' + currentFilterType + '"]').addClass('is-active active');
                    syncUrlParam('type', currentFilterType);
                }
            }

            applyNativeFilter();
        });

        // Clic en KPI de Desactivados
        $(document).on('click', '#aura-kpi-card-inactive', function(e) {
            e.preventDefault();
            currentFilterType = '';
            currentFilterStatus = '0';
            $('.aura-tp-status-btn').removeClass('is-active');
            $('.aura-tp-status-btn[data-status="0"]').addClass('is-active');
            $('.aura-tp-chip, .aura-tab-btn, .aura-navbar-item').removeClass('is-active active button-primary');
            $('.aura-tab-btn--inactive').addClass('is-active active');
            $('#aura-tp-inactive-alert').slideDown(150);
            syncUrlParam('type', 'inactive');
            applyNativeFilter();

            $('html, body').animate({
                scrollTop: $('#aura-third-parties-table').offset().top - 120
            }, 300);
        });

        // Botón "Ver todos" desde la alerta de inactivos
        $(document).on('click', '#aura-tp-btn-show-all-from-alert', function(e) {
            e.preventDefault();
            currentFilterStatus = 'all';
            currentFilterType = '';
            $('.aura-tp-status-btn').removeClass('is-active');
            $('.aura-tp-status-btn[data-status="all"]').addClass('is-active');
            $('.aura-tp-chip, .aura-tab-btn, .aura-navbar-item').removeClass('is-active active button-primary');
            $('.aura-tab-btn[data-type=""]').addClass('is-active active');
            $('#aura-tp-inactive-alert').slideUp(150);
            syncUrlParam('type', null);
            applyNativeFilter();
        });

        // Ver Ficha 360
        $(document).on('click', '.aura-btn-360', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const id = $btn.data('id') || $btn.closest('tr').data('id');
            if (id) {
                showThirdParty360(id, $btn);
            }
        });

        // Uploader Logo / Foto
        $(document).on('click', '#aura-tp-btn-upload-logo', function(e) {
            e.preventDefault();
            openMediaUploader();
        });

        $(document).on('click', '#aura-tp-btn-remove-logo', function(e) {
            e.preventDefault();
            $('#aura_tp_logo_id').val('');
            const currentType = $('#aura_tp_party_type').val() || 'company';
            const emoji = partyTypeEmojis[currentType] || '🏢';
            $('#aura-tp-logo-preview').html('<span class="aura-tp-preview-emoji" style="font-size:28px;line-height:1;">' + emoji + '</span>');
            $(this).hide();
        });

        // Cambio de tipo de entidad en modal actualiza icono/emoji placeholder y addon
        $(document).on('change', '#aura_tp_party_type', function() {
            const currentType = $(this).val() || 'company';
            const emoji = partyTypeEmojis[currentType] || '🏢';
            $('#addon-party-type').html('<span class="aura-tp-select-emoji" style="font-size:16px;line-height:1;">' + emoji + '</span>');
            if (!$('#aura_tp_logo_id').val()) {
                $('#aura-tp-logo-preview').html('<span class="aura-tp-preview-emoji" style="font-size:28px;line-height:1;">' + emoji + '</span>');
            }
        });
    }

    /**
     * Limpiar formulario modal
     */
    function resetModalForm() {
        const form = document.getElementById('aura-tp-form');
        if (form) {
            form.reset();
        }
        $('#aura_tp_id').val('');
        $('#aura_tp_logo_id').val('');
        $('#aura-tp-btn-remove-logo').hide();
        $('#addon-party-type').html('<span class="aura-tp-select-emoji" style="font-size:16px;line-height:1;">🏢</span>');
        $('#aura-tp-logo-preview').html('<span class="aura-tp-preview-emoji" style="font-size:28px;line-height:1;">🏢</span>');
    }

    /**
     * Media Uploader de WordPress
     */
    function openMediaUploader() {
        if (typeof wp === 'undefined' || !wp.media) {
            alert('El selector de medios de WordPress no está disponible.');
            return;
        }

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Seleccionar Logo o Imagen del Tercero',
            button: { text: 'Usar esta imagen' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#aura_tp_logo_id').val(attachment.id);
            const previewUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            $('#aura-tp-logo-preview').html('<img src="' + previewUrl + '" style="width:100%;height:100%;object-fit:cover;">');
            $('#aura-tp-btn-remove-logo').show();
        });

        mediaUploader.open();
    }

    /**
     * Abrir modal para edición
     */
    function editThirdParty(id) {
        const cachedItem = thirdPartiesMap[id];
        if (cachedItem) {
            populateAndOpenEdit(cachedItem);
        }

        if (typeof auraThirdPartiesData === 'undefined') return;

        // Obtener siempre los datos más actualizados por AJAX
        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_summary_360',
                nonce: auraThirdPartiesData.nonce,
                id: id
            },
            success: function(res) {
                if (res.success && res.data && res.data.third_party) {
                    populateAndOpenEdit(res.data.third_party);
                }
            }
        });
    }

    function populateAndOpenEdit(item) {
        resetModalForm();
        $('#aura_tp_id').val(item.id);
        const ptype = item.party_type || 'company';
        const emoji = partyTypeEmojis[ptype] || '🏢';
        $('#aura_tp_party_type').val(ptype);
        $('#addon-party-type').html('<span class="aura-tp-select-emoji" style="font-size:16px;line-height:1;">' + emoji + '</span>');
        $('#aura_tp_full_name').val(item.full_name || '');
        $('#aura_tp_commercial_name').val(item.commercial_name || '');
        $('#aura_tp_tax_id_type').val(item.tax_id_type || 'NIT');
        $('#aura_tp_document_id').val(item.document_id || '');
        $('#aura_tp_phone').val(item.phone || '');
        $('#aura_tp_email').val(item.email || '');
        $('#aura_tp_website').val(item.website || '');
        $('#aura_tp_address').val(item.address || '');
        $('#aura_tp_notes').val(item.notes || '');

        if (item.logo_id && item.logo_url) {
            $('#aura_tp_logo_id').val(item.logo_id);
            $('#aura-tp-logo-preview').html('<img src="' + item.logo_url + '" style="width:100%;height:100%;object-fit:cover;">');
            $('#aura-tp-btn-remove-logo').show();
        } else {
            $('#aura-tp-logo-preview').html('<span class="aura-tp-preview-emoji" style="font-size:28px;line-height:1;">' + emoji + '</span>');
        }

        $('#aura-tp-modal-title span:last-child').text('Editar Tercero / Empresa');
        $('#aura-third-party-modal').addClass('is-active').show();
    }

    /**
     * Guardar tercero (Creación o Actualización)
     */
    function saveThirdParty() {
        const id = $('#aura_tp_id').val();
        const action = id ? 'aura_third_parties_update' : 'aura_third_parties_create';
        const $submitBtn = $('#aura-tp-modal-submit');
        const origText = $submitBtn.text();

        $submitBtn.prop('disabled', true).text(auraThirdPartiesData.i18n.saving || 'Guardando...');

        const formData = {
            action: action,
            nonce: auraThirdPartiesData.nonce,
            id: id,
            party_type: $('#aura_tp_party_type').val(),
            full_name: $('#aura_tp_full_name').val(),
            commercial_name: $('#aura_tp_commercial_name').val(),
            tax_id_type: $('#aura_tp_tax_id_type').val(),
            document_id: $('#aura_tp_document_id').val(),
            phone: $('#aura_tp_phone').val(),
            email: $('#aura_tp_email').val(),
            website: $('#aura_tp_website').val(),
            address: $('#aura_tp_address').val(),
            notes: $('#aura_tp_notes').val(),
            logo_id: $('#aura_tp_logo_id').val() || 0
        };

        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(res) {
                $submitBtn.prop('disabled', false).text(origText);
                if (res.success) {
                    $('#aura-third-party-modal').removeClass('is-active').hide();
                    reloadTableFull();
                } else {
                    alert(res.data && res.data.message ? res.data.message : (auraThirdPartiesData.i18n.error || 'Error al guardar.'));
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false).text(origText);
                alert(auraThirdPartiesData.i18n.error || 'Error de conexión con el servidor.');
            }
        });
    }

    /**
     * Activar / Desactivar
     */
    function toggleThirdParty(id) {
        if (typeof auraThirdPartiesData === 'undefined') return;

        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_toggle',
                nonce: auraThirdPartiesData.nonce,
                id: id
            },
            success: function(res) {
                if (res.success) {
                    reloadTableFull();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al cambiar estado.');
                }
            }
        });
    }

    /**
     * Eliminar tercero
     */
    function deleteThirdParty(id) {
        if (typeof auraThirdPartiesData === 'undefined') return;

        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_delete',
                nonce: auraThirdPartiesData.nonce,
                id: id
            },
            success: function(res) {
                if (res.success) {
                    reloadTableFull();
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Error al eliminar.');
                }
            }
        });
    }

    /**
     * Mostrar Ficha 360° con Animación Inmediata de Carga
     */
    function showThirdParty360(id, $btn) {
        if (typeof auraThirdPartiesData === 'undefined') return;

        // 1. Poner spinner en el botón clicado
        if ($btn && $btn.length) {
            $btn.find('.dashicons').attr('class', 'dashicons dashicons-update aura-spin');
        }

        // 2. Pre-cargar datos básicos si existen en caché para feedback instantáneo
        const cachedItem = thirdPartiesMap[id];
        if (cachedItem) {
            $('#aura-tp-360-name').text(cachedItem.commercial_name ? (cachedItem.commercial_name + ' (' + cachedItem.full_name + ')') : cachedItem.full_name);
            const pt = cachedItem.party_type || 'company';
            $('#aura-tp-360-badge').attr('class', 'aura-pill is-party-' + pt).html('<span class="dashicons ' + (partyTypeIcons[pt] || 'dashicons-building') + '"></span> ' + (partyTypeLabels[pt] || 'Empresa'));
            $('#aura-tp-360-doc').text(cachedItem.document_id ? ((cachedItem.tax_id_type || 'NIT') + ': ' + cachedItem.document_id) : 'Sin documento fiscal');

            if (cachedItem.logo_url) {
                $('#aura-tp-360-avatar').html('<img src="' + cachedItem.logo_url + '" style="width:100%;height:100%;object-fit:cover;">');
            } else {
                const emoji = partyTypeEmojis[pt] || '🏢';
                $('#aura-tp-360-avatar').html('<span class="aura-tp-emoji" style="font-size:26px;line-height:1;">' + emoji + '</span>');
            }
        } else {
            $('#aura-tp-360-name').text('Cargando información...');
            $('#aura-tp-360-badge').attr('class', 'aura-pill').text('...');
            $('#aura-tp-360-doc').text('...');
            $('#aura-tp-360-avatar').html('<span class="aura-tp-emoji" style="font-size:26px;line-height:1;">🏢</span>');
        }

        // 3. Mostrar loader y ocultar cuerpo detallado dentro del modal
        $('#aura-tp-360-loader').css('display', 'flex');
        $('#aura-tp-360-content-wrap').hide();

        // 4. Abrir modal INSTANTÁNEAMENTE
        $('#aura-tp-modal-360').addClass('is-active').show();

        // 5. Petición AJAX en segundo plano
        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_summary_360',
                nonce: auraThirdPartiesData.nonce,
                id: id
            },
            success: function(res) {
                // Restaurar icono del botón
                if ($btn && $btn.length) {
                    $btn.find('.dashicons').attr('class', 'dashicons dashicons-visibility');
                }

                if (!res.success || !res.data) {
                    $('#aura-tp-360-loader').hide();
                    alert(res.data && res.data.message ? res.data.message : 'Error al obtener la ficha 360°.');
                    return;
                }

                const d = res.data;
                const tp = d.third_party;

                // Cabecera
                $('#aura-tp-360-name').text(tp.commercial_name ? (tp.commercial_name + ' (' + tp.full_name + ')') : tp.full_name);
                const pt = tp.party_type || 'company';
                const emoji = partyTypeEmojis[pt] || '🏢';
                $('#aura-tp-360-badge').attr('class', 'aura-pill is-party-' + pt).html('<span class="aura-tp-pill-emoji" style="margin-right:4px;">' + emoji + '</span> ' + (partyTypeLabels[pt] || 'Empresa'));
                $('#aura-tp-360-doc').text(tp.document_id ? ((tp.tax_id_type || 'NIT') + ': ' + tp.document_id) : 'Sin documento fiscal');

                if (tp.logo_url) {
                    $('#aura-tp-360-avatar').html('<img src="' + tp.logo_url + '" style="width:100%;height:100%;object-fit:cover;">');
                } else {
                    $('#aura-tp-360-avatar').html('<span class="aura-tp-emoji" style="font-size:26px;line-height:1;">' + emoji + '</span>');
                }

                // KPIs
                $('#tp-360-tx-count').text(d.financial.tx_count);
                $('#tp-360-total-out').text('$' + Number(d.financial.total_out).toLocaleString('es-CO', { minimumFractionDigits: 0 }));
                $('#tp-360-inv-loans').text(d.inventory.loans_active);
                $('#tp-360-lib-loans').text(d.library.loans_active);

                // Contacto
                $('#tp-360-phone').text(tp.phone || '-');
                $('#tp-360-email').text(tp.email || '-');
                $('#tp-360-web').html(tp.website ? '<a href="' + tp.website + '" target="_blank" style="color:#2563eb;text-decoration:underline;">' + tp.website + '</a>' : '-');
                $('#tp-360-address').text(tp.address || '-');
                $('#tp-360-notes').text(tp.notes || '-');

                // Usuario WordPress vinculado
                if (tp.wp_user_id) {
                    $('#tp-360-wp-user').html('<span class="aura-badge-wp-user" style="display:inline-flex;padding:3px 8px;font-size:12px;border-radius:6px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-weight:600;"><span class="dashicons dashicons-admin-users" style="font-size:14px;width:14px;height:14px;margin-right:4px;"></span> @' + (tp.wp_user_login || tp.wp_user_id) + '</span> <a href="user-edit.php?user_id=' + tp.wp_user_id + '" target="_blank" style="margin-left:8px;font-size:12px;color:#2563eb;text-decoration:underline;font-weight:500;">Ver perfil en WP &rarr;</a>');
                } else {
                    let wpUserHtml = '<em class="aura-text-muted">No vinculado</em>';
                    if (auraThirdPartiesData.canCreateUser) {
                        wpUserHtml += ' <button type="button" class="aura-btn-secondary aura-btn-sm aura-btn-create-wp" data-id="' + tp.id + '" style="margin-left:8px;padding:3px 10px;font-size:11px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;"><span class="dashicons dashicons-admin-users" style="font-size:13px;width:13px;height:13px;"></span> Crear Suscriptor WP</button>';
                    }
                    $('#tp-360-wp-user').html(wpUserHtml);
                }

                // Transacciones recientes
                const $txContainer = $('#tp-360-recent-txs');
                if (d.financial.recent_txs && d.financial.recent_txs.length) {
                    let html = '<table style="width:100%;border-collapse:collapse;margin-top:6px;">' +
                               '<thead><tr style="border-bottom:1px solid #cbd5e1;color:#64748b;text-align:left;font-size:11px;text-transform:uppercase;">' +
                               '<th style="padding:4px 0;">Fecha</th><th>Tipo</th><th>Descripción</th><th style="text-align:right;">Monto</th></tr></thead><tbody>';
                    d.financial.recent_txs.forEach(function(tx) {
                        const isIncome = tx.transaction_type === 'income';
                        html += '<tr style="border-bottom:1px solid #f1f5f9;">' +
                                '<td style="padding:6px 0;">' + tx.transaction_date + '</td>' +
                                '<td><span class="aura-pill ' + (isIncome ? 'is-active' : 'is-party-organization_foundation') + '">' + (isIncome ? 'Ingreso' : 'Egreso') + '</span></td>' +
                                '<td>' + $('<span>').text(tx.description || '').html() + '</td>' +
                                '<td style="text-align:right;font-weight:600;color:' + (isIncome ? '#16a34a' : '#dc2626') + ';">$' + Number(tx.amount).toLocaleString('es-CO') + '</td>' +
                                '</tr>';
                    });
                    html += '</tbody></table>';
                    $txContainer.html(html);
                } else {
                    $txContainer.html('<p class="aura-text-muted" style="margin:0;">No hay transacciones registradas para este tercero.</p>');
                }

                // Ocultar loader y revelar contenido con animación suave
                $('#aura-tp-360-loader').hide();
                $('#aura-tp-360-content-wrap').fadeIn(150);
            },
            error: function() {
                if ($btn && $btn.length) {
                    $btn.find('.dashicons').attr('class', 'dashicons dashicons-visibility');
                }
                $('#aura-tp-360-loader').hide();
                alert('Error de conexión al cargar la Ficha 360°.');
            }
        });
    }

    /**
     * Abrir modal para crear usuario WordPress con rol Suscriptor
     */
    function openCreateWpUserModal(id) {
        const item = thirdPartiesMap[id];
        if (!item) {
            $.ajax({
                url: auraThirdPartiesData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aura_third_parties_summary_360',
                    nonce: auraThirdPartiesData.nonce,
                    id: id
                },
                success: function(res) {
                    if (res.success && res.data && res.data.third_party) {
                        thirdPartiesMap[id] = res.data.third_party;
                        populateCreateWpUserModal(res.data.third_party);
                    }
                }
            });
            return;
        }
        populateCreateWpUserModal(item);
    }

    function populateCreateWpUserModal(item) {
        $('#aura_wp_user_tp_id').val(item.id);
        $('#aura_wp_user_name').val(item.commercial_name ? (item.commercial_name + ' (' + item.full_name + ')') : item.full_name);
        $('#aura_wp_user_email').val(item.email || '');

        let suggestedLogin = '';
        if (item.email) {
            suggestedLogin = item.email.split('@')[0].toLowerCase().replace(/[^a-z0-9._-]/g, '');
        } else if (item.full_name) {
            suggestedLogin = item.full_name.toLowerCase().trim().replace(/\s+/g, '.').replace(/[^a-z0-9._-]/g, '');
        }
        $('#aura_wp_user_login').val(suggestedLogin);
        $('#aura_wp_user_notify').prop('checked', true);
        $('#aura-wp-user-alert').hide().empty();

        const $submitBtn = $('#aura-wp-user-modal-submit');
        $submitBtn.prop('disabled', false);
        $submitBtn.find('.dashicons').attr('class', 'dashicons dashicons-saved');
        $submitBtn.find('span:last-child').text('Crear Usuario Suscriptor');

        $('#aura-modal-create-wp-user').addClass('is-active').show();
    }

    /**
     * Enviar petición AJAX para crear el usuario en WordPress
     */
    function submitCreateWpUser() {
        const id = $('#aura_wp_user_tp_id').val();
        const email = $('#aura_wp_user_email').val().trim();
        const userLogin = $('#aura_wp_user_login').val().trim();
        const notify = $('#aura_wp_user_notify').is(':checked') ? 1 : 0;
        const $alert = $('#aura-wp-user-alert');
        const $submitBtn = $('#aura-wp-user-modal-submit');

        if (!email) {
            $alert.attr('style', 'display:block;padding:10px 14px;border-radius:8px;font-size:0.88rem;background:#fef2f2;color:#991b1b;border:1px solid #f87171;')
                  .text('Debes ingresar un correo electrónico válido.');
            return;
        }

        $alert.hide();
        $submitBtn.prop('disabled', true);
        $submitBtn.find('.dashicons').attr('class', 'dashicons dashicons-update aura-spin');
        $submitBtn.find('span:last-child').text(auraThirdPartiesData.i18n.creatingUser || 'Creando usuario...');

        $.ajax({
            url: auraThirdPartiesData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aura_third_parties_create_wp_user',
                nonce: auraThirdPartiesData.nonce,
                id: id,
                email: email,
                user_login: userLogin,
                send_notification: notify
            },
            success: function(res) {
                $submitBtn.prop('disabled', false);
                $submitBtn.find('.dashicons').attr('class', 'dashicons dashicons-saved');
                $submitBtn.find('span:last-child').text('Crear Usuario Suscriptor');

                if (res.success) {
                    $alert.attr('style', 'display:block;padding:10px 14px;border-radius:8px;font-size:0.88rem;background:#f0fdf4;color:#166534;border:1px solid #86efac;')
                          .html('<strong>✅ Éxito:</strong> ' + res.data.message);

                    if (thirdPartiesMap[id]) {
                        thirdPartiesMap[id].wp_user_id = res.data.user_id;
                        thirdPartiesMap[id].wp_user_login = res.data.user_login;
                        thirdPartiesMap[id].wp_user_email = res.data.user_email;
                        thirdPartiesMap[id].email = res.data.user_email;
                    }

                    // Actualizar fila en la tabla si está visible
                    const $row = $('#aura-third-parties-table tr[data-id="' + id + '"]');
                    if ($row.length) {
                        const $nameBlock = $row.find('.aura-tp-name-block');
                        if ($nameBlock.length && !$nameBlock.find('.aura-badge-wp-user').length) {
                            $nameBlock.append(' <span class="aura-badge-wp-user" data-tooltip="Usuario WP vinculado: @' + res.data.user_login + '"><span class="dashicons dashicons-admin-users"></span> WP</span>');
                        }
                        const $btnCreate = $row.find('.aura-btn-create-wp');
                        if ($btnCreate.length) {
                            $btnCreate.replaceWith('<a href="' + (res.data.edit_url || ('user-edit.php?user_id=' + res.data.user_id)) + '" class="aura-btn-action aura-btn-action--wp-user" target="_blank" data-tooltip="👤 Usuario WordPress: @' + res.data.user_login + ' (Ver perfil)"><span class="dashicons dashicons-admin-users"></span></a>');
                        }
                    }

                    setTimeout(function() {
                        $('#aura-modal-create-wp-user').removeClass('is-active').hide();
                        reloadTableFull();
                    }, 1400);
                } else {
                    $alert.attr('style', 'display:block;padding:10px 14px;border-radius:8px;font-size:0.88rem;background:#fef2f2;color:#991b1b;border:1px solid #f87171;')
                          .html('<strong>⚠️ Error:</strong> ' + (res.data && res.data.message ? res.data.message : 'No se pudo crear el usuario.'));
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false);
                $submitBtn.find('.dashicons').attr('class', 'dashicons dashicons-saved');
                $submitBtn.find('span:last-child').text('Crear Usuario Suscriptor');
                $alert.attr('style', 'display:block;padding:10px 14px;border-radius:8px;font-size:0.88rem;background:#fef2f2;color:#991b1b;border:1px solid #f87171;')
                      .html('<strong>⚠️ Error:</strong> Error de conexión con el servidor.');
            }
        });
    }

    /**
     * Formatear fila hija (Child Row) responsiva
     */
    function formatChildRow(item) {
        const ptype = item.party_type || 'company';
        const ptypeLabel = partyTypeLabels[ptype] || 'Empresa';
        const ptypeEmoji = partyTypeEmojis[ptype] || '🏢';
        const isActive = parseInt(item.is_active, 10) === 1;

        let wpUserSection = '';
        if (item.wp_user_id) {
            wpUserSection = '<div class="aura-child-row-item"><strong>Usuario WP:</strong> <span class="aura-badge-wp-user" style="display:inline-flex;padding:2px 6px;font-size:11px;border-radius:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;"><span class="dashicons dashicons-admin-users" style="font-size:13px;width:13px;height:13px;margin-right:2px;"></span> @' + (item.wp_user_login || item.wp_user_id) + '</span></div>';
        } else if (auraThirdPartiesData.canCreateUser) {
            wpUserSection = '<div class="aura-child-row-item"><strong>Usuario WP:</strong> <button type="button" class="aura-btn-secondary aura-btn-sm aura-btn-create-wp" data-id="' + item.id + '" style="padding:2px 8px;font-size:11px;display:inline-flex;align-items:center;gap:4px;"><span class="dashicons dashicons-admin-users" style="font-size:12px;width:12px;height:12px;"></span> Crear Suscriptor</button></div>';
        }

        return '<div class="aura-child-row-content" style="padding:12px 16px;background:var(--aura-bg-card,#f8fafc);border-left:3px solid #2563eb;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;font-size:12px;">' +
               '<div class="aura-child-row-item"><strong>Tipo:</strong> ' + ptypeEmoji + ' ' + ptypeLabel + '</div>' +
               '<div class="aura-child-row-item"><strong>Documento:</strong> ' + (item.document_id ? (item.tax_id_type || 'NIT') + ': ' + item.document_id : '-') + '</div>' +
               '<div class="aura-child-row-item"><strong>Teléfono:</strong> ' + (item.phone || '-') + '</div>' +
               '<div class="aura-child-row-item"><strong>Email:</strong> ' + (item.email || '-') + '</div>' +
               '<div class="aura-child-row-item"><strong>Dirección:</strong> ' + (item.address || '-') + '</div>' +
               '<div class="aura-child-row-item"><strong>Estado:</strong> ' + (isActive ? 'Activo' : 'Inactivo') + '</div>' +
               wpUserSection +
               '</div>';
    }

})(jQuery);
