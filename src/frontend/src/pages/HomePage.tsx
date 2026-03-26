import { useState, useEffect, useRef, useLayoutEffect, useCallback } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { useMenuConfig } from '../hooks/useMenuConfig'
import { useInfiniteNewsList, usePopularNews } from '../hooks/useNews'
import HomeTabFeed from '../components/Home/HomeTabFeed'

const SCROLL_SAVE_KEY = 'home_scroll_'

export default function HomePage() {
  const location = useLocation()
  const navigate = useNavigate()
  const { tabs, tabLabels, tabToCategory, subCategoryToLabel, specialBadgeText } = useMenuConfig()
  const popularLabel = tabs.find((t) => t.key === 'popular')?.label ?? '인기'
  const specialLabel = tabs.find((t) => t.key === 'special')?.label ?? '특집'

  const [activeTab, setActiveTab] = useState<string>(() => {
    const s = (location.state as { restoreTab?: string } | null) ?? null
    const tab = s?.restoreTab
    return tab && tabLabels.includes(tab) ? tab : tabLabels[0] ?? '최신'
  })

  const carouselRef = useRef<HTMLDivElement>(null)
  const carouselInitTabRef = useRef(activeTab)
  const programmaticScrollRef = useRef(false)
  const scrollSyncRaf = useRef(0)
  const didInitCarouselScrollRef = useRef(false)

  const activeIndex = tabLabels.indexOf(activeTab) >= 0 ? tabLabels.indexOf(activeTab) : 0

  const restoreTab = (location.state as { restoreTab?: string } | null)?.restoreTab
  const isRestorePopular = restoreTab === popularLabel
  const restoreCategory = restoreTab
    ? (tabToCategory[restoreTab] ?? undefined)
    : undefined
  const { isLoading: restoreLoadingInfinite } = useInfiniteNewsList(
    restoreCategory,
    20,
    !!restoreTab && !isRestorePopular
  )
  const { isLoading: restoreLoadingPopular } = usePopularNews(!!restoreTab && isRestorePopular)
  const restoreLoading =
    !!restoreTab && (isRestorePopular ? restoreLoadingPopular : restoreLoadingInfinite)

  useLayoutEffect(() => {
    const el = carouselRef.current
    if (!el || tabLabels.length === 0) return
    if (didInitCarouselScrollRef.current) return
    const i = tabLabels.indexOf(carouselInitTabRef.current)
    const idx = i >= 0 ? i : 0
    const w = el.clientWidth
    if (w <= 0) return
    el.scrollLeft = idx * w
    didInitCarouselScrollRef.current = true
  }, [tabLabels])

  const onCarouselScroll = useCallback(() => {
    if (programmaticScrollRef.current) return
    if (scrollSyncRaf.current) return
    scrollSyncRaf.current = requestAnimationFrame(() => {
      scrollSyncRaf.current = 0
      const el = carouselRef.current
      if (!el || tabLabels.length === 0) return
      const w = el.clientWidth
      if (w <= 0) return
      const i = Math.round(el.scrollLeft / w)
      const clamped = Math.max(0, Math.min(tabLabels.length - 1, i))
      const tab = tabLabels[clamped]
      setActiveTab((prev) => {
        if (prev === tab) return prev
        window.scrollTo(0, 0)
        return tab
      })
    })
  }, [tabLabels])

  const onTabButtonClick = useCallback(
    (tab: string) => {
      if (tab === activeTab) return
      window.scrollTo(0, 0)
      const idx = tabLabels.indexOf(tab)
      const el = carouselRef.current
      if (el && idx >= 0) {
        programmaticScrollRef.current = true
        const w = el.clientWidth
        el.scrollTo({ left: idx * w, behavior: 'smooth' })
        setActiveTab(tab)
        window.setTimeout(() => {
          programmaticScrollRef.current = false
        }, 420)
      } else {
        setActiveTab(tab)
      }
    },
    [activeTab, tabLabels]
  )

  useEffect(() => {
    let raf = 0
    const onScroll = () => {
      if (raf) return
      raf = requestAnimationFrame(() => {
        raf = 0
        try {
          sessionStorage.setItem(SCROLL_SAVE_KEY + activeTab, String(window.scrollY))
        } catch {
          // ignore
        }
      })
    }
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      window.removeEventListener('scroll', onScroll)
      if (raf) cancelAnimationFrame(raf)
    }
  }, [activeTab])

  const didRestoreRef = useRef(false)
  useEffect(() => {
    if (!restoreTab) {
      didRestoreRef.current = false
      return
    }
    if (didRestoreRef.current) return
    if (restoreLoading) return

    didRestoreRef.current = true
    const raw = sessionStorage.getItem(SCROLL_SAVE_KEY + restoreTab)
    const y = raw ? parseInt(raw, 10) : 0
    if (!Number.isFinite(y) || y <= 0) {
      navigate('/', { replace: true, state: {} })
      return
    }
    const id = requestAnimationFrame(() => {
      window.scrollTo(0, y)
      navigate('/', { replace: true, state: {} })
    })
    return () => cancelAnimationFrame(id)
  }, [restoreTab, restoreLoading, navigate])

  return (
    <div className="min-h-screen bg-page pb-8">
      <div className="sticky top-14 bg-page-secondary z-30 border-b border-page">
        <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 md:px-8 lg:px-12 xl:px-16">
          <div className="flex">
            {tabLabels.map((tab) => (
              <button
                key={tab}
                type="button"
                onClick={() => onTabButtonClick(tab)}
                className={`flex-1 py-3 text-sm font-medium transition-colors relative flex flex-col items-center justify-center gap-0 ${
                  activeTab === tab
                    ? 'text-page'
                    : 'text-page-secondary hover:text-page'
                }`}
              >
                {tab === specialLabel ? (
                  <span className="relative inline-block">
                    <span className="absolute -top-2 right-0 translate-x-1 rounded-full bg-primary-500 px-1 py-0.5 text-[8px] font-medium leading-none text-white whitespace-nowrap">
                      {specialBadgeText}
                    </span>
                    {tab}
                  </span>
                ) : (
                  tab
                )}
                {activeTab === tab && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-[var(--text-primary)]" />
                )}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto pt-4 md:pt-5 bg-page">
        <div
          ref={carouselRef}
          role="tabpanel"
          aria-label="카테고리별 기사 목록"
          className="flex w-full overflow-x-auto overflow-y-visible overscroll-x-contain snap-x snap-mandatory scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          onScroll={onCarouselScroll}
        >
          {tabLabels.map((tab, i) => (
            <div
              key={tab}
              className="min-w-full shrink-0 snap-start snap-always box-border px-4 md:px-8 lg:px-12 xl:px-16"
            >
              <HomeTabFeed
                tabLabel={tab}
                popularLabel={popularLabel}
                category={tabToCategory[tab] ?? undefined}
                subCategoryToLabel={subCategoryToLabel}
                fetchEnabled={tabLabels.length > 0 && Math.abs(i - activeIndex) <= 1}
              />
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
