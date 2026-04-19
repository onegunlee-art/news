# Cloudflare(무료) 도입 검토 — 엣지 캐시·HTTP/3

현재 정적 파일·HTML은 **EC2 + Nginx** 직접 서빙입니다. 트래픽이 늘거나 해외·모바일 사용자가 많다면 **DNS만 Cloudflare 앞단에 두는 방식**으로 다음 효과를 기대할 수 있습니다.

| 항목 | 효과 |
|------|------|
| 글로벌 PoP | 한국 외 지역·모바일 RTT 감소 |
| HTTP/3(QUIC) | 패킷 손실 환경에서 재전송 효율 |
| Brotli | 원본 서버에 `ngx_brotli` 없어도 엣지에서 압축 가능(설정에 따름) |
| 캐시 규칙 | `/assets/*` 등 규칙 기반 캐시(주의: API는 캐시하지 말 것) |

## 주의

- **API 경로**(`/api/*`)는 동적이므로 Cloudflare에서 **Bypass cache** 유지.
- **HTML**(`index.html`)은 `no-cache` 정책과 맞춰 **캐시하지 않음**이 안전.
- **Digital Asset Links**(`/.well-known/assetlinks.json`)는 TWA 검증용이므로 캐시 무효화 정책 유지.

## 적용 순서(요약)

1. Cloudflare에 도메인 추가 → 네임서버 변경
2. SSL/TLS: **Full (strict)** (Let’s Encrypt 유지 시)
3. Page Rules 또는 Cache Rules: `www.thegist.co.kr/assets/*`만 **Cache Everything** + Edge TTL 조정(선택)
4. 배포 후 TWA·로그인·결제 플로우 스모크 테스트

자세한 DNS/프록시 설정은 운영 환경에 맞게 조정하세요.
