import React, { useState, useRef, useEffect, useCallback } from 'react';

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  isStreaming?: boolean;
}

export interface ArticleContext {
  title?: string;
  url?: string;
  news_id?: number;
  summary?: string;
  narration?: string;
  analysis?: string | object;
}

interface ConversationSummary {
  id: string;
  title: string;
  created_at: string;
}

interface AIWorkspaceProps {
  articleContext?: ArticleContext | null;
}

const AIWorkspace: React.FC<AIWorkspaceProps> = ({ articleContext }) => {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // 대화 목록
  const [conversations, setConversations] = useState<ConversationSummary[]>([]);
  const [showConvList, setShowConvList] = useState(false);
  const [convListLoading, setConvListLoading] = useState(false);

  // 텍스트 선택 재작성
  const [selectedText, setSelectedText] = useState('');
  const [showRewriteMenu, setShowRewriteMenu] = useState(false);
  const [rewritePos, setRewritePos] = useState({ x: 0, y: 0 });

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messagesContainerRef = useRef<HTMLDivElement>(null);
  const abortRef = useRef<AbortController | null>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // 대화 목록 로드
  const loadConversations = useCallback(async () => {
    setConvListLoading(true);
    try {
      const res = await fetch('/api/admin/chat-stream.php?action=list_conversations');
      if (res.ok) {
        const data = await res.json();
        if (Array.isArray(data)) {
          setConversations(data);
        }
      }
    } catch {
      // 실패 시 빈 목록
    } finally {
      setConvListLoading(false);
    }
  }, []);

  // 대화 메시지 로드
  const loadConversation = async (convId: string) => {
    setConversationId(convId);
    setShowConvList(false);
    setMessages([]);
    setError(null);
    try {
      const res = await fetch(`/api/admin/chat-stream.php?action=get_messages&conversation_id=${encodeURIComponent(convId)}`);
      if (res.ok) {
        const data = await res.json();
        if (Array.isArray(data)) {
          setMessages(
            data
              .filter((m: { role: string }) => m.role === 'user' || m.role === 'assistant')
              .map((m: { id: string; role: string; content: string }, i: number) => ({
                id: m.id || `msg-${i}`,
                role: m.role as 'user' | 'assistant',
                content: m.content,
              }))
          );
        }
      }
    } catch {
      setError('대화 로드 실패');
    }
  };

  // 텍스트 선택 감지 (assistant 메시지에서만)
  const handleMouseUp = useCallback(() => {
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed || !selection.toString().trim()) {
      setShowRewriteMenu(false);
      return;
    }
    const text = selection.toString().trim();
    // 메시지 영역 안에서 선택했는지 확인
    const container = messagesContainerRef.current;
    if (!container) return;
    const anchorNode = selection.anchorNode;
    if (!anchorNode || !container.contains(anchorNode)) {
      setShowRewriteMenu(false);
      return;
    }
    setSelectedText(text);
    const range = selection.getRangeAt(0);
    const rect = range.getBoundingClientRect();
    const containerRect = container.getBoundingClientRect();
    setRewritePos({
      x: rect.left - containerRect.left + rect.width / 2,
      y: rect.top - containerRect.top - 40,
    });
    setShowRewriteMenu(true);
  }, []);

  const requestRewrite = () => {
    if (!selectedText) return;
    setShowRewriteMenu(false);
    setInput(`다음 텍스트를 더 나은 표현으로 다시 작성해줘:\n\n"${selectedText}"`);
    window.getSelection()?.removeAllRanges();
  };

  const requestDeepen = () => {
    if (!selectedText) return;
    setShowRewriteMenu(false);
    setInput(`다음 내용을 더 깊이 분석해줘:\n\n"${selectedText}"`);
    window.getSelection()?.removeAllRanges();
  };

  // 메시지 전송
  const sendMessage = async () => {
    const text = input.trim();
    if (!text || loading) return;

    setInput('');
    setError(null);
    setShowRewriteMenu(false);

    const userMsg: ChatMessage = {
      id: `user-${Date.now()}`,
      role: 'user',
      content: text,
    };
    setMessages((prev) => [...prev, userMsg]);

    const assistantId = `assistant-${Date.now()}`;
    setMessages((prev) => [
      ...prev,
      { id: assistantId, role: 'assistant', content: '', isStreaming: true },
    ]);
    setLoading(true);

    abortRef.current = new AbortController();
    try {
      const res = await fetch('/api/admin/chat-stream.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: text,
          conversation_id: conversationId,
          article_context: articleContext ?? undefined,
          history: messages.map((m) => ({ role: m.role, content: m.content })),
          admin_user: 'admin',
          create_conversation: true,
        }),
        signal: abortRef.current.signal,
      });

      if (!res.ok) {
        const errText = await res.text();
        throw new Error(errText || `HTTP ${res.status}`);
      }

      const reader = res.body?.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

      if (reader) {
        let currentEvent = '';
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          const lines = buffer.split('\n');
          buffer = lines.pop() ?? '';
          for (const line of lines) {
            if (line.startsWith('event: ')) {
              currentEvent = line.slice(7).trim();
              continue;
            }
            if (line.startsWith('data: ')) {
              const raw = line.slice(6);
              if (raw === '[DONE]') continue;
              try {
                const data = JSON.parse(raw);
                if (data.error) {
                  throw new Error(data.error);
                }
                if (currentEvent === 'token' && data.text) {
                  setMessages((prev) =>
                    prev.map((m) =>
                      m.id === assistantId
                        ? { ...m, content: m.content + data.text, isStreaming: true }
                        : m
                    )
                  );
                } else if (currentEvent === 'done' && data.conversation_id) {
                  setConversationId(data.conversation_id);
                } else if (currentEvent === 'start' && data.conversation_id) {
                  if (!conversationId) setConversationId(data.conversation_id);
                }
                // fallback (unnamed events)
                if (!currentEvent && data.delta) {
                  setMessages((prev) =>
                    prev.map((m) =>
                      m.id === assistantId
                        ? { ...m, content: m.content + data.delta, isStreaming: true }
                        : m
                    )
                  );
                }
                if (!currentEvent && data.done && data.conversation_id) {
                  setConversationId(data.conversation_id);
                }
              } catch (e) {
                if (e instanceof SyntaxError) continue;
                throw e;
              }
              currentEvent = '';
            }
          }
        }
      }

      setMessages((prev) =>
        prev.map((m) => (m.id === assistantId ? { ...m, isStreaming: false } : m))
      );
    } catch (e) {
      const err = e instanceof Error ? e.message : String(e);
      if ((e as Error & { name?: string })?.name === 'AbortError') {
        setMessages((prev) =>
          prev.map((m) =>
            m.id === assistantId ? { ...m, content: m.content || '(취소됨)', isStreaming: false } : m
          )
        );
        return;
      }
      setError(err);
      setMessages((prev) =>
        prev.map((m) =>
          m.id === assistantId
            ? { ...m, content: m.content || `(오류: ${err})`, isStreaming: false }
            : m
        )
      );
    } finally {
      setLoading(false);
      abortRef.current = null;
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const startNewChat = () => {
    setConversationId(null);
    setMessages([]);
    setError(null);
    setShowRewriteMenu(false);
  };

  return (
    <div className="flex flex-col h-[calc(100vh-8rem)] bg-slate-800/30 rounded-2xl border border-slate-700/50 overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
        <h3 className="text-lg font-semibold text-white">AI Workspace (GPT 5.2 + RAG)</h3>
        <div className="flex items-center gap-2">
          {conversationId && (
            <span className="text-xs text-slate-500 truncate max-w-[160px]" title={conversationId}>
              {conversationId.slice(0, 8)}...
            </span>
          )}
          <button
            type="button"
            onClick={() => { setShowConvList((v) => !v); if (!showConvList) loadConversations(); }}
            className="px-3 py-1.5 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 text-sm"
          >
            대화 목록
          </button>
          <button
            type="button"
            onClick={startNewChat}
            className="px-3 py-1.5 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 text-sm"
          >
            새 대화
          </button>
        </div>
      </div>

      {/* Conversation list dropdown */}
      {showConvList && (
        <div className="border-b border-slate-700/50 bg-slate-900/50 max-h-48 overflow-y-auto">
          {convListLoading ? (
            <p className="px-6 py-3 text-slate-500 text-sm">로딩 중...</p>
          ) : conversations.length === 0 ? (
            <p className="px-6 py-3 text-slate-500 text-sm">저장된 대화가 없습니다</p>
          ) : (
            conversations.map((c) => (
              <button
                key={c.id}
                onClick={() => loadConversation(c.id)}
                className={`w-full text-left px-6 py-2.5 hover:bg-slate-700/50 text-sm transition-colors ${
                  c.id === conversationId ? 'bg-cyan-900/30 text-cyan-300' : 'text-slate-300'
                }`}
              >
                <span className="block truncate">{c.title || c.id.slice(0, 12)}</span>
                <span className="text-xs text-slate-500">{new Date(c.created_at).toLocaleString('ko-KR')}</span>
              </button>
            ))
          )}
        </div>
      )}

      {/* Article context */}
      {articleContext && (articleContext.title || articleContext.url) && (
        <div className="px-6 py-2 border-b border-slate-700/30 bg-slate-900/30">
          <p className="text-sm text-slate-400">
            <span className="text-slate-300">컨텍스트:</span>{' '}
            {articleContext.title || articleContext.url || '---'}
          </p>
        </div>
      )}

      {/* Messages */}
      <div
        ref={messagesContainerRef}
        className="flex-1 overflow-y-auto p-6 space-y-4 relative"
        onMouseUp={handleMouseUp}
      >
        {messages.length === 0 && (
          <div className="text-slate-500 text-sm space-y-2">
            <p>메시지를 입력하고 Enter로 전송하세요.</p>
            <p>기사 컨텍스트가 있으면 RAG로 관련 크리틱/분석을 참고합니다.</p>
            <p className="text-slate-600">Assistant 응답의 텍스트를 <strong>드래그</strong>하면 &quot;다시 쓰기&quot; / &quot;깊이 분석&quot;을 요청할 수 있습니다.</p>
          </div>
        )}
        {messages.map((msg) => (
          <div
            key={msg.id}
            className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
          >
            <div
              className={`max-w-[85%] rounded-2xl px-4 py-3 ${
                msg.role === 'user'
                  ? 'bg-cyan-500/20 text-cyan-100 border border-cyan-500/30'
                  : 'bg-slate-700/50 text-slate-200 border border-slate-600/50'
              }`}
            >
              <div className="whitespace-pre-wrap break-words text-sm leading-relaxed">
                {msg.content || (msg.isStreaming ? '...' : '')}
                {msg.isStreaming && <span className="inline-block w-1.5 h-4 bg-cyan-400 animate-pulse ml-0.5 rounded-sm" />}
              </div>
            </div>
          </div>
        ))}

        {/* Rewrite context menu */}
        {showRewriteMenu && (
          <div
            className="absolute z-50 bg-slate-800 border border-slate-600 rounded-lg shadow-xl flex gap-1 p-1"
            style={{ left: rewritePos.x, top: rewritePos.y, transform: 'translateX(-50%)' }}
          >
            <button
              onClick={requestRewrite}
              className="px-3 py-1.5 text-xs text-cyan-300 hover:bg-slate-700 rounded transition-colors whitespace-nowrap"
            >
              다시 쓰기
            </button>
            <button
              onClick={requestDeepen}
              className="px-3 py-1.5 text-xs text-amber-300 hover:bg-slate-700 rounded transition-colors whitespace-nowrap"
            >
              깊이 분석
            </button>
          </div>
        )}

        <div ref={messagesEndRef} />
      </div>

      {/* Error */}
      {error && (
        <div className="px-6 py-2 text-rose-400 text-sm bg-rose-900/10 border-t border-rose-800/30">{error}</div>
      )}

      {/* Input */}
      <div className="p-4 border-t border-slate-700/50">
        <div className="flex gap-2">
          <textarea
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="메시지 입력 (Enter 전송, Shift+Enter 줄바꿈)"
            className="flex-1 min-h-[44px] max-h-32 px-4 py-3 rounded-xl bg-slate-800 border border-slate-600 text-white placeholder-slate-500 resize-y text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
            rows={2}
            disabled={loading}
          />
          {loading ? (
            <button
              type="button"
              onClick={() => abortRef.current?.abort()}
              className="px-5 py-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-medium text-sm"
            >
              중지
            </button>
          ) : (
            <button
              type="button"
              onClick={sendMessage}
              disabled={!input.trim()}
              className="px-5 py-3 rounded-xl bg-cyan-500 hover:bg-cyan-400 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium text-sm"
            >
              전송
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default AIWorkspace;
