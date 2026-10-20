<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Opening {{ $name }}</title>
    <style nonce="{{ $nonce }}">
        :root { color-scheme: light dark; --bg: #f5f4f0; --fg: #1d1c1a; --muted: #6c6a64; --line: #d9d6cd; --accent: #2f8fd6; }
        @media (prefers-color-scheme: dark) { :root { --bg: #14130f; --fg: #ecebe6; --muted: #9a978d; --line: #2c2a24; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--fg); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: min(26rem, calc(100vw - 2rem)); border-top: 2px solid var(--accent); padding: 1.25rem 0 0; }
        h1 { margin: 0 0 .25rem; font-size: 1.125rem; font-weight: 600; }
        p { margin: 0 0 1rem; color: var(--muted); }
        button { font: inherit; padding: .6rem 1rem; border: 1px solid var(--line); background: transparent; color: var(--fg); border-radius: .25rem; cursor: pointer; }
        button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <h1>Opening {{ $name }}</h1>
        <p>Signing you in. This takes a moment.</p>
        <form id="launch" method="post" action="{{ $action }}">
            <input type="hidden" name="token" value="{{ $token }}">
            @if ($redirect !== null)
                <input type="hidden" name="redirect" value="{{ $redirect }}">
            @endif
            <noscript><button type="submit">Continue to {{ $name }}</button></noscript>
        </form>
    </main>
    <script nonce="{{ $nonce }}">document.getElementById('launch').submit();</script>
</body>
</html>
