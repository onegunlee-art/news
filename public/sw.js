/**
 * PWA 임시 비활성화: 이 파일이 활성화되면 자신을 해제하고 캐시를 비웁니다.
 * 이전에 등록된 클라이언트는 다음 방문 시 일반 네트워크 로드로 동작합니다.
 */
self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      try {
        const keys = await caches.keys()
        await Promise.all(keys.map((k) => caches.delete(k)))
      } catch {
        /* ignore */
      }
      try {
        await self.registration.unregister()
      } catch {
        /* ignore */
      }
    })(),
  )
})
