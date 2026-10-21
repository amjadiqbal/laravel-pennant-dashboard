<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Http\Controllers;

use AmjadIqbal\PennantDashboard\FeatureActions;
use AmjadIqbal\PennantDashboard\Support\FeatureCatalog;
use AmjadIqbal\PennantDashboard\Support\FeatureInfo;
use AmjadIqbal\PennantDashboard\Support\ScopeResolver;
use AmjadIqbal\PennantDashboard\Support\StoreInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ActionController
{
    use Concerns;

    public function __construct(private readonly FeatureActions $actions) {}

    public function scope(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'feature' => ['required', 'string'],
            'action' => ['required', 'in:activate,deactivate,forget'],
            'scope_type' => ['required', 'string'],
            'scope_key' => ['nullable', 'max:255'],
        ]);

        [$store, $feature] = $this->feature($request);
        $scope = (new ScopeResolver($store))->fromInput($data['scope_type'], isset($data['scope_key']) ? (string) $data['scope_key'] : null);

        if ($data['action'] === 'forget') {
            $this->actions->forgetForScope($store, $feature->name, $scope);
        } else {
            $this->actions->setForScope($store, $feature->name, $scope, $data['action'] === 'activate');
        }

        $note = $store->persists() === false ? ' The array driver forgets it after this request; the override applies because the feature calls Rollout::decide().' : '';
        if ($store->persists() === false && ! $feature->managed) {
            $note = ' The array driver forgets it after this request, and this feature does not call Rollout::decide(), so it will not stick.';
        }

        return $this->back($request, match ($data['action']) {
            'activate' => 'Activated',
            'deactivate' => 'Deactivated',
            default => 'Forgot the stored value of',
        }." {$feature->name} for this scope.".$note);
    }

    public function everyone(Request $request): RedirectResponse
    {
        $request->validate(['feature' => ['required', 'string'], 'active' => ['required', 'boolean']]);
        [$store, $feature] = $this->feature($request);
        $active = $request->boolean('active');

        $count = $this->actions->setForEveryone($store, $feature->name, $active, $feature->managed);
        $message = ($active ? 'Activated ' : 'Deactivated ')."{$feature->name} for everyone".($store->canListScopes() ? " ({$count} stored values)." : '.');

        if (! $feature->managed) {
            $message .= ' Scopes that have not been resolved yet still get your resolver\'s default; call Rollout::decide() in the resolver to make this cover them too.';
        }

        return $this->back($request, $message);
    }

    public function rollout(Request $request): RedirectResponse
    {
        $request->validate(['feature' => ['required', 'string'], 'percentage' => ['nullable', 'integer', 'between:0,100'], 'clear' => ['nullable', 'boolean']]);
        [$store, $feature] = $this->feature($request);

        if ($request->boolean('clear')) {
            $count = $this->actions->clearRollout($store, $feature->name);

            return $this->back($request, "Removed the rollout for {$feature->name}. {$count} stored values were forgotten and will resolve from your code again.");
        }

        abort_unless($request->filled('percentage'), 422, 'Enter a percentage.');
        $percentage = (int) $request->input('percentage');
        $changed = $this->actions->setRollout($store, $feature->name, $percentage);

        $message = "{$feature->name} is now rolled out to {$percentage}%.";
        $message .= $store->canListScopes() ? " {$changed} stored values changed; overrides were kept." : ' This store cannot list its stored values, so values already stored keep their state.';
        if (! $feature->managed) {
            $message .= ' This feature does not call Rollout::decide() yet, so scopes resolved from now on ignore the rollout.';
        }

        return $this->back($request, $message);
    }

    public function purge(Request $request): RedirectResponse
    {
        $request->validate(['feature' => ['required', 'string'], 'with_rule' => ['nullable', 'boolean']]);
        [$store, $feature] = $this->feature($request);

        $count = $this->actions->purge($store, $feature->name, $request->boolean('with_rule'));

        return $this->back($request, "Purged {$feature->name}".($store->canListScopes() ? " ({$count} stored values)" : '').'. Every scope resolves from your code again'.($request->boolean('with_rule') ? ' and the rollout was removed.' : '.'));
    }

    public function purgeStale(Request $request): RedirectResponse
    {
        $store = $this->store($request);
        $purged = $this->actions->purgeStale($store);

        return redirect()->route('pennant-dashboard.index', ['store' => $store->name])
            ->with('pennant-dashboard.status', $purged === [] ? 'No stale features to purge.' : 'Purged '.count($purged).' stale features: '.implode(', ', $purged).'.');
    }

    /** @return array{0: StoreInspector, 1: FeatureInfo} */
    private function feature(Request $request): array
    {
        $store = $this->store($request);
        $feature = (new FeatureCatalog($store))->find((string) $request->input('feature'));

        abort_if($feature === null, 404, 'Unknown feature.');

        return [$store, $feature];
    }

    private function back(Request $request, string $message): RedirectResponse
    {
        return redirect()->to(route('pennant-dashboard.feature', ['feature' => (string) $request->input('feature'), 'store' => $request->input('store')]).'#'.(string) $request->input('return', 'top'))
            ->with('pennant-dashboard.status', $message);
    }
}
