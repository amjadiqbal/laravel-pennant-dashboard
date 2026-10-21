<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Features') · Pennant Dashboard</title>
    <style>
        :root {
            --bg: #F8FAFC; --panel: #FFFFFF; --border: #E2E8F0; --text: #0F172A; --muted: #64748B;
            --accent: #0891B2; --accent-text: #0E7490; --accent-soft: #ECFEFF; --on-text: #15803D; --on-bg: #F0FDF4;
            --off-text: #475569; --off-bg: #F1F5F9; --danger: #DC2626; --code-bg: #F1F5F9; --shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0B1220; --panel: #111A2E; --border: #24324F; --text: #E2E8F0; --muted: #94A3B8;
                --accent: #22D3EE; --accent-text: #67E8F9; --accent-soft: #0E2A36; --on-text: #4ADE80; --on-bg: #0F2A1C;
                --off-text: #CBD5E1; --off-bg: #1E293B; --danger: #F87171; --code-bg: #0B1220; --shadow: none;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        a { color: var(--accent-text); text-decoration: none; }
        a:hover { text-decoration: underline; }
        code, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; }
        code { background: var(--code-bg); border: 1px solid var(--border); border-radius: 6px; padding: 1px 5px; overflow-wrap: anywhere; }
        pre { background: var(--code-bg); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; overflow-x: auto; margin: 8px 0 0; }
        pre code { background: none; border: 0; padding: 0; }
        header.top { background: var(--panel); border-bottom: 1px solid var(--border); }
        .wrap { max-width: 1160px; margin: 0 auto; padding: 0 16px; }
        .bar { display: flex; align-items: center; gap: 16px; min-height: 60px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 16px; color: var(--text); }
        .brand:hover { text-decoration: none; }
        nav.tabs { display: flex; gap: 4px; }
        nav.tabs a { padding: 6px 12px; border-radius: 8px; color: var(--muted); font-weight: 600; }
        nav.tabs a.active { background: var(--accent-soft); color: var(--accent-text); }
        .spacer { flex: 1; }
        main { padding: 24px 0 48px; }
        h1, h1.mono { font-size: 22px; margin: 0 0 4px; overflow-wrap: anywhere; }
        h2 { font-size: 15px; margin: 0 0 12px; }
        .sub { color: var(--muted); margin: 0 0 20px; }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: 14px; padding: 18px; box-shadow: var(--shadow); margin-bottom: 18px; }
        .grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .grid > .panel { margin-bottom: 0; }
        .stats { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 18px; }
        .stat { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
        .stat b { display: block; font-size: 22px; }
        .stat span { color: var(--muted); font-size: 12.5px; }
        .table-wrap { overflow-x: auto; margin: 0 -18px; padding: 0 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { color: var(--muted); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
        tr:last-child td { border-bottom: 0; }
        .pill { display: inline-block; padding: 1px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
        .pill.on { background: var(--on-bg); color: var(--on-text); }
        .pill.off { background: var(--off-bg); color: var(--off-text); }
        .pill.accent { background: var(--accent-soft); color: var(--accent-text); }
        .pill.outline { border-color: var(--border); color: var(--muted); }
        .pill.danger { border-color: var(--danger); color: var(--danger); }
        .bar-track { display: flex; height: 8px; border-radius: 99px; overflow: hidden; background: var(--off-bg); min-width: 90px; }
        .bar-track i { display: block; background: var(--accent); }
        .muted { color: var(--muted); }
        .small { font-size: 12.5px; }
        .row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        form.inline { display: inline; margin: 0; }
        input, select { font: inherit; color: var(--text); background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 7px 10px; min-width: 0; }
        input:focus, select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        input[type=number] { width: 90px; }
        input[type=range] { accent-color: var(--accent); padding: 0; border: 0; background: none; width: 180px; }
        label.check { display: inline-flex; gap: 6px; align-items: center; color: var(--muted); }
        .btn { display: inline-flex; align-items: center; gap: 6px; font: inherit; font-weight: 600; cursor: pointer; border-radius: 8px; padding: 7px 12px; border: 1px solid var(--border); background: var(--panel); color: var(--text); }
        .btn:hover { border-color: var(--accent); text-decoration: none; }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: #FFFFFF; }
        @media (prefers-color-scheme: dark) { .btn.primary { color: #0B1220; } }
        .btn.danger { color: var(--danger); border-color: var(--danger); background: transparent; }
        .btn.sm { padding: 3px 9px; font-size: 12.5px; }
        .btn[disabled] { opacity: .5; cursor: not-allowed; }
        .flash { position: fixed; z-index: 10; right: 16px; bottom: 16px; max-width: min(520px, calc(100vw - 32px)); border: 1px solid var(--accent); background: var(--panel); color: var(--text); border-radius: 12px; padding: 12px 40px 12px 14px; box-shadow: 0 8px 24px rgba(15, 23, 42, .18); }
        .flash.error { border-color: var(--danger); }
        .flash button { position: absolute; top: 6px; right: 8px; border: 0; background: none; color: var(--muted); font-size: 18px; cursor: pointer; }
        .note { border-left: 3px solid var(--accent); padding: 4px 0 4px 12px; color: var(--muted); margin: 10px 0 0; }
        .note.warn { border-left-color: var(--danger); }
        .yes { color: var(--on-text); font-weight: 700; }
        .no { color: var(--muted); }
        .pager { display: flex; gap: 10px; align-items: center; margin-top: 12px; }
        footer.foot { color: var(--muted); font-size: 12.5px; padding: 0 0 32px; }
        @media (max-width: 640px) { .hide-sm { display: none; } h1 { font-size: 19px; } }
    </style>
</head>
<body>
<header class="top">
    <div class="wrap bar">
        <a class="brand" href="{{ route('pennant-dashboard.index', ['store' => $store->name]) }}">
            <svg width="26" height="26" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0F172A"/><path d="M9 25V7" stroke="#E2E8F0" stroke-width="2.2" stroke-linecap="round"/><path d="M10 7.5 24 11 10 15Z" fill="#22D3EE" stroke="#22D3EE" stroke-linejoin="round"/></svg>
            Pennant Dashboard
        </a>
        <nav class="tabs">
            <a href="{{ route('pennant-dashboard.index', ['store' => $store->name]) }}" @class(['active' => request()->routeIs('pennant-dashboard.index', 'pennant-dashboard.feature')])>Features</a>
            <a href="{{ route('pennant-dashboard.audit', ['store' => $store->name]) }}" @class(['active' => request()->routeIs('pennant-dashboard.audit')])>Audit log</a>
        </nav>
        <span class="spacer"></span>
        @if (count($stores) > 1)
            <form method="get" class="inline">
                <label class="check">Store
                    <select name="store" onchange="this.form.submit()">
                        @foreach ($stores as $name)
                            <option value="{{ $name }}" @selected($name === $store->name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            </form>
        @else
            <span class="pill outline">store: {{ $store->name }} ({{ $store->driverName() }})</span>
        @endif
        @unless ($canManage)<span class="pill outline">read-only</span>@endunless
    </div>
</header>
<main class="wrap" id="top">
    @if (session('pennant-dashboard.status'))
        <div class="flash" role="status">{{ session('pennant-dashboard.status') }}<button type="button" aria-label="Dismiss" onclick="this.parentNode.remove()">×</button></div>
    @endif
    @if ($errors->any())
        <div class="flash error" role="alert">{{ $errors->first() }}<button type="button" aria-label="Dismiss" onclick="this.parentNode.remove()">×</button></div>
    @endif
    @yield('content')
</main>
<footer class="wrap foot">
    laravel-pennant-dashboard {{ $version }} · an unofficial community package for Laravel Pennant ·
    <a href="https://github.com/amjadiqbal/laravel-pennant-dashboard" rel="noopener">GitHub</a>
</footer>
<script>
    document.addEventListener('submit', function (e) {
        var message = e.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) { e.preventDefault(); }
    });
    document.addEventListener('click', function (e) {
        var pick = e.target.closest('[data-pick-key]');
        if (!pick) { return; }
        e.preventDefault();
        document.querySelectorAll('input[data-scope-picker]').forEach(function (input) { input.value = pick.getAttribute('data-pick-key'); });
        document.querySelectorAll('select[data-scope-picker]').forEach(function (select) { select.value = pick.getAttribute('data-pick-type'); });
    });
    document.querySelectorAll('[data-range-for]').forEach(function (range) {
        var target = document.getElementById(range.getAttribute('data-range-for'));
        range.addEventListener('input', function () { target.value = range.value; });
        target.addEventListener('input', function () { range.value = target.value; });
    });
</script>
</body>
</html>
