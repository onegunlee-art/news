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
                'INSERT INTO discovery_changes (edition_id, `rank`, category, title, summary, briefing_json, status)
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
            'SELECT * FROM discovery_changes WHERE edition_id = ? AND status != ? ORDER BY `rank` ASC'
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
    public function getPollStats(int $pollId, bool $allowDummy = true): array
    {
        $stats = $this->getPollStatsReal($pollId);
        if ($allowDummy && $stats['total'] === 0) {
            return $this->generateDummyStats();
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    public function getPollStatsReal(int $pollId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT option_idx, COUNT(*) AS cnt FROM discovery_votes WHERE poll_id = ? GROUP BY option_idx'
        );
        $stmt->execute([$pollId]);
        $rows = $stmt->fetchAll() ?: [];
        $total = array_sum(array_map(static fn($r) => (int) $r['cnt'], $rows));

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

    public function recordVote(int $pollId, string $deviceKey, int $optionIdx): void
    {
        $this->pdo->prepare(
            'INSERT INTO discovery_votes (poll_id, device_key, option_idx) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE option_idx = VALUES(option_idx), created_at = NOW()'
        )->execute([$pollId, $deviceKey, $optionIdx]);
    }

    public function castVoteOnce(int $pollId, string $deviceKey, int $optionIdx): void
    {
        if ($optionIdx < 0 || $optionIdx > 3) {
            throw new \InvalidArgumentException('option_idx must be 0-3');
        }

        if ($this->findVoteByDevice($pollId, $deviceKey)) {
            throw new \RuntimeException('이미 투표했습니다. 변경할 수 없습니다.', 409);
        }

        try {
            $this->pdo->prepare(
                'INSERT INTO discovery_votes (poll_id, device_key, option_idx) VALUES (?, ?, ?)'
            )->execute([$pollId, $deviceKey, $optionIdx]);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new \RuntimeException('이미 투표했습니다. 변경할 수 없습니다.', 409);
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public function findVoteByDevice(int $pollId, string $deviceKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM discovery_votes WHERE poll_id = ? AND device_key = ? LIMIT 1'
        );
        $stmt->execute([$pollId, $deviceKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedEditionByDate(string $date, bool $includeSeed = false): ?array
    {
        $seedClause = $includeSeed ? '' : ' AND is_seed = 0';
        $stmt = $this->pdo->prepare(
            'SELECT * FROM discovery_editions WHERE edition_date = ? AND status = ?' . $seedClause . ' LIMIT 1'
        );
        $stmt->execute([$date, 'published']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findLatestPublishedEdition(bool $includeSeed = false): ?array
    {
        $seedClause = $includeSeed ? '' : ' AND is_seed = 0';
        $stmt = $this->pdo->prepare(
            'SELECT * FROM discovery_editions WHERE status = ?' . $seedClause . ' ORDER BY edition_date DESC LIMIT 1'
        );
        $stmt->execute(['published']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function getPublicChangesForEdition(int $editionId, ?string $deviceKey): array
    {
        $changes = $this->getVerifiedChangesForEdition($editionId);
        $out = [];
        foreach ($changes as $change) {
            $out[] = $this->hydratePublicChange($change, $deviceKey);
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function getVerifiedChangesForEdition(int $editionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM discovery_changes WHERE edition_id = ? AND status = ? ORDER BY `rank` ASC'
        );
        $stmt->execute([$editionId, 'verified']);
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
            }
            $change['poll'] = $poll;
        }
        unset($change);

        return $changes;
    }

    /** @param array<string, mixed> $change */
    public function hydratePublicChange(array $change, ?string $deviceKey): array
    {
        if (!empty($change['poll']['id'])) {
            $pollId = (int) $change['poll']['id'];
            $change['poll']['stats'] = $this->getPollStatsReal($pollId);
            if ($deviceKey) {
                $vote = $this->findVoteByDevice($pollId, $deviceKey);
                $change['poll']['viewer'] = [
                    'has_voted' => $vote !== null,
                    'option_idx' => $vote ? (int) $vote['option_idx'] : null,
                ];
            }
        }
        return $change;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedChangeById(int $changeId, bool $includeSeed = false): ?array
    {
        $seedClause = $includeSeed ? '' : ' AND e.is_seed = 0';
        $stmt = $this->pdo->prepare(
            'SELECT c.*, e.edition_date, e.status AS edition_status, e.is_seed
             FROM discovery_changes c
             JOIN discovery_editions e ON e.id = c.edition_id
             WHERE c.id = ? AND c.status = ? AND e.status = ?' . $seedClause . '
             LIMIT 1'
        );
        $stmt->execute([$changeId, 'verified', 'published']);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $change = $row;
        $changeId = (int) $change['id'];
        $change['briefing'] = json_decode((string) ($change['briefing_json'] ?? '{}'), true) ?: [];
        unset($change['briefing_json']);

        $srcStmt = $this->pdo->prepare('SELECT id, name, url, article_title, verified FROM discovery_sources WHERE change_id = ? ORDER BY id ASC');
        $srcStmt->execute([$changeId]);
        $change['sources'] = $srcStmt->fetchAll() ?: [];

        $pollStmt = $this->pdo->prepare('SELECT * FROM discovery_polls WHERE change_id = ? LIMIT 1');
        $pollStmt->execute([$changeId]);
        $poll = $pollStmt->fetch() ?: null;
        if ($poll) {
            $poll['options'] = json_decode((string) ($poll['options_json'] ?? '[]'), true) ?: [];
            unset($poll['options_json']);
        }
        $change['poll'] = $poll;

        return $change;
    }

    /** @return array<string, mixed>|null */
    public function findPublishedPollBundle(int $pollId, bool $includeSeed = false): ?array
    {
        $seedClause = $includeSeed ? '' : ' AND e.is_seed = 0';
        $stmt = $this->pdo->prepare(
            'SELECT p.*, c.id AS change_row_id, c.edition_id, c.`rank`, c.category, c.title, c.summary,
                    c.briefing_json, c.status AS change_status, e.edition_date, e.status AS edition_status, e.is_seed
             FROM discovery_polls p
             JOIN discovery_changes c ON c.id = p.change_id
             JOIN discovery_editions e ON e.id = c.edition_id
             WHERE p.id = ? AND c.status = ? AND e.status = ?' . $seedClause . '
             LIMIT 1'
        );
        $stmt->execute([$pollId, 'verified', 'published']);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $changeId = (int) $row['change_row_id'];
        $change = [
            'id' => $changeId,
            'edition_id' => (int) $row['edition_id'],
            'rank' => (int) $row['rank'],
            'category' => $row['category'],
            'title' => $row['title'],
            'summary' => $row['summary'],
            'briefing' => json_decode((string) ($row['briefing_json'] ?? '{}'), true) ?: [],
            'status' => $row['change_status'],
            'edition_date' => $row['edition_date'],
        ];

        $srcStmt = $this->pdo->prepare('SELECT id, name, url, article_title, verified FROM discovery_sources WHERE change_id = ? ORDER BY id ASC');
        $srcStmt->execute([$changeId]);
        $change['sources'] = $srcStmt->fetchAll() ?: [];

        $poll = [
            'id' => (int) $row['id'],
            'change_id' => $changeId,
            'question' => $row['question'],
            'options' => json_decode((string) ($row['options_json'] ?? '[]'), true) ?: [],
            'stats' => $this->getPollStatsReal($pollId),
        ];
        $change['poll'] = $poll;

        return [
            'poll' => $poll,
            'change' => $change,
            'edition' => [
                'edition_date' => $row['edition_date'],
                'status' => $row['edition_status'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function listPublishedEditionsCursor(?string $cursor, int $limit, bool $includeSeed = false): array
    {
        $params = ['published'];
        $cursorClause = '';
        if ($cursor) {
            $cursorClause = ' AND edition_date < ?';
            $params[] = $cursor;
        }
        $seedClause = $includeSeed ? '' : ' AND is_seed = 0';

        $stmt = $this->pdo->prepare(
            'SELECT id, edition_date, status, published_at, change_count, is_seed
             FROM discovery_editions
             WHERE status = ?' . $seedClause . $cursorClause . '
             ORDER BY edition_date DESC
             LIMIT ' . ($limit + 1)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $nextCursor = $hasMore && $rows !== [] ? (string) end($rows)['edition_date'] : null;

        return [
            'items' => $rows,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /** @return array<string, mixed> */
    public function addComment(int $pollId, string $deviceKey, string $body, ?string $ipHash = null): array
    {
        $body = trim(strip_tags($body));
        if ($body === '') {
            throw new \InvalidArgumentException('댓글 내용을 입력해 주세요.');
        }
        if (mb_strlen($body) > 500) {
            throw new \InvalidArgumentException('댓글은 500자 이하로 작성해 주세요.');
        }

        $recent = $this->pdo->prepare(
            'SELECT COUNT(*) FROM discovery_comments
             WHERE device_key = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $recent->execute([$deviceKey]);
        if ((int) $recent->fetchColumn() >= 10) {
            throw new \RuntimeException('댓글 작성 한도를 초과했습니다. 잠시 후 다시 시도해 주세요.', 429);
        }

        $this->pdo->prepare(
            'INSERT INTO discovery_comments (poll_id, device_key, body, ip_hash) VALUES (?, ?, ?, ?)'
        )->execute([$pollId, $deviceKey, $body, $ipHash]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findCommentById($id) ?? [
            'id' => $id,
            'poll_id' => $pollId,
            'device_key' => $deviceKey,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, mixed>|null */
    public function findCommentById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM discovery_comments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    public function listComments(int $pollId, ?string $cursor, int $limit): array
    {
        $params = [$pollId];
        $cursorClause = '';
        if ($cursor) {
            $cursorClause = ' AND id < ?';
            $params[] = (int) $cursor;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, poll_id, body, created_at
             FROM discovery_comments
             WHERE poll_id = ? AND deleted = 0' . $cursorClause . '
             ORDER BY id DESC
             LIMIT ' . ($limit + 1)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $nextCursor = $hasMore && $rows !== [] ? (string) end($rows)['id'] : null;

        return [
            'items' => $rows,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    public function softDeleteComment(int $commentId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE discovery_comments SET deleted = 1, deleted_at = NOW() WHERE id = ? AND deleted = 0'
        );
        $stmt->execute([$commentId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<string, int> */
    public function getDeviceParticipationStats(string $deviceKey): array
    {
        $votes = $this->pdo->prepare('SELECT COUNT(*) FROM discovery_votes WHERE device_key = ?');
        $votes->execute([$deviceKey]);
        $comments = $this->pdo->prepare(
            'SELECT COUNT(*) FROM discovery_comments WHERE device_key = ? AND deleted = 0'
        );
        $comments->execute([$deviceKey]);

        return [
            'votes_count' => (int) $votes->fetchColumn(),
            'comments_count' => (int) $comments->fetchColumn(),
        ];
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
                $column = $key === 'rank' ? '`rank`' : $key;
                $sets[] = "$column = ?";
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

    /** @return list<array<string, mixed>> */
    public function searchPublishedChanges(string $query, int $limit = 50, bool $includeSeed = false): array
    {
        $seedClause = $includeSeed ? '' : ' AND e.is_seed = 0';
        $stmt = $this->pdo->prepare(
            'SELECT c.*, e.edition_date, e.status AS edition_status, e.is_seed
             FROM discovery_changes c
             JOIN discovery_editions e ON e.id = c.edition_id
             WHERE e.status = ?
               AND c.status = ?
               AND (c.title LIKE ? OR c.summary LIKE ?)' . $seedClause . '
             ORDER BY e.edition_date DESC, c.`rank` ASC
             LIMIT ?'
        );
        $like = '%' . $query . '%';
        $stmt->bindValue(1, 'published');
        $stmt->bindValue(2, 'verified');
        $stmt->bindValue(3, $like);
        $stmt->bindValue(4, $like);
        $stmt->bindValue(5, max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
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
             ORDER BY e.edition_date DESC, c.`rank` ASC
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

    public function hasRealEditionForDate(string $date): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM discovery_editions WHERE edition_date = ? AND is_seed = 0 LIMIT 1'
        );
        $stmt->execute([$date]);
        return (bool) $stmt->fetch();
    }

    public function deleteAllSeedEditions(): int
    {
        $stmt = $this->pdo->query('SELECT id FROM discovery_editions WHERE is_seed = 1');
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as $row) {
            $this->deleteSeedEditionById((int) $row['id']);
        }

        return count($rows);
    }

    public function deleteSeedEditionByDate(string $date): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM discovery_editions WHERE edition_date = ? AND is_seed = 1 LIMIT 1');
        $stmt->execute([$date]);
        $row = $stmt->fetch();
        if ($row) {
            $this->deleteSeedEditionById((int) $row['id']);
        }
    }

    private function deleteSeedEditionById(int $editionId): void
    {
        $this->clearEditionChildren($editionId);
        $this->pdo->prepare('DELETE FROM discovery_editions WHERE id = ? AND is_seed = 1')->execute([$editionId]);
    }

    /**
     * @param list<array<string, mixed>> $changes
     * @return array<string, mixed>|null null if real edition exists for date
     */
    public function insertSeedEdition(string $date, array $changes, int $dayIndex): ?array
    {
        if ($this->hasRealEditionForDate($date)) {
            return null;
        }

        $this->deleteSeedEditionByDate($date);

        $count = count($changes);
        $this->pdo->prepare(
            'INSERT INTO discovery_editions (edition_date, status, change_count, is_seed, published_at)
             VALUES (?, ?, ?, 1, NOW())'
        )->execute([$date, 'published', $count]);

        $editionId = (int) $this->pdo->lastInsertId();
        $this->saveSeedChanges($editionId, $changes, $dayIndex);

        return $this->findEditionById($editionId);
    }

    /** @param list<array<string, mixed>> $changes */
    private function saveSeedChanges(int $editionId, array $changes, int $dayIndex): void
    {
        $rank = 1;
        foreach ($changes as $change) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO discovery_changes (edition_id, `rank`, category, title, summary, briefing_json, status)
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
            $pollId = (int) $this->pdo->lastInsertId();

            $this->insertSeedDummyVotes(
                $pollId,
                (int) ($change['_seed_vote_total'] ?? 100),
                (array) ($change['_seed_vote_percents'] ?? [45, 25, 20, 10])
            );

            $rank++;
        }
    }

    /** @param list<int> $percents */
    public function insertSeedDummyVotes(int $pollId, int $total, array $percents): void
    {
        $counts = [0, 0, 0, 0];
        $assigned = 0;
        foreach ($percents as $i => $pct) {
            if ($i > 3) {
                break;
            }
            $cnt = (int) floor($total * $pct / 100);
            $counts[$i] = $cnt;
            $assigned += $cnt;
        }
        $counts[0] += max(0, $total - $assigned);

        $seq = 0;
        foreach ($counts as $optionIdx => $cnt) {
            for ($n = 0; $n < $cnt; $n++) {
                $deviceKey = sprintf('seed-dummy-%d-%d-%04d', $pollId, $optionIdx, $seq++);
                try {
                    $this->pdo->prepare(
                        'INSERT INTO discovery_votes (poll_id, device_key, option_idx) VALUES (?, ?, ?)'
                    )->execute([$pollId, $deviceKey, $optionIdx]);
                } catch (\PDOException) {
                    // ignore duplicate on re-run fragments
                }
            }
        }
    }

    public function countSeedEditions(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM discovery_editions WHERE is_seed = 1')->fetchColumn();
    }

    public function countSeedChanges(): int
    {
        return (int) $this->pdo->query(
            'SELECT COUNT(*) FROM discovery_changes c
             JOIN discovery_editions e ON e.id = c.edition_id
             WHERE e.is_seed = 1'
        )->fetchColumn();
    }
}
