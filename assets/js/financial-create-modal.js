(function($) {
    'use strict';

    $(document).ready(function() {
        const $modal = $('#aura-financial-create-modal');

        if (!$modal.length) {
            return;
        }

        const $frame = $modal.find('.aura-financial-create-modal__frame');
        const defaultUrl = $modal.data('modalUrl') || '';
        let lastActiveElement = null;

        function getModalUrl(trigger) {
            const $trigger = $(trigger);
            return $trigger.data('modalUrl') || defaultUrl || $trigger.attr('href') || '';
        }

        function openModal(url) {
            if (!url) {
                return;
            }

            lastActiveElement = document.activeElement;
            $frame.attr('src', url);
            $modal.prop('hidden', false).attr('aria-hidden', 'false').addClass('is-open');
            $('body').addClass('aura-financial-create-modal-open');

            window.setTimeout(function() {
                $modal.find('.aura-financial-create-modal__close').trigger('focus');
            }, 40);
        }

        function closeModal() {
            $modal.removeClass('is-open').attr('aria-hidden', 'true').prop('hidden', true);
            $('body').removeClass('aura-financial-create-modal-open');
            $frame.attr('src', 'about:blank');

            if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
                lastActiveElement.focus();
            }
        }

        $(document).on('click', '.aura-open-new-transaction-modal', function(event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || this.target === '_blank') {
                return;
            }

            event.preventDefault();
            openModal(getModalUrl(this));
        });

        $modal.on('click', '[data-aura-close-new-transaction]', function() {
            closeModal();
        });

        $(document).on('keydown', function(event) {
            if (event.key === 'Escape' && $modal.hasClass('is-open')) {
                closeModal();
            }
        });

        window.addEventListener('message', function(event) {
            if (event.origin !== window.location.origin) {
                return;
            }

            if (!event.data || event.data.type !== 'aura:financial-transaction-created') {
                return;
            }

            closeModal();
            window.location.reload();
        });

        const params = new URLSearchParams(window.location.search);
        if (params.get('action') === 'new') {
            const $trigger = $('.aura-open-new-transaction-modal').first();

            if ($trigger.length) {
                openModal(getModalUrl($trigger[0]));
                params.delete('action');
                const nextUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, document.title, nextUrl);
            }
        }
    });
})(jQuery);