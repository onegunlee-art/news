# nginx server { } 내부에서만 include 하세요.
# EC2 예: include /var/www/thegist/aws/thegist-server-locations.inc;
# 로컬 검증 전 deploy 로 이 파일이 서버에 있어야 합니다.

    # ──────────────────────────────────────
    # Gzip 압축
    # ──────────────────────────────────────
    gzip on;
    gzip_types text/plain text/css text/xml text/javascript
               application/javascript application/json application/xml
               image/svg+xml font/woff2;
    gzip_min_length 256;
    gzip_vary on;

    # Brotli: Ubuntu 패키지 nginx-module-brotli 설치 후에만 사용 (없으면 nginx -t 실패)
    # include 전 확인: nginx -V 2>&1 | grep brotli
    # brotli on;
    # brotli_comp_level 5;
    # brotli_types text/plain text/css text/xml text/javascript application/javascript application/json application/xml image/svg+xml;

    # ──────────────────────────────────────
    # 보안 헤더
    # ──────────────────────────────────────
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # ──────────────────────────────────────
    # 민감 파일 접근 차단
    # ──────────────────────────────────────
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }
    location ~* \.(sql|lock|yml|yaml)$ {
        deny all;
        return 404;
    }

    # ──────────────────────────────────────
    # 오디오 파일 CORS
    # ──────────────────────────────────────
    location ~* \.(wav|mp3|ogg)$ {
        add_header Access-Control-Allow-Origin "*" always;
        add_header Accept-Ranges "bytes" always;
        try_files $uri =404;
    }

    # ──────────────────────────────────────
    # SPA 엔트리 HTML + version.json: 항상 재검증 (구버전 번들 참조 방지)
    # ──────────────────────────────────────
    location = /index.html {
        expires -1;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        add_header Pragma "no-cache" always;
    }
    location = /version.json {
        expires -1;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        add_header Pragma "no-cache" always;
        try_files $uri =404;
    }

    # ──────────────────────────────────────
    # 정적 파일 캐시
    # ──────────────────────────────────────
    location ~* \.(jpg|jpeg|png|gif|svg|webp|ico)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    # SW/PWA 파일은 항상 최신 버전을 받아야 함 (immutable 금지)
    location ~* ^/(sw|registerSW)\.js$ {
        expires -1;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Pragma "no-cache";
        try_files $uri =404;
    }
    location = /manifest.webmanifest {
        expires -1;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        try_files $uri =404;
    }
    # Digital Asset Links (TWA ↔ 도메인 검증)
    location = /.well-known/assetlinks.json {
        default_type application/json;
        charset utf-8;
        expires -1;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Pragma "no-cache";
        try_files $uri =404;
    }
    # Vite 빌드 산출물 (파일명에 콘텐츠 해시): 재배포 시 파일명이 바뀌므로 1년 캐시 안전
    location ~* ^/assets/.+\.(js|css)$ {
        expires 365d;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }
    location ~* \.(css|js)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
    location ~* \.(woff2|woff|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # PWA 아이콘: 짧은 캐시
    location ~* ^/(icon-|apple-icon|favicon).*\.(png|ico)$ {
        expires 1h;
        add_header Cache-Control "public, must-revalidate";
        try_files $uri =404;
    }

    # ──────────────────────────────────────
    # PHP 파일 처리 (FastCGI) - 반드시 API 라우팅보다 먼저!
    # ──────────────────────────────────────
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        fastcgi_param HTTP_AUTHORIZATION $http_authorization;

        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 300;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # ──────────────────────────────────────
    # 카카오 로그인 API (직접 PHP 파일 처리 — AuthController가 아님)
    # ──────────────────────────────────────
    location = /api/auth/kakao {
        rewrite ^ /api/auth/kakao.php last;
    }
    location = /api/auth/kakao/ {
        rewrite ^ /api/auth/kakao.php last;
    }
    location = /api/auth/kakao/callback {
        rewrite ^ /api/auth/kakao/callback.php last;
    }
    location = /api/auth/kakao/callback/ {
        rewrite ^ /api/auth/kakao/callback.php last;
    }
    location = /api/auth/kakao/token {
        rewrite ^ /api/auth/kakao/token.php last;
    }
    location = /api/auth/kakao/token/ {
        rewrite ^ /api/auth/kakao/token.php last;
    }

    # ──────────────────────────────────────
    # Google 로그인 API (api.php 라우터 경유)
    # ──────────────────────────────────────
    location = /api/auth/google {
        rewrite ^ /api.php?__path=/api/auth/google last;
    }
    location = /api/auth/google/ {
        rewrite ^ /api.php?__path=/api/auth/google last;
    }
    location = /api/auth/google/callback {
        rewrite ^ /api.php?__path=/api/auth/google/callback last;
    }
    location = /api/auth/google/callback/ {
        rewrite ^ /api.php?__path=/api/auth/google/callback last;
    }

    # ──────────────────────────────────────
    # 회원 탈퇴
    # ──────────────────────────────────────
    location = /api/auth/withdraw {
        rewrite ^ /api/auth/withdraw.php last;
    }

    # ──────────────────────────────────────
    # 관리자 계정 생성 (1회 실행용)
    # ──────────────────────────────────────
    location = /api/auth/seed-admin-user {
        rewrite ^ /api/auth/seed-admin-user.php last;
    }

    # ──────────────────────────────────────
    # 구독 결제 API
    # ──────────────────────────────────────
    location = /api/subscription/order {
        rewrite ^ /api/subscription/order.php last;
    }
    location = /api/subscription/verify {
        rewrite ^ /api/subscription/verify.php last;
    }
    location = /api/subscription/verify-promo {
        rewrite ^ /api/subscription/verify-promo.php last;
    }
    location = /api/subscription/verify-promo/ {
        rewrite ^ /api/subscription/verify-promo.php last;
    }
    location = /api/subscription/status {
        rewrite ^ /api/subscription/status.php last;
    }
    location = /api/subscription/detail {
        rewrite ^ /api/subscription/detail.php last;
    }
    location = /api/subscription/auto-renew {
        rewrite ^ /api/subscription/auto-renew.php last;
    }
    location = /api/subscription/cancel {
        rewrite ^ /api/subscription/cancel.php last;
    }
    location = /api/subscription/settings {
        rewrite ^ /api/subscription/settings.php last;
    }
    location = /api/subscription/order-status {
        rewrite ^ /api/subscription/order-status.php last;
    }
    location = /api/subscription/plans {
        rewrite ^ /api/subscription/plans.php last;
    }

    # ──────────────────────────────────────
    # StepPay 웹훅
    # ──────────────────────────────────────
    location = /api/webhook/steppay {
        rewrite ^ /api/webhook/steppay.php last;
    }
    location = /api/subscription/test-steppay {
        rewrite ^ /api/subscription/test-steppay.php last;
    }

    # ──────────────────────────────────────
    # 분석 API
    # ──────────────────────────────────────
    location ~ ^/api/analysis/news/([0-9]+)$ {
        rewrite ^ /api/analysis/news.php last;
    }

    # ──────────────────────────────────────
    # Admin API -> api.php 라우터 경유
    # ──────────────────────────────────────
    location = /api/admin/promotion-codes {
        rewrite ^ /api/admin/promotion-codes.php last;
    }
    location = /api/admin/promotion-codes/ {
        rewrite ^ /api/admin/promotion-codes.php last;
    }
    location = /api/admin/sync-subscriptions {
        rewrite ^ /api/admin/sync-subscriptions.php last;
    }
    location ~ ^/api/admin/ {
        rewrite ^/api/admin/(.*)$ /api.php?__path=/api/admin/$1 last;
    }

    # ──────────────────────────────────────
    # 기타 API -> api.php 라우터 (파일 없는 경우)
    # ──────────────────────────────────────
    location ~ ^/api/ {
        try_files $uri @api_fallback;
    }

    location @api_fallback {
        rewrite ^ /api.php?__path=$uri&$args last;
    }

    # ──────────────────────────────────────
    # SEO: /news/{id}/ → 표준 URL(비슬래시), 중복·표준 없음 완화
    # ──────────────────────────────────────
    location ~ ^/news/([0-9]+)/$ {
        return 301 https://www.thegist.co.kr/news/$1$is_args$args;
    }

    # ──────────────────────────────────────
    # SEO: sitemap.xml → sitemap.php (동적 생성)
    # ──────────────────────────────────────
    location = /sitemap.xml {
        rewrite ^ /sitemap.php last;
    }

    # ──────────────────────────────────────
    # SEO: /news/{id} 봇 분기 — 검색엔진/SNS 크롤러만 og-news-html.php 로
    # ──────────────────────────────────────
    location ~ ^/news/([0-9]+)$ {
        set $news_id $1;
        if ($is_seo_bot = 1) {
            rewrite ^ /og-news-html.php?id=$news_id last;
        }
        try_files $uri /index.html;
    }

    # ──────────────────────────────────────
    # React SPA 라우팅 (fallback)
    # ──────────────────────────────────────
    location / {
        try_files $uri $uri/ /index.html;

        # SPA fallback으로 index.html이 반환될 때 캐시 방지
        # 정적 파일(js/css/img)은 위 location 블록에서 먼저 매칭되므로 영향 없음
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
        add_header Pragma "no-cache" always;
    }
