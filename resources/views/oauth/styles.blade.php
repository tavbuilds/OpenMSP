{{-- Shared look for the standalone OAuth screens. Always dark, no build step. --}}
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        margin: 0;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #0b1220;
        color: #e5e7eb;
        line-height: 1.5;
        padding: max(1.25rem, env(safe-area-inset-top))
                 max(1.25rem, env(safe-area-inset-right))
                 max(1.25rem, env(safe-area-inset-bottom))
                 max(1.25rem, env(safe-area-inset-left));
        -webkit-text-size-adjust: 100%;
    }
    .card {
        width: 100%;
        max-width: 28rem;
        background: #111827;
        border: 1px solid #1f2937;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
    }
    h1 { font-size: 1.15rem; line-height: 1.35; margin: 0 0 .6rem; }
    p { color: #9ca3af; font-size: .95rem; margin: 0 0 .75rem; }
    p:last-child { margin-bottom: 0; }
    code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; overflow-wrap: anywhere; }
    .who {
        display: grid;
        gap: .2rem;
        margin: 1.25rem 0;
        padding: .85rem 1rem;
        border-radius: .75rem;
        background: #0b1220;
        border: 1px solid #1f2937;
        font-size: .9rem;
    }
    .who-label {
        display: block;
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7280;
    }
    .who code { color: #9ca3af; }
    .error-code { margin-top: 1.25rem; }
    .error-code code { color: #fca5a5; }
    .row {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        margin-top: 1.25rem;
    }
    button {
        flex: 1 1 8rem;
        min-height: 2.75rem;
        border: 0;
        border-radius: .75rem;
        padding: .75rem 1rem;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
    }
    .allow { background: #2563eb; color: #fff; }
    .allow:hover { background: #1d4ed8; }
    .deny { background: transparent; color: #e5e7eb; border: 1px solid #374151; }
    .deny:hover { background: #1f2937; }
    button:focus-visible {
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
    }
</style>
