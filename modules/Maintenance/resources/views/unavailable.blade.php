<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f6f6f7; color: #1c1c1e; }
        main { max-width: 480px; padding: 32px; text-align: center; }
        h1 { font-size: 24px; margin: 0 0 12px; }
        p { line-height: 1.5; margin: 0 0 8px; }
        .hint { color: #6b6b70; font-size: 14px; }
        @media (prefers-color-scheme: dark) { body { background: #151517; color: #f2f2f3; } .hint { color: #9a9aa0; } }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <p class="hint">{{ $hint }}</p>
    </main>
</body>
</html>
