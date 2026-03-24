/* Minimal service worker for PWA installability + TWA Digital Asset Links.
 * Network-first: avoids stale SPA shells for navigations. */
self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)
  if (url.origin !== self.location.origin) {
    return
  }
  event.respondWith(
    fetch(event.request).catch(() => {
      if (event.request.mode === 'navigate') {
        return fetch('/index.html', { cache: 'no-store' })
      }
      throw new Error('network-error')
    }),
  )
})
