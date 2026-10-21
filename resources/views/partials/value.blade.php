@if ($value === true)
    <span class="pill on">on</span>
@elseif ($value === false)
    <span class="pill off">off</span>
@elseif ($value === null)
    <span class="muted">none</span>
@else
    <code>{{ \Illuminate\Support\Str::limit(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 80) }}</code>
@endif
