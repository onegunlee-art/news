import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useAuthStore } from '../store/authStore';

const LoginPage: React.FC = () => {
  const { login } = useAuthStore();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const handleEmailLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);

    try {
      if (!email || !password) {
        throw new Error('이메일과 비밀번호를 입력해주세요.');
      }
      alert('이메일 로그인 기능은 준비 중입니다. 카카오 로그인을 이용해주세요.');
    } catch (err: any) {
      setError(err.message || '로그인에 실패했습니다.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleKakaoLogin = () => {
    login();
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-12">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="w-full max-w-md"
      >
        {/* 로고 */}
        <div className="text-center mb-8">
          <Link to="/" className="inline-block">
            <h1 className="text-4xl text-gray-900" style={{ fontFamily: "'Lobster', cursive" }}>
              The gist
            </h1>
          </Link>
          <p className="text-gray-500 mt-2">뉴스 맥락 분석</p>
        </div>

        {/* 로그인 카드 */}
        <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-8">
          <h2 className="text-2xl font-semibold text-gray-900 text-center mb-6">로그인</h2>

          {error && (
            <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm text-center">
              {error}
            </div>
          )}

          {/* 이메일 로그인 폼 */}
          <form onSubmit={handleEmailLogin} className="space-y-4">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                이메일
              </label>
              <input
                type="email"
                id="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="example@email.com"
                className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
              />
            </div>
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                비밀번호
              </label>
              <input
                type="password"
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
              />
            </div>

            <button
              type="submit"
              disabled={isLoading}
              className="w-full py-3 bg-gray-900 hover:bg-gray-800 text-white font-semibold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? '로그인 중...' : '로그인'}
            </button>
          </form>

          {/* 구분선 */}
          <div className="relative my-6">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-gray-200"></div>
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="px-4 bg-white text-gray-500">또는</span>
            </div>
          </div>

          {/* 소셜 로그인 */}
          <button
            onClick={handleKakaoLogin}
            className="w-full flex items-center justify-center gap-3 py-3 bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-lg transition-all"
          >
            <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z"/>
            </svg>
            카카오로 시작하기
          </button>

          {/* 회원가입 링크 */}
          <div className="mt-6 text-center">
            <p className="text-gray-500 text-sm">
              아직 계정이 없으신가요?{' '}
              <Link to="/register" className="text-primary-500 hover:text-primary-600 font-medium">
                구독하기
              </Link>
            </p>
          </div>
        </div>

        {/* 홈으로 돌아가기 */}
        <div className="mt-6 text-center">
          <Link to="/" className="text-gray-500 hover:text-gray-700 text-sm transition-colors">
            ← 홈으로 돌아가기
          </Link>
        </div>
      </motion.div>
    </div>
  );
};

export default LoginPage;
