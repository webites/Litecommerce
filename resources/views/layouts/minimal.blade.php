<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Litecommerce') — Litecommerce</title>
    <script>
        (function () {
            try {
                var root = document.documentElement;
                var stored = localStorage.getItem('lc-theme');
                var media = window.matchMedia('(prefers-color-scheme: dark)');
                var apply = function () { root.dataset.theme = stored ? stored : (media.matches ? 'dark' : 'light'); };
                apply();
                if (!stored) { media.addEventListener('change', apply); }
                window.lcToggleTheme = function () {
                    stored = root.dataset.theme === 'dark' ? 'light' : 'dark';
                    localStorage.setItem('lc-theme', stored);
                    apply();
                };
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        {!! file_get_contents(resource_path('css/design-tokens.css')) !!}

        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; -webkit-font-smoothing: antialiased; }
        .guest { width: 100%; max-width: 560px; display: flex; flex-direction: column; gap: 22px; }
        .brand { font-family: var(--font-display); font-weight: 700; font-size: 22px; text-align: center; color: var(--text-primary); }
        .toolbar { display: flex; justify-content: flex-end; align-items: center; gap: 8px; }
        .pill { display: inline-flex; margin: 0; padding: 2px; gap: 2px; border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); }
        .pill button { border: 0; border-radius: var(--radius-pill); padding: 4px 10px; background: transparent; color: var(--text-secondary); font: 600 12px var(--font-body); cursor: pointer; }
        .pill button[aria-current="true"] { background: var(--brand-solid); color: #fff; cursor: default; }
        .icon-button { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); background: transparent; color: var(--text-secondary); cursor: pointer; }
        .icon-button:hover { background: var(--gray-3); }
        .icon-button .moon { display: none; }
        [data-theme='dark'] .icon-button .sun { display: none; }
        [data-theme='dark'] .icon-button .moon { display: block; }
        .card { background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-card); box-shadow: var(--shadow-sm); padding: 28px; display: flex; flex-direction: column; gap: 24px; }
        .steps { display: flex; align-items: center; list-style: none; margin: 0; padding: 0; }
        .steps li { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--text-tertiary); white-space: nowrap; }
        .steps li + li { flex: 1; }
        .steps li + li::before { content: ''; flex: 1; min-width: 12px; height: 1px; margin: 0 10px; background: var(--border-default); }
        .steps li.done + li::before { background: var(--success-solid); }
        .steps .dot { flex: none; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; background: var(--gray-4); color: var(--text-tertiary); }
        .steps li span:not(.dot) { display: none; }
        .steps li.current { color: var(--text-primary); font-weight: 600; }
        .steps li.current span:not(.dot) { display: inline; }
        .steps li.current .dot { background: var(--brand-solid); color: #fff; }
        .steps li.done .dot { background: var(--success-solid); color: #fff; }
        .page { display: flex; flex-direction: column; gap: 16px; }
        h1 { margin: 0; font-size: 16px; font-weight: 600; color: var(--text-primary); }
        .intro { margin: 0; font-size: 14px; color: var(--text-secondary); }
        .field { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid var(--border-default); border-radius: var(--radius-control); background: var(--bg-surface); color: var(--text-primary); font: 14px var(--font-body); transition: box-shadow var(--duration-fast) var(--ease-out); }
        input:focus { outline: none; border-color: var(--brand-solid); box-shadow: var(--shadow-focus); }
        .hint { margin: 0; font-size: 12px; color: var(--text-tertiary); }
        .actions { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 6px; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 38px; padding: 0 16px; margin: 0; border: 1px solid transparent; border-radius: var(--radius-control); background: var(--brand-solid); color: #fff; font: 600 14px var(--font-body); text-decoration: none; cursor: pointer; white-space: nowrap; transition: background var(--duration-fast) var(--ease-out); }
        .button:hover { background: var(--brand-solid-hover); }
        .button:active { background: var(--brand-solid-active); }
        .button.outline { background: transparent; color: var(--text-primary); border-color: var(--border-default); }
        .button.outline:hover { background: var(--gray-3); }
        .alert { display: flex; gap: 12px; padding: 12px 14px; border-radius: var(--radius-lg); font-size: 13px; }
        .alert ul { margin: 0; padding-left: 18px; }
        .alert.danger { background: var(--danger-subtle-bg); color: var(--danger-text); }
        .alert.success { background: var(--success-subtle-bg); color: var(--success-text); }
        .checks { list-style: none; margin: 0; padding: 0; }
        .checks li { display: flex; gap: 12px; padding: 12px 0; border-top: 1px solid var(--border-subtle); }
        .checks li:first-child { border-top: 0; padding-top: 0; }
        .badge { flex: none; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
        .badge.ok { background: var(--success-subtle-bg); color: var(--success-text); }
        .badge.fail { background: var(--danger-subtle-bg); color: var(--danger-text); }
        .check-title { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .check-detail { font-size: 13px; color: var(--text-secondary); }
        @media (max-width: 480px) {
            .card { padding: 20px; }
            .steps li + li::before { margin: 0 6px; }
        }
    </style>
</head>
<body>
    @php($stepKeys = ['environment', 'database', 'administrator', 'finalize'])
    @php($current = (int) trim($__env->yieldContent('step', '0')))
    <div class="guest">
        <div class="brand">Litecommerce</div>

        <div class="toolbar">
            <button type="button" class="icon-button" onclick="window.lcToggleTheme && window.lcToggleTheme()" aria-label="{{ __('installer::messages.theme') }}">
                <svg class="sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            </button>
            <form class="pill" method="POST" action="{{ route('install.locale') }}" aria-label="{{ __('installer::messages.language') }}">
                @csrf
                @foreach ((array) config('app.supported_ui_locales') as $code)
                    <button type="submit" name="locale" value="{{ $code }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ strtoupper($code) }}</button>
                @endforeach
            </form>
        </div>

        <main class="card">
            <ol class="steps">
                @foreach ($stepKeys as $index => $key)
                    <li class="{{ $index < $current ? 'done' : ($index === $current ? 'current' : '') }}">
                        <span class="dot">{{ $index < $current ? '✓' : $index + 1 }}</span>
                        <span>{{ __('installer::messages.steps.'.$key) }}</span>
                    </li>
                @endforeach
            </ol>

            <div class="page">
                <h1>@yield('title', 'Litecommerce')</h1>
                @hasSection('intro')
                    <p class="intro">@yield('intro')</p>
                @endif

                @if ($errors->any())
                    <div class="alert danger" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
