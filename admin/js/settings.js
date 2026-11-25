/**
 * CoreSpect settings screen: logo picker built on the WordPress media library.
 */
(function ($) {
    'use strict';

    $(function () {

        var $id      = $('#corespect-logo-id');
        var $preview = $('#corespect-logo-preview');
        var $select  = $('#corespect-logo-select');
        var $remove  = $('#corespect-logo-remove');
        var frame;

        // Not on the settings tab, or the media library failed to load.
        if (!$select.length || typeof wp === 'undefined' || !wp.media) {
            return;
        }

        function setLogo(id, url) {
            $id.val(id);

            if (url) {
                $preview.attr('src', url).removeClass('is-empty');
                $remove.prop('hidden', false);
            } else {
                $preview.attr('src', '').addClass('is-empty');
                $remove.prop('hidden', true);
            }
        }

        $select.on('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: $select.data('title'),
                button: { text: $select.data('button') },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = (attachment.sizes && attachment.sizes.medium)
                    ? attachment.sizes.medium.url
                    : attachment.url;

                setLogo(attachment.id, url);
            });

            frame.open();
        });

        $remove.on('click', function (event) {
            event.preventDefault();
            setLogo('', '');
        });
    });

})(jQuery);
