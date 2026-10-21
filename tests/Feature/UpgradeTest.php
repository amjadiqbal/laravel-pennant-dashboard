<?php

use AmjadIqbal\PennantDashboard\Models\Rule;
use AmjadIqbal\PennantDashboard\Rollout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
    $this->storeMigration = require dirname(__DIR__, 2).'/database/migrations/2026_10_22_000000_add_store_to_pennant_dashboard_tables.php';
});

/** Put the tables back the way 1.0.0 left them: no store column. */
function downgradeTo100(object $migration): void
{
    $migration->down();
    expect(Schema::hasColumn('pennant_dashboard_rules', 'store'))->toBeFalse();
}

it('keeps 1.0.0 rollouts working before the migration, and warns once in the log', function () {
    downgradeTo100($this->storeMigration);
    DB::table('pennant_dashboard_rules')->insert(['feature' => 'new-checkout', 'percentage' => 100, 'created_at' => now(), 'updated_at' => now()]);
    Log::spy();

    $this->nextRequest();
    expect(Rollout::decide('new-checkout', $this->user(1)))->toBeTrue();
    $this->nextRequest();
    expect(Rollout::decide('new-checkout', $this->user(2)))->toBeTrue();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message) => str_contains($message, 'Run php artisan migrate'));
});

it('shows a banner and refuses changes with a clear message instead of a 500', function () {
    downgradeTo100($this->storeMigration);

    $this->get('/pennant')->assertOk()
        ->assertSee('Run the migrations.')
        ->assertSee('from 1.0.0');

    $this->from('/pennant/features/new-checkout')
        ->post('/pennant/actions/rollout', ['feature' => 'new-checkout', 'percentage' => 50])
        ->assertRedirect('/pennant/features/new-checkout')
        ->assertSessionHasErrors('schema');

    expect(DB::table('pennant_dashboard_rules')->count())->toBe(0);
});

it('tells artisan about that the migration is pending', function () {
    downgradeTo100($this->storeMigration);

    $this->artisan('about')->expectsOutputToContain('outdated: run php artisan migrate')->assertSuccessful();
});

it('mentions publishing when run_migrations is off', function () {
    downgradeTo100($this->storeMigration);
    config(['pennant-dashboard.run_migrations' => false]);

    $this->get('/pennant')->assertSee('--tag=pennant-dashboard-migrations');
});

it('works normally again once migrated', function () {
    downgradeTo100($this->storeMigration);
    DB::table('pennant_dashboard_rules')->insert(['feature' => 'new-checkout', 'percentage' => 100, 'created_at' => now(), 'updated_at' => now()]);
    $this->storeMigration->up();

    expect(Rule::query()->value('store'))->toBe('database');

    $this->get('/pennant')->assertOk()->assertDontSee('Run the migrations.');
    $this->post('/pennant/actions/rollout', ['feature' => 'new-checkout', 'percentage' => 50])->assertSessionHasNoErrors();
    expect(Rule::query()->value('percentage'))->toBe(50);
});

it('reports missing tables the same way', function () {
    Schema::drop('pennant_dashboard_rules');
    Log::spy();

    expect(Rollout::decide('new-checkout', $this->user(1), true))->toBeTrue();
    $this->get('/pennant')->assertOk()->assertSee('tables are missing');
    $this->post('/pennant/actions/everyone', ['feature' => 'dark-mode', 'active' => 1])->assertSessionHasErrors('schema');

    Log::shouldHaveReceived('warning')->once();
});
