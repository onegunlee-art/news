import { useCallback, useRef } from 'react'

const THRESHOLD = 40

export function usePointerSwipe(handlers: {
  onSwipeUp?: () => void
  onSwipeDown?: () => void
  onSwipeLeft?: () => void
  onSwipeRight?: () => void
  enabled?: boolean
}) {
  const start = useRef<{ x: number; y: number } | null>(null)
  const enabled = handlers.enabled !== false

  const onStart = useCallback((x: number, y: number) => {
    if (!enabled) return
    start.current = { x, y }
  }, [enabled])

  const onEnd = useCallback((x: number, y: number) => {
    if (!enabled || !start.current) return
    const dx = x - start.current.x
    const dy = y - start.current.y
    start.current = null
    if (Math.abs(dx) < THRESHOLD && Math.abs(dy) < THRESHOLD) return
    if (Math.abs(dx) > Math.abs(dy)) {
      if (dx < 0) handlers.onSwipeLeft?.()
      else handlers.onSwipeRight?.()
    } else {
      if (dy < 0) handlers.onSwipeUp?.()
      else handlers.onSwipeDown?.()
    }
  }, [enabled, handlers])

  const bind = {
    onTouchStart: (e: React.TouchEvent) => onStart(e.touches[0].clientX, e.touches[0].clientY),
    onTouchEnd: (e: React.TouchEvent) => onEnd(e.changedTouches[0].clientX, e.changedTouches[0].clientY),
    onPointerDown: (e: React.PointerEvent) => {
      if (e.pointerType === 'mouse' && e.button !== 0) return
      onStart(e.clientX, e.clientY)
    },
    onPointerUp: (e: React.PointerEvent) => onEnd(e.clientX, e.clientY),
  }

  return bind
}
