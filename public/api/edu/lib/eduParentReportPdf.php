<?php
/**
 * GIST EDU — 부모 리포트 PDF (검정 표지 · Editorial gistudy v4)
 */
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

const EDU_PARENT_REPORT_BRAND = '#D85A30';
const EDU_PARENT_REPORT_INK = '#1a1a1a';
const EDU_PARENT_REPORT_MUTED = '#666666';

function eduParentReportH(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param list<string> $paragraphs */
function eduParentReportRenderLetter(array $paragraphs): string
{
    $html = '';
    foreach ($paragraphs as $p) {
        $html .= '<p class="letter-p">' . eduParentReportH($p) . '</p>';
    }

    return $html !== '' ? $html : '<p class="letter-p muted">아직 코치 편지를 생성하지 못했습니다.</p>';
}

/** @param array<string, mixed>|null $ba */
function eduParentReportRenderBeforeAfter(?array $ba): string
{
    if ($ba === null) {
        return '';
    }

    $beforeLabel = eduParentReportH((string) ($ba['before_label'] ?? ''));
    $beforeQuest = eduParentReportH((string) ($ba['before_quest'] ?? ''));
    $beforeText = eduParentReportH((string) ($ba['before_text'] ?? ''));
    $afterLabel = eduParentReportH((string) ($ba['after_label'] ?? ''));
    $afterQuest = eduParentReportH((string) ($ba['after_quest'] ?? ''));
    $afterText = eduParentReportH((string) ($ba['after_text'] ?? ''));

    return <<<HTML
<div class="section">
  <div class="eyebrow">생각이 자란 순간</div>
  <table class="ba-table" width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td class="ba-card before" width="46%" valign="top">
        <div class="ba-label">{$beforeLabel}</div>
        <div class="ba-quest">{$beforeQuest}</div>
        <div class="ba-text">"{$beforeText}"</div>
      </td>
      <td class="ba-arrow" width="8%" align="center" valign="middle">→</td>
      <td class="ba-card after" width="46%" valign="top">
        <div class="ba-label accent">{$afterLabel}</div>
        <div class="ba-quest">{$afterQuest}</div>
        <div class="ba-text">"{$afterText}"</div>
      </td>
    </tr>
  </table>
</div>
HTML;
}

/** @param list<array{level: int, label_ko: string, current: bool, done: bool}> $path */
function eduParentReportRenderGrowthPath(array $path): string
{
    $cells = '';
    foreach ($path as $step) {
        $label = eduParentReportH($step['label_ko']);
        $isCurrent = !empty($step['current']);
        $isDone = !empty($step['done']);
        $mark = $isCurrent ? '★' : ($isDone ? '✓' : '·');
        $class = $isCurrent ? 'step current' : ($isDone ? 'step done' : 'step');
        $cells .= <<<HTML
<td class="{$class}" align="center" valign="top">
  <div class="step-mark">{$mark}</div>
  <div class="step-label">{$label}</div>
</td>
HTML;
    }

    return <<<HTML
<div class="section">
  <div class="eyebrow">사고력 길</div>
  <table class="path-table" width="100%" cellpadding="0" cellspacing="0"><tr>{$cells}</tr></table>
</div>
HTML;
}

/** @param list<string> $tags */
function eduParentReportRenderTags(array $tags): string
{
    if ($tags === []) {
        return '';
    }
    $chips = '';
    foreach ($tags as $tag) {
        $chips .= '<span class="tag">' . eduParentReportH($tag) . '</span>';
    }

    return <<<HTML
<div class="section">
  <div class="eyebrow">따진 주제</div>
  <div class="tags">{$chips}</div>
</div>
HTML;
}

/** @param array<string, mixed> $payload */
function eduParentReportRenderHtml(array $payload, string $brandMarkDataUri = ''): string
{
    $name = eduParentReportH((string) ($payload['student_name'] ?? '학생'));
    $grade = eduParentReportH((string) ($payload['grade_label'] ?? ''));
    $period = eduParentReportH((string) ($payload['period_label'] ?? ''));
    $coverHeadline = eduParentReportH((string) ($payload['cover']['headline'] ?? ''));
    $letter = eduParentReportRenderLetter($payload['coach_letter']['paragraphs'] ?? []);
    $beforeAfter = eduParentReportRenderBeforeAfter($payload['before_after'] ?? null);
    $quote = trim((string) ($payload['student_quote'] ?? ''));
    $quoteBlock = $quote !== ''
        ? '<div class="quote-block">"' . eduParentReportH($quote) . '"</div>'
        : '';
    $growth = eduParentReportRenderGrowthPath($payload['growth_path'] ?? []);
    $tags = eduParentReportRenderTags($payload['topic_tags'] ?? []);
    $stats = $payload['stats'] ?? [];
    $completed = (int) ($stats['completed_count'] ?? 0);
    $streak = (int) ($stats['streak_days'] ?? 0);
    $coachLabel = eduParentReportH((string) ($stats['coach_label_ko'] ?? ''));

    $logoHtml = $brandMarkDataUri !== ''
        ? '<img src="' . $brandMarkDataUri . '" class="cover-logo" alt="">'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 0; }
@font-face {
  font-family: 'Noto Sans KR';
  font-style: normal;
  font-weight: 400;
  src: url('fonts/noto/NotoSansKR-Regular.ttf') format('truetype');
}
@font-face {
  font-family: 'Noto Sans KR';
  font-style: normal;
  font-weight: 700;
  src: url('fonts/noto/NotoSansKR-Bold.ttf') format('truetype');
}
body {
  font-family: 'Noto Sans KR', sans-serif;
  color: #1a1a1a;
  font-size: 11pt;
  line-height: 1.65;
  margin: 0;
  padding: 0;
}
.cover {
  background: #1a1a1a;
  color: #ffffff;
  min-height: 780px;
  padding: 56px 48px 48px;
  page-break-after: always;
  box-sizing: border-box;
}
.cover-logo { width: 36px; height: 36px; margin-bottom: 24px; }
.cover-brand {
  font-size: 13pt;
  letter-spacing: 0.02em;
  margin-bottom: 48px;
  color: #ffffff;
}
.cover-brand em { color: #D85A30; font-style: normal; }
.cover-headline {
  font-size: 26pt;
  font-weight: 700;
  line-height: 1.35;
  margin: 0 0 20px;
  max-width: 90%;
}
.cover-sub {
  font-size: 11pt;
  color: #cccccc;
  margin-top: 32px;
}
.cover-accent { color: #D85A30; }
.content { padding: 40px 44px 48px; }
.section { margin-bottom: 28px; page-break-inside: avoid; }
.eyebrow {
  font-size: 9pt;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #D85A30;
  margin-bottom: 10px;
}
.letter-title {
  font-size: 18pt;
  font-weight: 700;
  margin: 0 0 16px;
}
.letter-p { margin: 0 0 12px; }
.muted { color: #666666; }
.quote-block {
  border-left: 4px solid #D85A30;
  padding: 14px 18px;
  background: #fef3ef;
  font-size: 12pt;
  font-weight: 700;
  line-height: 1.55;
  margin: 8px 0 0;
}
.ba-table { border-collapse: separate; border-spacing: 0; }
.ba-card {
  border: 1.5px solid #e8e8e8;
  border-radius: 10px;
  padding: 14px 12px;
  background: #fafafa;
}
.ba-card.after { border-color: #D85A30; background: #fef3ef; }
.ba-label { font-size: 8.5pt; color: #666; margin-bottom: 4px; }
.ba-label.accent { color: #D85A30; font-weight: 700; }
.ba-quest { font-size: 8.5pt; color: #888; margin-bottom: 8px; }
.ba-text { font-size: 10.5pt; font-weight: 700; line-height: 1.5; }
.ba-arrow { font-size: 16pt; color: #D85A30; font-weight: 700; }
.path-table td { width: 20%; padding: 6px 4px; }
.step-mark { font-size: 14pt; font-weight: 700; color: #ccc; margin-bottom: 4px; }
.step.done .step-mark { color: #D85A30; }
.step.current .step-mark { color: #D85A30; font-size: 16pt; }
.step-label { font-size: 8.5pt; font-weight: 700; color: #666; }
.step.current .step-label { color: #1a1a1a; }
.tags { line-height: 1.9; }
.tag {
  display: inline-block;
  border: 1.5px solid #D85A30;
  color: #1a1a1a;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 8.5pt;
  font-weight: 700;
  margin: 0 6px 6px 0;
}
.stats-bar {
  margin-top: 32px;
  padding-top: 18px;
  border-top: 2px solid #1a1a1a;
  page-break-inside: avoid;
}
.stats-grid { width: 100%; border-collapse: collapse; }
.stats-grid td {
  text-align: center;
  padding: 8px 6px;
  vertical-align: top;
}
.stat-num {
  font-size: 22pt;
  font-weight: 700;
  color: #D85A30;
  line-height: 1.1;
}
.stat-label { font-size: 9pt; color: #666; margin-top: 4px; }
.footer {
  margin-top: 24px;
  font-size: 8pt;
  color: #999;
  text-align: center;
}
</style>
</head>
<body>

<div class="cover">
  {$logoHtml}
  <div class="cover-brand"><em>●</em> gistudy</div>
  <h1 class="cover-headline">{$coverHeadline}</h1>
  <div class="cover-sub">
    <span class="cover-accent">{$name}</span> · {$grade}<br>
    {$period}
  </div>
</div>

<div class="content">
  <div class="section">
    <div class="eyebrow">코치의 편지</div>
    <h2 class="letter-title">{$name}의 탐구 이야기</h2>
    {$letter}
  </div>

  {$beforeAfter}

  <div class="section">
    <div class="eyebrow">학생 글 인용</div>
    {$quoteBlock}
  </div>

  {$growth}
  {$tags}

  <div class="stats-bar">
    <table class="stats-grid" width="100%">
      <tr>
        <td>
          <div class="stat-num">{$completed}</div>
          <div class="stat-label">완주</div>
        </td>
        <td>
          <div class="stat-num">{$streak}</div>
          <div class="stat-label">연속 탐구 (일)</div>
        </td>
        <td>
          <div class="stat-num">{$coachLabel}</div>
          <div class="stat-label">현재 사고력</div>
        </td>
      </tr>
    </table>
  </div>

  <div class="footer"><em>●</em> gistudy · 부모 리포트 · EDU</div>
</div>

</body>
</html>
HTML;
}

function eduParentReportCreateDompdf(string $fontDir): Dompdf
{
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Noto Sans KR');
    $options->set('dpi', 150);
    $options->set('isFontSubsettingEnabled', true);

    $chroot = dirname($fontDir);
    $options->set('chroot', $chroot);
    if (is_dir($fontDir)) {
        $options->set('fontDir', $fontDir);
    }

    $dompdf = new Dompdf($options);
    $fontMetrics = $dompdf->getFontMetrics();
    if (is_dir($fontDir)) {
        $fontMetrics->registerFont(
            ['family' => 'Noto Sans KR', 'style' => 'normal', 'weight' => 'normal'],
            $fontDir . '/NotoSansKR-Regular.ttf'
        );
        $fontMetrics->registerFont(
            ['family' => 'Noto Sans KR', 'style' => 'normal', 'weight' => 'bold'],
            $fontDir . '/NotoSansKR-Bold.ttf'
        );
    }

    return $dompdf;
}

/** @param array<string, mixed> $payload */
function eduParentReportRenderPdf(array $payload): string
{
    $root = eduFindProjectRoot();
    if (!is_file($root . 'vendor/autoload.php')) {
        throw new RuntimeException('composer autoload missing');
    }
    require_once $root . 'vendor/autoload.php';

    $fontDir = $root . 'public/fonts/noto';
    $faviconPath = $root . 'public/favicon-G-edu.svg';
    $brandMarkDataUri = '';
    if (is_file($faviconPath)) {
        $brandMarkDataUri = 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents($faviconPath));
    }

    $html = eduParentReportRenderHtml($payload, $brandMarkDataUri);
    $dompdf = eduParentReportCreateDompdf($fontDir);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return (string) $dompdf->output();
}

/** @param array<string, mixed> $payload */
function eduParentReportPdfFilename(array $payload): string
{
    $name = preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string) ($payload['student_name'] ?? 'student')) ?: 'student';
    $date = date('Y-m-d');

    return "gistudy-report-{$name}-{$date}.pdf";
}
