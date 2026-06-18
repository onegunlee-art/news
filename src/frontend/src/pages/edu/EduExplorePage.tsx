import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import {
  eduApi,
  getEduToken,
  type EduExploreShelf,
  type EduQuestListItem,
} from '../../services/eduApi'

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
  const authed = !!getEduToken()

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [catRes, listRes] = await Promise.all([
        eduApi.exploreCategories(frame),
        eduApi.listQuests({
          limit: 50,
          frame,
          shelf: shelf || undefined,
        }),
      ])
      setShelves(catRes.shelves)
      setTotal(catRes.total)
      setQuests(listRes.quests)
    } catch (e) {
      setError(e instanceof Error ? e.message : '불러오기 실패')
    } finally {
      setLoading(false)
    }
  }, [frame, shelf])

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

  return (
    <div className="min-h-screen bg-[#0D0D0D] text-white">
      <header className="border-b border-[#333] px-4 py-4 max-w-2xl mx-auto flex items-center justify-between">
        <Link to="/edu" className="text-xs text-[#888] underline">
          ← 홈
        </Link>
        <span className="text-sm font-medium">논쟁 탐색</span>
        <span className="w-8" />
      </header>

      <main className="max-w-2xl mx-auto px-4 py-6 space-y-6">
        <section>
          <h1 className="text-xl font-bold mb-1">더 많은 논쟁</h1>
          <p className="text-sm text-[#888]">
            주제와 유형으로 골라보세요 · 총 {total}개
          </p>
        </section>

        <section className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setFilter({ shelf: '' })}
            className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${
              shelf === ''
                ? 'bg-[#E8521C] border-[#E8521C] text-white'
                : 'border-[#444] text-[#aaa] hover:border-[#666]'
            }`}
          >
            전체
          </button>
          {shelves.map((s) => (
            <button
              key={s.shelf_id}
              type="button"
              onClick={() => setFilter({ shelf: s.shelf_id })}
              className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${
                shelf === s.shelf_id
                  ? 'bg-[#E8521C] border-[#E8521C] text-white'
                  : 'border-[#444] text-[#aaa] hover:border-[#666]'
              }`}
            >
              {s.label}
              {s.count > 0 && <span className="ml-1 opacity-70">({s.count})</span>}
            </button>
          ))}
        </section>

        <section className="flex gap-2 border-b border-[#333] pb-2">
          {FRAME_TABS.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setFilter({ frame: tab.id })}
              className={`text-xs pb-2 border-b-2 transition-colors ${
                frame === tab.id
                  ? 'border-[#E8521C] text-white'
                  : 'border-transparent text-[#666] hover:text-[#999]'
              }`}
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
              const badge = frameLabel(q.quest_frame)
              return (
                <li key={q.quest_id}>
                  <button
                    type="button"
                    onClick={() => void handleStart(q.quest_id)}
                    disabled={!authed}
                    className="w-full text-left border border-[#333] rounded-lg p-4 bg-[#1a1a1a] hover:border-[#555] disabled:opacity-60 transition-colors"
                  >
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
