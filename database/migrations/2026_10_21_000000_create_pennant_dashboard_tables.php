<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('pennant-dashboard.connection');
    }

    public function up(): void
    {
        Schema::create(config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules'), function (Blueprint $table) {
            $table->id();
            $table->string('feature')->unique();
            $table->unsignedTinyInteger('percentage');
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create(config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides'), function (Blueprint $table) {
            $table->id();
            $table->string('feature');
            $table->string('scope');
            $table->text('value');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['feature', 'scope']);
        });

        Schema::create(config('pennant-dashboard.tables.audit', 'pennant_dashboard_audit'), function (Blueprint $table) {
            $table->id();
            $table->string('store')->nullable();
            $table->string('feature')->nullable()->index();
            $table->string('action', 40);
            $table->string('scope')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedInteger('affected')->nullable();
            $table->string('source', 20);
            $table->string('actor')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('pennant-dashboard.tables.audit', 'pennant_dashboard_audit'));
        Schema::dropIfExists(config('pennant-dashboard.tables.overrides', 'pennant_dashboard_overrides'));
        Schema::dropIfExists(config('pennant-dashboard.tables.rules', 'pennant_dashboard_rules'));
    }
};
