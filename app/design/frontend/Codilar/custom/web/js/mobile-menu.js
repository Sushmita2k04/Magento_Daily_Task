define([], function () {
    'use strict';

    function initMobileMenu() {
        const hamburger = document.querySelector('.hamburger-menu');
        const drawer = document.querySelector('[data-role="mobile-menu-drawer"]');
        const overlay = document.querySelector('[data-role="mobile-menu-overlay"]');
        const closeButton = document.querySelector('.mobile-menu-close');

        if (!hamburger || !drawer || !overlay || !closeButton) {
            return;
        }

        if (hamburger.dataset.mobileMenuInitialized === 'true') {
            return;
        }

        hamburger.dataset.mobileMenuInitialized = 'true';

        function openMenu() {
            document.body.classList.add('mobile-menu-open');
            drawer.setAttribute('aria-hidden', 'false');
            overlay.setAttribute('aria-hidden', 'false');
            hamburger.setAttribute('aria-expanded', 'true');
        }

        function closeMenu() {
            document.body.classList.remove('mobile-menu-open');
            drawer.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('aria-hidden', 'true');
            hamburger.setAttribute('aria-expanded', 'false');
        }

        function getCategoryName(item) {
            const link = Array.from(item.children).find(function (child) {
                return child.tagName === 'A';
            });

            return link ? link.textContent.trim() : 'category';
        }

        function createSubmenuIcon(expanded) {
            const icon = document.createElementNS(
                'http://www.w3.org/2000/svg',
                'svg'
            );

            icon.setAttribute('width', '12');
            icon.setAttribute('height', '12');
            icon.setAttribute('viewBox', '0 0 12 12');
            icon.setAttribute('fill', 'none');
            icon.setAttribute('aria-hidden', 'true');

            const path = document.createElementNS(
                'http://www.w3.org/2000/svg',
                'path'
            );

            const pathData = expanded
                ? 'M0.48 5.52H11.52V6.48H0.48Z'
                : 'M5.52 0.48H6.48V5.52H11.52V6.48H6.48V11.52H5.52V6.48H0.48V5.52H5.52Z';

            path.setAttribute('d', pathData);
            path.setAttribute('fill', expanded ? '#A18455' : '#404040');

            icon.appendChild(path);

            return icon;
        }

        function setExpanded(item, toggle, submenu, expanded) {
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute(
                'aria-label',
                (expanded ? 'Collapse ' : 'Expand ') + getCategoryName(item)
            );

            toggle.replaceChildren(createSubmenuIcon(expanded));

            submenu.hidden = !expanded;
            submenu.setAttribute('aria-hidden', String(!expanded));
            item.classList.toggle('submenu-expanded', expanded);
        }

        hamburger.setAttribute('aria-expanded', 'false');

        drawer.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('aria-hidden', 'true');

        hamburger.addEventListener('click', openMenu);
        closeButton.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);

        drawer.querySelectorAll('.mobile-menu-navigation li.parent').forEach(function (item) {
            const submenu = Array.from(item.children).find(function (child) {
                return child.tagName === 'UL';
            });

            if (!submenu) {
                return;
            }

            let toggle = Array.from(item.children).find(function (child) {
                return child.classList.contains('submenu-toggle');
            });

            if (!toggle) {
                toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'submenu-toggle';
                item.insertBefore(toggle, submenu);
            }

            const hasActiveChild = Array.from(
                submenu.querySelectorAll('li')
            ).some(function (child) {
                return child.classList.contains('active')
                    || child.classList.contains('has-active');
            });

            const expanded = item.classList.contains('active')
                || item.classList.contains('has-active')
                || hasActiveChild;

            setExpanded(item, toggle, submenu, expanded);

            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const isExpanded =
                    toggle.getAttribute('aria-expanded') === 'true';

                setExpanded(item, toggle, submenu, !isExpanded);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
});
