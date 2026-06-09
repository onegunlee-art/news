<?php
/**
 * GIST EDU 부모 리포트 샘플 PDF 생성
 * 디자인: Editorial Orange v3.1 (edu.pdf + 생각 변화 Share Card)
 *
 * Usage: php tools/generate_edu_parent_report_pdfs.php
 */
declare(strict_types=1);

ini_set('memory_limit', '512M');

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
} else {
    fwrite(STDERR, "vendor/autoload.php not found. Run composer install.\n");
    exit(1);
}

use Dompdf\Dompdf;
use Dompdf\Options;

$samples = require __DIR__ . '/edu_parent_report_samples_data.php';

$outDir = $root . '/docs/exports/gist-edu/parent-reports';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create output dir: {$outDir}\n");
    exit(1);
}

$fontDir = $root . '/public/fonts/noto';
$faviconPath = $root . '/public/favicon-G-edu.svg';
$brandMarkDataUri = '';
if (is_file($faviconPath)) {
    $brandMarkDataUri = 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents($faviconPath));
}

const BRAND = '#f05123';
const BRAND_DARK = '#e03a19';
const BRAND_BG = '#fef3ef';
const INK = '#1a1a1a';
const MUTED = '#666666';
const LINE = '#eeeeee';
const NEUTRAL_BG = '#f8f8f8';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderNarrativeParagraphs(array $paragraphs): string
{
    $html = '';
    foreach ($paragraphs as $p) {
        $safe = h($p);
        $safe = str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<strong>', '</strong>'], $safe);
        $html .= '<p class="narrative-p">' . $safe . '</p>';
    }
    return $html;
}

function renderParentAlert(?array $alert): string
{
    if ($alert === null || empty($alert['body'])) {
        return '';
    }
    $title = h($alert['title'] ?? '부모 알림');
    $body = h($alert['body']);
    return <<<HTML
<div class="notif">
  <strong>{$title}</strong><br>
  {$body}
</div>
HTML;
}

function renderTransform(array $t): string
{
    return <<<HTML
<table class="transform-table" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="tcard before" width="42%" valign="top">
      <div class="tc-time">{$t['before_time']}</div>
      <div class="tc-stance before-stance">{$t['before_stance']}</div>
      <div class="tc-text">{$t['before_text']}</div>
      <div class="tc-sub">{$t['before_sub']}</div>
    </td>
    <td class="arrow-col" width="16%" align="center" valign="middle">
      <div class="arrow-icon">→</div>
      <div class="arrow-label">반론 검토 후</div>
    </td>
    <td class="tcard after" width="42%" valign="top">
      <div class="tc-time after-time">{$t['after_time']}</div>
      <div class="tc-stance after-stance">{$t['after_stance']}</div>
      <div class="tc-text">{$t['after_text']}</div>
      <div class="tc-sub">{$t['after_sub']}</div>
    </td>
  </tr>
</table>
HTML;
}

function renderWritingBlocks(array $wg): string
{
    $v1Lines = '';
    foreach ($wg['v1_sentences'] as $line) {
        $v1Lines .= '<li>' . h($line) . '</li>';
    }

    $delta = h($wg['delta'] ?? '');
    $v2Block = '';
    if (!empty($wg['v2_sentences'])) {
        $v2Lines = '';
        foreach ($wg['v2_sentences'] as $line) {
            $v2Lines .= '<li>' . h($line) . '</li>';
        }
        $v2Block = <<<HTML
<div class="wblock w2">
  <div class="wlabel">이번 주 · Writing v2</div>
  <ol>{$v2Lines}</ol>
  <div class="wdelta">{$delta}</div>
</div>
HTML;
    } else {
        $v2Block = <<<HTML
<div class="wblock w2">
  <div class="wlabel">이번 주 · Writing v2</div>
  <p class="wdelta-p">{$delta}</p>
</div>
HTML;
    }

    $feedback = !empty($wg['teacher_feedback'])
        ? '<p class="note">교사 피드백: ' . h($wg['teacher_feedback']) . '</p>'
        : '';

    $challengerBlock = '';
    if (!empty($wg['challenger_slots'])) {
        $challengerBlock = '<div class="challenger-box"><strong>GIST Challenger 기록</strong><table class="data-mini">';
        foreach ($wg['challenger_slots'] as $slot => $text) {
            $challengerBlock .= '<tr><td>' . h($slot) . '</td><td>' . h($text) . '</td></tr>';
        }
        $challengerBlock .= '</table></div>';
    }

    return <<<HTML
<div class="wblock w1">
  <div class="wlabel">한 달 전 · Writing v1</div>
  <ol>{$v1Lines}</ol>
  <div class="wdelta muted-delta">근거 없음 · 반론 미검토</div>
</div>
{$feedback}
{$v2Block}
{$challengerBlock}
HTML;
}

function renderShareCard(array $card): string
{
    $kicker = h($card['kicker'] ?? '이번 달 가장 큰 변화');
    $before = h($card['before'] ?? '');
    $after = h($card['after'] ?? '');
    return <<<HTML
<div class="share-card">
  <div class="share-kicker">{$kicker}</div>
  <table class="share-table" width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td class="share-before" width="44%" valign="middle">{$before}</td>
      <td class="share-arrow" width="12%" align="center" valign="middle">→</td>
      <td class="share-after" width="44%" valign="middle">{$after}</td>
    </tr>
  </table>
</div>
HTML;
}

function renderMonthlyFirstChanges(array $items): string
{
    $lis = '';
    foreach ($items as $line) {
        $lis .= '<li>' . h($line) . '</li>';
    }
    return '<ul class="changes-list">' . $lis . '</ul>';
}

function renderQualitativeGrowth(array $items): string
{
    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr><td><strong>' . h($item['label']) . '</strong></td><td>' . h($item['detail']) . '</td></tr>';
    }
    return '<table class="data qual-table" width="100%"><tr><th>영역</th><th>관찰</th></tr>' . $rows . '</table>';
}

function renderTierSection(array $tp): string
{
    $pct = max(0, min(100, (int) ($tp['progress_pct'] ?? 0)));
    $tierEn = h($tp['tier_label_en']);
    $tierKo = ($tp['tier_label_ko'] ?? '') !== '' ? ' (' . h($tp['tier_label_ko']) . ')' : '';
    $initial = strtoupper(substr($tp['tier_label_en'], 0, 1));

    $xpLine = '';
    if (!empty($tp['next_tier_label_en']) && !empty($tp['xp_next_tier'])) {
        $xpLine = number_format((int) $tp['xp_current']) . ' / ' . number_format((int) $tp['xp_next_tier']) . ' XP';
        $barLabels = '<span>' . h($tp['tier_label_en']) . '</span><span>' . h($tp['next_tier_label_en']) . '</span>';
    } else {
        $xpLine = number_format((int) $tp['xp_current']) . ' XP · 최고 등급';
        $barLabels = '<span>' . h($tp['tier_label_en']) . '</span><span>—</span>';
    }

    $streak = (int) ($tp['streak_days'] ?? 0);

    return <<<HTML
<table class="tier-table" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td width="38%" valign="middle">
      <table cellpadding="0" cellspacing="0"><tr>
        <td><div class="tier-icon">{$initial}</div></td>
        <td style="padding-left:10px">
          <div class="tier-name">{$tierEn}{$tierKo}</div>
          <div class="tier-sub">{$xpLine}</div>
        </td>
      </tr></table>
    </td>
    <td width="42%" valign="middle">
      <div class="tier-bar-label">{$barLabels}</div>
      <div class="tier-bar-track"><div class="tier-bar-fill" style="width:{$pct}%"></div></div>
    </td>
    <td width="20%" align="right" valign="middle">
      <span class="streak-tag">{$streak}일 연속</span>
    </td>
  </tr>
</table>
HTML;
}

function renderSampleHtml(array $sample, string $brandMarkDataUri): string
{
    $tp = $sample['tier_progress'] ?? [];
    $t = $sample['transform'] ?? [];
    $wg = $sample['writing_growth'];
    $qg = $sample['qualitative_growth'] ?? [];

    foreach ($t as $k => $v) {
        $t[$k] = h((string) $v);
    }

    $brandImg = $brandMarkDataUri !== ''
        ? '<img src="' . $brandMarkDataUri . '" alt="g." class="brand-mark" />'
        : '<span class="brand-text">g.</span>';

    $studentName = h($sample['student_name']);
    $periodLabel = h($sample['period_label'] ?? $sample['period']);
    $questCode = h($sample['quest_code']);
    $questTitle = h($sample['quest_title']);
    $leadKicker = h($sample['lead_kicker'] ?? '이달의 핵심 순간');
    $leadHeadline = h($sample['lead_headline'] ?? '');
    $leadEm = h($sample['lead_headline_em'] ?? '');
    $monthlyEval = h($sample['monthly_eval'] ?? '');
    $period = h($sample['period']);

    $shareCard = !empty($sample['share_card'])
        ? renderShareCard($sample['share_card'])
        : '';
    $notif = renderParentAlert($sample['parent_alert'] ?? null);
    $transform = renderTransform($t);
    $narrative = renderNarrativeParagraphs($sample['narrative_paragraphs'] ?? []);
    $writing = renderWritingBlocks($wg);
    $firstChanges = renderMonthlyFirstChanges($sample['monthly_first_changes'] ?? []);
    $qualitative = renderQualitativeGrowth($qg);
    $tier = renderTierSection($tp);

    return <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8" />
<style>
  @page { margin: 14mm 14mm; }
  body {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 10pt;
    color: {$INK};
    line-height: 1.6;
    background: #FFFFFF;
  }
  .brand-row { margin-bottom: 6px; }
  .brand-mark { height: 78px; width: 78px; vertical-align: middle; }
  .brand-text { font-size: 48pt; font-weight: bold; color: {$INK}; }
  .wordmark { font-size: 11pt; color: {$MUTED}; margin-top: 4px; letter-spacing: 0.02em; }
  .wordmark em { color: {$BRAND}; font-style: normal; font-weight: 600; }

  .share-card {
    border: 2px solid {$BRAND};
    border-radius: 10px;
    padding: 16px 18px;
    margin-bottom: 18px;
    background: {$BRAND_BG};
    page-break-inside: avoid;
  }
  .share-kicker { font-size: 9pt; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: {$BRAND}; margin-bottom: 12px; }
  .share-table { border-collapse: collapse; }
  .share-before { font-size: 11pt; font-weight: 500; color: {$MUTED}; background: {$NEUTRAL_BG}; border: 1px solid {$LINE}; border-radius: 8px; padding: 14px 12px; text-align: center; line-height: 1.45; }
  .share-after { font-size: 11pt; font-weight: 600; color: {$INK}; background: #fff; border: 1px solid #fde4dc; border-left: 3px solid {$BRAND}; border-radius: 8px; padding: 14px 12px; text-align: center; line-height: 1.45; }
  .share-arrow { font-size: 22pt; font-weight: 700; color: {$BRAND}; }

  .changes-list { margin: 8px 0 0 0; padding-left: 20px; font-size: 10.5pt; color: {$INK}; line-height: 1.75; }
  .changes-list li { margin-bottom: 6px; }
  .changes-list li::marker { color: {$BRAND}; }

  .rpt-header { padding-bottom: 14px; border-bottom: 1px solid {$LINE}; margin-bottom: 16px; }
  .rpt-eyebrow { font-size: 9pt; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: {$BRAND}; margin-bottom: 8px; }
  h1.rpt-name { font-size: 22pt; font-weight: 700; margin: 0 0 6px 0; color: {$INK}; }
  .rpt-meta { font-size: 9.5pt; color: {$MUTED}; margin-bottom: 12px; }
  .quest-label { font-size: 10pt; color: {$MUTED}; background: {$NEUTRAL_BG}; border: 1px solid {$LINE}; padding: 10px 12px; line-height: 1.5; }
  .quest-label b { color: {$INK}; }

  .lead-section { padding: 18px 0; border-bottom: 1px solid {$LINE}; margin-bottom: 16px; }
  .lead-kicker { font-size: 9pt; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: {$BRAND}; margin-bottom: 10px; border-left: 3px solid {$BRAND}; padding-left: 8px; }
  .lead-headline { font-size: 20pt; font-weight: 700; line-height: 1.25; margin: 0; color: {$INK}; }
  .lead-headline em { font-style: normal; color: {$BRAND}; }

  .notif {
    background: {$BRAND_BG};
    border: 1px solid #fde4dc;
    border-left: 3px solid {$BRAND};
    padding: 12px 14px;
    margin-top: 14px;
    font-size: 10pt;
    color: {$MUTED};
    line-height: 1.55;
  }
  .notif strong { color: {$INK}; }

  .section { padding: 16px 0; border-bottom: 1px solid {$LINE}; margin-bottom: 4px; }
  .section-eyebrow { font-size: 8.5pt; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: {$MUTED}; margin-bottom: 12px; }

  .transform-table { border-collapse: collapse; }
  .tcard { border-radius: 8px; padding: 12px; min-height: 120px; }
  .tcard.before { background: {$NEUTRAL_BG}; border: 1px solid #e2d6d0; border-left: 3px solid {$MUTED}; }
  .tcard.after { background: {$BRAND_BG}; border: 1px solid #fde4dc; border-left: 3px solid {$BRAND}; }
  .tc-time { font-size: 8pt; font-weight: 700; text-transform: uppercase; color: {$MUTED}; margin-bottom: 6px; }
  .after-time { color: {$BRAND}; }
  .tc-stance { display: inline-block; font-size: 8.5pt; font-weight: 700; padding: 2px 8px; border-radius: 20px; margin-bottom: 8px; }
  .before-stance { background: #f0ebe8; color: {$MUTED}; }
  .after-stance { background: #fde4dc; color: {$BRAND_DARK}; }
  .tc-text { font-size: 11pt; font-weight: 600; line-height: 1.5; color: {$INK}; margin-bottom: 6px; }
  .tc-sub { font-size: 8.5pt; color: {$MUTED}; }
  .arrow-icon { font-size: 18pt; color: {$BRAND}; line-height: 1; }
  .arrow-label { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; color: {$MUTED}; margin-top: 4px; }

  .narrative-p { font-size: 10.5pt; color: {$MUTED}; line-height: 1.7; margin: 0 0 10px 0; }
  .narrative-p strong { color: {$INK}; }

  .wblock { border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; }
  .wblock.w1 { background: {$NEUTRAL_BG}; border: 1px solid #e2d6d0; }
  .wblock.w2 { background: {$BRAND_BG}; border: 1px solid #fde4dc; border-left: 3px solid {$BRAND}; }
  .wlabel { font-size: 8pt; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; }
  .wblock.w1 .wlabel { color: {$MUTED}; }
  .wblock.w2 .wlabel { color: {$BRAND}; }
  .wblock ol { padding-left: 18px; font-size: 9.5pt; color: {$MUTED}; margin: 0; }
  .wblock ol li { margin-bottom: 3px; }
  .wdelta { font-size: 9pt; color: {$BRAND}; font-weight: 600; margin-top: 8px; padding-top: 6px; border-top: 1px dashed #fde4dc; }
  .muted-delta { color: {$MUTED}; border-color: #e2d6d0; }
  .wdelta-p { font-size: 9.5pt; color: {$INK}; margin: 0; }
  .note { font-size: 8.5pt; color: {$MUTED}; margin-bottom: 8px; }

  .tier-table { margin-top: 4px; }
  .tier-icon { width: 36px; height: 36px; border-radius: 8px; background: {$BRAND}; color: #fff; font-size: 14pt; font-weight: 700; text-align: center; line-height: 36px; }
  .tier-name { font-size: 11pt; font-weight: 700; color: {$BRAND}; }
  .tier-sub { font-size: 8.5pt; color: {$MUTED}; }
  .tier-bar-label { font-size: 8pt; color: {$MUTED}; font-weight: 600; margin-bottom: 4px; }
  .tier-bar-track { height: 7px; background: #ede9e1; border-radius: 10px; overflow: hidden; }
  .tier-bar-fill { height: 100%; background: {$BRAND}; border-radius: 10px; }
  .streak-tag { font-size: 9pt; font-weight: 700; color: {$BRAND}; background: {$BRAND_BG}; border: 1px solid #fde4dc; border-radius: 20px; padding: 4px 10px; white-space: nowrap; }

  .eval-body { font-size: 12pt; font-weight: 600; line-height: 1.65; color: {$INK}; border-left: 3px solid {$BRAND}; padding-left: 14px; margin-top: 8px; }
  .eval-kicker { font-size: 8.5pt; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: {$BRAND}; border-left: 3px solid {$BRAND}; padding-left: 8px; }

  table.data, table.data-mini, table.qual-table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 8px; }
  table.data th, table.data td, table.data-mini td, table.qual-table th, table.qual-table td {
    border: 1px solid {$LINE}; padding: 6px 8px; vertical-align: top;
  }
  table.data th, table.qual-table th { background: {$NEUTRAL_BG}; text-align: left; font-weight: 700; }
  .challenger-box { margin-top: 10px; font-size: 9pt; }

  .footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid {$LINE}; font-size: 8pt; color: {$MUTED}; }
  .footer em { color: {$BRAND}; font-style: normal; font-weight: 600; }

  .notif, .tcard, .wblock, .share-card, .share-before, .share-after, .tier-icon, .tier-bar-fill, .streak-tag {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
</style>
</head>
<body>

  <div class="brand-row">
    {$brandImg}
    <div class="wordmark"><em>the</em> gist · EDU</div>
  </div>

  <div class="rpt-header">
    <div class="rpt-eyebrow">{$periodLabel}</div>
    <h1 class="rpt-name">{$studentName} 학생</h1>
    <div class="rpt-meta">{$questCode} · {$period}</div>
    <div class="quest-label">
      이번 달 탐구 주제<br>
      <b>{$questTitle}</b>
    </div>
  </div>

  {$shareCard}

  <div class="lead-section">
    <div class="lead-kicker">{$leadKicker}</div>
    <p class="lead-headline">{$leadHeadline}<br><em>{$leadEm}</em></p>
    {$notif}
  </div>

  <div class="section">
    <div class="section-eyebrow">생각이 바뀐 순간</div>
    {$transform}
  </div>

  <div class="section">
    <div class="section-eyebrow">이달 성장 이야기</div>
    {$narrative}
  </div>

  <div class="section">
    <div class="section-eyebrow">직접 쓴 글 — 변화 전후</div>
    {$writing}
  </div>

  <div class="section">
    <div class="section-eyebrow">이번 달 처음 생긴 변화</div>
    {$firstChanges}
    <div class="section-eyebrow" style="margin-top:16px">월간 성장 관찰</div>
    {$qualitative}
  </div>

  <div class="section">
    <div class="section-eyebrow">티어 진행</div>
    {$tier}
  </div>

  <div class="section" style="border-bottom:none">
    <div class="eval-kicker">GIST EDU 이달 한 줄 평가</div>
    <div class="eval-body">{$monthlyEval}</div>
  </div>

  <div class="footer">
    <em>the</em> gist · EDU &nbsp;·&nbsp; 본 리포트는 Sprint 0 검증용 가상 데이터입니다
  </div>

</body>
</html>
HTML;
}

function createDompdf(string $fontDir): Dompdf
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

    $regular = $fontDir . '/NotoSansKR-Regular.otf';
    $bold = $fontDir . '/NotoSansKR-Bold.otf';

    if (is_file($regular)) {
        try {
            $fontMetrics->registerFont(
                ['family' => 'Noto Sans KR', 'style' => 'normal', 'weight' => 'normal'],
                $regular
            );
        } catch (Throwable $e) {
            fwrite(STDERR, 'Font register regular: ' . $e->getMessage() . "\n");
        }
    }
    if (is_file($bold)) {
        try {
            $fontMetrics->registerFont(
                ['family' => 'Noto Sans KR', 'style' => 'normal', 'weight' => 'bold'],
                $bold
            );
        } catch (Throwable $e) {
            fwrite(STDERR, 'Font register bold: ' . $e->getMessage() . "\n");
        }
    }

    return $dompdf;
}

$generated = [];

foreach ($samples as $sample) {
    $dompdf = createDompdf($fontDir);
    $html = renderSampleHtml($sample, $brandMarkDataUri);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    $filename = $sample['filename'];
    $path = $outDir . '/' . $filename;
    file_put_contents($path, $pdf);
    $generated[] = ['file' => $path, 'bytes' => strlen($pdf), 'student' => $sample['student_name']];
    unset($dompdf, $html, $pdf);
    gc_collect_cycles();
}

echo "Generated " . count($generated) . " PDFs in {$outDir}\n";
foreach ($generated as $g) {
    echo "  - {$g['file']} ({$g['bytes']} bytes) · {$g['student']}\n";
}
