
define([
    'jquery',
    'mage/apply/main'
], function ($, mage) {
    'use strict';

    var isLoading = false;

    function getCategorySection() {
        return $('.block.filter .filter-options-item').filter(function () {
            return $.trim(
                $(this).find('.filter-options-title').first().text()
            ).toLowerCase() === 'category';
        });
    }

    function getSelectedCategories() {
        var params = new URL(window.location.href).searchParams;

        return (params.get('cat') || '')
            .split(',')
            .filter(Boolean);
    }

    function toggleCategoryUrl(clickedUrl) {
        var currentUrl = new URL(window.location.href);
        var clickedUrlObject = new URL(clickedUrl, window.location.origin);
        var clickedId = clickedUrlObject.searchParams.get('cat');
        var selected = getSelectedCategories();

        if (!clickedId) {
            return clickedUrl;
        }

        if (selected.indexOf(clickedId) !== -1) {
            selected = selected.filter(function (id) {
                return id !== clickedId;
            });
        } else {
            selected.push(clickedId);
        }

        if (selected.length) {
            currentUrl.searchParams.set('cat', selected.join(','));
        } else {
            currentUrl.searchParams.delete('cat');
        }

        currentUrl.searchParams.delete('p');
        currentUrl.searchParams.delete('_');

        return currentUrl.toString();
    }

    function syncCategorySelection() {
        var selected = getSelectedCategories();

        getCategorySection().find('.filter-option').each(function () {
            var $link = $(this);
            var href = $link.attr('href');

            if (!href) {
                return;
            }

            var linkUrl = new URL(href, window.location.origin);
            var id = linkUrl.searchParams.get('cat');

            if (!id) {
                return;
            }

            var active = selected.indexOf(id) !== -1;
            var $checkbox = $link.find('.filter-checkbox-box');

            $link.toggleClass('selected', active);
            $link.attr('aria-current', active ? 'true' : 'false');

            if (active) {
                if (!$checkbox.find('.filter-checkbox-check').length) {
                    $checkbox.html(
                        '<span class="filter-checkbox-check">✓</span>'
                    );
                }
            } else {
                $checkbox.empty();
            }
        });
    }

    function updateContent(url, pushState) {
        if (isLoading) {
            return;
        }

        isLoading = true;
        $('body').addClass('filter-loading');

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'html',
            cache: false
        }).done(function (response) {
            var $response = $('<div>').append($.parseHTML(response));

            var $newState = $response.find('.filter-current').first();
            var $newProducts = $response.find(
                '.products.wrapper.products-grid, .products-grid'
            ).first();
            var $newToolbar = $response.find('.toolbar-products').first();

            var $currentState = $('.filter-current').first();
            var $currentProducts = $(
                '.products.wrapper.products-grid, .products-grid'
            ).first();
            var $currentToolbar = $('.toolbar-products').first();

            if ($newState.length && $currentState.length) {
                $currentState.replaceWith($newState);
            } else if ($newState.length && !$currentState.length) {
                $('.block.filter .block-content.filter-content')
                    .before($newState);
            } else if (!$newState.length && $currentState.length) {
                $currentState.remove();
            }

            if ($newProducts.length && $currentProducts.length) {
                $currentProducts.replaceWith($newProducts);
            }

            if ($newToolbar.length && $currentToolbar.length) {
                $currentToolbar.replaceWith($newToolbar);
            }

            if (pushState) {
                window.history.pushState({ url: url }, '', url);
            }

            mage.apply();
            syncCategorySelection();
        }).fail(function (xhr, status, error) {
            console.error('Layered filter AJAX failed:', status, error);
        }).always(function () {
            isLoading = false;
            $('body').removeClass('filter-loading');
        });
    }

    window.codilarUpdateContent = updateContent;

    $(document).on(
        'click',
        '.codilar-filter-options .items .filter-option',
        function (event) {
            event.preventDefault();

            var href = $(this).attr('href');

            if (href) {
                updateContent(toggleCategoryUrl(href), true);
            }
        }
    );

    $(document).on('click', '.codilar-category-parent', function (event) {
        event.preventDefault();

        var href = $(this).attr('href');

        if (href) {
            updateContent(href, true);
        }
    });

    window.addEventListener('popstate', function () {
        updateContent(window.location.href, false);
    });

    syncCategorySelection();
});
