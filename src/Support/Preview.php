<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Support;

use Closure;
use Illuminate\Support\Lottery;
use Laravel\Pennant\Contracts\FeatureScopeable;

/**
 * Works out the value a scope gets without writing anything: no stored value, no cache entry,
 * no Pennant event. Order as in Pennant's Decorator: a class feature's before() hook, then the
 * stored value, then the resolver.
 */
class Preview
{
    public function __construct(private readonly StoreInspector $store) {}

    /**
     * Source is one of: before, stored, resolver, lottery or unknown.
     *
     * @return array{value: mixed, source: string, error: string|null}
     */
    public function value(string $feature, mixed $scope): array
    {
        if ($scope instanceof FeatureScopeable) {
            $scope = $scope->toFeatureIdentifier($this->store->name);
        }

        $resolver = $this->store->decorator->nameMap()[$feature] ?? null;

        try {
            $instance = is_string($resolver) && class_exists($resolver) ? app($resolver) : null;

            if ($instance !== null && method_exists($instance, 'before')) {
                $before = $instance->before($scope);
                if ($before !== null) {
                    return ['value' => $before, 'source' => 'before', 'error' => null];
                }
            }

            $key = (new ScopeResolver($this->store))->serialize($scope);
            $stored = $key !== null ? $this->store->storedValue($feature, $key) : null;
            if ($stored !== null) {
                return ['value' => json_decode($stored, true), 'source' => 'stored', 'error' => null];
            }

            $value = match (true) {
                $instance !== null => method_exists($instance, 'resolve') ? $instance->resolve($scope) : $instance($scope),
                $resolver instanceof Closure => $resolver($scope),
                $resolver instanceof Lottery => null,
                $resolver === null && ! in_array($feature, $this->store->decorator->defined(), true) => null,
                default => $resolver,
            };

            if ($resolver instanceof Lottery || $value instanceof Lottery) {
                return ['value' => null, 'source' => 'lottery', 'error' => null];
            }

            return ['value' => $value, 'source' => $resolver === null ? 'unknown' : 'resolver', 'error' => null];
        } catch (\Throwable $e) {
            return ['value' => null, 'source' => 'resolver', 'error' => class_basename($e).': '.$e->getMessage()];
        }
    }
}
