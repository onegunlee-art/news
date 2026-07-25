/**
 * Asia/Seoul 기준 오늘 날짜 (YYYY-MM-DD).
 * toISOString()은 UTC라 KST 자정~09시에 전날 날짜가 될 수 있음.
 */
export function todayKstDate(): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Seoul',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date())
}
