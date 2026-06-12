# GIST EDU nginx 설정 템플릿

> **용도:** edu.thegist.co.kr 서브도메인 설정  
> **위치:** `/etc/nginx/sites-available/thegist-edu`

## server 블록

```nginx
# GIST EDU — edu.thegist.co.kr
server {
    listen 80;
    server_name edu.thegist.co.kr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name edu.thegist.co.kr;

    # SSL (certbot에서 자동 추가됨)
    ssl_certificate /etc/letsencrypt/live/edu.thegist.co.kr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/edu.thegist.co.kr/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /var/www/thegist/public;
    index index.html index.php;

    # EDU API — PHP-FPM
    location ~ ^/api/edu/ {
        try_files $uri $uri/ /api.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
        add_header Cache-Control "no-cache, no-store, must-revalidate" always;
    }

    # 정적 파일 캐시
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Rate limiting (학원 동시접속 버스트 대비)
    limit_req zone=edu burst=20 nodelay;
}
```

## Rate limit zone 추가 (http 블록)

`/etc/nginx/nginx.conf`의 `http {}` 블록에 추가:

```nginx
# EDU rate limiting
limit_req_zone $binary_remote_addr zone=edu:10m rate=10r/s;
```

## 설정 적용

```bash
# 1. DNS 설정 (가비아 등)
# edu A 레코드 → EC2 IP

# 2. SSL 인증서 발급
sudo certbot --nginx -d edu.thegist.co.kr

# 3. 설정 파일 링크
sudo ln -s /etc/nginx/sites-available/thegist-edu /etc/nginx/sites-enabled/

# 4. nginx 검증 및 재시작
sudo nginx -t && sudo systemctl reload nginx
```

## 검증

```bash
curl -I https://edu.thegist.co.kr/api/edu/health
# 200 OK 확인
```
