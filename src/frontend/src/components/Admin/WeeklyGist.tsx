import { useState, useCallback } from 'react';
import MaterialIcon from '../Common/MaterialIcon';
import { adminFetch } from '../../services/api';

const API_BASE = import.meta.env.VITE_API_URL || '/api';

interface Perspective {
  viewpoint: string;
  source: string;
  difference_reason: string;
}

interface Cluster {
  cluster_id: number;
  title: string;
  category: string;
  priority_rank: number;
  impact_score: number;
  confidence: 'high' | 'medium' | 'low';
  one_line_takeaway: string;
  source_article_ids: number[];
  narrative: string;
  perspectives: Perspective[];
  so_what: string;
}

interface CrossConnection {
  from_cluster: number;
  to_cluster: number;
  relationship: string;
}

interface GistData {
  headline: string;
  macro_so_what: string;
  clusters: Cluster[];
  cross_connections: CrossConnection[];
  next_week_watch: string[];
  meta: {
    total_articles: number;
    period: string;
    generated_at: string;
    model?: string;
  };
}

interface ArticleItem {
  id: number;
  title: string;
  source?: string;
  category_parent?: string;
  description?: string;
  why_important?: string;
  narration?: string;
  future_prediction?: string;
  published_at?: string;
  rag_metadata?: {
    topic_label: string;
    topic_category: string;
    entities: string[];
    region: string[];
  } | null;
}

const confidenceColors = {
  high: 'bg-emerald-500/20 text-emerald-400',
  medium: 'bg-amber-500/20 text-amber-400',
  low: 'bg-red-500/20 text-red-400',
};

const categoryLabels: Record<string, string> = {
  diplomacy: '외교',
  economy: '경제',
  technology: '기술',
  energy: '에너지',
  security: '안보',
};

const categoryColors: Record<string, string> = {
  diplomacy: 'bg-blue-500/20 text-blue-400',
  economy: 'bg-emerald-500/20 text-emerald-400',
  technology: 'bg-purple-500/20 text-purple-400',
  energy: 'bg-orange-500/20 text-orange-400',
  security: 'bg-red-500/20 text-red-400',
};

function formatDate(d: Date): string {
  return d.toISOString().split('T')[0];
}

export default function WeeklyGist() {
  const [startDate, setStartDate] = useState(() => formatDate(new Date(Date.now() - 7 * 86400000)));
  const [endDate, setEndDate] = useState(() => formatDate(new Date()));
  const [articles, setArticles] = useState<ArticleItem[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [gistResult, setGistResult] = useState<GistData | null>(null);
  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState('');
  const [step, setStep] = useState<'fetch' | 'select' | 'result'>('fetch');

  const fetchArticles = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await adminFetch(
        `${API_BASE}/admin/weekly-gist.php?action=articles&start=${startDate}&end=${endDate}`
      );
      const data = await res.json();
      if (!data.success) throw new Error(data.error || '기사 조회 실패');
      const arts: ArticleItem[] = data.data.articles || [];
      setArticles(arts);
      setSelectedIds(new Set(arts.map(a => a.id)));
      setStep('select');
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoading(false);
    }
  }, [startDate, endDate]);

  const generateGist = useCallback(async () => {
    const selected = articles.filter(a => selectedIds.has(a.id));
    if (selected.length < 3) {
      setError('최소 3개 기사를 선택해야 합니다.');
      return;
    }
    setGenerating(true);
    setError('');
    try {
      const res = await adminFetch(`${API_BASE}/admin/weekly-gist.php`, {
        method: 'POST',
        body: JSON.stringify({
          action: 'generate',
          start: startDate,
          end: endDate,
          articles: selected,
        }),
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Gist 생성 실패');
      setGistResult(data.data);
      setStep('result');
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setGenerating(false);
    }
  }, [articles, selectedIds, startDate, endDate]);

  const toggleAll = () => {
    if (selectedIds.size === articles.length) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(articles.map(a => a.id)));
    }
  };

  const toggleId = (id: number) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const getArticleTitle = (id: number) => articles.find(a => a.id === id)?.title || `#${id}`;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 className="text-2xl font-bold text-white mb-1">위클리 Gist</h2>
          <p className="text-slate-400 text-sm">주간 뉴스를 종합하여 인텔리전스 브리핑을 생성합니다</p>
        </div>
        {step !== 'fetch' && (
          <button
            type="button"
            onClick={() => { setStep('fetch'); setGistResult(null); setArticles([]); setError(''); }}
            className="text-cyan-400 hover:text-cyan-300 text-sm flex items-center gap-1"
          >
            <MaterialIcon name="restart_alt" className="w-4 h-4" size={16} />
            초기화
          </button>
        )}
      </div>

      {error && (
        <div className="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-400 text-sm">
          {error}
        </div>
      )}

      {/* Step 1: 기간 선택 */}
      {step === 'fetch' && (
        <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
          <h3 className="text-white font-semibold mb-4 flex items-center gap-2">
            <MaterialIcon name="date_range" className="w-5 h-5 text-cyan-400" size={20} />
            기간 선택
          </h3>
          <div className="flex items-end gap-4 flex-wrap">
            <div>
              <label className="block text-slate-400 text-xs mb-1">시작일</label>
              <input
                type="date"
                value={startDate}
                onChange={e => setStartDate(e.target.value)}
                className="bg-slate-900/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-cyan-500 focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-slate-400 text-xs mb-1">종료일</label>
              <input
                type="date"
                value={endDate}
                onChange={e => setEndDate(e.target.value)}
                className="bg-slate-900/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm focus:border-cyan-500 focus:outline-none"
              />
            </div>
            <button
              type="button"
              onClick={fetchArticles}
              disabled={loading}
              className="px-6 py-2 rounded-lg bg-gradient-to-r from-cyan-500 to-emerald-500 text-white font-medium hover:opacity-90 transition disabled:opacity-50"
            >
              {loading ? (
                <span className="flex items-center gap-2">
                  <span className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
                  조회 중...
                </span>
              ) : '기사 조회'}
            </button>
          </div>
          <div className="mt-3 flex gap-2">
            {[7, 14, 30].map(days => (
              <button
                key={days}
                type="button"
                onClick={() => {
                  setStartDate(formatDate(new Date(Date.now() - days * 86400000)));
                  setEndDate(formatDate(new Date()));
                }}
                className="text-xs px-3 py-1 rounded-full bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition"
              >
                최근 {days}일
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Step 2: 기사 선택 */}
      {step === 'select' && (
        <div className="space-y-4">
          <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-white font-semibold flex items-center gap-2">
                <MaterialIcon name="checklist" className="w-5 h-5 text-cyan-400" size={20} />
                기사 선택 ({selectedIds.size}/{articles.length})
              </h3>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={toggleAll}
                  className="text-xs px-3 py-1 rounded-lg bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition"
                >
                  {selectedIds.size === articles.length ? '전체 해제' : '전체 선택'}
                </button>
              </div>
            </div>

            <div className="max-h-[400px] overflow-y-auto space-y-2 pr-2">
              {articles.map(art => (
                <label
                  key={art.id}
                  className={`flex items-start gap-3 p-3 rounded-xl cursor-pointer transition border ${
                    selectedIds.has(art.id)
                      ? 'bg-cyan-500/5 border-cyan-500/30'
                      : 'bg-slate-900/30 border-transparent hover:border-slate-600/50'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={selectedIds.has(art.id)}
                    onChange={() => toggleId(art.id)}
                    className="mt-1 rounded border-slate-600 text-cyan-500 focus:ring-cyan-500/30 bg-slate-800"
                  />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap mb-1">
                      {art.category_parent && (
                        <span className={`text-[10px] font-bold uppercase px-1.5 py-0.5 rounded ${
                          categoryColors[art.category_parent] || 'bg-slate-500/20 text-slate-400'
                        }`}>
                          {categoryLabels[art.category_parent] || art.category_parent}
                        </span>
                      )}
                      {art.rag_metadata?.topic_label && (
                        <span className="text-[10px] px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-400">
                          {art.rag_metadata.topic_label}
                        </span>
                      )}
                      <span className="text-slate-500 text-[10px]">{art.source}</span>
                    </div>
                    <p className="text-sm text-white truncate">{art.title}</p>
                    {art.rag_metadata?.entities && art.rag_metadata.entities.length > 0 && (
                      <div className="flex gap-1 mt-1 flex-wrap">
                        {art.rag_metadata.entities.map((e, i) => (
                          <span key={i} className="text-[9px] px-1.5 py-0.5 rounded-full bg-slate-700/60 text-slate-400">
                            {e}
                          </span>
                        ))}
                      </div>
                    )}
                  </div>
                </label>
              ))}
            </div>
          </div>

          <button
            type="button"
            onClick={generateGist}
            disabled={generating || selectedIds.size < 3}
            className="w-full py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-emerald-500 text-white font-semibold text-lg hover:opacity-90 transition disabled:opacity-50 flex items-center justify-center gap-2"
          >
            {generating ? (
              <>
                <span className="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent" />
                GPT 분석 중... (1~2분 소요)
              </>
            ) : (
              <>
                <MaterialIcon name="auto_awesome" className="w-5 h-5" size={20} />
                위클리 Gist 생성 ({selectedIds.size}개 기사)
              </>
            )}
          </button>
        </div>
      )}

      {/* Step 3: 결과 뷰어 */}
      {step === 'result' && gistResult && (
        <GistViewer data={gistResult} getArticleTitle={getArticleTitle} onRegenerate={() => setStep('select')} />
      )}
    </div>
  );
}

function GistViewer({ data, getArticleTitle, onRegenerate }: {
  data: GistData;
  getArticleTitle: (id: number) => string;
  onRegenerate: () => void;
}) {
  const [expandedCluster, setExpandedCluster] = useState<number | null>(
    data.clusters.length > 0 ? data.clusters[0].cluster_id : null
  );
  const [showRawJson, setShowRawJson] = useState(false);

  const sortedClusters = [...data.clusters].sort((a, b) => a.priority_rank - b.priority_rank);

  return (
    <div className="space-y-6">
      {/* Headline & Macro So What */}
      <div className="bg-gradient-to-br from-cyan-500/10 to-emerald-500/10 backdrop-blur-sm rounded-2xl p-6 border border-cyan-500/20">
        <p className="text-cyan-400 text-xs font-medium uppercase tracking-wider mb-2">
          {data.meta.period}
        </p>
        <h2 className="text-2xl font-bold text-white mb-3">{data.headline}</h2>
        <p className="text-slate-300 text-base leading-relaxed">{data.macro_so_what}</p>
        <div className="mt-3 flex items-center gap-3 text-xs text-slate-500">
          <span>기사 {data.meta.total_articles}개</span>
          <span>·</span>
          <span>클러스터 {data.clusters.length}개</span>
          <span>·</span>
          <span>{data.meta.model}</span>
        </div>
      </div>

      {/* Clusters */}
      {sortedClusters.map(cluster => {
        const isExpanded = expandedCluster === cluster.cluster_id;
        return (
          <div
            key={cluster.cluster_id}
            className="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden"
          >
            <button
              type="button"
              onClick={() => setExpandedCluster(isExpanded ? null : cluster.cluster_id)}
              className="w-full text-left p-5 flex items-start gap-4 hover:bg-slate-700/20 transition"
            >
              <div className="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-cyan-500 to-emerald-500 flex items-center justify-center text-white font-bold text-sm">
                {cluster.priority_rank}
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap mb-1">
                  <span className={`text-[10px] font-bold uppercase px-1.5 py-0.5 rounded ${
                    categoryColors[cluster.category] || 'bg-slate-500/20 text-slate-400'
                  }`}>
                    {categoryLabels[cluster.category] || cluster.category}
                  </span>
                  <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${confidenceColors[cluster.confidence]}`}>
                    {cluster.confidence}
                  </span>
                  <div className="flex gap-0.5 ml-auto">
                    {Array.from({ length: 5 }).map((_, i) => (
                      <div
                        key={i}
                        className={`w-2 h-2 rounded-full ${
                          i < cluster.impact_score ? 'bg-cyan-400' : 'bg-slate-700'
                        }`}
                      />
                    ))}
                  </div>
                </div>
                <h3 className="text-white font-semibold text-lg">{cluster.title}</h3>
                <p className="text-slate-400 text-sm mt-1">{cluster.one_line_takeaway}</p>
              </div>
              <MaterialIcon
                name={isExpanded ? 'expand_less' : 'expand_more'}
                className="w-5 h-5 text-slate-500 flex-shrink-0 mt-1"
                size={20}
              />
            </button>

            {isExpanded && (
              <div className="px-5 pb-5 space-y-4 border-t border-slate-700/50 pt-4">
                {/* Narrative */}
                <div>
                  <p className="text-slate-300 leading-relaxed">{cluster.narrative}</p>
                </div>

                {/* Perspectives */}
                <div className="space-y-3">
                  <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wider">관점 비교</h4>
                  {cluster.perspectives.map((p, i) => (
                    <div key={i} className="bg-slate-900/40 rounded-xl p-4 border border-slate-700/30">
                      <div className="flex items-center gap-2 mb-2">
                        <span className="text-cyan-400 text-xs font-medium">
                          {String.fromCharCode(65 + i)}
                        </span>
                        <span className="text-slate-500 text-xs">— {p.source}</span>
                      </div>
                      <p className="text-slate-300 text-sm">{p.viewpoint}</p>
                      {p.difference_reason && (
                        <p className="text-slate-500 text-xs mt-2 italic">근거: {p.difference_reason}</p>
                      )}
                    </div>
                  ))}
                </div>

                {/* So What */}
                <div className="bg-gradient-to-r from-amber-500/10 to-orange-500/10 rounded-xl p-4 border border-amber-500/20">
                  <h4 className="text-amber-400 text-xs font-semibold uppercase tracking-wider mb-2">So What</h4>
                  <p className="text-white font-medium">{cluster.so_what}</p>
                </div>

                {/* Source Articles */}
                <div className="flex flex-wrap gap-1.5">
                  <span className="text-slate-500 text-xs mr-1">참조 기사:</span>
                  {cluster.source_article_ids.map(id => (
                    <span key={id} className="text-[10px] px-2 py-0.5 rounded-full bg-slate-700/60 text-slate-400">
                      {getArticleTitle(id)}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        );
      })}

      {/* Cross Connections */}
      {data.cross_connections.length > 0 && (
        <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
          <h3 className="text-white font-semibold mb-4 flex items-center gap-2">
            <MaterialIcon name="hub" className="w-5 h-5 text-violet-400" size={20} />
            이슈 간 연결
          </h3>
          <div className="space-y-3">
            {data.cross_connections.map((cc, i) => {
              const from = data.clusters.find(c => c.cluster_id === cc.from_cluster);
              const to = data.clusters.find(c => c.cluster_id === cc.to_cluster);
              return (
                <div key={i} className="flex items-center gap-3 bg-slate-900/40 rounded-xl p-4 border border-slate-700/30">
                  <span className="text-cyan-400 font-medium text-sm whitespace-nowrap">
                    {from?.title || `#${cc.from_cluster}`}
                  </span>
                  <MaterialIcon name="arrow_forward" className="w-4 h-4 text-slate-600 flex-shrink-0" size={16} />
                  <span className="text-emerald-400 font-medium text-sm whitespace-nowrap">
                    {to?.title || `#${cc.to_cluster}`}
                  </span>
                  <span className="text-slate-500 text-xs ml-2">{cc.relationship}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Next Week Watch */}
      {data.next_week_watch.length > 0 && (
        <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
          <h3 className="text-white font-semibold mb-3 flex items-center gap-2">
            <MaterialIcon name="visibility" className="w-5 h-5 text-amber-400" size={20} />
            다음 주 주목 포인트
          </h3>
          <ul className="space-y-2">
            {data.next_week_watch.map((item, i) => (
              <li key={i} className="flex items-start gap-2 text-slate-300 text-sm">
                <span className="text-amber-400 mt-0.5">▸</span>
                {item}
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Actions */}
      <div className="flex items-center gap-3 flex-wrap">
        <button
          type="button"
          onClick={onRegenerate}
          className="px-5 py-2 rounded-xl bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition flex items-center gap-2"
        >
          <MaterialIcon name="refresh" className="w-4 h-4" size={16} />
          다시 생성
        </button>
        <button
          type="button"
          onClick={() => setShowRawJson(!showRawJson)}
          className="px-5 py-2 rounded-xl bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition flex items-center gap-2"
        >
          <MaterialIcon name="code" className="w-4 h-4" size={16} />
          {showRawJson ? 'JSON 숨기기' : 'Raw JSON'}
        </button>
        <button
          type="button"
          onClick={() => {
            navigator.clipboard.writeText(JSON.stringify(data, null, 2));
          }}
          className="px-5 py-2 rounded-xl bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition flex items-center gap-2"
        >
          <MaterialIcon name="content_copy" className="w-4 h-4" size={16} />
          JSON 복사
        </button>
      </div>

      {showRawJson && (
        <div className="bg-slate-900/80 rounded-2xl p-4 border border-slate-700/50 overflow-x-auto">
          <pre className="text-xs text-slate-400 whitespace-pre-wrap">
            {JSON.stringify(data, null, 2)}
          </pre>
        </div>
      )}
    </div>
  );
}
