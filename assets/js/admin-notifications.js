(function () {
    const SELECTORS = {
        container: '[data-admin-notifications]',
        toggle: '[data-notifications-toggle]',
        panel: '[data-notifications-panel]',
        badge: '[data-notifications-badge]',
        counter: '[data-notifications-counter]',
        list: '[data-notifications-list]',
        empty: '[data-notifications-empty]',
        markAll: '[data-notifications-mark-all]',
        loadMore: '[data-notifications-load-more]',
        viewAll: '[data-notifications-view-all]',
        scroll: '[data-notifications-scroll]',
        toast: '[data-notifications-toast]',
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

        const toggle = container.querySelector(SELECTORS.toggle);
        const panel = container.querySelector(SELECTORS.panel);
        const list = container.querySelector(SELECTORS.list);
        const badge = container.querySelector(SELECTORS.badge);
        const counter = container.querySelector(SELECTORS.counter);
        const empty = container.querySelector(SELECTORS.empty);
        const markAllButton = container.querySelector(SELECTORS.markAll);
        const loadMoreButton = container.querySelector(SELECTORS.loadMore);
        const viewAllButton = container.querySelector(SELECTORS.viewAll);
        const scrollBox = container.querySelector(SELECTORS.scroll);
        const toast = container.querySelector(SELECTORS.toast);

        const config = window.go360Notifications || {};
        const endpoints = config.endpoints || {};
        const listEndpoint = endpoints.list || '';
        const markEndpoint = endpoints.markAll || '';
        const nonce = config.nonce || '';
        const perPage = config.perPage || 8;
        const pollInterval = Math.max(3000, Number(config.pollInterval || 6000));
        const toastDuration = Math.max(4000, Number(config.toastDuration || 6000));

        if (!toggle || !panel || !list || !listEndpoint) {
            return;
        }

        const checkIcon = decodeIcon(container.dataset.iconCheck || '');
        const trashIcon = decodeIcon(container.dataset.iconTrash || '');
        const closeIcon = decodeIcon(container.dataset.iconClose || '');
        const markLabel = container.dataset.markLabel || 'Marcar como leído';
        const markedLabel = container.dataset.markedLabel || 'Leída';
        const deleteLabel = container.dataset.deleteLabel || 'Eliminar';
        const loadMoreLabel = container.dataset.loadMoreLabel || 'Cargar más';
        const loadingLabel = container.dataset.loadingLabel || 'Cargando…';
        const viewAllLabel = container.dataset.viewAllLabel || 'Ver todas las notificaciones';
        const toastOpenLabel = container.dataset.toastOpenLabel || 'Ver ahora';
        const toastDismissLabel = container.dataset.toastDismissLabel || 'Descartar';
        const modalPlaceholder = container.dataset.modalPlaceholder || '';
        const modalCloseLabel = container.dataset.modalCloseLabel || 'Cerrar';

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
            toastTimer: null,
            modal: null,
            modalOpen: false,
            latestId: 0,
            isPolling: false,
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
                body: typeof item.body === 'string' ? item.body : '',
                icon: typeof item.icon === 'string' ? item.icon : '',
                icon_svg: typeof item.icon_svg === 'string' ? item.icon_svg : '',
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
            if (badge) {
                if (count > 0) {
                    badge.hidden = false;
                    badge.textContent = String(count);
                } else {
                    badge.hidden = true;
                }
            }
            if (counter) {
                if (count > 0) {
                    counter.hidden = false;
                    counter.textContent = String(count);
                } else {
                    counter.hidden = true;
                }
            }
        };

        const updateEmptyState = () => {
            if (!empty) {
                return;
            }
            if (state.items.length === 0) {
                empty.hidden = false;
            } else {
                empty.hidden = true;
            }
        };

        const updateLoadMore = () => {
            if (!loadMoreButton) {
                return;
            }
            if (!state.hasMore) {
                loadMoreButton.hidden = true;
                loadMoreButton.disabled = false;
                loadMoreButton.textContent = loadMoreLabel;
                return;
            }
            loadMoreButton.hidden = false;
            loadMoreButton.disabled = state.loadingMore;
            loadMoreButton.textContent = state.loadingMore ? loadingLabel : loadMoreLabel;
        };

        const hideToast = () => {
            if (!toast) {
                return;
            }
            toast.hidden = true;
            toast.classList.remove('notifications-toast--visible');
            toast.innerHTML = '';
            if (state.toastTimer) {
                window.clearTimeout(state.toastTimer);
                state.toastTimer = null;
            }
        };

        const showToast = (item) => {
            if (!toast || !item) {
                return;
            }

            hideToast();

            const card = createElement('div', 'notifications-toast__inner');

            const iconWrapper = createElement('div', 'notifications-toast__icon');
            if (item.icon_svg) {
                iconWrapper.innerHTML = item.icon_svg;
            }
            card.appendChild(iconWrapper);

            const content = createElement('div', 'notifications-toast__content');
            const title = createElement('p', 'notifications-toast__title', item.title);
            content.appendChild(title);
            if (item.body) {
                const description = createElement('p', 'notifications-toast__description', item.body);
                content.appendChild(description);
            }

            const actions = createElement('div', 'notifications-toast__actions');
            const openButton = createElement('button', 'notifications-toast__action', toastOpenLabel);
            openButton.type = 'button';
            openButton.dataset.toastAction = 'open';
            actions.appendChild(openButton);

            const dismissButton = createElement('button', 'notifications-toast__dismiss');
            dismissButton.type = 'button';
            dismissButton.dataset.toastAction = 'dismiss';
            dismissButton.setAttribute('aria-label', toastDismissLabel);
            if (closeIcon) {
                dismissButton.innerHTML = closeIcon.replace('notifications-modal__close-icon', 'notifications-toast__dismiss-icon');
            } else {
                dismissButton.textContent = '×';
            }
            actions.appendChild(dismissButton);

            content.appendChild(actions);
            card.appendChild(content);

            toast.dataset.notificationId = String(item.id);
            toast.appendChild(card);
            toast.hidden = false;
            toast.classList.add('notifications-toast--visible');

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
            header.appendChild(closeButton);

            dialog.appendChild(header);

            const body = createElement('div', 'notifications-modal__body');
            if (modalPlaceholder) {
                body.appendChild(createElement('p', 'notifications-modal__placeholder', modalPlaceholder));
            }
            dialog.appendChild(body);

            modal.appendChild(dialog);
            document.body.appendChild(modal);

            state.modal = modal;

            modal.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }
                if (target.dataset.modalClose === 'true') {
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
        };

        const closeModal = () => {
            if (!state.modal) {
                return;
            }
            state.modal.hidden = true;
            state.modal.setAttribute('aria-hidden', 'true');
            state.modal.classList.remove('notifications-modal--visible');
            state.modalOpen = false;
        };

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

            const tone = item.tone || 'info';
            li.classList.add(`notifications-panel__item--tone-${tone}`);
            if (!item.is_read) {
                li.classList.add('notifications-panel__item--unread');
                li.appendChild(createElement('span', 'notifications-panel__indicator'));
            }

            const iconWrapper = createElement('div', 'notifications-panel__icon');
            if (item.icon_svg) {
                iconWrapper.innerHTML = item.icon_svg;
            }
            li.appendChild(iconWrapper);

            const header = createElement('div', 'notifications-panel__body');
            const titleRow = createElement('div', 'notifications-panel__title-row');
            titleRow.appendChild(createElement('p', 'notifications-panel__title-text', item.title));
            if (item.badge && !item.is_read) {
                titleRow.appendChild(createElement('span', 'notifications-panel__badge', item.badge));
            }
            header.appendChild(titleRow);

            const formattedTime = formatDate(item.created_at);
            if (formattedTime) {
                const time = createElement('time', 'notifications-panel__time', formattedTime);
                if (item.created_at) {
                    time.dateTime = item.created_at;
                }
                header.appendChild(time);
            }

            li.appendChild(header);

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
                const line = createElement('p', 'notifications-panel__status');
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
            if (trashIcon) {
                deleteButton.insertAdjacentHTML('afterbegin', trashIcon);
            }
            deleteButton.appendChild(createElement('span', 'notifications-panel__action-text', deleteLabel));
            quickActions.appendChild(deleteButton);

            const markButton = createElement('button', 'notifications-panel__action-button notifications-panel__action-button--ghost');
            markButton.type = 'button';
            markButton.dataset.action = 'mark';
            markButton.dataset.labelRead = markedLabel;
            markButton.dataset.labelUnread = markLabel;
            if (checkIcon) {
                markButton.insertAdjacentHTML('afterbegin', checkIcon);
            }
            const markText = createElement('span', 'notifications-panel__action-text', item.is_read ? markedLabel : markLabel);
            markButton.appendChild(markText);
            if (item.is_read) {
                markButton.classList.add('is-disabled');
                markButton.setAttribute('aria-disabled', 'true');
            } else {
                markButton.setAttribute('aria-disabled', 'false');
            }
            quickActions.appendChild(markButton);

            if (Array.isArray(item.actions)) {
                item.actions.forEach((action) => {
                    if (!action || !action.title) {
                        return;
                    }
                    if (action.url) {
                        const link = createElement('a', 'notifications-panel__action-button notifications-panel__action-button--primary', action.title);
                        link.href = action.url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        quickActions.appendChild(link);
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

        const renderNotifications = (items, append = false) => {
            if (!append) {
                list.innerHTML = '';
            }
            items.forEach((item) => {
                const element = buildNotification(item);
                list.appendChild(element);
            });
            updateEmptyState();
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

            if (!allowToast || normalized.length === 0 || state.isOpen) {
                return;
            }

            const previousIds = Array.isArray(previousItems)
                ? previousItems.map((entry) => Number(entry.id))
                : [];

            const unseen = normalized.filter((item) => !previousIds.includes(item.id));
            if (unseen.length > 0) {
                showToast(unseen[0]);
            }
        };

        const fetchNotifications = async ({ append = false, background = false } = {}) => {
            if (state.isLoading) {
                return;
            }
            state.isLoading = true;

            if (append) {
                state.loadingMore = true;
                updateLoadMore();
            }

            try {
                const page = append ? state.page + 1 : 1;
                const url = new URL(listEndpoint, window.location.origin);
                url.searchParams.set('page', String(page));
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
                    throw new Error('Request failed');
                }

                const payload = await response.json();
                const items = normalizeItems(payload.data);
                const meta = payload.meta || {};
                const latestFromMeta = typeof meta.latest_id === 'number'
                    ? meta.latest_id
                    : Number(meta.latest_id);

                if (append) {
                    state.page = page;
                    state.hasMore = Boolean(meta.has_more);
                    state.items = dedupeById(state.items.concat(items));
                    items.forEach((item) => state.knownIds.add(item.id));
                    renderNotifications(items, true);
                } else {
                    const previousItems = state.items.slice();
                    state.page = 1;
                    state.hasMore = Boolean(meta.has_more);
                    state.items = items;
                    renderNotifications(items, false);
                    handleNewItems(items, { allowToast: background, previousItems });
                }

                if (typeof meta.unread === 'number') {
                    state.unread = meta.unread;
                    setBadge(state.unread);
                }

                updateLatestId(items, Number.isFinite(latestFromMeta) ? latestFromMeta : null);

                updateLoadMore();
                updateEmptyState();
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications error', error);
            } finally {
                state.isLoading = false;
                if (state.loadingMore) {
                    state.loadingMore = false;
                    updateLoadMore();
                }
            }
        };

        const pollNotifications = async () => {
            if (state.isPolling || state.isLoading) {
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

                if (typeof meta.unread === 'number') {
                    state.unread = meta.unread;
                    setBadge(state.unread);
                }

                if (freshItems.length > 0) {
                    const previousItems = state.items.slice();
                    state.items = dedupeById(freshItems.concat(state.items));

                    let previousScroll = null;
                    if (state.isOpen && scrollBox) {
                        previousScroll = scrollBox.scrollTop;
                    }

                    renderNotifications(state.items, false);

                    if (state.isOpen && scrollBox && previousScroll !== null) {
                        scrollBox.scrollTop = previousScroll;
                    }

                    handleNewItems(freshItems, {
                        allowToast: !state.isOpen,
                        previousItems,
                    });
                }

                updateLatestId(freshItems, Number.isFinite(latestFromMeta) ? latestFromMeta : null);
                updateEmptyState();
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications poll error', error);
            } finally {
                state.isPolling = false;
            }
        };

        const markNotification = async (id) => {
            if (!id) {
                return;
            }
            try {
                const endpoint = `${listEndpoint}/${id}`;
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Mark failed');
                }
                const payload = await response.json();
                if (payload.meta && typeof payload.meta.unread === 'number') {
                    state.unread = payload.meta.unread;
                    setBadge(state.unread);
                }
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications mark error', error);
            }
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
                if (payload.meta && typeof payload.meta.unread === 'number') {
                    state.unread = payload.meta.unread;
                    setBadge(state.unread);
                }
                return true;
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications delete error', error);
            }
            return false;
        };

        const markAll = async () => {
            try {
                const response = await fetch(markEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'mark_read' }),
                });
                if (!response.ok) {
                    throw new Error('Mark all failed');
                }
                const payload = await response.json();
                if (payload.meta && typeof payload.meta.unread === 'number') {
                    state.unread = payload.meta.unread;
                    setBadge(state.unread);
                }
                state.items = state.items.map((item) => ({
                    ...item,
                    is_read: true,
                }));
                renderNotifications(state.items, false);
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications mark all error', error);
            }
        };

        const closePanel = () => {
            if (!state.isOpen) {
                return;
            }
            state.isOpen = false;
            panel.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            window.dispatchEvent(new CustomEvent('go360:notifications:closed'));
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
            if (!(target instanceof HTMLElement)) {
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
                if (!(target instanceof HTMLElement)) {
                    return;
                }
                const actionTarget = target.closest('[data-toast-action]');
                if (!actionTarget) {
                    return;
                }
                const action = actionTarget.getAttribute('data-toast-action');
                if (action === 'open') {
                    openPanel();
                    hideToast();
                }
                if (action === 'dismiss') {
                    hideToast();
                }
            });
        }

        list.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
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
                        state.items = state.items.filter((item) => String(item.id) !== id);
                        itemElement.remove();
                        updateEmptyState();
                        fetchNotifications({ append: false, background: state.isOpen === false });
                    });
                    return;
                }

                if (actionType === 'mark') {
                    if (!itemElement.classList.contains('notifications-panel__item--unread')) {
                        return;
                    }
                    itemElement.classList.remove('notifications-panel__item--unread');
                    const indicator = itemElement.querySelector('.notifications-panel__indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                    actionButton.classList.add('is-disabled');
                    actionButton.setAttribute('aria-disabled', 'true');
                    const markText = actionButton.querySelector('.notifications-panel__action-text');
                    if (markText) {
                        markText.textContent = markedLabel;
                    }
                    state.items = state.items.map((item) => {
                        if (String(item.id) === id) {
                            return { ...item, is_read: true };
                        }
                        return item;
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
            itemElement.classList.remove('notifications-panel__item--unread');
            const indicator = itemElement.querySelector('.notifications-panel__indicator');
            if (indicator) {
                indicator.remove();
            }
            const markButton = itemElement.querySelector('[data-action="mark"]');
            if (markButton) {
                markButton.classList.add('is-disabled');
                markButton.setAttribute('aria-disabled', 'true');
                const markText = markButton.querySelector('.notifications-panel__action-text');
                if (markText) {
                    markText.textContent = markedLabel;
                }
            }
            state.items = state.items.map((item) => {
                if (String(item.id) === id) {
                    return { ...item, is_read: true };
                }
                return item;
            });
            markNotification(id);
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
