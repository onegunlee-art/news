import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useAuthStore } from '../store/authStore';

const RegisterPage: React.FC = () => {
  const { login, isAuthenticated, setSubscribed } = useAuthStore();
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    confirmPassword: '',
    nickname: '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [agreeTerms, setAgreeTerms] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState<'monthly' | 'yearly'>('monthly');
  const [showSuccess, setShowSuccess] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleFreeTrial = async () => {
    if (!isAuthenticated) {
      if (!formData.email || !formData.password || !formData.nickname) {
        setError('모든 필드를 입력해주세요.');
        return;
      }

      if (formData.password !== formData.confirmPassword) {
        setError('비밀번호가 일치하지 않습니다.');
        return;
      }

      if (!agreeTerms) {
        setError('이용약관에 동의해주세요.');
        return;
      }
    }

    setIsLoading(true);
    setError('');

    try {
      await new Promise(resolve => setTimeout(resolve, 1500));
      setSubscribed(true);
      setShowSuccess(true);
    } catch (err: any) {
      setError(err.message || '처리 중 오류가 발생했습니다.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleKakaoLogin = () => {
    login();
  };

  if (showSuccess) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-12">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className="w-full max-w-md text-center"
        >
          <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-8">
            <div className="w-20 h-20 mx-auto mb-6 bg-green-500 rounded-full flex items-center justify-center">
              <svg className="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>

            <h2 className="text-2xl font-semibold text-gray-900 mb-3">구독이 시작되었습니다!</h2>
            <p className="text-gray-600 mb-6">
              1달 무료 체험이 활성화되었습니다.<br />
              모든 심층 분석 서비스를 이용하실 수 있습니다.
            </p>

            <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
              <p className="text-gray-500 font-medium text-sm">무료 체험 기간</p>
              <p className="text-gray-900 text-lg">오늘 ~ {new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toLocaleDateString('ko-KR')}</p>
            </div>

            <Link
              to="/"
              className="block w-full py-3 bg-gray-900 hover:bg-gray-800 text-white font-semibold rounded-lg transition-all"
            >
              뉴스 보러 가기
            </Link>
          </div>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 py-12 px-4">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="max-w-4xl mx-auto"
      >
        {/* 로고 */}
        <div className="text-center mb-8">
          <Link to="/" className="inline-block">
            <h1 className="font-light text-4xl text-gray-900">
              Infer
            </h1>
          </Link>
          <p className="text-gray-500 mt-2">전문가 수준의 뉴스 분석</p>
        </div>

        <div className="grid lg:grid-cols-2 gap-8">
          {/* 좌측: 구독 플랜 */}
          <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-8">
            <h2 className="text-2xl font-semibold text-gray-900 text-center mb-2">구독 서비스</h2>
            <p className="text-gray-500 text-center mb-6">뉴스의 본질을 파악하세요</p>

            {/* 무료 체험 배너 */}
            <div className="bg-primary-500 rounded-lg p-4 mb-6 text-center">
              <p className="text-white font-bold text-lg">🎁 1달 무료 체험!</p>
              <p className="text-white/80 text-sm">지금 가입하시면 첫 달은 완전 무료</p>
            </div>

            {/* 플랜 선택 */}
            <div className="grid grid-cols-2 gap-3 mb-6">
              <button
                onClick={() => setSelectedPlan('monthly')}
                className={`p-4 rounded-lg border-2 transition-all ${
                  selectedPlan === 'monthly'
                    ? 'border-primary-500 bg-red-50'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <p className="text-gray-900 font-semibold">월간</p>
                <p className="text-2xl font-bold text-primary-500">₩9,900</p>
                <p className="text-gray-500 text-sm">/월</p>
              </button>
              <button
                onClick={() => setSelectedPlan('yearly')}
                className={`p-4 rounded-lg border-2 transition-all relative ${
                  selectedPlan === 'yearly'
                    ? 'border-primary-500 bg-red-50'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <span className="absolute -top-2 -right-2 px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">
                  33% 할인
                </span>
                <p className="text-gray-900 font-semibold">연간</p>
                <p className="text-2xl font-bold text-primary-500">₩79,000</p>
                <p className="text-gray-500 text-sm">/년</p>
              </button>
            </div>

            {/* 혜택 목록 */}
            <div className="space-y-3 mb-6">
              <p className="text-gray-900 font-medium mb-2">구독 혜택:</p>
              {[
                '이게 왜 중요한대! - 뉴스의 핵심 분석',
                '빅픽쳐 - 글로벌 트렌드와 큰 그림',
                '그래서 우리에겐? - 실질적 영향 분석',
                '무제한 뉴스 분석',
                '북마크 & 히스토리 저장',
                '이메일 뉴스레터'
              ].map((benefit, index) => (
                <div key={index} className="flex items-center gap-3 text-gray-600">
                  <svg className="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                  <span>{benefit}</span>
                </div>
              ))}
            </div>

            <div className="bg-gray-50 rounded-lg p-3 text-sm text-gray-500 text-center">
              <p>💳 1달 무료 체험 후 자동 결제됩니다</p>
              <p>언제든지 취소 가능합니다</p>
            </div>
          </div>

          {/* 우측: 회원가입 폼 */}
          <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-8">
            <h2 className="text-2xl font-semibold text-gray-900 text-center mb-6">
              {isAuthenticated ? '무료 체험 시작' : '회원가입'}
            </h2>

            {error && (
              <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm text-center">
                {error}
              </div>
            )}

            {!isAuthenticated ? (
              <>
                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); handleFreeTrial(); }}>
                  <div>
                    <label htmlFor="nickname" className="block text-sm font-medium text-gray-700 mb-1">
                      닉네임
                    </label>
                    <input
                      type="text"
                      id="nickname"
                      name="nickname"
                      value={formData.nickname}
                      onChange={handleChange}
                      placeholder="사용할 닉네임"
                      className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                    />
                  </div>

                  <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                      이메일
                    </label>
                    <input
                      type="email"
                      id="email"
                      name="email"
                      value={formData.email}
                      onChange={handleChange}
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
                      name="password"
                      value={formData.password}
                      onChange={handleChange}
                      placeholder="6자 이상 입력"
                      className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                    />
                  </div>

                  <div>
                    <label htmlFor="confirmPassword" className="block text-sm font-medium text-gray-700 mb-1">
                      비밀번호 확인
                    </label>
                    <input
                      type="password"
                      id="confirmPassword"
                      name="confirmPassword"
                      value={formData.confirmPassword}
                      onChange={handleChange}
                      placeholder="비밀번호 재입력"
                      className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                    />
                  </div>

                  <div className="flex items-start gap-2">
                    <input
                      type="checkbox"
                      id="agreeTerms"
                      checked={agreeTerms}
                      onChange={(e) => setAgreeTerms(e.target.checked)}
                      className="mt-1 w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
                    />
                    <label htmlFor="agreeTerms" className="text-sm text-gray-600">
                      <span className="text-primary-500 hover:underline cursor-pointer">이용약관</span> 및{' '}
                      <span className="text-primary-500 hover:underline cursor-pointer">개인정보처리방침</span>에 동의합니다
                    </label>
                  </div>

                  <button
                    type="submit"
                    disabled={isLoading}
                    className="w-full py-3 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {isLoading ? '처리 중...' : '🎁 1달 무료로 시작하기'}
                  </button>
                </form>

                <div className="relative my-6">
                  <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-gray-200"></div>
                  </div>
                  <div className="relative flex justify-center text-sm">
                    <span className="px-4 bg-white text-gray-500">또는</span>
                  </div>
                </div>

                <button
                  onClick={handleKakaoLogin}
                  className="w-full flex items-center justify-center gap-3 py-3 bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-lg transition-all"
                >
                  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z"/>
                  </svg>
                  카카오로 시작하기
                </button>

                <div className="mt-6 text-center">
                  <p className="text-gray-500 text-sm">
                    이미 계정이 있으신가요?{' '}
                    <Link to="/login" className="text-primary-500 hover:text-primary-600 font-medium">
                      로그인
                    </Link>
                  </p>
                </div>
              </>
            ) : (
              <div className="text-center">
                <p className="text-gray-600 mb-6">
                  이미 로그인되어 있습니다.<br />
                  아래 버튼을 눌러 무료 체험을 시작하세요.
                </p>
                <button
                  onClick={handleFreeTrial}
                  disabled={isLoading}
                  className="w-full py-4 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-lg rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoading ? '처리 중...' : '🎁 1달 무료 체험 시작하기'}
                </button>
              </div>
            )}
          </div>
        </div>

        <div className="mt-6 text-center">
          <Link to="/" className="text-gray-500 hover:text-gray-700 text-sm transition-colors">
            ← 홈으로 돌아가기
          </Link>
        </div>
      </motion.div>
    </div>
  );
};

export default RegisterPage;
