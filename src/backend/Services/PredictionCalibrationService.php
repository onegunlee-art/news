<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/** Admin-only: track SCQA scenario predictions vs outcomes. */
class PredictionCalibrationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ensureTable(): void
    {
        $sqlFile = dirname(__DIR__, 3) . '/database/migrations/add_judgment_moat.sql';
        if (!is_file($sqlFile)) {
            return;
        }
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            return;
        }
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
            if ($statement !== '' && stripos($statement, 'CREATE TABLE') !== false
                && stripos($statement, 'prediction_outcomes') !== false) {
                try {
                    $this->pdo->exec($statement);
                } catch (\Throwable $e) {
                    error_log('PredictionCalibrationService ensureTable: ' . $e->getMessage());
                }
            }
        }
    }

    public function syncFromReport(int $reportId, string $reportWeek, array $scqa): int
    {
        $this->ensureTable();
        $scenarios = $scqa['answer']['scenarios'] ?? [];
        if (!is_array($scenarios) || $scenarios === []) {
            return 0;
        }

        $this->pdo->prepare('DELETE FROM prediction_outcomes WHERE report_id = :id')->execute(['id' => $reportId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO prediction_outcomes
             (report_id, report_week, scenario_type, scenario_index, prediction_signal, probability, outcome_status)
             VALUES (:rid, :week, :type, :idx, :signal, :prob, :status)'
        );

        $count = 0;
        foreach ($scenarios as $idx => $scenario) {
            if (!is_array($scenario)) {
                continue;
            }
            $type = (string) ($scenario['type'] ?? 'base');
            if (!in_array($type, ['base', 'upside', 'downside'], true)) {
                $type = 'base';
            }
            $ins->execute([
                'rid' => $reportId,
                'week' => $reportWeek,
                'type' => $type,
                'idx' => (int) $idx,
                'signal' => (string) ($scenario['prediction_signal'] ?? ''),
                'prob' => isset($scenario['probability']) ? (int) $scenario['probability'] : null,
                'status' => 'pending',
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * @param list<array{id: int, outcome_status: string, outcome_notes?: string}> $scores
     */
    public function scoreOutcomes(array $scores, string $scoredBy = 'admin'): int
    {
        $this->ensureTable();
        $upd = $this->pdo->prepare(
            'UPDATE prediction_outcomes
             SET outcome_status = :status, outcome_notes = :notes, scored_at = NOW(), scored_by = :by
             WHERE id = :id'
        );
        $updated = 0;
        foreach ($scores as $row) {
            $id = (int) ($row['id'] ?? 0);
            $status = (string) ($row['outcome_status'] ?? '');
            if ($id <= 0 || !in_array($status, ['pending', 'hit', 'miss', 'partial'], true)) {
                continue;
            }
            $upd->execute([
                'status' => $status,
                'notes' => (string) ($row['outcome_notes'] ?? ''),
                'by' => $scoredBy,
                'id' => $id,
            ]);
            $updated++;
        }
        return $updated;
    }

    /** @return array{total: int, hit: int, miss: int, partial: int, pending: int, hit_rate: float|null} */
    public function getCalibrationSummary(): array
    {
        $this->ensureTable();
        try {
            $rows = $this->pdo->query(
                "SELECT outcome_status, COUNT(*) AS cnt FROM prediction_outcomes GROUP BY outcome_status"
            )->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return ['total' => 0, 'hit' => 0, 'miss' => 0, 'partial' => 0, 'pending' => 0, 'hit_rate' => null];
        }

        $counts = ['hit' => 0, 'miss' => 0, 'partial' => 0, 'pending' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['outcome_status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row['cnt'];
            }
        }
        $scored = $counts['hit'] + $counts['miss'] + $counts['partial'];
        $total = $scored + $counts['pending'];
        $hitRate = $scored > 0 ? round($counts['hit'] / $scored, 3) : null;

        return [
            'total' => $total,
            'hit' => $counts['hit'],
            'miss' => $counts['miss'],
            'partial' => $counts['partial'],
            'pending' => $counts['pending'],
            'hit_rate' => $hitRate,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listForReport(int $reportId): array
    {
        $this->ensureTable();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM prediction_outcomes WHERE report_id = :id ORDER BY scenario_index ASC'
            );
            $stmt->execute(['id' => $reportId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
