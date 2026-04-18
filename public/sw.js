/**
 * the gist. — 최소 Service Worker (PWA 설치 요건 충족용)
 *
 * - 캐싱 없음: 네트워크 통과 (뉴스 사이트 특성상 콘텐츠 신선도 우선)
 * - fetch 리스너 존재 == Chrome의 installability 요건 충족
 * - 과거 kill-switch SW 사용자: 이 SW로 자연 교체됨
 */
const SW_VERSION = 'v1.0.0'

self.addEventListener('install', () => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('fetch', () => {
  // 명시적 pass-through. 브라우저 기본 처리.
})
