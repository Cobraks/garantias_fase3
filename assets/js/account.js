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

        const helpButtons = document.querySelectorAll('[data-account-help-trigger]');
        helpButtons.forEach((button) => {
            const targetId = button.getAttribute('aria-controls');
            if (!targetId) {
                return;
            }

            const help = document.getElementById(targetId);
            if (!help) {
                return;
            }

            button.addEventListener('click', () => {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                const nextState = !isExpanded;
                button.setAttribute('aria-expanded', String(nextState));

                if (nextState) {
                    help.hidden = false;
                } else {
                    help.hidden = true;
                }
            });
        });

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

        const paymentActivation = document.querySelector('[data-payment-activation]');
        if (paymentActivation) {
            const checkbox = paymentActivation.querySelector('[data-payment-toggle]');
            const detail = document.querySelector('[data-payment-detail]');
            let generated = false;
            let currentState = 'disabled';

            if (detail) {
                generated = detail.getAttribute('data-generated') === 'true';
            }

            const updatePanels = (state) => {
                currentState = state;
                paymentActivation.setAttribute('data-state', state);

                if (checkbox) {
                    const isLocked = state === 'locked' || checkbox.hasAttribute('disabled');
                    if (state === 'locked') {
                        checkbox.checked = true;
                        checkbox.disabled = true;
                    } else if (!isLocked) {
                        checkbox.disabled = false;
                        checkbox.checked = state !== 'disabled';
                    }
                }

                if (!detail) {
                    return;
                }

                detail.setAttribute('data-state', state);
                const panels = detail.querySelectorAll('[data-payment-state]');
                panels.forEach((panel) => {
                    const rawStates = panel.getAttribute('data-payment-state') || '';
                    const allowedStates = rawStates.split(/\s+/).filter(Boolean);
                    let shouldShow = allowedStates.length === 0 || allowedStates.includes(state);

                    if (panel.hasAttribute('data-payment-generated')) {
                        shouldShow = shouldShow && generated;
                    }

                    if (panel.hasAttribute('data-payment-awaiting')) {
                        shouldShow = shouldShow && !generated;
                    }

                    panel.hidden = !shouldShow;
                    panel.setAttribute('aria-hidden', String(!shouldShow));
                });
            };

            const setGenerated = (value) => {
                generated = value;
                if (detail) {
                    detail.setAttribute('data-generated', value ? 'true' : 'false');
                }
                updatePanels(currentState);
            };

            const initialState = paymentActivation.getAttribute('data-state') || 'disabled';
            currentState = initialState;
            updatePanels(initialState);

            if (checkbox) {
                checkbox.addEventListener('change', () => {
                    const nextState = checkbox.checked ? 'enabled' : 'disabled';
                    updatePanels(nextState);
                });
            }

            if (detail) {
                const generateButton = detail.querySelector('[data-payment-generate]');
                if (generateButton) {
                    generateButton.addEventListener('click', () => {
                        generateButton.disabled = true;
                        setGenerated(true);
                    });
                }
            }
        }

        const setupImageUpload = (containerId, inputId, previewId) => {
            const container = document.getElementById(containerId);
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const previewImage = preview ? preview.querySelector('img') : null;

            if (!container || !input) {
                return;
            }

            const label = container.querySelector('.file-label');
            const defaultLabel = label ? label.textContent : '';
            const initialPreviewVisible = preview ? !preview.hasAttribute('hidden') : false;
            const initialPreviewSrc = previewImage ? previewImage.getAttribute('src') : '';

            const restoreInitialPreview = () => {
                if (!preview) {
                    return;
                }

                if (initialPreviewVisible && initialPreviewSrc) {
                    preview.hidden = false;
                    preview.removeAttribute('aria-hidden');
                    if (previewImage) {
                        previewImage.src = initialPreviewSrc;
                    }
                } else {
                    preview.hidden = true;
                    preview.setAttribute('aria-hidden', 'true');
                    if (previewImage) {
                        previewImage.removeAttribute('src');
                    }
                }
            };

            const resetPreview = () => {
                restoreInitialPreview();
                if (label) {
                    label.textContent = defaultLabel;
                }
            };

            container.addEventListener('click', (event) => {
                if (event.target !== input) {
                    input.click();
                }
            });

            input.addEventListener('change', () => {
                if (!input.files || input.files.length === 0) {
                    resetPreview();
                    return;
                }

                const [file] = input.files;
                if (!file) {
                    resetPreview();
                    return;
                }

                if (label) {
                    label.textContent = file.name;
                }

                if (!preview || !previewImage || !file.type || !file.type.startsWith('image/')) {
                    return;
                }

                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    if (typeof reader.result === 'string') {
                        previewImage.src = reader.result;
                        preview.hidden = false;
                        preview.removeAttribute('aria-hidden');
                    }
                });
                reader.readAsDataURL(file);
            });
        };

        setupImageUpload('signature-upload', 'signature', 'signature-preview');
        setupImageUpload('stamp-upload', 'stamp', 'stamp-preview');
    });
})();
