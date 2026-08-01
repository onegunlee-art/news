<?php
declare(strict_types=1);

namespace Youtube\Contracts;

final class Project
{
    /** @param list<Scene> $scenes */
    public function __construct(
        public readonly int $changeId,
        public readonly string $editionDate,
        public readonly string $title,
        public readonly array $briefing,
        public readonly array $sources,
        public readonly array $scenes = [],
        public readonly ?int $id = null,
        public readonly string $status = 'pending',
        public readonly ?string $videoPath = null,
    ) {
    }

    public static function fromDiscoveryChange(array $change, string $editionDate): self
    {
        return new self(
            changeId: (int) $change['id'],
            editionDate: $editionDate,
            title: (string) $change['title'],
            briefing: (array) ($change['briefing'] ?? []),
            sources: (array) ($change['sources'] ?? []),
        );
    }

    public function withScenes(array $scenes): self
    {
        return new self(
            $this->changeId,
            $this->editionDate,
            $this->title,
            $this->briefing,
            $this->sources,
            $scenes,
            $this->id,
            $this->status,
            $this->videoPath,
        );
    }

    public function withStatus(string $status): self
    {
        return new self(
            $this->changeId,
            $this->editionDate,
            $this->title,
            $this->briefing,
            $this->sources,
            $this->scenes,
            $this->id,
            $status,
            $this->videoPath,
        );
    }

    public function withVideoPath(string $videoPath): self
    {
        return new self(
            $this->changeId,
            $this->editionDate,
            $this->title,
            $this->briefing,
            $this->sources,
            $this->scenes,
            $this->id,
            $this->status,
            $videoPath,
        );
    }

    public function getBriefingField(string $field): string
    {
        return (string) ($this->briefing[$field] ?? '');
    }

    public function getHighlights(): array
    {
        return (array) ($this->briefing['highlights'] ?? []);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'change_id' => $this->changeId,
            'edition_date' => $this->editionDate,
            'title' => $this->title,
            'briefing' => $this->briefing,
            'sources' => $this->sources,
            'scenes' => array_map(fn(Scene $s) => $s->toArray(), $this->scenes),
            'status' => $this->status,
            'video_path' => $this->videoPath,
        ];
    }
}
