import { motion } from 'framer-motion'
import { useNavigate } from 'react-router-dom'

interface PaywallOverlayProps {
  isAuthenticated: boolean
  /** restriction_type: login_or_subscribe(비회원) | subscription_required(로그인 무료회원) */
  restrictionType?: 'login_or_subscribe' | 'subscription_required' | null
  /** 로그인/회원가입 후 복귀할 경로 (예: /news/123) */
  returnTo?: string
  /** 카카오 로그인 시작 (비회원이 카카오로 빠르게 시작할 때 사용) */
  onKakaoLogin?: () => void
}

export default function PaywallOverlay({
  isAuthenticated,
  restrictionType = 'login_or_subscribe',
  returnTo,
  onKakaoLogin,
}: PaywallOverlayProps) {
  const navigate = useNavigate()
  const state = returnTo ? { returnTo, intent: 'subscribe' as const } : undefined

  const goLogin = () => navigate('/login', { state })
  const goRegister = () => navigate('/register', { state })
  const goSubscribe = () => navigate('/subscribe', { state })

  return (
    <motion.div
      initial={{ y: 60, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ duration: 0.5, ease: 'easeOut' }}
      className="relative z-10 bg-white dark:bg-gray-900"
    >
      <div className="h-1 bg-primary-500" />

      <div className="px-6 py-10 text-center max-w-md mx-auto">
        {/* 비회원: 이미 계정이 있나요? 로그인 */}
        {!isAuthenticated && (
          <p className="text-sm text-page-secondary mb-8">
            이미 계정이 있나요?{' '}
            <button
              onClick={goLogin}
              className="text-page font-semibold underline underline-offset-2 hover:text-primary-500 transition-colors"
            >
              로그인
            </button>
          </p>
        )}

        <h2 className="text-2xl font-bold text-page leading-snug mb-6">
          구독하고 <span style={{ fontFamily: "'Lobster', cursive" }}>the gist.</span>의
          <br />
          모든 컨텐츠를 만나세요
        </h2>

        {/* 구독 CTA */}
        <button
          onClick={goSubscribe}
          className="inline-block w-full max-w-xs px-8 py-3.5 bg-primary-500 hover:bg-primary-600
            text-white font-semibold rounded-lg transition-colors text-base shadow-sm"
        >
          구독 플랜 보기
        </button>

        {/* 비회원: 무료 회원가입 (이메일/카카오 선택 가능한 /register로) */}
        {!isAuthenticated && (
          <>
            <div className="flex items-center gap-4 my-8">
              <div className="flex-1 h-px bg-gray-200 dark:bg-gray-700" />
              <span className="text-sm text-page-muted">또는</span>
              <div className="flex-1 h-px bg-gray-200 dark:bg-gray-700" />
            </div>

            <p className="text-sm text-page-secondary mb-4 leading-relaxed">
              무료 가입하고 매일 최신 컨텐츠 2개를 열람하세요.
            </p>

            <button
              onClick={goRegister}
              className="inline-block w-full max-w-xs px-8 py-3 border-2 border-page
                text-page font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800
                transition-colors text-base"
            >
              무료 회원가입
            </button>
            {onKakaoLogin && (
              <button
                onClick={onKakaoLogin}
                className="mt-3 w-full max-w-xs flex items-center justify-center gap-2 px-8 py-3
                  bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-lg transition-colors"
              >
                <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z" />
                </svg>
                카카오로 무료 시작
              </button>
            )}
          </>
        )}

        {/* 로그인된 무료 회원: 구독 업셀 */}
        {isAuthenticated && restrictionType === 'subscription_required' && (
          <p className="text-sm text-page-secondary mt-6 leading-relaxed">
            이 기사는 구독 전용입니다. 구독하면 모든 외교·경제·특집 기사를 무제한으로 열람할 수 있습니다.
          </p>
        )}
        {isAuthenticated && restrictionType !== 'subscription_required' && (
          <p className="text-sm text-page-secondary mt-6 leading-relaxed">
            구독하면 모든 외교·경제·특집 기사를 무제한으로 열람할 수 있습니다.
          </p>
        )}
      </div>
    </motion.div>
  )
}
