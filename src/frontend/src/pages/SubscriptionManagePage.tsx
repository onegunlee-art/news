import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { subscriptionApi, subscriptionSettingsPublicApi, type SubscriptionDetail } from '../services/api'
import { useAuthStore } from '../store/authStore'
import MaterialIcon from '../components/Common/MaterialIcon'
import LoadingSpinner from '../components/Common/LoadingSpinner'

const CONTAINER_CLASS = 'max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4'

const STATUS_MAP: Record<string, { label: string; badgeClass: string }> = {
  ACTIVE: { label: '활성화', badgeClass: 'bg-green-100 text-green-800' },
  PAUSED: { label: '일시정지', badgeClass: 'bg-amber-100 text-amber-800' },
  CANCELED: { label: '취소됨', badgeClass: 'bg-red-100 text-red-800' },
  PAYMENT_FAILED: { label: '결제 실패', badgeClass: 'bg-red-100 text-red-800' },
  PENDING_CANCEL: { label: '취소 예정', badgeClass: 'bg-gray-100 text-gray-800' },
  PENDING_PAUSE: { label: '일시정지 예정', badgeClass: 'bg-amber-100 text-amber-800' },
}

export default function SubscriptionManagePage() {
  const navigate = useNavigate()
  const { isSubscribed } = useAuthStore()
  const [detail, setDetail] = useState<SubscriptionDetail | null>(null)
  const [notice, setNotice] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [togglingAutoRenew, setTogglingAutoRenew] = useState(false)
  const [canceling, setCanceling] = useState(false)
  const [showCancelConfirm, setShowCancelConfirm] = useState(false)

  useEffect(() => {
    if (!isSubscribed) {
      navigate('/profile', { replace: true })
      return
    }
    Promise.all([
      subscriptionApi.getDetail().then((r) => r.data),
      subscriptionSettingsPublicApi.getNotice().then((r) => r.data).catch(() => ({ success: false, data: { notice: '' } })),
    ])
      .then(([detailRes, noticeRes]) => {
        if (detailRes?.success && detailRes.data) {
          setDetail(detailRes.data)
        } else {
          setError('구독 정보를 불러올 수 없습니다.')
        }
        if (noticeRes?.success && noticeRes.data?.notice) {
          setNotice(noticeRes.data.notice)
        }
      })
      .catch(() => setError('구독 정보를 불러올 수 없습니다.'))
      .finally(() => setLoading(false))
  }, [isSubscribed, navigate])

  const handleAutoRenewToggle = async () => {
    if (!detail?.subscription_id || togglingAutoRenew) return
    const next = !detail.auto_renew
    setTogglingAutoRenew(true)
    try {
      const res = await subscriptionApi.setAutoRenew(next)
      if (res.data?.success) {
        setDetail((d) => (d ? { ...d, auto_renew: next } : null))
      } else {
        setError(res.data?.message ?? '자동 갱신 설정에 실패했습니다.')
      }
    } catch {
      setError('자동 갱신 설정에 실패했습니다.')
    } finally {
      setTogglingAutoRenew(false)
    }
  }

  const handleCancel = async () => {
    if (!detail?.subscription_id || canceling) return
    setCanceling(true)
    try {
      const res = await subscriptionApi.cancel()
      if (res.data?.success) {
        setShowCancelConfirm(false)
        setDetail((d) => (d ? { ...d, status: 'CANCELED', status_label: '취소됨', auto_renew: false } : null))
      } else {
        setError(res.data?.message ?? '구독 취소에 실패했습니다.')
      }
    } catch {
      setError('구독 취소에 실패했습니다.')
    } finally {
      setCanceling(false)
    }
  }

  const statusInfo = detail?.status ? STATUS_MAP[detail.status] ?? { label: detail.status_label || detail.status, badgeClass: 'bg-gray-100 text-gray-800' } : null
  const canToggleAutoRenew = detail && ['ACTIVE', 'PAUSED', 'PENDING_PAUSE', 'PENDING_CANCEL'].includes(detail.status)
  const canCancel = detail && ['ACTIVE', 'PAUSED', 'PAYMENT_FAILED'].includes(detail.status)
  const isCanceled = detail?.status === 'CANCELED'

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
            {notice && (
              <section className="bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800 p-4">
                <h2 className="text-xs font-bold text-amber-800 dark:text-amber-200 uppercase tracking-wider mb-2">공지사항</h2>
                <div className="text-sm text-page whitespace-pre-wrap">{notice}</div>
              </section>
            )}

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
                        <span className="text-page-secondary text-sm">다음 결제일</span>
                        <span className="text-page text-sm">{new Date(detail.next_payment_date).toLocaleDateString('ko-KR')}</span>
                      </div>
                    )}
                    <div className="flex items-center justify-between">
                      <span className="text-page-secondary text-sm">결제 금액</span>
                      <span className="text-page font-medium">{detail.amount_formatted}</span>
                    </div>
                  </div>
                </section>

                {canToggleAutoRenew && (
                  <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
                    <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">구독 설정</h2>
                    <div className="px-5 pb-5">
                      <div className="flex items-center justify-between gap-4">
                        <div>
                          <p className="text-page font-medium text-sm">자동 갱신</p>
                          <p className="text-page-secondary text-xs mt-0.5">토글 OFF 시 다음 결제일에 자동 취소됩니다.</p>
                        </div>
                        <button
                          type="button"
                          role="switch"
                          aria-checked={detail.auto_renew}
                          disabled={togglingAutoRenew}
                          onClick={handleAutoRenewToggle}
                          className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-0 ring-0 border-0 disabled:opacity-50 ${
                            detail.auto_renew ? 'bg-primary-500' : 'bg-page-secondary'
                          }`}
                        >
                          <span
                            className={`pointer-events-none inline-block h-5 w-5 shrink-0 transform rounded-full bg-white shadow ring-0 transition duration-200 ${
                              detail.auto_renew ? 'translate-x-5' : 'translate-x-0.5'
                            }`}
                          />
                        </button>
                      </div>
                    </div>
                  </section>
                )}

                {canCancel && (
                  <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
                    <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">구독 취소</h2>
                    <div className="px-5 pb-5">
                      <p className="text-page-secondary text-sm mb-3">취소 시 남은 기간까지 서비스 이용이 가능합니다.</p>
                      <button
                        type="button"
                        onClick={() => setShowCancelConfirm(true)}
                        className="px-4 py-2.5 border border-red-500 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                      >
                        구독 취소하기
                      </button>
                    </div>
                  </section>
                )}

                {isCanceled && (
                  <section className="bg-page rounded-xl border border-page p-5 text-center">
                    <p className="text-page-secondary text-sm mb-3">취소된 구독입니다. 다시 이용하시려면 재구독해 주세요.</p>
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

        {error && detail && (
          <div className="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300 text-sm">
            {error}
          </div>
        )}
      </div>

      {showCancelConfirm && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
            <h3 id="cancel-title" className="text-lg font-semibold text-page mb-2">구독 취소</h3>
            <p className="text-sm text-page-secondary mb-6">정말 구독을 취소하시겠습니까? 남은 기간까지는 이용 가능합니다.</p>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setShowCancelConfirm(false)}
                disabled={canceling}
                className="flex-1 py-2.5 rounded-lg border border-page text-page text-sm font-medium hover:bg-page-secondary transition-colors disabled:opacity-50"
              >
                닫기
              </button>
              <button
                type="button"
                onClick={handleCancel}
                disabled={canceling}
                className="flex-1 py-2.5 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50"
              >
                {canceling ? '처리 중...' : '취소하기'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
