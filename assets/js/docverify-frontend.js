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

    function sha256Fallback(bytes) {
        var K = [
            0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,
            0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,
            0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,
            0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,
            0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,
            0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,
            0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,
            0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2
        ];
        var H = [0x6a09e667,0xbb67ae85,0x3c6ef372,0xa54ff53a,0x510e527f,0x9b05688c,0x1f83d9ab,0x5be0cd19];
        var l = bytes.length;
        var bitLen = l * 8;
        var paddedLen = Math.ceil((l + 9) / 64) * 64;
        var buf = new Uint8Array(paddedLen);
        buf.set(bytes);
        buf[l] = 0x80;
        var view = new DataView(buf.buffer);
        view.setUint32(paddedLen - 8, Math.floor(bitLen / 0x100000000), false);
        view.setUint32(paddedLen - 4, bitLen >>> 0, false);

        var W = new Uint32Array(64);
        function rotr(x, n) { return (x >>> n) | (x << (32 - n)); }

        for (var i = 0; i < paddedLen; i += 64) {
            for (var t = 0; t < 16; t++) {
                W[t] = view.getUint32(i + t * 4, false);
            }
            for (t = 16; t < 64; t++) {
                var s0 = rotr(W[t - 15], 7) ^ rotr(W[t - 15], 18) ^ (W[t - 15] >>> 3);
                var s1 = rotr(W[t - 2], 17) ^ rotr(W[t - 2], 19) ^ (W[t - 2] >>> 10);
                W[t] = (W[t - 16] + s0 + W[t - 7] + s1) >>> 0;
            }
            var a = H[0], b = H[1], c = H[2], d = H[3], e = H[4], f = H[5], g = H[6], h = H[7];
            for (t = 0; t < 64; t++) {
                var S1 = rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25);
                var ch = (e & f) ^ ((~e) & g);
                var temp1 = (h + S1 + ch + K[t] + W[t]) >>> 0;
                var S0 = rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22);
                var maj = (a & b) ^ (a & c) ^ (b & c);
                var temp2 = (S0 + maj) >>> 0;
                h = g; g = f; f = e; e = (d + temp1) >>> 0;
                d = c; c = b; b = a; a = (temp1 + temp2) >>> 0;
            }
            H[0] = (H[0] + a) >>> 0; H[1] = (H[1] + b) >>> 0; H[2] = (H[2] + c) >>> 0; H[3] = (H[3] + d) >>> 0;
            H[4] = (H[4] + e) >>> 0; H[5] = (H[5] + f) >>> 0; H[6] = (H[6] + g) >>> 0; H[7] = (H[7] + h) >>> 0;
        }
        return H.map(function (v) { return ('00000000' + v.toString(16)).slice(-8); }).join('');
    }

    function computeLocalSha256(file, cb) {
        if (!file) {
            cb('');
            return;
        }
        var reader = new FileReader();
        reader.onerror = function () { cb(''); };
        reader.onload = function (ev) {
            var arrBuf = ev.target.result;
            if (window.crypto && window.crypto.subtle && typeof window.crypto.subtle.digest === 'function') {
                window.crypto.subtle.digest('SHA-256', arrBuf).then(function (buf) {
                    var arr = Array.from(new Uint8Array(buf));
                    cb(arr.map(function (b) { return b.toString(16).padStart(2, '0'); }).join('').toLowerCase());
                }).catch(function () {
                    cb(sha256Fallback(new Uint8Array(arrBuf)));
                });
            } else {
                cb(sha256Fallback(new Uint8Array(arrBuf)));
            }
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

        // Toggle táctil/click del tooltip informativo SHA-256
        $(document).on('click', '.aura-dv-tip-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $wrap = $(this).closest('.aura-dv-tip-wrap');
            var open = !$wrap.hasClass('is-open');
            $('.aura-dv-tip-wrap').removeClass('is-open').find('.aura-dv-tip-btn').attr('aria-expanded', 'false');
            if (open) {
                $wrap.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.aura-dv-tip-wrap').length) {
                $('.aura-dv-tip-wrap').removeClass('is-open').find('.aura-dv-tip-btn').attr('aria-expanded', 'false');
            }
        });

        $('#aura-dv-print-receipt').on('click', function () {
            window.print();
        });

        // Cotejo SHA-256 en navegador (Zero-Upload)
        var $drop = $('#aura-dv-public-sha-drop');
        var $input = $('#aura-dv-public-sha-input');
        var shaOrig = String($root.attr('data-sha-orig') || '').toLowerCase();
        var shaStamp = String($root.attr('data-sha-stamp') || '').toLowerCase();
        var folio = String($root.attr('data-folio') || '');
        var sig = String($root.attr('data-sig') || '');

        function verifyLocalFile(file) {
            if (!file) return;
            var $out = $('#aura-dv-public-sha-result');
            if (file.type && file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name || '')) {
                var badType = currentLang === 'en'
                    ? '⚠️ Please select a valid PDF file (.pdf).'
                    : '⚠️ Selecciona un archivo en formato PDF válido (.pdf).';
                $out.attr('class', 'aura-dv-sha-match-fail').text(badType).show();
                return;
            }
            var calcMsg = currentLang === 'en'
                ? ('⏳ Calculating SHA-256 for "' + (file.name || 'document.pdf') + '"…')
                : ('⏳ Calculando huella SHA-256 de "' + (file.name || 'documento.pdf') + '"…');
            $out.attr('class', 'aura-dv-sha-checking').text(calcMsg).show();
            computeLocalFileAndLog(file, $out);
        }

        function computeLocalFileAndLog(file, $out) {
            computeLocalSha256(file, function (hex) {
                if (!hex) {
                    var errMsg = currentLang === 'en'
                        ? 'Could not read the selected PDF file in this browser.'
                        : 'No se pudo leer el archivo PDF seleccionado en este navegador.';
                    $out.attr('class', 'aura-dv-sha-match-fail').text(errMsg).show();
                    return;
                }
                var match = 'none';
                if (shaOrig && hex === shaOrig) {
                    match = 'original';
                } else if (shaStamp && hex === shaStamp) {
                    match = 'stamped';
                }

                if (match !== 'none') {
                    var matchLabelEn = (match === 'original') ? 'original signed' : 'certified / QR-stamped';
                    var matchLabelEs = (match === 'original') ? 'original firmado' : 'certificado / sellado con QR';
                    var okMsg = currentLang === 'en'
                        ? ('✅ INTEGRITY VERIFIED: "' + (file.name || 'PDF') + '" matches bit-for-bit with the registered ' + matchLabelEn + ' document (' + hex.toUpperCase() + ').')
                        : ('✅ INTEGRIDAD VERIFICADA: "' + (file.name || 'PDF') + '" coincide bit a bit con el documento ' + matchLabelEs + ' registrado (' + hex.toUpperCase() + ').');
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

        if ($drop.length && $input.length) {
            $input.on('click', function (e) {
                e.stopPropagation();
            });
            $drop.on('click', function (e) {
                e.preventDefault();
                $input.val('');
                $input.trigger('click');
            });
            $drop.on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $input.val('');
                    $input.trigger('click');
                }
            });
            $input.on('change', function () {
                if (this.files && this.files[0]) {
                    verifyLocalFile(this.files[0]);
                }
            });
            $(document).on('dragover.auraDvPubDrop drop.auraDvPubDrop', function (e) {
                if ($('#aura-dv-public-sha-drop').length) {
                    e.preventDefault();
                }
            });
            $drop.on('dragenter dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.originalEvent && e.originalEvent.dataTransfer) {
                    e.originalEvent.dataTransfer.dropEffect = 'copy';
                }
                $drop.addClass('is-dragover');
            }).on('dragleave', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.currentTarget && e.relatedTarget && e.currentTarget.contains(e.relatedTarget)) {
                    return;
                }
                $drop.removeClass('is-dragover');
            }).on('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $drop.removeClass('is-dragover');
                var dt = e.originalEvent && e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files[0]) {
                    verifyLocalFile(dt.files[0]);
                }
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
