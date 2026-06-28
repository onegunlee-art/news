import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import {
  clearEduToken,
  eduApi,
  getEduToken,
  type EduExploreShelf,
  type EduQuestListItem,
} from '../../services/eduApi'
import { eduAuthedTopBarMenu, eduGuestTopBarMenu } from '../../utils/eduTopBarMenu'
import EduQuestCoverHero from '../../components/edu/EduQuestCoverHero'
import EduTopBar from '../../components/edu/EduTopBar'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'

const FRAME_TABS = [
  { id: 'all', label: '전체' },
  { id: 'decision_inquiry', label: '결정 탐구' },
  { id: 'myth_bust', label: 'Myth Bust' },
] as const

export default function EduExplorePage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const shelf = searchParams.get('shelf') ?? ''
  const frame = searchParams.get('frame') ?? 'all'

  const [shelves, setShelves] = useState<EduExploreShelf[]>([])
  const [quests, setQuests] = useState<EduQuestListItem[]>([])
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [streakDays, setStreakDays] = useState(0)
  const authed = !!getEduToken()

  const handleLogout = () => {
    clearEduToken()
    navigate('/edu')
  }

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [catRes, listRes, todayRes] = await Promise.all([
        eduApi.exploreCategories(frame),
        eduApi.listQuests({
          limit: 50,
          frame,
          shelf: shelf || undefined,
        }),
        authed ? eduApi.todayQuest().catch(() => null) : Promise.resolve(null),
      ])
      setStreakDays(todayRes?.tier?.streak_days ?? 0)
      setShelves(catRes.shelves)
      setTotal(catRes.total)
      setQuests(listRes.quests)
    } catch (e) {
      setError(e instanceof Error ? e.message : '불러오기 실패')
    } finally {
      setLoading(false)
    }
  }, [frame, shelf, authed])

  useEffect(() => {
    void load()
  }, [load])

  const setFilter = (patch: { shelf?: string; frame?: string }) => {
    const next = new URLSearchParams(searchParams)
    if ('shelf' in patch) {
      if (patch.shelf) next.set('shelf', patch.shelf)
      else next.delete('shelf')
    }
    if ('frame' in patch) {
      if (patch.frame && patch.frame !== 'all') next.set('frame', patch.frame)
      else next.delete('frame')
    }
    setSearchParams(next, { replace: true })
  }

  const handleStart = async (questId: string) => {
    if (!authed) {
      navigate('/edu')
      return
    }
    try {
      await eduApi.startSession(questId)
      navigate(`/edu/quest?quest_id=${encodeURIComponent(questId)}`)
    } catch (e) {
      setError(e instanceof Error ? e.message : '시작 실패')
    }
  }

  const frameLabel = (f?: string | null) => {
    if (f === 'myth_bust') return 'Myth Bust'
    if (f === 'decision_inquiry') return '결정 탐구'
    return null
  }

  /** P1-2k: entry_mode derive first, quest_frame fallback (behavior 0 vs frame-only) */
  const exploreQuestBadge = (
    q: EduQuestListItem & { entry_mode?: string | null },
  ): string | null => {
    const entryMode = q.entry_mode
    const frame = q.quest_frame ?? ''

    if (entryMode === 'open_response') {
      return 'Myth Bust'
    }
    if (entryMode === 'stance_pick') {
      if (frame === 'decision_inquiry') return '결정 탐구'
      return frameLabel(frame)
    }

    return frameLabel(frame)
  }

  return (
    <div
      className={`min-h-screen ${eduGameClasses.textKo}`}
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <EduTopBar
        streakDays={streakDays}
        menuItems={
          authed
            ? eduAuthedTopBarMenu(handleLogout)
            : eduGuestTopBarMenu(() => navigate('/edu'))
        }
        className="max-w-2xl mx-auto w-full"
      />

      <main className="max-w-2xl mx-auto px-4 py-6 space-y-6 pb-10">
        <section>
          <h1 className="text-xl font-bold mb-1">더 많은 논쟁</h1>
          <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
            주제와 유형으로 골라보세요 · 총 {total}개
          </p>
        </section>

        <section className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setFilter({ shelf: '' })}
            className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${
              shelf === ''
                ? 'text-white'
                : ''
            }`}
            style={
              shelf === ''
                ? { backgroundColor: eduGame.primary, borderColor: eduGame.primary }
                : { borderColor: eduGame.border, color: eduGame.muted }
            }
          >
            전체
          </button>
          {shelves.map((s) => (
            <button
              key={s.shelf_id}
              type="button"
              onClick={() => setFilter({ shelf: s.shelf_id })}
              className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors`}
              style={
                shelf === s.shelf_id
                  ? { backgroundColor: eduGame.primary, borderColor: eduGame.primary, color: '#fff' }
                  : { borderColor: eduGame.border, color: eduGame.muted }
              }
            >
              {s.label}
              {s.count > 0 && <span className="ml-1 opacity-70">({s.count})</span>}
            </button>
          ))}
        </section>

        <section className="flex gap-2 border-b pb-2" style={{ borderColor: eduGame.border }}>
          {FRAME_TABS.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setFilter({ frame: tab.id })}
              className="text-xs pb-2 border-b-2 transition-colors"
              style={{
                borderColor: frame === tab.id ? eduGame.primary : 'transparent',
                color: frame === tab.id ? eduGame.ink : eduGame.muted,
              }}
            >
              {tab.label}
            </button>
          ))}
        </section>

        {loading ? (
          <p className="text-center py-12 text-[#666]">불러오는 중…</p>
        ) : quests.length === 0 ? (
          <p className="text-center py-12 text-[#666]">이 조건에 맞는 논쟁이 없어요.</p>
        ) : (
          <ul className="space-y-3">
            {quests.map((q) => {
              const badge = exploreQuestBadge(q)
              return (
                <li key={q.quest_id}>
                  <button
                    type="button"
                    onClick={() => void handleStart(q.quest_id)}
                    disabled={!authed}
                    className="w-full text-left rounded-2xl border-2 overflow-hidden transition-colors disabled:opacity-60"
                    style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
                  >
                    <EduQuestCoverHero
                      coverImageUrl={q.cover_image_url}
                      questTitle={q.quest_title}
                      hookShort={q.hook_short}
                      timeAnchor={q.time_anchor}
                      variant="card"
                      topicLabel="따질 주제"
                    />
                    <div className="p-4 pt-3">
                    <div className="flex flex-wrap items-center gap-2 mb-2">
                      {q.shelf_label && (
                        <span className="text-[10px] px-2 py-0.5 rounded bg-[#2a2a2a] text-[#aaa]">
                          {q.shelf_label}
                        </span>
                      )}
                      {badge && (
                        <span className="text-[10px] px-2 py-0.5 rounded border border-[#444] text-[#888]">
                          {badge}
                        </span>
                      )}
                      {q.is_live && (
                        <span className="text-[10px] px-2 py-0.5 rounded bg-[#E8521C]/20 text-[#E8521C]">
                          LIVE
                        </span>
                      )}
                      {q.completed && (
                        <span className="text-[10px] text-[#4CAF50] ml-auto">완료</span>
                      )}
                    </div>
                    {q.lens_label && (
                      <p className="text-xs text-[#E8521C] mb-1">쟁점: {q.lens_label}</p>
                    )}
                    <p className="text-sm font-medium leading-snug">{q.quest_title}</p>
                    {q.conflict_summary && (
                      <p className="text-xs text-[#888] mt-2 line-clamp-2">{q.conflict_summary}</p>
                    )}
                    {q.time_anchor && (
                      <p className="text-[10px] text-[#666] mt-2">{q.time_anchor}</p>
                    )}
                    {!authed && (
                      <p className="text-xs text-[#666] mt-2">시작하려면 홈에서 로그인하세요</p>
                    )}
                    </div>
                  </button>
                </li>
              )
            })}
          </ul>
        )}

        {error && (
          <p className="text-sm text-red-400 border border-red-900 bg-red-900/20 p-3 rounded">
            {error}
          </p>
        )}
      </main>
    </div>
  )
}
