import React, { useState, useEffect } from 'react';

interface RAGResult {
  chunk_text?: string;
  similarity?: number;
  critique_id?: string;
  news_id?: number;
  article_url?: string;
  chunk_type?: string;
  metadata?: Record<string, unknown>;
}

const RAGTester: React.FC = () => {
  const [query, setQuery] = useState('');
  const [topK, setTopK] = useState(5);
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState<{ rag_configured: boolean; openai_configured: boolean; supabase_configured: boolean } | null>(null);
  const [result, setResult] = useState<{
    critiques: RAGResult[];
    analyses: RAGResult[];
    knowledge?: RAGResult[];
    system_prompt_preview?: string;
  } | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/admin/rag-test.php')
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setStatus(d);
      })
      .catch(() => setStatus(null));
  }, []);

  const runTest = async () => {
    if (!query.trim()) return;
    setLoading(true);
    setError(null);
    setResult(null);
    try {
      const res = await fetch('/api/admin/rag-test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query: query.trim(), top_k: topK }),
      });
      const data = await res.json();
      if (data.success) {
        setResult({
          critiques: data.critiques || [],
          analyses: data.analyses || [],
          knowledge: data.knowledge || [],
          system_prompt_preview: data.system_prompt_preview,
        });
      } else {
        setError(data.error || 'RAG 검색 실패. Supabase RPC(match_critique_embeddings 등) 및 테이블 점검 필요.');
      }
    } catch (e) {
      setError('서버 오류: ' + (e as Error).message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
        <h3 className="text-lg font-semibold text-white">RAG 검색 테스트</h3>
        <p className="text-slate-400 text-sm">
          쿼리를 입력하면 관련 크리틱/분석을 검색하고, 시스템 프롬프트에 주입될 내용을 미리봅니다.
        </p>

        {status && (
          <div className="flex flex-wrap gap-2 text-xs">
            <span className={`px-2 py-1 rounded ${status.rag_configured ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'}`}>
              RAG: {status.rag_configured ? '설정됨' : '비설정'}
            </span>
            <span className={`px-2 py-1 rounded ${status.openai_configured ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-600 text-slate-400'}`}>
              OpenAI: {status.openai_configured ? '설정됨' : '비설정'}
            </span>
            <span className={`px-2 py-1 rounded ${status.supabase_configured ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-600 text-slate-400'}`}>
              Supabase: {status.supabase_configured ? '설정됨' : '비설정'}
            </span>
          </div>
        )}

        <div className="flex flex-col sm:flex-row gap-3">
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="검색할 쿼리 입력 (예: 내레이션 톤 개선)"
            className="flex-1 px-4 py-2 rounded-lg bg-slate-900/50 border border-slate-700 text-white placeholder-slate-500 text-sm"
          />
          <div className="flex items-center gap-2">
            <label className="text-slate-400 text-sm">Top K:</label>
            <select
              value={topK}
              onChange={(e) => setTopK(Number(e.target.value))}
              className="px-3 py-2 rounded-lg bg-slate-900/50 border border-slate-700 text-white text-sm"
            >
              {[1, 3, 5, 10, 15, 20].map((n) => (
                <option key={n} value={n}>{n}</option>
              ))}
            </select>
          </div>
          <button
            type="button"
            onClick={runTest}
            disabled={loading || !query.trim()}
            className="px-5 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-400 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium text-sm"
          >
            {loading ? '검색 중...' : '검색'}
          </button>
        </div>

        {error && <p className="text-rose-400 text-sm">{error}</p>}
      </div>

      {result && (
        <>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
              <h4 className="font-semibold text-cyan-300">크리틱 결과 ({result.critiques.length})</h4>
              {result.critiques.length === 0 ? (
                <p className="text-slate-500 text-sm">검색된 크리틱 없음</p>
              ) : (
                <div className="space-y-3 max-h-64 overflow-y-auto">
                  {result.critiques.map((c, i) => (
                    <div key={i} className="p-3 rounded-lg bg-slate-900/50 border border-slate-700/50">
                      <div className="flex justify-between items-center mb-1">
                        <span className="text-xs text-cyan-400">
                          유사도: {typeof c.similarity === 'number' ? (c.similarity * 100).toFixed(1) : '?'}%
                        </span>
                      </div>
                      <p className="text-sm text-slate-300 whitespace-pre-wrap line-clamp-4">{c.chunk_text || ''}</p>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
              <h4 className="font-semibold text-emerald-300">분석 결과 ({result.analyses.length})</h4>
              {result.analyses.length === 0 ? (
                <p className="text-slate-500 text-sm">검색된 분석 없음</p>
              ) : (
                <div className="space-y-3 max-h-64 overflow-y-auto">
                  {result.analyses.map((a, i) => (
                    <div key={i} className="p-3 rounded-lg bg-slate-900/50 border border-slate-700/50">
                      <div className="flex justify-between items-center mb-1">
                        <span className="text-xs text-emerald-400">
                          유사도: {typeof a.similarity === 'number' ? (a.similarity * 100).toFixed(1) : '?'}%
                        </span>
                        {a.chunk_type && (
                          <span className="text-xs text-slate-500">{a.chunk_type}</span>
                        )}
                      </div>
                      <p className="text-sm text-slate-300 whitespace-pre-wrap line-clamp-4">{a.chunk_text || ''}</p>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
              <h4 className="font-semibold text-amber-300">지식 라이브러리 ({(result.knowledge || []).length})</h4>
              {(result.knowledge || []).length === 0 ? (
                <p className="text-slate-500 text-sm">검색된 지식 없음</p>
              ) : (
                <div className="space-y-3 max-h-64 overflow-y-auto">
                  {(result.knowledge || []).map((k, i) => (
                    <div key={i} className="p-3 rounded-lg bg-slate-900/50 border border-slate-700/50">
                      <div className="flex justify-between items-center mb-1">
                        <span className="text-xs text-amber-400">
                          유사도: {typeof k.similarity === 'number' ? (k.similarity * 100).toFixed(1) : '?'}%
                        </span>
                      </div>
                      <p className="text-sm text-slate-300 whitespace-pre-wrap line-clamp-4">{(k as { title?: string; content?: string }).title || (k as { chunk_text?: string }).chunk_text || ''}</p>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {result.system_prompt_preview && (
            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
              <h4 className="font-semibold text-slate-200">시스템 프롬프트 미리보기</h4>
              <pre className="p-4 rounded-lg bg-slate-900/80 text-slate-300 text-xs whitespace-pre-wrap overflow-x-auto max-h-48 overflow-y-auto">
                {result.system_prompt_preview}
              </pre>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default RAGTester;
