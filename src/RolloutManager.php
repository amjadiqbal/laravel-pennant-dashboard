<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard;

use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use Closure;
use Laravel\Pennant\Feature;
use Throwable;

/**
 * Bound as a scoped singleton: rules and overrides are read once per request (or job) per store and feature.
 * The store defaults to Pennant's default store; pass it when a feature is resolved in another store.
 */
class RolloutManager
{
    /** @var array<string, array<string, mixed>> */
    private array $overrides = [];

    /** @var array<string, int|null> */
    private array $percentages = [];

    public function decide(string $feature, mixed $scope, mixed $default = false, ?string $store = null): mixed
    {
        $key = self::serialize($scope);

        if ($key !== null) {
            $overrides = $this->overrides($feature, $store);

            if (array_key_exists($key, $overrides)) {
                return $overrides[$key];
            }
        }

        $percentage = $this->percentage($feature, $store);

        if ($percentage !== null) {
            return match (true) {
                $percentage >= 100 => true,
                $percentage <= 0 => false,
                default => $key !== null && Rollout::bucket($feature, $key) < $percentage,
            };
        }

        return $default instanceof Closure ? $default($scope) : $default;
    }

    public function percentage(string $feature, ?string $store = null): ?int
    {
        $store = self::store($store);
        $memo = $store."\0".$feature;

        if (! array_key_exists($memo, $this->percentages)) {
            try {
                $this->percentages[$memo] = Rule::query()->where('store', $store)->where('feature', $feature)->value('percentage');
            } catch (Throwable) {
                // Tables not migrated yet: behave as if the dashboard had never been used.
                $this->percentages[$memo] = null;
            }
        }

        return $this->percentages[$memo] === null ? null : (int) $this->percentages[$memo];
    }

    /** @return array<string, mixed> */
    public function overrides(string $feature, ?string $store = null): array
    {
        $store = self::store($store);
        $memo = $store."\0".$feature;

        if (! array_key_exists($memo, $this->overrides)) {
            try {
                $this->overrides[$memo] = Override::query()->where('store', $store)->where('feature', $feature)->pluck('value', 'scope')
                    ->map(fn ($value) => json_decode((string) $value, true))
                    ->all();
            } catch (Throwable) {
                $this->overrides[$memo] = [];
            }
        }

        return $this->overrides[$memo];
    }

    public static function store(?string $store = null): string
    {
        return $store ?? (string) (config('pennant.default') ?? 'database');
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
