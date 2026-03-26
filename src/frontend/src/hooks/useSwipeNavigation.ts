import { useState, useRef, useEffect, useCallback, type RefObject } from 'react'

const AXIS_THRESHOLD_PX = 10

function getSwipeThresholdPx(): number {
  if (typeof window === 'undefined') return 80
  return Math.max(80, window.innerWidth * 0.2)
}

export interface UseSwipeNavigationOptions {
  /** false면 터치 리스너를 붙이지 않음 */
  enabled: boolean
  hasPrev: boolean
  hasNext: boolean
  onSwipePrev: () => void
  onSwipeNext: () => void
}

export interface UseSwipeNavigationResult {
  /** transform·touch-action용 래퍼에 ref 부착 */
  containerRef: RefObject<HTMLDivElement | null>
  /** 드래그 중 수평 이동(px) */
  offsetX: number
  /** 가로 축으로 잠긴 드래그 중 */
  isSwiping: boolean
  /** 스냅 백 시에만 설정되는 CSS transition */
  cssTransition: string | undefined
}

/**
 * 기사 상세 등: 좌우 스와이프로 이전/다음으로 이동.
 * - 축 잠금: 먼저 10px 이상 움직인 쪽이 가로면 가로만 처리, 세로면 스와이프 무시
 * - 말단 없으면 rubber-band (×0.2)
 * - 임계값: max(80px, 뷰포트 20%)
 */
export function useSwipeNavigation({
  enabled,
  hasPrev,
  hasNext,
  onSwipePrev,
  onSwipeNext,
}: UseSwipeNavigationOptions): UseSwipeNavigationResult {
  const [offsetX, setOffsetX] = useState(0)
  const [cssTransition, setCssTransition] = useState<string | undefined>(undefined)
  const [isSwiping, setIsSwiping] = useState(false)

  const containerRef = useRef<HTMLDivElement | null>(null)

  const onSwipePrevRef = useRef(onSwipePrev)
  const onSwipeNextRef = useRef(onSwipeNext)
  const hasPrevRef = useRef(hasPrev)
  const hasNextRef = useRef(hasNext)

  onSwipePrevRef.current = onSwipePrev
  onSwipeNextRef.current = onSwipeNext
  hasPrevRef.current = hasPrev
  hasNextRef.current = hasNext

  const lastOffsetRef = useRef(0)

  const touchRef = useRef({
    pointerDown: false,
    startX: 0,
    startY: 0,
    lockX: 0,
    axis: 'none' as 'none' | 'h' | 'v',
  })

  const endSwipeDrag = useCallback(() => {
    touchRef.current.pointerDown = false
    touchRef.current.axis = 'none'
    setIsSwiping(false)
  }, [])

  useEffect(() => {
    const el = containerRef.current
    if (!el || !enabled) return

    const onStart = (e: TouchEvent) => {
      if (e.touches.length !== 1) return
      const x = e.touches[0].clientX
      const y = e.touches[0].clientY
      touchRef.current = {
        pointerDown: true,
        startX: x,
        startY: y,
        lockX: x,
        axis: 'none',
      }
      lastOffsetRef.current = 0
      setCssTransition(undefined)
      setOffsetX(0)
    }

    const onMove = (e: TouchEvent) => {
      if (!touchRef.current.pointerDown || e.touches.length !== 1) return
      const t = touchRef.current
      const cx = e.touches[0].clientX
      const cy = e.touches[0].clientY
      const dx = cx - t.startX
      const dy = cy - t.startY

      if (t.axis === 'none') {
        if (Math.abs(dx) < AXIS_THRESHOLD_PX && Math.abs(dy) < AXIS_THRESHOLD_PX) return
        if (Math.abs(dx) > Math.abs(dy)) {
          t.axis = 'h'
          t.lockX = cx
          setIsSwiping(true)
        } else {
          t.axis = 'v'
          return
        }
      }
      if (t.axis === 'v') return

      e.preventDefault()

      let delta = cx - t.lockX
      if (delta > 0 && !hasPrevRef.current) delta *= 0.2
      if (delta < 0 && !hasNextRef.current) delta *= 0.2

      lastOffsetRef.current = delta
      setOffsetX(delta)
    }

    const finishHorizontal = () => {
      const delta = lastOffsetRef.current
      const th = getSwipeThresholdPx()

      if (delta < -th && hasNextRef.current) {
        endSwipeDrag()
        setOffsetX(0)
        setCssTransition(undefined)
        onSwipeNextRef.current()
        return
      }
      if (delta > th && hasPrevRef.current) {
        endSwipeDrag()
        setOffsetX(0)
        setCssTransition(undefined)
        onSwipePrevRef.current()
        return
      }

      setCssTransition('transform 0.24s cubic-bezier(0.33, 1, 0.68, 1)')
      setOffsetX(0)
      lastOffsetRef.current = 0
      endSwipeDrag()
    }

    const onEnd = () => {
      if (!touchRef.current.pointerDown) return
      if (touchRef.current.axis === 'h') {
        finishHorizontal()
      } else {
        touchRef.current.pointerDown = false
        touchRef.current.axis = 'none'
      }
    }

    const onCancel = () => {
      if (!touchRef.current.pointerDown) return
      touchRef.current.pointerDown = false
      touchRef.current.axis = 'none'
      lastOffsetRef.current = 0
      setCssTransition('transform 0.2s ease-out')
      setOffsetX(0)
      setIsSwiping(false)
    }

    el.addEventListener('touchstart', onStart, { passive: true })
    el.addEventListener('touchmove', onMove, { passive: false })
    el.addEventListener('touchend', onEnd)
    el.addEventListener('touchcancel', onCancel)

    return () => {
      el.removeEventListener('touchstart', onStart)
      el.removeEventListener('touchmove', onMove)
      el.removeEventListener('touchend', onEnd)
      el.removeEventListener('touchcancel', onCancel)
    }
  }, [enabled, endSwipeDrag])

  return {
    containerRef,
    offsetX,
    isSwiping,
    cssTransition,
  }
}
