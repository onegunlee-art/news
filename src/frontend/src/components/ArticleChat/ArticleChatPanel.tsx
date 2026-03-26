import { useState, useCallback, useEffect, useRef, useMemo } from 'react'
import { adminFetch } from '../../services/api'
import {
  CHIP_DISPLAY,
  DISCLAIMER_FOOTER,
  FIXED_CHIPS,
  type ChatChip,
} from '../../constants/articleChat'
import MaterialIcon from '../Common/MaterialIcon'

const API_BASE = import.meta.env.VITE_API_URL || '/api'

function sessionStorageKey(newsId: number) {
  return `article_chat_sk_${newsId}`
}

function parseSseBuffer(
  buffer: string,
  onEvent: (event: string, data: unknown) => void
): string {
  let rest = buffer
  const blocks = buffer.split('\n\n')
  rest = blocks.pop() ?? ''
  for (const block of blocks) {
    if (block.trim() === '') continue
    let event = 'message'
    const dataLines: string[] = []
    for (const line of block.split('\n')) {
      if (line.startsWith('event:')) {
        event = line.slice(6).trim()
      } else if (line.startsWith('data:')) {
        dataLines.push(line.slice(5).trim())
      }
    }
    if (dataLines.length === 0) continue
    const dataStr = dataLines.join('\n')
    try {
      onEvent(event, JSON.parse(dataStr))
    } catch {
      onEvent(event, dataStr)
    }
  }
  return rest
}

async function postArticleChatStream(
  body: Record<string, unknown>,
  onToken: (text: string) => void,
  onDone: (data: { full_text?: string; disclaimer?: string }) => void
): Promise<void> {
  const res = await adminFetch(`${API_BASE}/article-chat.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })

  if (!res.ok) {
    const ct = res.headers.get('content-type') || ''
    if (ct.includes('application/json')) {
      const j = await res.json().catch(() => ({}))
      throw new Error((j as { error?: string }).error || `HTTP ${res.status}`)
    }
    throw new Error(`HTTP ${res.status}`)
  }

  const reader = res.body?.getReader()
  if (!reader) throw new Error('스트림을 읽을 수 없습니다.')

  const decoder = new TextDecoder()
  let buf = ''
  let chunk = await reader.read()
  while (!chunk.done) {
    buf += decoder.decode(chunk.value, { stream: true })
    buf = parseSseBuffer(buf, (event, data) => {
      if (event === 'token' && data && typeof data === 'object' && 'text' in data) {
        onToken(String((data as { text: string }).text))
      }
      if (event === 'done' && data && typeof data === 'object') {
        onDone(data as { full_text?: string; disclaimer?: string })
      }
    })
    chunk = await reader.read()
  }
  if (buf.trim() !== '') {
    parseSseBuffer(buf + '\n\n', () => {})
  }
}

/** user(chip_id) 직후 assistant 한 개를 해당 칩 답으로 매핑. 스트리밍 중이면 마지막 턴의 칩 id 반환 */
function deriveChipAnswers(
  messages: ChatMsg[],
  streaming: boolean
): { answers: Record<string, string>; streamingChipId: string | null } {
  const answers: Record<string, string> = {}
  for (let i = 0; i < messages.length; i++) {
    const m = messages[i]
    if (m.role === 'user' && m.chip_id) {
      const next = messages[i + 1]
      if (next?.role === 'assistant') {
        answers[m.chip_id] = next.content
      }
    }
  }

  let streamingChipId: string | null = null
  if (streaming && messages.length >= 2) {
    const last = messages[messages.length - 1]
    const prev = messages[messages.length - 2]
    if (last.role === 'assistant' && prev.role === 'user' && prev.chip_id) {
      streamingChipId = prev.chip_id
    }
  }
  return { answers, streamingChipId }
}

function previewLine(text: string, max = 80): string {
  const one = text.replace(/\s+/g, ' ').trim()
  if (one.length <= max) return one || '답변이 여기에 표시됩니다.'
  return `${one.slice(0, max)}…`
}

export interface ArticleChatPanelProps {
  newsId: number
}

type ChatMsg = { role: 'user' | 'assistant'; content: string; chip_id?: string | null }

export default function ArticleChatPanel({ newsId }: ArticleChatPanelProps) {
  const [chips, setChips] = useState<ChatChip[]>([])
  const [messages, setMessages] = useState<ChatMsg[]>([])
  const [streaming, setStreaming] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [expandedByChipId, setExpandedByChipId] = useState<Record<string, boolean>>({})
  const panelScrollRefs = useRef<Record<string, HTMLDivElement | null>>({})

  const usedChipIds = useMemo(() => {
    const s = new Set<string>()
    for (const m of messages) {
      if (m.role === 'user' && m.chip_id) {
        s.add(m.chip_id)
      }
    }
    return s
  }, [messages])

  const { answers, streamingChipId } = useMemo(
    () => deriveChipAnswers(messages, streaming),
    [messages, streaming]
  )

  const newSessionId = () =>
    typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
      ? crypto.randomUUID()
      : `sk_${Date.now()}_${Math.random().toString(36).slice(2, 11)}`

  const sessionKey = useRef(
    typeof sessionStorage !== 'undefined'
      ? sessionStorage.getItem(sessionStorageKey(newsId)) || newSessionId()
      : newSessionId()
  )

  useEffect(() => {
    if (typeof sessionStorage !== 'undefined') {
      sessionStorage.setItem(sessionStorageKey(newsId), sessionKey.current)
    }
  }, [newsId])

  const loadSession = useCallback(async () => {
    try {
      const res = await adminFetch(
        `${API_BASE}/article-chat.php?action=session&news_id=${newsId}`,
        { method: 'GET' }
      )
      const j = await res.json()
      if (!res.ok || !j.success) {
        throw new Error(j.error || '세션을 불러올 수 없습니다.')
      }
      const d = j.data as {
        messages: Array<{ role: string; content: string; chip_id?: string | null }>
      }
      if (Array.isArray(d.messages)) {
        setMessages(
          d.messages
            .filter((m) => m.role === 'user' || m.role === 'assistant')
            .map((m) => ({
              role: m.role as 'user' | 'assistant',
              content: m.content,
              chip_id: m.role === 'user' ? (m.chip_id ?? null) : undefined,
            }))
        )
      }
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : '세션 오류')
    }
  }, [newsId])

  const loadChips = useCallback(async () => {
    try {
      const res = await adminFetch(
        `${API_BASE}/article-chat.php?action=chips&news_id=${newsId}`,
        { method: 'GET' }
      )
      const j = await res.json()
      if (res.ok && j.success && j.data?.fixed) {
        const fixed = j.data.fixed as ChatChip[]
        const dyn = (j.data.dynamic as ChatChip[]) || []
        setChips([...fixed.slice(0, CHIP_DISPLAY.fixedCount), ...dyn.slice(0, CHIP_DISPLAY.dynamicCount)])
      } else {
        setChips([...FIXED_CHIPS.slice(0, CHIP_DISPLAY.fixedCount)])
      }
    } catch {
      setChips([...FIXED_CHIPS.slice(0, CHIP_DISPLAY.fixedCount)])
    }
  }, [newsId])

  useEffect(() => {
    void loadSession()
    void loadChips()
  }, [loadSession, loadChips])

  useEffect(() => {
    if (!streaming || !streamingChipId) return
    const el = panelScrollRefs.current[streamingChipId]
    if (el) requestAnimationFrame(() => { el.scrollTop = el.scrollHeight })
  }, [messages, streaming, streamingChipId])

  const sendMessage = async (text: string, chipId?: string) => {
    const trimmed = text.trim()
    if (!trimmed || streaming) return
    if (chipId && usedChipIds.has(chipId)) {
      return
    }

    setError(null)
    setStreaming(true)
    if (chipId) {
      setExpandedByChipId((prev) => ({ ...prev, [chipId]: true }))
    }
    const userMsg: ChatMsg = {
      role: 'user',
      content: trimmed,
      chip_id: chipId ?? null,
    }
    setMessages((prev) => [...prev, userMsg])

    let assistant = ''
    setMessages((prev) => [...prev, { role: 'assistant', content: '' }])

    try {
      const history = messages.map((m) => ({ role: m.role, content: m.content }))
      await postArticleChatStream(
        {
          news_id: newsId,
          message: trimmed,
          chip_id: chipId || undefined,
          session_key: sessionKey.current,
          history,
        },
        (token) => {
          assistant += token
          setMessages((prev) => {
            const next = [...prev]
            const last = next.length - 1
            if (last >= 0 && next[last].role === 'assistant') {
              next[last] = { role: 'assistant', content: assistant }
            }
            return next
          })
        },
        () => {}
      )
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : '전송 실패')
      setMessages((prev) => (prev.length >= 2 ? prev.slice(0, -2) : prev))
    } finally {
      setStreaming(false)
      if (chipId) {
        setExpandedByChipId((prev) => ({ ...prev, [chipId]: false }))
      }
      void loadSession()
    }
  }

  const toggleExpanded = (chipId: string) => {
    setExpandedByChipId((prev) => ({ ...prev, [chipId]: !prev[chipId] }))
  }

  return (
    <section
      className="mt-10 pt-8 border-t border-page"
      aria-label="기사 이해 도우미"
    >
      {error && (
        <div className="mb-3 text-sm text-red-600 dark:text-red-400">{error}</div>
      )}

      <div>
        {chips.map((c) => {
          const used = usedChipIds.has(c.id)
          const isLive = streaming && streamingChipId === c.id
          const text = answers[c.id] ?? ''
          const expanded = expandedByChipId[c.id] ?? false
          const showEllipsis = isLive && !text.trim()
          const answerComplete = used && !isLive && text.trim().length > 0

          return (
            <div key={c.id} className="mb-5 last:mb-0">
              <button
                type="button"
                disabled={streaming || usedChipIds.has(c.id)}
                onClick={() => void sendMessage(c.label, c.id)}
                className="w-full px-3 py-2.5 text-sm rounded-lg bg-primary-500 text-white hover:bg-primary-600 disabled:opacity-50 transition-colors text-left font-medium"
              >
                {c.label}
              </button>

              {used && (
                <div
                  id={`article-chat-answer-${c.id}`}
                  className="mt-2 rounded-xl border border-page bg-page-secondary/50 overflow-hidden"
                >
                  {isLive || expanded ? (
                    <div className="p-4">
                      {answerComplete && (
                        <button
                          type="button"
                          onClick={() => toggleExpanded(c.id)}
                          className="mb-3 flex w-full items-center justify-between gap-2 text-left text-xs font-medium text-page-secondary hover:text-page"
                          aria-expanded={true}
                          aria-controls={`article-chat-answer-body-${c.id}`}
                        >
                          <span>답변 접기</span>
                          <MaterialIcon name="expand_less" className="w-5 h-5 shrink-0" size={20} aria-hidden />
                        </button>
                      )}
                      <div
                        id={`article-chat-answer-body-${c.id}`}
                        ref={(el) => {
                          panelScrollRefs.current[c.id] = el
                        }}
                        className="text-sm text-page-secondary whitespace-pre-wrap break-words leading-relaxed max-h-[360px] overflow-y-auto"
                        role="region"
                        aria-label={`${c.label} 답변`}
                      >
                        {text || (showEllipsis ? '…' : '')}
                      </div>
                      {!isLive && (
                        <p className="text-xs text-page-muted mt-3 leading-relaxed">
                          {DISCLAIMER_FOOTER}
                        </p>
                      )}
                    </div>
                  ) : (
                    <button
                      type="button"
                      onClick={() => toggleExpanded(c.id)}
                      className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm text-page-secondary hover:bg-page-secondary/30 transition-colors"
                      aria-expanded={false}
                      aria-controls={`article-chat-answer-${c.id}`}
                    >
                      <span className="min-w-0 flex-1 line-clamp-2 break-keep-ko-mobile">
                        {previewLine(text)}
                      </span>
                      <MaterialIcon name="expand_more" className="w-5 h-5 shrink-0" size={20} aria-hidden />
                    </button>
                  )}
                </div>
              )}
            </div>
          )
        })}
      </div>

    </section>
  )
}
