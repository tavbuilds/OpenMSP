<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $platformColor ?? '#0f172a' }}">
    <title>@yield('title', 'Customer portal') — {{ $platformName ?? 'MSP Platform' }}</title>
    @if (!empty($platformFavicon))
        <link rel="icon" href="{{ $platformFavicon }}">
    @endif
    <style>
        :root {
            --bg:#f4f6f9; --card:#fff; --text:#1e293b; --muted:#64748b;
            --primary: {{ $platformColor ?? '#2563eb' }}; --border:#e2e8f0; --ok:#16a34a; --warn:#d97706;
            --header:#0f172a; --radius:12px;
            --pad-x: max(1rem, env(safe-area-inset-left));
            --pad-r: max(1rem, env(safe-area-inset-right));
            --pad-t: env(safe-area-inset-top);
            --pad-b: env(safe-area-inset-bottom);
        }
        * { box-sizing: border-box; }
        html, body { overflow-x: clip; }
        body {
            margin:0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background:var(--bg); color:var(--text);
            padding-bottom: var(--pad-b);
            -webkit-text-size-adjust: 100%;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .wrap {
            width: 100%;
            max-width: 960px;
            margin: 0 auto;
            padding: 1.25rem var(--pad-x) 1.25rem var(--pad-r);
        }
        header.app {
            background: var(--header);
            color:#fff;
            padding-top: var(--pad-t);
        }
        header.app .wrap {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.85rem;
            padding-top: 0.9rem;
            padding-bottom: 0.9rem;
        }
        header.app a { color:#e2e8f0; }
        header.app nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 0.85rem;
            align-items: center;
        }
        header.app nav form { display: contents; }
        .brand { font-weight:700; letter-spacing:.02em; font-size: 1.05rem; display:flex; align-items:center; gap:.6rem; }
        .brand-logo { height:28px; width:auto; max-width:160px; object-fit:contain; }
        .nav-user { color:#94a3b8; font-size: .9rem; }
        .card {
            background:var(--card);
            border:1px solid var(--border);
            border-radius: var(--radius);
            padding:1.1rem 1.15rem;
            margin-bottom:1rem;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
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
            cursor:pointer;
            text-decoration:none !important;
            text-align:center;
        }
        .btn:hover { filter: brightness(1.05); text-decoration:none !important; }
        .btn-secondary { background:#475569; }
        .btn-outline { background:#fff; color:var(--primary) !important; border:1px solid var(--primary); }
        .btn-disabled { background:#94a3b8; cursor:default; }
        .btn-block { width: 100%; }
        input[type=email], input[type=text], input[type=password] {
            width:100%;
            padding:.75rem .85rem;
            border:1px solid var(--border);
            border-radius:10px;
            font-size:16px; /* iOS: no auto-zoom */
        }
        label { display:block; font-weight:600; margin-bottom:.35rem; }
        .alert { padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem; }
        .alert-ok { background:#dcfce7; color:#166534; }
        .alert-err { background:#fee2e2; color:#991b1b; }
        table { width:100%; border-collapse: collapse; }
        th, td { text-align:left; padding:.75rem .5rem; border-bottom:1px solid var(--border); vertical-align:top; }
        th { font-size:.8rem; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
        .badge { display:inline-block; font-size:.75rem; font-weight:600; padding:.25rem .55rem; border-radius:999px; background:#e2e8f0; }
        .badge-ok { background:#dcfce7; color:#166534; }
        .badge-pending { background:#ffedd5; color:#9a3412; }
        .login-box { max-width:420px; margin:1.5rem auto 0; }
        h1 { font-size:1.35rem; margin:0 0 .35rem; line-height:1.25; }
        h2 { font-size:1.05rem; margin:0 0 .5rem; }
        footer { color:var(--muted); font-size:.85rem; margin-top:2rem; text-align:center; padding-bottom:1rem; }
        .lede { margin: 0 0 1.1rem; }

        /* Cards on phone / small tablet; table from 768px. */
        .stack { display: grid; gap: 0.85rem; }
        .data-table { display: none; }
        .data-table .card { padding: 0; overflow: auto; -webkit-overflow-scrolling: touch; }
        .stack-card h2 { margin-bottom: .2rem; }
        .stack-meta { display: grid; gap: .45rem; margin: .75rem 0; font-size: .95rem; }
        .stack-meta div { display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; }
        .stack-meta dt { color: var(--muted); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
        .stack-actions { margin-top: .5rem; }

        nav[role="navigation"] {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            justify-content: center;
            margin-top: 1rem;
        }
        nav[role="navigation"] a,
        nav[role="navigation"] span {
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
            text-decoration: none;
        }

        @media (min-width: 640px) {
            header.app .wrap {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
            .wrap { padding-top: 1.5rem; padding-bottom: 1.5rem; }
            h1 { font-size: 1.5rem; }
            .login-box { margin-top: 3rem; }
            .btn-block-sm { width: auto; }
        }
        @media (min-width: 768px) {
            .stack { display: none; }
            .data-table { display: block; }
        }
    </style>
</head>
<body>
@auth('portal')
<header class="app">
    <div class="wrap">
        <div class="brand">
            @if (!empty($platformLogo))
                <img src="{{ $platformLogo }}" alt="" class="brand-logo">
            @endif
            <span>{{ $platformName ?? 'MSP Platform' }}</span>
        </div>
        <nav>
            <a href="{{ route('portal.dashboard') }}">Services</a>
            <a href="{{ route('portal.invoices') }}">Invoices</a>
            <span class="nav-user">{{ Auth::guard('portal')->user()->name }}</span>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary" style="min-height:2.4rem;padding:.4rem .85rem;font-size:.85rem">Sign out</button>
            </form>
        </nav>
    </div>
</header>
@endauth
<main class="wrap">
    @if (session('status'))
        <div class="alert alert-ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-err">
            <ul style="margin:0;padding-left:1.1rem">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @yield('content')
    <footer>{{ $platformName ?? 'MSP Platform' }} — customer portal. Internal cost, margins, and license keys are never shown.</footer>
</main>
</body>
</html>
