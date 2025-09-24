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

        const notificationsCard = document.querySelector('[data-notifications-card]');
        if (notificationsCard) {
            const requestButton = notificationsCard.querySelector('[data-notifications-request]');
            const statusElement = notificationsCard.querySelector('[data-notifications-status]');
            const statusClasses = [
                'account-status--info',
                'account-status--success',
                'account-status--warning',
                'account-status--error',
            ];

            const setStatus = (message, variant = 'info') => {
                if (!statusElement) {
                    return;
                }

                statusElement.textContent = message;
                statusElement.classList.remove(...statusClasses);
                if (!statusElement.classList.contains('account-status')) {
                    statusElement.classList.add('account-status');
                }
                const className = `account-status--${variant}`;
                if (statusClasses.includes(className)) {
                    statusElement.classList.add(className);
                }
            };

            const syncPermission = () => {
                if (!statusElement) {
                    return;
                }

                if (!('Notification' in window)) {
                    setStatus('Tu navegador no admite notificaciones push.', 'error');
                    if (requestButton) {
                        requestButton.disabled = true;
                    }
                    return;
                }

                switch (Notification.permission) {
                    case 'granted':
                        setStatus('Las notificaciones del navegador están activas en este dispositivo.', 'success');
                        if (requestButton) {
                            requestButton.disabled = true;
                        }
                        break;
                    case 'denied':
                        setStatus('Has bloqueado las notificaciones en tu navegador.', 'warning');
                        if (requestButton) {
                            requestButton.disabled = true;
                        }
                        break;
                    default:
                        setStatus('Puedes activar las notificaciones para recibir avisos inmediatos.', 'info');
                        if (requestButton) {
                            requestButton.disabled = false;
                        }
                        break;
                }
            };

            syncPermission();

            if (requestButton) {
                requestButton.addEventListener('click', () => {
                    if (!('Notification' in window)) {
                        syncPermission();
                        return;
                    }

                    requestButton.disabled = true;

                    Promise.resolve(Notification.requestPermission())
                        .then((permission) => {
                            if (permission === 'granted') {
                                setStatus('Has activado las notificaciones en este navegador.', 'success');
                            } else if (permission === 'denied') {
                                setStatus('Has bloqueado las notificaciones para esta página.', 'warning');
                            } else {
                                setStatus('Aún no has decidido si quieres recibir notificaciones.', 'info');
                            }

                            syncPermission();
                        })
                        .catch(() => {
                            setStatus('No se han podido actualizar los permisos. Inténtalo de nuevo.', 'error');
                            syncPermission();
                        });
                });
            }
        }
    });
})();
