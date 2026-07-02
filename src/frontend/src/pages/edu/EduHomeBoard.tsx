import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import EduStudentProfileHero from '../../components/edu/EduStudentProfileHero'
import EduQuestBoardCard from '../../components/edu/EduQuestBoardCard'
import EduTopBar from '../../components/edu/EduTopBar'
import { eduCoachLevelByNumber, type EduCoachLevelInfo } from '../../constants/eduCoachLevel'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import {
  clearEduToken,
  eduApi,
  getEduStudent,
  type EduQuestListItem,
  type EduStudent,
  type EduTierProgress,
} from '../../services/eduApi'
import {
  EDU_HOME_LEVEL_SECTION_LABELS,
  filterApprovedQuestsForHome,
  partitionHomeBoard,
} from '../../utils/eduHomeBoardSections'
import { eduQuestFlowPath } from '../../constants/eduNarrativeBridge'
import { eduAuthedTopBarMenu } from '../../utils/eduTopBarMenu'

type Props = {
  onLogout: () => void
}

export default function EduHomeBoard({ onLogout }: Props) {
  const navigate = useNavigate()
  const [student] = useState<EduStudent | null>(() => getEduStudent())
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [coachLevel, setCoachLevel] = useState<EduCoachLevelInfo>(() => eduCoachLevelByNumber(1))
  const [quests, setQuests] = useState<EduQuestListItem[]>([])
  const [loading, setLoading] = useState(true)
  const [starting, setStarting] = useState(false)
  const [error, setError] = useState('')

  const sections = useMemo(
    () => partitionHomeBoard(quests, coachLevel.coach_level),
    [quests, coachLevel.coach_level],
  )

  const totalVisible = useMemo(() => {
    const levelTotal = ([1, 2, 3, 4, 5] as const).reduce(
      (n, lv) => n + sections.byLevel[lv].length,
      0,
    )
    return sections.myLevelRecommended.length + sections.newQuests.length + levelTotal
  }, [sections])

  const loadBoard = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [statusRes, listRes] = await Promise.all([
        eduApi.todayQuest(),
        eduApi.listQuests({ limit: 50 }),
      ])
      if (statusRes.tier) setTier(statusRes.tier)
      if (statusRes.coach_level) setCoachLevel(statusRes.coach_level)
      setQuests(filterApprovedQuestsForHome(listRes.quests))
    } catch (e) {
      const msg = e instanceof Error ? e.message : '불러오기 실패'
      setError(msg)
      if (msg.includes('401')) {
        clearEduToken()
        onLogout()
      }
    } finally {
      setLoading(false)
    }
  }, [onLogout])

  useEffect(() => {
    void loadBoard()
  }, [loadBoard])

  const handleStart = async (quest: EduQuestListItem) => {
    setStarting(true)
    setError('')
    try {
      await eduApi.startSession(quest.quest_id)
      navigate(
        eduQuestFlowPath({
          questId: quest.quest_id,
          coachMode: quest.coach_mode,
          questCode: quest.quest_code,
        })
      )
    } catch (e) {
      setError(e instanceof Error ? e.message : '시작 실패')
    } finally {
      setStarting(false)
    }
  }

  const myLevelLabel = coachLevel.label_ko

  return (
    <div
      className={`min-h-screen ${eduGameClasses.textKo}`}
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <EduTopBar
        streakDays={tier?.streak_days ?? 0}
        menuItems={eduAuthedTopBarMenu(onLogout)}
        className="max-w-lg mx-auto w-full"
      />

      <main
        className="max-w-lg mx-auto px-4 py-5 space-y-8 pb-12"
        style={{ paddingBottom: 'max(3rem, env(safe-area-inset-bottom))' }}
      >
        {loading ? (
          <div className="text-center py-16" style={{ color: eduGame.muted }}>
            불러오는 중…
          </div>
        ) : (
          <>
            {tier && (
              <EduStudentProfileHero
                student={student}
                tier={tier}
                coachLevel={coachLevel}
                layout="homeBoard"
              />
            )}

            <section className="space-y-1">
              <h1 className="font-bold px-0.5" style={{ fontSize: '1.25rem', color: eduGame.ink }}>
                오늘 뭐 따질까?
              </h1>
              <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                {totalVisible > 0
                  ? `${totalVisible}개의 논쟁 · 네 레벨에 맞는 글부터`
                  : '네 레벨에 맞는 글부터 골라보세요'}
              </p>
            </section>

            {sections.myLevelRecommended.length > 0 && (
              <section className="space-y-3" aria-labelledby="home-my-level">
                <h2
                  id="home-my-level"
                  className="font-bold px-0.5"
                  style={{ fontSize: eduGame.fontSize.body, color: eduGame.primary }}
                >
                  ⭐ 내 레벨 추천
                </h2>
                <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                  네 레벨({myLevelLabel})에 맞는 글
                </p>
                <ul className="space-y-4">
                  {sections.myLevelRecommended.map((q) => (
                    <li key={q.quest_id}>
                      <EduQuestBoardCard
                        quest={q}
                        size="featured"
                        disabled={starting}
                        showRecommended
                        onSelect={(id) => {
                          const q = quests.find(item => item.quest_id === id)
                          if (q) void handleStart(q)
                        }}
                      />
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {sections.newQuests.length > 0 && (
              <section className="space-y-3" aria-labelledby="home-new">
                <h2
                  id="home-new"
                  className="font-bold px-0.5"
                  style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink }}
                >
                  🆕 새로 들어온 글
                </h2>
                <ul className="space-y-3">
                  {sections.newQuests.map((q) => (
                    <li key={q.quest_id}>
                      <EduQuestBoardCard
                        quest={q}
                        disabled={starting}
                        onSelect={(id) => {
                          const q = quests.find(item => item.quest_id === id)
                          if (q) void handleStart(q)
                        }}
                      />
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {([1, 2, 3, 4, 5] as const).map((lv) => {
              const items = sections.byLevel[lv]
              if (items.length === 0) return null
              const meta = EDU_HOME_LEVEL_SECTION_LABELS[lv]
              return (
                <section key={lv} className="space-y-3" aria-labelledby={`home-level-${lv}`}>
                  <h2
                    id={`home-level-${lv}`}
                    className="font-bold px-0.5"
                    style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink }}
                  >
                    {meta.title}
                  </h2>
                  {meta.subtitle && (
                    <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                      {meta.subtitle}
                    </p>
                  )}
                  <ul className="space-y-3">
                    {items.map((q) => (
                      <li key={q.quest_id}>
                        <EduQuestBoardCard
                          quest={q}
                          disabled={starting}
                          onSelect={(id) => {
                          const q = quests.find(item => item.quest_id === id)
                          if (q) void handleStart(q)
                        }}
                        />
                      </li>
                    ))}
                  </ul>
                </section>
              )
            })}

            {totalVisible === 0 && (
              <section
                className="rounded-2xl border-2 p-8 text-center"
                style={{ borderColor: eduGame.border, backgroundColor: eduGame.surface }}
              >
                <p style={{ fontSize: eduGame.fontSize.body, color: eduGame.muted }}>
                  아직 열린 논쟁이 없어요.
                </p>
                <p className="mt-2" style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                  새 글이 올라오면 여기에 보여요.
                </p>
              </section>
            )}

            <div className="text-center pt-2">
              <Link
                to="/edu/explore"
                className="inline-block text-sm underline"
                style={{ color: eduGame.primary }}
              >
                레벨·주제로 더 찾아보기 →
              </Link>
            </div>
          </>
        )}

        {error && (
          <p
            className="text-sm border-2 p-3 rounded-xl"
            style={{ color: '#b71c1c', borderColor: '#ffcdd2', backgroundColor: '#ffebee' }}
          >
            {error}
          </p>
        )}
      </main>
    </div>
  )
}

