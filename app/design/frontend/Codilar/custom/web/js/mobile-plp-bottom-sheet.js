define(['jquery'], function ($) {
    'use strict';

    return function () {
        var $body = $('body');
        var $sortButton = $('.mobile-plp-bottom-bar__sort');
        var $filterButton = $('.mobile-plp-bottom-bar__filter');
        var $sorter = $('[data-role="sorter"]').first();
        var $filter = $('.sidebar-main .block.filter').first();
        var $originalParent;
        var $originalNext;
        var $sheet;
        var $overlay;

        if (!$sortButton.length || !$filterButton.length) {
            return;
        }

        $overlay = $('<div class="mobile-plp-sheet-overlay"></div>');
        $sheet = $('<section class="mobile-plp-sheet" aria-modal="true" role="dialog"></section>');

        $body.append($overlay, $sheet);

        function closeSheet() {
            $sheet.removeClass('is-open').empty();
            $overlay.removeClass('is-open');

            if ($originalParent && $filter.length) {
                if ($originalNext && $originalNext.parent().is($originalParent)) {
                    $filter.insertBefore($originalNext);
                } else {
                    $filter.appendTo($originalParent);
                }

                $originalParent = null;
                $originalNext = null;
            }

            $body.removeClass('mobile-plp-sheet-open');
        }

        function openSortSheet() {
            if (!$sorter.length) {
                return;
            }

            $sheet.empty();

            var $header = $('<div class="mobile-plp-sheet__header"><span>SORT BY</span><button type="button" class="mobile-plp-sheet__close" aria-label="Close">×</button></div>');
            var $options = $('<div class="mobile-plp-sheet__options"></div>');

            $sorter.find('option').each(function () {
                var $option = $(this);
                var value = $option.val();
                var selected = $option.is(':selected');
                var $label = $('<label class="mobile-plp-sheet__option"></label>');
                var $radio = $('<input type="radio" name="mobile-plp-sort">');

                $radio.val(value).prop('checked', selected);
                $label.append($('<span></span>').text($option.text()), $radio);
                $options.append($label);
            });

            $sheet.append($header, $options);
            openSheet();

            $sheet.find('input[name="mobile-plp-sort"]').on('change', function () {
                $sorter.val($(this).val()).trigger('change');
                closeSheet();
            });
        }

        function openFilterSheet() {
            if (!$filter.length) {
                return;
            }

            $sheet.empty();

            var $header = $('<div class="mobile-plp-sheet__header"><span>FILTERS</span><button type="button" class="mobile-plp-sheet__close" aria-label="Close">×</button></div>');
            var $content = $('<div class="mobile-plp-sheet__content"></div>');
            var $footer = $('<div class="mobile-plp-sheet__footer"><button type="button" class="mobile-plp-sheet__results">Show Results</button></div>');

            $originalParent = $filter.parent();
            $originalNext = $filter.next();

            $filter.find('.filter-title strong').show();
            $content.append($filter);
            $sheet.append($header, $content, $footer);

            openSheet();
        }

        function openSheet() {
            $overlay.addClass('is-open');
            $sheet.addClass('is-open');
            $body.addClass('mobile-plp-sheet-open');
        }

        $sortButton.on('click', openSortSheet);
        $filterButton.on('click', openFilterSheet);

        $overlay.on('click', closeSheet);

        $sheet.on('click', '.mobile-plp-sheet__close', closeSheet);

        $sheet.on('click', '.mobile-plp-sheet__results', closeSheet);

        $sheet.on('click', '.filter-options-title', function () {
            $(this).closest('.filter-options-item').toggleClass('active');
        });

        $(document).on('keydown.mobilePlpSheet', function (event) {
            if (event.key === 'Escape') {
                closeSheet();
            }
        });
    };
});
