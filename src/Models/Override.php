<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A value set for one scope from the dashboard. Rollout changes never overwrite it.
 *
 * @property int $id
 * @property string $feature
 * @property string $scope serialized the way Pennant stores it, e.g. "App\Models\User|42"
 * @property string $value JSON
 * @property string|null $updated_by
 * @property Carbon|null $updated_at
 */
class Override extends Model
{
    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return config('pennant-dashboard.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return (string) config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides');
    }

    public function decodedValue(): mixed
    {
        return json_decode($this->value, true);
    }
}
