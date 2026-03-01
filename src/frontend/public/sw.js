/**
 * Service Worker - Web Push 알림 수신
 */
self.addEventListener('push', (event) => {
  let data = { title: 'The Gist', body: '새 글이 올라왔습니다.', url: '/' }
  if (event.data) {
    try {
      const parsed = JSON.parse(event.data.text())
      data = { ...data, ...parsed }
    } catch {}
  }
  const options = {
    body: data.body || '새 글이 올라왔습니다.',
    icon: '/favicon.svg',
    badge: '/favicon.svg',
    tag: 'thegist-new-article',
    requireInteraction: false,
    data: { url: data.url || '/' },
  }
  event.waitUntil(
    self.registration.showNotification(data.title || 'The Gist', options)
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const url = event.notification.data?.url || '/'
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      for (const client of list) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.navigate(url)
          return client.focus()
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(self.location.origin + (url.startsWith('/') ? url : '/' + url))
      }
    })
  )
})
