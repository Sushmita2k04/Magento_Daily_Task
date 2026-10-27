
define([
    'jquery'
], function ($) {
    'use strict';

    return function () {
        var $modal = $('#youtube-modal');

        if (!$modal.length || $modal.data('youtubeModalInitialized')) {
            return;
        }

        $modal.data('youtubeModalInitialized', true);


        var $player = $modal.find('.youtube-modal__player');

        function closeModal() {
            $modal.removeClass('is-open').attr('aria-hidden', 'true');
            $player.empty();
            $('body').removeClass('youtube-modal-open');
        }

        $(document).on('click.youtubeModal', '.youtube-video-trigger', function () {
            var videoId = $(this).data('video');
            if (!videoId) {
                console.log('not working');
                return;
            }

            var iframe = $('<iframe>', {
                src: 'https://www.youtube-nocookie.com/embed/' +
                    encodeURIComponent(videoId) + '?autoplay=1&rel=0',
                title: 'YouTube video player',
                allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture',
                allowfullscreen: true
            });

            $player.empty().append(iframe);
            $modal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('youtube-modal-open');
        });

        $modal.on('click.youtubeModal', '.youtube-modal__close, .youtube-modal__backdrop', closeModal);

        $(document).on('keydown.youtubeModal', function (event) {
            if (event.key === 'Escape' && $modal.hasClass('is-open')) {
                closeModal();
            }
        });
    };
});
