self.addEventListener('install', (event) => {
  console.info('[GO360 Push SW] install');
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  console.info('[GO360 Push SW] activate');
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  if (!event.data) {
    console.warn('[GO360 Push SW] push event without data');
    return;
  }

  let payload = {};
  try {
    payload = event.data.json ? event.data.json() : event.data.text();
  } catch (error) {
    console.error('[GO360 Push SW] payload parse error', error);
    payload = {};
  }
  if (typeof payload === 'string') {
    try {
      payload = JSON.parse(payload);
    } catch (error) {
      console.error('[GO360 Push SW] payload JSON parse error', error);
      payload = {};
    }
  }

  console.info('[GO360 Push SW] push payload', payload);
  const title = payload.title || 'Notificación';
  const options = {
    body: payload.body || '',
    icon: payload.icon || '',
    badge: payload.badge || payload.icon || '',
    data: {
      url: payload.link || '',
      notificationId: payload.id || 0,
    },
    actions: Array.isArray(payload.actions)
      ? payload.actions.map((action) => ({
          action: action.action || 'open',
          title: action.title || '',
        }))
      : [],
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  console.info('[GO360 Push SW] notification click', event.notification && event.notification.data);
  event.notification.close();
  const targetUrl = event.notification.data && event.notification.data.url;
  if (!targetUrl) {
    return;
  }

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      const client = clients.find((c) => c.url === targetUrl);
      if (client) {
        return client.focus();
      }
      return self.clients.openWindow(targetUrl);
    })
  );
});

self.addEventListener('notificationclose', (event) => {
  console.info('[GO360 Push SW] notification closed', event.notification && event.notification.data);
  // Placeholder for analytics or synchronization.
});
