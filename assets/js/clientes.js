(() => {
    'use strict';

    const DEBOUNCE_DELAY = 300;

    document.addEventListener('DOMContentLoaded', () => {
        const config = window.__GO_CLIENTES__ || {};
        const restRoot = (config.rest && config.rest.root) || '/wp-json/';
        const restNonce = (config.rest && config.rest.nonce) || '';
        const perPage = (config.pagination && config.pagination.perPage) || 20;
        const strings = config.strings || {};
        const icons = config.icons || {};

        const listContainer = document.querySelector('.guarantees-list');
        const table = document.querySelector('.guarantees-table');
        const tbody = table ? table.querySelector('tbody') : null;
        const searchInput = document.getElementById('clientes-search');
        const closeIcon = document.querySelector('.guarantees-list__close-icon');
        const channelSelect = document.getElementById('clientes-channel-filter');
        const sentinel = document.getElementById('scroll-end');
        const spinner = sentinel ? sentinel.querySelector('.spinner') : null;
        const panel1 = document.getElementById('detail-panel-1');
        const panel2 = document.getElementById('detail-panel-2');

        if (!tbody || !panel1 || !panel2) {
            return;
        }

        if (closeIcon) {
            closeIcon.setAttribute('role', 'button');
            if (!closeIcon.hasAttribute('tabindex')) {
                closeIcon.setAttribute('tabindex', '0');
            }
        }

        const state = {
            page: 0,
            totalPages: 1,
            isLoading: false,
            search: '',
            channel: '',
        };

        const cache = new Map();
        let selectedRow = null;
        let lastRowIndex = -1;
        let debounceTimer = null;
        const COLUMN_COUNT = 5;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function escapeAttribute(value) {
            return escapeHtml(value).replace(/`/g, '&#096;');
        }

        function joinNonEmpty(values, separator = ', ') {
            return values.filter((value) => {
                if (typeof value === 'number') {
                    return true;
                }

                return typeof value === 'string' && value.trim() !== '';
            }).join(separator);
        }

        function getDisplayName(name) {
            if (!name || typeof name !== 'object') {
                return '';
            }

            const first = typeof name.first === 'string' ? name.first.trim() : '';
            const last = typeof name.last === 'string' ? name.last.trim() : '';
            const full = typeof name.full === 'string' ? name.full.trim() : '';
            const personal = typeof name.personal === 'string' ? name.personal.trim() : '';
            const composed = [first, last].filter(Boolean).join(' ').trim();

            return full || composed || personal || '';
        }

        function getCommercialDisplay(commercial) {
            if (!commercial || typeof commercial !== 'object') {
                return '';
            }

            const first = typeof commercial.first_name === 'string' ? commercial.first_name.trim() : '';
            const last = typeof commercial.last_name === 'string' ? commercial.last_name.trim() : '';
            const full = typeof commercial.full_name === 'string' ? commercial.full_name.trim() : '';
            const name = typeof commercial.name === 'string' ? commercial.name.trim() : '';
            const composed = [first, last].filter(Boolean).join(' ').trim();

            return full || composed || name;
        }

        function normalizeChannelOption(option) {
            if (!option || typeof option !== 'object') {
                return null;
            }

            const value = typeof option.value === 'string' ? option.value.trim() : '';
            const label = typeof option.label === 'string' ? option.label.trim() : '';

            if (value === '' || label === '') {
                return null;
            }

            return { value, label };
        }

        function updateChannelFilterOptions(options) {
            if (!channelSelect) {
                return;
            }

            const normalized = Array.isArray(options)
                ? options.map((option) => normalizeChannelOption(option)).filter(Boolean)
                : [];

            const seen = new Set();
            const unique = [];

            normalized.forEach((option) => {
                if (seen.has(option.value)) {
                    return;
                }
                seen.add(option.value);
                unique.push(option);
            });

            const previousValue = state.channel || channelSelect.value || '';
            const placeholder = typeof strings.channelFilterAll === 'string'
                ? strings.channelFilterAll
                : 'Todos los canales';

            channelSelect.innerHTML = '';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = placeholder;
            channelSelect.appendChild(defaultOption);

            unique.forEach((option) => {
                const element = document.createElement('option');
                element.value = option.value;
                element.textContent = option.label;
                channelSelect.appendChild(element);
            });

            if (previousValue && !seen.has(previousValue)) {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = previousValue;
                fallbackOption.textContent = previousValue;
                channelSelect.appendChild(fallbackOption);
                seen.add(previousValue);
            }

            channelSelect.value = previousValue && seen.has(previousValue) ? previousValue : '';
            channelSelect.disabled = unique.length === 0;
        }

        function initResizableColumns(table) {
            if (!table || table.dataset.resizableInitialized === 'true') {
                return;
            }

            if (window.innerWidth < 1024) {
                return;
            }

            const wrapper = table.parentElement;
            if (!wrapper || !table.tHead || !table.tBodies.length) {
                return;
            }

            const headers = Array.from(table.tHead.querySelectorAll('th'));
            if (!headers.length) {
                return;
            }

            wrapper.style.position = wrapper.style.position || 'relative';
            table.style.tableLayout = 'fixed';

            let colgroup = table.querySelector('colgroup');
            if (!colgroup) {
                colgroup = document.createElement('colgroup');
                headers.forEach(() => {
                    colgroup.appendChild(document.createElement('col'));
                });
                table.insertBefore(colgroup, table.firstChild);
            }

            const cols = Array.from(colgroup.children);
            const MIN_WIDTH = 120;

            let widths = headers.map((header, index) => {
                const col = cols[index];
                const defaultWidth = col ? parseInt(col.dataset.defaultWidth || '', 10) : NaN;
                if (Number.isFinite(defaultWidth) && defaultWidth > 0) {
                    return Math.max(defaultWidth, MIN_WIDTH);
                }

                const headerWidth = header.getBoundingClientRect().width;
                return Math.max(Math.round(headerWidth), MIN_WIDTH);
            });

            widths.forEach((width, index) => {
                if (cols[index]) {
                    cols[index].style.width = `${width}px`;
                }
            });

            const overlay = document.createElement('div');
            overlay.className = 'column-resizers';
            wrapper.appendChild(overlay);

            const handles = [];

            function updateOverlay() {
                overlay.style.width = `${table.offsetWidth}px`;
                overlay.style.height = `${table.offsetHeight}px`;
                overlay.style.top = `${table.offsetTop}px`;
                overlay.style.left = `${table.offsetLeft}px`;

                handles.forEach((handle, index) => {
                    const header = headers[index];
                    if (!header) {
                        return;
                    }

                    const left = header.offsetLeft + header.offsetWidth;
                    handle.style.left = `${left - 4}px`;
                    handle.style.height = `${table.offsetHeight}px`;
                });
            }

            function bindHandle(handle, index) {
                handle.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    const startX = event.pageX;
                    const startWidth = widths[index];

                    function onMove(moveEvent) {
                        const delta = moveEvent.pageX - startX;
                        const nextWidth = Math.max(startWidth + delta, MIN_WIDTH);

                        widths[index] = nextWidth;

                        if (cols[index]) {
                            cols[index].style.width = `${nextWidth}px`;
                        }

                        updateOverlay();
                    }

                    function onUp() {
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                    }

                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
            }

            for (let i = 0; i < widths.length - 1; i += 1) {
                const handle = document.createElement('span');
                handle.className = 'column-resizer';
                overlay.appendChild(handle);
                handles.push(handle);
                bindHandle(handle, i);
            }

            const mutationObserver = new MutationObserver(() => {
                updateOverlay();
            });
            mutationObserver.observe(table.tBodies[0], { childList: true });

            window.addEventListener('resize', updateOverlay);

            table.__goUpdateColumnOverlay = updateOverlay;
            table.dataset.resizableInitialized = 'true';

            requestAnimationFrame(updateOverlay);
        }

        function setSpinner(visible) {
            if (!spinner) {
                return;
            }

            spinner.style.display = visible ? 'block' : 'none';
        }

        function updateCloseIcon() {
            if (!closeIcon || !searchInput) {
                return;
            }

            if (searchInput.value.trim() !== '') {
                closeIcon.classList.add('visible');
            } else {
                closeIcon.classList.remove('visible');
            }
        }

        function formatOffers(offers) {
            if (!Array.isArray(offers) || offers.length === 0) {
                return `<span class="clients-table__empty">${escapeHtml(strings.offersEmpty || 'Sin ofertas activas')}</span>`;
            }

            return offers
                .map((offer) => {
                    const label = escapeHtml(offer.label || '');
                    const discount = typeof offer.discount === 'number' && offer.discount > 0
                        ? `<span class="clients-table__offer-discount">${Math.round(offer.discount)}%</span>`
                        : '';

                    return `<span class="guarantees-list__badge">${label}${discount}</span>`;
                })
                .join('');
        }

        function formatCommercialSummary(commercials) {
            if (!Array.isArray(commercials) || commercials.length === 0) {
                return strings.commercialsEmpty || 'Sin comercial asignado';
            }

            return commercials
                .map((commercial) => getCommercialDisplay(commercial))
                .map((display) => escapeHtml(display || ''))
                .filter((name) => name !== '')
                .join(', ');
        }

        function formatCount(value) {
            if (typeof value === 'number' && Number.isFinite(value)) {
                return value.toLocaleString();
            }

            return '0';
        }

        function renderRow(item) {
            const profile = item.profile || {};
            const name = item.name || {};
            const registered = item.registered || {};
            const salesChannel = item.sales_channel || {};
            const guarantees = item.guarantees || {};
            const commercials = item.commercials || [];

            const displayName = getDisplayName(name);
            const safeName = displayName || name.company || '';
            const fallbackName = safeName !== '' ? safeName : '—';
            const avatarAlt = safeName !== '' ? safeName : (strings.client || 'Cliente');
            const avatar = profile.avatar
                ? `<img src="${escapeAttribute(profile.avatar)}" alt="${escapeAttribute(avatarAlt)}" class="clients-table__avatar">`
                : `<span class="clients-table__initials">${escapeHtml(profile.initials || '')}</span>`;
            const companyName = typeof name.company === 'string' ? name.company.trim() : '';
            const channelLabel = typeof salesChannel.label === 'string' ? salesChannel.label.trim() : '';
            const channelHtml = channelLabel !== ''
                ? `<span class="clients-table__channel${companyName === '' ? ' clients-table__channel--solo' : ''}">${escapeHtml(channelLabel)}</span>`
                : '';
            const identityLine = companyName !== ''
                ? `<span class="clients-table__company">${escapeHtml(companyName)}${channelHtml}</span>`
                : channelHtml;
            const offersHtml = formatOffers(item.offers);
            const commercialsText = formatCommercialSummary(commercials);
            const registeredLabel = strings.registered || 'Registro';

            const tr = document.createElement('tr');
            tr.className = 'guarantees-table__row';
            tr.tabIndex = 0;
            tr.dataset.id = String(item.id);
            tr.dataset.index = String(tbody.children.length);

            tr.innerHTML = `
                <td data-label="${escapeHtml(strings.client || 'Cliente')}" class="clients-table__client-cell">
                    <div class="clients-table__client">
                        <div class="clients-table__avatar-wrapper">${avatar}</div>
                        <div class="clients-table__identity">
                            <span class="clients-table__name">${escapeHtml(fallbackName)}</span>
                            ${identityLine || ''}
                        </div>
                    </div>
                </td>
                <td data-label="${escapeHtml(registeredLabel)}" class="clients-table__registered">
                    ${registered.display ? escapeHtml(registered.display) : '—'}
                </td>
                <td data-label="${escapeHtml(strings.offers || 'Ofertas activas')}" class="clients-table__offers">${offersHtml}</td>
                <td data-label="${escapeHtml(strings.guarantees || 'Nº Garantías')}" class="clients-table__meta clients-table__meta--count">
                    ${formatCount(guarantees.count)}
                </td>
                <td data-label="${escapeHtml(strings.commercials || 'Comercial')}" class="clients-table__meta clients-table__meta--commercial">
                    ${escapeHtml(commercialsText || '—')}
                </td>
            `;

            tr.addEventListener('click', () => selectRow(tr, item));
            tr.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectRow(tr, item);
                }
            });

            return tr;
        }

        function selectRow(row, item) {
            if (selectedRow === row) {
                return;
            }

            const previousIndex = lastRowIndex;
            const nextIndex = parseInt(row.dataset.index || '0', 10);
            const direction = Number.isFinite(previousIndex) && nextIndex < previousIndex ? 'backward' : 'forward';

            if (selectedRow) {
                selectedRow.classList.remove('selected');
            }

            selectedRow = row;
            lastRowIndex = nextIndex;
            row.classList.add('selected');

            showDetail(item, direction);
        }

        function formatLink(protocol, value) {
            if (typeof value !== 'string' || value.trim() === '') {
                return '<span class="client-detail__empty">—</span>';
            }

            const trimmed = value.trim();
            const href = `${protocol}:${protocol === 'tel' ? trimmed.replace(/\s+/g, '') : trimmed}`;

            return `<a href="${escapeAttribute(href)}">${escapeHtml(trimmed)}</a>`;
        }

        function renderOffersList(offers) {
            if (!Array.isArray(offers) || offers.length === 0) {
                return `<p class="client-detail__empty">${escapeHtml(strings.offersEmpty || 'Sin ofertas activas')}</p>`;
            }

            const items = offers.map((offer) => {
                const label = escapeHtml(offer.label || '');
                const discount = typeof offer.discount === 'number' && offer.discount > 0
                    ? `<span class="client-detail__chip-extra">${Math.round(offer.discount)}%</span>`
                    : '';
                const expiry = offer.expires ? `<span class="client-detail__chip-meta">${escapeHtml(offer.expires)}</span>` : '';

                return `<li class="client-detail__chip">${label}${discount}${expiry}</li>`;
            });

            return `<ul class="client-detail__chips">${items.join('')}</ul>`;
        }

        function renderCommercialsList(commercials) {
            if (!Array.isArray(commercials) || commercials.length === 0) {
                return `<p class="client-detail__empty">${escapeHtml(strings.commercialsEmpty || 'Sin comercial asignado')}</p>`;
            }

            const items = commercials.map((commercial) => {
                const display = escapeHtml(getCommercialDisplay(commercial) || '');
                const email = commercial.email ? `<span>${formatLink('mailto', commercial.email)}</span>` : '';
                const phone = commercial.phone ? `<span>${formatLink('tel', commercial.phone)}</span>` : '';

                return `<li class="client-detail__commercial">${display}${email}${phone}</li>`;
            });

            return `<ul class="client-detail__commercials">${items.join('')}</ul>`;
        }

        function formatMultiline(value) {
            if (typeof value !== 'string') {
                return '';
            }

            const trimmed = value.trim();
            if (trimmed === '') {
                return '';
            }

            return escapeHtml(trimmed).replace(/\n/g, '<br>');
        }

        function renderContactActions(contact) {
            const loginEmail = typeof contact.login_email === 'string' && contact.login_email ? contact.login_email.trim() : '';
            const fallbackEmail = typeof contact.email === 'string' && contact.email ? contact.email.trim() : '';
            const baseEmail = loginEmail !== '' ? loginEmail : fallbackEmail;
            const notificationEmail = typeof contact.notification_email === 'string' && contact.notification_email
                ? contact.notification_email.trim()
                : '';
            const phone = typeof contact.phone === 'string' && contact.phone ? contact.phone.trim() : '';

            const actions = [];

            if (baseEmail !== '') {
                actions.push({
                    type: 'email',
                    href: `mailto:${baseEmail}`,
                    label: strings.loginEmail || 'Email de inicio de sesión',
                    value: baseEmail,
                    note: '',
                });
            }

            if (notificationEmail !== '') {
                const isSame = baseEmail !== '' && notificationEmail.toLowerCase() === baseEmail.toLowerCase();
                actions.push({
                    type: 'email',
                    href: `mailto:${notificationEmail}`,
                    label: strings.notificationEmail || 'Email de notificaciones',
                    value: notificationEmail,
                    note: isSame ? (strings.notificationEmailSame || '') : '',
                });
            }

            if (phone !== '') {
                const sanitized = phone.replace(/[^0-9+]/g, '');
                actions.push({
                    type: 'phone',
                    href: `tel:${sanitized}`,
                    label: strings.contactPhone || 'Teléfono de contacto',
                    value: phone,
                    note: '',
                });
            }

            if (actions.length === 0) {
                return `<p class="client-detail__contact-empty">${escapeHtml(strings.contactEmpty || 'No hay datos de contacto disponibles')}</p>`;
            }

            const items = actions.map((action) => {
                const icon = action.type === 'phone' ? icons.phone : icons.email;
                const note = action.note && action.note.trim() !== ''
                    ? `<span class="client-detail__contact-note">${escapeHtml(action.note)}</span>`
                    : '';

                return `
                    <li class="fast-actions__item">
                        <a class="fast-actions__link" href="${escapeAttribute(action.href)}">
                            <span class="fast-actions__icon" aria-hidden="true">${icon || ''}</span>
                            <span class="fast-actions__label">
                                ${escapeHtml(action.label)}
                                <span class="client-detail__contact-value">${escapeHtml(action.value)}</span>
                                ${note}
                            </span>
                        </a>
                    </li>
                `;
            });

            return `
                <div class="client-detail__contact-actions">
                    <ul class="fast-actions">${items.join('')}</ul>
                </div>
            `;
        }

        function renderWorkshop(workshop) {
            const data = workshop && typeof workshop === 'object' ? workshop : {};
            const hasWorkshop = Boolean(data.has_workshop);
            const statusClass = hasWorkshop ? 'client-detail__status--success' : 'client-detail__status--info';
            const statusLabel = hasWorkshop
                ? (strings.workshopYes || 'Con taller propio')
                : (strings.workshopNo || 'Sin taller propio');

            let details = '';

            if (hasWorkshop) {
                const fields = [
                    { key: 'name', label: strings.workshopName || 'Nombre del taller' },
                    { key: 'contact_person', label: strings.workshopContact || 'Persona de contacto' },
                    { key: 'phone', label: strings.workshopPhone || 'Teléfono', type: 'phone' },
                    { key: 'email', label: strings.workshopEmail || 'Email', type: 'email' },
                    { key: 'address', label: strings.workshopAddress || 'Dirección', formatter: formatMultiline },
                    { key: 'tax_id', label: strings.workshopTaxId || 'CIF/NIF' },
                ];

                const items = fields.map((field) => {
                    const raw = typeof data[field.key] === 'string' ? data[field.key].trim() : '';

                    if (raw === '') {
                        return '';
                    }

                    let valueHtml = '';
                    if (field.type === 'phone') {
                        valueHtml = formatLink('tel', raw);
                    } else if (field.type === 'email') {
                        valueHtml = formatLink('mailto', raw);
                    } else if (typeof field.formatter === 'function') {
                        valueHtml = field.formatter(raw);
                        if (valueHtml === '') {
                            return '';
                        }
                    } else {
                        valueHtml = escapeHtml(raw);
                    }

                    return `
                        <div class="client-detail__item">
                            <dt>${escapeHtml(field.label)}</dt>
                            <dd>${valueHtml}</dd>
                        </div>
                    `;
                }).filter((item) => item !== '');

                if (items.length > 0) {
                    details = `<div class="client-detail__workshop-details">${items.join('')}</div>`;
                }
            }

            return `
                <section class="client-detail__section client-detail__section--workshop">
                    <h4 class="client-detail__section-title">${escapeHtml(strings.workshop || 'Taller propio')}</h4>
                    <p class="client-detail__status ${statusClass}">${escapeHtml(statusLabel)}</p>
                    ${details}
                </section>
            `;
        }

        function renderDetail(item) {
            const name = item.name || {};
            const registered = item.registered || {};
            const contact = item.contact || {};
            const company = item.company || {};
            const address = item.address || {};
            const payment = item.payment || {};
            const salesChannel = item.sales_channel || {};
            const guarantees = item.guarantees || {};
            const sepa = item.sepa || {};

            const displayName = getDisplayName(name);
            const avatarAlt = displayName || name.company || '';
            const avatar = item.profile && item.profile.avatar
                ? `<img src="${escapeAttribute(item.profile.avatar)}" alt="${escapeAttribute(avatarAlt)}" class="client-detail__avatar">`
                : `<span class="client-detail__avatar client-detail__avatar--initials">${escapeHtml(item.profile?.initials || '')}</span>`;

            const companyName = typeof name.company === 'string' ? name.company.trim() : '';
            const salesChannelLabel = typeof salesChannel.label === 'string' ? salesChannel.label.trim() : '';
            const companyLine = companyName !== '' ? `<p class="client-detail__company">${escapeHtml(companyName)}</p>` : '';
            const addressLines = joinNonEmpty([
                address.street || '',
                joinNonEmpty([address.zip || '', address.city || ''], ' '),
                joinNonEmpty([address.state || '', address.country || ''], ' '),
            ], '<br>');

            const sepaVariant = sepa.variant ? ` client-detail__status--${escapeHtml(sepa.variant)}` : '';
            const sepaMessage = sepa.label || strings.sepaEmpty || 'Sin información del mandato';
            const registeredLabel = strings.registered || 'Registro';
            const safeSalesChannel = salesChannelLabel !== '' ? escapeHtml(salesChannelLabel) : '—';
            const salesTagClass = salesChannelLabel !== '' ? '' : ' client-detail__stat-tag--muted';
            const paymentLabel = typeof payment.label === 'string' ? payment.label.trim() : '';
            const paymentDisplay = paymentLabel !== '' ? escapeHtml(paymentLabel) : '—';
            const paymentTagClass = paymentLabel !== '' ? '' : ' client-detail__stat-tag--muted';
            const registrationBadge = `
                <span class="client-detail__registration">
                    ${escapeHtml(registeredLabel)}
                    <time datetime="${escapeAttribute(registered.iso || '')}">${registered.display ? escapeHtml(registered.display) : '—'}</time>
                </span>
            `;
            const contactActions = renderContactActions(contact);
            const workshopSection = renderWorkshop(item.workshop);

            return `
                <div class="client-detail">
                    <header class="client-detail__header">
                        ${avatar}
                        <div class="client-detail__identity">
                            <h3 class="client-detail__title">${escapeHtml(displayName || strings.detailTitle || 'Detalles del cliente')}</h3>
                            ${companyLine}
                        </div>
                        ${registrationBadge}
                    </header>
                    <div class="client-detail__stats">
                        <div class="client-detail__stat client-detail__stat--guarantees">
                            <span class="client-detail__stat-label">${escapeHtml(strings.guarantees || 'Nº Garantías')}</span>
                            <span class="client-detail__stat-emphasis">${formatCount(guarantees.count)}</span>
                        </div>
                        <div class="client-detail__stat">
                            <span class="client-detail__stat-label">${escapeHtml(strings.salesChannel || 'Canal de venta')}</span>
                            <span class="client-detail__stat-tag${salesTagClass}">${safeSalesChannel}</span>
                        </div>
                        <div class="client-detail__stat">
                            <span class="client-detail__stat-label">${escapeHtml(strings.paymentMethod || 'Método de pago')}</span>
                            <span class="client-detail__stat-tag${paymentTagClass}">${paymentDisplay}</span>
                        </div>
                    </div>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.contact || 'Contacto')}</h4>
                        ${contactActions}
                    </section>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.company || 'Empresa')}</h4>
                        <dl class="client-detail__list client-detail__list--columns">
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.company || 'Empresa')}</dt>
                                <dd>${escapeHtml(company.name || '—')}</dd>
                            </div>
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.taxId || 'CIF/NIF')}</dt>
                                <dd>${escapeHtml(company.tax_id || '—')}</dd>
                            </div>
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.address || 'Dirección')}</dt>
                                <dd>${addressLines || '<span class="client-detail__empty">—</span>'}</dd>
                            </div>
                        </dl>
                    </section>
                    ${workshopSection}
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.offers || 'Ofertas activas')}</h4>
                        ${renderOffersList(item.offers)}
                    </section>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.commercials || 'Comercial')}</h4>
                        ${renderCommercialsList(item.commercials)}
                    </section>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.sepaStatus || 'Estado SEPA')}</h4>
                        <p class="client-detail__status${sepaVariant}">${escapeHtml(sepaMessage)}</p>
                    </section>
                </div>
            `;
        }

        let activePanel = panel1;
        let inactivePanel = panel2;

        function swapPanels(content, direction = 'forward') {
            const nextPanel = activePanel === panel1 ? panel2 : panel1;
            const previousPanel = activePanel;

            nextPanel.innerHTML = content;
            nextPanel.dataset.loadedId = content ? 'loaded' : '';

            const enterClass = direction === 'backward' ? 'slide-in-left' : 'slide-in-right';
            const leaveClass = direction === 'backward' ? 'slide-out-right' : 'slide-out-left';

            nextPanel.classList.add('active', enterClass);
            previousPanel.classList.add(leaveClass);

            const handleNextEnd = (event) => {
                if (event.target !== nextPanel) {
                    return;
                }

                nextPanel.classList.remove('slide-in-left', 'slide-in-right');
                if (nextPanel.__goAnimationTimeout) {
                    window.clearTimeout(nextPanel.__goAnimationTimeout);
                    nextPanel.__goAnimationTimeout = null;
                }
                nextPanel.removeEventListener('animationend', handleNextEnd);
            };

            const handlePreviousEnd = (event) => {
                if (event.target !== previousPanel) {
                    return;
                }

                previousPanel.classList.remove('slide-out-left', 'slide-out-right');
                previousPanel.classList.remove('active');
                previousPanel.innerHTML = '';
                if (previousPanel.__goAnimationTimeout) {
                    window.clearTimeout(previousPanel.__goAnimationTimeout);
                    previousPanel.__goAnimationTimeout = null;
                }
                previousPanel.removeEventListener('animationend', handlePreviousEnd);
            };

            nextPanel.addEventListener('animationend', handleNextEnd);
            previousPanel.addEventListener('animationend', handlePreviousEnd);

            if (nextPanel.__goAnimationTimeout) {
                window.clearTimeout(nextPanel.__goAnimationTimeout);
            }
            if (previousPanel.__goAnimationTimeout) {
                window.clearTimeout(previousPanel.__goAnimationTimeout);
            }

            nextPanel.__goAnimationTimeout = window.setTimeout(() => {
                handleNextEnd({ target: nextPanel });
            }, 400);

            previousPanel.__goAnimationTimeout = window.setTimeout(() => {
                handlePreviousEnd({ target: previousPanel });
            }, 400);

            activePanel = nextPanel;
            inactivePanel = previousPanel;
        }

        function showDetail(item, direction = 'forward') {
            if (!item) {
                showEmptyDetail(direction);
                return;
            }

            const content = renderDetail(item);
            swapPanels(content, direction);
        }

        function showEmptyDetail(direction = 'forward') {
            const content = `
                <div class="guarantee-detail__empty">
                    <h3 class="guarantee-detail__title">${escapeHtml(strings.detailTitle || 'Detalles del cliente')}</h3>
                    <p>${escapeHtml(strings.selectPrompt || 'Selecciona un cliente para consultar su información, asignar comerciales, gestionar ofertas y más.')}</p>
                </div>
            `;
            swapPanels(content, direction);
        }

        function clearSelection() {
            if (selectedRow) {
                selectedRow.classList.remove('selected');
            }
            selectedRow = null;
            lastRowIndex = -1;
        }

        async function loadPage(page, append = false) {
            if (state.isLoading) {
                return;
            }

            state.isLoading = true;
            setSpinner(true);

            if (!append) {
                tbody.innerHTML = '';
                cache.clear();
                clearSelection();
                showEmptyDetail();
            }

            const params = new URLSearchParams();
            params.set('page', String(page));
            params.set('per_page', String(perPage));
            if (state.search) {
                params.set('search', state.search);
            }
            if (state.channel) {
                params.set('channel', state.channel);
            }

            try {
                const response = await fetch(`${restRoot}go/v1/clientes?${params.toString()}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: restNonce ? { 'X-WP-Nonce': restNonce } : {},
                });

                if (!response.ok) {
                    throw new Error(`Request failed: ${response.status}`);
                }

                const data = await response.json();
                const items = Array.isArray(data.items) ? data.items : [];

                if (data && data.filters && data.filters.channels) {
                    updateChannelFilterOptions(data.filters.channels);
                }

                state.page = Number.isFinite(data.page) ? data.page : page;
                state.totalPages = Number.isFinite(data.total_pages) ? Math.max(1, data.total_pages) : state.totalPages;
                tbody.dataset.currentPage = String(state.page);
                tbody.dataset.totalPages = String(state.totalPages);

                if (!append && items.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.className = 'guarantees-table__row guarantees-table__row--empty';
                    emptyRow.innerHTML = `<td colspan="${COLUMN_COUNT}">${escapeHtml(strings.noResults || 'No se han encontrado clientes con los filtros actuales.')}</td>`;
                    tbody.appendChild(emptyRow);
                    if (typeof table.__goUpdateColumnOverlay === 'function') {
                        table.__goUpdateColumnOverlay();
                    }
                    return;
                }

                items.forEach((item) => {
                    cache.set(String(item.id), item);
                    const row = renderRow(item);
                    tbody.appendChild(row);
                });

                if (typeof table.__goUpdateColumnOverlay === 'function') {
                    table.__goUpdateColumnOverlay();
                }

                if (append && selectedRow) {
                    selectedRow.focus({ preventScroll: true });
                }
            } catch (error) {
                console.error('Error loading clients', error);
                if (!append) {
                    const errorRow = document.createElement('tr');
                    errorRow.className = 'guarantees-table__row guarantees-table__row--empty';
                    errorRow.innerHTML = `<td colspan="${COLUMN_COUNT}">${escapeHtml(strings.error || 'No se ha podido cargar la información de clientes.')}</td>`;
                    tbody.appendChild(errorRow);
                    if (typeof table.__goUpdateColumnOverlay === 'function') {
                        table.__goUpdateColumnOverlay();
                    }
                }
            } finally {
                state.isLoading = false;
                setSpinner(false);
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                updateCloseIcon();

                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                debounceTimer = setTimeout(() => {
                    const value = searchInput.value.trim();
                    state.search = value;
                    loadPage(1, false);
                }, DEBOUNCE_DELAY);
            });
        }

        if (closeIcon && searchInput) {
            closeIcon.addEventListener('click', () => {
                searchInput.value = '';
                state.search = '';
                updateCloseIcon();
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                loadPage(1, false);
                searchInput.focus();
            });

            closeIcon.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    closeIcon.click();
                }
            });
        }

        if (channelSelect) {
            channelSelect.addEventListener('change', () => {
                state.channel = channelSelect.value;
                loadPage(1, false);
            });
        }

        if (sentinel && listContainer) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && !state.isLoading && state.page < state.totalPages) {
                        loadPage(state.page + 1, true);
                    }
                });
            }, {
                root: listContainer,
                threshold: 0.1,
                rootMargin: '200px 0px',
            });

            observer.observe(sentinel);
        }

        updateCloseIcon();
        setSpinner(false);
        initResizableColumns(table);
        showEmptyDetail();
        loadPage(1, false);
    });
})();
