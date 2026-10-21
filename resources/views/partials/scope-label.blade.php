@php($info = $labels[$scope] ?? null)
@if ($info)
    <span class="pill outline">{{ $info['type'] }}</span>
    @if ($info['key'] !== '')<span class="mono">#{{ $info['key'] }}</span>@endif
    @if ($info['label'])<span class="muted"> · {{ $info['label'] }}</span>@endif
@else
    <code>{{ $scope }}</code>
@endif
