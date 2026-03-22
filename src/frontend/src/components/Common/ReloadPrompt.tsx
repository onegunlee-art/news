import { useEffect, useState } from 'react'

export default function ReloadPrompt() {
  const [show, setShow] = useState(false)
  const [waitingSW, setWaitingSW] = useState<ServiceWorker | null>(null)

  useEffect(() => {
    if (!('serviceWorker' in navigator)) return

    navigator.serviceWorker.ready.then((registration) => {
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing
        if (!newWorker) return

        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            setWaitingSW(newWorker)
            setShow(true)
          }
        })
      })
    })
  }, [])

  const handleUpdate = () => {
    if (waitingSW) {
      waitingSW.postMessage({ type: 'SKIP_WAITING' })
      setShow(false)
      window.location.reload()
    }
  }

  if (!show) return null

  return (
    <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-[9999] bg-gray-900 text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-sm">
      <span>새 버전이 있습니다.</span>
      <button
        onClick={handleUpdate}
        className="bg-primary-500 hover:bg-primary-600 text-white px-3 py-1 rounded-lg text-xs font-medium transition-colors"
      >
        업데이트
      </button>
      <button
        onClick={() => setShow(false)}
        className="text-gray-400 hover:text-white text-xs"
      >
        닫기
      </button>
    </div>
  )
}
