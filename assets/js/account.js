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
        const sepaConfig = accountConfig.sepa && typeof accountConfig.sepa === 'object'
            ? accountConfig.sepa
            : {};
        const sepaTemplateUrl = typeof sepaConfig.templateUrl === 'string'
            ? sepaConfig.templateUrl.trim()
            : '';
        const sepaFontkitUrl = typeof sepaConfig.fontkitUrl === 'string'
            ? sepaConfig.fontkitUrl.trim()
            : '';
        const sepaFontUrl = typeof sepaConfig.fontUrl === 'string'
            ? sepaConfig.fontUrl.trim()
            : '';
        const sepaReferencePrefix = typeof sepaConfig.referencePrefix === 'string'
            && sepaConfig.referencePrefix.trim() !== ''
            ? sepaConfig.referencePrefix.trim().toUpperCase()
            : 'GO';
        const sepaCreditor = sepaConfig.creditor && typeof sepaConfig.creditor === 'object'
            ? sepaConfig.creditor
            : {};
        const sepaText = {
            awaitingValidation: strings.sepaAwaitingValidation || 'Pendiente de validación',
            awaitingSignature: strings.sepaAwaitingSignature || 'Pendiente de firma',
            awaitingMessage: strings.sepaAwaitingMessage || 'Tu SEPA firmado está pendiente de validación.',
            awaitingActivation: strings.sepaAwaitingActivation || 'Pendiente de domiciliación',
            signedDocument: strings.sepaSignedDocument || 'Tu SEPA firmado',
            downloadPrompt: strings.sepaDownloadPrompt || 'Descarga el documento.',
        };
        const sepaSignedFallbackName = strings.sepaSignedFilename || 'Mandato SEPA firmado';
        const sepaPendingLink = document.querySelector('[data-sepa-pending-link]');
        const sepaPendingLabel = document.querySelector('[data-sepa-pending-label]');
        const sepaPendingDefaultHref = sepaPendingLink instanceof HTMLAnchorElement
            ? sepaPendingLink.getAttribute('href') || '#'
            : '#';
        const sepaPendingDefaultLabel = sepaPendingLabel
            ? sepaPendingLabel.textContent.trim()
            : '';
        const formatIban = (value) => {
            const raw = typeof value === 'string' ? value.replace(/\s+/g, '').toUpperCase() : '';
            if (!raw) {
                return '';
            }
            return raw.replace(/(.{4})/g, '$1 ').trim();
        };
        const maskIban = (value) => {
            const raw = typeof value === 'string' ? value.replace(/\s+/g, '').toUpperCase() : '';
            if (!raw) {
                return '';
            }
            const groups = raw.match(/.{1,4}/g) || [];
            return groups
                .map((group, index) => {
                    if (index <= 1 || index === groups.length - 1) {
                        return group;
                    }
                    return '****';
                })
                .join(' ')
                .trim();
        };
        const isValidIban = (value) => {
            const sanitized = typeof value === 'string'
                ? value.replace(/\s+/g, '').toUpperCase()
                : '';
            if (!/^[A-Z0-9]{15,34}$/.test(sanitized)) {
                return false;
            }
            const rearranged = sanitized.slice(4) + sanitized.slice(0, 4);
            const converted = rearranged.replace(/[A-Z]/g, (char) => String(char.charCodeAt(0) - 55));
            let remainder = 0;
            for (let index = 0; index < converted.length; index += 1) {
                const digit = Number(converted[index]);
                if (Number.isNaN(digit)) {
                    return false;
                }
                remainder = (remainder * 10 + digit) % 97;
            }
            return remainder === 1;
        };
        const buildSepaFilename = (reference) => {
            const safe = typeof reference === 'string'
                ? reference.replace(/[^A-Za-z0-9-]/g, '').toLowerCase()
                : '';
            return safe ? `mandato-sepa-${safe}.pdf` : 'mandato-sepa.pdf';
        };
        const sepaMandateState = {
            currentPromise: null,
            templateBytes: null,
            fontBytes: null,
            fontkitRegistered: false,
            fontSource: '',
            snapshot: null,
            reference: '',
            generatedAt: '',
            blob: null,
            filename: '',
            signatureLocality: '',
            signatureDate: '',
        };
        const generateReferenceValue = () => {
            const timestamp = Date.now().toString(36).toUpperCase();
            const random = Math.random().toString(36).slice(2, 8).toUpperCase();
            const raw = `${sepaReferencePrefix}-${timestamp}-${random}`.replace(/[^A-Z0-9-]/g, '').slice(0, 40);
            return raw || `${sepaReferencePrefix}-${timestamp}`;
        };
        const ensureSepaReference = () => {
            if (!sepaMandateState.reference) {
                sepaMandateState.reference = generateReferenceValue();
            }
            return sepaMandateState.reference;
        };
        const loadFontkit = async () => {
            if (sepaMandateState.fontkitRegistered && window.fontkit) {
                return true;
            }
            if (typeof window.fontkit !== 'undefined' && window.fontkit) {
                sepaMandateState.fontkitRegistered = true;
                return true;
            }
            if (!sepaFontkitUrl) {
                return false;
            }
            const existing = document.querySelector('script[data-sepa-fontkit]');
            if (existing && existing.getAttribute('data-loaded') === 'true') {
                sepaMandateState.fontkitRegistered = Boolean(window.fontkit);
                return Boolean(window.fontkit);
            }
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = sepaFontkitUrl;
                script.async = true;
                script.setAttribute('data-sepa-fontkit', 'true');
                script.addEventListener('load', () => {
                    script.setAttribute('data-loaded', 'true');
                    resolve();
                });
                script.addEventListener('error', reject);
                document.head.appendChild(script);
            }).catch((error) => {
                console.warn('[account] fontkit load failed', error);
            });
            if (typeof window.fontkit !== 'undefined' && window.fontkit) {
                sepaMandateState.fontkitRegistered = true;
                return true;
            }
            return false;
        };
        const fetchArrayBuffer = async (url) => {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.arrayBuffer();
        };
        const getSepaSnapshotFromFields = (fieldElements) => {
            const snapshot = {
                nombre_deudor: '',
                direccion_deudor: '',
                codigo_postal: '',
                poblacion: '',
                provincia: '',
                pais_deudor: '',
                swift_bic: '',
                numero_cuenta: '',
            };
            fieldElements.forEach((field) => {
                if (!(field instanceof HTMLInputElement)) {
                    return;
                }
                const key = field.dataset.sepaField || '';
                if (!key || !(key in snapshot)) {
                    return;
                }
                snapshot[key] = field.value ? field.value.trim() : '';
            });
            return snapshot;
        };
        const snapshotsAreEqual = (a, b) => {
            if (!a || !b) {
                return false;
            }
            const keys = Object.keys(a);
            return keys.every((key) => (a[key] || '') === (b[key] || ''));
        };
        const createSepaMandate = async (snapshot) => {
            if (!sepaTemplateUrl) {
                throw new Error('sepa_template_missing');
            }
            if (typeof PDFLib === 'undefined' || !PDFLib.PDFDocument) {
                throw new Error('pdf_lib_unavailable');
            }
            const reference = ensureSepaReference();
            if (!sepaMandateState.templateBytes) {
                sepaMandateState.templateBytes = await fetchArrayBuffer(sepaTemplateUrl);
            }
            const pdfDoc = await PDFLib.PDFDocument.load(sepaMandateState.templateBytes);
            const fontkitLoaded = await loadFontkit();
            if (fontkitLoaded && window.fontkit) {
                try {
                    pdfDoc.registerFontkit(window.fontkit);
                } catch (error) {
                    console.warn('[account] fontkit register failed', error);
                }
            }
            if (!sepaMandateState.fontBytes && sepaFontUrl) {
                try {
                    sepaMandateState.fontBytes = await fetchArrayBuffer(sepaFontUrl);
                    sepaMandateState.fontSource = sepaFontUrl;
                } catch (error) {
                    console.warn('[account] sepa font fetch failed', error);
                }
            }
            let activeFont = null;
            let appearanceFontName = '';
            if (sepaMandateState.fontBytes) {
                try {
                    activeFont = await pdfDoc.embedFont(sepaMandateState.fontBytes);
                    if (activeFont && typeof activeFont.name === 'string') {
                        appearanceFontName = activeFont.name;
                    }
                } catch (error) {
                    console.warn('[account] sepa custom font embed failed', error);
                }
            }
            if (!activeFont) {
                const fallbackName = (PDFLib.StandardFonts && PDFLib.StandardFonts.Helvetica)
                    ? PDFLib.StandardFonts.Helvetica
                    : 'Helvetica';
                activeFont = await pdfDoc.embedStandardFont(fallbackName);
                appearanceFontName = typeof fallbackName === 'string' ? fallbackName : 'Helvetica';
            }
            const resolvedFontName = appearanceFontName || 'Helvetica';
            const form = pdfDoc.getForm();
            if (form && PDFLib?.PDFName && PDFLib?.PDFBool && typeof pdfDoc.catalog?.lookup === 'function') {
                try {
                    const acroForm = pdfDoc.catalog.lookup(PDFLib.PDFName.of('AcroForm'));
                    if (acroForm && typeof acroForm.set === 'function') {
                        acroForm.set(PDFLib.PDFName.of('NeedAppearances'), PDFLib.PDFBool.True);
                    }
                } catch (error) {
                    console.warn('[account] Unable to mark AcroForm for appearances', error);
                }
            }
            const creditorCountry = typeof sepaCreditor.country === 'string' && sepaCreditor.country !== ''
                ? sepaCreditor.country
                : 'España';
            const creditorPostal = typeof sepaCreditor.postal_code === 'string' ? sepaCreditor.postal_code : '';
            const creditorCity = typeof sepaCreditor.city === 'string' ? sepaCreditor.city : '';
            const creditorProvince = typeof sepaCreditor.province === 'string' ? sepaCreditor.province : '';
            const debtorCountry = snapshot.pais_deudor || creditorCountry;
            const signatureLocality = creditorProvince || snapshot.provincia || snapshot.poblacion || '';
            const signatureDate = new Date();
            const formattedSignatureDate = signatureDate.toLocaleDateString('es-ES');
            const fieldMap = {
                pdf_acreedor_referencia: reference,
                pdf_acreedor_id: sepaCreditor.id || '',
                pdf_acreedor_nombre: sepaCreditor.name || '',
                pdf_acreedor_direccion: sepaCreditor.address || '',
                pdf_acreedor_pais: creditorCountry,
                pdf_acreedor_cp: creditorPostal,
                pdf_acreedor_poblacion: creditorCity,
                pdf_acreedor_provincia: creditorProvince,
                pdf_deudor_nombre: snapshot.nombre_deudor,
                pdf_deudor_direccion: snapshot.direccion_deudor,
                pdf_deudor_pais: debtorCountry,
                pdf_deudor_cp: snapshot.codigo_postal,
                pdf_deudor_poblacion: snapshot.poblacion,
                pdf_deudor_provincia: snapshot.provincia,
                pdf_deudor_swift: snapshot.swift_bic,
                pdf_deudor_iban: formatIban(snapshot.numero_cuenta || ''),
                pdf_deudor_firma_fecha: formattedSignatureDate,
                pdf_deudor_firma_localidad: signatureLocality,
            };
            const editableFields = new Set(['pdf_deudor_firma', 'pdf_deudor_firma_fecha', 'pdf_deudor_firma_localidad']);
            Object.entries(fieldMap).forEach(([name, value]) => {
                const stringValue = value === undefined || value === null ? '' : String(value);
                if (stringValue === '') {
                    return;
                }
                try {
                    const field = form.getTextField(name);
                    field.setText(stringValue);
                    field.setFontSize(9);
                    if (field.acroField && typeof field.acroField.setDefaultAppearance === 'function') {
                        field.acroField.setDefaultAppearance(`0 0 0 rg /${resolvedFontName} 9 Tf`);
                    }
                    if (typeof field.updateAppearances === 'function') {
                        try {
                            if (PDFLib?.rgb) {
                                field.updateAppearances(activeFont, {
                                    textColor: PDFLib.rgb(0, 0, 0),
                                    fontSize: 9,
                                });
                            } else {
                                field.updateAppearances(activeFont);
                            }
                        } catch (appearanceError) {
                            console.warn('[account] Unable to refresh field appearance', appearanceError);
                        }
                    }
                    if (!editableFields.has(name) && typeof field.enableReadOnly === 'function') {
                        field.enableReadOnly();
                    }
                } catch (error) {
                    console.warn('[account] Missing PDF field', name, error);
                }
            });
            const paymentType = typeof sepaCreditor.payment_type === 'string'
                ? sepaCreditor.payment_type.toLowerCase()
                : 'recurrente';
            try {
                const recurrentField = form.getCheckBox('pdf_deudor_pago_recurrente');
                const uniqueField = form.getCheckBox('pdf_deudor_pago_unico');
                if (paymentType === 'unico') {
                    uniqueField.check();
                    recurrentField.uncheck();
                } else {
                    recurrentField.check();
                    uniqueField.uncheck();
                }
                if (typeof recurrentField.enableReadOnly === 'function') {
                    recurrentField.enableReadOnly();
                }
                if (typeof uniqueField.enableReadOnly === 'function') {
                    uniqueField.enableReadOnly();
                }
            } catch (error) {
                console.warn('[account] Unable to set payment checkbox', error);
            }
            if (form && typeof form.getSignature === 'function') {
                try {
                    const signatureField = form.getSignature('pdf_deudor_firma');
                    if (signatureField && typeof signatureField.disableReadOnly === 'function') {
                        signatureField.disableReadOnly();
                    }
                    if (
                        signatureField
                        && signatureField.acroField
                        && signatureField.acroField.dict
                        && PDFLib?.PDFName
                        && PDFLib?.PDFNumber
                        && typeof signatureField.acroField.dict.set === 'function'
                    ) {
                        try {
                            signatureField.acroField.dict.set(PDFLib.PDFName.of('Ff'), PDFLib.PDFNumber.of(0));
                            if (typeof signatureField.acroField.dict.delete === 'function') {
                                signatureField.acroField.dict.delete(PDFLib.PDFName.of('V'));
                            }
                        } catch (innerError) {
                            console.warn('[account] Unable to reset signature field flags', innerError);
                        }
                    }
                } catch (error) {
                    console.warn('[account] Unable to keep signature field editable', error);
                }
            }
            const generatedAt = signatureDate.toISOString();
            const filled = await pdfDoc.save({ updateFieldAppearances: false });
            const blob = new Blob([filled], { type: 'application/pdf' });
            const filename = buildSepaFilename(reference);
            return {
                blob,
                filename,
                reference,
                generatedAt,
                signatureLocality,
                signatureDate: formattedSignatureDate,
            };
        };
        const ensureSepaMandateReady = async (fieldElements) => {
            const snapshot = getSepaSnapshotFromFields(fieldElements);
            if (
                sepaMandateState.blob
                && sepaMandateState.snapshot
                && snapshotsAreEqual(sepaMandateState.snapshot, snapshot)
            ) {
                return {
                    blob: sepaMandateState.blob,
                    filename: sepaMandateState.filename || buildSepaFilename(ensureSepaReference()),
                    reference: sepaMandateState.reference || ensureSepaReference(),
                    generatedAt: sepaMandateState.generatedAt || new Date().toISOString(),
                    signatureLocality: sepaMandateState.signatureLocality || '',
                    signatureDate: sepaMandateState.signatureDate || new Date().toLocaleDateString('es-ES'),
                };
            }
            if (!sepaMandateState.currentPromise) {
                sepaMandateState.currentPromise = createSepaMandate(snapshot);
            }
            try {
                const result = await sepaMandateState.currentPromise;
                sepaMandateState.currentPromise = null;
                sepaMandateState.blob = result.blob;
                sepaMandateState.filename = result.filename;
                sepaMandateState.snapshot = snapshot;
                sepaMandateState.reference = result.reference;
                sepaMandateState.generatedAt = result.generatedAt;
                sepaMandateState.signatureLocality = result.signatureLocality;
                sepaMandateState.signatureDate = result.signatureDate;
                return result;
            } catch (error) {
                sepaMandateState.currentPromise = null;
                sepaMandateState.blob = null;
                sepaMandateState.filename = '';
                sepaMandateState.snapshot = null;
                sepaMandateState.reference = '';
                sepaMandateState.generatedAt = '';
                sepaMandateState.signatureLocality = '';
                sepaMandateState.signatureDate = '';
                throw error;
            }
        };
        const buildSepaRequestFields = (fieldElements) => {
            const values = {};
            fieldElements.forEach((field) => {
                if (!(field instanceof HTMLInputElement)) {
                    return;
                }
                const key = field.dataset.sepaField || '';
                if (!key) {
                    return;
                }
                values[key] = field.value ? field.value.trim() : '';
            });
            if (values.numero_cuenta) {
                values.numero_cuenta = values.numero_cuenta.replace(/\s+/g, '').toUpperCase();
            }
            if (values.swift_bic) {
                values.swift_bic = values.swift_bic.toUpperCase();
            }
            if (values.pais_deudor) {
                values.pais_deudor = values.pais_deudor.trim();
            }
            return values;
        };
        const POSTAL_CODE_REGEX = /^(0[1-9]|[1-4]\d|5[0-3])\d{3}$/;
        const SWIFT_REGEX = /^[A-Za-z]{4}[A-Za-z]{2}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/;
        const syncSignedDocumentBlocks = (documentData) => {
            const blocks = document.querySelectorAll('[data-sepa-signed]');
            if (!blocks.length) {
                return;
            }

            const doc = documentData && typeof documentData === 'object' ? documentData : null;
            const url = doc && typeof doc.url === 'string' ? doc.url.trim() : '';
            const filename = doc
                && typeof doc.filename === 'string'
                && doc.filename.trim() !== ''
                ? doc.filename.trim()
                : sepaSignedFallbackName;
            const hasDocument = Boolean(url || (doc && (doc.hash || doc.id)));

            blocks.forEach((block) => {
                if (!(block instanceof HTMLElement)) {
                    return;
                }

                const link = block.querySelector('[data-sepa-signed-link]');
                const name = block.querySelector('[data-sepa-signed-name]');

                if (hasDocument) {
                    block.hidden = false;
                    block.setAttribute('aria-hidden', 'false');
                    if (link instanceof HTMLAnchorElement) {
                        link.hidden = false;
                        link.setAttribute('aria-hidden', 'false');
                        link.removeAttribute('tabindex');
                        link.setAttribute('href', url || '#');
                    }
                    if (name) {
                        name.textContent = filename;
                    }
                } else {
                    block.hidden = true;
                    block.setAttribute('aria-hidden', 'true');
                    if (link instanceof HTMLAnchorElement) {
                        link.hidden = true;
                        link.setAttribute('aria-hidden', 'true');
                        link.setAttribute('tabindex', '-1');
                        link.setAttribute('href', '#');
                    }
                    if (name) {
                        name.textContent = sepaSignedFallbackName;
                    }
                }
            });
        };
        const sepaIbanController = (() => {
            const valueElement = document.querySelector('[data-sepa-iban]');
            const toggle = document.querySelector('[data-sepa-iban-toggle]');
            if (!valueElement) {
                return {
                    setValue: () => {},
                };
            }

            let revealed = toggle ? toggle.getAttribute('aria-pressed') === 'true' : false;

            const apply = () => {
                const fullValue = valueElement.getAttribute('data-full-value') || '';
                const maskedValue = valueElement.getAttribute('data-masked-value') || '';
                const hasValue = Boolean(fullValue || maskedValue);
                const display = revealed ? (fullValue || maskedValue || '—') : (maskedValue || fullValue || '—');

                valueElement.textContent = display || '—';

                if (!toggle) {
                    return;
                }

                if (hasValue) {
                    toggle.hidden = false;
                    toggle.setAttribute('aria-hidden', 'false');
                    toggle.setAttribute('aria-pressed', revealed ? 'true' : 'false');
                    const showLabel = toggle.getAttribute('data-label-show') || '';
                    const hideLabel = toggle.getAttribute('data-label-hide') || '';
                    toggle.setAttribute('aria-label', revealed ? (hideLabel || showLabel) : (showLabel || hideLabel));
                } else {
                    toggle.hidden = true;
                    toggle.setAttribute('aria-hidden', 'true');
                    toggle.setAttribute('aria-pressed', 'false');
                    const showLabel = toggle.getAttribute('data-label-show') || '';
                    if (showLabel) {
                        toggle.setAttribute('aria-label', showLabel);
                    }
                }
            };

            if (toggle) {
                toggle.addEventListener('click', () => {
                    revealed = !revealed;
                    apply();
                });
            }

            apply();

            return {
                setValue: (value) => {
                    const formatted = formatIban(value);
                    const masked = maskIban(value);
                    valueElement.setAttribute('data-full-value', formatted);
                    valueElement.setAttribute('data-masked-value', masked);
                    if (!formatted) {
                        revealed = false;
                        if (toggle) {
                            toggle.setAttribute('aria-pressed', 'false');
                        }
                    }
                    apply();
                },
            };
        })();
        const applyPaymentsSnapshot = (paymentsData) => {
            const sepaData = paymentsData && typeof paymentsData === 'object' ? paymentsData.sepa : null;
            const sepaController = documentUploadControllers.sepa_signed;

            if (sepaData && typeof sepaData === 'object') {
                if (
                    sepaData.documents
                    && sepaData.documents.signed
                    && sepaController
                    && typeof sepaController.applyServerState === 'function'
                ) {
                    sepaController.applyServerState(sepaData.documents.signed);
                }

                const sepaStatusCodeRaw = typeof sepaData.status_code === 'string'
                    ? sepaData.status_code.trim().toLowerCase()
                    : '';
                const sepaIsDisabled = sepaStatusCodeRaw === 'deshabilitado';
                const awaitingValidation = Boolean(sepaData.awaiting_validation);
                const needsActivation = Boolean(sepaData.needs_activation);
                const signedDocumentData = (!needsActivation && !sepaIsDisabled)
                    && sepaData.documents
                    ? sepaData.documents.signed
                    : null;
                syncSignedDocumentBlocks(signedDocumentData);
                const sepaIsActive = Boolean(sepaData.status);
                const sepaIsActivated = Boolean(sepaData.activated);
                if (sepaController && typeof sepaController.setLocked === 'function') {
                    sepaController.setLocked(awaitingValidation || needsActivation);
                }
                const sepaFields = Array.isArray(sepaData.fields) ? sepaData.fields : [];
                const ibanField = sepaFields.find((field) => {
                    if (!field || typeof field !== 'object') {
                        return false;
                    }
                    const name = typeof field.name === 'string' ? field.name : '';
                    const altName = typeof field.field === 'string' ? field.field : '';
                    return name === 'numero_cuenta' || altName === 'numero_cuenta';
                });
                const ibanValue = ibanField && typeof ibanField.value === 'string'
                    ? ibanField.value
                    : '';
                sepaIbanController.setValue(ibanValue);
                const activationCard = paymentActivation;
                const detailCard = paymentDetail;
                const toggleWrapper = activationCard
                    ? activationCard.querySelector('[data-sepa-toggle]')
                    : null;
                const toggleInput = activationCard
                    ? activationCard.querySelector('[data-payment-toggle]')
                    : null;
                const reactivationContainer = document.querySelector('[data-sepa-reactivation]');
                const reactivationStatus = document.querySelector('[data-sepa-reactivation-status]');

                if (toggleWrapper) {
                    const shouldHideToggle = sepaIsActivated || Boolean(sepaData.requested) || needsActivation || sepaIsDisabled;
                    toggleWrapper.hidden = shouldHideToggle;
                    toggleWrapper.setAttribute('aria-hidden', shouldHideToggle ? 'true' : 'false');
                }

                if (toggleInput) {
                    const shouldDisableToggle = sepaIsActivated
                        || awaitingValidation
                        || Boolean(sepaData.requested)
                        || needsActivation
                        || sepaIsDisabled;
                    if (sepaIsActivated) {
                        toggleInput.checked = true;
                    } else if (!shouldDisableToggle) {
                        toggleInput.checked = false;
                    }
                    toggleInput.disabled = shouldDisableToggle;
                    if (sepaIsDisabled) {
                        toggleInput.checked = false;
                    }
                }

                if (activationCard) {
                    const selectedMethod = paymentsData && typeof paymentsData.selected_method === 'string'
                        ? paymentsData.selected_method
                        : 'transferencia';
                    let nextState = 'disabled';
                    if (sepaIsDisabled) {
                        nextState = 'reactivation';
                    } else if (sepaIsActive) {
                        nextState = 'locked';
                    } else if (sepaData.requested || needsActivation) {
                        nextState = 'requested';
                    } else if (selectedMethod === 'domiciliacion') {
                        nextState = 'enabled';
                    }

                    activationCard.setAttribute('data-state', nextState);
                    if (detailCard) {
                        detailCard.setAttribute('data-state', nextState);
                    }

                    if (typeof activationCard.__goUpdatePanels === 'function') {
                        activationCard.__goUpdatePanels(nextState);
                    }
                }

                const awaitingMessage = document.querySelector('[data-sepa-awaiting-message]');
                if (awaitingMessage) {
                    const shouldShowAwaiting = awaitingValidation && !sepaIsDisabled;
                    awaitingMessage.hidden = !shouldShowAwaiting;
                    awaitingMessage.setAttribute('aria-hidden', shouldShowAwaiting ? 'false' : 'true');
                    if (awaitingValidation) {
                        awaitingMessage.textContent = sepaText.awaitingMessage;
                    }
                }

                if (reactivationContainer) {
                    const shouldShowReactivation = sepaIsDisabled;
                    reactivationContainer.hidden = !shouldShowReactivation;
                    reactivationContainer.setAttribute('aria-hidden', shouldShowReactivation ? 'false' : 'true');
                }

                if (reactivationStatus) {
                    const shouldShowStatus = sepaIsDisabled;
                    reactivationStatus.hidden = !shouldShowStatus;
                    reactivationStatus.setAttribute('aria-hidden', shouldShowStatus ? 'false' : 'true');
                    if (shouldShowStatus) {
                        reactivationStatus.textContent = 'La domiciliación bancaria ha sido desactivada. Ponte en contacto con garantias@360vo.es';
                    }
                }

                const requestedFlag = typeof sepaData.requested !== 'undefined'
                    ? Boolean(sepaData.requested)
                    : true;
                const downloadAction = document.querySelector('[data-sepa-download]');
                if (downloadAction) {
                    let shouldShowDownload = true;
                    if (awaitingValidation) {
                        shouldShowDownload = hasSignedDocument;
                    } else {
                        shouldShowDownload = !(!requestedFlag && !hasPendingDocument);
                    }
                    downloadAction.hidden = !shouldShowDownload;
                    downloadAction.setAttribute('aria-hidden', shouldShowDownload ? 'false' : 'true');
                }

                const downloadStep = document.querySelector('[data-sepa-step-download]');
                if (downloadStep) {
                    const downloadStepNumber = downloadStep.querySelector('[data-sepa-step-number]');
                    const downloadStepLabel = downloadStep.querySelector('[data-sepa-step-label]');
                    if (awaitingValidation) {
                        const shouldShowStep = hasSignedDocument;
                        downloadStep.hidden = !shouldShowStep;
                        downloadStep.setAttribute('aria-hidden', shouldShowStep ? 'false' : 'true');
                        if (downloadStepNumber) {
                            downloadStepNumber.hidden = true;
                            downloadStepNumber.setAttribute('aria-hidden', 'true');
                        }
                        const textValue = sepaText.signedDocument || 'Tu SEPA firmado';
                        if (downloadStepLabel) {
                            downloadStepLabel.textContent = textValue;
                        } else {
                            const label = document.createElement('span');
                            label.setAttribute('data-sepa-step-label', '');
                            label.textContent = textValue;
                            downloadStep.appendChild(label);
                        }
                    } else {
                        const hideDownload = (!requestedFlag) && !hasPendingDocument;
                        downloadStep.hidden = hideDownload;
                        downloadStep.setAttribute('aria-hidden', hideDownload ? 'true' : 'false');
                        if (downloadStepNumber) {
                            downloadStepNumber.hidden = false;
                            downloadStepNumber.setAttribute('aria-hidden', 'false');
                        }
                        const textValue = sepaText.downloadPrompt || 'Descarga el documento';
                        if (downloadStepLabel) {
                            downloadStepLabel.textContent = textValue;
                        } else {
                            const label = document.createElement('span');
                            label.setAttribute('data-sepa-step-label', '');
                            label.textContent = textValue;
                            downloadStep.appendChild(label);
                        }
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
                                ),
                            );
                            removeButton.hidden = !hasServerDocument;
                            removeButton.setAttribute('aria-hidden', hasServerDocument ? 'false' : 'true');
                        }
                    }
                }

                const pendingDocument = sepaData.documents && sepaData.documents.pending
                    ? sepaData.documents.pending
                    : null;
                const hasPendingDocument = Boolean(
                    pendingDocument
                    && (pendingDocument.url || pendingDocument.hash || pendingDocument.filename),
                );
                const signedDocument = sepaData.documents && sepaData.documents.signed
                    ? sepaData.documents.signed
                    : null;
                const hasSignedDocument = Boolean(
                    signedDocument
                    && (signedDocument.url || signedDocument.hash || signedDocument.filename),
                );
                if (sepaPendingLink instanceof HTMLAnchorElement) {
                    if (hasPendingDocument) {
                        const pendingUrl = typeof pendingDocument.url === 'string'
                            ? pendingDocument.url.trim()
                            : '';
                        const resolvedUrl = pendingUrl || sepaPendingDefaultHref || '#';
                        sepaPendingLink.href = resolvedUrl;
                        sepaPendingLink.hidden = false;
                        sepaPendingLink.setAttribute('aria-hidden', 'false');
                        sepaPendingLink.removeAttribute('tabindex');
                    } else {
                        sepaPendingLink.href = sepaPendingDefaultHref || '#';
                        sepaPendingLink.hidden = true;
                        sepaPendingLink.setAttribute('aria-hidden', 'true');
                        sepaPendingLink.setAttribute('tabindex', '-1');
                    }
                }
                if (sepaPendingLabel) {
                    const labelCandidates = [];
                    if (hasPendingDocument) {
                        const pendingLabel = typeof pendingDocument.label === 'string'
                            ? pendingDocument.label.trim()
                            : '';
                        const pendingFilename = typeof pendingDocument.filename === 'string'
                            ? pendingDocument.filename.trim()
                            : '';
                        if (pendingLabel) {
                            labelCandidates.push(pendingLabel);
                        }
                        if (pendingFilename) {
                            labelCandidates.push(pendingFilename);
                        }
                    }
                    const nextLabel = labelCandidates.find((value) => value !== '') || sepaPendingDefaultLabel;
                    sepaPendingLabel.textContent = nextLabel;
                }
                const hasGeneratedMandate = hasPendingDocument || hasSignedDocument || sepaData.status !== null;

                if (paymentActivation && typeof paymentActivation.__goSetGenerated === 'function') {
                    paymentActivation.__goSetGenerated(hasGeneratedMandate);
                } else if (detailCard) {
                    detailCard.setAttribute('data-generated', hasGeneratedMandate ? 'true' : 'false');
                }

                return;
            }

            sepaIbanController.setValue('');
            syncSignedDocumentBlocks(null);
            const reactivationContainer = document.querySelector('[data-sepa-reactivation]');
            const reactivationStatus = document.querySelector('[data-sepa-reactivation-status]');
            if (reactivationContainer) {
                reactivationContainer.hidden = true;
                reactivationContainer.setAttribute('aria-hidden', 'true');
            }
            if (reactivationStatus) {
                reactivationStatus.hidden = true;
                reactivationStatus.setAttribute('aria-hidden', 'true');
                reactivationStatus.textContent = '';
            }
            if (paymentActivation && typeof paymentActivation.__goSetGenerated === 'function') {
                paymentActivation.__goSetGenerated(false);
            } else if (paymentDetail) {
                paymentDetail.setAttribute('data-generated', 'false');
            }
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

                        applyPaymentsSnapshot(data.payments);

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
        const paymentDetail = document.querySelector('[data-payment-detail]');
        if (paymentActivation) {
            const checkbox = paymentActivation.querySelector('[data-payment-toggle]');
            const detail = paymentDetail;
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

            paymentActivation.__goUpdatePanels = updatePanels;

            const setGenerated = (value) => {
                generated = value;
                if (detail) {
                    detail.setAttribute('data-generated', value ? 'true' : 'false');
                }
                updatePanels(currentState);
            };

            paymentActivation.__goSetGenerated = setGenerated;

            const initialState = paymentActivation.getAttribute('data-state') || 'disabled';
            currentState = initialState;
            updatePanels(initialState);

            if (checkbox) {
                checkbox.addEventListener('change', () => {
                    const nextState = checkbox.checked ? 'enabled' : 'disabled';
                    updatePanels(nextState);
                });
            }

        }

        (() => {
            const detail = paymentDetail;
            if (!detail) {
                return;
            }

            const form = detail.querySelector('[data-sepa-form]');
            if (!form) {
                return;
            }

            const fields = Array.from(form.querySelectorAll('[data-sepa-field]'))
                .filter((element) => element instanceof HTMLInputElement);
            if (!fields.length) {
                return;
            }

            const generateButton = detail.querySelector('[data-payment-generate]');
            if (!(generateButton instanceof HTMLButtonElement)) {
                return;
            }

            const getActivationState = () => detail.getAttribute('data-state') || 'disabled';
            const isGenerated = () => detail.getAttribute('data-generated') === 'true';

            const messages = {
                required: strings.requiredField || 'Este campo es obligatorio.',
                postalCode: strings.postalCode || 'Introduce un código postal válido.',
                iban: strings.iban || 'Introduce un IBAN válido.',
                swift: strings.swift || 'Introduce un código SWIFT/BIC válido.',
            };

            const validators = {
                codigo_postal: (value) => POSTAL_CODE_REGEX.test(value),
                numero_cuenta: (value) => isValidIban(value),
                swift_bic: (value) => SWIFT_REGEX.test(value),
            };

            const validateField = (field, report = false) => {
                const key = field.dataset.sepaField || '';
                const rawValue = typeof field.value === 'string' ? field.value.trim() : '';
                const required = field.hasAttribute('required');
                let valid = true;
                let message = '';

                if (required && rawValue === '') {
                    valid = false;
                    message = messages.required;
                } else if (rawValue !== '' && validators[key]) {
                    valid = validators[key](rawValue);
                    if (!valid) {
                        if (key === 'codigo_postal') {
                            message = messages.postalCode;
                        } else if (key === 'numero_cuenta') {
                            message = messages.iban;
                        } else {
                            message = messages.swift;
                        }
                    }
                }

                if (typeof field.setCustomValidity === 'function') {
                    field.setCustomValidity(valid ? '' : message);
                }

                if (valid) {
                    field.removeAttribute('aria-invalid');
                } else {
                    field.setAttribute('aria-invalid', 'true');
                }

                if (report && !valid && typeof field.reportValidity === 'function') {
                    field.reportValidity();
                }

                return valid;
            };

            const validateForm = (report = false) => fields.every((field) => validateField(field, report));

            const defaultCountryField = fields.find((field) => field.dataset.sepaField === 'pais_deudor');
            if (defaultCountryField && defaultCountryField.value.trim() === '') {
                const defaultCountry = sepaCreditor && typeof sepaCreditor.country === 'string'
                    ? sepaCreditor.country.trim()
                    : '';
                defaultCountryField.value = defaultCountry !== '' ? defaultCountry : 'España';
            }

            const updateButtonState = () => {
                const state = getActivationState();
                const ready = state === 'enabled' && !isGenerated() && validateForm(false);
                generateButton.disabled = !ready;
                generateButton.setAttribute('aria-disabled', ready ? 'false' : 'true');
            };

            fields.forEach((field) => {
                field.addEventListener('input', () => {
                    validateField(field, false);
                    updateButtonState();
                });
                field.addEventListener('blur', () => {
                    validateField(field, false);
                    updateButtonState();
                });
            });

            const observer = new MutationObserver(() => {
                updateButtonState();
            });
            observer.observe(detail, { attributes: true, attributeFilter: ['data-state', 'data-generated'] });

            updateButtonState();

            const setGenerateLoading = (loading) => {
                generateButton.classList.toggle('account-button--loading', Boolean(loading));
            };

            const handleSepaGeneration = async () => {
                if (generateButton.disabled) {
                    return;
                }
                if (!restEndpoint || !restNonce) {
                    const fallback = strings.error || strings.invalid || 'No se han podido guardar los cambios.';
                    setStatus(fallback, 'error');
                    updateButtonState();
                    return;
                }

                if (!validateForm(true)) {
                    updateButtonState();
                    return;
                }

                generateButton.disabled = true;
                generateButton.setAttribute('aria-disabled', 'true');
                setGenerateLoading(true);
                setStatus(strings.sepaGenerateLoading || 'Generando mandato…', 'info');

                try {
                    const mandate = await ensureSepaMandateReady(fields);
                    if (!mandate || !mandate.blob) {
                        throw new Error('mandate_generation_failed');
                    }

                    const fileName = mandate.filename || buildSepaFilename(mandate.reference || '');
                    const pdfBlob = mandate.blob instanceof Blob
                        ? mandate.blob
                        : new Blob([mandate.blob], { type: 'application/pdf' });

                    const formData = new FormData();
                    formData.append('account_sepa_pending', pdfBlob, fileName);
                    formData.append('reference', mandate.reference || '');
                    formData.append('generated_at', mandate.generatedAt || '');
                    formData.append('signature_locality', mandate.signatureLocality || '');
                    formData.append('signature_date', mandate.signatureDate || '');
                    formData.append('sepa', JSON.stringify(buildSepaRequestFields(fields)));

                    const response = await fetch(`${restEndpoint}/sepa/generate`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-WP-Nonce': restNonce,
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || !payload || payload.success !== true) {
                        const errorMessage = payload && typeof payload.message === 'string' ? payload.message : '';
                        throw new Error(errorMessage || 'request_failed');
                    }

                    applyPaymentsSnapshot(payload.payments);
                    updateButtonState();
                    setStatus(
                        strings.sepaGenerateSuccess
                            || 'Mandato SEPA generado correctamente. Descárgalo para firmarlo.',
                        'success',
                    );
                } catch (error) {
                    console.error('[account] sepa generate error', error);
                    const message = error && typeof error.message === 'string' && error.message !== 'request_failed'
                        ? error.message
                        : '';
                    setStatus(
                        message
                            || strings.sepaGenerateError
                            || 'No se ha podido generar el mandato SEPA. Revisa los datos e inténtalo de nuevo.',
                        'error',
                    );
                    updateButtonState();
                    return;
                } finally {
                    setGenerateLoading(false);
                }
            };

            generateButton.addEventListener('click', (event) => {
                event.preventDefault();
                handleSepaGeneration();
            });
        })();

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
