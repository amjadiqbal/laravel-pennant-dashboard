<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Gate::define('viewPennantDashboard', fn ($user = null) => true);
    Route::get('/experiments', fn () => 'ok')->name('pennant-experiments.index');
    Route::get('/experiments/{id}', fn () => 'ok')->name('pennant-experiments.show');
    app('router')->getRoutes()->refreshNameLookups();
});

it('renders an extra tab from config and skips invalid entries', function () {
    config(['pennant-dashboard.tabs' => [
        ['label' => 'Experiments', 'route' => 'pennant-experiments.index', 'active' => 'pennant-experiments.*'],
        ['label' => 'Ghost', 'route' => 'no-such.route'],
        ['label' => 'Needs a parameter', 'route' => 'pennant-experiments.show'],
        ['route' => 'pennant-experiments.index'],
        'not an array',
    ]]);

    $html = $this->get('/pennant')->assertOk()->getContent();

    expect($html)->toContain('>Experiments</a>')
        ->toContain('href="'.url('/experiments').'"')
        ->not->toContain('Ghost')
        ->not->toContain('Needs a parameter');
});

it('shows no extra tabs by default', function () {
    $this->get('/pennant')->assertOk()->assertDontSee('Experiments');
});
