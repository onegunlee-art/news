import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import EduCoachLevelIcon from '../../components/edu/EduCoachLevelIcon'
import EduQuestCoverHero from '../../components/edu/EduQuestCoverHero'
import EduQuestDifficultyBadge from '../../components/edu/EduQuestDifficultyBadge'
import EduTopBar from '../../components/edu/EduTopBar'
import { eduCoachLevelByNumber } from '../../constants/eduCoachLevel'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import {
  clearEduToken,
  eduApi,
  getEduToken,
  type EduExploreLevel,
  type EduExploreShelf,
  type EduQuestListItem,
} from '../../services/eduApi'
import { eduAuthedTopBarMenu, eduGuestTopBarMenu } from '../../utils/eduTopBarMenu'

export default function EduExplorePage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const shelf = searchParams.get('shelf') ?? ''
  const levelParam = searchParams.get('level') ?? ''
  const levelFilter = levelParam !== '' ? parseInt(levelParam, 10) : 0

  const [shelves, setShelves] = useState<EduExploreShelf[]>([])
  const [levels, setLevels] = useState<EduExploreLevel[]>([])
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
        eduApi.exploreCategories(),
        eduApi.listQuests({
          limit: 50,
          level: levelFilter >= 1 && levelFilter <= 5 ? levelFilter : undefined,
          shelf: shelf || undefined,
        }),
        authed ? eduApi.todayQuest().catch(() => null) : Promise.resolve(null),
      ])
      setStreakDays(todayRes?.tier?.streak_days ?? 0)
      setShelves(catRes.shelves)
      setLevels(catRes.levels ?? [])
      setTotal(catRes.total)
      setQuests(listRes.quests)
    } catch (e) {
      setError(e instanceof Error ? e.message : '불러오기 실패')
    } finally {
      setLoading(false)
    }
  }, [levelFilter, shelf, authed])

  useEffect(() => {
    void load()
  }, [load])

  const setFilter = (patch: { shelf?: string; level?: number | null }) => {
    const next = new URLSearchParams(searchParams)
    if ('shelf' in patch) {
      if (patch.shelf) next.set('shelf', patch.shelf)
      else next.delete('shelf')
    }
    if ('level' in patch) {
      if (patch.level != null && patch.level >= 1 && patch.level <= 5) {
        next.set('level', String(patch.level))
      } else {
        next.delete('level')
      }
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
            레벨과 주제로 골라보세요 · 총 {total}개
          </p>
        </section>

        <section className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setFilter({ shelf: '' })}
            className="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
            style={
              shelf === ''
                ? { backgroundColor: eduGame.primary, borderColor: eduGame.primary, color: '#fff' }
                : { borderColor: eduGame.border, color: eduGame.muted }
            }
          >
            전체 주제
          </button>
          {shelves.map((s) => (
            <button
              key={s.shelf_id}
              type="button"
              onClick={() => setFilter({ shelf: s.shelf_id })}
              className="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
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

        <section className="flex flex-wrap gap-2 border-b pb-3" style={{ borderColor: eduGame.border }}>
          <button
            type="button"
            onClick={() => setFilter({ level: null })}
            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
            style={
              levelFilter < 1 || levelFilter > 5
                ? { backgroundColor: eduGame.primary, borderColor: eduGame.primary, color: '#fff' }
                : { borderColor: eduGame.border, color: eduGame.muted }
            }
          >
            전체 레벨
          </button>
          {(levels.length > 0
            ? levels
            : [1, 2, 3, 4, 5].map((id) => ({
                id,
                label_ko: eduCoachLevelByNumber(id).label_ko,
                label_en: '',
                count: 0,
              }))
          ).map((lv) => (
            <button
              key={lv.id}
              type="button"
              onClick={() => setFilter({ level: lv.id })}
              className="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
              style={
                levelFilter === lv.id
                  ? { backgroundColor: eduGame.primary, borderColor: eduGame.primary, color: '#fff' }
                  : { borderColor: eduGame.border, color: eduGame.muted }
              }
            >
              <EduCoachLevelIcon level={lv.id} size={14} />
              L{lv.id} {lv.label_ko}
              {lv.count > 0 && <span className="opacity-70">({lv.count})</span>}
            </button>
          ))}
        </section>

        {loading ? (
          <p className="text-center py-12 text-[#666]">불러오는 중…</p>
        ) : quests.length === 0 ? (
          <p className="text-center py-12 text-[#666]">이 조건에 맞는 논쟁이 없어요.</p>
        ) : (
          <ul className="space-y-3">
            {quests.map((q) => (
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
                      <EduQuestDifficultyBadge quest={q} showHint={q.difficulty_level === 1} />
                      {q.recommended_for_you && (
                        <span className="text-[10px] px-2 py-0.5 rounded bg-[#E8521C]/15 text-[#E8521C]">
                          내 레벨
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
                      <p className="text-xs text-[#888] mb-1">{q.lens_label}</p>
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
            ))}
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
