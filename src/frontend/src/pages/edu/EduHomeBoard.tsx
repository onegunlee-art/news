import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import EduHomeStatusBanner from '../../components/edu/EduHomeStatusBanner'
import EduQuestBoardCard from '../../components/edu/EduQuestBoardCard'
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
import { partitionHomeBoard } from '../../utils/eduHomeBoardSections'

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

  const sections = useMemo(() => partitionHomeBoard(quests), [quests])
  const totalVisible =
    sections.recommended.length + sections.newQuests.length + sections.allRest.length

  const loadBoard = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [statusRes, listRes] = await Promise.all([
        eduApi.todayQuest(),
        eduApi.listQuests({ limit: 50, frame: 'all' }),
      ])
      if (statusRes.tier) setTier(statusRes.tier)
      if (statusRes.coach_level) setCoachLevel(statusRes.coach_level)
      setQuests(listRes.quests)
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

  const handleStart = async (questId: string) => {
    setStarting(true)
    setError('')
    try {
      await eduApi.startSession(questId)
      navigate(`/edu/quest?quest_id=${encodeURIComponent(questId)}`)
    } catch (e) {
      setError(e instanceof Error ? e.message : '시작 실패')
    } finally {
      setStarting(false)
    }
  }

  return (
    <div
      className={`min-h-screen ${eduGameClasses.textKo}`}
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <header
        className="border-b px-4 py-3 flex items-center justify-between max-w-lg mx-auto sticky top-0 z-10"
        style={{ borderColor: eduGame.border, backgroundColor: eduGame.bg }}
      >
        <div className="flex items-center gap-2 min-w-0">
          <span className="font-bold text-xl shrink-0" style={{ fontFamily: 'Lobster, cursive' }}>
            g.
          </span>
          <span
            className="text-sm tracking-wide truncate"
            style={{ color: eduGame.muted }}
          >
            the gist · EDU
          </span>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <Link
            to="/edu/profile"
            className="text-xs underline font-bold"
            style={{ color: eduGame.primary }}
          >
            내 프로필
          </Link>
          <button
            type="button"
            onClick={onLogout}
            className="text-xs underline"
            style={{ color: eduGame.muted }}
          >
            나가기
          </button>
        </div>
      </header>

      <main className="max-w-lg mx-auto px-4 py-5 space-y-8 pb-12">
        {loading ? (
          <div className="text-center py-16" style={{ color: eduGame.muted }}>
            불러오는 중…
          </div>
        ) : (
          <>
            {tier && (
              <EduHomeStatusBanner student={student} tier={tier} coachLevel={coachLevel} />
            )}

            <section className="space-y-1">
              <h1 className="font-bold px-0.5" style={{ fontSize: '1.25rem', color: eduGame.ink }}>
                오늘 뭐 따질까?
              </h1>
              <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                골라서 바로 시작 · approved {totalVisible}개
              </p>
            </section>

            {sections.recommended.length > 0 && (
              <section className="space-y-3" aria-labelledby="home-recommended">
                <h2
                  id="home-recommended"
                  className="font-bold px-0.5"
                  style={{ fontSize: eduGame.fontSize.body, color: eduGame.primary }}
                >
                  ⭐ 오늘의 추천
                </h2>
                <p style={{ fontSize: eduGame.fontSize.caption, color: eduGame.muted }}>
                  너의 레벨에 맞춰 골라봤어요
                </p>
                <ul className="space-y-4">
                  {sections.recommended.map((q) => (
                    <li key={q.quest_id}>
                      <EduQuestBoardCard
                        quest={q}
                        size="featured"
                        disabled={starting}
                        onSelect={(id) => void handleStart(id)}
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
                        onSelect={(id) => void handleStart(id)}
                      />
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {sections.allRest.length > 0 && (
              <section className="space-y-3" aria-labelledby="home-all">
                <h2
                  id="home-all"
                  className="font-bold px-0.5"
                  style={{ fontSize: eduGame.fontSize.body, color: eduGame.ink }}
                >
                  📂 더 따지기
                </h2>
                <ul className="space-y-3">
                  {sections.allRest.map((q) => (
                    <li key={q.quest_id}>
                      <EduQuestBoardCard
                        quest={q}
                        disabled={starting}
                        onSelect={(id) => void handleStart(id)}
                      />
                    </li>
                  ))}
                </ul>
              </section>
            )}

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
                주제·유형으로 더 찾아보기 →
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
