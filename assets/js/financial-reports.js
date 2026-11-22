/**
 * Aura Financial Reports — Fase 3, Item 3.2
 * Maneja la generación, renderizado y exportación de reportes financieros.
 */
/* global jQuery, auraReports, Chart */
( function ( $ ) {
    'use strict';

    function __(s, domain) { return (window.wp && wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__(s, domain || 'aura-suite') : s; }
    function sprintf(fmt, ...args) { return (window.wp && wp.i18n && typeof wp.i18n.sprintf === 'function') ? wp.i18n.sprintf(fmt, ...args) : fmt; }

    // ─── Estado ──────────────────────────────────────────────────────────────
    const state = {
        currentType  : null,
        currentParams: null,
        currentData  : null,
        reportChart  : null,
    };

    // ─── Mapas ─────────────────────────────────────────────────────────────
    const REPORT_LABELS = {
        pl                 : __('Estado de Resultados (P&L)', 'aura-suite'),
        cashflow           : __('Flujo de Efectivo', 'aura-suite'),
        categories         : __('Análisis por Categoría', 'aura-suite'),
        pending            : __('Transacciones Pendientes', 'aura-suite'),
        budget             : __('Presupuesto vs Ejecutado', 'aura-suite'),
        budget_area_detail : __('Detalle por Área', 'aura-suite'),
        audit              : __('Auditoría Completa', 'aura-suite'),
        user_payments      : __('Sueldos / Pagos a Usuarios', 'aura-suite'),
    };

    const STATUS_LABELS = {
        approved: __('Aprobado', 'aura-suite'),
        pending : __('Pendiente', 'aura-suite'),
        rejected: __('Rechazado', 'aura-suite'),
    };

    // ─── Init ─────────────────────────────────────────────────────────────
    $( document ).ready( function () {
        initSidebarToggle();
        initPresets();
        loadSavedConfigs();
        bindEvents();
    } );

    // ─── Control de Sidebar Ocultar / Mostrar y Exportación Top Bar ──────
    function updateTopBarExportVisibility() {
        const isSidebarCollapsed = $( '#aura-reports-sidebar' ).hasClass( 'collapsed' );
        const hasReportData = Boolean( state.currentData );
        if ( isSidebarCollapsed && hasReportData ) {
            $( '#aura-top-bar-export' ).fadeIn( 150 );
        } else {
            $( '#aura-top-bar-export' ).hide();
        }
    }

    function initSidebarToggle() {
        const $sidebar = $( '#aura-reports-sidebar' );
        const $layout  = $( '#aura-reports-layout' );
        const $showBtn = $( '#show-filters' );

        // Restaurar estado guardado
        const isCollapsed = localStorage.getItem( 'aura_reports_sidebar_collapsed' ) === 'true';
        if ( isCollapsed ) {
            $sidebar.addClass( 'collapsed' ).hide();
            $layout.addClass( 'sidebar-collapsed' );
            $showBtn.fadeIn( 150 );
        }
        updateTopBarExportVisibility();

        $( '#toggle-filters' ).on( 'click', function ( e ) {
            e.preventDefault();
            $sidebar.addClass( 'collapsed' ).fadeOut( 200, function () {
                $layout.addClass( 'sidebar-collapsed' );
                $showBtn.fadeIn( 150 );
                updateTopBarExportVisibility();
                localStorage.setItem( 'aura_reports_sidebar_collapsed', 'true' );
            } );
        } );

        $showBtn.on( 'click', function ( e ) {
            e.preventDefault();
            $showBtn.fadeOut( 150, function () {
                $layout.removeClass( 'sidebar-collapsed' );
                $sidebar.removeClass( 'collapsed' ).fadeIn( 200 );
                updateTopBarExportVisibility();
                localStorage.setItem( 'aura_reports_sidebar_collapsed', 'false' );
            } );
        } );
    }

    // ─── Eventos ──────────────────────────────────────────────────────────

    function bindEvents() {
        // Activar botón generar al seleccionar tipo
        $( '#report_type' ).on( 'change', function () {
            const type = $( this ).val();
            $( '#btn-generate' ).prop( 'disabled', ! type );
            toggleFieldsByType( type );
        } );

        // Manejar interacción con chips de selección múltiple (Tipos de movimiento)
        $( document ).on( 'change', '.aura-chip-checkbox input[type="checkbox"]', function () {
            $( this ).closest( '.aura-chip-checkbox' ).toggleClass( 'active', this.checked );
        } );

        // Formulario
        $( '#aura-report-form' ).on( 'submit', function ( e ) {
            e.preventDefault();
            generateReport();
        } );

        // Exportación (Sidebar + Barra superior cuando está oculto)
        $( '#btn-export-csv, #btn-top-export-csv' ).on( 'click',   exportCSV );
        $( '#btn-export-excel, #btn-top-export-excel' ).on( 'click', exportExcel );
        $( '#btn-print, #btn-top-print' ).on( 'click',        printReport );

        // Guardar configuración
        $( '#btn-save-config' ).on( 'click', saveConfig );
    }

    // ─── Presets de fecha ────────────────────────────────────────────────

    function initPresets() {
        $( '.aura-preset-btn' ).on( 'click', function () {
            $( '.aura-preset-btn' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            applyPreset( $( this ).data( 'preset' ) );
        } );
    }

    function applyPreset( preset ) {
        const now   = new Date();
        let start, end;

        const fmt = d => d.toISOString().slice( 0, 10 );
        const y   = now.getFullYear();
        const m   = now.getMonth();

        switch ( preset ) {
            case 'month':
                start = fmt( new Date( y, m, 1 ) );
                end   = fmt( new Date( y, m + 1, 0 ) );
                break;
            case 'prevmonth':
                start = fmt( new Date( y, m - 1, 1 ) );
                end   = fmt( new Date( y, m, 0 ) );
                break;
            case 'quarter': {
                const q = Math.floor( m / 3 );
                start   = fmt( new Date( y, q * 3, 1 ) );
                end     = fmt( new Date( y, q * 3 + 3, 0 ) );
                break;
            }
            case 'year':
                start = `${y}-01-01`;
                end   = `${y}-12-31`;
                break;
        }
        if ( start ) $( '#report_start' ).val( start );
        if ( end )   $( '#report_end' ).val( end );
    }

    // ─── Mostrar/ocultar campos según tipo ───────────────────────────────

    function toggleFieldsByType( type ) {
        const hideDates   = [ 'pending' ];
        const hideStatus  = [ 'pending', 'budget' ];
        const showCreator = [ 'audit' ];
        const showArea    = [ 'budget', 'budget_area_detail' ];
        const showTypes   = [ 'categories' ];

        $( '#group-dates' ).toggle( ! hideDates.includes( type ) );
        $( '#group-status' ).toggle( ! hideStatus.includes( type ) );
        $( '#group-creator' ).toggle( showCreator.includes( type ) );
        $( '#group-area' ).toggle( showArea.includes( type ) );
        $( '#group-transaction-types' ).toggle( showTypes.includes( type ) );
    }

    // ─── Generar reporte (AJAX) ───────────────────────────────────────────

    function generateReport() {
        const formData = getFormData();

        showLoader();

        $.ajax( {
            url    : auraReports.ajaxUrl,
            method : 'POST',
            data   : {
                action            : 'aura_generate_report',
                nonce             : auraReports.nonce,
                report_type       : formData.report_type,
                start             : formData.start,
                end               : formData.end,
                status            : formData.status,
                created_by        : formData.created_by,
                area_id           : formData.area_id,
                transaction_types : formData.transaction_types,
            },
            success: function ( res ) {
                if ( res.success ) {
                    state.currentType   = res.data.type;
                    state.currentParams = res.data.params;
                    state.currentData   = res.data.data;
                    renderReport( res.data.type, res.data.params, res.data.data );
                } else {
                    showError( res.data || __('Error al generar el reporte.', 'aura-suite') );
                }
            },
            error: function () {
                showError( __('Error de conexión. Intenta nuevamente.', 'aura-suite') );
            },
        } );
    }

    // ─── Renderizar reporte ───────────────────────────────────────────────

    function renderReport( type, params, data ) {
        // Cabecera de impresión
        $( '#print-report-title' ).text( REPORT_LABELS[ type ] || type );
        $( '#print-report-period' ).text(
            params.start ? `${params.start} — ${params.end}` : __('Período global', 'aura-suite')
        );
        $( '#print-report-date' ).text( __('Generado:', 'aura-suite') + ' ' + new Date().toLocaleString( 'es' ) );

        // Destruir gráfico anterior si existe
        if ( state.reportChart ) {
            state.reportChart.destroy();
            state.reportChart = null;
        }

        switch ( type ) {
            case 'pl':                 renderPL( data );               break;
            case 'cashflow':           renderCashflow( data );         break;
            case 'categories':         renderCategories( data );       break;
            case 'pending':            renderPending( data );          break;
            case 'budget':             renderBudget( data );           break;
            case 'budget_area_detail': renderBudgetAreaDetail( data ); break;
            case 'audit':              renderAudit( data );            break;
            case 'user_payments':      renderUserPayments( data );     break;
        }

        showContent();
        $( '#aura-export-card' ).show();
        updateTopBarExportVisibility();
    }

    // ─── A. Estado de Resultados ─────────────────────────────────────────

    function renderPL( data ) {
        // KPIs
        const kpisHtml = `
            <div class="aura-report-kpi income">
                <div class="aura-report-kpi__label">${__('Total Ingresos', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_income )}</div>
                ${yearChangeHtml( data.prev_year?.income_pct_change )}
            </div>
            <div class="aura-report-kpi expense">
                <div class="aura-report-kpi__label">${__('Total Egresos', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_expense )}</div>
                ${yearChangeHtml( data.prev_year?.expense_pct_change )}
            </div>
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Balance Neto', 'aura-suite')}</div>
                <div class="aura-report-kpi__value ${data.net_balance >= 0 ? 'amount-income' : 'amount-expense'}">${formatMoney( data.net_balance )}</div>
                ${data.prev_year ? `<div class="aura-report-kpi__sub">${sprintf(__('Año ant.: Ingresos %1$s / Egresos %2$s', 'aura-suite'), formatMoney( data.prev_year.income ), formatMoney( data.prev_year.expense ))}</div>` : ''}
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();

        // Gráfico de barras P&L
        const incCats = data.income.map( r => r.category );
        const exCats  = data.expenses.map( r => r.category );
        const allCats = [ ...new Set( [ ...incCats, ...exCats ] ) ].slice( 0, 12 );

        const incMap = Object.fromEntries( data.income.map( r => [ r.category, r.total ] ) );
        const exMap  = Object.fromEntries( data.expenses.map( r => [ r.category, r.total ] ) );

        state.reportChart = createBarChart( 'report-chart', allCats, [
            { label: __('Ingresos', 'aura-suite'), data: allCats.map( c => incMap[ c ] || 0 ), backgroundColor: 'rgba(22,163,74,.7)' },
            { label: __('Egresos', 'aura-suite'),  data: allCats.map( c => exMap[ c ]  || 0 ), backgroundColor: 'rgba(220,38,38,.7)' },
        ] );
        $( '#report-chart-wrap' ).show();

        // Tabla
        let html = `<table class="aura-report-table">
            <thead><tr><th>${__('Tipo', 'aura-suite')}</th><th>${__('Categoría', 'aura-suite')}</th><th>${__('Monto', 'aura-suite')}</th></tr></thead><tbody>`;
        if ( data.income.length ) {
            html += `<tr class="section-header"><td colspan="3">${__('Ingresos', 'aura-suite')}</td></tr>`;
            data.income.forEach( r => {
                html += `<tr><td>${__('Ingreso', 'aura-suite')}</td><td>${esc( r.category )}</td><td class="amount-income">${formatMoney( r.total )}</td></tr>`;
            } );
        }
        if ( data.expenses.length ) {
            html += `<tr class="section-header"><td colspan="3">${__('Egresos', 'aura-suite')}</td></tr>`;
            data.expenses.forEach( r => {
                html += `<tr><td>${__('Egreso', 'aura-suite')}</td><td>${esc( r.category )}</td><td class="amount-expense">${formatMoney( r.total )}</td></tr>`;
            } );
        }
        html += `<tr class="totals-row"><td colspan="2">${__('Total Ingresos', 'aura-suite')}</td><td class="amount-income">${formatMoney( data.total_income )}</td></tr>`;
        html += `<tr class="totals-row"><td colspan="2">${__('Total Egresos', 'aura-suite')}</td><td class="amount-expense">${formatMoney( data.total_expense )}</td></tr>`;
        html += `<tr class="totals-row"><td colspan="2"><strong>${__('Balance Neto', 'aura-suite')}</strong></td><td class="${data.net_balance >= 0 ? 'amount-income' : 'amount-expense'}"><strong>${formatMoney( data.net_balance )}</strong></td></tr>`;
        html += '</tbody></table>';
        $( '#report-table-wrap' ).html( html );
    }

    // ─── B. Flujo de Efectivo ─────────────────────────────────────────────

    function renderCashflow( data ) {
        const kpisHtml = `
            <div class="aura-report-kpi income">
                <div class="aura-report-kpi__label">${__('Total Entradas', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_in )}</div>
            </div>
            <div class="aura-report-kpi expense">
                <div class="aura-report-kpi__label">${__('Total Salidas', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_out )}</div>
            </div>
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Flujo Neto', 'aura-suite')}</div>
                <div class="aura-report-kpi__value ${data.net >= 0 ? 'amount-income' : 'amount-expense'}">${formatMoney( data.net )}</div>
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();

        const methods = Object.keys( data.by_method );
        const ins  = methods.map( m => data.by_method[ m ]?.income?.total  || 0 );
        const outs = methods.map( m => data.by_method[ m ]?.expense?.total || 0 );

        state.reportChart = createBarChart( 'report-chart', methods, [
            { label: __('Entradas', 'aura-suite'), data: ins,  backgroundColor: 'rgba(22,163,74,.7)' },
            { label: __('Salidas', 'aura-suite'),  data: outs, backgroundColor: 'rgba(220,38,38,.7)' },
        ] );
        $( '#report-chart-wrap' ).show();

        let html = `<table class="aura-report-table">
            <thead><tr><th>${__('Método de Pago', 'aura-suite')}</th><th>${__('Entradas', 'aura-suite')}</th><th>${__('# Tx', 'aura-suite')}</th><th>${__('Salidas', 'aura-suite')}</th><th>${__('# Tx', 'aura-suite')}</th><th>${__('Neto', 'aura-suite')}</th></tr></thead><tbody>`;
        for ( const [ method, types ] of Object.entries( data.by_method ) ) {
            const inc_t = types.income?.total  || 0;
            const inc_c = types.income?.count  || 0;
            const exp_t = types.expense?.total || 0;
            const exp_c = types.expense?.count || 0;
            const net   = inc_t - exp_t;
            html += `<tr>
                <td>${esc( method )}</td>
                <td class="amount-income">${formatMoney( inc_t )}</td><td>${inc_c}</td>
                <td class="amount-expense">${formatMoney( exp_t )}</td><td>${exp_c}</td>
                <td class="${net >= 0 ? 'amount-income' : 'amount-expense'}">${formatMoney( net )}</td>
            </tr>`;
        }
        const netClass = data.net >= 0 ? 'amount-income' : 'amount-expense';
        html += `<tr class="totals-row">
            <td><strong>${__('TOTALES', 'aura-suite')}</strong></td>
            <td class="amount-income"><strong>${formatMoney( data.total_in )}</strong></td><td></td>
            <td class="amount-expense"><strong>${formatMoney( data.total_out )}</strong></td><td></td>
            <td class="${netClass}"><strong>${formatMoney( data.net )}</strong></td>
        </tr>`;
        html += '</tbody></table>';
        $( '#report-table-wrap' ).html( html );
    }

    // ─── C. Análisis Jerárquico por Categoría y Subcategorías ─────────────

    function renderCategories( data ) {
        const parents = data.parents || [];
        const grandTotal = data.grand_total || 0;
        const totalIncome = data.total_income || 0;
        const totalExpense = data.total_expense || 0;
        const totalCapital = data.total_capital || 0;
        const selectedTypes = data.selected_types || [ 'income', 'expense', 'capital' ];

        let kpisHtml = `
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Total Movimiento', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( grandTotal )}</div>
            </div>`;

        if ( selectedTypes.includes( 'income' ) || totalIncome > 0 ) {
            kpisHtml += `
            <div class="aura-report-kpi income">
                <div class="aura-report-kpi__label">${__('Total Ingresos', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( totalIncome )}</div>
            </div>`;
        }

        if ( selectedTypes.includes( 'expense' ) || totalExpense > 0 ) {
            kpisHtml += `
            <div class="aura-report-kpi expense">
                <div class="aura-report-kpi__label">${__('Total Egresos', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( totalExpense )}</div>
            </div>`;
        }

        if ( selectedTypes.includes( 'capital' ) || totalCapital > 0 ) {
            kpisHtml += `
            <div class="aura-report-kpi capital" style="border-left-color: #8b5cf6;">
                <div class="aura-report-kpi__label">${__('Gastos Capital (CapEx)', 'aura-suite')}</div>
                <div class="aura-report-kpi__value" style="color: #7c3aed;">${formatMoney( totalCapital )}</div>
            </div>`;
        }

        kpisHtml += `
            <div class="aura-report-kpi pending">
                <div class="aura-report-kpi__label">${__('Categorías Principales', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${parents.length}</div>
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();

        // Contenedor de Gráfico con soporte de Drilldown y Leyenda Lateral Interactiva
        let currentChartParentId = null;

        function drawCategoriesChart( parentId ) {
            if ( state.reportChart ) {
                state.reportChart.destroy();
                state.reportChart = null;
            }

            const ctx = document.getElementById( 'report-chart' ).getContext( '2d' );
            let labels = [], totals = [], colors = [], subcatCounts = [], catIds = [], chartTitle = '';

            if ( parentId === null ) {
                // Nivel 1: Categorías Padre
                labels       = parents.map( p => p.name );
                totals       = parents.map( p => p.total );
                colors       = parents.map( p => p.color || '#6366f1' );
                subcatCounts = parents.map( p => p.subcategories ? p.subcategories.length : 0 );
                catIds       = parents.map( p => p.id );
                chartTitle   = __('Distribución por Categorías Principales', 'aura-suite');
                $( '#report-cat-drilldown-bar' ).hide();
                $( '#report-donut-legend-title' ).text( __('Categorías Principales', 'aura-suite') );
                $( '#report-donut-legend-count' ).text( sprintf(__('%d categorías', 'aura-suite'), parents.length) );
            } else {
                // Nivel 2: Subcategorías del Padre seleccionado
                const parentObj = parents.find( p => p.id === parentId );
                if ( parentObj && parentObj.subcategories && parentObj.subcategories.length ) {
                    labels       = parentObj.subcategories.map( s => s.name );
                    totals       = parentObj.subcategories.map( s => s.total );
                    colors       = parentObj.subcategories.map( s => s.color || '#10b981' );
                    subcatCounts = parentObj.subcategories.map( () => 0 );
                    catIds       = [];
                    chartTitle   = __('Desglose de Subcategorías:', 'aura-suite') + ' ' + parentObj.name;
                    $( '#report-cat-drilldown-title' ).text( parentObj.name );
                    $( '#report-cat-drilldown-bar' ).show();
                    $( '#report-donut-legend-title' ).text( __('Subcategorías:', 'aura-suite') + ' ' + parentObj.name );
                    $( '#report-donut-legend-count' ).text( sprintf(__('%d subcats', 'aura-suite'), parentObj.subcategories.length) );
                } else {
                    drawCategoriesChart( null );
                    return;
                }
            }

            const isDarkMode = document.documentElement.getAttribute( 'data-wp-dark-mode-scheme' ) === 'dark' ||
                               document.body.classList.contains( 'wp-dark-mode-active' ) ||
                               document.body.classList.contains( 'aura-dark-mode' ) ||
                               document.body.getAttribute( 'data-theme' ) === 'dark';

            state.reportChart = new Chart( ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [ {
                        data: totals,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: isDarkMode ? '#1e293b' : '#ffffff',
                        hoverOffset: 8,
                    } ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false, // Desactivamos la leyenda rígida en canvas
                        },
                        tooltip: {
                            callbacks: {
                                label: function ( ctxTooltip ) {
                                    const sum = ctxTooltip.dataset.data.reduce( ( a, b ) => a + b, 0 );
                                    const pct = sum > 0 ? ( ctxTooltip.parsed / sum * 100 ).toFixed( 1 ) : 0;
                                    return ` ${ctxTooltip.label}: ${formatMoney( ctxTooltip.parsed )} (${pct}%)`;
                                }
                            }
                        }
                    },
                    onClick: function ( evt, elements ) {
                        if ( ! elements.length ) return;
                        const clickedIdx = elements[0].index;
                        if ( currentChartParentId === null ) {
                            const selectedParent = parents[clickedIdx];
                            if ( selectedParent && selectedParent.subcategories && selectedParent.subcategories.length > 1 ) {
                                currentChartParentId = selectedParent.id;
                                drawCategoriesChart( currentChartParentId );
                            }
                        }
                    }
                }
            } );

            // Renderizar Leyenda HTML a la derecha
            renderCategoriesDonutLegend( labels, totals, colors, subcatCounts, catIds, parentId === null );
        }

        function renderCategoriesDonutLegend( labels, amounts, colors, subcatCounts, catIds, isLevel1 ) {
            const $legend = $( '#report-donut-legend' ).empty();
            const sumTotal = amounts.reduce( ( a, b ) => a + b, 0 );

            labels.forEach( ( lbl, i ) => {
                const amt = amounts[i];
                const pct = sumTotal > 0 ? ( amt / sumTotal * 100 ).toFixed( 1 ) : 0;
                const subCount = subcatCounts[i] !== undefined ? subcatCounts[i] : 0;
                const hasSubs = isLevel1 && subCount > 1;

                const $item = $( `<div class="aura-donut-legend__item ${hasSubs ? 'is-clickable' : ''}"></div>` );
                $item.append( `<span class="aura-donut-legend__color" style="background:${colors[i]}"></span>` );

                const $nameWrap = $( '<div class="aura-donut-legend__name-wrap"></div>' );
                $nameWrap.append( `<span class="aura-donut-legend__name" title="${esc( lbl )}">${esc( lbl )}</span>` );

                if ( isLevel1 ) {
                    if ( subCount > 1 ) {
                        $nameWrap.append( `<span class="aura-subcat-badge" title="${sprintf(__('%d subcategorías registradas', 'aura-suite'), subCount)}"><span class="dashicons dashicons-category"></span> ${sprintf(__('%d subcats', 'aura-suite'), subCount)}</span>` );
                    } else {
                        $nameWrap.append( '<span class="aura-subcat-badge aura-subcat-badge--none" title="' + __('Sin subcategorías', 'aura-suite') + '">' + __('Directo', 'aura-suite') + '</span>' );
                    }
                }

                $item.append( $nameWrap );
                $item.append( `<span class="aura-donut-legend__amt">${formatMoney( amt )}</span>` );
                $item.append( `<span class="aura-donut-legend__pct">${pct}%</span>` );

                // Hover para resaltar segmento del Donut
                $item.on( 'mouseenter', function () {
                    if ( state.reportChart ) {
                        state.reportChart.setActiveElements( [ { datasetIndex: 0, index: i } ] );
                        state.reportChart.update();
                    }
                } ).on( 'mouseleave', function () {
                    if ( state.reportChart ) {
                        state.reportChart.setActiveElements( [] );
                        state.reportChart.update();
                    }
                } );

                // Click para drilldown si es nivel 1 y tiene subcategorías
                if ( hasSubs && catIds[i] ) {
                    $item.attr( 'title', sprintf(__('Haz clic para ver el desglose de %s', 'aura-suite'), lbl) );
                    $item.on( 'click', function () {
                        currentChartParentId = catIds[i];
                        drawCategoriesChart( currentChartParentId );
                    } );
                }

                $legend.append( $item );
            } );
        }

        // HTML para envolver gráfico Donut a la izquierda y leyenda HTML a la derecha
        let chartWrapHtml = `
            <div id="report-cat-drilldown-bar" class="aura-drilldown-nav-bar" style="display:none;margin-bottom:14px;">
                <button type="button" class="button button-small" id="btn-back-to-parents">
                    <span class="dashicons dashicons-arrow-left-alt2"></span> ${__('Volver a Categorías Principales', 'aura-suite')}
                </button>
                <span class="aura-drilldown-badge">${__('Desglose:', 'aura-suite')} <strong id="report-cat-drilldown-title"></strong></span>
            </div>
            <div class="aura-donut-report-layout">
                <div class="aura-donut-chart-box">
                    <canvas id="report-chart"></canvas>
                </div>
                <div class="aura-donut-legend-box">
                    <div class="aura-donut-legend-header">
                        <span class="aura-donut-legend-header__title" id="report-donut-legend-title">${__('Categorías Principales', 'aura-suite')}</span>
                        <span class="aura-donut-legend-header__count" id="report-donut-legend-count"></span>
                    </div>
                    <div class="aura-donut-legend" id="report-donut-legend"></div>
                </div>
            </div>
        `;
        $( '#report-chart-wrap' ).html( chartWrapHtml ).show();
        drawCategoriesChart( null );

        $( document ).off( 'click', '#btn-back-to-parents' ).on( 'click', '#btn-back-to-parents', function () {
            currentChartParentId = null;
            drawCategoriesChart( null );
        } );

        // Helpers de badges de tipo
        function getTypeBadgeHtml( type ) {
            if ( type === 'income' ) {
                return '<span class="aura-status-badge approved" style="background:#ecfdf5;color:#047857;border-color:#10b981;">' + __('Ingreso', 'aura-suite') + '</span>';
            } else if ( type === 'capital' ) {
                return '<span class="aura-status-badge capital" style="background:#f5f3ff;color:#6d28d9;border-color:#8b5cf6;">CapEx</span>';
            } else {
                return '<span class="aura-status-badge rejected" style="background:#fef2f2;color:#b91c1c;border-color:#ef4444;">' + __('Egreso', 'aura-suite') + '</span>';
            }
        }

        function getTypeAmountClass( type ) {
            if ( type === 'income' ) return 'amount-income';
            if ( type === 'capital' ) return 'amount-capital';
            return 'amount-expense';
        }

        // Tabla Jerárquica con Acordeón
        let html = `
            <div class="aura-table-toolbar">
                <span class="aura-table-toolbar__title"><strong>${__('Estructura Jerárquica de Categorías y Subcategorías', 'aura-suite')}</strong></span>
                <div class="aura-table-toolbar__actions">
                    <button type="button" class="button button-small" id="btn-expand-all-cats">
                        <span class="dashicons dashicons-plus-alt2"></span> ${__('Expandir Todas', 'aura-suite')}
                    </button>
                    <button type="button" class="button button-small" id="btn-collapse-all-cats">
                        <span class="dashicons dashicons-minus"></span> ${__('Colapsar Todas', 'aura-suite')}
                    </button>
                </div>
            </div>
            <table class="aura-report-table aura-table-hierarchical" id="aura-cat-hierarchical-table">
            <thead>
                <tr>
                    <th style="width:38px;"></th>
                    <th>${__('Categoría / Subcategoría', 'aura-suite')}</th>
                    <th>${__('Tipo', 'aura-suite')}</th>
                    <th>${__('Monto Total', 'aura-suite')}</th>
                    <th>${__('Movimientos', 'aura-suite')}</th>
                    <th>${__('% Sobre Padre', 'aura-suite')}</th>
                    <th>${__('% Sobre Total', 'aura-suite')}</th>
                    <th style="width:110px;text-align:center;">${__('Acción', 'aura-suite')}</th>
                </tr>
            </thead>
            <tbody>`;

        parents.forEach( ( p, pIdx ) => {
            const subCount = p.subcategories ? p.subcategories.length : 0;
            const hasSubs = subCount > 0;
            const isSingleDirect = subCount === 1 && p.subcategories[0].is_direct;

            html += `<tr class="aura-cat-parent-row ${hasSubs && !isSingleDirect ? 'has-children' : ''}" data-parent-id="${p.id}">
                <td style="text-align:center;">
                    ${hasSubs && !isSingleDirect ? `<button type="button" class="aura-tree-toggle-btn" title="${__('Mostrar/Ocultar subcategorías', 'aura-suite')}"><span class="dashicons dashicons-plus-alt2"></span></button>` : `<span class="aura-tree-dot"></span>`}
                </td>
                <td>
                    <span class="aura-cat-color-dot" style="background:${p.color};"></span>
                    <strong class="aura-cat-parent-name">${esc( p.name )}</strong>
                    ${hasSubs && !isSingleDirect ? `<span class="aura-subcat-count-badge">${sprintf(__('%d subcats', 'aura-suite'), subCount)}</span>` : `<span class="aura-subcat-count-badge aura-subcat-count-badge--direct">${__('Directo', 'aura-suite')}</span>`}
                </td>
                <td>${getTypeBadgeHtml( p.type )}</td>
                <td class="${getTypeAmountClass( p.type )}"><strong>${formatMoney( p.total )}</strong></td>
                <td><strong>${p.count}</strong></td>
                <td><span style="color:#64748b;font-size:11px;">100.0%</span></td>
                <td><span class="aura-pct-pill">${p.percentage}%</span></td>
                <td style="text-align:center;">
                    ${hasSubs && !isSingleDirect ? `<button type="button" class="button button-small aura-btn-drill-chart" data-parent-id="${p.id}" title="${__('Ver gráfico de subcategorías', 'aura-suite')}"><span class="dashicons dashicons-chart-pie"></span> ${__('Gráfico', 'aura-suite')}</button>` : '—'}
                </td>
            </tr>`;

            if ( hasSubs && !isSingleDirect ) {
                p.subcategories.forEach( ( sub ) => {
                    html += `<tr class="aura-cat-sub-row" data-parent-id="${p.id}" style="display:none;">
                        <td></td>
                        <td class="aura-subcat-cell">
                            <span class="aura-subcat-branch">└─</span>
                            <span class="aura-cat-color-dot" style="background:${sub.color};"></span>
                            <span class="aura-subcat-name">${esc( sub.name )}</span>
                        </td>
                        <td>${getTypeBadgeHtml( sub.type )}</td>
                        <td class="${getTypeAmountClass( sub.type )}">${formatMoney( sub.total )}</td>
                        <td>${sub.count}</td>
                        <td><span style="font-weight:600;color:#6366f1;">${sub.pct_parent}%</span></td>
                        <td>${sub.pct_grand}%</td>
                        <td style="text-align:center;">—</td>
                    </tr>`;
                } );
            }
        } );

        if ( ! parents.length ) {
            html += '<tr><td colspan="8" class="aura-no-data">' + __('Sin movimientos para el período seleccionado.', 'aura-suite') + '</td></tr>';
        } else {
            html += `<tr class="totals-row">
                <td></td>
                <td><strong>${__('TOTAL GENERAL', 'aura-suite')}</strong></td>
                <td>—</td>
                <td class="amount-expense"><strong>${formatMoney( grandTotal )}</strong></td>
                <td><strong>${parents.reduce( ( sum, p ) => sum + p.count, 0 )}</strong></td>
                <td>—</td>
                <td><strong>100.0%</strong></td>
                <td></td>
            </tr>`;
        }

        html += '</tbody></table>';
        $( '#report-table-wrap' ).html( html );

        // Eventos de la tabla jerárquica
        $( '#aura-cat-hierarchical-table' ).on( 'click', '.aura-tree-toggle-btn, .aura-cat-parent-row.has-children .aura-cat-parent-name', function ( e ) {
            e.stopPropagation();
            const $row = $( this ).closest( '.aura-cat-parent-row' );
            const parentId = $row.data( 'parent-id' );
            const $subRows = $( `.aura-cat-sub-row[data-parent-id="${parentId}"]` );
            const $btn = $row.find( '.aura-tree-toggle-btn span' );

            if ( $subRows.is( ':visible' ) ) {
                $subRows.hide();
                $btn.removeClass( 'dashicons-minus' ).addClass( 'dashicons-plus-alt2' );
                $row.removeClass( 'is-expanded' );
            } else {
                $subRows.show();
                $btn.removeClass( 'dashicons-plus-alt2' ).addClass( 'dashicons-minus' );
                $row.addClass( 'is-expanded' );
            }
        } );

        $( '#btn-expand-all-cats' ).on( 'click', function () {
            $( '.aura-cat-sub-row' ).show();
            $( '.aura-cat-parent-row.has-children' ).addClass( 'is-expanded' );
            $( '.aura-tree-toggle-btn span' ).removeClass( 'dashicons-plus-alt2' ).addClass( 'dashicons-minus' );
        } );

        $( '#btn-collapse-all-cats' ).on( 'click', function () {
            $( '.aura-cat-sub-row' ).hide();
            $( '.aura-cat-parent-row.has-children' ).removeClass( 'is-expanded' );
            $( '.aura-tree-toggle-btn span' ).removeClass( 'dashicons-minus' ).addClass( 'dashicons-plus-alt2' );
        } );

        $( '#aura-cat-hierarchical-table' ).on( 'click', '.aura-btn-drill-chart', function ( e ) {
            e.stopPropagation();
            const pId = parseInt( $( this ).data( 'parent-id' ), 10 );
            currentChartParentId = pId;
            drawCategoriesChart( pId );
            $( 'html, body' ).animate( {
                scrollTop: $( '#report-chart-wrap' ).offset().top - 100
            }, 300 );
        } );
    }

    // ─── D. Pendientes ────────────────────────────────────────────────────

    function renderPending( data ) {
        const kpisHtml = `
            <div class="aura-report-kpi pending">
                <div class="aura-report-kpi__label">${__('Transacciones pendientes', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${data.count}</div>
            </div>
            <div class="aura-report-kpi expense">
                <div class="aura-report-kpi__label">${__('Monto total pendiente', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_pending )}</div>
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();
        $( '#report-chart-wrap' ).hide();

        let html = `<table class="aura-report-table">
            <thead><tr><th>${__('Usuario', 'aura-suite')}</th><th>${__('Tipo', 'aura-suite')}</th><th>${__('Categoría', 'aura-suite')}</th><th>${__('Monto', 'aura-suite')}</th><th>${__('Descripción', 'aura-suite')}</th><th>${__('Fecha', 'aura-suite')}</th><th>${__('Días', 'aura-suite')}</th></tr></thead><tbody>`;

        let totalRendered = 0;
        for ( const [ user, txs ] of Object.entries( data.by_user ) ) {
            html += `<tr class="section-header"><td colspan="7">👤 ${esc( user )} — ${sprintf(__('%d pendiente(s)', 'aura-suite'), txs.length)}</td></tr>`;
            txs.forEach( r => {
                const ageCls = r.age_days > 14 ? 'amount-expense' : ( r.age_days > 7 ? 'amount-net negative' : '' );
                html += `<tr>
                    <td>${esc( user )}</td>
                    <td>${r.transaction_type === 'income' ? __('Ingreso', 'aura-suite') : __('Egreso', 'aura-suite')}</td>
                    <td>${esc( r.category_name )}</td>
                    <td class="${r.transaction_type === 'income' ? 'amount-income' : 'amount-expense'}">${formatMoney( r.amount )}</td>
                    <td>${esc( r.description || '—' )}</td>
                    <td>${r.transaction_date}</td>
                    <td class="${ageCls}">${sprintf(__('%d días', 'aura-suite'), r.age_days)}</td>
                </tr>`;
                totalRendered++;
            } );
        }
        if ( totalRendered === 0 ) html += '<tr><td colspan="7" class="aura-no-data">' + __('No hay transacciones pendientes. ✅', 'aura-suite') + '</td></tr>';
        html += `<tr class="totals-row"><td colspan="3"><strong>${__('TOTAL PENDIENTE', 'aura-suite')}</strong></td><td class="amount-expense"><strong>${formatMoney( data.total_pending )}</strong></td><td colspan="3"></td></tr>`;
        html += '</tbody></table>';
        $( '#report-table-wrap' ).html( html );
    }

    // ─── E. Presupuesto ───────────────────────────────────────────────────

    function renderBudget( data ) {
        const exec_pct = data.total_budget > 0
            ? Math.round( data.total_executed / data.total_budget * 100 )
            : 0;
        const kpisHtml = `
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Presupuesto Total', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_budget )}</div>
            </div>
            <div class="aura-report-kpi expense">
                <div class="aura-report-kpi__label">${__('Ejecutado Total', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_executed )}</div>
                <div class="aura-report-kpi__sub">${sprintf(__('%s%% del presupuesto', 'aura-suite'), exec_pct)}</div>
            </div>
            <div class="aura-report-kpi income">
                <div class="aura-report-kpi__label">${__('Disponible', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_budget - data.total_executed )}</div>
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();

        // Labels combinados: Área / Categoría
        const labels = data.budgets.map( r => {
            const area = r.area_name || __('Sin área', 'aura-suite');
            const cat = r.category_name || __('Sin categoría', 'aura-suite');
            return `${area} / ${cat}`;
        } );
        const budgets  = data.budgets.map( r => r.budget_amount );
        const executed = data.budgets.map( r => r.executed );

        state.reportChart = createBarChart( 'report-chart', labels, [
            { label: __('Presupuesto', 'aura-suite'), data: budgets,  backgroundColor: 'rgba(37,99,235,.6)' },
            { label: __('Ejecutado', 'aura-suite'),  data: executed, backgroundColor: data.budgets.map( r => r.overrun ? 'rgba(220,38,38,.7)' : 'rgba(22,163,74,.7)' ) },
        ] );
        $( '#report-chart-wrap' ).show();

        let html = `<table class="aura-report-table">
            <thead><tr><th>${__('Área/Programa', 'aura-suite')}</th><th>${__('Categoría', 'aura-suite')}</th><th>${__('Presupuesto', 'aura-suite')}</th><th>${__('Ejecutado', 'aura-suite')}</th><th>${__('% Ejecución', 'aura-suite')}</th><th>${__('Disponible', 'aura-suite')}</th><th>${__('Proyectado', 'aura-suite')}</th><th>${__('Estado', 'aura-suite')}</th></tr></thead><tbody>`;
        data.budgets.forEach( r => {
            const fillCls = r.pct > 100 ? 'over' : ( r.pct > 80 ? 'warn' : '' );
            const bar = `<div class="aura-progress-wrap">
                <div class="aura-progress-bar"><div class="aura-progress-bar__fill ${fillCls}" style="width:${Math.min( r.pct, 100 )}%"></div></div>
                <span class="aura-progress-pct">${r.pct}%</span>
            </div>`;
            
            // Badge del área con icono y color
            const areaIcon = r.area_icon || 'dashicons-building';
            const areaColor = r.area_color || '#6B7280';
            const areaBadge = `<span class="aura-area-badge" style="background-color:${areaColor}20;border-color:${areaColor}">
                <span class="dashicons ${areaIcon}" style="color:${areaColor}"></span>
                ${esc( r.area_name || __('Sin área', 'aura-suite') )}
            </span>`;
            
            html += `<tr>
                <td>${areaBadge}</td>
                <td>${esc( r.category_name || __('Sin categoría', 'aura-suite') )}</td>
                <td>${formatMoney( r.budget_amount )}</td>
                <td class="${r.overrun ? 'amount-expense' : 'amount-income'}">${formatMoney( r.executed )}</td>
                <td>${bar}</td>
                <td>${formatMoney( r.remaining )}</td>
                <td>${formatMoney( r.projected )}</td>
                <td>${r.overrun ? '<span class="aura-status-badge rejected">' + __('Sobregiro', 'aura-suite') + '</span>' : '<span class="aura-status-badge approved">' + __('OK', 'aura-suite') + '</span>'}</td>
            </tr>`;
        } );
        if ( ! data.budgets.length ) html += '<tr><td colspan="8" class="aura-no-data">' + __('No hay presupuestos activos configurados.', 'aura-suite') + '</td></tr>';
        html += '</tbody></table>';
        $( '#report-table-wrap' ).html( html );
    }
    // ─── E2. Detalle por Área ─────────────────────────────────────────────

    function renderBudgetAreaDetail( data ) {
        if ( data.error ) {
            showError( data.error );
            return;
        }

        const areaName  = data.area?.name || __('Área', 'aura-suite');
        const areaColor = data.area?.color || '#2271b1';
        const areaIcon  = data.area?.icon  || 'dashicons-building';
        const pctColor  = data.pct > 100 ? '#d63638' : ( data.pct >= 80 ? '#f97316' : '#00a32a' );

        // KPIs
        const kpisHtml = `
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Presupuesto Total Área', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_budget )}</div>
            </div>
            <div class="aura-report-kpi income">
                <div class="aura-report-kpi__label">${__('Total Ingresos', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_income )}</div>
            </div>
            <div class="aura-report-kpi expense">
                <div class="aura-report-kpi__label">${__('Total Egresos', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.total_expense )}</div>
                <div class="aura-report-kpi__sub" style="color:${pctColor}">${sprintf(__('%s%% ejecutado', 'aura-suite'), data.pct)}</div>
            </div>
            <div class="aura-report-kpi ${data.total_expense > data.total_budget && data.total_budget > 0 ? 'expense' : 'income'}">
                <div class="aura-report-kpi__label">${__('Disponible', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( data.available )}</div>
            </div>
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Transacciones', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${data.tx_count}</div>
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();

        // Gráfico: egresos por categoría vs presupuesto
        const cats      = data.by_category.filter( c => c.total_expense > 0 || c.budget > 0 );
        const labels    = cats.map( c => c.name );
        const budgets   = cats.map( c => c.budget );
        const expenses  = cats.map( c => c.total_expense );
        const incomes   = cats.map( c => c.total_income );

        state.reportChart = createBarChart( 'report-chart', labels, [
            { label: __('Presupuesto', 'aura-suite'), data: budgets,  backgroundColor: 'rgba(37,99,235,.45)' },
            { label: __('Ejecutado', 'aura-suite'),   data: expenses, backgroundColor: cats.map( c => c.overrun ? 'rgba(220,38,38,.7)' : 'rgba(22,163,74,.7)' ) },
            { label: __('Ingresos', 'aura-suite'),    data: incomes,  backgroundColor: 'rgba(16,185,129,.5)' },
        ] );
        $( '#report-chart-wrap' ).show();

        // Cabecera del área
        let html = `<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:10px 14px;
                        background:${areaColor}18;border-left:4px solid ${areaColor};border-radius:4px;">
            <span class="dashicons ${esc(areaIcon)}" style="color:${areaColor};font-size:22px;"></span>
            <strong style="font-size:16px;">${esc(areaName)}</strong>
        </div>`;

        // Una sección por categoría
        data.by_category.forEach( cat => {
            const pct      = cat.pct || 0;
            const fillCls  = pct > 100 ? 'over' : ( pct >= 80 ? 'warn' : '' );
            const budgetInfo = cat.budget > 0
                ? `<span style="margin-left:12px;font-size:12px;color:#6b7280;">
                       ${__('Presupuesto:', 'aura-suite')} ${formatMoney(cat.budget)} &nbsp;|&nbsp;
                       ${__('Ejecutado:', 'aura-suite')} ${formatMoney(cat.total_expense)} &nbsp;|&nbsp;
                       <span class="aura-progress-wrap" style="display:inline-flex;gap:6px;align-items:center;">
                           <span class="aura-progress-bar" style="width:80px;display:inline-block;">
                               <span class="aura-progress-bar__fill ${fillCls}" style="width:${Math.min(pct,100)}%"></span>
                           </span>
                           <b style="color:${pct>100?'#d63638':pct>=80?'#f97316':'#374151'}">${pct}%</b>
                       </span>
                   </span>`
                : '';

            html += `<div style="margin-bottom:18px;">
                <h3 style="margin:0 0 6px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${esc(cat.color)};flex-shrink:0;"></span>
                    ${esc(cat.name)}${budgetInfo}
                </h3>
                <table class="aura-report-table" style="margin:0;">
                    <thead><tr>
                        <th>${__('ID', 'aura-suite')}</th><th>${__('Fecha', 'aura-suite')}</th><th>${__('Tipo', 'aura-suite')}</th><th>${__('Monto', 'aura-suite')}</th>
                        <th>${__('Descripción', 'aura-suite')}</th><th>${__('Método', 'aura-suite')}</th><th>${__('Estado', 'aura-suite')}</th><th>${__('Registrado por', 'aura-suite')}</th>
                    </tr></thead><tbody>`;

            const PAYMENT_LABELS = { cash:__('Efectivo', 'aura-suite'), transfer:__('Transferencia', 'aura-suite'), check:__('Cheque', 'aura-suite'), card:__('Tarjeta', 'aura-suite'), other:__('Otro', 'aura-suite') };
            cat.transactions.forEach( tx => {
                const isIncome = tx.transaction_type === 'income';
                html += `<tr>
                    <td>${tx.id}</td>
                    <td>${tx.transaction_date}</td>
                    <td>${isIncome ? '<span class="aura-status-badge approved">' + __('Ingreso', 'aura-suite') + '</span>' : '<span class="aura-status-badge rejected">' + __('Egreso', 'aura-suite') + '</span>'}</td>
                    <td class="${isIncome?'amount-income':'amount-expense'}">${formatMoney(tx.amount)}</td>
                    <td>${esc(tx.description||'\u2014')}</td>
                    <td>${PAYMENT_LABELS[tx.payment_method]||tx.payment_method||'\u2014'}</td>
                    <td><span class="aura-status-badge ${tx.status}">${STATUS_LABELS[tx.status]||tx.status}</span></td>
                    <td>${esc(tx.created_by_name||'\u2014')}</td>
                </tr>`;
            } );

            const subtotalInc = cat.total_income > 0
                ? `<td class="amount-income">${formatMoney(cat.total_income)}</td><td colspan="5"></td>` : '';
            html += `<tr class="totals-row">
                <td colspan="3"><strong>${sprintf(__('Subtotal %s', 'aura-suite'), esc(cat.name))}</strong></td>
                <td class="amount-expense"><strong>${formatMoney(cat.total_expense)}</strong></td>
                <td colspan="4"></td>
            </tr></tbody></table></div>`;
        } );

        if ( ! data.by_category.length ) {
            html += '<p class="aura-no-data">' + __('No hay transacciones para esta área en el período seleccionado.', 'aura-suite') + '</p>';
        }

        $( '#report-table-wrap' ).html( html );
    }
    // ─── F. Auditoría ─────────────────────────────────────────────────────

    function renderAudit( data ) {
        const kpisHtml = `
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Total transacciones', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${data.count}</div>
                ${data.count >= 500 ? '<div class="aura-report-kpi__sub">' + __('Limitado a 500 registros', 'aura-suite') + '</div>' : ''}
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();
        $( '#report-chart-wrap' ).hide();

        let html = `<table class="aura-report-table">
            <thead><tr><th>${__('ID', 'aura-suite')}</th><th>${__('Fecha', 'aura-suite')}</th><th>${__('Tipo', 'aura-suite')}</th><th>${__('Categoría', 'aura-suite')}</th><th>${__('Monto', 'aura-suite')}</th><th>${__('Estado', 'aura-suite')}</th><th>${__('Creado por', 'aura-suite')}</th><th>${__('Aprobado por', 'aura-suite')}</th><th>${__('Comprobante', 'aura-suite')}</th></tr></thead><tbody>`;
        data.rows.forEach( r => {
            html += `<tr>
                <td>${r.id}</td>
                <td>${r.transaction_date}</td>
                <td>${r.transaction_type === 'income' ? 'Ingreso' : 'Egreso'}</td>
                <td>${esc( r.category_name )}</td>
                <td class="${r.transaction_type === 'income' ? 'amount-income' : 'amount-expense'}">${formatMoney( r.amount )}</td>
                <td><span class="aura-status-badge ${r.status}">${STATUS_LABELS[ r.status ] || r.status}</span></td>
                <td>${esc( r.creator_name || '—' )}</td>
                <td>${esc( r.approver_name || '—' )}</td>
                <td>${r.receipt_file ? '✅' : '—'}</td>
            </tr>`;
        } );
        if ( ! data.rows.length ) html += '<tr><td colspan="9" class="aura-no-data">' + __('Sin transacciones para el período y filtros seleccionados.', 'aura-suite') + '</td></tr>';
        html += '</tbody></table>';
        $( '#report-table-wrap' ).html( html );
    }

    // ─── G. Sueldos / Pagos a Usuarios ─────────────────────────────────────

    function renderUserPayments( data ) {
        const totals   = data.totals  || {};
        const summary  = data.summary || [];
        const rows     = data.rows    || [];

        const kpisHtml = `
            <div class="aura-report-kpi expense">
                <div class="aura-report-kpi__label">${__('Total Pagado', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( totals.paid || 0 )}</div>
            </div>
            <div class="aura-report-kpi income">
                <div class="aura-report-kpi__label">${__('Total Cobrado', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${formatMoney( totals.received || 0 )}</div>
            </div>
            <div class="aura-report-kpi balance">
                <div class="aura-report-kpi__label">${__('Transacciones', 'aura-suite')}</div>
                <div class="aura-report-kpi__value">${totals.count || 0}</div>
            </div>`;
        $( '#report-kpis' ).html( kpisHtml ).show();
        $( '#report-chart-wrap' ).hide();

        // Tabla resumen por usuario
        let html = '';
        if ( summary.length ) {
            html += `<table class="aura-report-table">
                <thead><tr><th>${__('Usuario', 'aura-suite')}</th><th>${__('Email', 'aura-suite')}</th><th>${__('Total Pagado', 'aura-suite')}</th><th>${__('Total Cobrado', 'aura-suite')}</th><th>${__('Movimientos', 'aura-suite')}</th></tr></thead><tbody>`;
            summary.forEach( s => {
                html += `<tr>
                    <td>${esc( s.user_name )}</td>
                    <td>${esc( s.user_email )}</td>
                    <td class="amount-expense">${formatMoney( s.total_paid )}</td>
                    <td class="amount-income">${formatMoney( s.total_received )}</td>
                    <td>${s.count}</td>
                </tr>`;
            } );
            html += '</tbody></table>';
            html += '<hr style="margin:1.2rem 0">';
        }

        // Tabla detalle
        html += `<table class="aura-report-table">
            <thead><tr><th>${__('ID', 'aura-suite')}</th><th>${__('Fecha', 'aura-suite')}</th><th>${__('Usuario', 'aura-suite')}</th><th>${__('Concepto', 'aura-suite')}</th><th>${__('Tipo', 'aura-suite')}</th><th>${__('Categoría', 'aura-suite')}</th><th>${__('Monto', 'aura-suite')}</th><th>${__('Estado', 'aura-suite')}</th><th>${__('Registrado por', 'aura-suite')}</th></tr></thead><tbody>`;
        rows.forEach( r => {
            const isExpense = r.transaction_type === 'expense';
            html += `<tr>
                <td>${r.id}</td>
                <td>${r.transaction_date}</td>
                <td>${esc( r.related_user_name || '—' )}</td>
                <td>${esc( r.concept_label || r.related_user_concept || '—' )}</td>
                <td>${isExpense ? __('Pago a usuario', 'aura-suite') : __('Cobro a usuario', 'aura-suite')}</td>
                <td>${esc( r.category_name )}</td>
                <td class="${isExpense ? 'amount-expense' : 'amount-income'}">${formatMoney( r.amount )}</td>
                <td><span class="aura-status-badge ${r.status}">${STATUS_LABELS[ r.status ] || r.status}</span></td>
                <td>${esc( r.creator_name || '—' )}</td>
            </tr>`;
        } );
        if ( ! rows.length ) html += '<tr><td colspan="9" class="aura-no-data">' + __('Sin transacciones para el período y filtros seleccionados.', 'aura-suite') + '</td></tr>';
        html += '</tbody></table>';
        $( '#report-table-wrap' ).html( html );
    }

    // ─── Exportación ──────────────────────────────────────────────────────

    function buildExportUrl( format ) {
        const params = new URLSearchParams( {
            action       : `aura_export_report_${format}`,
            nonce        : auraReports.exportNonce,
            report_type  : state.currentType,
            start        : state.currentParams?.start || '',
            end          : state.currentParams?.end   || '',
            status       : state.currentParams?.status || 'all',
            created_by   : state.currentParams?.created_by || 0,
            area_id      : state.currentParams?.area_id || 0,
        } );

        if ( state.currentParams?.transaction_types ) {
            const types = Array.isArray( state.currentParams.transaction_types )
                ? state.currentParams.transaction_types.join( ',' )
                : state.currentParams.transaction_types;
            params.set( 'transaction_types', types );
        }

        return `${auraReports.ajaxUrl}?${params.toString()}`;
    }

    function exportCSV() {
        if ( ! state.currentType ) return;
        window.location.href = buildExportUrl( 'csv' );
    }

    function exportExcel() {
        if ( ! state.currentType ) return;
        window.location.href = buildExportUrl( 'excel' );
    }

    function printReport() {
        window.print();
    }

    // ─── Configuraciones guardadas ────────────────────────────────────────

    function loadSavedConfigs() {
        $.ajax( {
            url    : auraReports.ajaxUrl,
            method : 'POST',
            data   : { action: 'aura_load_report_configs', nonce: auraReports.nonce },
            success: function ( res ) {
                if ( res.success ) renderConfigs( res.data.configs );
            },
        } );
    }

    function saveConfig() {
        const name = $( '#config-name-input' ).val().trim();
        if ( ! name ) { alert( __('Ingresa un nombre para la configuración.', 'aura-suite') ); return; }
        if ( ! state.currentType ) { alert( __('Genera un reporte primero.', 'aura-suite') ); return; }

        $.ajax( {
            url    : auraReports.ajaxUrl,
            method : 'POST',
            data   : {
                action            : 'aura_save_report_config',
                nonce             : auraReports.nonce,
                config_name       : name,
                report_type       : state.currentType,
                start             : state.currentParams?.start || '',
                end               : state.currentParams?.end   || '',
                status            : state.currentParams?.status || 'all',
                transaction_types : state.currentParams?.transaction_types || [ 'income', 'expense', 'capital' ],
            },
            success: function ( res ) {
                if ( res.success ) {
                    renderConfigs( res.data.configs );
                    $( '#config-name-input' ).val( '' );
                    showInlineNotice( __('✓ Configuración guardada.', 'aura-suite'), 'success' );
                }
            },
        } );
    }

    function deleteConfig( key ) {
        if ( ! confirm( __('¿Eliminar esta configuración?', 'aura-suite') ) ) return;
        $.ajax( {
            url    : auraReports.ajaxUrl,
            method : 'POST',
            data   : { action: 'aura_delete_report_config', nonce: auraReports.nonce, config_key: key },
            success: loadSavedConfigs,
        } );
    }

    function applyConfig( config ) {
        $( '#report_type' ).val( config.report_type ).trigger( 'change' );
        if ( config.start ) $( '#report_start' ).val( config.start );
        if ( config.end )   $( '#report_end' ).val( config.end );
        if ( config.status ) $( '#report_status' ).val( config.status );
        if ( config.transaction_types && Array.isArray( config.transaction_types ) ) {
            $( 'input[name="transaction_types[]"]' ).each( function () {
                const isChecked = config.transaction_types.includes( $( this ).val() );
                $( this ).prop( 'checked', isChecked );
                $( this ).closest( '.aura-chip-checkbox' ).toggleClass( 'active', isChecked );
            } );
        }
        generateReport();
    }

    function renderConfigs( configs ) {
        const $wrap = $( '#aura-saved-configs' );
        if ( ! configs.length ) {
            $wrap.html( '<p class="aura-empty-msg">' + __('No hay configuraciones guardadas.', 'aura-suite') + '</p>' );
            return;
        }
        let html = '';
        configs.forEach( c => {
            html += `<div class="aura-config-item">
                <span class="aura-config-item__name" data-config='${JSON.stringify( c.config )}'>${esc( c.name )}</span>
                <span class="aura-config-item__type">${REPORT_LABELS[ c.config.report_type ] || c.config.report_type}</span>
                <button class="aura-config-item__del" data-key="${c.key}">✕</button>
            </div>`;
        } );
        $wrap.html( html );

        $wrap.find( '.aura-config-item__name' ).on( 'click', function () {
            applyConfig( JSON.parse( $( this ).attr( 'data-config' ) ) );
        } );
        $wrap.find( '.aura-config-item__del' ).on( 'click', function () {
            deleteConfig( $( this ).data( 'key' ) );
        } );
    }

    // ─── Helpers UI ───────────────────────────────────────────────────────

    function showLoader() {
        $( '#aura-report-empty' ).hide();
        $( '#aura-report-content' ).hide();
        $( '#aura-report-loader' ).show();
    }
    function showContent() {
        $( '#aura-report-loader' ).hide();
        $( '#aura-report-empty' ).hide();
        $( '#aura-report-content' ).show();
    }
    function showError( msg ) {
        $( '#aura-report-loader' ).hide();
        $( '#aura-report-content' ).hide();
        $( '#aura-report-empty' ).html( `<div class="aura-reports-notice error"><span class="dashicons dashicons-warning"></span>${esc( msg )}</div>` ).show();
    }
    function showInlineNotice( msg, type = 'success' ) {
        const $n = $( `<div class="aura-reports-notice ${type}">${msg}</div>` );
        $( '#aura-report-form' ).prepend( $n );
        setTimeout( () => $n.fadeOut( 400, () => $n.remove() ), 3000 );
    }

    function getFormData() {
        const types = [];
        $( 'input[name="transaction_types[]"]:checked' ).each( function () {
            types.push( $( this ).val() );
        } );

        return {
            report_type       : $( '#report_type' ).val(),
            start             : $( '#report_start' ).val(),
            end               : $( '#report_end' ).val(),
            status            : $( '#report_status' ).val(),
            created_by        : $( '#report_creator' ).val() || 0,
            area_id           : $( '#report_area' ).val() || 0,
            transaction_types : types.length ? types : [ 'income', 'expense', 'capital' ],
        };
    }

    // ─── Helpers gráficos ─────────────────────────────────────────────────

    function createBarChart( canvasId, labels, datasets ) {
        const ctx = document.getElementById( canvasId ).getContext( '2d' );
        return new Chart( ctx, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive        : true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { ticks: { callback: v => '$' + Number( v ).toLocaleString() } },
                },
            },
        } );
    }

    // ─── Helpers formato ───────────────────────────────────────────────────

    function formatMoney( amount ) {
        const n = parseFloat( amount ) || 0;
        return '$' + n.toLocaleString( 'en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
    }

    function yearChangeHtml( pct ) {
        if ( pct === undefined || pct === null ) return '';
        const cls = pct >= 0 ? 'up' : 'down';
        const arrow = pct >= 0 ? '▲' : '▼';
        return `<span class="aura-report-kpi__change ${cls}">${arrow} ${sprintf(__('%s%% vs año ant.', 'aura-suite'), Math.abs( pct ))}</span>`;
    }

    function esc( str ) {
        if ( ! str ) return '';
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

} )( jQuery );
