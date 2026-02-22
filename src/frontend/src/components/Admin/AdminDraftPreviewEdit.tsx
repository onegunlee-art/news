import { useState, useCallback } from 'react'
import RichTextEditor from '../Common/RichTextEditor'
import { formatContentHtml, normalizeEditorHtml } from '../../utils/sanitizeHtml'
import { getPlaceholderImageUrl } from '../../utils/imagePolicy'
import { formatSourceDisplayName } from '../../utils/formatSource'
import { extractTitleFromUrl } from '../../utils/extractTitleFromUrl'

/** Admin draft article (from admin/news.php?id=X) */
export interface DraftArticle {
  id: number
  title: string
  subtitle?: string | null
  description?: string | null
  content: string | null
  why_important: string | null
  narration: string | null
  future_prediction?: string | null
  source: string | null
  source_url?: string | null
  original_source?: string | null
  original_title?: string | null
  url: string
  published_at: string | null
  created_at?: string | null
  updated_at?: string | null
  image_url?: string | null
  author?: string | null
  category?: string | null
  status?: string
}

const sanitizeText = (text: string): string =>
  text
    .replace(/[\u201C\u201D\u201E\u201F\u2033\u2036]/g, '"')
    .replace(/[\u2018\u2019\u201A\u201B\u2032\u2035]/g, "'")
    .replace(/[\u2013\u2014\u2015\u2212]/g, '-')
    .replace(/[\u00A0\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200A\u202F\u205F\u3000]/g, ' ')
    .replace(/[\u200B\u200C\u200D\uFEFF]/g, '')
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .replace(/[^\S\n]+/g, ' ')
    .split('\n')
    .map((line) => line.trim())
    .join('\n')
    .replace(/\n{3,}/g, '\n\n')

type EditSection = 'why_important' | 'narration' | 'content' | null

interface AdminDraftPreviewEditProps {
  news: DraftArticle
  onUpdate: (updates: Partial<DraftArticle>) => Promise<void>
  onPublish: () => Promise<void>
  onBack: () => void
}

export default function AdminDraftPreviewEdit({
  news: initialNews,
  onUpdate,
  onPublish,
  onBack,
}: AdminDraftPreviewEditProps) {
  const [news, setNews] = useState<DraftArticle>(initialNews)
  const [editingSection, setEditingSection] = useState<EditSection>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [isPublishing, setIsPublishing] = useState(false)

  const formatDate = () => {
    if (news.published_at) {
      const d = new Date(news.published_at)
      return `${d.getFullYear()}년 ${d.getMonth() + 1}월 ${d.getDate()}일`
    }
    return ''
  }

  const formatHeaderDate = () => {
    const s = news.updated_at || news.created_at
    if (s) {
      const d = new Date(s)
      return `${d.getFullYear()}년 ${d.getMonth() + 1}월 ${d.getDate()}일`
    }
    return ''
  }

  const getSourceName = () => {
    const raw = news.original_source?.trim() || news.source || 'The Gist'
    return formatSourceDisplayName(raw) || 'The Gist'
  }

  const getImageUrl = () => {
    if (news.image_url) return news.image_url
    return getPlaceholderImageUrl(
      {
        id: news.id,
        title: news.title,
        description: news.description ?? null,
        published_at: news.published_at,
        url: news.url,
        source: news.source ?? null,
      },
      800,
      400
    )
  }

  const handleSectionChange = useCallback(
    (field: 'why_important' | 'narration' | 'content', value: string) => {
      setNews((prev) => ({ ...prev, [field]: value }))
    },
    []
  )

  const handleSaveDraft = async () => {
    setIsSaving(true)
    try {
      await onUpdate({
        why_important: news.why_important ? normalizeEditorHtml(news.why_important) : null,
        narration: news.narration ? normalizeEditorHtml(news.narration) : null,
        content: news.content ? normalizeEditorHtml(news.content) : null,
      })
      setEditingSection(null)
    } finally {
      setIsSaving(false)
    }
  }

  const handlePublish = async () => {
    setIsPublishing(true)
    try {
      await onPublish()
    } finally {
      setIsPublishing(false)
    }
  }

  const proseClasses =
    'text-gray-700 leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100'

  return (
    <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-6">
      {/* 상단 액션 바 */}
      <div className="flex flex-wrap items-center gap-3 mb-6 pb-4 border-b border-slate-600">
        <button
          onClick={onBack}
          className="flex items-center gap-1 text-slate-400 hover:text-white transition"
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
          <span className="text-sm">목록으로</span>
        </button>
        <span className="text-slate-500">|</span>
        <button
          onClick={handleSaveDraft}
          disabled={isSaving}
          className="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white text-sm rounded-lg transition disabled:opacity-50"
        >
          {isSaving ? '저장 중...' : '임시 저장 업데이트'}
        </button>
        <button
          onClick={handlePublish}
          disabled={isPublishing}
          className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg transition disabled:opacity-50"
        >
          {isPublishing ? '게시 중...' : '게시하기'}
        </button>
        <span className="text-amber-400/80 text-sm">(유저 페이지와 동일한 형상)</span>
      </div>

      {/* 유저 페이지 형상 */}
      <article className="bg-white rounded-xl shadow-sm overflow-hidden">
        {/* 대표 이미지 */}
        <div className="aspect-video bg-gray-100 overflow-hidden">
          <img
            src={getImageUrl()}
            alt={news.title}
            className="w-full h-full object-cover"
            onError={(e) => {
              ;(e.target as HTMLImageElement).src = getPlaceholderImageUrl(
                {
                  id: news.id,
                  title: news.title,
                  description: news.description ?? null,
                  published_at: news.published_at,
                  url: news.url,
                  source: news.source ?? null,
                },
                800,
                400
              )
            }}
          />
        </div>

        <div className="px-4 pt-5 pb-8">
          {/* 소스 및 날짜 */}
          <div className="flex items-center gap-2 text-sm mb-4">
            <span className="text-primary-500 font-medium">{getSourceName()}</span>
            <span className="text-gray-300"> | </span>
            <span className="text-gray-400">{formatHeaderDate()}</span>
          </div>

          {/* 제목 */}
          <h1 className="text-2xl font-bold text-gray-900 leading-snug mb-2">{news.title}</h1>

          {/* 부제목 */}
          {news.subtitle && (
            <p className="text-lg text-gray-500 italic mb-3 leading-relaxed">{news.subtitle}</p>
          )}

          {/* 매체 설명 */}
          <p className="text-sm text-gray-500 mb-6">
            이 글은 {formatDate() ? `${formatDate()}자, ` : ''}
            {(news.original_source?.trim() || news.source || 'The Gist')}에 게재된 &quot;
            {(news.original_title?.trim() || extractTitleFromUrl(news.url) || '원문')}&quot; 기사를
            The Gist가 AI를 통해 분석/정리한 것 입니다.
          </p>

          {/* 저자 */}
          {news.author && (
            <div className="text-sm text-gray-500 mb-4">
              <span className="font-medium text-gray-700">{news.author}</span> 씀
            </div>
          )}

          {/* The Gist 섹션 */}
          <div className="mb-8">
            <div className="flex items-center justify-between mb-2">
              <h2
                className="text-xl font-semibold tracking-wide"
                style={{ fontFamily: "'Lobster', cursive", color: '#FF6F00' }}
              >
                The Gist
              </h2>
              <button
                onClick={() =>
                  setEditingSection(editingSection === 'why_important' ? null : 'why_important')
                }
                className="text-xs px-2 py-1 rounded bg-slate-600 hover:bg-slate-500 text-slate-200"
              >
                {editingSection === 'why_important' ? '적용' : '수정'}
              </button>
            </div>
            {editingSection === 'why_important' ? (
              <div className="border-l-4 border-orange-500 bg-gradient-to-r from-orange-50/50 via-amber-50/50 to-white rounded-r-xl p-4">
                <RichTextEditor
                  value={news.why_important || ''}
                  onChange={(v) => handleSectionChange('why_important', v)}
                  sanitizePaste={(t) =>
                    sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
                  }
                  placeholder="The Gist's Critique..."
                  rows={6}
                  className="w-full bg-slate-800/30 border border-slate-600 rounded-lg text-slate-200"
                />
              </div>
            ) : (
              <div
                className={`border-l-4 border-orange-500 bg-gradient-to-r from-orange-50 via-amber-50 to-white rounded-r-xl px-5 py-5 ${proseClasses}`}
                dangerouslySetInnerHTML={{
                  __html: formatContentHtml(news.why_important || ''),
                }}
              />
            )}
          </div>

          {/* 내레이션 섹션 */}
          <div className="mb-8">
            <div className="flex items-center justify-between mb-2">
              <h3 className="text-lg font-semibold text-gray-800">내레이션</h3>
              <button
                onClick={() =>
                  setEditingSection(editingSection === 'narration' ? null : 'narration')
                }
                className="text-xs px-2 py-1 rounded bg-slate-600 hover:bg-slate-500 text-slate-200"
              >
                {editingSection === 'narration' ? '적용' : '수정'}
              </button>
            </div>
            {editingSection === 'narration' ? (
              <div className="prose prose-lg max-w-none">
                <RichTextEditor
                  value={news.narration || ''}
                  onChange={(v) => handleSectionChange('narration', v)}
                  sanitizePaste={(t) =>
                    sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
                  }
                  placeholder="내레이션..."
                  rows={10}
                  className="w-full bg-slate-800/30 border border-slate-600 rounded-lg text-slate-200"
                />
              </div>
            ) : (
              <div
                className={`prose prose-lg max-w-none ${proseClasses}`}
                dangerouslySetInnerHTML={{
                  __html: formatContentHtml(news.narration || news.description || ''),
                }}
              />
            )}
          </div>

          {/* 원문 AI 요약/구조 분석 섹션 */}
          <div className="mb-8 border-t border-gray-100 pt-6">
            <div className="flex items-center justify-between mb-3">
              <h3 className="flex items-center gap-2 text-sm font-semibold text-gray-500 uppercase tracking-wider">
                <svg
                  className="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  strokeWidth={2}
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"
                  />
                </svg>
                원문 AI 요약/구조 분석
              </h3>
              <button
                onClick={() =>
                  setEditingSection(editingSection === 'content' ? null : 'content')
                }
                className="text-xs px-2 py-1 rounded bg-slate-600 hover:bg-slate-500 text-slate-200"
              >
                {editingSection === 'content' ? '적용' : '수정'}
              </button>
            </div>
            {editingSection === 'content' ? (
              <div className="p-4 bg-gray-50 rounded-lg border border-gray-100">
                <RichTextEditor
                  value={news.content || ''}
                  onChange={(v) => handleSectionChange('content', v)}
                  sanitizePaste={(t) =>
                    sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
                  }
                  placeholder="원문 AI 요약..."
                  rows={12}
                  className="w-full bg-slate-800/30 border border-slate-600 rounded-lg text-slate-200"
                />
              </div>
            ) : (
              <div
                className={`p-4 bg-gray-50 rounded-lg border border-gray-100 text-sm text-gray-600 ${proseClasses}`}
                dangerouslySetInnerHTML={{
                  __html: formatContentHtml(news.content || ''),
                }}
              />
            )}
          </div>

          {/* 원문 링크 */}
          {news.url && news.url !== '#' && (
            <div className="border-t border-gray-100 pt-6">
              <a
                href={news.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-primary-500 transition-colors"
              >
                <svg
                  className="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  strokeWidth={2}
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                  />
                </svg>
                원문 보기
              </a>
            </div>
          )}
        </div>
      </article>
    </div>
  )
}
