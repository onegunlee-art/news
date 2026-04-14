import { useState, useCallback, useEffect } from 'react';
import MaterialIcon from '../Common/MaterialIcon';
import { adminFetch } from '../../services/api';

const API_BASE = import.meta.env.VITE_API_URL || '/api';

interface Perspective {
  viewpoint: string;
  source: string;
  difference_reason: string;
}

interface SoWhat {
  implication: string;
  why_it_matters: string;
  what_to_watch: string[];
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
  so_what: SoWhat | string;
}

interface ActionHints {
  watch: string[];
  consider: string[];
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
  action_hints?: ActionHints;
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

interface SavedListItem {
  id: number;
  period_start: string;
  period_end: string;
  headline: string | null;
  article_count: number;
  created_at: string;
  updated_at: string;
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

type WeeklyGistProps = {
  /** 참조 기사 ID로 뉴스 관리 탭에서 해당 기사 편집 열기 */
  onEditNewsArticle?: (newsId: number) => void | Promise<void>;
};

export default function WeeklyGist({ onEditNewsArticle }: WeeklyGistProps) {
  const [uiTab, setUiTab] = useState<'library' | 'create'>('library');
  const [savedList, setSavedList] = useState<SavedListItem[]>([]);
  const [loadingList, setLoadingList] = useState(false);

  const [startDate, setStartDate] = useState(() => formatDate(new Date(Date.now() - 7 * 86400000)));
  const [endDate, setEndDate] = useState(() => formatDate(new Date()));
  const [articles, setArticles] = useState<ArticleItem[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [gistResult, setGistResult] = useState<GistData | null>(null);
  /** 저장 스냅샷 id -> 제목 (목록에서 연 경우·생성 직후) */
  const [articleTitleMap, setArticleTitleMap] = useState<Record<number, string>>({});
  const [currentSavedId, setCurrentSavedId] = useState<number | null>(null);

  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState('');
  const [step, setStep] = useState<'fetch' | 'select' | 'result'>('fetch');

  const loadSavedList = useCallback(async () => {
    setLoadingList(true);
    try {
      const res = await adminFetch(`${API_BASE}/admin/weekly-gist.php?action=list&limit=80`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || '목록 조회 실패');
      setSavedList(data.data?.items ?? []);
    } catch {
      setSavedList([]);
    } finally {
      setLoadingList(false);
    }
  }, []);

  useEffect(() => {
    loadSavedList();
  }, [loadSavedList]);

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
      const map: Record<number, string> = {};
      arts.forEach(a => {
        map[a.id] = a.title || '';
      });
      setArticleTitleMap(map);
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
      const map: Record<number, string> = {};
      selected.forEach(a => {
        map[a.id] = a.title || '';
      });
      setArticleTitleMap(map);
      if (typeof data.saved_id === 'number' && data.saved_id > 0) {
        setCurrentSavedId(data.saved_id);
      } else {
        setCurrentSavedId(null);
        if (data.save_error) {
          setError(`DB 저장 실패: ${data.save_error}`);
        }
      }
      setStep('result');
      loadSavedList();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setGenerating(false);
    }
  }, [articles, selectedIds, startDate, endDate, loadSavedList]);

  const openSavedReport = useCallback(
    async (id: number) => {
      setError('');
      try {
        const res = await adminFetch(`${API_BASE}/admin/weekly-gist.php?action=detail&id=${id}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '불러오기 실패');
        const d = data.data;
        setGistResult(d.gist as GistData);
        const titles = (d.article_titles_map || {}) as Record<number, string>;
        const norm: Record<number, string> = {};
        Object.keys(titles).forEach(k => {
          norm[Number(k)] = titles[Number(k)];
        });
        setArticleTitleMap(norm);
        setCurrentSavedId(id);
        setArticles([]);
        setStep('result');
        setUiTab('create');
      } catch (e) {
        setError((e as Error).message);
      }
    },
    []
  );

  const resetFlow = () => {
    setStep('fetch');
    setGistResult(null);
    setArticles([]);
    setError('');
    setCurrentSavedId(null);
    setArticleTitleMap({});
  };

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

  const getArticleTitle = (id: number) =>
    articleTitleMap[id] || articles.find(a => a.id === id)?.title || `기사 #${id}`;

  const allReferenceIds = gistResult
    ? Array.from(
        new Set(
          gistResult.clusters.flatMap(c => c.source_article_ids || []).filter(n => typeof n === 'number')
        )
      ).sort((a, b) => a - b)
    : [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 className="text-2xl font-bold text-white mb-1">위클리 Gist</h2>
          <p className="text-slate-400 text-sm">주간 뉴스를 종합하여 인텔리전스 브리핑을 생성합니다</p>
        </div>
      </div>

      {/* 상단 탭: 저장 목록 | 새로 생성 */}
      <div className="flex gap-2 border-b border-slate-700/50 pb-1">
        <button
          type="button"
          onClick={() => setUiTab('library')}
          className={`px-4 py-2 rounded-t-lg text-sm font-medium transition ${
            uiTab === 'library'
              ? 'bg-slate-800 text-cyan-400 border border-b-0 border-slate-600'
              : 'text-slate-400 hover:text-white'
          }`}
        >
          <MaterialIcon name="folder_open" className="w-4 h-4 inline mr-1" size={16} />
          저장된 리포트
        </button>
        <button
          type="button"
          onClick={() => {
            setUiTab('create');
            if (step === 'result') resetFlow();
          }}
          className={`px-4 py-2 rounded-t-lg text-sm font-medium transition ${
            uiTab === 'create'
              ? 'bg-slate-800 text-cyan-400 border border-b-0 border-slate-600'
              : 'text-slate-400 hover:text-white'
          }`}
        >
          <MaterialIcon name="add_circle_outline" className="w-4 h-4 inline mr-1" size={16} />
          새로 생성
        </button>
      </div>

      {error && (
        <div className="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-400 text-sm">
          {error}
        </div>
      )}

      {/* 저장 목록 */}
      {uiTab === 'library' && (
        <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
          <div className="p-4 flex items-center justify-between border-b border-slate-700/50">
            <h3 className="text-white font-semibold text-sm">저장된 위클리 Gist</h3>
            <button
              type="button"
              onClick={() => loadSavedList()}
              className="text-cyan-400 hover:text-cyan-300 text-xs flex items-center gap-1"
            >
              <MaterialIcon name="refresh" className="w-3.5 h-3.5" size={14} />
              새로고침
            </button>
          </div>
          {loadingList ? (
            <div className="flex justify-center py-12">
              <div className="animate-spin rounded-full h-8 w-8 border-2 border-cyan-500 border-t-transparent" />
            </div>
          ) : savedList.length === 0 ? (
            <p className="text-slate-500 text-sm p-8 text-center">저장된 리포트가 없습니다. 「새로 생성」에서 만드세요.</p>
          ) : (
            <div className="max-h-[480px] overflow-y-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-left text-slate-500 text-xs uppercase border-b border-slate-700/50">
                    <th className="p-3 pl-4">기간</th>
                    <th className="p-3">한 줄</th>
                    <th className="p-3 w-20">기사</th>
                    <th className="p-3 w-40">저장일</th>
                    <th className="p-3 pr-4 w-28" />
                  </tr>
                </thead>
                <tbody>
                  {savedList.map(row => (
                    <tr key={row.id} className="border-b border-slate-700/30 hover:bg-slate-700/20">
                      <td className="p-3 pl-4 text-slate-300 whitespace-nowrap">
                        {row.period_start} ~ {row.period_end}
                      </td>
                      <td className="p-3 text-white max-w-md truncate" title={row.headline || ''}>
                        {row.headline || '(제목 없음)'}
                      </td>
                      <td className="p-3 text-slate-400">{row.article_count}</td>
                      <td className="p-3 text-slate-500 text-xs whitespace-nowrap">
                        {new Date(row.created_at).toLocaleString('ko-KR')}
                      </td>
                      <td className="p-3 pr-4">
                        <button
                          type="button"
                          onClick={() => openSavedReport(row.id)}
                          className="text-xs px-3 py-1.5 rounded-lg bg-cyan-500/20 text-cyan-400 hover:bg-cyan-500/30"
                        >
                          열기
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* 새로 생성 플로우 */}
      {uiTab === 'create' && (
        <>
          <div className="flex items-center justify-between flex-wrap gap-3">
            {step !== 'fetch' && (
              <button
                type="button"
                onClick={resetFlow}
                className="text-cyan-400 hover:text-cyan-300 text-sm flex items-center gap-1 ml-auto"
              >
                <MaterialIcon name="restart_alt" className="w-4 h-4" size={16} />
                처음부터
              </button>
            )}
          </div>

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
                  ) : (
                    '기사 조회'
                  )}
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

          {step === 'select' && (
            <div className="space-y-4">
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-white font-semibold flex items-center gap-2">
                    <MaterialIcon name="checklist" className="w-5 h-5 text-cyan-400" size={20} />
                    기사 선택 ({selectedIds.size}/{articles.length})
                  </h3>
                  <button
                    type="button"
                    onClick={toggleAll}
                    className="text-xs px-3 py-1 rounded-lg bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition"
                  >
                    {selectedIds.size === articles.length ? '전체 해제' : '전체 선택'}
                  </button>
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
                            <span
                              className={`text-[10px] font-bold uppercase px-1.5 py-0.5 rounded ${
                                categoryColors[art.category_parent] || 'bg-slate-500/20 text-slate-400'
                              }`}
                            >
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

          {step === 'result' && gistResult && (
            <GistViewer
              data={gistResult}
              getArticleTitle={getArticleTitle}
              savedId={currentSavedId}
              allReferenceIds={allReferenceIds}
              onRegenerate={() => setStep('select')}
              onEditNewsArticle={onEditNewsArticle}
            />
          )}
        </>
      )}
    </div>
  );
}

function GistViewer({
  data,
  getArticleTitle,
  savedId,
  allReferenceIds,
  onRegenerate,
  onEditNewsArticle,
}: {
  data: GistData;
  getArticleTitle: (id: number) => string;
  savedId: number | null;
  allReferenceIds: number[];
  onRegenerate: () => void;
  onEditNewsArticle?: (id: number) => void | Promise<void>;
}) {
  const [expandedCluster, setExpandedCluster] = useState<number | null>(
    data.clusters.length > 0 ? data.clusters[0].cluster_id : null
  );
  const [showRawJson, setShowRawJson] = useState(false);

  const sortedClusters = [...data.clusters].sort((a, b) => a.priority_rank - b.priority_rank);

  return (
    <div className="space-y-6">
      {savedId !== null && (
        <p className="text-slate-500 text-xs">
          저장 ID: <span className="text-cyan-400 font-mono">{savedId}</span> — 「저장된 리포트」에서 다시 열 수 있습니다.
        </p>
      )}

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
                  <span
                    className={`text-[10px] font-bold uppercase px-1.5 py-0.5 rounded ${
                      categoryColors[cluster.category] || 'bg-slate-500/20 text-slate-400'
                    }`}
                  >
                    {categoryLabels[cluster.category] || cluster.category}
                  </span>
                  <span
                    className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${confidenceColors[cluster.confidence]}`}
                  >
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
                <div>
                  <p className="text-slate-300 leading-relaxed">{cluster.narrative}</p>
                </div>

                <div className="space-y-3">
                  <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wider">관점 비교</h4>
                  {cluster.perspectives.map((p, i) => (
                    <div key={i} className="bg-slate-900/40 rounded-xl p-4 border border-slate-700/30">
                      <div className="flex items-center gap-2 mb-2">
                        <span className="text-cyan-400 text-xs font-medium">{String.fromCharCode(65 + i)}</span>
                        <span className="text-slate-500 text-xs">— {p.source}</span>
                      </div>
                      <p className="text-slate-300 text-sm">{p.viewpoint}</p>
                      {p.difference_reason && (
                        <p className="text-slate-500 text-xs mt-2 italic">근거: {p.difference_reason}</p>
                      )}
                    </div>
                  ))}
                </div>

                <div className="bg-gradient-to-r from-amber-500/10 to-orange-500/10 rounded-xl p-4 border border-amber-500/20 space-y-3">
                  <h4 className="text-amber-400 text-xs font-semibold uppercase tracking-wider">So What</h4>
                  {typeof cluster.so_what === 'object' && cluster.so_what !== null ? (
                    <>
                      <p className="text-white font-medium">{cluster.so_what.implication}</p>
                      {cluster.so_what.why_it_matters && (
                        <div className="pl-3 border-l-2 border-amber-500/40">
                          <span className="text-amber-400/70 text-[10px] font-semibold uppercase tracking-wider">Why it matters</span>
                          <p className="text-slate-300 text-sm mt-0.5">{cluster.so_what.why_it_matters}</p>
                        </div>
                      )}
                      {cluster.so_what.what_to_watch?.length > 0 && (
                        <div className="pl-3 border-l-2 border-cyan-500/40">
                          <span className="text-cyan-400/70 text-[10px] font-semibold uppercase tracking-wider">Watch signals</span>
                          <ul className="mt-1 space-y-1">
                            {cluster.so_what.what_to_watch.map((sig, si) => (
                              <li key={si} className="text-slate-400 text-xs flex items-start gap-1.5">
                                <span className="text-cyan-400 mt-0.5">▹</span>{sig}
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}
                    </>
                  ) : (
                    <p className="text-white font-medium">{String(cluster.so_what)}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <span className="text-slate-500 text-xs">참조 기사</span>
                  <div className="flex flex-col gap-2">
                    {(cluster.source_article_ids || []).map(id => (
                      <div
                        key={id}
                        className="flex items-center justify-between gap-2 flex-wrap bg-slate-900/40 rounded-lg px-3 py-2 border border-slate-700/40"
                      >
                        <span className="text-sm text-slate-300 flex-1 min-w-0 truncate" title={getArticleTitle(id)}>
                          <span className="text-slate-500 text-xs mr-2">#{id}</span>
                          {getArticleTitle(id)}
                        </span>
                        {onEditNewsArticle && (
                          <button
                            type="button"
                            onClick={() => void onEditNewsArticle(id)}
                            className="text-xs shrink-0 px-2.5 py-1 rounded-md bg-slate-600/80 text-white hover:bg-slate-500 flex items-center gap-1"
                          >
                            <MaterialIcon name="edit_note" className="w-3.5 h-3.5" size={14} />
                            기사 수정
                          </button>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>
        );
      })}

      {onEditNewsArticle && allReferenceIds.length > 0 && (
        <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-5 border border-slate-700/50">
          <h3 className="text-white font-semibold mb-3 text-sm flex items-center gap-2">
            <MaterialIcon name="newspaper" className="w-5 h-5 text-slate-400" size={20} />
            리포트 참조 기사 한눈에 ({allReferenceIds.length})
          </h3>
          <div className="flex flex-wrap gap-2">
            {allReferenceIds.map(id => (
              <button
                key={id}
                type="button"
                onClick={() => void onEditNewsArticle(id)}
                className="text-xs px-3 py-1.5 rounded-lg bg-slate-700/60 text-slate-200 hover:bg-slate-600 border border-slate-600/50"
                title={getArticleTitle(id)}
              >
                #{id} · {getArticleTitle(id).length > 28 ? `${getArticleTitle(id).slice(0, 28)}…` : getArticleTitle(id)}
              </button>
            ))}
          </div>
        </div>
      )}

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
                <div key={i} className="flex items-center gap-3 bg-slate-900/40 rounded-xl p-4 border border-slate-700/30 flex-wrap">
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

      {data.action_hints && (data.action_hints.watch?.length > 0 || data.action_hints.consider?.length > 0) && (
        <div className="bg-gradient-to-br from-rose-500/10 to-orange-500/10 backdrop-blur-sm rounded-2xl p-6 border border-rose-500/20">
          <h3 className="text-white font-semibold mb-4 flex items-center gap-2">
            <MaterialIcon name="lightbulb" className="w-5 h-5 text-rose-400" size={20} />
            Action Hints
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {data.action_hints.watch?.length > 0 && (
              <div>
                <h4 className="text-rose-400 text-xs font-semibold uppercase tracking-wider mb-2">Watch — 추적 행동</h4>
                <ul className="space-y-2">
                  {data.action_hints.watch.map((item, i) => (
                    <li key={i} className="flex items-start gap-2 text-slate-300 text-sm">
                      <span className="text-rose-400 mt-0.5">▸</span>
                      {item}
                    </li>
                  ))}
                </ul>
              </div>
            )}
            {data.action_hints.consider?.length > 0 && (
              <div>
                <h4 className="text-orange-400 text-xs font-semibold uppercase tracking-wider mb-2">Consider — 검토 사항</h4>
                <ul className="space-y-2">
                  {data.action_hints.consider.map((item, i) => (
                    <li key={i} className="flex items-start gap-2 text-slate-300 text-sm">
                      <span className="text-orange-400 mt-0.5">▸</span>
                      {item}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        </div>
      )}

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
            void navigator.clipboard.writeText(JSON.stringify(data, null, 2));
          }}
          className="px-5 py-2 rounded-xl bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition flex items-center gap-2"
        >
          <MaterialIcon name="content_copy" className="w-4 h-4" size={16} />
          JSON 복사
        </button>
      </div>

      {showRawJson && (
        <div className="bg-slate-900/80 rounded-2xl p-4 border border-slate-700/50 overflow-x-auto">
          <pre className="text-xs text-slate-400 whitespace-pre-wrap">{JSON.stringify(data, null, 2)}</pre>
        </div>
      )}
    </div>
  );
}
