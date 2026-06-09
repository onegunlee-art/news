import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import TierProgressCard from '../../components/edu/TierProgressCard'
import {
  clearEduToken,
  eduApi,
  getEduToken,
  setEduToken,
  type EduTierProgress,
} from '../../services/eduApi'

export default function EduHomePage() {
  const navigate = useNavigate()
  const [inviteCode, setInviteCode] = useState('')
  const [studentName, setStudentName] = useState('')
  const [tier, setTier] = useState<EduTierProgress | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [authed, setAuthed] = useState(!!getEduToken())

  const loadToday = async () => {
    setLoading(true)
    setError('')
    try {
      const res = await eduApi.todayQuest()
      setTier(res.tier)
      setAuthed(true)
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
    if (getEduToken()) {
      loadToday()
    }
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

  const handleLogout = () => {
    clearEduToken()
    setAuthed(false)
    setTier(null)
    setStudentName('')
  }

  return (
    <div className="min-h-screen bg-white text-[#1a1a1a]">
      <header className="border-b border-[#1a1a1a] px-4 py-4 flex items-center justify-between max-w-lg mx-auto">
        <div className="flex items-center gap-2">
          <span className="font-bold text-xl">g.</span>
          <span className="text-sm tracking-wide">the gist · EDU</span>
        </div>
        {authed && (
          <button type="button" onClick={handleLogout} className="text-xs underline text-[#666]">
            나가기
          </button>
        )}
      </header>

      <main className="max-w-lg mx-auto px-4 py-6 space-y-6">
        {!authed ? (
          <section className="space-y-4">
            <h1 className="text-2xl font-bold">파일럿 학생 로그인</h1>
            <p className="text-sm text-[#666]">학원에서 받은 초대코드를 입력하세요.</p>
            <input
              type="text"
              value={inviteCode}
              onChange={(e) => setInviteCode(e.target.value)}
              placeholder="EDU-PILOT-01"
              className="w-full border border-[#1a1a1a] rounded px-3 py-2"
            />
            <button
              type="button"
              onClick={handleRedeem}
              disabled={loading}
              className="w-full py-3 bg-[#1a1a1a] text-white rounded font-medium disabled:opacity-50"
            >
              {loading ? '확인 중…' : '시작하기'}
            </button>
          </section>
        ) : (
          <>
            {studentName && (
              <p className="text-sm text-[#666]">안녕하세요, {studentName}님</p>
            )}
            {tier && (
              <TierProgressCard tier={tier} onStartQuest={handleStart} loading={loading} />
            )}
            <Link
              to="/edu/quest"
              className="block text-center text-sm underline text-[#666]"
            >
              진행 중인 퀘스트 이어하기
            </Link>
          </>
        )}

        {error && (
          <p className="text-sm text-red-600 border border-red-200 bg-red-50 p-3 rounded">{error}</p>
        )}
      </main>
    </div>
  )
}
