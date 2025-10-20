(function () {
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
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; i += 1) {
            outputArray[i] = rawData.charCodeAt(i);
        }
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
            // eslint-disable-next-line no-console
            console.warn('GO360 push scope lookup error', error);
        }

        try {
            const active = await navigator.serviceWorker.getRegistration();
            if (active) {
                candidates.push(active);
            }
        } catch (error) {
            // eslint-disable-next-line no-console
            console.warn('GO360 push registration lookup error', error);
        }

        try {
            const ready = await navigator.serviceWorker.ready;
            if (ready) {
                candidates.push(ready);
            }
        } catch (error) {
            // eslint-disable-next-line no-console
            console.warn('GO360 push ready lookup error', error);
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
            button.disabled = true;
            if (testButton) {
                testButton.disabled = true;
            }
            setStatus('Tu navegador no soporta notificaciones push.', 'error');
            return;
        }

        if (!window.isSecureContext) {
            button.disabled = true;
            if (testButton) {
                testButton.disabled = true;
            }
            setStatus('Accede mediante HTTPS para activar las notificaciones.', 'error');
            return;
        }

        if (!pushConfig.publicKey || !pushConfig.subscriptionEndpoint || !pushConfig.serviceWorker) {
            button.disabled = true;
            if (testButton) {
                testButton.disabled = true;
            }
            setStatus('La configuración de notificaciones no está disponible.', 'error');
            return;
        }

        const publicKey = pushConfig.publicKey;
        const subscriptionEndpoint = pushConfig.subscriptionEndpoint;
        const testEndpoint = pushConfig.testEndpoint || '';
        const testAllEndpoint = pushConfig.testAllEndpoint || '';
        const testIcon = pushConfig.testIcon || '';
        const initialSubscribed = Boolean(pushConfig.isSubscribed);
        const serviceWorkerUrl = pushConfig.serviceWorker;
        const restNonce = restConfig.nonce || '';

        let isActive = initialSubscribed;
        let isProcessing = false;

        const syncTestButtons = () => {
            [testButton, broadcastButton].forEach((testControl) => {
                if (!testControl) {
                    return;
                }
                const isBroadcast = testControl === broadcastButton;
                const isSingle = testControl === testButton;
                const missingEndpoint = (isBroadcast && !testAllEndpoint) || (isSingle && !testEndpoint);
                const shouldDisable = !isActive || isProcessing || missingEndpoint;
                testControl.disabled = shouldDisable;
                if (!shouldDisable) {
                    testControl.removeAttribute('disabled');
                }
            });
        };

        const updateControls = (active) => {
            isActive = active;

            if (!isProcessing) {
                button.disabled = false;
                button.removeAttribute('disabled');
            }

            if (label) {
                label.textContent = active ? 'Desactivar notificaciones' : 'Activar notificaciones';
            } else {
                button.textContent = active ? 'Desactivar notificaciones' : 'Activar notificaciones';
            }

            syncTestButtons();
        };

        const setProcessing = (processing) => {
            isProcessing = processing;
            button.disabled = processing;
            if (!processing) {
                button.removeAttribute('disabled');
            }
            syncTestButtons();
        };

        button.disabled = false;
        button.removeAttribute('disabled');

        if (isActive) {
            updateControls(true);
            setStatus('Las notificaciones del navegador están activas en este dispositivo.', 'success');
        } else {
            syncTestButtons();
        }

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
                // eslint-disable-next-line no-console
                console.error('GO360 push local notification error', notificationError);
            }
        };

        const refreshUI = async () => {
            try {
                const registration = await getRegistration(serviceWorkerUrl, false);
                if (!registration) {
                    updateControls(false);
                    return;
                }
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    updateControls(true);
                    setStatus('Las notificaciones del navegador están activas en este dispositivo.', 'success');
                } else {
                    updateControls(false);
                    setStatus('Pulsa “Activar notificaciones” para empezar a recibir avisos.', 'info');
                }
            } catch (error) {
                setStatus('No se pudo comprobar el estado de las notificaciones.', 'warning');
            }
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

            if (!response.ok) {
                throw new Error('Request failed');
            }
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
                throw new Error('Request failed');
            }
        };

        const requestPermissionAndSubscribe = async () => {
            try {
                setProcessing(true);
                setStatus('Solicitando permisos…', 'info');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    setStatus('Debes permitir las notificaciones en el navegador.', 'warning');
                    updateControls(false);
                    setProcessing(false);
                    return;
                }

                const registration = await getRegistration(serviceWorkerUrl, true);
                const existing = await registration.pushManager.getSubscription();
                if (existing) {
                    await sendSubscription(existing);
                    setStatus('Notificaciones activadas correctamente.', 'success');
                    updateControls(true);
                    setProcessing(false);
                    return;
                }

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                });
                await sendSubscription(subscription);
                setStatus('Notificaciones activadas correctamente.', 'success');
                updateControls(true);
            } catch (error) {
                setStatus('No se pudieron activar las notificaciones. Comprueba la consola.', 'error');
                // eslint-disable-next-line no-console
                console.error('GO360 push error', error);
            } finally {
                setProcessing(false);
            }
        };

        const unsubscribe = async () => {
            try {
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
                setStatus('Notificaciones desactivadas correctamente.', 'success');
                updateControls(false);
            } catch (error) {
                setStatus('No se pudieron desactivar las notificaciones. Comprueba la consola.', 'error');
                // eslint-disable-next-line no-console
                console.error('GO360 push error', error);
            } finally {
                setProcessing(false);
            }
        };

        const sendTestNotification = async () => {
            if (!testButton || !testEndpoint) {
                return;
            }

            try {
                setStatus('Enviando notificación de prueba…', 'info');
                testButton.disabled = true;
                const response = await fetch(testEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce,
                    },
                    body: JSON.stringify({}),
                });
                if (!response.ok) {
                    throw new Error('Request failed');
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
                // eslint-disable-next-line no-console
                console.error('GO360 push error', error);
            } finally {
                syncTestButtons();
                if (testButton && !testButton.disabled && isActive && testEndpoint) {
                    testButton.removeAttribute('disabled');
                }
            }
        };

        const sendBroadcastTest = async () => {
            if (!broadcastButton || !testAllEndpoint) {
                return;
            }

            try {
                setStatus('Enviando notificación de prueba a todos los dispositivos…', 'info');
                broadcastButton.disabled = true;
                const response = await fetch(testAllEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce,
                    },
                    body: JSON.stringify({}),
                });
                if (!response.ok) {
                    throw new Error('Request failed');
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
                // eslint-disable-next-line no-console
                console.error('GO360 push error', error);
            } finally {
                syncTestButtons();
                if (broadcastButton && !broadcastButton.disabled && isActive && testAllEndpoint) {
                    broadcastButton.removeAttribute('disabled');
                }
            }
        };

        button.addEventListener('click', () => {
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
