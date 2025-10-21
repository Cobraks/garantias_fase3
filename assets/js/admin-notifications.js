(function () {
    const SELECTORS = {
        container: '[data-admin-notifications]',
        toggle: '[data-notifications-toggle]',
        panel: '[data-notifications-panel]',
        badge: '[data-notifications-badge]',
        list: '[data-notifications-list]',
        empty: '[data-notifications-empty]',
        markAll: '[data-notifications-mark-all]',
        prev: '[data-notifications-prev]',
        next: '[data-notifications-next]',
        page: '[data-notifications-page]',
        scroll: '[data-notifications-scroll]',
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

    const renderAction = (action) => {
        if (!action || !action.title) {
            return null;
        }

        if (action.url) {
            const link = document.createElement('a');
            link.href = action.url;
            link.className = 'notifications-panel__button';
            link.textContent = action.title;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            return link;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'notifications-panel__button';
        button.textContent = action.title;
        return button;
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
        const empty = container.querySelector(SELECTORS.empty);
        const markAllButton = container.querySelector(SELECTORS.markAll);
        const prevButton = container.querySelector(SELECTORS.prev);
        const nextButton = container.querySelector(SELECTORS.next);
        const pageLabel = container.querySelector(SELECTORS.page);
        const scrollBox = container.querySelector(SELECTORS.scroll);

        const config = window.go360Notifications || {};
        const endpoints = config.endpoints || {};
        const listEndpoint = endpoints.list || '';
        const markEndpoint = endpoints.markAll || '';
        const nonce = config.nonce || '';
        const perPage = config.perPage || 6;
        const markLabel = container.dataset.markLabel || 'Marcar como leído';
        const markedLabel = container.dataset.markedLabel || 'Leída';
        const deleteLabel = container.dataset.deleteLabel || 'Eliminar';
        const markIcon = container.dataset.iconView || '';
        const deleteIcon = container.dataset.iconDelete || '';

        if (!toggle || !panel || !list || !listEndpoint) {
            return;
        }

        let currentPage = 1;
        let isOpen = false;
        let isLoading = false;
        let refreshTimer = null;

        const emitEvent = (name) => {
            if (typeof window === 'undefined') {
                return;
            }
            if (typeof window.CustomEvent === 'function') {
                window.dispatchEvent(new CustomEvent(name));
            } else if (typeof document !== 'undefined' && typeof document.createEvent === 'function') {
                const custom = document.createEvent('CustomEvent');
                custom.initCustomEvent(name, false, false, {});
                window.dispatchEvent(custom);
            }
        };

        const setBadge = (count) => {
            if (!badge) {
                return;
            }
            if (count > 0) {
                badge.hidden = false;
                badge.textContent = String(count);
            } else {
                badge.hidden = true;
            }
        };

        const closePanel = () => {
            if (!isOpen) {
                return;
            }
            isOpen = false;
            panel.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            if (refreshTimer) {
                window.clearInterval(refreshTimer);
                refreshTimer = null;
            }
            emitEvent('go360:notifications:closed');
        };

        const openPanel = () => {
            if (isOpen) {
                return;
            }
            isOpen = true;
            panel.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            if (scrollBox) {
                scrollBox.scrollTop = 0;
            }
            emitEvent('go360:profile:close');
            emitEvent('go360:notifications:opened');
            fetchNotifications();
            if (!refreshTimer) {
                refreshTimer = window.setInterval(fetchNotifications, 60000);
            }
        };

        const togglePanel = () => {
            if (isOpen) {
                closePanel();
            } else {
                openPanel();
            }
        };

        const renderNotifications = (items) => {
            list.innerHTML = '';
            if (!items.length) {
                if (empty) {
                    empty.hidden = false;
                }
                return;
            }

            if (empty) {
                empty.hidden = true;
            }

            items.forEach((item) => {
                const li = document.createElement('li');
                li.className = 'notifications-panel__item';
                if (!item.is_read) {
                    li.classList.add('notifications-panel__item--unread');
                }
                li.dataset.notificationId = item.id;

                const iconWrapper = document.createElement('div');
                iconWrapper.className = 'notifications-panel__icon';
                if (item.icon) {
                    const img = document.createElement('img');
                    img.src = item.icon;
                    img.alt = '';
                    iconWrapper.appendChild(img);
                } else {
                    iconWrapper.textContent = '•';
                }

                const body = document.createElement('div');
                body.className = 'notifications-panel__body';

                const title = document.createElement('p');
                title.className = 'notifications-panel__title-text';
                title.textContent = item.title || '';

                const description = document.createElement('p');
                description.className = 'notifications-panel__description';
                description.textContent = item.body || '';

                const meta = document.createElement('span');
                meta.className = 'notifications-panel__meta';
                meta.textContent = formatDate(item.created_at);

                body.appendChild(title);
                if (item.body) {
                    body.appendChild(description);
                }
                if (meta.textContent) {
                    body.appendChild(meta);
                }

                const quickActions = document.createElement('div');
                quickActions.className = 'notifications-panel__quick-actions';

                const markButton = document.createElement('button');
                markButton.type = 'button';
                markButton.className = 'notifications-panel__action-button';
                markButton.dataset.action = 'mark';
                markButton.dataset.labelRead = markedLabel;
                markButton.dataset.labelUnread = markLabel;
                if (markIcon) {
                    markButton.innerHTML = markIcon;
                }
                const markText = document.createElement('span');
                markText.className = 'notifications-panel__action-text';
                markText.textContent = item.is_read ? markedLabel : markLabel;
                markButton.appendChild(markText);
                if (item.is_read) {
                    markButton.classList.add('is-disabled');
                    markButton.setAttribute('aria-disabled', 'true');
                    markButton.setAttribute('aria-label', markedLabel);
                } else {
                    markButton.setAttribute('aria-disabled', 'false');
                    markButton.setAttribute('aria-label', markLabel);
                }
                quickActions.appendChild(markButton);

                const deleteButton = document.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'notifications-panel__action-button';
                deleteButton.dataset.action = 'delete';
                deleteButton.setAttribute('aria-label', deleteLabel);
                if (deleteIcon) {
                    deleteButton.innerHTML = deleteIcon;
                }
                const deleteText = document.createElement('span');
                deleteText.className = 'notifications-panel__action-text';
                deleteText.textContent = deleteLabel;
                deleteButton.appendChild(deleteText);
                quickActions.appendChild(deleteButton);

                body.appendChild(quickActions);

                if (Array.isArray(item.actions) && item.actions.length) {
                    const actionsContainer = document.createElement('div');
                    actionsContainer.className = 'notifications-panel__actions';
                    item.actions.forEach((action) => {
                        const element = renderAction(action);
                        if (element) {
                            if (element.tagName === 'A' && item.link && !element.href) {
                                element.href = item.link;
                            }
                            actionsContainer.appendChild(element);
                        }
                    });
                    body.appendChild(actionsContainer);
                } else if (item.link) {
                    const link = document.createElement('a');
                    link.href = item.link;
                    link.className = 'notifications-panel__button';
                    link.textContent = 'Abrir';
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    body.appendChild(link);
                }

                li.appendChild(iconWrapper);
                li.appendChild(body);
                list.appendChild(li);
            });
        };

        const updatePagination = (itemsLength) => {
            if (prevButton) {
                prevButton.disabled = currentPage <= 1;
            }
            if (nextButton) {
                nextButton.disabled = itemsLength < perPage;
            }
            if (pageLabel) {
                pageLabel.textContent = String(currentPage);
            }
        };

        const fetchNotifications = async () => {
            if (isLoading) {
                return;
            }
            isLoading = true;
            try {
                const url = new URL(listEndpoint, window.location.origin);
                url.searchParams.set('page', String(currentPage));
                url.searchParams.set('per_page', String(perPage));
                const response = await fetch(url.toString(), {
                    headers: {
                        'X-WP-Nonce': nonce,
                    },
                });
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                const payload = await response.json();
                const items = Array.isArray(payload.data) ? payload.data : [];
                renderNotifications(items);
                updatePagination(items.length);
                if (payload.meta && typeof payload.meta.unread === 'number') {
                    setBadge(payload.meta.unread);
                }
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications error', error);
            } finally {
                isLoading = false;
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
                });
                if (!response.ok) {
                    throw new Error('Mark failed');
                }
                const payload = await response.json();
                if (payload.meta && typeof payload.meta.unread === 'number') {
                    setBadge(payload.meta.unread);
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
                });
                if (!response.ok) {
                    throw new Error('Delete failed');
                }
                const payload = await response.json();
                if (payload.meta && typeof payload.meta.unread === 'number') {
                    setBadge(payload.meta.unread);
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
                    body: JSON.stringify({ action: 'mark_read' }),
                });
                if (!response.ok) {
                    throw new Error('Mark all failed');
                }
                const payload = await response.json();
                if (payload.meta && typeof payload.meta.unread === 'number') {
                    setBadge(payload.meta.unread);
                }
                fetchNotifications();
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('GO360 notifications mark all error', error);
            }
        };

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            togglePanel();
        });

        document.addEventListener('click', (event) => {
            if (!isOpen) {
                return;
            }
            if (!panel.contains(event.target) && !toggle.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isOpen) {
                closePanel();
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

        if (prevButton) {
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage -= 1;
                    fetchNotifications();
                }
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => {
                currentPage += 1;
                fetchNotifications();
            });
        }

        list.addEventListener('click', (event) => {
            const actionButton = event.target.closest('.notifications-panel__action-button');
            if (actionButton) {
                event.preventDefault();
                event.stopPropagation();
                const item = actionButton.closest('.notifications-panel__item');
                if (!item) {
                    return;
                }
                const id = item.dataset.notificationId;
                if (!id) {
                    return;
                }

                if (actionButton.dataset.action === 'delete') {
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
                        item.remove();
                        if (empty) {
                            empty.hidden = list.children.length > 0;
                        }
                        fetchNotifications();
                    });
                    return;
                }

                if (actionButton.dataset.action === 'mark') {
                    if (!item.classList.contains('notifications-panel__item--unread')) {
                        return;
                    }
                    item.classList.remove('notifications-panel__item--unread');
                    actionButton.classList.add('is-disabled');
                    actionButton.setAttribute('aria-disabled', 'true');
                    actionButton.setAttribute('aria-label', actionButton.dataset.labelRead || markedLabel);
                    const markText = actionButton.querySelector('.notifications-panel__action-text');
                    if (markText) {
                        markText.textContent = actionButton.dataset.labelRead || markedLabel;
                    }
                    markNotification(id);
                }
                return;
            }

            const item = event.target.closest('.notifications-panel__item');
            if (!item) {
                return;
            }
            const id = item.dataset.notificationId;
            if (!id || !item.classList.contains('notifications-panel__item--unread')) {
                return;
            }
            item.classList.remove('notifications-panel__item--unread');
            const markButton = item.querySelector('[data-action="mark"]');
            if (markButton) {
                markButton.classList.add('is-disabled');
                markButton.setAttribute('aria-disabled', 'true');
                markButton.setAttribute('aria-label', markButton.dataset.labelRead || markedLabel);
                const markText = markButton.querySelector('.notifications-panel__action-text');
                if (markText) {
                    markText.textContent = markButton.dataset.labelRead || markedLabel;
                }
            }
            markNotification(id);
        });

        window.addEventListener('go360:notifications:refresh', () => {
            fetchNotifications();
        });

        fetchNotifications();
    });
})();
