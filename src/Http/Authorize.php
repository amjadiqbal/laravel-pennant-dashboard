<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Http;

use AmjadIqbal\PennantDashboard\Support\SchemaStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::check('viewPennantDashboard', [$request]), 403);

        if (! $request->isMethodSafe()) {
            abort_if((bool) config('pennant-dashboard.read_only'), 403, 'The Pennant dashboard is read-only.');
            abort_unless(Gate::check('managePennantDashboard', [$request]), 403);

            // Un-migrated tables would fail mid-write with a 500: refuse up front and say why.
            $status = SchemaStatus::check();
            if ($status !== SchemaStatus::OK) {
                return redirect()->back()->withErrors(['schema' => SchemaStatus::message($status)]);
            }
        }

        return $next($request);
    }

    public static function canManage(): bool
    {
        return ! config('pennant-dashboard.read_only') && Gate::check('managePennantDashboard', [request()]);
    }
}
