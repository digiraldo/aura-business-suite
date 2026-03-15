/**
 * Notifications — Aura Business Suite
 * Fase 5, Item 5.4
 */
/* global auraNotifConfig, jQuery */
(function ($) {
    'use strict';

    const cfg = auraNotifConfig;
    let currentPage = 1;

    /* ===========================================================
       Init
       =========================================================== */
    $(function () {
        loadNotifications();
        bindEvents();
    });

    /* ===========================================================
       Cargar lista paginada
       =========================================================== */
    function loadNotifications(page) {
        page = page || 1;
        currentPage = page;
        showState('loading');

        $.post(cfg.ajaxUrl, {
            action:      'aura_get_all_notifications',
            nonce:       cfg.nonce,
            page:        page,
            filter_type: $('#f-notif-type').val(),
            filter_read: $('#f-notif-read').val(),
        }).done(function (res) {
            if (!res.success || !res.data.items.length) {
                showState('empty');
                updateBadge(res.data ? res.data.unread : 0);
                return;
            }
            renderList(res.data.items);
            renderPagination(res.data.page, res.data.pages);
            updateBadge(res.data.unread);
            showState('results');
        }).fail(function () {
            showState('empty');
        });
    }

    /* ===========================================================
       Renderizar lista
       =========================================================== */
    function renderList(items) {
        const $list = $('#notif-list').empty();
        items.forEach(function (n) {
            const color   = cfg.typeColors[n.type] || '#888';
            const readCls = n.is_read === '1' || n.is_read === 1 ? 'is-read' : 'is-unread';
            const linkBtn = n.link
                ? `<a href="${escHtml(n.link)}" class="button button-small" target="_blank">${cfg.i18n.view}</a>`
                : '';

            const $li = $(`<li class="aura-notif-item ${readCls}" data-id="${n.id}">
                <span class="aura-notif-dot" style="background:${color};"></span>
                <div class="aura-notif-body">
                    <strong class="aura-notif-title">${escHtml(n.title)}</strong>
                    <p class="aura-notif-msg">${escHtml(n.message)}</p>
                    <span class="aura-notif-meta">
                        <span class="aura-notif-type-badge" style="background:${color}20;color:${color};">${escHtml(n.type_label || n.type)}</span>
                        <span class="aura-notif-time">${escHtml(n.time_ago || n.created_at)}</span>
                    </span>
                </div>
                <div class="aura-notif-item-actions">
                    ${linkBtn}
                    <button class="button button-small btn-toggle-read"
                            title="${n.is_read ? cfg.i18n.markUnread : cfg.i18n.markRead}">
                        <span class="dashicons ${n.is_read ? 'dashicons-hidden' : 'dashicons-yes'}"></span>
                    </button>
                    <button class="button button-small btn-delete-notif" title="${cfg.i18n.delete}">
                        <span class="dashicons dashicons-trash" style="color:#c00;"></span>
                    </button>
                </div>
            </li>`);

            $list.append($li);
        });
    }

    /* ===========================================================
       Paginación
       =========================================================== */
    function renderPagination(page, pages) {
        const $pag = $('#notif-pagination');
        if (pages <= 1) { $pag.hide(); return; }

        const start = Math.max(1, page - 3);
        const end   = Math.min(pages, page + 3);
        let html    = cfg.i18n.page + ' ';

        if (start > 1) html += `<button class="page-btn" data-page="1">1</button>…`;
        for (let i = start; i <= end; i++) {
            html += `<button class="page-btn${i === page ? ' active' : ''}" data-page="${i}">${i}</button>`;
        }
        if (end < pages) html += `…<button class="page-btn" data-page="${pages}">${pages}</button>`;
        html += ` ${cfg.i18n.of} ${pages}`;
        $pag.html(html).show();
    }

    /* ===========================================================
       Eventos
       =========================================================== */
    function bindEvents() {
        /* Filtros */
        $('#f-notif-type, #f-notif-read').on('change', function () { loadNotifications(1); });

        /* Marcar todo como leído */
        $('#btn-mark-all-read').on('click', function () {
            $.post(cfg.ajaxUrl, {
                action: 'aura_mark_all_notifications_read',
                nonce:  cfg.nonce,
            }).done(function (res) {
                if (res.success) {
                    updateBadge(0);
                    loadNotifications(currentPage);
                }
            });
        });

        /* Paginación */
        $(document).on('click', '.page-btn', function () {
            const p = parseInt($(this).data('page'));
            if (p !== currentPage) loadNotifications(p);
        });

        /* Toggle leída */
        $(document).on('click', '.btn-toggle-read', function () {
            const $item = $(this).closest('.aura-notif-item');
            const id    = $item.data('id');
            const isRead= $item.hasClass('is-read');

            if (isRead) {
                // Re-marcar como no leída (client side only — marcamos en DB como no leída vía delete + re-insert no disponible)
                // Simplificamos: solo soportamos marcar como leída desde este botón
                return;
            }

            $.post(cfg.ajaxUrl, {
                action: 'aura_mark_notification_read',
                nonce:  cfg.nonce,
                id:     id,
            }).done(function (res) {
                if (res.success) {
                    $item.removeClass('is-unread').addClass('is-read');
                    $item.find('.btn-toggle-read .dashicons')
                         .removeClass('dashicons-yes').addClass('dashicons-hidden');
                    updateBadge(res.data.unread);
                }
            });
        });

        /* Eliminar notificación */
        $(document).on('click', '.btn-delete-notif', function () {
            if (!confirm(cfg.i18n.confirmDelete)) return;
            const $item = $(this).closest('.aura-notif-item');
            const id    = $item.data('id');

            $.post(cfg.ajaxUrl, {
                action: 'aura_delete_notification',
                nonce:  cfg.nonce,
                id:     id,
            }).done(function (res) {
                if (res.success) {
                    $item.fadeOut(200, function () { $(this).remove(); });
                    updateBadge(res.data.unread);
                }
            });
        });

        /* Guardar preferencias */
        $('#form-notif-prefs').on('submit', function (e) {
            e.preventDefault();
            const data = { action: 'aura_save_notification_prefs', nonce: cfg.nonce };
            $(this).serializeArray().forEach(function (f) { data[f.name] = f.value; });

            // checkboxes no incluidos en serializeArray cuando no marcados
            ['email_transaction_approval','email_transaction_result','email_budget_alert',
             'email_reminders','email_system','no_disturb_weekend','no_disturb_hours'].forEach(function (k) {
                if (!(k in data)) data[k] = '';
            });

            $.post(cfg.ajaxUrl, data).done(function (res) {
                const $msg = $('#notif-prefs-msg');
                if (res.success) {
                    $msg.html('<span style="color:#1e7e34;">✓ ' + cfg.i18n.prefsSaved + '</span>').show();
                    setTimeout(() => $msg.fadeOut(), 3000);
                }
            });
        });
    }

    /* ===========================================================
       Estado del panel
       =========================================================== */
    function showState(state) {
        $('#notif-loading, #notif-empty, #notif-list, #notif-pagination').hide();
        if (state === 'loading') $('#notif-loading').show();
        if (state === 'empty')   $('#notif-empty').show();
        if (state === 'results') $('#notif-list, #notif-pagination').show();
    }

    /* ===========================================================
       Admin bar bell badge update
       =========================================================== */
    function updateBadge(count) {
        const $badge = $('.aura-bell-badge');
        if (!count) {
            $badge.remove();
            return;
        }
        const text = count > 99 ? '99+' : count;
        if ($badge.length) {
            $badge.text(text);
        } else {
            $('#wp-admin-bar-aura-notifications-bell .ab-item').append(
                '<span class="aura-bell-badge">' + text + '</span>'
            );
        }
    }

    /* ===========================================================
       Utils
       =========================================================== */
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);
