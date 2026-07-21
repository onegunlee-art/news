/**
 * the gist. — Service Worker
 * - /assets/* (Vite 해시 번들): network-first + offline cache fallback
 * - 그 외: 네트워크 통과 (뉴스 HTML/API 신선도)
 * v4: ASSETS_CACHE를 빌드마다 치환 — 옛 해시 번들이 Cache Storage에 영구 잔존하던 문제 수정
 * ASSETS_CACHE placeholder는 vite build 시 buildVersion 타임스탬프로 치환됨
 */
<<<<<<< HEAD
const ASSETS_CACHE = 'gist-assets-1784595674861'
=======
const ASSETS_CACHE = 'gist-assets-1784595425254'
>>>>>>> feat/edu-mobile-board-strip
const ASSETS_PREFIX = '/assets/'

self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('gist-assets-') && key !== ASSETS_CACHE)
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
        // 404 등 non-ok는 cache fallback 없이 그대로 반환
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
