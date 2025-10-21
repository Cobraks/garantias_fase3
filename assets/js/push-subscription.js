(function () {
    const log = (...args) => {
        if (typeof console !== 'undefined' && console.log) {
            console.log('GO360 push', ...args);
        }
    };

    const warn = (...args) => {
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('GO360 push', ...args);
        }
    };

    const reportError = (...args) => {
        if (typeof console !== 'undefined' && console.error) {
            console.error('GO360 push', ...args);
        }
    };
    const variants = {
        info: 'account-status--info',
        success: 'account-status--success',
        warning: 'account-status--warning',
        error: 'account-status--error',
    };

    const encodeKey = (key) => {
        if (!key) {
            return '';
        }
        const buffer = new Uint8Array(key);
        let string = '';
        buffer.forEach((value) => {
            string += String.fromCharCode(value);
        });
        return btoa(string);
    };

    const urlBase64ToUint8Array = (base64String) => {
        log('urlBase64ToUint8Array: received key', base64String);
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; i += 1) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        log('urlBase64ToUint8Array: converted length', outputArray.length);
        return outputArray;
    };

    const resolveServiceWorkerUrl = (url) => {
        try {
            const absolute = new URL(url, window.location.href);
            if (absolute.origin !== window.location.origin) {
                absolute.protocol = window.location.protocol;
                absolute.host = window.location.host;
            }
            return absolute.href;
        } catch (error) {
            return url;
        }
    };

    const resolveServiceWorkerScope = (url) => {
        try {
            const absolute = new URL(resolveServiceWorkerUrl(url));
            absolute.hash = '';
            absolute.search = '';
            const segments = absolute.pathname.split('/');
            if (segments.length > 1) {
                segments.pop();
            }
            absolute.pathname = `${segments.join('/')}/`;
            return absolute.href;
        } catch (error) {
            return url;
        }
    };

    const matchesRegistration = (registration, scriptUrl) => {
        if (!registration) {
            return false;
        }

        const candidates = [registration.active, registration.waiting, registration.installing].filter(Boolean);
        if (candidates.length === 0) {
            return false;
        }

        return candidates.some((worker) => worker && worker.scriptURL === scriptUrl);
    };

    const getRegistration = async (serviceWorkerUrl, createIfMissing = false) => {
        const resolvedUrl = resolveServiceWorkerUrl(serviceWorkerUrl);
        const scopeUrl = resolveServiceWorkerScope(serviceWorkerUrl);

        const candidates = [];
        try {
            const scoped = await navigator.serviceWorker.getRegistration(scopeUrl);
            if (scoped) {
                candidates.push(scoped);
            }
        } catch (error) {
            warn('scope lookup error', error);
        }

        try {
            const active = await navigator.serviceWorker.getRegistration();
            if (active) {
                candidates.push(active);
            }
        } catch (error) {
            warn('registration lookup error', error);
        }

        try {
            const ready = await navigator.serviceWorker.ready;
            if (ready) {
                candidates.push(ready);
            }
        } catch (error) {
            warn('ready lookup error', error);
        }

        const match = candidates.find((registration) => matchesRegistration(registration, resolvedUrl));
        if (match) {
            return match;
        }

        if (candidates.length > 0) {
            return candidates[0];
        }

        if (!createIfMissing) {
            return null;
        }

        return navigator.serviceWorker.register(resolvedUrl, { scope: scopeUrl });
    };

    document.addEventListener('DOMContentLoaded', () => {
        const card = document.querySelector('[data-notifications-card]');
        if (!card) {
            return;
        }

        const button = card.querySelector('[data-notifications-request]');
        const label = button ? button.querySelector('[data-notifications-label]') : null;
        const testButton = card.querySelector('[data-notifications-test]');
        const status = card.querySelector('[data-notifications-status]');
        const accountConfig = window.go360Account || {};
        const pushConfig = accountConfig.push || {};
        const restConfig = accountConfig.rest || {};
        const broadcastButton = card.querySelector('[data-notifications-test-all]');

        const setStatus = (message, tone) => {
            if (!status) {
                return;
            }
            status.textContent = message;
            Object.keys(variants).forEach((variant) => {
                status.classList.remove(variants[variant]);
            });
            if (tone && variants[tone]) {
                status.classList.add(variants[tone]);
            }
        };

        if (!button || !status) {
            return;
        }

        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            setStatus('Tu navegador no soporta notificaciones push.', 'error');
            return;
        }

        if (!window.isSecureContext) {
            setStatus('Accede mediante HTTPS para activar las notificaciones.', 'error');
            return;
        }

        if (!pushConfig.subscriptionEndpoint || !pushConfig.serviceWorker) {
            setStatus('La configuración de notificaciones no está disponible.', 'error');
            return;
        }

        if (!pushConfig.publicKey && !pushConfig.publicKeyEndpoint) {
            setStatus('No se pudo obtener la clave pública de notificaciones.', 'error');
            return;
        }

        const publicKeyEndpoint = pushConfig.publicKeyEndpoint || '';
        let publicKey = pushConfig.publicKey;
        const subscriptionEndpoint = pushConfig.subscriptionEndpoint;
        const statusEndpoint = pushConfig.statusEndpoint || '';
        const testEndpoint = pushConfig.testEndpoint || '';
        const testAllEndpoint = pushConfig.testAllEndpoint || '';
        const testIcon = pushConfig.testIcon || '';
        const initialSubscribed = Boolean(pushConfig.isSubscribed);
        const serviceWorkerUrl = pushConfig.serviceWorker;
        const restNonce = restConfig.nonce || '';

        let isActive = initialSubscribed;
        let isProcessing = false;
        let hasSyncedSubscription = initialSubscribed;

        const syncTestButtons = () => {
            [testButton, broadcastButton].forEach((testControl) => {
                if (!testControl) {
                    return;
                }

                const isBroadcast = testControl === broadcastButton;
                const isSingle = testControl === testButton;
                const missingEndpoint = (isBroadcast && !testAllEndpoint) || (isSingle && !testEndpoint);
                const inactive = !isActive || isProcessing || missingEndpoint;

                testControl.disabled = false;
                testControl.removeAttribute('disabled');

                if (inactive) {
                    testControl.dataset.pushInactive = '1';
                    testControl.setAttribute('aria-disabled', 'true');
                } else {
                    delete testControl.dataset.pushInactive;
                    testControl.removeAttribute('aria-disabled');
                }
            });
        };

        const updateControls = (active) => {
            isActive = active;

            button.disabled = false;
            button.removeAttribute('disabled');

            if (label) {
                label.textContent = active ? 'Desactivar notificaciones' : 'Activar notificaciones';
            } else {
                button.textContent = active ? 'Desactivar notificaciones' : 'Activar notificaciones';
            }

            button.setAttribute('aria-pressed', active ? 'true' : 'false');

            syncTestButtons();
        };

        const setProcessing = (processing) => {
            isProcessing = processing;
            log('processing state changed', { processing });
            syncTestButtons();
        };

        updateControls(isActive);
        setStatus('Comprobando el estado de las notificaciones…', 'info');

        const showLocalTestNotification = async () => {
            try {
                const registration = await getRegistration(serviceWorkerUrl, true);
                if (registration && typeof registration.showNotification === 'function') {
                    registration.showNotification('Notificación de prueba', {
                        body: 'Todo funciona correctamente. Recibirás avisos en cuanto haya novedades importantes.',
                        icon: testIcon,
                        badge: testIcon,
                        data: {
                            url: window.location.href,
                        },
                    });
                }
            } catch (notificationError) {
                reportError('local notification error', notificationError);
            }
        };

        const refreshUI = async () => {
            log('refreshUI: checking current subscription status');

            let serverState = {
                hasSubscriptions: Boolean(initialSubscribed),
                count: initialSubscribed ? 1 : 0,
            };

            if (statusEndpoint) {
                try {
                    const response = await fetch(statusEndpoint, {
                        headers: {
                            'X-WP-Nonce': restNonce,
                        },
                        credentials: 'same-origin',
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data && typeof data === 'object') {
                            serverState = {
                                hasSubscriptions: Boolean(data.hasSubscriptions),
                                count: Number(data.count || 0),
                            };
                            log('refreshUI: server subscription state', serverState);
                        }
                    } else {
                        const text = await response.text();
                        warn('refreshUI: status endpoint responded with error', response.status, text);
                    }
                } catch (statusError) {
                    warn('refreshUI: status endpoint failed', statusError);
                }
            }

            let registration = null;
            try {
                registration = await getRegistration(serviceWorkerUrl, false);
                log('refreshUI: service worker registration', registration);
            } catch (registrationError) {
                reportError('refreshUI: error obtaining service worker registration', registrationError);
            }

            if (!registration) {
                updateControls(serverState.hasSubscriptions);
                if (serverState.hasSubscriptions) {
                    setStatus('Las notificaciones están activas, pero este navegador no tiene una suscripción válida. Pulsa “Desactivar notificaciones” y vuelve a activarlas.', 'warning');
                } else {
                    setStatus('Pulsa “Activar notificaciones” para empezar a recibir avisos.', 'info');
                }
                syncTestButtons();
                return;
            }

            let subscription = null;
            try {
                subscription = await registration.pushManager.getSubscription();
            } catch (subscriptionError) {
                reportError('refreshUI: error reading push subscription', subscriptionError);
            }

            log('refreshUI: local subscription', subscription);

            if (subscription && !serverState.hasSubscriptions) {
                log('refreshUI: local subscription exists but server has no record, synchronising');
                try {
                    await sendSubscription(subscription);
                    hasSyncedSubscription = true;
                    serverState.hasSubscriptions = true;
                    serverState.count = Math.max(serverState.count, 1);
                    log('refreshUI: subscription synchronised with server');
                } catch (syncError) {
                    reportError('refreshUI: failed to synchronise subscription', syncError);
                }
            }

            if (!subscription && serverState.hasSubscriptions) {
                warn('refreshUI: server reports active subscriptions but browser is missing one');
            }

            const active = Boolean(subscription || serverState.hasSubscriptions);
            updateControls(active);

            if (active) {
                setStatus('Las notificaciones del navegador están activas en este dispositivo.', 'success');
            } else {
                setStatus('Pulsa “Activar notificaciones” para empezar a recibir avisos.', 'info');
            }

            syncTestButtons();
        };

        const sendSubscription = async (subscription) => {
            const body = {
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: encodeKey(subscription.getKey('p256dh')),
                    auth: encodeKey(subscription.getKey('auth')),
                },
                contentEncoding: 'aes128gcm',
                userAgent: navigator.userAgent,
            };

            const response = await fetch(subscriptionEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce,
                },
                body: JSON.stringify(body),
            });

            log('subscription request response', { endpoint: subscription.endpoint, status: response.status });

            if (!response.ok) {
                const text = await response.text();
                reportError('subscription failed', text);
                throw new Error('Request failed');
            }

            hasSyncedSubscription = true;

            return true;
        };

        const deleteSubscription = async (endpoint) => {
            const url = new URL(subscriptionEndpoint, window.location.origin);
            url.searchParams.set('endpoint', endpoint);
            const response = await fetch(url.toString(), {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': restNonce,
                },
            });

            if (!response.ok) {
                reportError('delete subscription failed', response.status);
                throw new Error('Request failed');
            }

            log('subscription deleted', { endpoint });
        };

        const ensurePublicKey = async () => {
            if (publicKey) {
                return publicKey;
            }

            if (!publicKeyEndpoint) {
                return '';
            }

            try {
                const response = await fetch(publicKeyEndpoint, {
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': restNonce,
                    },
                });
                if (!response.ok) {
                    const text = await response.text();
                    reportError('public key endpoint error', text);
                    return '';
                }
                const data = await response.json();
                if (data && data.publicKey) {
                    publicKey = data.publicKey;
                    log('public key fetched from endpoint');
                }
            } catch (error) {
                reportError('public key fetch failed', error);
                return '';
            }

            return publicKey;
        };

        const requestPermissionAndSubscribe = async () => {
            try {
                if (isProcessing) {
                    return;
                }

                setProcessing(true);
                setStatus('Solicitando permisos…', 'info');
                log('requestPermissionAndSubscribe: requesting permission');
                const permission = await Notification.requestPermission();
                log('requestPermissionAndSubscribe: permission result', permission);
                if (permission !== 'granted') {
                    setStatus('Debes permitir las notificaciones en el navegador.', 'warning');
                    updateControls(false);
                    setProcessing(false);
                    return;
                }

                const registration = await getRegistration(serviceWorkerUrl, true);
                log('requestPermissionAndSubscribe: obtained registration', registration);
                const existing = await registration.pushManager.getSubscription();
                log('requestPermissionAndSubscribe: existing subscription', existing);
                if (existing) {
                    await sendSubscription(existing);
                    hasSyncedSubscription = true;
                    setStatus('Notificaciones activadas correctamente.', 'success');
                    updateControls(true);
                    setProcessing(false);
                    return;
                }

                const resolvedPublicKey = await ensurePublicKey();
                if (!resolvedPublicKey) {
                    setStatus('No se pudo obtener la clave de notificaciones. Comprueba la consola.', 'error');
                    setProcessing(false);
                    return;
                }

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(resolvedPublicKey),
                });
                log('requestPermissionAndSubscribe: new subscription created', subscription);
                await sendSubscription(subscription);
                hasSyncedSubscription = true;
                setStatus('Notificaciones activadas correctamente.', 'success');
                updateControls(true);
            } catch (error) {
                setStatus('No se pudieron activar las notificaciones. Comprueba la consola.', 'error');
                reportError('requestPermissionAndSubscribe error', error);
            } finally {
                setProcessing(false);
            }
        };

        const unsubscribe = async () => {
            try {
                if (isProcessing) {
                    return;
                }

                setProcessing(true);
                setStatus('Desactivando notificaciones…', 'info');
                const registration = await getRegistration(serviceWorkerUrl, false);
                if (!registration) {
                    setStatus('No hay notificaciones activas en este dispositivo.', 'warning');
                    updateControls(false);
                    return;
                }

                const subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    setStatus('No hay notificaciones activas en este dispositivo.', 'warning');
                    updateControls(false);
                    return;
                }

                const endpoint = subscription.endpoint;
                await subscription.unsubscribe();
                await deleteSubscription(endpoint);
                hasSyncedSubscription = false;
                setStatus('Notificaciones desactivadas correctamente.', 'success');
                updateControls(false);
            } catch (error) {
                setStatus('No se pudieron desactivar las notificaciones. Comprueba la consola.', 'error');
                reportError('unsubscribe error', error);
            } finally {
                setProcessing(false);
            }
        };

        const sendTestNotification = async () => {
            if (!testButton) {
                return;
            }

            if (!testEndpoint) {
                setStatus('La configuración de notificaciones no está disponible.', 'error');
                return;
            }

            if (!isActive) {
                setStatus('Activa primero las notificaciones en este dispositivo.', 'warning');
                return;
            }

            try {
                setStatus('Enviando notificación de prueba…', 'info');
                log('sendTestNotification: requesting test notification');
                const response = await fetch(testEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce,
                    },
                    body: JSON.stringify({}),
                });
                if (!response.ok) {
                    const errorText = await response.text();
                    reportError('test notification payload error', errorText);
                    throw new Error('Request failed');
                }
                const payload = await response.json().catch(() => ({}));
                log('sendTestNotification: response received', payload);
                if (!payload.success) {
                    throw new Error('Push dispatch failed');
                }
                setStatus('Hemos enviado una notificación de prueba. Revisa tu navegador y la campana del panel.', 'success');
                await showLocalTestNotification();
                if (window.dispatchEvent) {
                    let refreshEvent;
                    try {
                        refreshEvent = new CustomEvent('go360:notifications:refresh');
                    } catch (eventError) {
                        refreshEvent = document.createEvent('Event');
                        refreshEvent.initEvent('go360:notifications:refresh', true, true);
                    }
                    window.dispatchEvent(refreshEvent);
                }
            } catch (error) {
                setStatus('No se pudo enviar la notificación de prueba. Comprueba la consola.', 'error');
                reportError('sendTestNotification error', error);
            } finally {
                syncTestButtons();
            }
        };

        const sendBroadcastTest = async () => {
            if (!broadcastButton) {
                return;
            }

            if (!testAllEndpoint) {
                setStatus('La configuración de notificaciones no está disponible.', 'error');
                return;
            }

            if (!isActive) {
                setStatus('Activa primero las notificaciones en este dispositivo.', 'warning');
                return;
            }

            try {
                setStatus('Enviando notificación de prueba a todos los dispositivos…', 'info');
                log('sendBroadcastTest: requesting broadcast notification');
                const response = await fetch(testAllEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce,
                    },
                    body: JSON.stringify({}),
                });
                if (!response.ok) {
                    const errorText = await response.text();
                    reportError('broadcast notification payload error', errorText);
                    throw new Error('Request failed');
                }
                const payload = await response.json().catch(() => ({}));
                log('sendBroadcastTest: response received', payload);
                if (!payload.success) {
                    throw new Error('Broadcast dispatch failed');
                }
                setStatus('Hemos enviado la notificación de prueba global. Revisa todos tus dispositivos y la campana del panel.', 'success');
                await showLocalTestNotification();
                if (window.dispatchEvent) {
                    let refreshEvent;
                    try {
                        refreshEvent = new CustomEvent('go360:notifications:refresh');
                    } catch (eventError) {
                        refreshEvent = document.createEvent('Event');
                        refreshEvent.initEvent('go360:notifications:refresh', true, true);
                    }
                    window.dispatchEvent(refreshEvent);
                }
            } catch (error) {
                setStatus('No se pudo enviar la notificación global de prueba. Comprueba la consola.', 'error');
                reportError('sendBroadcastTest error', error);
            } finally {
                syncTestButtons();
            }
        };

        button.addEventListener('click', () => {
            if (isProcessing) {
                return;
            }

            if (isActive) {
                unsubscribe();
            } else {
                requestPermissionAndSubscribe();
            }
        });

        if (testButton) {
            testButton.addEventListener('click', () => {
                sendTestNotification();
            });
        }

        if (broadcastButton) {
            broadcastButton.addEventListener('click', () => {
                sendBroadcastTest();
            });
        }

        refreshUI();
        if (navigator.serviceWorker && 'ready' in navigator.serviceWorker) {
            navigator.serviceWorker.ready.then(() => {
                refreshUI();
            }).catch(() => {
                // noop
            });
        }
    });
})();
