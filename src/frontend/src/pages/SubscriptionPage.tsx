import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { api } from '../services/api'
import MaterialIcon from '../components/Common/MaterialIcon'

interface Plan {
  id: string
  label: string
  monthlyPrice: string
  discount: string | null
  billing: string
  renewal: string
  bestValue: boolean
}

const PLANS: Plan[] = [
  {
    id: '12m',
    label: '연간 구독',
    monthlyPrice: '4,620',
    discount: '월간 구독 대비 40% 할인',
    billing: '최초 55,440원 결제, 이후 매년 자동 연장',
    renewal: '',
    bestValue: true,
  },
  {
    id: '6m',
    label: '6개월 구독',
    monthlyPrice: '5,390',
    discount: '월간 구독 대비 30% 할인',
    billing: '최초 32,340원 결제, 기간 종료후 6개월씩 자동 연장',
    renewal: '',
    bestValue: false,
  },
  {
    id: '3m',
    label: '3개월 구독',
    monthlyPrice: '6,160',
    discount: '월간 구독 대비 20% 할인',
    billing: '최초 18,480원 결제, 기간 종료후 3개월씩 자동 연장',
    renewal: '',
    bestValue: false,
  },
  {
    id: '1m',
    label: '1개월 구독',
    monthlyPrice: '7,700',
    discount: null,
    billing: '기간 종료후 1개월씩 자동 연장',
    renewal: '',
    bestValue: false,
  },
]

export default function SubscriptionPage() {
  const [selectedPlan, setSelectedPlan] = useState('12m')
  const [loading, setLoading] = useState(false)
  const [loadingOnetime, setLoadingOnetime] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const { isAuthenticated, isSubscribed, accessToken, isInitialized } = useAuthStore()
  const navigate = useNavigate()

  const handleCheckout = async () => {
    if (!isInitialized) return
    if (!isAuthenticated) {
      navigate('/login', { state: { returnTo: '/subscribe', intent: 'subscribe' } })
      return
    }
    if (isSubscribed) { setError('이미 구독 중입니다.'); return }

    setLoading(true)
    setError(null)
    try {
      const res = await api.post('/subscription/order', { planId: selectedPlan }, {
        headers: { Authorization: `Bearer ${accessToken}` },
      })
      if (res.data?.success && res.data?.data?.paymentUrl) {
        window.location.href = res.data.data.paymentUrl
      } else {
        setError(res.data?.message || '주문 생성에 실패했습니다.')
      }
    } catch (err: any) {
      setError(err.response?.data?.message || '결제 요청 중 오류가 발생했습니다.')
    } finally {
      setLoading(false)
    }
  }

  const handleBuyOnetime = async (onetimeId: string) => {
    if (!isInitialized) return
    if (!isAuthenticated) {
      navigate('/login', { state: { returnTo: '/subscribe', intent: 'subscribe' } })
      return
    }
    setLoadingOnetime(onetimeId)
    setError(null)
    try {
      const res = await api.post('/subscription/order', { onetimeId }, {
        headers: { Authorization: `Bearer ${accessToken}` },
      })
      if (res.data?.success && res.data?.data?.paymentUrl) {
        window.location.href = res.data.data.paymentUrl
      } else {
        setError(res.data?.message || '주문 생성에 실패했습니다.')
      }
    } catch (err: any) {
      setError(err.response?.data?.message || '결제 요청 중 오류가 발생했습니다.')
    } finally {
      setLoadingOnetime(null)
    }
  }

  return (
    <div className="min-h-screen bg-white dark:bg-gray-900">
      <div className="max-w-lg mx-auto px-5 py-10 md:py-16">
        {/* 헤더 */}
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4 }}
          className="text-center mb-10"
        >
          <h1 className="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white leading-snug">
            <span style={{ fontFamily: "'Lobster', cursive" }}>the gist.</span>의 모든 컨텐츠를 만나세요
          </h1>
          <p className="mt-4 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
            외교·정치·안보·분쟁에서
            <br />
            비즈니스·에너지·첨단기술·사회문화에 이르기까지
            <br />
            글로벌 이슈를 관통하는
            <br />
            품격 있는 가치와 생각으로 하루를 시작하세요
          </p>
        </motion.div>

        {/* 비회원: 이미 회원이신가요? 로그인 */}
        {isInitialized && !isAuthenticated && (
          <p className="text-center text-sm text-gray-600 dark:text-gray-400 mb-6">
            이미 계정이 있으신가요?{' '}
            <Link
              to="/login"
              state={{ returnTo: '/subscribe', intent: 'subscribe' }}
              className="text-primary-500 hover:text-primary-600 font-semibold underline underline-offset-2"
            >
              로그인
            </Link>
          </p>
        )}

        {/* 구독 중 안내 */}
        {isSubscribed && (
          <div className="mb-6 flex items-center justify-center gap-2 px-4 py-3 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg">
            <MaterialIcon name="check_circle" className="w-5 h-5 text-green-600 dark:text-green-400" size={20} filled />
            <span className="text-green-700 dark:text-green-300 text-sm font-medium">현재 구독 중입니다</span>
          </div>
        )}

        {/* 에러 */}
        {error && (
          <div className="mb-6 px-4 py-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg text-center">
            <span className="text-red-700 dark:text-red-300 text-sm">{error}</span>
          </div>
        )}

        {/* 플랜 선택 */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, delay: 0.1 }}
          className="space-y-3"
        >
          {PLANS.map((plan) => {
            const isSelected = selectedPlan === plan.id
            return (
              <button
                key={plan.id}
                type="button"
                onClick={() => setSelectedPlan(plan.id)}
                className={`w-full text-left rounded-xl border-2 transition-all duration-200 ${
                  isSelected
                    ? 'border-primary-500 bg-gray-100 dark:bg-gray-800'
                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 hover:border-gray-300 dark:hover:border-gray-600'
                } ${plan.bestValue && isSelected ? 'ring-1 ring-primary-500/30' : ''}`}
              >
                {/* Best Value 배지 */}
                {plan.bestValue && (
                  <div className="px-5 pt-3">
                    <span className="inline-block px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-widest text-primary-500 border border-primary-500 rounded">
                      Best Value
                    </span>
                  </div>
                )}

                <div className={`px-5 ${plan.bestValue ? 'pt-2 pb-4' : 'py-4'}`}>
                  {/* 라디오 + 플랜명 + 가격 */}
                  <div className="flex items-center gap-3">
                    <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-colors ${
                      isSelected ? 'border-primary-500' : 'border-gray-400 dark:border-gray-500'
                    }`}>
                      {isSelected && <div className="w-2.5 h-2.5 rounded-full bg-primary-500" />}
                    </div>
                    <div className="flex-1 flex items-baseline justify-between gap-2">
                      <span className="text-gray-900 dark:text-white font-semibold">{plan.label}</span>
                      <span className="text-gray-900 dark:text-white font-bold whitespace-nowrap">
                        {plan.monthlyPrice}원<span className="text-gray-900 dark:text-white font-bold">/월</span>
                      </span>
                    </div>
                  </div>

                  {/* 할인/설명 */}
                  <div className="ml-8 mt-1.5 space-y-0.5">
                    {plan.discount && (
                      <p className="text-xs text-primary-600 dark:text-primary-400 font-medium">{plan.discount}</p>
                    )}
                    <p className="text-xs text-gray-600 dark:text-gray-400">
                      {plan.billing.startsWith('최초 ') ? (
                        <>
                          <span className="font-bold text-gray-900 dark:text-white">
                            {plan.billing.match(/^최초 [0-9,]+원 결제/)?.[0] ?? plan.billing}
                          </span>
                          {plan.billing.replace(/^최초 [0-9,]+원 결제\s*/, '') ? (
                            <span>{plan.billing.replace(/^최초 [0-9,]+원 결제\s*/, '')}</span>
                          ) : null}
                        </>
                      ) : (
                        plan.billing
                      )}
                    </p>
                  </div>
                </div>
              </button>
            )
          })}
        </motion.div>

        {/* 결제 버튼 */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.4, delay: 0.2 }}
          className="mt-8"
        >
          <button
            type="button"
            disabled={loading || isSubscribed}
            onClick={handleCheckout}
            className={`w-full py-4 rounded-xl text-base font-bold transition-all duration-200 ${
              isSubscribed
                ? 'bg-gray-600 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                : loading
                  ? 'bg-primary-400 text-white cursor-wait'
                  : 'bg-primary-500 hover:bg-primary-600 text-white shadow-lg shadow-primary-500/20'
            }`}
          >
            {loading ? '처리 중...' : isSubscribed ? '구독 중' : '결제하기'}
          </button>
          <p className="text-center text-xs font-bold text-gray-600 dark:text-gray-500 mt-3">
            언제든지 자동 연장을 취소할 수 있습니다
          </p>
        </motion.div>

        {/* 단건 상품 */}
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, delay: 0.3 }}
          className="mt-14 pt-8 border-t border-gray-200 dark:border-gray-700"
        >
          <h3 className="text-center text-sm font-semibold text-gray-600 dark:text-gray-400 mb-4">단건 상품</h3>
          <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-5">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h4 className="text-gray-900 dark:text-white font-semibold text-sm">the gist. 컨텐츠 1건</h4>
                <p className="text-xs text-gray-600 dark:text-gray-400 mt-0.5">구독자가 직접 선택하는 the gist. 컨텐츠 1건</p>
              </div>
              <span className="text-gray-900 dark:text-white font-bold whitespace-nowrap">500원</span>
            </div>
            <button
              type="button"
              disabled={!!loadingOnetime}
              onClick={() => handleBuyOnetime('newsletter_feb')}
              className={`w-full mt-4 py-2.5 rounded-lg text-sm font-semibold transition-colors ${
                loadingOnetime === 'newsletter_feb'
                  ? 'bg-gray-400 dark:bg-gray-600 text-white cursor-wait'
                  : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
              }`}
            >
              {loadingOnetime === 'newsletter_feb' ? '처리 중...' : '구매하기'}
            </button>
          </div>
        </motion.div>

        <p className="text-center text-[10px] text-gray-500 dark:text-gray-400 mt-8">
          이용 시 약관 및 결제 정책이 적용됩니다.
        </p>
      </div>
    </div>
  )
}
