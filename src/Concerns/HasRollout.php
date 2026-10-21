<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Concerns;

use AmjadIqbal\PennantDashboard\Rollout;
use Laravel\Pennant\Attributes\Name;
use ReflectionClass;

/**
 * For class-based features:
 *
 *     class NewCheckout
 *     {
 *         use HasRollout;
 *
 *         public function resolve(User $user): bool
 *         {
 *             return $this->rollout($user, default: false);
 *         }
 *     }
 */
trait HasRollout
{
    protected function rollout(mixed $scope, mixed $default = false, ?string $store = null): mixed
    {
        return Rollout::decide($this->pennantFeatureName(), $scope, $default, $store);
    }

    /** The name Pennant registers this class under: #[Name], then a $name property, then the class. */
    protected function pennantFeatureName(): string
    {
        $attribute = (new ReflectionClass($this))->getAttributes(Name::class)[0] ?? null;

        if ($attribute !== null) {
            return $attribute->newInstance()->name;
        }

        return property_exists($this, 'name') && is_string($this->name) ? $this->name : static::class;
    }
}
