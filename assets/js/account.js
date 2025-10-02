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

            const closeButton = help.querySelector('[data-account-help-dismiss]');

            const toggleHelp = (show) => {
                const nextState = Boolean(show);
                button.setAttribute('aria-expanded', String(nextState));
                help.hidden = !nextState;
                help.classList.toggle('account-help--visible', nextState);
            };

            button.addEventListener('click', () => {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                toggleHelp(!isExpanded);
            });

            if (closeButton) {
                closeButton.addEventListener('click', () => {
                    toggleHelp(false);
                    if (typeof button.focus === 'function') {
                        try {
                            button.focus({ preventScroll: true });
                        } catch (error) {
                            button.focus();
                        }
                    }
                });
            }
        });

        const saveButton = document.querySelector('[data-account-save]');
        const accountPage = document.querySelector('.account-page');
        const statusElement = document.querySelector('[data-account-status]');
        const statusVariants = [
            'account-status--info',
            'account-status--success',
            'account-status--warning',
            'account-status--error',
        ];
        const accountConfig = window.go360Account || {};
        const restEndpoint = accountConfig.rest && accountConfig.rest.endpoint ? accountConfig.rest.endpoint : '';
        const restNonce = accountConfig.rest && accountConfig.rest.nonce ? accountConfig.rest.nonce : '';
        const strings = accountConfig.strings || {};
        const workshopToggle = document.querySelector('[data-workshop-toggle]');
        const workshopFieldKeys = [
            'name',
            'fiscal_name',
            'tax_id',
            'contact_person',
            'phone',
            'email',
            'address',
        ];
        let workshopVisibilityUpdater = null;

        const setStatus = (message, variant = 'info') => {
            if (!statusElement) {
                return;
            }

            const text = typeof message === 'string' ? message.trim() : '';

            statusElement.classList.remove(...statusVariants);

            if (text === '') {
                statusElement.textContent = '';
                statusElement.hidden = true;
                statusElement.setAttribute('aria-hidden', 'true');
                return;
            }

            statusElement.textContent = text;
            statusElement.hidden = false;
            statusElement.setAttribute('aria-hidden', 'false');

            const className = `account-status--${variant}`;
            if (statusVariants.includes(className)) {
                statusElement.classList.add(className);
            } else {
                statusElement.classList.add('account-status--info');
            }
        };

        setStatus('', 'info');

        const setSaveDisabled = (disabled) => {
            if (!saveButton) {
                return;
            }

            const method = disabled ? 'add' : 'remove';
            saveButton.classList[method]('disabled');

            if (disabled) {
                saveButton.setAttribute('disabled', 'disabled');
                saveButton.setAttribute('aria-disabled', 'true');
            } else {
                saveButton.removeAttribute('disabled');
                saveButton.setAttribute('aria-disabled', 'false');
            }
        };

        const enableSaveButton = () => {
            if (!saveButton) {
                return;
            }

            const wasDisabled = saveButton.classList.contains('disabled');
            setSaveDisabled(false);

            if (wasDisabled && strings.dirty) {
                setStatus(strings.dirty, 'info');
            }
        };

        if (saveButton) {
            setSaveDisabled(saveButton.classList.contains('disabled') || saveButton.hasAttribute('disabled'));
        }

        if (saveButton && accountPage) {
            const maybeEnableSave = (event) => {
                const target = event.target;
                if (!target) {
                    return;
                }

                const tagName = target.tagName;
                const isTextControl = tagName === 'TEXTAREA';
                const isSelect = tagName === 'SELECT';
                let isInput = false;

                if (tagName === 'INPUT') {
                    const type = (target.getAttribute('type') || '').toLowerCase();
                    if (['button', 'submit', 'reset'].includes(type)) {
                        return;
                    }
                    isInput = true;
                }

                if (!isInput && !isSelect && !isTextControl) {
                    return;
                }

                if (saveButton.classList.contains('disabled')) {
                    enableSaveButton();
                }
            };

            ['change', 'input'].forEach((eventName) => {
                accountPage.addEventListener(eventName, maybeEnableSave, true);
            });
        }

        if (workshopToggle) {
            const details = document.querySelector('[data-workshop-details]');
            const updateWorkshopVisibility = () => {
                const isChecked = workshopToggle.checked;
                workshopToggle.setAttribute('aria-expanded', String(isChecked));

                if (!details) {
                    return;
                }

                details.hidden = !isChecked;

                if (isChecked) {
                    details.removeAttribute('aria-hidden');
                } else {
                    details.setAttribute('aria-hidden', 'true');
                }
            };

            workshopVisibilityUpdater = updateWorkshopVisibility;
            updateWorkshopVisibility();
            workshopToggle.addEventListener('change', updateWorkshopVisibility);
        }

        const collectNotificationsPayload = () => {
            const input = document.getElementById('account-notification-email');
            const rawValue = input && typeof input.value === 'string' ? input.value.trim() : '';

            return {
                email: rawValue,
                use_registration: rawValue === '',
            };
        };

        const collectWorkshopPayload = () => {
            const hasWorkshop = workshopToggle ? workshopToggle.checked : false;
            const values = {};

            workshopFieldKeys.forEach((key) => {
                const field = document.querySelector(`[name="account_workshop[${key}]"]`);
                if (!field || typeof field.value !== 'string') {
                    values[key] = '';
                    return;
                }

                values[key] = field.value.trim();
            });

            return Object.assign({ has_workshop: hasWorkshop }, values);
        };

        if (saveButton) {
            saveButton.addEventListener('click', (event) => {
                event.preventDefault();

                if (saveButton.classList.contains('disabled')) {
                    return;
                }

                if (!restEndpoint || !restNonce) {
                    const fallback = strings.error || strings.invalid || 'No se han podido guardar los cambios.';
                    setStatus(fallback, 'error');
                    return;
                }

                const payload = {
                    notifications: collectNotificationsPayload(),
                    workshop: collectWorkshopPayload(),
                };

                setSaveDisabled(true);
                saveButton.setAttribute('aria-busy', 'true');
                setStatus(strings.saving || 'Guardando cambios…', 'info');

                fetch(restEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce,
                    },
                    body: JSON.stringify(payload),
                })
                    .then((response) => {
                        if (!response.ok) {
                            return response
                                .json()
                                .catch(() => ({}))
                                .then((data) => {
                                    const message = data && typeof data.message === 'string' && data.message !== ''
                                        ? data.message
                                        : (strings.error || strings.invalid || 'No se han podido guardar los cambios.');
                                    const error = new Error(message);
                                    error.code = data && data.code ? data.code : 'error';
                                    throw error;
                                });
                        }

                        return response.json();
                    })
                    .then((data) => {
                        if (!data || data.success !== true) {
                            throw new Error(strings.error || 'No se han podido guardar los cambios.');
                        }

                        if (data.notifications) {
                            const notificationInput = document.getElementById('account-notification-email');
                            if (notificationInput) {
                                const useRegistration = Boolean(data.notifications.use_registration);
                                const nextValue = useRegistration ? '' : (data.notifications.email || '');
                                notificationInput.value = nextValue;
                            }
                        }

                        if (data.workshop) {
                            const hasWorkshop = Boolean(data.workshop.has_workshop);
                            if (workshopToggle) {
                                workshopToggle.checked = hasWorkshop;
                            }

                            workshopFieldKeys.forEach((key) => {
                                const field = document.querySelector(`[name="account_workshop[${key}]"]`);
                                if (!field) {
                                    return;
                                }

                                const nextValue = data.workshop[key];
                                field.value = typeof nextValue === 'string' ? nextValue : '';
                            });

                            if (typeof workshopVisibilityUpdater === 'function') {
                                workshopVisibilityUpdater();
                            }
                        }

                        setStatus(strings.success || 'Cambios guardados correctamente.', 'success');
                    })
                    .catch((error) => {
                        const message = error && typeof error.message === 'string' && error.message !== ''
                            ? error.message
                            : (strings.error || strings.invalid || 'No se han podido guardar los cambios.');
                        setStatus(message, 'error');
                        setSaveDisabled(false);
                    })
                    .finally(() => {
                        if (saveButton) {
                            saveButton.removeAttribute('aria-busy');
                        }
                    });
            });
        }

        const profileUploadButton = document.querySelector('[data-profile-upload]');
        if (profileUploadButton) {
            const inputId = profileUploadButton.getAttribute('data-profile-upload');
            const fileInput = inputId ? document.getElementById(inputId) : null;

            if (fileInput) {
                profileUploadButton.addEventListener('click', () => {
                    fileInput.click();
                });
            }
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

        const notificationsRepeater = document.querySelector('[data-notification-repeater]');
        if (notificationsRepeater) {
            const rowsContainer = notificationsRepeater.querySelector('[data-repeater-rows]');
            const template = notificationsRepeater.querySelector('template[data-repeater-template]');
            const addButton = notificationsRepeater.querySelector('[data-repeater-add]');
            let nextIndex = parseInt(notificationsRepeater.getAttribute('data-next-index') || '0', 10);

            if (Number.isNaN(nextIndex)) {
                nextIndex = 0;
            }

            const updateRemoveState = () => {
                if (!rowsContainer) {
                    return;
                }

                const rows = rowsContainer.querySelectorAll('[data-repeater-row]');
                rows.forEach((row) => {
                    const removeButton = row.querySelector('[data-repeater-remove]');
                    if (!removeButton) {
                        return;
                    }
                    const disabled = rows.length <= 1;
                    removeButton.disabled = disabled;
                    removeButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                });
            };

            const bindRow = (row) => {
                if (!row) {
                    return;
                }

                const removeButton = row.querySelector('[data-repeater-remove]');
                if (removeButton) {
                    removeButton.addEventListener('click', () => {
                        if (!rowsContainer) {
                            return;
                        }
                        const rows = rowsContainer.querySelectorAll('[data-repeater-row]');
                        if (rows.length <= 1) {
                            return;
                        }
                        row.remove();
                        updateRemoveState();
                        enableSaveButton();
                    });
                }
            };

            const createRow = (data = {}) => {
                if (!rowsContainer || !template) {
                    return null;
                }

                const index = nextIndex;
                nextIndex += 1;

                const html = template.innerHTML.replace(/__index__/g, String(index));
                const fragment = document.createElement('div');
                fragment.innerHTML = html.trim();
                const row = fragment.firstElementChild;

                if (!row) {
                    return null;
                }

                const emailInput = row.querySelector('[data-repeater-email]');
                if (emailInput && data.email) {
                    emailInput.value = data.email;
                }

                const bccInput = row.querySelector('[data-repeater-bcc]');
                if (bccInput) {
                    bccInput.checked = Boolean(data.bcc);
                }

                rowsContainer.appendChild(row);
                bindRow(row);
                updateRemoveState();
                enableSaveButton();

                window.requestAnimationFrame(() => {
                    if (emailInput && typeof emailInput.focus === 'function') {
                        try {
                            emailInput.focus({ preventScroll: true });
                        } catch (error) {
                            emailInput.focus();
                        }
                    }
                });

                return row;
            };

            if (rowsContainer) {
                const existingRows = rowsContainer.querySelectorAll('[data-repeater-row]');
                existingRows.forEach((row) => bindRow(row));
                updateRemoveState();
            }

            if (addButton) {
                addButton.addEventListener('click', () => {
                    createRow();
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
                    panel.style.display = shouldShow ? '' : 'none';
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

        const formatBytes = (bytes) => {
            if (typeof bytes !== 'number' || Number.isNaN(bytes) || bytes <= 0) {
                return '';
            }

            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let value = bytes;
            let unitIndex = 0;

            while (value >= 1024 && unitIndex < units.length - 1) {
                value /= 1024;
                unitIndex += 1;
            }

            const decimals = value < 10 && unitIndex > 0 ? 1 : 0;
            return `${value.toFixed(decimals)} ${units[unitIndex]}`;
        };

        const documentUploads = document.querySelectorAll('[data-document-upload]');
        documentUploads.forEach((container) => {
            const input = container.querySelector('input[type="file"]');
            const label = container.querySelector('[data-document-label]');
            const body = container.querySelector('[data-document-body]');
            const nameElement = container.querySelector('[data-document-name]');
            const sizeElement = container.querySelector('[data-document-size]');
            const linkElement = container.querySelector('[data-document-link]');
            const placeholder = container.querySelector('[data-document-placeholder]');
            const removeButton = container.querySelector('[data-document-remove]');
            const defaultLabel = container.dataset.defaultLabel || (label ? label.textContent : '') || '';

            const initialLabel = label ? label.textContent : defaultLabel;
            const initialSize = sizeElement && !sizeElement.hasAttribute('hidden') ? sizeElement.textContent : '';
            const initialUrl = linkElement && !linkElement.hasAttribute('hidden') ? linkElement.getAttribute('href') : '';
            const initialLinkText = linkElement ? linkElement.textContent : '';
            const initialHasDocument = body ? !body.hasAttribute('hidden') : false;

            const state = {
                hasDocument: initialHasDocument,
                label: initialLabel,
                size: initialSize,
                url: initialUrl,
                linkText: initialLinkText || 'Ver documento',
            };

            let temporaryUrl = '';

            const revokeTemporaryUrl = () => {
                if (temporaryUrl) {
                    try {
                        URL.revokeObjectURL(temporaryUrl);
                    } catch (error) {
                        // Ignorado
                    }
                    temporaryUrl = '';
                }
            };

            const renderState = () => {
                if (label) {
                    label.textContent = state.hasDocument ? state.label : defaultLabel;
                }

                if (nameElement) {
                    nameElement.textContent = state.hasDocument ? state.label : defaultLabel;
                }

                if (body) {
                    body.hidden = !state.hasDocument;
                    body.setAttribute('aria-hidden', state.hasDocument ? 'false' : 'true');
                }

                if (sizeElement) {
                    const showSize = state.hasDocument && state.size !== '';
                    sizeElement.hidden = !showSize;
                    sizeElement.setAttribute('aria-hidden', showSize ? 'false' : 'true');
                    sizeElement.textContent = showSize ? state.size : '';
                }

                if (linkElement) {
                    const hasLink = state.hasDocument && state.url !== '';
                    if (hasLink) {
                        linkElement.href = state.url;
                        linkElement.textContent = state.linkText;
                    } else {
                        linkElement.removeAttribute('href');
                        linkElement.textContent = initialLinkText || 'Ver documento';
                    }
                    linkElement.hidden = !hasLink;
                    linkElement.setAttribute('aria-hidden', hasLink ? 'false' : 'true');
                }

                if (placeholder) {
                    placeholder.hidden = state.hasDocument;
                    placeholder.setAttribute('aria-hidden', state.hasDocument ? 'true' : 'false');
                }

                if (removeButton) {
                    removeButton.hidden = !state.hasDocument;
                }
            };

            renderState();

            if (container && input) {
                container.addEventListener('click', (event) => {
                    if (!input) {
                        return;
                    }

                    const target = event.target;
                    if (target === input) {
                        return;
                    }

                    if (removeButton && (target === removeButton || removeButton.contains(target))) {
                        return;
                    }

                    input.click();
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    revokeTemporaryUrl();
                    state.hasDocument = false;
                    state.label = defaultLabel;
                    state.size = '';
                    state.url = '';
                    state.linkText = initialLinkText || 'Ver documento';
                    if (input) {
                        input.value = '';
                    }
                    renderState();
                    enableSaveButton();
                });
            }

            if (input) {
                input.addEventListener('change', () => {
                    if (!input.files || input.files.length === 0) {
                        renderState();
                        return;
                    }

                    const [file] = input.files;
                    if (!file) {
                        renderState();
                        return;
                    }

                    revokeTemporaryUrl();

                    let formattedSize = '';
                    if (typeof file.size === 'number') {
                        formattedSize = formatBytes(file.size);
                    }

                    temporaryUrl = URL.createObjectURL(file);

                    state.hasDocument = true;
                    state.label = file.name || defaultLabel;
                    state.size = formattedSize;
                    state.url = temporaryUrl;
                    state.linkText = 'Previsualizar';

                    renderState();
                    enableSaveButton();
                });
            }
        });

        const setupImageUpload = (containerId, inputId, previewId) => {
            const container = document.getElementById(containerId);
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const previewImage = preview ? preview.querySelector('img') : null;

            if (!container || !input) {
                return;
            }

            const label = container.querySelector('.file-label');
            const removeButton = container.querySelector('[data-file-remove]');
            const initialLabel = label ? label.textContent : '';
            const defaultLabel = container.dataset.defaultLabel || initialLabel;
            const initialPreviewVisible = preview ? !preview.hasAttribute('hidden') : false;
            const initialPreviewSrc = previewImage ? previewImage.getAttribute('src') : '';
            let allowRestoreInitial = initialPreviewVisible && Boolean(initialPreviewSrc);

            const setPreviewVisible = (visible, src = '') => {
                if (!preview) {
                    return;
                }

                if (visible && src) {
                    preview.hidden = false;
                    preview.removeAttribute('aria-hidden');
                    if (previewImage) {
                        previewImage.src = src;
                    }
                } else {
                    preview.hidden = true;
                    preview.setAttribute('aria-hidden', 'true');
                    if (previewImage) {
                        if (src) {
                            previewImage.src = src;
                        } else {
                            previewImage.removeAttribute('src');
                        }
                    }
                }
            };

            const updateRemoveVisibility = (visible) => {
                if (!removeButton) {
                    return;
                }

                removeButton.hidden = !visible;
            };

            const resetPreview = (options = {}) => {
                const restoreInitial = options.restoreInitial !== false;

                if (restoreInitial && allowRestoreInitial && initialPreviewVisible && initialPreviewSrc) {
                    setPreviewVisible(true, initialPreviewSrc);
                    updateRemoveVisibility(true);
                    if (label) {
                        label.textContent = initialLabel || defaultLabel;
                    }
                } else {
                    setPreviewVisible(false);
                    updateRemoveVisibility(false);
                    if (label) {
                        label.textContent = defaultLabel;
                    }
                }
            };

            container.addEventListener('click', (event) => {
                if (removeButton && event.target && event.target.closest('[data-file-remove]')) {
                    return;
                }

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

                allowRestoreInitial = false;

                if (label) {
                    label.textContent = file.name;
                }

                if (!preview || !previewImage || !file.type || !file.type.startsWith('image/')) {
                    updateRemoveVisibility(true);
                    return;
                }

                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    if (typeof reader.result === 'string') {
                        setPreviewVisible(true, reader.result);
                        updateRemoveVisibility(true);
                    }
                });
                reader.readAsDataURL(file);
            });

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    allowRestoreInitial = false;
                    input.value = '';
                    resetPreview({ restoreInitial: false });
                    const inputEvent = new Event('input', { bubbles: true });
                    input.dispatchEvent(inputEvent);
                });

                updateRemoveVisibility(!removeButton.hasAttribute('hidden'));
            }

            if (!initialPreviewVisible) {
                setPreviewVisible(false);
            }
        };

        setupImageUpload('signature-upload', 'signature', 'signature-preview');
        setupImageUpload('stamp-upload', 'stamp', 'stamp-preview');
    });
})();
