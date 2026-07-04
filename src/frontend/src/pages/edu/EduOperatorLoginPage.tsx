import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import { eduOperatorLogin } from '../../services/eduOperatorApi'
import { setEduOperatorSession } from '../../utils/eduOperatorSession'

const DEFAULT_RETURN = '/edu/dashboard'

export default function EduOperatorLoginPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const returnTo = searchParams.get('returnTo') || DEFAULT_RETURN

  const [email, setEmail] = useState('test@edu.com')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const { token, operator } = await eduOperatorLogin(email.trim(), password)
      setEduOperatorSession(token, operator)
      const target = returnTo.startsWith('/edu') ? returnTo : DEFAULT_RETURN
      navigate(target, { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : '로그인 실패')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div
      className="min-h-screen flex flex-col"
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <header className="px-4 py-3 border-b" style={{ borderColor: eduGame.border }}>
        <p className="text-xs font-bold" style={{ color: eduGame.primary }}>
          gistudy · 운영자
        </p>
        <h1 className="text-lg font-bold mt-0.5">교사·원장 로그인</h1>
      </header>

      <main className="flex-1 flex items-center justify-center px-4 py-8">
        <form onSubmit={(e) => void handleSubmit(e)} className="w-full max-w-sm space-y-4">
          <p className="text-sm" style={{ color: eduGame.muted }}>
            학원 대시보드 전용 계정입니다. 학생 카카오 로그인과 별도입니다.
          </p>

          <div>
            <label className="block text-xs font-bold mb-1" htmlFor="operator-email">
              이메일
            </label>
            <input
              id="operator-email"
              type="email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className={`w-full px-3 py-2.5 rounded-xl border ${eduGameClasses.input}`}
              style={{ borderColor: eduGame.border }}
              required
            />
          </div>

          <div>
            <label className="block text-xs font-bold mb-1" htmlFor="operator-password">
              비밀번호
            </label>
            <input
              id="operator-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className={`w-full px-3 py-2.5 rounded-xl border ${eduGameClasses.input}`}
              style={{ borderColor: eduGame.border }}
              required
            />
          </div>

          {error && <p className="text-sm text-red-600 text-center">{error}</p>}

          <button
            type="submit"
            disabled={loading}
            className={`w-full py-3 ${eduGameClasses.btnPrimary}`}
            style={{ backgroundColor: eduGame.primary, fontSize: eduGame.fontSize.button }}
          >
            {loading ? '로그인 중…' : '로그인'}
          </button>

          <p className="text-center text-xs" style={{ color: eduGame.muted }}>
            <Link to="/edu" className="underline">
              EDU 홈으로
            </Link>
          </p>
        </form>
      </main>
    </div>
  )
}
