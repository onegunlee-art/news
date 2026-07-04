import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import EduGistudyLogo from '../../components/edu/EduGistudyLogo'
import {
  eduApi,
  getEduKakaoLoginUrl,
  setEduStudent,
  setEduToken,
} from '../../services/eduApi'
import { eduQuestFlowPath } from '../../constants/eduNarrativeBridge'

const LANDING = {
  bg: '#0A0A0A',
  orange: '#E85D2C',
  orangeDark: '#D85A30',
  muted: '#888888',
  border: '#222222',
  card: '#141414',
  serif: "'Noto Serif KR', Georgia, serif",
  sans: "'Noto Sans KR', sans-serif",
} as const

const FIXED_QUEST = {
  title: '핵무기, 세계를 더 안전하게 만들까?',
  hook: '이란·북한·우크라이나 — 핵 얘기가 다시 뜨고 있어요. 정말 억지력이 평화를 지킬까요?',
  participation: '31명 참여 중',
}

export default function EduLandingPage() {
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)
  const [showLogin, setShowLogin] = useState(false)
  const [inviteCode, setInviteCode] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    const id = 'edu-landing-serif-font'
    if (!document.getElementById(id)) {
      const link = document.createElement('link')
      link.id = id
      link.rel = 'stylesheet'
      link.href =
        'https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@400;600;700&display=swap'
      document.head.appendChild(link)
    }
  }, [])

  const scrollTo = (id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const handleKakaoLogin = () => {
    window.location.href = getEduKakaoLoginUrl()
  }

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
      const today = await eduApi.todayQuest()
      navigate(
        eduQuestFlowPath({
          questId: today.quest?.quest_id,
          coachMode: today.quest?.coach_mode,
          questCode: today.quest?.quest_code,
        })
      )
    } catch (e) {
      setError(e instanceof Error ? e.message : '체험 시작 실패')
    } finally {
      setLoading(false)
    }
  }

  const openLogin = () => {
    setShowLogin(true)
    setError('')
  }

  return (
    <div
      className="min-h-screen text-white"
      style={{ backgroundColor: LANDING.bg, fontFamily: LANDING.sans }}
    >
      {/* 1. 상단바 */}
      <header
        className="sticky top-0 z-30 flex items-center justify-between px-4 py-3 border-b backdrop-blur-md"
        style={{ borderColor: LANDING.border, backgroundColor: 'rgba(10,10,10,0.92)' }}
      >
        <EduGistudyLogo size="md" variant="dark" to="/edu" />
        <button
          type="button"
          aria-label="메뉴"
          onClick={() => setMenuOpen((v) => !v)}
          className="p-2 -mr-2"
        >
          <span className="material-symbols-outlined text-2xl">menu</span>
        </button>
      </header>

      {menuOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/60"
          onClick={() => setMenuOpen(false)}
          aria-hidden
        />
      )}
      <nav
        className={`fixed top-0 right-0 z-50 h-full w-64 border-l p-5 transition-transform duration-200 ${
          menuOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
        style={{ backgroundColor: LANDING.card, borderColor: LANDING.border }}
      >
        <button
          type="button"
          className="absolute top-3 right-3 p-1"
          onClick={() => setMenuOpen(false)}
          aria-label="닫기"
        >
          <span className="material-symbols-outlined">close</span>
        </button>
        <ul className="mt-10 space-y-4 text-sm">
          <li>
            <button type="button" className="underline" onClick={() => { setMenuOpen(false); openLogin() }}>
              학생 로그인
            </button>
          </li>
          <li>
            <Link to="/edu/operator/login" className="underline" onClick={() => setMenuOpen(false)}>
              교사·학원 로그인
            </Link>
          </li>
          <li>
            <a href="https://www.thegist.co.kr" className="underline">
              the gist 본체
            </a>
          </li>
        </ul>
      </nav>

      <main className="max-w-lg mx-auto px-4 pb-16">
        {/* 2. 히어로 */}
        <section className="pt-8 pb-10 text-center">
          <p
            className="inline-flex items-center gap-2 text-xs font-medium mb-5 px-3 py-1 rounded-full border"
            style={{ borderColor: LANDING.border, color: LANDING.muted }}
          >
            <span
              className="w-2 h-2 rounded-full animate-pulse shrink-0"
              style={{ backgroundColor: LANDING.orange }}
              aria-hidden
            />
            스스로 탐구하는 gistudy
          </p>

          <h1
            className="text-[1.65rem] sm:text-3xl font-bold leading-snug mb-5"
            style={{ fontFamily: LANDING.serif }}
          >
            AI는 답을 줍니다.
            <br />
            gistudy는{' '}
            <span style={{ color: LANDING.orange }}>답을 찾는 힘</span>을
            <br />
            기릅니다
          </h1>

          <p className="text-sm leading-relaxed mb-8" style={{ color: LANDING.muted }}>
            AI가 대신 생각해주는 시대 — 정작 아이들은 스스로 따지는 법을 잃어갑니다.
            gistudy는 답을 알려주지 않아요. 질문으로 스스로 탐구하게 합니다.
          </p>

          <button
            type="button"
            onClick={() => scrollTo('quest-section')}
            className="w-full py-4 rounded-xl font-bold text-white shadow-lg active:scale-[0.98] transition-transform"
            style={{
              backgroundColor: LANDING.orange,
              boxShadow: '0 8px 24px rgba(232, 93, 44, 0.35)',
            }}
          >
            오늘의 이슈 따지러 가기
          </button>

          <button
            type="button"
            onClick={() => scrollTo('vs-section')}
            className="mt-4 text-sm underline"
            style={{ color: LANDING.muted }}
          >
            어떻게 다른가요? ↓
          </button>
        </section>

        {/* 3. VS 비교 */}
        <section id="vs-section" className="py-8 scroll-mt-16">
          <div className="grid gap-3 sm:grid-cols-2">
            <div
              className="rounded-xl border p-4"
              style={{ borderColor: LANDING.border, backgroundColor: LANDING.card }}
            >
              <p className="text-xs font-bold mb-1" style={{ color: LANDING.muted }}>
                일반 AI
              </p>
              <p className="font-bold mb-3" style={{ fontFamily: LANDING.serif }}>
                &ldquo;답을 알려줄게&rdquo;
              </p>
              <ul className="space-y-1.5 text-xs" style={{ color: LANDING.muted }}>
                <li>✕ 빠른 정답</li>
                <li>✕ 받아적음</li>
                <li>✕ AI가 대신</li>
              </ul>
            </div>
            <div
              className="rounded-xl border p-4"
              style={{
                borderColor: LANDING.orange,
                backgroundColor: 'rgba(232, 93, 44, 0.08)',
              }}
            >
              <p className="text-xs font-bold mb-1" style={{ color: LANDING.orange }}>
                gistudy
              </p>
              <p className="font-bold mb-3" style={{ fontFamily: LANDING.serif }}>
                &ldquo;같이 따져보자&rdquo;
              </p>
              <ul className="space-y-1.5 text-xs">
                <li>✓ 되묻기</li>
                <li>✓ 파고들게</li>
                <li>✓ 너의 것</li>
              </ul>
            </div>
          </div>
        </section>

        {/* 4. 증거 3칸 */}
        <section className="py-8 grid grid-cols-3 gap-2 text-center">
          {(
            [
              ['매일', '오늘의 이슈'],
              ['1:1', '코치와 대화'],
              ['5단계', '탐구 성장'],
            ] as const
          ).map(([title, sub]) => (
            <div
              key={title}
              className="rounded-xl border p-3"
              style={{ borderColor: LANDING.border, backgroundColor: LANDING.card }}
            >
              <p className="text-lg font-bold" style={{ color: LANDING.orange, fontFamily: LANDING.serif }}>
                {title}
              </p>
              <p className="text-[0.65rem] mt-1 leading-tight" style={{ color: LANDING.muted }}>
                {sub}
              </p>
            </div>
          ))}
        </section>

        {/* 5. 탐구가 바꾸는 변화 */}
        <section className="py-8 space-y-3">
          <h2 className="text-lg font-bold text-center mb-4" style={{ fontFamily: LANDING.serif }}>
            탐구가 바꾸는 변화
          </h2>
          {(
            [
              ['📰', '뉴스에 안 속고'],
              ['🧭', '안 휘둘리고'],
              ['✍️', '설득한다'],
            ] as const
          ).map(([icon, text]) => (
            <div
              key={text}
              className="flex items-center gap-3 rounded-xl border px-4 py-3"
              style={{ borderColor: LANDING.border, backgroundColor: LANDING.card }}
            >
              <span className="text-xl">{icon}</span>
              <span className="font-medium">{text}</span>
            </div>
          ))}
        </section>

        {/* 6. 작동 방식 */}
        <section className="py-8">
          <h2 className="text-lg font-bold text-center mb-6" style={{ fontFamily: LANDING.serif }}>
            작동 방식
          </h2>
          <div className="flex items-start justify-between gap-2 text-center text-sm">
            {(
              [
                ['1', '이슈'],
                ['2', '따지기'],
                ['3', '글'],
              ] as const
            ).map(([num, label], i) => (
              <div key={num} className="flex-1">
                <div
                  className="w-10 h-10 mx-auto rounded-full flex items-center justify-center font-bold mb-2"
                  style={{ backgroundColor: LANDING.orange, color: '#fff' }}
                >
                  {num}
                </div>
                <p className="font-medium">{label}</p>
                {i < 2 && (
                  <span className="hidden sm:inline text-xs" style={{ color: LANDING.muted }}>
                    →
                  </span>
                )}
              </div>
            ))}
          </div>
        </section>

        {/* 7. 오늘의 퀘스트 (고정) */}
        <section id="quest-section" className="py-8 scroll-mt-16">
          <h2 className="text-sm font-bold mb-3" style={{ color: LANDING.muted }}>
            오늘의 퀘스트
          </h2>
          <div
            className="rounded-xl border overflow-hidden"
            style={{ borderColor: LANDING.border, backgroundColor: LANDING.card }}
          >
            <div
              className="h-32 flex items-end p-4"
              style={{
                background: `linear-gradient(135deg, #1a1a2e 0%, ${LANDING.orangeDark}88 100%)`,
              }}
            >
              <span className="text-xs font-bold px-2 py-0.5 rounded bg-black/40">따질 주제</span>
            </div>
            <div className="p-4 space-y-3">
              <h3 className="font-bold leading-snug" style={{ fontFamily: LANDING.serif }}>
                {FIXED_QUEST.title}
              </h3>
              <p className="text-sm" style={{ color: LANDING.muted }}>
                {FIXED_QUEST.hook}
              </p>
              <p className="text-xs text-center" style={{ color: LANDING.muted }}>
                {FIXED_QUEST.participation}
              </p>
              <button
                type="button"
                onClick={openLogin}
                className="w-full py-3.5 rounded-xl font-bold text-white"
                style={{ backgroundColor: LANDING.orange }}
              >
                시작하기
              </button>
            </div>
          </div>
        </section>

        {/* 8. 학교/학원 밴드 */}
        <section
          className="py-8 my-4 rounded-xl border text-center px-4"
          style={{ borderColor: LANDING.border, backgroundColor: LANDING.card }}
        >
          <p className="text-sm font-bold mb-1" style={{ fontFamily: LANDING.serif }}>
            현대차가 신뢰하는 the gist
          </p>
          <p className="text-xs mb-4" style={{ color: LANDING.muted }}>
            학교·학원 도입 문의
          </p>
          <a
            href="mailto:contact@thegist.co.kr?subject=gistudy%20도입%20문의"
            className="inline-block px-5 py-2.5 rounded-xl border text-sm font-bold"
            style={{ borderColor: LANDING.orange, color: LANDING.orange }}
          >
            도입 문의하기
          </a>
        </section>

        {/* 로그인 패널 */}
        {showLogin && (
          <section
            className="fixed inset-x-0 bottom-0 z-40 max-w-lg mx-auto rounded-t-2xl border p-5 pb-8 shadow-2xl"
            style={{ borderColor: LANDING.border, backgroundColor: LANDING.card }}
          >
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-lg font-bold">시작하기</h2>
              <button type="button" onClick={() => setShowLogin(false)} aria-label="닫기">
                <span className="material-symbols-outlined">close</span>
              </button>
            </div>
            <button
              type="button"
              onClick={handleKakaoLogin}
              disabled={loading}
              className="w-full py-3 rounded-xl font-medium disabled:opacity-50 flex items-center justify-center gap-2 mb-3"
              style={{ backgroundColor: '#FEE500', color: '#191919' }}
            >
              <span className="font-bold">카카오</span> 로그인
            </button>
            <p className="text-center text-xs my-2" style={{ color: LANDING.muted }}>
              또는
            </p>
            <input
              type="text"
              value={inviteCode}
              onChange={(e) => setInviteCode(e.target.value)}
              placeholder="초대코드 (EDU-PILOT-01)"
              className="w-full border rounded-xl px-3 py-2.5 mb-2 bg-transparent"
              style={{ borderColor: LANDING.border }}
            />
            <button
              type="button"
              onClick={() => void handleRedeem()}
              disabled={loading}
              className="w-full py-3 rounded-xl font-bold text-white mb-2 disabled:opacity-50"
              style={{ backgroundColor: LANDING.orange }}
            >
              {loading ? '확인 중…' : '초대코드로 시작'}
            </button>
            <button
              type="button"
              onClick={() => void handleGuestStart()}
              disabled={loading}
              className="w-full py-3 rounded-xl border font-medium disabled:opacity-50"
              style={{ borderColor: LANDING.orange, color: LANDING.orange }}
            >
              게스트로 체험하기
            </button>
            {error && <p className="text-sm text-red-400 mt-3 text-center">{error}</p>}
          </section>
        )}

        {/* 9. 푸터 */}
        <footer className="pt-8 pb-4 text-center text-xs space-y-2" style={{ color: LANDING.muted }}>
          <p>© gistudy · the gist</p>
          <div className="flex justify-center gap-4">
            <Link to="/privacy" className="underline">
              개인정보
            </Link>
            <Link to="/terms" className="underline">
              이용약관
            </Link>
          </div>
        </footer>
      </main>
    </div>
  )
}
