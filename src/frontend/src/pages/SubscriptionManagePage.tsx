import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { subscriptionApi, subscriptionSettingsPublicApi, type SubscriptionDetail } from '../services/api'
import { useAuthStore } from '../store/authStore'
import MaterialIcon from '../components/Common/MaterialIcon'
import LoadingSpinner from '../components/Common/LoadingSpinner'

const CONTAINER_CLASS = 'max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4'

const STATUS_MAP: Record<string, { label: string; badgeClass: string }> = {
  ACTIVE: { label: '활성화', badgeClass: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' },
  PAUSED: { label: '일시정지', badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
  CANCELED: { label: '취소됨', badgeClass: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
  PAYMENT_FAILED: { label: '결제 실패', badgeClass: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
  PENDING_CANCEL: { label: '취소 예정', badgeClass: 'bg-gray-100 text-gray-800 dark:bg-gray-700/30 dark:text-gray-300' },
  PENDING_PAUSE: { label: '일시정지 예정', badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
}

export default function SubscriptionManagePage() {
  const navigate = useNavigate()
  const { isSubscribed } = useAuthStore()
  const [detail, setDetail] = useState<SubscriptionDetail | null>(null)
  const [notice, setNotice] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [canceling, setCanceling] = useState(false)
  const [showCancelConfirm, setShowCancelConfirm] = useState(false)
  const [cancelSuccess, setCancelSuccess] = useState(false)

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

  const handleCancel = async () => {
    if (!detail?.subscription_id || canceling) return
    setCanceling(true)
    try {
      const res = await subscriptionApi.cancel()
      if (res.data?.success) {
        setShowCancelConfirm(false)
        setCancelSuccess(true)
        setDetail((d) => (d ? { ...d, status: 'PENDING_CANCEL', status_label: '취소 예정', auto_renew: false } : null))
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
  const canCancel = detail && ['ACTIVE', 'PAUSED', 'PAYMENT_FAILED'].includes(detail.status)
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
              <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
                <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">공지사항</h2>
                <div className="px-5 pb-5">
                  <p className="text-page-secondary text-sm whitespace-pre-wrap">{notice}</p>
                </div>
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

                {canCancel && (
                  <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
                    <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">구독 해지</h2>
                    <div className="px-5 pb-5 space-y-3">
                      <div className="text-page-secondary text-sm space-y-1.5">
                        <p>구독을 해지하면 다음과 같이 처리됩니다.</p>
                        <ul className="list-disc list-inside space-y-1 pl-1">
                          <li>다음 결제일에 자동 결제가 이루어지지 않습니다.</li>
                          <li>현재 결제 기간 종료일까지 서비스를 정상 이용할 수 있습니다.</li>
                          <li>해지 후에도 언제든 다시 구독할 수 있습니다.</li>
                        </ul>
                      </div>
                      <button
                        type="button"
                        onClick={() => setShowCancelConfirm(true)}
                        className="px-4 py-2.5 border border-page text-page-secondary rounded-lg text-sm font-medium hover:bg-page-secondary/50 transition-colors"
                      >
                        구독 해지 신청
                      </button>
                    </div>
                  </section>
                )}

                {isPendingCancel && (
                  <section className="bg-page rounded-xl overflow-hidden shadow-sm border border-page">
                    <h2 className="px-5 py-4 text-xs font-bold text-primary-500 uppercase tracking-wider">해지 예정</h2>
                    <div className="px-5 pb-5 space-y-3">
                      <p className="text-page-secondary text-sm">
                        구독 해지가 예약되었습니다.{' '}
                        {detail.next_payment_date && (
                          <>
                            <span className="text-page font-medium">{new Date(detail.next_payment_date).toLocaleDateString('ko-KR')}</span>까지 서비스를 이용하실 수 있습니다.
                          </>
                        )}
                      </p>
                      <p className="text-page-secondary text-sm">
                        해지를 취소하고 구독을 계속하시려면 아래 버튼을 눌러주세요.
                      </p>
                      <button
                        type="button"
                        onClick={async () => {
                          try {
                            const res = await subscriptionApi.setAutoRenew(true)
                            if (res.data?.success) {
                              setDetail((d) => (d ? { ...d, status: 'ACTIVE', status_label: '활성화', auto_renew: true } : null))
                              setCancelSuccess(false)
                            } else {
                              setError(res.data?.message ?? '구독 재개에 실패했습니다.')
                            }
                          } catch {
                            setError('구독 재개에 실패했습니다.')
                          }
                        }}
                        className="px-4 py-2.5 bg-primary-500 text-white rounded-lg text-sm font-medium hover:bg-primary-600 transition-colors"
                      >
                        해지 취소 (구독 유지)
                      </button>
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

        {error && detail && (
          <div className="mt-4 p-3 bg-page rounded-lg border border-page text-page-secondary text-sm">
            {error}
          </div>
        )}
      </div>

      {showCancelConfirm && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6">
            <h3 id="cancel-title" className="text-lg font-semibold text-page mb-3">구독 해지 확인</h3>
            <div className="text-sm text-page-secondary space-y-2 mb-6">
              <p>구독을 해지하시면:</p>
              <ul className="list-disc list-inside space-y-1 pl-1">
                <li>다음 결제일부터 자동 결제가 중단됩니다.</li>
                {detail?.next_payment_date && (
                  <li>
                    <span className="text-page font-medium">{new Date(detail.next_payment_date).toLocaleDateString('ko-KR')}</span>
                    까지 서비스를 이용할 수 있습니다.
                  </li>
                )}
                <li>해지 후에도 언제든 다시 구독하실 수 있습니다.</li>
              </ul>
            </div>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setShowCancelConfirm(false)}
                disabled={canceling}
                className="flex-1 py-2.5 rounded-lg border border-page text-page text-sm font-medium hover:bg-page-secondary transition-colors disabled:opacity-50"
              >
                유지하기
              </button>
              <button
                type="button"
                onClick={handleCancel}
                disabled={canceling}
                className="flex-1 py-2.5 rounded-lg border border-page text-page-secondary text-sm font-medium hover:bg-page-secondary transition-colors disabled:opacity-50"
              >
                {canceling ? '처리 중...' : '해지하기'}
              </button>
            </div>
          </div>
        </div>
      )}

      {cancelSuccess && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" role="dialog" aria-modal="true">
          <div className="bg-page border border-page rounded-xl shadow-xl max-w-sm w-full p-6 text-center">
            <p className="text-page font-medium mb-2">구독 해지가 예약되었습니다.</p>
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
