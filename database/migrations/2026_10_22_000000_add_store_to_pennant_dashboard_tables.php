<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollout rules and overrides belong to one Pennant store. Rows written by 1.0.0 had no store;
 * they are assigned to the default store, which is where Rollout::decide() read them anyway.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('pennant-dashboard.connection');
    }

    public function up(): void
    {
        $store = (string) (config('pennant.default') ?? 'database');

        Schema::table(config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules'), function (Blueprint $table) use ($store) {
            $table->string('store')->default($store)->after('id');
        });
        Schema::table(config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules'), function (Blueprint $table) {
            $table->dropUnique(['feature']);
            $table->unique(['store', 'feature']);
        });

        Schema::table(config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides'), function (Blueprint $table) use ($store) {
            $table->string('store')->default($store)->after('id');
        });
        Schema::table(config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides'), function (Blueprint $table) {
            $table->dropUnique(['feature', 'scope']);
            $table->unique(['store', 'feature', 'scope']);
        });
    }

    public function down(): void
    {
        $rules = config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules');
        $overrides = config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides');

        if (! Schema::hasTable($rules) || ! Schema::hasTable($overrides) || ! Schema::hasColumn($rules, 'store')) {
            return;
        }

        // 1.0.0 had one rule per feature: keep the default store's rows, drop the others.
        $store = (string) (config('pennant.default') ?? 'database');
        DB::connection($this->getConnection())->table($rules)->where('store', '!=', $store)->delete();
        DB::connection($this->getConnection())->table($overrides)->where('store', '!=', $store)->delete();

        Schema::table(config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides'), function (Blueprint $table) {
            $table->dropUnique(['store', 'feature', 'scope']);
            $table->unique(['feature', 'scope']);
            $table->dropColumn('store');
        });

        Schema::table(config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules'), function (Blueprint $table) {
            $table->dropUnique(['store', 'feature']);
            $table->unique(['feature']);
            $table->dropColumn('store');
        });
    }
};
