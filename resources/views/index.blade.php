@extends('pennant-dashboard::layout')

@section('title', 'Features')

@section('content')
    @php($stale = array_filter($features, fn ($f) => $f->stale()))
    <h1>Feature flags</h1>
    <p class="sub">Every Pennant feature in the <b>{{ $store->name }}</b> store: defined in code, stored by the driver, or changed here.</p>

    <div class="stats">
        <div class="stat"><b>{{ count($features) }}</b><span>features</span></div>
        <div class="stat"><b>{{ count(array_filter($features, fn ($f) => $f->percentage !== null)) }}</b><span>with a rollout rule</span></div>
        <div class="stat"><b>{{ count(array_filter($features, fn ($f) => $f->managed)) }}</b><span>call Rollout::decide()</span></div>
        <div class="stat"><b>{{ count($stale) }}</b><span>stored but no longer defined</span></div>
    </div>

    <div class="panel">
        <div class="row" style="justify-content: space-between; margin-bottom: 8px;">
            <h2 style="margin: 0;">Features</h2>
            @if ($canManage && count($stale) > 0)
                <form method="post" action="{{ route('pennant-dashboard.purge-stale') }}" class="inline" data-confirm="Purge every stored value of {{ count($stale) }} features that are no longer defined?">
                    @csrf <input type="hidden" name="store" value="{{ $store->name }}">
                    <button class="btn danger sm">Purge {{ count($stale) }} stale</button>
                </form>
            @endif
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Feature</th><th>Kind</th><th>State</th>@if ($store->canListScopes())<th class="hide-sm">Stored values</th>@endif<th class="hide-sm">Overrides</th></tr></thead>
            <tbody>
            @forelse ($features as $feature)
                <tr>
                    <td>
                        <a class="mono" href="{{ route('pennant-dashboard.feature', ['feature' => $feature->name, 'store' => $store->name]) }}"><b>{{ $feature->name }}</b></a>
                        @if ($feature->class && $feature->class !== $feature->name)<div class="muted small mono">{{ $feature->class }}</div>@endif
                    </td>
                    <td>
                        <span class="pill outline">{{ $feature->kind }}</span>
                        @if ($feature->managed)<span class="pill accent" title="The resolver calls Rollout::decide()">rollout-ready</span>@endif
                        @if ($feature->stale())<span class="pill danger">stale</span>@endif
                    </td>
                    <td>
                        @if ($feature->rolloutLabel())
                            <span class="pill accent">{{ $feature->rolloutLabel() }}</span>
                        @elseif ($feature->counts)
                            <span class="muted small">resolved per scope</span>
                        @else
                            <span class="muted small">from code</span>
                        @endif
                    </td>
                    @if ($store->canListScopes())
                        <td class="hide-sm">
                            @if ($feature->counts)
                                @php($c = $feature->counts)
                                <div class="row" style="flex-wrap: nowrap;">
                                    <div class="bar-track" title="{{ $c['on'] }} on of {{ $c['total'] }}"><i style="width: {{ $c['total'] ? round(100 * $c['on'] / $c['total'], 1) : 0 }}%"></i></div>
                                    <span class="small"><b>{{ $c['on'] }}</b> on · {{ $c['off'] }} off @if ($c['other'])· {{ $c['other'] }} other @endif</span>
                                </div>
                            @else
                                <span class="muted small">none yet</span>
                            @endif
                        </td>
                    @endif
                    <td class="hide-sm">{{ $feature->overrides ?: '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No features yet. Define one with <code>Feature::define('new-checkout', fn (User $user) => false)</code> in a service provider, or add a class to <code>app/Features</code>.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="grid">
        <div class="panel">
            <h2>What does one scope see?</h2>
            <form method="get" class="row">
                <input type="hidden" name="store" value="{{ $store->name }}">
                @include('pennant-dashboard::partials.scope-picker')
                <button class="btn primary">Check</button>
            </form>
            @if ($inspection)
                @if ($inspection['error'])
                    <p class="note warn">{{ $inspection['error'] }}</p>
                @else
                    <p class="small muted" style="margin: 12px 0 4px;">
                        @include('pennant-dashboard::partials.scope-label', ['scope' => $inspection['key'], 'labels' => [$inspection['key'] => $inspection['label']]])
                    </p>
                    <table>
                        @forelse ($inspection['values'] as $name => $result)
                            <tr>
                                <td class="mono"><a href="{{ route('pennant-dashboard.feature', ['feature' => $name, 'store' => $store->name]) }}">{{ $name }}</a></td>
                                <td>@if ($result['error'])<span class="small" style="color: var(--danger)">{{ $result['error'] }}</span>@elseif ($result['source'] === 'lottery')<span class="muted small">lottery, decided on first check</span>@else @include('pennant-dashboard::partials.value', ['value' => $result['value']]) @if ($result['source'] === 'resolver' && $store->canListScopes())<span class="muted small"> not stored</span>@endif @endif</td>
                            </tr>
                        @empty
                            <tr><td class="muted">No defined feature accepts this scope type.</td></tr>
                        @endforelse
                    </table>
                    <p class="note small">Read-only preview: stored values where they exist, otherwise what your resolver returns now. Nothing is stored by checking; <i>not stored</i> marks values Pennant has not stored yet.</p>
                @endif
            @endif
        </div>

        <div class="panel">
            <h2>What the <code>{{ $store->driverName() }}</code> driver supports</h2>
            <table>
                @foreach ($capabilities as $cap)
                    <tr>
                        <td>{{ $cap['label'] }}</td>
                        <td>@if ($cap['ok'] === true)<span class="yes">yes</span>@elseif ($cap['ok'] === false)<span class="no">no</span>@else<span class="muted">unknown</span>@endif</td>
                        <td class="muted small hide-sm">{{ $cap['note'] }}</td>
                    </tr>
                @endforeach
            </table>
            @if ($store->isArray())
                <p class="note warn small">The array driver keeps values for one request only. Per-scope changes stick only for features that call <code>Rollout::decide()</code>, which reads the overrides and rollout saved by this dashboard.</p>
            @elseif (! $store->isDatabase())
                <p class="note small">Custom driver: changes go through Pennant's Driver contract, so they work, but the dashboard cannot list the scopes it has stored.</p>
            @endif
        </div>
    </div>

    <div class="panel">
        <div class="row" style="justify-content: space-between;"><h2 style="margin: 0;">Recent changes</h2><a href="{{ route('pennant-dashboard.audit', ['store' => $store->name]) }}">Full audit log →</a></div>
        @include('pennant-dashboard::partials.audit-table', ['entries' => $recent, 'showFeature' => true, 'labels' => []])
    </div>
@endsection
