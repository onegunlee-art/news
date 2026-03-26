import { useState, useCallback, useEffect, useRef, useMemo } from 'react'
import { adminFetch } from '../../services/api'
import {
  CHIP_DISPLAY,
  DISCLAIMER_FOOTER,
  FIXED_CHIPS,
  type ChatChip,
} from '../../constants/articleChat'

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

export interface ArticleChatPanelProps {
  newsId: number
}

type ChatMsg = { role: 'user' | 'assistant'; content: string; chip_id?: string | null }

export default function ArticleChatPanel({ newsId }: ArticleChatPanelProps) {
  const [chips, setChips] = useState<ChatChip[]>([])
  const [messages, setMessages] = useState<ChatMsg[]>([])
  const [streaming, setStreaming] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const messagesRef = useRef<HTMLDivElement>(null)

  const assistantMessages = useMemo(
    () => messages.filter((m) => m.role === 'assistant'),
    [messages]
  )

  const usedChipIds = useMemo(() => {
    const s = new Set<string>()
    for (const m of messages) {
      if (m.role === 'user' && m.chip_id) {
        s.add(m.chip_id)
      }
    }
    return s
  }, [messages])

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
    const el = messagesRef.current
    if (el) requestAnimationFrame(() => { el.scrollTop = el.scrollHeight })
  }, [messages, streaming])

  const sendMessage = async (text: string, chipId?: string) => {
    const trimmed = text.trim()
    if (!trimmed || streaming) return
    if (chipId && usedChipIds.has(chipId)) {
      return
    }

    setError(null)
    setStreaming(true)
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
      void loadSession()
    }
  }

  return (
    <section
      className="mt-10 pt-8 border-t border-page"
      aria-label="기사 이해 도우미"
    >
      {error && (
        <div className="mb-3 text-sm text-red-600 dark:text-red-400">{error}</div>
      )}

      <div className="flex flex-wrap gap-2 mb-4">
        {chips.map((c) => (
          <button
            key={c.id}
            type="button"
            disabled={streaming || usedChipIds.has(c.id)}
            onClick={() => void sendMessage(c.label, c.id)}
            className="px-3 py-2 text-sm rounded-lg bg-primary-500 text-white hover:bg-primary-600 disabled:opacity-50 transition-colors max-w-full text-left font-medium"
          >
            {c.label}
          </button>
        ))}
      </div>

      <div ref={messagesRef} className="rounded-xl border border-page bg-page-secondary/50 p-4 min-h-[120px] max-h-[360px] overflow-y-auto mb-3">
        {assistantMessages.length === 0 && (
          <p className="text-sm text-page-muted">아래 버튼을 눌러 질문해 보세요.</p>
        )}
        {assistantMessages.map((m, i) => {
          const isLast = i === assistantMessages.length - 1
          const showDisclaimer = !(streaming && isLast)
          const showEllipsis = streaming && isLast && !m.content
          return (
            <div key={`assistant-${i}`} className="mb-6 last:mb-0">
              <div className="text-sm text-page-secondary whitespace-pre-wrap break-words leading-relaxed">
                {m.content || (showEllipsis ? '…' : '')}
              </div>
              {showDisclaimer && (
                <p className="text-xs text-page-muted mt-3 leading-relaxed">
                  {DISCLAIMER_FOOTER}
                </p>
              )}
            </div>
          )
        })}
      </div>

    </section>
  )
}
