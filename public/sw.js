/**
 * the gist. — Service Worker
 * - /assets/* (Vite 해시 번들): stale-while-revalidate 캐시로 재방문 즉시 로드
 * - 그 외: 네트워크 통과 (뉴스 HTML/API 신신도)
 * v2: 캐시 버전 bump — PWA 모바일 옛 번들 방지
 */
const ASSETS_CACHE = 'gist-assets-v2'
const LEGACY_CACHES = ['gist-assets-v1']
const ASSETS_PREFIX = '/assets/'

self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => LEGACY_CACHES.includes(key) || (key.startsWith('gist-assets-') && key !== ASSETS_CACHE))
          .map((key) => caches.delete(key)),
      ),
    ).then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)
  if (url.origin !== self.location.origin) return
  if (!url.pathname.startsWith(ASSETS_PREFIX)) return
  if (event.request.method !== 'GET') return

  event.respondWith(
    caches.open(ASSETS_CACHE).then((cache) =>
      cache.match(event.request).then((cached) => {
        const networkPromise = fetch(event.request).then((response) => {
          if (response.ok) {
            cache.put(event.request, response.clone())
          }
          return response
        })
        if (cached) {
          networkPromise.catch(() => {})
          return cached
        }
        return networkPromise
      }),
    ),
  )
})
