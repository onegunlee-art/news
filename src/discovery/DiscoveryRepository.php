<?php
declare(strict_types=1);

namespace Discovery;

use PDO;

final class DiscoveryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findEditionByDate(string $date): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM discovery_editions WHERE edition_date = ? LIMIT 1');
        $stmt->execute([$date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findEditionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM discovery_editions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listEditions(int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM discovery_editions ORDER BY edition_date DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function createEdition(string $date, string $status = 'generating'): array
    {
        $existing = $this->findEditionByDate($date);
        if ($existing) {
            $this->pdo->prepare(
                'UPDATE discovery_editions SET status = ?, change_count = 0, warning_message = NULL, updated_at = NOW() WHERE id = ?'
            )->execute([$status, $existing['id']]);
            $this->clearEditionChildren((int) $existing['id']);
            return $this->findEditionById((int) $existing['id']) ?? $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO discovery_editions (edition_date, status, change_count) VALUES (?, ?, 0)'
        );
        $stmt->execute([$date, $status]);
        return $this->findEditionById((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function updateEditionStatus(int $editionId, string $status, ?string $warning = null): void
    {
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $this->pdo->prepare(
            'UPDATE discovery_editions SET status = ?, published_at = COALESCE(?, published_at), warning_message = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$status, $publishedAt, $warning, $editionId]);
    }

    public function setEditionChangeCount(int $editionId, int $count, ?string $warning = null): void
    {
        $this->pdo->prepare(
            'UPDATE discovery_editions SET change_count = ?, warning_message = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$count, $warning, $editionId]);
    }

    /** @param list<array<string, mixed>> $changes */
    public function saveChanges(int $editionId, array $changes): void
    {
        $this->clearEditionChildren($editionId);
        $rank = 1;
        foreach ($changes as $change) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO discovery_changes (edition_id, rank, category, title, summary, briefing_json, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $editionId,
                $rank,
                $change['category'],
                $change['title'],
                $change['summary'],
                json_encode($change['briefing'], JSON_UNESCAPED_UNICODE),
                'verified',
            ]);
            $changeId = (int) $this->pdo->lastInsertId();

            foreach ($change['sources'] as $source) {
                if (empty($source['verified'])) {
                    continue;
                }
                $this->pdo->prepare(
                    'INSERT INTO discovery_sources (change_id, name, url, article_title, verified, verified_at)
                     VALUES (?, ?, ?, ?, 1, NOW())'
                )->execute([
                    $changeId,
                    $source['name'],
                    $source['url'],
                    $source['article_title'] ?? null,
                ]);
            }

            $poll = $change['poll'];
            $this->pdo->prepare(
                'INSERT INTO discovery_polls (change_id, question, options_json) VALUES (?, ?, ?)'
            )->execute([
                $changeId,
                $poll['question'],
                json_encode($poll['options'], JSON_UNESCAPED_UNICODE),
            ]);

            $rank++;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getChangesForEdition(int $editionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM discovery_changes WHERE edition_id = ? AND status != ? ORDER BY rank ASC'
        );
        $stmt->execute([$editionId, 'discarded']);
        $changes = $stmt->fetchAll() ?: [];

        foreach ($changes as &$change) {
            $changeId = (int) $change['id'];
            $change['briefing'] = json_decode((string) ($change['briefing_json'] ?? '{}'), true) ?: [];
            unset($change['briefing_json']);

            $srcStmt = $this->pdo->prepare('SELECT * FROM discovery_sources WHERE change_id = ? ORDER BY id ASC');
            $srcStmt->execute([$changeId]);
            $change['sources'] = $srcStmt->fetchAll() ?: [];

            $pollStmt = $this->pdo->prepare('SELECT * FROM discovery_polls WHERE change_id = ? LIMIT 1');
            $pollStmt->execute([$changeId]);
            $poll = $pollStmt->fetch() ?: null;
            if ($poll) {
                $poll['options'] = json_decode((string) ($poll['options_json'] ?? '[]'), true) ?: [];
                unset($poll['options_json']);
                $poll['stats'] = $this->getPollStats((int) $poll['id']);
            }
            $change['poll'] = $poll;
        }
        unset($change);

        return $changes;
    }

    /** @return array<string, mixed> */
    public function getPollStats(int $pollId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT option_idx, COUNT(*) AS cnt FROM discovery_votes WHERE poll_id = ? GROUP BY option_idx'
        );
        $stmt->execute([$pollId]);
        $rows = $stmt->fetchAll() ?: [];
        $total = array_sum(array_map(static fn($r) => (int) $r['cnt'], $rows));

        if ($total === 0) {
            return $this->generateDummyStats();
        }

        $counts = [0, 0, 0, 0];
        foreach ($rows as $row) {
            $idx = (int) $row['option_idx'];
            if ($idx >= 0 && $idx <= 3) {
                $counts[$idx] = (int) $row['cnt'];
            }
        }
        $percents = array_map(static fn($c) => $total > 0 ? round($c * 100 / $total) : 0, $counts);

        return [
            'total' => $total,
            'counts' => $counts,
            'percents' => $percents,
            'is_dummy' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function generateDummyStats(): array
    {
        return [
            'total' => 127,
            'counts' => [44, 36, 28, 19],
            'percents' => [35, 28, 22, 15],
            'is_dummy' => true,
        ];
    }

    public function recordVote(int $pollId, string $userKey, int $optionIdx): void
    {
        $this->pdo->prepare(
            'INSERT INTO discovery_votes (poll_id, user_key, option_idx) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE option_idx = VALUES(option_idx), created_at = NOW()'
        )->execute([$pollId, $userKey, $optionIdx]);
    }

    /** @param list<array<string, mixed>> $discarded */
    public function logRun(string $date, int $generated, int $discarded, array $discardedItems, ?float $costUsd, int $durationSec): void
    {
        $this->pdo->prepare(
            'INSERT INTO discovery_runs (edition_date, generated_count, discarded_count, reasons_json, cost_usd, duration_sec)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $date,
            $generated,
            $discarded,
            json_encode($discardedItems, JSON_UNESCAPED_UNICODE),
            $costUsd,
            $durationSec,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listRuns(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM discovery_runs ORDER BY run_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function publishEdition(int $editionId): void
    {
        $this->updateEditionStatus($editionId, 'published');
    }

    public function publishEditionByDate(string $date): ?array
    {
        $edition = $this->findEditionByDate($date);
        if (!$edition) {
            return null;
        }
        $this->publishEdition((int) $edition['id']);
        return $this->findEditionById((int) $edition['id']);
    }

    /** @param array<string, mixed> $fields */
    public function updateChange(int $changeId, array $fields): void
    {
        $sets = [];
        $params = [];
        foreach (['title', 'summary', 'category', 'rank', 'status'] as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = "$key = ?";
                $params[] = $fields[$key];
            }
        }
        if (array_key_exists('briefing', $fields)) {
            $sets[] = 'briefing_json = ?';
            $params[] = json_encode($fields['briefing'], JSON_UNESCAPED_UNICODE);
        }
        if ($sets === []) {
            return;
        }
        $params[] = $changeId;
        $this->pdo->prepare('UPDATE discovery_changes SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?')
            ->execute($params);
    }

    /** @param list<string> $options */
    public function updatePoll(int $changeId, string $question, array $options): void
    {
        $this->pdo->prepare(
            'UPDATE discovery_polls SET question = ?, options_json = ? WHERE change_id = ?'
        )->execute([$question, json_encode($options, JSON_UNESCAPED_UNICODE), $changeId]);
    }

    public function deleteChange(int $changeId): void
    {
        $pollStmt = $this->pdo->prepare('SELECT id FROM discovery_polls WHERE change_id = ?');
        $pollStmt->execute([$changeId]);
        $pollIds = array_map(static fn($r) => (int) $r['id'], $pollStmt->fetchAll() ?: []);
        foreach ($pollIds as $pollId) {
            $this->pdo->prepare('DELETE FROM discovery_votes WHERE poll_id = ?')->execute([$pollId]);
        }
        $this->pdo->prepare('DELETE FROM discovery_polls WHERE change_id = ?')->execute([$changeId]);
        $this->pdo->prepare('DELETE FROM discovery_sources WHERE change_id = ?')->execute([$changeId]);
        $this->pdo->prepare('DELETE FROM discovery_changes WHERE id = ?')->execute([$changeId]);
    }

    /** @return list<array<string, mixed>> */
    public function searchChanges(string $query, int $days = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, e.edition_date, e.status AS edition_status
             FROM discovery_changes c
             JOIN discovery_editions e ON e.id = c.edition_id
             WHERE e.edition_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND c.status != ?
               AND (c.title LIKE ? OR c.summary LIKE ?)
             ORDER BY e.edition_date DESC, c.rank ASC
             LIMIT 50'
        );
        $like = '%' . $query . '%';
        $stmt->execute([$days, 'discarded', $like, $like]);
        return $stmt->fetchAll() ?: [];
    }

    private function clearEditionChildren(int $editionId): void
    {
        $changeIds = $this->pdo->prepare('SELECT id FROM discovery_changes WHERE edition_id = ?');
        $changeIds->execute([$editionId]);
        foreach ($changeIds->fetchAll() ?: [] as $row) {
            $this->deleteChange((int) $row['id']);
        }
    }
}
