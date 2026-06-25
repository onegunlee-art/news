import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import TierProgressCard from '../../components/edu/TierProgressCard'
import EduQuestCoverHero from '../../components/edu/EduQuestCoverHero'
import {
  clearEduToken,
  eduApi,
  getEduDisplayName,
  getEduKakaoLoginUrl,
  getEduStudent,
  getEduToken,
  setEduStudent,
  setEduToken,
  type EduTierProgress,
  type EduQuest,
  type EduQuestListItem,
} from '../../services/eduApi'

export default function EduHomePage() {
  const navigate = useNavigate()
  const [inviteCode, setInviteCode] = useState('')
  const [studentName, setStudentName] = useState(() => getEduStudent()?.display_name || getEduDisplayName() || '')
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [otherQuests, setOtherQuests] = useState<EduQuestListItem[]>([])
  const [participation, setParticipation] = useState<string>('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [authed, setAuthed] = useState(!!getEduToken())
  const [showLogin, setShowLogin] = useState(false)

  const loadHome = async () => {
    setLoading(true)
    setError('')
    try {
      const [todayRes, listRes] = await Promise.all([
        eduApi.todayQuest(),
        eduApi.listQuests({ limit: 3, frame: 'all' }),
      ])
      setQuest(todayRes.quest)
      setParticipation(todayRes.participation?.display || '')
      if (todayRes.tier) {
        setTier(todayRes.tier)
        setAuthed(true)
      }
      const liveId = todayRes.quest?.quest_id
      setOtherQuests(
        listRes.quests
          .filter((q) => !q.is_live && q.quest_id !== liveId)
          .slice(0, 3)
      )
    } catch (e) {
      setError(e instanceof Error ? e.message : '로드 실패')
      if ((e as Error).message?.includes('401')) {
        clearEduToken()
        setAuthed(false)
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadHome()
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
      setAuthed(true)
      await loadHome()
    } catch (e) {
      setError(e instanceof Error ? e.message : '초대코드 오류')
    } finally {
      setLoading(false)
    }
  }

  const handleStart = async (questId?: string) => {
    const id = questId ?? quest?.quest_id
    if (!id) return
    setLoading(true)
    try {
      await eduApi.startSession(id)
      navigate(questId ? `/edu/quest?quest_id=${encodeURIComponent(id)}` : '/edu/quest')
    } catch (e) {
      setError(e instanceof Error ? e.message : '시작 실패')
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
      setAuthed(true)
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

  const handleLogout = () => {
    clearEduToken()
    setAuthed(false)
    setTier(null)
    setStudentName('')
  }

  const coverArticle =
    quest?.articles.find((a) => a.role === 'primary') ?? quest?.articles[0] ?? null

  return (
    <div className="min-h-screen bg-[#0D0D0D] text-white">
      <header className="border-b border-[#333] px-4 py-4 flex items-center justify-between max-w-lg mx-auto">
        <div className="flex items-center gap-2">
          <span className="font-bold text-xl" style={{ fontFamily: 'Lobster, cursive' }}>g.</span>
          <span className="text-sm tracking-wide text-[#999]">the gist · EDU</span>
        </div>
        {authed ? (
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => navigate('/edu/profile')}
              className="text-xs underline font-bold"
              style={{ color: '#f05123' }}
            >
              내 프로필
            </button>
            <button type="button" onClick={handleLogout} className="text-xs underline text-[#666]">
              나가기
            </button>
          </div>
        ) : null}
      </header>

      <main className="max-w-lg mx-auto px-4 py-6 space-y-6">
        {loading && !quest ? (
          <div className="text-center py-12 text-[#666]">불러오는 중…</div>
        ) : quest ? (
          <>
            {/* 오늘의 퀘스트 표지 */}
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

            {/* 참여 버튼 또는 로그인 */}
            {authed ? (
              <>
                {studentName && (
                  <p className="text-sm text-[#666]">안녕하세요, {studentName}님</p>
                )}
                {tier && (
                  <TierProgressCard tier={tier} onStartQuest={() => handleStart()} loading={loading} />
                )}
                <button
                  type="button"
                  onClick={() => handleStart()}
                  disabled={loading}
                  className="w-full py-4 bg-[#E8521C] text-white rounded-lg font-bold text-lg disabled:opacity-50"
                >
                  퀘스트 시작하기
                </button>
              </>
            ) : showLogin ? (
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
                  onClick={handleRedeem}
                  disabled={loading}
                  className="w-full py-3 bg-[#E8521C] text-white rounded font-medium disabled:opacity-50"
                >
                  {loading ? '확인 중…' : '시작하기'}
                </button>
                <div className="text-center text-[#666] text-xs my-2">또는</div>
                <button
                  type="button"
                  onClick={handleGuestStart}
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
            <p className="text-sm text-[#666] mt-2">수, 토, 일 오후 4시에 새 퀘스트가 드랍됩니다!</p>
          </section>
        )}

        {otherQuests.length > 0 && (
          <section className="space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-medium text-[#888]">다른 논쟁</h2>
              <Link to="/edu/explore" className="text-xs text-[#E8521C] underline">
                전체 보기 →
              </Link>
            </div>
            {otherQuests.map((q) => (
              <button
                key={q.quest_id}
                type="button"
                onClick={() => authed && handleStart(q.quest_id)}
                disabled={loading || !authed}
                className="w-full text-left border border-[#333] rounded-lg overflow-hidden bg-[#1a1a1a] hover:border-[#555] disabled:opacity-50 transition-colors"
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
                <div className="flex items-start justify-between gap-2 mb-1">
                  {q.lens_label ? (
                    <span className="text-xs text-[#E8521C] edu-game-text-ko">쟁점: {q.lens_label}</span>
                  ) : q.time_anchor ? (
                    <span className="text-xs text-[#888]">{q.time_anchor}</span>
                  ) : null}
                  {q.completed && (
                    <span className="text-xs text-[#4CAF50] shrink-0">완료</span>
                  )}
                </div>
                <p className="text-sm font-medium leading-snug edu-game-text-ko">{q.quest_title}</p>
                {!authed && (
                  <p className="text-xs text-[#666] mt-2">참여하려면 로그인하세요</p>
                )}
                </div>
              </button>
            ))}
          </section>
        )}

        {otherQuests.length === 0 && quest && (
          <section className="text-center py-4">
            <Link
              to="/edu/explore"
              className="inline-block px-5 py-3 border border-[#E8521C] text-[#E8521C] rounded-lg text-sm font-medium hover:bg-[#E8521C]/10 transition-colors"
            >
              더 많은 논쟁 탐색하기
            </Link>
          </section>
        )}

        {error && (
          <p className="text-sm text-red-400 border border-red-900 bg-red-900/20 p-3 rounded">{error}</p>
        )}
      </main>
    </div>
  )
}
