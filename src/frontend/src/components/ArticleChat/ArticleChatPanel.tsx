import { useState, useCallback, useEffect, useRef } from 'react'
import MaterialIcon from '../Common/MaterialIcon'
import { adminFetch } from '../../services/api'
import {
  CHAT_INTRO_COPY,
  CHIP_DISPLAY,
  DISCLAIMER_FOOTER,
  FIXED_CHIPS,
  EXTRA_CHIP_POOL,
  CHAT_LIMITS,
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
  articleTitle: string
}

type ChatMsg = { role: 'user' | 'assistant'; content: string }

export default function ArticleChatPanel({ newsId, articleTitle }: ArticleChatPanelProps) {
  const [chips, setChips] = useState<ChatChip[]>([])
  const [messages, setMessages] = useState<ChatMsg[]>([])
  const [remaining, setRemaining] = useState<number>(CHAT_LIMITS.maxQuestionsPerSession)
  const [input, setInput] = useState('')
  const [streaming, setStreaming] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const bottomRef = useRef<HTMLDivElement>(null)

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
        remaining: number
        messages: Array<{ role: string; content: string }>
      }
      setRemaining(typeof d.remaining === 'number' ? d.remaining : CHAT_LIMITS.maxQuestionsPerSession)
      if (Array.isArray(d.messages)) {
        setMessages(
          d.messages
            .filter((m) => m.role === 'user' || m.role === 'assistant')
            .map((m) => ({
              role: m.role as 'user' | 'assistant',
              content: m.content,
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
        setChips([
          ...FIXED_CHIPS.slice(0, CHIP_DISPLAY.fixedCount),
          ...EXTRA_CHIP_POOL.slice(0, CHIP_DISPLAY.dynamicCount),
        ])
      }
    } catch {
      setChips([
        ...FIXED_CHIPS.slice(0, CHIP_DISPLAY.fixedCount),
        ...EXTRA_CHIP_POOL.slice(0, CHIP_DISPLAY.dynamicCount),
      ])
    }
  }, [newsId])

  useEffect(() => {
    void loadSession()
    void loadChips()
  }, [loadSession, loadChips])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, streaming])

  const sendMessage = async (text: string, chipId?: string) => {
    const trimmed = text.trim()
    if (!trimmed || streaming) return
    if (remaining <= 0) {
      setError('질문 한도에 도달했습니다.')
      return
    }

    setError(null)
    setStreaming(true)
    const userMsg: ChatMsg = { role: 'user', content: trimmed }
    setMessages((prev) => [...prev, userMsg])
    setInput('')

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

  const downloadLast = () => {
    const last = [...messages].reverse().find((m) => m.role === 'assistant' && m.content.trim())
    if (!last) return
    const md = `# ${articleTitle}\n\n${last.content}\n\n---\n${DISCLAIMER_FOOTER}\n`
    const blob = new Blob([md], { type: 'text/markdown;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `article-chat-${newsId}.md`
    a.click()
    URL.revokeObjectURL(a.href)
  }

  return (
    <section
      className="mt-10 pt-8 border-t border-page"
      aria-label="기사 이해 도우미"
    >
      <div className="flex items-center gap-2 mb-3">
        <MaterialIcon name="chat" className="w-5 h-5 text-primary-500" size={20} />
        <h2 className="text-lg font-semibold text-page">{CHAT_INTRO_COPY}</h2>
        <span className="text-xs text-amber-600 dark:text-amber-400 font-medium">(관리자 베타)</span>
      </div>

      <p className="text-xs text-page-muted mb-4">
        남은 질문: {remaining}/{CHAT_LIMITS.maxQuestionsPerSession} · 입력 {CHAT_LIMITS.maxInputChars}자 이하
      </p>

      {error && (
        <div className="mb-3 text-sm text-red-600 dark:text-red-400">{error}</div>
      )}

      <div className="flex flex-wrap gap-2 mb-4">
        {chips.map((c) => (
          <button
            key={c.id}
            type="button"
            disabled={streaming || remaining <= 0}
            onClick={() => void sendMessage(c.label, c.id)}
            className="px-3 py-1.5 text-sm rounded-full border border-page bg-page-secondary hover:bg-primary-500/10 hover:border-primary-500/50 text-page-secondary disabled:opacity-50 transition-colors max-w-full text-left"
          >
            {c.label}
          </button>
        ))}
      </div>

      <div className="rounded-xl border border-page bg-page-secondary/50 p-4 min-h-[120px] max-h-[360px] overflow-y-auto mb-3">
        {messages.length === 0 && (
          <p className="text-sm text-page-muted">칩을 누르거나 아래에 질문을 입력해 보세요.</p>
        )}
        {messages.map((m, i) => (
          <div
            key={i}
            className={`mb-3 text-sm ${m.role === 'user' ? 'text-page' : 'text-page-secondary whitespace-pre-wrap'}`}
          >
            <span className="font-semibold text-primary-500">
              {m.role === 'user' ? '나' : 'AI'}
            </span>
            : {m.content || (streaming && i === messages.length - 1 ? '…' : '')}
          </div>
        ))}
        <div ref={bottomRef} />
      </div>

      <div className="flex flex-col sm:flex-row gap-2 mb-2">
        <input
          type="text"
          maxLength={CHAT_LIMITS.maxInputChars}
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault()
              void sendMessage(input)
            }
          }}
          placeholder="질문을 입력하세요"
          disabled={streaming || remaining <= 0}
          className="flex-1 px-3 py-2 rounded-lg border border-page bg-page text-page text-sm"
        />
        <button
          type="button"
          disabled={streaming || remaining <= 0 || !input.trim()}
          onClick={() => void sendMessage(input)}
          className="px-4 py-2 rounded-lg bg-primary-500 text-white text-sm font-medium disabled:opacity-50"
        >
          보내기
        </button>
        <button
          type="button"
          onClick={downloadLast}
          className="px-4 py-2 rounded-lg border border-page text-sm text-page-secondary hover:bg-page-secondary"
        >
          마지막 답변 .md 저장
        </button>
      </div>

      <p className="text-xs text-page-muted">{DISCLAIMER_FOOTER}</p>
    </section>
  )
}
