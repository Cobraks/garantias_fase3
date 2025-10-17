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
        const isAdminAccount = accountPage && accountPage.getAttribute('data-account-admin') === 'true';
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
        const sepaText = {
            awaitingValidation: strings.sepaAwaitingValidation || 'Pendiente de validación',
            awaitingSignature: strings.sepaAwaitingSignature || 'Pendiente de firma',
            awaitingMessage: strings.sepaAwaitingMessage || 'Tu SEPA firmado está pendiente de validación.',
        };
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
        const notificationsRepeater = document.querySelector('[data-notification-repeater]');
        const adminReplyInput = document.getElementById('admin-reply-to-email');
        const adminTransferInput = document.getElementById('account-transfer-iban');
        const signatureUpload = document.getElementById('signature-upload');
        const stampUpload = document.getElementById('stamp-upload');
        const certificatesWarning = document.querySelector('[data-certificates-warning]');
        const certificatesWarningText = certificatesWarning
            ? certificatesWarning.textContent.trim()
            : 'Necesitas subir tanto la firma como el sello si quieres que tus certificados se generen ya firmados.';
        let signatureHasFile = false;
        let stampHasFile = false;
        let certificatesValid = true;
        let certificatesWarningForced = false;
        let certificatesRemovalWarning = false;
        let workshopVisibilityUpdater = null;
        const documentUploadStates = {};
        const documentUploadControllers = {};

        const updateCertificatesWarningState = () => {
            const mismatch = (signatureHasFile && !stampHasFile) || (!signatureHasFile && stampHasFile);
            certificatesValid = !mismatch;

            if (!mismatch) {
                certificatesWarningForced = false;
                certificatesRemovalWarning = false;
            }

            const shouldWarn = mismatch && (certificatesWarningForced || certificatesRemovalWarning);

            if (certificatesWarning) {
                certificatesWarning.hidden = !shouldWarn;
                certificatesWarning.setAttribute('aria-hidden', shouldWarn ? 'false' : 'true');
            }

            const toggleInvalid = (element, active) => {
                if (!element) {
                    return;
                }

                element.classList.toggle('file-upload--invalid', active);
            };

            toggleInvalid(signatureUpload, shouldWarn);
            toggleInvalid(stampUpload, shouldWarn);
        };

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

        const collectAdminNotificationsPayload = () => {
            if (!isAdminAccount || (!notificationsRepeater && !adminReplyInput)) {
                return null;
            }

            const recipients = [];

            if (notificationsRepeater) {
                const rows = notificationsRepeater.querySelectorAll('[data-repeater-row]');
                rows.forEach((row) => {
                    const emailInput = row.querySelector('[data-repeater-email]');
                    const bccInput = row.querySelector('[data-repeater-bcc]');
                    const email = emailInput && typeof emailInput.value === 'string'
                        ? emailInput.value.trim()
                        : '';
                    const bcc = bccInput ? bccInput.checked : false;

                    if (email === '' && !bcc) {
                        return;
                    }

                    recipients.push({
                        email,
                        bcc,
                    });
                });
            }

            const replyTo = adminReplyInput && typeof adminReplyInput.value === 'string'
                ? adminReplyInput.value.trim()
                : '';

            return {
                recipients,
                reply_to: replyTo,
            };
        };

        const collectAdminTransferPayload = () => {
            if (!isAdminAccount || !adminTransferInput) {
                return null;
            }

            const value = typeof adminTransferInput.value === 'string'
                ? adminTransferInput.value.trim()
                : '';

            return { iban: value };
        };

        const collectAdminDocumentsPayload = () => {
            if (!isAdminAccount) {
                return null;
            }

            const entries = Object.entries(documentUploadStates)
                .filter(([, state]) => Boolean(state))
                .reduce((accumulator, [key, state]) => {
                    if (key === 'sepa_signed') {
                        return accumulator;
                    }
                    if (state && state.removed) {
                        accumulator[key] = { remove: true };
                    }
                    return accumulator;
                }, {});

            return Object.keys(entries).length > 0 ? entries : null;
        };

        const collectAdminPayload = () => {
            if (!isAdminAccount) {
                return null;
            }

            const notifications = collectAdminNotificationsPayload();
            const transfer = collectAdminTransferPayload();
            const documents = collectAdminDocumentsPayload();

            if (!notifications && !transfer && !documents) {
                return null;
            }

            const payload = {};
            if (notifications) {
                payload.notifications = notifications;
            }
            if (transfer) {
                payload.transfer = transfer;
            }
            if (documents) {
                payload.documents = documents;
            }

            return payload;
        };

        const collectPaymentsPayload = () => {
            const sepaState = documentUploadStates.sepa_signed;
            if (!sepaState) {
                return null;
            }

            const signedPayload = {};
            if (sepaState.removed) {
                signedPayload.remove = true;
            }
            if (sepaState.changed) {
                signedPayload.upload = true;
            }

            if (Object.keys(signedPayload).length === 0) {
                return null;
            }

            return {
                sepa: {
                    signed: signedPayload,
                },
            };
        };

        if (saveButton) {
            saveButton.addEventListener('click', (event) => {
                event.preventDefault();

                if (saveButton.classList.contains('disabled')) {
                    return;
                }

                if (!certificatesValid) {
                    certificatesWarningForced = true;
                    updateCertificatesWarningState();
                    setStatus(certificatesWarningText, 'warning');
                    if (signatureUpload && typeof signatureUpload.scrollIntoView === 'function') {
                        signatureUpload.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }

                if (!restEndpoint || !restNonce) {
                    const fallback = strings.error || strings.invalid || 'No se han podido guardar los cambios.';
                    setStatus(fallback, 'error');
                    return;
                }

                const notificationsPayload = collectNotificationsPayload();
                const workshopPayload = collectWorkshopPayload();
                const adminPayload = collectAdminPayload();
                const paymentsPayload = collectPaymentsPayload();
                const adminDocumentFiles = Object.entries(documentUploadStates)
                    .filter(([key]) => key !== 'sepa_signed')
                    .map(([, state]) => state)
                    .filter((state) => state && state.input && state.input.files && state.input.files.length > 0);
                const sepaUploadState = documentUploadStates.sepa_signed || null;
                const sepaHasFile = Boolean(
                    sepaUploadState
                    && sepaUploadState.input
                    && sepaUploadState.input.files
                    && sepaUploadState.input.files.length > 0
                );
                const usingFormData = (profileImageFile instanceof File)
                    || adminDocumentFiles.length > 0
                    || sepaHasFile;

                const buildFormData = () => {
                    const formData = new FormData();

                    const appendObject = (object, prefix) => {
                        Object.keys(object).forEach((key) => {
                            const value = object[key];
                            let normalized = value;

                            if (typeof value === 'boolean') {
                                normalized = value ? '1' : '0';
                            } else if (value === null || typeof value === 'undefined') {
                                normalized = '';
                            }

                            formData.append(`${prefix}[${key}]`, normalized);
                        });
                    };

                    appendObject(notificationsPayload, 'notifications');
                    appendObject(workshopPayload, 'workshop');

                    if (profileImageFile) {
                        formData.append('profile_image', profileImageFile);
                    }

                    adminDocumentFiles.forEach((state) => {
                        const { input } = state;
                        if (!input) {
                            return;
                        }
                        const name = input.getAttribute('name') || '';
                        if (!name) {
                            return;
                        }
                        const file = input.files && input.files[0] ? input.files[0] : null;
                        if (!file) {
                            return;
                        }
                        formData.append(name, file);
                    });

                    if (adminPayload) {
                        formData.append('admin', JSON.stringify(adminPayload));
                    }
                    if (paymentsPayload) {
                        formData.append('payments', JSON.stringify(paymentsPayload));
                    }

                    if (sepaHasFile && sepaUploadState && sepaUploadState.input) {
                        const sepaFile = sepaUploadState.input.files && sepaUploadState.input.files[0]
                            ? sepaUploadState.input.files[0]
                            : null;
                        if (sepaFile) {
                            formData.append('account_sepa_signed', sepaFile);
                        }
                    }

                    return formData;
                };

                const payload = usingFormData
                    ? buildFormData()
                    : JSON.stringify({
                        notifications: notificationsPayload,
                        workshop: workshopPayload,
                        ...(adminPayload ? { admin: adminPayload } : {}),
                        ...(paymentsPayload ? { payments: paymentsPayload } : {}),
                    });

                setSaveDisabled(true);
                saveButton.setAttribute('aria-busy', 'true');
                setStatus(strings.saving || 'Guardando cambios…', 'info');

                fetch(restEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': restNonce,
                        ...(usingFormData ? {} : { 'Content-Type': 'application/json' }),
                    },
                    body: payload,
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

                        if (data.profile_image) {
                            const nextSrc = typeof data.profile_image.url === 'string'
                                ? data.profile_image.url
                                : '';
                            if (nextSrc !== '') {
                                profileImageOriginalSrc = nextSrc;
                            }
                            syncProfilePreview(profileImageOriginalSrc);
                        }

                        if (profileUploadInput) {
                            profileUploadInput.value = '';
                        }

                        profileImageFile = null;
                        revokeProfilePreview();

                        if (data.admin) {
                            const adminData = data.admin;

                            if (adminData.notifications) {
                                if (adminReplyInput) {
                                    adminReplyInput.value = typeof adminData.notifications.reply_to === 'string'
                                        ? adminData.notifications.reply_to
                                        : '';
                                }

                                if (notificationsRepeater && typeof notificationsRepeater.__syncRows === 'function') {
                                    notificationsRepeater.__syncRows(Array.isArray(adminData.notifications.recipients)
                                        ? adminData.notifications.recipients
                                        : []);
                                }
                            }

                            if (adminData.transfer && adminTransferInput) {
                                adminTransferInput.value = typeof adminData.transfer.iban === 'string'
                                    ? adminData.transfer.iban
                                    : '';
                            }

                            if (adminData.documents && adminData.documents.claim_procedure) {
                                const controller = documentUploadControllers.claim_procedure;
                                if (controller && typeof controller.applyServerState === 'function') {
                                    controller.applyServerState(adminData.documents.claim_procedure);
                                }
                            }
                        }

                        if (data.payments && data.payments.sepa) {
                            const sepaData = data.payments.sepa;
                            const sepaController = documentUploadControllers.sepa_signed;
                            if (
                                sepaData.documents
                                && sepaData.documents.signed
                                && sepaController
                                && typeof sepaController.applyServerState === 'function'
                            ) {
                                sepaController.applyServerState(sepaData.documents.signed);
                            }

                            const awaitingValidation = Boolean(sepaData.awaiting_validation);
                            if (sepaController && typeof sepaController.setLocked === 'function') {
                                sepaController.setLocked(awaitingValidation);
                            }
                            const activationCard = document.querySelector('[data-payment-activation]');
                            const sepaStatusRow = activationCard
                                ? activationCard.querySelector('[data-sepa-status]')
                                : null;

                            if (sepaStatusRow) {
                                if (typeof sepaData.requested !== 'undefined') {
                                    sepaStatusRow.hidden = !sepaData.requested;
                                    sepaStatusRow.setAttribute('aria-hidden', sepaData.requested ? 'false' : 'true');
                                    sepaStatusRow.setAttribute('data-sepa-requested', sepaData.requested ? 'true' : 'false');
                                }

                                sepaStatusRow.setAttribute('data-sepa-awaiting', awaitingValidation ? 'true' : 'false');
                                sepaStatusRow.classList.toggle('account-card__status--sepa-success', awaitingValidation);

                                const statusLabel = sepaStatusRow.querySelector('[data-sepa-status-label]');
                                if (statusLabel) {
                                    let statusText = '';
                                    if (sepaData && typeof sepaData.status_label === 'string') {
                                        statusText = sepaData.status_label.trim();
                                    }
                                    if (statusText === '') {
                                        statusText = awaitingValidation
                                            ? (sepaText.awaitingValidation || 'Pendiente de validación')
                                            : (sepaText.awaitingSignature || 'Pendiente de firma');
                                    }
                                    statusLabel.textContent = statusText;
                                }
                            }

                            const awaitingMessage = document.querySelector('[data-sepa-awaiting-message]');
                            if (awaitingMessage) {
                                awaitingMessage.hidden = !awaitingValidation;
                                awaitingMessage.setAttribute('aria-hidden', awaitingValidation ? 'false' : 'true');
                                if (awaitingValidation) {
                                    awaitingMessage.textContent = sepaText.awaitingMessage;
                                }
                            }

                            const downloadAction = document.querySelector('[data-sepa-download]');
                            if (downloadAction) {
                                if (awaitingValidation) {
                                    downloadAction.remove();
                                } else {
                                    const hideDownload = typeof sepaData.requested !== 'undefined' && !sepaData.requested;
                                    downloadAction.hidden = hideDownload;
                                    downloadAction.setAttribute('aria-hidden', hideDownload ? 'true' : 'false');
                                }
                            }

                            const downloadStep = document.querySelector('[data-sepa-step-download]');
                            if (downloadStep) {
                                if (awaitingValidation) {
                                    downloadStep.remove();
                                } else {
                                    const hideDownload = typeof sepaData.requested !== 'undefined' && !sepaData.requested;
                                    downloadStep.hidden = hideDownload;
                                    downloadStep.setAttribute('aria-hidden', hideDownload ? 'true' : 'false');
                                }
                            }

                            const uploadStep = document.querySelector('[data-sepa-step-upload]');
                            if (uploadStep) {
                                if (awaitingValidation) {
                                    uploadStep.remove();
                                } else {
                                    uploadStep.hidden = false;
                                    uploadStep.setAttribute('aria-hidden', 'false');
                                }
                            }

                            const uploadAction = document.querySelector('[data-sepa-upload]');
                            if (uploadAction) {
                                const uploadWrapper = uploadAction.querySelector('.account-sepa-request__upload');
                                if (uploadWrapper) {
                                    if (awaitingValidation) {
                                        uploadWrapper.classList.add('account-sepa-request__upload--locked');
                                        uploadWrapper.setAttribute('data-locked', 'true');
                                    } else {
                                        uploadWrapper.classList.remove('account-sepa-request__upload--locked');
                                        uploadWrapper.removeAttribute('data-locked');
                                    }
                                }

                                const uploadContainer = uploadAction.querySelector('[data-document-upload]');
                                if (uploadContainer) {
                                    uploadContainer.dataset.locked = awaitingValidation ? 'true' : 'false';
                                }

                                const fileLabel = uploadAction.querySelector('[data-document-label]');
                                if (fileLabel) {
                                    if (awaitingValidation) {
                                        fileLabel.remove();
                                    } else {
                                        fileLabel.hidden = false;
                                        fileLabel.setAttribute('aria-hidden', 'false');
                                    }
                                }

                                const fileHint = uploadAction.querySelector('.file-hint');
                                if (fileHint) {
                                    if (awaitingValidation) {
                                        fileHint.remove();
                                    } else {
                                        fileHint.hidden = false;
                                        fileHint.setAttribute('aria-hidden', 'false');
                                    }
                                }

                                const fileInput = uploadAction.querySelector('input[type="file"]');
                                if (fileInput) {
                                    if (awaitingValidation) {
                                        fileInput.setAttribute('disabled', 'disabled');
                                        fileInput.setAttribute('hidden', '');
                                        fileInput.setAttribute('aria-hidden', 'true');
                                    } else {
                                        fileInput.removeAttribute('disabled');
                                        fileInput.removeAttribute('hidden');
                                        fileInput.setAttribute('aria-hidden', 'false');
                                    }
                                }

                                const removeButton = uploadAction.querySelector('[data-document-remove]');
                                if (removeButton) {
                                    if (awaitingValidation) {
                                        removeButton.remove();
                                    } else {
                                        const hasServerDocument = Boolean(
                                            sepaData
                                            && sepaData.documents
                                            && sepaData.documents.signed
                                            && (
                                                sepaData.documents.signed.url
                                                || sepaData.documents.signed.filename
                                                || sepaData.documents.signed.hash
                                            )
                                        );
                                        removeButton.hidden = !hasServerDocument;
                                        removeButton.setAttribute('aria-hidden', hasServerDocument ? 'false' : 'true');
                                    }
                                }
                            }
                        }

                        setStatus(strings.success || 'Cambios guardados correctamente.', 'success');
                        setSaveDisabled(true);
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
        const profileAvatarImage = document.querySelector('.account-summary__avatar img');
        let profileUploadInput = null;
        let profileImageFile = null;
        let profileImagePreviewUrl = '';
        let profileImageOriginalSrc = profileAvatarImage && profileAvatarImage.getAttribute('src')
            ? profileAvatarImage.getAttribute('src')
            : '';

        const revokeProfilePreview = () => {
            if (profileImagePreviewUrl) {
                URL.revokeObjectURL(profileImagePreviewUrl);
                profileImagePreviewUrl = '';
            }
        };

        const syncProfilePreview = (src) => {
            if (!profileAvatarImage) {
                return;
            }

            profileAvatarImage.setAttribute('src', src || '');
        };

        const resetProfileSelection = () => {
            profileImageFile = null;
            revokeProfilePreview();
            syncProfilePreview(profileImageOriginalSrc);
        };

        const attachProfileUpload = (fileInput) => {
            profileUploadButton.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', () => {
                const files = fileInput.files;
                if (!files || files.length === 0) {
                    resetProfileSelection();
                    enableSaveButton();
                    return;
                }

                const [file] = files;
                if (!file) {
                    resetProfileSelection();
                    enableSaveButton();
                    return;
                }

                profileImageFile = file;
                revokeProfilePreview();
                profileImagePreviewUrl = URL.createObjectURL(file);
                syncProfilePreview(profileImagePreviewUrl);
                enableSaveButton();
            });
        };

        if (profileUploadButton) {
            const inputId = profileUploadButton.getAttribute('data-profile-upload');
            const fileInput = inputId ? document.getElementById(inputId) : null;

            if (fileInput) {
                profileUploadInput = fileInput;
                attachProfileUpload(fileInput);
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

            const createRow = (data = {}, options = {}) => {
                if (!rowsContainer || !template) {
                    return null;
                }

                const index = nextIndex;
                nextIndex += 1;
                notificationsRepeater.setAttribute('data-next-index', String(nextIndex));

                const config = typeof options === 'object' && options !== null ? options : {};
                const suppressDirty = Boolean(config.suppressDirty);
                const shouldFocus = config.focus !== false;

                const html = template.innerHTML.replace(/__index__/g, String(index));
                const fragment = document.createElement('div');
                fragment.innerHTML = html.trim();
                const row = fragment.firstElementChild;

                if (!row) {
                    return null;
                }

                const emailInput = row.querySelector('[data-repeater-email]');
                if (emailInput && typeof data.email === 'string') {
                    emailInput.value = data.email;
                }

                const bccInput = row.querySelector('[data-repeater-bcc]');
                if (bccInput) {
                    bccInput.checked = Boolean(data.bcc);
                }

                rowsContainer.appendChild(row);
                bindRow(row);
                updateRemoveState();
                if (!suppressDirty) {
                    enableSaveButton();
                }

                if (shouldFocus) {
                    window.requestAnimationFrame(() => {
                        if (emailInput && typeof emailInput.focus === 'function') {
                            try {
                                emailInput.focus({ preventScroll: true });
                            } catch (error) {
                                emailInput.focus();
                            }
                        }
                    });
                }

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

            notificationsRepeater.__syncRows = (rows = []) => {
                if (!rowsContainer || !template) {
                    return;
                }

                rowsContainer.innerHTML = '';
                nextIndex = 0;
                notificationsRepeater.setAttribute('data-next-index', '0');

                const normalized = Array.isArray(rows) ? rows : [];
                if (normalized.length === 0) {
                    createRow({}, { suppressDirty: true, focus: false });
                } else {
                    normalized.forEach((rowData) => {
                        createRow({
                            email: typeof rowData.email === 'string' ? rowData.email : '',
                            bcc: Boolean(rowData.bcc),
                        }, { suppressDirty: true, focus: false });
                    });
                }

                updateRemoveState();
            };
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
            const hintElement = container.querySelector('.file-hint');
            const defaultLabel = container.dataset.defaultLabel || (label ? label.textContent : '') || '';
            const documentType = container.dataset.documentType || '';
            let locked = container.dataset.locked === 'true';

            let initialLabel = label ? label.textContent : defaultLabel;
            let initialSize = sizeElement && !sizeElement.hasAttribute('hidden') ? sizeElement.textContent : '';
            let initialUrl = linkElement && !linkElement.hasAttribute('hidden') ? linkElement.getAttribute('href') : '';
            let initialLinkText = linkElement ? linkElement.textContent : '';
            let initialHasDocument = body ? !body.hasAttribute('hidden') : false;

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
                container.classList.toggle('file-upload--locked', locked);

                if (label) {
                    label.textContent = state.hasDocument ? state.label : defaultLabel;
                    label.hidden = locked;
                    label.setAttribute('aria-hidden', locked ? 'true' : 'false');
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

                if (hintElement) {
                    hintElement.hidden = locked;
                    hintElement.setAttribute('aria-hidden', locked ? 'true' : 'false');
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
                    const hidePlaceholder = state.hasDocument || locked;
                    placeholder.hidden = hidePlaceholder;
                    placeholder.setAttribute('aria-hidden', hidePlaceholder ? 'true' : 'false');
                }

                if (removeButton) {
                    const showRemove = state.hasDocument && !locked;
                    removeButton.hidden = !showRemove;
                    removeButton.setAttribute('aria-hidden', showRemove ? 'false' : 'true');
                }

                if (input) {
                    input.disabled = locked;
                }
            };

            renderState();

            const registerState = (reason = 'render') => {
                if (!documentType) {
                    return;
                }

                const files = input && input.files ? Array.from(input.files) : [];
                const file = files.length > 0 ? files[0] : null;
                const removed = !state.hasDocument && initialHasDocument && !file;

                documentUploadStates[documentType] = {
                    hasDocument: state.hasDocument,
                    removed,
                    changed: Boolean(file),
                    input,
                    container,
                    reason,
                    locked,
                };
            };

            const applyServerState = (data = {}) => {
                revokeTemporaryUrl();

                const hasServerDocument = data && (data.id || data.url || data.filename || data.title);
                const nextLabel = hasServerDocument
                    ? (typeof data.filename === 'string' && data.filename !== ''
                        ? data.filename
                        : (typeof data.title === 'string' && data.title !== '' ? data.title : defaultLabel))
                    : defaultLabel;
                const nextSize = hasServerDocument && typeof data.size === 'string' ? data.size : '';
                const nextUrl = hasServerDocument && typeof data.url === 'string' ? data.url : '';
                const nextLinkText = hasServerDocument && typeof data.linkText === 'string' && data.linkText !== ''
                    ? data.linkText
                    : (initialLinkText || 'Ver documento');

                initialLabel = nextLabel;
                initialSize = nextSize;
                initialUrl = nextUrl;
                initialLinkText = nextLinkText;
                initialHasDocument = Boolean(hasServerDocument);

                state.hasDocument = initialHasDocument;
                state.label = nextLabel;
                state.size = nextSize;
                state.url = nextUrl;
                state.linkText = nextLinkText;

                if (input) {
                    input.value = '';
                }

                renderState();
                registerState('sync');
            };

            const setLocked = (value) => {
                const nextLocked = Boolean(value);
                if (nextLocked === locked) {
                    return;
                }

                locked = nextLocked;
                container.dataset.locked = locked ? 'true' : 'false';
                renderState();
                registerState(locked ? 'lock' : 'unlock');
            };

            if (documentType) {
                documentUploadControllers[documentType] = {
                    applyServerState,
                    setLocked,
                };
            }

            registerState('init');

            if (container && input) {
                container.addEventListener('click', (event) => {
                    if (locked) {
                        return;
                    }

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
                    if (locked) {
                        return;
                    }

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
                    registerState('remove');
                    enableSaveButton();
                });
            }

            if (input) {
                input.addEventListener('change', () => {
                    if (locked) {
                        renderState();
                        return;
                    }

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
                    registerState('select');
                    enableSaveButton();
                });
            }
        });

        const setupImageUpload = (containerId, inputId, previewId, options) => {
            const container = document.getElementById(containerId);
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const previewImage = preview ? preview.querySelector('img') : null;

            if (!container || !input) {
                return;
            }

            const config = typeof options === 'object' && options !== null ? options : {};
            const onStateChange = typeof config.onStateChange === 'function' ? config.onStateChange : null;
            const label = container.querySelector('.file-label');
            const removeButton = container.querySelector('[data-file-remove]');
            const initialLabel = label ? label.textContent : '';
            const defaultLabel = container.dataset.defaultLabel || initialLabel;
            const initialPreviewVisible = preview ? !preview.hasAttribute('hidden') : false;
            const initialPreviewSrc = previewImage ? previewImage.getAttribute('src') : '';
            let allowRestoreInitial = initialPreviewVisible && Boolean(initialPreviewSrc);
            let hasFile = initialPreviewVisible && Boolean(initialPreviewSrc);

            const notifyStateChange = (reason = 'change') => {
                if (onStateChange) {
                    onStateChange({
                        hasFile,
                        input,
                        container,
                        reason,
                    });
                }
            };

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

            const resetPreview = (resetOptions = {}) => {
                const restoreInitial = resetOptions.restoreInitial !== false;
                const shouldRestore = restoreInitial && allowRestoreInitial && initialPreviewVisible && initialPreviewSrc;
                const reason = typeof resetOptions.notifyReason === 'string' && resetOptions.notifyReason !== ''
                    ? resetOptions.notifyReason
                    : 'reset';

                if (shouldRestore) {
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

                hasFile = shouldRestore;
                notifyStateChange(reason);
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
                    resetPreview({ notifyReason: 'reset' });
                    return;
                }

                const [file] = input.files;
                if (!file) {
                    resetPreview({ notifyReason: 'reset' });
                    return;
                }

                allowRestoreInitial = false;

                if (label) {
                    label.textContent = file.name;
                }

                if (!preview || !previewImage || !file.type || !file.type.startsWith('image/')) {
                    updateRemoveVisibility(true);
                    hasFile = true;
                    notifyStateChange('select');
                    return;
                }

                const reader = new FileReader();
                reader.addEventListener('load', () => {
                    if (typeof reader.result === 'string') {
                        setPreviewVisible(true, reader.result);
                        updateRemoveVisibility(true);
                        hasFile = true;
                        notifyStateChange('select');
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
                    resetPreview({ restoreInitial: false, notifyReason: 'remove' });
                    const inputEvent = new Event('input', { bubbles: true });
                    input.dispatchEvent(inputEvent);
                });

                updateRemoveVisibility(!removeButton.hasAttribute('hidden'));
            }

            if (!initialPreviewVisible) {
                setPreviewVisible(false);
            }

            notifyStateChange('init');
        };

        setupImageUpload('signature-upload', 'signature', 'signature-preview', {
            onStateChange: (state) => {
                signatureHasFile = Boolean(state && state.hasFile);
                const reason = state && typeof state.reason === 'string' ? state.reason : '';

                if (reason === 'remove') {
                    certificatesRemovalWarning = Boolean(stampHasFile);
                } else if (reason === 'select' || reason === 'reset' || reason === 'init') {
                    if (!certificatesWarningForced) {
                        certificatesRemovalWarning = false;
                    }
                }

                updateCertificatesWarningState();
            },
        });
        setupImageUpload('stamp-upload', 'stamp', 'stamp-preview', {
            onStateChange: (state) => {
                stampHasFile = Boolean(state && state.hasFile);
                const reason = state && typeof state.reason === 'string' ? state.reason : '';

                if (reason === 'remove') {
                    certificatesRemovalWarning = Boolean(signatureHasFile);
                } else if (reason === 'select' || reason === 'reset' || reason === 'init') {
                    if (!certificatesWarningForced) {
                        certificatesRemovalWarning = false;
                    }
                }

                updateCertificatesWarningState();
            },
        });
        updateCertificatesWarningState();
    });
})();
