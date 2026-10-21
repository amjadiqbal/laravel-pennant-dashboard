<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $store
 * @property string|null $feature
 * @property string $action
 * @property string|null $scope
 * @property string|null $old_value JSON
 * @property string|null $new_value JSON
 * @property int|null $affected
 * @property string $source dashboard, code or console
 * @property string|null $actor
 * @property string|null $actor_id
 * @property string|null $ip
 * @property Carbon $created_at
 */
class AuditEntry extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'affected' => 'integer'];

    public function getConnectionName(): ?string
    {
        return config('pennant-dashboard.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return (string) config('pennant-dashboard.tables.audit', 'pennant_dashboard_audit');
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        $days = config('pennant-dashboard.audit.retention_days');

        return $days === null
            ? static::query()->whereRaw('1 = 0')
            : static::query()->where('created_at', '<', now()->subDays((int) $days));
    }
}
