(function () {
    const SELECTORS = {
        container: '[data-admin-notifications]',
        toggle: '[data-notifications-toggle]',
        panel: '[data-notifications-panel]',
        badge: '[data-notifications-badge]',
        list: '[data-notifications-list]',
        empty: '[data-notifications-empty]',
        markAll: '[data-notifications-mark-all]',
        loadMore: '[data-notifications-load-more]',
        viewAll: '[data-notifications-view-all]',
        scroll: '[data-notifications-scroll]',
        toast: '[data-notifications-toast]',
    };

    const STORAGE_KEYS = {
        snapshot: 'go360_notifications_snapshot',
        browserLast: 'go360_notifications_browser_last',
    };

    const SNAPSHOT_LIMIT = 4;

    const reduceMotionQuery = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;

    const prefersReducedMotion = () => Boolean(reduceMotionQuery && reduceMotionQuery.matches);

    const safeStorage = {
        get(key) {
            try {
                return window.localStorage.getItem(key);
            } catch (error) {
                return null;
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, value);
            } catch (error) {
                // noop
            }
        },
        remove(key) {
            try {
                window.localStorage.removeItem(key);
            } catch (error) {
                // noop
            }
        },
    };

    const formatDate = (iso) => {
        if (!iso) {
            return '';
        }
        try {
            const date = new Date(iso.replace(' ', 'T'));
            const formatter = new Intl.DateTimeFormat('es-ES', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            });
            return formatter.format(date);
        } catch (error) {
            return '';
        }
    };

    const decodeIcon = (encoded) => {
        if (!encoded || typeof encoded !== 'string') {
            return '';
        }
        try {
            return window.atob(encoded).trim();
        } catch (error) {
            return '';
        }
    };

    const parseUnread = (value) => {
        const parsed = Number(value);
        if (!Number.isFinite(parsed) || parsed < 0) {
            return 0;
        }
        return Math.floor(parsed);
    };

    const decodeHtmlEntities = (value) => {
        if (typeof value !== 'string' || value === '') {
            return '';
        }
        const textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        return textarea.value;
    };

    const toPlainText = (value) => {
        if (typeof value !== 'string' || value === '') {
            return '';
        }
        const wrapper = document.createElement('div');
        wrapper.innerHTML = value;
        const text = wrapper.textContent || wrapper.innerText || '';
        return decodeHtmlEntities(text);
    };

    const dedupeById = (items) => {
        if (!Array.isArray(items)) {
            return [];
        }
        const seen = new Set();
        return items.filter((item) => {
            const id = Number(item.id);
            if (seen.has(id)) {
                return false;
            }
            seen.add(id);
            return true;
        });
    };

    const isLoginActivity = (item) => {
        if (!item || typeof item !== 'object') {
            return false;
        }
        const slug = typeof item.icon_slug === 'string' ? item.icon_slug : '';
        return slug === 'login' || slug === 'logout';
    };

    const filterPanelItems = (items) => {
        if (!Array.isArray(items)) {
            return [];
        }
        return items.filter((item) => !isLoginActivity(item) && !item.is_read);
    };

    const createElement = (tag, className, content) => {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (typeof content === 'string' && content !== '') {
            element.textContent = content;
        } else if (content && typeof content === 'object') {
            if (typeof content.html === 'string' && content.html !== '') {
                element.innerHTML = content.html;
            } else if (typeof content.text === 'string' && content.text !== '') {
                element.textContent = content.text;
            }
        }
        return element;
    };

    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector(SELECTORS.container);
        if (!container) {
            return;
        }

        const panelHistory =
            typeof window !== 'undefined' && window.go360PanelHistory
                ? window.go360PanelHistory
                : null;
        const hasPanelHistory =
            panelHistory &&
            typeof panelHistory.push === 'function' &&
            typeof panelHistory.close === 'function';
        const desktopMediaQuery =
            typeof window !== 'undefined' && typeof window.matchMedia === 'function'
                ? window.matchMedia('(min-width: 1280px)')
                : null;

        const toggle = container.querySelector(SELECTORS.toggle);
        const panel = container.querySelector(SELECTORS.panel);
        const list = container.querySelector(SELECTORS.list);
        const badge = container.querySelector(SELECTORS.badge);
        const empty = container.querySelector(SELECTORS.empty);
        const markAllButton = container.querySelector(SELECTORS.markAll);
        const loadMoreButton = container.querySelector(SELECTORS.loadMore);
        const viewAllButton = container.querySelector(SELECTORS.viewAll);
        const scrollBox = container.querySelector(SELECTORS.scroll);
        const toast = container.querySelector(SELECTORS.toast);
        const topBar = container.closest('.top-bar');

        const config = window.go360Notifications || {};
        const endpoints = config.endpoints || {};
        const listEndpoint = endpoints.list || '';
        const markEndpoint = endpoints.markAll || '';
        const nonce = config.nonce || '';
        const perPage = config.perPage || 8;
        const pollInterval = Math.max(3000, Number(config.pollInterval || 6000));
        const toastDuration = Math.max(6000, Number(config.toastDuration || 8000));

        const userConfig = config.user || {};
        const userId = Number(userConfig.id || 0);
        const preferenceDefaults = typeof config.preferences === 'object' && config.preferences !== null
            ? config.preferences
            : {};

        const filtersConfig = typeof config.filters === 'object' && config.filters !== null
            ? config.filters
            : {};
        const allFilterLabel = typeof filtersConfig.allLabel === 'string' && filtersConfig.allLabel.trim() !== ''
            ? filtersConfig.allLabel.trim()
            : 'Todas';

        const normalizeCategoryEntry = (entry) => {
            if (!entry || typeof entry !== 'object') {
                return null;
            }
            const key = typeof entry.key === 'string' ? entry.key.trim() : '';
            const label = typeof entry.label === 'string' ? entry.label.trim() : '';
            if (key === '' || label === '') {
                return null;
            }
            const icons = Array.isArray(entry.icons)
                ? entry.icons
                    .map((icon) => (typeof icon === 'string' ? icon.trim() : ''))
                    .filter((icon) => icon !== '')
                : [];
            return { key, label, icons };
        };

        const categoryByKey = new Map();
        const categoryIconMap = new Map();
        const availableCategories = [];

        const registerCategory = (entry) => {
            if (!entry || categoryByKey.has(entry.key)) {
                return;
            }
            categoryByKey.set(entry.key, entry);
            availableCategories.push(entry);
        };

        const rawCategories = Array.isArray(filtersConfig.categories) ? filtersConfig.categories : [];
        rawCategories.forEach((entry) => {
            const normalized = normalizeCategoryEntry(entry);
            if (normalized) {
                registerCategory(normalized);
            }
        });

        if (availableCategories.length === 0) {
            [
                { key: 'login', label: 'Inicio de sesión', icons: ['login', 'logout'] },
                { key: 'payments', label: 'Pagos', icons: ['payment', 'sell', 'iban'] },
                { key: 'guarantees', label: 'Garantías', icons: ['new_shield'] },
                { key: 'registrations', label: 'Registros', icons: ['check_shield', 'person_add'] },
            ].forEach((fallback) => registerCategory(fallback));
        }

        availableCategories.forEach((entry) => {
            if (!Array.isArray(entry.icons)) {
                return;
            }
            entry.icons.forEach((icon) => {
                if (typeof icon === 'string' && icon !== '' && !categoryIconMap.has(icon)) {
                    categoryIconMap.set(icon, entry.key);
                }
            });
        });

        const categoryOptions = availableCategories.map((entry) => ({
            key: entry.key,
            label: entry.label,
        }));

        const resolveCategory = (iconSlug) => {
            const slug = typeof iconSlug === 'string' ? iconSlug : '';
            if (slug !== '' && categoryIconMap.has(slug)) {
                return categoryIconMap.get(slug);
            }
            return 'other';
        };

        const isNearBottom = (element, offset = 8) => {
            if (!element) {
                return false;
            }
            return element.scrollTop + element.clientHeight >= element.scrollHeight - offset;
        };

        const filterByCategory = (items, categoryKey) => {
            if (!Array.isArray(items)) {
                return [];
            }
            const key = typeof categoryKey === 'string' && categoryKey !== '' ? categoryKey : 'all';
            if (key === 'all') {
                return items;
            }
            return items.filter((item) => item.category === key);
        };

        const getModalCategoryState = (categoryKey) => {
            const key = typeof categoryKey === 'string' && categoryKey !== '' ? categoryKey : 'all';
            if (key === 'all') {
                return null;
            }
            if (!state.categoryData.has(key)) {
                state.categoryData.set(key, {
                    items: [],
                    page: 0,
                    hasMore: true,
                    loading: false,
                    loadingMore: false,
                });
            }
            return state.categoryData.get(key);
        };

        const getModalDataset = () => {
            const category = state.modalCategory || 'all';
            if (category === 'all') {
                return {
                    items: filterByCategory(state.items, 'all'),
                    hasMore: state.hasMore,
                    loadingMore: state.loadingMore,
                };
            }
            const categoryState = getModalCategoryState(category);
            if (!categoryState) {
                return { items: [], hasMore: false, loadingMore: false };
            }
            return {
                items: Array.isArray(categoryState.items) ? categoryState.items : [],
                hasMore: Boolean(categoryState.hasMore),
                loadingMore: Boolean(categoryState.loadingMore),
            };
        };

        const canUseModalHistory = () =>
            hasPanelHistory && (!desktopMediaQuery || !desktopMediaQuery.matches);

        const registerModalHistory = () => {
            if (!canUseModalHistory() || state.modalHistoryRegistered) {
                return;
            }
            state.modalHistoryRegistered = true;
            panelHistory.push('notifications-modal', () => {
                closeModal({ silentHistory: true });
            });
        };

        const releaseModalHistory = (silent = false) => {
            if (!state.modalHistoryRegistered) {
                return;
            }
            if (canUseModalHistory() && !silent) {
                panelHistory.close('notifications-modal');
            }
            state.modalHistoryRegistered = false;
        };

        const getPreferenceKey = (type) => {
            const suffix = Number.isFinite(userId) && userId > 0 ? `_${userId}` : '';
            return `go360_notifications_pref_${type}${suffix}`;
        };

        const readStoredPreference = (type, fallback) => {
            const key = getPreferenceKey(type);
            const raw = safeStorage.get(key);
            if (raw === null || typeof raw === 'undefined') {
                return fallback;
            }
            if (raw === '1' || raw === 'true') {
                return true;
            }
            if (raw === '0' || raw === 'false') {
                return false;
            }
            return fallback;
        };

        const persistPreference = (type, value) => {
            const key = getPreferenceKey(type);
            if (!key) {
                return;
            }
            const normalized = value ? '1' : '0';
            if (safeStorage.get(key) === normalized) {
                return;
            }
            safeStorage.set(key, normalized);
        };

        const defaultToastPreference = typeof preferenceDefaults.toast === 'boolean'
            ? preferenceDefaults.toast
            : true;
        const defaultSoundPreference = typeof preferenceDefaults.sound === 'boolean'
            ? preferenceDefaults.sound
            : true;

        const toastSoundSrc = container.dataset.toastSound || '';
        let toastAudio = null;
        let toastHideHandler = null;
        let toastPositionListenersBound = false;
        let toastHideFallback = null;

        if (!toggle || !panel || !list || !listEndpoint) {
            return;
        }

        if (loadMoreButton) {
            loadMoreButton.hidden = true;
        }

        const markIcon = decodeIcon(container.dataset.iconMark || '');
        const markIconRead = decodeIcon(container.dataset.iconMarkRead || '');
        const deleteIcon = decodeIcon(container.dataset.iconDelete || '');
        const closeIcon = decodeIcon(container.dataset.iconClose || '');
        const markLabel = container.dataset.markLabel || 'Sin leer';
        const markedLabel = container.dataset.markedLabel || 'Leída';
        const deleteLabel = container.dataset.deleteLabel || 'Eliminar';
        const loadMoreLabel = container.dataset.loadMoreLabel || 'Cargar más';
        const loadingLabel = container.dataset.loadingLabel || 'Cargando…';
        const viewAllLabel = container.dataset.viewAllLabel || 'Ver todas las notificaciones';
        const toastDismissLabel = container.dataset.toastDismissLabel || 'Descartar';
        const modalPlaceholder = container.dataset.modalPlaceholder || '';
        const modalCloseLabel = container.dataset.modalCloseLabel || 'Cerrar';
        const browserIcon = container.dataset.browserIcon || '';
        const browserNotificationsSupported = typeof window.Notification !== 'undefined';

        const state = {
            items: [],
            page: 1,
            hasMore: false,
            isOpen: false,
            isLoading: false,
            loadingMore: false,
            pollTimer: null,
            knownIds: new Set(),
            initialized: false,
            unread: 0,
            panelUnread: 0,
            toastTimer: null,
            modal: null,
            modalElements: null,
            modalOpen: false,
            latestId: 0,
            isPolling: false,
            toastEnabled: defaultToastPreference,
            toastSoundEnabled: defaultSoundPreference,
            currentToastId: null,
            lastToastId: null,
            toastHistory: new Set(),
            markAllInFlight: false,
            browserHistory: new Set(),
            browserPermission: browserNotificationsSupported ? window.Notification.permission : 'denied',
            requestingBrowserPermission: false,
            lastBrowserNotified: Number(safeStorage.get(STORAGE_KEYS.browserLast) || '0'),
            pendingMarks: new Map(),
            filters: {
                categories: categoryOptions,
                allLabel: allFilterLabel,
            },
            modalCategory: 'all',
            modalReachedEnd: false,
            modalFilteredCount: 0,
            categoryData: new Map(),
            modalHistoryRegistered: false,
        };

        state.userId = Number.isFinite(userId) ? userId : 0;
        state.toastEnabled = readStoredPreference('toast', state.toastEnabled);
        state.toastSoundEnabled = readStoredPreference('sound', state.toastSoundEnabled);
        persistPreference('toast', state.toastEnabled);
        persistPreference('sound', state.toastSoundEnabled);

        if (Number.isFinite(state.lastBrowserNotified) && state.lastBrowserNotified > 0) {
            state.browserHistory.add(state.lastBrowserNotified);
        }

        const persistSnapshot = () => {
            const panelItems = filterPanelItems(state.items);
            const panelSnapshot = panelItems.slice(0, SNAPSHOT_LIMIT).map((item) => ({
                id: item.id,
                title: item.title,
                body: item.body,
                icon: item.icon,
                icon_svg: item.icon_svg,
                tone: item.tone,
                badge: item.badge,
                link: item.link,
                meta: item.meta,
                is_read: item.is_read,
                created_at: item.created_at,
                actions: item.actions,
                icon_slug: item.icon_slug,
            }));

            const modalSnapshot = state.items.slice(0, SNAPSHOT_LIMIT).map((item) => ({
                id: item.id,
                title: item.title,
                body: item.body,
                icon: item.icon,
                icon_svg: item.icon_svg,
                tone: item.tone,
                badge: item.badge,
                link: item.link,
                meta: item.meta,
                is_read: item.is_read,
                created_at: item.created_at,
                actions: item.actions,
                icon_slug: item.icon_slug,
            }));

            const payload = {
                unread: parseUnread(state.panelUnread),
                total_unread: parseUnread(state.unread),
                items: panelSnapshot,
            };

            if (modalSnapshot.length > 0) {
                payload.modal_items = modalSnapshot;
            }

            safeStorage.set(STORAGE_KEYS.snapshot, JSON.stringify(payload));
        };

        const hydrateSnapshot = () => {
            const raw = safeStorage.get(STORAGE_KEYS.snapshot);
            if (!raw) {
                return;
            }

            try {
                const snapshot = JSON.parse(raw);
                if (snapshot) {
                    if (typeof snapshot.total_unread === 'number' || typeof snapshot.total_unread === 'string') {
                        state.unread = parseUnread(snapshot.total_unread);
                    }

                    if (typeof snapshot.unread === 'number' || typeof snapshot.unread === 'string') {
                        const cachedPanelUnread = parseUnread(snapshot.unread);
                        state.panelUnread = cachedPanelUnread;
                        setBadge(cachedPanelUnread);
                    }
                }

                const snapshotModalItems = Array.isArray(snapshot?.modal_items) && snapshot.modal_items.length > 0
                    ? snapshot.modal_items
                    : snapshot?.items;

                if (Array.isArray(snapshotModalItems) && snapshotModalItems.length > 0) {
                    const items = normalizeItems(snapshotModalItems).slice(0, perPage);
                    state.items = items;
                    items.forEach((item) => {
                        state.knownIds.add(item.id);
                    });
                    synchronizeInterface({
                        persist: false,
                        preservePanelScroll: false,
                        preserveModalScroll: false,
                    });
                }
            } catch (error) {
                safeStorage.remove(STORAGE_KEYS.snapshot);
            }
        };

        const setMarkButtonState = (button, isRead) => {
            if (!button) {
                return;
            }

            const markText = button.querySelector('.notifications-panel__action-text');
            if (markText) {
                markText.textContent = isRead ? markedLabel : markLabel;
            }

            const existingIcon = button.querySelector('.notifications-panel__action-icon');
            if (existingIcon) {
                existingIcon.remove();
            }

            const iconMarkup = isRead ? markIconRead : markIcon;
            if (iconMarkup) {
                button.insertAdjacentHTML('afterbegin', iconMarkup);
            }

            if (isRead) {
                button.classList.add('is-disabled');
                button.setAttribute('aria-disabled', 'true');
            } else {
                button.classList.remove('is-disabled');
                button.setAttribute('aria-disabled', 'false');
            }
        };

        const markItemAsRead = (itemElement) => {
            if (!itemElement) {
                return;
            }
            itemElement.classList.remove('notifications-panel__item--unread');
            itemElement.classList.add('notifications-panel__item--read');
            const badge = itemElement.querySelector('.notifications-panel__badge');
            if (badge) {
                badge.remove();
            }
        };

        const updateLatestId = (items, metaLatest) => {
            let candidate = state.latestId;

            if (Array.isArray(items) && items.length > 0) {
                const ids = items
                    .map((item) => Number(item.id) || 0)
                    .filter((id) => Number.isFinite(id) && id > 0);
                if (ids.length) {
                    const maxId = Math.max(...ids);
                    candidate = Math.max(candidate, maxId);
                }
            }

            if (typeof metaLatest === 'number' && Number.isFinite(metaLatest) && metaLatest > 0) {
                candidate = Math.max(candidate, metaLatest);
            }

            state.latestId = candidate;
        };

        const normalizeItems = (items) => {
            if (!Array.isArray(items)) {
                return [];
            }
            return items.map((item) => ({
                id: Number(item.id),
                title: typeof item.title === 'string' ? item.title : '',
                body: typeof item.body === 'string' ? decodeHtmlEntities(item.body) : '',
                icon: typeof item.icon === 'string' ? item.icon : '',
                icon_svg: typeof item.icon_svg === 'string' ? item.icon_svg : '',
                icon_slug: typeof item.icon_slug === 'string' ? item.icon_slug : '',
                category: resolveCategory(item.icon_slug),
                tone: typeof item.tone === 'string' ? item.tone : '',
                badge: typeof item.badge === 'string' ? item.badge : '',
                link: typeof item.link === 'string' ? item.link : '',
                meta: Array.isArray(item.meta) ? item.meta : [],
                is_read: Boolean(item.is_read),
                created_at: typeof item.created_at === 'string' ? item.created_at : '',
                actions: Array.isArray(item.actions) ? item.actions : [],
            }));
        };

        const setBadge = (count) => {
            if (!badge) {
                return;
            }
            const safeCount = parseUnread(count);
            if (safeCount > 0) {
                badge.hidden = false;
                badge.textContent = String(safeCount);
            } else {
                badge.hidden = true;
                badge.textContent = '';
            }
        };

        const updateEmptyState = () => {
            if (empty) {
                const panelItems = filterPanelItems(state.items);
                empty.hidden = panelItems.length > 0;
            }
            if (state.modalElements && state.modalElements.empty) {
                state.modalElements.empty.hidden = state.modalFilteredCount > 0;
            }
        };

        const applyLoadMoreState = (button, { context = 'panel', wrapper = null, emptyIndicator = null } = {}) => {
            if (!button) {
                return;
            }

            const panelItems = filterPanelItems(state.items);
            const hasPanelItems = panelItems.length > 0;
            const isPanelEmpty = context === 'panel'
                && emptyIndicator
                && emptyIndicator.hidden === false;
            const totalUnread = Number.isFinite(state.unread) && state.unread >= 0
                ? state.unread
                : state.panelUnread;
            const hasPanelOverflow = state.hasMore
                && hasPanelItems
                && !isPanelEmpty
                && totalUnread > perPage;
            const modalData = getModalDataset();
            const hasModalOverflow = modalData.hasMore
                && Array.isArray(modalData.items)
                && modalData.items.length > 0;
            const shouldShowButton = context === 'panel'
                ? hasPanelOverflow
                : hasModalOverflow && state.modalReachedEnd;
            const loadingMoreState = context === 'panel'
                ? state.loadingMore
                : modalData.loadingMore;

            if (!shouldShowButton) {
                if (wrapper) {
                    wrapper.hidden = true;
                }
                button.hidden = true;
                button.style.display = 'none';
                button.disabled = false;
                button.classList.remove('is-loading');
                button.textContent = loadMoreLabel;
                return;
            }

            if (wrapper) {
                wrapper.hidden = false;
            }
            button.hidden = false;
            button.style.display = '';
            button.disabled = Boolean(loadingMoreState);
            if (loadingMoreState) {
                button.classList.add('is-loading');
                button.innerHTML = `<span class="notifications-panel__load-more-spinner" aria-hidden="true"></span><span class="notifications-panel__load-more-text">${loadingLabel}</span>`;
            } else {
                button.classList.remove('is-loading');
                button.textContent = loadMoreLabel;
            }
        };

        const updateLoadMore = () => {
            applyLoadMoreState(loadMoreButton, { context: 'panel', emptyIndicator: empty });
            if (state.modalElements && state.modalElements.loadMore) {
                applyLoadMoreState(state.modalElements.loadMore, {
                    context: 'modal',
                    wrapper: state.modalElements.loadMoreWrapper || null,
                    emptyIndicator: state.modalElements.empty || null,
                });
            }
        };

        const setToggleToastState = (active) => {
            if (!toggle) {
                return;
            }
            toggle.classList.toggle('is-toasting', Boolean(active));
        };

        const updateToastPosition = () => {
            if (!toast || toast.hidden) {
                return;
            }
            const anchor = topBar || toggle || container;
            if (!anchor || typeof anchor.getBoundingClientRect !== 'function') {
                return;
            }
            const rect = anchor.getBoundingClientRect();
            const horizontalPadding = 24;
            const verticalOffset = 12;
            const rightOffset = Math.max(horizontalPadding, window.innerWidth - rect.right + horizontalPadding);
            const topOffset = Math.max(verticalOffset, rect.bottom + verticalOffset);
            toast.style.right = `${Math.round(rightOffset)}px`;
            toast.style.top = `${Math.round(topOffset)}px`;
        };

        const bindToastPositionListeners = () => {
            if (toastPositionListenersBound) {
                return;
            }
            toastPositionListenersBound = true;
            window.addEventListener('resize', updateToastPosition);
            window.addEventListener('scroll', updateToastPosition, { passive: true });
        };

        const unbindToastPositionListeners = () => {
            if (!toastPositionListenersBound) {
                return;
            }
            toastPositionListenersBound = false;
            window.removeEventListener('resize', updateToastPosition);
            window.removeEventListener('scroll', updateToastPosition);
        };

        const hideToast = (options = {}) => {
            if (!toast) {
                return;
            }

            const { immediate = false } = options;

            if (state.toastTimer) {
                window.clearTimeout(state.toastTimer);
                state.toastTimer = null;
            }

            const finalize = () => {
                if (!toast) {
                    return;
                }
                if (toastHideFallback) {
                    window.clearTimeout(toastHideFallback);
                    toastHideFallback = null;
                }
                if (toastHideHandler) {
                    toast.removeEventListener('animationend', toastHideHandler);
                    toastHideHandler = null;
                }
                toast.hidden = true;
                toast.classList.remove('notifications-toast--visible', 'notifications-toast--hiding');
                toast.innerHTML = '';
                delete toast.dataset.notificationLink;
                delete toast.dataset.notificationId;
                toast.style.top = '';
                toast.style.right = '';
                setToggleToastState(false);
                unbindToastPositionListeners();
                state.currentToastId = null;
            };

            if (
                immediate
                || toast.hidden
                || !toast.classList.contains('notifications-toast--visible')
                || prefersReducedMotion()
            ) {
                finalize();
                return;
            }

            if (toast.classList.contains('notifications-toast--hiding')) {
                return;
            }

            toast.classList.add('notifications-toast--hiding');

            if (toastHideHandler) {
                toast.removeEventListener('animationend', toastHideHandler);
                toastHideHandler = null;
            }

            if (toastHideFallback) {
                window.clearTimeout(toastHideFallback);
                toastHideFallback = null;
            }

            toastHideHandler = (event) => {
                if (event && event.target !== toast) {
                    return;
                }
                finalize();
            };

            toast.addEventListener('animationend', toastHideHandler);

            toastHideFallback = window.setTimeout(() => {
                toastHideFallback = null;
                finalize();
            }, 450);
        };

        const markBrowserNotified = (id) => {
            if (!Number.isFinite(id) || id <= 0) {
                return;
            }
            state.browserHistory.add(id);
            state.lastBrowserNotified = id;
            safeStorage.set(STORAGE_KEYS.browserLast, String(id));
        };

        const requestBrowserPermission = async () => {
            if (!browserNotificationsSupported) {
                return 'denied';
            }

            if (state.browserPermission === 'granted' || state.browserPermission === 'denied') {
                return state.browserPermission;
            }

            if (state.requestingBrowserPermission) {
                return state.browserPermission;
            }

            state.requestingBrowserPermission = true;

            try {
                const result = await window.Notification.requestPermission();
                state.browserPermission = result;
                return result;
            } catch (error) {
                state.browserPermission = 'denied';
                return 'denied';
            } finally {
                state.requestingBrowserPermission = false;
            }
        };

        const maybeShowBrowserNotification = (item) => {
            if (!browserNotificationsSupported || !item) {
                return;
            }

            const id = Number(item.id);
            if (!Number.isFinite(id) || id <= 0) {
                return;
            }

            if (state.browserHistory.has(id)) {
                return;
            }

            const storedId = Number(safeStorage.get(STORAGE_KEYS.browserLast) || '0');
            if (Number.isFinite(storedId) && storedId === id) {
                state.browserHistory.add(id);
                state.lastBrowserNotified = id;
                return;
            }

            const dispatch = () => {
                markBrowserNotified(id);

                const body = toPlainText(item.body);

                try {
                    const notification = new window.Notification(item.title || document.title || 'Notificación', {
                        body,
                        icon: browserIcon || undefined,
                        tag: `go360-notification-${id}`,
                        renotify: false,
                    });

                    notification.onclick = (event) => {
                        event.preventDefault();
                        window.focus();
                        if (item.link) {
                            window.open(item.link, '_blank', 'noopener');
                        }
                        try {
                            notification.close();
                        } catch (closeError) {
                            // noop
                        }
                    };
                } catch (error) {
                    // noop
                }
            };

            if (state.browserPermission === 'granted') {
                dispatch();
                return;
            }

            if (state.browserPermission === 'default') {
                requestBrowserPermission()
                    .then((permission) => {
                        if (permission === 'granted') {
                            dispatch();
                        }
                    })
                    .catch(() => {
                        // noop
                    });
            }
        };

        const playToastSound = () => {
            if (!toastSoundSrc || document.hidden || !state.toastSoundEnabled) {
                return;
            }
            if (!toastAudio) {
                toastAudio = new Audio(toastSoundSrc);
            }
            try {
                toastAudio.pause();
                toastAudio.currentTime = 0;
            } catch (error) {
                // noop
            }
            const playPromise = toastAudio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {
                    // noop
                });
            }
        };

        const showToast = (item) => {
            if (!toast || !item) {
                return;
            }

            if (state.toastHistory.has(item.id)) {
                return;
            }

            state.lastToastId = item.id;

            if (document.hidden) {
                state.toastHistory.add(item.id);
                return;
            }

            const currentId = Number(toast.dataset.notificationId || '0');
            if (!toast.hidden && currentId === item.id) {
                return;
            }

            hideToast({ immediate: true });

            state.currentToastId = item.id;
            state.toastHistory.add(item.id);

            const card = createElement('div', 'notifications-toast__inner');

            const closeButton = createElement('button', 'notifications-toast__close');
            closeButton.type = 'button';
            closeButton.setAttribute('aria-label', toastDismissLabel);
            closeButton.dataset.toastClose = 'true';
            if (closeIcon) {
                closeButton.innerHTML = closeIcon.replace('notifications-modal__close-icon', 'notifications-toast__close-icon');
            } else {
                closeButton.textContent = '×';
            }
            card.appendChild(closeButton);

            const iconWrapper = createElement('div', 'notifications-toast__icon');
            if (item.icon_svg) {
                iconWrapper.innerHTML = item.icon_svg;
            }
            card.appendChild(iconWrapper);

            const content = createElement('div', 'notifications-toast__content');
            const title = createElement('p', 'notifications-toast__title', item.title);
            content.appendChild(title);
            if (item.body) {
                const description = createElement('p', 'notifications-toast__description', { html: item.body });
                content.appendChild(description);
            }

            card.appendChild(content);

            toast.dataset.notificationId = String(item.id);
            if (item.link) {
                toast.dataset.notificationLink = item.link;
            } else {
                delete toast.dataset.notificationLink;
            }
            toast.appendChild(card);
            toast.hidden = false;
            toast.classList.remove('notifications-toast--hiding');
            toast.classList.add('notifications-toast--visible');
            setToggleToastState(true);
            updateToastPosition();
            window.requestAnimationFrame(updateToastPosition);
            bindToastPositionListeners();

            playToastSound();

            state.toastTimer = window.setTimeout(() => {
                hideToast();
            }, toastDuration);
        };

        const ensureModal = () => {
            if (state.modal) {
                return state.modal;
            }

            const modal = createElement('div', 'notifications-modal');
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');

            const overlay = createElement('div', 'notifications-modal__overlay');
            overlay.dataset.modalClose = 'true';
            modal.appendChild(overlay);

            const dialog = createElement('div', 'notifications-modal__dialog');
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'notifications-modal-title');

            const header = createElement('div', 'notifications-modal__header');
            const modalTitle = createElement('h2', 'notifications-modal__title', viewAllLabel);
            modalTitle.id = 'notifications-modal-title';
            header.appendChild(modalTitle);

            const closeButton = createElement('button', 'notifications-modal__close');
            closeButton.type = 'button';
            closeButton.setAttribute('aria-label', modalCloseLabel);
            closeButton.dataset.modalClose = 'true';
            if (closeIcon) {
                closeButton.innerHTML = closeIcon;
            } else {
                closeButton.textContent = '×';
            }
            closeButton.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                closeModal();
            });
            header.appendChild(closeButton);

            dialog.appendChild(header);

            const body = createElement('div', 'notifications-modal__body');
            const content = createElement('div', 'notifications-modal__content');

            const controls = createElement('div', 'notifications-modal__controls');
            let modalFilter = null;

            if (Array.isArray(state.filters.categories) && state.filters.categories.length > 0) {
                controls.classList.add('notifications-modal__controls--with-filter');
                modalFilter = document.createElement('select');
                modalFilter.className = 'notifications-modal__filter';

                const appendOption = (value, label) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    modalFilter.appendChild(option);
                };

                appendOption('all', state.filters.allLabel || 'Todas');
                state.filters.categories.forEach((category) => {
                    appendOption(category.key, category.label);
                });

                modalFilter.value = state.modalCategory || 'all';
                modalFilter.addEventListener('change', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLSelectElement)) {
                        return;
                    }
                    const selected = target.value || 'all';
                    if (selected === state.modalCategory) {
                        return;
                    }
                    state.modalCategory = selected;
                    state.modalFilteredCount = 0;
                    state.modalReachedEnd = false;
                    if (selected !== 'all') {
                        const categoryState = getModalCategoryState(selected);
                        if (categoryState) {
                            categoryState.items = [];
                            categoryState.page = 0;
                            categoryState.hasMore = true;
                            categoryState.loading = false;
                            categoryState.loadingMore = false;
                        }
                    }
                    if (state.modalElements && state.modalElements.scroll) {
                        state.modalElements.scroll.scrollTop = 0;
                    }
                    synchronizeInterface({
                        persist: false,
                        preservePanelScroll: state.isOpen,
                        preserveModalScroll: false,
                    });
                    updateLoadMore();
                    if (selected !== 'all') {
                        fetchNotifications({
                            append: false,
                            background: false,
                            category: selected,
                        });
                    }
                });

                controls.appendChild(modalFilter);
            } else {
                controls.classList.add('notifications-modal__controls--solo');
            }

            const modalMarkAll = createElement('button', 'notifications-panel__mark notifications-modal__mark');
            modalMarkAll.type = 'button';
            modalMarkAll.textContent = markAllButton ? markAllButton.textContent.trim() : 'Marcar todo como leído';
            controls.appendChild(modalMarkAll);
            content.appendChild(controls);

            const scrollArea = createElement('div', 'notifications-modal__scroll');
            const emptyMessage = empty ? empty.textContent : 'No tienes notificaciones nuevas.';
            const modalEmpty = createElement('p', 'notifications-panel__empty', emptyMessage);
            modalEmpty.hidden = true;
            scrollArea.appendChild(modalEmpty);

            const modalList = createElement('ul', 'notifications-panel__list');
            scrollArea.appendChild(modalList);

            const modalFooter = createElement('div', 'notifications-modal__footer');
            modalFooter.hidden = true;

            const modalLoadMore = createElement('button', 'notifications-panel__load-more notifications-modal__load-more');
            modalLoadMore.type = 'button';
            modalLoadMore.textContent = loadMoreLabel;
            modalFooter.appendChild(modalLoadMore);
            scrollArea.appendChild(modalFooter);

            content.appendChild(scrollArea);
            body.appendChild(content);
            dialog.appendChild(body);

            modal.appendChild(dialog);
            document.body.appendChild(modal);

            state.modal = modal;
            state.modalElements = {
                list: modalList,
                empty: modalEmpty,
                loadMore: modalLoadMore,
                loadMoreWrapper: modalFooter,
                markAll: modalMarkAll,
                scroll: scrollArea,
                filter: modalFilter,
            };

            scrollArea.addEventListener('scroll', () => {
                if (!state.modalElements || state.modalElements.scroll !== scrollArea) {
                    return;
                }
                const reachedEnd = isNearBottom(scrollArea, 8);
                if (state.modalReachedEnd !== reachedEnd) {
                    state.modalReachedEnd = reachedEnd;
                    updateLoadMore();
                }
            }, { passive: true });

            if (modalFilter) {
                modalFilter.value = state.modalCategory || 'all';
            }

            attachListEvents(modalList, 'modal');

            modalMarkAll.addEventListener('click', () => {
                markAll();
            });

            modalLoadMore.addEventListener('click', () => {
                const modalData = getModalDataset();
                if (modalData.loadingMore || !modalData.hasMore) {
                    return;
                }
                fetchNotifications({
                    append: true,
                    background: state.isOpen === false && state.modalOpen === false,
                    category: state.modalCategory || 'all',
                });
            });

            modal.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }
                const closeTrigger = target.closest('[data-modal-close="true"]');
                if (closeTrigger) {
                    event.preventDefault();
                    event.stopPropagation();
                    closeModal();
                }
            });

            return modal;
        };

        const openModal = () => {
            const modal = ensureModal();
            hideToast();
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('notifications-modal--visible');
            state.modalOpen = true;
            state.modalReachedEnd = false;
            if (state.modalElements && state.modalElements.filter) {
                state.modalElements.filter.value = state.modalCategory || 'all';
            }
            synchronizeInterface({
                persist: false,
                preservePanelScroll: state.isOpen,
                preserveModalScroll: false,
            });
            registerModalHistory();
        };

        function closeModal(options = {}) {
            if (!state.modal) {
                return;
            }
            const silentHistory = Boolean(options.silentHistory);
            state.modal.hidden = true;
            state.modal.setAttribute('aria-hidden', 'true');
            state.modal.classList.remove('notifications-modal--visible');
            state.modalOpen = false;
            state.modalReachedEnd = false;
            releaseModalHistory(silentHistory);
        }

        const buildMetaLine = (entry) => {
            if (!entry || typeof entry.text !== 'string' || entry.text === '') {
                return null;
            }
            const line = createElement('p', 'notifications-panel__meta-line');
            if (entry.label) {
                const label = createElement('span', 'notifications-panel__meta-label', `${entry.label}:`);
                line.appendChild(label);
                line.appendChild(document.createTextNode(` ${entry.text}`));
            } else {
                line.textContent = entry.text;
            }
            return line;
        };

        const buildNotification = (item) => {
            const li = createElement('li', 'notifications-panel__item');
            li.dataset.notificationId = String(item.id);
            if (typeof item.category === 'string' && item.category !== '') {
                li.dataset.notificationCategory = item.category;
            }

            const tone = item.tone || 'info';
            if (tone) {
                li.classList.add(`notifications-panel__item--tone-${tone}`);
            }
            if (!item.is_read) {
                li.classList.add('notifications-panel__item--unread');
            } else {
                li.classList.add('notifications-panel__item--read');
            }

            const formattedTime = formatDate(item.created_at);

            const headerBlock = createElement('div', 'notifications-panel__header-block');
            const titleRow = createElement('div', 'notifications-panel__title-row');
            const titleMain = createElement('div', 'notifications-panel__title-main');
            const iconWrapper = createElement('span', 'notifications-panel__icon');
            if (item.icon_svg) {
                iconWrapper.innerHTML = item.icon_svg;
            }
            iconWrapper.setAttribute('aria-hidden', 'true');
            titleMain.appendChild(iconWrapper);

            const titleContent = createElement('div', 'notifications-panel__title-content');
            titleContent.appendChild(createElement('p', 'notifications-panel__title-text', item.title));

            if (formattedTime) {
                const time = createElement('time', 'notifications-panel__time', formattedTime);
                if (item.created_at) {
                    time.dateTime = item.created_at;
                }
                titleContent.appendChild(time);
            }

            titleMain.appendChild(titleContent);
            titleRow.appendChild(titleMain);

            if (item.badge && !item.is_read) {
                titleRow.appendChild(createElement('span', 'notifications-panel__badge', item.badge));
            }

            headerBlock.appendChild(titleRow);
            li.appendChild(headerBlock);

            if (item.body) {
                li.appendChild(createElement('p', 'notifications-panel__description', { html: item.body }));
            }

            const statusEntries = [];
            const metaEntries = [];

            if (Array.isArray(item.meta)) {
                item.meta.forEach((entry) => {
                    if (!entry || typeof entry.text !== 'string' || entry.text === '') {
                        return;
                    }
                    const type = typeof entry.type === 'string' ? entry.type : '';
                    const label = typeof entry.label === 'string' ? entry.label : '';
                    if (type === 'status') {
                        statusEntries.push(entry);
                        return;
                    }
                    if (label && label.toLowerCase() === 'garantía') {
                        return;
                    }
                    metaEntries.push(entry);
                });
            }

            statusEntries.forEach((entry) => {
                const line = createElement('p', 'notifications-panel__status-line');
                if (entry.label) {
                    const label = createElement('span', 'notifications-panel__status-label', `${entry.label}:`);
                    line.appendChild(label);
                    line.appendChild(document.createTextNode(` ${entry.text}`));
                } else {
                    line.textContent = entry.text;
                }
                li.appendChild(line);
            });

            metaEntries.forEach((entry) => {
                const metaLine = buildMetaLine(entry);
                if (metaLine) {
                    li.appendChild(metaLine);
                }
            });

            const quickActions = createElement('div', 'notifications-panel__quick-actions');

            const deleteButton = createElement('button', 'notifications-panel__action-button notifications-panel__action-button--ghost');
            deleteButton.type = 'button';
            deleteButton.dataset.action = 'delete';
            deleteButton.setAttribute('aria-label', deleteLabel);
            if (deleteIcon) {
                deleteButton.insertAdjacentHTML('afterbegin', deleteIcon);
            }
            deleteButton.appendChild(createElement('span', 'notifications-panel__action-text', deleteLabel));
            quickActions.appendChild(deleteButton);

            const markButton = createElement('button', 'notifications-panel__action-button notifications-panel__action-button--ghost');
            markButton.type = 'button';
            markButton.dataset.action = 'mark';
            markButton.dataset.labelRead = markedLabel;
            markButton.dataset.labelUnread = markLabel;
            markButton.appendChild(createElement('span', 'notifications-panel__action-text'));
            setMarkButtonState(markButton, Boolean(item.is_read));
            quickActions.appendChild(markButton);

            if (Array.isArray(item.actions)) {
                item.actions.forEach((action) => {
                    if (!action || !action.title) {
                        return;
                    }
                    if (action.url) {
                        const linkButton = createElement('button', 'notifications-panel__action-button notifications-panel__action-button--primary', action.title);
                        linkButton.type = 'button';
                        linkButton.dataset.action = 'open-link';
                        linkButton.dataset.url = action.url;
                        quickActions.appendChild(linkButton);
                    }
                });
            }

            if (quickActions.childNodes.length > 0) {
                const footer = createElement('div', 'notifications-panel__meta-bar');
                footer.appendChild(quickActions);
                li.appendChild(footer);
            }

            return li;
        };

        const refreshNotifications = ({
            panelItems = filterPanelItems(state.items),
            preservePanelScroll = state.isOpen,
            preserveModalScroll = state.modalOpen,
        } = {}) => {
            const modalData = getModalDataset();
            const modalItems = modalData.items;
            state.modalFilteredCount = modalItems.length;

            if (list) {
                let previousScroll = null;
                if (preservePanelScroll && scrollBox) {
                    previousScroll = scrollBox.scrollTop;
                }
                list.innerHTML = '';
                panelItems.forEach((item) => {
                    const element = buildNotification(item);
                    list.appendChild(element);
                });
                if (previousScroll !== null && scrollBox) {
                    scrollBox.scrollTop = previousScroll;
                }
            }

            if (state.modalElements && state.modalElements.list) {
                const { list: modalList, scroll, filter } = state.modalElements;
                let previousModalScroll = null;
                if (preserveModalScroll && scroll) {
                    previousModalScroll = scroll.scrollTop;
                }
                modalList.innerHTML = '';
                modalItems.forEach((item) => {
                    modalList.appendChild(buildNotification(item));
                });
                if (filter) {
                    filter.value = state.modalCategory || 'all';
                }
                if (scroll) {
                    if (previousModalScroll !== null) {
                        scroll.scrollTop = previousModalScroll;
                    } else if (!preserveModalScroll) {
                        scroll.scrollTop = 0;
                    }
                    state.modalReachedEnd = isNearBottom(scroll, 8);
                } else {
                    state.modalReachedEnd = modalItems.length === 0;
                }
            } else {
                state.modalReachedEnd = modalItems.length === 0 ? true : state.modalReachedEnd;
            }

            updateEmptyState();
        };

        const synchronizeInterface = ({
            persist = true,
            preservePanelScroll = state.isOpen,
            preserveModalScroll = state.modalOpen,
        } = {}) => {
            const panelItems = filterPanelItems(state.items);
            state.panelUnread = panelItems.length;
            setBadge(state.panelUnread);
            refreshNotifications({
                panelItems,
                preservePanelScroll,
                preserveModalScroll,
            });
            updateLoadMore();
            if (persist) {
                persistSnapshot();
            }
        };

        const handleNewItems = (items, { allowToast = false, previousItems = [] } = {}) => {
            const normalized = normalizeItems(items);
            normalized.forEach((item) => {
                state.knownIds.add(item.id);
            });

            if (!state.initialized) {
                state.initialized = true;
                return;
            }

            if (!allowToast || normalized.length === 0 || state.isOpen || !state.toastEnabled) {
                return;
            }

            const previousIds = Array.isArray(previousItems)
                ? previousItems.map((entry) => Number(entry.id))
                : [];

            const unseen = normalized.filter((item) => !previousIds.includes(item.id));
            if (unseen.length > 0) {
                const candidate = unseen[0];
                if (!state.browserHistory.has(candidate.id)) {
                    maybeShowBrowserNotification(candidate);
                }
                if (state.lastToastId !== candidate.id) {
                    showToast(candidate);
                }
            }
        };

        const fetchNotifications = async ({ append = false, background = false, category = 'all' } = {}) => {
            const targetCategory = typeof category === 'string' && category !== '' ? category : 'all';
            const categoryState = targetCategory === 'all' ? null : getModalCategoryState(targetCategory);
            if (targetCategory === 'all') {
                if (state.isLoading) {
                    return;
                }
                state.isLoading = true;
                if (append) {
                    state.loadingMore = true;
                    updateLoadMore();
                }
            } else {
                if (!categoryState || categoryState.loading) {
                    return;
                }
                categoryState.loading = true;
                if (append) {
                    categoryState.loadingMore = true;
                    updateLoadMore();
                } else {
                    state.modalReachedEnd = false;
                }
            }

            try {
                const page = append
                    ? targetCategory === 'all'
                        ? state.page + 1
                        : (categoryState?.page || 0) + 1
                    : 1;
                const url = new URL(listEndpoint, window.location.origin);
                url.searchParams.set('page', String(page));
                url.searchParams.set('per_page', String(perPage));
                if (targetCategory !== 'all') {
                    url.searchParams.set('category', targetCategory);
                }
                url.searchParams.set('_', String(Date.now()));

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const payload = await response.json();
                const items = normalizeItems(payload.data);
                const meta = payload.meta || {};
                const latestFromMeta = typeof meta.latest_id === 'number'
                    ? meta.latest_id
                    : Number(meta.latest_id);

                if (targetCategory === 'all') {
                    if (append) {
                        state.page = page;
                        state.hasMore = Boolean(meta.has_more);
                        state.items = dedupeById(state.items.concat(items));
                        items.forEach((item) => state.knownIds.add(item.id));
                    } else {
                        const previousItems = state.items.slice();
                        state.page = 1;
                        state.hasMore = Boolean(meta.has_more);
                        state.items = items;
                        handleNewItems(items, { allowToast: background, previousItems });
                    }

                    if (typeof meta.unread !== 'undefined') {
                        state.unread = parseUnread(meta.unread);
                    }

                    synchronizeInterface({
                        preservePanelScroll: append && state.isOpen,
                        preserveModalScroll: append && state.modalOpen,
                    });

                    updateLatestId(items, Number.isFinite(latestFromMeta) ? latestFromMeta : null);
                } else if (categoryState) {
                    if (append) {
                        categoryState.page = page;
                        categoryState.hasMore = Boolean(meta.has_more);
                        categoryState.items = dedupeById(categoryState.items.concat(items));
                    } else {
                        categoryState.page = 1;
                        categoryState.hasMore = Boolean(meta.has_more);
                        categoryState.items = items;
                    }
                    if (state.modalCategory === targetCategory) {
                        refreshNotifications({
                            preservePanelScroll: true,
                            preserveModalScroll: append && state.modalOpen,
                        });
                        updateLoadMore();
                    }
                    return;
                }
            } catch (error) {
                // eslint-disable-next-line no-console
                console.warn('GO360 notifications warning', error);
            } finally {
                if (targetCategory === 'all') {
                    state.isLoading = false;
                    if (state.loadingMore) {
                        state.loadingMore = false;
                        updateLoadMore();
                    }
                } else if (categoryState) {
                    categoryState.loading = false;
                    if (categoryState.loadingMore) {
                        categoryState.loadingMore = false;
                        updateLoadMore();
                    }
                }
            }
        };

        const pollNotifications = async () => {
            if (state.isPolling || state.isLoading || state.markAllInFlight) {
                return;
            }

            state.isPolling = true;

            try {
                if (state.latestId <= 0) {
                    await fetchNotifications({ append: false, background: true });
                    return;
                }

                const url = new URL(listEndpoint, window.location.origin);
                url.searchParams.set('since', String(state.latestId));
                url.searchParams.set('per_page', String(perPage));
                url.searchParams.set('_', String(Date.now()));

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error('Poll request failed');
                }

                const payload = await response.json();
                const freshItems = normalizeItems(payload.data);
                const meta = payload.meta || {};
                const latestFromMeta = typeof meta.latest_id === 'number'
                    ? meta.latest_id
                    : Number(meta.latest_id);

                if (typeof meta.unread !== 'undefined') {
                    state.unread = parseUnread(meta.unread);
                }

                if (freshItems.length > 0) {
                    const previousItems = state.items.slice();
                    state.items = dedupeById(freshItems.concat(state.items));
                    freshItems.forEach((item) => state.knownIds.add(item.id));
                    handleNewItems(freshItems, {
                        allowToast: !state.isOpen,
                        previousItems,
                    });
                }

                synchronizeInterface({
                    preservePanelScroll: state.isOpen,
                    preserveModalScroll: state.modalOpen,
                });

                updateLatestId(freshItems, Number.isFinite(latestFromMeta) ? latestFromMeta : null);
            } catch (error) {
                // eslint-disable-next-line no-console
                console.warn('GO360 notifications poll warning', error);
            } finally {
                state.isPolling = false;
            }
        };

        const markNotification = async (id) => {
            if (!id || !listEndpoint) {
                return;
            }

            const numericId = Number(id);
            if (!Number.isFinite(numericId) || numericId <= 0) {
                return;
            }

            if (state.pendingMarks.has(numericId)) {
                return state.pendingMarks.get(numericId);
            }

            const baseEndpoint = typeof listEndpoint === 'string'
                ? listEndpoint.replace(/\/+$/, '')
                : '';

            if (!baseEndpoint) {
                return;
            }

            const attemptRequest = async (attempt = 1) => {
                try {
                    const response = await fetch(`${baseEndpoint}/${numericId}`, {
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': nonce,
                        },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    if (!response.ok) {
                        throw new Error(`Mark failed (${response.status})`);
                    }
                    const payload = await response.json();
                    if (payload.meta && typeof payload.meta.unread !== 'undefined') {
                        state.unread = parseUnread(payload.meta.unread);
                    }
                } catch (error) {
                    if (attempt < 2) {
                        await new Promise((resolve) => {
                            window.setTimeout(resolve, 400);
                        });
                        return attemptRequest(attempt + 1);
                    }
                    // eslint-disable-next-line no-console
                    console.warn('GO360 notifications mark warning', error);
                }
                return null;
            };

            const request = attemptRequest();
            state.pendingMarks.set(numericId, request);

            try {
                await request;
            } finally {
                state.pendingMarks.delete(numericId);
            }

            return request;
        };

        const deleteNotification = async (id) => {
            if (!id) {
                return false;
            }
            try {
                const endpoint = `${listEndpoint}/${id}`;
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Delete failed');
                }
                const payload = await response.json();
                if (payload.meta && typeof payload.meta.unread !== 'undefined') {
                    state.unread = parseUnread(payload.meta.unread);
                }
                return true;
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications delete error', error);
            }
            return false;
        };

        const markAll = async () => {
            if (state.markAllInFlight || !markEndpoint) {
                return;
            }

            const hadItems = state.items.length > 0;
            const previousItems = state.items.map((item) => ({ ...item }));
            const previousUnread = state.unread;

            state.markAllInFlight = true;

            if (hadItems) {
                state.items = state.items.map((item) => ({ ...item, is_read: true }));
                state.unread = 0;
                synchronizeInterface({
                    preservePanelScroll: false,
                    preserveModalScroll: false,
                });
            }

            try {
                const response = await fetch(markEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'mark_read' }),
                    cache: 'no-store',
                });
                if (!response.ok) {
                    throw new Error('Mark all failed');
                }
                const payload = await response.json();
                if (payload.meta && typeof payload.meta.unread !== 'undefined') {
                    state.unread = parseUnread(payload.meta.unread);
                }
                synchronizeInterface();
            } catch (error) {
                state.items = previousItems;
                state.unread = previousUnread;
                synchronizeInterface();
                // eslint-disable-next-line no-console
                console.error('GO360 notifications mark all error', error);
            } finally {
                state.markAllInFlight = false;
            }
        };

        const closePanel = (options = {}) => {
            const silent =
                options && typeof options === 'object' && !Array.isArray(options)
                    ? Boolean(options.silent)
                    : false;
            if (!state.isOpen) {
                return;
            }
            state.isOpen = false;
            panel.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            window.dispatchEvent(new CustomEvent('go360:notifications:closed'));
            if (hasPanelHistory && !silent) {
                panelHistory.close('notifications-panel');
            }
        };

        const openPanel = () => {
            if (state.isOpen) {
                return;
            }
            hideToast();
            closeModal();
            state.isOpen = true;
            panel.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            if (scrollBox) {
                scrollBox.scrollTop = 0;
            }
            window.dispatchEvent(new CustomEvent('go360:profile:close'));
            window.dispatchEvent(new CustomEvent('go360:notifications:opened'));
            fetchNotifications({ append: false, background: false });
            if (hasPanelHistory) {
                panelHistory.push('notifications-panel', () => {
                    closePanel({ silent: true });
                });
            }
        };

        const togglePanel = () => {
            if (state.isOpen) {
                closePanel();
            } else {
                openPanel();
            }
        };

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            togglePanel();
        });

        document.addEventListener('click', (event) => {
            if (!state.isOpen) {
                return;
            }
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (!panel.contains(target) && !toggle.contains(target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (state.modalOpen) {
                    closeModal();
                } else if (state.isOpen) {
                    closePanel();
                } else if (!toast.hidden) {
                    hideToast();
                }
            }
        });

        window.addEventListener('go360:profile:opened', () => {
            closePanel();
        });

        window.addEventListener('go360:notifications:close', () => {
            closePanel();
        });

        if (markAllButton) {
            markAllButton.addEventListener('click', () => {
                markAll();
            });
        }

        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', () => {
                if (state.loadingMore || !state.hasMore) {
                    return;
                }
                fetchNotifications({ append: true, background: state.isOpen === false });
            });
        }

        if (viewAllButton) {
            viewAllButton.addEventListener('click', () => {
                closePanel();
                openModal();
            });
        }

        if (toast) {
            toast.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                const closeTarget = target.closest('[data-toast-close]');
                if (closeTarget) {
                    event.preventDefault();
                    event.stopPropagation();
                    hideToast();
                    return;
                }

                const link = toast.dataset.notificationLink || '';
                if (link) {
                    window.open(link, '_blank', 'noopener,noreferrer');
                } else {
                    openPanel();
                }
                hideToast();
            });
        }

        const attachListEvents = (listElement, context) => {
            if (!listElement) {
                return;
            }

            listElement.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                const actionButton = target.closest('.notifications-panel__action-button');
                if (actionButton) {
                    const actionType = actionButton.dataset.action || '';
                    if (actionType === '') {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    const itemElement = actionButton.closest('.notifications-panel__item');
                    if (!itemElement) {
                        return;
                    }

                    const id = itemElement.dataset.notificationId;
                    if (!id) {
                        return;
                    }

                    const preservePanelScroll = state.isOpen;
                    const preserveModalScroll = state.modalOpen;

                    if (actionType === 'delete') {
                        if (actionButton.classList.contains('is-disabled')) {
                            return;
                        }
                        actionButton.classList.add('is-disabled');
                        actionButton.setAttribute('aria-disabled', 'true');
                        deleteNotification(id).then((success) => {
                            if (!success) {
                                actionButton.classList.remove('is-disabled');
                                actionButton.setAttribute('aria-disabled', 'false');
                                return;
                            }
                            const wasUnread = itemElement.classList.contains('notifications-panel__item--unread');
                            state.items = state.items.filter((item) => String(item.id) !== id);
                            if (wasUnread) {
                                state.unread = Math.max(0, state.unread - 1);
                            }
                            synchronizeInterface({
                                preservePanelScroll,
                                preserveModalScroll,
                            });
                            const backgroundRefresh = !state.isOpen && !state.modalOpen;
                            fetchNotifications({ append: false, background: backgroundRefresh });
                        });
                        return;
                    }

                    if (actionType === 'open-link') {
                        const url = actionButton.dataset.url || '';
                        if (url) {
                            window.open(url, '_blank', 'noopener,noreferrer');
                        }
                        if (itemElement.classList.contains('notifications-panel__item--unread')) {
                            state.items = state.items.map((item) => {
                                if (String(item.id) === id) {
                                    return { ...item, is_read: true };
                                }
                                return item;
                            });
                            state.unread = Math.max(0, state.unread - 1);
                            synchronizeInterface({
                                preservePanelScroll,
                                preserveModalScroll,
                            });
                            markNotification(id);
                        }
                        return;
                    }

                    if (actionType === 'mark') {
                        if (!itemElement.classList.contains('notifications-panel__item--unread')) {
                            return;
                        }
                        state.items = state.items.map((item) => {
                            if (String(item.id) === id) {
                                return { ...item, is_read: true };
                            }
                            return item;
                        });
                        if (state.unread > 0) {
                            state.unread = Math.max(0, state.unread - 1);
                        }
                        synchronizeInterface({
                            preservePanelScroll,
                            preserveModalScroll,
                        });
                        markNotification(id);
                    }
                    return;
                }

                const itemElement = target.closest('.notifications-panel__item');
                if (!itemElement) {
                    return;
                }
                const id = itemElement.dataset.notificationId;
                if (!id) {
                    return;
                }
                if (!itemElement.classList.contains('notifications-panel__item--unread')) {
                    return;
                }
                state.items = state.items.map((item) => {
                    if (String(item.id) === id) {
                        return { ...item, is_read: true };
                    }
                    return item;
                });
                if (state.unread > 0) {
                    state.unread = Math.max(0, state.unread - 1);
                }
                synchronizeInterface({
                    preservePanelScroll: state.isOpen,
                    preserveModalScroll: state.modalOpen,
                });
                markNotification(id);
            });
        };

        attachListEvents(list, 'panel');

        window.addEventListener('go360:notifications:preferences', (event) => {
            if (!event || typeof event.detail !== 'object' || event.detail === null) {
                return;
            }

            const detail = event.detail;

            if (typeof detail.toastEnabled === 'boolean') {
                state.toastEnabled = detail.toastEnabled;
                persistPreference('toast', state.toastEnabled);
                if (!state.toastEnabled) {
                    hideToast({ immediate: true });
                    setToggleToastState(false);
                }
            }

            if (typeof detail.soundEnabled === 'boolean') {
                state.toastSoundEnabled = detail.soundEnabled;
                persistPreference('sound', state.toastSoundEnabled);
            }
        });

        window.addEventListener('go360:notifications:refresh', () => {
            fetchNotifications({ append: false, background: !state.isOpen });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                fetchNotifications({ append: false, background: !state.isOpen });
            }
        });

        const schedulePoll = () => {
            if (state.pollTimer) {
                window.clearTimeout(state.pollTimer);
            }
            state.pollTimer = window.setTimeout(async () => {
                await pollNotifications();
                schedulePoll();
            }, pollInterval);
        };

        hydrateSnapshot();

        fetchNotifications({ append: false, background: false })
            .catch(() => {})
            .finally(() => {
                schedulePoll();
                window.setTimeout(() => {
                    pollNotifications();
                }, Math.min(pollInterval, 2500));
            });
    });
})();
