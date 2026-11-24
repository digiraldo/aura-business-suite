/**
 * JS Admin — Validación Documental (Subida PDF, SHA-256 en navegador, Modales Item 26, Settings)
 */
(function ($) {
    'use strict';

    var cfg = window.auraDocVerify || { ajaxurl: '', nonce: '', i18n: {} };
    var currentDetailId = 0;

    function toast(msg, type) {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.showToast === 'function') {
            AuraUI.showToast(msg, type || 'info');
        } else {
            window.alert(msg);
        }
    }

    function escHtml(str) {
        return $('<div>').text(String(str || '')).html();
    }

    function copyText(text, successMsg) {
        if (!text) return;
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(function () {
                toast(successMsg || cfg.i18n.copied || 'Copiado', 'success');
            });
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;left:-9999px;';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        toast(successMsg || cfg.i18n.copied || 'Copiado', 'success');
    }

    function computeFileSha256(file, cb) {
        if (!file || !window.crypto || !window.crypto.subtle) {
            cb('');
            return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) {
            window.crypto.subtle.digest('SHA-256', ev.target.result).then(function (hashBuf) {
                var arr = Array.from(new Uint8Array(hashBuf));
                var hex = arr.map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
                cb(hex);
            }).catch(function () { cb(''); });
        };
        reader.readAsArrayBuffer(file);
    }

    function showModal(id, opts) {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.openModal === 'function') {
            AuraUI.openModal(id, opts || {});
        } else {
            $('#' + id).css('display', 'flex').addClass('active is-active');
        }
    }

    function closeModal(id) {
        if (typeof AuraUI !== 'undefined' && typeof AuraUI.closeModal === 'function') {
            AuraUI.closeModal(id);
        } else {
            $('#' + id).removeClass('active is-active').hide();
        }
    }

    function renderDetailModalBody(d) {
        var statusBadge = d.effective_status === 'active'
            ? '<span class="badge badge-traffic-success"><span class="traffic-dot traffic-dot-success"></span> Vigente</span>'
            : (d.effective_status === 'expired'
                ? '<span class="badge badge-traffic-warning"><span class="traffic-dot traffic-dot-warning"></span> Expirado</span>'
                : '<span class="badge badge-traffic-danger"><span class="traffic-dot traffic-dot-danger"></span> Revocado</span>');

        var driveBadge = d.storage_driver === 'google_drive'
            ? '<span class="badge badge-cyan badge-sm">☁️ Google Drive (Privado)</span>'
            : '<span class="badge badge-slate badge-sm">🔒 Almacenamiento Local</span>';

        var html = '<div class="aura-dv-modal-hero">';
        html += '<div class="aura-dv-modal-qr-box">' +
                '<img src="' + escHtml(d.qr_preview_url) + '" alt="QR ' + escHtml(d.folio) + '">' +
                '<div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">' +
                '<a class="btn btn-secondary btn-sm" href="' + escHtml(d.download_qr_png_url) + '">PNG</a>' +
                '<a class="btn btn-secondary btn-sm" href="' + escHtml(d.download_qr_svg_url) + '">SVG</a>' +
                '</div>' +
                '</div>';

        html += '<div>' +
                '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">' +
                    '<span class="badge badge-blue">' + escHtml(d.folio) + '</span>' +
                    statusBadge + ' ' + driveBadge +
                '</div>' +
                '<h3 style="margin:0 0 10px;font-size:16px;">' + escHtml(d.title_subject) + '</h3>' +
                '<div class="aura-dv-modal-specs">' +
                    '<div class="aura-dv-spec-box"><small>Código de Verificación</small><strong><code>' + escHtml(d.verification_code) + '</code></strong></div>' +
                    '<div class="aura-dv-spec-box"><small>Titular / Invitado</small><strong>' + escHtml(d.holder_name) + (d.holder_doc_masked ? ' (' + escHtml(d.holder_doc_masked) + ')' : '') + '</strong></div>' +
                    '<div class="aura-dv-spec-box"><small>Entidad Destinataria</small><strong>' + escHtml(d.recipient_entity || '—') + (d.recipient_country ? ' · ' + escHtml(d.recipient_country) : '') + '</strong></div>' +
                    (d.stay_address ? '<div class="aura-dv-spec-box"><small>Lugar de Estadía / Respaldo</small><strong>' + escHtml(d.stay_address) + '</strong></div>' : '') +
                    '<div class="aura-dv-spec-box"><small>Firmante Autorizado</small><strong>' + escHtml((d.signer_name || '—') + (d.signer_title ? ' — ' + d.signer_title : '')) + '</strong></div>' +
                    '<div class="aura-dv-spec-box"><small>Emisión / Vigencia</small><strong>' + escHtml(d.issued_at) + ' → ' + escHtml(d.expires_at || 'Sin vencimiento') + '</strong></div>' +
                    '<div class="aura-dv-spec-box"><small>Consultas Públicas</small><strong>' + escHtml(String(d.verified_count || 0)) + '</strong></div>' +
                '</div>';

        if (d.revoke_reason) {
            html += '<div class="alert-card alert-danger" style="margin-top:12px;"><span class="alert-icon">⚠️</span><div><div class="alert-title">Motivo de revocación</div><p style="margin:3px 0 0;">' + escHtml(d.revoke_reason) + '</p></div></div>';
        }

        html += '<div class="aura-dv-spec-box" style="margin-top:10px;">' +
                '<small>URL Pública de Verificación</small>' +
                '<div class="aura-dv-inline-copy" style="margin-top:4px;">' +
                '<code style="font-size:11.5px;word-break:break-all;">' + escHtml(d.verify_url) + '</code>' +
                '<button type="button" class="btn btn-secondary btn-sm aura-dv-copy-btn" data-copy="' + escHtml(d.verify_url) + '">Copiar URL</button>' +
                '<a href="' + escHtml(d.verify_url) + '" target="_blank" rel="noopener noreferrer" class="btn btn-glass btn-sm">Abrir</a>' +
                '</div></div>';

        html += '<div class="aura-dv-spec-box" style="margin-top:10px;">' +
                '<small>SHA-256 PDF Original</small>' +
                '<code style="font-size:11px;word-break:break-all;">' + escHtml(d.sha256_original || '—') + '</code>' +
                (d.sha256_stamped ? '<small style="margin-top:6px;">SHA-256 PDF Sellado / Anexo</small><code style="font-size:11px;word-break:break-all;">' + escHtml(d.sha256_stamped) + '</code>' : '') +
                '</div>';

        html += '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">' +
                '<a href="' + escHtml(d.download_original_url) + '" class="btn btn-secondary btn-sm"><span class="dashicons dashicons-download"></span> Descargar PDF Original</a>' +
                (d.has_stamped_pdf ? '<a href="' + escHtml(d.download_stamped_url) + '" class="btn btn-primary btn-shimmer btn-sm"><span class="dashicons dashicons-shield"></span> Descargar PDF Certificado / Anexo</a>' : '') +
                '</div>';

        html += '</div></div>';
        return html;
    }

    function openDetailModal(docId) {
        currentDetailId = parseInt(docId, 10) || 0;
        if (!currentDetailId) return;

        $('#aura-docverify-detail-body').html('');
        $('#aura-dv-btn-open-revoke, #aura-dv-btn-reactivate').hide();
        showModal('aura-docverify-detail-modal', { async: true });

        $.post(cfg.ajaxurl, {
            action: 'aura_docverify_get_detail',
            nonce: cfg.nonce,
            id: currentDetailId
        }, function (res) {
            if (!res || !res.success) {
                if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                    AuraUI.hideModalSkeleton('aura-docverify-detail-modal');
                }
                closeModal('aura-docverify-detail-modal');
                toast((res && res.data && res.data.message) ? res.data.message : cfg.i18n.error, 'error');
                return;
            }
            var d = res.data;
            $('#aura-docverify-detail-body').html(renderDetailModalBody(d));
            if (d.effective_status === 'revoked') {
                $('#aura-dv-btn-reactivate').show();
            } else {
                $('#aura-dv-btn-open-revoke').show();
            }
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-docverify-detail-modal');
            }
        }).fail(function () {
            if (typeof AuraUI !== 'undefined' && AuraUI.hideModalSkeleton) {
                AuraUI.hideModalSkeleton('aura-docverify-detail-modal');
            }
            closeModal('aura-docverify-detail-modal');
            toast(cfg.i18n.error, 'error');
        });
    }

    $(function () {
        // Expandir Child Rows (+ / -)
        $(document).on('click', '.aura-dv-row-toggle', function () {
            var $btn = $(this);
            var id = $btn.data('id');
            var $child = $('.aura-dv-child-row[data-parent-id="' + id + '"]');
            var isOpen = $child.is(':visible');
            $child.toggle(!isOpen);
            $btn.text(isOpen ? '+' : '−').attr('aria-expanded', isOpen ? 'false' : 'true');
        });

        // Copiar textos
        $(document).on('click', '.aura-dv-copy-btn', function () {
            copyText($(this).data('copy'), cfg.i18n.copied);
        });

        // Abrir modal de detalle/QR
        $(document).on('click', '.aura-dv-open-detail', function () {
            openDetailModal($(this).data('id'));
        });

        // Auto-abrir modal tras crear un documento nuevo (?created=ID)
        var createdId = parseInt($('#aura-docverify-list-page').data('createdId'), 10) || 0;
        if (createdId > 0) {
            openDetailModal(createdId);
        }

        // Protección anti-cierre en 2 fases + Escape en ambos modales
        var _backdropDown = {};
        $(document).on('mousedown', '#aura-docverify-detail-modal, #aura-docverify-revoke-modal', function (e) {
            _backdropDown[this.id] = (e.target === this);
        });
        $(document).on('click', '#aura-docverify-detail-modal, #aura-docverify-revoke-modal', function (e) {
            if (_backdropDown[this.id] && e.target === this) {
                closeModal(this.id);
            }
            _backdropDown[this.id] = false;
        });
        $(document).on('click', '#aura-docverify-detail-modal .aura-modal-close, #aura-docverify-detail-modal .aura-dv-modal-close-btn', function () {
            closeModal('aura-docverify-detail-modal');
        });
        $(document).on('click', '#aura-docverify-revoke-modal .aura-modal-close, #aura-docverify-revoke-modal .aura-dv-modal-close-btn', function () {
            closeModal('aura-docverify-revoke-modal');
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                if ($('#aura-docverify-revoke-modal').is(':visible')) {
                    closeModal('aura-docverify-revoke-modal');
                } else if ($('#aura-docverify-detail-modal').is(':visible')) {
                    closeModal('aura-docverify-detail-modal');
                }
            }
        });

        // Revocación / Reactivación / Eliminación
        $(document).on('click', '#aura-dv-btn-open-revoke', function () {
            $('#aura-dv-revoke-id').val(currentDetailId);
            $('#aura-dv-revoke-reason').val('');
            showModal('aura-docverify-revoke-modal');
        });

        $(document).on('submit', '#aura-docverify-revoke-form', function (e) {
            e.preventDefault();
            var reason = $.trim($('#aura-dv-revoke-reason').val() || '');
            if (!reason) {
                toast(cfg.i18n.needRevokeReason, 'error');
                return;
            }
            $.post(cfg.ajaxurl, {
                action: 'aura_docverify_revoke',
                nonce: cfg.nonce,
                id: $('#aura-dv-revoke-id').val(),
                reason: reason
            }, function (res) {
                if (res && res.success) {
                    toast(res.data.message, 'success');
                    window.location.reload();
                } else {
                    toast((res && res.data && res.data.message) ? res.data.message : cfg.i18n.error, 'error');
                }
            });
        });

        $(document).on('click', '#aura-dv-btn-reactivate', function () {
            if (!window.confirm(cfg.i18n.confirmReact)) return;
            $.post(cfg.ajaxurl, {
                action: 'aura_docverify_reactivate',
                nonce: cfg.nonce,
                id: currentDetailId
            }, function (res) {
                if (res && res.success) {
                    toast(res.data.message, 'success');
                    window.location.reload();
                } else {
                    toast((res && res.data && res.data.message) ? res.data.message : cfg.i18n.error, 'error');
                }
            });
        });

        $(document).on('click', '#aura-dv-btn-delete', function () {
            if (!window.confirm(cfg.i18n.confirmDelete)) return;
            $.post(cfg.ajaxurl, {
                action: 'aura_docverify_delete',
                nonce: cfg.nonce,
                id: currentDetailId
            }, function (res) {
                if (res && res.success) {
                    toast(res.data.message, 'success');
                    window.location.href = cfg.listUrl;
                } else {
                    toast((res && res.data && res.data.message) ? res.data.message : cfg.i18n.error, 'error');
                }
            });
        });

        // Formulario Registro / Subida PDF
        var $dz = $('#aura-dv-pdf-dropzone');
        var $fileInput = $('#aura-dv-pdf-input');

        function handleSelectedPdf(file) {
            if (!file) return;
            $('#aura-dv-dz-empty').hide();
            $('#aura-dv-dz-ready').show();
            $('#aura-dv-dz-filename').text(file.name);
            $('#aura-dv-dz-filesize').text('(' + (file.size / 1024 / 1024).toFixed(2) + ' MB)');
            $('#aura-dv-dz-sha256').text('Calculando…');
            computeFileSha256(file, function (hex) {
                $('#aura-dv-dz-sha256').text(hex || 'Se calculará en servidor');
            });
        }

        if ($dz.length) {
            $dz.on('click', function (e) {
                if ($(e.target).closest('#aura-dv-dz-change').length) return;
                $fileInput.trigger('click');
            });
            $('#aura-dv-dz-change').on('click', function (e) {
                e.stopPropagation();
                $fileInput.trigger('click');
            });
            $fileInput.on('change', function () {
                if (this.files && this.files[0]) {
                    handleSelectedPdf(this.files[0]);
                }
            });
            $dz.on('dragover dragenter', function (e) {
                e.preventDefault();
                $dz.addClass('is-dragover');
            }).on('dragleave drop', function (e) {
                e.preventDefault();
                $dz.removeClass('is-dragover');
            }).on('drop', function (e) {
                var dt = e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files[0]) {
                    try { $fileInput[0].files = dt.files; } catch (err) {}
                    handleSelectedPdf(dt.files[0]);
                }
            });
        }

        $('#aura-dv-stamp-mode').on('change', function () {
            $('#aura-dv-stamp-pos-wrap').toggle($(this).val() === 'overlay_corner');
        });

        $('#aura-dv-doc-type').on('change', function () {
            var v = $(this).val();
            if (v === 'migration_invitation') {
                $('#aura-dv-recipient').attr('placeholder', 'Ej: Autoridad Migratoria / Control Fronterizo');
                $('#aura-dv-purpose').attr('placeholder', 'Ej: Ingreso al país sin visa — Acreditación de estadía, evento y respaldo institucional');
            } else if (v === 'invitation_letter') {
                $('#aura-dv-recipient').attr('placeholder', 'Ej: Embajada de los Estados Unidos / Sección Consular');
                $('#aura-dv-purpose').attr('placeholder', 'Ej: Trámite de Visa de Visitante B1/B2 — Participación en Seminario Internacional');
            }
        });

        $('#aura-dv-signer-preset').on('change', function () {
            var $opt = $(this).find('option:selected');
            if ($opt.val()) {
                $('#aura-dv-signer-name').val($opt.data('name') || '');
                $('#aura-dv-signer-title').val($opt.data('title') || '');
            }
        });

        $('#aura-docverify-register-form').on('submit', function (e) {
            e.preventDefault();
            var formEl = this;
            var docId = parseInt($(formEl).find('input[name="id"]').val(), 10) || 0;
            if (docId === 0 && (!$fileInput[0] || !$fileInput[0].files || !$fileInput[0].files.length)) {
                toast(cfg.i18n.needPdf, 'error');
                return;
            }

            var fd = new FormData(formEl);
            fd.append('action', 'aura_docverify_save');
            fd.append('nonce', cfg.nonce);

            var $btn = $('#aura-dv-submit-btn').prop('disabled', true);
            $('#aura-dv-submit-progress').show();

            $.ajax({
                url: cfg.ajaxurl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res && res.success) {
                        toast(res.data.message || cfg.i18n.saved, 'success');
                        if (res.data.warning) {
                            toast(res.data.warning, 'warning');
                        }
                        window.location.href = res.data.redirect || cfg.listUrl;
                    } else {
                        $btn.prop('disabled', false);
                        $('#aura-dv-submit-progress').hide();
                        toast((res && res.data && res.data.message) ? res.data.message : cfg.i18n.error, 'error');
                    }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false);
                    $('#aura-dv-submit-progress').hide();
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                        ? xhr.responseJSON.data.message
                        : cfg.i18n.error;
                    toast(msg, 'error');
                }
            });
        });

        // Media selector + Guardado de Settings
        $('#aura-dv-pick-logo').on('click', function () {
            if (!window.wp || !wp.media) return;
            var frame = wp.media({ title: 'Seleccionar Logo Institucional', multiple: false, library: { type: 'image' } });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                if (att && att.url) {
                    $('#dv-org-logo').val(att.url);
                }
            });
            frame.open();
        });

        $('#aura-docverify-settings-form').on('submit', function (e) {
            e.preventDefault();
            var data = $(this).serializeArray();
            data.push({ name: 'action', value: 'aura_docverify_save_settings' });
            data.push({ name: 'nonce', value: cfg.nonce });

            $.post(cfg.ajaxurl, data, function (res) {
                if (res && res.success) {
                    toast(res.data.message, 'success');
                } else {
                    toast((res && res.data && res.data.message) ? res.data.message : cfg.i18n.error, 'error');
                }
            }).fail(function () {
                toast(cfg.i18n.error, 'error');
            });
        });
    });
})(jQuery);
