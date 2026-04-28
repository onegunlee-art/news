import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { subscriptionApi, type SubscriptionDetail } from '../services/api'
import { useAuthStore } from '../store/authStore'
import MaterialIcon from '../components/Common/MaterialIcon'
import LoadingSpinner from '../components/Common/LoadingSpinner'

const CONTAINER_CLASS = 'max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4'

const STATUS_MAP: Record<string, { label: string; badgeClass: string }> = {
  ACTIVE: { label: '활성화', badgeClass: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' },
  PAUSED: { label: '일시정지', badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
  CANCELED: { label: '취소됨', badgeClass: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
  PAYMENT_FAILED: { label: '결제 실패', badgeClass: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
  PENDING_CANCEL: { label: '해지 예정', badgeClass: 'bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-300' },
  PENDING_PAUSE: { label: '일시정지 예정', badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
}

export default function SubscriptionManagePage() {
  const navigate = useNavigate()
  const { isAuthenticated, isInitialized } = useAuthStore()
  const [detail, setDetail] = useState<SubscriptionDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [toggling, setToggling] = useState(false)
  const [showOffConfirm, setShowOffConfirm] = useState(false)
  const [cancelSuccess, setCancelSuccess] = useState(false)
  // 서버 응답 기준으로 진입 분기. 클라 isSubscribed 캐시(localStorage)는
  // 결제/만료 직후 서버와 어긋날 수 있어 잘못된 redirect 가 발생할 수 있으므로
  // 마운트 시 무조건 detail 을 호출하고 그 결과로 분기한다.
  const [needsSubscribe, setNeedsSubscribe] = useState(false)

  useEffect(() => {
    // 인증 초기화 전이면 잠시 대기 (refresh 토큰 회전 후 호출하기 위함)
    if (!isInitialized) return
    // 비로그인 사용자는 로그인으로 보냄 (returnTo 유지)
    if (!isAuthenticated) {
      navigate('/login', { state: { returnTo: '/subscription/manage' }, replace: true })
      return
    }
    let cancelled = false
    subscriptionApi.getDetail()
      .then((r) => {
        if (cancelled) return
        if (r.data?.success && r.data.data) {
          setDetail(r.data.data)
        } else {
          // 서버는 200 인데 data 가 비어 있는 경우 = 구독 이력 없음
          setNeedsSubscribe(true)
        }
      })
      .catch((err: { response?: { status?: number } }) => {
        if (cancelled) return
        const status = err?.response?.status
        if (status === 401) {
          navigate('/login', { state: { returnTo: '/subscription/manage' }, replace: true })
          return
        }
        if (status === 404) {
          // 구독 정보 자체가 없음 → 구독 페이지 안내
          setNeedsSubscribe(true)
          return
        }
        setError('구독 정보를 불러올 수 없습니다. 잠시 후 다시 시도해 주세요.')
      })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [isAuthenticated, isInitialized, navigate])

  const isAutoRenewOn = detail ? detail.auto_renew : true
  const canToggle = detail && ['ACTIVE', 'PAUSED', 'PENDING_CANCEL', 'PENDING_PAUSE'].includes(detail.status)

  const handleToggleClick = () => {
    if (!detail?.subscription_id || toggling) return
    if (isAutoRenewOn) {
      setShowOffConfirm(true)
    } else {
      handleResumeAutoRenew()
    }
  }

  const handleCancelAutoRenew = async () => {
    if (!detail?.subscription_id || toggling) return
    setToggling(true)
    setError(null)
    try {
      const res = await subscriptionApi.setAutoRenew(false)
      if (res.data?.success) {
        setShowOffConfirm(false)
        setCancelSuccess(true)
        setDetail((d) => (d ? { ...d, status: 'PENDING_CANCEL', status_label: '해지 예정', auto_renew: false } : null))
      } else {
        setShowOffConfirm(false)
        setError(res.data?.message ?? '구독 연장 종료 처리에 실패했습니다.')
      }
    } catch {
      setShowOffConfirm(false)
      setError('구독 연장 종료 처리에 실패했습니다. 잠시 후 다시 시도해 주세요.')
    } finally {
      setToggling(false)
    }
  }

  const handleResumeAutoRenew = async () => {
    if (!detail?.subscription_id || toggling) return
    setToggling(true)
    setError(null)
    try {
      const res = await subscriptionApi.setAutoRenew(true)
      if (res.data?.success) {
        setDetail((d) => (d ? { ...d, status: 'ACTIVE', status_label: '활성화', auto_renew: true } : null))
        setCancelSuccess(false)
      } else {
        setError(res.data?.message ?? '구독 연장 재개에 실패했습니다.')
      }
    } catch {
      setError('구독 연장 재개에 실패했습니다. 잠시 후 다시 시도해 주세요.')
    } finally {
      setToggling(false)
    }
  }

  const statusInfo = detail?.status ? STATUS_MAP[detail.status] ?? { label: detail.status_label || detail.status, badgeClass: 'bg-gray-100 text-gray-800' } : null
  const isCanceled = detail?.status === 'CANCELED'
  const isPendingCancel = detail?.status === 'PENDING_CANCEL'

  return (
    <div className="min-h-screen bg-page pb-24">
      <div className={CONTAINER_CLASS}>
        <header className="pt-12 pb-6 flex items-center gap-4">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="p-2 -ml-2 rounded-lg text-page-secondary hover:text-page hover:bg-page-secondary transition-colors"
            aria-label="뒤로 가기"
          >
            <MaterialIcon name="arrow_back" size={24} />
          </button>
          <h1 className="text-xl font-bold text-page">구독 관리</h1>
        </header>

        {loading ? (
          <div className="flex justify-center py-16">
            <LoadingSpinner size="large" />
          </div>
        ) : needsSubscribe ? (
          <div className="bg-page rounded-xl border border-page p-6 text-center">
            <p className="text-page-secondary mb-4">현재 활성화된 구독이 없습니다.</p>
            <div className="flex justify-center gap-2">
              <button
                type="button"
                onClick={() => navigate('/subscribe')}
                className="px-4 py-2 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600"
              >
                구독하러 가기
              </button>
              <button
                type="button"
                onClick={() => navigate('/profile')}
                className="px-4 py-2 bg-page-secondary text-page rounded-lg text-sm font-medium hover:bg-page-tertiary"
              >
                My Page로 이동
              </button>
            </div>
          </div>
        ) : error && !detail ? (
          <div className="bg-page rounded-xl border border-page p-6 text-center">
            <p className="text-page-secondary mb-4">{error}</p>
            <button
              type="button"
              onClick={() => navigate('/profile')}
              className="px-4 py-2 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600"
            >
              My Page로 이동
            </button>
          </div>
        ) : (
          <div className="space-y-6">
            {detail && (
              <>
                <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
                  <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">현재 구독 정보</h2>
                  <div className="px-5 pb-5 space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-page-secondary text-sm">플랜</span>
                      <span className="text-page font-medium">{detail.plan_name}</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-page-secondary text-sm">상태</span>
                      {statusInfo && (
                        <span className={`inline-block px-2.5 py-0.5 text-xs font-medium rounded-md ${statusInfo.badgeClass}`}>
                          {statusInfo.label}
                        </span>
                      )}
                    </div>
                    {detail.start_date && (
                      <div className="flex items-center justify-between">
                        <span className="text-page-secondary text-sm">구독 시작일</span>
                        <span className="text-page text-sm">{new Date(detail.start_date).toLocaleDateString('ko-KR')}</span>
                      </div>
                    )}
                    {detail.next_payment_date && (
                      <div className="flex items-center justify-between">
                        <span className="text-page-secondary text-sm">{isPendingCancel ? '서비스 이용 종료일' : '다음 결제일'}</span>
                        <span className="text-page text-sm">{new Date(detail.next_payment_date).toLocaleDateString('ko-KR')}</span>
                      </div>
                    )}
                    <div className="flex items-center justify-between">
                      <span className="text-page-secondary text-sm">결제 금액</span>
                      <span className="text-page font-medium">{detail.amount_formatted}</span>
                    </div>
                  </div>
                </section>

                {canToggle && (
                  <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
                    <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">구독 설정</h2>
                    <ul className="divide-y divide-[var(--border-color)]">
                      <li className="flex items-center justify-between gap-3 px-5 py-4">
                        <span className="flex items-center gap-3 text-page text-sm font-medium">
                          <MaterialIcon name="autorenew" className="w-5 h-5 text-page-secondary flex-shrink-0" size={20} />
                          구독 연장 종료
                        </span>
                        <button
                          type="button"
                          role="switch"
                          aria-checked={!isAutoRenewOn}
                          disabled={toggling}
                          onClick={handleToggleClick}
                          className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-0 ring-0 border-0 disabled:opacity-50 ${
                            !isAutoRenewOn ? 'bg-primary-500' : 'bg-page-secondary'
                          }`}
                        >
                          <span
                            className={`pointer-events-none inline-block h-5 w-5 shrink-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ${
                              !isAutoRenewOn ? 'translate-x-5' : 'translate-x-0.5'
                            }`}
                          />
                        </button>
                      </li>
                    </ul>
                    <div className="px-5 pb-4">
                      <p className="text-page-secondary text-xs">
                        {isAutoRenewOn
                          ? '다음 결제일에 자동으로 구독이 연장됩니다.'
                          : '다음 결제일부터 자동 결제가 중단되며, 현재 기간까지 서비스를 이용할 수 있습니다.'}
                      </p>
                      {error && (
                        <p className="mt-2 text-red-600 dark:text-red-400 text-xs font-medium">{error}</p>
                      )}
                    </div>
                  </section>
                )}

                {isCanceled && (
                  <section className="bg-page rounded-xl border border-page p-5 text-center">
                    <p className="text-page-secondary text-sm mb-3">구독이 종료되었습니다. 다시 이용하시려면 재구독해 주세요.</p>
                    <button
                      type="button"
                      onClick={() => navigate('/subscribe')}
                      className="px-4 py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600"
                    >
                      구독하기
                    </button>
                  </section>
                )}
              </>
            )}
          </div>
        )}

      
      </div>

      {showOffConfirm && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
            <h3 id="cancel-title" className="text-lg font-semibold text-page mb-3">구독 연장 종료</h3>
            <div className="text-sm text-page-secondary space-y-2 mb-6">
              <p>구독 연장을 종료하시면:</p>
              <ul className="list-disc list-inside space-y-1 pl-1">
                <li>다음 결제일부터 자동 결제가 중단됩니다.</li>
                {detail?.next_payment_date && (
                  <li>
                    <span className="text-page font-medium">{new Date(detail.next_payment_date).toLocaleDateString('ko-KR')}</span>
                    까지 서비스를 이용할 수 있습니다.
                  </li>
                )}
                <li>언제든 다시 구독 연장을 켤 수 있습니다.</li>
              </ul>
            </div>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setShowOffConfirm(false)}
                disabled={toggling}
                className="flex-1 py-2.5 rounded-lg border border-page text-page text-sm font-medium hover:bg-page-secondary transition-colors disabled:opacity-50"
              >
                유지하기
              </button>
              <button
                type="button"
                onClick={handleCancelAutoRenew}
                disabled={toggling}
                className="flex-1 py-2.5 rounded-lg border border-page text-page-secondary text-sm font-medium hover:bg-page-secondary transition-colors disabled:opacity-50"
              >
                {toggling ? '처리 중...' : '종료하기'}
              </button>
            </div>
          </div>
        </div>
      )}

      {cancelSuccess && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6 text-center">
            <p className="text-page font-medium mb-2">구독 연장이 종료되었습니다.</p>
            {detail?.next_payment_date && (
              <p className="text-page-secondary text-sm mb-4">
                {new Date(detail.next_payment_date).toLocaleDateString('ko-KR')}까지 서비스를 정상 이용하실 수 있습니다.
              </p>
            )}
            <button
              type="button"
              onClick={() => setCancelSuccess(false)}
              className="px-6 py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 transition-colors"
            >
              확인
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
