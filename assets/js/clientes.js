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
        const iconSave = icons.save || '';
        const iconPdf = icons.pdf || '';
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
        const offersDialog = createOffersDialog({
            restRoot,
            restNonce,
        });
        let sepaDialog = null;
        let sepaConfirmDialog = null;
        let currentSepaDialogContext = null;
        let currentSepaDialogElements = null;

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

        function extractSepaReference(sepa) {
            if (!sepa || typeof sepa !== 'object') {
                return '';
            }

            const documents = sepa.documents && typeof sepa.documents === 'object' ? sepa.documents : {};
            const signed = documents.signed && typeof documents.signed === 'object' ? documents.signed : {};
            const pending = documents.pending && typeof documents.pending === 'object' ? documents.pending : {};

            const signedReference = typeof signed.reference === 'string' ? signed.reference.trim() : '';
            if (signedReference !== '') {
                return signedReference;
            }

            const pendingReference = typeof pending.reference === 'string' ? pending.reference.trim() : '';
            if (pendingReference !== '') {
                return pendingReference;
            }

            return '';
        }

        function formatSepaActor(item) {
            if (!item || typeof item !== 'object') {
                return strings.manageSepaConfirmActorFallback || 'este profesional';
            }

            const name = item.name && typeof item.name === 'object' ? item.name : {};
            const displayName = getDisplayName(name);
            const companyName = typeof name.company === 'string' ? name.company.trim() : '';

            if (displayName !== '' && companyName !== '' && displayName.toLowerCase() !== companyName.toLowerCase()) {
                return `${displayName} (${companyName})`;
            }

            if (displayName !== '') {
                return displayName;
            }

            if (companyName !== '') {
                return companyName;
            }

            return strings.manageSepaConfirmActorFallback || 'este profesional';
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

                const fallbackContact = email === '' && phone !== ''
                    ? `<p class="client-detail__commercial-contact">${escapeHtml(phone)}</p>`
                    : '';

                const emailIconHtml = iconEmail !== ''
                    ? `<span class="client-detail__commercial-action-icon" aria-hidden="true">${iconEmail}</span>`
                    : '';
                const phoneIconHtml = iconPhone !== ''
                    ? `<span class="client-detail__commercial-action-icon" aria-hidden="true">${iconPhone}</span>`
                    : '';

                const emailAction = email !== ''
                    ? `<a class="client-detail__commercial-action" href="mailto:${escapeAttribute(email)}">${emailIconHtml}<span>${escapeHtml(email)}</span></a>`
                    : '';

                const phoneSanitized = phone.replace(/[^0-9+]/g, '');
                const phoneAction = phone !== ''
                    ? `<a class="client-detail__commercial-action" href="tel:${escapeAttribute(phoneSanitized)}">${phoneIconHtml}<span>${escapeHtml(phone)}</span></a>`
                    : '';

                const actions = [emailAction, phoneAction].filter((action) => action !== '').join('\n');
                const actionsMarkup = actions !== ''
                    ? `<div class="client-detail__commercial-actions">${actions}</div>`
                    : '';

                return `
                    <li class="client-detail__commercial">
                        <div class="client-detail__commercial-media">${avatarHtml}</div>
                        <div class="client-detail__commercial-body">
                            <span class="client-detail__commercial-name">${escapeHtml(name)}</span>
                            ${emailLine || fallbackContact}
                        </div>
                        ${actionsMarkup}
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

        function renderBadge(label, variant = '') {
            if (typeof label !== 'string') {
                return '';
            }

            const trimmedLabel = label.trim();
            if (trimmedLabel === '') {
                return '';
            }

            const variantKey = typeof variant === 'string' ? variant.trim() : '';
            const variantClass = variantKey !== ''
                ? ` client-detail__badge--${escapeHtml(variantKey)}`
                : '';

            return `<span class="client-detail__badge${variantClass}">${escapeHtml(trimmedLabel)}</span>`;
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

            const badge = renderBadge(statusLabel, statusClass.replace('client-detail__status--', ''));

            return `
                <section class="client-detail__section client-detail__section--workshop">
                    <div class="client-detail__section-header">
                        <div class="client-detail__section-heading">
                            <h4 class="client-detail__section-title">${escapeHtml(strings.workshop || 'Taller propio')}</h4>
                            ${badge}
                        </div>
                    </div>
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

        function hasSepaDocument(document) {
            if (!document || typeof document !== 'object') {
                return false;
            }

            if (typeof document.url === 'string' && document.url.trim() !== '') {
                return true;
            }

            if (typeof document.hash === 'string' && document.hash.trim() !== '') {
                return true;
            }

            if (typeof document.id === 'number' && Number.isFinite(document.id) && document.id > 0) {
                return true;
            }

            return false;
        }

        function formatSepaTimestamp(value) {
            const raw = typeof value === 'string' ? value.trim() : '';
            if (raw === '') {
                return '';
            }

            const date = new Date(raw);
            if (Number.isNaN(date.getTime())) {
                return raw;
            }

            const months = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sept.', 'oct.', 'nov.', 'dic.'];
            const day = String(date.getDate()).padStart(2, '0');
            const month = months[date.getMonth()] || '';
            const year = String(date.getFullYear()).slice(-2);
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const datePart = month !== '' ? `${day} ${month} ${year}` : `${day} ${year}`;

            return `${datePart}, ${hours}:${minutes}h`;
        }

        function renderSepaDocumentCard({
            title,
            description,
            status,
            document,
            buttonLabel,
            type,
        }) {
            if (!hasSepaDocument(document)) {
                return '';
            }

            const safeTitle = typeof title === 'string' ? title.trim() : '';
            const safeDescription = typeof description === 'string' ? description.trim() : '';
            const docFilename = typeof document.filename === 'string' && document.filename.trim() !== ''
                ? document.filename.trim()
                : (strings.manageSepaDownload || 'Mandato SEPA');
            const docUrl = typeof document.url === 'string' ? document.url.trim() : '';
            const docReference = typeof document.reference === 'string' ? document.reference.trim() : '';
            const docGeneratedRaw = typeof document.generated_at === 'string' ? document.generated_at.trim() : '';
            const docGenerated = formatSepaTimestamp(docGeneratedRaw);
            const metaParts = [];

            if (docReference !== '') {
                metaParts.push(escapeHtml(docReference));
            }

            if (docGenerated !== '') {
                metaParts.push(escapeHtml(docGenerated));
            }

            const metaHtml = metaParts.length > 0
                ? `<p class="client-sepa-dialog__meta">${metaParts.join(' · ')}</p>`
                : '';

            const buttonText = buttonLabel && buttonLabel.trim() !== ''
                ? buttonLabel.trim()
                : (strings.manageSepaDownload || 'Descargar mandato');

            const iconHtml = iconPdf
                ? `<span class="client-sepa-dialog__button-icon" aria-hidden="true">${iconPdf}</span>`
                : '';

            const linkHtml = docUrl !== ''
                ? `<a class="client-sepa-dialog__button" href="${escapeAttribute(docUrl)}" target="_blank" rel="noopener">${iconHtml}<span>${escapeHtml(buttonText)}</span></a>`
                : '';

            return `
                <section class="client-sepa-dialog__card client-sepa-dialog__card--${escapeHtml(type || 'info')}">
                    <header class="client-sepa-dialog__card-header">
                        <div class="client-sepa-dialog__card-heading">
                            <h3>${escapeHtml(safeTitle || (strings.manageSepaPendingTitle || 'Mandato SEPA'))}</h3>
                            ${safeDescription !== '' ? `<p class="client-sepa-dialog__card-description">${escapeHtml(safeDescription)}</p>` : ''}
                        </div>
                    </header>
                    <div class="client-sepa-dialog__card-body">
                        <p class="client-sepa-dialog__filename">${escapeHtml(docFilename)}</p>
                        ${metaHtml}
                        ${linkHtml}
                    </div>
                </section>
            `;
        }

        function renderSepaSignedUploadCard() {
            const title = strings.manageSepaSignedUploadTitle || 'Mandato firmado';
            const description = strings.manageSepaSignedUploadDescription || '';
            const placeholder = strings.manageSepaSignedUploadPlaceholder || 'Selecciona un archivo PDF…';
            const buttonLabel = strings.manageSepaSignedUploadButton || 'Subir mandato firmado';
            const loadingLabel = strings.manageSepaSignedUploadLoading || 'Subiendo…';
            const help = strings.manageSepaSignedUploadHelp || '';

            return `
                <section class="client-sepa-dialog__card client-sepa-dialog__card--signed" data-sepa-upload-card>
                    <header class="client-sepa-dialog__card-header">
                        <div class="client-sepa-dialog__card-heading">
                            <h3>${escapeHtml(title)}</h3>
                            ${description !== '' ? `<p class="client-sepa-dialog__card-description">${escapeHtml(description)}</p>` : ''}
                        </div>
                    </header>
                    <div class="client-sepa-dialog__card-body">
                        <div class="client-sepa-dialog__upload">
                            <label class="client-sepa-dialog__upload-input">
                                <span class="client-sepa-dialog__upload-placeholder" data-sepa-signed-placeholder>${escapeHtml(placeholder)}</span>
                                <input type="file" accept="application/pdf" data-sepa-signed-input>
                            </label>
                            <p class="client-sepa-dialog__upload-meta" data-sepa-signed-meta hidden></p>
                            <p class="client-sepa-dialog__upload-help" data-sepa-signed-help${help === '' ? ' hidden' : ''}>${escapeHtml(help)}</p>
                            <p class="client-sepa-dialog__upload-error" data-sepa-signed-error hidden></p>
                            <div class="client-sepa-dialog__upload-actions">
                                <button type="button" class="client-sepa-dialog__button client-sepa-dialog__button--primary" data-sepa-signed-submit disabled>
                                    <span data-default-label>${escapeHtml(buttonLabel)}</span>
                                    <span data-loading-label hidden>${escapeHtml(loadingLabel)}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            `;
        }

        function renderSepaDialog(item) {
            const sepa = item && item.sepa && typeof item.sepa === 'object' ? item.sepa : {};
            const documents = sepa.documents && typeof sepa.documents === 'object' ? sepa.documents : {};
            const pendingDocument = documents.pending || {};
            const signedDocument = documents.signed || {};
            const hasPending = hasSepaDocument(pendingDocument);
            const hasSigned = hasSepaDocument(signedDocument);
            const statusCodeRaw = typeof sepa.status_code === 'string'
                ? sepa.status_code.trim().toLowerCase()
                : '';
            const isDisabled = statusCodeRaw === 'deshabilitado';
            let awaitingValidation = Boolean(sepa.awaiting_validation);
            let needsActivation = Boolean(sepa.needs_activation);
            if (isDisabled) {
                awaitingValidation = false;
                needsActivation = true;
            }
            const statusLabel = typeof sepa.label === 'string' && sepa.label.trim() !== ''
                ? sepa.label.trim()
                : (strings.sepaEmpty || 'Sin información del mandato');
            const statusVariant = typeof sepa.variant === 'string' && sepa.variant.trim() !== ''
                ? sepa.variant.trim()
                : 'info';
            const disabledMessage = typeof sepa.disabled_message === 'string' ? sepa.disabled_message.trim() : '';

            const sections = [];

            const detailsHtml = renderSepaDetails(sepa);
            if (detailsHtml) {
                sections.push(`<div class="client-sepa-dialog__details">${detailsHtml}</div>`);
            }

            if (disabledMessage !== '') {
                const noticeLabel = strings.manageSepaDeactivateReasonLabel || 'Notas';
                const noticeIntro = strings.manageSepaDisabledNotice || '';
                const introHtml = noticeIntro !== '' ? `<strong>${escapeHtml(noticeIntro)}</strong>` : `<strong>${escapeHtml(noticeLabel)}</strong>`;
                sections.push(`
                    <div class="client-sepa-dialog__notice client-sepa-dialog__notice--danger">
                        ${introHtml}
                        <p class="client-sepa-dialog__notice-message">${escapeHtml(disabledMessage)}</p>
                    </div>
                `);
            }

            const cards = [];

            if (hasPending) {
                cards.push(renderSepaDocumentCard({
                    title: strings.manageSepaPendingTitle || 'Mandato pendiente de firma',
                    description: strings.manageSepaPendingDescription || '',
                    status: strings.manageSepaPendingStatus || 'Pendiente de firma',
                    document: pendingDocument,
                    buttonLabel: strings.manageSepaDownload || 'Descargar mandato',
                    type: 'pending',
                }));
            }

            if (hasSigned) {
                let signedStatus = '';
                if (awaitingValidation) {
                    signedStatus = strings.manageSepaValidationStatus || 'Pendiente de validación';
                } else if (needsActivation) {
                    signedStatus = strings.manageSepaActivationStatus || 'Pendiente de domiciliación';
                }
                cards.push(renderSepaDocumentCard({
                    title: strings.manageSepaSignedTitle || 'Mandato firmado por el profesional',
                    description: strings.manageSepaSignedDescription || '',
                    status: signedStatus,
                    document: signedDocument,
                    buttonLabel: strings.manageSepaViewSigned || 'Ver mandato firmado',
                    type: 'signed',
                }));
            } else if (!awaitingValidation) {
                cards.push(renderSepaSignedUploadCard());
            }

            if (cards.length === 0) {
                cards.push(`<p class="client-sepa-dialog__empty">${escapeHtml(strings.manageSepaNoDocuments || 'No hay documentos SEPA disponibles.')}</p>`);
            }

            sections.push(`<div class="client-sepa-dialog__cards">${cards.join('')}</div>`);

            const statusCode = statusCodeRaw;
            const canActivate = statusCode === 'pendiente_validacion' || statusCode === 'deshabilitado';
            const canDeactivate = statusCode === 'firmado';

            let action = null;
            let actionHelp = '';
            let activateAction = null;

            if (canDeactivate) {
                const deactivateLabel = typeof strings.manageSepaDeactivate === 'string'
                    ? strings.manageSepaDeactivate.trim()
                    : '';
                const label = deactivateLabel !== ''
                    ? deactivateLabel
                    : 'Deshabilitar domiciliación bancaria';
                activateAction = {
                    type: 'deactivate',
                    label,
                };
                actionHelp = typeof strings.manageSepaDeactivateHelp === 'string'
                    ? strings.manageSepaDeactivateHelp.trim()
                    : '';
            } else if (canActivate) {
                const activateLabelBase = typeof strings.manageSepaActivate === 'string'
                    ? strings.manageSepaActivate.trim()
                    : '';
                const label = activateLabelBase !== ''
                    ? activateLabelBase
                    : 'Habilitar domiciliación bancaria';
                activateAction = {
                    type: 'activate',
                    label,
                };
                actionHelp = typeof strings.manageSepaActivateHelp === 'string'
                    ? strings.manageSepaActivateHelp.trim()
                    : '';
            }

            return {
                html: `<div class="client-sepa-dialog">${sections.join('')}</div>`,
                status: statusLabel !== '' ? { label: statusLabel, variant: statusVariant } : null,
                action,
                help: actionHelp,
                activateAction,
            };
        }

        function setupSepaDialogBody(elements) {
            if (!elements || !elements.body) {
                return;
            }

            const body = elements.body;
            const currentContext = elements.context && typeof elements.context === 'object' ? elements.context : {};
            const item = currentContext.item && typeof currentContext.item === 'object' ? currentContext.item : null;

            const dialogData = renderSepaDialog(item);
            body.innerHTML = dialogData.html;

            initSepaDialogUploads(body, item);

            updateSepaDialogStatusBadge(elements.panel, dialogData.status);

            const actionButtons = prepareSepaDialogFooter({
                footer: elements.footer,
                saveButton: elements.saveButton,
                statusEl: elements.status,
                action: dialogData.action,
                help: dialogData.help,
                activateAction: dialogData.activateAction,
            });

            if (actionButtons && actionButtons.activate) {
                bindSepaActionButton(actionButtons.activate, dialogData.activateAction, item, currentContext);
            }

            if (actionButtons && actionButtons.secondary) {
                bindSepaActionButton(actionButtons.secondary, dialogData.action, item, currentContext);
            }
        }

        function updateSepaDialogStatusBadge(panel, status) {
            if (!panel) {
                return;
            }

            const header = panel.querySelector('.client-dialog__header');
            if (!header) {
                return;
            }

            const closeButton = header.querySelector('.client-dialog__close');
            let badge = header.querySelector('.client-sepa-dialog__status');

            if (!status || typeof status.label !== 'string' || status.label.trim() === '') {
                if (badge && badge.parentElement === header) {
                    if (badge.__goSepaHandler) {
                        badge.removeEventListener('click', badge.__goSepaHandler);
                        delete badge.__goSepaHandler;
                    }
                    badge.remove();
                }
                return;
            }

            const label = status.label.trim();
            const variant = typeof status.variant === 'string' && status.variant.trim() !== ''
                ? status.variant.trim()
                : 'info';

            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'client-sepa-dialog__status';
                badge.setAttribute('aria-live', 'polite');
                if (closeButton) {
                    header.insertBefore(badge, closeButton);
                } else {
                    header.appendChild(badge);
                }
            }

            badge.className = 'client-sepa-dialog__status';
            if (variant) {
                badge.classList.add(`client-sepa-dialog__status--${variant}`);
            }
            badge.textContent = label;
        }

        function prepareSepaDialogFooter({ footer, saveButton, statusEl, action, help, activateAction }) {
            if (statusEl) {
                const helpText = typeof help === 'string' ? help.trim() : '';
                statusEl.textContent = helpText;
                if (helpText === '') {
                    delete statusEl.dataset.variant;
                }
            }

            if (!footer) {
                return null;
            }

            let actionsContainer = footer.querySelector('.client-dialog__footer-actions');
            if (!actionsContainer) {
                actionsContainer = document.createElement('div');
                actionsContainer.className = 'client-dialog__footer-actions';
                if (saveButton && saveButton.parentElement === footer) {
                    footer.removeChild(saveButton);
                }
                footer.appendChild(actionsContainer);
                if (saveButton) {
                    actionsContainer.appendChild(saveButton);
                }
            } else if (saveButton && saveButton.parentElement !== actionsContainer) {
                actionsContainer.appendChild(saveButton);
            }

            actionsContainer.querySelectorAll('[data-sepa-action]').forEach((button) => {
                if (button.__goSepaHandler) {
                    button.removeEventListener('click', button.__goSepaHandler);
                    delete button.__goSepaHandler;
                }
                button.remove();
            });

            const result = {};

            if (activateAction && typeof activateAction.label === 'string' && activateAction.label.trim() !== '') {
                const activateButton = document.createElement('button');
                activateButton.type = 'button';
                activateButton.className = 'client-dialog__footer-btn';
                activateButton.dataset.sepaAction = activateAction.type === 'deactivate' ? 'deactivate' : 'activate';
                activateButton.textContent = activateAction.label.trim();
                actionsContainer.insertBefore(activateButton, saveButton || null);
                result.activate = activateButton;
            }

            if (action && typeof action.label === 'string' && action.label.trim() !== '') {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'client-dialog__footer-btn';
                button.dataset.sepaAction = action.type === 'deactivate' ? 'deactivate' : 'activate';
                button.textContent = action.label.trim();
                actionsContainer.insertBefore(button, saveButton || null);
                result.secondary = button;
            }

            return Object.keys(result).length > 0 ? result : null;
        }

        function bindSepaActionButton(button, action, item, context) {
            if (!button || !action) {
                return;
            }

            const type = action.type === 'deactivate' ? 'deactivate' : 'activate';
            const handler = buildSepaActionHandler(type, item, context);

            if (!handler) {
                button.disabled = true;
                return;
            }

            button.disabled = false;

            if (button.__goSepaHandler) {
                button.removeEventListener('click', button.__goSepaHandler);
            }

            button.__goSepaHandler = handler;
            button.addEventListener('click', handler);
        }

        function initSepaDialogUploads(container, item) {
            if (!container || !item || typeof item.id !== 'number') {
                return;
            }

            const uploadCard = container.querySelector('[data-sepa-upload-card]');
            if (!uploadCard) {
                return;
            }

            const input = uploadCard.querySelector('[data-sepa-signed-input]');
            const placeholderEl = uploadCard.querySelector('[data-sepa-signed-placeholder]');
            const metaEl = uploadCard.querySelector('[data-sepa-signed-meta]');
            const helpEl = uploadCard.querySelector('[data-sepa-signed-help]');
            const errorEl = uploadCard.querySelector('[data-sepa-signed-error]');
            const submitButton = uploadCard.querySelector('[data-sepa-signed-submit]');

            if (!input || !submitButton) {
                return;
            }

            let selectedFile = null;
            const defaultPlaceholder = strings.manageSepaSignedUploadPlaceholder || 'Selecciona un archivo PDF…';
            const selectedTemplate = strings.manageSepaSignedUploadSelected || 'Archivo seleccionado: %s';
            const errorFallback = strings.manageSepaSignedUploadError || 'No se ha podido subir el mandato SEPA firmado. Inténtalo de nuevo.';
            const buttonLabel = strings.manageSepaSignedUploadButton || 'Subir mandato firmado';
            const loadingLabel = strings.manageSepaSignedUploadLoading || 'Subiendo…';

            function formatSelectedLabel(name) {
                if (selectedTemplate.includes('%s')) {
                    return selectedTemplate.replace('%s', name);
                }
                return `${selectedTemplate} ${name}`;
            }

            function clearError() {
                if (errorEl) {
                    errorEl.textContent = '';
                    errorEl.hidden = true;
                }
            }

            function showError(message) {
                if (errorEl) {
                    errorEl.textContent = message;
                    errorEl.hidden = false;
                }
            }

            function resetFileState() {
                selectedFile = null;
                if (input instanceof HTMLInputElement) {
                    input.value = '';
                }
                if (placeholderEl) {
                    placeholderEl.textContent = defaultPlaceholder;
                }
                if (metaEl) {
                    metaEl.textContent = '';
                    metaEl.hidden = true;
                }
                submitButton.disabled = true;
            }

            function setButtonLoading(isLoading) {
                const defaultLabelEl = submitButton.querySelector('[data-default-label]');
                const loadingLabelEl = submitButton.querySelector('[data-loading-label]');

                if (isLoading) {
                    submitButton.disabled = true;
                    submitButton.classList.add('client-sepa-dialog__button--loading');
                    if (defaultLabelEl) {
                        defaultLabelEl.hidden = true;
                    }
                    if (loadingLabelEl) {
                        loadingLabelEl.textContent = loadingLabel;
                        loadingLabelEl.hidden = false;
                    }
                } else {
                    submitButton.classList.remove('client-sepa-dialog__button--loading');
                    if (defaultLabelEl) {
                        defaultLabelEl.textContent = buttonLabel;
                        defaultLabelEl.hidden = false;
                    }
                    if (loadingLabelEl) {
                        loadingLabelEl.hidden = true;
                    }
                    submitButton.disabled = !selectedFile;
                }
            }

            resetFileState();
            clearError();

            input.addEventListener('change', () => {
                if (input instanceof HTMLInputElement && input.files && input.files.length > 0) {
                    selectedFile = input.files[0];
                } else {
                    selectedFile = null;
                }

                if (selectedFile) {
                    if (placeholderEl) {
                        placeholderEl.textContent = selectedFile.name;
                    }
                    if (metaEl) {
                        metaEl.textContent = formatSelectedLabel(selectedFile.name);
                        metaEl.hidden = false;
                    }
                    submitButton.disabled = false;
                } else {
                    resetFileState();
                }

                clearError();
            });

            submitButton.addEventListener('click', async (event) => {
                event.preventDefault();
                if (!selectedFile) {
                    return;
                }

                clearError();
                setButtonLoading(true);

                try {
                    const formData = new FormData();
                    formData.append('sepa_signed', selectedFile);

                    const endpoint = `${restRoot}go/v1/clientes/${item.id}/sepa/signed`;
                    const headers = restNonce ? { 'X-WP-Nonce': restNonce } : {};
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers,
                        body: formData,
                    });

                    let payload = {};
                    try {
                        payload = await response.json();
                    } catch (error) {
                        payload = {};
                    }

                    if (!response.ok) {
                        const message = payload && typeof payload.message === 'string' && payload.message.trim() !== ''
                            ? payload.message.trim()
                            : errorFallback;
                        throw new Error(message);
                    }

                    if (payload && typeof payload.sepa === 'object') {
                        item.sepa = payload.sepa;
                    }

                    if (payload && typeof payload.payment === 'object') {
                        item.payment = payload.payment;
                    }

                    cache.set(String(item.id), item);
                    refreshActiveDetail(item);

                    if (currentSepaDialogContext) {
                        currentSepaDialogContext.item = item;
                    }

                    if (currentSepaDialogElements) {
                        currentSepaDialogElements.context = currentSepaDialogContext;
                        setupSepaDialogBody(currentSepaDialogElements);
                    }

                    return;
                } catch (error) {
                    const message = error && typeof error.message === 'string' && error.message.trim() !== ''
                        ? error.message.trim()
                        : errorFallback;
                    showError(message);
                    setButtonLoading(false);
                }
            });

            if (helpEl && helpEl.textContent === '') {
                helpEl.hidden = true;
            }
        }

        function buildSepaActionHandler(type, item, context) {
            if (!item || !sepaConfirmDialog || typeof sepaConfirmDialog.open !== 'function') {
                return null;
            }

            const reference = extractSepaReference(item.sepa || {});
            const actor = formatSepaActor(item);
            const subject = typeof context?.subject === 'string' ? context.subject : '';
            const name = item.name && typeof item.name === 'object' ? item.name : {};
            const companyName = typeof name.company === 'string' ? name.company.trim() : '';
            let subtitleHtml = '';

            if (companyName !== '' && reference !== '') {
                subtitleHtml = `${escapeHtml(companyName)} <strong>${escapeHtml(reference)}</strong>`;
            } else if (companyName !== '') {
                subtitleHtml = escapeHtml(companyName);
            } else if (reference !== '') {
                subtitleHtml = `<strong>${escapeHtml(reference)}</strong>`;
            } else if (subject !== '') {
                subtitleHtml = escapeHtml(subject);
            }

            if (type === 'activate') {
                return () => {
                    sepaConfirmDialog.open({
                        actor,
                        subtitleHtml,
                        confirmLabel: strings.manageSepaConfirmAccept || 'Activar domiciliación bancaria',
                        note: strings.manageSepaConfirmNote || '',
                        onConfirm: async ({ close, setError, setLoading }) => {
                            try {
                                setError('');
                                setLoading(true);
                                await activateSepaForItem(item);
                                setLoading(false);
                                close();
                            } catch (error) {
                                const message = error && typeof error.message === 'string' && error.message.trim() !== ''
                                    ? error.message.trim()
                                    : (strings.manageSepaConfirmError || 'No se ha podido activar la domiciliación bancaria. Inténtalo de nuevo.');
                                setLoading(false);
                                setError(message);
                            }
                        },
                    });
                };
            }

            if (type === 'deactivate') {
                return () => {
                    sepaConfirmDialog.open({
                        actor,
                        subtitleHtml,
                        title: strings.manageSepaDeactivateConfirmTitle || strings.manageSepaConfirmTitle || 'Deshabilitar SEPA',
                        confirmLabel: strings.manageSepaDeactivateConfirmAccept || 'Deshabilitar domiciliación bancaria',
                        loadingLabel: strings.manageSepaDeactivateConfirmLoading || 'Deshabilitando…',
                        note: strings.manageSepaDeactivateConfirmNote || '',
                        checkboxLabel: strings.manageSepaDeactivateConfirmCheckbox || strings.manageSepaConfirmCheckbox,
                        message: strings.manageSepaDeactivateConfirmMessage || 'Confirmo que vamos a deshabilitar la domiciliación bancaria a %s.',
                        reasonRequired: true,
                        reasonLabel: strings.manageSepaDeactivateReasonLabel || strings.manageSepaDeactivateConfirmTitle || 'Notas',
                        reasonPlaceholder: strings.manageSepaDeactivateReasonPlaceholder || 'Añade una nota…',
                        reasonHelp: strings.manageSepaDeactivateReasonHelp || '',
                        reasonError: strings.manageSepaDeactivateReasonError || strings.manageSepaDeactivateConfirmError || 'Introduce una nota.',
                        onConfirm: async ({ close, setError, setLoading, reason }) => {
                            try {
                                setError('');
                                setLoading(true);
                                await deactivateSepaForItem(item, { reason });
                                setLoading(false);
                                close();
                            } catch (error) {
                                const message = error && typeof error.message === 'string' && error.message.trim() !== ''
                                    ? error.message.trim()
                                    : (strings.manageSepaDeactivateConfirmError || 'No se ha podido deshabilitar la domiciliación bancaria. Inténtalo de nuevo.');
                                setLoading(false);
                                setError(message);
                            }
                        },
                    });
                };
            }

            return null;
        }

        async function activateSepaForItem(item) {
            const userId = Number(item && item.id);
            if (!Number.isFinite(userId) || userId <= 0) {
                throw new Error(strings.manageSepaConfirmError || 'No se ha podido activar la domiciliación bancaria. Inténtalo de nuevo.');
            }

            const endpoint = `${restRoot}go/v1/clientes/${userId}/sepa/activate`;
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce,
                },
                body: JSON.stringify({}),
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (error) {
                payload = {};
            }

            if (!response.ok) {
                const message = payload && typeof payload.message === 'string' && payload.message.trim() !== ''
                    ? payload.message.trim()
                    : (strings.manageSepaConfirmError || 'No se ha podido activar la domiciliación bancaria. Inténtalo de nuevo.');
                throw new Error(message);
            }

            if (payload && typeof payload.sepa === 'object') {
                item.sepa = payload.sepa;
            }

            if (payload && typeof payload.payment === 'object') {
                item.payment = payload.payment;
            }

            cache.set(String(userId), item);
            refreshActiveDetail(item);

            if (currentSepaDialogContext) {
                currentSepaDialogContext.item = item;
            }

            if (currentSepaDialogElements) {
                currentSepaDialogElements.context = currentSepaDialogContext;
                setupSepaDialogBody(currentSepaDialogElements);
            }

            return payload;
        }

        async function deactivateSepaForItem(item, options = {}) {
            const userId = Number(item && item.id);
            if (!Number.isFinite(userId) || userId <= 0) {
                throw new Error(strings.manageSepaDeactivateConfirmError || 'No se ha podido deshabilitar la domiciliación bancaria. Inténtalo de nuevo.');
            }

            const reasonValue = typeof options.reason === 'string' ? options.reason.trim() : '';
            if (reasonValue === '') {
                throw new Error(strings.manageSepaDeactivateReasonError || strings.manageSepaDeactivateConfirmError || 'Introduce una nota.');
            }

            const endpoint = `${restRoot}go/v1/clientes/${userId}/sepa/deactivate`;
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce,
                },
                body: JSON.stringify({ reason: reasonValue }),
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (error) {
                payload = {};
            }

            if (!response.ok) {
                const message = payload && typeof payload.message === 'string' && payload.message.trim() !== ''
                    ? payload.message.trim()
                    : (strings.manageSepaDeactivateConfirmError || 'No se ha podido deshabilitar la domiciliación bancaria. Inténtalo de nuevo.');
                throw new Error(message);
            }

            if (payload && typeof payload.sepa === 'object') {
                item.sepa = payload.sepa;
            }

            if (payload && typeof payload.payment === 'object') {
                item.payment = payload.payment;
            }

            cache.set(String(userId), item);
            refreshActiveDetail(item);

            if (currentSepaDialogContext) {
                currentSepaDialogContext.item = item;
            }

            if (currentSepaDialogElements) {
                currentSepaDialogElements.context = currentSepaDialogContext;
                setupSepaDialogBody(currentSepaDialogElements);
            }

            return payload;
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
            const addressParts = [
                typeof address.street === 'string' ? address.street.trim() : '',
                joinNonEmpty([address.zip || '', address.city || ''], ' '),
                joinNonEmpty([address.state || '', address.country || ''], ' '),
            ];
            const addressLines = joinNonEmpty(addressParts, ', ');
            const addressHtml = addressLines !== '' ? escapeHtml(addressLines) : '';

            const sepaVariantKey = typeof sepa.variant === 'string' ? sepa.variant.trim() : '';
            const sepaMessage = typeof sepa.label === 'string' && sepa.label.trim() !== ''
                ? sepa.label.trim()
                : (strings.sepaEmpty || 'Sin información del mandato');
            const sepaBadge = renderBadge(sepaMessage, sepaVariantKey !== '' ? sepaVariantKey : 'muted');
            const sepaDisabledMessage = typeof sepa.disabled_message === 'string' ? sepa.disabled_message.trim() : '';
            const sepaNoticeHtml = sepaDisabledMessage !== ''
                ? `<p class="client-detail__sepa-notice">${escapeHtml(sepaDisabledMessage)}</p>`
                : '';
            const offers = Array.isArray(item.offers) ? item.offers : [];
            const offersCount = offers.length;
            const offersBadgeLabel = offersCount === 0
                ? (strings.offersEmpty || 'Sin ofertas activas')
                : offersCount === 1
                    ? (strings.offersBadgeSingular || '1 oferta activa')
                    : (strings.offersBadgePlural || '%s ofertas activas').replace('%s', offersCount);
            const offersBadgeVariant = offersCount > 0 ? 'info' : 'muted';
            const offersBadge = renderBadge(offersBadgeLabel, offersBadgeVariant);
            const commercials = Array.isArray(item.commercials) ? item.commercials : [];
            const commercialCount = commercials.length;
            const commercialBadgeLabel = commercialCount === 0
                ? (strings.commercialsBadgeEmpty || strings.commercialsEmpty || 'Sin comercial asignado')
                : commercialCount === 1
                    ? (strings.commercialsBadgeSingular || '1 comercial asignado')
                    : (strings.commercialsBadgePlural || '%s comerciales asignados').replace('%s', commercialCount);
            const commercialBadgeVariant = commercialCount > 0 ? 'info' : 'muted';
            const commercialBadge = renderBadge(commercialBadgeLabel, commercialBadgeVariant);
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
            const adminLink = item.links && typeof item.links.admin === 'string' ? item.links.admin.trim() : '';
            const adminLinkHtml = adminLink !== ''
                ? `<div class="client-detail__admin"><a class="client-detail__admin-link" href="${escapeAttribute(adminLink)}" target="_blank" rel="noopener">${escapeHtml(strings.adminLink || 'Edita en panel de administración WordPress')}</a></div>`
                : '';
            const hasCommercials = commercialCount > 0;
            const assignButton = canAssignCommercials
                ? `
                        <button type="button" class="client-detail__action" data-assign-commercial>
                            ${iconPersonAdd}
                            <span>${escapeHtml((hasCommercials ? strings.manageCommercials : strings.assignCommercial) || (hasCommercials ? 'Gestionar comerciales' : 'Asignar comercial'))}</span>
                        </button>
                `
                : '';
            const manageOffersButton = `
                        <button type="button" class="client-detail__action" data-manage-offers>
                            ${iconManageOffers}
                            <span>${escapeHtml(strings.manageOffers || 'Gestionar ofertas')}</span>
                        </button>
            `;
            const manageSepaButton = `
                        <button type="button" class="client-detail__action" data-manage-sepa>
                            ${iconManageSepa}
                            <span>${escapeHtml(strings.manageSepa || 'Gestionar SEPA')}</span>
                        </button>
            `;
            const assignButtonHtml = assignButton ? assignButton.trim() : '';
            const manageOffersButtonHtml = manageOffersButton.trim();
            const manageSepaButtonHtml = manageSepaButton.trim();
            const offersActionsHtml = manageOffersButtonHtml !== ''
                ? `<div class="client-detail__actions client-detail__actions--inline">${manageOffersButtonHtml}</div>`
                : '';
            const sepaActionsHtml = manageSepaButtonHtml !== ''
                ? `<div class="client-detail__actions client-detail__actions--inline">${manageSepaButtonHtml}</div>`
                : '';

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
                            <div class="client-detail__item client-detail__item--direccion">
                                <dt>${escapeHtml(strings.address || 'Dirección')}</dt>
                                <dd>${addressHtml || '<span class="client-detail__empty">—</span>'}</dd>
                            </div>
                        </dl>
                    </section>
                    <section class="client-detail__section">
                        <div class="client-detail__section-header client-detail__section-header--has-meta">
                            <div class="client-detail__section-heading">
                                <h4 class="client-detail__section-title">${escapeHtml(strings.commercials || 'Comercial')}</h4>
                                ${commercialBadge}
                            </div>
                            ${assignButtonHtml}
                        </div>
                        ${renderCommercialsList(commercials)}
                    </section>
                    ${workshopSection}
                    ${preferencesSection}
                    <section class="client-detail__section client-detail__section--offers">
                        <div class="client-detail__section-header client-detail__section-header--has-meta">
                            <div class="client-detail__section-heading">
                                <h4 class="client-detail__section-title">${escapeHtml(strings.offers || 'Ofertas activas')}</h4>
                                ${offersBadge}
                            </div>
                        </div>
                        ${offersActionsHtml}
                        ${renderOffersList(offers)}
                    </section>
                    <section class="client-detail__section client-detail__section--sepa">
                        <div class="client-detail__section-header client-detail__section-header--has-meta">
                            <div class="client-detail__section-heading">
                                <h4 class="client-detail__section-title">${escapeHtml(strings.sepaStatus || 'Estado SEPA')}</h4>
                                ${sepaBadge}
                            </div>
                        </div>
                        ${sepaActionsHtml}
                        ${sepaNoticeHtml}
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
            let closeTimer = null;
            let closeTransitionHandler = null;

            function clearCloseTransition() {
                if (closeTransitionHandler) {
                    overlay.removeEventListener('transitionend', closeTransitionHandler);
                    closeTransitionHandler = null;
                }
                if (closeTimer !== null) {
                    window.clearTimeout(closeTimer);
                    closeTimer = null;
                }
            }

            function hideOverlayAfterTransition() {
                clearCloseTransition();
                overlay.hidden = true;
            }

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
                clearCloseTransition();
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                closeTransitionHandler = (event) => {
                    if (event.target === overlay) {
                        hideOverlayAfterTransition();
                    }
                };
                overlay.addEventListener('transitionend', closeTransitionHandler);
                closeTimer = window.setTimeout(hideOverlayAfterTransition, 320);
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

                clearCloseTransition();
                overlay.classList.remove('is-open');
                overlay.hidden = false;
                overlay.setAttribute('aria-hidden', 'false');
                document.addEventListener('keydown', handleKeydown);
                window.requestAnimationFrame(() => {
                    overlay.classList.add('is-open');
                    if (panel && typeof panel.focus === 'function') {
                        window.requestAnimationFrame(() => {
                            panel.focus({ preventScroll: true });
                        });
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


        function createOffersDialog(options = {}) {
            const restRootUrl = typeof options.restRoot === 'string' && options.restRoot !== '' ? options.restRoot : '/wp-json/';
            const restNonceValue = typeof options.restNonce === 'string' ? options.restNonce : '';
            const restBase = restRootUrl.endsWith('/') ? restRootUrl : `${restRootUrl}/`;
            const titleId = uniqueId('client-dialog-title');
            const baseTitle = (strings.manageOffersTitle || strings.manageOffers || 'Gestionar ofertas').trim() || 'Gestionar ofertas';
            const baseSaveLabel = (strings.dialogSave || 'Guardar cambios').trim() || 'Guardar cambios';
            const savingLabel = (strings.manageOffersSaving || strings.assignCommercialSaving || 'Guardando…').trim() || 'Guardando…';
            const saveIconMarkup = iconSave ? `<span class="client-dialog__save-icon" aria-hidden="true">${iconSave}</span>` : '';
            const addLabel = (strings.manageOffersAdd || 'Añadir nueva oferta').trim() || 'Añadir nueva oferta';
            const overlay = document.createElement('div');
            overlay.className = 'client-dialog client-dialog--simple client-dialog--offers';
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            overlay.innerHTML = `
                <div class="client-dialog__backdrop" data-dialog-close></div>
                <div class="client-dialog__panel client-dialog__panel--simple client-dialog__panel--offers" role="dialog" aria-modal="true" aria-labelledby="${titleId}" tabindex="-1">
                    <header class="client-dialog__header">
                        <h2 id="${titleId}" class="client-dialog__title">${escapeHtml(baseTitle)}</h2>
                        <button type="button" class="client-dialog__close" data-dialog-close aria-label="${escapeHtml(strings.close || 'Cerrar')}">${iconClose}</button>
                    </header>
                    <div class="client-dialog__body client-dialog__body--simple client-dialog__body--offers"></div>
                    <footer class="client-dialog__footer">
                        <span class="client-dialog__status" aria-live="polite"></span>
                        <div class="client-dialog__footer-actions">
                            <button type="button" class="client-offers__add">
                                <span class="client-offers__add-label">${escapeHtml(addLabel)}</span>
                            </button>
                            <button type="button" class="client-dialog__save" disabled>
                                ${saveIconMarkup}
                                <span class="client-dialog__save-label">${escapeHtml(baseSaveLabel)}</span>
                                <span class="client-dialog__spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                    </footer>
                </div>
            `;

            document.body.appendChild(overlay);

            const panel = overlay.querySelector('.client-dialog__panel');
            const titleEl = overlay.querySelector('.client-dialog__title');
            const body = overlay.querySelector('.client-dialog__body--offers');
            const statusEl = overlay.querySelector('.client-dialog__status');
            const addButton = overlay.querySelector('.client-offers__add');
            const saveButton = overlay.querySelector('.client-dialog__save');
            const saveLabelEl = saveButton ? saveButton.querySelector('.client-dialog__save-label') : null;
            const closeControls = overlay.querySelectorAll('[data-dialog-close]');

            let previousActiveElement = null;
            let currentContext = null;
            let offers = [];
            let choices = { tipo_oferta: [], aplicacion: [] };
            let modalities = [];
            let isLoading = false;
            let isSaving = false;
            let dirty = false;
            let initialSnapshot = '';
            let offerIdCounter = 0;

            const choiceMaps = {
                tipo: new Map(),
                scope: new Map(),
            };

            function resetState() {
                offers = [];
                choices = { tipo_oferta: [], aplicacion: [] };
                modalities = [];
                isLoading = false;
                isSaving = false;
                dirty = false;
                initialSnapshot = '';
                offerIdCounter = 0;
                choiceMaps.tipo.clear();
                choiceMaps.scope.clear();
                if (body) {
                    body.innerHTML = '';
                }
            }

            if (addButton) {
                addButton.addEventListener('click', () => {
                    offers.push(createEmptyOffer());
                    renderOffers();
                    updateDirtyState();
                    if (body) {
                        body.scrollTop = body.scrollHeight;
                    }
                });
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

            function updateSaveButton() {
                if (!saveButton || !saveLabelEl) {
                    return;
                }
                const disabled = isLoading || isSaving || !dirty;
                saveButton.disabled = disabled;
                saveButton.classList.toggle('is-loading', isSaving);
                saveLabelEl.textContent = isSaving ? savingLabel : baseSaveLabel;
                if (addButton) {
                    addButton.disabled = isLoading || isSaving;
                }
            }

            function formatTitle(context) {
                const subject = context && typeof context === 'object' && typeof context.subject === 'string'
                    ? context.subject.trim()
                    : '';
                const template = typeof strings.manageOffersTitleTemplate === 'string' ? strings.manageOffersTitleTemplate.trim() : '';
                if (template !== '' && subject !== '' && template.includes('%s')) {
                    return template.replace('%s', subject);
                }
                if (baseTitle.includes('%s') && subject !== '') {
                    return baseTitle.replace('%s', subject);
                }
                return baseTitle;
            }

            function handleKeydown(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close();
                }
            }

            function generateOfferId() {
                offerIdCounter += 1;
                return `offer-${Date.now()}-${offerIdCounter}`;
            }

            function normalizeChoices(rawChoices) {
                const normalized = {
                    tipo_oferta: [],
                    aplicacion: [],
                };
                if (rawChoices && typeof rawChoices === 'object') {
                    if (Array.isArray(rawChoices.tipo_oferta)) {
                        normalized.tipo_oferta = rawChoices.tipo_oferta.filter((choice) => choice && typeof choice.value === 'string');
                    }
                    if (Array.isArray(rawChoices.aplicacion)) {
                        normalized.aplicacion = rawChoices.aplicacion.filter((choice) => choice && typeof choice.value === 'string');
                    }
                }
                choiceMaps.tipo.clear();
                choiceMaps.scope.clear();
                normalized.tipo_oferta.forEach((choice) => {
                    const value = typeof choice.value === 'string' ? choice.value : '';
                    const label = typeof choice.label === 'string' ? choice.label : value;
                    if (value !== '') {
                        choiceMaps.tipo.set(value, label);
                    }
                });
                normalized.aplicacion.forEach((choice) => {
                    const value = typeof choice.value === 'string' ? choice.value : '';
                    const label = typeof choice.label === 'string' ? choice.label : value;
                    if (value !== '') {
                        choiceMaps.scope.set(value, label);
                    }
                });
                return normalized;
            }

            function normalizeModalities(list) {
                if (!Array.isArray(list)) {
                    return [];
                }
                return list
                    .map((item) => {
                        const id = Number(item && item.id);
                        return {
                            id: Number.isFinite(id) ? id : 0,
                            title: typeof item?.title === 'string' ? item.title : '',
                            slug: typeof item?.slug === 'string' ? item.slug : '',
                            nivel: typeof item?.nivel === 'string' ? item.nivel : '',
                            tipo: typeof item?.tipo === 'string' ? item.tipo : '',
                        };
                    })
                    .filter((item) => item.id > 0);
            }

            function normalizeOffers(rawOffers) {
                if (!Array.isArray(rawOffers)) {
                    return [];
                }
                return rawOffers.map((raw) => {
                    const typeRaw = raw && typeof raw === 'object' ? raw.tipo_oferta : '';
                    const scopeRaw = raw && typeof raw === 'object' ? raw.aplicacion : '';
                    const typeValue = typeof typeRaw === 'object' && typeRaw !== null
                        ? (typeof typeRaw.value === 'string' ? typeRaw.value : '')
                        : (typeof typeRaw === 'string' ? typeRaw : '');
                    const scopeValue = typeof scopeRaw === 'object' && scopeRaw !== null
                        ? (typeof scopeRaw.value === 'string' ? scopeRaw.value : '')
                        : (typeof scopeRaw === 'string' ? scopeRaw : '');
                    let discount = '';
                    if (raw && Object.prototype.hasOwnProperty.call(raw, 'porcentaje_descuento') && raw.porcentaje_descuento !== null) {
                        const numeric = Number(raw.porcentaje_descuento);
                        discount = Number.isFinite(numeric) ? String(numeric) : '';
                    }
                    const selection = [];
                    if (Array.isArray(raw?.seleccion_modalidad)) {
                        raw.seleccion_modalidad.forEach((value) => {
                            const modalId = Number(value);
                            if (Number.isFinite(modalId) && modalId > 0) {
                                selection.push(modalId);
                            }
                        });
                    }
                    const isoDate = typeof raw?.caducidad_iso === 'string' ? raw.caducidad_iso : '';
                    const localDate = typeof raw?.caducidad_oferta === 'string' ? raw.caducidad_oferta : '';
                    return {
                        uid: typeof raw?.uid === 'string' && raw.uid !== '' ? raw.uid : generateOfferId(),
                        tipo_oferta: typeValue,
                        nombre_oferta: typeof raw?.nombre_oferta === 'string' ? raw.nombre_oferta : '',
                        porcentaje_descuento: discount,
                        aplicacion: scopeValue !== '' ? scopeValue : 'todas',
                        caducidad_iso: isoDate,
                        caducidad_oferta: localDate,
                        seleccion_modalidad: selection,
                        estado: raw?.estado !== false,
                        errors: {},
                        dom: {},
                    };
                });
            }

            function createEmptyOffer() {
                const defaultScope = choices.aplicacion.length > 0 && typeof choices.aplicacion[0].value === 'string'
                    ? choices.aplicacion[0].value
                    : 'todas';
                return {
                    uid: generateOfferId(),
                    tipo_oferta: '',
                    nombre_oferta: '',
                    porcentaje_descuento: '',
                    aplicacion: defaultScope,
                    caducidad_iso: '',
                    caducidad_oferta: '',
                    seleccion_modalidad: [],
                    estado: true,
                    errors: {},
                    dom: {},
                };
            }

            function formatIsoToLocal(value) {
                if (typeof value !== 'string' || value.trim() === '') {
                    return '';
                }
                const parts = value.trim().split('-');
                if (parts.length !== 3) {
                    return '';
                }
                return `${parts[2]}/${parts[1]}/${parts[0]}`;
            }

            function setInitialSnapshot() {
                initialSnapshot = JSON.stringify(serializeOffers());
                dirty = false;
                updateSaveButton();
            }

            function updateDirtyState() {
                const snapshot = JSON.stringify(serializeOffers());
                dirty = snapshot !== initialSnapshot;
                updateSaveButton();
            }

            function renderLoading() {
                if (!body) {
                    return;
                }
                const message = strings.manageOffersLoading || 'Cargando ofertas…';
                body.innerHTML = `<div class="client-offers__state client-offers__state--loading"><span class="client-offers__spinner" aria-hidden="true"></span><p>${escapeHtml(message)}</p></div>`;
            }

            function renderError(message) {
                if (!body) {
                    return;
                }
                body.innerHTML = `<div class="client-offers__state client-offers__state--error"><p>${escapeHtml(message)}</p></div>`;
                dirty = false;
                updateSaveButton();
            }

            function refreshOfferOrdering() {
                offers.forEach((offer, index) => {
                    updateOfferMetadata(offer, index);
                });
            }

            function renderOffers() {
                if (!body) {
                    return;
                }

                body.innerHTML = '';
                const container = document.createElement('div');
                container.className = 'client-offers';

                const introText = typeof strings.manageOffersIntro === 'string' ? strings.manageOffersIntro.trim() : '';
                if (introText !== '') {
                    const intro = document.createElement('p');
                    intro.className = 'client-offers__intro';
                    intro.textContent = introText;
                    container.appendChild(intro);
                }

                const list = document.createElement('div');
                list.className = 'client-offers__list';

                if (offers.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'client-offers__empty';
                    empty.textContent = strings.manageOffersEmptyState || 'No hay ofertas configuradas para este cliente.';
                    list.appendChild(empty);
                } else {
                    offers.forEach((offer, index) => {
                        if (!offer || typeof offer !== 'object') {
                            return;
                        }
                        offer.errors = offer.errors || {};
                        offer.dom = offer.dom || {};
                        const card = buildOfferCard(offer, index);
                        list.appendChild(card);
                    });
                }

                container.appendChild(list);

                body.appendChild(container);
                refreshOfferOrdering();
                updateSaveButton();
            }

            function createFieldElement(id, labelText, controlElement) {
                const field = document.createElement('div');
                field.className = 'client-offer-card__field';
                const label = document.createElement('label');
                label.className = 'client-offer-card__label';
                if (id) {
                    label.setAttribute('for', id);
                }
                label.textContent = labelText;
                const control = document.createElement('div');
                control.className = 'client-offer-card__control';
                control.appendChild(controlElement);
                const error = document.createElement('p');
                error.className = 'client-offer-card__error';
                control.appendChild(error);
                field.appendChild(label);
                field.appendChild(control);
                return { field, control, error };
            }

            function buildOfferCard(offer, index) {
                const card = document.createElement('article');
                card.className = 'client-offer-card';
                card.dataset.uid = offer.uid;
                card.dataset.index = String(index);

                const header = document.createElement('header');
                header.className = 'client-offer-card__header';

                const heading = document.createElement('div');
                heading.className = 'client-offer-card__heading';

                const title = document.createElement('h3');
                title.className = 'client-offer-card__title';
                heading.appendChild(title);

                const badge = document.createElement('span');
                badge.className = 'client-offer-card__badge';
                heading.appendChild(badge);

                header.appendChild(heading);

                const toolbar = document.createElement('div');
                toolbar.className = 'client-offer-card__toolbar';

                const actions = document.createElement('div');
                actions.className = 'client-offer-card__actions';

                const duplicateButton = document.createElement('button');
                duplicateButton.type = 'button';
                duplicateButton.className = 'client-offer-card__action';
                duplicateButton.textContent = strings.manageOffersDuplicate || 'Duplicar';
                duplicateButton.addEventListener('click', () => {
                    duplicateOffer(index);
                });
                actions.appendChild(duplicateButton);

                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'client-offer-card__action client-offer-card__action--danger';
                deleteButton.textContent = strings.manageOffersDelete || 'Eliminar';
                deleteButton.addEventListener('click', () => {
                    removeOffer(index);
                });
                actions.appendChild(deleteButton);

                const statusToggle = document.createElement('label');
                statusToggle.className = 'client-toggle client-offer-card__toggle';
                const statusId = uniqueId('offer-status');
                const statusInput = document.createElement('input');
                statusInput.type = 'checkbox';
                statusInput.id = statusId;
                statusInput.className = 'client-toggle__input';
                statusInput.checked = Boolean(offer.estado);
                const toggleTrack = document.createElement('span');
                toggleTrack.className = 'client-toggle__track';
                const statusText = document.createElement('span');
                statusText.className = 'client-toggle__text';
                statusToggle.appendChild(statusInput);
                statusToggle.appendChild(toggleTrack);
                statusToggle.appendChild(statusText);
                actions.appendChild(statusToggle);

                toolbar.appendChild(actions);
                header.appendChild(toolbar);
                card.appendChild(header);

                const grid = document.createElement('div');
                grid.className = 'client-offer-card__grid';

                const typeId = uniqueId('offer-type');
                const typeSelect = document.createElement('select');
                typeSelect.id = typeId;
                typeSelect.className = 'client-offer-card__select';
                const typePlaceholder = document.createElement('option');
                typePlaceholder.value = '';
                typePlaceholder.textContent = strings.manageOffersTypePlaceholder || 'Selecciona un tipo…';
                typeSelect.appendChild(typePlaceholder);
                choices.tipo_oferta.forEach((choice) => {
                    if (!choice || typeof choice.value !== 'string') {
                        return;
                    }
                    const option = document.createElement('option');
                    option.value = choice.value;
                    option.textContent = typeof choice.label === 'string' ? choice.label : choice.value;
                    typeSelect.appendChild(option);
                });
                typeSelect.value = offer.tipo_oferta || '';
                const typeFieldObj = createFieldElement(typeId, strings.manageOffersTypeLabel || 'Tipo de oferta', typeSelect);
                grid.appendChild(typeFieldObj.field);

                const nameId = uniqueId('offer-name');
                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.id = nameId;
                nameInput.className = 'client-offer-card__input';
                nameInput.placeholder = strings.manageOffersNamePlaceholder || '';
                nameInput.value = offer.nombre_oferta || '';
                const nameFieldObj = createFieldElement(nameId, strings.manageOffersNameLabel || 'Nombre personalizado', nameInput);
                grid.appendChild(nameFieldObj.field);

                const discountId = uniqueId('offer-discount');
                const discountInput = document.createElement('input');
                discountInput.type = 'number';
                discountInput.min = '0';
                discountInput.max = '100';
                discountInput.step = '0.5';
                discountInput.id = discountId;
                discountInput.className = 'client-offer-card__input';
                discountInput.placeholder = strings.manageOffersDiscountPlaceholder || '';
                discountInput.value = offer.porcentaje_descuento || '';
                const discountFieldObj = createFieldElement(discountId, strings.manageOffersDiscountLabel || 'Porcentaje de descuento', discountInput);
                grid.appendChild(discountFieldObj.field);

                const scopeId = uniqueId('offer-scope');
                const scopeSelect = document.createElement('select');
                scopeSelect.id = scopeId;
                scopeSelect.className = 'client-offer-card__select';
                const scopePlaceholder = document.createElement('option');
                scopePlaceholder.value = '';
                scopePlaceholder.textContent = strings.manageOffersScopePlaceholder || 'Selecciona un ámbito…';
                scopeSelect.appendChild(scopePlaceholder);
                choices.aplicacion.forEach((choice) => {
                    if (!choice || typeof choice.value !== 'string') {
                        return;
                    }
                    const option = document.createElement('option');
                    option.value = choice.value;
                    option.textContent = typeof choice.label === 'string' ? choice.label : choice.value;
                    scopeSelect.appendChild(option);
                });
                scopeSelect.value = offer.aplicacion || '';
                const scopeFieldObj = createFieldElement(scopeId, strings.manageOffersScopeLabel || 'Ámbito de aplicación', scopeSelect);
                grid.appendChild(scopeFieldObj.field);

                const modalitiesId = uniqueId('offer-modalities');
                const modalitiesContainer = document.createElement('div');
                modalitiesContainer.className = 'client-offer-card__modalities';
                modalitiesContainer.id = modalitiesId;
                const modalitiesFieldObj = createFieldElement(modalitiesId, strings.manageOffersModalitiesLabel || 'Modalidades incluidas', modalitiesContainer);
                grid.appendChild(modalitiesFieldObj.field);

                const modalitiesList = document.createElement('div');
                modalitiesList.className = 'client-offer-card__modalities-list';
                modalitiesContainer.appendChild(modalitiesList);

                const expiryId = uniqueId('offer-expiry');
                const expiryInput = document.createElement('input');
                expiryInput.type = 'date';
                expiryInput.id = expiryId;
                expiryInput.className = 'client-offer-card__input';
                if (offer.caducidad_iso) {
                    expiryInput.value = offer.caducidad_iso;
                }
                const expiryFieldObj = createFieldElement(expiryId, strings.manageOffersExpiryLabel || 'Caducidad', expiryInput);
                grid.appendChild(expiryFieldObj.field);

                const note = document.createElement('p');
                note.className = 'client-offer-card__note';
                note.textContent = strings.manageOffersSinSuplementosNote || 'No se aplicarán suplementos cuando esta oferta esté activa.';
                grid.appendChild(note);

                card.appendChild(grid);

                offer.dom = {
                    card,
                    title,
                    badge,
                    typeSelect,
                    nameInput,
                    discountInput,
                    scopeSelect,
                    modalitiesList,
                    modalitiesField: modalitiesFieldObj.field,
                    modalitiesContainer,
                    expiryInput,
                    statusInput,
                    statusText,
                    note,
                    errorElements: {
                        tipo_oferta: typeFieldObj.error,
                        nombre_oferta: nameFieldObj.error,
                        porcentaje_descuento: discountFieldObj.error,
                        aplicacion: scopeFieldObj.error,
                        seleccion_modalidad: modalitiesFieldObj.error,
                        caducidad_iso: expiryFieldObj.error,
                    },
                    fieldWrappers: {
                        tipo_oferta: typeFieldObj.field,
                        nombre_oferta: nameFieldObj.field,
                        porcentaje_descuento: discountFieldObj.field,
                        aplicacion: scopeFieldObj.field,
                        seleccion_modalidad: modalitiesFieldObj.field,
                        caducidad_iso: expiryFieldObj.field,
                    },
                };

                setupModalitiesList(offer);
                syncOfferVisibility(offer);
                applyOfferErrors(offer);
                updateOfferMetadata(offer, index);

                typeSelect.addEventListener('change', (event) => {
                    offer.tipo_oferta = event.target.value;
                    if (offer.tipo_oferta !== 'personalizar') {
                        offer.nombre_oferta = offer.nombre_oferta || '';
                    }
                    if (offer.tipo_oferta === 'sin_suplementos') {
                        offer.porcentaje_descuento = '';
                        discountInput.value = '';
                    }
                    clearFieldError(offer, 'tipo_oferta');
                    if (offer.tipo_oferta !== 'personalizar') {
                        clearFieldError(offer, 'nombre_oferta');
                    }
                    if (offer.tipo_oferta === 'sin_suplementos') {
                        clearFieldError(offer, 'porcentaje_descuento');
                    }
                    syncOfferVisibility(offer);
                    updateOfferMetadata(offer, index);
                    updateDirtyState();
                });

                nameInput.addEventListener('input', (event) => {
                    offer.nombre_oferta = event.target.value;
                    clearFieldError(offer, 'nombre_oferta');
                    updateDirtyState();
                });

                discountInput.addEventListener('input', (event) => {
                    offer.porcentaje_descuento = event.target.value;
                    clearFieldError(offer, 'porcentaje_descuento');
                    updateDirtyState();
                });

                scopeSelect.addEventListener('change', (event) => {
                    offer.aplicacion = event.target.value;
                    clearFieldError(offer, 'aplicacion');
                    if (offer.aplicacion !== 'seleccion') {
                        offer.seleccion_modalidad = [];
                    }
                    setupModalitiesList(offer);
                    syncOfferVisibility(offer);
                    updateDirtyState();
                });

                expiryInput.addEventListener('change', (event) => {
                    offer.caducidad_iso = event.target.value;
                    offer.caducidad_oferta = formatIsoToLocal(event.target.value);
                    clearFieldError(offer, 'caducidad_iso');
                    updateDirtyState();
                });

                statusInput.addEventListener('change', () => {
                    offer.estado = statusInput.checked;
                    updateOfferMetadata(offer, index);
                    updateDirtyState();
                });

                return card;
            }

            function setupModalitiesList(offer) {
                if (!offer.dom || !offer.dom.modalitiesList) {
                    return;
                }
                const list = offer.dom.modalitiesList;
                list.innerHTML = '';
                if (!Array.isArray(modalities) || modalities.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'client-offer-card__modalities-empty';
                    empty.textContent = strings.manageOffersModalitiesEmpty || 'No hay modalidades disponibles.';
                    list.appendChild(empty);
                    return;
                }
                const selected = Array.isArray(offer.seleccion_modalidad) ? offer.seleccion_modalidad.slice() : [];
                modalities.forEach((modalidad) => {
                    const item = document.createElement('label');
                    item.className = 'client-offer-card__modalities-item';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'client-offer-card__checkbox';
                    checkbox.value = String(modalidad.id);
                    checkbox.checked = selected.includes(modalidad.id);
                    const marker = document.createElement('span');
                    marker.className = 'client-offer-card__checkbox-marker';
                    const text = document.createElement('span');
                    text.className = 'client-offer-card__modalities-text';
                    const title = document.createElement('span');
                    title.className = 'client-offer-card__modalities-name';
                    title.textContent = modalidad.title || '';
                    text.appendChild(title);
                    const metaParts = [modalidad.nivel, modalidad.tipo].filter((part) => typeof part === 'string' && part.trim() !== '');
                    if (metaParts.length > 0) {
                        const meta = document.createElement('span');
                        meta.className = 'client-offer-card__modalities-meta';
                        meta.textContent = metaParts.join(' · ');
                        text.appendChild(meta);
                    }
                    checkbox.addEventListener('change', (event) => {
                        const value = parseInt(event.target.value, 10);
                        if (event.target.checked) {
                            if (!offer.seleccion_modalidad.includes(value)) {
                                offer.seleccion_modalidad.push(value);
                            }
                        } else {
                            offer.seleccion_modalidad = offer.seleccion_modalidad.filter((id) => id !== value);
                        }
                        clearFieldError(offer, 'seleccion_modalidad');
                        updateDirtyState();
                    });
                    item.appendChild(checkbox);
                    item.appendChild(marker);
                    item.appendChild(text);
                    list.appendChild(item);
                });
            }

            function syncOfferVisibility(offer) {
                if (!offer.dom) {
                    return;
                }
                const isPersonalizada = offer.tipo_oferta === 'personalizar';
                const isSinSuplementos = offer.tipo_oferta === 'sin_suplementos';
                const isSeleccion = offer.aplicacion === 'seleccion';
                if (offer.dom.fieldWrappers?.nombre_oferta) {
                    offer.dom.fieldWrappers.nombre_oferta.classList.toggle('is-hidden', !isPersonalizada);
                }
                if (offer.dom.fieldWrappers?.porcentaje_descuento) {
                    offer.dom.fieldWrappers.porcentaje_descuento.classList.toggle('is-hidden', isSinSuplementos);
                }
                if (offer.dom.modalitiesField) {
                    offer.dom.modalitiesField.classList.toggle('is-hidden', !isSeleccion);
                }
                if (offer.dom.note) {
                    offer.dom.note.hidden = !isSinSuplementos;
                }
            }

            function updateOfferMetadata(offer, index) {
                if (!offer.dom) {
                    return;
                }
                const resolvedIndex = typeof index === 'number' ? index : offers.indexOf(offer);
                if (offer.dom.title && resolvedIndex >= 0) {
                    const base = strings.manageOffersCardTitle || 'Oferta';
                    offer.dom.title.textContent = `${base} ${resolvedIndex + 1}`;
                }
                const typeLabel = choiceMaps.tipo.get(offer.tipo_oferta) || (offer.tipo_oferta || '');
                if (offer.dom.badge) {
                    offer.dom.badge.textContent = typeLabel;
                    offer.dom.badge.hidden = typeLabel === '';
                }
                if (offer.dom.statusText) {
                    offer.dom.statusText.textContent = offer.estado
                        ? (strings.manageOffersStatusActive || 'Activa')
                        : (strings.manageOffersStatusInactive || 'Inactiva');
                }
                if (offer.dom.card) {
                    offer.dom.card.classList.toggle('client-offer-card--inactive', !offer.estado);
                    offer.dom.card.classList.toggle('client-offer-card--sin-suplementos', offer.tipo_oferta === 'sin_suplementos');
                }
            }

            function applyOfferErrors(offer) {
                if (!offer.dom || !offer.dom.errorElements) {
                    return;
                }
                const errors = offer.errors || {};
                Object.keys(offer.dom.errorElements).forEach((key) => {
                    const message = typeof errors[key] === 'string' ? errors[key] : '';
                    const errorEl = offer.dom.errorElements[key];
                    if (errorEl) {
                        errorEl.textContent = message;
                    }
                    const wrapper = offer.dom.fieldWrappers ? offer.dom.fieldWrappers[key] : null;
                    if (wrapper) {
                        wrapper.classList.toggle('client-offer-card__field--error', message !== '');
                    }
                });
            }

            function clearFieldError(offer, fieldKey) {
                if (!offer || !offer.dom || !offer.dom.errorElements) {
                    return;
                }
                if (offer.errors && offer.errors[fieldKey]) {
                    delete offer.errors[fieldKey];
                }
                const errorEl = offer.dom.errorElements[fieldKey];
                if (errorEl) {
                    errorEl.textContent = '';
                }
                const wrapper = offer.dom.fieldWrappers ? offer.dom.fieldWrappers[fieldKey] : null;
                if (wrapper) {
                    wrapper.classList.remove('client-offer-card__field--error');
                }
            }

            function duplicateOffer(index) {
                const source = offers[index];
                if (!source) {
                    return;
                }
                const clone = {
                    ...source,
                    uid: generateOfferId(),
                    seleccion_modalidad: Array.isArray(source.seleccion_modalidad)
                        ? source.seleccion_modalidad.slice()
                        : [],
                    errors: {},
                    dom: {},
                };
                offers.splice(index + 1, 0, clone);
                renderOffers();
                updateDirtyState();
            }

            function removeOffer(index) {
                if (index < 0 || index >= offers.length) {
                    return;
                }
                offers.splice(index, 1);
                renderOffers();
                updateDirtyState();
            }

            function serializeOffers() {
                return offers.map((offer) => {
                    const scope = offer.aplicacion || '';
                    const nameValue = typeof offer.nombre_oferta === 'string' ? offer.nombre_oferta.trim() : '';
                    const selected = Array.isArray(offer.seleccion_modalidad)
                        ? offer.seleccion_modalidad
                            .map((value) => parseInt(value, 10))
                            .filter((value) => Number.isFinite(value) && value > 0)
                        : [];
                    const payload = {
                        tipo_oferta: offer.tipo_oferta || '',
                        nombre_oferta: nameValue,
                        porcentaje_descuento: null,
                        aplicacion: scope,
                        caducidad_iso: offer.caducidad_iso || '',
                        seleccion_modalidad: selected,
                        estado: Boolean(offer.estado),
                    };
                    if (offer.tipo_oferta !== 'sin_suplementos') {
                        const numeric = Number(offer.porcentaje_descuento);
                        payload.porcentaje_descuento = Number.isFinite(numeric) ? numeric : '';
                    }
                    if (scope !== 'seleccion') {
                        payload.seleccion_modalidad = [];
                    }
                    return payload;
                });
            }

            function validateOffers() {
                let isValid = true;
                offers.forEach((offer) => {
                    offer.errors = offer.errors || {};
                    offer.errors.tipo_oferta = '';
                    offer.errors.nombre_oferta = '';
                    offer.errors.porcentaje_descuento = '';
                    offer.errors.aplicacion = '';
                    offer.errors.seleccion_modalidad = '';
                    offer.errors.caducidad_iso = '';

                    if (!offer.tipo_oferta) {
                        offer.errors.tipo_oferta = strings.manageOffersTypeError || 'Selecciona un tipo de oferta.';
                        isValid = false;
                    }
                    if (offer.tipo_oferta === 'personalizar' && (!offer.nombre_oferta || offer.nombre_oferta.trim() === '')) {
                        offer.errors.nombre_oferta = strings.manageOffersNameError || 'Introduce un nombre para esta oferta.';
                        isValid = false;
                    }
                    if (offer.tipo_oferta !== 'sin_suplementos') {
                        const numeric = Number(offer.porcentaje_descuento);
                        if (!Number.isFinite(numeric) || numeric <= 0 || numeric > 100) {
                            offer.errors.porcentaje_descuento = strings.manageOffersDiscountError || 'Introduce un porcentaje entre 1 y 100.';
                            isValid = false;
                        }
                    }
                    if (!offer.aplicacion) {
                        offer.errors.aplicacion = strings.manageOffersScopeError || 'Selecciona un ámbito de aplicación.';
                        isValid = false;
                    }
                    if (offer.aplicacion === 'seleccion' && (!Array.isArray(offer.seleccion_modalidad) || offer.seleccion_modalidad.length === 0)) {
                        offer.errors.seleccion_modalidad = strings.manageOffersModalitiesError || 'Selecciona al menos una modalidad.';
                        isValid = false;
                    }
                    if (offer.caducidad_iso && !/^\d{4}-\d{2}-\d{2}$/.test(offer.caducidad_iso)) {
                        offer.errors.caducidad_iso = strings.manageOffersExpiryError || 'Introduce una fecha válida.';
                        isValid = false;
                    }
                    applyOfferErrors(offer);
                    syncOfferVisibility(offer);
                });
                if (!isValid) {
                    setStatus(strings.manageOffersValidationError || 'Revisa los campos marcados para continuar.', 'error');
                } else {
                    setStatus('');
                }
                return isValid;
            }

            async function loadOffers(userId) {
                isLoading = true;
                updateSaveButton();
                renderLoading();
                setStatus('');
                try {
                    const response = await fetch(`${restBase}go/v1/clientes/${userId}/offers`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: restNonceValue ? { 'X-WP-Nonce': restNonceValue } : {},
                    });
                    if (!response.ok) {
                        throw new Error(`Request failed: ${response.status}`);
                    }
                    const data = await response.json();
                    choices = normalizeChoices(data?.choices || {});
                    modalities = normalizeModalities(data?.modalidades || []);
                    offers = normalizeOffers(data?.offers || []);
                    renderOffers();
                    setInitialSnapshot();
                    setStatus('');
                } catch (error) {
                    console.error('Error loading offers', error);
                    renderError(strings.manageOffersFetchError || 'No se han podido cargar las ofertas. Actualiza la página e inténtalo de nuevo.');
                } finally {
                    isLoading = false;
                    updateSaveButton();
                }
            }

            async function handleSave(event) {
                event.preventDefault();
                if (isLoading || isSaving || !dirty) {
                    return;
                }
                if (!validateOffers()) {
                    return;
                }
                const userId = currentContext && Number.isFinite(currentContext.userId) ? currentContext.userId : null;
                if (!userId) {
                    return;
                }
                isSaving = true;
                updateSaveButton();
                setStatus(strings.manageOffersSaving || 'Guardando…');
                try {
                    const response = await fetch(`${restBase}go/v1/clientes/${userId}/offers`, {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            ...(restNonceValue ? { 'X-WP-Nonce': restNonceValue } : {}),
                        },
                        body: JSON.stringify({ offers: serializeOffers() }),
                    });
                    if (!response.ok) {
                        throw new Error(`Request failed: ${response.status}`);
                    }
                    const data = await response.json();
                    choices = normalizeChoices(data?.choices || {});
                    modalities = normalizeModalities(data?.modalidades || []);
                    offers = normalizeOffers(data?.offers || []);
                    renderOffers();
                    setInitialSnapshot();
                    setStatus(strings.manageOffersSaved || 'Ofertas actualizadas correctamente.', 'success');
                    if (currentContext && typeof currentContext.onComplete === 'function') {
                        currentContext.onComplete(Array.isArray(data?.summary) ? data.summary : []);
                    }
                } catch (error) {
                    console.error('Error saving offers', error);
                    setStatus(strings.manageOffersError || 'No se ha podido guardar las ofertas. Inténtalo de nuevo.', 'error');
                } finally {
                    isSaving = false;
                    updateSaveButton();
                }
            }

            function close() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                overlay.hidden = true;
                document.removeEventListener('keydown', handleKeydown);
                setStatus('');
                resetState();
                currentContext = null;
                if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
                    previousActiveElement.focus();
                }
            }

            function open(context = {}) {
                previousActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                currentContext = context || {};
                if (titleEl) {
                    titleEl.textContent = formatTitle(currentContext);
                }
                resetState();
                overlay.hidden = false;
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.addEventListener('keydown', handleKeydown);
                window.requestAnimationFrame(() => {
                    if (panel && typeof panel.focus === 'function') {
                        panel.focus({ preventScroll: true });
                    }
                });
                const userId = Number(context && context.id);
                if (!Number.isFinite(userId) || userId <= 0) {
                    renderError(strings.manageOffersFetchError || 'No se han podido cargar las ofertas. Actualiza la página e inténtalo de nuevo.');
                    return;
                }
                currentContext.userId = userId;
                renderLoading();
                loadOffers(userId);
            }

            if (saveButton) {
                saveButton.addEventListener('click', handleSave);
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
            const overlayClass = typeof options.overlayClass === 'string' ? options.overlayClass.trim() : '';
            const panelClass = typeof options.panelClass === 'string' ? options.panelClass.trim() : '';
            const bodyClass = typeof options.bodyClass === 'string' ? options.bodyClass.trim() : '';
            const showFooter = options.showFooter !== false;
            const resetBody = options.resetBody !== false;
            const onOpen = typeof options.onOpen === 'function' ? options.onOpen : null;
            const onClose = typeof options.onClose === 'function' ? options.onClose : null;
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

            if (overlayClass !== '') {
                overlay.classList.add(overlayClass);
            }

            document.body.appendChild(overlay);

            const panel = overlay.querySelector('.client-dialog__panel');
            const titleEl = overlay.querySelector('.client-dialog__title');
            const bodyEl = overlay.querySelector('.client-dialog__body--simple');
            const saveButton = overlay.querySelector('.client-dialog__save');
            const footer = overlay.querySelector('.client-dialog__footer');
            const statusEl = overlay.querySelector('.client-dialog__status');
            const closeControls = overlay.querySelectorAll('[data-dialog-close]');

            if (panelClass !== '' && panel) {
                panel.classList.add(panelClass);
            }

            if (bodyClass !== '' && bodyEl) {
                bodyEl.classList.add(bodyClass);
            }

            if (!showFooter && footer) {
                footer.hidden = true;
                footer.setAttribute('aria-hidden', 'true');
            }

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
                if (onClose) {
                    onClose({
                        overlay,
                        panel,
                        body: bodyEl,
                        status: statusEl,
                        saveButton,
                        footer,
                    });
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

                if (bodyEl && resetBody) {
                    bodyEl.innerHTML = '';
                    bodyEl.scrollTop = 0;
                }

                if (onOpen) {
                    onOpen({
                        overlay,
                        panel,
                        body: bodyEl,
                        status: statusEl,
                        saveButton,
                        footer,
                        context,
                        close,
                        setStatus,
                    });
                }

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

        function setupSepaConfirmModal(modal) {
            if (!modal) {
                return null;
            }

            const dialog = modal.querySelector('.confirm-modal__dialog');
            const closeBtn = modal.querySelector('.confirm-modal__close');
            const cancelBtn = modal.querySelector('.confirm-modal__btn--cancel');
            const confirmBtn = modal.querySelector('.confirm-modal__btn--confirm');
            const subtitleEl = modal.querySelector('.confirm-modal__subtitle');
            const messageEl = modal.querySelector('.confirm-modal__message');
            const noteEl = modal.querySelector('.confirm-modal__note');
            const errorEl = modal.querySelector('.confirm-modal__error');
            const titleEl = modal.querySelector('.confirm-modal__title');
            const checkboxInput = modal.querySelector('.confirm-modal__checkbox-input');
            const checkboxLabel = modal.querySelector('.confirm-modal__checkbox-label');
            const reasonField = modal.querySelector('[data-confirm-reason]');
            const reasonTextarea = reasonField ? reasonField.querySelector('.confirm-modal__textarea') : null;
            const reasonHelpEl = reasonField ? reasonField.querySelector('.confirm-modal__field-help') : null;
            const reasonLabelEl = reasonField ? reasonField.querySelector('.confirm-modal__field-label') : null;
            const defaultConfirmLabel = strings.manageSepaConfirmAccept || 'Activar domiciliación bancaria';
            const defaultErrorMessage = strings.manageSepaConfirmError || 'No se ha podido activar la domiciliación bancaria. Inténtalo de nuevo.';
            const defaultTitle = strings.manageSepaConfirmTitle || 'Confirmar SEPA';
            const defaultCheckboxLabel = strings.manageSepaConfirmCheckbox || 'He revisado esta información y confirmo la operación.';
            const defaultLoadingLabel = strings.manageSepaConfirmLoading || 'Activando…';
            const defaultMessageTemplate = strings.manageSepaConfirmMessage || 'Confirmo que %s nos ha enviado el mandato SEPA firmado y todos los datos son correctos.';
            const defaultNote = strings.manageSepaConfirmNote || '';

            const sanitizeText = (value, fallback) => {
                if (typeof value === 'string') {
                    const trimmed = value.trim();
                    if (trimmed !== '') {
                        return trimmed;
                    }
                }

                return fallback;
            };

            let currentContext = null;
            let previousActiveElement = null;

            function resetReasonField() {
                if (reasonField) {
                    reasonField.hidden = true;
                    reasonField.style.display = 'none';
                    reasonField.setAttribute('aria-hidden', 'true');
                    reasonField.classList.remove('confirm-modal__field--invalid');
                }
                if (reasonTextarea) {
                    reasonTextarea.value = '';
                    reasonTextarea.disabled = false;
                    reasonTextarea.placeholder = '';
                }
                if (reasonHelpEl) {
                    reasonHelpEl.textContent = '';
                    reasonHelpEl.hidden = true;
                }
            }

            function setReasonError(message) {
                if (!reasonField) {
                    return;
                }

                const text = typeof message === 'string' ? message.trim() : '';
                if (text === '') {
                    reasonField.classList.remove('confirm-modal__field--invalid');
                    if (reasonHelpEl) {
                        const baseHelp = currentContext && typeof currentContext.reasonHelp === 'string'
                            ? currentContext.reasonHelp
                            : '';
                        reasonHelpEl.textContent = baseHelp;
                        reasonHelpEl.hidden = baseHelp === '';
                    }
                } else {
                    reasonField.classList.add('confirm-modal__field--invalid');
                    if (reasonHelpEl) {
                        reasonHelpEl.textContent = text;
                        reasonHelpEl.hidden = false;
                    }
                }
            }

            function getReasonValue() {
                if (!reasonTextarea) {
                    return '';
                }

                return reasonTextarea.value.trim();
            }

            function setError(message) {
                if (!errorEl) {
                    return;
                }

                const text = typeof message === 'string' ? message.trim() : '';
                if (text === '') {
                    errorEl.textContent = '';
                    errorEl.hidden = true;
                } else {
                    errorEl.textContent = text;
                    errorEl.hidden = false;
                }
            }

            function syncConfirmState() {
                if (!confirmBtn) {
                    return;
                }

                if (confirmBtn.dataset.loading === 'true') {
                    confirmBtn.disabled = true;
                    return;
                }

                const checked = checkboxInput ? checkboxInput.checked : true;
                const reasonRequired = Boolean(currentContext && currentContext.reasonRequired);
                const reasonValid = !reasonRequired || getReasonValue() !== '';
                confirmBtn.disabled = !checked || !reasonValid;
            }

            function setLoading(isLoading) {
                if (!confirmBtn) {
                    return;
                }

                if (isLoading) {
                    confirmBtn.dataset.loading = 'true';
                    confirmBtn.disabled = true;
                    confirmBtn.textContent = sanitizeText(currentContext?.loadingLabel, defaultLoadingLabel);
                    if (reasonTextarea) {
                        reasonTextarea.disabled = true;
                    }
                } else {
                    confirmBtn.dataset.loading = 'false';
                    confirmBtn.textContent = sanitizeText(currentContext?.confirmLabel, defaultConfirmLabel);
                    syncConfirmState();
                    if (reasonTextarea) {
                        reasonTextarea.disabled = false;
                    }
                }
            }

            function resetConfirmButton() {
                if (!confirmBtn) {
                    return;
                }

                confirmBtn.dataset.loading = 'false';
                confirmBtn.textContent = defaultConfirmLabel;
                syncConfirmState();
            }

            function close() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                modal.hidden = true;
                document.removeEventListener('keydown', handleKeydown);
                setError('');
                if (checkboxInput) {
                    checkboxInput.checked = false;
                }
                resetReasonField();
                currentContext = null;
                resetConfirmButton();
                if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
                    previousActiveElement.focus();
                }
            }

            function handleOverlayClick(event) {
                if (event.target === modal) {
                    close();
                }
            }

            function handleKeydown(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close();
                }
            }

            function formatMessage(actor, template) {
                if (template.includes('%s')) {
                    const actorHtml = template.includes('<strong>%s</strong>')
                        ? escapeHtml(actor)
                        : `<strong>${escapeHtml(actor)}</strong>`;
                    return template.replace('%s', actorHtml);
                }

                return template;
            }

            function open(context = {}) {
                previousActiveElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                resetReasonField();
                currentContext = {
                    confirmLabel: sanitizeText(context.confirmLabel, defaultConfirmLabel),
                    loadingLabel: sanitizeText(context.loadingLabel, defaultLoadingLabel),
                    title: sanitizeText(context.title, defaultTitle),
                    checkboxLabel: sanitizeText(context.checkboxLabel, defaultCheckboxLabel),
                    message: sanitizeText(context.message, defaultMessageTemplate),
                    note: sanitizeText(context.note, defaultNote),
                    actor: sanitizeText(context.actor, strings.manageSepaConfirmActorFallback || 'este profesional'),
                    subtitleHtml: typeof context.subtitleHtml === 'string' ? context.subtitleHtml.trim() : '',
                    onConfirm: typeof context.onConfirm === 'function' ? context.onConfirm : null,
                    reasonRequired: Boolean(context.reasonRequired),
                    reasonLabel: sanitizeText(context.reasonLabel, strings.manageSepaDeactivateReasonLabel || ''),
                    reasonPlaceholder: sanitizeText(context.reasonPlaceholder, strings.manageSepaDeactivateReasonPlaceholder || ''),
                    reasonHelp: sanitizeText(context.reasonHelp, strings.manageSepaDeactivateReasonHelp || ''),
                    reasonError: sanitizeText(context.reasonError, strings.manageSepaDeactivateReasonError || defaultErrorMessage),
                };

                if (titleEl) {
                    titleEl.textContent = currentContext.title;
                }

                if (checkboxLabel) {
                    checkboxLabel.textContent = currentContext.checkboxLabel;
                }

                if (subtitleEl) {
                    if (currentContext.subtitleHtml !== '') {
                        subtitleEl.innerHTML = currentContext.subtitleHtml;
                        subtitleEl.hidden = false;
                    } else {
                        subtitleEl.textContent = '';
                        subtitleEl.hidden = true;
                    }
                }

                if (messageEl) {
                    messageEl.innerHTML = formatMessage(currentContext.actor, currentContext.message);
                }

                if (noteEl) {
                    if (currentContext.note === '') {
                        noteEl.textContent = '';
                        noteEl.hidden = true;
                    } else {
                        noteEl.textContent = currentContext.note;
                        noteEl.hidden = false;
                    }
                }

                if (reasonField && reasonTextarea) {
                    const shouldShowReason = Boolean(currentContext.reasonRequired);
                    if (shouldShowReason) {
                        reasonField.hidden = false;
                        reasonField.style.display = '';
                        reasonField.setAttribute('aria-hidden', 'false');
                        reasonField.classList.remove('confirm-modal__field--invalid');
                        reasonTextarea.value = '';
                        reasonTextarea.disabled = false;
                        reasonTextarea.placeholder = currentContext.reasonPlaceholder || '';
                        if (reasonLabelEl) {
                            const reasonLabelText = currentContext.reasonLabel || (strings.manageSepaDeactivateReasonLabel || 'Notas');
                            reasonLabelEl.textContent = reasonLabelText;
                        }
                        if (reasonHelpEl) {
                            if (currentContext.reasonHelp !== '') {
                                reasonHelpEl.textContent = currentContext.reasonHelp;
                                reasonHelpEl.hidden = false;
                            } else {
                                reasonHelpEl.textContent = '';
                                reasonHelpEl.hidden = true;
                            }
                        }
                    } else {
                        resetReasonField();
                    }
                }

                if (confirmBtn) {
                    confirmBtn.textContent = currentContext.confirmLabel;
                    confirmBtn.dataset.loading = 'false';
                }

                setError('');
                setLoading(false);

                if (checkboxInput) {
                    checkboxInput.checked = false;
                }

                modal.hidden = false;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.addEventListener('keydown', handleKeydown);
                syncConfirmState();

                window.requestAnimationFrame(() => {
                    if (dialog && typeof dialog.focus === 'function') {
                        dialog.focus({ preventScroll: true });
                    }
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', close);
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    close();
                });
            }

            if (checkboxInput) {
                checkboxInput.addEventListener('change', syncConfirmState);
            }

            if (reasonTextarea) {
                reasonTextarea.addEventListener('input', () => {
                    if (!currentContext) {
                        return;
                    }
                    setReasonError('');
                    syncConfirmState();
                });
            }

            if (confirmBtn) {
                confirmBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (!currentContext || typeof currentContext.onConfirm !== 'function') {
                        close();
                        return;
                    }

                    setError('');
                    const reasonValue = getReasonValue();
                    if (currentContext.reasonRequired && reasonValue === '') {
                        setReasonError(currentContext.reasonError || defaultErrorMessage);
                        syncConfirmState();
                        return;
                    }

                    setReasonError('');
                    setLoading(true);

                    Promise.resolve(currentContext.onConfirm({
                        close,
                        setError,
                        setLoading,
                        reason: reasonValue,
                        setReasonError,
                    })).catch((error) => {
                        const message = error && typeof error.message === 'string' && error.message.trim() !== ''
                            ? error.message.trim()
                            : defaultErrorMessage;
                        setLoading(false);
                        setError(message);
                    });
                });
            }

            modal.addEventListener('click', handleOverlayClick);

            return {
                open,
            };
        }
        sepaConfirmDialog = setupSepaConfirmModal(document.querySelector('[data-sepa-confirm-modal]'));

        sepaDialog = createSimpleDialog({
            titleKey: 'manageSepaTitle',
            titleTemplateKey: 'manageSepaTitleTemplate',
            fallbackTitle: 'Gestionar SEPA',
            showFooter: true,
            overlayClass: 'client-dialog--sepa',
            panelClass: 'client-dialog__panel--sepa',
            bodyClass: 'client-dialog__body--sepa',
            onOpen(dialogElements) {
                if (!dialogElements || !dialogElements.body) {
                    return;
                }

                currentSepaDialogContext = dialogElements.context || {};
                currentSepaDialogElements = dialogElements;
                setupSepaDialogBody(dialogElements);
            },
            onClose() {
                currentSepaDialogContext = null;
                currentSepaDialogElements = null;
            },
        });


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
                    if (!item) {
                        return;
                    }
                    offersDialog.open({
                        id: item.id,
                        subject: getCompanyLabelFromItem(item),
                        summary: Array.isArray(item.offers) ? item.offers : [],
                        onComplete(updatedSummary) {
                            if (!Array.isArray(updatedSummary)) {
                                return;
                            }

                            item.offers = updatedSummary;
                            cache.set(String(item.id), item);
                            refreshActiveDetail(item);
                        },
                    });
                });
            }

            const manageSepaButton = container.querySelector('[data-manage-sepa]');
            if (manageSepaButton && sepaDialog && typeof sepaDialog.open === 'function') {
                manageSepaButton.addEventListener('click', () => {
                    if (!item) {
                        return;
                    }

                    sepaDialog.open({
                        subject: getCompanyLabelFromItem(item),
                        item,
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
