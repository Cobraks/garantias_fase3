const DEFAULT_ICON_URL = new URL('../images/logo-notify.png', self.location.href).href;

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  if (!event.data) {
    return;
  }

  const payload = event.data.json ? event.data.json() : {};
  const title = payload.title || 'Notificación';
  const options = {
    body: payload.body || '',
    icon: DEFAULT_ICON_URL,
    badge: DEFAULT_ICON_URL,
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
  // Placeholder for analytics or synchronization.
});
