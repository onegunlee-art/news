import { useCallback, useMemo, useState } from 'react'
import type { DiscoveryEdition } from '../types'

const MAX_ARCHIVE_DAYS = 30

export function useSwipeNavigation(editions: DiscoveryEdition[], initialDate: string) {
  const sortedDates = useMemo(() => {
    return [...editions]
      .map((e) => e.edition_date)
      .sort((a, b) => (a < b ? 1 : -1))
  }, [editions])

  const [currentDate, setCurrentDate] = useState(initialDate)
  const [changeIdx, setChangeIdx] = useState(0)
  const [boundaryMessage, setBoundaryMessage] = useState<string | null>(null)

  const dateIndex = sortedDates.indexOf(currentDate)

  const goToPastDate = useCallback(() => {
    setBoundaryMessage(null)
    const idx = sortedDates.indexOf(currentDate)
    if (idx < 0 || idx >= sortedDates.length - 1) {
      setBoundaryMessage('더 이상 과거 변화가 없습니다')
      return
    }
    const nextDate = sortedDates[idx + 1]
    const diffDays = Math.abs(
      (new Date(currentDate).getTime() - new Date(nextDate).getTime()) / (1000 * 60 * 60 * 24),
    )
    if (diffDays > MAX_ARCHIVE_DAYS) {
      setBoundaryMessage('30일 이전 아카이브는 없습니다')
      return
    }
    setCurrentDate(nextDate)
    setChangeIdx(0)
  }, [currentDate, sortedDates])

  const goToRecentDate = useCallback(() => {
    setBoundaryMessage(null)
    const idx = sortedDates.indexOf(currentDate)
    if (idx <= 0) {
      setBoundaryMessage('이미 최신 날짜입니다')
      return
    }
    setCurrentDate(sortedDates[idx - 1])
    setChangeIdx(0)
  }, [currentDate, sortedDates])

  const nextChange = useCallback((total: number) => {
    if (total <= 0) return
    setChangeIdx((i) => (i + 1) % total)
  }, [])

  const prevChange = useCallback((total: number) => {
    if (total <= 0) return
    setChangeIdx((i) => (i - 1 + total) % total)
  }, [])

  const setDate = useCallback((date: string) => {
    setCurrentDate(date)
    setChangeIdx(0)
    setBoundaryMessage(null)
  }, [])

  return {
    currentDate,
    changeIdx,
    boundaryMessage,
    dateIndex,
    sortedDates,
    goToPastDate,
    goToRecentDate,
    nextChange,
    prevChange,
    setDate,
    setChangeIdx,
  }
}

export function useTouchSwipe(handlers: {
  onSwipeUp?: () => void
  onSwipeDown?: () => void
  onSwipeLeft?: () => void
  onSwipeRight?: () => void
}) {
  const onTouchEnd = useCallback(
    (start: { x: number; y: number } | null, end: { x: number; y: number }) => {
      if (!start) return
      const dx = end.x - start.x
      const dy = end.y - start.y
      const absX = Math.abs(dx)
      const absY = Math.abs(dy)
      const threshold = 40
      if (absX < threshold && absY < threshold) return
      if (absX > absY) {
        if (dx < 0) handlers.onSwipeLeft?.()
        else handlers.onSwipeRight?.()
      } else {
        if (dy < 0) handlers.onSwipeUp?.()
        else handlers.onSwipeDown?.()
      }
    },
    [handlers],
  )

  return { onTouchEnd }
}
