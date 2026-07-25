import { useCallback, useMemo, useState } from 'react'
import type { DiscoveryEdition } from '../types'

export function usePublicSwipeNavigation(editions: DiscoveryEdition[], initialDate: string) {
  const sortedDates = useMemo(() => {
    return [...editions]
      .map((e) => e.edition_date)
      .sort((a, b) => (a < b ? 1 : -1))
  }, [editions])

  const [currentDate, setCurrentDate] = useState(initialDate)
  const [changeIdx, setChangeIdx] = useState(0)
  const [boundaryMessage, setBoundaryMessage] = useState<string | null>(null)

  const goToPastDate = useCallback(() => {
    setBoundaryMessage(null)
    const idx = sortedDates.indexOf(currentDate)
    if (idx < 0 || idx >= sortedDates.length - 1) {
      setBoundaryMessage('더 이상 과거 변화가 없습니다')
      return
    }
    setCurrentDate(sortedDates[idx + 1])
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
    sortedDates,
    goToPastDate,
    goToRecentDate,
    nextChange,
    prevChange,
    setDate,
    setChangeIdx,
  }
}
