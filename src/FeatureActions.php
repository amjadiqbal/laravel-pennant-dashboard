<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard;

use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\Models\Rule;
use AmjadIqbal\PennantDashboard\Support\FeatureCatalog;
use AmjadIqbal\PennantDashboard\Support\ScopeResolver;
use AmjadIqbal\PennantDashboard\Support\StoreInspector;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Every change the dashboard can make. All writes go through Pennant's own API (activate,
 * deactivate, forget, activateForEveryone, setAll, purge); the package's tables only hold the
 * rollout rule, the per-scope overrides made here, and the audit log.
 */
class FeatureActions
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly RolloutManager $rollouts,
    ) {}

    /** Turn a feature on or off for one scope, and remember it as an override. */
    public function setForScope(StoreInspector $store, string $feature, mixed $scope, bool $active): void
    {
        $key = (new ScopeResolver($store))->serialize($scope);
        $old = $this->currentValue($store, $feature, $key);

        $this->audit->muted(function () use ($store, $feature, $scope, $active, $key) {
            $active
                ? $store->decorator->for($scope)->activate($feature)
                : $store->decorator->for($scope)->deactivate($feature);

            if ($key !== null) {
                Override::query()->updateOrCreate(
                    ['store' => $store->name, 'feature' => $feature, 'scope' => $key],
                    ['value' => json_encode($active), 'updated_by' => $this->actor()],
                );
            }
        });

        $this->rollouts->flush();
        $this->audit->record($active ? 'activate' : 'deactivate', $feature, $store->name, $key, $old, $active);
    }

    /** Remove the stored value and the override for one scope, so the resolver decides again. */
    public function forgetForScope(StoreInspector $store, string $feature, mixed $scope): void
    {
        $key = (new ScopeResolver($store))->serialize($scope);
        $old = $this->currentValue($store, $feature, $key);

        $this->audit->muted(function () use ($store, $feature, $scope, $key) {
            $store->decorator->for($scope)->forget($feature);
            Override::query()->where('store', $store->name)->where('feature', $feature)->where('scope', $key)->delete();
        });

        $this->rollouts->flush();
        $this->audit->record('forget', $feature, $store->name, $key, $old);
    }

    /**
     * Pennant's activateForEveryone / deactivateForEveryone: every stored value is overwritten.
     * For a feature that calls Rollout::decide(), the rule (100% or 0%) also covers scopes that
     * have not been resolved yet. Overrides are cleared, since "everyone" includes them.
     */
    public function setForEveryone(StoreInspector $store, string $feature, bool $active, bool $managed): int
    {
        $affected = $store->canListScopes() ? $store->query()->where('name', $feature)->count() : 0;

        $this->audit->muted(function () use ($store, $feature, $active, $managed) {
            $active
                ? $store->decorator->activateForEveryone($feature)
                : $store->decorator->deactivateForEveryone($feature);

            Override::query()->where('store', $store->name)->where('feature', $feature)->delete();

            if ($managed) {
                Rule::query()->updateOrCreate(['store' => $store->name, 'feature' => $feature], ['percentage' => $active ? 100 : 0, 'updated_by' => $this->actor()]);
            }
        });

        $this->rollouts->flush();
        $this->audit->record($active ? 'activate_everyone' : 'deactivate_everyone', $feature, $store->name, new: $active, affected: $affected);

        return $affected;
    }

    /**
     * Roll a feature out to a sticky percentage of scopes. Stored values that are not overrides
     * are re-bucketed (database driver), so the percentage holds for scopes already resolved too.
     * Returns how many stored values changed.
     */
    public function setRollout(StoreInspector $store, string $feature, int $percentage): int
    {
        $percentage = max(0, min(100, $percentage));
        $old = $this->rollouts->percentage($feature, $store->name);

        $changed = $this->audit->muted(function () use ($store, $feature, $percentage) {
            Rule::query()->updateOrCreate(['store' => $store->name, 'feature' => $feature], ['percentage' => $percentage, 'updated_by' => $this->actor()]);

            return $this->rebucket($store, $feature, $percentage);
        });

        $this->rollouts->flush();
        $this->audit->record('rollout', $feature, $store->name, old: $old, new: $percentage, affected: $changed);

        return $changed;
    }

    /** Remove the rollout: stored values that are not overrides are forgotten and resolve from code again. */
    public function clearRollout(StoreInspector $store, string $feature): int
    {
        $old = $this->rollouts->percentage($feature, $store->name);

        $forgotten = $this->audit->muted(function () use ($store, $feature) {
            Rule::query()->where('store', $store->name)->where('feature', $feature)->delete();

            return $this->forgetUnpinned($store, $feature);
        });

        $this->rollouts->flush();
        $this->audit->record('rollout_cleared', $feature, $store->name, old: $old, affected: $forgotten);

        return $forgotten;
    }

    /** Pennant's purge for one feature, plus the overrides (and optionally the rollout rule). */
    public function purge(StoreInspector $store, string $feature, bool $withRule): int
    {
        $affected = $store->canListScopes() ? $store->query()->where('name', $feature)->count() : 0;

        $this->audit->muted(function () use ($store, $feature, $withRule) {
            $store->decorator->purge($feature);
            Override::query()->where('store', $store->name)->where('feature', $feature)->delete();

            if ($withRule) {
                Rule::query()->where('store', $store->name)->where('feature', $feature)->delete();
            }
        });

        $this->rollouts->flush();
        $this->audit->record($withRule ? 'purge_with_rule' : 'purge', $feature, $store->name, affected: $affected);

        return $affected;
    }

    /**
     * Purge every feature that is stored but no longer defined.
     *
     * @return list<string>
     */
    public function purgeStale(StoreInspector $store): array
    {
        $stale = array_values(array_map(
            fn ($feature) => $feature->name,
            array_filter((new FeatureCatalog($store))->all(), fn ($feature) => $feature->stale()),
        ));

        foreach ($stale as $feature) {
            $this->purge($store, $feature, withRule: true);
        }

        return $stale;
    }

    private function rebucket(StoreInspector $store, string $feature, int $percentage): int
    {
        if (! $store->canListScopes()) {
            return 0;
        }

        $pinned = array_flip(Override::query()->where('store', $store->name)->where('feature', $feature)->pluck('scope')->map(fn ($s) => (string) $s)->all());
        $changed = 0;

        $store->query()->where('name', $feature)->orderBy('id')->chunkById(500, function ($rows) use ($store, $feature, $percentage, $pinned, &$changed) {
            $writes = [];

            foreach ($rows as $row) {
                $scope = (string) $row->scope;

                if (isset($pinned[$scope])) {
                    continue;
                }

                $value = $percentage >= 100 || ($percentage > 0 && Rollout::bucket($feature, $scope) < $percentage);

                if (json_decode((string) $row->value, true) !== $value) {
                    $writes[] = ['feature' => $feature, 'scope' => $scope, 'value' => $value];
                }
            }

            if ($writes !== []) {
                // A serialized scope string serializes to itself, so these write the same rows.
                if (method_exists($store->decorator, 'setAll')) {
                    $store->decorator->setAll($writes);
                } else {
                    // Pennant before 1.19 has no bulk write.
                    foreach ($writes as $write) {
                        $store->decorator->set($feature, $write['scope'], $write['value']);
                    }
                }
                $changed += count($writes);
            }
        });

        return $changed;
    }

    private function forgetUnpinned(StoreInspector $store, string $feature): int
    {
        if (! $store->canListScopes()) {
            return 0;
        }

        $pinned = Override::query()->where('store', $store->name)->where('feature', $feature)->pluck('scope')->map(fn ($s) => (string) $s)->all();
        $forgotten = 0;

        $store->query()->where('name', $feature)->orderBy('id')->chunkById(500, function ($rows) use ($store, $feature, $pinned, &$forgotten) {
            $scopes = $rows->pluck('scope')->map(fn ($s) => (string) $s)->diff($pinned)->values()->all();

            if ($scopes !== []) {
                $store->decorator->for($scopes)->forget($feature);
                $forgotten += count($scopes);
            }
        });

        return $forgotten;
    }

    private function currentValue(StoreInspector $store, string $feature, ?string $key): mixed
    {
        if ($key === null) {
            return null;
        }

        $stored = $store->storedValue($feature, $key);

        if ($stored !== null) {
            return json_decode($stored, true);
        }

        return $this->rollouts->overrides($feature, $store->name)[$key] ?? null;
    }

    private function actor(): ?string
    {
        $user = auth()->user();

        return $user instanceof Authenticatable ? (string) $user->getAuthIdentifier() : null;
    }
}
