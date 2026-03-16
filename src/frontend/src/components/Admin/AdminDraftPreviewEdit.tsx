import { useState, useCallback, useEffect, useRef, useMemo } from 'react'
import MaterialIcon from '../Common/MaterialIcon'
import RichTextEditor from '../Common/RichTextEditor'
import { formatContentHtml, normalizeEditorHtml, ensureBrForEditor } from '../../utils/sanitizeHtml'
import { getPlaceholderImageUrl } from '../../utils/imagePolicy'
import { formatSourceDisplayName, buildEditorialLine, parseEditorialLine } from '../../utils/formatSource'
import { extractTitleFromUrl } from '../../utils/extractTitleFromUrl'
import { adminFetch } from '../../services/api'
import { useMenuConfig } from '../../hooks/useMenuConfig'

/** Admin draft article (from admin/news.php?id=X) */
export interface DraftArticle {
  id: number
  title: string
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
  category_parent?: string | null
  status?: string
}

// eslint-disable-next-line no-misleading-character-class
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

type EditSection = 'title' | 'editorial' | 'why_important' | 'narration' | 'content' | null

const CATEGORIES = [
  { id: 'diplomacy', name: '외교', color: 'from-blue-500 to-cyan-500' },
  { id: 'economy', name: '경제', color: 'from-emerald-500 to-green-500' },
  { id: 'special', name: '특집', color: 'from-orange-500 to-red-500' },
] as const

interface AdminDraftPreviewEditProps {
  news: DraftArticle
  onUpdate: (updates: Partial<DraftArticle>) => Promise<void>
  onPublish: (currentState: DraftArticle) => Promise<void>
  onBack: () => void
}

export default function AdminDraftPreviewEdit({
  news: initialNews,
  onUpdate,
  onPublish,
  onBack,
}: AdminDraftPreviewEditProps) {
  const { subCategoryToLabel } = useMenuConfig()
  const subCategoryOptions = useMemo(
    () => Object.entries(subCategoryToLabel).map(([value, label]) => ({ value, label })),
    [subCategoryToLabel]
  )

  const [news, setNews] = useState<DraftArticle>(initialNews)
  const [editingSection, setEditingSection] = useState<EditSection>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [isPublishing, setIsPublishing] = useState(false)
  const [saveMsg, setSaveMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null)
  const [categoryParent, setCategoryParent] = useState<string>(() =>
    initialNews.category_parent ?? (initialNews.category === 'entertainment' ? 'special' : initialNews.category ?? 'diplomacy')
  )
  const [categorySub, setCategorySub] = useState<string>(() => {
    const sub = initialNews.category || ''
    return subCategoryOptions.some((o) => o.value === sub) ? sub : sub ? '__custom__' : ''
  })
  const [categorySubCustom, setCategorySubCustom] = useState<string>(() => {
    const sub = initialNews.category || ''
    return subCategoryOptions.some((o) => o.value === sub) ? '' : sub
  })
  const [dallePrompt, setDallePrompt] = useState('')
  const [isRegeneratingDalle, setIsRegeneratingDalle] = useState(false)
  const [thumbnailMessage, setThumbnailMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)
  const editorialInputRef = useRef<HTMLInputElement>(null)
  const contentEditorRef = useRef<HTMLDivElement>(null)
  const narrationEditorRef = useRef<HTMLDivElement>(null)
  const whyImportantEditorRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    setNews(initialNews)
  }, [initialNews])

  useEffect(() => {
    const parent =
      initialNews.category_parent ?? (initialNews.category === 'entertainment' ? 'special' : initialNews.category ?? 'diplomacy')
    setCategoryParent(parent)
    const sub = initialNews.category || ''
    if (subCategoryOptions.some((o) => o.value === sub)) {
      setCategorySub(sub)
      setCategorySubCustom('')
    } else {
      setCategorySub(sub ? '__custom__' : '')
      setCategorySubCustom(sub)
    }
  }, [initialNews.category_parent, initialNews.category, subCategoryOptions])

  const formatHeaderDate = () => {
    const s = news.updated_at || news.created_at
    if (s) {
      const d = new Date(s)
      return `${d.getFullYear()}년 ${d.getMonth() + 1}월 ${d.getDate()}일`
    }
    return ''
  }

  const getSourceName = () => {
    const raw = news.original_source?.trim() || news.source || 'the gist.'
    return formatSourceDisplayName(raw) || 'the gist.'
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
    (field: 'title' | 'why_important' | 'narration' | 'content', value: string) => {
      setNews((prev) => ({ ...prev, [field]: value }))
    },
    []
  )

  const getEditorialLine = () =>
    buildEditorialLine({
      sourceDisplay: formatSourceDisplayName(news.original_source?.trim() || news.source || 'the gist.') || 'the gist.',
      originalTitle: news.original_title?.trim() || extractTitleFromUrl(news.url) || '원문',
    })

  const getSubCategoryValue = () =>
    categorySub === '__custom__' ? (categorySubCustom || '').trim() : categorySub || ''

  const handleSaveDraft = async () => {
    setIsSaving(true)
    setSaveMsg(null)
    try {
      const subVal = getSubCategoryValue()
      const finalContent = contentEditorRef.current?.innerHTML ?? news.content
      const finalNarration = narrationEditorRef.current?.innerHTML ?? news.narration
      const finalWhyImportant = whyImportantEditorRef.current?.innerHTML ?? news.why_important
      const normalizedContent = finalContent ? normalizeEditorHtml(finalContent) : null
      const normalizedNarration = finalNarration ? normalizeEditorHtml(finalNarration) : null
      const normalizedWhyImportant = finalWhyImportant ? normalizeEditorHtml(finalWhyImportant) : null

      await onUpdate({
        category_parent: categoryParent,
        category: subVal || null,
        title: news.title,
        original_source: news.original_source ?? null,
        original_title: news.original_title ?? null,
        why_important: normalizedWhyImportant,
        narration: normalizedNarration,
        content: normalizedContent,
        image_url: news.image_url ?? null,
      })
      setNews((prev) => ({
        ...prev,
        category_parent: categoryParent,
        category: subVal || null,
        why_important: normalizedWhyImportant,
        narration: normalizedNarration,
        content: normalizedContent,
      }))
      setEditingSection(null)
      setSaveMsg({ type: 'success', text: '임시저장이 업데이트되었습니다.' })
      setTimeout(() => setSaveMsg(null), 4000)
    } catch (e) {
      setSaveMsg({ type: 'error', text: (e as Error).message || '임시저장 업데이트 실패' })
    } finally {
      setIsSaving(false)
    }
  }

  const handlePublish = async () => {
    setIsPublishing(true)
    try {
      const subVal = getSubCategoryValue()
      const current: DraftArticle = {
        ...news,
        category_parent: categoryParent,
        category: subVal || null,
        original_source: news.original_source ?? null,
        original_title: news.original_title ?? null,
        why_important: news.why_important ? normalizeEditorHtml(news.why_important) : null,
        narration: news.narration ? normalizeEditorHtml(news.narration) : null,
        content: news.content ? normalizeEditorHtml(news.content) : null,
        image_url: news.image_url ?? null,
      }
      await onPublish(current)
    } finally {
      setIsPublishing(false)
    }
  }

  // 유저 페이지(NewsDetailPage)와 동일한 스타일 - 문맥별로 나뉜 형태 유지
  const proseClasses =
    'leading-relaxed whitespace-pre-wrap [&_mark]:rounded-sm [&_mark]:px-0.5 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-0.5 [&_table]:border-collapse [&_table]:w-full [&_table]:my-2 [&_td]:border [&_td]:border-gray-300 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-gray-300 [&_th]:px-2 [&_th]:py-1.5 [&_th]:font-semibold [&_th]:bg-gray-100'

  return (
    <div className="max-w-lg md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto px-4 py-6">
      {/* 상단 액션 바 */}
      <div className="flex flex-wrap items-center gap-3 mb-6 pb-4 border-b border-slate-600">
        <button
          onClick={onBack}
          className="flex items-center gap-1 text-slate-400 hover:text-white transition"
        >
          <MaterialIcon name="arrow_back" className="w-5 h-5" size={20} />
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

      {saveMsg && (
        <div className={`mb-4 px-4 py-3 rounded-lg text-sm font-medium ${
          saveMsg.type === 'success'
            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
            : 'bg-red-500/20 text-red-300 border border-red-500/30'
        }`}>
          {saveMsg.text}
        </div>
      )}

      {/* 카테고리 선택 (뉴스 작성과 동일: 상위 → 하위) */}
      <div className="mb-6 space-y-3">
        <div className="flex gap-3 flex-wrap">
          {CATEGORIES.map((cat) => (
            <button
              key={cat.id}
              type="button"
              onClick={() => setCategoryParent(cat.id)}
              className={`px-5 py-3 rounded-xl font-medium transition-all ${
                categoryParent === cat.id
                  ? `bg-gradient-to-r ${cat.color} text-white shadow-lg`
                  : 'bg-slate-800/50 text-slate-300 hover:bg-slate-700/50 border border-slate-700/50'
              }`}
            >
              {cat.name}
            </button>
          ))}
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <span className="text-slate-400 text-sm font-medium">하위 카테고리:</span>
          <select
            value={categorySub}
            onChange={(e) => {
              const v = e.target.value
              if (v === '__custom__') {
                setCategorySub('__custom__')
              } else {
                setCategorySub(v)
                setCategorySubCustom('')
              }
            }}
            className="bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm min-w-[180px]"
          >
            <option value="">선택 (선택사항)</option>
            {subCategoryOptions.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
            <option value="__custom__">직접 입력</option>
          </select>
          {categorySub === '__custom__' ? (
            <input
              type="text"
              value={categorySubCustom}
              onChange={(e) => setCategorySubCustom(e.target.value)}
              placeholder="직접 입력 시 여기에 입력"
              className="bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm w-48 placeholder-slate-500"
            />
          ) : null}
        </div>
      </div>

      {/* 유저 페이지 형상 */}
      <article className="bg-white rounded-xl shadow-sm overflow-visible">
        {/* 대표 이미지 */}
        <div className="aspect-video bg-gray-100 overflow-hidden">
          <img
            src={getImageUrl()}
            alt={news.title}
            className="w-full h-full object-cover"
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(
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

        {/* DALL-E로 썸네일 수정 */}
        <div className="px-4 py-4 border-t border-gray-200 bg-gray-50">
          <label className="block text-gray-600 text-sm font-medium mb-1">DALL-E로 썸네일 수정</label>
          <p className="text-gray-500 text-xs mb-2">기사 제목을 넣으면 메타포 카툰 스타일로 썸네일을 생성합니다 (비워두면 뉴스 제목 사용)</p>
          <div className="flex gap-2">
            <input
              type="text"
              value={dallePrompt}
              onChange={(e) => {
                setDallePrompt(e.target.value)
                setThumbnailMessage(null)
              }}
              placeholder="기사 제목 또는 시각화할 개념 (비워두면 뉴스 제목 사용)"
              className="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-gray-900 text-sm placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition"
            />
            <button
              type="button"
              disabled={isRegeneratingDalle || (!dallePrompt.trim() && !(news.title || '').trim())}
              onClick={async () => {
                setThumbnailMessage(null)
                setIsRegeneratingDalle(true)
                try {
                  const res = await adminFetch('/api/admin/ai-analyze.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                      action: 'regenerate_thumbnail_dalle',
                      news_id: news.id,
                      prompt: dallePrompt.trim() || undefined,
                      news_title: news.title || undefined,
                    }),
                  })
                  const data = await res.json()
                  if (data.success && data.image_url) {
                    setNews((prev) => ({ ...prev, image_url: data.image_url }))
                    setThumbnailMessage({ type: 'success', text: 'DALL-E로 썸네일이 새로 생성되었습니다. 아래 "임시 저장 업데이트"를 누르면 저장됩니다.' })
                  } else {
                    setThumbnailMessage({ type: 'error', text: data.error || data.message || 'DALL-E 썸네일 생성 실패' })
                  }
                } catch (e) {
                  setThumbnailMessage({ type: 'error', text: 'DALL-E 요청 실패: ' + (e as Error).message })
                } finally {
                  setIsRegeneratingDalle(false)
                }
              }}
              className="px-3 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs rounded-lg transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isRegeneratingDalle ? '생성 중...' : 'DALL-E로 수정'}
            </button>
          </div>
          {thumbnailMessage && (
            <p className={`mt-2 text-sm ${thumbnailMessage.type === 'success' ? 'text-green-600' : 'text-red-600'}`}>
              {thumbnailMessage.text}
            </p>
          )}
        </div>

        <div className="px-4 pt-5 pb-8">
          {/* 소스 및 날짜 */}
          <div className="flex items-center gap-2 text-sm mb-4">
            <span className="text-primary-500 font-medium">{getSourceName()}</span>
            <span className="text-gray-300"> | </span>
            <span className="text-gray-400">{formatHeaderDate()}</span>
          </div>

          {/* 제목 — 수정/미리보기 토글 */}
          <div className="mb-3">
            <div className="flex items-center justify-between gap-2 mb-2">
              <span className="text-xs text-gray-400">제목</span>
              <button
                onClick={() =>
                  setEditingSection(editingSection === 'title' ? null : 'title')
                }
                className="text-xs px-2 py-1 rounded bg-slate-600 hover:bg-slate-500 text-slate-200"
              >
                {editingSection === 'title' ? '적용' : '수정'}
              </button>
            </div>
            {editingSection === 'title' ? (
              <input
                type="text"
                value={news.title}
                onChange={(e) => handleSectionChange('title', e.target.value)}
                className="w-full text-2xl font-bold text-gray-900 bg-slate-50 border border-slate-200 px-3 py-2"
                placeholder="제목"
              />
            ) : (
              <h1 className="text-2xl font-bold text-gray-900 leading-snug">{news.title}</h1>
            )}
          </div>

          {/* 매체 설명 — 수정 가능 */}
          <div className="mb-6">
            <div className="flex items-center justify-between gap-2 mb-1">
              <span className="text-xs text-gray-400">매체 설명</span>
              <button
                onClick={() => {
                  if (editingSection === 'editorial') {
                    const val = editorialInputRef.current?.value?.trim()
                    if (val) {
                      const parsed = parseEditorialLine(val)
                      if (parsed) {
                        setNews((prev) => ({
                          ...prev,
                          original_source: parsed.source,
                          original_title: parsed.title,
                        }))
                      }
                    }
                    setEditingSection(null)
                  } else {
                    setEditingSection('editorial')
                  }
                }}
                className="text-xs px-2 py-1 rounded bg-slate-600 hover:bg-slate-500 text-slate-200"
              >
                {editingSection === 'editorial' ? '적용' : '수정'}
              </button>
            </div>
            {editingSection === 'editorial' ? (
              <div className="p-4 bg-gray-50 border border-gray-100 rounded-lg">
                <input
                  ref={editorialInputRef}
                  type="text"
                  defaultValue={getEditorialLine()}
                  placeholder="이 글은 {매체}에 게재된 {원문 제목} 글의 시각을 참고하였습니다."
                  className="w-full px-4 py-2 bg-white border border-gray-200 rounded-lg text-gray-800 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                />
                <p className="text-xs text-gray-500 mt-2">
                  포맷: 이 글은 [매체]에 게재된 [원문 제목] 글의 시각을 참고하였습니다.
                </p>
              </div>
            ) : (
              <p className="text-sm text-gray-500 mb-0">
                {getEditorialLine()}
              </p>
            )}
          </div>

          {/* 저자 */}
          {news.author && (
            <div className="text-sm text-gray-500 mb-4">
              <span className="font-medium text-gray-700">{news.author}</span> 씀
            </div>
          )}

          {/* 1. AI 분석 (원문 요약) — 작업 순서: AI 분석 → 내레이션 → The Gist */}
          <div className="mb-8 border-t border-gray-100 pt-6 mt-2">
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
              <div className="p-4 bg-gray-50 border border-gray-100 min-w-0 overflow-x-hidden">
                <RichTextEditor
                  ref={contentEditorRef}
                  value={ensureBrForEditor(news.content)}
                  onChange={(v) => handleSectionChange('content', v)}
                  sanitizePaste={(t) =>
                    sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
                  }
                  placeholder="원문 AI 요약..."
                  rows={12}
                  className="w-full bg-slate-800/30 border border-slate-600 rounded-none text-slate-200"
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

          {/* 2. 내레이션 */}
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
              <div className="p-4 bg-gray-50 border border-gray-100 min-w-0 overflow-x-hidden">
                <RichTextEditor
                    ref={narrationEditorRef}
                    value={ensureBrForEditor(news.narration)}
                    onChange={(v) => handleSectionChange('narration', v)}
                    sanitizePaste={(t) =>
                      sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
                    }
                    placeholder="내레이션..."
                    rows={10}
                    className="w-full bg-slate-800/30 border border-slate-600 rounded-none text-slate-200"
                  />
              </div>
            ) : (
              <div
                className={`prose prose-lg max-w-none text-gray-700 ${proseClasses}`}
                dangerouslySetInnerHTML={{
                  __html: formatContentHtml(news.narration || news.description || ''),
                }}
              />
            )}
          </div>

          {/* 3. The Gist */}
          <div className="mb-8">
            <div className="flex items-center justify-between mb-2">
              <h2
                className="text-xl font-semibold tracking-wide"
                style={{ fontFamily: "'Lobster', cursive", color: '#FF6F00' }}
              >
                the gist.
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
              <div className="border-l-4 border-orange-500 bg-gradient-to-r from-orange-50/50 via-amber-50/50 to-white p-4 min-w-0 overflow-x-hidden">
                <RichTextEditor
                  ref={whyImportantEditorRef}
                  value={ensureBrForEditor(news.why_important)}
                  onChange={(v) => handleSectionChange('why_important', v)}
                  sanitizePaste={(t) =>
                    sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')
                  }
                  placeholder="The Gist's Critique..."
                  rows={6}
                  className="w-full bg-slate-800/30 border border-slate-600 rounded-none text-slate-200"
                />
              </div>
            ) : (
              <div className="border-l-4 border-orange-500 bg-gradient-to-r from-orange-50 via-amber-50 to-white rounded-r-xl shadow-sm overflow-hidden">
                <div className="px-5 py-5 sm:px-6 sm:py-6">
                  <div
                    className={`text-gray-800 ${proseClasses}`}
                    dangerouslySetInnerHTML={{
                      __html: formatContentHtml(news.why_important || ''),
                    }}
                  />
                </div>
              </div>
            )}
          </div>

          {/* 원문 링크 — 유저 페이지와 동일 */}
          {news.url && news.url !== '#' && (
            <div className="border-t border-gray-100 pt-6 mt-6">
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
