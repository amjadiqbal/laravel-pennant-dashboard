<?php

use AmjadIqbal\PennantDashboard\Http\Controllers\ActionController;
use AmjadIqbal\PennantDashboard\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('pennant-dashboard.index');
Route::get('/audit', [DashboardController::class, 'audit'])->name('pennant-dashboard.audit');
Route::get('/features/{feature}', [DashboardController::class, 'show'])->where('feature', '.+')->name('pennant-dashboard.feature');

Route::post('/actions/scope', [ActionController::class, 'scope'])->name('pennant-dashboard.scope');
Route::post('/actions/everyone', [ActionController::class, 'everyone'])->name('pennant-dashboard.everyone');
Route::post('/actions/rollout', [ActionController::class, 'rollout'])->name('pennant-dashboard.rollout');
Route::post('/actions/purge', [ActionController::class, 'purge'])->name('pennant-dashboard.purge');
Route::post('/actions/purge-stale', [ActionController::class, 'purgeStale'])->name('pennant-dashboard.purge-stale');
