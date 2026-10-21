<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Support;

use AmjadIqbal\PennantDashboard\RolloutManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\GlobalScope;
use Throwable;

/**
 * Turns dashboard input into a Pennant scope, and Pennant's stored scope strings back into
 * something a person can read ("User #42 · ada@example.com").
 */
class ScopeResolver
{
    public const GLOBAL = '__laravel_global';

    public const NULL = '__laravel_null';

    public function __construct(private readonly StoreInspector $store) {}

    /**
     * Scope types offered in the picker: configured models plus model types found in storage.
     *
     * @return array<string, string> type => label
     */
    public function types(): array
    {
        $types = [];

        foreach (array_keys((array) config('pennant-dashboard.scopes', [])) as $class) {
            if ($this->modelClass((string) $class) !== null) {
                $types[(string) $class] = class_basename((string) $class);
            }
        }

        if ($this->store->canListScopes()) {
            try {
                $this->store->query()->select('scope')->distinct()->limit(5000)->pluck('scope')
                    ->map(fn ($scope) => Str::contains((string) $scope, '|') ? Str::beforeLast((string) $scope, '|') : null)
                    ->filter()->unique()
                    ->each(function ($type) use (&$types) {
                        if ($this->modelClass((string) $type) !== null) {
                            $types[(string) $type] ??= class_basename($this->modelClass((string) $type));
                        }
                    });
            } catch (Throwable) {
                // The features table may not exist yet.
            }
        }

        return $types;
    }

    /**
     * The Eloquent class for a stored type prefix (a class name or a morph alias), or null.
     *
     * @return class-string<Model>|null
     */
    public function modelClass(string $type): ?string
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * Build the scope Pennant should receive from the picker.
     *
     * @throws ValidationException
     */
    public function fromInput(string $type, ?string $key): mixed
    {
        $key = trim((string) $key);

        if ($type === 'global') {
            return new GlobalScope;
        }

        if ($type === 'raw') {
            if ($key === '') {
                throw ValidationException::withMessages(['scope_key' => 'Enter the scope exactly as Pennant stores it.']);
            }

            return $key;
        }

        $class = array_key_exists($type, $this->types()) ? $this->modelClass($type) : null;

        if ($class === null) {
            throw ValidationException::withMessages(['scope_type' => 'Pick a scope type from the list.']);
        }

        if ($key === '') {
            throw ValidationException::withMessages(['scope_key' => 'Enter the '.class_basename($class).' ID.']);
        }

        /** @var Model|null $model */
        $model = $class::query()->find($key);

        if ($model === null) {
            throw ValidationException::withMessages(['scope_key' => 'No '.class_basename($class)." with ID {$key}."]);
        }

        return $model;
    }

    /** The string Pennant uses for this scope in this store. */
    public function serialize(mixed $scope): ?string
    {
        if ($scope instanceof FeatureScopeable) {
            $scope = $scope->toFeatureIdentifier($this->store->name);
        }

        return RolloutManager::serialize($scope);
    }

    /**
     * Readable labels for stored scope strings, loading models in one query per type.
     *
     * @param  iterable<string>  $scopes
     * @return array<string, array{type: string, key: string, label: string|null}>
     */
    public function describe(iterable $scopes): array
    {
        $parsed = [];
        /** @var array<class-string<Model>, list<string>> $keysByClass */
        $keysByClass = [];

        foreach ($scopes as $scope) {
            $scope = (string) $scope;
            $parsed[$scope] = $this->parse($scope);

            if (($class = $parsed[$scope]['class']) !== null) {
                $keysByClass[$class][] = $parsed[$scope]['key'];
            }
        }

        $labels = [];
        foreach ($keysByClass as $class => $keys) {
            try {
                foreach ($class::query()->whereKey(array_unique($keys))->get() as $model) {
                    $labels[$class][(string) $model->getKey()] = self::label($model);
                }
            } catch (Throwable) {
                // Unreadable model table: show raw keys.
            }
        }

        $out = [];
        foreach ($parsed as $scope => $info) {
            $out[$scope] = [
                'type' => $info['type'],
                'key' => $info['key'],
                'label' => $info['class'] !== null ? ($labels[$info['class']][$info['key']] ?? null) : null,
            ];
        }

        return $out;
    }

    /** @return array{type: string, key: string, class: string|null} */
    public function parse(string $scope): array
    {
        if ($scope === self::GLOBAL) {
            return ['type' => 'Global', 'key' => '', 'class' => null];
        }

        if ($scope === self::NULL) {
            return ['type' => 'No scope', 'key' => '', 'class' => null];
        }

        if (Str::contains($scope, '|')) {
            $type = Str::beforeLast($scope, '|');
            $class = $this->modelClass($type);

            return ['type' => $class !== null ? class_basename($class) : $type, 'key' => Str::afterLast($scope, '|'), 'class' => $class];
        }

        return ['type' => 'Value', 'key' => $scope, 'class' => null];
    }

    public static function label(Model $model): ?string
    {
        $attributes = (array) (((array) config('pennant-dashboard.scopes', []))[$model::class]['label']
            ?? config('pennant-dashboard.label_attributes', []));

        $parts = [];
        foreach ($attributes as $attribute) {
            $value = $model->getAttribute((string) $attribute);
            if (is_scalar($value) && (string) $value !== '') {
                $parts[] = (string) $value;
            }
        }

        return $parts === [] ? null : implode(' · ', array_unique($parts));
    }

    /**
     * Search a model type by its searchable columns, for the scope picker.
     *
     * @return list<array{key: string, label: string}>
     */
    public function search(string $type, string $term, int $limit = 20): array
    {
        $class = array_key_exists($type, $this->types()) ? $this->modelClass($type) : null;
        $term = trim($term);

        if ($class === null || $term === '') {
            return [];
        }

        /** @var Model $instance */
        $instance = new $class;
        $columns = array_values(array_filter(
            (array) config('pennant-dashboard.search_columns', []),
            fn ($column) => Schema::connection($instance->getConnectionName())->hasColumn($instance->getTable(), (string) $column),
        ));

        $query = $class::query()->limit($limit);
        $query->where(function ($query) use ($columns, $term, $instance) {
            $query->where($instance->getKeyName(), $term);
            foreach ($columns as $column) {
                $query->orWhere((string) $column, 'like', '%'.addcslashes($term, '%_\\').'%');
            }
        });

        return $query->get()->map(fn (Model $model) => [
            'key' => (string) $model->getKey(),
            'label' => self::label($model) ?? class_basename($class).' #'.$model->getKey(),
        ])->values()->all();
    }
}
