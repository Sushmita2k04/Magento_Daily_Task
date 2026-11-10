define([
    'jquery',
    'mage/translate',
    'Magento_Catalog/js/price-utils',
    'mage/cookies'
], function ($, $t, priceUtils) {
    'use strict';

    // Amounts are handled in minor units to avoid floating point drift. The server recomputes the real price.
    var toMinor = function (v) { return Math.round(parseFloat(v) * 100); };

    return function (config, element) {
        var $root = $(element),
            state = { coffee: [], equipment: null },
            submitting = false,
            $add = $root.find('[data-role=add]'),
            $messages = $root.find('[data-role=messages]'),
            $message = $root.find('[data-role=message]'),
            $buttons = $root.find('.byob-card-btn'),
            format = function (minor) {
                return priceUtils.formatPrice(minor / 100, config.priceFormat);
            },
            codePoints = function (s) { return Array.from(s); },
            find = function (role) { return $root.find('[data-role=' + role + ']'); };

        function priceOf(group, id) {
            var $b = $buttons.filter('[data-byob-group=' + group + '][data-byob-id=' + id + ']');
            return toMinor($b.data('byob-price'));
        }

        function showMessage(text, isError) {
            $messages.empty().append($('<div/>', { 'class': 'message ' + (isError ? 'error' : 'success') })
                .append($('<div/>').text(text)));
        }

        function render() {
            var remaining = config.coffeeCount - state.coffee.length,
                coffeeSub = 0, equipSub = 0, subtotal, discount, len;

            state.coffee.forEach(function (id) { coffeeSub += priceOf('coffee', id); });
            if (state.equipment) { equipSub = priceOf('equipment', state.equipment); }
            subtotal = coffeeSub + equipSub;
            discount = Math.min(toMinor(config.discount), subtotal);

            find('coffee-counter').text(
                $t('%1 of %2 selected').replace('%1', state.coffee.length).replace('%2', config.coffeeCount) + ' - ' +
                (remaining > 0 ? $t('%1 remaining').replace('%1', remaining) : $t('your coffee selection is complete'))
            );
            find('coffee-subtotal').text(format(coffeeSub));
            find('equipment-subtotal').text(format(equipSub));
            find('subtotal').text(format(subtotal));
            find('discount').text(discount ? '-' + format(discount) : format(0));
            find('final').text(format(subtotal - discount));

            len = codePoints($message.val()).length;
            find('message-counter').text(
                $t('%1 characters remaining').replace('%1', config.maxMessageLength - len)
            );

            $buttons.each(function () {
                var $b = $(this), id = parseInt($b.data('byob-id'), 10), group = $b.data('byob-group'),
                    selected = group === 'coffee' ? state.coffee.indexOf(id) !== -1 : state.equipment === id,
                    unavailable = $b.closest('.byob-card').hasClass('is-unavailable'),
                    blocked = group === 'coffee' && !selected && remaining <= 0;

                $b.attr('aria-pressed', selected ? 'true' : 'false')
                    .prop('disabled', unavailable || blocked)
                    .closest('.byob-card').toggleClass('is-selected', selected);
                $b.find('[data-role=state]').text(selected ? $t('Selected - click to remove') : $t('Select'));
            });

            $add.prop('disabled', remaining !== 0 || submitting);
        }

        $buttons.on('click', function () {
            var $b = $(this), id = parseInt($b.data('byob-id'), 10), group = $b.data('byob-group'), i;

            $messages.empty();
            if (group === 'coffee') {
                i = state.coffee.indexOf(id);
                if (i !== -1) {
                    state.coffee.splice(i, 1);
                } else if (state.coffee.length < config.coffeeCount) {
                    state.coffee.push(id);
                }
            } else {
                state.equipment = state.equipment === id ? null : id;
            }
            render();
        });

        $message.on('input', function () {
            var chars = codePoints($message.val());

            if (chars.length > config.maxMessageLength) {
                $message.val(chars.slice(0, config.maxMessageLength).join(''));
            }
            render();
        });

        $add.on('click', function () {
            if (submitting || state.coffee.length !== config.coffeeCount) { return; }
            if (/[<>]/.test($message.val())) {
                showMessage($t('The gift message cannot contain HTML (the < and > characters).'), true);
                return;
            }
            submitting = true;
            $add.find('span').text($t('Adding...'));
            render();

            $.ajax({
                url: config.addUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: $.mage.cookies.get('form_key'),
                    coffee: state.coffee,
                    equipment: state.equipment || '',
                    message: $message.val()
                }
            }).done(function (res) {
                showMessage(res.message, false);
                window.location.href = res.redirect;
            }).fail(function (xhr) {
                var res = xhr.responseJSON;

                showMessage(res && res.message ? res.message : $t('We could not add your box. Please try again.'), true);
                submitting = false;
                $add.find('span').text($t('Add Box to Cart'));
                render();
            });
        });

        render();
    };
});
