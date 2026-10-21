<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard;

use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use Closure;
use Laravel\Pennant\Feature;
use Throwable;

/**
 * Bound as a scoped singleton: rules and overrides are read once per request (or job) per feature.
 */
class RolloutManager
{
    /** @var array<string, array<string, mixed>> */
    private array $overrides = [];

    /** @var array<string, int|null> */
    private array $percentages = [];

    public function decide(string $feature, mixed $scope, mixed $default = false): mixed
    {
        $key = self::serialize($scope);

        if ($key !== null) {
            $overrides = $this->overrides($feature);

            if (array_key_exists($key, $overrides)) {
                return $overrides[$key];
            }
        }

        $percentage = $this->percentage($feature);

        if ($percentage !== null) {
            return match (true) {
                $percentage >= 100 => true,
                $percentage <= 0 => false,
                default => $key !== null && Rollout::bucket($feature, $key) < $percentage,
            };
        }

        return $default instanceof Closure ? $default($scope) : $default;
    }

    public function percentage(string $feature): ?int
    {
        if (! array_key_exists($feature, $this->percentages)) {
            try {
                $this->percentages[$feature] = Rule::query()->where('feature', $feature)->value('percentage');
            } catch (Throwable) {
                // Tables not migrated yet: behave as if the dashboard had never been used.
                $this->percentages[$feature] = null;
            }
        }

        return $this->percentages[$feature] === null ? null : (int) $this->percentages[$feature];
    }

    /** @return array<string, mixed> */
    public function overrides(string $feature): array
    {
        if (! array_key_exists($feature, $this->overrides)) {
            try {
                $this->overrides[$feature] = Override::query()->where('feature', $feature)->pluck('value', 'scope')
                    ->map(fn ($value) => json_decode((string) $value, true))
                    ->all();
            } catch (Throwable) {
                $this->overrides[$feature] = [];
            }
        }

        return $this->overrides[$feature];
    }

    public function flush(): void
    {
        $this->overrides = [];
        $this->percentages = [];
    }

    /** The scope as Pennant stores it, or null when Pennant cannot serialize it. */
    public static function serialize(mixed $scope): ?string
    {
        try {
            return Feature::serializeScope($scope);
        } catch (Throwable) {
            return null;
        }
    }
}
