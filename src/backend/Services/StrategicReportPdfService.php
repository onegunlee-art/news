<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * 전략 레포트 PDF 생성 서비스
 * dompdf를 사용하여 HTML → PDF 변환
 */
class StrategicReportPdfService
{
    private StrategicReportDocumentService $documentService;
    private array $config;
    private ?Dompdf $dompdf = null;

    public function __construct(?StrategicReportDocumentService $documentService = null)
    {
        $this->documentService = $documentService ?? new StrategicReportDocumentService();
        
        $configPath = dirname(__DIR__, 3) . '/config/strategic_report_document.php';
        $this->config = is_file($configPath) ? require $configPath : [];
    }

    /**
     * 레포트 데이터로 PDF 바이너리 생성
     * 
     * @param array $report weekly_strategic_reports 테이블 row
     * @return string PDF 바이너리
     */
    public function generateFromReport(array $report): string
    {
        $html = $this->documentService->renderHtml($report);
        return $this->htmlToPdf($html);
    }

    /**
     * HTML 문자열을 PDF 바이너리로 변환
     * 
     * @param string $html HTML 문자열
     * @return string PDF 바이너리
     */
    public function htmlToPdf(string $html): string
    {
        $dompdf = $this->createDompdf();
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper(
            $this->config['pdf']['paper_size'] ?? 'A4',
            $this->config['pdf']['orientation'] ?? 'portrait'
        );
        
        $dompdf->render();
        
        return $dompdf->output();
    }

    /**
     * PDF를 파일로 저장
     * 
     * @param array $report weekly_strategic_reports 테이블 row
     * @param string $outputPath 저장 경로
     * @return bool
     */
    public function saveToFile(array $report, string $outputPath): bool
    {
        $pdfContent = $this->generateFromReport($report);
        $result = file_put_contents($outputPath, $pdfContent);
        return $result !== false;
    }

    /**
     * 레포트용 파일명 생성
     * 
     * @param array $report weekly_strategic_reports 테이블 row
     * @return string 파일명 (확장자 포함)
     */
    public function generateFilename(array $report): string
    {
        $week = (string) ($report['report_week'] ?? date('Y-\WW'));
        $prefix = $this->config['document_prefix'] ?? 'TG-SR';
        $prefixClean = str_replace('/', '-', $prefix);
        
        return "strategic-report-{$prefixClean}-{$week}.pdf";
    }

    /**
     * dompdf 인스턴스 생성 (설정 적용)
     */
    private function createDompdf(): Dompdf
    {
        if ($this->dompdf !== null) {
            return $this->dompdf;
        }

        $options = new Options();
        
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', $this->config['pdf']['enable_remote'] ?? false);
        $options->set('defaultFont', 'Noto Sans KR');
        $options->set('dpi', $this->config['pdf']['dpi'] ?? 150);
        $options->set('isFontSubsettingEnabled', true);
        
        $chroot = dirname(__DIR__, 3) . '/public';
        $options->set('chroot', $chroot);
        
        $fontDir = $this->config['fonts']['noto_path'] ?? ($chroot . '/fonts/noto');
        if (is_dir($fontDir)) {
            $options->set('fontDir', $fontDir);
        }

        $this->dompdf = new Dompdf($options);
        
        $this->registerKoreanFonts($this->dompdf, $fontDir);
        
        return $this->dompdf;
    }

    /**
     * 한국어 폰트 등록
     */
    private function registerKoreanFonts(Dompdf $dompdf, string $fontDir): void
    {
        if (!is_dir($fontDir)) {
            return;
        }

        $fontMetrics = $dompdf->getFontMetrics();
        
        $regularFont = $fontDir . '/NotoSansKR-Regular.otf';
        $boldFont = $fontDir . '/NotoSansKR-Bold.otf';
        
        if (is_file($regularFont)) {
            try {
                $fontMetrics->registerFont(
                    ['family' => 'Noto Sans KR', 'style' => 'normal', 'weight' => 'normal'],
                    $regularFont
                );
            } catch (\Throwable $e) {
                error_log('StrategicReportPdfService: Failed to register regular font - ' . $e->getMessage());
            }
        }
        
        if (is_file($boldFont)) {
            try {
                $fontMetrics->registerFont(
                    ['family' => 'Noto Sans KR', 'style' => 'normal', 'weight' => 'bold'],
                    $boldFont
                );
            } catch (\Throwable $e) {
                error_log('StrategicReportPdfService: Failed to register bold font - ' . $e->getMessage());
            }
        }
    }

    /**
     * PDF 미리보기용 Content-Type 헤더와 함께 출력
     * 
     * @param array $report weekly_strategic_reports 테이블 row
     */
    public function outputInline(array $report): void
    {
        $pdfContent = $this->generateFromReport($report);
        $filename = $this->generateFilename($report);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        
        echo $pdfContent;
    }

    /**
     * PDF 다운로드용 Content-Type 헤더와 함께 출력
     * 
     * @param array $report weekly_strategic_reports 테이블 row
     */
    public function outputAttachment(array $report): void
    {
        $pdfContent = $this->generateFromReport($report);
        $filename = $this->generateFilename($report);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        
        echo $pdfContent;
    }

    /**
     * PDF 바이너리를 Base64로 인코딩 (이메일 첨부용)
     * 
     * @param array $report weekly_strategic_reports 테이블 row
     * @return string Base64 인코딩된 PDF
     */
    public function generateBase64(array $report): string
    {
        $pdfContent = $this->generateFromReport($report);
        return base64_encode($pdfContent);
    }
}
