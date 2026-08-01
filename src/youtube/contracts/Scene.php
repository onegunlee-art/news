<?php
declare(strict_types=1);

namespace Youtube\Contracts;

final class Scene
{
    public function __construct(
        public readonly int $sceneNum,
        public readonly string $visualType,
        public readonly string $narration,
        public readonly mixed $textOverlay,
        public readonly ?string $location = null,
        public readonly ?string $visualPath = null,
        public readonly ?string $audioPath = null,
        public readonly ?int $durationMs = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sceneNum: (int) ($data['scene'] ?? $data['scene_num'] ?? 0),
            visualType: (string) ($data['visual_type'] ?? 'text'),
            narration: (string) ($data['narration'] ?? ''),
            textOverlay: $data['text_overlay'] ?? null,
            location: isset($data['location']) ? (string) $data['location'] : null,
            visualPath: isset($data['visual_path']) ? (string) $data['visual_path'] : null,
            audioPath: isset($data['audio_path']) ? (string) $data['audio_path'] : null,
            durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'scene_num' => $this->sceneNum,
            'visual_type' => $this->visualType,
            'narration' => $this->narration,
            'text_overlay' => $this->textOverlay,
            'location' => $this->location,
            'visual_path' => $this->visualPath,
            'audio_path' => $this->audioPath,
            'duration_ms' => $this->durationMs,
        ];
    }

    public function withVisualPath(string $path): self
    {
        return new self(
            $this->sceneNum,
            $this->visualType,
            $this->narration,
            $this->textOverlay,
            $this->location,
            $path,
            $this->audioPath,
            $this->durationMs,
        );
    }

    public function withAudioPath(string $path, int $durationMs): self
    {
        return new self(
            $this->sceneNum,
            $this->visualType,
            $this->narration,
            $this->textOverlay,
            $this->location,
            $this->visualPath,
            $path,
            $durationMs,
        );
    }
}
