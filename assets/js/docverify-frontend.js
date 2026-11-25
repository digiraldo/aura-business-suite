/**
 * JS Frontend — Verificación Pública Bilingüe (ES/EN), Cotejo SHA-256 Zero-Upload y Desbloqueo Consular
 */
(function ($) {
    'use strict';

    var cfg = window.auraDocVerifyPublic || { ajaxurl: '', nonce: '' };
    var currentLang = 'es';

    function applyLang(lang) {
        currentLang = (lang === 'en') ? 'en' : 'es';
        $('.aura-dv-lang-btn').removeClass('is-active');
        $('.aura-dv-lang-btn[data-dv-lang="' + currentLang + '"]').addClass('is-active');

        $('[data-es][data-en]').each(function () {
            var txt = $(this).attr('data-' + currentLang);
            if (typeof txt === 'string') {
                $(this).text(txt);
            }
        });
    }

    function computeLocalSha256(file, cb) {
        if (!file || !window.crypto || !window.crypto.subtle) {
            cb('');
            return;
        }
        var reader = new FileReader();
        reader.onload = function (ev) {
            window.crypto.subtle.digest('SHA-256', ev.target.result).then(function (buf) {
                var arr = Array.from(new Uint8Array(buf));
                cb(arr.map(function (b) { return b.toString(16).padStart(2, '0'); }).join('').toLowerCase());
            }).catch(function () { cb(''); });
        };
        reader.readAsArrayBuffer(file);
    }

    $(function () {
        var $root = $('#aura-dv-public-root');
        if (!$root.length) return;

        // Sincronizar con el idioma activo del selector estándar de AURA (o detectar inglés del navegador si no se forzó)
        var initialLang = String($root.attr('data-lang') || '').toLowerCase();
        if (initialLang === 'en' || initialLang === 'es') {
            applyLang(initialLang);
        } else if (navigator.language && navigator.language.toLowerCase().indexOf('en') === 0) {
            applyLang('en');
        }

        $(document).on('click', '.aura-dv-lang-btn', function () {
            applyLang($(this).data('dvLang'));
        });

        $('#aura-dv-print-receipt').on('click', function () {
            window.print();
        });

        // Cotejo SHA-256 en navegador (Zero-Upload)
        var $drop = $('#aura-dv-public-sha-drop');
        var $input = $('#aura-dv-public-sha-input');
        var shaOrig = String($root.data('shaOrig') || '').toLowerCase();
        var shaStamp = String($root.data('shaStamp') || '').toLowerCase();
        var folio = String($root.data('folio') || '');
        var sig = String($root.data('sig') || '');

        function verifyLocalFile(file) {
            if (!file) return;
            var $out = $('#aura-dv-public-sha-result');
            computeLocalFileAndLog(file, $out);
        }

        function computeLocalFileAndLog(file, $out) {
            computeLocalSha256(file, function (hex) {
                if (!hex) {
                    $out.attr('class', 'aura-dv-sha-match-fail').text('Web Crypto API not available in this browser.').show();
                    return;
                }
                var match = 'none';
                if (shaOrig && hex === shaOrig) {
                    match = 'original';
                } else if (shaStamp && hex === shaStamp) {
                    match = 'stamped';
                }

                if (match !== 'none') {
                    var okMsg = currentLang === 'en'
                        ? ('✅ INTEGRITY VERIFIED: The PDF matches bit-for-bit with the registered ' + match + ' document (' + hex.toUpperCase() + ').')
                        : ('✅ INTEGRIDAD VERIFICADA: El PDF coincide bit a bit con el documento ' + (match === 'original' ? 'original' : 'certificado') + ' registrado (' + hex.toUpperCase() + ').');
                    $out.attr('class', 'aura-dv-sha-match-ok').text(okMsg).show();
                } else {
                    var failMsg = currentLang === 'en'
                        ? ('❌ MISMATCH: Calculated SHA-256 (' + hex.toUpperCase() + ') does NOT match our official record.')
                        : ('❌ NO COINCIDE: El SHA-256 calculado (' + hex.toUpperCase() + ') NO coincide con el registro oficial.');
                    $out.attr('class', 'aura-dv-sha-match-fail').text(failMsg).show();
                }

                if (folio && sig && cfg.ajaxurl) {
                    $.post(cfg.ajaxurl, {
                        action: 'aura_docverify_log_hash_check',
                        nonce: cfg.nonce,
                        folio: folio,
                        sig: sig,
                        match: match
                    });
                }
            });
        }

        if ($drop.length) {
            $drop.on('click', function () { $input.trigger('click'); });
            $input.on('change', function () {
                if (this.files && this.files[0]) verifyLocalFile(this.files[0]);
            });
            $drop.on('dragover dragenter', function (e) { e.preventDefault(); })
                 .on('drop', function (e) {
                     e.preventDefault();
                     var dt = e.originalEvent.dataTransfer;
                     if (dt && dt.files && dt.files[0]) verifyLocalFile(dt.files[0]);
                 });
        }

        // Desbloqueo con PIN / Pasaporte
        $('#aura-dv-unlock-form').on('submit', function (e) {
            e.preventDefault();
            var secret = $.trim($('#aura-dv-unlock-secret').val() || '');
            if (!secret || !folio || !sig) return;

            var $msg = $('#aura-dv-unlock-msg').text('...');
            $.post(cfg.ajaxurl, {
                action: 'aura_docverify_unlock',
                nonce: cfg.nonce,
                folio: folio,
                sig: sig,
                secret: secret
            }, function (res) {
                if (!res || !res.success) {
                    $msg.css('color', '#dc2626').text((res && res.data && res.data.message) ? res.data.message : 'Error');
                    return;
                }
                var d = res.data;
                if (d.recipient_entity) {
                    $('#aura-dv-pub-recipient').text(d.recipient_entity + (d.recipient_country ? ' (' + d.recipient_country + ')' : ''));
                }
                if (d.purpose) {
                    $('#aura-dv-pub-purpose').text(d.purpose);
                }
                if (d.period) {
                    $('#aura-dv-pub-period').text(d.period);
                }
                if (d.stay_address) {
                    $('#aura-dv-pub-stay').text(d.stay_address);
                }
                if (d.download_pdf_url) {
                    $('#aura-dv-public-download-link').attr('href', d.download_pdf_url);
                    $('#aura-dv-public-download-wrap').slideDown(150);
                }
                $('#aura-dv-unlock-box').slideUp(180);
            }).fail(function (xhr) {
                var txt = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Error';
                $msg.css('color', '#dc2626').text(txt);
            });
        });
    });
})(jQuery);
