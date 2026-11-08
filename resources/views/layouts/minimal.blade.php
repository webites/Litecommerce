<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Litecommerce') — Litecommerce</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f4f5f7; --card: #ffffff; --text: #1b1f27; --muted: #5d6675; --border: #dde1e8;
            --accent: #2f5bea; --accent-text: #ffffff; --danger: #b3261e; --danger-bg: #fdecea;
            --ok: #1d7a46; --ok-bg: #e7f5ec; --field: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #12151b; --card: #1b2029; --text: #e8eaee; --muted: #9aa3b2; --border: #2c3340;
                --accent: #7c9bff; --accent-text: #0f1420; --danger: #ff8a80; --danger-bg: #3a1f1d;
                --ok: #6fd39a; --ok-bg: #16301f; --field: #12151b;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        .shell { max-width: 520px; margin: 0 auto; padding: 2rem 1rem 3rem; }
        .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .brand { font-weight: 700; font-size: 1.15rem; letter-spacing: .01em; }
        .lang { display: flex; gap: .25rem; margin: 0; }
        .lang button { margin: 0; padding: .25rem .6rem; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: var(--muted); font: inherit; font-size: .85rem; cursor: pointer; }
        .lang button[aria-current="true"] { background: var(--accent); border-color: var(--accent); color: var(--accent-text); }
        .steps { display: flex; gap: .35rem; list-style: none; margin: 0 0 1rem; padding: 0; font-size: .8rem; color: var(--muted); }
        .steps li { flex: 1; padding-top: .45rem; border-top: 3px solid var(--border); }
        .steps li.done, .steps li.current { border-top-color: var(--accent); }
        .steps li.current { color: var(--text); font-weight: 600; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; }
        h1 { font-size: 1.3rem; margin: 0 0 .25rem; }
        .intro { color: var(--muted); margin: 0 0 1rem; }
        label { display: block; margin-top: 1rem; font-weight: 600; font-size: .92rem; }
        input { width: 100%; padding: .6rem .7rem; margin-top: .3rem; border: 1px solid var(--border); border-radius: 8px; background: var(--field); color: var(--text); font: inherit; }
        input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        .hint { color: var(--muted); font-size: .82rem; margin: .3rem 0 0; }
        .actions { margin-top: 1.5rem; display: flex; gap: .75rem; align-items: center; }
        .button, button.primary { display: inline-block; margin: 0; padding: .65rem 1.3rem; border: 0; border-radius: 8px; background: var(--accent); color: var(--accent-text); font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .button.secondary { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .errors { margin: 0 0 1rem; padding: .75rem 1rem; border-radius: 8px; background: var(--danger-bg); color: var(--danger); }
        .errors ul { margin: 0; padding-left: 1.1rem; }
        .checks { list-style: none; margin: 1rem 0 0; padding: 0; }
        .checks li { display: flex; gap: .6rem; padding: .6rem 0; border-top: 1px solid var(--border); }
        .checks li:first-child { border-top: 0; }
        .badge { flex: none; width: 1.4rem; height: 1.4rem; border-radius: 50%; text-align: center; font-size: .85rem; line-height: 1.4rem; font-weight: 700; }
        .badge.ok { background: var(--ok-bg); color: var(--ok); }
        .badge.fail { background: var(--danger-bg); color: var(--danger); }
        .check-title { font-weight: 600; }
        .check-detail { color: var(--muted); font-size: .88rem; }
        .notice { margin-top: 1rem; padding: .7rem 1rem; border-radius: 8px; background: var(--ok-bg); color: var(--ok); }
        @media (max-width: 480px) { .shell { padding-top: 1rem; } .card { padding: 1.1rem; } .steps { font-size: .7rem; } }
    </style>
</head>
<body>
    @php($stepKeys = ['environment', 'database', 'administrator', 'finalize'])
    @php($current = (int) trim($__env->yieldContent('step', '0')))
    <div class="shell">
        <div class="top">
            <span class="brand">Litecommerce</span>
            <form class="lang" method="POST" action="{{ route('install.locale') }}" aria-label="{{ __('installer::messages.language') }}">
                @csrf
                @foreach ((array) config('app.supported_ui_locales') as $code)
                    <button type="submit" name="locale" value="{{ $code }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ strtoupper($code) }}</button>
                @endforeach
            </form>
        </div>

        <ol class="steps">
            @foreach ($stepKeys as $index => $key)
                <li class="{{ $index < $current ? 'done' : ($index === $current ? 'current' : '') }}">{{ __('installer::messages.steps.'.$key) }}</li>
            @endforeach
        </ol>

        <main class="card">
            <h1>@yield('title', 'Litecommerce')</h1>
            @hasSection('intro')
                <p class="intro">@yield('intro')</p>
            @endif

            @if ($errors->any())
                <div class="errors" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
