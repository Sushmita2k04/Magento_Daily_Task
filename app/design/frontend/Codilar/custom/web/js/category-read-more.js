define([], function () {
    'use strict';

    return function (config, element) {
        var content = element.querySelector('[data-role="description-content"]'),
            toggle = element.querySelector('[data-role="description-toggle"]'),
            label = element.querySelector('[data-role="description-toggle-label"]'),
            moreText = label.textContent.trim(),
            lessText = 'Read Less';

        if (!content || !toggle) {
            return;
        }

        // Only offer the toggle when the text is actually clipped.
        if (content.scrollHeight <= content.clientHeight + 1) {
            element.classList.add('is-expanded');

            return;
        }

        toggle.hidden = false;
        toggle.addEventListener('click', function () {
            var expanded = element.classList.toggle('is-expanded');

            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            label.textContent = expanded ? lessText : moreText;
        });
    };
});
