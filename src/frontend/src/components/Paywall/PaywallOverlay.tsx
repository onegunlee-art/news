import { motion } from 'framer-motion'
import { useNavigate } from 'react-router-dom'

interface PaywallOverlayProps {
  isAuthenticated: boolean
  onLogin: () => void
}

export default function PaywallOverlay({ isAuthenticated, onLogin }: PaywallOverlayProps) {
  const navigate = useNavigate()

  return (
    <motion.div
      initial={{ y: 60, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ duration: 0.5, ease: 'easeOut' }}
      className="relative z-10 bg-white dark:bg-gray-900"
    >
      {/* 상단 컬러 보더 (primary 주황) */}
      <div className="h-1 bg-primary-500" />

      <div className="px-6 py-10 text-center max-w-md mx-auto">
        {/* 비로그인: 로그인 안내 */}
        {!isAuthenticated && (
          <p className="text-sm text-page-secondary mb-8">
            이미 계정이 있나요?{' '}
            <button
              onClick={onLogin}
              className="text-page font-semibold underline underline-offset-2 hover:text-primary-500 transition-colors"
            >
              로그인
            </button>
          </p>
        )}

        {/* 메인 헤딩 */}
        <h2 className="text-2xl font-bold text-page leading-snug mb-6">
          구독하고 The Gist의
          <br />
          모든 컨텐츠를 만나세요
        </h2>

        {/* 구독 CTA */}
        <button
          onClick={() => navigate('/subscribe')}
          className="inline-block w-full max-w-xs px-8 py-3.5 bg-primary-500 hover:bg-primary-600
            text-white font-semibold rounded-lg transition-colors text-base shadow-sm"
        >
          구독 플랜 보기
        </button>

        {/* 무료 가입 섹션 (비로그인만) */}
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
              onClick={onLogin}
              className="inline-block w-full max-w-xs px-8 py-3 border-2 border-page
                text-page font-semibold rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800
                transition-colors text-base"
            >
              무료 가입하기
            </button>
          </>
        )}

        {/* 로그인된 무료 회원용 안내 */}
        {isAuthenticated && (
          <p className="text-sm text-page-secondary mt-6 leading-relaxed">
            구독하면 모든 외교·경제·특집 기사를
            <br />
            무제한으로 열람할 수 있습니다.
          </p>
        )}
      </div>
    </motion.div>
  )
}
