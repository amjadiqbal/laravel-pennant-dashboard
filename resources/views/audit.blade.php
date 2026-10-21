@extends('pennant-dashboard::layout')

@section('title', 'Audit log')

@section('content')
    <h1>Audit log</h1>
    <p class="sub">Who changed what. Dashboard changes record the signed-in user and IP; changes made by your own code or artisan are recorded too. @if (! config('pennant-dashboard.audit.code_changes', true)) (Code changes are turned off in config.) @endif</p>

    <form method="get" class="row" style="margin-bottom: 14px;">
        <input type="hidden" name="store" value="{{ $store->name }}">
        <input name="feature" value="{{ request('feature') }}" placeholder="Feature name">
        <select name="source">
            <option value="">All sources</option>
            @foreach (['dashboard', 'code', 'console'] as $source)
                <option value="{{ $source }}" @selected(request('source') === $source)>{{ $source }}</option>
            @endforeach
        </select>
        <button class="btn">Filter</button>
    </form>

    <div class="panel">
        @if ($entries === null)
            <p class="note warn">The audit table does not exist yet. Run <code>php artisan migrate</code>.</p>
        @else
            @include('pennant-dashboard::partials.audit-table', ['entries' => $entries->items(), 'showFeature' => true])
            @if ($entries->hasPages())
                <div class="pager">
                    @if ($entries->previousPageUrl())<a class="btn sm" href="{{ $entries->previousPageUrl() }}">← Newer</a>@endif
                    <span class="muted small">Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>
                    @if ($entries->nextPageUrl())<a class="btn sm" href="{{ $entries->nextPageUrl() }}">Older →</a>@endif
                </div>
            @endif
        @endif
    </div>
@endsection
