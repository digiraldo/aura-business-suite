/**
 * Aura Business Suite — Manejador de Modo Oscuro / Claro en Frontend
 * Soporta persistencia en localStorage ('aura_frontend_theme') y sincronización dinámica
 *
 * @package AuraBusinessSuite
 */

(function () {
    'use strict';

    var STORAGE_KEY = 'aura_frontend_theme';

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
     * Aplicar tema al DOM
     */
    function applyTheme(theme) {
        var isDark = theme === 'dark';
        var root = document.documentElement;
        var body = document.body;

        if (root) {
            root.setAttribute('data-theme', theme);
            if (isDark) {
                root.classList.add('aura-dark-theme');
            } else {
                root.classList.remove('aura-dark-theme');
            }
        }

        if (body) {
            body.setAttribute('data-theme', theme);
            if (isDark) {
                body.classList.add('aura-dark-theme');
            } else {
                body.classList.remove('aura-dark-theme');
            }
        }

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
                btn.setAttribute('title', 'Cambiar a modo claro');
                btn.setAttribute('aria-label', 'Cambiar a modo claro');
                if (icon) {
                    icon.className = 'dashicons dashicons-lightbulb';
                    icon.innerHTML = '☀️';
                }
                if (label) {
                    label.textContent = 'Modo claro';
                }
            } else {
                btn.setAttribute('title', 'Cambiar a modo oscuro');
                btn.setAttribute('aria-label', 'Cambiar a modo oscuro');
                if (icon) {
                    icon.className = 'dashicons dashicons-moon';
                    icon.innerHTML = '🌙';
                }
                if (label) {
                    label.textContent = 'Modo oscuro';
                }
            }
        });
    }

    /**
     * Alternar entre tema claro y oscuro
     */
    function toggleTheme() {
        var current = (document.documentElement.getAttribute('data-theme') === 'dark') ? 'dark' : 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        return next;
    }

    // Exponer API global
    window.AuraTheme = {
        getTheme: function () {
            return document.documentElement.getAttribute('data-theme') || getStoredTheme();
        },
        setTheme: applyTheme,
        toggle: toggleTheme
    };
    window.AuraToggleTheme = toggleTheme;

    // Ejecución inmediata para evitar FOUC
    var initialTheme = getStoredTheme();
    if (document.documentElement) {
        document.documentElement.setAttribute('data-theme', initialTheme);
        if (initialTheme === 'dark') {
            document.documentElement.classList.add('aura-dark-theme');
        }
    }

    // Al cargar el DOM, vincular botones existentes
    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(initialTheme);

        document.addEventListener('click', function (e) {
            var toggleBtn = e.target.closest('.aura-theme-toggle');
            if (toggleBtn) {
                e.preventDefault();
                toggleTheme();
            }
        });
    });
})();
