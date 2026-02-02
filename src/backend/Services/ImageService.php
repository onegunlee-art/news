<?php

namespace Backend\Services;

/**
 * 저작권 무료 이미지 자동 매칭 서비스
 * Unsplash Source API 사용 (API 키 불필요, 완전 무료, 상업적 사용 가능)
 */
class ImageService
{
    // 키워드 매핑 (한글 → 영어 검색어)
    private array $keywordMap = [
        // 인물
        '트럼프' => 'trump,president,politics',
        'trump' => 'trump,president,politics',
        '바이든' => 'biden,president,whitehouse',
        'biden' => 'biden,president,whitehouse',
        '시진핑' => 'china,politics,beijing',
        '푸틴' => 'russia,kremlin,politics',
        '윤석열' => 'korea,seoul,politics',
        '김정은' => 'northkorea,politics',
        '일론' => 'tesla,spacex,technology',
        '머스크' => 'tesla,spacex,technology',
        'elon' => 'tesla,spacex,technology',
        'musk' => 'tesla,spacex,technology',
        '오픈ai' => 'artificial-intelligence,robot,technology',
        'openai' => 'artificial-intelligence,robot,technology',
        'chatgpt' => 'artificial-intelligence,chat,technology',
        'gpt' => 'artificial-intelligence,technology',
        
        // 경제/금융
        '주식' => 'stock-market,trading,finance',
        '증시' => 'stock-market,wall-street,finance',
        '금리' => 'bank,finance,money',
        '환율' => 'currency,money,exchange',
        '비트코인' => 'bitcoin,cryptocurrency,blockchain',
        '코인' => 'cryptocurrency,bitcoin,blockchain',
        '부동산' => 'real-estate,building,city',
        '인플레이션' => 'inflation,money,economy',
        '경제' => 'economy,business,finance',
        '무역' => 'trade,shipping,container',
        '관세' => 'trade,customs,shipping',
        
        // 기술
        'ai' => 'artificial-intelligence,robot,technology',
        '인공지능' => 'artificial-intelligence,robot,future',
        '반도체' => 'semiconductor,chip,technology',
        '배터리' => 'battery,electric,energy',
        '전기차' => 'electric-car,tesla,automotive',
        '자율주행' => 'self-driving,car,technology',
        '로봇' => 'robot,automation,technology',
        '우주' => 'space,rocket,nasa',
        '스타링크' => 'satellite,space,technology',
        
        // 외교/정치
        '외교' => 'diplomacy,handshake,politics',
        '정상회담' => 'summit,diplomacy,politics',
        'nato' => 'nato,military,alliance',
        '유엔' => 'united-nations,diplomacy,global',
        'un' => 'united-nations,diplomacy,global',
        '전쟁' => 'war,military,conflict',
        '우크라이나' => 'ukraine,europe,politics',
        '대만' => 'taiwan,asia,politics',
        '북한' => 'northkorea,military,politics',
        '핵' => 'nuclear,energy,military',
        '미사일' => 'missile,military,defense',
        
        // 산업
        '삼성' => 'samsung,technology,korea',
        '애플' => 'apple,iphone,technology',
        '구글' => 'google,search,technology',
        '아마존' => 'amazon,ecommerce,warehouse',
        '테슬라' => 'tesla,electric-car,automotive',
        '현대' => 'hyundai,car,automotive',
        'k-pop' => 'kpop,concert,music',
        '케이팝' => 'kpop,concert,music',
        'bts' => 'concert,music,performance',
        
        // 기타
        '기후' => 'climate,environment,nature',
        '환경' => 'environment,nature,green',
        '에너지' => 'energy,solar,windmill',
        '석유' => 'oil,petroleum,industry',
        '가스' => 'natural-gas,pipeline,energy',
    ];
    
    // 카테고리별 기본 이미지 키워드
    private array $categoryDefaults = [
        'diplomacy' => 'diplomacy,politics,globe,summit',
        'economy' => 'economy,business,finance,stock-market',
        'technology' => 'technology,innovation,future,digital',
        'entertainment' => 'entertainment,music,movie,celebrity',
    ];
    
    /**
     * 제목과 카테고리 기반으로 관련 이미지 URL 생성
     */
    public function getImageUrl(string $title, string $category = '', int $width = 800, int $height = 600): string
    {
        $keywords = $this->extractKeywords($title, $category);
        
        // Unsplash Source URL (완전 무료, API 키 불필요)
        // 랜덤 시드 추가로 같은 키워드여도 다른 이미지 가능
        $seed = substr(md5($title), 0, 8);
        
        return "https://source.unsplash.com/{$width}x{$height}/?{$keywords}&sig={$seed}";
    }
    
    /**
     * 고품질 이미지 URL (Unsplash API 사용 시)
     * API 키가 있으면 더 정확한 검색 가능
     */
    public function getImageUrlWithApi(string $title, string $category = '', ?string $apiKey = null): ?string
    {
        if (!$apiKey) {
            return $this->getImageUrl($title, $category);
        }
        
        $keywords = $this->extractKeywords($title, $category);
        $query = urlencode(str_replace(',', ' ', $keywords));
        
        $url = "https://api.unsplash.com/search/photos?query={$query}&per_page=1&orientation=landscape";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Client-ID {$apiKey}",
                "Accept-Version: v1"
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data['results'][0]['urls']['regular'])) {
                return $data['results'][0]['urls']['regular'];
            }
        }
        
        // API 실패 시 기본 방식 사용
        return $this->getImageUrl($title, $category);
    }
    
    /**
     * 제목에서 키워드 추출
     */
    private function extractKeywords(string $title, string $category = ''): string
    {
        $title = mb_strtolower($title);
        $foundKeywords = [];
        
        // 키워드 맵에서 매칭되는 것 찾기
        foreach ($this->keywordMap as $keyword => $searchTerms) {
            if (mb_strpos($title, mb_strtolower($keyword)) !== false) {
                $foundKeywords[] = $searchTerms;
                if (count($foundKeywords) >= 2) {
                    break; // 최대 2개 키워드 그룹
                }
            }
        }
        
        // 키워드 찾지 못하면 카테고리 기본값 사용
        if (empty($foundKeywords)) {
            $category = strtolower($category);
            if (isset($this->categoryDefaults[$category])) {
                return $this->categoryDefaults[$category];
            }
            // 최종 기본값
            return 'news,newspaper,global';
        }
        
        return implode(',', array_unique(explode(',', implode(',', $foundKeywords))));
    }
    
    /**
     * 기존 뉴스의 이미지 일괄 업데이트
     */
    public function updateAllNewsImages(\PDO $pdo): array
    {
        $updated = [];
        $errors = [];
        
        // 모든 뉴스 가져오기
        $stmt = $pdo->query("SELECT id, title, category, image_url FROM news ORDER BY id DESC");
        $newsList = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($newsList as $news) {
            try {
                $newImageUrl = $this->getImageUrl($news['title'], $news['category'] ?? '');
                
                $updateStmt = $pdo->prepare("UPDATE news SET image_url = ? WHERE id = ?");
                $updateStmt->execute([$newImageUrl, $news['id']]);
                
                $updated[] = [
                    'id' => $news['id'],
                    'title' => $news['title'],
                    'old_image' => $news['image_url'],
                    'new_image' => $newImageUrl
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $news['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'updated' => $updated,
            'errors' => $errors,
            'total' => count($newsList),
            'success_count' => count($updated),
            'error_count' => count($errors)
        ];
    }
}
