<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Support;

use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Extra nav tabs from config('pennant-dashboard.tabs'), added by the app or by other packages:
 * ['label' => 'Experiments', 'route' => 'pennant-experiments.index', 'active' => 'pennant-experiments.*'].
 * Invalid entries, unknown routes and routes that need parameters are skipped, never thrown.
 */
final class ExtraTabs
{
    /** @return list<array{label: string, url: string, active: bool}> */
    public static function all(): array
    {
        $tabs = [];

        foreach ((array) config('pennant-dashboard.tabs', []) as $tab) {
            if (! is_array($tab) || ! is_string($tab['label'] ?? null) || ! is_string($tab['route'] ?? null) || ! Route::has($tab['route'])) {
                continue;
            }

            try {
                $url = route($tab['route']);
            } catch (Throwable) {
                continue;
            }

            $active = is_string($tab['active'] ?? null) ? $tab['active'] : $tab['route'];

            $tabs[] = ['label' => $tab['label'], 'url' => $url, 'active' => request()->routeIs($active)];
        }

        return $tabs;
    }
}
