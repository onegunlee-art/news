<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 전략 레포트 HTML 문서 생성 서비스
 * UN Security Council Resolution 스타일 레이아웃 + the gist 브랜딩
 */
class StrategicReportDocumentService
{
    private array $config;
    private string $logoBase64 = '';

    public function __construct()
    {
        $configPath = dirname(__DIR__, 3) . '/config/strategic_report_document.php';
        $this->config = is_file($configPath) ? require $configPath : [];
        $this->loadLogoBase64();
    }

    private function loadLogoBase64(): void
    {
        $logoPath = $this->config['logo_path'] ?? '';
        if ($logoPath !== '' && is_file($logoPath)) {
            $imageData = file_get_contents($logoPath);
            if ($imageData !== false) {
                $mimeType = mime_content_type($logoPath) ?: 'image/jpeg';
                $this->logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
        }
    }

    /**
     * 레포트 데이터로 HTML 문서 생성
     * 
     * @param array $report weekly_strategic_reports 테이블 row
     * @return string HTML 문서
     */
    public function renderHtml(array $report): string
    {
        $scqa = $this->getScqa($report);
        $documentNumber = $this->formatDocumentNumber($report);
        $dateFormatted = $this->formatDate($report);
        $title = $this->extractTitle($scqa);
        
        $html = $this->getDocumentHeader($documentNumber, $dateFormatted, $title);
        $html .= $this->renderBody($scqa, $report);
        $html .= $this->getDocumentFooter($documentNumber);

        return $html;
    }

    private function getScqa(array $report): array
    {
        $scqaEdited = $report['scqa_edited_json'] ?? null;
        $scqaRaw = $report['scqa_raw_json'] ?? null;

        if (is_string($scqaEdited) && $scqaEdited !== '' && $scqaEdited !== 'null') {
            $decoded = json_decode($scqaEdited, true);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        if (is_string($scqaRaw)) {
            $decoded = json_decode($scqaRaw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($scqaRaw)) {
            return $scqaRaw;
        }

        return [];
    }

    private function formatDocumentNumber(array $report): string
    {
        $prefix = $this->config['document_prefix'] ?? 'TG/SR';
        $week = (string) ($report['report_week'] ?? date('Y-\WW'));
        return $prefix . '/' . $week;
    }

    private function formatDate(array $report): string
    {
        $periodEnd = $report['period_end'] ?? $report['created_at'] ?? date('Y-m-d');
        $timestamp = strtotime($periodEnd);
        return $timestamp !== false ? date('j F Y', $timestamp) : date('j F Y');
    }

    private function extractTitle(array $scqa): string
    {
        $coreQuestion = trim((string) ($scqa['core_question'] ?? ''));
        if ($coreQuestion !== '') {
            return $coreQuestion;
        }

        $shiftHeadline = trim((string) ($scqa['structural_shift']['headline'] ?? ''));
        if ($shiftHeadline !== '') {
            return $shiftHeadline;
        }

        return '주간 전략 인텔리전스 레포트';
    }

    private function getDocumentHeader(string $documentNumber, string $date, string $title): string
    {
        $org = htmlspecialchars($this->config['organization'] ?? 'the gist.', ENT_QUOTES, 'UTF-8');
        $distLabel = htmlspecialchars($this->config['distribution_label'] ?? 'Strategic Intelligence', ENT_QUOTES, 'UTF-8');
        $distType = htmlspecialchars($this->config['distribution_type'] ?? 'Distr.: General', ENT_QUOTES, 'UTF-8');
        $docNumEsc = htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8');
        $dateEsc = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        
        $logoHtml = $this->logoBase64 !== ''
            ? '<img src="' . $this->logoBase64 . '" alt="the gist" class="logo" />'
            : '<div class="logo-text">' . $org . '</div>';

        return <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titleEsc}</title>
    <style>
        @font-face {
            font-family: 'Noto Sans KR';
            src: url('fonts/noto/NotoSansKR-Regular.otf') format('opentype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Noto Sans KR';
            src: url('fonts/noto/NotoSansKR-Bold.otf') format('opentype');
            font-weight: bold;
            font-style: normal;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4 portrait;
            margin: 25mm 25mm 30mm 25mm;
        }

        body {
            font-family: 'Noto Sans KR', 'Malgun Gothic', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #1a1a1a;
            background: #fff;
        }

        .document {
            max-width: 170mm;
            margin: 0 auto;
            padding: 20px 0;
        }

        /* Header - UN 스타일 */
        .header {
            border-bottom: 1px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            height: 50px;
            width: auto;
        }

        .logo-text {
            font-family: serif;
            font-size: 24pt;
            font-weight: bold;
            letter-spacing: -1px;
        }

        .org-name {
            font-family: serif;
            font-size: 18pt;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .header-right {
            text-align: right;
            font-family: 'Times New Roman', serif;
        }

        .doc-number {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header-meta {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            margin-top: 10px;
        }

        .distr-label {
            font-style: italic;
        }

        .doc-date {
            text-align: right;
        }

        /* Title */
        .title-section {
            text-align: center;
            margin: 30px 0;
            padding: 0 20px;
        }

        .main-title {
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 11pt;
            font-style: italic;
            color: #444;
        }

        /* Body sections */
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .section-number {
            font-family: serif;
            font-style: italic;
            margin-right: 8px;
        }

        /* Numbered paragraphs - UN 스타일 */
        .numbered-para {
            margin-bottom: 12px;
            text-align: justify;
            text-indent: 0;
        }

        .para-number {
            font-weight: bold;
            margin-right: 10px;
        }

        .sub-para {
            margin-left: 25px;
            margin-bottom: 8px;
        }

        .sub-para-marker {
            font-style: italic;
            margin-right: 8px;
        }

        /* Executive Summary Box */
        .executive-summary {
            background: #f8f9fa;
            border-left: 4px solid #333;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-size: 11pt;
        }

        .executive-summary-label {
            font-weight: bold;
            font-size: 10pt;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Structural Shift */
        .structural-shift {
            background: #fff5e6;
            border: 1px solid #ffc107;
            padding: 15px;
            margin-bottom: 25px;
        }

        .shift-headline {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .shift-detail {
            display: flex;
            gap: 20px;
            font-size: 10pt;
        }

        .shift-from, .shift-to {
            flex: 1;
        }

        .shift-label {
            font-weight: bold;
            display: block;
            margin-bottom: 3px;
        }

        /* Timeline */
        .timeline-item {
            display: flex;
            gap: 15px;
            margin-bottom: 12px;
            padding-left: 10px;
            border-left: 2px solid #ddd;
        }

        .timeline-date {
            font-weight: bold;
            min-width: 100px;
            font-size: 10pt;
            color: #555;
        }

        .timeline-content {
            flex: 1;
        }

        /* Narrative Collisions */
        .collision {
            margin-bottom: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 4px;
        }

        .collision-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 11pt;
        }

        .collision-views {
            display: flex;
            gap: 20px;
        }

        .collision-view {
            flex: 1;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
        }

        .view-actor {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 5px;
            color: #444;
        }

        /* Scenarios */
        .scenario {
            margin-bottom: 15px;
            padding: 10px 15px;
            border-left: 3px solid #007bff;
        }

        .scenario-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .scenario-prob {
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }

        /* Action Matrix */
        .action-matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-top: 10px;
        }

        .action-matrix th,
        .action-matrix td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
        }

        .action-matrix th {
            background: #f0f0f0;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }

        .page-number:before {
            content: counter(page);
        }

        .page-number:after {
            content: "/" counter(pages);
        }

        /* Source references */
        .source-ref {
            font-size: 9pt;
            color: #666;
            vertical-align: super;
        }

        /* Print styles */
        @media print {
            body {
                font-size: 10pt;
            }
            .document {
                max-width: 100%;
            }
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="document">
        <header class="header">
            <div class="header-top">
                <div class="header-left">
                    {$logoHtml}
                </div>
                <div class="header-right">
                    <div class="doc-number">{$docNumEsc}</div>
                </div>
            </div>
            <div class="header-meta">
                <div class="distr-label">{$distLabel}<br>{$distType}</div>
                <div class="doc-date">{$dateEsc}</div>
            </div>
        </header>

        <div class="title-section">
            <h1 class="main-title">{$titleEsc}</h1>
        </div>
HTML;
    }

    private function renderBody(array $scqa, array $report): string
    {
        $html = '';
        $sectionNum = 1;

        $execSummary = trim((string) ($scqa['executive_summary'] ?? ''));
        if ($execSummary !== '') {
            $html .= $this->renderExecutiveSummary($execSummary);
        }

        $structuralShift = $scqa['structural_shift'] ?? null;
        if (is_array($structuralShift) && trim((string) ($structuralShift['headline'] ?? '')) !== '') {
            $html .= $this->renderStructuralShift($structuralShift, $sectionNum++);
        }

        $situation = $scqa['situation'] ?? null;
        if (is_array($situation)) {
            $html .= $this->renderSituation($situation, $sectionNum++);
        }

        $complication = $scqa['complication'] ?? null;
        if (is_array($complication)) {
            $perspectives = $complication['perspectives'] ?? [];
            $collisions = $complication['narrative_collisions'] ?? [];
            
            if ($perspectives !== [] || $collisions !== []) {
                $html .= $this->renderComplication($complication, $sectionNum++);
            }
        }

        $answer = $scqa['answer'] ?? null;
        if (is_array($answer)) {
            $html .= $this->renderAnswer($answer, $sectionNum++);
        }

        $meta = $report['meta_json'] ?? null;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        if (is_array($meta)) {
            $html .= $this->renderMetaSummary($meta, $sectionNum);
        }

        return $html;
    }

    private function renderExecutiveSummary(string $summary): string
    {
        $label = htmlspecialchars($this->config['section_labels']['executive_summary'] ?? '요약', ENT_QUOTES, 'UTF-8');
        $content = nl2br(htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'));
        
        return <<<HTML
        <div class="executive-summary">
            <div class="executive-summary-label">{$label}</div>
            <p>{$content}</p>
        </div>
HTML;
    }

    private function renderStructuralShift(array $shift, int $sectionNum): string
    {
        $label = htmlspecialchars($this->config['section_labels']['structural_shift'] ?? '구조적 변화', ENT_QUOTES, 'UTF-8');
        $headline = htmlspecialchars((string) ($shift['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fromPattern = htmlspecialchars((string) ($shift['from_pattern'] ?? ''), ENT_QUOTES, 'UTF-8');
        $toPattern = htmlspecialchars((string) ($shift['to_pattern'] ?? ''), ENT_QUOTES, 'UTF-8');
        $whyNow = htmlspecialchars((string) ($shift['why_now'] ?? ''), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <div class="section structural-shift">
            <h2 class="section-title"><span class="section-number">{$sectionNum}.</span> {$label}</h2>
            <div class="shift-headline">{$headline}</div>
HTML;

        if ($fromPattern !== '' || $toPattern !== '') {
            $html .= <<<HTML
            <div class="shift-detail">
                <div class="shift-from">
                    <span class="shift-label">이전 패턴:</span>
                    {$fromPattern}
                </div>
                <div class="shift-to">
                    <span class="shift-label">새로운 패턴:</span>
                    {$toPattern}
                </div>
            </div>
HTML;
        }

        if ($whyNow !== '') {
            $html .= '<p class="numbered-para" style="margin-top: 10px;"><strong>왜 지금인가:</strong> ' . $whyNow . '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderSituation(array $situation, int $sectionNum): string
    {
        $label = htmlspecialchars($this->config['section_labels']['situation'] ?? '상황 분석', ENT_QUOTES, 'UTF-8');
        
        $html = <<<HTML
        <div class="section">
            <h2 class="section-title"><span class="section-number">{$sectionNum}.</span> {$label}</h2>
HTML;

        $narrative = trim((string) ($situation['narrative'] ?? ''));
        if ($narrative !== '') {
            $html .= '<p class="numbered-para">' . nl2br(htmlspecialchars($narrative, ENT_QUOTES, 'UTF-8')) . '</p>';
        }

        $timeline = $situation['timeline'] ?? [];
        if ($timeline !== []) {
            $timelineLabel = htmlspecialchars($this->config['section_labels']['timeline'] ?? '주요 타임라인', ENT_QUOTES, 'UTF-8');
            $html .= '<h3 style="font-size: 11pt; margin: 15px 0 10px;">' . $timelineLabel . '</h3>';
            
            foreach ($timeline as $event) {
                $date = htmlspecialchars((string) ($event['date'] ?? ''), ENT_QUOTES, 'UTF-8');
                $eventText = htmlspecialchars((string) ($event['event'] ?? ''), ENT_QUOTES, 'UTF-8');
                $significance = htmlspecialchars((string) ($event['significance'] ?? ''), ENT_QUOTES, 'UTF-8');
                
                $html .= <<<HTML
                <div class="timeline-item">
                    <div class="timeline-date">{$date}</div>
                    <div class="timeline-content">
                        <strong>{$eventText}</strong>
                        {$significance}
                    </div>
                </div>
HTML;
            }
        }

        $html .= '</div>';
        return $html;
    }

    private function renderComplication(array $complication, int $sectionNum): string
    {
        $label = htmlspecialchars($this->config['section_labels']['narrative_collisions'] ?? '관점 충돌', ENT_QUOTES, 'UTF-8');
        
        $html = <<<HTML
        <div class="section">
            <h2 class="section-title"><span class="section-number">{$sectionNum}.</span> {$label}</h2>
HTML;

        $collisions = $complication['narrative_collisions'] ?? [];
        foreach ($collisions as $idx => $collision) {
            $title = htmlspecialchars((string) ($collision['collision_point'] ?? '충돌 ' . ($idx + 1)), ENT_QUOTES, 'UTF-8');
            $actorA = htmlspecialchars((string) ($collision['actor_a'] ?? '관점 A'), ENT_QUOTES, 'UTF-8');
            $viewA = htmlspecialchars((string) ($collision['view_a'] ?? ''), ENT_QUOTES, 'UTF-8');
            $actorB = htmlspecialchars((string) ($collision['actor_b'] ?? '관점 B'), ENT_QUOTES, 'UTF-8');
            $viewB = htmlspecialchars((string) ($collision['view_b'] ?? ''), ENT_QUOTES, 'UTF-8');
            $implication = htmlspecialchars((string) ($collision['implication'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            $html .= <<<HTML
            <div class="collision">
                <div class="collision-title">{$title}</div>
                <div class="collision-views">
                    <div class="collision-view">
                        <div class="view-actor">{$actorA}</div>
                        <p>{$viewA}</p>
                    </div>
                    <div class="collision-view">
                        <div class="view-actor">{$actorB}</div>
                        <p>{$viewB}</p>
                    </div>
                </div>
HTML;
            if ($implication !== '') {
                $html .= '<p style="margin-top: 10px; font-style: italic; color: #555;">→ ' . $implication . '</p>';
            }
            $html .= '</div>';
        }

        $perspectives = $complication['perspectives'] ?? [];
        if ($perspectives !== []) {
            $html .= '<h3 style="font-size: 11pt; margin: 15px 0 10px;">주요 관점</h3>';
            foreach ($perspectives as $idx => $perspective) {
                $actor = htmlspecialchars((string) ($perspective['actor'] ?? ''), ENT_QUOTES, 'UTF-8');
                $stance = htmlspecialchars((string) ($perspective['stance'] ?? ''), ENT_QUOTES, 'UTF-8');
                $rationale = htmlspecialchars((string) ($perspective['rationale'] ?? ''), ENT_QUOTES, 'UTF-8');
                
                $html .= '<div class="numbered-para"><span class="para-number">(' . chr(97 + $idx) . ')</span>';
                if ($actor !== '') {
                    $html .= '<strong>' . $actor . ':</strong> ';
                }
                $html .= $stance;
                if ($rationale !== '') {
                    $html .= ' — <em>' . $rationale . '</em>';
                }
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    private function renderAnswer(array $answer, int $sectionNum): string
    {
        $label = htmlspecialchars($this->config['section_labels']['answer'] ?? '시사점 및 전망', ENT_QUOTES, 'UTF-8');
        
        $html = <<<HTML
        <div class="section">
            <h2 class="section-title"><span class="section-number">{$sectionNum}.</span> {$label}</h2>
HTML;

        $implications = $answer['implications'] ?? [];
        if ($implications !== []) {
            foreach ($implications as $idx => $impl) {
                $text = htmlspecialchars((string) (is_array($impl) ? ($impl['text'] ?? '') : $impl), ENT_QUOTES, 'UTF-8');
                if ($text !== '') {
                    $html .= '<p class="numbered-para"><span class="para-number">' . ($idx + 1) . '.</span> ' . $text . '</p>';
                }
            }
        }

        $whyMatters = $answer['why_it_matters_chain'] ?? [];
        if ($whyMatters !== []) {
            $html .= '<h3 style="font-size: 11pt; margin: 15px 0 10px;">왜 중요한가</h3>';
            foreach ($whyMatters as $idx => $matter) {
                $text = htmlspecialchars((string) (is_array($matter) ? ($matter['text'] ?? $matter['step'] ?? '') : $matter), ENT_QUOTES, 'UTF-8');
                if ($text !== '') {
                    $html .= '<p class="sub-para"><span class="sub-para-marker">→</span> ' . $text . '</p>';
                }
            }
        }

        $scenarios = $answer['scenarios'] ?? [];
        if ($scenarios !== []) {
            $scenarioLabel = htmlspecialchars($this->config['section_labels']['scenarios'] ?? '시나리오 분석', ENT_QUOTES, 'UTF-8');
            $html .= '<h3 style="font-size: 11pt; margin: 20px 0 10px;">' . $scenarioLabel . '</h3>';
            
            foreach ($scenarios as $scenario) {
                $name = htmlspecialchars((string) ($scenario['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $prob = htmlspecialchars((string) ($scenario['probability'] ?? ''), ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars((string) ($scenario['description'] ?? ''), ENT_QUOTES, 'UTF-8');
                $outcome = htmlspecialchars((string) ($scenario['outcome'] ?? ''), ENT_QUOTES, 'UTF-8');
                
                $html .= <<<HTML
                <div class="scenario">
                    <div class="scenario-name">{$name}</div>
HTML;
                if ($prob !== '') {
                    $html .= '<div class="scenario-prob">확률: ' . $prob . '</div>';
                }
                if ($desc !== '') {
                    $html .= '<p>' . $desc . '</p>';
                }
                if ($outcome !== '') {
                    $html .= '<p style="margin-top: 5px; font-style: italic;">예상 결과: ' . $outcome . '</p>';
                }
                $html .= '</div>';
            }
        }

        $actionMatrix = $answer['action_matrix'] ?? [];
        if ($actionMatrix !== []) {
            $matrixLabel = htmlspecialchars($this->config['section_labels']['action_matrix'] ?? '핵심 행동 지표', ENT_QUOTES, 'UTF-8');
            $html .= '<h3 style="font-size: 11pt; margin: 20px 0 10px;">' . $matrixLabel . '</h3>';
            $html .= '<table class="action-matrix"><thead><tr><th>행위자</th><th>주시해야 할 행동</th><th>의미</th></tr></thead><tbody>';
            
            foreach ($actionMatrix as $action) {
                $actor = htmlspecialchars((string) ($action['actor'] ?? ''), ENT_QUOTES, 'UTF-8');
                $watch = htmlspecialchars((string) ($action['action_to_watch'] ?? ''), ENT_QUOTES, 'UTF-8');
                $signal = htmlspecialchars((string) ($action['signal'] ?? ''), ENT_QUOTES, 'UTF-8');
                
                $html .= "<tr><td>{$actor}</td><td>{$watch}</td><td>{$signal}</td></tr>";
            }
            
            $html .= '</tbody></table>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderMetaSummary(array $meta, int $sectionNum): string
    {
        $confidence = (string) ($meta['confidence'] ?? $meta['verification']['confidence_label'] ?? 'medium');
        $confidenceKo = match ($confidence) {
            'high' => '높음',
            'low' => '낮음',
            default => '보통',
        };

        $gistAnchors = (int) ($meta['gist_anchor_count'] ?? 0);
        $externalMatched = (int) ($meta['matched_external_count'] ?? 0);
        $total = (int) ($meta['article_total'] ?? ($gistAnchors + $externalMatched));

        $html = <<<HTML
        <div class="section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc;">
            <p style="font-size: 9pt; color: #666; text-align: right;">
                신뢰도: {$confidenceKo} | 출처: the gist {$gistAnchors}건, 외부 {$externalMatched}건 (총 {$total}건)
            </p>
        </div>
HTML;

        return $html;
    }

    private function getDocumentFooter(string $documentNumber): string
    {
        $docNumEsc = htmlspecialchars($documentNumber, ENT_QUOTES, 'UTF-8');
        
        return <<<HTML
    </div>

    <div class="footer">
        <span>{$docNumEsc}</span>
    </div>
</body>
</html>
HTML;
    }
}
