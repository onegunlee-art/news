import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { formatSourceDisplayName } from '../utils/formatSource';
import {
  ChartBarIcon,
  UsersIcon,
  NewspaperIcon,
  CogIcon,
  ArrowTrendingUpIcon,
  ClockIcon,
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
} from '@heroicons/react/24/outline';
import RichTextToolbar from '../components/Common/RichTextToolbar';
import AIWorkspace from '../components/AIWorkspace/AIWorkspace';
import type { ArticleContext } from '../components/AIWorkspace/AIWorkspace';
import CritiqueEditor from '../components/CritiqueEditor/CritiqueEditor';
import RAGTester from '../components/RAGTester/RAGTester';
import { adminSettingsApi, ttsApi } from '../services/api';

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

interface DashboardStats {
  totalUsers: number;
  totalNews: number;
  totalAnalyses: number;
  todayUsers: number;
  todayAnalyses: number;
  apiStatus: {
    nyt: boolean;
    kakao: boolean;
    database: boolean;
  };
}

interface RecentActivity {
  id: number;
  type: 'user' | 'analysis' | 'news';
  message: string;
  time: string;
}

interface NewsArticle {
  id?: number;
  category: string;
  title: string;
  description?: string;
  content: string;
  why_important?: string;
  narration?: string;
  future_prediction?: string;
  source?: string;
  source_url?: string;
  original_source?: string;
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

const AdminPage: React.FC = () => {
  const navigate = useNavigate();
  const { } = useAuthStore(); // 권한 체크용 (추후 활성화)
  const [activeTab, setActiveTab] = useState<'dashboard' | 'users' | 'news' | 'ai' | 'workspace' | 'knowledge' | 'settings'>('dashboard');

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

  // Admin 설정 (TTS Voice)
  const [ttsVoice, setTtsVoice] = useState<string>('ko-KR-Standard-A');
  const [settingsLoading, setSettingsLoading] = useState(false);
  const [settingsSaving, setSettingsSaving] = useState(false);
  const [settingsError, setSettingsError] = useState<string | null>(null);
  const [settingsSuccess, setSettingsSuccess] = useState<string | null>(null);

  // 뉴스 관리 상태
  const [selectedCategory, setSelectedCategory] = useState<string>('diplomacy');
  const [newsTitle, setNewsTitle] = useState('');
  const [newsContent, setNewsContent] = useState('');
  const [newsWhyImportant, setNewsWhyImportant] = useState('');
  const [newsNarration, setNewsNarration] = useState('');
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
  const [showExtractedInfo, setShowExtractedInfo] = useState(false);
  const [isRegeneratingThumbnail, setIsRegeneratingThumbnail] = useState(false);
  const [isRegeneratingDalle, setIsRegeneratingDalle] = useState(false);
  const [dallePrompt, setDallePrompt] = useState('');
  const [isRegeneratingTts, setIsRegeneratingTts] = useState(false);
  const [regeneratedTtsUrl, setRegeneratedTtsUrl] = useState<string | null>(null);
  const refArticleSummary = useRef<HTMLTextAreaElement>(null);
  const refNewsContent = useRef<HTMLTextAreaElement>(null);
  const refNewsNarration = useRef<HTMLTextAreaElement>(null);
  const refNewsWhyImportant = useRef<HTMLTextAreaElement>(null);

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

  const saveTtsVoice = () => {
    setSettingsSaving(true);
    setSettingsError(null);
    setSettingsSuccess(null);
    adminSettingsApi
      .updateSettings({ tts_voice: ttsVoice })
      .then(() => {
        setSettingsError(null);
        setSettingsSuccess('보이스 설정이 저장되었습니다. 다음 AI 분석부터 적용됩니다.');
        setTimeout(() => setSettingsSuccess(null), 4000);
      })
      .catch((err) => setSettingsError(err.response?.data?.message || '저장에 실패했습니다.'))
      .finally(() => setSettingsSaving(false));
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
  
  const [stats, setStats] = useState<DashboardStats>({
    totalUsers: 0,
    totalNews: 0,
    totalAnalyses: 0,
    todayUsers: 0,
    todayAnalyses: 0,
    apiStatus: {
      nyt: false,
      kakao: false,
      database: false,
    },
  });
  const [recentActivities, setRecentActivities] = useState<RecentActivity[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // 권한 체크 (실제 환경에서는 API 호출)
    // if (!isAuthenticated || user?.role !== 'admin') {
    //   navigate('/');
    //   return;
    // }

    loadDashboardData();
  }, []);

  // 뉴스 탭이 활성화되거나 카테고리가 변경될 때 뉴스 목록 로드
  useEffect(() => {
    if (activeTab === 'news') {
      loadNewsList();
    }
  }, [activeTab, selectedCategory]);

  // 기존 뉴스 목록 로드
  const loadNewsList = async () => {
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
  };

  // 뉴스 수정 시작
  const handleEditNews = (news: NewsArticle) => {
    setEditingNewsId(news.id || null);
    setNewsTitle(news.title);
    setNewsContent(news.content);
    setNewsWhyImportant(news.why_important || '');
    setNewsNarration(news.narration || '');
    // 추가 메타데이터 (출처, 작성자, 작성일, 사진)
    setArticleUrl(news.source_url || '');
    setArticleSource(news.original_source || news.source || '');
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
    
    // 실제 API 호출 대신 데모 데이터 사용
    setTimeout(() => {
      setStats({
        totalUsers: 127,
        totalNews: 1543,
        totalAnalyses: 892,
        todayUsers: 23,
        todayAnalyses: 45,
        apiStatus: {
          nyt: true,
          kakao: true,
          database: true,
        },
      });

      setRecentActivities([
        { id: 1, type: 'user', message: '새 사용자가 가입했습니다', time: '5분 전' },
        { id: 2, type: 'analysis', message: '뉴스 분석이 완료되었습니다', time: '12분 전' },
        { id: 3, type: 'news', message: 'NYT에서 새 뉴스를 가져왔습니다', time: '1시간 전' },
        { id: 4, type: 'user', message: '사용자가 로그인했습니다', time: '2시간 전' },
        { id: 5, type: 'analysis', message: '키워드 분석이 실행되었습니다', time: '3시간 전' },
      ]);

      setLoading(false);
    }, 500);
  };

  const StatCard: React.FC<{
    title: string;
    value: number | string;
    icon: React.ReactNode;
    change?: string;
    color: string;
  }> = ({ title, value, icon, change, color }) => (
    <div className={`bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-slate-400 text-sm">{title}</p>
          <p className="text-3xl font-bold text-white mt-2">{value}</p>
          {change && (
            <p className="text-emerald-400 text-sm mt-1 flex items-center gap-1">
              <ArrowTrendingUpIcon className="w-4 h-4" />
              {change}
            </p>
          )}
        </div>
        <div className={`p-4 rounded-xl ${color}`}>
          {icon}
        </div>
      </div>
    </div>
  );

  const ApiStatusBadge: React.FC<{ name: string; status: boolean }> = ({ name, status }) => (
    <div className="flex items-center justify-between py-3 px-4 bg-slate-900/50 rounded-lg">
      <span className="text-slate-300">{name}</span>
      <div className={`flex items-center gap-2 ${status ? 'text-emerald-400' : 'text-red-400'}`}>
        {status ? (
          <>
            <CheckCircleIcon className="w-5 h-5" />
            <span className="text-sm">정상</span>
          </>
        ) : (
          <>
            <ExclamationTriangleIcon className="w-5 h-5" />
            <span className="text-sm">오류</span>
          </>
        )}
      </div>
    </div>
  );

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
    { id: 'ai', name: 'AI 분석', icon: SparklesIcon },
    { id: 'workspace', name: 'AI Workspace', icon: AcademicCapIcon },
    { id: 'knowledge', name: '이론 라이브러리', icon: BookOpenIcon },
    { id: 'settings', name: '설정', icon: CogIcon },
  ] as const;

  return (
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
          {activeTab === 'dashboard' && (
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
                  {/* Stats Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard
                      title="전체 사용자"
                      value={stats.totalUsers}
                      icon={<UsersIcon className="w-6 h-6 text-white" />}
                      change="+12% 이번 주"
                      color="bg-gradient-to-br from-blue-500 to-blue-600"
                    />
                    <StatCard
                      title="저장된 뉴스"
                      value={stats.totalNews.toLocaleString()}
                      icon={<NewspaperIcon className="w-6 h-6 text-white" />}
                      change="+8% 이번 주"
                      color="bg-gradient-to-br from-emerald-500 to-emerald-600"
                    />
                    <StatCard
                      title="분석 완료"
                      value={stats.totalAnalyses}
                      icon={<ChartBarIcon className="w-6 h-6 text-white" />}
                      change="+23% 이번 주"
                      color="bg-gradient-to-br from-purple-500 to-purple-600"
                    />
                    <StatCard
                      title="오늘 분석"
                      value={stats.todayAnalyses}
                      icon={<ClockIcon className="w-6 h-6 text-white" />}
                      color="bg-gradient-to-br from-orange-500 to-orange-600"
                    />
                  </div>

                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* API Status */}
                    <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                      <h3 className="text-lg font-semibold text-white mb-4">API 상태</h3>
                      <div className="space-y-3">
                        <ApiStatusBadge name="NYT News API" status={stats.apiStatus.nyt} />
                        <ApiStatusBadge name="Kakao Login API" status={stats.apiStatus.kakao} />
                        <ApiStatusBadge name="MySQL Database" status={stats.apiStatus.database} />
                      </div>
                    </div>

                    {/* Recent Activity */}
                    <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                      <h3 className="text-lg font-semibold text-white mb-4">최근 활동</h3>
                      <div className="space-y-3">
                        {recentActivities.map((activity) => (
                          <div
                            key={activity.id}
                            className="flex items-center gap-3 py-2 border-b border-slate-700/30 last:border-0"
                          >
                            <div
                              className={`w-2 h-2 rounded-full ${
                                activity.type === 'user'
                                  ? 'bg-blue-400'
                                  : activity.type === 'analysis'
                                  ? 'bg-purple-400'
                                  : 'bg-emerald-400'
                              }`}
                            />
                            <span className="text-slate-300 flex-1">{activity.message}</span>
                            <span className="text-slate-500 text-sm">{activity.time}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>

                  {/* Quick Actions */}
                  <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                    <h3 className="text-lg font-semibold text-white mb-4">빠른 작업</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <NewspaperIcon className="w-8 h-8 text-cyan-400 mb-2" />
                        <p className="text-white font-medium">뉴스 새로고침</p>
                        <p className="text-slate-500 text-sm">NYT API 호출</p>
                      </button>
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <ChartBarIcon className="w-8 h-8 text-purple-400 mb-2" />
                        <p className="text-white font-medium">분석 리포트</p>
                        <p className="text-slate-500 text-sm">통계 다운로드</p>
                      </button>
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <UsersIcon className="w-8 h-8 text-blue-400 mb-2" />
                        <p className="text-white font-medium">사용자 초대</p>
                        <p className="text-slate-500 text-sm">이메일 발송</p>
                      </button>
                      <button className="p-4 bg-slate-900/50 rounded-xl hover:bg-slate-700/50 transition-all text-left">
                        <CogIcon className="w-8 h-8 text-orange-400 mb-2" />
                        <p className="text-white font-medium">캐시 초기화</p>
                        <p className="text-slate-500 text-sm">시스템 정리</p>
                      </button>
                    </div>
                  </div>
                </>
              )}
            </div>
          )}

          {activeTab === 'users' && (
            <div className="space-y-6">
              <h2 className="text-2xl font-bold text-white">사용자 관리</h2>
              <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50">
                <p className="text-slate-400">사용자 관리 기능이 곧 추가됩니다.</p>
              </div>
            </div>
          )}

          {activeTab === 'news' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between flex-wrap gap-3">
                <div>
                  <h2 className="text-2xl font-bold text-white mb-2">뉴스 관리</h2>
                  <p className="text-slate-400">카테고리별 뉴스를 작성하고 관리하세요</p>
                </div>
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
                        onChange={(e) => setArticleUrl(e.target.value)}
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
                          setSaveMessage({ type: 'info', text: '분석을 시작했습니다. 잠시만 기다려주세요...' });
                          setAiError(null);
                          setAiResult(null);
                          let pollAborted = false;
                          try {
                            // 1단계: analyze 요청 → 서버가 즉시 job_id 반환 (504 회피)
                            const startRes = await fetch('/api/admin/ai-analyze.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json' },
                              body: JSON.stringify({
                                action: 'analyze',
                                url: articleUrl.trim(),
                                enable_tts: false,
                                enable_interpret: false,
                                enable_learning: false
                              }),
                            });

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
                            if (article) {
                              setArticleImageUrl((article.image_url as string) || '');
                              setArticleSummary((article.description as string) || '');
                              setArticleSource((article.source as string) || '');
                              if (article.published_at) setArticlePublishedAt(article.published_at as string);
                              if (article.author) setArticleAuthor((article.author as string) || '');
                            }
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

                            // 2단계: TTS 생성
                            setSaveMessage({ type: 'info', text: '분석 완료. TTS 생성 중...' });
                            const ttsRes = await fetch('/api/admin/ai-analyze.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json' },
                              body: JSON.stringify({
                                action: 'generate_tts',
                                narration: narrationForTts,
                                news_title: (a.news_title as string) || (article?.title as string) || '',
                                source: article?.source || '',
                                author: article?.author || '',
                                published_at: article?.published_at || null
                              }),
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
                            const msg = err.name === 'AbortError'
                              ? '요청 시간 초과. 서버·프록시 타임아웃이거나 URL 접근이 느립니다.'
                              : '서버 오류: ' + (err.message || String(error));
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
                          setSaveMessage({ type: 'error', text: '상태 확인 실패: ' + (e as Error).message });
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
                        {/* 출처 */}
                        <div>
                          <label className="block text-slate-400 text-sm mb-1">출처 (Source)</label>
                          <input
                            type="text"
                            value={articleSource}
                            onChange={(e) => setArticleSource(e.target.value)}
                            placeholder="예: Financial Times, Reuters..."
                            className="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition"
                          />
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
                            <p className="text-slate-500 text-xs mb-2">프롬프트를 입력하면 DALL-E로 새 썸네일을 생성합니다 (영문 권장)</p>
                            <div className="flex gap-2">
                              <input
                                type="text"
                                value={dallePrompt}
                                onChange={(e) => setDallePrompt(e.target.value)}
                                placeholder="예: A blue ocean with sailing ships, editorial style"
                                className="flex-1 bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none transition"
                              />
                              <button
                                type="button"
                                disabled={isRegeneratingDalle || !dallePrompt.trim()}
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
                        <RichTextToolbar textareaRef={refArticleSummary} value={articleSummary} onChange={setArticleSummary} />
                        <textarea
                          ref={refArticleSummary}
                          value={articleSummary}
                          onChange={(e) => setArticleSummary(e.target.value)}
                          placeholder="기사 요약 내용..."
                          rows={3}
                          className="w-full bg-slate-800/50 border border-slate-600 rounded-lg px-3 py-2 text-white text-sm placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition resize-none"
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

                  {/* gpt 요약 입력 */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">
                      gpt 요약
                      <span className="ml-2 text-xs text-cyan-400">(붙여넣기 시 자동 정제)</span>
                    </label>
                    <RichTextToolbar textareaRef={refNewsContent} value={newsContent} onChange={setNewsContent} />
                    <textarea
                      ref={refNewsContent}
                      value={newsContent}
                      onChange={(e) => setNewsContent(e.target.value)}
                      onPaste={(e) => {
                        e.preventDefault();
                        const pastedText = e.clipboardData.getData('text');
                        const sanitized = sanitizeText(pastedText);
                        setNewsContent(sanitized);
                      }}
                      placeholder="뉴스 본문을 작성하세요..."
                      rows={8}
                      className="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 outline-none transition resize-none"
                    />
                    <p className="text-slate-500 text-sm mt-1">{newsContent.length} / 10,000자</p>
                  </div>

                  {/* 내레이션 톤 입력 */}
                  <div>
                    <label className="block text-slate-300 mb-2 text-sm font-medium">
                      <span className="text-emerald-400">내레이션 톤</span>
                      <span className="ml-2 text-xs text-emerald-400/70">(붙여넣기 시 자동 정제)</span>
                    </label>
                    <RichTextToolbar textareaRef={refNewsNarration} value={newsNarration} onChange={setNewsNarration} />
                    <textarea
                      ref={refNewsNarration}
                      value={newsNarration}
                      onChange={(e) => setNewsNarration(e.target.value)}
                      onPaste={(e) => {
                        e.preventDefault();
                        const pastedText = e.clipboardData.getData('text');
                        const sanitized = sanitizeText(pastedText);
                        setNewsNarration(sanitized);
                      }}
                      placeholder="내레이션 스타일로 작성하세요..."
                      rows={6}
                      className="w-full bg-slate-900/50 border border-emerald-700/50 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition resize-none"
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
                              const res = await fetch('/api/admin/ai-analyze.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({
                                action: 'generate_tts',
                                narration: newsNarration.trim(),
                                news_title: newsTitle || '제목 없음',
                                source: articleSource || '',
                                author: articleAuthor || '',
                                published_at: articlePublishedAt || null
                              }),
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
                    <label className="block text-slate-300 mb-2 text-sm font-medium">
                      <span className="text-amber-400">The Gist's Critique</span>
                      <span className="ml-2 text-xs text-amber-400/70">(붙여넣기 시 자동 정제)</span>
                    </label>
                    <RichTextToolbar textareaRef={refNewsWhyImportant} value={newsWhyImportant} onChange={setNewsWhyImportant} />
                    <textarea
                      ref={refNewsWhyImportant}
                      value={newsWhyImportant}
                      onChange={(e) => setNewsWhyImportant(e.target.value)}
                      onPaste={(e) => {
                        e.preventDefault();
                        const pastedText = e.clipboardData.getData('text');
                        const sanitized = sanitizeText(pastedText);
                        setNewsWhyImportant(sanitized);
                      }}
                      placeholder="The Gist's Critique를 작성해주세요..."
                      rows={5}
                      className="w-full bg-slate-900/50 border border-amber-700/50 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition resize-none"
                    />
                    <p className="text-slate-500 text-sm mt-1">{newsWhyImportant.length} / 5,000자</p>
                  </div>

                  {/* 저장 버튼 */}
                  <div className="flex items-center gap-4">
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
                          const requestBody = {
                            ...(isEditing && { id: editingNewsId }),
                            category: selectedCategory,
                            title: newsTitle,
                            content: newsContent,
                            why_important: newsWhyImportant.trim() || null,
                            narration: newsNarration.trim() || null,
                            source_url: articleUrl.trim() || null,
                            source: articleSource.trim() || null,
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
                            setSaveMessage({ 
                              type: 'success', 
                              text: isEditing ? '뉴스가 수정되었습니다!' : '뉴스가 저장되었습니다!' 
                            });
                            // 목록 새로고침
                            await loadNewsList();
                            // 폼 초기화
                            setNewsTitle('');
                            setNewsContent('');
                            setNewsWhyImportant('');
                            setNewsNarration('');
                            setArticleUrl('');
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

          {activeTab === 'ai' && (
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
                            if (data.article.author) setArticleAuthor(data.article.author);
                          }
                          const a = data.analysis;
                          const narrationForTts = a.narration || (a.key_points || []).join(' ');
                          if (!narrationForTts.trim()) {
                            setAiResult(data.analysis);
                            setIsAnalyzing(false);
                            return;
                          }
                          try {
                            const ttsRes = await fetch('/api/admin/ai-analyze.php', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json' },
                              body: JSON.stringify({
                                action: 'generate_tts',
                                narration: narrationForTts,
                                news_title: data.analysis?.news_title || data.article?.title || '',
                                source: data.article?.source || '',
                                author: data.article?.author || '',
                                published_at: data.article?.published_at || null
                              })
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
                        </div>
                      )}

                      {/* 내레이션 */}
                      {(aiResult.narration || aiResult.translation_summary) && (
                        <div className="p-4 bg-slate-900/50 rounded-xl">
                          <h4 className="text-cyan-400 font-medium mb-2 flex items-center gap-2">
                            <DocumentTextIcon className="w-4 h-4" />
                            내레이션
                          </h4>
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
                          <h4 className="text-purple-400 font-medium mb-3">The Gist's Critique</h4>
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
                          setArticleUrl(aiUrl);
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

          {activeTab === 'workspace' && (
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

          {activeTab === 'knowledge' && (
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

          {activeTab === 'settings' && (
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
  );
};

export default AdminPage;
