@extends('pennant-dashboard::layout')

@section('title', $feature->name)

@section('content')
    @php($hidden = '<input type="hidden" name="store" value="'.e($store->name).'"><input type="hidden" name="feature" value="'.e($feature->name).'">')
    <p class="small" style="margin: 0 0 8px;"><a href="{{ route('pennant-dashboard.index', ['store' => $store->name]) }}">← All features</a></p>
    <h1 class="mono">{{ $feature->name }}</h1>
    <p class="sub row">
        <span class="pill outline">{{ $feature->kind }}</span>
        @if ($feature->class && $feature->class !== $feature->name)<code>{{ $feature->class }}</code>@endif
        @if ($feature->managed)<span class="pill accent">rollout-ready</span>@endif
        @if ($feature->rolloutLabel())<span class="pill accent">{{ $feature->rolloutLabel() }}</span>@endif
        @if ($feature->stale())<span class="pill danger">stored but no longer defined</span>@endif
        @if ($feature->counts)<span class="muted">{{ $feature->counts['on'] }} on · {{ $feature->counts['off'] }} off @if ($feature->counts['other']) · {{ $feature->counts['other'] }} other values @endif</span>@endif
    </p>

    <div class="grid">
        <div class="panel" id="everyone">
            <h2>Everyone</h2>
            <div class="row">
                @foreach ([1 => 'Activate for everyone', 0 => 'Deactivate for everyone'] as $active => $label)
                    <form method="post" action="{{ route('pennant-dashboard.everyone') }}" class="inline" data-confirm="{{ $label }}: {{ $feature->name }}?">
                        @csrf {!! $hidden !!}<input type="hidden" name="active" value="{{ $active }}"><input type="hidden" name="return" value="everyone">
                        <button class="btn {{ $active ? 'primary' : '' }}" @disabled(! $canManage)>{{ $label }}</button>
                    </form>
                @endforeach
            </div>
            @if ($feature->managed)
                <p class="note small">Overwrites every stored value, clears the overrides and saves a 100% or 0% rule, so scopes resolved later follow it too.</p>
            @else
                <p class="note small">Uses Pennant's <code>activateForEveryone()</code>: every <b>stored</b> value is overwritten. Scopes that have not been resolved yet still get your resolver's default. To cover them too, opt in to rollouts (see below).</p>
            @endif
        </div>

        <div class="panel" id="rollout">
            <h2>Percentage rollout</h2>
            <form method="post" action="{{ route('pennant-dashboard.rollout') }}" class="row" data-confirm="Change the rollout of {{ $feature->name }}?">
                @csrf {!! $hidden !!}<input type="hidden" name="return" value="rollout">
                <input type="range" min="0" max="100" step="1" value="{{ $feature->percentage ?? 25 }}" data-range-for="pct" aria-label="Rollout percentage">
                <input type="number" min="0" max="100" name="percentage" id="pct" value="{{ $feature->percentage ?? 25 }}"> %
                <button class="btn primary" @disabled(! $canManage)>Save rollout</button>
            </form>
            @if ($feature->percentage !== null)
                <form method="post" action="{{ route('pennant-dashboard.rollout') }}" class="row" style="margin-top: 8px;" data-confirm="Remove the rollout and let your code decide again?">
                    @csrf {!! $hidden !!}<input type="hidden" name="clear" value="1"><input type="hidden" name="return" value="rollout">
                    <span class="muted small">Now: <b>{{ $feature->rolloutLabel() }}</b></span>
                    <button class="btn sm" @disabled(! $canManage)>Remove rollout</button>
                </form>
            @endif
            <p class="note small">Sticky: each scope gets a fixed bucket from 0 to 99 (a hash of the feature and the scope), and is in the rollout when its bucket is below the percentage. Raising the percentage only adds scopes; lowering it turns off the scopes above the new percentage. Overrides set for one scope are never changed.</p>
            @unless ($feature->managed)
                <p class="note warn small">This feature's resolver does not call <code>Rollout::decide()</code> yet, so the rollout only re-buckets values that are already stored. Opt in:</p>
                @if ($feature->kind === 'class')
<pre><code>use AmjadIqbal\PennantDashboard\Concerns\HasRollout;

class {{ class_basename((string) $feature->class) }}
{
    use HasRollout;

    public function resolve(User $user): bool
    {
        return $this->rollout($user, default: false);
    }
}</code></pre>
                @else
<pre><code>use AmjadIqbal\PennantDashboard\Rollout;

Feature::define('{{ $feature->name }}', fn (User $user) =>
    Rollout::decide('{{ $feature->name }}', $user, default: false));</code></pre>
                @endif
            @endunless
        </div>
    </div>

    <div class="panel" id="scope" style="margin-top: 18px;">
        <h2>One scope (a user, a team, any model)</h2>
        <form method="get" action="{{ route('pennant-dashboard.feature', ['feature' => $feature->name]) }}#scope" class="row">
            <input type="hidden" name="store" value="{{ $store->name }}">
            <select name="search_type" aria-label="Search in">
                @foreach ($scopeTypes as $class => $label)
                    <option value="{{ $class }}" @selected(request('search_type', $defaultType) === $class)>{{ $label }}</option>
                @endforeach
            </select>
            <input name="q" value="{{ request('q') }}" placeholder="Search by ID, name or email">
            <button class="btn">Search</button>
        </form>
        @if (count($search))
            <div class="row" style="margin-top: 8px;">
                @foreach ($search as $hit)
                    <a href="#" class="pill outline" data-pick-type="{{ request('search_type') }}" data-pick-key="{{ $hit['key'] }}">#{{ $hit['key'] }} · {{ $hit['label'] }}</a>
                @endforeach
            </div>
        @elseif (request()->filled('q'))
            <p class="muted small">No match.</p>
        @endif

        <form method="post" action="{{ route('pennant-dashboard.scope') }}" class="row" style="margin-top: 12px;">
            @csrf {!! $hidden !!}<input type="hidden" name="return" value="scope">
            @include('pennant-dashboard::partials.scope-picker', ['selectedType' => $check['type'] ?? request('scope_type', request('search_type', $defaultType)), 'selectedKey' => $check['scope_key'] ?? null])
            <button class="btn primary" name="action" value="activate" @disabled(! $canManage)>Activate</button>
            <button class="btn" name="action" value="deactivate" @disabled(! $canManage)>Deactivate</button>
            <button class="btn" name="action" value="forget" @disabled(! $canManage) title="Delete the stored value and the override; the resolver decides again">Forget</button>
            <button class="btn" formmethod="get" formaction="{{ route('pennant-dashboard.feature', ['feature' => $feature->name]) }}#scope" formnovalidate>Check value</button>
        </form>

        @if ($check)
            @if ($check['error'] && ! isset($check['key']))
                <p class="note warn">{{ $check['error'] }}</p>
            @else
                <div class="note">
                    @include('pennant-dashboard::partials.scope-label', ['scope' => $check['key'], 'labels' => [$check['key'] => $check['label']]])
                    {{ $check['source'] === 'lottery' ? 'gets' : 'sees' }}
                    @if ($check['error'])<span style="color: var(--danger)">{{ $check['error'] }}</span>@elseif ($check['source'] === 'lottery')<b>a random value</b>@else @include('pennant-dashboard::partials.value', ['value' => $check['value']]) @endif
                    <div class="small">
                        @if ($check['override'])Set for this scope from the dashboard (override). @endif
                        @switch ($check['source'])
                            @case('before') From the feature class's before() hook, which runs ahead of stored values. @break
                            @case('stored') The value Pennant has stored for this scope. @break
                            @case('lottery') A lottery: decided at random on this scope's first real check. @break
                            @case('resolver') {{ $store->canListScopes() ? 'Not stored yet: this is what your resolver returns now. Pennant stores it on the first real check.' : 'What your resolver returns now. This driver cannot show stored values, so a value stored earlier may differ.' }} @break
                        @endswitch
                        Nothing was stored by checking.
                        @if ($check['bucket'] !== null && ($feature->percentage !== null || $feature->managed))
                            Rollout bucket {{ $check['bucket'] }} of 100{{ $feature->percentage !== null ? ' (in a '.$feature->percentage.'% rollout: '.($check['bucket'] < $feature->percentage ? 'in' : 'out').')' : '' }}.
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if ($rows !== null)
        <div class="panel" id="stored">
            <div class="row" style="justify-content: space-between; margin-bottom: 8px;">
                <h2 style="margin: 0;">Stored values <span class="muted small">({{ $rows->total() }})</span></h2>
                <form method="get" action="{{ route('pennant-dashboard.feature', ['feature' => $feature->name]) }}#stored" class="row">
                    <input type="hidden" name="store" value="{{ $store->name }}">
                    <select name="type" aria-label="Scope type">
                        <option value="">All scope types</option>
                        @foreach ($scopeTypes as $class => $label)
                            <option value="{{ $class }}" @selected(request('type') === $class)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="value" aria-label="Value">
                        <option value="">On and off</option>
                        <option value="on" @selected(request('value') === 'on')>On</option>
                        <option value="off" @selected(request('value') === 'off')>Off</option>
                    </select>
                    <button class="btn sm">Filter</button>
                </form>
            </div>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Scope</th><th>Value</th><th class="hide-sm">Source</th><th class="hide-sm">Updated</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php($scope = (string) $row->scope)
                    <tr>
                        <td>@include('pennant-dashboard::partials.scope-label', ['scope' => $scope])</td>
                        <td>@include('pennant-dashboard::partials.value', ['value' => json_decode((string) $row->value, true)])</td>
                        <td class="hide-sm small">
                            @if (array_key_exists($scope, $overrides))<span class="pill accent">override</span>
                            @elseif ($feature->percentage !== null)<span class="muted">rollout · bucket {{ \AmjadIqbal\PennantDashboard\Rollout::bucket($feature->name, $scope) }}</span>
                            @else<span class="muted">resolved</span>@endif
                        </td>
                        <td class="hide-sm small muted">{{ $row->updated_at ? \Illuminate\Support\Carbon::parse($row->updated_at)->diffForHumans() : '' }}</td>
                        <td style="white-space: nowrap; text-align: right;">
                            @if ($canManage)
                                <form method="post" action="{{ route('pennant-dashboard.scope') }}" class="inline">
                                    @csrf {!! $hidden !!}<input type="hidden" name="scope_type" value="raw"><input type="hidden" name="scope_key" value="{{ $scope }}"><input type="hidden" name="return" value="stored">
                                    @if (json_decode((string) $row->value, true) === true)
                                        <button class="btn sm" name="action" value="deactivate">Turn off</button>
                                    @else
                                        <button class="btn sm" name="action" value="activate">Turn on</button>
                                    @endif
                                    <button class="btn sm" name="action" value="forget" title="Forget: the resolver decides again">Forget</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Nothing stored{{ request()->hasAny(['type', 'value']) ? ' for this filter' : ' yet: Pennant stores a value the first time a scope is checked' }}.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            @if ($rows->hasPages())
                <div class="pager">
                    @if ($rows->previousPageUrl())<a class="btn sm" href="{{ $rows->previousPageUrl() }}#stored">← Previous</a>@endif
                    <span class="muted small">Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
                    @if ($rows->nextPageUrl())<a class="btn sm" href="{{ $rows->nextPageUrl() }}#stored">Next →</a>@endif
                </div>
            @endif
        </div>
    @else
        <div class="panel" id="stored">
            <h2>Overrides set here <span class="muted small">({{ count($overrides) }})</span></h2>
            <p class="muted small">The {{ $store->driverName() }} driver does not expose its stored scopes, so this lists the overrides saved by the dashboard.</p>
            <table>
                @forelse ($overrides as $scope => $value)
                    <tr>
                        <td>@include('pennant-dashboard::partials.scope-label', ['scope' => (string) $scope])</td>
                        <td>@include('pennant-dashboard::partials.value', ['value' => $value])</td>
                        <td style="text-align: right;">
                            @if ($canManage)
                                <form method="post" action="{{ route('pennant-dashboard.scope') }}" class="inline">
                                    @csrf {!! $hidden !!}<input type="hidden" name="scope_type" value="raw"><input type="hidden" name="scope_key" value="{{ $scope }}"><input type="hidden" name="return" value="stored">
                                    <button class="btn sm" name="action" value="forget">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="muted">No overrides.</td></tr>
                @endforelse
            </table>
        </div>
    @endif

    <div class="grid">
        <div class="panel">
            <h2>Recent changes</h2>
            @include('pennant-dashboard::partials.audit-table', ['entries' => $recent, 'showFeature' => false])
            <p class="small"><a href="{{ route('pennant-dashboard.audit', ['store' => $store->name, 'feature' => $feature->name]) }}">Full history →</a></p>
        </div>
        <div class="panel" id="purge">
            <h2>Purge</h2>
            <p class="muted small">Deletes every stored value of this feature (Pennant's <code>Feature::purge()</code>) and the overrides set here. Every scope resolves from your code again on its next check.</p>
            <form method="post" action="{{ route('pennant-dashboard.purge') }}" class="row" data-confirm="Purge every stored value of {{ $feature->name }}?">
                @csrf {!! $hidden !!}<input type="hidden" name="return" value="top">
                <label class="check"><input type="checkbox" name="with_rule" value="1"> also remove the rollout rule</label>
                <button class="btn danger" @disabled(! $canManage)>Purge {{ $feature->name }}</button>
            </form>
        </div>
    </div>
@endsection
