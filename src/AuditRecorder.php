<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard;

use AmjadIqbal\PennantDashboard\Models\AuditEntry;
use AmjadIqbal\PennantDashboard\Support\ScopeResolver;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Events\AllFeaturesPurged;
use Laravel\Pennant\Events\FeatureDeleted;
use Laravel\Pennant\Events\FeaturesPurged;
use Laravel\Pennant\Events\FeatureUpdated;
use Laravel\Pennant\Events\FeatureUpdatedForAllScopes;
use Throwable;

/**
 * Writes the audit log. Dashboard actions are recorded with their actor and IP; while one runs,
 * the Pennant events it causes are muted so each action is logged once.
 */
class AuditRecorder
{
    private int $muted = 0;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function muted(Closure $callback): mixed
    {
        $this->muted++;

        try {
            return $callback();
        } finally {
            $this->muted--;
        }
    }

    public function record(
        string $action,
        ?string $feature,
        ?string $store = null,
        ?string $scope = null,
        mixed $old = null,
        mixed $new = null,
        ?int $affected = null,
        string $source = 'dashboard',
    ): ?AuditEntry {
        $user = auth()->user();

        try {
            return AuditEntry::query()->create([
                'store' => $store,
                'feature' => $feature,
                'action' => $action,
                'scope' => $scope,
                'old_value' => $old === null ? null : json_encode($old),
                'new_value' => $new === null ? null : json_encode($new),
                'affected' => $affected,
                'source' => $source,
                'actor' => $user instanceof Authenticatable ? $this->actorLabel($user) : null,
                'actor_id' => $user instanceof Authenticatable ? (string) $user->getAuthIdentifier() : null,
                'ip' => $source === 'dashboard' ? request()->ip() : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            if ($source === 'dashboard') {
                throw $e;
            }

            // Never break the app's own feature changes because the audit table is missing.
            return null;
        }
    }

    /** Listener for Pennant's events: changes made by the app's own code or by artisan. */
    public function handle(object $event): void
    {
        if ($this->muted > 0) {
            return;
        }

        $source = app()->runningInConsole() ? 'console' : 'code';

        match (true) {
            $event instanceof FeatureUpdated => $this->record('set', (string) $event->feature, scope: RolloutManager::serialize($event->scope), new: $event->value, source: $source),
            $event instanceof FeatureUpdatedForAllScopes => $this->record('set_everyone', (string) $event->feature, new: $event->value, source: $source),
            $event instanceof FeatureDeleted => $this->record('forget', (string) $event->feature, scope: RolloutManager::serialize($event->scope), source: $source),
            $event instanceof FeaturesPurged => array_map(fn ($feature) => $this->record('purge', (string) $feature, source: $source), (array) $event->features),
            $event instanceof AllFeaturesPurged => $this->record('purge_all', null, source: $source),
            default => null,
        };
    }

    /** @return list<class-string> */
    public static function events(): array
    {
        return [FeatureUpdated::class, FeatureUpdatedForAllScopes::class, FeatureDeleted::class, FeaturesPurged::class, AllFeaturesPurged::class];
    }

    private function actorLabel(Authenticatable $user): string
    {
        $label = $user instanceof Model ? ScopeResolver::label($user) : null;

        return $label ?? class_basename($user).' #'.$user->getAuthIdentifier();
    }
}
