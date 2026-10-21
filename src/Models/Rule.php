<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A percentage rollout for one feature. 100 means on for everyone, 0 off for everyone.
 *
 * @property int $id
 * @property string $feature
 * @property int $percentage
 * @property string|null $updated_by
 * @property Carbon|null $updated_at
 */
class Rule extends Model
{
    protected $guarded = [];

    protected $casts = ['percentage' => 'integer'];

    public function getConnectionName(): ?string
    {
        return config('pennant-dashboard.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return (string) config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules');
    }
}
