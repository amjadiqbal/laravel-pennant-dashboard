<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Http\Controllers;

use AmjadIqbal\PennantDashboard\Http\Authorize;
use AmjadIqbal\PennantDashboard\Models\AuditEntry;
use AmjadIqbal\PennantDashboard\Models\Override;
use AmjadIqbal\PennantDashboard\PennantDashboardServiceProvider;
use AmjadIqbal\PennantDashboard\Rollout;
use AmjadIqbal\PennantDashboard\RolloutManager;
use AmjadIqbal\PennantDashboard\Support\FeatureCatalog;
use AmjadIqbal\PennantDashboard\Support\ScopeResolver;
use AmjadIqbal\PennantDashboard\Support\StoreInspector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DashboardController
{
    use Concerns;

    public function index(Request $request): View
    {
        $store = $this->store($request);
        $catalog = new FeatureCatalog($store);
        $scopes = new ScopeResolver($store);
        $features = $catalog->all();

        // "What does this user see?": every feature's value for one scope.
        $inspection = null;
        if ($request->filled('scope_type')) {
            $inspection = $this->inspect($store, $scopes, $request);
        }

        return view('pennant-dashboard::index', [
            ...$this->shared($store),
            'features' => $features,
            'capabilities' => $store->capabilities(),
            'scopeTypes' => $scopes->types(),
            'inspection' => $inspection,
            'recent' => $this->recentAudit(null),
        ]);
    }

    public function show(Request $request, string $feature): View
    {
        $store = $this->store($request);
        $info = (new FeatureCatalog($store))->find($feature);
        abort_if($info === null, 404, "Feature [{$feature}] is not defined or stored in the [{$store->name}] store.");

        $scopes = new ScopeResolver($store);
        $overrides = rescue(fn () => Override::query()->where('feature', $feature)->pluck('value', 'scope')->map(fn ($v) => json_decode((string) $v, true))->all(), [], false);

        $rows = null;
        if ($store->canListScopes()) {
            $query = $store->query()->where('name', $feature);
            if ($request->filled('type')) {
                $query->where('scope', 'like', addcslashes((string) $request->input('type'), '%_\\').'|%');
            }
            if ($request->filled('value')) {
                $query->where('value', $request->input('value') === 'on' ? 'true' : 'false');
            }
            $rows = $query->orderByDesc('updated_at')->orderByDesc('id')
                ->paginate((int) config('pennant-dashboard.per_page', 50))->withQueryString();
        }

        $listed = $rows ? collect($rows->items())->pluck('scope')->map(fn ($s) => (string) $s) : collect();
        $labels = $scopes->describe($listed->merge(array_keys($overrides))->unique());

        $check = null;
        if ($request->filled('scope_type')) {
            $check = $this->check($store, $scopes, $feature, $request);
        }

        $search = [];
        if ($request->filled('q') && $request->filled('search_type')) {
            $search = rescue(fn () => $scopes->search((string) $request->input('search_type'), (string) $request->input('q')), [], false);
        }

        return view('pennant-dashboard::feature', [
            ...$this->shared($store),
            'feature' => $info,
            'rows' => $rows,
            'overrides' => $overrides,
            'labels' => $labels,
            'scopeTypes' => $scopes->types(),
            'check' => $check,
            'search' => $search,
            'recent' => $this->recentAudit($feature),
        ]);
    }

    public function audit(Request $request): View
    {
        $store = $this->store($request);

        $entries = rescue(function () use ($request) {
            $query = AuditEntry::query()->latest('created_at')->latest('id');
            if ($request->filled('feature')) {
                $query->where('feature', $request->input('feature'));
            }
            if ($request->filled('source')) {
                $query->where('source', $request->input('source'));
            }

            return $query->paginate((int) config('pennant-dashboard.per_page', 50))->withQueryString();
        }, null, false);

        return view('pennant-dashboard::audit', [
            ...$this->shared($store),
            'entries' => $entries,
            'labels' => $entries ? (new ScopeResolver($store))->describe(collect($entries->items())->pluck('scope')->filter()->unique()) : [],
        ]);
    }

    /** @return array<string, mixed> */
    private function shared(StoreInspector $store): array
    {
        return [
            'store' => $store,
            'stores' => StoreInspector::names(),
            'canManage' => Authorize::canManage(),
            'version' => PennantDashboardServiceProvider::version(),
        ];
    }

    /** @return array<string, mixed> */
    private function inspect(StoreInspector $store, ScopeResolver $scopes, Request $request): array
    {
        try {
            $scope = $scopes->fromInput((string) $request->input('scope_type'), (string) $request->input('scope_key'));
        } catch (ValidationException $e) {
            return ['error' => collect($e->errors())->flatten()->first()];
        }

        $key = $scopes->serialize($scope);
        $applicable = rescue(fn () => $store->decorator->definedFeaturesForScope($scope)->all(), [], false);
        $values = [];

        foreach ($applicable as $feature) {
            try {
                $values[(string) $feature] = ['value' => $store->decorator->for($scope)->value($feature), 'error' => null];
            } catch (Throwable $e) {
                $values[(string) $feature] = ['value' => null, 'error' => class_basename($e).': '.$e->getMessage()];
            }
        }

        return [
            'error' => null,
            'key' => $key,
            'label' => $key !== null ? ($scopes->describe([$key])[$key] ?? null) : null,
            'values' => $values,
        ];
    }

    /** @return array<string, mixed> */
    private function check(StoreInspector $store, ScopeResolver $scopes, string $feature, Request $request): array
    {
        try {
            $scope = $scopes->fromInput((string) $request->input('scope_type'), (string) $request->input('scope_key'));
        } catch (ValidationException $e) {
            return ['error' => collect($e->errors())->flatten()->first()];
        }

        $key = $scopes->serialize($scope);
        $storedBefore = $key !== null ? $store->storedValue($feature, $key) : null;
        $manager = app(RolloutManager::class);

        try {
            $value = $store->decorator->for($scope)->value($feature);
            $error = null;
        } catch (Throwable $e) {
            $value = null;
            $error = class_basename($e).': '.$e->getMessage();
        }

        return [
            'error' => $error,
            'scope' => $scope,
            'type' => (string) $request->input('scope_type'),
            'scope_key' => (string) $request->input('scope_key'),
            'key' => $key,
            'label' => $key !== null ? ($scopes->describe([$key])[$key] ?? null) : null,
            'value' => $value,
            'resolvedNow' => $store->canListScopes() && $storedBefore === null,
            'override' => $key !== null && array_key_exists($key, $manager->overrides($feature)),
            'bucket' => $key !== null ? Rollout::bucket($feature, $key) : null,
        ];
    }

    /** @return Collection<int, AuditEntry> */
    private function recentAudit(?string $feature): Collection
    {
        return rescue(fn () => AuditEntry::query()
            ->when($feature !== null, fn ($q) => $q->where('feature', $feature))
            ->latest('created_at')->latest('id')->limit(8)->get(), collect(), false);
    }
}
