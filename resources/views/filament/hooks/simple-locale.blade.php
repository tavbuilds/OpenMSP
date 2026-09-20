<div class="locale-switch-simple">
    @include('filament.hooks.language-switcher')
</div>
<style>
    .locale-switch-simple {
        position: absolute;
        top: 1rem;
        inset-inline-end: 1rem;
        z-index: 40;
    }
    @media (max-width: 480px) {
        .locale-switch-simple {
            position: relative;
            top: auto;
            inset-inline-end: auto;
            display: flex;
            justify-content: flex-end;
            padding: 0.75rem 1rem 0;
        }
    }
</style>
