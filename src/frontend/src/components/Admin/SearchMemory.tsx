import { useCallback, useEffect, useState } from 'react';
import MaterialIcon from '../Common/MaterialIcon';
import { adminFetch } from '../../services/api';

const API = `${import.meta.env.VITE_API_URL || '/api'}/admin/search-reports.php`;

interface ReportListItem {
  id: number;
  search_query: string | null;
  cluster_name: string;
  cluster_question: string | null;
  article_count: number;
  entities: string[];
  topic_labels: string[];
  analysis_preview: string;
  created_at: string;
  updated_at: string;
}

interface MemoryDiff {
  matched: boolean;
  diff_summary: string;
  gist_report_id?: number | null;
  gist_period?: string | null;
  gist_headline?: string | null;
  weeks_ago?: number | null;
  framing_then?: string;
  framing_now?: string;
  matched_cluster?: {
    title: string;
    one_line_takeaway: string;
    narrative_excerpt: string;
  } | null;
  overlap?: {
    entities: string[];
    topic_labels: string[];
    combined_score: number;
  } | null;
}

interface ReportDetail {
  id: number;
  search_query: string | null;
  cluster_name: string;
  cluster_question: string | null;
  analysis_text: string;
  news_ids: number[];
  article_titles: Record<number, string>;
  entities: string[];
  topic_labels: string[];
  meta?: { memory_diff?: MemoryDiff };
  created_at: string;
  updated_at: string;
}

type UiTab = 'library' | 'memory' | 'create';

export default function SearchMemory() {
  const [uiTab, setUiTab] = useState<UiTab>('library');
  const [items, setItems] = useState<ReportListItem[]>([]);
  const [loadingList, setLoadingList] = useState(false);
  const [selected, setSelected] = useState<ReportDetail | null>(null);
  const [editText, setEditText] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const [newsIdsInput, setNewsIdsInput] = useState('');
  const [clusterName, setClusterName] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [clusterQuestion, setClusterQuestion] = useState('');
  const [analysisText, setAnalysisText] = useState('');
  const [memoryResult, setMemoryResult] = useState<MemoryDiff | null>(null);
  const [generating, setGenerating] = useState(false);
  const [checkingMemory, setCheckingMemory] = useState(false);

  const loadList = useCallback(async () => {
    setLoadingList(true);
    setError('');
    try {
      const res = await adminFetch(`${API}?action=list&limit=80`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || '목록 조회 실패');
      setItems(data.data?.items ?? []);
    } catch (e) {
      setItems([]);
      setError(e instanceof Error ? e.message : '목록 조회 실패');
    } finally {
      setLoadingList(false);
    }
  }, []);

  useEffect(() => {
    if (uiTab === 'library') loadList();
  }, [uiTab, loadList]);

  const openDetail = async (id: number) => {
    setError('');
    try {
      const res = await adminFetch(`${API}?action=detail&id=${id}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || '상세 조회 실패');
      const detail = data.data as ReportDetail;
      setSelected(detail);
      setEditText(detail.analysis_text);
    } catch (e) {
      setError(e instanceof Error ? e.message : '상세 조회 실패');
    }
  };

  const saveEdit = async () => {
    if (!selected) return;
    setSaving(true);
    setError('');
    try {
      const res = await adminFetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', id: selected.id, analysis_text: editText }),
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || '저장 실패');
      setSelected(data.data as ReportDetail);
      await loadList();
    } catch (e) {
      setError(e instanceof Error ? e.message : '저장 실패');
    } finally {
      setSaving(false);
    }
  };

  const parseNewsIds = (): number[] =>
    newsIdsInput
      .split(/[,\s]+/)
      .map((s) => parseInt(s.trim(), 10))
      .filter((id) => id > 0);

  const runMemoryDiff = async () => {
    const ids = parseNewsIds();
    if (ids.length === 0 || !clusterName.trim()) {
      setError('news_ids와 cluster_name을 입력하세요.');
      return;
    }
    setCheckingMemory(true);
    setError('');
    setMemoryResult(null);
    try {
      const qs = new URLSearchParams({
        action: 'memory_diff',
        news_ids: ids.join(','),
        cluster_name: clusterName.trim(),
      });
      const res = await adminFetch(`${API}?${qs}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Memory diff 실패');
      setMemoryResult(data.data as MemoryDiff);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Memory diff 실패');
    } finally {
      setCheckingMemory(false);
    }
  };

  const handleGenerate = async () => {
    const ids = parseNewsIds();
    if (ids.length === 0 || !clusterName.trim()) {
      setError('news_ids와 cluster_name을 입력하세요.');
      return;
    }
    setGenerating(true);
    setError('');
    try {
      const res = await adminFetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'generate',
          news_ids: ids,
          cluster_name: clusterName.trim(),
          search_query: searchQuery.trim() || undefined,
          cluster_question: clusterQuestion.trim() || undefined,
        }),
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || '생성 실패');
      setMemoryResult(data.memory as MemoryDiff);
      setSelected(data.data as ReportDetail);
      setEditText((data.data as ReportDetail).analysis_text);
      setUiTab('library');
      await loadList();
    } catch (e) {
      setError(e instanceof Error ? e.message : '생성 실패');
    } finally {
      setGenerating(false);
    }
  };

  const handleManualSave = async () => {
    const ids = parseNewsIds();
    if (ids.length === 0 || !clusterName.trim() || !analysisText.trim()) {
      setError('news_ids, cluster_name, analysis_text를 입력하세요.');
      return;
    }
    setSaving(true);
    setError('');
    try {
      const res = await adminFetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save',
          news_ids: ids,
          cluster_name: clusterName.trim(),
          search_query: searchQuery.trim() || undefined,
          cluster_question: clusterQuestion.trim() || undefined,
          analysis_text: analysisText.trim(),
        }),
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || '저장 실패');
      setMemoryResult(data.memory as MemoryDiff);
      setSelected(data.data as ReportDetail);
      setUiTab('library');
      await loadList();
    } catch (e) {
      setError(e instanceof Error ? e.message : '저장 실패');
    } finally {
      setSaving(false);
    }
  };

  const renderMemoryPanel = (memory: MemoryDiff | null | undefined) => {
    if (!memory) return null;
    return (
      <div
        className={`rounded-xl border p-4 ${
          memory.matched
            ? 'border-violet-500/40 bg-violet-500/10'
            : 'border-slate-600/60 bg-slate-800/40'
        }`}
      >
        <div className="flex items-center gap-2 mb-2">
          <MaterialIcon name="history" className="text-violet-400" size={18} />
          <span className="text-sm font-semibold text-violet-300">Gist ↔ 검색 Memory</span>
          {memory.matched && memory.weeks_ago != null && (
            <span className="text-xs text-slate-500">{memory.weeks_ago}주 전 Gist 연결</span>
          )}
        </div>
        <p className="text-sm text-slate-300 leading-relaxed">{memory.diff_summary}</p>
        {memory.matched && memory.matched_cluster && (
          <div className="mt-3 grid gap-2 md:grid-cols-2 text-xs">
            <div className="rounded-lg bg-slate-900/60 p-3">
              <p className="text-slate-500 mb-1">그때 (Gist)</p>
              <p className="text-slate-200">{memory.framing_then}</p>
            </div>
            <div className="rounded-lg bg-slate-900/60 p-3">
              <p className="text-slate-500 mb-1">지금 (클러스터)</p>
              <p className="text-slate-200">{memory.framing_now}</p>
            </div>
          </div>
        )}
        {memory.overlap && (
          <p className="mt-2 text-xs text-slate-500">
            overlap score {memory.overlap.combined_score}
            {memory.overlap.entities?.length > 0 && ` · entities: ${memory.overlap.entities.join(', ')}`}
          </p>
        )}
      </div>
    );
  };

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold text-white">검색 메모리</h2>
        <p className="text-sm text-slate-400 mt-1">
          검색 클러스터 분석 저장·재열람 및 위클리 Gist entity/topic 연결 (고객 검색 UI 무변경)
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {(
          [
            { id: 'library' as const, label: '저장 목록', icon: 'folder' },
            { id: 'memory' as const, label: 'Memory Diff', icon: 'compare' },
            { id: 'create' as const, label: '생성·저장', icon: 'add_circle' },
          ] as const
        ).map((tab) => (
          <button
            key={tab.id}
            type="button"
            onClick={() => {
              setUiTab(tab.id);
              setError('');
            }}
            className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm ${
              uiTab === tab.id
                ? 'bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-500/30'
                : 'bg-slate-800 text-slate-400 hover:text-slate-200'
            }`}
          >
            <MaterialIcon name={tab.icon} size={16} />
            {tab.label}
          </button>
        ))}
      </div>

      {error && (
        <div className="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300">
          {error}
        </div>
      )}

      {uiTab === 'library' && (
        <div className="grid gap-6 lg:grid-cols-2">
          <div className="rounded-xl border border-slate-700/60 bg-slate-900/40 p-4">
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-semibold text-white">search_reports</h3>
              <button
                type="button"
                onClick={loadList}
                className="text-xs text-cyan-400 hover:text-cyan-300"
              >
                새로고침
              </button>
            </div>
            {loadingList ? (
              <p className="text-sm text-slate-500 py-8 text-center">불러오는 중...</p>
            ) : items.length === 0 ? (
              <p className="text-sm text-slate-500 py-8 text-center">저장된 검색 분석이 없습니다.</p>
            ) : (
              <ul className="space-y-2 max-h-[520px] overflow-y-auto">
                {items.map((item) => (
                  <li key={item.id}>
                    <button
                      type="button"
                      onClick={() => openDetail(item.id)}
                      className={`w-full text-left rounded-lg border px-3 py-3 transition ${
                        selected?.id === item.id
                          ? 'border-cyan-500/50 bg-cyan-500/10'
                          : 'border-slate-700/60 bg-slate-800/40 hover:border-slate-600'
                      }`}
                    >
                      <p className="text-sm font-medium text-white truncate">{item.cluster_name}</p>
                      <p className="text-xs text-slate-500 mt-1">
                        #{item.id} · 기사 {item.article_count}건 · {item.created_at.slice(0, 10)}
                      </p>
                      {item.entities.length > 0 && (
                        <p className="text-xs text-violet-400/80 mt-1 truncate">
                          {item.entities.slice(0, 4).join(', ')}
                        </p>
                      )}
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="rounded-xl border border-slate-700/60 bg-slate-900/40 p-4">
            {!selected ? (
              <p className="text-sm text-slate-500 py-12 text-center">목록에서 리포트를 선택하세요.</p>
            ) : (
              <div className="space-y-4">
                <div>
                  <h3 className="text-lg font-semibold text-white">{selected.cluster_name}</h3>
                  {selected.search_query && (
                    <p className="text-xs text-slate-500 mt-1">검색어: {selected.search_query}</p>
                  )}
                  <p className="text-xs text-slate-500">
                    news_ids: {selected.news_ids.join(', ')}
                  </p>
                </div>
                {renderMemoryPanel(selected.meta?.memory_diff)}
                <textarea
                  value={editText}
                  onChange={(e) => setEditText(e.target.value)}
                  rows={14}
                  className="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-200 leading-relaxed"
                />
                <button
                  type="button"
                  onClick={saveEdit}
                  disabled={saving}
                  className="rounded-lg bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 px-4 py-2 text-sm font-medium text-white"
                >
                  {saving ? '저장 중...' : '분석 저장'}
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {uiTab === 'memory' && (
        <div className="rounded-xl border border-slate-700/60 bg-slate-900/40 p-6 space-y-4 max-w-2xl">
          <p className="text-sm text-slate-400">
            news_ids와 cluster_name으로 위클리 Gist와의 entity/topic 겹침을 확인합니다.
          </p>
          <label className="block">
            <span className="text-xs text-slate-500">news_ids (쉼표 구분)</span>
            <input
              value={newsIdsInput}
              onChange={(e) => setNewsIdsInput(e.target.value)}
              placeholder="12, 45, 67"
              className="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white"
            />
          </label>
          <label className="block">
            <span className="text-xs text-slate-500">cluster_name</span>
            <input
              value={clusterName}
              onChange={(e) => setClusterName(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white"
            />
          </label>
          <button
            type="button"
            onClick={runMemoryDiff}
            disabled={checkingMemory}
            className="rounded-lg bg-violet-600 hover:bg-violet-500 disabled:opacity-50 px-4 py-2 text-sm font-medium text-white"
          >
            {checkingMemory ? '비교 중...' : 'Gist Memory Diff 실행'}
          </button>
          {renderMemoryPanel(memoryResult)}
        </div>
      )}

      {uiTab === 'create' && (
        <div className="rounded-xl border border-slate-700/60 bg-slate-900/40 p-6 space-y-4 max-w-2xl">
          <label className="block">
            <span className="text-xs text-slate-500">search_query (선택)</span>
            <input
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white"
            />
          </label>
          <label className="block">
            <span className="text-xs text-slate-500">news_ids</span>
            <input
              value={newsIdsInput}
              onChange={(e) => setNewsIdsInput(e.target.value)}
              placeholder="12, 45, 67"
              className="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white"
            />
          </label>
          <label className="block">
            <span className="text-xs text-slate-500">cluster_name</span>
            <input
              value={clusterName}
              onChange={(e) => setClusterName(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white"
            />
          </label>
          <label className="block">
            <span className="text-xs text-slate-500">cluster_question (선택)</span>
            <input
              value={clusterQuestion}
              onChange={(e) => setClusterQuestion(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white"
            />
          </label>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={handleGenerate}
              disabled={generating}
              className="rounded-lg bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 px-4 py-2 text-sm font-medium text-white"
            >
              {generating ? 'GPT 생성 중...' : 'GPT 생성 + 저장'}
            </button>
            <button
              type="button"
              onClick={runMemoryDiff}
              disabled={checkingMemory}
              className="rounded-lg bg-violet-600/80 hover:bg-violet-500 disabled:opacity-50 px-4 py-2 text-sm font-medium text-white"
            >
              Memory Diff만
            </button>
          </div>
          <label className="block">
            <span className="text-xs text-slate-500">analysis_text (수동 저장 시)</span>
            <textarea
              value={analysisText}
              onChange={(e) => setAnalysisText(e.target.value)}
              rows={8}
              placeholder="고객 검색에서 복사한 분석 텍스트를 붙여넣을 수 있습니다."
              className="mt-1 w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-200"
            />
          </label>
          <button
            type="button"
            onClick={handleManualSave}
            disabled={saving}
            className="rounded-lg border border-slate-500 hover:bg-slate-800 disabled:opacity-50 px-4 py-2 text-sm text-slate-300"
          >
            {saving ? '저장 중...' : '수동 텍스트 저장'}
          </button>
          {renderMemoryPanel(memoryResult)}
        </div>
      )}
    </div>
  );
}
