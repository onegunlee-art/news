import React, { useState, useEffect } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { authApi, welcomeSettingsApi } from '../services/api'
import { saveAuthReturnState } from '../utils/authReturnState'
import PrivacyPolicyModal from '../components/Common/PrivacyPolicyModal'
import TermsModal from '../components/Common/TermsModal'
import WelcomePopup from '../components/Common/WelcomePopup'

const RegisterPage: React.FC = () => {
  const navigate = useNavigate()
  const location = useLocation()
  const locState = location.state as { returnTo?: string; intent?: string } | undefined
  const returnTo = locState?.returnTo
  const intent = locState?.intent
  const { login, setTokens, setUser, isAuthenticated } = useAuthStore()
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    confirmPassword: '',
    nickname: '',
  })
  const [verificationCode, setVerificationCode] = useState('')
  const [emailVerified, setEmailVerified] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [sendCodeLoading, setSendCodeLoading] = useState(false)
  const [verifyCodeLoading, setVerifyCodeLoading] = useState(false)
  const [error, setError] = useState('')
  const [agreeTerms, setAgreeTerms] = useState(false)
  const [agreePrivacy, setAgreePrivacy] = useState(false)
  const [showPrivacyModal, setShowPrivacyModal] = useState(false)
  const [showTermsModal, setShowTermsModal] = useState(false)
  const [showSuccess, setShowSuccess] = useState(false)
  const [welcomeMessage, setWelcomeMessage] = useState('the gist. 가입을 감사드립니다.')
  const [welcomePopupData, setWelcomePopupData] = useState<{ userName: string } | null>(null)

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
    if (name === 'email') setError('')
  }

  const handleSendCode = async () => {
    const email = formData.email.trim()
    if (!email) {
      setError('이메일을 입력해주세요.')
      return
    }
    setError('')
    setSendCodeLoading(true)
    try {
      await authApi.sendVerification(email)
      setError('')
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        (err as Error)?.message ??
        '인증 코드 발송에 실패했습니다.'
      setError(msg)
    } finally {
      setSendCodeLoading(false)
    }
  }

  const handleVerifyCode = async () => {
    const email = formData.email.trim()
    const code = verificationCode.trim()
    if (!email || !code || code.length !== 6) {
      setError('이메일과 6자리 인증 코드를 입력해주세요.')
      return
    }
    setError('')
    setVerifyCodeLoading(true)
    try {
      await authApi.verifyCode(email, code)
      setEmailVerified(true)
      setError('')
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        (err as Error)?.message ??
        '인증에 실패했습니다.'
      setError(msg)
    } finally {
      setVerifyCodeLoading(false)
    }
  }

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    if (!emailVerified) {
      setError('이메일 인증을 먼저 완료해주세요.')
      return
    }

    if (!formData.email || !formData.password) {
      setError('이메일과 비밀번호를 입력해주세요.')
      return
    }

    if (formData.password.length < 6) {
      setError('비밀번호는 6자 이상이어야 합니다.')
      return
    }

    if (formData.password !== formData.confirmPassword) {
      setError('비밀번호가 일치하지 않습니다.')
      return
    }

    if (!agreeTerms || !agreePrivacy) {
      setError('이용약관 및 개인정보처리방침에 동의해주세요.')
      return
    }

    setIsLoading(true)

    try {
      const res = await authApi.register(
        formData.email.trim(),
        formData.password,
        formData.nickname.trim() || formData.email.split('@')[0] || 'User'
      )

      if (res.data?.success && res.data?.data) {
        const { user, access_token, refresh_token } = res.data.data
        setTokens(access_token, refresh_token)
        setUser(user)
        localStorage.setItem('user', JSON.stringify(user))
        setWelcomePopupData({
          userName: user?.nickname || user?.email?.split('@')[0] || '회원',
        })
        setShowSuccess(true)
      } else {
        throw new Error(res.data?.message || '회원가입에 실패했습니다.')
      }
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        (err as Error)?.message ??
        '회원가입에 실패했습니다.'
      setError(msg)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    welcomeSettingsApi.getWelcome().then((r) => {
      if (r.data?.success && r.data?.data?.message) setWelcomeMessage(r.data.data.message)
    }).catch(() => {})
  }, [])

  useEffect(() => {
    if (isAuthenticated && !showSuccess) {
      navigate(returnTo || '/', { replace: true })
    }
  }, [isAuthenticated, showSuccess, navigate, returnTo])

  const handleCloseWelcome = () => {
    setShowSuccess(false)
    setWelcomePopupData(null)
    const target = intent === 'subscribe' ? (returnTo || '/subscribe') : (returnTo || '/')
    navigate(target, { replace: true })
  }

  if (showSuccess && welcomePopupData) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-12">
        <WelcomePopup
          isOpen={true}
          onClose={handleCloseWelcome}
          userName={welcomePopupData.userName}
          welcomeMessage={welcomeMessage}
        />
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50 py-12 px-4">
      <TermsModal isOpen={showTermsModal} onClose={() => setShowTermsModal(false)} />
      <PrivacyPolicyModal isOpen={showPrivacyModal} onClose={() => setShowPrivacyModal(false)} />

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="max-w-md mx-auto"
      >
        {/* 로고 */}
        <div className="text-center mb-8">
          <Link to="/" className="inline-block">
            <h1 className="text-5xl text-page" style={{ fontFamily: "'Lobster', cursive" }}>
              the gist.
            </h1>
          </Link>
          <p className="text-gray-500 mt-2">Gisters, Becoming Leaders</p>
        </div>

        <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-8">
          <h2 className="text-2xl font-semibold text-gray-900 text-center mb-6">
            회원가입
          </h2>

          {error && (
            <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm text-center">
              {error}
            </div>
          )}

          <form className="space-y-4" onSubmit={handleRegister}>
            {/* 이메일 + 인증 */}
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                이메일 <span className="text-red-500">*</span>
              </label>
              <div className="flex gap-2">
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  placeholder="example@email.com"
                  disabled={emailVerified}
                  className="flex-1 p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all disabled:bg-gray-100"
                />
                <button
                  type="button"
                  onClick={handleSendCode}
                  disabled={sendCodeLoading || emailVerified}
                  className="px-4 py-3 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium text-sm whitespace-nowrap disabled:opacity-50"
                >
                  {sendCodeLoading ? '발송 중...' : emailVerified ? '인증됨' : '인증번호 발송'}
                </button>
              </div>
            </div>

            {!emailVerified && (
              <div>
                <label htmlFor="verificationCode" className="block text-sm font-medium text-gray-700 mb-1">
                  인증번호 <span className="text-red-500">*</span>
                </label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    id="verificationCode"
                    value={verificationCode}
                    onChange={(e) => setVerificationCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                    placeholder="6자리 숫자"
                    maxLength={6}
                    className="flex-1 p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                  />
                  <button
                    type="button"
                    onClick={handleVerifyCode}
                    disabled={verifyCodeLoading || verificationCode.length !== 6}
                    className="px-4 py-3 rounded-lg bg-primary-500 hover:bg-primary-600 text-white font-medium text-sm whitespace-nowrap disabled:opacity-50"
                  >
                    {verifyCodeLoading ? '확인 중...' : '인증하기'}
                  </button>
                </div>
              </div>
            )}

            {emailVerified && (
              <>
                <div>
                  <label htmlFor="nickname" className="block text-sm font-medium text-gray-700 mb-1">
                    닉네임 <span className="text-gray-400 text-xs">(선택)</span>
                  </label>
                  <input
                    type="text"
                    id="nickname"
                    name="nickname"
                    value={formData.nickname}
                    onChange={handleChange}
                    placeholder="사용할 닉네임"
                    className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                  />
                </div>

                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                    비밀번호 <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="password"
                    id="password"
                    name="password"
                    value={formData.password}
                    onChange={handleChange}
                    placeholder="6자 이상 입력"
                    minLength={6}
                    className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                  />
                </div>

                <div>
                  <label htmlFor="confirmPassword" className="block text-sm font-medium text-gray-700 mb-1">
                    비밀번호 확인 <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="password"
                    id="confirmPassword"
                    name="confirmPassword"
                    value={formData.confirmPassword}
                    onChange={handleChange}
                    placeholder="비밀번호 재입력"
                    className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                  />
                </div>

                <div className="space-y-3">
                  <div className="flex items-start gap-2">
                    <input
                      type="checkbox"
                      id="agreeTerms"
                      checked={agreeTerms}
                      onChange={(e) => setAgreeTerms(e.target.checked)}
                      className="mt-1 w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                    />
                    <label htmlFor="agreeTerms" className="text-sm text-gray-600">
                      <button
                        type="button"
                        onClick={() => setShowTermsModal(true)}
                        className="text-primary-500 hover:text-primary-600 hover:underline cursor-pointer"
                      >
                        이용약관
                      </button>
                      에 동의합니다 <span className="text-red-500">(필수)</span>
                    </label>
                  </div>
                  <div className="flex items-start gap-2">
                    <input
                      type="checkbox"
                      id="agreePrivacy"
                      checked={agreePrivacy}
                      onChange={(e) => setAgreePrivacy(e.target.checked)}
                      className="mt-1 w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                    />
                    <label htmlFor="agreePrivacy" className="text-sm text-gray-600">
                      <button
                        type="button"
                        onClick={() => setShowPrivacyModal(true)}
                        className="text-primary-500 hover:text-primary-600 hover:underline cursor-pointer"
                      >
                        개인정보처리방침
                      </button>
                      에 동의합니다 <span className="text-red-500">(필수)</span>
                    </label>
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={isLoading}
                  className="w-full py-3 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoading ? '가입 중...' : '회원가입'}
                </button>
              </>
            )}
          </form>

          <div className="relative my-6">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-gray-200"></div>
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="px-4 bg-white text-gray-500">또는</span>
            </div>
          </div>

          <button
            type="button"
            onClick={() => { saveAuthReturnState(returnTo, intent); login() }}
            className="w-full flex items-center justify-center gap-3 py-3 bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-lg transition-all"
          >
            <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z" />
            </svg>
            카카오로 시작하기
          </button>

          <button
            type="button"
            onClick={() => {
              saveAuthReturnState(returnTo, intent);
              const base = import.meta.env.VITE_API_URL || '/api';
              window.location.href = `${base}/auth/google`;
            }}
            className="w-full flex items-center justify-center gap-3 py-3 mt-3 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-semibold rounded-lg transition-all"
          >
            <svg className="w-5 h-5" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Google로 시작하기
          </button>

          <div className="mt-6 text-center">
            <p className="text-gray-500 text-sm">
              이미 계정이 있으신가요?{' '}
              <Link to="/login" state={returnTo ? { returnTo, intent } : undefined} className="text-primary-500 hover:text-primary-600 font-medium">
                로그인
              </Link>
            </p>
          </div>
        </div>

        <div className="mt-6 text-center">
          <Link to="/" className="text-gray-500 hover:text-gray-700 text-sm transition-colors">
            ← 홈으로 돌아가기
          </Link>
        </div>
      </motion.div>
    </div>
  )
}

export default RegisterPage
