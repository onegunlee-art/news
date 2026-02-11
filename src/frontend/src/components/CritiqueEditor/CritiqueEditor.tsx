import React, { useState, useEffect } from 'react';

interface Critique {
  id: string;
  news_id?: number;
  article_url?: string;
  article_title?: string;
  critique_text: string;
  critique_type: string;
  editor_notes: Record<string, string>;
  version: number;
  parent_id?: string;
  created_at: string;
}

interface CritiqueEditorProps {
  newsId?: number;
  articleUrl?: string;
  articleTitle?: string;
}

const CRITIQUE_TYPES = [
  { value: 'general', label: '일반 피드백' },
  { value: 'analysis_quality', label: '분석 품질' },
  { value: 'narration_tone', label: '내레이션 톤' },
  { value: 'missing_perspective', label: '누락 관점' },
  { value: 'improvement', label: '개선 제안' },
  { value: 'factual_error', label: '사실 오류' },
];

const CritiqueEditor: React.FC<CritiqueEditorProps> = ({ newsId, articleUrl, articleTitle }) => {
  const [critiques, setCritiques] = useState<Critique[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Form state
  const [critiqueText, setCritiqueText] = useState('');
  const [critiqueType, setCritiqueType] = useState('general');
  const [analysisQuality, setAnalysisQuality] = useState('');
  const [narrationTone, setNarrationTone] = useState('');
  const [missingPerspective, setMissingPerspective] = useState('');
  const [improvementSuggestion, setImprovementSuggestion] = useState('');

  // Version viewing
  const [selectedCritique, setSelectedCritique] = useState<Critique | null>(null);
  const [versions, setVersions] = useState<Critique[]>([]);
  const [editingId, setEditingId] = useState<string | null>(null);

  const fetchCritiques = async () => {
    setLoading(true);
    try {
      const params = newsId ? `news_id=${newsId}` : articleUrl ? `article_url=${encodeURIComponent(articleUrl)}` : '';
      if (!params) { setLoading(false); return; }
      const res = await fetch(`/api/admin/critique-api.php?action=list_critiques&${params}`);
      const data = await res.json();
      if (data.success) setCritiques(data.critiques || []);
    } catch {
      /* ignore */
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (newsId || articleUrl) fetchCritiques();
  }, [newsId, articleUrl]);

  const handleSave = async () => {
    if (!critiqueText.trim()) return;
    setSaving(true);
    setMessage(null);

    const editorNotes: Record<string, string> = {};
    if (analysisQuality.trim()) editorNotes.analysis_quality = analysisQuality;
    if (narrationTone.trim()) editorNotes.narration_tone = narrationTone;
    if (missingPerspective.trim()) editorNotes.missing_perspective = missingPerspective;
    if (improvementSuggestion.trim()) editorNotes.improvement_suggestion = improvementSuggestion;

    try {
      const body: Record<string, unknown> = {
        action: editingId ? 'update_critique' : 'save_critique',
        critique_text: critiqueText,
        critique_type: critiqueType,
        editor_notes: editorNotes,
        news_id: newsId ?? null,
        article_url: articleUrl ?? null,
        article_title: articleTitle ?? null,
      };
      if (editingId) body.parent_id = editingId;

      const res = await fetch('/api/admin/critique-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (data.success) {
        setMessage({
          type: 'success',
          text: `크리틱 저장 완료! (임베딩 ${data.embedded_chunks ?? 0}개 생성)`,
        });
        resetForm();
        fetchCritiques();
      } else {
        setMessage({ type: 'error', text: data.error || '저장 실패' });
      }
    } catch (e) {
      setMessage({ type: 'error', text: '서버 오류: ' + (e as Error).message });
    } finally {
      setSaving(false);
    }
  };

  const resetForm = () => {
    setCritiqueText('');
    setCritiqueType('general');
    setAnalysisQuality('');
    setNarrationTone('');
    setMissingPerspective('');
    setImprovementSuggestion('');
    setEditingId(null);
  };

  const fetchVersions = async (critiqueId: string) => {
    try {
      const res = await fetch(`/api/admin/critique-api.php?action=critique_versions&critique_id=${critiqueId}`);
      const data = await res.json();
      if (data.success) setVersions(data.versions || []);
    } catch {
      /* ignore */
    }
  };

  const startEdit = (c: Critique) => {
    setEditingId(c.id);
    setCritiqueText(c.critique_text);
    setCritiqueType(c.critique_type);
    const notes = typeof c.editor_notes === 'object' ? c.editor_notes : {};
    setAnalysisQuality(notes.analysis_quality || '');
    setNarrationTone(notes.narration_tone || '');
    setMissingPerspective(notes.missing_perspective || '');
    setImprovementSuggestion(notes.improvement_suggestion || '');
  };

  return (
    <div className="space-y-6">
      {/* Critique Form */}
      <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
        <h3 className="text-lg font-semibold text-white">
          {editingId ? '크리틱 수정 (새 버전 생성)' : '새 크리틱 작성'}
        </h3>

        {articleTitle && (
          <p className="text-sm text-slate-400">
            기사: <span className="text-slate-300">{articleTitle}</span>
          </p>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm text-slate-300 mb-1">크리틱 유형</label>
            <select
              value={critiqueType}
              onChange={(e) => setCritiqueType(e.target.value)}
              className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"
            >
              {CRITIQUE_TYPES.map((t) => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label className="block text-sm text-slate-300 mb-1">크리틱 본문</label>
          <textarea
            value={critiqueText}
            onChange={(e) => setCritiqueText(e.target.value)}
            rows={4}
            placeholder="기사 분석에 대한 편집자 피드백을 작성하세요..."
            className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm resize-y"
          />
        </div>

        {/* Structured Notes */}
        <details className="group">
          <summary className="cursor-pointer text-sm text-cyan-400 hover:text-cyan-300">
            구조화된 노트 (선택사항)
          </summary>
          <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs text-slate-400 mb-1">분석 품질 평가</label>
              <textarea
                value={analysisQuality}
                onChange={(e) => setAnalysisQuality(e.target.value)}
                rows={2}
                placeholder="분석의 깊이와 정확성에 대한 평가..."
                className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs resize-y"
              />
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">내레이션 톤</label>
              <textarea
                value={narrationTone}
                onChange={(e) => setNarrationTone(e.target.value)}
                rows={2}
                placeholder="내레이션의 어조와 전달력 평가..."
                className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs resize-y"
              />
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">누락된 관점</label>
              <textarea
                value={missingPerspective}
                onChange={(e) => setMissingPerspective(e.target.value)}
                rows={2}
                placeholder="분석에서 빠진 시각이나 맥락..."
                className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs resize-y"
              />
            </div>
            <div>
              <label className="block text-xs text-slate-400 mb-1">개선 제안</label>
              <textarea
                value={improvementSuggestion}
                onChange={(e) => setImprovementSuggestion(e.target.value)}
                rows={2}
                placeholder="향후 분석 시 적용할 개선사항..."
                className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs resize-y"
              />
            </div>
          </div>
        </details>

        <div className="flex items-center gap-3">
          <button
            onClick={handleSave}
            disabled={saving || !critiqueText.trim()}
            className="px-6 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium text-sm"
          >
            {saving ? '저장 중...' : editingId ? '새 버전으로 저장' : '크리틱 저장'}
          </button>
          {editingId && (
            <button onClick={resetForm} className="px-4 py-2 text-sm text-slate-400 hover:text-white">
              취소
            </button>
          )}
        </div>

        {message && (
          <p className={`text-sm ${message.type === 'success' ? 'text-emerald-400' : 'text-rose-400'}`}>
            {message.text}
          </p>
        )}
      </div>

      {/* Critique List */}
      <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
        <h3 className="text-lg font-semibold text-white">
          저장된 크리틱 ({critiques.length})
        </h3>

        {loading ? (
          <p className="text-slate-400 text-sm">불러오는 중...</p>
        ) : critiques.length === 0 ? (
          <p className="text-slate-500 text-sm">저장된 크리틱이 없습니다.</p>
        ) : (
          <div className="space-y-3">
            {critiques.map((c) => (
              <div
                key={c.id}
                className="p-4 rounded-xl bg-slate-900/50 border border-slate-700/50 space-y-2"
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="px-2 py-0.5 rounded-full text-xs bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">
                      {CRITIQUE_TYPES.find((t) => t.value === c.critique_type)?.label || c.critique_type}
                    </span>
                    <span className="text-xs text-slate-500">v{c.version}</span>
                    <span className="text-xs text-slate-500">
                      {new Date(c.created_at).toLocaleDateString('ko-KR')}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => { setSelectedCritique(c); fetchVersions(c.id); }}
                      className="text-xs text-slate-400 hover:text-cyan-400"
                    >
                      버전
                    </button>
                    <button
                      onClick={() => startEdit(c)}
                      className="text-xs text-slate-400 hover:text-emerald-400"
                    >
                      수정
                    </button>
                  </div>
                </div>
                <p className="text-sm text-slate-300 whitespace-pre-wrap">{c.critique_text}</p>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Version History Modal */}
      {selectedCritique && versions.length > 0 && (
        <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-white">버전 히스토리</h3>
            <button
              onClick={() => { setSelectedCritique(null); setVersions([]); }}
              className="text-sm text-slate-400 hover:text-white"
            >
              닫기
            </button>
          </div>
          <div className="space-y-3">
            {versions.map((v) => (
              <div key={v.id} className="p-3 rounded-lg bg-slate-900/50 border border-slate-700/30">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-xs font-medium text-cyan-300">v{v.version}</span>
                  <span className="text-xs text-slate-500">
                    {new Date(v.created_at).toLocaleString('ko-KR')}
                  </span>
                </div>
                <p className="text-xs text-slate-400 whitespace-pre-wrap">{v.critique_text}</p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default CritiqueEditor;
