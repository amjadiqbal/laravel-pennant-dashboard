<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Are the package's tables migrated, and to the current version? The dashboard shows a banner and
 * refuses changes until they are; Rollout::decide() keeps working and logs a warning once.
 */
final class SchemaStatus
{
    public const OK = 'ok';

    public const MISSING = 'missing';

    public const OUTDATED = 'outdated';

    /** @var array<string, true> */
    private static array $warned = [];

    public static function check(): string
    {
        try {
            $schema = Schema::connection(config('pennant-dashboard.connection'));
            $rules = (string) config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules');
            $overrides = (string) config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides');
            $audit = (string) config('pennant-dashboard.tables.audit', 'pennant_dashboard_audit');

            if (! $schema->hasTable($rules) || ! $schema->hasTable($overrides) || ! $schema->hasTable($audit)) {
                return self::MISSING;
            }

            return $schema->hasColumn($rules, 'store') && $schema->hasColumn($overrides, 'store') ? self::OK : self::OUTDATED;
        } catch (Throwable) {
            return self::MISSING;
        }
    }

    public static function message(string $status): ?string
    {
        $publish = config('pennant-dashboard.run_migrations', true)
            ? ''
            : ' You set run_migrations to false, so publish the new migration first: php artisan vendor:publish --tag=pennant-dashboard-migrations.';

        return match ($status) {
            self::MISSING => 'The Pennant Dashboard tables are missing. Run php artisan migrate.'.$publish,
            self::OUTDATED => 'The Pennant Dashboard tables are from 1.0.0. Run php artisan migrate to add the store column.'.$publish
                .' Until then rollouts and overrides apply to every store, and the dashboard cannot save changes.',
            default => null,
        };
    }

    /** Log the problem once per process, so a busy app does not flood the log. */
    public static function warnOnce(string $status): void
    {
        if ($status === self::OK || isset(self::$warned[$status])) {
            return;
        }

        self::$warned[$status] = true;

        try {
            Log::warning('[pennant-dashboard] '.self::message($status));
        } catch (Throwable) {
            // Logging must never break feature resolution.
        }
    }

    public static function resetWarnings(): void
    {
        self::$warned = [];
    }
}
