import React, { useState, useEffect } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { newsApi } from '../services/api';

interface NewsItem {
  id: string;
  title: string;
  description: string;
  url: string;
  image: string | null;
  source: string;
  section: string;
  author: string;
  published_at: string;
  keywords: string[];
}

interface CategoryConfig {
  name: string;
  nameEn: string;
  emoji: string;
  nytSection: string;
  description: string;
  gradient: string;
}

const categories: Record<string, CategoryConfig> = {
  diplomacy: {
    name: '외교',
    nameEn: 'Diplomacy',
    emoji: '',
    nytSection: 'world',
    description: '국제 관계, 외교 정책, 글로벌 뉴스',
    gradient: 'from-blue-500 to-cyan-500',
  },
  economy: {
    name: '경제',
    nameEn: 'Economy',
    emoji: '',
    nytSection: 'business',
    description: '경제 동향, 금융 시장, 비즈니스 뉴스',
    gradient: 'from-emerald-500 to-green-500',
  },
  technology: {
    name: '기술',
    nameEn: 'Technology',
    emoji: '',
    nytSection: 'technology',
    description: 'IT, 과학, 혁신, 테크 뉴스',
    gradient: 'from-purple-500 to-pink-500',
  },
  entertainment: {
    name: 'Entertainment',
    nameEn: 'Entertainment',
    emoji: '',
    nytSection: 'arts',
    description: '영화, 음악, 문화, 엔터테인먼트 뉴스',
    gradient: 'from-orange-500 to-red-500',
  },
};

const CategoryPage: React.FC = () => {
  const location = useLocation();
  const category = location.pathname.replace('/', ''); // '/diplomacy' -> 'diplomacy'
  const [news, setNews] = useState<NewsItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const config = category ? categories[category] : null;

  useEffect(() => {
    if (!config) return;

    const fetchNews = async () => {
      setLoading(true);
      setError(null);

      try {
        // NYT API 호출 시도
        const response = await newsApi.nytTop(config.nytSection);
        if (response.data.success && response.data.data.items) {
          setNews(response.data.data.items);
        } else {
          // 데모 데이터 사용
          setNews(getDemoNews(category!));
        }
      } catch (err) {
        console.error('Failed to fetch news:', err);
        // 데모 데이터 사용
        setNews(getDemoNews(category!));
      } finally {
        setLoading(false);
      }
    };

    fetchNews();
  }, [category, config]);

  if (!config) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-white mb-4">카테고리를 찾을 수 없습니다</h1>
          <Link to="/" className="text-primary-400 hover:underline">홈으로 돌아가기</Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen py-8 px-4 md:px-8">
      {/* 카테고리 헤더 */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="max-w-7xl mx-auto mb-12"
      >
        <div className={`bg-gradient-to-r ${config.gradient} rounded-3xl p-8 md:p-12`}>
          <div className="mb-4">
            <h1 className="text-3xl md:text-4xl font-bold text-white">
              {config.name}
            </h1>
            <p className="text-white/80 text-lg">{config.nameEn}</p>
          </div>
          <p className="text-white/90 text-lg">{config.description}</p>
        </div>
      </motion.div>

      {/* 뉴스 그리드 */}
      <div className="max-w-7xl mx-auto">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="animate-spin rounded-full h-12 w-12 border-4 border-primary-500 border-t-transparent"></div>
          </div>
        ) : error ? (
          <div className="text-center py-20">
            <p className="text-red-400">{error}</p>
          </div>
        ) : news.length === 0 ? (
          <div className="text-center py-20">
            <p className="text-gray-400">뉴스가 없습니다.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {news.map((item, index) => (
              <motion.article
                key={item.id || index}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.05 }}
                className="bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-lg transition-all group"
              >
                {item.image && (
                  <div className="aspect-video overflow-hidden">
                    <img
                      src={item.image}
                      alt={item.title}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                      onError={(e) => {
                        (e.target as HTMLImageElement).style.display = 'none';
                      }}
                    />
                  </div>
                )}
                <div className="p-6">
                  <div className="flex items-center gap-2 mb-3">
                    <span className="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                      {item.source || 'NYT'}
                    </span>
                    <span className="text-xs text-gray-500">
                      {formatDate(item.published_at)}
                    </span>
                  </div>
                  <h2 className="text-lg font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-primary-600 transition-colors">
                    {item.title}
                  </h2>
                  <p className="text-gray-600 text-sm line-clamp-3 mb-4">
                    {item.description}
                  </p>
                  <div className="flex items-center justify-between">
                    <a
                      href={item.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-primary-600 text-sm hover:underline"
                    >
                      자세히 보기 →
                    </a>
                  </div>
                </div>
              </motion.article>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

// 날짜 포맷팅
function formatDate(dateString: string): string {
  if (!dateString) return '';
  const date = new Date(dateString);
  const now = new Date();
  const diff = now.getTime() - date.getTime();
  const hours = Math.floor(diff / (1000 * 60 * 60));
  
  if (hours < 1) return '방금 전';
  if (hours < 24) return `${hours}시간 전`;
  if (hours < 48) return '어제';
  return date.toLocaleDateString('ko-KR');
}

// 데모 뉴스 데이터
function getDemoNews(category: string): NewsItem[] {
  const demoData: Record<string, NewsItem[]> = {
    diplomacy: [
      { id: '1', title: '미중 정상회담, 새로운 협력 방안 모색', description: '양국 정상이 경제 및 안보 문제에 대해 논의하며 관계 개선의 실마리를 찾았습니다.', url: '#', image: null, source: 'NYT', section: 'World', author: '', published_at: new Date().toISOString(), keywords: ['미국', '중국', '외교'] },
      { id: '2', title: 'NATO 정상회의, 동맹 강화 합의', description: '북대서양조약기구 회원국들이 방위비 증액과 협력 확대에 합의했습니다.', url: '#', image: null, source: 'NYT', section: 'World', author: '', published_at: new Date().toISOString(), keywords: ['NATO', '안보'] },
      { id: '3', title: 'UN 기후변화 협약 당사국 총회 개최', description: '각국 대표들이 탄소 중립 목표 달성을 위한 구체적 방안을 논의합니다.', url: '#', image: null, source: 'NYT', section: 'World', author: '', published_at: new Date().toISOString(), keywords: ['UN', '기후'] },
    ],
    economy: [
      { id: '1', title: '연준, 금리 동결 결정... 시장 안도', description: '미국 연방준비제도가 기준금리를 동결하며 인플레이션 대응에 나섰습니다.', url: '#', image: null, source: 'NYT', section: 'Business', author: '', published_at: new Date().toISOString(), keywords: ['금리', '연준'] },
      { id: '2', title: '글로벌 반도체 수요 급증, 공급망 재편', description: 'AI 붐으로 반도체 수요가 폭발적으로 증가하며 공급망 다변화가 가속화되고 있습니다.', url: '#', image: null, source: 'NYT', section: 'Business', author: '', published_at: new Date().toISOString(), keywords: ['반도체', 'AI'] },
      { id: '3', title: '원유 가격 상승세, 에너지 시장 긴장', description: '중동 지역 불안으로 원유 가격이 상승하며 글로벌 에너지 시장에 영향을 미치고 있습니다.', url: '#', image: null, source: 'NYT', section: 'Business', author: '', published_at: new Date().toISOString(), keywords: ['원유', '에너지'] },
    ],
    technology: [
      { id: '1', title: 'OpenAI, GPT-5 출시 임박', description: '차세대 AI 모델이 곧 공개될 예정이며, 더욱 강력한 추론 능력을 갖출 것으로 예상됩니다.', url: '#', image: null, source: 'NYT', section: 'Technology', author: '', published_at: new Date().toISOString(), keywords: ['AI', 'GPT'] },
      { id: '2', title: '애플, 혼합현실 헤드셋 새 버전 발표', description: 'Vision Pro 후속 모델이 더 가볍고 저렴한 가격으로 출시됩니다.', url: '#', image: null, source: 'NYT', section: 'Technology', author: '', published_at: new Date().toISOString(), keywords: ['애플', 'VR'] },
      { id: '3', title: '양자컴퓨터, 상용화 시대 열린다', description: '주요 기업들이 양자컴퓨터 상용 서비스를 시작하며 새로운 컴퓨팅 시대가 열립니다.', url: '#', image: null, source: 'NYT', section: 'Technology', author: '', published_at: new Date().toISOString(), keywords: ['양자컴퓨터'] },
    ],
    entertainment: [
      { id: '1', title: '한국 영화, 해외 시상식 휩쓸어', description: '올해 주요 영화제에서 한국 작품들이 연이어 수상하며 K-콘텐츠의 위상을 높였습니다.', url: '#', image: null, source: 'NYT', section: 'Arts', author: '', published_at: new Date().toISOString(), keywords: ['영화', '한류'] },
      { id: '2', title: 'K-POP 그룹, 빌보드 차트 1위', description: '한국 아이돌 그룹이 빌보드 핫 100 1위를 기록하며 글로벌 음악 시장을 석권했습니다.', url: '#', image: null, source: 'NYT', section: 'Arts', author: '', published_at: new Date().toISOString(), keywords: ['K-POP', '빌보드'] },
      { id: '3', title: '넷플릭스, 한국 드라마 신작 공개', description: '글로벌 OTT 플랫폼에서 한국 드라마가 최고 인기를 기록하고 있습니다.', url: '#', image: null, source: 'NYT', section: 'Arts', author: '', published_at: new Date().toISOString(), keywords: ['넷플릭스', '드라마'] },
    ],
  };
  
  return demoData[category] || [];
}

export default CategoryPage;
