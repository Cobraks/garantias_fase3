(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const nav = document.querySelector('.account-page__nav');
        if (!nav) {
            return;
        }

        const links = Array.from(nav.querySelectorAll('a[data-target]'));
        if (!links.length) {
            return;
        }

        const sections = links
            .map((link) => document.getElementById(link.getAttribute('data-target')))
            .filter(Boolean);

        const setActive = (id) => {
            links.forEach((link) => {
                if (link.getAttribute('data-target') === id) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        };

        links.forEach((link) => {
            link.addEventListener('click', (event) => {
                const targetId = link.getAttribute('data-target');
                const section = document.getElementById(targetId);
                if (!section) {
                    return;
                }

                event.preventDefault();
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setActive(targetId);
                window.history.replaceState(null, '', '#' + targetId);

                window.setTimeout(() => {
                    if (typeof section.focus === 'function') {
                        section.focus({ preventScroll: true });
                    }
                }, 350);
            });
        });

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

                if (visible.length) {
                    setActive(visible[0].target.id);
                }
            }, {
                rootMargin: '-45% 0px -45%',
            });

            sections.forEach((section) => observer.observe(section));
        }

        const hash = window.location.hash.replace('#', '');
        if (hash) {
            const exists = sections.find((section) => section.id === hash);
            if (exists) {
                setActive(hash);
            }
        } else if (sections[0]) {
            setActive(sections[0].id);
        }
    });
})();
