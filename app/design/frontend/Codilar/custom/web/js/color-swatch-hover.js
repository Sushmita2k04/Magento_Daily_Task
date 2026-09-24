define([
    'jquery'
], function ($) {
    'use strict';

    var colorSelector = '.swatch-attribute.color .swatch-option.color';

    function getRenderer($swatch) {
        var $container = $swatch.closest('[data-role^="swatch-option-"]');

        return {
            container: $container,
            renderer: $container.data('mage-SwatchRenderer')
        };
    }

    function getColorImage($swatch, callback) {
        var data = getRenderer($swatch);
        var renderer = data.renderer;

        if (!renderer) {
            return;
        }

        var $container = data.container;
        var colorOptionId = String($swatch.data('option-id'));
        var cacheKey = 'color-image-' + colorOptionId;
        var cachedImage = $container.data(cacheKey);

        if (cachedImage) {
            callback(cachedImage);
            return;
        }

        var index = renderer.options.jsonConfig.index;
        var childProductId = null;

        $.each(index, function (productId, attributes) {
            if (String(attributes[93]) === colorOptionId) {
                childProductId = productId;
                return false;
            }
        });

        if (!childProductId) {
            return;
        }

        $.get(
            renderer.options.mediaCallback,
            {
                product_id: childProductId,
                parent_product_id: renderer.options.jsonConfig.productId
            }
        ).done(function (response) {
            if (!response || !response.medium) {
                return;
            }

            $container.data(cacheKey, response.medium);

            callback(response.medium);
        });
    }
    
    $(document).on('mouseenter', colorSelector, function () {
        var $swatch = $(this);
        var $productCard = $swatch.closest('.product-item-info');
        var $image = $productCard.find('.product-image-photo');

        if (!$image.length) {
            return;
        }

        getColorImage($swatch, function (imageUrl) {
            var preloadImage = new Image();

            preloadImage.onload = function () {
                $image.attr('src', imageUrl);
            };

            preloadImage.src = imageUrl;
        });
    });

    $(document).on('mouseleave', colorSelector, function () {
        var $swatch = $(this);
        var $productCard = $swatch.closest('.product-item-info');
        var $image = $productCard.find('.product-image-photo');

        var selectedImage = $productCard.data('selected-color-image');

        if (selectedImage) {
            $image.attr('src', selectedImage);
        }
    });

    $(document).on('click', colorSelector, function () {
        var $swatch = $(this);
        var $productCard = $swatch.closest('.product-item-info');

        getColorImage($swatch, function (imageUrl) {
            /*
             * Remember the selected color image.
             */
            $productCard.data('selected-color-image', imageUrl);

            /*
             * Keep selected color image visible.
             */
            var $image = $productCard.find('.product-image-photo');

            if ($image.length) {
                $image.attr('src', imageUrl);
            }
        });
    });
});