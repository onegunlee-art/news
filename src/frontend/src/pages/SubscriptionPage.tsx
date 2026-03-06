import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { motion } from 'framer-motion'
import { useAuthStore } from '../store/authStore'
import { api } from '../services/api'

const CONTAINER_CLASS = 'max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4'

const PLANS = [
  {
    id: '1m',
    months: 1,
    title: '1개월',
    price: 7700,
    priceLabel: '7,700원',
    periodLabel: '1개월',
    description: 'The Gist의 뉴스 요약와 인사이트를 1개월간 이용합니다. 언제든 해지 가능합니다.',
    icon: '1m',
    badge: null as string | null,
  },
  {
    id: '3m',
    months: 3,
    title: '3개월',
    price: 18480,
    priceLabel: '18,480원',
    periodLabel: '3개월',
    description: '3개월 구독으로 안정적으로 이용하세요. 월 기준 약 6,160원입니다.',
    icon: '3m',
    badge: null as string | null,
  },
  {
    id: '6m',
    months: 6,
    title: '6개월',
    price: 32340,
    priceLabel: '32,340원',
    periodLabel: '6개월',
    description: '6개월 구독 시 더 유리한 가격. 월 기준 약 5,390원입니다.',
    icon: '6m',
    badge: '인기' as string | null,
  },
  {
    id: '12m',
    months: 12,
    title: '12개월',
    price: 55400,
    priceLabel: '55,400원',
    periodLabel: '12개월',
    description: '1년 구독 시 최저가. 월 기준 약 4,617원으로 가장 합리적입니다.',
    icon: '12m',
    badge: '최저가' as string | null,
  },
]

function PlanIcon({ type }: { type: string }) {
  const base = 'w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600'
  if (type === '1m')
    return (
      <div className={base}>
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
    )
  if (type === '3m')
    return (
      <div className={base}>
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
    )
  if (type === '6m')
    return (
      <div className={base}>
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </div>
    )
  return (
    <div className={base}>
      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
      </svg>
    </div>
  )
}

export default function SubscriptionPage() {
  const [expandedInclude, setExpandedInclude] = useState<string | null>(null)
  const [loadingPlan, setLoadingPlan] = useState<string | null>(null)
  const [loadingOnetime, setLoadingOnetime] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const { isAuthenticated, isSubscribed, accessToken } = useAuthStore()
  const navigate = useNavigate()

  const handleSelectPlan = async (planId: string) => {
    if (!isAuthenticated) {
      navigate('/login')
      return
    }

    if (isSubscribed) {
      setError('이미 구독 중입니다.')
      return
    }

    setLoadingPlan(planId)
    setError(null)

    try {
      const res = await api.post('/subscription/order', { planId }, {
        headers: { Authorization: `Bearer ${accessToken}` },
      })

      if (res.data?.success && res.data?.data?.paymentUrl) {
        window.location.href = res.data.data.paymentUrl
      } else {
        setError(res.data?.message || '주문 생성에 실패했습니다.')
      }
    } catch (err: any) {
      const msg = err.response?.data?.message || '결제 요청 중 오류가 발생했습니다.'
      setError(msg)
    } finally {
      setLoadingPlan(null)
    }
  }

  const handleBuyOnetime = async (onetimeId: string) => {
    if (!isAuthenticated) {
      navigate('/login')
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
      const msg = err.response?.data?.message || '결제 요청 중 오류가 발생했습니다.'
      setError(msg)
    } finally {
      setLoadingOnetime(null)
    }
  }

  return (
    <div className="min-h-screen bg-page">
      <div className={`${CONTAINER_CLASS} py-10 md:py-14`}>
        <motion.section
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4 }}
          className="text-center mb-10 md:mb-12"
        >
          <h1 className="text-2xl md:text-3xl font-bold text-page">구독 플랜</h1>
          <p className="text-page-muted text-sm md:text-base mt-2">
            기간에 맞는 플랜을 선택하고 The Gist를 이용하세요.
          </p>
          {isSubscribed && (
            <div className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-lg">
              <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              <span className="text-green-700 text-sm font-medium">현재 구독 중입니다</span>
            </div>
          )}
          {error && (
            <div className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-red-50 border border-red-200 rounded-lg">
              <span className="text-red-700 text-sm">{error}</span>
            </div>
          )}
        </motion.section>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, delay: 0.1 }}
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6"
        >
          {PLANS.map((plan, i) => (
            <motion.article
              key={plan.id}
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.3, delay: 0.05 * i }}
              className="flex flex-col bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-gray-300 transition-all duration-200 overflow-hidden"
            >
              <div className="p-5 md:p-6 flex flex-col flex-1">
                {plan.badge && (
                  <span className="inline-flex self-start mb-3 px-2.5 py-1 text-[11px] font-semibold text-primary-700 bg-primary-100 rounded-full">
                    {plan.badge}
                  </span>
                )}
                <PlanIcon type={plan.icon} />
                <h2 className="mt-4 text-lg font-bold text-gray-900">{plan.title}</h2>
                <p className="mt-1 text-gray-900 font-semibold">
                  {plan.priceLabel}
                  <span className="text-sm font-normal text-gray-500 ml-1">/ {plan.periodLabel}</span>
                </p>
                <p className="mt-3 text-sm text-gray-600 leading-relaxed flex-1">
                  {plan.description}
                </p>
                <div className="mt-5 flex flex-col gap-2">
                  <button
                    type="button"
                    disabled={!!loadingPlan || isSubscribed}
                    onClick={() => handleSelectPlan(plan.id)}
                    className={`w-full py-2.5 rounded-lg text-sm font-semibold transition-colors ${
                      isSubscribed
                        ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                        : loadingPlan === plan.id
                          ? 'bg-primary-400 text-white cursor-wait'
                          : 'bg-primary-500 hover:bg-primary-600 text-white'
                    }`}
                  >
                    {loadingPlan === plan.id ? '처리 중...' : isSubscribed ? '구독 중' : '선택'}
                  </button>
                  <button
                    type="button"
                    onClick={() => setExpandedInclude(expandedInclude === plan.id ? null : plan.id)}
                    className="w-full py-2 rounded-lg border border-gray-200 text-primary-600 text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-1"
                  >
                    포함 내용
                    <svg
                      className={`w-4 h-4 transition-transform ${expandedInclude === plan.id ? 'rotate-180' : ''}`}
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
                {expandedInclude === plan.id && (
                  <div className="mt-3 p-3 bg-gray-50 rounded-lg text-xs text-gray-600 space-y-1">
                    <p>· The Gist 전체 뉴스 요약 열람</p>
                    <p>· 카테고리별·인기 기사 목록</p>
                    <p>· 오디오 요약 (지원 시)</p>
                    <p>· 기기 제한 없이 이용</p>
                  </div>
                )}
              </div>
            </motion.article>
          ))}
        </motion.div>

        <motion.section
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, delay: 0.25 }}
          className="mt-12 md:mt-16 pt-8 border-t border-gray-200"
        >
          <div className="text-center mb-6">
            <h3 className="text-lg font-semibold text-gray-900">단건 상품</h3>
            <p className="text-sm text-gray-500 mt-1">구독 없이 개별 구매할 수 있는 콘텐츠입니다.</p>
          </div>

          <div className="max-w-md mx-auto">
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-gray-300 transition-all duration-200 overflow-hidden">
              <div className="p-5 md:p-6">
                <span className="inline-flex mb-3 px-2.5 py-1 text-[11px] font-semibold text-blue-700 bg-blue-50 rounded-full">
                  단건 구매
                </span>
                <div className="w-10 h-10 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                  </svg>
                </div>
                <h2 className="mt-4 text-lg font-bold text-gray-900">The Gist 2월호 News Letter</h2>
                <p className="mt-1 text-gray-900 font-semibold">
                  10,900원
                  <span className="text-sm font-normal text-gray-500 ml-1">/ 1회</span>
                </p>
                <p className="mt-3 text-sm text-gray-600 leading-relaxed">
                  The Gist 1,2월호 뉴스레터를 단건으로 구매하실 수 있습니다. 구독 없이도 핵심 인사이트를 확인하세요.
                </p>
                <div className="mt-5">
                  <button
                    type="button"
                    disabled={!!loadingOnetime}
                    onClick={() => handleBuyOnetime('newsletter_feb')}
                    className={`w-full py-2.5 rounded-lg text-sm font-semibold transition-colors ${
                      loadingOnetime === 'newsletter_feb'
                        ? 'bg-blue-400 text-white cursor-wait'
                        : 'bg-blue-600 hover:bg-blue-700 text-white'
                    }`}
                  >
                    {loadingOnetime === 'newsletter_feb' ? '처리 중...' : '구매하기'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </motion.section>

        <motion.section
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.4, delay: 0.35 }}
          className="mt-12 md:mt-16 pt-8 border-t border-gray-200"
        >
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h3 className="text-lg font-semibold text-gray-900">플랜 비교</h3>
              <p className="text-sm text-gray-500 mt-0.5">
                기간별 요금과 혜택을 비교해 나에게 맞는 플랜을 선택하세요.
              </p>
            </div>
            <button
              type="button"
              className="self-start sm:self-center px-5 py-2.5 rounded-lg bg-gray-900 text-white text-sm font-medium hover:bg-gray-800 transition-colors"
            >
              플랜 비교
            </button>
          </div>
          <p className="text-xs text-gray-400 mt-4 text-center">
            이용 시 약관 및 결제 정책이 적용됩니다.
          </p>
        </motion.section>
      </div>
    </div>
  )
}
