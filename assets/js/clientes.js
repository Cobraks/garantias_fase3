(() => {
    'use strict';

    const DEBOUNCE_DELAY = 300;

    document.addEventListener('DOMContentLoaded', () => {
        const config = window.__GO_CLIENTES__ || {};
        const restRoot = (config.rest && config.rest.root) || '/wp-json/';
        const restNonce = (config.rest && config.rest.nonce) || '';
        const perPage = (config.pagination && config.pagination.perPage) || 20;
        const strings = config.strings || {};

        const listContainer = document.querySelector('.guarantees-list');
        const table = document.querySelector('.guarantees-table');
        const tbody = table ? table.querySelector('tbody') : null;
        const searchInput = document.getElementById('clientes-search');
        const closeIcon = document.querySelector('.guarantees-list__close-icon');
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
        };

        const cache = new Map();
        let selectedRow = null;
        let lastRowIndex = -1;
        let debounceTimer = null;

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
                .map((commercial) => escapeHtml(commercial.name || ''))
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
            const payment = item.payment || {};
            const commercials = item.commercials || [];

            const avatar = profile.avatar ? `<img src="${escapeAttribute(profile.avatar)}" alt="${escapeAttribute(name.personal || name.company || '')}" class="clients-table__avatar">`
                : `<span class="clients-table__initials">${escapeHtml(profile.initials || '')}</span>`;

            const companyLine = name.company ? `<span class="clients-table__company">${escapeHtml(name.company)}</span>` : '';
            const offersHtml = formatOffers(item.offers);
            const commercialsText = formatCommercialSummary(commercials);

            const tr = document.createElement('tr');
            tr.className = 'guarantees-table__row';
            tr.tabIndex = 0;
            tr.dataset.id = String(item.id);
            tr.dataset.index = String(tbody.children.length);

            tr.innerHTML = `
                <td data-label="${escapeHtml(strings.profile || 'Perfil')}">
                    <div class="clients-table__profile">${avatar}</div>
                </td>
                <td data-label="${escapeHtml(strings.client || 'Cliente')}">
                    <div class="clients-table__client">
                        <span class="clients-table__name">${escapeHtml(name.personal || name.company || '')}</span>
                        ${companyLine}
                    </div>
                </td>
                <td data-label="${escapeHtml(strings.registered || 'Registrado desde')}" class="clients-table__registered">
                    ${registered.display ? escapeHtml(registered.display) : '—'}
                </td>
                <td data-label="${escapeHtml(strings.salesChannel || 'Canal de venta')}" class="clients-table__meta">
                    ${escapeHtml(salesChannel.label || '—')}
                </td>
                <td data-label="${escapeHtml(strings.offers || 'Ofertas activas')}" class="clients-table__offers">${offersHtml}</td>
                <td data-label="${escapeHtml(strings.guarantees || 'Garantías contratadas')}" class="clients-table__meta">
                    ${formatCount(guarantees.count)}
                </td>
                <td data-label="${escapeHtml(strings.paymentMethod || 'Método de pago')}" class="clients-table__meta">
                    ${escapeHtml(payment.label || '—')}
                </td>
                <td data-label="${escapeHtml(strings.commercials || 'Comercial asignado')}" class="clients-table__meta">
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
                const name = escapeHtml(commercial.name || '');
                const email = commercial.email ? `<span>${formatLink('mailto', commercial.email)}</span>` : '';
                const phone = commercial.phone ? `<span>${formatLink('tel', commercial.phone)}</span>` : '';

                return `<li class="client-detail__commercial">${name}${email}${phone}</li>`;
            });

            return `<ul class="client-detail__commercials">${items.join('')}</ul>`;
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

            const avatar = item.profile && item.profile.avatar
                ? `<img src="${escapeAttribute(item.profile.avatar)}" alt="${escapeAttribute(name.personal || name.company || '')}" class="client-detail__avatar">`
                : `<span class="client-detail__avatar client-detail__avatar--initials">${escapeHtml(item.profile?.initials || '')}</span>`;

            const companyLine = name.company ? `<p class="client-detail__company">${escapeHtml(name.company)}</p>` : '';
            const addressLines = joinNonEmpty([
                address.street || '',
                joinNonEmpty([address.zip || '', address.city || ''], ' '),
                joinNonEmpty([address.state || '', address.country || ''], ' '),
            ], '<br>');

            const sepaVariant = sepa.variant ? ` client-detail__status--${escapeHtml(sepa.variant)}` : '';
            const sepaMessage = sepa.label || strings.sepaEmpty || 'Sin información del mandato';

            return `
                <div class="client-detail">
                    <header class="client-detail__header">
                        ${avatar}
                        <div class="client-detail__identity">
                            <h3 class="client-detail__title">${escapeHtml(name.personal || strings.detailTitle || 'Detalles del cliente')}</h3>
                            ${companyLine}
                            <p class="client-detail__meta-line">${escapeHtml(strings.registered || 'Registrado desde')}: <time datetime="${escapeAttribute(registered.iso || '')}">${escapeHtml(registered.display || '—')}</time></p>
                        </div>
                    </header>
                    <div class="client-detail__stats">
                        <div class="client-detail__stat">
                            <span class="client-detail__stat-label">${escapeHtml(strings.guarantees || 'Garantías contratadas')}</span>
                            <span class="client-detail__stat-value">${formatCount(guarantees.count)}</span>
                        </div>
                        <div class="client-detail__stat">
                            <span class="client-detail__stat-label">${escapeHtml(strings.salesChannel || 'Canal de venta')}</span>
                            <span class="client-detail__stat-value">${escapeHtml(salesChannel.label || '—')}</span>
                        </div>
                        <div class="client-detail__stat">
                            <span class="client-detail__stat-label">${escapeHtml(strings.paymentMethod || 'Método de pago')}</span>
                            <span class="client-detail__stat-value">${escapeHtml(payment.label || '—')}</span>
                        </div>
                    </div>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.contact || 'Contacto')}</h4>
                        <dl class="client-detail__list">
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.contactEmail || 'Email de contacto')}</dt>
                                <dd>${formatLink('mailto', contact.email)}</dd>
                            </div>
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.notificationEmail || 'Email de notificaciones')}</dt>
                                <dd>${formatLink('mailto', contact.notification_email)}</dd>
                            </div>
                            <div class="client-detail__item">
                                <dt>${escapeHtml(strings.contactPhone || 'Teléfono de contacto')}</dt>
                                <dd>${formatLink('tel', contact.phone)}</dd>
                            </div>
                        </dl>
                    </section>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.company || 'Empresa')}</h4>
                        <dl class="client-detail__list">
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
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.offers || 'Ofertas activas')}</h4>
                        ${renderOffersList(item.offers)}
                    </section>
                    <section class="client-detail__section">
                        <h4 class="client-detail__section-title">${escapeHtml(strings.commercials || 'Comercial asignado')}</h4>
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

            nextPanel.classList.add('active', direction === 'backward' ? 'slide-in-left' : 'slide-in-right');
            previousPanel.classList.add(direction === 'backward' ? 'slide-out-right' : 'slide-out-left');

            nextPanel.addEventListener('animationend', () => {
                nextPanel.classList.remove('slide-in-left', 'slide-in-right');
            }, { once: true });

            previousPanel.addEventListener('animationend', () => {
                previousPanel.classList.remove('slide-out-left', 'slide-out-right');
                previousPanel.classList.remove('active');
                previousPanel.innerHTML = '';
            }, { once: true });

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
                    <p>${escapeHtml(strings.selectPrompt || 'Selecciona un cliente para ver la información.')}</p>
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

                state.page = Number.isFinite(data.page) ? data.page : page;
                state.totalPages = Number.isFinite(data.total_pages) ? Math.max(1, data.total_pages) : state.totalPages;
                tbody.dataset.currentPage = String(state.page);
                tbody.dataset.totalPages = String(state.totalPages);

                if (!append && items.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.className = 'guarantees-table__row guarantees-table__row--empty';
                    emptyRow.innerHTML = `<td colspan="8">${escapeHtml(strings.noResults || 'No se han encontrado clientes con los filtros actuales.')}</td>`;
                    tbody.appendChild(emptyRow);
                    return;
                }

                items.forEach((item) => {
                    cache.set(String(item.id), item);
                    const row = renderRow(item);
                    tbody.appendChild(row);
                });

                if (append && selectedRow) {
                    selectedRow.focus({ preventScroll: true });
                }
            } catch (error) {
                console.error('Error loading clients', error);
                if (!append) {
                    const errorRow = document.createElement('tr');
                    errorRow.className = 'guarantees-table__row guarantees-table__row--empty';
                    errorRow.innerHTML = `<td colspan="8">${escapeHtml(strings.error || 'No se ha podido cargar la información de clientes.')}</td>`;
                    tbody.appendChild(errorRow);
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
        showEmptyDetail();
        loadPage(1, false);
    });
})();
