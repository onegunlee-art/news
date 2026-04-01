import React, { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { motion } from 'framer-motion';
import { useAuthStore } from '../store/authStore';
import { authApi, siteSettingsApi } from '../services/api';
import GistLogo from '../components/Common/GistLogo';
import { DEFAULT_VISION } from '../constants/site';
import { saveAuthReturnState, getAuthRedirectTarget } from '../utils/authReturnState';
import { apiErrorMessage } from '../utils/apiErrorMessage';
import { formatContentHtml } from '../utils/sanitizeHtml';

const LoginPage: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const state = location.state as { returnTo?: string; intent?: string } | undefined;
  const returnTo = state?.returnTo;
  const intent = state?.intent;
  const { login, setTokens, setUser } = useAuthStore();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [step, setStep] = useState<'credentials' | 'otp'>('credentials');
  const [otpSession, setOtpSession] = useState('');
  const [otpCode, setOtpCode] = useState('');
  const [resendCooldown, setResendCooldown] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [isResending, setIsResending] = useState(false);
  const [error, setError] = useState('');
  const [vision, setVision] = useState(DEFAULT_VISION);

  useEffect(() => {
    siteSettingsApi.getSite().then((res) => {
      if (res.data?.data?.the_gist_vision?.trim()) setVision(res.data.data.the_gist_vision.trim());
      else setVision(DEFAULT_VISION);
    }).catch(() => setVision(DEFAULT_VISION));
  }, []);

  useEffect(() => {
    if (resendCooldown <= 0) return;
    const t = window.setInterval(() => {
      setResendCooldown((c) => (c <= 1 ? 0 : c - 1));
    }, 1000);
    return () => window.clearInterval(t);
  }, [resendCooldown]);

  const finishLoginWithTokens = (
    user: unknown,
    accessToken: string,
    refreshToken: string
  ) => {
    setTokens(accessToken, refreshToken);
    setUser(user);
    localStorage.setItem('user', JSON.stringify(user));
    const isAdmin = (user as { role?: string })?.role === 'admin';
    const target = getAuthRedirectTarget(returnTo, intent, isAdmin);
    navigate(target, { replace: true });
  };

  const handleEmailLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);

    try {
      if (!email || !password) {
        throw new Error('이메일과 비밀번호를 입력해주세요.');
      }
      const res = await authApi.login(email, password);
      if (res.data.success && res.data.data) {
        const data = res.data.data as Record<string, unknown>;
        if (data.requires_otp === true && typeof data.otp_session === 'string') {
          setOtpSession(data.otp_session);
          setOtpCode('');
          setStep('otp');
          setResendCooldown(60);
          return;
        }
        const { user, access_token, refresh_token } = data as {
          user: unknown;
          access_token: string;
          refresh_token: string;
        };
        if (access_token && refresh_token) {
          finishLoginWithTokens(user, access_token, refresh_token);
          return;
        }
      }
      throw new Error(res.data.message || '로그인에 실패했습니다.');
    } catch (err: unknown) {
      setError(apiErrorMessage(err, '로그인에 실패했습니다.'));
    } finally {
      setIsLoading(false);
    }
  };

  const handleOtpSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsLoading(true);
    try {
      const code = otpCode.replace(/\D/g, '').slice(0, 6);
      if (code.length !== 6) {
        throw new Error('6자리 인증 코드를 입력해주세요.');
      }
      const res = await authApi.verifyLoginOtp(otpSession, code);
      if (res.data.success && res.data.data) {
        const { user, access_token, refresh_token } = res.data.data as {
          user: unknown;
          access_token: string;
          refresh_token: string;
        };
        finishLoginWithTokens(user, access_token, refresh_token);
      } else {
        throw new Error(res.data.message || '인증에 실패했습니다.');
      }
    } catch (err: unknown) {
      setError(apiErrorMessage(err, '인증에 실패했습니다.'));
    } finally {
      setIsLoading(false);
    }
  };

  const handleResendOtp = async () => {
    if (!otpSession || resendCooldown > 0 || isResending) return;
    setError('');
    setIsResending(true);
    try {
      const res = await authApi.resendLoginOtp(otpSession);
      if (res.data.success) {
        setResendCooldown(60);
      } else {
        throw new Error(res.data.message || '재발송에 실패했습니다.');
      }
    } catch (err: unknown) {
      setError(apiErrorMessage(err, '재발송에 실패했습니다.'));
    } finally {
      setIsResending(false);
    }
  };

  const handleBackToCredentials = () => {
    setStep('credentials');
    setOtpSession('');
    setOtpCode('');
    setResendCooldown(0);
    setError('');
  };

  const handleKakaoLogin = () => {
    saveAuthReturnState(returnTo, intent);
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
          <GistLogo as="h1" size="default" link />
          <div
            className="text-gray-500 mt-2 [&_b]:font-bold [&_strong]:font-bold"
            dangerouslySetInnerHTML={{ __html: formatContentHtml(vision) }}
          />
        </div>

        {/* 로그인 카드 */}
        <div className="bg-white rounded-lg shadow-lg border border-gray-200 p-8">
          <h2 className="text-2xl font-semibold text-gray-900 text-center mb-6">
            {step === 'credentials' ? '로그인' : '이메일 인증'}
          </h2>

          {error && (
            <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm text-center">
              {error}
            </div>
          )}

          {step === 'credentials' ? (
            <>
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
                    autoComplete="email"
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
                    autoComplete="current-password"
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

              <div className="relative my-6">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-gray-200"></div>
                </div>
                <div className="relative flex justify-center text-sm">
                  <span className="px-4 bg-white text-gray-500">또는</span>
                </div>
              </div>

              <button
                type="button"
                onClick={handleKakaoLogin}
                className="w-full flex items-center justify-center gap-3 py-3 bg-[#FEE500] hover:bg-[#FDD835] text-[#3C1E1E] font-semibold rounded-lg transition-all"
              >
                <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 3C6.48 3 2 6.48 2 10.8c0 2.76 1.84 5.17 4.6 6.53-.2.75-.73 2.72-.84 3.14-.13.51.19.5.4.37.16-.1 2.59-1.76 3.64-2.48.72.1 1.47.16 2.2.16 5.52 0 10-3.48 10-7.72S17.52 3 12 3z"/>
                </svg>
                카카오로 시작하기
              </button>

              <button
                type="button"
                onClick={() => {
                  saveAuthReturnState(returnTo, intent);
                  const base = import.meta.env.VITE_API_URL || '/api';
                  window.location.href = `${base}/auth/google`;
                }}
                className="w-full flex items-center justify-center gap-3 py-3 mt-3 bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-semibold rounded-lg transition-all"
              >
                <svg className="w-5 h-5" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Google로 시작하기
              </button>

              <div className="mt-6 text-center">
                <p className="text-gray-500 text-sm">
                  아직 계정이 없으신가요?{' '}
                  <Link to="/register" state={returnTo ? { returnTo, intent } : undefined} className="text-primary-500 hover:text-primary-600 font-medium">
                    회원가입
                  </Link>
                </p>
              </div>
            </>
          ) : (
            <>
              <p className="text-sm text-gray-600 text-center mb-4 leading-relaxed">
                <span className="font-medium text-gray-800">{email}</span>
                <br />
                로 주소로 인증 코드를 보냈습니다. 10분 이내에 6자리 숫자를 입력해 주세요.
              </p>
              <form onSubmit={handleOtpSubmit} className="space-y-4">
                <div>
                  <label htmlFor="login-otp" className="block text-sm font-medium text-gray-700 mb-1">
                    인증 코드
                  </label>
                  <input
                    id="login-otp"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    value={otpCode}
                    onChange={(e) => setOtpCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                    placeholder="000000"
                    className="w-full p-3 rounded-lg bg-white border border-gray-300 text-gray-900 text-center text-lg tracking-[0.3em] font-mono placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                  />
                </div>
                <button
                  type="submit"
                  disabled={isLoading || otpCode.length !== 6}
                  className="w-full py-3 bg-gray-900 hover:bg-gray-800 text-white font-semibold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoading ? '확인 중...' : '인증하고 로그인'}
                </button>
              </form>
              <div className="mt-4 flex flex-col gap-2">
                <button
                  type="button"
                  disabled={resendCooldown > 0 || isResending}
                  onClick={handleResendOtp}
                  className="w-full py-2 text-sm text-primary-600 hover:text-primary-700 font-medium disabled:text-gray-400 disabled:cursor-not-allowed"
                >
                  {isResending
                    ? '발송 중...'
                    : resendCooldown > 0
                      ? `인증 코드 재발송 (${resendCooldown}초)`
                      : '인증 코드 재발송'}
                </button>
                <button
                  type="button"
                  onClick={handleBackToCredentials}
                  className="w-full py-2 text-sm text-gray-600 hover:text-gray-800"
                >
                  이메일·비밀번호 다시 입력
                </button>
              </div>
            </>
          )}
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
