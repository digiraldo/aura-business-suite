/**
 * Tags Management — Aura Business Suite
 * Fase 5, Item 5.2
 */
/* global auraTagsConfig, jQuery */
(function ($) {
    'use strict';

    /* ============================================================
       Estado global
       ============================================================ */
    let allTags = [];       // [{name, count}]
    let pendingDeleteTag = null;

    /* ============================================================
       Init
       ============================================================ */
    $(function () {
        loadAllTags();
        loadCloud();
        bindEvents();
        initAutocomplete();
    });

    /* ============================================================
       Cargar tabla de tags
       ============================================================ */
    function loadAllTags() {
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_get_all',
            nonce:  auraTagsConfig.nonce,
        }).done(function (res) {
            if (res.success) {
                allTags = res.data.tags;
                renderTable(allTags);
            } else {
                renderTableError(res.data && res.data.message);
            }
        }).fail(function () {
            renderTableError('Error de conexión');
        });
    }

    /* ============================================================
       Renderizar tabla
       ============================================================ */
    function renderTable(tags) {
        const $body = $('#tags-table-body');

        if (!tags || tags.length === 0) {
            $body.html('<tr><td colspan="3" style="text-align:center;">' +
                auraTagsConfig.i18n.noResults + '</td></tr>');
            return;
        }

        const rows = tags.map(function (tag) {
            const escaped = escHtml(tag.name);
            return `<tr data-tag="${escaped}">
                <td>
                    <span class="aura-tag-chip" data-tag="${escaped}">${escaped}</span>
                </td>
                <td style="text-align:center;">
                    <strong>${tag.count}</strong>
                </td>
                <td style="text-align:right;">
                    <button class="button button-small btn-rename-quick" data-tag="${escaped}">✏️ Renombrar</button>
                    <button class="button button-small btn-delete-tag" data-tag="${escaped}"
                            style="color:#c00;border-color:#c00;margin-left:4px;">
                        🗑️ Eliminar
                    </button>
                </td>
            </tr>`;
        });

        $body.html(rows.join(''));
    }

    function renderTableError(msg) {
        $('#tags-table-body').html(
            `<tr><td colspan="3" style="color:#c00;text-align:center;">${escHtml(msg || 'Error')}</td></tr>`
        );
    }

    /* ============================================================
       Nube de tags
       ============================================================ */
    function loadCloud() {
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_cloud',
            nonce:  auraTagsConfig.nonce,
        }).done(function (res) {
            const $cloud = $('#aura-tag-cloud');
            if (!res.success || !res.data.tags.length) {
                $cloud.html('<em>No hay etiquetas todavía.</em>');
                return;
            }
            const chips = res.data.tags.map(function (t) {
                return `<a class="aura-cloud-tag" href="#" style="font-size:${t.size}px;" data-tag="${escHtml(t.name)}" title="${t.count} usos">${escHtml(t.name)}</a>`;
            });
            $cloud.html(chips.join(''));
        });
    }

    /* ============================================================
       Autocomplete en inputs de operaciones
       ============================================================ */
    function initAutocomplete() {
        const tagInputs = ['#rename-old', '#merge-source', '#merge-target'];
        tagInputs.forEach(function (sel) {
            $(sel).autocomplete({
                source: function (req, response) {
                    $.post(auraTagsConfig.ajaxUrl, {
                        action: 'aura_tags_autocomplete',
                        nonce:  auraTagsConfig.nonce,
                        term:   req.term,
                    }).done(function (res) {
                        response(res.success ? res.data : []);
                    });
                },
                minLength: 1,
            });
        });
    }

    /* ============================================================
       Eventos
       ============================================================ */
    function bindEvents() {

        /* Renombrar desde botón principal */
        $('#btn-rename-tag').on('click', function () {
            const oldName = $('#rename-old').val().trim();
            const newName = $('#rename-new').val().trim();
            if (!oldName || !newName) return showNotice('Completa ambos campos.', 'error');
            doRename(oldName, newName);
        });

        /* Renombrar rápido desde tabla */
        $(document).on('click', '.btn-rename-quick', function () {
            const tag = $(this).data('tag');
            $('#rename-old').val(tag);
            $('#rename-new').val('').focus();
            $('html, body').animate({ scrollTop: 0 }, 300);
        });

        /* Fusionar */
        $('#btn-merge-tags').on('click', function () {
            const source = $('#merge-source').val().trim();
            const target = $('#merge-target').val().trim();
            if (!source || !target) return showNotice('Completa ambos campos.', 'error');
            if (!confirm(auraTagsConfig.i18n.confirmMerge)) return;
            doMerge(source, target);
        });

        /* Eliminar → abrir modal */
        $(document).on('click', '.btn-delete-tag', function () {
            pendingDeleteTag = $(this).data('tag');
            $('#delete-tag-name').text(pendingDeleteTag);
            $('#aura-delete-tag-modal').show();
        });

        $('#confirm-delete-tag').on('click', function () {
            if (!pendingDeleteTag) return;
            doDelete(pendingDeleteTag);
            $('#aura-delete-tag-modal').hide();
        });

        $('#cancel-delete-tag').on('click', function () {
            pendingDeleteTag = null;
            $('#aura-delete-tag-modal').hide();
        });

        /* Clic en chip de nube / tabla → prefill rename-old */
        $(document).on('click', '.aura-cloud-tag, .aura-tag-chip', function (e) {
            e.preventDefault();
            $('#rename-old').val($(this).data('tag'));
            $('html, body').animate({ scrollTop: 0 }, 200);
        });

        /* Filtro en tabla */
        $('#tags-table-filter').on('input', function () {
            const q = $(this).val().toLowerCase();
            const filtered = allTags.filter(t => t.name.includes(q));
            renderTable(filtered);
        });
    }

    /* ============================================================
       AJAX: Renombrar
       ============================================================ */
    function doRename(oldName, newName) {
        setLoading(true);
        $.post(auraTagsConfig.ajaxUrl, {
            action:   'aura_tags_rename',
            nonce:    auraTagsConfig.nonce,
            old_name: oldName,
            new_name: newName,
        }).done(function (res) {
            setLoading(false);
            if (res.success) {
                showNotice(res.data.message, 'success');
                reload();
            } else {
                showNotice(res.data && res.data.message, 'error');
            }
        }).fail(function () {
            setLoading(false);
            showNotice('Error de conexión', 'error');
        });
    }

    /* ============================================================
       AJAX: Fusionar
       ============================================================ */
    function doMerge(source, target) {
        setLoading(true);
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_merge',
            nonce:  auraTagsConfig.nonce,
            source: source,
            target: target,
        }).done(function (res) {
            setLoading(false);
            if (res.success) {
                showNotice(res.data.message, 'success');
                reload();
            } else {
                showNotice(res.data && res.data.message, 'error');
            }
        }).fail(function () {
            setLoading(false);
            showNotice('Error de conexión', 'error');
        });
    }

    /* ============================================================
       AJAX: Eliminar
       ============================================================ */
    function doDelete(tag) {
        setLoading(true);
        $.post(auraTagsConfig.ajaxUrl, {
            action: 'aura_tags_delete',
            nonce:  auraTagsConfig.nonce,
            name:   tag,
        }).done(function (res) {
            setLoading(false);
            if (res.success) {
                showNotice(res.data.message, 'success');
                reload();
            } else {
                showNotice(res.data && res.data.message, 'error');
            }
        }).fail(function () {
            setLoading(false);
            showNotice('Error de conexión', 'error');
        });
    }

    /* ============================================================
       Utils
       ============================================================ */
    function reload() {
        $('#rename-old, #rename-new, #merge-source, #merge-target').val('');
        loadAllTags();
        loadCloud();
    }

    function showNotice(msg, type) {
        const cls = type === 'error' ? 'notice-error' : 'notice-success';
        $('#aura-tags-notice')
            .removeClass('notice-error notice-success notice')
            .addClass('notice ' + cls)
            .html('<p>' + escHtml(msg || '') + '</p>')
            .show();
        setTimeout(function () { $('#aura-tags-notice').fadeOut(); }, 5000);
    }

    function setLoading(on) {
        $('#btn-rename-tag, #btn-merge-tags, #confirm-delete-tag').prop('disabled', on);
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);
