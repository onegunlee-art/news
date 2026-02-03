import React, { useState, useEffect } from 'react';
import { useLocation, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { newsApi } from '../services/api';
import { getPlaceholderImageUrl } from '../utils/imagePolicy';

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
  description: string;
}

const categories: Record<string, CategoryConfig> = {
  diplomacy: {
    name: 'Foreign Affairs',
    description: '국제 관계, 외교 정책, 글로벌 이슈에 대한 전문 분석',
  },
  economy: {
    name: 'Economy',
    description: '경제 동향, 금융 시장, 비즈니스 뉴스에 대한 심층 분석',
  },
  technology: {
    name: 'Technology',
    description: '기술 혁신, 디지털 트랜스포메이션, IT 산업에 대한 분석',
  },
  entertainment: {
    name: 'Entertainment',
    description: '영화, 음악, 문화, 엔터테인먼트 산업에 대한 분석',
  },
};

const CategoryPage: React.FC = () => {
  const location = useLocation();
  const category = location.pathname.replace('/', '');
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
        const nytSections: Record<string, string> = {
          diplomacy: 'world',
          economy: 'business',
          technology: 'technology',
          entertainment: 'arts',
        };
        const response = await newsApi.nytTop(nytSections[category] || 'world');
        if (response.data.success && response.data.data.items) {
          setNews(response.data.data.items);
        } else {
          setNews(getDemoNews(category!));
        }
      } catch (err) {
        console.error('Failed to fetch news:', err);
        setNews(getDemoNews(category!));
      } finally {
        setLoading(false);
      }
    };

    fetchNews();
  }, [category, config]);

  if (!config) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <div className="text-center">
          <h1 className="text-4xl font-semibold text-gray-900 mb-4">카테고리를 찾을 수 없습니다</h1>
          <Link to="/" className="text-primary-500 hover:underline">홈으로 돌아가기</Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      {/* 카테고리 헤더 */}
      <section className="border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 py-12">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="max-w-3xl"
          >
            <h1 className="text-4xl md:text-5xl font-semibold text-gray-900 mb-4">
              {config.name}
            </h1>
            <p className="text-xl text-gray-600 leading-relaxed">
              {config.description}
            </p>
          </motion.div>
        </div>
      </section>

      {/* 뉴스 그리드 */}
      <section className="py-12">
        <div className="max-w-7xl mx-auto px-4">
          {loading ? (
            <div className="flex items-center justify-center py-20">
              <div className="animate-spin rounded-full h-12 w-12 border-4 border-primary-500 border-t-transparent"></div>
            </div>
          ) : error ? (
            <div className="text-center py-20">
              <p className="text-red-500">{error}</p>
            </div>
          ) : news.length === 0 ? (
            <div className="text-center py-20">
              <p className="text-gray-500">기사가 없습니다.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              {news.map((item, index) => (
                <ArticleCard key={item.id || index} article={item} index={index} />
              ))}
            </div>
          )}
        </div>
      </section>
    </div>
  );
};

function ArticleCard({ article, index }: { article: NewsItem; index: number }) {
  const articleForImage = {
    id: parseInt(article.id, 10) || undefined,
    title: article.title,
    description: article.description,
    published_at: article.published_at,
    category: article.section,
    url: article.url,
    source: article.source,
  };
  const imageUrl = article.image || getPlaceholderImageUrl(articleForImage, 400, 250);

  return (
    <motion.article
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: index * 0.05 }}
      className="group"
    >
      <a href={article.url} target="_blank" rel="noopener noreferrer" className="block">
        {/* 이미지 (기사별 고유 시드, 중복 없음) */}
        <div className="aspect-[16/10] overflow-hidden mb-4">
          <img
            src={imageUrl}
            alt={article.title}
            className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
            onError={(e) => {
              (e.target as HTMLImageElement).src = getPlaceholderImageUrl(articleForImage, 400, 250);
            }}
          />
        </div>
        
        {/* 콘텐츠 */}
        <div>
          <span className="text-xs font-semibold text-primary-500 uppercase tracking-wider">
            {article.source || 'News'}
          </span>
          <h2 className="text-xl font-medium text-gray-900 mt-2 mb-2 leading-snug group-hover:text-primary-500 transition-colors line-clamp-2">
            {article.title}
          </h2>
          {article.description && (
            <p className="text-gray-600 text-sm leading-relaxed line-clamp-2 mb-3">
              {article.description}
            </p>
          )}
          <p className="text-xs text-gray-400">
            {formatDate(article.published_at)}
          </p>
        </div>
      </a>
    </motion.article>
  );
}

function formatDate(dateString: string): string {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
}

function getDemoNews(category: string): NewsItem[] {
  const demoData: Record<string, NewsItem[]> = {
    diplomacy: [
      { id: '1', title: 'US-China Summit: New Cooperation Framework Discussed', description: 'Leaders from both nations met to discuss economic and security issues, seeking to improve bilateral relations.', url: '#', image: null, source: 'Analysis', section: 'World', author: '', published_at: new Date().toISOString(), keywords: ['US', 'China', 'Diplomacy'] },
      { id: '2', title: 'NATO Summit Strengthens Alliance Commitments', description: 'Member nations agreed to increase defense spending and expand cooperation.', url: '#', image: null, source: 'Analysis', section: 'World', author: '', published_at: new Date().toISOString(), keywords: ['NATO', 'Security'] },
      { id: '3', title: 'UN Climate Summit Sets New Targets', description: 'World leaders commit to ambitious carbon neutrality goals.', url: '#', image: null, source: 'Analysis', section: 'World', author: '', published_at: new Date().toISOString(), keywords: ['UN', 'Climate'] },
    ],
    economy: [
      { id: '1', title: 'Federal Reserve Holds Interest Rates Steady', description: 'The central bank maintains rates amid inflation concerns, markets respond positively.', url: '#', image: null, source: 'Analysis', section: 'Business', author: '', published_at: new Date().toISOString(), keywords: ['Fed', 'Interest Rates'] },
      { id: '2', title: 'Global Semiconductor Demand Surges', description: 'AI boom drives explosive growth in chip demand, reshaping supply chains.', url: '#', image: null, source: 'Analysis', section: 'Business', author: '', published_at: new Date().toISOString(), keywords: ['Semiconductors', 'AI'] },
      { id: '3', title: 'Oil Prices Rise on Middle East Tensions', description: 'Regional instability pushes crude prices higher, affecting global energy markets.', url: '#', image: null, source: 'Analysis', section: 'Business', author: '', published_at: new Date().toISOString(), keywords: ['Oil', 'Energy'] },
    ],
    technology: [
      { id: '1', title: 'OpenAI Announces GPT-5 Launch Timeline', description: 'Next-generation AI model promises enhanced reasoning capabilities.', url: '#', image: null, source: 'Analysis', section: 'Technology', author: '', published_at: new Date().toISOString(), keywords: ['AI', 'GPT'] },
      { id: '2', title: 'Apple Unveils Next-Generation Mixed Reality Headset', description: 'Vision Pro successor launches with improved features at lower price point.', url: '#', image: null, source: 'Analysis', section: 'Technology', author: '', published_at: new Date().toISOString(), keywords: ['Apple', 'VR'] },
      { id: '3', title: 'Quantum Computing Reaches Commercial Milestone', description: 'Major tech companies begin offering quantum computing services.', url: '#', image: null, source: 'Analysis', section: 'Technology', author: '', published_at: new Date().toISOString(), keywords: ['Quantum'] },
    ],
    entertainment: [
      { id: '1', title: 'Korean Cinema Dominates International Awards Season', description: 'K-content continues global expansion with multiple prestigious wins.', url: '#', image: null, source: 'Analysis', section: 'Arts', author: '', published_at: new Date().toISOString(), keywords: ['Film', 'Korea'] },
      { id: '2', title: 'K-Pop Group Tops Billboard Hot 100', description: 'Korean artists achieve historic chart success in global music market.', url: '#', image: null, source: 'Analysis', section: 'Arts', author: '', published_at: new Date().toISOString(), keywords: ['K-POP', 'Billboard'] },
      { id: '3', title: 'Streaming Platform Announces Major Korean Drama Series', description: 'Global OTT platforms continue heavy investment in Korean content.', url: '#', image: null, source: 'Analysis', section: 'Arts', author: '', published_at: new Date().toISOString(), keywords: ['Streaming', 'Drama'] },
    ],
  };
  
  return demoData[category] || [];
}

export default CategoryPage;
