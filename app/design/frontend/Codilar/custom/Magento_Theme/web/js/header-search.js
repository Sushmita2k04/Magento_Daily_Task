define([
    'jquery'
], function ($) {
    'use strict';

    return function () {

        $('.header-actions .block-search').each(function () {

            var $search = $(this);
            var $button = $search.find('.action.search');
            var $input = $search.find('input[name="q"]');

            if (!$button.length || !$input.length) {
                return;
            }

            $button.on('click.codilarSearch', function (event) {

                if (!$search.hasClass('is-open')) {

                    event.preventDefault();

                    $search.addClass('is-open');

                    $input
                        .show()
                        .focus();
                }

            });

        });

    };
});