# Nginx Brotli (선택)

`aws/thegist-server-locations.inc` 상단에 **주석 처리된** `brotli` 지시어가 있습니다. 실제로 켜려면 Nginx에 **ngx_brotli** 모듈이 필요합니다.

## Ubuntu (패키지 제공 시)

```bash
sudo apt-get install nginx-module-brotli
# /etc/nginx/nginx.conf http 블록 맨 위에:
# load_module modules/ngx_http_brotli_filter_module.so;
# load_module modules/ngx_http_brotli_static_module.so;
```

그 다음 `aws/thegist-server-locations.inc`의 `brotli` 관련 주석을 해제하고:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

## 확인

```bash
curl -sI -H 'Accept-Encoding: br' https://www.thegist.co.kr/assets/index-*.js | grep -i content-encoding
```

`content-encoding: br` 가 보이면 성공입니다.

모듈이 없으면 **gzip만으로도 동작**하며, Brotli 없이 주석을 해제하면 `nginx -t`가 실패합니다.
