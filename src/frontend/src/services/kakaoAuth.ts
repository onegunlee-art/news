/**
 * 카카오 JavaScript SDK 인증 서비스
 * 
 * 백엔드 라우터를 우회하고 프론트엔드에서 직접 카카오 로그인을 처리합니다.
 */

// Kakao SDK 타입 선언
declare global {
  interface Window {
    Kakao: {
      init: (appKey: string) => void;
      isInitialized: () => boolean;
      Auth: {
        authorize: (settings: {
          redirectUri: string;
          scope?: string;
          state?: string;
        }) => void;
        setAccessToken: (token: string) => void;
        getAccessToken: () => string | null;
        logout: (callback?: () => void) => void;
      };
      API: {
        request: (settings: {
          url: string;
          success?: (response: any) => void;
          fail?: (error: any) => void;
        }) => Promise<any>;
      };
    };
  }
}

// 카카오 REST API 키
const KAKAO_REST_API_KEY = import.meta.env.VITE_KAKAO_REST_API_KEY || '2b4a37bb18a276469b69bf3d8627e425';

// 카카오 JavaScript 키 (선택)
const KAKAO_JAVASCRIPT_KEY = import.meta.env.VITE_KAKAO_JAVASCRIPT_KEY || '';

// Redirect URI (프론트엔드 콜백 URL)
// 카카오 개발자 콘솔에 등록된 URI와 정확히 일치해야 합니다
const REDIRECT_URI = import.meta.env.VITE_KAKAO_REDIRECT_URI || 
  'http://ailand.dothome.co.kr/auth/callback';

/**
 * 카카오 SDK 초기화
 */
export const initKakao = (): boolean => {
  if (typeof window === 'undefined' || !window.Kakao) {
    console.log('Kakao SDK not loaded, using REST API method');
    return false;
  }

  if (!window.Kakao.isInitialized()) {
    if (!KAKAO_JAVASCRIPT_KEY) {
      console.log('Kakao JavaScript Key is not set, using REST API method');
      return false;
    }
    window.Kakao.init(KAKAO_JAVASCRIPT_KEY);
    console.log('Kakao SDK initialized:', window.Kakao.isInitialized());
  }

  return window.Kakao.isInitialized();
};

/**
 * 카카오 로그인 (REST API 방식 - JavaScript 키 불필요)
 */
export const kakaoLogin = (): void => {
  // Redirect URI 확인 (디버깅)
  console.log('Kakao Login - Redirect URI:', REDIRECT_URI);
  console.log('Kakao Login - REST API Key:', KAKAO_REST_API_KEY ? 'Set' : 'Not Set');
  
  // JavaScript SDK가 초기화되어 있으면 SDK 사용
  if (initKakao()) {
    console.log('Using Kakao SDK');
    window.Kakao.Auth.authorize({
      redirectUri: REDIRECT_URI,
      scope: 'profile_nickname,profile_image,account_email',
    });
    return;
  }

  // REST API 방식으로 직접 리다이렉트
  console.log('Using REST API method');
  const params = new URLSearchParams({
    client_id: KAKAO_REST_API_KEY,
    redirect_uri: REDIRECT_URI,
    response_type: 'code',
    scope: 'profile_nickname,profile_image,account_email',
  });

  const authUrl = `https://kauth.kakao.com/oauth/authorize?${params.toString()}`;
  console.log('Kakao Auth URL:', authUrl);
  window.location.href = authUrl;
};

/**
 * 카카오 로그아웃
 */
export const kakaoLogout = (): Promise<void> => {
  return new Promise((resolve) => {
    if (!window.Kakao?.isInitialized()) {
      resolve();
      return;
    }

    if (window.Kakao.Auth.getAccessToken()) {
      window.Kakao.Auth.logout(() => {
        console.log('Kakao logout completed');
        resolve();
      });
    } else {
      resolve();
    }
  });
};

/**
 * 카카오 사용자 정보 조회
 */
export const getKakaoUserInfo = async (): Promise<any> => {
  if (!window.Kakao?.isInitialized()) {
    throw new Error('Kakao SDK not initialized');
  }

  return window.Kakao.API.request({
    url: '/v2/user/me',
  });
};

/**
 * 인가 코드로 액세스 토큰 교환 (백엔드 API 호출)
 */
export const exchangeCodeForToken = async (code: string): Promise<{
  accessToken: string;
  refreshToken: string;
  user: any;
}> => {
  // 백엔드 API로 인가 코드 전송하여 토큰 교환
  const response = await fetch('/api/auth/kakao/token', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ code }),
  });

  if (!response.ok) {
    throw new Error('Token exchange failed');
  }

  return response.json();
};

/**
 * URL에서 인가 코드 추출
 */
export const getAuthCodeFromUrl = (): string | null => {
  const params = new URLSearchParams(window.location.search);
  return params.get('code');
};

/**
 * URL에서 에러 정보 추출
 */
export const getAuthErrorFromUrl = (): { error: string; description: string } | null => {
  const params = new URLSearchParams(window.location.search);
  const error = params.get('error');
  
  if (error) {
    return {
      error,
      description: params.get('error_description') || 'Unknown error',
    };
  }
  
  return null;
};

export default {
  initKakao,
  kakaoLogin,
  kakaoLogout,
  getKakaoUserInfo,
  exchangeCodeForToken,
  getAuthCodeFromUrl,
  getAuthErrorFromUrl,
};
