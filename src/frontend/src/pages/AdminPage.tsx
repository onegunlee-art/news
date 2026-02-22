import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { formatSourceDisplayName, buildEditorialLine } from '../utils/formatSource'
import { extractTitleFromUrl } from '../utils/extractTitleFromUrl';
import {
  ChartBarIcon,
  UsersIcon,
  NewspaperIcon,
  CogIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  PencilSquareIcon,
  TrashIcon,
  XMarkIcon,
  SparklesIcon,
  PlayIcon,
  DocumentTextIcon,
  SpeakerWaveIcon,
  AcademicCapIcon,
  BookOpenIcon,
  ChatBubbleLeftRightIcon,
  ArrowPathIcon,
  HandThumbUpIcon,
  StarIcon,
  CurrencyDollarIcon,
  ClipboardDocumentIcon,
  UserCircleIcon,
  ArrowsPointingOutIcon,
  DocumentDuplicateIcon,
} from '@heroicons/react/24/outline';
import RichTextEditor from '../components/Common/RichTextEditor';
import { normalizeEditorHtml } from '../utils/sanitizeHtml';
import AdminDraftPreviewEdit, { type DraftArticle } from '../components/Admin/AdminDraftPreviewEdit';
import AIWorkspace from '../components/AIWorkspace/AIWorkspace';
import type { ArticleContext } from '../components/AIWorkspace/AIWorkspace';
import CritiqueEditor from '../components/CritiqueEditor/CritiqueEditor';
import RAGTester from '../components/RAGTester/RAGTester';
import { api, adminSettingsApi, adminTtsApi, ttsApi } from '../services/api';
import { PRIVACY_POLICY_CONTENT } from '../components/Common/PrivacyPolicyContent';
import WelcomePopup from '../components/Common/WelcomePopup';

/** Listen과 동일한 구조로 TTS params 구성 (캐시 공유) */
function buildTtsParamsForListen(params: {
  title: string
  narration: string
  whyImportant?: string
  publishedAt?: string | null | undefined | unknown
  updatedAt?: string | null | undefined | unknown
  createdAt?: string | null | undefined | unknown
  source?: string | null | undefined | unknown
  originalSource?: string | null | undefined | unknown
  sourceUrl?: string | null | undefined
  originalTitle?: string | null | undefined
}): { title: string; meta: string; narration: string; critique_part: string } {
  const titleRaw = (params.title || '제목 없음').trim()
  const title = titleRaw
  const originalTitle = (params.originalTitle && String(params.originalTitle).trim()) || (params.sourceUrl && extractTitleFromUrl(params.sourceUrl)) || '원문'
  const toDateStr = (v: unknown) => (typeof v === 'string' ? v : null)
  const ref = toDateStr(params.publishedAt) || toDateStr(params.updatedAt) || toDateStr(params.createdAt)
  const dateStr = ref
    ? `${new Date(ref).getFullYear()}년 ${new Date(ref).getMonth() + 1}월 ${new Date(ref).getDate()}일`
    : ''
  const rawSourceVal = (params.originalSource != null ? String(params.originalSource).trim() : '') || (params.source === 'Admin' ? 'The Gist' : (params.source != null ? String(params.source) : 'The Gist'))
  const rawSource: string = typeof rawSourceVal === 'string' ? rawSourceVal : 'The Gist'
  const sourceDisplay = formatSourceDisplayName(rawSource) || 'The Gist'
  const meta = buildEditorialLine({ dateStr, sourceDisplay, originalTitle })
  const narration = (params.narration || '').trim()
  const critiquePart = params.whyImportant ? `The Gist's Critique. ${params.whyImportant.trim()}` : ''
  return { title, meta, narration, critique_part: critiquePart }
}

/** Google TTS 한국어 보이스 목록 (Admin에서 선택용) */
const GOOGLE_TTS_VOICES = [
  { value: 'ko-KR-Standard-A', label: 'Standard A (여성)' },
  { value: 'ko-KR-Standard-B', label: 'Standard B (남성)' },
  { value: 'ko-KR-Standard-C', label: 'Standard C (여성)' },
  { value: 'ko-KR-Standard-D', label: 'Standard D (남성)' },
  { value: 'ko-KR-Wavenet-A', label: 'Wavenet A (여성)' },
  { value: 'ko-KR-Wavenet-B', label: 'Wavenet B (남성)' },
  { value: 'ko-KR-Wavenet-C', label: 'Wavenet C (여성)' },
  { value: 'ko-KR-Wavenet-D', label: 'Wavenet D (남성)' },
  { value: 'ko-KR-Neural2-A', label: 'Neural2 A (여성)' },
  { value: 'ko-KR-Neural2-B', label: 'Neural2 B (남성)' },
  { value: 'ko-KR-Neural2-C', label: 'Neural2 C (여성)' },
  { value: 'ko-KR-Neural2-D', label: 'Neural2 D (남성)' },
];

interface NewsArticle {
  id?: number;
  category: string;
  title: string;
  subtitle?: string;
  description?: string;
  content: string;
  why_important?: string;
  narration?: string;
  future_prediction?: string;
  source?: string;
  source_url?: string;
  url?: string;
  original_source?: string;
  status?: 'draft' | 'published';
  author?: string;
  published_at?: string;
  image_url?: string;
  created_at?: string;
}

const categories = [
  { id: 'diplomacy', name: 'Foreign Affairs', color: 'from-blue-500 to-cyan-500' },
  { id: 'economy', name: 'Economy', color: 'from-emerald-500 to-green-500' },
  { id: 'entertainment', name: 'Entertainment', color: 'from-orange-500 to-red-500' },
];

// Feedback / Revision 인터페이스
interface FeedbackEntry {
  id: string;
  article_id?: number;
  article_url?: string;
  revision_number: number;
  admin_comment?: string;
  score?: number;
  gpt_analysis?: Record<string, unknown>;
  gpt_revision?: Record<string, unknown>;
  revision_prompt?: string;
  status: string;
  parent_id?: string;
  created_at: string;
}

// Knowledge Library 인터페이스
interface KnowledgeItem {
  id: string;
  category: string;
  framework_name: string;
  title: string;
  content: string;
  keywords?: string[];
  source?: string;
  created_at: string;
}

const KNOWLEDGE_CATEGORIES = [
  { value: 'ir_theory', label: '국제정치 이론' },
  { value: 'geopolitics', label: '지정학' },
  { value: 'economics', label: '경제/금융' },
  { value: 'history', label: '역사/유사사례' },
  { value: 'security', label: '안보/군사' },
  { value: 'other', label: '기타' },
];

// AI 분석 결과 인터페이스
interface AIAnalysisResult {
  news_title?: string;
  original_title?: string;
  translation_summary?: string;
  key_points?: string[];
  content_summary?: string;
  narration?: string;
  critical_analysis?: {
    why_important?: string;
  };
  audio_url?: string;
}

// 텍스트 정제 함수 - 복사/붙여넣기 시 문제 될 수 있는 문자 변환
const sanitizeText = (text: string): string => {
  return text
    // 스마트 따옴표 → 일반 따옴표
    .replace(/[\u201C\u201D\u201E\u201F\u2033\u2036]/g, '"')  // 큰따옴표
    .replace(/[\u2018\u2019\u201A\u201B\u2032\u2035]/g, "'")  // 작은따옴표
    // 특수 대시 → 일반 하이픈
    .replace(/[\u2013\u2014\u2015\u2212]/g, '-')              // em-dash, en-dash
    // 특수 공백 → 일반 공백
    .replace(/[\u00A0\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200A\u202F\u205F\u3000]/g, ' ')
    // Zero-width 문자 제거
    .replace(/[\u200B\u200C\u200D\uFEFF]/g, '')
    // 특수 줄바꿈 문자 정규화
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    // 연속된 공백 정리 (줄바꿈 제외)
    .replace(/[^\S\n]+/g, ' ')
    // 각 줄의 앞뒤 공백 제거
    .split('\n')
    .map(line => line.trim())
    .join('\n')
    // 연속된 빈 줄 → 하나로
    .replace(/\n{3,}/g, '\n\n');
};

type UserRow = { id: number; nickname: string; email: string | null; status: string; last_login_at: string | null; created_at: string };
type UserDetail = UserRow & { usage?: { analyses_count: number; bookmarks_count: number; search_count: number } };

const UsersManagementSection: React.FC<{
  onUserDetail: (u: UserDetail | null) => void;
}> = ({ onUserDetail }) => {
  const [users, setUsers] = useState<UserRow[]>([]);
  const [pagination, setPagination] = useState({ page: 1, per_page: 20, total: 0, total_pages: 0 });
  const [loading, setLoading] = useState(true);
  const loadUsers = useCallback(async (page = 1) => {
    setLoading(true);
    try {
      const res = await api.get<{ success: boolean; data: { items: UserRow[]; pagination: typeof pagination } }>(`/admin/users?page=${page}&per_page=20`);
      if (res.data.success && res.data.data) {
        setUsers(res.data.data.items);
        setPagination(res.data.data.pagination);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  }, []);
  useEffect(() => {
    loadUsers(1);
  }, [loadUsers]);
  return (
    <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
      {loading ? (
        <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-10 w-10 border-2 border-cyan-500 border-t-transparent" /></div>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-left">
              <thead>
                <tr className="border-b border-slate-600">
                  <th className="py-3 px-4 text-slate-400 font-medium">ID</th>
                  <th className="py-3 px-4 text-slate-400 font-medium">닉네임</th>
                  <th className="py-3 px-4 text-slate-400 font-medium">이메일</th>
                  <th className="py-3 px-4 text-slate-400 font-medium">상태</th>
                  <th className="py-3 px-4 text-slate-400 font-medium">최근 로그인</th>
                  <th className="py-3 px-4 text-slate-400 font-medium">가입일</th>
                  <th className="py-3 px-4 text-slate-400 font-medium"></th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id} className="border-b border-slate-700/50">
                    <td className="py-3 px-4 text-white">{u.id}</td>
                    <td className="py-3 px-4 text-white">{u.nickname}</td>
                    <td className="py-3 px-4 text-slate-300">{u.email || '-'}</td>
                    <td className="py-3 px-4"><span className={`px-2 py-1 rounded text-xs ${u.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' : u.status === 'banned' ? 'bg-red-500/20 text-red-400' : 'bg-slate-500/20 text-slate-400'}`}>{u.status}</span></td>
                    <td className="py-3 px-4 text-slate-400 text-sm">{u.last_login_at ? new Date(u.last_login_at).toLocaleString('ko-KR') : '-'}</td>
                    <td className="py-3 px-4 text-slate-400 text-sm">{u.created_at ? new Date(u.created_at).toLocaleString('ko-KR') : '-'}</td>
                    <td className="py-3 px-4">
                      <button
                        type="button"
                        onClick={async () => {
                          try {
                            const res = await api.get<{ success: boolean; data: UserDetail }>(`/admin/users/${u.id}`);
                            if (res.data.success && res.data.data) onUserDetail(res.data.data);
                          } catch (e) {
                            console.error(e);
                          }
                        }}
                        className="text-cyan-400 hover:text-cyan-300 text-sm"
                      >
                        상세
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {pagination.total_pages > 1 && (
            <div className="flex justify-center gap-2 mt-4">
              <button
                type="button"
                disabled={pagination.page <= 1}
                onClick={() => loadUsers(pagination.page - 1)}
                className="px-3 py-1 rounded bg-slate-700 disabled:opacity-50 text-slate-300"
              >
                이전
              </button>
              <span className="py-1 text-slate-400">{pagination.page} / {pagination.total_pages}</span>
              <button
                type="button"
                disabled={pagination.page >= pagination.total_pages}
                onClick={() => loadUsers(pagination.page + 1)}
                className="px-3 py-1 rounded bg-slate-700 disabled:opacity-50 text-slate-300"
              >
                다음
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
};

const AdminPage: React.FC = () => {
  const navigate = useNavigate();
  const { } = useAuthStore(); // 권한 체크용 (추후 활성화)
  const [activeTab, setActiveTab] = useState<'dashboard' | 'users' | 'news' | 'drafts' | 'ai' | 'workspace' | 'persona' | 'knowledge' | 'usage' | 'settings'>('dashboard');

  // feedback useEffect deps에서 참조되므로 컴포넌트 최상단에 선언
  const [articleUrl, setArticleUrl] = useState('');
  const [editingNewsId, setEditingNewsId] = useState<number | null>(null);

  // AI 분석 상태
  const [aiUrl, setAiUrl] = useState('');
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [aiResult, setAiResult] = useState<AIAnalysisResult | null>(null);
  const [aiError, setAiError] = useState<string | null>(null);
  // aiMockMode 제거됨 - The Gist AI 시스템으로 통합
  const [learningTexts, setLearningTexts] = useState('');
  const [isLearning, setIsLearning] = useState(false);
  const [learnedPatterns, setLearnedPatterns] = useState<any>(null);
  const [isSpeaking, setIsSpeaking] = useState(false);
  const [speechRate, setSpeechRate] = useState(1.2); // 기본: 약간 빠름

  // 피드백 / 재분석 상태
  const [feedbackComment, setFeedbackComment] = useState('');
  const [feedbackScore, setFeedbackScore] = useState(5);
  const [feedbackHistory, setFeedbackHistory] = useState<FeedbackEntry[]>([]);
  const [isSavingFeedback, setIsSavingFeedback] = useState(false);
  const [isRequestingRevision, setIsRequestingRevision] = useState(false);
  const [isApprovingAnalysis, setIsApprovingAnalysis] = useState(false);
  const [feedbackMessage, setFeedbackMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Knowledge Library 상태
  const [knowledgeItems, setKnowledgeItems] = useState<KnowledgeItem[]>([]);
  const [knowledgeLoading, setKnowledgeLoading] = useState(false);
  const [knowledgeCategory, setKnowledgeCategory] = useState('all');
  const [knFormCategory, setKnFormCategory] = useState('ir_theory');
  const [knFormFramework, setKnFormFramework] = useState('');
  const [knFormTitle, setKnFormTitle] = useState('');
  const [knFormContent, setKnFormContent] = useState('');
  const [knFormKeywords, setKnFormKeywords] = useState('');
  const [knFormSource, setKnFormSource] = useState('');
  const [isAddingKnowledge, setIsAddingKnowledge] = useState(false);
  const [knowledgeMessage, setKnowledgeMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // API 과금 대시보드
  // Persona Playground & Tester
  const [personaMessages, setPersonaMessages] = useState<{ role: 'user' | 'assistant'; content: string }[]>([]);
  const [personaInput, setPersonaInput] = useState('');
  const [personaLoading, setPersonaLoading] = useState(false);
  const [personaExtracting, setPersonaExtracting] = useState(false);
  const [personaName, setPersonaName] = useState('The Gist 수석 에디터 v1');
  const [personaList, setPersonaList] = useState<{ id: string; name: string; system_prompt: string; is_active: boolean }[]>([]);
  const [activePersona, setActivePersona] = useState<{ id: string; name: string; system_prompt: string } | null>(null);
  const [personaTestUrl, setPersonaTestUrl] = useState('');
  const [personaTestArticleId, setPersonaTestArticleId] = useState('');
  const [personaTestLoading, setPersonaTestLoading] = useState(false);
  const [personaTestResult, setPersonaTestResult] = useState<{ analysis_result?: Record<string, unknown>; checklist?: Record<string, unknown> } | null>(null);
  const personaChatEndRef = useRef<HTMLDivElement>(null);

  // API 과금 대시보드
  const [usageData, setUsageData] = useState<{
    providers?: Record<string, { configured: boolean; dashboard_url?: string; usage?: unknown; usage_error?: string }>;
    self_tracked?: { has_table: boolean; today: Array<Record<string, unknown>>; month: Array<Record<string, unknown>>; by_provider: Record<string, Record<string, unknown>> };
  } | null>(null);
  const [usageLoading, setUsageLoading] = useState(false);

  // Admin 설정 (TTS Voice)
  const [ttsVoice, setTtsVoice] = useState<string>('ko-KR-Standard-A');
  const [settingsLoading, setSettingsLoading] = useState(false);
  const [settingsSaving, setSettingsSaving] = useState(false);
  const [settingsError, setSettingsError] = useState<string | null>(null);
  const [settingsSuccess, setSettingsSuccess] = useState<string | null>(null);

  // 뉴스 관리 상태
  const [selectedCategory, setSelectedCategory] = useState<string>('diplomacy');
  const [newsTitle, setNewsTitle] = useState('');
  const [newsSubtitle, setNewsSubtitle] = useState('');
  const [newsContent, setNewsContent] = useState('');
  const [newsWhyImportant, setNewsWhyImportant] = useState('');
  const [newsNarration, setNewsNarration] = useState('');
  const [isContentFullscreen, setIsContentFullscreen] = useState(false);
  const [newsList, setNewsList] = useState<NewsArticle[]>([]);
  const [isSaving, setIsSaving] = useState(false);
  const [saveMessage, setSaveMessage] = useState<{ type: 'success' | 'error' | 'info'; text: string } | null>(null);
  const [isLoadingNews, setIsLoadingNews] = useState(false);
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null);
  const [articleSource, setArticleSource] = useState('');
  const [articleAuthor, setArticleAuthor] = useState('');
  const [articlePublishedAt, setArticlePublishedAt] = useState('');
  const [articleImageUrl, setArticleImageUrl] = useState('');
  const [articleSummary, setArticleSummary] = useState('');
  const [articleOriginalTitle, setArticleOriginalTitle] = useState('');
  const [showExtractedInfo, setShowExtractedInfo] = useState(false);
  const [isRegeneratingThumbnail, setIsRegeneratingThumbnail] = useState(false);
  const [isRegeneratingDalle, setIsRegeneratingDalle] = useState(false);
  const [dallePrompt, setDallePrompt] = useState('');
  const [isRegeneratingTts, setIsRegeneratingTts] = useState(false);
  const [regeneratedTtsUrl, setRegeneratedTtsUrl] = useState<string | null>(null);
  const [draftPreviewId, setDraftPreviewId] = useState<number | null>(null);
  const [draftArticle, setDraftArticle] = useState<DraftArticle | null>(null);
  const [draftList, setDraftList] = useState<NewsArticle[]>([]);
  const [isLoadingDraft, setIsLoadingDraft] = useState(false);

  // API 과금 탭 활성 시 데이터 로드
  useEffect(() => {
    if (activeTab !== 'usage') return;
    setUsageLoading(true);
    fetch('/api/admin/usage-dashboard.php')
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setUsageData(d);
      })
      .catch(() => setUsageData(null))
      .finally(() => setUsageLoading(false));
  }, [activeTab]);

  // 설정 탭 활성 시 설정 로드
  useEffect(() => {
    if (activeTab !== 'settings') return;
    setSettingsLoading(true);
    setSettingsError(null);
    adminSettingsApi
      .getSettings()
      .then((res) => {
        if (res.data?.success && res.data?.data) {
          const v = res.data.data.tts_voice;
          if (v && GOOGLE_TTS_VOICES.some((o) => o.value === v)) setTtsVoice(v);
        }
      })
      .catch((err) => setSettingsError(err.response?.data?.message || '설정을 불러올 수 없습니다.'))
      .finally(() => setSettingsLoading(false));
  }, [activeTab]);

  // Persona 탭 활성 시 목록 로드
  useEffect(() => {
    if (activeTab !== 'persona') return;
    fetch('/api/admin/persona-api.php?action=list')
      .then((r) => r.json())
      .then((d) => {
        if (d.success && Array.isArray(d.personas)) setPersonaList(d.personas);
      })
      .catch(() => setPersonaList([]));
    fetch('/api/admin/persona-api.php?action=active')
      .then((r) => r.json())
      .then((d) => {
        if (d.success && d.persona) setActivePersona(d.persona);
        else setActivePersona(null);
      })
      .catch(() => setActivePersona(null));
  }, [activeTab]);

  useEffect(() => {
    personaChatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [personaMessages]);

  const saveTtsVoice = async () => {
    setSettingsSaving(true);
    setSettingsError(null);
    setSettingsSuccess(null);
    let totalGen = 0;
    try {
      await adminSettingsApi.updateSettings({ tts_voice: ttsVoice });
      setSettingsError(null);
      setSettingsSuccess('보이스 저장됨. TTS 배치 재생성 중…');
      let offset = 0;
      const limit = 1;
      let totalSkip = 0;
      let total = 0;
      let hasMore = true;
      while (hasMore) {
        const r = await adminTtsApi.regenerateAll({ offset, limit });
        const d = r.data?.data;
        if (!r.data?.success || !d) break;
        totalGen += d.generated;
        totalSkip += d.skipped;
        total = d.total;
        hasMore = d.has_more ?? false;
        offset += limit;
        if (hasMore) setSettingsSuccess(`TTS 재생성 중... ${Math.min(offset, total)}/${total}`);
      }
      setSettingsSuccess(`보이스 저장 완료. TTS ${totalGen}건 재생성, ${totalSkip}건 스킵 (총 ${total}건)`);
      setTimeout(() => setSettingsSuccess(null), 6000);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      if (totalGen > 0) {
        setSettingsSuccess(`보이스 저장됨. TTS 일부 재생성됨 (${totalGen}건). 나머지는 TTS 전체 갱신 버튼으로 실행하세요.`);
      } else {
        setSettingsError(e.response?.data?.message || '저장에 실패했습니다.');
      }
    } finally {
      setSettingsSaving(false);
    }
  };

  // ── Feedback API helpers ────────────────────────────
  const loadFeedbackHistory = useCallback(async (articleUrl?: string, articleId?: number) => {
    if (!articleUrl && !articleId) return;
    try {
      const params = articleId ? `article_id=${articleId}` : `article_url=${encodeURIComponent(articleUrl!)}`;
      const res = await fetch(`/api/admin/feedback-api.php?action=get_history&${params}`);
      const data = await res.json();
      if (data.success) {
        setFeedbackHistory(data.history ?? []);
      }
    } catch (e) {
      console.error('Failed to load feedback history:', e);
    }
  }, []);

  const saveFeedback = async () => {
    if (!feedbackComment.trim()) {
      setFeedbackMessage({ type: 'error', text: '코멘트를 입력해주세요.' });
      return;
    }
    setIsSavingFeedback(true);
    setFeedbackMessage(null);
    try {
      const res = await fetch('/api/admin/feedback-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save_feedback',
          article_url: aiUrl || articleUrl || undefined,
          article_id: editingNewsId ?? undefined,
          admin_comment: feedbackComment.trim(),
          score: feedbackScore,
          gpt_analysis: aiResult ?? {},
        }),
      });
      const data = await res.json();
      if (data.success) {
        setFeedbackMessage({ type: 'success', text: '피드백이 저장되었습니다.' });
        setFeedbackComment('');
        await loadFeedbackHistory(aiUrl || articleUrl, editingNewsId ?? undefined);
      } else {
        setFeedbackMessage({ type: 'error', text: data.error || '저장 실패' });
      }
    } catch (e) {
      setFeedbackMessage({ type: 'error', text: '요청 실패: ' + (e as Error).message });
    } finally {
      setIsSavingFeedback(false);
      setTimeout(() => setFeedbackMessage(null), 4000);
    }
  };

  const requestRevision = async () => {
    if (!feedbackComment.trim()) {
      setFeedbackMessage({ type: 'error', text: '피드백 코멘트를 입력해주세요.' });
      return;
    }
    setIsRequestingRevision(true);
    setFeedbackMessage(null);
    try {
      const res = await fetch('/api/admin/feedback-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'request_revision',
          article_url: aiUrl || articleUrl || undefined,
          article_id: editingNewsId ?? undefined,
          admin_comment: feedbackComment.trim(),
          score: feedbackScore,
          original_analysis: aiResult ?? {},
        }),
      });
      const data = await res.json();
      if (data.success && data.revision) {
        // 재분석 결과를 현재 분석에 반영
        setAiResult(data.revision as AIAnalysisResult);
        setFeedbackMessage({ type: 'success', text: 'GPT 재분석 완료! 결과가 업데이트되었습니다.' });
        setFeedbackComment('');
        await loadFeedbackHistory(aiUrl || articleUrl, editingNewsId ?? undefined);
      } else {
        setFeedbackMessage({ type: 'error', text: data.error || '재분석 실패' });
      }
    } catch (e) {
      setFeedbackMessage({ type: 'error', text: '재분석 요청 실패: ' + (e as Error).message });
    } finally {
      setIsRequestingRevision(false);
      setTimeout(() => setFeedbackMessage(null), 5000);
    }
  };

  const approveAnalysis = async () => {
    const lastFeedback = feedbackHistory[feedbackHistory.length - 1];
    if (!lastFeedback) {
      setFeedbackMessage({ type: 'error', text: '피드백을 먼저 저장해주세요.' });
      return;
    }
    setIsApprovingAnalysis(true);
    setFeedbackMessage(null);
    try {
      const res = await fetch('/api/admin/feedback-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'approve',
          feedback_id: lastFeedback.id,
          article_id: editingNewsId ?? undefined,
          article_url: aiUrl || articleUrl || undefined,
          final_analysis: aiResult ?? {},
        }),
      });
      const data = await res.json();
      if (data.success) {
        setFeedbackMessage({ type: 'success', text: `승인 완료! (${data.embedded_chunks ?? 0}개 임베딩 저장)` });
        await loadFeedbackHistory(aiUrl || articleUrl, editingNewsId ?? undefined);
      } else {
        setFeedbackMessage({ type: 'error', text: data.error || '승인 실패' });
      }
    } catch (e) {
      setFeedbackMessage({ type: 'error', text: '승인 요청 실패: ' + (e as Error).message });
    } finally {
      setIsApprovingAnalysis(false);
      setTimeout(() => setFeedbackMessage(null), 5000);
    }
  };

  // ── Knowledge Library API helpers ─────────────────────
  const loadKnowledgeLibrary = useCallback(async (category = 'all') => {
    setKnowledgeLoading(true);
    try {
      const catParam = category !== 'all' ? `&category=${encodeURIComponent(category)}` : '';
      const res = await fetch(`/api/admin/knowledge-api.php?action=list${catParam}`);
      const data = await res.json();
      if (data.success) {
        setKnowledgeItems(data.items ?? []);
      }
    } catch (e) {
      console.error('Failed to load knowledge library:', e);
    } finally {
      setKnowledgeLoading(false);
    }
  }, []);

  const addKnowledgeFramework = async () => {
    if (!knFormTitle.trim() || !knFormContent.trim() || !knFormFramework.trim()) {
      setKnowledgeMessage({ type: 'error', text: '제목, 프레임워크명, 내용은 필수입니다.' });
      return;
    }
    setIsAddingKnowledge(true);
    setKnowledgeMessage(null);
    try {
      const keywords = knFormKeywords.split(',').map(k => k.trim()).filter(Boolean);
      const res = await fetch('/api/admin/knowledge-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'add_framework',
          category: knFormCategory,
          framework_name: knFormFramework.trim(),
          title: knFormTitle.trim(),
          content: knFormContent.trim(),
          keywords,
          source: knFormSource.trim() || null,
        }),
      });
      const data = await res.json();
      if (data.success) {
        setKnowledgeMessage({ type: 'success', text: `프레임워크가 추가되었습니다. (임베딩: ${data.has_embedding ? '완료' : '미완료'})` });
        setKnFormFramework('');
        setKnFormTitle('');
        setKnFormContent('');
        setKnFormKeywords('');
        setKnFormSource('');
        await loadKnowledgeLibrary(knowledgeCategory);
      } else {
        setKnowledgeMessage({ type: 'error', text: data.error || '추가 실패' });
      }
    } catch (e) {
      setKnowledgeMessage({ type: 'error', text: '요청 실패: ' + (e as Error).message });
    } finally {
      setIsAddingKnowledge(false);
      setTimeout(() => setKnowledgeMessage(null), 4000);
    }
  };

  const deleteKnowledgeItem = async (id: string) => {
    if (!confirm('이 프레임워크를 삭제하시겠습니까?')) return;
    try {
      const res = await fetch('/api/admin/knowledge-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id }),
      });
      const data = await res.json();
      if (data.success) {
        setKnowledgeItems(prev => prev.filter(item => item.id !== id));
        setKnowledgeMessage({ type: 'success', text: '삭제되었습니다.' });
      } else {
        setKnowledgeMessage({ type: 'error', text: data.error || '삭제 실패' });
      }
    } catch (e) {
      setKnowledgeMessage({ type: 'error', text: '삭제 요청 실패' });
    }
    setTimeout(() => setKnowledgeMessage(null), 3000);
  };

  // knowledge 탭 활성화 시 목록 로드
  useEffect(() => {
    if (activeTab === 'knowledge') {
      loadKnowledgeLibrary(knowledgeCategory);
    }
  }, [activeTab, knowledgeCategory, loadKnowledgeLibrary]);

  // AI 분석 결과가 나오면 피드백 히스토리 로드
  useEffect(() => {
    if (aiResult && (aiUrl || articleUrl)) {
      loadFeedbackHistory(aiUrl || articleUrl, editingNewsId ?? undefined);
    }
  }, [aiResult, aiUrl, articleUrl, editingNewsId, loadFeedbackHistory]);

  // Admin 오디오 엘리먼트 ref
  const adminAudioRef = useRef<HTMLAudioElement | null>(null);

  // Google TTS 기반 음성 읽기 함수
  const speakText = async (text: string) => {
    // 기존 재생 중지
    if (adminAudioRef.current) {
      adminAudioRef.current.pause();
      adminAudioRef.current.src = '';
    }
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();

    if (!text.trim()) return;
    setIsSpeaking(true);

    try {
      const res = await ttsApi.generate(text);
      const url = res.data?.data?.url;
      if (!url) {
        setIsSpeaking(false);
        alert('Google TTS 오디오 생성에 실패했습니다.');
        return;
      }
      if (!adminAudioRef.current) {
        adminAudioRef.current = new Audio();
      }
      const audio = adminAudioRef.current;
      audio.src = url;
      audio.onended = () => setIsSpeaking(false);
      audio.onerror = () => setIsSpeaking(false);
      await audio.play();
    } catch {
      setIsSpeaking(false);
      alert('Google TTS 요청에 실패했습니다. 서버 설정을 확인해주세요.');
    }
  };

  // 전체 분석 결과 읽기
  const speakFullAnalysis = () => {
    if (!aiResult) return;
    
    // narration이 있으면 그대로 사용 (GPT가 이미 앵커 톤으로 작성)
    if (aiResult.narration) {
      speakText(aiResult.narration);
      return;
    }
    
    // fallback: 기존 방식
    let fullText = '';
    
    if (aiResult.translation_summary) {
      fullText += '요약입니다. ' + aiResult.translation_summary + ' ';
    }
    
    if (aiResult.key_points && aiResult.key_points.length > 0) {
      fullText += '주요 포인트입니다. ';
      aiResult.key_points.forEach((point, i) => {
        fullText += `${i + 1}번. ${point}. `;
      });
    }
    
    if (aiResult.critical_analysis?.why_important) {
      fullText += 'The Gist\'s Critique. ' + aiResult.critical_analysis.why_important + ' ';
    }
    
    speakText(fullText);
  };

  // 음성 중지
  const stopSpeaking = () => {
    if (adminAudioRef.current) {
      adminAudioRef.current.pause();
      adminAudioRef.current.src = '';
    }
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    setIsSpeaking(false);
  };
  
  const [loading, setLoading] = useState(true);

  // 대시보드: 회원 목록, 개인정보처리방침
  const [dashboardUsers, setDashboardUsers] = useState<{ id: number; nickname: string; email: string | null; status: string; last_login_at: string | null; created_at: string }[]>([]);
  const [selectedUserDetail, setSelectedUserDetail] = useState<{ id: number; nickname: string; email: string | null; status: string; last_login_at: string | null; created_at: string; usage?: { analyses_count: number; bookmarks_count: number; search_count: number } } | null>(null);
  const [privacyContent, setPrivacyContent] = useState('');
  const [, setTermsContent] = useState('');
  const [privacySaving, setPrivacySaving] = useState(false);
  const [privacyMessage, setPrivacyMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [welcomeMessage, setWelcomeMessage] = useState('');
  const [welcomeTitleTemplate, setWelcomeTitleTemplate] = useState('{name}님');
  const [welcomeSaving, setWelcomeSaving] = useState(false);
  const [welcomeSaveMsg, setWelcomeSaveMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [showWelcomePreview, setShowWelcomePreview] = useState(false);

  useEffect(() => {
    // 권한 체크 (실제 환경에서는 API 호출)
    // if (!isAuthenticated || user?.role !== 'admin') {
    //   navigate('/');
    //   return;
    // }
    loadDashboardData();
  }, []);

  // 기존 뉴스 목록 로드
  const loadNewsList = useCallback(async () => {
    setIsLoadingNews(true);
    try {
      const response = await fetch(`/api/admin/news.php?category=${selectedCategory}`);
      const data = await response.json();
      if (data.success && data.data?.items) {
        setNewsList(data.data.items);
      }
    } catch (error) {
      console.error('Failed to load news:', error);
    } finally {
      setIsLoadingNews(false);
    }
  }, [selectedCategory]);

  // 뉴스 탭이 활성화되거나 카테고리가 변경될 때 뉴스 목록 로드
  useEffect(() => {
    if (activeTab === 'news') {
      loadNewsList();
    }
  }, [activeTab, loadNewsList]);

  // 임시 저장 목록 로드
  const loadDraftList = useCallback(async () => {
    setIsLoadingDraft(true);
    try {
      const response = await fetch('/api/admin/news.php?status_filter=draft&per_page=50');
      const data = await response.json();
      if (data.success && data.data?.items) {
        setDraftList(data.data.items);
      } else {
        setDraftList([]);
      }
    } catch {
      setDraftList([]);
    } finally {
      setIsLoadingDraft(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === 'drafts') {
      loadDraftList();
    }
  }, [activeTab, loadDraftList]);

  // draft 단건 조회 (미리보기/편집용)
  const fetchDraftArticle = useCallback(async (id: number) => {
    setIsLoadingDraft(true);
    try {
      const response = await fetch(`/api/admin/news.php?id=${id}`);
      const data = await response.json();
      if (data.success && data.data?.article) {
        setDraftArticle(data.data.article as DraftArticle);
        setDraftPreviewId(id);
      } else {
        setSaveMessage({ type: 'error', text: '기사를 불러올 수 없습니다.' });
      }
    } catch (e) {
      setSaveMessage({ type: 'error', text: '기사 조회 실패: ' + (e as Error).message });
    } finally {
      setIsLoadingDraft(false);
    }
  }, []);

  // 뉴스 수정 시작
  const handleEditNews = (news: NewsArticle) => {
    setEditingNewsId(news.id || null);
    setNewsTitle(news.title);
    setNewsSubtitle(news.subtitle || '');
    setNewsContent(news.content);
    setNewsWhyImportant(news.why_important || '');
    setNewsNarration(news.narration || '');
    // 추가 메타데이터 (출처, 작성자, 작성일, 사진) - source_url 우선, 없으면 url (API가 url 반환)
    const urlToShow = (news.source_url && news.source_url.trim()) || (news.url && news.url.trim()) || '';
    setArticleUrl(urlToShow);
    setArticleSource(news.original_source || news.source || '');
    setArticleOriginalTitle((news as { original_title?: string }).original_title || '');
    setArticleAuthor(news.author || '');
    setArticlePublishedAt(news.published_at || '');
    setArticleImageUrl(news.image_url || '');
    setArticleSummary(news.description || '');
    setShowExtractedInfo(true); // 메타데이터 섹션 펼치기
    // 스크롤을 폼 위치로 이동
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // 수정 취소
  const handleCancelEdit = () => {
    setEditingNewsId(null);
    setNewsTitle('');
    setNewsSubtitle('');
    setNewsContent('');
    setNewsWhyImportant('');
    setNewsNarration('');
    setArticleUrl('');
    setRegeneratedTtsUrl(null);
    // 메타데이터 필드 초기화
    setArticleSource('');
    setArticleAuthor('');
    setArticlePublishedAt('');
    setArticleImageUrl('');
    setArticleSummary('');
    setArticleOriginalTitle('');
    setShowExtractedInfo(false);
    setSaveMessage(null);
  };

  // 뉴스 삭제
  const handleDeleteNews = async (id: number) => {
    try {
      const response = await fetch(`/api/admin/news.php?id=${id}`, {
        method: 'DELETE',
      });
      const data = await response.json();
      if (data.success) {
        setSaveMessage({ type: 'success', text: '뉴스가 삭제되었습니다.' });
        setNewsList(prev => prev.filter(n => n.id !== id));
      } else {
        throw new Error(data.message);
      }
    } catch (error) {
      setSaveMessage({ type: 'error', text: '삭제 실패: ' + (error as Error).message });
    } finally {
      setDeleteConfirmId(null);
      setTimeout(() => setSaveMessage(null), 3000);
    }
  };

  const loadDashboardData = async () => {
    setLoading(true);
    try {
      const [usersRes, settingsRes] = await Promise.all([
        api.get<{ success: boolean; data: { items: { id: number; nickname: string; email: string | null; status: string; last_login_at: string | null; created_at: string }[] } }>('/admin/users?per_page=5&page=1'),
        adminSettingsApi.getSettings(),
      ]);
      if (usersRes.data.success && usersRes.data.data?.items)
        setDashboardUsers(usersRes.data.data.items);
      const s = settingsRes.data?.data;
      if (s) {
        setPrivacyContent((s.privacy_policy && s.privacy_policy.trim()) ? s.privacy_policy : PRIVACY_POLICY_CONTENT);
        setTermsContent(s.terms_of_service ?? '');
        setWelcomeMessage(s.welcome_popup_message ?? 'The Gist 가입을 감사드립니다.');
        setWelcomeTitleTemplate(s.welcome_popup_title ?? '{name}님');
      }
    } catch (e) {
      console.error('대시보드 로드 실패:', e);
    } finally {
      setLoading(false);
    }
  };

  // Build article context for AI Workspace from current editing state
  const workspaceArticleContext: ArticleContext | null = (articleUrl || newsTitle) ? {
    title: newsTitle || undefined,
    url: articleUrl || undefined,
    summary: articleSummary || undefined,
    narration: newsNarration || undefined,
    analysis: aiResult ? JSON.stringify({
      news_title: aiResult.news_title,
      key_points: aiResult.key_points,
      content_summary: aiResult.content_summary,
    }) : undefined,
  } : null;

  const tabs = [
    { id: 'dashboard', name: '대시보드', icon: ChartBarIcon },
    { id: 'users', name: '사용자 관리', icon: UsersIcon },
    { id: 'news', name: '뉴스 관리', icon: NewspaperIcon },
    { id: 'drafts', name: '임시 저장', icon: DocumentDuplicateIcon },
    { id: 'ai', name: 'AI 분석', icon: SparklesIcon },
    { id: 'workspace', name: 'AI Workspace', icon: AcademicCapIcon },
    { id: 'persona', name: '페르소나', icon: UserCircleIcon },
    { id: 'knowledge', name: '이론 라이브러리', icon: BookOpenIcon },
    { id: 'usage', name: 'API 과금', icon: CurrencyDollarIcon },
    { id: 'settings', name: '설정', icon: CogIcon },
  ] as const;

  return (
    <>
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
      <div className="flex">
        {/* Sidebar */}
        <div className="w-64 min-h-screen bg-slate-900/80 backdrop-blur-xl border-r border-slate-700/50 p-6">
          <div className="mb-8">
            <h1 className="text-2xl font-bold bg-gradient-to-r from-cyan-400 to-emerald-400 bg-clip-text text-transparent">
              Admin Panel
            </h1>
            <p className="text-slate-500 text-sm mt-1">The Gist</p>
          </div>

          <nav className="space-y-2">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${
                  activeTab === tab.id
                    ? 'bg-gradient-to-r from-cyan-500/20 to-emerald-500/20 text-cyan-400 border border-cyan-500/30'
                    : 'text-slate-400 hover:bg-slate-800/50 hover:text-white'
                }`}
              >
                <tab.icon className="w-5 h-5" />
                {tab.name}
              </button>
            ))}
          </nav>

          <div className="mt-auto pt-8 border-t border-slate-700/50 mt-8">
            <button
              onClick={() => navigate('/')}
              className="w-full text-slate-400 hover:text-white text-sm py-2"
            >
              ← 홈으로 돌아가기
            </button>
          </div>
        </div>

        {/* Main Content */}
        <div className="flex-1 p-8">
          {/* 임시 저장 미리보기/편집 화면 */}
          {draftPreviewId && draftArticle && (
            <AdminDraftPreviewEdit
              news={draftArticle}
              onUpdate={async (updates) => {
                const cleanContent = updates.content != null ? normalizeEditorHtml(updates.content) : (draftArticle.content ?? null);
                const cleanNarration = updates.narration != null ? normalizeEditorHtml(updates.narration) : (draftArticle.narration ?? null);
                const cleanWhyImportant = updates.why_important != null ? normalizeEditorHtml(updates.why_important) : (draftArticle.why_important ?? null);
                const response = await fetch('/api/admin/news.php', {
                  method: 'PUT',
                  headers: { 'Content-Type': 'application/json; charset=utf-8' },
                  body: JSON.stringify({
                    id: draftPreviewId,
                    category: draftArticle.category,
                    title: draftArticle.title,
                    subtitle: draftArticle.subtitle ?? null,
                    content: cleanContent ?? draftArticle.content ?? '',
                    why_important: cleanWhyImportant,
                    narration: cleanNarration,
                    source_url: draftArticle.source_url ?? draftArticle.url ?? null,
                    source: draftArticle.original_source ?? draftArticle.source ?? null,
                    original_title: draftArticle.original_title ?? null,
                    author: draftArticle.author ?? null,
                    published_at: draftArticle.published_at ?? null,
                    image_url: draftArticle.image_url ?? null,
                    status: 'draft',
                  }),
                });
                const data = await response.json();
                if (!data.success) throw new Error(data.message || '저장 실패');
                setDraftArticle((prev) => prev ? { ...prev, ...updates } : null);
              }}
              onPublish={async () => {
                const response = await fetch('/api/admin/news.php', {
                  method: 'PUT',
                  headers: { 'Content-Type': 'application/json; charset=utf-8' },
                  body: JSON.stringify({
                    id: draftPreviewId,
                    category: draftArticle.category,
                    title: draftArticle.title,
                    subtitle: draftArticle.subtitle ?? null,
                    content: draftArticle.content ?? '',
                    why_important: draftArticle.why_important ?? null,
                    narration: draftArticle.narration ?? null,
                    source_url: draftArticle.source_url ?? draftArticle.url ?? null,
                    source: draftArticle.original_source ?? draftArticle.source ?? null,
                    original_title: draftArticle.original_title ?? null,
                    author: draftArticle.author ?? null,
                    published_at: draftArticle.published_at ?? null,
                    image_url: draftArticle.image_url ?? null,
                    status: 'published',
                  }),
                });
                const data = await response.json();
                if (!data.success) throw new Error(data.message || '게시 실패');
                setDraftPreviewId(null);
                setDraftArticle(null);
                await loadNewsList();
                await loadDraftList();
                setSaveMessage({ type: 'success', text: '게시되었습니다!' });
                setTimeout(() => setSaveMessage(null), 3000);
              }}
              onBack={() => {
                setDraftPreviewId(null);
                setDraftArticle(null);
              }}
            />
          )}

          {!draftPreviewId && activeTab === 'dashboard' && (
            <div className="space-y-8">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">대시보드</h2>
                <p className="text-slate-400">시스템 현황을 한눈에 확인하세요</p>
              </div>

              {loading ? (
                <div className="flex items-center justify-center py-20">
                  <div className="animate-spin rounded-full h-12 w-12 border-4 border-cyan-500 border-t-transparent"></div>
                </div>
              ) : (
                <>
                  {/* 회원 관리 (최근 5명) */}
                  <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="text-lg font-semibold text-white">최근 가입 회원</h3>
                      <button
                        type="button"
                        onClick={() => setActiveTab('users')}
                        className="text-cyan-400 hover:text-cyan-300 text-sm"
                      >
                        전체 보기 →
                      </button>
                    </div>
                    <div className="space-y-2">
                      {dashboardUsers.length === 0 ? (
                        <p className="text-slate-500 text-sm">등록된 회원이 없습니다.</p>
                      ) : (
                        dashboardUsers.map((u) => (
                          <div
                            key={u.id}
                            className="flex items-center justify-between py-2 px-3 bg-slate-900/50 rounded-lg hover:bg-slate-700/30"
                          >
                            <div>
                              <span className="text-white font-medium">{u.nickname}</span>
                              {u.email && <span className="text-slate-500 text-sm ml-2">({u.email})</span>}
                            </div>
                            <button
                              type="button"
                              onClick={async () => {
                                try {
                                  const res = await api.get<{ success: boolean; data: typeof selectedUserDetail }>(`/admin/users/${u.id}`);
                                  if (res.data.success && res.data.data) setSelectedUserDetail(res.data.data);
                                } catch (e) {
                                  console.error(e);
                                }
                              }}
                              className="text-cyan-400 hover:text-cyan-300 text-sm"
                            >
                              상세
                            </button>
                          </div>
                        ))
                      )}
                    </div>
                  </div>

                  {/* 개인정보처리방침 수정 */}
                  <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                    <h3 className="text-lg font-semibold text-white mb-4">개인정보처리방침 수정</h3>
                    <textarea
                      value={privacyContent}
                      onChange={(e) => setPrivacyContent(e.target.value)}
                      placeholder="개인정보처리방침 내용을 입력하세요. 공개 페이지 /privacy 에 표시됩니다."
                      className="w-full h-32 px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-slate-200 placeholder-slate-500 focus:ring-2 focus:ring-cyan-500 focus:border-transparent resize-none"
                    />
                    <div className="flex items-center gap-3 mt-3">
                      <button
                        type="button"
                        disabled={privacySaving}
                        onClick={async () => {
                          setPrivacySaving(true);
                          setPrivacyMessage(null);
                          try {
                            await adminSettingsApi.updateSettings({ privacy_policy: privacyContent });
                            setPrivacyMessage({ type: 'success', text: '저장되었습니다.' });
                          } catch (e) {
                            setPrivacyMessage({ type: 'error', text: '저장에 실패했습니다.' });
                          } finally {
                            setPrivacySaving(false);
                          }
                        }}
                        className="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 text-white rounded-lg text-sm"
                      >
                        {privacySaving ? '저장 중...' : '저장'}
                      </button>
                      {privacyMessage && (
                        <span className={privacyMessage.type === 'success' ? 'text-emerald-400' : 'text-red-400'}>{privacyMessage.text}</span>
                      )}
                    </div>
                  </div>

                  {/* 가입 환영 팝업 설정 */}
                  <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                    <h3 className="text-lg font-semibold text-white mb-4">가입 환영 팝업 설정</h3>
                    <p className="text-slate-400 text-sm mb-4">가입 완료 시 표시되는 환영 팝업 메시지를 설정합니다.</p>
                    <div className="space-y-4">
                      <div>
                        <label className="block text-slate-400 text-sm mb-1">환영 메시지</label>
                        <input
                          type="text"
                          value={welcomeMessage}
                          onChange={(e) => setWelcomeMessage(e.target.value)}
                          placeholder="The Gist 가입을 감사드립니다."
                          className="w-full px-4 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-slate-200"
                        />
                      </div>
                      <div>
                        <label className="block text-slate-400 text-sm mb-1">이름 표시 형식 (&#123;name&#125; = 닉네임)</label>
                        <input
                          type="text"
                          value={welcomeTitleTemplate}
                          onChange={(e) => setWelcomeTitleTemplate(e.target.value)}
                          placeholder="{name}님"
                          className="w-full px-4 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-slate-200"
                        />
                      </div>
                    </div>
                    <div className="flex items-center gap-3 mt-4">
                      <button
                        type="button"
                        onClick={() => setShowWelcomePreview(true)}
                        className="px-4 py-2 bg-slate-600 hover:bg-slate-500 text-white rounded-lg text-sm"
                      >
                        미리보기
                      </button>
                      <button
                        type="button"
                        disabled={welcomeSaving}
                        onClick={async () => {
                          setWelcomeSaving(true);
                          setWelcomeSaveMsg(null);
                          try {
                            await adminSettingsApi.updateSettings({
                              welcome_popup_message: welcomeMessage,
                              welcome_popup_title: welcomeTitleTemplate,
                            });
                            setWelcomeSaveMsg({ type: 'success', text: '저장되었습니다.' });
                          } catch (e) {
                            setWelcomeSaveMsg({ type: 'error', text: '저장에 실패했습니다.' });
                          } finally {
                            setWelcomeSaving(false);
                          }
                        }}
                        className="px-4 py-2 bg-orange-600 hover:bg-orange-500 disabled:opacity-50 text-white rounded-lg text-sm"
                      >
                        {welcomeSaving ? '저장 중...' : '저장'}
                      </button>
                      {welcomeSaveMsg && (
                        <span className={welcomeSaveMsg.type === 'success' ? 'text-emerald-400' : 'text-red-400'}>{welcomeSaveMsg.text}</span>
                      )}
                    </div>
                  </div>

                  {/* 환영 팝업 미리보기 */}
                  {showWelcomePreview && (
                    <WelcomePopup
                      isOpen={true}
                      onClose={() => setShowWelcomePreview(false)}
                      userName="홍길동"
                      welcomeMessage={welcomeMessage || 'The Gist 가입을 감사드립니다.'}
                    />
                  )}
                </>
              )}
            </div>
          )}

          {!draftPreviewId && activeTab === 'users' && (
            <div className="space-y-6">
              <h2 className="text-2xl font-bold text-white">사용자 관리</h2>
              <UsersManagementSection onUserDetail={setSelectedUserDetail} />
            </div>
          )}

          {!draftPreviewId && activeTab === 'news' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between flex-wrap gap-3">
                <div>
                  <h2 className="text-2xl font-bold text-white mb-2">뉴스 관리</h2>
                  <p className="text-slate-400">카테고리별 뉴스를 작성하고 관리하세요</p>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                  <button
                    type="button"
                    onClick={async () => {
                      if (!confirm('기존 캐시를 무시하고 모든 기사 TTS를 강제 재생성합니다. (배치 처리로 504 방지) 계속할까요?')) return;
                      let offset = 0;
                      const limit = 1;
                      let totalGen = 0;
                      let totalSkip = 0;
                      let total = 0;
                      let hasMore = true;
                      try {
                        setSaveMessage({ type: 'success', text: 'TTS 강제 재생성 중...' });
                        while (hasMore) {
                          const res = await adminTtsApi.regenerateAll({ force: true, offset, limit });
                          const data = res.data?.data;
                          if (!res.data?.success || !data) {
                            setSaveMessage({ type: 'error', text: (res.data as { message?: string })?.message || 'TTS 갱신 실패' });
                            return;
                          }
                          totalGen += data.generated;
                          totalSkip += data.skipped;
                          total = data.total;
                          hasMore = data.has_more ?? false;
                          offset += limit;
                          if (hasMore) {
                            setSaveMessage({ type: 'success', text: `TTS 재생성 중... ${Math.min(offset, total)}/${total} (${totalGen}건 생성)` });
                          }
                        }
                        setSaveMessage({ type: 'success', text: `TTS 완료: ${totalGen}건 생성, ${totalSkip}건 스킵 (총 ${total}건)` });
                      } catch (e: unknown) {
                        const err = e as { response?: { data?: { message?: string } }; message?: string };
                        const msg = err.response?.data?.message || err.message || 'TTS 갱신 요청 실패';
                        setSaveMessage({ type: 'error', text: totalGen > 0 ? `${msg} (진행: ${totalGen}건 생성됨)` : msg });
                      }
                    }}
                    className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-cyan-500 to-teal-500 text-white rounded-xl hover:opacity-90 transition text-sm"
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    </svg>
                    TTS 전체 갱신
                  </button>
                  <button
                    type="button"
                    onClick={async () => {
                      if (!confirm('전체 기사의 썸네일을 새 규칙(인물/국가/API)으로 일괄 갱신합니다. 계속할까요?')) return;
                      try {
                        setSaveMessage({ type: 'success', text: '썸네일 갱신 중...' });
                        const res = await fetch('/api/admin/update-images.php?action=update_all');
                        const data = await res.json();
                        if (data.success) {
                          setSaveMessage({ type: 'success', text: `${data.total}개 기사 썸네일이 갱신되었습니다.` });
                          loadNewsList();
                        } else {
                          setSaveMessage({ type: 'error', text: data.error || '갱신 실패' });
                        }
                      } catch (e) {
                        setSaveMessage({ type: 'error', text: '썸네일 갱신 요청 실패' });
                      }
                    }}
                    className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-xl hover:opacity-90 transition text-sm"
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    썸네일 전체 갱신
                  </button>
                </div>
              </div>

              {/* 카테고리 선택 네비게이션 */}
              <div className="flex gap-3 flex-wrap">
                {categories.map((cat) => (
                  <button
                    key={cat.id}
                    onClick={() => setSelectedCategory(cat.id)}
                    className={`px-5 py-3 rounded-xl font-medium transition-all ${
                      selectedCategory === cat.id
                        ? `bg-gradient-to-r ${cat.color} text-white shadow-lg`
                        : 'bg-slate-800/50 text-slate-300 hover:bg-slate-700/50 border border-slate-700/50'
                    }`}
                  >
                    {cat.name}
                  </button>
                ))}
              </div>

              {/* 뉴스 작성/수정 폼 */}
              <div className={`bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border ${editingNewsId ? 'border-amber-500/50' : 'border-slate-700/50'}`}>
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-white">
                    {editingNewsId 
                      ? `뉴스 수정 중 (ID: ${editingNewsId})`
                      : `${categories.find(c => c.id === selectedCategory)?.name} 뉴스 작성`
                    }
                  </h3>
                  {editingNewsId && (
                    <button
                      onClick={handleCancelEdit}
                      className="flex items-center gap-1 px-3 py-1.5 text-sm text-amber-400 hover:text-amber-300 border border-amber-500/30 rounded-lg hover:bg-amber-500/10 transition"
                    >
                      <XMarkIcon className="w-4 h-4" />
                      수정 취소
                    </button>
                  )}
                </div>

                <div className="space-y-4">
                  {/* URL → GPT 분석 (버튼 1개) */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">기사 URL</label>
                    <div className="flex gap-2 flex-wrap items-center">
                      <input
                        type="url"
                        value={articleUrl}
                        onChange={(e) => {
                          const url = e.target.value;
                          setArticleUrl(url);
                          const lower = url.toLowerCase();
                          if (lower.includes('financial') || lower.includes('economy')) {
                            setSelectedCategory('economy');
                          } else if (lower.includes('foreign')) {
                            setSelectedCategory('diplomacy');
                          }
                        }}
                        placeholder="https://example.com/article..."
                        className="flex-1 min-w-[200px] bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                      />
                      <button
                        onClick={async () => {
                          if (!articleUrl.trim()) {
                            setSaveMessage({ type: 'error', text: 'URL을 입력해주세요.' });
                            return;
                          }
                          setIsAnalyzing(true);
                          setSaveMessage({ type: 'info', text: 'API 연결 확인 중...' });
                          setAiError(null);
                          setAiResult(null);
                          let pollAborted = false;
                          const apiUrl = '/api/admin/ai-analyze.php';
                          try {
                            // 0단계: 사전 연결 확인 (Failed to fetch 원인 진단) - 가벼운 ping 사용
                            const preflightCtrl = new AbortController();
                            const preflightTimer = setTimeout(() => preflightCtrl.abort(), 10000);
                            try {
                              const preRes = await fetch(`${apiUrl}?action=ping`, { signal: preflightCtrl.signal });
                              clearTimeout(preflightTimer);
                              if (!preRes.ok) {
                                setSaveMessage({ type: 'error', text: `API 연결됐으나 HTTP ${preRes.status}. 서버 오류일 수 있습니다.` });
                                setIsAnalyzing(false);
                                return;
                              }
                            } catch (preErr: unknown) {
                              clearTimeout(preflightTimer);
                              const pe = preErr as Error & { name?: string };
                              if (pe.name === 'AbortError') {
                                setSaveMessage({ type: 'error', text: 'API 연결 시간 초과(10초). 서버·호스팅 타임아웃 또는 배포 경로를 확인해주세요.' });
                              } else {
                                const pingUrl = `${typeof window !== 'undefined' ? window.location.origin : ''}${apiUrl}?action=ping`;
                              setSaveMessage({ type: 'error', text: `API 연결 실패: ${pe.message || String(preErr)}. 배포 경로(${apiUrl}), 서버, 네트워크를 확인하세요. 브라우저에서 ${pingUrl} 을 직접 열어 연결 여부를 확인해보세요.` });
                              }
                              setIsAnalyzing(false);
                              return;
                            }

                            setSaveMessage({ type: 'info', text: '분석을 시작했습니다. 잠시만 기다려주세요...' });
                            // 1단계: analyze 요청 → 서버가 즉시 job_id 반환 (504 회피)
                            const startCtrl = new AbortController();
                            const startTimer = setTimeout(() => startCtrl.abort(), 90000);
                            const startRes = await fetch(apiUrl, {
                              method: 'POST',
                              signal: startCtrl.signal,
                              headers: { 'Content-Type': 'application/json' },
                              body: JSON.stringify({
                                action: 'analyze',
                                url: articleUrl.trim(),
                                enable_tts: false,
                                enable_interpret: false,
                                enable_learning: false
                              }),
                            });
                            clearTimeout(startTimer);

                            const contentType = startRes.headers.get('content-type') || '';
                            if (!contentType.includes('application/json')) {
                              const text = await startRes.text();
                              setSaveMessage({ type: 'error', text: `서버 오류: JSON이 아님 (HTTP ${startRes.status}). ${text.slice(0, 150)}` });
                              setIsAnalyzing(false);
                              return;
                            }

                            const startData = await startRes.json();

                            // job_id 없으면 이전 동기 방식 응답 (폴백)
                            const jobId = startData.job_id;
                            let data: Record<string, unknown> | null = null;

                            if (jobId && startData.status === 'processing') {
                              // 폴링: 첫 폴링 즉시, 이후 3초마다, 최대 6분
                              const pollInterval = 3000;
                              const maxPolls = 120;
                              for (let i = 0; i < maxPolls && !pollAborted; i++) {
                                if (i > 0) await new Promise((r) => setTimeout(r, pollInterval));
                                if (pollAborted) break;
                                setSaveMessage({ type: 'info', text: `분석 중... (${i + 1}회 확인)` });
                                const pollRes = await fetch(`/api/admin/ai-analyze.php?action=job_status&job_id=${encodeURIComponent(jobId)}`);
                                const pollJson = await pollRes.json().catch(() => ({}));
                                if (pollJson.status === 'processing') continue;
                                data = pollJson as Record<string, unknown>;
                                break;
                              }
                              if (!data) {
                                setSaveMessage({ type: 'error', text: '분석 시간이 초과되었습니다. 다시 시도해 주세요.' });
                                setIsAnalyzing(false);
                                return;
                              }
                            } else {
                              data = startData as Record<string, unknown>;
                            }

                            if (data.mock_mode) {
                              const debugHint = (data.debug as { env_tried?: string[] })?.env_tried?.length ? ` (시도한 env: ${(data.debug as { env_tried: string[] }).env_tried.join(', ')})` : '';
                              setSaveMessage({ type: 'error', text: `⚠️ Mock 모드: API 키가 서버에서 읽히지 않습니다.${debugHint}` });
                              setIsAnalyzing(false);
                              return;
                            }

                            if (!data.success || !data.analysis) {
                              let errDetail = (data.error as string) || 'GPT 분석 실패';
                              const clar = data.clarification_data as { clarification_question?: string } | undefined;
                              if (data.needs_clarification && clar?.clarification_question) {
                                errDetail = '분석 방향 명확화 필요: ' + clar.clarification_question;
                              } else if (data.failed_step) {
                                errDetail = ((data.error as string) || errDetail) + ` (실패 단계: ${data.failed_step})`;
                              }
                              setSaveMessage({ type: 'error', text: errDetail });
                              setIsAnalyzing(false);
                              return;
                            }

                            const a = data.analysis as Record<string, unknown>;
                            const article = data.article as Record<string, unknown> | undefined;
                            setAiUrl(articleUrl.trim());
                            setNewsTitle((a.news_title as string) || (article?.title as string) || '');
                            setNewsSubtitle((a.subtitle as string) || '');
                            if (article) {
                              setArticleImageUrl((article.image_url as string) || '');
                              setArticleSummary((article.description as string) || '');
                              setArticleSource((article.source as string) || '');
                              if (article.published_at) setArticlePublishedAt(article.published_at as string);
                              if (article.author) setArticleAuthor((article.author as string) || '');
                            }
                            setArticleOriginalTitle((a.original_title as string) || (article?.title as string) || '');
                            setNewsContent(
                              (a.content_summary as string) ||
                              ('## 주요 포인트\n' + ((a.key_points as string[])?.map((p: string) => `- ${p}`).join('\n') || ''))
                            );
                            setNewsNarration(
                              (a.narration as string) ||
                              (((a.translation_summary as string) || '') + ' ' + ((a.key_points as string[])?.map((p: string, i: number) => `${i + 1}번. ${p}`).join(' ') || ''))
                            );
                            setNewsWhyImportant('');
                            setShowExtractedInfo(true);

                            const narrationForTts = (a.narration as string) || ((a.key_points as string[]) || []).join(' ');
                            if (!narrationForTts?.trim()) {
                              setAiResult(a as Record<string, unknown>);
                              setSaveMessage({ type: 'success', text: 'GPT 분석 완료. (내레이션 없어 TTS 생략)' });
                              setIsAnalyzing(false);
                              return;
                            }

                            // 2단계: TTS 생성 (Listen 캐시와 동일 구조 → 첫 Listen 시 즉시 재생)
                            setSaveMessage({ type: 'info', text: '분석 완료. TTS 생성 중...' });
                            const ttsParams = buildTtsParamsForListen({
                              title: (a.news_title as string) || (article?.title as string) || '',
                              narration: narrationForTts,
                              whyImportant: (a as { critical_analysis?: { why_important?: string }; why_important?: string })?.critical_analysis?.why_important ?? (a as { why_important?: string })?.why_important ?? '',
                              publishedAt: article?.published_at,
                              source: article?.source,
                              originalSource: (article as { original_source?: string })?.original_source,
                              sourceUrl: (article as { source_url?: string; url?: string })?.source_url ?? (article as { url?: string })?.url,
                              originalTitle: (a.original_title as string) || undefined,
                            })
                            const ttsRes = await fetch('/api/admin/ai-analyze.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json' },
                              body: JSON.stringify({ action: 'generate_tts', ...ttsParams }),
                            });
                            const ttsData = ttsRes.ok ? await ttsRes.json().catch(() => ({})) : {};
                            const audioUrl = ttsData.success && ttsData.audio_url ? ttsData.audio_url : null;
                            setAiResult({ ...a, audio_url: audioUrl ?? undefined } as Record<string, unknown>);
                            if (audioUrl) {
                              const duration = data.duration_ms ? ` (분석 ${((data.duration_ms as number) / 1000).toFixed(1)}초 + TTS)` : '';
                              setSaveMessage({ type: 'success', text: `GPT 분석·TTS 완료!${duration}` });
                            } else {
                              const ttsErrMsg = ttsData?.error ? `TTS 실패: ${ttsData.error}` : 'TTS 생성만 실패. 내레이션은 사용 가능합니다.';
                              setSaveMessage({ type: 'error', text: ttsErrMsg });
                            }
                          } catch (error: unknown) {
                            console.error('GPT 분석 에러:', error);
                            const err = error as Error & { name?: string };
                            let msg: string;
                            if (err.name === 'AbortError') {
                              msg = '요청 시간 초과(90초). 서버·호스팅 타임아웃이거나 URL 접근이 느립니다.';
                            } else if ((err.message || '').includes('Failed to fetch')) {
                              const pingUrl = typeof window !== 'undefined' ? `${window.location.origin}/api/admin/ai-analyze.php?action=ping` : '/api/admin/ai-analyze.php?action=ping';
                              msg = `서버 연결 실패 (Failed to fetch). 배포 경로, 서버 상태, 네트워크를 확인하세요. 브라우저에서 ${pingUrl} 을 직접 열어 연결 여부를 확인해보세요.`;
                            } else {
                              msg = '서버 오류: ' + (err.message || String(error));
                            }
                            setSaveMessage({ type: 'error', text: msg });
                          } finally {
                            pollAborted = true;
                            setIsAnalyzing(false);
                          }
                        }}
                        disabled={isAnalyzing || !articleUrl.trim()}
                        className={`px-6 py-3 rounded-xl font-medium transition-all whitespace-nowrap ${
                          isAnalyzing || !articleUrl.trim()
                            ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                            : 'bg-gradient-to-r from-emerald-500 to-cyan-500 text-white hover:opacity-90'
                        }`}
                      >
                        {isAnalyzing ? (
                          <span className="flex items-center gap-2">
                            <span className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                            GPT 분석 중...
                          </span>
                        ) : 'GPT 분석'}
                      </button>
                    </div>
                    <p className="text-slate-500 text-sm mt-1">기사 URL을 입력하고 <strong>GPT 분석</strong>을 누르면 제목, 요약, 내레이션, 썸네일이 자동 생성됩니다.</p>
                    <button
                      type="button"
                      onClick={async () => {
                        try {
                          const r = await fetch('/api/admin/ai-analyze.php');
                          const d = await r.json();
                          const msg = d.success
                            ? `상태: ${d.status} | Mock: ${d.mock_mode ? '예' : '아니오'} | API키: ${d.openai_key_set ? '설정됨' : '없음'} | env: ${d.env_loaded ? '로드됨' : '없음'} | root: ${(d.project_root || '').slice(-30)}`
                            : `상태 오류: ${d.error || r.status}`;
                          setSaveMessage({ type: d.openai_key_set ? 'success' : 'error', text: msg });
                          if (d.env_tried?.length) console.log('env_tried', d.env_tried);
                        } catch (e) {
                          const em = (e as Error).message;
                          const hint = em.includes('Failed to fetch')
                            ? ' (API 연결 불가. 배포 경로·서버·네트워크 확인)'
                            : '';
                          setSaveMessage({ type: 'error', text: '상태 확인 실패: ' + em + hint });
                        }
                      }}
                      className="text-slate-500 hover:text-cyan-400 text-xs mt-1 underline"
                    >
                      API 상태 확인
                    </button>
                  </div>

                  {/* 추출된 정보 섹션 (편집 가능) */}
                  {showExtractedInfo && (
                    <div className="bg-slate-900/30 border border-slate-700/50 rounded-xl p-5 space-y-4">
                      <div className="flex items-center justify-between mb-2">
                        <h4 className="text-slate-300 font-medium flex items-center gap-2">
                          <svg className="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                          추출된 정보 (편집 가능)
                        </h4>
                        <button
                          type="button"
                          onClick={() => setShowExtractedInfo(false)}
                          className="text-slate-500 hover:text-slate-300 text-sm"
                        >
                          접기
                        </button>
                      </div>
                      
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Original Source (매체, 영어) */}
                        <div>
                          <label className="block text-slate-400 text-sm mb-1">Original Source 매체 (영어 필수)</label>
                          <input
                            type="text"
                            value={articleSource}
                            onChange={(e) => setArticleSource(e.target.value)}
                            placeholder="예: Foreign Affairs, Financial Times, Reuters"
                            className="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                          />
                          <p className="text-slate-500 text-xs mt-0.5">기사 페이지 출처 문구에 표시됩니다</p>
                        </div>
                        
                        {/* Original Title (원문 영어 제목) */}
                        <div>
                          <label className="block text-slate-400 text-sm mb-1">Original Title 원문 제목 (영어 필수)</label>
                          <input
                            type="text"
                            value={articleOriginalTitle}
                            onChange={(e) => setArticleOriginalTitle(e.target.value)}
                            placeholder="예: The Limits of Russian Power"
                            className="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                          />
                          <p className="text-slate-500 text-xs mt-0.5">매체에 게재된 영어 원제목</p>
                        </div>
                        
                        {/* 기존 기사 백필 / original_title 점검 / 원칙 점검 */}
                        <div className="md:col-span-2 flex flex-wrap items-center gap-3 pt-2">
                          <button
                            type="button"
                            onClick={async () => {
                              try {
                                const r = await fetch('/api/admin/check-article-principles.php');
                                const d = await r.json();
                                if (d.success && d.data) {
                                  const { total, title_not_korean, original_title_has_korean, original_title_missing, message } = d.data;
                                  const parts: string[] = [];
                                  if ((title_not_korean as number) > 0) parts.push(`title 한글 아님 ${title_not_korean}건`);
                                  if ((original_title_has_korean as number) > 0) parts.push(`original_title 한글 포함 ${original_title_has_korean}건`);
                                  if ((original_title_missing as number) > 0) parts.push(`original_title 누락 ${original_title_missing}건`);
                                  const summary = parts.length > 0 ? parts.join(', ') : message;
                                  setSaveMessage({ type: parts.length > 0 ? 'error' : 'info', text: `${summary} (총 ${total}건)` });
                                } else {
                                  setSaveMessage({ type: 'error', text: d.message || '원칙 점검 실패' });
                                }
                              } catch {
                                setSaveMessage({ type: 'error', text: '원칙 점검 실패' });
                              }
                            }}
                            className="px-3 py-1.5 text-sm bg-cyan-700/50 text-cyan-200 rounded-lg hover:bg-cyan-600/50 transition"
                          >
                            원칙 점검
                          </button>
                          <button
                            type="button"
                            onClick={async () => {
                              try {
                                const r = await fetch('/api/admin/check-original-title.php');
                                const d = await r.json();
                                if (d.success && d.data) {
                                  const { total, with_original_title, missing, message } = d.data;
                                  const extra = (missing as number) > 0 ? `, ${missing}건 누락` : '';
                                  setSaveMessage({ type: 'info', text: `${message} (${with_original_title}/${total}건 보유${extra})` });
                                } else {
                                  setSaveMessage({ type: 'error', text: d.message || '점검 실패' });
                                }
                              } catch {
                                setSaveMessage({ type: 'error', text: 'original_title 점검 실패' });
                              }
                            }}
                            className="px-3 py-1.5 text-sm bg-slate-700/50 text-slate-300 rounded-lg hover:bg-slate-600/50 transition"
                          >
                            original_title 점검
                          </button>
                          <button
                            type="button"
                            onClick={async () => {
                              if (!confirm('기존 모든 기사의 Original Source/Title을 source, title 값으로 채웁니다. 실행할까요?')) return;
                              setSaveMessage({ type: 'info', text: '백필 실행 중...' });
                              try {
                                const r = await fetch('/api/admin/run-backfill-original.php');
                                const d = await r.json();
                                if (d.success) {
                                  setSaveMessage({ type: 'success', text: d.message || '백필 완료' });
                                  await loadNewsList();
                                } else {
                                  setSaveMessage({ type: 'error', text: d.message || '백필 실패' });
                                }
                              } catch (e) {
                                setSaveMessage({ type: 'error', text: '백필 요청 실패: ' + (e as Error).message });
                              }
                            }}
                            className="px-3 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs rounded-lg transition-colors"
                          >
                            기존 기사 Original Source/Title 채우기
                          </button>
                          <button
                            type="button"
                            onClick={async () => {
                              if (!confirm('URL 슬러그에서 original_title을 추출해 백필합니다. 즉시 처리됩니다. 실행할까요?')) return;
                              setSaveMessage({ type: 'info', text: 'original_title URL 백필 중...' });
                              try {
                                const r = await fetch('/api/admin/backfill-original-title-url');
                                const d = r.ok ? await r.json() : null;
                                const data = d?.data ?? d;
                                if (d?.success ?? r.ok) {
                                  const msg = data?.message ?? d?.message ?? '백필 완료';
                                  setSaveMessage({ type: 'success', text: msg });
                                  await loadNewsList();
                                } else {
                                  setSaveMessage({ type: 'error', text: d?.message ?? '백필 실패' });
                                }
                              } catch (e) {
                                setSaveMessage({ type: 'error', text: '백필 요청 실패: ' + (e as Error).message });
                              }
                            }}
                            className="px-3 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs rounded-lg transition-colors"
                          >
                            original_title URL 백필
                          </button>
                          <button
                            type="button"
                            onClick={async () => {
                              if (!confirm('원문 URL HTML에서 <title>을 추출해 original_title을 백필합니다. 수 분 소요. 실행할까요?')) return;
                              setSaveMessage({ type: 'info', text: 'original_title HTML 백필 중...' });
                              try {
                                const r = await fetch('/api/admin/backfill-original-title');
                                const d = r.ok ? await r.json() : null;
                                const data = d?.data ?? d;
                                if (d?.success ?? r.ok) {
                                  const msg = data?.message ?? d?.message ?? '백필 완료';
                                  setSaveMessage({ type: 'success', text: msg });
                                  await loadNewsList();
                                } else {
                                  setSaveMessage({ type: 'error', text: d?.message ?? '백필 실패' });
                                }
                              } catch (e) {
                                setSaveMessage({ type: 'error', text: '백필 요청 실패: ' + (e as Error).message });
                              }
                            }}
                            className="px-3 py-2 bg-cyan-700/50 hover:bg-cyan-600/50 text-cyan-200 text-xs rounded-lg transition-colors"
                          >
                            original_title HTML 백필
                          </button>
                          <span className="text-slate-500 text-xs">Source/title 채우기: source만. URL 백필: 슬러그 추출. HTML 백필: &lt;title&gt; 추출</span>
                        </div>
                        
                        {/* 작성자 */}
                        <div>
                          <label className="block text-slate-400 text-sm mb-1">작성자 (Author)</label>
                          <input
                            type="text"
                            value={articleAuthor}
                            onChange={(e) => setArticleAuthor(e.target.value)}
                            placeholder="예: John Smith"
                            className="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                          />
                        </div>
                        
                        {/* 작성일 */}
                        <div>
                          <label className="block text-slate-400 text-sm mb-1">작성일 (Published Date)</label>
                          <input
                            type="text"
                            value={articlePublishedAt}
                            onChange={(e) => setArticlePublishedAt(e.target.value)}
                            placeholder="예: 2026-02-03"
                            className="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                          />
                        </div>
                        
                        {/* 썸네일 URL */}
                        <div>
                          <label className="block text-slate-400 text-sm mb-1">썸네일 URL</label>
                          <div className="flex gap-2">
                            <input
                              type="text"
                              value={articleImageUrl}
                              onChange={(e) => setArticleImageUrl(e.target.value)}
                              placeholder="비워두면 키워드 기반 자동 생성"
                              className="flex-1 bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                            />
                            <button
                              type="button"
                              onClick={() => setArticleImageUrl('')}
                              className="px-3 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs rounded-lg transition-colors whitespace-nowrap"
                              title="썸네일을 비워두면 저장 시 제목 키워드 기반으로 자동 생성됩니다"
                            >
                              자동 생성
                            </button>
                            {editingNewsId && (
                              <button
                                type="button"
                                disabled={isRegeneratingThumbnail}
                                onClick={async () => {
                                  setIsRegeneratingThumbnail(true);
                                  setSaveMessage(null);
                                  try {
                                    const res = await fetch(`/api/admin/update-images.php?action=update_one&id=${editingNewsId}`);
                                    const data = await res.json();
                                    if (data.success && data.new_image) {
                                      setArticleImageUrl(data.new_image);
                                      setSaveMessage({ type: 'success', text: '썸네일이 재생성되었습니다.' });
                                    } else {
                                      setSaveMessage({ type: 'error', text: data.error || '썸네일 재생성 실패' });
                                    }
                                  } catch (e) {
                                    setSaveMessage({ type: 'error', text: '썸네일 재생성 요청 실패' });
                                  } finally {
                                    setIsRegeneratingThumbnail(false);
                                  }
                                }}
                                className="px-3 py-2 bg-cyan-700/70 hover:bg-cyan-600 text-white text-xs rounded-lg transition-colors whitespace-nowrap disabled:opacity-50"
                              >
                                {isRegeneratingThumbnail ? '재생성 중...' : '썸네일 재생성'}
                              </button>
                            )}
                          </div>
                          <p className="text-slate-500 text-xs mt-1">비워두면 제목 키워드 기반으로 썸네일이 자동 생성됩니다</p>
                        </div>
                      </div>
                      
                      {/* 썸네일 미리보기 */}
                      {articleImageUrl && (
                        <div className="mt-3 space-y-3">
                          <label className="block text-slate-400 text-sm mb-2">썸네일 미리보기</label>
                          <div className="relative w-40 h-24 bg-slate-800 rounded-lg overflow-hidden border border-slate-600">
                            <img
                              src={articleImageUrl}
                              alt="Thumbnail preview"
                              className="w-full h-full object-cover"
                              onError={(e) => {
                                (e.target as HTMLImageElement).style.display = 'none';
                              }}
                            />
                          </div>
                          {/* DALL-E로 썸네일 수정 */}
                          <div className="pt-2 border-t border-slate-700/50">
                            <label className="block text-slate-400 text-sm mb-1">DALL-E로 썸네일 수정</label>
                            <p className="text-slate-500 text-xs mb-2">기사 제목을 넣으면 메타포 카툰 스타일로 썸네일을 생성합니다 (비워두면 뉴스 제목 사용)</p>
                            <div className="flex gap-2">
                              <input
                                type="text"
                                value={dallePrompt}
                                onChange={(e) => setDallePrompt(e.target.value)}
                                placeholder="기사 제목 또는 시각화할 개념 (비워두면 뉴스 제목 사용)"
                                className="flex-1 bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition"
                              />
                              <button
                                type="button"
                                disabled={isRegeneratingDalle || (!dallePrompt.trim() && !(newsTitle || '').trim())}
                                onClick={async () => {
                                  setIsRegeneratingDalle(true);
                                  setSaveMessage(null);
                                  try {
                                    const res = await fetch('/api/admin/ai-analyze.php', {
                                      method: 'POST',
                                      headers: { 'Content-Type': 'application/json' },
                                      body: JSON.stringify({
                                        action: 'regenerate_thumbnail_dalle',
                                        prompt: dallePrompt.trim(),
                                        news_title: newsTitle || undefined
                                      })
                                    });
                                    const data = await res.json();
                                    if (data.success && data.image_url) {
                                      setArticleImageUrl(data.image_url);
                                      setSaveMessage({ type: 'success', text: 'DALL-E로 썸네일이 새로 생성되었습니다.' });
                                    } else {
                                      setSaveMessage({ type: 'error', text: data.error || 'DALL-E 썸네일 생성 실패' });
                                    }
                                  } catch (e) {
                                    setSaveMessage({ type: 'error', text: 'DALL-E 요청 실패: ' + (e as Error).message });
                                  } finally {
                                    setIsRegeneratingDalle(false);
                                  }
                                }}
                                className="px-3 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs rounded-lg transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                              >
                                {isRegeneratingDalle ? '생성 중...' : 'DALL-E로 수정'}
                              </button>
                            </div>
                          </div>
                        </div>
                      )}
                      
                      {/* 요약 */}
                      <div>
                        <label className="block text-slate-400 text-sm mb-1">요약 (Summary)</label>
                        <RichTextEditor
                          value={articleSummary}
                          onChange={setArticleSummary}
                          sanitizePaste={(t) => sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}
                          placeholder="기사 요약 내용..."
                          rows={3}
                          className="w-full bg-slate-800/50 border border-slate-600 rounded-lg text-sm"
                        />
                      </div>
                    </div>
                  )}

                  {/* 제목 입력 */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">뉴스 제목</label>
                    <input
                      type="text"
                      value={newsTitle}
                      onChange={(e) => setNewsTitle(e.target.value)}
                      onPaste={(e) => {
                        e.preventDefault();
                        const pastedText = e.clipboardData.getData('text');
                        const sanitized = sanitizeText(pastedText).replace(/\n/g, ' ');
                        setNewsTitle(sanitized);
                      }}
                      placeholder="뉴스 제목을 입력하세요"
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                    />
                  </div>

                  {/* 부제목 입력 */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">
                      부제목 <span className="text-slate-500 text-xs">(Subtitle)</span>
                    </label>
                    <input
                      type="text"
                      value={newsSubtitle}
                      onChange={(e) => setNewsSubtitle(e.target.value)}
                      onPaste={(e) => {
                        e.preventDefault();
                        const pastedText = e.clipboardData.getData('text');
                        const sanitized = sanitizeText(pastedText).replace(/\n/g, ' ');
                        setNewsSubtitle(sanitized);
                      }}
                      placeholder="부제목 (Foreign Affairs 등 매체의 서브타이틀)"
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition text-sm"
                    />
                  </div>

                  {/* gpt 요약 입력 */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label className="text-slate-300 text-sm font-medium">
                        gpt 요약
                        <span className="ml-2 text-xs text-cyan-400">(붙여넣기 시 자동 정제)</span>
                      </label>
                      <button
                        type="button"
                        onClick={() => setIsContentFullscreen(true)}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs transition"
                        title="전체 화면 편집"
                      >
                        <ArrowsPointingOutIcon className="w-4 h-4" />
                        확대
                      </button>
                    </div>
                    <RichTextEditor
                      value={newsContent}
                      onChange={setNewsContent}
                      sanitizePaste={(t) => sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}
                      placeholder="뉴스 본문을 작성하세요..."
                      rows={8}
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-xl"
                    />
                    <p className="text-slate-500 text-sm mt-1">{newsContent.length} / 10,000자</p>
                  </div>

                  {/* 내레이션 톤 입력 */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label className="text-slate-300 text-sm font-medium">
                        <span className="text-emerald-400">내레이션 톤</span>
                        <span className="ml-2 text-xs text-emerald-400/70">(붙여넣기 시 자동 정제)</span>
                      </label>
                      <button
                        type="button"
                        onClick={() => {
                          if (newsNarration.trim()) {
                            navigator.clipboard.writeText(newsNarration.trim());
                            setSaveMessage({ type: 'success', text: '내레이션을 클립보드에 복사했습니다.' });
                            setTimeout(() => setSaveMessage(null), 2000);
                          }
                        }}
                        disabled={!newsNarration.trim()}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs transition disabled:opacity-40 disabled:cursor-not-allowed"
                        title="전체 복사"
                      >
                        <ClipboardDocumentIcon className="w-4 h-4" />
                        전체 복사
                      </button>
                    </div>
                    <RichTextEditor
                      value={newsNarration}
                      onChange={setNewsNarration}
                      sanitizePaste={(t) => sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}
                      placeholder="내레이션 스타일로 작성하세요..."
                      rows={6}
                      className="w-full bg-slate-900/50 border border-emerald-700/50 rounded-xl focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                    />
                    <p className="text-slate-500 text-sm mt-1">{newsNarration.length} / 10,000자</p>
                    {newsNarration.trim() && (
                      <div className="mt-2 flex items-center gap-2">
                        <button
                          type="button"
                          disabled={isRegeneratingTts}
                          onClick={async () => {
                            setIsRegeneratingTts(true);
                            setRegeneratedTtsUrl(null);
                            setSaveMessage(null);
                            try {
                              const ttsParams = buildTtsParamsForListen({
                                title: newsTitle || '제목 없음',
                                narration: newsNarration.trim(),
                                whyImportant: newsWhyImportant,
                                publishedAt: articlePublishedAt,
                                source: articleSource || 'Admin',
                                originalSource: (articleSource !== 'Admin' ? articleSource : null) || undefined,
                                sourceUrl: articleUrl || undefined,
                                originalTitle: articleOriginalTitle || undefined,
                              })
                              const res = await fetch('/api/admin/ai-analyze.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'generate_tts', ...ttsParams }),
                              });
                              const data = await res.json();
                              if (data.success && data.audio_url) {
                                setRegeneratedTtsUrl(data.audio_url);
                                setSaveMessage({ type: 'success', text: 'TTS가 재생성되었습니다.' });
                              } else {
                                setSaveMessage({ type: 'error', text: data.error || 'TTS 재생성 실패' });
                              }
                            } catch (e) {
                              setSaveMessage({ type: 'error', text: 'TTS 재생성 요청 실패' });
                            } finally {
                              setIsRegeneratingTts(false);
                            }
                          }}
                          className="px-4 py-2 bg-emerald-700/70 hover:bg-emerald-600 text-white text-sm rounded-lg transition-colors disabled:opacity-50 flex items-center gap-2"
                        >
                          <SpeakerWaveIcon className="w-4 h-4" />
                          {isRegeneratingTts ? 'TTS 생성 중...' : 'TTS 재생성'}
                        </button>
                      </div>
                    )}
                    {regeneratedTtsUrl && (
                      <div className="mt-2 p-3 bg-slate-800/50 rounded-lg">
                        <p className="text-slate-400 text-sm mb-2">생성된 TTS</p>
                        <audio controls src={regeneratedTtsUrl} className="w-full max-w-md" />
                      </div>
                    )}
                  </div>

                  {/* The Gist's Critique 입력 */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label className="text-slate-300 text-sm font-medium">
                        <span className="text-amber-400">The Gist's Critique</span>
                        <span className="ml-2 text-xs text-amber-400/70">(붙여넣기 시 자동 정제)</span>
                      </label>
                      <button
                        type="button"
                        onClick={() => {
                          if (newsWhyImportant.trim()) {
                            navigator.clipboard.writeText(newsWhyImportant.trim());
                            setSaveMessage({ type: 'success', text: 'The Gist\'s Critique를 클립보드에 복사했습니다.' });
                            setTimeout(() => setSaveMessage(null), 2000);
                          }
                        }}
                        disabled={!newsWhyImportant.trim()}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs transition disabled:opacity-40 disabled:cursor-not-allowed"
                        title="전체 복사"
                      >
                        <ClipboardDocumentIcon className="w-4 h-4" />
                        전체 복사
                      </button>
                    </div>
                    <RichTextEditor
                      value={newsWhyImportant}
                      onChange={setNewsWhyImportant}
                      sanitizePaste={(t) => sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}
                      placeholder="The Gist's Critique를 작성해주세요..."
                      rows={5}
                      className="w-full bg-slate-900/50 border border-amber-700/50 rounded-xl focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    />
                    <p className="text-slate-500 text-sm mt-1">{newsWhyImportant.length} / 5,000자</p>
                  </div>

                  {/* 저장 버튼 */}
                  <div className="flex items-center gap-4 flex-wrap">
                    <button
                      onClick={async () => {
                        if (!newsTitle.trim() || !newsContent.trim()) {
                          setSaveMessage({ type: 'error', text: '제목과 내용을 모두 입력해주세요.' });
                          return;
                        }
                        
                        setIsSaving(true);
                        setSaveMessage(null);
                        
                        try {
                          const isEditing = editingNewsId !== null;
                          const cleanContent = normalizeEditorHtml(newsContent)
                          const cleanNarration = normalizeEditorHtml(newsNarration)
                          const cleanWhyImportant = normalizeEditorHtml(newsWhyImportant)
                          const requestBody = {
                            ...(isEditing && { id: editingNewsId }),
                            category: selectedCategory,
                            title: newsTitle,
                            subtitle: newsSubtitle.trim() || null,
                            content: cleanContent,
                            why_important: cleanWhyImportant || null,
                            narration: cleanNarration || null,
                            source_url: articleUrl.trim() || null,
                            source: articleSource.trim() || null,
                            original_title: articleOriginalTitle.trim() || null,
                            author: articleAuthor.trim() || null,
                            published_at: articlePublishedAt.trim() || null,
                            image_url: articleImageUrl.trim() || null,
                          };
                          
                          console.log('Sending request:', { 
                            method: isEditing ? 'PUT' : 'POST',
                            contentLength: newsContent.length,
                            bodySize: JSON.stringify(requestBody).length 
                          });
                          
                          const response = await fetch('/api/admin/news.php', {
                            method: isEditing ? 'PUT' : 'POST',
                            headers: { 'Content-Type': 'application/json; charset=utf-8' },
                            body: JSON.stringify(requestBody),
                          });
                          
                          // 응답 텍스트 먼저 가져오기
                          const responseText = await response.text();
                          console.log('Response status:', response.status, 'Response length:', responseText.length);
                          
                          // 응답이 비어있으면 에러
                          if (!responseText) {
                            throw new Error('서버에서 빈 응답을 반환했습니다. (status: ' + response.status + ')');
                          }
                          
                          // JSON 파싱 시도
                          let data;
                          try {
                            data = JSON.parse(responseText);
                          } catch (parseError) {
                            console.error('JSON parse error:', parseError, 'Response:', responseText.substring(0, 500));
                            throw new Error('서버 응답 파싱 실패. 서버 오류 발생.');
                          }
                          
                          if (data.success) {
                            const savedId = data.data?.id ?? data.new_id ?? data.id ?? editingNewsId
                            setSaveMessage({ 
                              type: 'success', 
                              text: isEditing ? '뉴스가 수정되었습니다!' : '뉴스가 저장되었습니다!' 
                            });
                            // Listen 캐시 선반입 (저장 직후 TTS 생성 → 첫 Listen 시 즉시 재생)
                            const ttsParams = buildTtsParamsForListen({
                              title: newsTitle,
                              narration: newsNarration.trim(),
                              whyImportant: newsWhyImportant.trim() || undefined,
                              publishedAt: articlePublishedAt.trim() || undefined,
                              source: articleSource.trim() || 'Admin',
                              originalSource: articleSource !== 'Admin' ? articleSource : undefined,
                              sourceUrl: articleUrl.trim() || undefined,
                              originalTitle: articleOriginalTitle.trim() || undefined,
                            })
                            if (ttsParams.narration || ttsParams.critique_part) {
                              fetch('/api/admin/ai-analyze.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'generate_tts', ...ttsParams, news_id: savedId ?? undefined }),
                              }).catch(() => { /* ignore */ })
                            }
                            // 목록 새로고침
                            await loadNewsList();
                            // 폼 초기화
                            setNewsTitle('');
                            setNewsSubtitle('');
                            setNewsContent('');
                            setNewsWhyImportant('');
                            setNewsNarration('');
                            setArticleUrl('');
                            setArticleOriginalTitle('');
                            setEditingNewsId(null);
                            setRegeneratedTtsUrl(null);
                            // 추출 정보 초기화
                            setArticleSource('');
                            setArticleAuthor('');
                            setArticlePublishedAt('');
                            setArticleImageUrl('');
                            setArticleSummary('');
                            setShowExtractedInfo(false);
                          } else {
                            throw new Error(data.message || '저장 실패');
                          }
                        } catch (error) {
                          console.error('Save error:', error);
                          setSaveMessage({ type: 'error', text: '저장 실패: ' + (error as Error).message });
                        } finally {
                          setIsSaving(false);
                          setTimeout(() => setSaveMessage(null), 5000);
                        }
                      }}
                      disabled={isSaving || !newsTitle.trim() || !newsContent.trim()}
                      className={`px-6 py-3 rounded-xl font-medium transition-all flex items-center gap-2 ${
                        isSaving || !newsTitle.trim() || !newsContent.trim()
                          ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                          : editingNewsId
                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:opacity-90'
                            : 'bg-gradient-to-r from-cyan-500 to-emerald-500 text-white hover:opacity-90'
                      }`}
                    >
                      {isSaving ? (
                        <>
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                          저장 중...
                        </>
                      ) : editingNewsId ? (
                        <>
                          <PencilSquareIcon className="w-5 h-5" />
                          뉴스 수정
                        </>
                      ) : (
                        <>
                          <NewspaperIcon className="w-5 h-5" />
                          뉴스 저장
                        </>
                      )}
                    </button>

                    {!editingNewsId && (
                      <button
                        onClick={async () => {
                          if (!newsTitle.trim() || !newsContent.trim()) {
                            setSaveMessage({ type: 'error', text: '제목과 내용을 모두 입력해주세요.' });
                            return;
                          }
                          setIsSaving(true);
                          setSaveMessage(null);
                          try {
                            const cleanContent = normalizeEditorHtml(newsContent);
                            const cleanNarration = normalizeEditorHtml(newsNarration);
                            const cleanWhyImportant = normalizeEditorHtml(newsWhyImportant);
                            const response = await fetch('/api/admin/news.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json; charset=utf-8' },
                              body: JSON.stringify({
                                category: selectedCategory,
                                title: newsTitle,
                                subtitle: newsSubtitle.trim() || null,
                                content: cleanContent,
                                why_important: cleanWhyImportant || null,
                                narration: cleanNarration || null,
                                source_url: articleUrl.trim() || null,
                                source: articleSource.trim() || null,
                                original_title: articleOriginalTitle.trim() || null,
                                author: articleAuthor.trim() || null,
                                published_at: articlePublishedAt.trim() || null,
                                image_url: articleImageUrl.trim() || null,
                                status: 'draft',
                              }),
                            });
                            const data = await response.json();
                            if (data.success) {
                              const savedId = data.data?.id ?? data.new_id ?? data.id;
                              setSaveMessage({ type: 'success', text: '임시 저장되었습니다. 미리보기에서 확인하세요.' });
                              if (savedId) {
                                await fetchDraftArticle(savedId);
                              }
                            } else {
                              throw new Error(data.message || '임시 저장 실패');
                            }
                          } catch (error) {
                            setSaveMessage({ type: 'error', text: '임시 저장 실패: ' + (error as Error).message });
                          } finally {
                            setIsSaving(false);
                            setTimeout(() => setSaveMessage(null), 5000);
                          }
                        }}
                        disabled={isSaving || !newsTitle.trim() || !newsContent.trim()}
                        className="px-6 py-3 rounded-xl font-medium bg-amber-600/80 hover:bg-amber-500 text-white transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                      >
                        <DocumentDuplicateIcon className="w-5 h-5" />
                        임시 저장
                      </button>
                    )}

                    <button
                      onClick={handleCancelEdit}
                      className="px-6 py-3 rounded-xl font-medium bg-slate-700/50 text-slate-300 hover:bg-slate-600/50 transition"
                    >
                      초기화
                    </button>
                  </div>

                  {/* 저장 메시지 */}
                  {saveMessage && (
                    <div className={`p-4 rounded-xl flex items-start gap-2 ${
                      saveMessage.type === 'success' 
                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' 
                        : saveMessage.type === 'info'
                        ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30'
                        : 'bg-red-500/20 text-red-400 border border-red-500/30'
                    }`}>
                      {saveMessage.type === 'success' ? (
                        <CheckCircleIcon className="w-5 h-5 flex-shrink-0 mt-0.5" />
                      ) : (
                        <ExclamationTriangleIcon className="w-5 h-5 flex-shrink-0 mt-0.5" />
                      )}
                      {saveMessage.text}
                    </div>
                  )}
                </div>
              </div>

              {/* 저장된 뉴스 목록 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-white">
                    {categories.find(c => c.id === selectedCategory)?.name} 뉴스 목록
                  </h3>
                  <span className="text-slate-400 text-sm">
                    총 {newsList.length}개
                  </span>
                </div>

                {isLoadingNews ? (
                  <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-4 border-cyan-500 border-t-transparent"></div>
                  </div>
                ) : newsList.length === 0 ? (
                  <p className="text-slate-500 text-center py-8">
                    이 카테고리에 저장된 뉴스가 없습니다.
                  </p>
                ) : (
                  <div className="space-y-3 max-h-[500px] overflow-y-auto">
                    {newsList.map((news) => (
                      <div
                        key={news.id}
                        className={`p-4 bg-slate-900/50 rounded-xl border transition-all ${
                          editingNewsId === news.id 
                            ? 'border-amber-500/50 bg-amber-500/5' 
                            : 'border-slate-700/30 hover:border-slate-600/50'
                        }`}
                      >
                        <div className="flex items-start justify-between gap-4">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1">
                              <span className="text-xs px-2 py-0.5 bg-slate-700 text-slate-300 rounded">
                                ID: {news.id}
                              </span>
                              {news.source && news.source !== 'Admin' && (
                                <span className="text-xs px-2 py-0.5 bg-blue-500/20 text-blue-400 rounded">
                                  {formatSourceDisplayName(news.source)}
                                </span>
                              )}
                            </div>
                            <h4 className="text-white font-medium truncate">{news.title}</h4>
                            <p className="text-slate-400 text-sm mt-1 line-clamp-2">
                              {news.description || news.content}
                            </p>
                            <div className="flex items-center gap-3 mt-2">
                              <p className="text-slate-500 text-xs">
                                {news.created_at ? new Date(news.created_at).toLocaleString('ko-KR') : ''}
                              </p>
                              {news.source_url && !news.source_url.startsWith('admin://') && (
                                <a
                                  href={news.source_url}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="text-xs text-cyan-400 hover:text-cyan-300 hover:underline"
                                >
                                  원문 보기 →
                                </a>
                              )}
                            </div>
                          </div>
                          <div className="flex items-center gap-2 shrink-0">
                            <button
                              onClick={() => handleEditNews(news)}
                              disabled={editingNewsId === news.id}
                              className={`p-2 rounded-lg transition ${
                                editingNewsId === news.id
                                  ? 'bg-amber-500/20 text-amber-400 cursor-not-allowed'
                                  : 'text-slate-400 hover:text-cyan-400 hover:bg-cyan-500/10'
                              }`}
                              title="수정"
                            >
                              <PencilSquareIcon className="w-5 h-5" />
                            </button>
                            <button
                              onClick={() => setDeleteConfirmId(news.id || null)}
                              className="p-2 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition"
                              title="삭제"
                            >
                              <TrashIcon className="w-5 h-5" />
                            </button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* 삭제 확인 다이얼로그 */}
              {deleteConfirmId && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
                  <div className="bg-slate-800 rounded-2xl p-6 border border-slate-700 max-w-md w-full mx-4 shadow-2xl">
                    <div className="flex items-center gap-3 mb-4">
                      <div className="p-3 bg-red-500/20 rounded-full">
                        <TrashIcon className="w-6 h-6 text-red-400" />
                      </div>
                      <div>
                        <h3 className="text-lg font-semibold text-white">뉴스 삭제</h3>
                        <p className="text-slate-400 text-sm">이 작업은 되돌릴 수 없습니다.</p>
                      </div>
                    </div>
                    <p className="text-slate-300 mb-6">
                      ID {deleteConfirmId} 뉴스를 정말 삭제하시겠습니까?
                    </p>
                    <div className="flex gap-3 justify-end">
                      <button
                        onClick={() => setDeleteConfirmId(null)}
                        className="px-4 py-2 rounded-lg bg-slate-700 text-slate-300 hover:bg-slate-600 transition"
                      >
                        취소
                      </button>
                      <button
                        onClick={() => handleDeleteNews(deleteConfirmId)}
                        className="px-4 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600 transition"
                      >
                        삭제
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {!draftPreviewId && activeTab === 'drafts' && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">임시 저장</h2>
                <p className="text-slate-400">배포 전 condition 체크 및 편집이 가능한 초안 목록</p>
              </div>
              {isLoadingDraft ? (
                <div className="flex justify-center py-20">
                  <div className="animate-spin rounded-full h-12 w-12 border-4 border-cyan-500 border-t-transparent" />
                </div>
              ) : draftList.length === 0 ? (
                <div className="bg-slate-800/50 rounded-2xl p-12 text-center border border-slate-700/50">
                  <DocumentDuplicateIcon className="w-16 h-16 mx-auto text-slate-500 mb-4" />
                  <p className="text-slate-400">임시 저장된 글이 없습니다.</p>
                  <p className="text-slate-500 text-sm mt-2">뉴스 관리에서 URL 추출 후 &quot;임시 저장&quot; 버튼을 누르세요.</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {draftList.map((item) => (
                    <button
                      key={item.id}
                      onClick={() => item.id && fetchDraftArticle(item.id)}
                      className="w-full text-left p-4 rounded-xl bg-slate-800/50 hover:bg-slate-700/50 border border-slate-700/50 transition"
                    >
                      <div className="flex items-center justify-between">
                        <div className="min-w-0 flex-1">
                          <p className="font-medium text-white truncate">{item.title}</p>
                          <p className="text-slate-500 text-sm mt-1">
                            {item.category} · {(item as { updated_at?: string }).updated_at || (item as { created_at?: string }).created_at || '-'}
                          </p>
                        </div>
                        <span className="text-amber-400/80 text-xs ml-2">편집</span>
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {!draftPreviewId && activeTab === 'ai' && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">AI 뉴스 분석</h2>
                <p className="text-slate-400">URL을 입력하면 AI가 기사를 분석, 요약, 번역합니다</p>
              </div>

              {/* 상태 표시 */}
              <div className="flex items-center gap-4">
                <div className="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                  <SparklesIcon className="w-5 h-5" />
                  The Gist AI 분석 시스템
                </div>
{/* 상태 새로고침 버튼 제거 - The Gist AI로 통합 */}
              </div>

              {/* URL 분석 섹션 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                  <DocumentTextIcon className="w-5 h-5 text-cyan-400" />
                  기사 URL 분석
                </h3>
                
                <div className="space-y-4">
                  <div className="flex gap-3">
                    <input
                      type="url"
                      value={aiUrl}
                      onChange={(e) => setAiUrl(e.target.value)}
                      placeholder="https://www.reuters.com/article/..."
                      className="flex-1 bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                    />
                    <button
                      onClick={async () => {
                        if (!aiUrl.trim()) {
                          setAiError('URL을 입력해주세요.');
                          return;
                        }
                        setIsAnalyzing(true);
                        setAiError(null);
                        setAiResult(null);
                        try {
                          const response = await fetch('/api/admin/ai-analyze.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              action: 'analyze',
                              url: aiUrl.trim(),
                              enable_tts: false,
                              enable_interpret: false,
                              enable_learning: false
                            })
                          });
                          const data = await response.json();
                          if (!data.success || !data.analysis) {
                            const err = data.needs_clarification && data.clarification_data?.clarification_question
                              ? '명확화 필요: ' + (data.clarification_data.clarification_question as string)
                              : (data.error || '분석 실패') + (data.failed_step ? ` (${data.failed_step})` : '');
                            setAiError(err);
                            setIsAnalyzing(false);
                            return;
                          }
                          if (data.article) {
                            setArticleImageUrl(data.article.image_url || '');
                            setNewsTitle(data.article.title || '');
                            setArticleSummary(data.article.description || '');
                            if (data.article.published_at) setArticlePublishedAt(data.article.published_at);
                            const author = (data.analysis as { author?: string })?.author || data.article.author;
                            if (author) setArticleAuthor(author);
                          }
                          setArticleOriginalTitle(
                            ((data.analysis as { original_title?: string })?.original_title || (data.article as { title?: string })?.title || '').trim()
                          );
                          const a = data.analysis;
                          const narrationForTts = a.narration || (a.key_points || []).join(' ');
                          if (!narrationForTts.trim()) {
                            setAiResult(data.analysis);
                            setIsAnalyzing(false);
                            return;
                          }
                          try {
                            const ttsParams = buildTtsParamsForListen({
                              title: data.analysis?.news_title || data.article?.title || '',
                              narration: narrationForTts,
                              whyImportant: (data.analysis as { critical_analysis?: { why_important?: string }; why_important?: string })?.critical_analysis?.why_important ?? (data.analysis as { why_important?: string })?.why_important ?? '',
                              publishedAt: data.article?.published_at,
                              source: data.article?.source,
                              originalSource: (data.article as { original_source?: string })?.original_source,
                              sourceUrl: (data.article as { source_url?: string; url?: string })?.source_url ?? (data.article as { url?: string })?.url,
                              originalTitle: (data.analysis as { original_title?: string })?.original_title,
                            })
                            const ttsRes = await fetch('/api/admin/ai-analyze.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json' },
                              body: JSON.stringify({ action: 'generate_tts', ...ttsParams }),
                            });
                            const ttsData = ttsRes.ok ? await ttsRes.json().catch(() => ({})) : {};
                            const audioUrl = ttsData.success && ttsData.audio_url ? ttsData.audio_url : null;
                            setAiResult({ ...data.analysis, audio_url: audioUrl ?? undefined });
                            if (!audioUrl && ttsData?.error) {
                              setSaveMessage({ type: 'error', text: `TTS 실패: ${ttsData.error}` });
                            }
                          } catch {
                            setAiResult(data.analysis);
                          }
                        } catch (error) {
                          setAiError('서버 오류: ' + (error as Error).message);
                        } finally {
                          setIsAnalyzing(false);
                        }
                      }}
                      disabled={isAnalyzing || !aiUrl.trim()}
                      className={`px-6 py-3 rounded-xl font-medium transition-all flex items-center gap-2 ${
                        isAnalyzing || !aiUrl.trim()
                          ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                          : 'bg-gradient-to-r from-cyan-500 to-emerald-500 text-white hover:opacity-90'
                      }`}
                    >
                      {isAnalyzing ? (
                        <>
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                          분석 중...
                        </>
                      ) : (
                        <>
                          <PlayIcon className="w-5 h-5" />
                          AI 분석 실행
                        </>
                      )}
                    </button>
                  </div>

                  {/* 에러 메시지 */}
                  {aiError && (
                    <div className="p-4 rounded-xl bg-red-500/20 text-red-400 border border-red-500/30 flex items-center gap-2">
                      <ExclamationTriangleIcon className="w-5 h-5" />
                      {aiError}
                    </div>
                  )}

                  {/* 분석 결과 */}
                  {aiResult && (
                    <div className="space-y-4 pt-4 border-t border-slate-700/50">
                      {/* GPT 생성 제목 */}
                      {aiResult.news_title && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <h4 className="text-yellow-400 font-medium mb-2 flex items-center gap-2">
                            <DocumentTextIcon className="w-4 h-4" />
                            GPT 생성 제목
                          </h4>
                          <p className="text-white text-lg font-semibold">{aiResult.news_title}</p>
                          {!!(aiResult as Record<string, unknown>).subtitle && (
                            <p className="text-slate-400 text-sm italic mt-1">{String((aiResult as Record<string, unknown>).subtitle)}</p>
                          )}
                        </div>
                      )}

                      {/* 내레이션 */}
                      {(aiResult.narration || aiResult.translation_summary) && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <div className="flex items-center justify-between mb-2">
                            <h4 className="text-cyan-400 font-medium flex items-center gap-2">
                              <DocumentTextIcon className="w-4 h-4" />
                              내레이션
                            </h4>
                            <button
                              type="button"
                              onClick={() => {
                                const text = aiResult.narration || aiResult.translation_summary || '';
                                navigator.clipboard.writeText(text);
                                setSaveMessage({ type: 'success', text: '내레이션을 클립보드에 복사했습니다.' });
                                setTimeout(() => setSaveMessage(null), 2000);
                              }}
                              className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs transition"
                              title="전체 복사"
                            >
                              <ClipboardDocumentIcon className="w-4 h-4" />
                              전체 복사
                            </button>
                          </div>
                          <p className="text-slate-300 leading-relaxed whitespace-pre-line">{aiResult.narration || aiResult.translation_summary}</p>
                        </div>
                      )}

                      {/* 주요 포인트 */}
                      {aiResult.key_points && aiResult.key_points.length > 0 && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <h4 className="text-emerald-400 font-medium mb-2">주요 포인트 ({aiResult.key_points.length}개)</h4>
                          <ul className="space-y-2">
                            {aiResult.key_points.map((point, i) => (
                              <li key={i} className="text-slate-300 flex items-start gap-2">
                                <span className="text-emerald-400 mt-1 font-bold">{i + 1}.</span>
                                {point}
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}

                      {/* 크리티컬 분석 */}
                      {aiResult.critical_analysis && aiResult.critical_analysis.why_important && (
                        <div className="p-4 bg-gradient-to-br from-purple-500/10 to-pink-500/10 rounded-xl border border-purple-500/20">
                          <div className="flex items-center justify-between mb-3">
                            <h4 className="text-purple-400 font-medium">The Gist's Critique</h4>
                            <button
                              type="button"
                              onClick={() => {
                                const text = aiResult.critical_analysis?.why_important || '';
                                navigator.clipboard.writeText(text);
                                setSaveMessage({ type: 'success', text: 'The Gist\'s Critique를 클립보드에 복사했습니다.' });
                                setTimeout(() => setSaveMessage(null), 2000);
                              }}
                              className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs transition"
                              title="전체 복사"
                            >
                              <ClipboardDocumentIcon className="w-4 h-4" />
                              전체 복사
                            </button>
                          </div>
                          <p className="text-slate-200">{aiResult.critical_analysis.why_important}</p>
                        </div>
                      )}

                      {/* 오디오 (있는 경우) */}
                      {aiResult.audio_url && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <h4 className="text-orange-400 font-medium mb-2 flex items-center gap-2">
                            <SpeakerWaveIcon className="w-4 h-4" />
                            오디오 분석
                          </h4>
                          <audio controls className="w-full">
                            <source src={aiResult.audio_url} type="audio/mpeg" />
                          </audio>
                        </div>
                      )}

                      {/* 음성 읽기 컨트롤 */}
                      <div className="p-4 bg-slate-900/50 rounded-xl">
                        <div className="flex items-center justify-between mb-3">
                          <h4 className="text-orange-400 font-medium flex items-center gap-2">
                            <SpeakerWaveIcon className="w-4 h-4" />
                            AI 음성 읽기
                          </h4>
                          <div className="flex items-center gap-2">
                            <span className="text-slate-400 text-sm">속도:</span>
                            <select
                              value={speechRate}
                              onChange={(e) => setSpeechRate(parseFloat(e.target.value))}
                              className="bg-slate-800 text-white text-sm rounded px-2 py-1 border border-slate-700"
                            >
                              <option value="0.7">느리게 (0.7x)</option>
                              <option value="0.85">조금 느리게</option>
                              <option value="1.0">보통 (1.0x)</option>
                              <option value="1.2">약간 빠름 ✓</option>
                              <option value="1.4">빠르게</option>
                              <option value="1.6">매우 빠르게</option>
                              <option value="2.0">최고속도 (2.0x)</option>
                            </select>
                          </div>
                        </div>
                        
                        <div className="flex gap-2">
                          <button
                            onClick={isSpeaking ? stopSpeaking : speakFullAnalysis}
                            className={`flex-1 py-3 rounded-xl font-medium transition flex items-center justify-center gap-2 ${
                              isSpeaking
                                ? 'bg-red-500 text-white hover:bg-red-600'
                                : 'bg-gradient-to-r from-orange-500 to-red-500 text-white hover:opacity-90'
                            }`}
                          >
                            {isSpeaking ? (
                              <>
                                <XMarkIcon className="w-5 h-5" />
                                읽기 중지
                              </>
                            ) : (
                              <>
                                <SpeakerWaveIcon className="w-5 h-5" />
                                전체 분석 읽어주기
                              </>
                            )}
                          </button>
                        </div>
                        
                        {/* 개별 섹션 읽기 */}
                        <div className="flex gap-2 mt-2">
                          <button
                            onClick={() => speakText(aiResult.narration || aiResult.translation_summary || '')}
                            disabled={isSpeaking}
                            className="flex-1 py-2 text-sm rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition disabled:opacity-50"
                          >
                            내레이션
                          </button>
                          <button
                            onClick={() => speakText(aiResult.key_points?.join('. ') || '')}
                            disabled={isSpeaking}
                            className="flex-1 py-2 text-sm rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition disabled:opacity-50"
                          >
                            포인트만
                          </button>
                          <button
                            onClick={() => speakText(aiResult.critical_analysis?.why_important || '')}
                            disabled={isSpeaking}
                            className="flex-1 py-2 text-sm rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 transition disabled:opacity-50"
                          >
                            크리티크만
                          </button>
                        </div>
                      </div>

                      {/* ── 피드백 & 재분석 패널 ─────────────── */}
                      <div className="p-5 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 rounded-xl border border-indigo-500/20 space-y-4">
                        <h4 className="text-indigo-400 font-semibold flex items-center gap-2">
                          <ChatBubbleLeftRightIcon className="w-5 h-5" />
                          피드백 & 재분석 (학습 루프)
                        </h4>

                        {/* 코멘트 + 점수 */}
                        <div className="space-y-3">
                          <div>
                            <label className="block text-slate-400 text-sm mb-1">피드백 코멘트</label>
                            <textarea
                              value={feedbackComment}
                              onChange={(e) => setFeedbackComment(e.target.value)}
                              placeholder="분석에 대한 피드백을 남겨주세요. (예: '현실주의 관점 분석 추가 필요', '수치가 부정확함')"
                              rows={3}
                              className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition resize-none"
                            />
                          </div>
                          <div className="flex items-center gap-4">
                            <label className="text-slate-400 text-sm whitespace-nowrap flex items-center gap-1">
                              <StarIcon className="w-4 h-4 text-yellow-400" />
                              품질 점수
                            </label>
                            <input
                              type="range"
                              min="1"
                              max="10"
                              value={feedbackScore}
                              onChange={(e) => setFeedbackScore(parseInt(e.target.value))}
                              className="flex-1 accent-indigo-500"
                            />
                            <span className="text-white font-bold text-lg min-w-[2.5rem] text-center">
                              {feedbackScore}
                              <span className="text-slate-500 text-xs">/10</span>
                            </span>
                          </div>
                        </div>

                        {/* 액션 버튼 */}
                        <div className="flex flex-wrap gap-2">
                          <button
                            onClick={saveFeedback}
                            disabled={isSavingFeedback || !feedbackComment.trim()}
                            className="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            {isSavingFeedback ? (
                              <span className="animate-spin rounded-full h-3.5 w-3.5 border-2 border-white border-t-transparent" />
                            ) : (
                              <ChatBubbleLeftRightIcon className="w-4 h-4" />
                            )}
                            피드백 저장
                          </button>
                          <button
                            onClick={requestRevision}
                            disabled={isRequestingRevision || !feedbackComment.trim()}
                            className="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5 bg-gradient-to-r from-purple-600 to-pink-600 hover:opacity-90 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            {isRequestingRevision ? (
                              <span className="animate-spin rounded-full h-3.5 w-3.5 border-2 border-white border-t-transparent" />
                            ) : (
                              <ArrowPathIcon className="w-4 h-4" />
                            )}
                            {isRequestingRevision ? 'GPT 재분석 중...' : 'GPT 재분석 요청'}
                          </button>
                          <button
                            onClick={approveAnalysis}
                            disabled={isApprovingAnalysis || feedbackHistory.length === 0}
                            className="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            {isApprovingAnalysis ? (
                              <span className="animate-spin rounded-full h-3.5 w-3.5 border-2 border-white border-t-transparent" />
                            ) : (
                              <HandThumbUpIcon className="w-4 h-4" />
                            )}
                            최종 승인
                          </button>
                        </div>

                        {/* 피드백 메시지 */}
                        {feedbackMessage && (
                          <div className={`p-3 rounded-lg text-sm flex items-center gap-2 ${
                            feedbackMessage.type === 'success'
                              ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                              : 'bg-red-500/20 text-red-400 border border-red-500/30'
                          }`}>
                            {feedbackMessage.type === 'success' ? <CheckCircleIcon className="w-4 h-4" /> : <ExclamationTriangleIcon className="w-4 h-4" />}
                            {feedbackMessage.text}
                          </div>
                        )}

                        {/* Revision 히스토리 */}
                        {feedbackHistory.length > 0 && (
                          <div className="space-y-2">
                            <h5 className="text-slate-400 text-sm font-medium">Revision 히스토리 ({feedbackHistory.length}개)</h5>
                            <div className="max-h-48 overflow-y-auto space-y-2">
                              {feedbackHistory.map((fb) => (
                                <div key={fb.id} className="p-3 bg-slate-900/50 rounded-lg border border-slate-700/30 text-sm">
                                  <div className="flex items-center justify-between mb-1">
                                    <span className="flex items-center gap-2">
                                      <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                                        fb.status === 'approved'
                                          ? 'bg-emerald-500/20 text-emerald-400'
                                          : fb.status === 'revised'
                                          ? 'bg-purple-500/20 text-purple-400'
                                          : fb.status === 'reviewed'
                                          ? 'bg-indigo-500/20 text-indigo-400'
                                          : 'bg-slate-700 text-slate-400'
                                      }`}>
                                        {fb.status === 'approved' ? '승인됨' : fb.status === 'revised' ? '재분석됨' : fb.status === 'reviewed' ? '리뷰됨' : '초안'}
                                      </span>
                                      <span className="text-slate-500">rev #{fb.revision_number}</span>
                                    </span>
                                    <div className="flex items-center gap-2">
                                      {fb.score && (
                                        <span className="flex items-center gap-0.5 text-yellow-400 text-xs">
                                          <StarIcon className="w-3 h-3" />
                                          {fb.score}/10
                                        </span>
                                      )}
                                      <span className="text-slate-600 text-xs">
                                        {new Date(fb.created_at).toLocaleString('ko-KR', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                      </span>
                                    </div>
                                  </div>
                                  {fb.admin_comment && (
                                    <p className="text-slate-300 text-xs mt-1">{fb.admin_comment}</p>
                                  )}
                                  {fb.gpt_revision && (
                                    <button
                                      className="text-purple-400 text-xs mt-1 hover:underline"
                                      onClick={() => setAiResult(fb.gpt_revision as unknown as AIAnalysisResult)}
                                    >
                                      이 버전으로 복원 →
                                    </button>
                                  )}
                                </div>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>

                      {/* 뉴스로 저장 버튼 */}
                      <button
                        onClick={() => {
                          setActiveTab('news');
                          // 제목: GPT news_title 우선
                          setNewsTitle(aiResult.news_title || aiResult.translation_summary?.substring(0, 100) || '');
                          // 본문: content_summary 우선, 없으면 key_points 불렛만 (Critique 미사용)
                          setNewsContent(
                            aiResult.content_summary ||
                            ('## 주요 포인트\n' + (aiResult.key_points?.map(p => `- ${p}`).join('\n') || ''))
                          );
                          // 내레이션: GPT narration 우선
                          setNewsNarration(
                            aiResult.narration ||
                            ((aiResult.translation_summary || '') + ' ' +
                            (aiResult.key_points?.map((p, i) => `${i + 1}번. ${p}`).join(' ') || ''))
                          );
                          setNewsWhyImportant('');
                          setNewsSubtitle(((aiResult as Record<string, unknown>).subtitle as string) || '');
                          setArticleUrl(aiUrl);
                          setArticleOriginalTitle((aiResult.original_title as string) || '');
                        }}
                        className="w-full py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white font-medium hover:opacity-90 transition flex items-center justify-center gap-2"
                      >
                        <NewspaperIcon className="w-5 h-5" />
                        이 분석을 뉴스로 저장
                      </button>
                    </div>
                  )}
                </div>
              </div>

              {/* 학습 섹션 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                  <AcademicCapIcon className="w-5 h-5 text-purple-400" />
                  스타일 학습
                </h3>
                <p className="text-slate-400 text-sm mb-4">
                  당신이 작성한 글을 입력하면 AI가 스타일을 학습하여 분석에 적용합니다.
                </p>

                <div className="space-y-4">
                  <textarea
                    value={learningTexts}
                    onChange={(e) => setLearningTexts(e.target.value)}
                    placeholder="학습시킬 글을 입력하세요... (여러 글은 --- 로 구분)"
                    rows={6}
                    className="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition resize-none"
                  />
                  
                  <div className="flex items-center gap-4">
                    <button
                      onClick={async () => {
                        if (!learningTexts.trim()) return;
                        
                        setIsLearning(true);
                        try {
                          const texts = learningTexts.split('---').map(t => t.trim()).filter(t => t);
                          const response = await fetch('/api/admin/ai-analyze.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              action: 'learn',
                              texts
                            })
                          });
                          
                          const data = await response.json();
                          if (data.success) {
                            setLearnedPatterns(data.patterns);
                            setLearningTexts('');
                          }
                        } catch (error) {
                          console.error('Learning failed:', error);
                        } finally {
                          setIsLearning(false);
                        }
                      }}
                      disabled={isLearning || !learningTexts.trim()}
                      className={`px-6 py-3 rounded-xl font-medium transition-all flex items-center gap-2 ${
                        isLearning || !learningTexts.trim()
                          ? 'bg-slate-700 text-slate-400 cursor-not-allowed'
                          : 'bg-gradient-to-r from-purple-500 to-pink-500 text-white hover:opacity-90'
                      }`}
                    >
                      {isLearning ? (
                        <>
                          <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                          학습 중...
                        </>
                      ) : (
                        <>
                          <AcademicCapIcon className="w-5 h-5" />
                          스타일 학습
                        </>
                      )}
                    </button>

                    <button
                      onClick={async () => {
                        try {
                          const response = await fetch('/api/admin/ai-analyze.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'status' })
                          });
                          const data = await response.json();
                          setLearnedPatterns(data.patterns);
                        } catch (error) {
                          console.error('Status check failed:', error);
                        }
                      }}
                      className="text-slate-400 hover:text-white text-sm underline"
                    >
                      학습 현황 확인
                    </button>
                  </div>

                  {/* 학습된 패턴 표시 */}
                  {learnedPatterns && Object.keys(learnedPatterns).length > 0 && (
                    <div className="p-4 bg-purple-500/10 rounded-xl border border-purple-500/20">
                      <h4 className="text-purple-400 font-medium mb-2">학습된 스타일</h4>
                      <pre className="text-slate-300 text-sm overflow-x-auto">
                        {JSON.stringify(learnedPatterns, null, 2)}
                      </pre>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {!draftPreviewId && activeTab === 'workspace' && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">AI Workspace</h2>
                <p className="text-slate-400">GPT 5.2 + RAG 기반 외교/정책 전문가 AI와 실시간 대화. 기사를 분석·개선하고, 크리틱을 학습시킵니다.</p>
              </div>
              <AIWorkspace articleContext={workspaceArticleContext} />
              <div>
                <h3 className="text-lg font-semibold text-white mb-3">Critique Training</h3>
                <p className="text-slate-400 text-sm mb-4">편집자 피드백을 구조화하여 저장하면 RAG에 자동 반영됩니다.</p>
                <CritiqueEditor
                  newsId={editingNewsId ?? undefined}
                  articleUrl={articleUrl || undefined}
                  articleTitle={newsTitle || undefined}
                />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-white mb-3">RAG 검색 테스트</h3>
                <p className="text-slate-400 text-sm mb-4">쿼리로 관련 크리틱/분석을 검색하고, 시스템 프롬프트 주입 결과를 확인합니다.</p>
                <RAGTester />
              </div>
            </div>
          )}

          {!draftPreviewId && activeTab === 'persona' && (
            <div className="space-y-8">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">GPT 페르소나</h2>
                <p className="text-slate-400">대화로 페르소나를 정의하고 DB에 저장. 실제 서비스에서 사용되는 system prompt를 관리합니다.</p>
              </div>

              {/* 1. Persona Playground */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                  <ChatBubbleLeftRightIcon className="w-5 h-5 text-cyan-400" />
                  Persona Playground
                </h3>
                <p className="text-slate-400 text-sm mb-4">GPT와 대화하며 The Gist 에디터 페르소나(톤·스타일·원칙)를 정의하세요. 완료 후 &quot;페르소나 추출 &amp; 저장&quot;을 누르면 system prompt로 DB에 저장됩니다.</p>

                <div className="space-y-4">
                  <div className="bg-slate-900/70 rounded-xl border border-slate-700/50 max-h-80 overflow-y-auto p-4 space-y-3">
                    {personaMessages.length === 0 && (
                      <p className="text-slate-500 text-sm">대화를 시작하세요. 예: &quot;당신은 The Gist의 수석 에디터입니다. 톤은 친근하면서도 전문적이어야 합니다.&quot;</p>
                    )}
                    {personaMessages.map((m, i) => (
                      <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                        <div className={`max-w-[85%] rounded-lg px-4 py-2 text-sm ${m.role === 'user' ? 'bg-cyan-500/20 text-cyan-100' : 'bg-slate-700/50 text-slate-200'}`}>
                          {m.content}
                        </div>
                      </div>
                    ))}
                    {personaLoading && (
                      <div className="flex justify-start">
                        <div className="bg-slate-700/50 rounded-lg px-4 py-2 text-sm text-slate-400 animate-pulse">생성 중...</div>
                      </div>
                    )}
                    <div ref={personaChatEndRef} />
                  </div>

                  <div className="flex gap-2 flex-wrap">
                    <input
                      type="text"
                      value={personaInput}
                      onChange={(e) => setPersonaInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                          e.preventDefault();
                          if (personaInput.trim() && !personaLoading) {
                            const msg = personaInput.trim();
                            setPersonaMessages((prev) => [...prev, { role: 'user', content: msg }]);
                            setPersonaInput('');
                            setPersonaLoading(true);
                            fetch('/api/admin/persona-api.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json' },
                              body: JSON.stringify({
                                action: 'chat',
                                message: msg,
                                history: personaMessages,
                              }),
                            })
                              .then((res) => res.ok ? res : Promise.reject(new Error('SSE failed')))
                              .then(async (res) => {
                                const reader = res.body?.getReader();
                                if (!reader) return;
                                const decoder = new TextDecoder();
                                let buf = '';
                                let fullText = '';
                                while (true) {
                                  const { done, value } = await reader.read();
                                  if (done) break;
                                  buf += decoder.decode(value, { stream: true });
                                  const lines = buf.split('\n');
                                  buf = lines.pop() || '';
                                  for (const line of lines) {
                                    if (line.startsWith('data: ')) {
                                      try {
                                        const raw = line.slice(6).trim();
                                        if (raw === '[DONE]' || raw === '') continue;
                                        const data = JSON.parse(raw);
                                        if (data.full_text) fullText = data.full_text;
                                        else if (data.text) fullText += data.text;
                                      } catch {}
                                    }
                                  }
                                }
                                if (fullText) {
                                  setPersonaMessages((prev) => [...prev, { role: 'assistant', content: fullText }]);
                                }
                              })
                              .catch(() => setPersonaMessages((prev) => [...prev, { role: 'assistant', content: '오류가 발생했습니다. API 키와 네트워크를 확인하세요.' }]))
                              .finally(() => setPersonaLoading(false));
                          }
                        }
                      }}
                      placeholder="메시지 입력 후 Enter..."
                      className="flex-1 min-w-[200px] bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-cyan-500 outline-none"
                    />
                    <input
                      type="text"
                      value={personaName}
                      onChange={(e) => setPersonaName(e.target.value)}
                      placeholder="페르소나 이름"
                      className="w-48 bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-cyan-500 outline-none"
                    />
                    <button
                      type="button"
                      onClick={async () => {
                        if (personaMessages.length === 0) {
                          setSaveMessage({ type: 'error', text: '대화 내용이 없습니다.' });
                          return;
                        }
                        setPersonaExtracting(true);
                        setSaveMessage(null);
                        try {
                          const res = await fetch('/api/admin/persona-api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                              action: 'extract_and_save',
                              history: personaMessages,
                              name: personaName.trim() || 'The Gist 수석 에디터 v1',
                            }),
                          });
                          const d = await res.json();
                          if (d.success) {
                            setSaveMessage({ type: 'success', text: '페르소나가 저장되었습니다.' });
                            setActivePersona(d.persona);
                            fetch('/api/admin/persona-api.php?action=list')
                              .then((r) => r.json())
                              .then((x) => x.success && Array.isArray(x.personas) && setPersonaList(x.personas));
                          } else {
                            setSaveMessage({ type: 'error', text: d.error || '저장 실패' });
                          }
                        } catch (e) {
                          setSaveMessage({ type: 'error', text: '요청 실패: ' + (e as Error).message });
                        } finally {
                          setPersonaExtracting(false);
                        }
                      }}
                      disabled={personaExtracting || personaMessages.length === 0}
                      className="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {personaExtracting ? '추출 중...' : '페르소나 추출 & 저장'}
                    </button>
                  </div>
                </div>
              </div>

              {/* 2. 활성 페르소나 & 목록 */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                  <h3 className="text-lg font-semibold text-white mb-3">활성 페르소나</h3>
                  {activePersona ? (
                    <div className="space-y-2">
                      <p className="text-cyan-400 font-medium">{activePersona.name}</p>
                      <pre className="text-slate-300 text-xs whitespace-pre-wrap break-words max-h-40 overflow-y-auto bg-slate-900/50 rounded p-3">
                        {activePersona.system_prompt}
                      </pre>
                    </div>
                  ) : (
                    <p className="text-slate-500 text-sm">활성 페르소나가 없습니다. Playground에서 정의 후 저장하세요.</p>
                  )}
                </div>
                <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                  <h3 className="text-lg font-semibold text-white mb-3">저장된 페르소나 목록</h3>
                  {personaList.length === 0 ? (
                    <p className="text-slate-500 text-sm">저장된 페르소나가 없습니다.</p>
                  ) : (
                    <ul className="space-y-2">
                      {personaList.map((p) => (
                        <li key={p.id} className="flex items-center justify-between py-2 border-b border-slate-700/50 last:border-0">
                          <span className="text-slate-300 text-sm">{p.name} {p.is_active && <span className="text-cyan-400 text-xs">(활성)</span>}</span>
                          {!p.is_active && (
                            <button
                              type="button"
                              onClick={() => {
                                fetch('/api/admin/persona-api.php', {
                                  method: 'POST',
                                  headers: { 'Content-Type': 'application/json' },
                                  body: JSON.stringify({ action: 'set_active', persona_id: p.id }),
                                })
                                  .then((r) => r.json())
                                  .then((d) => {
                                    if (d.success) {
                                      setSaveMessage({ type: 'success', text: '활성 페르소나가 변경되었습니다.' });
                                      setActivePersona(p);
                                      fetch('/api/admin/persona-api.php?action=active').then((x) => x.json()).then((x) => x.success && x.persona && setActivePersona(x.persona));
                                      fetch('/api/admin/persona-api.php?action=list').then((x) => x.json()).then((x) => x.success && Array.isArray(x.personas) && setPersonaList(x.personas));
                                    }
                                  });
                              }}
                              className="text-cyan-400 hover:text-cyan-300 text-xs"
                            >
                              활성화
                            </button>
                          )}
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </div>

              {/* 3. Persona Tester - 일관성 점검 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <h3 className="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                  <CheckCircleIcon className="w-5 h-5 text-emerald-400" />
                  일관성 점검 (Persona Tester)
                </h3>
                <p className="text-slate-400 text-sm mb-4">실제 파이프라인으로 기사를 분석하고, 페르소나 일관성(지스터 언급, 글자 수 등)을 점검합니다.</p>
                <div className="flex gap-2 flex-wrap mb-4">
                  <input
                    type="text"
                    value={personaTestUrl}
                    onChange={(e) => setPersonaTestUrl(e.target.value)}
                    placeholder="기사 URL"
                    className="flex-1 min-w-[200px] bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-cyan-500 outline-none"
                  />
                  <input
                    type="text"
                    value={personaTestArticleId}
                    onChange={(e) => setPersonaTestArticleId(e.target.value)}
                    placeholder="또는 기사 ID"
                    className="w-28 bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-cyan-500 outline-none"
                  />
                  <button
                    type="button"
                    onClick={async () => {
                      if (!personaTestUrl.trim() && !personaTestArticleId.trim()) {
                        setSaveMessage({ type: 'error', text: 'URL 또는 기사 ID를 입력하세요.' });
                        return;
                      }
                      setPersonaTestLoading(true);
                      setPersonaTestResult(null);
                      setSaveMessage(null);
                      try {
                        const res = await fetch('/api/admin/persona-api.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({
                            action: 'test_consistency',
                            url: personaTestUrl.trim() || undefined,
                            article_id: personaTestArticleId.trim() ? parseInt(personaTestArticleId, 10) : undefined,
                          }),
                        });
                        const d = await res.json();
                        if (d.success) {
                          setPersonaTestResult({ analysis_result: d.analysis_result, checklist: d.checklist });
                          setSaveMessage({ type: 'success', text: `점검 완료. 점수: ${d.checklist?.score ?? '-'}` });
                        } else {
                          setSaveMessage({ type: 'error', text: d.error || '테스트 실패' });
                        }
                      } catch (e) {
                        setSaveMessage({ type: 'error', text: '요청 실패: ' + (e as Error).message });
                      } finally {
                        setPersonaTestLoading(false);
                      }
                    }}
                    disabled={personaTestLoading}
                    className="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {personaTestLoading ? '분석 중...' : '테스트 실행'}
                  </button>
                </div>
                {personaTestResult && (
                  <div className="space-y-4 mt-4 pt-4 border-t border-slate-700/50">
                    <div>
                      <h4 className="text-slate-300 font-medium mb-2">분석 결과</h4>
                      <pre className="text-slate-400 text-xs whitespace-pre-wrap bg-slate-900/50 rounded p-3 max-h-48 overflow-y-auto">
                        {JSON.stringify(personaTestResult.analysis_result, null, 2)}
                      </pre>
                    </div>
                    <div>
                      <h4 className="text-slate-300 font-medium mb-2">체크리스트</h4>
                      <ul className="text-sm space-y-1">
                        {personaTestResult.checklist && Object.entries(personaTestResult.checklist).map(([k, v]) => (
                          <li key={k} className="flex items-center gap-2">
                            {typeof v === 'boolean' ? (
                              v ? <CheckCircleIcon className="w-4 h-4 text-emerald-400" /> : <ExclamationTriangleIcon className="w-4 h-4 text-amber-400" />
                            ) : null}
                            <span className="text-slate-400">{k}:</span>
                            <span className="text-slate-200">{String(v)}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {!draftPreviewId && activeTab === 'knowledge' && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-white mb-2">이론 라이브러리</h2>
                <p className="text-slate-400">
                  국제정치 이론, 지정학 프레임워크, 경제/금융 패턴 등을 등록하면 GPT 분석 시 RAG로 자동 참조됩니다.
                </p>
              </div>

              {/* 프레임워크 추가 폼 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
                <h3 className="text-lg font-semibold text-white flex items-center gap-2">
                  <BookOpenIcon className="w-5 h-5 text-amber-400" />
                  프레임워크 추가
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-slate-400 text-sm mb-1">카테고리</label>
                    <select
                      value={knFormCategory}
                      onChange={(e) => setKnFormCategory(e.target.value)}
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:ring-1 focus:ring-amber-500 outline-none"
                    >
                      {KNOWLEDGE_CATEGORIES.map(c => (
                        <option key={c.value} value={c.value}>{c.label}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-slate-400 text-sm mb-1">프레임워크명</label>
                    <input
                      type="text"
                      value={knFormFramework}
                      onChange={(e) => setKnFormFramework(e.target.value)}
                      placeholder="예: realism, liberalism, constructivism..."
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-amber-500 outline-none"
                    />
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-slate-400 text-sm mb-1">제목</label>
                    <input
                      type="text"
                      value={knFormTitle}
                      onChange={(e) => setKnFormTitle(e.target.value)}
                      placeholder="예: 현실주의 국제정치 이론 (케네스 왈츠)"
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-amber-500 outline-none"
                    />
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-slate-400 text-sm mb-1">내용 (프레임워크 설명/원칙)</label>
                    <textarea
                      value={knFormContent}
                      onChange={(e) => setKnFormContent(e.target.value)}
                      placeholder="프레임워크의 핵심 원칙, 주요 개념, 분석 관점 등을 설명하세요..."
                      rows={5}
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-amber-500 outline-none resize-none"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 text-sm mb-1">키워드 (쉼표 구분)</label>
                    <input
                      type="text"
                      value={knFormKeywords}
                      onChange={(e) => setKnFormKeywords(e.target.value)}
                      placeholder="예: 현실주의, 세력균형, 안보딜레마"
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-amber-500 outline-none"
                    />
                  </div>
                  <div>
                    <label className="block text-slate-400 text-sm mb-1">출처 (선택)</label>
                    <input
                      type="text"
                      value={knFormSource}
                      onChange={(e) => setKnFormSource(e.target.value)}
                      placeholder="예: Kenneth Waltz, Theory of International Politics"
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:ring-1 focus:ring-amber-500 outline-none"
                    />
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <button
                    onClick={addKnowledgeFramework}
                    disabled={isAddingKnowledge || !knFormTitle.trim() || !knFormContent.trim() || !knFormFramework.trim()}
                    className="px-5 py-2.5 rounded-lg font-medium transition flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {isAddingKnowledge ? (
                      <span className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
                    ) : (
                      <BookOpenIcon className="w-5 h-5" />
                    )}
                    {isAddingKnowledge ? '추가 중...' : '프레임워크 추가'}
                  </button>
                </div>

                {knowledgeMessage && (
                  <div className={`p-3 rounded-lg text-sm flex items-center gap-2 ${
                    knowledgeMessage.type === 'success'
                      ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                      : 'bg-red-500/20 text-red-400 border border-red-500/30'
                  }`}>
                    {knowledgeMessage.type === 'success' ? <CheckCircleIcon className="w-4 h-4" /> : <ExclamationTriangleIcon className="w-4 h-4" />}
                    {knowledgeMessage.text}
                  </div>
                )}
              </div>

              {/* 카테고리 필터 + 목록 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <div className="flex items-center justify-between mb-4 flex-wrap gap-3">
                  <h3 className="text-lg font-semibold text-white">등록된 프레임워크</h3>
                  <div className="flex gap-2 flex-wrap">
                    <button
                      onClick={() => setKnowledgeCategory('all')}
                      className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${
                        knowledgeCategory === 'all' ? 'bg-amber-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                      }`}
                    >
                      전체
                    </button>
                    {KNOWLEDGE_CATEGORIES.map(c => (
                      <button
                        key={c.value}
                        onClick={() => setKnowledgeCategory(c.value)}
                        className={`px-3 py-1.5 rounded-lg text-xs font-medium transition ${
                          knowledgeCategory === c.value ? 'bg-amber-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                        }`}
                      >
                        {c.label}
                      </button>
                    ))}
                  </div>
                </div>

                {knowledgeLoading ? (
                  <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-4 border-amber-500 border-t-transparent"></div>
                  </div>
                ) : knowledgeItems.length === 0 ? (
                  <p className="text-slate-500 text-center py-8">등록된 프레임워크가 없습니다. 위에서 추가해보세요.</p>
                ) : (
                  <div className="space-y-3 max-h-[600px] overflow-y-auto">
                    {knowledgeItems.map((item) => (
                      <div key={item.id} className="p-4 bg-slate-900/50 rounded-xl border border-slate-700/30 hover:border-amber-500/30 transition">
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1 flex-wrap">
                              <span className="text-xs px-2 py-0.5 bg-amber-500/20 text-amber-400 rounded font-medium">
                                {KNOWLEDGE_CATEGORIES.find(c => c.value === item.category)?.label || item.category}
                              </span>
                              <span className="text-xs px-2 py-0.5 bg-slate-700 text-slate-300 rounded">
                                {item.framework_name}
                              </span>
                              {item.source && (
                                <span className="text-xs text-slate-500">출처: {item.source}</span>
                              )}
                            </div>
                            <h4 className="text-white font-medium">{item.title}</h4>
                            <p className="text-slate-400 text-sm mt-1 line-clamp-3">{item.content}</p>
                            {item.keywords && item.keywords.length > 0 && (
                              <div className="flex gap-1 mt-2 flex-wrap">
                                {item.keywords.map((kw, i) => (
                                  <span key={i} className="text-xs px-1.5 py-0.5 bg-slate-800 text-slate-400 rounded">
                                    {kw}
                                  </span>
                                ))}
                              </div>
                            )}
                          </div>
                          <button
                            onClick={() => deleteKnowledgeItem(item.id)}
                            className="p-2 rounded-lg text-slate-400 hover:text-red-400 hover:bg-red-500/10 transition shrink-0"
                            title="삭제"
                          >
                            <TrashIcon className="w-5 h-5" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}

          {!draftPreviewId && activeTab === 'usage' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <div>
                  <h2 className="text-2xl font-bold text-white">API 과금 대시보드</h2>
                  <p className="text-slate-400 text-sm mt-1">OpenAI, Google TTS, Kakao 등 API 사용량·과금 실시간 확인</p>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setUsageLoading(true);
                    fetch('/api/admin/usage-dashboard.php')
                      .then((r) => r.json())
                      .then((d) => {
                        if (d.success) setUsageData(d);
                      })
                      .finally(() => setUsageLoading(false));
                  }}
                  disabled={usageLoading}
                  className="flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm transition disabled:opacity-50"
                >
                  <ArrowPathIcon className={`w-4 h-4 ${usageLoading ? 'animate-spin' : ''}`} />
                  새로고침
                </button>
              </div>

              {usageLoading ? (
                <div className="flex justify-center py-12">
                  <div className="animate-spin rounded-full h-10 w-10 border-2 border-cyan-500 border-t-transparent" />
                </div>
              ) : usageData ? (
                <div className="space-y-6">
                  {/* API 연동 상태 */}
                  <div className="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    {[
                      { key: 'openai', name: 'OpenAI', label: 'GPT / DALL-E / Embeddings' },
                      { key: 'google_tts', name: 'Google TTS', label: '음성 합성' },
                      { key: 'kakao', name: 'Kakao', label: '로그인 / 카카오톡' },
                      { key: 'supabase', name: 'Supabase', label: 'DB / RAG / Auth' },
                      { key: 'nyt', name: 'NYT', label: '뉴스 API' },
                    ].map(({ key, name, label }) => {
                      const p = usageData.providers?.[key];
                      const configured = p?.configured ?? false;
                      return (
                        <div
                          key={key}
                          className={`rounded-xl p-4 border ${configured ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-slate-800/50 border-slate-700/50'}`}
                        >
                          <div className="flex items-center justify-between">
                            <span className="font-semibold text-white">{name}</span>
                            {configured ? (
                              <CheckCircleIcon className="w-5 h-5 text-emerald-400" />
                            ) : (
                              <ExclamationTriangleIcon className="w-5 h-5 text-amber-400" />
                            )}
                          </div>
                          <p className="text-slate-400 text-xs mt-1">{label}</p>
                          {p?.dashboard_url && (
                            <a
                              href={p.dashboard_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="block mt-2 text-cyan-400 text-xs hover:underline"
                            >
                              대시보드 열기 →
                            </a>
                          )}
                        </div>
                      );
                    })}
                  </div>

                  {/* 자체 사용량 로그 (실시간) */}
                  {usageData.self_tracked?.has_table && (
                    <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                      <h3 className="text-lg font-semibold text-slate-200 mb-4">실시간 사용량 (자체 로그)</h3>
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead>
                            <tr className="text-left text-slate-400 border-b border-slate-700">
                              <th className="pb-3 pr-4">Provider</th>
                              <th className="pb-3 pr-4">Endpoint</th>
                              <th className="pb-3 pr-4">Input (tokens)</th>
                              <th className="pb-3 pr-4">Output (tokens)</th>
                              <th className="pb-3 pr-4">Images</th>
                              <th className="pb-3 pr-4">Characters</th>
                              <th className="pb-3 pr-4">이번 달</th>
                            </tr>
                          </thead>
                          <tbody>
                            {Object.entries(usageData.self_tracked.by_provider || {}).map(([provider, row]) => {
                              const r = row as { input_tokens?: number; output_tokens?: number; images?: number; characters?: number; cost_usd?: number };
                              return (
                                <tr key={provider} className="border-b border-slate-700/50">
                                  <td className="py-3 pr-4"><span className="capitalize">{provider.replace('_', ' ')}</span></td>
                                  <td className="py-3 pr-4">-</td>
                                  <td className="py-3 pr-4">{r.input_tokens?.toLocaleString() ?? '-'}</td>
                                  <td className="py-3 pr-4">{r.output_tokens?.toLocaleString() ?? '-'}</td>
                                  <td className="py-3 pr-4">{r.images?.toLocaleString() ?? '-'}</td>
                                  <td className="py-3 pr-4">{r.characters?.toLocaleString() ?? '-'}</td>
                                  <td className="py-3 pr-4">{r.cost_usd != null ? `$${Number(r.cost_usd).toFixed(4)}` : '-'}</td>
                                </tr>
                              );
                            })}
                            {Object.keys(usageData.self_tracked.by_provider || {}).length === 0 && (
                              <tr>
                                <td colSpan={7} className="py-6 text-center text-slate-500">
                                  아직 사용량 로그가 없습니다. API 호출 후 여기에 표시됩니다.
                                </td>
                              </tr>
                            )}
                          </tbody>
                        </table>
                      </div>
                      <p className="text-slate-500 text-xs mt-4">
                        GPT 분석, 채팅, 썸네일 생성, TTS 등 모든 API 호출 시 자동으로 기록됩니다.
                      </p>
                    </div>
                  )}

                  {!usageData.self_tracked?.has_table && (
                    <div className="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4">
                      <p className="text-amber-200 text-sm mb-4">
                        api_usage_logs 테이블이 없습니다. 아래 버튼을 눌러 마이그레이션을 실행해주세요.
                      </p>
                      <button
                        type="button"
                        onClick={async () => {
                          setSaveMessage({ type: 'info', text: '마이그레이션 실행 중...' });
                          try {
                            const r = await fetch('/api/admin/run-usage-migration.php');
                            const d = await r.json();
                            if (d.success) {
                              setSaveMessage({ type: 'success', text: d.message });
                              setUsageLoading(true);
                              fetch('/api/admin/usage-dashboard.php')
                                .then((res) => res.json())
                                .then((data) => {
                                  if (data.success) setUsageData(data);
                                })
                                .finally(() => setUsageLoading(false));
                            } else {
                              setSaveMessage({ type: 'error', text: d.message || '마이그레이션 실패' });
                            }
                          } catch (e) {
                            setSaveMessage({ type: 'error', text: '마이그레이션 요청 실패: ' + (e as Error).message });
                          }
                          setTimeout(() => setSaveMessage(null), 5000);
                        }}
                        className="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium transition"
                      >
                        api_usage_logs 테이블 생성
                      </button>
                    </div>
                  )}

                  {/* OpenAI Usage API 오류 */}
                  {usageData.providers?.openai?.usage_error && (
                    <div className="bg-slate-800/50 rounded-xl p-4 border border-slate-700">
                      <p className="text-amber-400 text-sm">{usageData.providers.openai.usage_error}</p>
                      <p className="text-slate-500 text-xs mt-2">
                        <a href="https://platform.openai.com/settings/organization/api-keys" target="_blank" rel="noopener noreferrer" className="text-cyan-400 hover:underline">
                          OpenAI Organization Admin API 키
                        </a>
                        를 설정하면 Usage API에서 직접 사용량을 가져올 수 있습니다.
                      </p>
                    </div>
                  )}
                </div>
              ) : (
                <p className="text-slate-500">데이터를 불러오지 못했습니다.</p>
              )}
            </div>
          )}

          {!draftPreviewId && activeTab === 'settings' && (
            <div className="space-y-6">
              <h2 className="text-2xl font-bold text-white">설정</h2>

              {/* TTS / Voice */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
                <h3 className="text-lg font-semibold text-slate-200">TTS / Voice</h3>
                <p className="text-slate-400 text-sm">분석 결과 음성 읽기에 사용할 Google TTS 보이스를 선택하세요.</p>
                {settingsLoading ? (
                  <p className="text-slate-400">설정 불러오는 중...</p>
                ) : (
                  <>
                    <div>
                      <label className="block text-slate-300 mb-2">보이스</label>
                      <select
                        value={ttsVoice}
                        onChange={(e) => setTtsVoice(e.target.value)}
                        className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-cyan-500"
                      >
                        {GOOGLE_TTS_VOICES.map((opt) => (
                          <option key={opt.value} value={opt.value}>
                            {opt.label}
                          </option>
                        ))}
                      </select>
                    </div>
                    {settingsError && (
                      <p className="text-red-400 text-sm">{settingsError}</p>
                    )}
                    {settingsSuccess && (
                      <p className="text-emerald-400 text-sm">{settingsSuccess}</p>
                    )}
                    <button
                      type="button"
                      onClick={saveTtsVoice}
                      disabled={settingsSaving}
                      className="bg-gradient-to-r from-cyan-500 to-emerald-500 text-white px-6 py-2 rounded-lg hover:opacity-90 transition disabled:opacity-50"
                    >
                      {settingsSaving ? '저장 중...' : 'Voice 설정 저장'}
                    </button>
                  </>
                )}
              </div>

              {/* GPT 분석 API 상태 확인 */}
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
                <h3 className="text-lg font-semibold text-slate-200">GPT 분석 API</h3>
                <p className="text-slate-400 text-sm">API 키·env 확인 후, 실제 OpenAI 호출이 되는지 테스트합니다.</p>
                <div className="flex flex-wrap gap-2">
                  <button
                    type="button"
                    onClick={async () => {
                      try {
                        const r = await fetch('/api/admin/ai-analyze.php');
                        const d = await r.json();
                        const msg = d.success
                          ? `상태: ${d.status} | Mock: ${d.mock_mode ? '예' : '아니오'} | API키: ${d.openai_key_set ? '설정됨' : '없음'} | env: ${d.env_loaded ? '로드됨' : '없음'} | root: ${(d.project_root || '').slice(-40)}`
                          : `오류: ${d.error || r.status}`;
                        setSaveMessage({ type: d.openai_key_set ? 'success' : 'error', text: msg });
                        if (d.env_tried?.length) console.log('env_tried', d.env_tried);
                      } catch (e) {
                        setSaveMessage({ type: 'error', text: '상태 확인 실패: ' + (e as Error).message });
                      }
                    }}
                    className="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-sm transition"
                  >
                    API 상태 확인
                  </button>
                  <button
                    type="button"
                    onClick={async () => {
                      setSaveMessage({ type: 'info', text: 'GPT API 호출 테스트 중...' });
                      try {
                        const r = await fetch('/api/admin/ai-analyze.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({ action: 'test_openai' })
                        });
                        const d = await r.json();
                        if (d.success) {
                          setSaveMessage({ type: 'success', text: `GPT API 호출 성공 (${d.duration_ms}ms): ${d.response ?? ''}` });
                        } else {
                          setSaveMessage({ type: 'error', text: `${d.message ?? d.error ?? '실패'}${d.debug?.mock_mode ? ' (Mock 모드)' : ''}` });
                        }
                      } catch (e) {
                        setSaveMessage({ type: 'error', text: '테스트 요청 실패: ' + (e as Error).message });
                      }
                    }}
                    className="bg-cyan-600 hover:bg-cyan-500 text-white px-4 py-2 rounded-lg text-sm transition"
                  >
                    GPT API 호출 테스트
                  </button>
                  <button
                    type="button"
                    onClick={async () => {
                      setSaveMessage({ type: 'info', text: 'DALL-E 3 썸네일 연동 테스트 중... (약 30초 소요)' });
                      try {
                        const r = await fetch('/api/admin/ai-analyze.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({ action: 'test_dalle' })
                        });
                        const d = await r.json();
                        if (d.success) {
                          setSaveMessage({ type: 'success', text: `DALL-E 3 연동 성공 (${d.debug?.duration_ms ?? 0}ms) - 생성된 이미지: ${d.image_url ?? ''}` });
                        } else {
                          setSaveMessage({ type: 'error', text: `${d.message ?? d.error ?? '실패'}${d.debug?.mock_mode ? ' (Mock 모드)' : ''}` });
                        }
                      } catch (e) {
                        setSaveMessage({ type: 'error', text: 'DALL-E 테스트 요청 실패: ' + (e as Error).message });
                      }
                    }}
                    className="bg-purple-600 hover:bg-purple-500 text-white px-4 py-2 rounded-lg text-sm transition"
                  >
                    DALL-E 3 테스트
                  </button>
                </div>
              </div>

              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 space-y-4">
                <div>
                  <label className="block text-slate-300 mb-2">NYT API Key</label>
                  <input
                    type="text"
                    placeholder="YOUR_NYT_API_KEY"
                    className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-4 py-2 text-white"
                  />
                </div>
                <div>
                  <label className="block text-slate-300 mb-2">Kakao API Key</label>
                  <input
                    type="text"
                    placeholder="YOUR_KAKAO_API_KEY"
                    className="w-full bg-slate-900/50 border border-slate-700 rounded-lg px-4 py-2 text-white"
                  />
                </div>
                <button className="bg-gradient-to-r from-cyan-500 to-emerald-500 text-white px-6 py-2 rounded-lg hover:opacity-90 transition">
                  설정 저장
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>

    {/* 회원 상세 모달 */}
    {selectedUserDetail && (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60" onClick={() => setSelectedUserDetail(null)}>
        <div className="bg-slate-800 rounded-2xl p-6 max-w-md w-full mx-4 border border-slate-600 shadow-xl" onClick={(e) => e.stopPropagation()}>
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-semibold text-white">회원 상세</h3>
            <button type="button" onClick={() => setSelectedUserDetail(null)} className="p-1 rounded hover:bg-slate-700 text-slate-400">
              <XMarkIcon className="w-5 h-5" />
            </button>
          </div>
          <div className="space-y-3 text-sm">
            <p><span className="text-slate-500">닉네임:</span> <span className="text-white">{selectedUserDetail.nickname}</span></p>
            <p><span className="text-slate-500">이메일:</span> <span className="text-white">{selectedUserDetail.email || '-'}</span></p>
            <p><span className="text-slate-500">상태:</span> <span className={`px-2 py-0.5 rounded text-xs ${selectedUserDetail.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-500/20 text-slate-400'}`}>{selectedUserDetail.status}</span></p>
            <p><span className="text-slate-500">가입일:</span> <span className="text-slate-300">{selectedUserDetail.created_at ? new Date(selectedUserDetail.created_at).toLocaleString('ko-KR') : '-'}</span></p>
            <p><span className="text-slate-500">최근 로그인:</span> <span className="text-slate-300">{selectedUserDetail.last_login_at ? new Date(selectedUserDetail.last_login_at).toLocaleString('ko-KR') : '-'}</span></p>
            {selectedUserDetail.usage && (
              <div className="pt-3 border-t border-slate-600">
                <p className="text-slate-400 font-medium mb-2">사용 통계</p>
                <p><span className="text-slate-500">분석 횟수:</span> <span className="text-white">{selectedUserDetail.usage.analyses_count}</span></p>
                <p><span className="text-slate-500">북마크:</span> <span className="text-white">{selectedUserDetail.usage.bookmarks_count}</span></p>
                <p><span className="text-slate-500">검색 횟수:</span> <span className="text-white">{selectedUserDetail.usage.search_count}</span></p>
              </div>
            )}
          </div>
        </div>
      </div>
    )}

    {/* GPT 요약 전체 화면 편집 모달 */}
    {isContentFullscreen && (
      <div className="fixed inset-0 z-50 bg-slate-900 flex flex-col">
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-700 bg-slate-800">
          <h2 className="text-white text-lg font-semibold">gpt 요약 편집</h2>
          <button
            type="button"
            onClick={() => setIsContentFullscreen(false)}
            className="p-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 transition"
          >
            <XMarkIcon className="w-5 h-5" />
          </button>
        </div>
        <div className="flex-1 overflow-auto p-6">
          <RichTextEditor
            value={newsContent}
            onChange={setNewsContent}
            sanitizePaste={(t) => sanitizeText(t).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>')}
            placeholder="뉴스 본문을 작성하세요..."
            rows={30}
            className="w-full bg-slate-900/50 border border-slate-700 rounded-xl min-h-[70vh]"
          />
        </div>
        <div className="px-6 py-3 border-t border-slate-700 bg-slate-800 flex items-center justify-between">
          <p className="text-slate-400 text-sm">{newsContent.length} / 10,000자</p>
          <button
            type="button"
            onClick={() => setIsContentFullscreen(false)}
            className="px-6 py-2 rounded-lg bg-gradient-to-r from-cyan-500 to-emerald-500 text-white font-medium hover:opacity-90 transition"
          >
            편집 완료
          </button>
        </div>
      </div>
    )}
  </>
  );
};

export default AdminPage;
