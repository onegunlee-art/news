import { useState, useEffect, useCallback } from 'react';
import MaterialIcon from '../Common/MaterialIcon';
import { adminFetch } from '../../services/api';

interface JudgementPattern {
  id: string;
  pattern_hash: string;
  category: string;
  description: string;
  ai_approach: string | null;
  editor_correction: string | null;
  frequency: number;
  weight: number;
  is_active: boolean;
  last_seen_at: string;
  created_at: string;
}

interface DashboardStats {
  total_records: number;
  total_patterns: number;
  active_patterns: number;
  top_categories: { category: string; count: number }[];
}

interface PlaygroundResult {
  ai_generated: {
    news_title: string;
    narration: string;
    why_important: string;
    content_summary: string;
    key_points: string[];
  };
  applied_patterns: JudgementPattern[];
  rag_context: {
    critiques: number;
    analyses: number;
    knowledge: number;
  };
  comparison?: {
    match_rate: number;
    differences: {
      field: string;
      ai_value: string;
      human_value: string;
      similarity: number;
    }[];
  };
}

const AGILab: React.FC = () => {
  const [activeSection, setActiveSection] = useState<'dashboard' | 'playground'>('dashboard');
  
  // Dashboard state
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [patterns, setPatterns] = useState<JudgementPattern[]>([]);
  const [patternsLoading, setPatternsLoading] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  
  // Playground state
  const [playgroundUrl, setPlaygroundUrl] = useState('');
  const [playgroundLoading, setPlaygroundLoading] = useState(false);
  const [playgroundResult, setPlaygroundResult] = useState<PlaygroundResult | null>(null);
  const [playgroundError, setPlaygroundError] = useState<string | null>(null);
  const [compareWithPublished, setCompareWithPublished] = useState(true);

  // Load dashboard data
  const loadDashboard = useCallback(async () => {
    setPatternsLoading(true);
    try {
      const res = await adminFetch('/api/admin/judgement-dashboard.php');
      const data = await res.json();
      if (data.success) {
        setStats(data.stats);
        setPatterns(data.patterns || []);
      }
    } catch (e) {
      console.error('Failed to load judgement dashboard:', e);
    } finally {
      setPatternsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activeSection === 'dashboard') {
      loadDashboard();
    }
  }, [activeSection, loadDashboard]);

  // Run playground test
  const runPlayground = async () => {
    if (!playgroundUrl.trim()) return;
    
    setPlaygroundLoading(true);
    setPlaygroundError(null);
    setPlaygroundResult(null);
    
    try {
      const res = await adminFetch('/api/admin/agi-playground.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          url: playgroundUrl.trim(),
          compare_with_published: compareWithPublished,
        }),
      });
      const data = await res.json();
      if (data.success) {
        setPlaygroundResult(data.result);
      } else {
        setPlaygroundError(data.error || '생성 실패');
      }
    } catch (e) {
      setPlaygroundError('서버 오류: ' + (e as Error).message);
    } finally {
      setPlaygroundLoading(false);
    }
  };

  const filteredPatterns = selectedCategory === 'all' 
    ? patterns 
    : patterns.filter(p => p.category === selectedCategory);

  const uniqueCategories = [...new Set(patterns.map(p => p.category))];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-white mb-2">AGI Lab</h2>
        <p className="text-slate-400">
          Judgment RAG 학습 현황 및 성능 테스트 실험실
        </p>
      </div>

      {/* Section Tabs */}
      <div className="flex gap-2">
        <button
          onClick={() => setActiveSection('dashboard')}
          className={`px-4 py-2 rounded-lg font-medium transition-all ${
            activeSection === 'dashboard'
              ? 'bg-cyan-500 text-white'
              : 'bg-slate-800 text-slate-400 hover:bg-slate-700'
          }`}
        >
          <MaterialIcon name="analytics" className="mr-2" />
          Judgment 대시보드
        </button>
        <button
          onClick={() => setActiveSection('playground')}
          className={`px-4 py-2 rounded-lg font-medium transition-all ${
            activeSection === 'playground'
              ? 'bg-emerald-500 text-white'
              : 'bg-slate-800 text-slate-400 hover:bg-slate-700'
          }`}
        >
          <MaterialIcon name="science" className="mr-2" />
          Playground
        </button>
      </div>

      {/* Dashboard Section */}
      {activeSection === 'dashboard' && (
        <div className="space-y-6">
          {/* Stats Cards */}
          {stats && (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
                <div className="text-slate-400 text-sm mb-1">총 학습 기록</div>
                <div className="text-2xl font-bold text-white">{stats.total_records}</div>
              </div>
              <div className="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
                <div className="text-slate-400 text-sm mb-1">발견된 패턴</div>
                <div className="text-2xl font-bold text-cyan-400">{stats.total_patterns}</div>
              </div>
              <div className="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
                <div className="text-slate-400 text-sm mb-1">활성 패턴</div>
                <div className="text-2xl font-bold text-emerald-400">{stats.active_patterns}</div>
              </div>
              <div className="bg-slate-800/50 rounded-xl p-4 border border-slate-700/50">
                <div className="text-slate-400 text-sm mb-1">권장 샘플 수</div>
                <div className="text-2xl font-bold text-amber-400">
                  {stats.total_records >= 200 ? '✓ 충분' : `${stats.total_records}/200`}
                </div>
              </div>
            </div>
          )}

          {/* Category Filter */}
          <div className="flex items-center gap-3">
            <span className="text-slate-400 text-sm">카테고리:</span>
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="px-3 py-2 rounded-lg bg-slate-900/50 border border-slate-700 text-white text-sm"
            >
              <option value="all">전체 ({patterns.length})</option>
              {uniqueCategories.map(cat => (
                <option key={cat} value={cat}>
                  {cat} ({patterns.filter(p => p.category === cat).length})
                </option>
              ))}
            </select>
            <button
              onClick={loadDashboard}
              disabled={patternsLoading}
              className="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm"
            >
              {patternsLoading ? '로딩...' : '새로고침'}
            </button>
          </div>

          {/* Patterns Table */}
          <div className="bg-slate-800/50 rounded-xl border border-slate-700/50 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="bg-slate-900/50 text-left">
                    <th className="px-4 py-3 text-slate-400 text-sm font-medium">카테고리</th>
                    <th className="px-4 py-3 text-slate-400 text-sm font-medium">패턴 설명</th>
                    <th className="px-4 py-3 text-slate-400 text-sm font-medium">AI 경향</th>
                    <th className="px-4 py-3 text-slate-400 text-sm font-medium">편집장 수정</th>
                    <th className="px-4 py-3 text-slate-400 text-sm font-medium text-center">빈도</th>
                    <th className="px-4 py-3 text-slate-400 text-sm font-medium text-center">가중치</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredPatterns.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                        {patternsLoading ? '로딩 중...' : '학습된 패턴이 없습니다. 글을 더 게시해주세요.'}
                      </td>
                    </tr>
                  ) : (
                    filteredPatterns.map((pattern) => (
                      <tr key={pattern.id} className="border-t border-slate-700/50 hover:bg-slate-700/20">
                        <td className="px-4 py-3">
                          <span className="px-2 py-1 rounded-full text-xs bg-cyan-500/20 text-cyan-300">
                            {pattern.category}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-slate-300 text-sm max-w-xs">
                          <div className="line-clamp-2">{pattern.description}</div>
                        </td>
                        <td className="px-4 py-3 text-slate-400 text-sm max-w-[200px]">
                          <div className="line-clamp-2">{pattern.ai_approach || '-'}</div>
                        </td>
                        <td className="px-4 py-3 text-emerald-400 text-sm max-w-[200px]">
                          <div className="line-clamp-2">{pattern.editor_correction || '-'}</div>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <span className="text-white font-medium">{pattern.frequency}</span>
                        </td>
                        <td className="px-4 py-3 text-center">
                          <div className="flex items-center justify-center gap-2">
                            <div className="w-16 h-2 bg-slate-700 rounded-full overflow-hidden">
                              <div 
                                className="h-full bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-full"
                                style={{ width: `${pattern.weight * 100}%` }}
                              />
                            </div>
                            <span className="text-slate-400 text-xs">{(pattern.weight * 100).toFixed(0)}%</span>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Info Box */}
          <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4">
            <div className="flex items-start gap-3">
              <MaterialIcon name="info" className="text-amber-400 mt-0.5" />
              <div className="text-sm text-amber-200">
                <p className="font-medium mb-1">패턴 가중치 기준</p>
                <p className="text-amber-300/80">
                  가중치 = 빈도 / 50 (최대 100%). 200개 이상의 학습 데이터가 쌓이면 
                  높은 가중치의 패턴을 AI 생성에 적용할 수 있습니다. 현재는 관찰 모드입니다.
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Playground Section */}
      {activeSection === 'playground' && (
        <div className="space-y-6">
          {/* Input Area */}
          <div className="bg-slate-800/50 rounded-xl p-6 border border-slate-700/50 space-y-4">
            <h3 className="text-lg font-semibold text-white">테스트 URL 입력</h3>
            <p className="text-slate-400 text-sm">
              URL을 입력하면 Judgment RAG가 적용된 AI가 글을 생성하고, 
              실제 게시된 글과 비교하여 정합률을 측정합니다.
            </p>
            
            <div className="flex flex-col sm:flex-row gap-3">
              <input
                type="text"
                value={playgroundUrl}
                onChange={(e) => setPlaygroundUrl(e.target.value)}
                placeholder="https://example.com/news/article..."
                className="flex-1 px-4 py-3 rounded-lg bg-slate-900/50 border border-slate-700 text-white placeholder-slate-500"
                onKeyDown={(e) => e.key === 'Enter' && runPlayground()}
              />
              <button
                onClick={runPlayground}
                disabled={playgroundLoading || !playgroundUrl.trim()}
                className="px-6 py-3 rounded-lg bg-gradient-to-r from-emerald-500 to-cyan-500 hover:from-emerald-400 hover:to-cyan-400 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium"
              >
                {playgroundLoading ? (
                  <span className="flex items-center gap-2">
                    <span className="animate-spin">⏳</span> 생성 중...
                  </span>
                ) : (
                  <span className="flex items-center gap-2">
                    <MaterialIcon name="play_arrow" /> 테스트 실행
                  </span>
                )}
              </button>
            </div>

            <label className="flex items-center gap-2 text-sm text-slate-400">
              <input
                type="checkbox"
                checked={compareWithPublished}
                onChange={(e) => setCompareWithPublished(e.target.checked)}
                className="rounded"
              />
              게시된 글과 정합률 비교 (해당 URL이 이미 게시된 경우)
            </label>
          </div>

          {/* Error */}
          {playgroundError && (
            <div className="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-400">
              {playgroundError}
            </div>
          )}

          {/* Results */}
          {playgroundResult && (
            <div className="space-y-6">
              {/* Match Rate */}
              {playgroundResult.comparison && (
                <div className="bg-slate-800/50 rounded-xl p-6 border border-slate-700/50">
                  <h3 className="text-lg font-semibold text-white mb-4">정합률 분석</h3>
                  
                  <div className="flex items-center gap-6 mb-6">
                    <div className="relative w-32 h-32">
                      <svg className="w-32 h-32 transform -rotate-90">
                        <circle
                          cx="64" cy="64" r="56"
                          className="stroke-slate-700"
                          strokeWidth="12"
                          fill="none"
                        />
                        <circle
                          cx="64" cy="64" r="56"
                          className={`${
                            playgroundResult.comparison.match_rate >= 80 
                              ? 'stroke-emerald-500' 
                              : playgroundResult.comparison.match_rate >= 60 
                                ? 'stroke-amber-500' 
                                : 'stroke-red-500'
                          }`}
                          strokeWidth="12"
                          fill="none"
                          strokeDasharray={`${playgroundResult.comparison.match_rate * 3.52} 352`}
                          strokeLinecap="round"
                        />
                      </svg>
                      <div className="absolute inset-0 flex items-center justify-center">
                        <span className="text-3xl font-bold text-white">
                          {playgroundResult.comparison.match_rate}%
                        </span>
                      </div>
                    </div>
                    
                    <div className="flex-1">
                      <div className="text-slate-400 text-sm mb-2">필드별 유사도</div>
                      <div className="space-y-2">
                        {playgroundResult.comparison.differences.map((diff, i) => (
                          <div key={i} className="flex items-center gap-3">
                            <span className="text-slate-300 text-sm w-24">{diff.field}</span>
                            <div className="flex-1 h-2 bg-slate-700 rounded-full overflow-hidden">
                              <div 
                                className={`h-full rounded-full ${
                                  diff.similarity >= 80 
                                    ? 'bg-emerald-500' 
                                    : diff.similarity >= 60 
                                      ? 'bg-amber-500' 
                                      : 'bg-red-500'
                                }`}
                                style={{ width: `${diff.similarity}%` }}
                              />
                            </div>
                            <span className="text-slate-400 text-xs w-10">{diff.similarity}%</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* Applied Patterns */}
              <div className="bg-slate-800/50 rounded-xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4">
                  적용된 Judgment 패턴 ({playgroundResult.applied_patterns.length})
                </h3>
                {playgroundResult.applied_patterns.length === 0 ? (
                  <p className="text-slate-500">적용된 패턴이 없습니다. (학습 데이터 부족)</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {playgroundResult.applied_patterns.map((p, i) => (
                      <span 
                        key={i}
                        className="px-3 py-1 rounded-full text-sm bg-cyan-500/20 text-cyan-300 border border-cyan-500/30"
                        title={p.description}
                      >
                        {p.category}: {p.description.substring(0, 30)}...
                      </span>
                    ))}
                  </div>
                )}
              </div>

              {/* RAG Context */}
              <div className="bg-slate-800/50 rounded-xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4">RAG 컨텍스트</h3>
                <div className="flex gap-4">
                  <div className="px-4 py-2 rounded-lg bg-slate-700/50">
                    <span className="text-slate-400 text-sm">크리틱:</span>
                    <span className="text-white font-medium ml-2">{playgroundResult.rag_context.critiques}개</span>
                  </div>
                  <div className="px-4 py-2 rounded-lg bg-slate-700/50">
                    <span className="text-slate-400 text-sm">분석:</span>
                    <span className="text-white font-medium ml-2">{playgroundResult.rag_context.analyses}개</span>
                  </div>
                  <div className="px-4 py-2 rounded-lg bg-slate-700/50">
                    <span className="text-slate-400 text-sm">지식:</span>
                    <span className="text-white font-medium ml-2">{playgroundResult.rag_context.knowledge}개</span>
                  </div>
                </div>
              </div>

              {/* Generated Content */}
              <div className="bg-slate-800/50 rounded-xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4">AI 생성 결과</h3>
                <div className="space-y-4">
                  <div>
                    <div className="text-slate-400 text-sm mb-1">제목</div>
                    <div className="text-white">{playgroundResult.ai_generated.news_title}</div>
                  </div>
                  <div>
                    <div className="text-slate-400 text-sm mb-1">내레이션</div>
                    <div className="text-slate-300 whitespace-pre-wrap">{playgroundResult.ai_generated.narration}</div>
                  </div>
                  <div>
                    <div className="text-slate-400 text-sm mb-1">왜 중요한가</div>
                    <div className="text-slate-300 whitespace-pre-wrap">{playgroundResult.ai_generated.why_important}</div>
                  </div>
                  <div>
                    <div className="text-slate-400 text-sm mb-1">본문 요약</div>
                    <div className="text-slate-300 whitespace-pre-wrap">{playgroundResult.ai_generated.content_summary}</div>
                  </div>
                  {playgroundResult.ai_generated.key_points?.length > 0 && (
                    <div>
                      <div className="text-slate-400 text-sm mb-1">주요 포인트</div>
                      <ul className="list-disc list-inside text-slate-300 space-y-1">
                        {playgroundResult.ai_generated.key_points.map((kp, i) => (
                          <li key={i}>{kp}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Empty State */}
          {!playgroundResult && !playgroundLoading && !playgroundError && (
            <div className="bg-slate-800/30 rounded-xl p-12 border border-dashed border-slate-700 text-center">
              <MaterialIcon name="science" className="text-6xl text-slate-600 mb-4" />
              <p className="text-slate-500">
                URL을 입력하고 테스트를 실행하면<br />
                Judgment RAG가 적용된 AI 생성 결과를 확인할 수 있습니다.
              </p>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default AGILab;
