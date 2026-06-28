import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import EduQuestCoverHero from '../../components/edu/EduQuestCoverHero'
import EduTopBar from '../../components/edu/EduTopBar'
import {
  clearEduToken,
  eduApi,
  getEduDisplayName,
  getEduKakaoLoginUrl,
  getEduStudent,
  getEduToken,
  setEduStudent,
  setEduToken,
  type EduQuest,
  type EduQuestListItem,
} from '../../services/eduApi'
import EduHomeBoard from './EduHomeBoard'
import { eduGuestTopBarMenu } from '../../utils/eduTopBarMenu'

/** 비로그인 체험·가입 유도 (3단계에서 확장) */
function EduHomeGuestPage() {
  const navigate = useNavigate()
  const [inviteCode, setInviteCode] = useState('')
  const [studentName, setStudentName] = useState(
    () => getEduStudent()?.display_name || getEduDisplayName() || '',
  )
  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [otherQuests, setOtherQuests] = useState<EduQuestListItem[]>([])
  const [participation, setParticipation] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [showLogin, setShowLogin] = useState(false)

  const loadGuestPreview = async () => {
    setLoading(true)
    setError('')
    try {
      const [todayRes, listRes] = await Promise.all([
        eduApi.todayQuest(),
        eduApi.listQuests({ limit: 3, frame: 'all' }),
      ])
      setQuest(todayRes.quest)
      setParticipation(todayRes.participation?.display || '')
      const liveId = todayRes.quest?.quest_id
      setOtherQuests(
        listRes.quests
          .filter((q) => !q.is_live && q.quest_id !== liveId)
          .slice(0, 3),
      )
    } catch (e) {
      setError(e instanceof Error ? e.message : '로드 실패')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadGuestPreview()
  }, [])

  const handleRedeem = async () => {
    if (!inviteCode.trim()) return
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.redeemInvite(inviteCode.trim())
      setEduToken(res.token)
      setEduStudent({
        id: res.student.id,
        display_name: res.student.display_name,
        grade_band: res.student.grade_band,
      })
      setStudentName(res.student.display_name)
      window.location.reload()
    } catch (e) {
      setError(e instanceof Error ? e.message : '초대코드 오류')
    } finally {
      setLoading(false)
    }
  }

  const handleGuestStart = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.createGuestSession()
      setEduToken(res.token)
      setEduStudent({
        id: res.student.id,
        display_name: res.student.display_name,
        grade_band: res.student.grade_band,
      })
      setStudentName(res.student.display_name)
      navigate('/edu/quest')
    } catch (e) {
      setError(e instanceof Error ? e.message : '게스트 시작 실패')
    } finally {
      setLoading(false)
    }
  }

  const handleKakaoLogin = () => {
    window.location.href = getEduKakaoLoginUrl()
  }

  const coverArticle =
    quest?.articles.find((a) => a.role === 'primary') ?? quest?.articles[0] ?? null

  return (
    <div className="min-h-screen bg-[#0D0D0D] text-white">
      <EduTopBar
        variant="dark"
        streakDays={0}
        menuItems={eduGuestTopBarMenu(() => setShowLogin(true))}
        className="max-w-lg mx-auto w-full"
      />

      <main className="max-w-lg mx-auto px-4 py-6 space-y-6">
        {loading && !quest ? (
          <div className="text-center py-12 text-[#666]">불러오는 중…</div>
        ) : quest ? (
          <>
            <EduQuestCoverHero
              coverImageUrl={quest.cover_image_url}
              questTitle={quest.quest_title}
              hookShort={quest.hook_short}
              timeAnchor={quest.time_anchor}
              conflictSummary={quest.conflict_summary}
              coverArticle={coverArticle}
              variant="hero"
            />

            <section className="border border-[#333] rounded-lg p-5 bg-[#1a1a1a] space-y-4">
              <h1 className="text-lg font-bold leading-snug edu-game-text-ko">{quest.quest_title}</h1>
              <div className="grid grid-cols-2 gap-3">
                <div className="border border-[#333] rounded p-3">
                  <span className="text-xs text-[#4CAF50] font-bold block mb-1">찬성</span>
                  <p className="text-sm text-[#ccc] edu-game-text-ko">{quest.pro_line}</p>
                </div>
                <div className="border border-[#333] rounded p-3">
                  <span className="text-xs text-[#F44336] font-bold block mb-1">반대</span>
                  <p className="text-sm text-[#ccc] edu-game-text-ko">{quest.con_line}</p>
                </div>
              </div>
              {participation && (
                <p className="text-xs text-[#666] text-center">{participation}</p>
              )}
            </section>

            {showLogin ? (
              <section className="space-y-4 border border-[#333] rounded-lg p-5 bg-[#1a1a1a]">
                <h2 className="text-lg font-bold">로그인</h2>
                <button
                  type="button"
                  onClick={handleKakaoLogin}
                  disabled={loading}
                  className="w-full py-3 rounded font-medium disabled:opacity-50 flex items-center justify-center gap-2"
                  style={{ backgroundColor: '#FEE500', color: '#191919' }}
                >
                  <span className="font-bold">카카오</span>
                  로그인
                </button>
                <div className="text-center text-[#666] text-xs my-2">또는</div>
                <p className="text-sm text-[#666]">학원에서 받은 초대코드를 입력하세요.</p>
                <input
                  type="text"
                  value={inviteCode}
                  onChange={(e) => setInviteCode(e.target.value)}
                  placeholder="EDU-PILOT-01"
                  className="w-full border border-[#333] bg-[#0D0D0D] rounded px-3 py-2 text-white"
                />
                <button
                  type="button"
                  onClick={() => void handleRedeem()}
                  disabled={loading}
                  className="w-full py-3 bg-[#E8521C] text-white rounded font-medium disabled:opacity-50"
                >
                  {loading ? '확인 중…' : '시작하기'}
                </button>
                <div className="text-center text-[#666] text-xs my-2">또는</div>
                <button
                  type="button"
                  onClick={() => void handleGuestStart()}
                  disabled={loading}
                  className="w-full py-3 border border-[#E8521C] text-[#E8521C] rounded font-medium disabled:opacity-50"
                >
                  게스트로 체험하기
                </button>
                <button
                  type="button"
                  onClick={() => setShowLogin(false)}
                  className="w-full py-2 text-[#666] text-sm"
                >
                  취소
                </button>
              </section>
            ) : (
              <button
                type="button"
                onClick={() => setShowLogin(true)}
                className="w-full py-4 bg-[#E8521C] text-white rounded-lg font-bold text-lg"
              >
                참여하기
              </button>
            )}
          </>
        ) : (
          <section className="text-center py-12">
            <p className="text-[#666]">오늘은 퀘스트가 없어요.</p>
            <p className="text-sm text-[#666] mt-2">로그인하면 더 많은 논쟁을 골라볼 수 있어요.</p>
            <button
              type="button"
              onClick={() => setShowLogin(true)}
              className="mt-6 px-6 py-3 bg-[#E8521C] text-white rounded-lg font-bold"
            >
              참여하기
            </button>
          </section>
        )}

        {otherQuests.length > 0 && (
          <section className="space-y-3">
            <h2 className="text-sm font-medium text-[#888]">다른 논쟁 미리보기</h2>
            {otherQuests.map((q) => (
              <div
                key={q.quest_id}
                className="w-full text-left border border-[#333] rounded-lg overflow-hidden bg-[#1a1a1a] opacity-80"
              >
                <EduQuestCoverHero
                  coverImageUrl={q.cover_image_url}
                  questTitle={q.quest_title}
                  hookShort={q.hook_short}
                  timeAnchor={q.time_anchor}
                  variant="card"
                  topicLabel="따질 주제"
                />
                <div className="p-3 pt-2">
                  <p className="text-sm font-medium leading-snug edu-game-text-ko">{q.quest_title}</p>
                  <p className="text-xs text-[#666] mt-2">참여하려면 로그인하세요</p>
                </div>
              </div>
            ))}
          </section>
        )}

        {error && (
          <p className="text-sm text-red-400 border border-red-900 bg-red-900/20 p-3 rounded">{error}</p>
        )}

        {studentName && !showLogin && (
          <p className="text-xs text-center text-[#666]">환영합니다, {studentName}님</p>
        )}
      </main>
    </div>
  )
}

export default function EduHomePage() {
  const [authed, setAuthed] = useState(!!getEduToken())

  const handleLogout = () => {
    clearEduToken()
    setAuthed(false)
  }

  if (authed) {
    return <EduHomeBoard onLogout={handleLogout} />
  }

  return <EduHomeGuestPage />
}
