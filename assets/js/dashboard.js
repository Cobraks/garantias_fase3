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
            dateFrom: module.querySelector('[data-activity-date-from]'),
            dateTo: module.querySelector('[data-activity-date-to]'),
            perPage: module.querySelector('[data-activity-per-page]'),
            prev: module.querySelector('[data-activity-prev]'),
            next: module.querySelector('[data-activity-next]'),
            pageInfo: module.querySelector('[data-activity-pageinfo]'),
            refresh: module.querySelector('[data-activity-refresh]'),
            table: module.querySelector('[data-activity-table]')
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
                date_from: '',
                date_to: ''
            },
            catalog: go360Activity.catalog || { event_types: [], categories: [] },
            debounceTimer: null
        };
        const supportsAbort = typeof AbortController !== 'undefined';
        let currentFetchController = null;

        function initResizableColumns(table){
            if (!table || table.dataset.resizableInitialized === 'true') {
                return;
            }
            if (window.innerWidth < 1024) {
                return;
            }
            const wrapper = table.closest('.activity__table-wrapper');
            if (!wrapper || !table.tHead) {
                return;
            }
            const colgroup = table.querySelector('colgroup');
            if (!colgroup) {
                return;
            }

            const cols = Array.from(colgroup.children);
            const headers = Array.from(table.tHead.rows[0].cells || []);
            if (!cols.length || !headers.length) {
                return;
            }

            const fallbackMin = 140;
            const fallbackMax = 480;
            const clamp = function(value, min, max){
                return Math.min(Math.max(value, min), max);
            };
            const parseOr = function(value, fallback){
                const parsed = parseInt(value || '', 10);
                return Number.isFinite(parsed) ? parsed : fallback;
            };

            wrapper.style.position = wrapper.style.position || 'relative';
            table.style.tableLayout = 'fixed';

            const columns = cols.map(function(col, index){
                const min = parseOr(col.dataset.minWidth, fallbackMin);
                const max = parseOr(col.dataset.maxWidth, fallbackMax);
                const def = parseOr(col.dataset.defaultWidth, 0);
                const headerWidth = headers[index] ? headers[index].getBoundingClientRect().width : def;
                const base = def || headerWidth || min;
                const width = clamp(base, min, max);
                col.style.width = width + 'px';
                return { min: min, max: max, width: width };
            });

            const overlay = document.createElement('div');
            overlay.className = 'activity-table__resizers';
            wrapper.appendChild(overlay);

            const handles = [];

            function updateOverlay(){
                overlay.style.width = table.offsetWidth + 'px';
                overlay.style.height = table.offsetHeight + 'px';
                overlay.style.top = table.offsetTop + 'px';
                overlay.style.left = table.offsetLeft + 'px';
                handles.forEach(function(handle, index){
                    const header = headers[index];
                    if (!header) {
                        return;
                    }
                    const left = header.offsetLeft + header.offsetWidth;
                    handle.style.left = (left - 4) + 'px';
                    handle.style.height = table.offsetHeight + 'px';
                });
            }

            function bindHandle(handle, index){
                handle.addEventListener('mousedown', function(event){
                    event.preventDefault();
                    const startX = event.pageX;
                    const current = columns[index];
                    const next = columns[index + 1];
                    if (!current || !next) {
                        return;
                    }
                    const startWidth = current.width;
                    const startNextWidth = next.width;
                    const total = startWidth + startNextWidth;

                    function onMove(ev){
                        const delta = ev.pageX - startX;
                        let newWidth = clamp(startWidth + delta, current.min, current.max);
                        let newNextWidth = total - newWidth;
                        if (newNextWidth < next.min) {
                            newNextWidth = next.min;
                            newWidth = total - newNextWidth;
                        }
                        if (newNextWidth > next.max) {
                            newNextWidth = next.max;
                            newWidth = total - newNextWidth;
                        }
                        newWidth = clamp(newWidth, current.min, current.max);
                        newNextWidth = clamp(newNextWidth, next.min, next.max);
                        columns[index].width = newWidth;
                        columns[index + 1].width = newNextWidth;
                        cols[index].style.width = newWidth + 'px';
                        cols[index + 1].style.width = newNextWidth + 'px';
                        updateOverlay();
                    }

                    function onUp(){
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                    }

                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
            }

            for (let i = 0; i < columns.length - 1; i += 1) {
                const handle = document.createElement('span');
                handle.className = 'activity-table__resizer';
                overlay.appendChild(handle);
                handles.push(handle);
                bindHandle(handle, i);
            }

            const body = table.tBodies[0];
            if (body) {
                const observer = new MutationObserver(function(){
                    updateOverlay();
                });
                observer.observe(body, { childList: true });
            }

            window.addEventListener('resize', updateOverlay);
            wrapper.addEventListener('scroll', updateOverlay, { passive: true });

            updateOverlay();
            table.__go360UpdateOverlay = updateOverlay;
            table.dataset.resizableInitialized = 'true';
        }

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
            if (!elements.event) {
                return;
            }
            if (elements.event.value !== current) {
                if (current === '') {
                    elements.event.value = '';
                } else if (elements.event.querySelector('option[value="' + current + '"]')) {
                    elements.event.value = current;
                } else {
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
            }
            if (Array.isArray(meta.categories)) {
                state.catalog.categories = meta.categories;
            }
            const options = Array.isArray(state.catalog.categories) && state.catalog.categories.length
                ? state.catalog.categories
                : state.catalog.event_types;
            populateSelect(elements.event, options, true);
            renderCategoryPills(state.catalog.categories);
        }

        function applyInitialCatalog(){
            const options = Array.isArray(state.catalog.categories) && state.catalog.categories.length
                ? state.catalog.categories
                : state.catalog.event_types;
            populateSelect(elements.event, options, false);
            renderCategoryPills(state.catalog.categories);
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
                container.classList.add('activity__actions--empty');
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
                const presentation = item.presentation || {};

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
                const title = document.createElement('strong');
                title.className = 'activity-table__event-title';
                title.textContent = presentation.title || item.event_label || item.event_type;
                title.title = title.textContent;
                eventCell.appendChild(title);

                const lines = Array.isArray(presentation.lines) ? presentation.lines : [];
                if (lines.length) {
                    const linesContainer = document.createElement('div');
                    linesContainer.className = 'activity-table__event-lines';
                    lines.forEach(function(line){
                        const lineEl = document.createElement('span');
                        lineEl.className = 'activity-table__event-line';
                        lineEl.textContent = line;
                        lineEl.title = line;
                        linesContainer.appendChild(lineEl);
                    });
                    eventCell.appendChild(linesContainer);
                }

                if (context && Object.keys(context).length) {
                    const debugContext = JSON.parse(JSON.stringify(context));
                    if (debugContext.document_endpoint) {
                        delete debugContext.document_endpoint;
                    }
                    const pre = document.createElement('pre');
                    pre.className = 'activity-table__context';
                    pre.textContent = JSON.stringify(debugContext, null, 2);
                    pre.setAttribute('data-context', String(item.id));
                    eventCell.appendChild(pre);
                }
                row.appendChild(eventCell);

                const userCell = document.createElement('td');
                userCell.className = 'activity-table__actor-cell';
                const actorWrapper = document.createElement('div');
                actorWrapper.className = 'activity__actor';
                const name = document.createElement('span');
                name.className = 'activity__actor-name';
                name.textContent = (item.actor && item.actor.name) || '—';
                name.title = name.textContent;
                actorWrapper.appendChild(name);
                if (item.actor && item.actor.email) {
                    const email = document.createElement('span');
                    email.className = 'activity__actor-email';
                    email.textContent = item.actor.email;
                    email.title = item.actor.email;
                    actorWrapper.appendChild(email);
                }
                userCell.appendChild(actorWrapper);
                row.appendChild(userCell);

                const actionsCell = document.createElement('td');
                actionsCell.className = 'activity-table__actions-cell';
                actionsCell.appendChild(buildActionsCell(item));
                row.appendChild(actionsCell);

                elements.body.appendChild(row);
            });

            if (elements.table && typeof elements.table.__go360UpdateOverlay === 'function') {
                requestAnimationFrame(elements.table.__go360UpdateOverlay);
            }
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
                state.filters.category = elements.event.value;
                state.filters.event_type = '';
                updateCategorySelection();
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
                state.filters.event_type = '';
                updateCategorySelection();
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
            if (elements.table && typeof elements.table.__go360UpdateOverlay === 'function') {
                requestAnimationFrame(elements.table.__go360UpdateOverlay);
            }
        });

        applyInitialCatalog();
        initResizableColumns(elements.table);
        window.addEventListener('resize', function(){
            initResizableColumns(elements.table);
        });
        fetchData();
    });
})();
