<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $platformColor ?? '#0f172a' }}">
    <title>@yield('title', __('Customer portal')) — {{ $platformName ?? 'MSP Platform' }}</title>
    @if (!empty($platformFavicon))
        <link rel="icon" href="{{ $platformFavicon }}">
    @endif
    <style>
        :root {
            color-scheme: light; /* the portal is a light-only design */
            --bg:#f4f6f9; --card:#fff; --text:#1e293b; --muted:#64748b;
            --primary: {{ $platformColor ?? '#2563eb' }}; --border:#e2e8f0; --ok:#16a34a; --warn:#d97706;
            --header:#0f172a; --radius:12px;
            --pad-l: max(1rem, env(safe-area-inset-left));
            --pad-r: max(1rem, env(safe-area-inset-right));
            --pad-t: env(safe-area-inset-top);
            --pad-b: env(safe-area-inset-bottom);
            --gutter: 1.15rem; /* inner padding of a card, reused by table cells */
        }
        * { box-sizing: border-box; }
        html, body { overflow-x: clip; }
        body {
            margin:0;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            line-height: 1.5;
            background:var(--bg); color:var(--text);
            padding-bottom: var(--pad-b);
            -webkit-text-size-adjust: 100%;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* One visible focus treatment for every interactive element. */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        summary:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
            border-radius: 6px;
        }
        header.app a:focus-visible,
        header.app button:focus-visible { outline-color: #fff; }

        .wrap {
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            padding: 1.25rem var(--pad-r) 1.25rem var(--pad-l);
        }
        main.wrap { flex: 1 0 auto; }
        /* Signed out there is no header, so centre the card in the viewport. */
        body.is-guest main.wrap {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        header.app {
            background: var(--header);
            color:#fff;
            padding-top: var(--pad-t);
        }
        header.app .wrap {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.6rem 1rem;
            padding-top: 0.9rem;
            padding-bottom: 0.9rem;
        }
        header.app a { color:#e2e8f0; }
        header.app nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
        }
        /* Nav links left, identity and sign-out pushed to the far end. */
        .nav-primary { flex: 1 1 auto; }
        .nav-account {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-inline-start: auto;
        }
        .nav-account form { display: contents; }
        .nav-link {
            display: inline-flex;
            align-items: center;
            min-height: 2.25rem;
            padding: 0 .7rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none !important;
        }
        .nav-link:hover { background: rgba(255,255,255,.08); }
        .nav-link[aria-current="page"] {
            background: rgba(255,255,255,.14);
            color: #fff;
            font-weight: 600;
        }
        .brand {
            font-weight:700; letter-spacing:.02em; font-size: 1.05rem;
            display:flex; align-items:center; gap:.6rem;
            min-width: 0;
        }
        .brand-logo { height:28px; width:auto; max-width:160px; object-fit:contain; }
        .brand-guest { justify-content:center; margin-bottom: .5rem; color: var(--text); }
        .nav-user {
            color:#94a3b8; font-size: .85rem;
            max-width: 14rem;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        .card {
            background:var(--card);
            border:1px solid var(--border);
            border-radius: var(--radius);
            padding: var(--gutter);
            margin-bottom:1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .card-flush { padding: 0; overflow: auto; -webkit-overflow-scrolling: touch; }
        .muted { color: var(--muted); }

        .btn {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            background:var(--primary);
            color:#fff !important;
            border:none;
            border-radius:10px;
            padding:.7rem 1rem;
            min-height: 2.75rem;
            font-weight:600;
            font-size: .95rem;
            line-height: 1.2;
            cursor:pointer;
            text-decoration:none !important;
            text-align:center;
        }
        .btn:hover { filter: brightness(1.05); text-decoration:none !important; }
        .btn-secondary { background:#475569; }
        .btn-outline { background:#fff; color:var(--primary) !important; border:1px solid var(--primary); }
        .btn-disabled { background:#94a3b8; cursor:default; }
        .btn-block { width: 100%; }
        /* Compact variant for table rows and the header, where a 2.75rem
           button would stretch the row it sits in. */
        .btn-sm {
            min-height: 2.25rem;
            padding: .4rem .8rem;
            font-size: .85rem;
            white-space: nowrap;
        }

        input[type=email], input[type=text], input[type=password] {
            width:100%;
            padding:.75rem .85rem;
            border:1px solid var(--border);
            border-radius:10px;
            font-size:16px; /* iOS: no auto-zoom */
            background: #fff;
            color: var(--text);
        }
        input[type=email]:focus, input[type=text]:focus, input[type=password]:focus {
            border-color: var(--primary);
        }
        label { display:block; font-weight:600; margin-bottom:.35rem; }
        .field-hint { color: var(--muted); font-size: .85rem; margin: .4rem 0 0; }

        .alert { padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem; border:1px solid transparent; }
        .alert-ok { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
        .alert-err { background:#fee2e2; color:#991b1b; border-color:#fecaca; }
        .alert ul { margin:0; padding-inline-start:1.1rem; }

        table { width:100%; border-collapse: collapse; }
        th, td { text-align:left; padding:.85rem .6rem; border-bottom:1px solid var(--border); vertical-align:middle; }
        /* Line the outer columns up with the padding of every other card. */
        th:first-child, td:first-child { padding-inline-start: var(--gutter); }
        th:last-child, td:last-child { padding-inline-end: var(--gutter); }
        tbody tr:last-child td { border-bottom: 0; }
        th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); font-weight:600; white-space:nowrap; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .cell-sub { font-size:.85rem; margin-top:.15rem; }

        .badge {
            display:inline-block; font-size:.75rem; font-weight:600;
            padding:.25rem .6rem; border-radius:999px;
            background:#e2e8f0; color:#334155; white-space:nowrap;
        }
        .badge-ok { background:#dcfce7; color:#166534; }
        .badge-pending { background:#ffedd5; color:#9a3412; }
        .badge-err { background:#fee2e2; color:#991b1b; }

        .login-box { max-width:420px; margin:1.5rem auto 0; }
        h1 { font-size:1.35rem; margin:0 0 .35rem; line-height:1.25; }
        h2 { font-size:1.05rem; margin:0 0 .5rem; line-height:1.3; overflow-wrap:anywhere; }
        footer {
            flex-shrink: 0;
            color:var(--muted); font-size:.85rem;
            text-align:center;
            padding: 1.5rem var(--pad-r) 1.5rem var(--pad-l);
        }
        .lede { margin: 0 0 1.1rem; max-width: 70ch; }
        .visually-hidden {
            position:absolute; width:1px; height:1px; padding:0; margin:-1px;
            overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0;
        }
        .empty { margin: 0; }

        /* Cards on phone / small tablet; table from 768px. */
        .stack { display: grid; gap: 1rem; }
        .stack > .card { margin-bottom: 0; }
        .data-table { display: none; }
        .stack-card h2 { margin-bottom: .2rem; }
        .stack-meta { display: grid; gap: .5rem; margin: .9rem 0 0; font-size: .95rem; }
        .stack-meta > div {
            display: flex; justify-content: space-between;
            gap: 1.25rem; align-items: baseline;
        }
        .stack-meta dt { color: var(--muted); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        .stack-meta dd { margin: 0; text-align: right; }
        .stack-actions { margin-top: 1rem; display: grid; gap: .5rem; justify-items: start; }
        .stack-actions .btn { width: 100%; }

        /* Pagination (rendered by resources/views/portal/pagination.blade.php). */
        .pager {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .75rem;
            margin-top: 1.25rem;
        }
        .pager-summary { color: var(--muted); font-size: .85rem; }
        .pager-links { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: center; }
        .pager-links a,
        .pager-links span {
            display: inline-flex;
            min-height: 2.5rem;
            min-width: 2.5rem;
            align-items: center;
            justify-content: center;
            padding: 0 .7rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            font-size: .9rem;
            text-decoration: none;
        }
        .pager-links a:hover { border-color: var(--primary); color: var(--primary); text-decoration: none; }
        .pager-links [aria-current="page"] {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .pager-links .is-disabled { color: #cbd5e1; background: #f8fafc; }
        .pager-links .is-gap { border-color: transparent; background: transparent; color: var(--muted); }

        @media (min-width: 640px) {
            .wrap { padding-top: 1.5rem; padding-bottom: 1.5rem; }
            h1 { font-size: 1.5rem; }
            .login-box { margin-top: 3rem; }
            .btn-block-sm { width: auto; }
            .stack-actions { grid-auto-flow: column; justify-content: start; align-items: center; }
            .stack-actions .btn { width: auto; }
            .pager { flex-direction: row; justify-content: space-between; }
            .pager-links { justify-content: flex-end; }
        }
        @media (min-width: 768px) {
            .stack { display: none; }
            .data-table { display: block; }
        }
    </style>
</head>
<body @guest('portal') class="is-guest" @endguest>
@auth('portal')
    @php($currentRoute = Route::currentRouteName())
    <header class="app">
        <div class="wrap">
            <div class="brand">
                @if (!empty($platformLogo))
                    <img src="{{ $platformLogo }}" alt="" class="brand-logo">
                @endif
                <span>{{ $platformName ?? 'MSP Platform' }}</span>
            </div>
            <nav class="nav-primary" aria-label="{{ __('Customer portal') }}">
                <a class="nav-link" href="{{ route('portal.dashboard') }}"
                   @if ($currentRoute === 'portal.dashboard') aria-current="page" @endif>{{ __('Services') }}</a>
                <a class="nav-link" href="{{ route('portal.invoices') }}"
                   @if ($currentRoute === 'portal.invoices') aria-current="page" @endif>{{ __('Invoices') }}</a>
            </nav>
            <div class="nav-account">
                <span class="nav-user">{{ Auth::guard('portal')->user()->name }}</span>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('Sign out') }}</button>
                </form>
            </div>
        </div>
    </header>
@endauth
<main class="wrap">
    @auth('portal')
    @else
        <div class="brand brand-guest">
            @if (!empty($platformLogo))
                <img src="{{ $platformLogo }}" alt="" class="brand-logo">
            @endif
            <span>{{ $platformName ?? 'MSP Platform' }}</span>
        </div>
    @endauth

    @if (session('status'))
        <div class="alert alert-ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
</main>
<footer>
    @auth('portal')
        {{ __(':platform — customer portal. Prices shown are the amounts we invoice you.', ['platform' => $platformName ?? 'MSP Platform']) }}
    @else
        {{ __(':platform — customer portal.', ['platform' => $platformName ?? 'MSP Platform']) }}
    @endauth
</footer>
</body>
</html>
