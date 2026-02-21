import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeftIcon } from '@heroicons/react/24/outline'
import { api } from '../services/api'
import { PRIVACY_POLICY_CONTENT } from '../components/Common/PrivacyPolicyContent'

export default function PrivacyPage() {
  const [content, setContent] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get<{ success: boolean; data: { content: string | null } }>('/settings/privacy')
      .then((res) => {
        const c = res.data?.data?.content
        setContent(c && c.trim() ? c : PRIVACY_POLICY_CONTENT)
      })
      .catch(() => setContent(PRIVACY_POLICY_CONTENT))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 py-12">
        <Link to="/" className="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-8">
          <ArrowLeftIcon className="w-4 h-4" />
          홈으로
        </Link>
        <h1 className="text-2xl font-bold text-gray-900 mb-6">개인정보처리방침</h1>
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
