<?php
declare(strict_types=1);

namespace Youtube;

use Discovery\DiscoveryRepository;
use Youtube\Contracts\Project;

/**
 * Reads Discovery change data for YouTube video generation.
 * Read-only access to discovery_changes table.
 */
final class Reader
{
    public function __construct(
        private readonly DiscoveryRepository $discoveryRepo,
    ) {
    }

    /**
     * Get a specific change by ID for video generation.
     * @return Project|null
     */
    public function getChangeById(int $changeId): ?Project
    {
        $change = $this->discoveryRepo->findPublishedChangeById($changeId, includeSeed: false);
        if ($change === null) {
            return null;
        }

        $editionDate = $change['edition_date'] ?? date('Y-m-d');
        return Project::fromDiscoveryChange($change, $editionDate);
    }

    /**
     * Get all verified changes for a specific date.
     * @return list<Project>
     */
    public function getChangesForDate(string $date): array
    {
        $edition = $this->discoveryRepo->findPublishedEditionByDate($date, includeSeed: false);
        if ($edition === null) {
            return [];
        }

        $changes = $this->discoveryRepo->getVerifiedChangesForEdition((int) $edition['id']);
        $projects = [];
        
        foreach ($changes as $change) {
            $projects[] = Project::fromDiscoveryChange($change, $date);
        }

        return $projects;
    }

    /**
     * Get the latest published edition's changes.
     * @return list<Project>
     */
    public function getLatestChanges(): array
    {
        $edition = $this->discoveryRepo->findLatestPublishedEdition(includeSeed: false);
        if ($edition === null) {
            return [];
        }

        $date = $edition['edition_date'];
        $changes = $this->discoveryRepo->getVerifiedChangesForEdition((int) $edition['id']);
        $projects = [];
        
        foreach ($changes as $change) {
            $projects[] = Project::fromDiscoveryChange($change, $date);
        }

        return $projects;
    }

    /**
     * Search for changes by title keyword.
     * @return list<Project>
     */
    public function searchChanges(string $query, int $limit = 20): array
    {
        $changes = $this->discoveryRepo->searchPublishedChanges($query, $limit, includeSeed: false);
        $projects = [];
        
        foreach ($changes as $change) {
            $editionDate = $change['edition_date'] ?? date('Y-m-d');
            $change['briefing'] = json_decode($change['briefing_json'] ?? '{}', true) ?: [];
            unset($change['briefing_json']);
            $projects[] = Project::fromDiscoveryChange($change, $editionDate);
        }

        return $projects;
    }
}
