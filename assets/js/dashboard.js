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
            category: module.querySelector('[data-activity-category]'),
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
                select.appendChild(opt);
            });
            if (keepValue && current && select.querySelector('option[value="' + current + '"]')) {
                select.value = current;
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
                populateSelect(elements.category, meta.categories, true);
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
            populateSelect(elements.category, state.catalog.categories, false);
            populateSelect(elements.level, state.catalog.levels, false);
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
                elements.pageInfo.textContent = total > 0
                    ? sprintf(__('Página %1$d de %2$d · %3$d eventos', fallback), fallback, page, totalPages, total)
                    : __('Sin resultados', 'Sin resultados');
            }
            if (elements.prev) {
                elements.prev.disabled = state.page <= 1;
            }
            if (elements.next) {
                elements.next.disabled = state.page >= state.totalPages;
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

                const dateCell = document.createElement('td');
                dateCell.textContent = formatDate(item.created_iso || item.created_gmt || item.created_at);
                dateCell.className = 'activity-table__date';
                row.appendChild(dateCell);

                const eventCell = document.createElement('td');
                eventCell.className = 'activity-table__event';
                const badge = document.createElement('span');
                const levelClass = 'activity-table__badge--' + (item.level || 'info');
                badge.className = 'activity-table__badge ' + levelClass;
                badge.textContent = item.event_category_label || item.event_label || item.event_type;
                const title = document.createElement('span');
                title.className = 'activity-table__message';
                title.textContent = item.event_label || item.event_type;
                eventCell.appendChild(badge);
                eventCell.appendChild(title);
                row.appendChild(eventCell);

                const messageCell = document.createElement('td');
                messageCell.className = 'activity-table__message';
                const message = item.message || '';
                messageCell.textContent = message !== '' ? message : (item.context && item.context.legacy_details) || '—';
                if (item.context && Object.keys(item.context).length) {
                    const pre = document.createElement('pre');
                    pre.className = 'activity-table__context';
                    pre.textContent = JSON.stringify(item.context, null, 2);
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

                const targetCell = document.createElement('td');
                targetCell.textContent = item.guarantee_id ? ('#' + item.guarantee_id) : (item.target && item.target.type ? item.target.type : '—');
                row.appendChild(targetCell);

                const channelCell = document.createElement('td');
                const pieces = [];
                if (item.channel) {
                    pieces.push(item.channel);
                }
                if (item.vendor && item.vendor.name) {
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

            setLoading(true);
            try {
                const response = await fetch(go360Activity.endpoint + '?' + params.toString(), {
                    headers: { 'X-WP-Nonce': go360Activity.nonce }
                });
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
                console.error('Error cargando actividad', err);
                setEmpty(true);
            } finally {
                setLoading(false);
            }
        }

        function queueFetch(){
            if (state.debounceTimer) {
                clearTimeout(state.debounceTimer);
            }
            state.debounceTimer = setTimeout(fetchData, 300);
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

        if (elements.category) {
            elements.category.addEventListener('change', function(){
                state.filters.category = elements.category.value;
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
