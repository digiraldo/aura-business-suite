/**
 * AURA Business Suite — Dashboard Principal
 * Animaciones, accesos rápidos interactivos y auto-refresh de stats.
 *
 * @package AuraBusinessSuite
 * @version 2.0.0
 */

(function ($) {
    'use strict';

    /* ── Config ────────────────────────────────────────────────── */
    const REFRESH_INTERVAL = 120000; // 2 minutos
    const STORAGE_KEY      = 'aura_dashboard_dismissed';

    /* ── Init ──────────────────────────────────────────────────── */
    $(document).ready(function () {
        AuraDashboard.init();
    });

    /* ── Módulo principal ───────────────────────────────────────── */
    const AuraDashboard = {

        init: function () {
            this.animateOnScroll();
            this.setupModuleHovers();
            this.setupAutoRefresh();
            this.animateProgressBars();
            this.animateCounters();
        },

        /* Intersection Observer — animar cards al hacer scroll */
        animateOnScroll: function () {
            if (!('IntersectionObserver' in window)) return;

            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity  = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.08 });

            document.querySelectorAll('.adp-module-card, .adp-panel, .adp-stat-card')
                .forEach(function (el) {
                    observer.observe(el);
                });
        },

        /* Efectos hover en los módulos */
        setupModuleHovers: function () {
            $('.adp-module-card:not(.adp-module-card--coming-soon)').each(function () {
                $(this).on('mouseenter', function () {
                    $(this).find('.adp-module-card__icon').css('transform', 'scale(1.18) rotate(-4deg)');
                }).on('mouseleave', function () {
                    $(this).find('.adp-module-card__icon').css('transform', '');
                });
            });

            // Añadir transición al ícono vía CSS inline
            $('.adp-module-card__icon').css('transition', 'transform .25s ease');
        },

        /* Animar progress bars con la anchura real */
        animateProgressBars: function () {
            $('.adp-progress__fill').each(function () {
                const target = $(this).css('width');
                $(this).css('width', '0').animate({ width: target }, 800);
            });
        },

        /* Animar números de las stat-cards (contador) */
        animateCounters: function () {
            $('.adp-stat-card__value').each(function () {
                const $el   = $(this);
                const raw   = $el.text().trim();
                // Solo animar si parece un número entero o con formato $
                const clean = raw.replace(/[^0-9]/g, '');
                if (!clean || clean.length > 9) return;

                const end    = parseInt(clean, 10);
                const prefix = raw.replace(/[0-9.,]+/, '').split('').shift() || '';
                const isNeg  = raw.includes('-');
                let   count  = 0;
                const step   = Math.max(1, Math.ceil(end / 30));

                $el.text((isNeg ? '-' : '') + prefix + '0');

                const timer = setInterval(function () {
                    count = Math.min(count + step, end);
                    $el.text((isNeg ? '-' : '') + prefix + count.toLocaleString('es'));
                    if (count >= end) clearInterval(timer);
                }, 30);
            });
        },

        /* Auto-refresh de stats via AJAX */
        setupAutoRefresh: function () {
            if (typeof auraVars === 'undefined') return;

            const self = this;
            setInterval(function () { self.refreshStats(); }, REFRESH_INTERVAL);
        },

        refreshStats: function () {
            if (typeof auraVars === 'undefined') return;

            $.ajax({
                url:  ajaxurl,
                type: 'POST',
                data: {
                    action: 'aura_dashboard_refresh_stats',
                    nonce:  auraVars.nonce
                },
                success: function (resp) {
                    if (!resp.success) return;
                    const d = resp.data;

                    // Notificaciones
                    if (typeof d.notifications !== 'undefined') {
                        const $notif = $('.adp-stat-card--alert .adp-stat-card__value, ' +
                                        '.adp-stat-card .adp-stat-card__value').filter(function () {
                            return $(this).closest('.adp-stat-card').find('.adp-stat-card__label').text()
                                         .indexOf('Notif') !== -1;
                        });
                        $notif.text(d.notifications);
                    }

                    // Pendientes de aprobación
                    if (typeof d.pending_approvals !== 'undefined') {
                        $('.adp-mini-stat').filter(function () {
                            return $(this).find('.adp-mini-stat__label').text().indexOf('Pendiente') !== -1;
                        }).find('.adp-mini-stat__value').text(d.pending_approvals);
                    }

                    // Ingresos / Egresos del mes
                    if (d.finance) {
                        const $incEl = $('.adp-module-card--finance .adp-mini-stat:eq(0) .adp-mini-stat__value');
                        const $expEl = $('.adp-module-card--finance .adp-mini-stat:eq(1) .adp-mini-stat__value');
                        const $penEl = $('.adp-module-card--finance .adp-mini-stat:eq(2) .adp-mini-stat__value');

                        if (d.finance.income  !== undefined) $incEl.text('$' + Number(d.finance.income).toLocaleString('es'));
                        if (d.finance.expense !== undefined) $expEl.text('$' + Number(d.finance.expense).toLocaleString('es'));
                        if (d.finance.pending !== undefined) $penEl.text(d.finance.pending);

                        // Progress bar
                        if (d.finance.budget_exec !== undefined) {
                            const pct = Math.min(100, d.finance.budget_exec);
                            const color = pct > 90 ? '#ef4444' : (pct > 70 ? '#f59e0b' : '#10b981');
                            $('.adp-module-card--finance .adp-progress__fill')
                                .css({ width: pct + '%', background: color });
                            $('.adp-module-card--finance .adp-progress__label strong').text(pct + '%');
                        }
                    }

                    // Vehículos
                    if (d.vehicles) {
                        if (d.vehicles.today !== undefined) {
                            $('.adp-module-card--vehicles .adp-mini-stat:eq(1) .adp-mini-stat__value')
                                .text(d.vehicles.today);
                        }
                        if (d.vehicles.critical !== undefined) {
                            $('.adp-module-card--vehicles .adp-mini-stat:eq(2) .adp-mini-stat__value')
                                .text(d.vehicles.critical);
                        }
                    }
                }
            });
        }
    };

})(jQuery);
