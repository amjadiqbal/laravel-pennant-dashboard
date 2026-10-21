<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Contracts\CanListStoredFeatures;
use Laravel\Pennant\Contracts\CanSetManyFeaturesForScopes;
use Laravel\Pennant\Contracts\DefinesFeaturesExternally;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Drivers\ArrayDriver;
use Laravel\Pennant\Drivers\DatabaseDriver;
use Laravel\Pennant\Drivers\Decorator;
use Laravel\Pennant\Feature;

/**
 * What one Pennant store can do, and read access to its stored values where the driver allows it.
 */
class StoreInspector
{
    public readonly Decorator $decorator;

    public readonly Driver $driver;

    public function __construct(public readonly string $name)
    {
        $this->decorator = Feature::store($name);
        $this->driver = $this->decorator->getDriver();
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_map('strval', array_keys((array) config('pennant.stores', [])));
    }

    public static function default(): string
    {
        return (string) (config('pennant.default') ?? 'database');
    }

    public function driverName(): string
    {
        return (string) config("pennant.stores.{$this->name}.driver", 'unknown');
    }

    public function isDatabase(): bool
    {
        return $this->driver instanceof DatabaseDriver;
    }

    public function isArray(): bool
    {
        return $this->driver instanceof ArrayDriver;
    }

    /** True when values set here survive the request. Null when we cannot know (custom drivers). */
    public function persists(): ?bool
    {
        return match (true) {
            $this->isDatabase() => true,
            $this->isArray() => false,
            default => null,
        };
    }

    /** Can the dashboard read every stored scope of a feature? Only for the database driver. */
    public function canListScopes(): bool
    {
        return $this->isDatabase();
    }

    public function canListStoredFeatures(): bool
    {
        return $this->driver instanceof CanListStoredFeatures;
    }

    /**
     * One row per capability, for the "what this driver supports" panel.
     *
     * @return list<array{label: string, ok: bool|null, note: string}>
     */
    public function capabilities(): array
    {
        $persists = $this->persists();

        return [
            ['label' => 'List defined features', 'ok' => true, 'note' => 'Feature::defined()'],
            ['label' => 'List stored features', 'ok' => $this->canListStoredFeatures(), 'note' => 'CanListStoredFeatures'],
            ['label' => 'Show every stored scope', 'ok' => $this->canListScopes(), 'note' => $this->canListScopes() ? 'reads the '.$this->table().' table' : 'only the database driver exposes its rows'],
            ['label' => 'Values survive the request', 'ok' => $persists, 'note' => match ($persists) {
                true => 'stored per scope',
                false => 'the array driver forgets everything after each request',
                null => 'depends on your driver',
            }],
            ['label' => 'Set for one scope / everyone, purge', 'ok' => true, 'note' => 'part of the Driver contract'],
            ['label' => 'Bulk writes', 'ok' => $this->driver instanceof CanSetManyFeaturesForScopes, 'note' => 'CanSetManyFeaturesForScopes'],
            ['label' => 'Features defined outside the app', 'ok' => $this->driver instanceof DefinesFeaturesExternally, 'note' => 'DefinesFeaturesExternally'],
        ];
    }

    public function table(): string
    {
        return (string) (config("pennant.stores.{$this->name}.table") ?? 'features');
    }

    public function query(): Builder
    {
        return DB::connection(config("pennant.stores.{$this->name}.connection"))->table($this->table());
    }

    /** @return list<string> */
    public function storedFeatureNames(): array
    {
        if (! $this->canListStoredFeatures()) {
            return [];
        }

        return array_values(array_map('strval', $this->decorator->stored()));
    }

    /**
     * Stored value counts per feature: total, on (true), off (false) and other values.
     *
     * @return array<string, array{total: int, on: int, off: int, other: int}>
     */
    public function counts(): array
    {
        if (! $this->canListScopes()) {
            return [];
        }

        $counts = [];

        foreach ($this->query()->select('name', 'value', DB::raw('count(*) as aggregate'))->groupBy('name', 'value')->get() as $row) {
            $name = (string) $row->name;
            $counts[$name] ??= ['total' => 0, 'on' => 0, 'off' => 0, 'other' => 0];
            $n = (int) $row->aggregate;
            $counts[$name]['total'] += $n;
            $bucket = match (json_decode((string) $row->value, true)) {
                true => 'on',
                false => 'off',
                default => 'other',
            };
            $counts[$name][$bucket] += $n;
        }

        return $counts;
    }

    /** The stored value for one feature and serialized scope, or null when none is stored. */
    public function storedValue(string $feature, string $scope): ?string
    {
        if (! $this->canListScopes()) {
            return null;
        }

        $value = $this->query()->where('name', $feature)->where('scope', $scope)->value('value');

        return $value === null ? null : (string) $value;
    }
}
