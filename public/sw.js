/**
 * the gist. — Service Worker
 * - /assets/* (Vite 해시 번들): network-first + offline cache fallback
 * - 그 외: 네트워크 통과 (뉴스 HTML/API 신선도)
 * v3: stale-while-revalidate 제거 — PWA가 옛 해시 번들을 즉시 반환하던 문제 수정
 */
const ASSETS_CACHE = 'gist-assets-v3'
const LEGACY_CACHES = ['gist-assets-v1', 'gist-assets-v2']
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
    caches.open(ASSETS_CACHE).then(async (cache) => {
      try {
        const response = await fetch(event.request)
        if (response.ok) {
          cache.put(event.request, response.clone())
        }
        return response
      } catch {
        const cached = await cache.match(event.request)
        if (cached) return cached
        throw new Error('asset fetch failed')
      }
    }),
  )
})
