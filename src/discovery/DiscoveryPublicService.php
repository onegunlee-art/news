<?php
declare(strict_types=1);

namespace Discovery;

final class DiscoveryPublicService
{
    public function __construct(
        private readonly DiscoveryRepository $repo,
        private readonly DiscoveryRateLimiter $rateLimiter,
        private readonly array $config = [],
    ) {
    }

    private function includeSeed(): bool
    {
        return !empty($this->config['show_seed']);
    }

    /** @return array<string, mixed> */
    public function getToday(?string $deviceKey): array
    {
        $today = discoveryTodayKst();
        $includeSeed = $this->includeSeed();
        $todayEdition = $this->repo->findPublishedEditionByDate($today, $includeSeed);
        $latestPublished = $this->repo->findLatestPublishedEdition($includeSeed);

        $displayEdition = $todayEdition ?? $latestPublished;
        $preparingToday = $todayEdition === null && $latestPublished !== null;

        if ($displayEdition === null) {
            return [
                'edition' => null,
                'changes' => [],
                'viewer' => $this->viewerBlock($deviceKey, null),
                'meta' => [
                    'today_date' => $today,
                    'today_status' => 'empty',
                    'display_mode' => 'empty',
                ],
            ];
        }

        $changes = $this->repo->getPublicChangesForEdition((int) $displayEdition['id'], $deviceKey);

        return [
            'edition' => $displayEdition,
            'changes' => $changes,
            'viewer' => $this->viewerBlock($deviceKey, $changes),
            'meta' => [
                'today_date' => $today,
                'today_status' => $todayEdition ? 'published' : ($preparingToday ? 'preparing' : 'empty'),
                'display_mode' => $todayEdition ? 'today' : ($preparingToday ? 'latest_while_preparing' : 'latest'),
                'message' => $preparingToday ? '오늘의 변화 준비 중' : null,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function getEditionByDate(string $date, ?string $deviceKey): ?array
    {
        $edition = $this->repo->findPublishedEditionByDate($date, $this->includeSeed());
        if (!$edition) {
            return null;
        }
        $changes = $this->repo->getPublicChangesForEdition((int) $edition['id'], $deviceKey);

        return [
            'edition' => $edition,
            'changes' => $changes,
            'viewer' => $this->viewerBlock($deviceKey, $changes),
        ];
    }

    /** @return array<string, mixed> */
    public function listEditions(?string $cursor, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        return $this->repo->listPublishedEditionsCursor($cursor, $limit, $this->includeSeed());
    }

    /** @return array<string, mixed>|null */
    public function getChange(int $changeId, ?string $deviceKey): ?array
    {
        $change = $this->repo->findPublishedChangeById($changeId, $this->includeSeed());
        if (!$change) {
            return null;
        }
        $change = $this->repo->hydratePublicChange($change, $deviceKey);

        return [
            'change' => $change,
            'viewer' => $this->viewerForPoll($deviceKey, $change['poll']['id'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    public function getPoll(int $pollId, ?string $deviceKey): ?array
    {
        $bundle = $this->repo->findPublishedPollBundle($pollId, $this->includeSeed());
        if (!$bundle) {
            return null;
        }

        return [
            'poll' => $bundle['poll'],
            'change' => $bundle['change'],
            'edition' => $bundle['edition'],
            'viewer' => $this->viewerForPoll($deviceKey, $pollId),
        ];
    }

    /** @return array<string, mixed> */
    public function listComments(int $pollId, ?string $cursor, int $limit): array
    {
        if (!$this->repo->findPublishedPollBundle($pollId, $this->includeSeed())) {
            throw new \RuntimeException('Poll not found', 404);
        }

        $limit = max(1, min(50, $limit));
        return $this->repo->listComments($pollId, $cursor, $limit);
    }

    /** @return array<string, mixed> */
    public function castVote(string $deviceKey, int $pollId, int $optionIdx): array
    {
        $this->rateLimiter->hit('vote:ip:' . DiscoveryRateLimiter::ipHash(), 30, 60);
        $this->rateLimiter->hit('vote:device:' . $deviceKey, 20, 3600);

        if (!$this->repo->findPublishedPollBundle($pollId, $this->includeSeed())) {
            throw new \RuntimeException('Poll not found', 404);
        }

        $this->repo->castVoteOnce($pollId, $deviceKey, $optionIdx);

        return [
            'stats' => $this->repo->getPollStatsReal($pollId),
            'viewer' => ['has_voted' => true, 'option_idx' => $optionIdx],
        ];
    }

    /** @return array<string, mixed> */
    public function addComment(string $deviceKey, int $pollId, string $body): array
    {
        $this->rateLimiter->hit('comment:ip:' . DiscoveryRateLimiter::ipHash(), 20, 60);
        $this->rateLimiter->hit('comment:device:' . $deviceKey, 5, 3600);

        if (!$this->repo->findPublishedPollBundle($pollId, $this->includeSeed())) {
            throw new \RuntimeException('Poll not found', 404);
        }

        $comment = $this->repo->addComment($pollId, $deviceKey, $body, DiscoveryRateLimiter::ipHash());

        return ['comment' => $comment];
    }

    /** @return array<string, mixed> */
    public function getMyStats(string $deviceKey): array
    {
        return $this->repo->getDeviceParticipationStats($deviceKey);
    }

    /** @param list<array<string, mixed>>|null $changes */
    private function viewerBlock(?string $deviceKey, ?array $changes): array
    {
        if (!$deviceKey || !$changes) {
            return ['has_voted' => false, 'option_idx' => null, 'poll_id' => null];
        }

        foreach ($changes as $change) {
            $pollId = isset($change['poll']['id']) ? (int) $change['poll']['id'] : 0;
            if ($pollId < 1) {
                continue;
            }
            $viewer = $this->viewerForPoll($deviceKey, $pollId);
            if ($viewer['has_voted']) {
                return $viewer;
            }
        }

        $firstPollId = null;
        foreach ($changes as $change) {
            if (!empty($change['poll']['id'])) {
                $firstPollId = (int) $change['poll']['id'];
                break;
            }
        }

        return $this->viewerForPoll($deviceKey, $firstPollId);
    }

    /** @return array{has_voted: bool, option_idx: int|null, poll_id: int|null} */
    private function viewerForPoll(?string $deviceKey, ?int $pollId): array
    {
        if (!$deviceKey || !$pollId) {
            return ['has_voted' => false, 'option_idx' => null, 'poll_id' => $pollId];
        }

        $vote = $this->repo->findVoteByDevice($pollId, $deviceKey);
        if (!$vote) {
            return ['has_voted' => false, 'option_idx' => null, 'poll_id' => $pollId];
        }

        return [
            'has_voted' => true,
            'option_idx' => (int) $vote['option_idx'],
            'poll_id' => $pollId,
        ];
    }
}
