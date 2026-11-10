
define([], function () {
    'use strict';

    function initGallery() {
        document.querySelectorAll('.custom-product-gallery').forEach(function (gallery) {
            if (gallery.dataset.sliderInitialized) {
                return;
            }

            const items = Array.from(
                gallery.querySelectorAll('.custom-product-gallery__item')
            );

            if (items.length < 2) {
                return;
            }

            gallery.dataset.sliderInitialized = 'true';

            const dots = document.createElement('div');
            dots.className = 'custom-product-gallery__dots';

            items.forEach(function (_, index) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'custom-product-gallery__dot';
                dot.setAttribute('aria-label', 'Show image ' + (index + 1));

                dot.addEventListener('click', function () {
                    showSlide(index);
                });

                dots.appendChild(dot);
            });

            gallery.insertAdjacentElement('afterend', dots);

            let currentIndex = 0;
            let startX = 0;
            let endX = 0;

            function isMobile() {
                return window.matchMedia('(max-width: 47.99rem)').matches;
            }

            function showSlide(index) {
                currentIndex = Math.max(0, Math.min(index, items.length - 1));

                items.forEach(function (item, itemIndex) {
                    if (isMobile()) {
                        item.style.transform =
                            'translateX(-' + currentIndex * 100 + '%)';
                    } else {
                        item.style.transform = '';
                    }
                });

                Array.from(dots.children).forEach(function (dot, dotIndex) {
                    dot.classList.toggle('active', dotIndex === currentIndex);
                    dot.setAttribute(
                        'aria-current',
                        dotIndex === currentIndex ? 'true' : 'false'
                    );
                });
            }

            gallery.addEventListener('touchstart', function (event) {
                startX = event.changedTouches[0].screenX;
            }, { passive: true });

            gallery.addEventListener('touchend', function (event) {
                endX = event.changedTouches[0].screenX;

                const difference = startX - endX;

                if (Math.abs(difference) > 40 && isMobile()) {
                    showSlide(currentIndex + (difference > 0 ? 1 : -1));
                }
            }, { passive: true });

            window.addEventListener('resize', function () {
                showSlide(currentIndex);
            });

            showSlide(0);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGallery);
    } else {
        initGallery();
    }
});
