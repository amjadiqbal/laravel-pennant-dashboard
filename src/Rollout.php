<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard;

/**
 * Call this from a feature's resolver to let the dashboard control it:
 *
 *     Feature::define('new-checkout', fn (User $user) => Rollout::decide('new-checkout', $user, false));
 *
 * It returns the dashboard override for this scope if there is one, otherwise the
 * rollout result (on for a sticky N% of scopes), otherwise your default.
 * Rules and overrides belong to a Pennant store: Pennant's default store unless you pass another.
 */
final class Rollout
{
    public static function decide(string $feature, mixed $scope, mixed $default = false, ?string $store = null): mixed
    {
        return app(RolloutManager::class)->decide($feature, $scope, $default, $store);
    }

    /**
     * The scope's fixed bucket for a feature, 0 to 99. A scope is in an N% rollout when
     * its bucket is below N, so raising the percentage only ever adds scopes.
     */
    public static function bucket(string $feature, string $serializedScope): int
    {
        return (int) (crc32($feature.'|'.$serializedScope) % 100);
    }
}
