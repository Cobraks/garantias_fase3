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
        const iconEmail = icons.email || '';
        const iconPhone = icons.phone || '';
        const iconArrowDown = icons.arrowDown || '';
        const iconArrowUp = icons.arrowUp || '';
        const iconPersonAdd = icons.personAdd || '';
        const iconClose = icons.close || '';
        const iconSearch = icons.search || '';
        const iconManageOffers = icons.manageOffers || '';
        const iconManageSepa = icons.manageSepa || '';
        const permissions = config.permissions || {};
        const canAssignCommercials = Boolean(permissions.canAssignCommercials);
        const router = config.router || {};
        const basePath = typeof router.basePath === 'string' ? router.basePath : '';
        const normalizedBasePath = basePath ? (basePath.endsWith('/') ? basePath : `${basePath}/`) : '';

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

        let dialogIdCounter = 0;

        const commercialDirectory = {
            items: [],
            loading: false,
            loaded: false,
            error: '',
            promise: null,
        };

        const assignDialog = createAssignDialog();
        const offersDialog = createSimpleDialog({
            titleKey: 'manageOffersTitle',
            titleTemplateKey: 'manageOffersTitleTemplate',
            fallbackTitle: 'Gestionar ofertas',
            saveLabelKey: 'dialogSave',
        });
        const sepaDialog = createSimpleDialog({
            titleKey: 'manageSepaTitle',
            titleTemplateKey: 'manageSepaTitleTemplate',
            fallbackTitle: 'Gestionar SEPA',
            saveLabelKey: 'dialogSave',
        });

        if (canAssignCommercials) {
            fetchCommercialDirectory().catch(() => {});
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
        const slugIndex = new Map();
        let selectedRow = null;
        let lastRowIndex = -1;
        let debounceTimer = null;
        const COLUMN_COUNT = 5;

        function uniqueId(prefix) {
            dialogIdCounter += 1;
            return `${prefix}-${dialogIdCounter}`;
        }

        function normalizeCommercialEntry(entry) {
            if (!entry || typeof entry !== 'object') {
                return null;
            }

            const id = Number(entry.id) || 0;
            if (!id) {
                return null;
            }

            const displayName = getCommercialDisplay(entry) || '';
            const email = typeof entry.email === 'string' ? entry.email.trim() : '';
            const phone = typeof entry.phone === 'string' ? entry.phone.trim() : '';
            const avatar = typeof entry.avatar === 'string' ? entry.avatar.trim() : '';
            const initialsSource = typeof entry.initials === 'string' && entry.initials.trim() !== ''
                ? entry.initials.trim()
                : (displayName || email || '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .map((part) => part[0])
                    .join('');

            const clientCount = Number(entry.client_count);

            return {
                ...entry,
                id,
                name: displayName,
                email,
                phone,
                avatar,
                initials: initialsSource.slice(0, 2).toUpperCase(),
                client_count: Number.isFinite(clientCount) && clientCount > 0 ? clientCount : 0,
            };
        }

        function fetchCommercialDirectory(force = false) {
            if (!canAssignCommercials) {
                return Promise.resolve([]);
            }

            if (commercialDirectory.loaded && !force) {
                return Promise.resolve(commercialDirectory.items);
            }

            if (commercialDirectory.loading && commercialDirectory.promise) {
                return commercialDirectory.promise;
            }

            const params = new URLSearchParams();
            params.set('per_page', '200');

            commercialDirectory.loading = true;
            commercialDirectory.error = '';

            const request = fetch(`${restRoot}go/v1/clientes/comerciales?${params.toString()}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: restNonce ? { 'X-WP-Nonce': restNonce } : {},
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Request failed: ${response.status}`);
                    }
                    return response.json();
                })
                .then((data) => {
                    const items = Array.isArray(data.items) ? data.items : [];
                    const normalized = items
                        .map((entry) => normalizeCommercialEntry(entry))
                        .filter((entry) => entry && entry.id);

                    normalized.sort((a, b) => {
                        const nameA = (a.name || a.email || '').toLowerCase();
                        const nameB = (b.name || b.email || '').toLowerCase();
                        if (nameA < nameB) {
                            return -1;
                        }
                        if (nameA > nameB) {
                            return 1;
                        }
                        return a.id - b.id;
                    });

                    commercialDirectory.items = normalized;
                    commercialDirectory.loaded = true;
                    commercialDirectory.loading = false;
                    commercialDirectory.error = '';
                    return normalized;
                })
                .catch((error) => {
                    console.error('Error loading commercials directory', error);
                    commercialDirectory.items = [];
                    commercialDirectory.loaded = false;
                    commercialDirectory.loading = false;
                    commercialDirectory.error = (strings.assignCommercialError || 'No se ha podido cargar la lista de comerciales.');
                    throw error;
                })
                .finally(() => {
                    commercialDirectory.promise = null;
                });

            commercialDirectory.promise = request;
            return request;
        }

        function getSlugFromUrl() {
            if (!normalizedBasePath) {
                return '';
            }

            const path = window.location.pathname;
            if (!path.startsWith(normalizedBasePath)) {
                return '';
            }

            const remainder = path.slice(normalizedBasePath.length);
            const segments = remainder.split('/').filter((segment) => segment !== '');
            if (segments.length === 0) {
                return '';
            }

            try {
                return decodeURIComponent(segments[0]).toLowerCase();
            } catch (error) {
                return segments[0].toLowerCase();
            }
        }

        function buildClientUrl(slug) {
            if (!normalizedBasePath) {
                return window.location.pathname;
            }

            if (!slug) {
                return `${normalizedBasePath}`;
            }

            const encodedSlug = encodeURIComponent(slug);
            return `${normalizedBasePath}${encodedSlug}/`;
        }

        let initialSlug = getSlugFromUrl();

        function updateHistory(slugValue) {
            if (!normalizedBasePath) {
                return;
            }

            const targetPath = buildClientUrl(slugValue);
            const desiredPath = targetPath.endsWith('/') ? targetPath : `${targetPath}/`;
            if (window.location.pathname === desiredPath) {
                return;
            }

            const newUrl = `${desiredPath}${window.location.search}${window.location.hash}`;
            try {
                window.history.replaceState({}, '', newUrl);
            } catch (error) {
                // Ignore history errors (e.g. Safari private mode)
            }
        }

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

        function getPreferredSlug(item) {
            if (!item || typeof item !== 'object') {
                return '';
            }

            if (typeof item.username === 'string' && item.username.trim() !== '') {
                return item.username.trim();
            }
            if (typeof item.slug === 'string' && item.slug.trim() !== '') {
                return item.slug.trim();
            }
            if (typeof item.nicename === 'string' && item.nicename.trim() !== '') {
                return item.nicename.trim();
            }

            return '';
        }

        function registerItemSlugs(item) {
            if (!item || typeof item !== 'object') {
                return;
            }

            const identifiers = [];
            if (typeof item.username === 'string' && item.username.trim() !== '') {
                identifiers.push(item.username.trim());
            }
            if (typeof item.slug === 'string' && item.slug.trim() !== '') {
                identifiers.push(item.slug.trim());
            }
            if (typeof item.nicename === 'string' && item.nicename.trim() !== '') {
                identifiers.push(item.nicename.trim());
            }

            identifiers.forEach((identifier) => {
                slugIndex.set(identifier.toLowerCase(), String(item.id));
            });
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

        function getCompanyLabelFromItem(item) {
            if (!item || typeof item !== 'object') {
                return strings.client || 'este cliente';
            }

            const name = item.name && typeof item.name === 'object' ? item.name : {};
            const company = typeof name.company === 'string' ? name.company.trim() : '';
            if (company !== '') {
                return company;
            }

            const display = getDisplayName(name);
            if (display !== '') {
                return display;
            }

            return strings.client || 'este cliente';
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
                    const label = typeof offer.label === 'string' ? offer.label.trim() : '';
                    const type = typeof offer.type === 'string' ? offer.type.trim() : '';
                    const name = typeof offer.name === 'string' ? offer.name.trim() : '';
                    const discountValue = typeof offer.discount === 'number' && offer.discount > 0
                        ? Math.round(offer.discount)
                        : null;
                    const typeKey = type.toLowerCase();
                    const title = typeKey === 'personalizar' && name !== ''
                        ? escapeHtml(name)
                        : escapeHtml(label || name || '');
                    const discount = discountValue !== null
                        ? `<span class="clients-table__offer-discount">-${discountValue}%</span>`
                        : '';

                    return `
                        <span class="guarantees-list__badge clients-table__offer-badge">
                            <span class="clients-table__offer-title">${title}</span>
                            ${discount}
                        </span>
                    `;
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

            registerItemSlugs(item);

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
            const username = typeof item.username === 'string' ? item.username.trim() : '';
            const slug = typeof item.slug === 'string' ? item.slug.trim() : '';
            const nicename = typeof item.nicename === 'string' ? item.nicename.trim() : '';
            const rowSlug = username || slug || nicename;

            const tr = document.createElement('tr');
            tr.className = 'guarantees-table__row';
            tr.tabIndex = 0;
            tr.dataset.id = String(item.id);
            tr.dataset.index = String(tbody.children.length);
            if (rowSlug) {
                tr.dataset.slug = rowSlug.toLowerCase();
            }
            if (username) {
                tr.dataset.username = username;
            }
            if (slug) {
                tr.dataset.clientSlug = slug;
            }
            if (nicename) {
                tr.dataset.nicename = nicename;
            }

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

        function selectRow(row, item, options = {}) {
            const preserveUrl = Boolean(options.preserveUrl);
            if (selectedRow === row) {
                if (!preserveUrl) {
                    row.classList.remove('selected');
                    selectedRow = null;
                    lastRowIndex = -1;
                    updateHistory('');
                    showEmptyDetail('backward');
                }
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

            const slugValue = getPreferredSlug(item);
            if (slugValue) {
                const currentSlug = getSlugFromUrl();
                if (!preserveUrl || currentSlug !== slugValue.toLowerCase()) {
                    updateHistory(slugValue);
                }
            } else if (!preserveUrl) {
                updateHistory('');
            }

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
                const label = typeof offer.label === 'string' ? offer.label.trim() : '';
                const type = typeof offer.type === 'string' ? offer.type.trim() : '';
                const name = typeof offer.name === 'string' ? offer.name.trim() : '';
                const expires = typeof offer.expires === 'string' ? offer.expires.trim() : '';
                const discountValue = typeof offer.discount === 'number' && offer.discount > 0
                    ? Math.round(offer.discount)
                    : null;
                const typeKey = type.toLowerCase();
                const title = typeKey === 'personalizar' && name !== ''
                    ? `<span class="client-detail__chip-title">${escapeHtml(name)}</span>`
                    : `<span class="client-detail__chip-title">${escapeHtml(label || name || '')}</span>`;
                const discount = discountValue !== null
                    ? `<span class="client-detail__chip-extra">-${discountValue}%</span>`
                    : '';
                return `<li class="client-detail__chip">${title}${discount}</li>`;
            });

            return `<ul class="client-detail__chips">${items.join('')}</ul>`;
        }

        function renderCommercialsList(commercials) {
            if (!Array.isArray(commercials) || commercials.length === 0) {
                return `<p class="client-detail__empty">${escapeHtml(strings.commercialsEmpty || 'Sin comercial asignado')}</p>`;
            }

            const items = commercials.map((commercial) => {
                const rawName = getCommercialDisplay(commercial) || '';
                const name = rawName !== '' ? rawName : (strings.commercials || 'Comercial');
                const email = typeof commercial.email === 'string' && commercial.email ? commercial.email.trim() : '';
                const phone = typeof commercial.phone === 'string' && commercial.phone ? commercial.phone.trim() : '';
                const avatar = typeof commercial.avatar === 'string' && commercial.avatar ? commercial.avatar.trim() : '';
                const fallbackInitials = rawName
                    .split(/\s+/)
                    .filter((part) => part !== '')
                    .map((part) => part[0])
                    .join('')
                    .slice(0, 2)
                    .toUpperCase();
                const avatarHtml = avatar !== ''
                    ? `<img src="${escapeAttribute(avatar)}" alt="${escapeAttribute(name)}" class="client-detail__commercial-avatar">`
                    : `<span class="client-detail__commercial-initials">${escapeHtml(fallbackInitials || name.slice(0, 1).toUpperCase())}</span>`;

                const emailLine = email !== ''
                    ? `<p class="client-detail__commercial-contact">${escapeHtml(email)}</p>`
                    : '';

                const emailAction = email !== ''
                    ? `<a class="client-detail__commercial-action" href="mailto:${escapeAttribute(email)}">${iconEmail}<span>${escapeHtml(email)}</span></a>`
                    : '';

                const phoneSanitized = phone.replace(/[^0-9+]/g, '');
                const phoneAction = phone !== ''
                    ? `<a class="client-detail__commercial-action" href="tel:${escapeAttribute(phoneSanitized)}">${iconPhone}<span>${escapeHtml(phone)}</span></a>`
                    : '';

                const actions = [emailAction, phoneAction].filter((action) => action !== '').join('\n');

                return `
                    <li class="client-detail__commercial">
                        <div class="client-detail__commercial-media">${avatarHtml}</div>
                        <div class="client-detail__commercial-body">
                            <span class="client-detail__commercial-name">${escapeHtml(name)}</span>
                            ${emailLine}
                            ${actions !== '' ? `<div class="client-detail__commercial-actions">${actions}</div>` : ''}
                        </div>
                    </li>
                `;
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

        function formatDefinitionValue(value) {
            if (typeof value === 'number' && Number.isFinite(value)) {
                return escapeHtml(String(value));
            }

            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (trimmed !== '') {
                    return escapeHtml(trimmed);
                }
            }

            return '<span class="client-detail__empty">—</span>';
        }

        function renderContactActions(contact) {
            const loginEmail = typeof contact.login_email === 'string' && contact.login_email ? contact.login_email.trim() : '';
            const notificationEmail = typeof contact.notification_email === 'string' && contact.notification_email
                ? contact.notification_email.trim()
                : '';
            const phone = typeof contact.phone === 'string' && contact.phone ? contact.phone.trim() : '';

            if (notificationEmail === '' && phone === '') {
                return `<p class="client-detail__contact-empty">${escapeHtml(strings.contactEmpty || 'No hay datos de contacto disponibles')}</p>`;
            }

            const actions = [];
            let showDifferenceNote = false;

            if (notificationEmail !== '') {
                const isSame = loginEmail !== '' && notificationEmail.toLowerCase() === loginEmail.toLowerCase();
                showDifferenceNote = !isSame && loginEmail !== '';
                actions.push(`
                    <li class="fast-actions__item">
                        <a class="fast-actions__link client-detail__contact-link" href="mailto:${escapeAttribute(notificationEmail)}">
                            <span class="fast-actions__icon" aria-hidden="true">${iconEmail}</span>
                            <span class="client-detail__contact-value">${escapeHtml(notificationEmail)}</span>
                        </a>
                    </li>
                `);
            }

            if (phone !== '') {
                const sanitizedPhone = phone.replace(/[^0-9+]/g, '');
                actions.push(`
                    <li class="fast-actions__item">
                        <a class="fast-actions__link client-detail__contact-link" href="tel:${escapeAttribute(sanitizedPhone)}">
                            <span class="fast-actions__icon" aria-hidden="true">${iconPhone}</span>
                            <span class="client-detail__contact-value">${escapeHtml(phone)}</span>
                        </a>
                    </li>
                `);
            }

            return `
                <div class="client-detail__contact-actions">
                    <ul class="fast-actions client-detail__contact-buttons">${actions.join('')}</ul>
                    ${showDifferenceNote && (strings.notificationEmailDifferent || '').trim() !== ''
                        ? `<p class="client-detail__contact-note">${escapeHtml(strings.notificationEmailDifferent)}</p>`
                        : ''}
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
                    { key: 'fiscal_name', label: strings.workshopFiscal || 'Denominación fiscal' },
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
                        valueHtml = formatDefinitionValue(raw);
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

        function renderPreferences(documents, services) {
            const docs = documents && typeof documents === 'object' ? documents : {};
            const serviceData = services && typeof services === 'object' ? services : {};
            const web360 = serviceData.web360 && typeof serviceData.web360 === 'object' ? serviceData.web360 : {};

            const signatureEnabled = Boolean(docs.add_to_certificates);
            const signature = docs.signature && typeof docs.signature === 'object' ? docs.signature : {};
            const seal = docs.seal && typeof docs.seal === 'object' ? docs.seal : {};

            const signatureMeta = [];
            if (signatureEnabled) {
                signatureMeta.push(signature.url ? (strings.signatureUploaded || 'Firma subida') : (strings.signatureMissing || 'Firma no disponible'));
                signatureMeta.push(seal.url ? (strings.sealUploaded || 'Sello subido') : (strings.sealMissing || 'Sello no disponible'));
            }

            const webEnabled = Boolean(web360.enabled);
            const webUrl = typeof web360.url === 'string' ? web360.url.trim() : '';

            const signatureCard = {
                title: strings.signatureTitle || 'Firma y sello en certificados',
                status: signatureEnabled ? 'enabled' : 'disabled',
                description: signatureEnabled
                    ? (strings.signatureEnabled || 'Incluye firma y sello en los certificados')
                    : (strings.signatureDisabled || 'No se añaden a los certificados'),
                meta: signatureMeta,
            };

            const webMeta = [];
            if (webEnabled && webUrl !== '') {
                webMeta.push(`<a href="${escapeAttribute(webUrl)}" target="_blank" rel="noopener">${escapeHtml(webUrl)}</a>`);
            }

            const webCard = {
                title: strings.web360Title || 'Web 360VO',
                status: webEnabled ? 'enabled' : 'disabled',
                description: webEnabled
                    ? (strings.web360Enabled || 'Web 360VO activa')
                    : (strings.web360Disabled || 'Sin web configurada'),
                metaHtml: webMeta.join(''),
            };

            const cards = [signatureCard, webCard].map((card) => {
                const metaHtml = card.metaHtml
                    ? `<div class="client-detail__preference-meta">${card.metaHtml}</div>`
                    : (Array.isArray(card.meta) && card.meta.length > 0
                        ? `<div class="client-detail__preference-meta">${card.meta.map((entry) => escapeHtml(entry)).join(' · ')}</div>`
                        : '');

                return `
                    <li class="client-detail__preference client-detail__preference--${card.status}">
                        <span class="client-detail__preference-title">${escapeHtml(card.title)}</span>
                        <p class="client-detail__preference-status">${escapeHtml(card.description)}</p>
                        ${metaHtml}
                    </li>
                `;
            });

            return `
                <section class="client-detail__section">
                    <h4 class="client-detail__section-title">${escapeHtml(strings.preferences || 'Configuración adicional')}</h4>
                    <ul class="client-detail__preferences">${cards.join('')}</ul>
                </section>
            `;
        }

        function renderSepaDetails(sepa) {
            if (!sepa || typeof sepa !== 'object') {
                return '';
            }

            const fields = Array.isArray(sepa.fields) ? sepa.fields : [];
            const validFields = fields
                .map((field) => ({
                    label: typeof field.label === 'string' ? field.label.trim() : '',
                    value: typeof field.value === 'string' ? field.value.trim() : '',
                }))
                .filter((field) => field.value !== '');

            if (validFields.length === 0) {
                return '';
            }

            const rows = validFields.map((field) => `
                <tr>
                    <th scope="row">${escapeHtml(field.label || strings.sepaDetails || 'Dato')}</th>
                    <td>${escapeHtml(field.value)}</td>
                </tr>
            `).join('');

            return `
                <details class="detail__transfer-toggle client-detail__sepa-toggle">
                    <summary class="detail__transfer-toggle-summary">
                        <span class="detail__transfer-toggle-label">${escapeHtml(strings.sepaDetails || 'Ver datos del deudor SEPA')}</span>
                        <span class="detail__transfer-toggle-icon detail__transfer-toggle-icon--closed" aria-hidden="true">${iconArrowDown}</span>
                        <span class="detail__transfer-toggle-icon detail__transfer-toggle-icon--open" aria-hidden="true">${iconArrowUp}</span>
                    </summary>
                    <div class="detail__transfer-toggle-content">
                        <table class="detail__transfer-table client-detail__sepa-table">
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                </details>
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
            const companyLegalName = typeof company.legal_name === 'string' ? company.legal_name.trim() : '';
            const salesChannelLabel = typeof salesChannel.label === 'string' ? salesChannel.label.trim() : '';
            const companyLine = companyName !== '' ? `<p class="client-detail__company">${escapeHtml(companyName)}</p>` : '';
            const loginEmail = typeof contact.login_email === 'string' && contact.login_email ? contact.login_email.trim() : '';
            const loginEmailLine = loginEmail !== ''
                ? `<p class="client-detail__meta-line client-detail__login-email">${escapeHtml(loginEmail)}</p>`
                : '';
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
            const preferencesSection = renderPreferences(item.documents || {}, item.services || {});
            const sepaDetails = renderSepaDetails(sepa);
            const adminLink = item.links && typeof item.links.admin === 'string' ? item.links.admin.trim() : '';
            const adminLinkHtml = adminLink !== ''
                ? `<div class="client-detail__admin"><a class="client-detail__admin-link" href="${escapeAttribute(adminLink)}" target="_blank" rel="noopener">${escapeHtml(strings.adminLink || 'Ver ficha del cliente en el panel de gestión')}</a></div>`
                : '';
            const hasCommercials = Array.isArray(item.commercials) && item.commercials.length > 0;
            const assignButton = canAssignCommercials
                ? `
                    <div class="client-detail__actions">
                        <button type="button" class="client-detail__action" data-assign-commercial>
                            ${iconPersonAdd}
                            <span>${escapeHtml((hasCommercials ? strings.manageCommercials : strings.assignCommercial) || (hasCommercials ? 'Gestionar comerciales' : 'Asignar comercial'))}</span>
                        </button>
                    </div>
                `
                : '';
            const manageOffersButton = `
                <div class="client-detail__actions client-detail__actions--inline">
                    <button type="button" class="client-detail__action" data-manage-offers>
                        ${iconManageOffers}
                        <span>${escapeHtml(strings.manageOffers || 'Gestionar ofertas')}</span>
                    </button>
                </div>
            `;
            const manageSepaButton = `
                <div class="client-detail__actions client-detail__actions--inline">
                    <button type="button" class="client-detail__action" data-manage-sepa>
                        ${iconManageSepa}
                        <span>${escapeHtml(strings.manageSepa || 'Gestionar SEPA')}</span>
                    </button>
                </div>
            `;

            return `
                <div class="client-detail">
                    <header class="client-detail__header">
                        ${avatar}
                        <div class="client-detail__identity">
                            <h3 class="client-detail__title">${escapeHtml(displayName || strings.detailTitle || 'Detalles del cliente')}</h3>
                            ${companyLine}
                            ${loginEmailLine}
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
                                <dd>${formatDefinitionValue(company.name || '')}</dd>
                            </div>
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.legalName || 'Razón social')}</dt>
                                <dd>${formatDefinitionValue(companyLegalName)}</dd>
                            </div>
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.taxId || 'CIF/NIF')}</dt>
                                <dd>${formatDefinitionValue(company.tax_id || '')}</dd>
                            </div>
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.address || 'Dirección')}</dt>
                                <dd>${addressLines || '<span class="client-detail__empty">—</span>'}</dd>
                            </div>
                        </dl>
                    </section>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.commercials || 'Comercial')}</h4>
                        ${renderCommercialsList(item.commercials)}
                        ${assignButton}
                    </section>
                    ${workshopSection}
                    ${preferencesSection}
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.offers || 'Ofertas activas')}</h4>
                        ${renderOffersList(item.offers)}
                        ${manageOffersButton}
                    </section>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.sepaStatus || 'Estado SEPA')}</h4>
                        <p class="client-detail__status${sepaVariant}">${escapeHtml(sepaMessage)}</p>
                        ${sepaDetails}
                        ${manageSepaButton}
                    </section>
                    ${adminLinkHtml}
                </div>
            `;
        }

        function createAssignDialog() {
            const overlay = document.createElement('div');
            overlay.className = 'client-dialog';
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            overlay.innerHTML = `
                <div class="client-dialog__backdrop" data-dialog-close></div>
                <div class="client-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="client-dialog-title" tabindex="-1">
                    <header class="client-dialog__header">
                        <h2 id="client-dialog-title" class="client-dialog__title">${escapeHtml(strings.assignCommercialTitle || 'Asignar comercial')}</h2>
                        <button type="button" class="client-dialog__close" data-dialog-close aria-label="${escapeHtml(strings.close || 'Cerrar')}">${iconClose}</button>
                    </header>
                    <div class="client-dialog__body">
                        <section class="client-dialog__section client-dialog__section--assigned client-dialog__assigned" aria-live="polite">
                            <h3 class="client-dialog__section-title client-dialog__assigned-title"></h3>
                            <div class="client-dialog__assigned-list"></div>
                        </section>
                        <section class="client-dialog__section client-dialog__section--directory" aria-live="polite">
                            <h3 class="client-dialog__section-title">${escapeHtml(strings.assignCommercial || 'Seleccionar comercial')}</h3>
                            <div class="client-dialog__intro">
                                <p class="client-dialog__description">${escapeHtml(strings.assignCommercialDescription || 'Selecciona el comercial que gestionará a este cliente.')}</p>
                                <div class="client-dialog__search">
                                    <span class="client-dialog__search-icon" aria-hidden="true">${iconSearch}</span>
                                    <input type="search" class="client-dialog__search-input" placeholder="${escapeHtml(strings.assignCommercialSearchPlaceholder || 'Buscar comercial por nombre o email…')}" aria-label="${escapeHtml(strings.assignCommercialSearchPlaceholder || 'Buscar comercial')}">
                                </div>
                            </div>
                            <div class="client-dialog__commercials" role="listbox" aria-live="polite"></div>
                        </section>
                    </div>
                    <footer class="client-dialog__footer">
                        <span class="client-dialog__status" aria-live="polite"></span>
                        <button type="button" class="client-dialog__save" disabled>
                            <span class="client-dialog__save-label">${escapeHtml(strings.assignCommercialSave || 'Guardar cambios')}</span>
                            <span class="client-dialog__spinner" aria-hidden="true"></span>
                        </button>
                    </footer>
                </div>
            `;
            document.body.appendChild(overlay);

            const panel = overlay.querySelector('.client-dialog__panel');
            const titleEl = overlay.querySelector('.client-dialog__title');
            const descriptionEl = overlay.querySelector('.client-dialog__description');
            const searchInput = overlay.querySelector('.client-dialog__search-input');
            const commercialContainer = overlay.querySelector('.client-dialog__commercials');
            const saveButton = overlay.querySelector('.client-dialog__save');
            const saveLabel = overlay.querySelector('.client-dialog__save-label');
            const statusEl = overlay.querySelector('.client-dialog__status');
            const closeControls = overlay.querySelectorAll('[data-dialog-close]');
            const assignedSection = overlay.querySelector('.client-dialog__assigned');
            const assignedTitle = overlay.querySelector('.client-dialog__assigned-title');
            const assignedList = overlay.querySelector('.client-dialog__assigned-list');

            let previousActiveElement = null;
            let currentContext = null;
            let selectedIds = new Set();
            let originalIds = new Set();
            let selectedRecords = new Map();
            let companyLabel = '';
            let isSaving = false;
            let searchTimer = null;
            let searchTerm = '';
            let filteredItems = [];

            function resolveCompanyLabel(context) {
                const fallback = (strings.client || 'este cliente');
                if (!context || typeof context !== 'object') {
                    return fallback;
                }

                const directCompany = typeof context.company === 'string' ? context.company.trim() : '';
                if (directCompany !== '') {
                    return directCompany;
                }

                if (context.company && typeof context.company.name === 'string') {
                    const companyName = context.company.name.trim();
                    if (companyName !== '') {
                        return companyName;
                    }
                }

                const directName = typeof context.name === 'string' ? context.name.trim() : '';
                if (directName !== '') {
                    return directName;
                }

                if (context.name && typeof context.name.company === 'string') {
                    const companyName = context.name.company.trim();
                    if (companyName !== '') {
                        return companyName;
                    }
                }

                if (context.name && typeof context.name.personal === 'string') {
                    const personalName = context.name.personal.trim();
                    if (personalName !== '') {
                        return personalName;
                    }
                }

                return fallback;
            }

            function setStatus(message, variant = '') {
                if (!statusEl) {
                    return;
                }
                statusEl.textContent = message || '';
                if (variant) {
                    statusEl.dataset.variant = variant;
                } else {
                    delete statusEl.dataset.variant;
                }
            }

            function hasChanges() {
                if (selectedIds.size !== originalIds.size) {
                    return true;
                }
                for (const value of selectedIds) {
                    if (!originalIds.has(value)) {
                        return true;
                    }
                }
                return false;
            }

            function updateSaveButton() {
                if (!saveButton || !saveLabel) {
                    return;
                }
                if (isSaving) {
                    saveLabel.textContent = strings.assignCommercialSaving || 'Guardando…';
                    saveButton.classList.add('is-loading');
                    saveButton.disabled = true;
                    return;
                }
                saveLabel.textContent = strings.assignCommercialSave || 'Guardar cambios';
                saveButton.classList.remove('is-loading');
                saveButton.disabled = !hasChanges();
            }

            function getResultLabels() {
                return {
                    select: (strings.assignCommercialSelectAction || 'Seleccionar').trim(),
                    selected: (strings.assignCommercialSelectedAction || 'Seleccionado').trim(),
                    remove: (strings.assignCommercialRemoveAction || 'Quitar').trim(),
                };
            }

            function extractCommercialSummary(commercial) {
                if (!commercial || typeof commercial !== 'object') {
                    return null;
                }

                const id = Number(commercial.id) || 0;
                if (!id) {
                    return null;
                }

                const name = getCommercialDisplay(commercial) || '';
                const email = typeof commercial.email === 'string' && commercial.email ? commercial.email.trim() : '';
                const phone = typeof commercial.phone === 'string' && commercial.phone ? commercial.phone.trim() : '';
                const avatar = typeof commercial.avatar === 'string' && commercial.avatar ? commercial.avatar.trim() : '';
                const baseInitialsSource = typeof commercial.initials === 'string' && commercial.initials.trim() !== ''
                    ? commercial.initials.trim()
                    : (name || email || phone)
                        .split(/\s+/)
                        .filter(Boolean)
                        .map((part) => part[0])
                        .join('');
                const initials = baseInitialsSource.slice(0, 2).toUpperCase();
                const rawClientCount = Number(commercial.client_count);
                const clientCount = Number.isFinite(rawClientCount) && rawClientCount > 0 ? rawClientCount : 0;

                return {
                    id,
                    name,
                    email,
                    phone,
                    avatar,
                    initials,
                    client_count: clientCount,
                };
            }

            function hydrateSelectedRecordsFromDirectory() {
                if (!commercialDirectory.loaded) {
                    return;
                }
                selectedIds.forEach((id) => {
                    const entry = commercialDirectory.items.find((item) => Number(item.id) === id);
                    if (!entry) {
                        return;
                    }
                    const summary = extractCommercialSummary(entry);
                    if (summary) {
                        const existing = selectedRecords.get(summary.id);
                        selectedRecords.set(summary.id, existing ? { ...existing, ...summary } : summary);
                    }
                });
            }

            function formatClientCount(value) {
                const count = Number.isFinite(value) && value > 0 ? value : 0;
                if (count === 1) {
                    return strings.assignCommercialClientsSingular || '1 cliente asignado';
                }
                const label = strings.assignCommercialClientsPlural || 'clientes asignados';
                return `${count} ${label}`;
            }

            function renderAssignedList() {
                if (!assignedList || !assignedSection) {
                    return;
                }

                const resolvedCompany = companyLabel || (strings.client || 'este cliente');
                if (assignedTitle) {
                    const template = (strings.assignCommercialAssignedTitle || 'Comerciales asignados a %s').trim();
                    assignedTitle.textContent = template.includes('%s')
                        ? template.replace('%s', resolvedCompany)
                        : `${template} ${resolvedCompany}`.trim();
                }

                const entries = Array.from(selectedIds)
                    .map((id) => selectedRecords.get(id))
                    .filter((entry) => entry && entry.id);

                if (entries.length === 0) {
                    assignedSection.classList.add('client-dialog__assigned--empty');
                    assignedList.innerHTML = `<p class="client-dialog__assigned-empty">${escapeHtml(strings.assignCommercialAssignedEmpty || 'No hay comerciales asignados actualmente.')}</p>`;
                    return;
                }

                assignedSection.classList.remove('client-dialog__assigned--empty');
                const itemsHtml = entries.map((entry) => {
                    const avatarHtml = entry.avatar
                        ? `<img src="${escapeAttribute(entry.avatar)}" alt="${escapeAttribute(entry.name || entry.email || strings.commercials || 'Comercial')}" class="client-dialog__assigned-avatar">`
                        : `<span class="client-dialog__assigned-avatar client-dialog__assigned-avatar--initials">${escapeHtml(entry.initials || (entry.name || entry.email || 'C').slice(0, 2).toUpperCase())}</span>`;
                    const emailLine = entry.email
                        ? `<span class="client-dialog__assigned-email">${escapeHtml(entry.email)}</span>`
                        : '';

                    return `
                        <article class="client-dialog__assigned-card">
                            <div class="client-dialog__assigned-media">${avatarHtml}</div>
                            <div class="client-dialog__assigned-info">
                                <span class="client-dialog__assigned-name">${escapeHtml(entry.name || entry.email || strings.commercials || 'Comercial')}</span>
                                ${emailLine}
                            </div>
                        </article>
                    `;
                }).join('');

                assignedList.innerHTML = itemsHtml;
            }

            function updateCardChip(card, isSelected, isHover) {
                const chip = card.querySelector('.client-dialog__card-chip');
                if (!chip) {
                    return;
                }
                const labels = getResultLabels();
                chip.textContent = isSelected
                    ? (isHover ? labels.remove : labels.selected)
                    : labels.select;
            }

            function syncCardStates() {
                if (!commercialContainer) {
                    return;
                }
                commercialContainer.querySelectorAll('.client-dialog__card').forEach((card) => {
                    const id = Number(card.dataset.id) || 0;
                    if (!id) {
                        return;
                    }
                    const isSelected = selectedIds.has(id);
                    card.classList.toggle('is-selected', isSelected);
                    card.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    updateCardChip(card, isSelected, false);
                });
            }

            function renderCommercialGrid() {
                if (!commercialContainer) {
                    return;
                }

                if (commercialDirectory.loading) {
                    commercialContainer.innerHTML = `<div class="client-dialog__results-message client-dialog__results-message--loading">${escapeHtml(strings.assignCommercialLoading || 'Buscando comerciales…')}</div>`;
                    return;
                }

                if (commercialDirectory.error) {
                    commercialContainer.innerHTML = `<div class="client-dialog__results-message">${escapeHtml(commercialDirectory.error)}</div>`;
                    return;
                }

                if (!commercialDirectory.loaded) {
                    commercialContainer.innerHTML = `<div class="client-dialog__results-message client-dialog__results-message--loading">${escapeHtml(strings.assignCommercialLoading || 'Buscando comerciales…')}</div>`;
                    return;
                }

                if (!Array.isArray(filteredItems) || filteredItems.length === 0) {
                    const emptyMessage = searchTerm.trim() !== ''
                        ? (strings.assignCommercialEmpty || 'No se han encontrado comerciales con ese criterio.')
                        : (strings.assignCommercialDirectoryEmpty || 'No hay comerciales disponibles para asignar.');
                    commercialContainer.innerHTML = `<div class="client-dialog__commercials-empty">${escapeHtml(emptyMessage)}</div>`;
                    return;
                }

                const labels = getResultLabels();
                const cardsHtml = filteredItems.map((entry) => {
                    const id = Number(entry.id) || 0;
                    if (!id) {
                        return '';
                    }
                    const isSelected = selectedIds.has(id);
                    const name = entry.name || entry.email || strings.commercials || 'Comercial';
                    const avatarHtml = entry.avatar
                        ? `<img src="${escapeAttribute(entry.avatar)}" alt="${escapeAttribute(name)}" class="client-dialog__card-avatar">`
                        : `<span class="client-dialog__card-avatar client-dialog__card-avatar--initials">${escapeHtml((entry.initials || name.slice(0, 2)).toUpperCase())}</span>`;
                    const emailLine = entry.email
                        ? `<span class="client-dialog__card-email">${escapeHtml(entry.email)}</span>`
                        : '';
                    const chipLabel = isSelected ? labels.selected : labels.select;
                    const countLabel = formatClientCount(entry.client_count);

                    return `
                        <article class="client-dialog__card${isSelected ? ' is-selected' : ''}" data-id="${id}" role="option" aria-selected="${isSelected ? 'true' : 'false'}" tabindex="0">
                            <div class="client-dialog__card-header">
                                ${avatarHtml}
                                <div class="client-dialog__card-body">
                                    <span class="client-dialog__card-name">${escapeHtml(name)}</span>
                                    ${emailLine}
                                </div>
                            </div>
                            <div class="client-dialog__card-footer">
                                <span class="client-dialog__card-count">${escapeHtml(countLabel)}</span>
                                <span class="client-dialog__card-chip" aria-hidden="true">${escapeHtml(chipLabel)}</span>
                            </div>
                        </article>
                    `;
                }).join('');

                commercialContainer.innerHTML = cardsHtml;

                commercialContainer.querySelectorAll('.client-dialog__card').forEach((card) => {
                    const id = Number(card.dataset.id) || 0;
                    if (!id) {
                        return;
                    }
                    const entry = filteredItems.find((item) => Number(item.id) === id);
                    if (!entry) {
                        return;
                    }
                    card.addEventListener('click', () => toggleSelection(id, entry, card));
                    card.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            toggleSelection(id, entry, card);
                        }
                    });
                    card.addEventListener('mouseenter', () => updateCardChip(card, selectedIds.has(id), true));
                    card.addEventListener('mouseleave', () => updateCardChip(card, selectedIds.has(id), false));
                });
            }

            function filterCommercials(query) {
                searchTerm = query;
                if (!commercialDirectory.loaded) {
                    return [];
                }
                const normalized = query.trim().toLowerCase();
                if (normalized === '') {
                    return commercialDirectory.items.slice();
                }
                return commercialDirectory.items.filter((entry) => {
                    const candidates = [
                        entry.name,
                        entry.full_name,
                        entry.email,
                        entry.phone,
                        entry.first_name,
                        entry.last_name,
                    ];
                    return candidates.some((value) => typeof value === 'string' && value.toLowerCase().includes(normalized));
                });
            }

            function toggleSelection(id, entry, cardElement) {
                if (selectedIds.has(id)) {
                    selectedIds.delete(id);
                    selectedRecords.delete(id);
                } else {
                    selectedIds.add(id);
                    const summary = extractCommercialSummary(entry) || { id };
                    if (summary && summary.id) {
                        selectedRecords.set(summary.id, summary);
                    }
                }

                updateSaveButton();
                renderAssignedList();
                if (cardElement) {
                    const isSelected = selectedIds.has(id);
                    cardElement.classList.toggle('is-selected', isSelected);
                    cardElement.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    updateCardChip(cardElement, isSelected, false);
                } else {
                    syncCardStates();
                }
            }

            function adjustDirectoryCount(id, delta) {
                if (!commercialDirectory.loaded) {
                    return;
                }
                const entry = commercialDirectory.items.find((item) => Number(item.id) === id);
                if (!entry) {
                    return;
                }
                const next = Math.max(0, (Number(entry.client_count) || 0) + delta);
                entry.client_count = next;
            }

            async function saveSelection() {
                if (!currentContext || !currentContext.id || isSaving || !hasChanges()) {
                    return;
                }

                const ids = Array.from(selectedIds).filter((value) => Number.isFinite(value) && value > 0);
                isSaving = true;
                setStatus(strings.assignCommercialSaving || 'Guardando…');
                updateSaveButton();

                try {
                    const response = await fetch(`${restRoot}go/v1/clientes/${currentContext.id}/commercials`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            ...(restNonce ? { 'X-WP-Nonce': restNonce } : {}),
                        },
                        body: JSON.stringify({ commercial_ids: ids }),
                    });

                    if (!response.ok) {
                        throw new Error(`Request failed: ${response.status}`);
                    }

                    const data = await response.json();
                    const updatedCommercials = Array.isArray(data.commercials) ? data.commercials : [];

                    const previousIds = new Set(originalIds);
                    originalIds = new Set(updatedCommercials.map((entry) => Number(entry.id) || 0));
                    selectedIds = new Set(originalIds);
                    selectedRecords = new Map();
                    updatedCommercials.forEach((entry) => {
                        const summary = extractCommercialSummary(entry);
                        if (summary) {
                            selectedRecords.set(summary.id, summary);
                        }
                    });
                    hydrateSelectedRecordsFromDirectory();

                    const added = [];
                    const removed = [];
                    selectedIds.forEach((value) => {
                        if (!previousIds.has(value)) {
                            added.push(value);
                        }
                    });
                    previousIds.forEach((value) => {
                        if (!selectedIds.has(value)) {
                            removed.push(value);
                        }
                    });
                    added.forEach((id) => adjustDirectoryCount(id, 1));
                    removed.forEach((id) => adjustDirectoryCount(id, -1));

                    isSaving = false;
                    setStatus(strings.assignCommercialSaved || 'Cambios guardados', 'success');
                    updateSaveButton();
                    renderAssignedList();
                    filteredItems = filterCommercials(searchTerm);
                    renderCommercialGrid();
                    syncCardStates();

                    if (typeof currentContext.onComplete === 'function') {
                        currentContext.onComplete(updatedCommercials);
                    }
                } catch (error) {
                    console.error('Error saving commercials', error);
                    isSaving = false;
                    setStatus(strings.assignCommercialError || 'No se ha podido guardar la asignación.', 'error');
                    updateSaveButton();
                }
            }

            function handleSearchInput() {
                if (!searchInput) {
                    return;
                }
                if (searchTimer) {
                    window.clearTimeout(searchTimer);
                }
                const value = searchInput.value || '';
                searchTimer = window.setTimeout(() => {
                    filteredItems = filterCommercials(value);
                    renderCommercialGrid();
                    syncCardStates();
                }, 120);
            }

            function handleKeydown(event) {
                if (event.key === 'Escape') {
                    close();
                }
            }

            function close() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                overlay.hidden = true;
                document.removeEventListener('keydown', handleKeydown);
                setStatus('');
                isSaving = false;
                updateSaveButton();
                if (searchInput) {
                    searchInput.value = '';
                }
                searchTerm = '';
                filteredItems = commercialDirectory.loaded ? commercialDirectory.items.slice() : [];
                selectedIds = new Set();
                originalIds = new Set();
                selectedRecords = new Map();
                companyLabel = '';
                currentContext = null;
                if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
                    previousActiveElement.focus();
                }
            }

            async function ensureDirectoryLoaded(options = {}) {
                const force = Boolean(options.force);
                if (!canAssignCommercials) {
                    return;
                }
                if (commercialDirectory.loaded && !force) {
                    filteredItems = filterCommercials(searchTerm);
                    hydrateSelectedRecordsFromDirectory();
                    renderAssignedList();
                    renderCommercialGrid();
                    syncCardStates();
                    return;
                }

                renderCommercialGrid();
                try {
                    await fetchCommercialDirectory(force);
                } catch (error) {
                    // Error already handled within fetchCommercialDirectory.
                }
                filteredItems = filterCommercials(searchTerm);
                hydrateSelectedRecordsFromDirectory();
                renderAssignedList();
                renderCommercialGrid();
                syncCardStates();
            }

            function open(context = {}) {
                previousActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                currentContext = context || {};
                const resolvedCompany = resolveCompanyLabel(currentContext);
                companyLabel = resolvedCompany;

                if (titleEl) {
                    const template = (strings.assignCommercialTitleTemplate || strings.assignCommercialTitle || 'Asignar comercial').trim();
                    titleEl.textContent = template.includes('%s')
                        ? template.replace('%s', resolvedCompany)
                        : `${template} ${resolvedCompany}`.trim();
                }

                if (descriptionEl) {
                    const template = (strings.assignCommercialDescription || 'Selecciona el comercial que gestionará a %s.').trim();
                    descriptionEl.textContent = template.includes('%s')
                        ? template.replace('%s', resolvedCompany)
                        : template;
                }

                selectedIds = new Set(Array.isArray(context.assignedIds)
                    ? context.assignedIds.map((value) => Number(value) || 0).filter((value) => value > 0)
                    : []);
                originalIds = new Set(selectedIds);
                selectedRecords = new Map();
                const existingCommercials = Array.isArray(context.commercials) ? context.commercials : [];
                existingCommercials.forEach((entry) => {
                    const summary = extractCommercialSummary(entry);
                    if (summary) {
                        selectedRecords.set(summary.id, summary);
                    }
                });
                hydrateSelectedRecordsFromDirectory();

                if (searchInput) {
                    searchInput.value = '';
                }
                searchTerm = '';
                filteredItems = commercialDirectory.loaded ? commercialDirectory.items.slice() : [];

                renderAssignedList();
                renderCommercialGrid();
                updateSaveButton();
                setStatus('');

                overlay.hidden = false;
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.addEventListener('keydown', handleKeydown);
                window.requestAnimationFrame(() => {
                    if (panel && typeof panel.focus === 'function') {
                        panel.focus({ preventScroll: true });
                    }
                });

                ensureDirectoryLoaded();
            }

            if (searchInput) {
                searchInput.addEventListener('input', handleSearchInput);
            }

            if (saveButton) {
                saveButton.addEventListener('click', saveSelection);
            }

            closeControls.forEach((element) => {
                element.addEventListener('click', close);
            });

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    close();
                }
            });

            return {
                open,
                close,
            };
        }

        function createSimpleDialog(options = {}) {
            const titleKey = typeof options.titleKey === 'string' ? options.titleKey : '';
            const titleTemplateKey = typeof options.titleTemplateKey === 'string' ? options.titleTemplateKey : '';
            const fallbackTitle = typeof options.fallbackTitle === 'string' && options.fallbackTitle.trim() !== ''
                ? options.fallbackTitle.trim()
                : 'Gestión';
            const saveLabelKey = typeof options.saveLabelKey === 'string' ? options.saveLabelKey : 'dialogSave';
            const overlay = document.createElement('div');
            overlay.className = 'client-dialog client-dialog--simple';
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            const titleId = uniqueId('client-dialog-title');
            const baseTitle = (strings[titleKey] || fallbackTitle || 'Gestión').trim() || 'Gestión';
            const saveLabel = (strings[saveLabelKey] || strings.dialogSave || strings.assignCommercialSave || 'Guardar cambios').trim() || 'Guardar cambios';

            overlay.innerHTML = `
                <div class="client-dialog__backdrop" data-dialog-close></div>
                <div class="client-dialog__panel client-dialog__panel--simple" role="dialog" aria-modal="true" aria-labelledby="${titleId}" tabindex="-1">
                    <header class="client-dialog__header">
                        <h2 id="${titleId}" class="client-dialog__title">${escapeHtml(baseTitle)}</h2>
                        <button type="button" class="client-dialog__close" data-dialog-close aria-label="${escapeHtml(strings.close || 'Cerrar')}">${iconClose}</button>
                    </header>
                    <div class="client-dialog__body client-dialog__body--simple"></div>
                    <footer class="client-dialog__footer">
                        <span class="client-dialog__status" aria-live="polite"></span>
                        <button type="button" class="client-dialog__save" disabled>
                            <span class="client-dialog__save-label">${escapeHtml(saveLabel)}</span>
                            <span class="client-dialog__spinner" aria-hidden="true"></span>
                        </button>
                    </footer>
                </div>
            `;

            document.body.appendChild(overlay);

            const panel = overlay.querySelector('.client-dialog__panel');
            const titleEl = overlay.querySelector('.client-dialog__title');
            const saveButton = overlay.querySelector('.client-dialog__save');
            const statusEl = overlay.querySelector('.client-dialog__status');
            const closeControls = overlay.querySelectorAll('[data-dialog-close]');

            let previousActiveElement = null;

            function formatTitle(context) {
                const subject = context && typeof context === 'object' && typeof context.subject === 'string'
                    ? context.subject.trim()
                    : '';
                const template = typeof strings[titleTemplateKey] === 'string' ? strings[titleTemplateKey].trim() : '';
                const fallback = (strings[titleKey] || baseTitle || fallbackTitle).trim() || fallbackTitle;

                if (template !== '' && subject !== '' && template.includes('%s')) {
                    return template.replace('%s', subject);
                }

                if (fallback.includes('%s') && subject !== '') {
                    return fallback.replace('%s', subject);
                }

                return fallback;
            }

            function setStatus(message = '', variant = '') {
                if (!statusEl) {
                    return;
                }
                statusEl.textContent = message;
                if (variant) {
                    statusEl.dataset.variant = variant;
                } else {
                    delete statusEl.dataset.variant;
                }
            }

            function close() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                overlay.hidden = true;
                document.removeEventListener('keydown', handleKeydown);
                if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
                    previousActiveElement.focus();
                }
            }

            function handleKeydown(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close();
                }
            }

            function open(context = {}) {
                previousActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                const titleText = formatTitle(context);

                if (titleEl) {
                    titleEl.textContent = titleText;
                }

                setStatus('');

                overlay.hidden = false;
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.addEventListener('keydown', handleKeydown);

                window.requestAnimationFrame(() => {
                    if (panel && typeof panel.focus === 'function') {
                        panel.focus({ preventScroll: true });
                    }
                });
            }

            closeControls.forEach((element) => {
                element.addEventListener('click', close);
            });

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    close();
                }
            });

            if (saveButton) {
                saveButton.addEventListener('click', (event) => {
                    event.preventDefault();
                });
            }

            return {
                open,
                close,
            };
        }


        function initDetailInteractions(container, item) {
            if (!container) {
                return;
            }

            const assignButton = container.querySelector('[data-assign-commercial]');
            if (assignButton && assignDialog && typeof assignDialog.open === 'function') {
                assignButton.addEventListener('click', () => {
                    if (!item) {
                        return;
                    }

                    const displayName = getDisplayName(item.name || {}) || '';
                    const companyName = typeof item.name?.company === 'string' ? item.name.company.trim() : '';
                    const assignedIds = Array.isArray(item.commercials)
                        ? item.commercials.map((commercial) => Number(commercial.id) || 0)
                        : [];

                    assignDialog.open({
                        id: item.id,
                        name: displayName,
                        company: companyName,
                        commercials: Array.isArray(item.commercials) ? item.commercials : [],
                        assignedIds,
                        onComplete(updatedCommercials) {
                            if (!Array.isArray(updatedCommercials)) {
                                return;
                            }

                            item.commercials = updatedCommercials;
                            cache.set(String(item.id), item);
                            refreshActiveDetail(item);
                            updateRowCommercialSummary(item);
                        },
                    });
                });
            }

            const manageOffersButton = container.querySelector('[data-manage-offers]');
            if (manageOffersButton && offersDialog && typeof offersDialog.open === 'function') {
                manageOffersButton.addEventListener('click', () => {
                    offersDialog.open({
                        subject: getCompanyLabelFromItem(item),
                    });
                });
            }

            const manageSepaButton = container.querySelector('[data-manage-sepa]');
            if (manageSepaButton && sepaDialog && typeof sepaDialog.open === 'function') {
                manageSepaButton.addEventListener('click', () => {
                    sepaDialog.open({
                        subject: getCompanyLabelFromItem(item),
                    });
                });
            }
        }

        let activePanel = panel1;
        let inactivePanel = panel2;

        function setActivePanelContent(content, itemContext = null) {
            if (!activePanel) {
                activePanel = panel1;
                inactivePanel = panel2;
            }

            const target = activePanel;
            const other = target === panel1 ? panel2 : panel1;

            target.innerHTML = content;
            initDetailInteractions(target, itemContext);
            target.classList.add('active');

            other.classList.remove('active', 'slide-in-left', 'slide-in-right', 'slide-out-left', 'slide-out-right');
            other.innerHTML = '';

            inactivePanel = other;
        }

        function refreshActiveDetail(item) {
            if (!activePanel) {
                return;
            }

            activePanel.innerHTML = renderDetail(item);
            initDetailInteractions(activePanel, item);
        }

        function updateRowCommercialSummary(item) {
            if (!item || typeof item.id === 'undefined') {
                return;
            }

            const row = tbody.querySelector(`tr[data-id="${item.id}"]`);
            if (!row) {
                return;
            }

            const cell = row.querySelector('.clients-table__meta--commercial');
            if (!cell) {
                return;
            }

            const summary = formatCommercialSummary(item.commercials || []);
            cell.textContent = summary !== '' ? summary : '—';
        }

        function swapPanels(content, direction = 'forward', itemContext = null) {
            const nextPanel = activePanel === panel1 ? panel2 : panel1;
            const previousPanel = activePanel;

            nextPanel.innerHTML = content;
            nextPanel.dataset.loadedId = content ? 'loaded' : '';
            initDetailInteractions(nextPanel, itemContext);

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
            swapPanels(content, direction, item);
        }

        function showEmptyDetail(direction = 'forward', options = {}) {
            const preserveUrl = Boolean(options.preserveUrl);
            const animate = options.animate !== undefined ? Boolean(options.animate) : true;
            if (!preserveUrl) {
                updateHistory('');
            }
            const content = `
                <div class="guarantee-detail__empty">
                    <h3 class="guarantee-detail__title">${escapeHtml(strings.detailTitle || 'Detalles del cliente')}</h3>
                    <p>${escapeHtml(strings.selectPrompt || 'Selecciona un cliente para consultar su información, asignar comerciales, gestionar ofertas y más.')}</p>
                </div>
            `;
            if (!animate) {
                setActivePanelContent(content, null);
                return;
            }
            swapPanels(content, direction, null);
        }

        function showLoadingDetail() {
            const content = `
                <div class="guarantee-detail__empty guarantee-detail__empty--loading">
                    <div class="client-detail__loading">
                        <span class="client-detail__loading-spinner" aria-hidden="true"></span>
                        <p>${escapeHtml(strings.detailLoading || 'Cargando datos del cliente…')}</p>
                    </div>
                </div>
            `;
            setActivePanelContent(content, null);
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
            let queuedPage = null;

            if (!append) {
                tbody.innerHTML = '';
                cache.clear();
                clearSelection();
                if (initialSlug) {
                    showLoadingDetail();
                } else {
                    showEmptyDetail('forward', { animate: false });
                }
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

                if (initialSlug) {
                    const matchedId = slugIndex.get(initialSlug);
                    if (matchedId) {
                        const targetRow = tbody.querySelector(`tr[data-id="${matchedId}"]`);
                        const targetItem = cache.get(String(matchedId));
                        if (targetRow && targetItem) {
                            selectRow(targetRow, targetItem, { preserveUrl: true });
                            initialSlug = '';
                            window.requestAnimationFrame(() => {
                                targetRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                            });
                        }
                    } else if (state.page < state.totalPages) {
                        queuedPage = state.page + 1;
                    } else {
                        initialSlug = '';
                        showEmptyDetail('forward', { animate: false });
                    }
                }

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
                if (queuedPage && queuedPage <= state.totalPages) {
                    loadPage(queuedPage, true);
                }
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
        showEmptyDetail('forward', { preserveUrl: Boolean(initialSlug) });
        loadPage(1, false);
    });
})();
