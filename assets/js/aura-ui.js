/**
 * Aura Business Suite - UI Controller
 * Maneja el comportamiento interactivo global: Modales, Toasts, Tabs.
 */

// Polyfill defensivo para select2: previene errores de scripts externos cuando Select2 no está cargado
if (typeof jQuery !== 'undefined' && !jQuery.fn.select2) {
    jQuery.fn.select2 = function() {
        return this;
    };
}

const AuraUI = {
    
    init: function() {
        this.initTabs();
        this.initModals();
        this.initNavbarScroll();
        this.initTooltips();
        this.initResponsiveTables();
        this.initOffCanvasDrawers();
        this.initWpNoticesRelocation();
    },

    /**
     * Reubica avisos/notices del sistema de WordPress fuera de los componentes
     * internos de Aura Suite, colocándolos en la parte superior sobre el layout/navbar.
     */
    initWpNoticesRelocation: function() {
        const doRelocate = () => {
            const appWrapper = document.querySelector('.aura-app-wrapper, .wrap.aura-app-context, .aura-layout, #wpbody-content > .wrap');
            if (!appWrapper) return;

            let container = document.getElementById('aura-wp-notices-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'aura-wp-notices-container';
                container.className = 'aura-wp-notices-container';
                appWrapper.parentNode.insertBefore(container, appWrapper);
            }

            const notices = document.querySelectorAll(
                '#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .error, #wpbody-content .update-nag, .wrap > .notice, .wrap > .updated, .wrap > .error, .aura-content .notice, .aura-widget-card .notice, .aura-tab-panel .notice'
            );

            notices.forEach(function(notice) {
                if (container.contains(notice)) return;

                // Evitar reubicar feedbacks internos de Aura
                if (notice.classList.contains('aura-inline-feedback') || 
                    notice.classList.contains('aura-feedback') || 
                    (notice.id && notice.id.startsWith('aura-')) ||
                    notice.closest('.aura-modal-content') ||
                    notice.closest('.aura-dialog')) {
                    return;
                }

                container.appendChild(notice);
            });
        };

        doRelocate();
        setTimeout(doRelocate, 100);
        setTimeout(doRelocate, 500);
        setTimeout(doRelocate, 1500);

        if (window.MutationObserver) {
            const targetNode = document.getElementById('wpbody-content') || document.body;
            let debounceTimer = null;
            const observer = new MutationObserver(function(mutations) {
                let shouldRelocate = false;
                for (let i = 0; i < mutations.length; i++) {
                    const addedNodes = mutations[i].addedNodes;
                    for (let j = 0; j < addedNodes.length; j++) {
                        const node = addedNodes[j];
                        if (node.nodeType === 1 && (
                            node.classList.contains('notice') || 
                            node.classList.contains('updated') || 
                            node.classList.contains('error') || 
                            (node.querySelector && node.querySelector('.notice, .updated, .error'))
                        )) {
                            const container = document.getElementById('aura-wp-notices-container');
                            if (!container || !container.contains(node)) {
                                shouldRelocate = true;
                                break;
                            }
                        }
                    }
                    if (shouldRelocate) break;
                }

                if (shouldRelocate) {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(doRelocate, 60);
                }
            });

            observer.observe(targetNode, { childList: true, subtree: true });
        }
    },

    /**
     * Tooltips Globales Flotantes (Portaleados a document.body)
     * Resuelve de forma definitiva que los tooltips queden recortados por
     * overflow:hidden, tablas, modales o stacking contexts en cualquier pantalla,
     * con comportamiento 100% fluido en hover (cero parpadeo) y soporte táctil interactivo.
     */
    initTooltips: function() {
        if (this._tooltipsInitialized) return;
        this._tooltipsInitialized = true;

        // Limpiar cualquier nodo duplicado de #aura-global-tooltip o tooltips huérfanos .aura-tooltip
        const allGlobalTips = document.querySelectorAll('#aura-global-tooltip');
        if (allGlobalTips.length > 1) {
            for (let i = 1; i < allGlobalTips.length; i++) {
                allGlobalTips[i].remove();
            }
        }
        document.querySelectorAll('.aura-tooltip').forEach(el => el.remove());

        let tooltipEl = document.getElementById('aura-global-tooltip');
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'aura-global-tooltip';
            tooltipEl.className = 'aura-global-tooltip';
            tooltipEl.style.cssText = 'display:none; position:fixed; top:-9999px; left:-9999px; z-index:9999999999;';
            tooltipEl.innerHTML = '<div class="aura-global-tooltip-inner"></div><div class="aura-global-tooltip-arrow"></div>';
            document.body.appendChild(tooltipEl);
        } else {
            tooltipEl.classList.add('aura-global-tooltip');
            tooltipEl.style.position = 'fixed';
            tooltipEl.style.zIndex = '9999999999';
        }

        let innerEl = tooltipEl.querySelector('.aura-global-tooltip-inner');
        if (!innerEl) {
            innerEl = document.createElement('div');
            innerEl.className = 'aura-global-tooltip-inner';
            tooltipEl.appendChild(innerEl);
        }
        let arrowEl = tooltipEl.querySelector('.aura-global-tooltip-arrow');
        if (!arrowEl) {
            arrowEl = document.createElement('div');
            arrowEl.className = 'aura-global-tooltip-arrow';
            tooltipEl.appendChild(arrowEl);
        }

        let currentTarget = null;
        let showTimeout = null;
        let hideTimeout = null;
        let pinnedByTouch = false;

        const selector = '.aura-help-tip, [data-tooltip], [data-aura-tooltip], .aura-tooltip-trigger, .aura-has-tooltip, .aura-tooltip-wrap, .aura-table-thumb-wrap, .aura-table-thumb-preview, .aura-img-preview-trigger, .avatar-zoomable, .avatar-hover-zoom, .aura-avatar-zoom, [data-preview-title], .aura-type-wrap, .aura-txn-pill-tooltip-wrap, .aura-help-icon';

        const getContainer = (el) => {
            if (!el) return null;
            return el.closest(selector) || el;
        };

        const showTooltip = (el, isTouch = false) => {
            if (document.body.classList.contains('aura-is-dragging')) return;
            const container = getContainer(el);
            if (!container) return;

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

            // 1. Verificar contenido enriquecido inline estándar (.aura-tooltip-content o .aura-tooltip-box)
            const childTooltipBox = container.querySelector ? container.querySelector('.aura-tooltip-content, .aura-tooltip-box, .aura-txn-id-tooltip-box, .aura-type-tooltip-box') : null;
            let htmlContent = '';
            let isCard = false;
            
            if (childTooltipBox) {
                htmlContent = childTooltipBox.innerHTML;
                isCard = true;
            }

            // 2. Verificar atributo explícito de tooltip enriquecido data-aura-tooltip
            if (!htmlContent) {
                htmlContent = container.getAttribute('data-aura-tooltip') || (el.getAttribute ? el.getAttribute('data-aura-tooltip') : '');
                if (htmlContent) {
                    isCard = true;
                }
            }

            // 3. Soporte para Previsualización Flotante HD de Logos/Fotos/Avatares
            const isImgTrigger = container.classList.contains('aura-img-preview-trigger') ||
                                 container.classList.contains('aura-table-thumb-preview') ||
                                 container.classList.contains('avatar-zoomable') ||
                                 container.classList.contains('avatar-hover-zoom') ||
                                 container.classList.contains('aura-avatar-zoom') ||
                                 container.hasAttribute('data-preview-title') ||
                                 container.hasAttribute('data-img-url') ||
                                 (el.classList && (
                                     el.classList.contains('aura-img-preview-trigger') ||
                                     el.classList.contains('aura-table-thumb-preview') ||
                                     el.classList.contains('avatar-zoomable') ||
                                     el.classList.contains('avatar-hover-zoom') ||
                                     el.classList.contains('aura-avatar-zoom')
                                 )) ||
                                 (el.hasAttribute && (el.hasAttribute('data-preview-title') || el.hasAttribute('data-img-url')));

            if (!htmlContent && isImgTrigger) {
                const imgEl = el.tagName === 'IMG' ? el : (container.querySelector ? container.querySelector('img') : null);
                const imgUrl = container.getAttribute('data-img-url') || (el.getAttribute ? el.getAttribute('data-img-url') : '') || (imgEl ? imgEl.src : '');
                const row = el.closest ? el.closest('tr') : null;
                const rowName = row ? ($(row).find('.aura-tp-fullname').text() || $(row).find('strong').text()) : '';
                const imgTitle = container.getAttribute('data-preview-title') || (el.getAttribute ? el.getAttribute('data-preview-title') : '') || container.getAttribute('data-img-title') || (el.getAttribute ? el.getAttribute('data-img-title') : '') || container.getAttribute('data-tooltip') || (imgEl ? imgEl.getAttribute('alt') : '') || rowName || 'Previsualización';

                if (imgUrl) {
                    const isAvatar = container.classList.contains('avatar-zoomable') || container.classList.contains('avatar-hover-zoom') || container.classList.contains('aura-avatar-zoom') || (el.classList && (el.classList.contains('avatar-zoomable') || el.classList.contains('avatar-hover-zoom') || el.classList.contains('aura-avatar-zoom')));
                    const subLabel = isAvatar ? '<span class="aura-floating-img-sub">Foto de Perfil</span>' : '';
                    htmlContent = '<div class="aura-floating-img-card">' +
                                  '<img src="' + imgUrl + '" alt="' + $('<span>').text(imgTitle).html() + '" style="' + (isAvatar ? 'border-radius:50% !important;width:140px !important;height:140px !important;object-fit:cover !important;' : '') + '">' +
                                  '<span class="aura-floating-img-title">' + $('<span>').text(imgTitle).html() + '</span>' +
                                  subLabel +
                                  '</div>';
                    isCard = true;
                }
            }

            // 4. Fallback a atributo data-tooltip o title
            if (!htmlContent) {
                htmlContent = container.getAttribute('data-tooltip') || container.getAttribute('title') || container.getAttribute('data-original-title') || (el.getAttribute ? (el.getAttribute('data-tooltip') || el.getAttribute('title')) : '');
            }

            if (!htmlContent || !htmlContent.trim()) return;

            // Decodificar si viene con codificación URL (encodeURIComponent)
            if (typeof htmlContent === 'string' && (htmlContent.startsWith('%3C') || htmlContent.startsWith('%3c') || htmlContent.includes('%20') || htmlContent.includes('%3E') || htmlContent.includes('%22') || htmlContent.includes('%27'))) {
                try { htmlContent = decodeURIComponent(htmlContent); } catch (e) {}
            }

            if (typeof htmlContent === 'string' && (htmlContent.indexOf('<div') !== -1 || htmlContent.indexOf('<span') !== -1 || htmlContent.indexOf('<table') !== -1 || htmlContent.indexOf('aura-tip-card') !== -1)) {
                isCard = true;
            }

            // Prevenir tooltip nativo del navegador si usa title
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

            // Asegurar que innerEl esté activo y vinculado
            innerEl = tooltipEl.querySelector('.aura-global-tooltip-inner');
            if (!innerEl) {
                innerEl = document.createElement('div');
                innerEl.className = 'aura-global-tooltip-inner';
                tooltipEl.appendChild(innerEl);
            }
            arrowEl = tooltipEl.querySelector('.aura-global-tooltip-arrow');
            if (!arrowEl) {
                arrowEl = document.createElement('div');
                arrowEl.className = 'aura-global-tooltip-arrow';
                tooltipEl.appendChild(arrowEl);
            }

            // Limpiar cualquier nodo hijo inesperado que no sea innerEl o arrowEl
            Array.from(tooltipEl.childNodes).forEach(node => {
                if (node !== innerEl && node !== arrowEl) {
                    node.remove();
                }
            });

            if (htmlContent.includes('<') && htmlContent.includes('>')) {
                innerEl.innerHTML = htmlContent;
            } else {
                innerEl.textContent = htmlContent;
            }

            if (isCard) {
                tooltipEl.classList.add('has-card-content');
            } else {
                tooltipEl.classList.remove('has-card-content');
            }

            tooltipEl.classList.remove('is-bottom', 'is-top', 'is-visible');
            tooltipEl.style.display = 'block';
            tooltipEl.style.visibility = 'hidden';

            // Medir dimensiones
            const rect = container.getBoundingClientRect();
            const tipRect = tooltipEl.getBoundingClientRect();
            const gap = 10;
            const arrowSize = 6;
            const winWidth = window.innerWidth;
            const winHeight = window.innerHeight;

            // Decidir si va arriba o abajo
            let placeAbove = true;
            if (rect.top < tipRect.height + gap + 15) {
                placeAbove = false;
            }

            let top = placeAbove 
                ? (rect.top - tipRect.height - gap)
                : (rect.bottom + gap);

            // Ajustar colisión vertical para que nunca se desborde fuera de la pantalla
            if (top < 10) {
                top = 10;
            } else if (top + tipRect.height > winHeight - 10) {
                if (rect.top - tipRect.height - gap >= 10) {
                    top = rect.top - tipRect.height - gap;
                    placeAbove = true;
                } else {
                    top = Math.max(10, winHeight - tipRect.height - 10);
                }
            }

            // Centrar horizontalmente respecto al elemento
            const triggerCenter = rect.left + (rect.width / 2);
            let left = triggerCenter - (tipRect.width / 2);

            // Asegurar que no se salga de la pantalla por izquierda o derecha
            const minLeft = 10;
            const maxLeft = winWidth - tipRect.width - 10;
            if (left < minLeft) left = minLeft;
            if (left > maxLeft) left = maxLeft;

            // Posicionar la flecha para que apunte exactamente al centro del disparador
            const arrowLeft = triggerCenter - left - arrowSize;
            const clampedArrowLeft = Math.max(8, Math.min(arrowLeft, tipRect.width - 18));

            if (arrowEl) {
                arrowEl.style.left = `${clampedArrowLeft}px`;
            }
            tooltipEl.style.top = `${Math.round(top)}px`;
            tooltipEl.style.left = `${Math.round(left)}px`;

            if (placeAbove) {
                tooltipEl.classList.add('is-top');
            } else {
                tooltipEl.classList.add('is-bottom');
            }

            // Forzar reflow y activar animación
            tooltipEl.style.visibility = 'visible';
            void tooltipEl.offsetWidth;
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

            const prevTarget = currentTarget;
            currentTarget = null;
            pinnedByTouch = false;

            // Restaurar título original si se había quitado
            if (prevTarget && prevTarget.hasAttribute && prevTarget.hasAttribute('data-original-title')) {
                prevTarget.setAttribute('title', prevTarget.getAttribute('data-original-title'));
                prevTarget.removeAttribute('data-original-title');
            }

            if (tooltipEl) {
                tooltipEl.classList.remove('is-visible');
                setTimeout(() => {
                    if (!currentTarget && tooltipEl && !tooltipEl.classList.contains('is-visible')) {
                        tooltipEl.style.display = 'none';
                        tooltipEl.style.top = '-9999px';
                        tooltipEl.style.left = '-9999px';
                        tooltipEl.classList.remove('has-card-content');
                        const curInner = tooltipEl.querySelector('.aura-global-tooltip-inner');
                        if (curInner) {
                            curInner.innerHTML = '';
                        }
                    }
                }, 150);
            }
        };

        // Eventos Hover Delegados con validación de relatedTarget
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

        // Permitir hover interactivo sobre el tooltip
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

        // Soporte Touch y Clic (Pinning / Unpinning)
        document.addEventListener('click', (e) => {
            const target = e.target && e.target.closest ? e.target.closest(selector) : null;
            if (target) {
                const container = getContainer(target);
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
    },

    /**
     * Gestión del Navbar Shrink on Scroll
     */
    initNavbarScroll: function() {
        const navbar = document.querySelector('.aura-navbar');
        if (!navbar) return;

        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('aura-navbar-shrink');
            } else {
                navbar.classList.remove('aura-navbar-shrink');
            }
        }, { passive: true });
    },

    /**
     * Gestión de Tabs Genéricos
     * Requiere botones con class="aura-tab-btn" y data-tab="id_panel"
     * y paneles con class="aura-tab-panel" y id="id_panel"
     */
    initTabs: function() {
        document.querySelectorAll('.aura-tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Buscar el contexto de los tabs (contenedor padre común o documento)
                const nav = btn.closest('.aura-nav') || document;
                const wrapper = btn.closest('.aura-app-wrapper') || document;
                
                // Desactivar botones en este nav
                nav.querySelectorAll('.aura-tab-btn').forEach(b => b.classList.remove('active'));
                
                // Desactivar paneles en este wrapper
                const tabId = btn.getAttribute('data-tab');
                if(!tabId) return;
                
                wrapper.querySelectorAll('.aura-tab-panel').forEach(p => p.classList.remove('active'));
                
                // Activar seleccionado
                btn.classList.add('active');
                const target = document.getElementById(tabId);
                if (target) {
                    target.classList.add('active');
                }
            });
        });
    },

    /**
     * Gestión de Notificaciones (Toasts)
     * @param {string} message - El mensaje a mostrar
     * @param {string} type - 'success', 'error', 'warning'
     */
    showToast: function(message, type = 'success') {
        let container = document.querySelector('.aura-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'aura-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `aura-toast ${type}`;
        
        let icon = '✅';
        if (type === 'error') icon = '❌';
        if (type === 'warning') icon = '⚠️';

        toast.innerHTML = `<span style="font-size:18px;">${icon}</span> <span>${message}</span>`;
        container.appendChild(toast);

        // Animar entrada
        setTimeout(() => toast.classList.add('show'), 50);

        // Remover después de 3.5s
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400); // esperar transición
        }, 3500);
    },

    /**
     * Gestión de Modales
     */
    initModals: function() {
        // Abrir modales con botones que tengan data-modal-target="id_modal"
        document.querySelectorAll('[data-modal-target]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.getAttribute('data-modal-target');
                this.openModal(modalId);
            });
        });

        // Cerrar modales (botones con clase aura-modal-close)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.aura-modal-close')) {
                const modal = e.target.closest('.aura-modal-overlay');
                if (modal) this.closeModal(modal.id);
            }
            
            // Cerrar al hacer clic en el overlay (fuera del contenido)
            if (e.target.classList.contains('aura-modal-overlay')) {
                this.closeModal(e.target.id);
            }
        });
        
        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.aura-modal-overlay.active');
                if (activeModal) this.closeModal(activeModal.id);
            }
        });
    },

    /**
     * Tablas Responsive y Filas Expandibles Universales (Child Rows)
     * Funciona automáticamente para cualquier tabla de cualquier módulo
     * con delegación de eventos en document (compatible con AJAX, paginación y filtros).
     */
    initResponsiveTables: function() {
        // Inyectar data-label en celdas para modo tarjeta en móvil
        this.initMobileTableLabels();

        document.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('.aura-row-toggle');
            if (!toggleBtn) return;

            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            const parentRow = toggleBtn.closest('.aura-parent-row') || toggleBtn.closest('tr');
            if (!parentRow) return;

            const rowId = toggleBtn.getAttribute('data-id') || parentRow.getAttribute('data-id');
            if (!rowId) return;

            const childRow = document.getElementById(`aura-child-row-${rowId}`);
            if (!childRow) return;

            const isCurrentlyExpanded = parentRow.classList.contains('is-expanded');

            if (isCurrentlyExpanded) {
                // Colapsar fila hija
                parentRow.classList.remove('is-expanded');
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.classList.remove('is-active');
                
                const cardWrapper = childRow.querySelector('.aura-child-card-wrapper');
                if (cardWrapper) {
                    cardWrapper.classList.remove('is-open');
                    setTimeout(() => {
                        if (!parentRow.classList.contains('is-expanded')) {
                            childRow.style.display = 'none';
                        }
                    }, 350);
                } else {
                    childRow.style.display = 'none';
                }
            } else {
                // Colapsar todas las demás filas primero (opcional — una a la vez)
                // document.querySelectorAll('.aura-parent-row.is-expanded').forEach(otherRow => { ... });

                // Expandir fila hija
                parentRow.classList.add('is-expanded');
                toggleBtn.setAttribute('aria-expanded', 'true');
                toggleBtn.classList.add('is-active');
                
                childRow.style.display = 'table-row';
                const cardWrapper = childRow.querySelector('.aura-child-card-wrapper');
                if (cardWrapper) {
                    // Forzar reflujo para activar transición suave
                    void cardWrapper.offsetHeight;
                    cardWrapper.classList.add('is-open');
                }
            }
        });
    },

    /**
     * Inyecta atributos data-label en las celdas de tablas .aura-table-responsive-wrap
     * leyendo los encabezados del <thead>. Esto permite que CSS ::before los muestre
     * como etiquetas en modo tarjeta en pantallas pequeñas.
     */
    initMobileTableLabels: function() {
        const applyLabels = () => {
            document.querySelectorAll('.aura-table-responsive-wrap .wp-list-table').forEach(table => {
                const headerThs = table.querySelectorAll('thead tr:first-child > *');
                if (!headerThs.length) return;

                const classLabelMap = {};
                const indexLabelMap = [];

                headerThs.forEach((th, i) => {
                    const clone = th.cloneNode(true);
                    clone.querySelectorAll('input, .dashicons, .screen-reader-text, .sorting-indicators').forEach(el => el.remove());
                    const labelText = clone.textContent.replace(/\s+/g, ' ').trim();

                    indexLabelMap[i] = labelText;

                    th.classList.forEach(cls => {
                        if (cls.startsWith('column-')) {
                            classLabelMap[cls] = labelText;
                        }
                    });
                });

                table.querySelectorAll('tbody tr.aura-parent-row').forEach(row => {
                    const cells = row.children;
                    for (let i = 0; i < cells.length; i++) {
                        const cell = cells[i];
                        if (cell.classList.contains('check-column')) continue;

                        let assigned = false;
                        cell.classList.forEach(cls => {
                            if (cls.startsWith('column-') && classLabelMap[cls]) {
                                cell.setAttribute('data-label', classLabelMap[cls]);
                                assigned = true;
                            }
                        });

                        if (!assigned && indexLabelMap[i] && !cell.getAttribute('data-label')) {
                            cell.setAttribute('data-label', indexLabelMap[i]);
                        }
                    }
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyLabels);
        } else {
            applyLabels();
        }

        document.addEventListener('aura:table:updated', applyLabels);
    },

    /**
     * Drawers Off-Canvas y Sidebars Móviles Universales
     * Convierte cualquier barra lateral de filtros o panel en drawer táctil en móvil.
     */
    initOffCanvasDrawers: function() {
        // Asegurar backdrop en el DOM
        let backdrop = document.getElementById('aura-drawer-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'aura-drawer-backdrop';
            backdrop.className = 'aura-drawer-backdrop';
            document.body.appendChild(backdrop);
        }

        const openDrawer = (drawerEl) => {
            if (!drawerEl) return;
            drawerEl.classList.add('is-drawer-open');
            backdrop.classList.add('is-active');
            document.body.classList.add('aura-drawer-body-locked');
        };

        const closeAllDrawers = () => {
            document.querySelectorAll('.aura-filters-card.is-drawer-open, .aura-offcanvas-drawer.is-drawer-open').forEach(el => {
                el.classList.remove('is-drawer-open');
            });
            backdrop.classList.remove('is-active');
            document.body.classList.remove('aura-drawer-body-locked');
        };

        // Disparador de apertura (solo offcanvas en pantallas móviles o disparadores explícitos)
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('#show-filters, [data-aura-toggle-drawer], .aura-toggle-drawer-btn');
            if (!trigger) return;

            const isMobile = window.innerWidth <= 1024;
            // En desktop (> 1024px), el botón #show-filters es para expandir el sidebar en el grid, no un drawer offcanvas modal
            if (!isMobile && trigger.id === 'show-filters' && !trigger.hasAttribute('data-aura-toggle-drawer')) {
                return; // Dejar que el script específico de la página maneje la expansión del grid
            }

            e.preventDefault();
            const targetSelector = trigger.getAttribute('data-aura-target') || '#aura-filters-sidebar';
            const drawer = document.querySelector(targetSelector);
            if (drawer) {
                if (drawer.classList.contains('is-drawer-open')) {
                    closeAllDrawers();
                } else {
                    openDrawer(drawer);
                }
            }
        });

        // Cerrar al hacer clic en el backdrop o botón de cerrar
        document.addEventListener('click', (e) => {
            if (e.target === backdrop || e.target.closest('.aura-drawer-close-btn, #close-filters-drawer, #toggle-filters')) {
                // Si estamos en móvil o el drawer está abierto, cerrar drawer
                const openDrawers = document.querySelectorAll('.is-drawer-open');
                if (openDrawers.length > 0 || window.innerWidth <= 1024) {
                    closeAllDrawers();
                }
            }
        });

        // Cerrar con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllDrawers();
            }
        });
    },

    openModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            // Manejar modales tipo aura-finance-modal que usan display:none inline
            if (modal.classList.contains('aura-finance-modal') || modal.classList.contains('aura-modal-overlay')) {
                modal.style.display = '';
            }
            document.body.style.overflow = 'hidden'; // prevenir scroll
        }
    },

    closeModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            // Ocultar modales tipo aura-finance-modal que usan display:none inline
            if (modal.classList.contains('aura-finance-modal')) {
                modal.style.display = 'none';
            }
            document.body.style.overflow = '';
        }
    }
};

// Exportar globalmente para otros scripts
window.AuraUI = AuraUI;

// Inicializar cuando el DOM esté listo o de inmediato si ya lo está
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        AuraUI.init();
    });
} else {
    AuraUI.init();
}

