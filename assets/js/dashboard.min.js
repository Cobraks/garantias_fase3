(function(){
    function ready(fn){
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function formatDate(value){
        if (!value) {
            return '';
        }
        try {
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }
            return date.toLocaleString(undefined, {
                dateStyle: 'short',
                timeStyle: 'medium'
            });
        } catch (err) {
            console.debug('Failed to format date', value, err);
            return value;
        }
    }

    function __(text, fallback){
        if (window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function') {
            return window.wp.i18n.__(text, 'garantias-online-360vo');
        }
        return fallback || text;
    }

    function sprintf(text, fallback){
        if (window.wp && window.wp.i18n && typeof window.wp.i18n.sprintf === 'function') {
            const args = Array.prototype.slice.call(arguments, 2);
            return window.wp.i18n.sprintf.apply(null, [text].concat(args));
        }
        if (typeof fallback === 'string') {
            return fallback;
        }
        return text;
    }

    ready(function(){
        if (typeof go360Activity === 'undefined') {
            return;
        }

        const module = document.querySelector('[data-activity-module]');
        if (!module) {
            return;
        }

        const elements = {
            body: module.querySelector('[data-activity-body]'),
            loading: module.querySelector('[data-activity-loading]'),
            empty: module.querySelector('[data-activity-empty]'),
            search: module.querySelector('[data-activity-search]'),
            event: module.querySelector('[data-activity-event]'),
            categoryPills: module.querySelector('[data-activity-category-pills]'),
            level: module.querySelector('[data-activity-level]'),
            channel: module.querySelector('[data-activity-channel]'),
            vendor: module.querySelector('[data-activity-vendor]'),
            vendorWrapper: module.querySelector('[data-activity-vendor-wrapper]'),
            dateFrom: module.querySelector('[data-activity-date-from]'),
            dateTo: module.querySelector('[data-activity-date-to]'),
            perPage: module.querySelector('[data-activity-per-page]'),
            prev: module.querySelector('[data-activity-prev]'),
            next: module.querySelector('[data-activity-next]'),
            pageInfo: module.querySelector('[data-activity-pageinfo]'),
            refresh: module.querySelector('[data-activity-refresh]')
        };

        const state = {
            page: 1,
            perPage: parseInt(elements.perPage ? elements.perPage.value : 25, 10) || 25,
            totalPages: 1,
            total: 0,
            filters: {
                search: '',
                event_type: '',
                category: '',
                level: '',
                channel: '',
                vendor_id: '',
                date_from: '',
                date_to: ''
            },
            catalog: go360Activity.catalog || { event_types: [], categories: [], levels: [], channels: [], vendors: [] },
            debounceTimer: null
        };
        const supportsAbort = typeof AbortController !== 'undefined';
        let currentFetchController = null;

        function populateSelect(select, options, keepValue){
            if (!select || !Array.isArray(options)) {
                return;
            }
            const current = keepValue ? select.value : '';
            const placeholderOption = select.querySelector('option[value=""]');
            const placeholderText = select.getAttribute('data-placeholder') || (placeholderOption ? placeholderOption.textContent : __('Todos', 'Todos'));
            select.innerHTML = '';
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholderText;
            select.appendChild(opt);
            options.forEach(function(option){
                const opt = document.createElement('option');
                opt.value = option.value || '';
                opt.textContent = option.label || option.value;
                if (option.category && select === elements.event) {
                    opt.dataset.category = option.category;
                }
                opt.hidden = false;
                opt.disabled = false;
                select.appendChild(opt);
            });
            if (keepValue && current && select.querySelector('option[value="' + current + '"]')) {
                select.value = current;
            }
        }

        function renderCategoryPills(categories){
            if (!elements.categoryPills) {
                return;
            }
            const list = Array.isArray(categories) ? categories : [];
            elements.categoryPills.innerHTML = '';

            const allButton = document.createElement('button');
            allButton.type = 'button';
            allButton.className = 'activity__pill';
            allButton.dataset.categoryValue = '';
            allButton.textContent = __('Todos los eventos', 'Todos los eventos');
            allButton.setAttribute('aria-pressed', state.filters.category === '' ? 'true' : 'false');
            elements.categoryPills.appendChild(allButton);

            list.forEach(function(option){
                const value = option.value || '';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'activity__pill activity__pill--' + (value || 'all');
                button.dataset.categoryValue = value;
                button.textContent = option.label || value;
                button.setAttribute('aria-pressed', state.filters.category === value ? 'true' : 'false');
                elements.categoryPills.appendChild(button);
            });

            updateCategorySelection();
            filterEventOptionsByCategory();
        }

        function updateCategorySelection(){
            if (!elements.categoryPills) {
                return;
            }
            const current = state.filters.category || '';
            elements.categoryPills.querySelectorAll('[data-category-value]').forEach(function(button){
                const isActive = button.dataset.categoryValue === current;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            filterEventOptionsByCategory();
        }

        function filterEventOptionsByCategory(){
            if (!elements.event) {
                return;
            }
            const category = state.filters.category || '';
            let hasVisibleOption = category === '';

            elements.event.querySelectorAll('option').forEach(function(option){
                if (option.value === '') {
                    return;
                }
                const optionCategory = option.dataset.category || '';
                const matches = category === '' || optionCategory === category;
                option.hidden = !matches;
                option.disabled = !matches;
                if (matches) {
                    hasVisibleOption = true;
                }
            });

            if (!hasVisibleOption) {
                state.filters.event_type = '';
                elements.event.value = '';
            } else if (state.filters.event_type) {
                const selected = elements.event.querySelector('option[value="' + state.filters.event_type + '"]');
                if (!selected || selected.disabled) {
                    state.filters.event_type = '';
                    elements.event.value = '';
                }
            }
        }

        function syncCatalog(meta){
            if (!meta || typeof meta !== 'object') {
                return;
            }
            if (Array.isArray(meta.event_types)) {
                state.catalog.event_types = meta.event_types;
                populateSelect(elements.event, meta.event_types, true);
            }
            if (Array.isArray(meta.categories)) {
                state.catalog.categories = meta.categories;
                renderCategoryPills(state.catalog.categories);
            }
            if (Array.isArray(meta.levels)) {
                state.catalog.levels = meta.levels;
                populateSelect(elements.level, meta.levels, true);
            }
            if (Array.isArray(meta.channels)) {
                state.catalog.channels = meta.channels.map(function(value){
                    if (typeof value === 'object') {
                        return value;
                    }
                    return { value: value, label: value };
                });
                populateSelect(elements.channel, state.catalog.channels, true);
            }
            if (Array.isArray(meta.vendors)) {
                state.catalog.vendors = meta.vendors;
                populateSelect(elements.vendor, meta.vendors, true);
            }
        }

        function applyInitialCatalog(){
            populateSelect(elements.event, state.catalog.event_types, false);
            populateSelect(elements.level, state.catalog.levels, false);
            renderCategoryPills(state.catalog.categories);
            if (state.catalog.channels) {
                populateSelect(elements.channel, state.catalog.channels.map(function(value){
                    if (typeof value === 'object') {
                        return value;
                    }
                    return { value: value, label: value };
                }), false);
            }
            if (state.catalog.vendors) {
                populateSelect(elements.vendor, state.catalog.vendors, false);
            }
            if (elements.vendorWrapper && go360Activity.currentUser && !go360Activity.currentUser.canManage) {
                elements.vendorWrapper.style.display = 'none';
            }
        }

        function setLoading(isLoading){
            if (!elements.loading) {
                return;
            }
            elements.loading.hidden = !isLoading;
        }

        function setEmpty(isEmpty){
            if (!elements.empty) {
                return;
            }
            elements.empty.hidden = !isEmpty;
        }

        function updatePagination(){
            if (elements.pageInfo) {
                const page = state.page;
                const totalPages = state.totalPages || 1;
                const total = state.total || 0;
                const fallback = 'Página ' + page + ' de ' + totalPages + ' · ' + total + ' eventos';
                const template = __('Página %1$d de %2$d · %3$d eventos', 'Página %1$d de %2$d · %3$d eventos');
                elements.pageInfo.textContent = total > 0
                    ? sprintf(template, fallback, page, totalPages, total)
                    : __('Sin resultados', 'Sin resultados');
            }
            if (elements.prev) {
                elements.prev.disabled = state.page <= 1;
            }
            if (elements.next) {
                elements.next.disabled = state.page >= state.totalPages;
            }
        }

        function buildDocumentUrl(endpoint){
            if (!endpoint) {
                return '';
            }
            try {
                const url = new URL(endpoint, window.location.origin);
                if (go360Activity && go360Activity.nonce) {
                    url.searchParams.set('_wpnonce', go360Activity.nonce);
                }
                url.searchParams.set('download', '1');
                return url.toString();
            } catch (error) {
                console.debug('No se pudo construir la URL de documento', error);
                return endpoint;
            }
        }

        function buildActionsCell(item){
            const container = document.createElement('div');
            container.className = 'activity__actions';
            if (item.guarantee_id && go360Activity.guaranteeEditBase) {
                try {
                    const link = document.createElement('a');
                    const url = new URL(go360Activity.guaranteeEditBase);
                    url.searchParams.set('post', item.guarantee_id);
                    url.searchParams.set('action', 'edit');
                    link.href = url.toString();
                    link.textContent = __('Ver garantía', 'Ver garantía');
                    link.target = '_blank';
                    container.appendChild(link);
                } catch (error) {
                    console.debug('No se pudo construir la URL de edición', error);
                }
            }

            if (item.context && item.context.document_endpoint) {
                const docLink = document.createElement('a');
                docLink.href = buildDocumentUrl(item.context.document_endpoint);
                docLink.textContent = __('Descargar documento', 'Descargar documento');
                docLink.target = '_blank';
                container.appendChild(docLink);
            }

            if (item.context && Object.keys(item.context).length) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'activity-table__context-toggle';
                btn.dataset.activityContextToggle = 'true';
                btn.textContent = __('Detalles', 'Detalles');
                btn.setAttribute('aria-expanded', 'false');
                btn.setAttribute('data-context-id', String(item.id));
                container.appendChild(btn);
            }

            if (!container.childNodes.length) {
                container.textContent = '—';
            }

            return container;
        }

        function renderRows(items){
            if (!elements.body) {
                return;
            }
            elements.body.innerHTML = '';
            if (!Array.isArray(items) || !items.length) {
                setEmpty(true);
                return;
            }
            setEmpty(false);

            items.forEach(function(item){
                const row = document.createElement('tr');
                const context = item.context || {};

                const dateCell = document.createElement('td');
                dateCell.textContent = formatDate(item.created_iso || item.created_gmt || item.created_at);
                dateCell.className = 'activity-table__date';
                row.appendChild(dateCell);

                const typeCell = document.createElement('td');
                typeCell.className = 'activity-table__type';
                const categorySlug = (item.event_category || 'system').replace(/[^a-z0-9_-]/gi, '');
                const typeBadge = document.createElement('span');
                typeBadge.className = 'activity-table__type-badge activity-table__type-badge--' + (categorySlug || 'system');
                typeBadge.textContent = item.event_category_label || item.event_category || '—';
                typeCell.appendChild(typeBadge);
                row.appendChild(typeCell);

                const eventCell = document.createElement('td');
                eventCell.className = 'activity-table__event';
                const title = document.createElement('span');
                title.className = 'activity-table__event-title';
                title.textContent = item.event_label || item.event_type;
                eventCell.appendChild(title);
                row.appendChild(eventCell);

                const messageCell = document.createElement('td');
                messageCell.className = 'activity-table__description';
                let message = item.message || '';
                if (!message && context.legacy_details) {
                    message = context.legacy_details;
                }
                const messageText = document.createElement('span');
                messageText.className = 'activity-table__message';
                messageText.textContent = message !== '' ? message : '—';
                messageCell.appendChild(messageText);
                if (context && Object.keys(context).length) {
                    const debugContext = JSON.parse(JSON.stringify(context));
                    if (debugContext.document_endpoint) {
                        delete debugContext.document_endpoint;
                    }
                    const pre = document.createElement('pre');
                    pre.className = 'activity-table__context';
                    pre.textContent = JSON.stringify(debugContext, null, 2);
                    pre.setAttribute('data-context', String(item.id));
                    messageCell.appendChild(pre);
                }
                row.appendChild(messageCell);

                const userCell = document.createElement('td');
                userCell.className = 'activity__actor';
                const name = document.createElement('strong');
                name.textContent = (item.actor && item.actor.name) || '—';
                userCell.appendChild(name);
                if (item.actor && item.actor.email) {
                    const email = document.createElement('span');
                    email.textContent = item.actor.email;
                    userCell.appendChild(email);
                }
                if (item.actor && item.actor.role) {
                    const role = document.createElement('span');
                    role.textContent = item.actor.role;
                    userCell.appendChild(role);
                }
                row.appendChild(userCell);

                const entityCell = document.createElement('td');
                entityCell.className = 'activity-table__entity';
                let entity = '—';
                if (context.guarantee_label) {
                    entity = context.guarantee_label;
                } else if (item.guarantee_id) {
                    entity = '#' + item.guarantee_id;
                } else if (item.target && item.target.type) {
                    entity = item.target.type;
                }
                entityCell.textContent = entity;
                row.appendChild(entityCell);

                const channelCell = document.createElement('td');
                channelCell.className = 'activity-table__channel';
                const pieces = [];
                if (context.channel_label) {
                    pieces.push(context.channel_label);
                } else if (item.channel) {
                    pieces.push(item.channel);
                }
                if (context.vendor_name) {
                    pieces.push(context.vendor_name);
                } else if (item.vendor && item.vendor.name) {
                    pieces.push(item.vendor.name);
                }
                channelCell.textContent = pieces.length ? pieces.join(' · ') : '—';
                row.appendChild(channelCell);

                const actionsCell = document.createElement('td');
                actionsCell.appendChild(buildActionsCell(item));
                row.appendChild(actionsCell);

                elements.body.appendChild(row);
            });
        }

        async function fetchData(){
            const params = new URLSearchParams();
            params.set('page', String(state.page));
            params.set('per_page', String(state.perPage));
            Object.keys(state.filters).forEach(function(key){
                const value = state.filters[key];
                if (value) {
                    params.set(key, value);
                }
            });

            if (supportsAbort && currentFetchController) {
                currentFetchController.abort();
            }

            const controller = supportsAbort ? new AbortController() : null;
            const signal = controller ? controller.signal : undefined;
            if (supportsAbort) {
                currentFetchController = controller;
            }

            setEmpty(false);
            setLoading(true);
            try {
                const options = { headers: { 'X-WP-Nonce': go360Activity.nonce } };
                if (signal) {
                    options.signal = signal;
                }
                const response = await fetch(go360Activity.endpoint + '?' + params.toString(), options);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const payload = await response.json();
                const meta = payload.meta || {};
                state.totalPages = meta.total_pages || 1;
                state.total = meta.total || 0;
                renderRows(payload.data || []);
                syncCatalog(meta);
                updatePagination();
            } catch (err) {
                if (supportsAbort && err.name === 'AbortError') {
                    return;
                }
                console.error('Error cargando actividad', err);
                setEmpty(true);
            } finally {
                if (! supportsAbort) {
                    setLoading(false);
                    return;
                }
                if (! currentFetchController || currentFetchController === controller) {
                    currentFetchController = null;
                    setLoading(false);
                }
            }
        }

        function queueFetch(){
            if (state.debounceTimer) {
                clearTimeout(state.debounceTimer);
            }
            state.debounceTimer = setTimeout(fetchData, 80);
        }

        function handleFilterChange(){
            state.page = 1;
            queueFetch();
        }

        if (elements.search) {
            elements.search.addEventListener('input', function(){
                state.filters.search = elements.search.value.trim();
                handleFilterChange();
            });
        }

        if (elements.event) {
            elements.event.addEventListener('change', function(){
                state.filters.event_type = elements.event.value;
                handleFilterChange();
            });
        }

        if (elements.categoryPills) {
            elements.categoryPills.addEventListener('click', function(event){
                const button = event.target.closest('[data-category-value]');
                if (!button) {
                    return;
                }
                const value = button.dataset.categoryValue || '';
                if (state.filters.category === value) {
                    return;
                }
                state.filters.category = value;
                updateCategorySelection();
                if (elements.event) {
                    const selected = elements.event.querySelector('option[value="' + state.filters.event_type + '"]');
                    if (!selected || selected.disabled || (value && selected.dataset.category !== value)) {
                        state.filters.event_type = '';
                        elements.event.value = '';
                    }
                }
                handleFilterChange();
            });
        }

        if (elements.level) {
            elements.level.addEventListener('change', function(){
                state.filters.level = elements.level.value;
                handleFilterChange();
            });
        }

        if (elements.channel) {
            elements.channel.addEventListener('change', function(){
                state.filters.channel = elements.channel.value;
                handleFilterChange();
            });
        }

        if (elements.vendor) {
            elements.vendor.addEventListener('change', function(){
                state.filters.vendor_id = elements.vendor.value;
                handleFilterChange();
            });
        }

        if (elements.dateFrom) {
            elements.dateFrom.addEventListener('change', function(){
                state.filters.date_from = elements.dateFrom.value;
                handleFilterChange();
            });
        }

        if (elements.dateTo) {
            elements.dateTo.addEventListener('change', function(){
                state.filters.date_to = elements.dateTo.value;
                handleFilterChange();
            });
        }

        if (elements.perPage) {
            elements.perPage.addEventListener('change', function(){
                state.perPage = parseInt(elements.perPage.value, 10) || 25;
                state.page = 1;
                fetchData();
            });
        }

        if (elements.prev) {
            elements.prev.addEventListener('click', function(){
                if (state.page > 1) {
                    state.page -= 1;
                    fetchData();
                }
            });
        }

        if (elements.next) {
            elements.next.addEventListener('click', function(){
                if (state.page < state.totalPages) {
                    state.page += 1;
                    fetchData();
                }
            });
        }

        if (elements.refresh) {
            elements.refresh.addEventListener('click', function(){
                fetchData();
            });
        }

        module.addEventListener('click', function(event){
            const button = event.target.closest('[data-activity-context-toggle]');
            if (!button) {
                return;
            }
            const contextId = button.getAttribute('data-context-id');
            if (!contextId) {
                return;
            }
            const pre = module.querySelector('pre[data-context="' + contextId + '"]');
            if (!pre) {
                return;
            }
            const isVisible = pre.classList.toggle('is-visible');
            button.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
        });

        applyInitialCatalog();
        fetchData();
    });
})();
