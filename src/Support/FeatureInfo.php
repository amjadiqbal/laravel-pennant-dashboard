<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Support;

final class FeatureInfo
{
    /**
     * @param  string  $kind  class, closure, lottery, value or "not defined"
     * @param  bool  $managed  the resolver calls Rollout::decide(), so rollouts and overrides apply to new scopes
     * @param  array{total: int, on: int, off: int, other: int}|null  $counts  stored values (database driver only)
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $defined,
        public readonly bool $stored,
        public readonly string $kind,
        public readonly ?string $class,
        public readonly bool $managed,
        public readonly ?int $percentage,
        public readonly int $overrides,
        public readonly ?array $counts,
    ) {}

    /** Stored, but no longer defined anywhere: a candidate for "purge stale". */
    public function stale(): bool
    {
        return $this->stored && ! $this->defined;
    }

    public function rolloutLabel(): ?string
    {
        return match ($this->percentage) {
            null => null,
            100 => 'on for everyone',
            0 => 'off for everyone',
            default => $this->percentage.'% rollout',
        };
    }
}
