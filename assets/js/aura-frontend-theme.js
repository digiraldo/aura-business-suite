/**
 * Aura Business Suite — Manejador de Modo Oscuro / Claro en Frontend
 * Soporta persistencia en localStorage ('aura_frontend_theme') y sincronización dinámica
 *
 * @package AuraBusinessSuite
 */

(function () {
    'use strict';

    function __(s, domain) { return (window.wp && wp.i18n && typeof wp.i18n.__ === 'function') ? wp.i18n.__(s, domain || 'aura-suite') : s; }

    var STORAGE_KEY = 'aura_frontend_theme';
    var AURA_CONTAINER_SELECTOR = [
        '.aura-portal-wrap',
        '.aura-login-wrap',
        '.aura-student-schedule-wrap',
        '.aura-enroll-wrap',
        '.aura-teacher-portal',
        '.aura-calendar-wrapper',
        '.aura-my-certs-wrap',
        '.aura-cert-verify-card',
        '.aura-cert-verify-search',
        '.aura-dv-public-wrap',
        '.aura-dv-card',
        '.aura-paz-salvo-check',
        '.aura-form-portal',
        '.aura-form-wrap',
        '.aura-modal-overlay',
        '#modal-student-event-detail',
        '#aura-cal-event-tooltip'
    ].join(', ');

    /**
     * Obtener tema actual guardado o preferencia del sistema
     */
    function getStoredTheme() {
        var saved = null;
        try {
            saved = localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            saved = null;
        }

        if (saved === 'dark' || saved === 'light') {
            return saved;
        }

        // Por defecto en frontend usamos modo claro salvo que el sistema prefiera dark
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        return 'light';
    }

    /**
     * Aplicar tema exclusivamente a los contenedores de Aura (sin tocar data-theme global en html/body
     * para no alterar encabezados, barra de navegación, carrito ni footer del sitio).
     */
    function applyTheme(theme) {
        var isDark = theme === 'dark';
        var root = document.documentElement;
        var body = document.body;

        if (root) {
            root.setAttribute('data-aura-theme', theme);
            if (isDark) {
                root.classList.add('aura-dark-theme');
            } else {
                root.classList.remove('aura-dark-theme');
            }
        }

        if (body) {
            body.setAttribute('data-aura-theme', theme);
            if (isDark) {
                body.classList.add('aura-dark-theme');
            } else {
                body.classList.remove('aura-dark-theme');
            }
            if (document.querySelector('.aura-theme-toggle')) {
                body.classList.add('aura-has-theme-toggle');
            }
        }

        try {
            document.querySelectorAll(AURA_CONTAINER_SELECTOR).forEach(function (el) {
                el.setAttribute('data-aura-theme', theme);
                el.setAttribute('data-theme', theme);
                if (isDark) {
                    el.classList.add('aura-dark-theme');
                } else {
                    el.classList.remove('aura-dark-theme');
                }
            });
        } catch (e) {}

        // Guardar preferencia
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {}

        // Actualizar todos los botones toggle presentes en la página
        updateToggleButtons(theme);

        // Disparar evento para componentes interactivos (ej: FullCalendar)
        try {
            window.dispatchEvent(new CustomEvent('aura:themeChanged', { detail: { theme: theme, isDark: isDark } }));
            window.dispatchEvent(new Event('resize'));
        } catch (e) {}
    }

    /**
     * Actualizar estado visual de los botones de toggle
     */
    function updateToggleButtons(theme) {
        var isDark = theme === 'dark';
        var buttons = document.querySelectorAll('.aura-theme-toggle');

        buttons.forEach(function (btn) {
            var icon = btn.querySelector('.dashicons');
            var label = btn.querySelector('.aura-theme-toggle-label');

            if (isDark) {
                btn.setAttribute('title', __('Cambiar a modo claro', 'aura-suite'));
                btn.setAttribute('aria-label', __('Cambiar a modo claro', 'aura-suite'));
                if (icon) {
                    icon.className = 'dashicons dashicons-lightbulb';
                    icon.textContent = ''; // Vacío para evitar doble icono con el pseudo-elemento :before
                }
                if (label) {
                    label.textContent = __('Modo claro', 'aura-suite');
                }
            } else {
                btn.setAttribute('title', __('Cambiar a modo oscuro', 'aura-suite'));
                btn.setAttribute('aria-label', __('Cambiar a modo oscuro', 'aura-suite'));
                if (icon) {
                    icon.className = 'dashicons dashicons-moon';
                    icon.textContent = ''; // Vacío para evitar doble icono con el pseudo-elemento :before
                }
                if (label) {
                    label.textContent = __('Modo oscuro', 'aura-suite');
                }
            }
        });
    }

    /**
     * Alternar entre tema claro y oscuro
     */
    function toggleTheme() {
        var current = (document.documentElement.getAttribute('data-aura-theme') === 'dark' || document.documentElement.classList.contains('aura-dark-theme')) ? 'dark' : 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        return next;
    }

    // Exponer API global
    window.AuraTheme = {
        getTheme: function () {
            return document.documentElement.getAttribute('data-aura-theme') || getStoredTheme();
        },
        setTheme: applyTheme,
        toggle: toggleTheme
    };
    window.AuraToggleTheme = toggleTheme;

    // Ejecución inmediata para evitar FOUC usando solo namespace exclusivo de Aura
    var initialTheme = getStoredTheme();
    if (document.documentElement) {
        document.documentElement.setAttribute('data-aura-theme', initialTheme);
        if (initialTheme === 'dark') {
            document.documentElement.classList.add('aura-dark-theme');
        }
    }

    // Vincular clic delegado inmediatamente (funciona aunque el script cargue antes o después de DOMContentLoaded)
    if (!window.__auraThemeClickBound) {
        window.__auraThemeClickBound = true;
        document.addEventListener('click', function (e) {
            var target = e.target;
            if (!target) {
                return;
            }
            var el = target.nodeType === 1 ? target : target.parentElement;
            var toggleBtn = el && el.closest ? el.closest('.aura-theme-toggle') : null;
            if (toggleBtn) {
                e.preventDefault();
                toggleTheme();
            }
        });
    }

    function syncThemeOnReady() {
        applyTheme(getStoredTheme());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncThemeOnReady);
    } else {
        syncThemeOnReady();
    }
})();
