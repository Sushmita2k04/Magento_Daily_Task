define([
    'jquery'
], function ($) {
    'use strict';

    return function () {
        var $campaign = $('.ramadan-campaign');

        if (!$campaign.length) {
            return;
        }

        var videoId = $campaign.data('youtube-video-id');
        var countdownEnd = $campaign.data('countdown-end');
        var player = null;
        var countdownInterval = null;

        function loadYouTubeApi(callback) {
            if (window.YT && window.YT.Player) {
                callback();
                return;
            }

            var previousCallback = window.onYouTubeIframeAPIReady;

            window.onYouTubeIframeAPIReady = function () {
                if (typeof previousCallback === 'function') {
                    previousCallback();
                }

                callback();
            };

            if (!document.querySelector('script[src="https://www.youtube.com/iframe_api"]')) {
                var script = document.createElement('script');

                script.src = 'https://www.youtube.com/iframe_api';
                script.async = true;

                document.head.appendChild(script);
            }
        }

        function initializePlayer() {
            if (player || !videoId || !window.YT || !window.YT.Player) {
                return;
            }

            player = new YT.Player('ramadan-youtube-player', {
                videoId: videoId,
                playerVars: {
                    autoplay: 0,
                    controls: 0,
                    disablekb: 1,
                    fs: 0,
                    modestbranding: 1,
                    playsinline: 1,
                    rel: 0
                },
                events: {
                    onReady: function () {
                        updatePlayButton();
                        updateMuteButton();
                    }
                }
            });
        }

        function updatePlayButton() {
            var $button = $('.ramadan-campaign__control--play');

            if (!player || !player.getPlayerState) {
                return;
            }

            if (player.getPlayerState() === YT.PlayerState.PLAYING) {
                $button.text('Pause');
                $button.attr('aria-label', 'Pause video');
            } else {
                $button.text('Play');
                $button.attr('aria-label', 'Play video');
            }
        }

        function updateMuteButton() {
            var $button = $('.ramadan-campaign__control--mute');

            if (!player || !player.isMuted) {
                return;
            }

            if (player.isMuted()) {
                $button.text('Unmute');
                $button.attr('aria-label', 'Unmute video');
            } else {
                $button.text('Mute');
                $button.attr('aria-label', 'Mute video');
            }
        }

        function togglePlay() {
            if (!player) {
                return;
            }

            if (player.getPlayerState() === YT.PlayerState.PLAYING) {
                player.pauseVideo();
            } else {
                player.playVideo();
            }

            setTimeout(updatePlayButton, 100);
        }

        function seekVideo(seconds) {
            if (!player || !player.getCurrentTime || !player.getDuration) {
                return;
            }

            var currentTime = player.getCurrentTime();
            var duration = player.getDuration();
            var targetTime = Math.max(0, Math.min(currentTime + seconds, duration));

            player.seekTo(targetTime, true);
        }

        function toggleMute() {
            if (!player) {
                return;
            }

            if (player.isMuted()) {
                player.unMute();
            } else {
                player.mute();
            }

            setTimeout(updateMuteButton, 100);
        }

        function toggleFullscreen() {
            var playerElement = document.getElementById('ramadan-youtube-player');

            if (!playerElement) {
                return;
            }

            if (document.fullscreenElement) {
                document.exitFullscreen();
                return;
            }

            if (playerElement.requestFullscreen) {
                playerElement.requestFullscreen();
            }
        }

        function updateCountdown() {
            var targetTime = new Date(countdownEnd).getTime();
            var currentTime = Date.now();
            var remaining = Math.max(0, targetTime - currentTime);

            var totalSeconds = Math.floor(remaining / 1000);

            var days = Math.floor(totalSeconds / 86400);
            var hours = Math.floor((totalSeconds % 86400) / 3600);
            var minutes = Math.floor((totalSeconds % 3600) / 60);
            var seconds = totalSeconds % 60;

            $('[data-countdown-days]').text(String(days).padStart(2, '0'));
            $('[data-countdown-hours]').text(String(hours).padStart(2, '0'));
            $('[data-countdown-minutes]').text(String(minutes).padStart(2, '0'));
            $('[data-countdown-seconds]').text(String(seconds).padStart(2, '0'));

            if (remaining <= 0 && countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
        }

        function initializeCountdown() {
            if (!countdownEnd) {
                return;
            }

            updateCountdown();

            countdownInterval = setInterval(updateCountdown, 1000);
        }

        $campaign.on(
            'click',
            '.ramadan-campaign__control--play',
            togglePlay
        );

        $campaign.on(
            'click',
            '.ramadan-campaign__control--backward',
            function () {
                seekVideo(-10);
            }
        );

        $campaign.on(
            'click',
            '.ramadan-campaign__control--forward',
            function () {
                seekVideo(10);
            }
        );

        $campaign.on(
            'click',
            '.ramadan-campaign__control--mute',
            toggleMute
        );

        $campaign.on(
            'click',
            '.ramadan-campaign__control--fullscreen',
            toggleFullscreen
        );

        loadYouTubeApi(initializePlayer);
        initializeCountdown();
    };
});
