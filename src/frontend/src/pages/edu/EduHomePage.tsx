import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import TierProgressCard from '../../components/edu/TierProgressCard'
import {
  clearEduToken,
  eduApi,
  getEduToken,
  setEduToken,
  type EduTierProgress,
  type EduQuest,
} from '../../services/eduApi'

export default function EduHomePage() {
  const navigate = useNavigate()
  const [inviteCode, setInviteCode] = useState('')
  const [studentName, setStudentName] = useState('')
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [quest, setQuest] = useState<EduQuest | null>(null)
  const [participation, setParticipation] = useState<string>('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [authed, setAuthed] = useState(!!getEduToken())
  const [showLogin, setShowLogin] = useState(false)

  const loadToday = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.todayQuest()
      setQuest(res.quest)
      setParticipation(res.participation?.display || '')
      if (res.tier) {
        setTier(res.tier)
        setAuthed(true)
      }
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
    loadToday()
  }, [])

  const handleRedeem = async () => {
    if (!inviteCode.trim()) return
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.redeemInvite(inviteCode.trim())
      setEduToken(res.token)
      setStudentName(res.student.display_name)
      setAuthed(true)
      await loadToday()
    } catch (e) {
      setError(e instanceof Error ? e.message : '초대코드 오류')
    } finally {
      setLoading(false)
    }
  }

  const handleStart = async () => {
    setLoading(true)
    try {
      await eduApi.startSession()
      navigate('/edu/quest')
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
      setStudentName('게스트')
      setAuthed(true)
      navigate('/edu/quest')
    } catch (e) {
      setError(e instanceof Error ? e.message : '게스트 시작 실패')
    } finally {
      setLoading(false)
    }
  }

  const handleLogout = () => {
    clearEduToken()
    setAuthed(false)
    setTier(null)
    setStudentName('')
  }

  return (
    <div className="min-h-screen bg-[#0D0D0D] text-white">
      <header className="border-b border-[#333] px-4 py-4 flex items-center justify-between max-w-lg mx-auto">
        <div className="flex items-center gap-2">
          <span className="font-bold text-xl" style={{ fontFamily: 'Lobster, cursive' }}>g.</span>
          <span className="text-sm tracking-wide text-[#999]">the gist · EDU</span>
        </div>
        {authed && (
          <button type="button" onClick={handleLogout} className="text-xs underline text-[#666]">
            나가기
          </button>
        )}
      </header>

      <main className="max-w-lg mx-auto px-4 py-6 space-y-6">
        {loading && !quest ? (
          <div className="text-center py-12 text-[#666]">불러오는 중…</div>
        ) : quest ? (
          <>
            {/* 오늘의 퀘스트 카드 */}
            <section className="border border-[#333] rounded-lg p-5 bg-[#1a1a1a]">
              <p className="text-xs text-[#E8521C] font-medium mb-2">오늘의 논쟁</p>
              <h1 className="text-xl font-bold leading-snug mb-3">{quest.quest_title}</h1>
              <p className="text-sm text-[#999] mb-4">{quest.conflict_summary}</p>
              
              <div className="grid grid-cols-2 gap-3 mb-4">
                <div className="border border-[#333] rounded p-3">
                  <span className="text-xs text-[#4CAF50] font-bold block mb-1">찬성</span>
                  <p className="text-sm text-[#ccc]">{quest.pro_line}</p>
                </div>
                <div className="border border-[#333] rounded p-3">
                  <span className="text-xs text-[#F44336] font-bold block mb-1">반대</span>
                  <p className="text-sm text-[#ccc]">{quest.con_line}</p>
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
                  <TierProgressCard tier={tier} onStartQuest={handleStart} loading={loading} />
                )}
                <button
                  type="button"
                  onClick={handleStart}
                  disabled={loading}
                  className="w-full py-4 bg-[#E8521C] text-white rounded-lg font-bold text-lg disabled:opacity-50"
                >
                  퀘스트 시작하기
                </button>
              </>
            ) : showLogin ? (
              <section className="space-y-4 border border-[#333] rounded-lg p-5 bg-[#1a1a1a]">
                <h2 className="text-lg font-bold">로그인</h2>
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

        {error && (
          <p className="text-sm text-red-400 border border-red-900 bg-red-900/20 p-3 rounded">{error}</p>
        )}
      </main>
    </div>
  )
}
