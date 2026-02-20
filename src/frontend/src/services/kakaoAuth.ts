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
      Share: {
        sendDefault: (settings: {
          objectType: 'feed' | 'list' | 'location' | 'commerce' | 'text';
          content: {
            title: string;
            description?: string;
            imageUrl?: string;
            link: {
              mobileWebUrl?: string;
              webUrl?: string;
            };
          };
          buttons?: Array<{
            title: string;
            link: {
              mobileWebUrl?: string;
              webUrl?: string;
            };
          }>;
        }) => void;
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
  'https://www.thegist.co.kr/api/auth/kakao/callback';

/**
 * 카카오 SDK 동적 로드
 */
let sdkLoading: Promise<void> | null = null;

const loadKakaoSDK = (): Promise<void> => {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Window is not available'));
  }

  // 이미 로드되어 있으면 즉시 반환
  if (window.Kakao) {
    return Promise.resolve();
  }

  // 이미 로딩 중이면 기존 Promise 반환
  if (sdkLoading) {
    return sdkLoading;
  }

  // SDK 로드
  sdkLoading = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://t1.kakaocdn.net/kakao_js_sdk/2.7.4/kakao.min.js';
    script.async = true;
    script.crossOrigin = 'anonymous';
    
    script.onload = () => {
      console.log('Kakao SDK loaded successfully');
      resolve();
    };
    
    script.onerror = () => {
      console.error('Failed to load Kakao SDK');
      sdkLoading = null;
      reject(new Error('Failed to load Kakao SDK'));
    };
    
    document.head.appendChild(script);
  });

  return sdkLoading;
};

/**
 * 카카오 SDK 초기화
 */
export const initKakao = async (): Promise<boolean> => {
  if (typeof window === 'undefined') {
    return false;
  }

  try {
    // SDK 로드 시도
    await loadKakaoSDK();
  } catch (error) {
    console.log('Kakao SDK not loaded, using REST API method');
    return false;
  }

  if (!window.Kakao) {
    console.log('Kakao SDK not available, using REST API method');
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
export const kakaoLogin = async (): Promise<void> => {
  // JS Key가 있을 때만 SDK 시도, 없으면 바로 REST API 방식
  if (KAKAO_JAVASCRIPT_KEY) {
    const sdkInitialized = await initKakao();
    if (sdkInitialized) {
      window.Kakao.Auth.authorize({
        redirectUri: REDIRECT_URI,
        scope: 'profile_nickname,profile_image',
      });
      return;
    }
  }

  // REST API 방식으로 직접 리다이렉트 (SDK 불필요)
  const params = new URLSearchParams({
    client_id: KAKAO_REST_API_KEY,
    redirect_uri: REDIRECT_URI,
    response_type: 'code',
    scope: 'profile_nickname,profile_image',
  });

  window.location.href = `https://kauth.kakao.com/oauth/authorize?${params.toString()}`;
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

/**
 * 카카오톡으로 기사 공유
 */
export const shareToKakao = async (article: {
  title: string;
  description: string;
  imageUrl?: string;
  webUrl: string;
}): Promise<boolean> => {
  // SDK 초기화 확인
  const sdkInitialized = await initKakao();
  
  if (!sdkInitialized || !window.Kakao?.Share) {
    // SDK 사용 불가 시 카카오톡 공유 URL 스킴 사용
    const shareUrl = `https://story.kakao.com/share?url=${encodeURIComponent(article.webUrl)}`;
    window.open(shareUrl, '_blank', 'width=600,height=400');
    return true;
  }

  try {
    window.Kakao.Share.sendDefault({
      objectType: 'feed',
      content: {
        title: article.title,
        description: article.description || '',
        imageUrl: article.imageUrl || 'https://picsum.photos/seed/thegist/400/200',
        link: {
          webUrl: article.webUrl,
          mobileWebUrl: article.webUrl,
        },
      },
      buttons: [
        {
          title: '자세히 보기',
          link: {
            webUrl: article.webUrl,
            mobileWebUrl: article.webUrl,
          },
        },
      ],
    });
    return true;
  } catch (error) {
    console.error('Kakao share failed:', error);
    // 폴백: 일반 공유 창 열기
    const shareUrl = `https://story.kakao.com/share?url=${encodeURIComponent(article.webUrl)}`;
    window.open(shareUrl, '_blank', 'width=600,height=400');
    return false;
  }
};

export default {
  initKakao,
  kakaoLogin,
  kakaoLogout,
  getKakaoUserInfo,
  exchangeCodeForToken,
  getAuthCodeFromUrl,
  getAuthErrorFromUrl,
  shareToKakao,
};
