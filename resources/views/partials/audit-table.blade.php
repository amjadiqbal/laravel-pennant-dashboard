<div class="table-wrap">
<table>
    <thead><tr><th>When</th><th>Who</th><th>What</th>@if ($showFeature)<th>Feature</th>@endif<th>Scope</th><th>Change</th></tr></thead>
    <tbody>
    @forelse ($entries as $entry)
        <tr>
            <td class="small" title="{{ $entry->created_at }}">{{ $entry->created_at?->diffForHumans() }}</td>
            <td class="small">
                {{ $entry->actor ?? ($entry->source === 'console' ? 'artisan / console' : ($entry->source === 'code' ? 'app code' : 'guest')) }}
                <span class="pill outline">{{ $entry->source }}</span>
                @if ($entry->ip)<div class="muted small">{{ $entry->ip }}</div>@endif
            </td>
            <td><span class="pill accent">{{ str_replace('_', ' ', $entry->action) }}</span></td>
            @if ($showFeature)
                <td class="mono">@if ($entry->feature)<a href="{{ route('pennant-dashboard.feature', ['feature' => $entry->feature, 'store' => $store->name]) }}">{{ $entry->feature }}</a>@else <span class="muted">all</span>@endif</td>
            @endif
            <td class="small">@if ($entry->scope)@include('pennant-dashboard::partials.scope-label', ['scope' => $entry->scope])@else<span class="muted">-</span>@endif</td>
            <td class="small">
                @if ($entry->old_value !== null)@include('pennant-dashboard::partials.value', ['value' => json_decode($entry->old_value, true)]) → @endif
                @if ($entry->new_value !== null)@include('pennant-dashboard::partials.value', ['value' => json_decode($entry->new_value, true)])@endif
                @if (in_array($entry->action, ['rollout', 'rollout_cleared'], true))<span class="muted">%</span>@endif
                @if ($entry->affected !== null)<span class="muted"> · {{ $entry->affected }} stored</span>@endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">No changes recorded yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
