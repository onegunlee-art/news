import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeftIcon } from '@heroicons/react/24/outline'
import { api } from '../services/api'

const DEFAULT_TERMS = `이용약관

주식회사 더지스트(이하 "회사")의 thegist.co.kr(이하 "서비스") 이용약관입니다.

제1조 (목적)
본 약관은 회사가 제공하는 서비스의 이용 조건 및 절차를 규정합니다.

제2조 (약관의 효력)
본 약관은 서비스 화면에 게시하거나 기타의 방법으로 공지함으로써 효력이 발생합니다.

제3조 (서비스의 제공)
회사는 뉴스 분석, AI 기반 콘텐츠 등 서비스를 제공합니다.

제4조 (이용자의 의무)
이용자는 관련 법령 및 본 약관을 준수하여야 합니다.

제5조 (문의)
문의: info@thegist.co.kr`

export default function TermsPage() {
  const [content, setContent] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get<{ success: boolean; data: { content: string | null } }>('/settings/terms')
      .then((res) => {
        const c = res.data?.data?.content
        setContent(c && c.trim() ? c : DEFAULT_TERMS)
      })
      .catch(() => setContent(DEFAULT_TERMS))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 py-12">
        <Link to="/" className="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-8">
          <ArrowLeftIcon className="w-4 h-4" />
          홈으로
        </Link>
        <h1 className="text-2xl font-bold text-gray-900 mb-6">이용약관</h1>
        {loading ? (
          <div className="animate-pulse h-64 bg-gray-200 rounded" />
        ) : (
          <div className="prose prose-gray max-w-none whitespace-pre-wrap text-gray-700 leading-relaxed">
            {content}
          </div>
        )}
      </div>
    </div>
  )
}
