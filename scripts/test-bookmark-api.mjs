/**
 * 즐겨찾기 API E2E 테스트
 * 실행: node scripts/test-bookmark-api.mjs
 */
const BASE = 'https://ailand.dothome.co.kr/api';

async function main() {
  console.log('1. 로그인...');
  const loginRes = await fetch(`${BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'test@test.com', password: 'Test1234!' }),
  });
  const loginJson = await loginRes.json();
  if (!loginJson.success || !loginJson.data?.access_token) {
    console.error('로그인 실패:', loginJson);
    process.exit(1);
  }
  const token = loginJson.data.access_token;
  console.log('   로그인 성공, token:', token.slice(0, 20) + '...');

  const newsId = 20; // 뉴스 목록에서 확인한 id
  console.log('2. 북마크 추가 POST /news/' + newsId + '/bookmark...');
  const addRes = await fetch(`${BASE}/news/${newsId}/bookmark`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({}),
  });
  const addJson = await addRes.json();
  if (!addRes.ok) {
    console.error('북마크 추가 실패:', addRes.status, addJson);
    process.exit(1);
  }
  console.log('   북마크 추가 성공:', addJson.message || addJson);

  console.log('3. 북마크 목록 GET /user/bookmarks...');
  const listRes = await fetch(`${BASE}/user/bookmarks?page=1&per_page=10`, {
    headers: { 'Authorization': `Bearer ${token}` },
  });
  const listJson = await listRes.json();
  if (!listRes.ok) {
    console.error('북마크 목록 실패:', listRes.status, listJson);
    process.exit(1);
  }
  const items = listJson.data?.items || [];
  const found = items.some((item) => Number(item.id) === newsId || Number(item.news_id) === newsId);
  console.log('   북마크 목록 성공, 개수:', items.length, found ? '(방금 추가한 기사 포함)' : '(방금 추가한 기사 없음)');

  console.log('\n✅ 즐겨찾기 API 테스트 통과');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
