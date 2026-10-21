<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard;

use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use AmjadIqbal\PennantDashboard\Support\SchemaStatus;
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

    /** SchemaStatus of the package tables, checked on the first read of the request. */
    private ?string $schema = null;

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
            $this->percentages[$memo] = $this->read(
                fn (bool $byStore) => Rule::query()->when($byStore, fn ($q) => $q->where('store', $store))->where('feature', $feature)->value('percentage'),
                null,
            );
        }

        return $this->percentages[$memo] === null ? null : (int) $this->percentages[$memo];
    }

    /** @return array<string, mixed> */
    public function overrides(string $feature, ?string $store = null): array
    {
        $store = self::store($store);
        $memo = $store."\0".$feature;

        if (! array_key_exists($memo, $this->overrides)) {
            $this->overrides[$memo] = $this->read(
                fn (bool $byStore) => Override::query()->when($byStore, fn ($q) => $q->where('store', $store))->where('feature', $feature)->pluck('value', 'scope')
                    ->map(fn ($value) => json_decode((string) $value, true))
                    ->all(),
                [],
            );
        }

        return $this->overrides[$memo];
    }

    /**
     * Run a read scoped to the store. The schema is checked once per request first: a failed query
     * is not a reliable signal (SQLite treats an unknown "store" column as a string literal and just
     * returns nothing). With tables still at 1.0.0 (no store column) it reads the way 1.0.0 did, so
     * rollouts keep working until the app migrates; missing tables fall back to the default. Either
     * way the log gets one warning saying to run the migration.
     *
     * @template T
     *
     * @param  Closure(bool): T  $query  receives whether to filter by store
     * @param  T  $fallback
     * @return T
     */
    private function read(Closure $query, mixed $fallback): mixed
    {
        $this->schema ??= SchemaStatus::check();
        SchemaStatus::warnOnce($this->schema);

        if ($this->schema === SchemaStatus::MISSING) {
            return $fallback;
        }

        try {
            return $query($this->schema === SchemaStatus::OK);
        } catch (Throwable) {
            return $fallback;
        }
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
