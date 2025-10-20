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

    const getRegistration = async (serviceWorkerUrl) => {
        const existing = await navigator.serviceWorker.getRegistration();
        if (existing) {
            return existing;
        }
        return navigator.serviceWorker.register(serviceWorkerUrl, { scope: '/' });
    };

    document.addEventListener('DOMContentLoaded', () => {
        const card = document.querySelector('[data-notifications-card]');
        if (!card) {
            return;
        }

        const button = card.querySelector('[data-notifications-request]');
        const status = card.querySelector('[data-notifications-status]');
        const accountConfig = window.go360Account || {};
        const pushConfig = accountConfig.push || {};
        const restConfig = accountConfig.rest || {};

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
            setStatus('Tu navegador no soporta notificaciones push.', 'error');
            return;
        }

        if (!window.isSecureContext) {
            button.disabled = true;
            setStatus('Accede mediante HTTPS para activar las notificaciones.', 'error');
            return;
        }

        if (!pushConfig.publicKey || !pushConfig.subscriptionEndpoint || !pushConfig.serviceWorker) {
            button.disabled = true;
            setStatus('La configuración de notificaciones no está disponible.', 'error');
            return;
        }

        const publicKey = pushConfig.publicKey;
        const subscriptionEndpoint = pushConfig.subscriptionEndpoint;
        const serviceWorkerUrl = pushConfig.serviceWorker;
        const restNonce = restConfig.nonce || '';

        const refreshUI = async () => {
            try {
                const registration = await navigator.serviceWorker.getRegistration();
                if (!registration) {
                    button.textContent = 'Activar notificaciones';
                    return;
                }
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    button.textContent = 'Notificaciones activas';
                    button.disabled = false;
                    setStatus('Recibirás avisos cuando haya novedades.', 'success');
                } else {
                    button.textContent = 'Activar notificaciones';
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

        const requestPermissionAndSubscribe = async () => {
            try {
                setStatus('Solicitando permisos…', 'info');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    setStatus('Debes permitir las notificaciones en el navegador.', 'warning');
                    return;
                }

                const registration = await getRegistration(serviceWorkerUrl);
                const existing = await registration.pushManager.getSubscription();
                if (existing) {
                    await sendSubscription(existing);
                    setStatus('Notificaciones activadas correctamente.', 'success');
                    button.textContent = 'Notificaciones activas';
                    return;
                }

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                });
                await sendSubscription(subscription);
                setStatus('Notificaciones activadas correctamente.', 'success');
                button.textContent = 'Notificaciones activas';
            } catch (error) {
                setStatus('No se pudieron activar las notificaciones. Comprueba la consola.', 'error');
                // eslint-disable-next-line no-console
                console.error('GO360 push error', error);
            }
        };

        button.addEventListener('click', () => {
            requestPermissionAndSubscribe();
        });

        refreshUI();
    });
})();
