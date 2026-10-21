@include('filament.hooks.passkey-script')

{{-- Hidden until the script confirms the browser can do this at all: an
     offer that cannot work is worse than no offer. --}}
<div class="omsp-passkey-login" data-passkey-login hidden>
    <div class="omsp-passkey-divider"><span>{{ __('or') }}</span></div>

    <button type="button" class="fi-btn fi-btn-size-md omsp-passkey-btn" data-passkey-button>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
             stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15.75 5.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM4.5 19.5a8.25 8.25 0 0 1 15 0M12 12.75v6.75" />
        </svg>
        <span data-passkey-label>{{ __('Sign in with a passkey') }}</span>
    </button>

    <p class="omsp-passkey-error" data-passkey-error hidden></p>
</div>

<script>
(function () {
    const root = document.querySelector('[data-passkey-login]');
    if (!root || !window.omspPasskey?.supported()) {
        return;
    }

    root.hidden = false;

    const button = root.querySelector('[data-passkey-button]');
    const label = root.querySelector('[data-passkey-label]');
    const error = root.querySelector('[data-passkey-error]');
    const idle = label.textContent;

    button.addEventListener('click', async () => {
        error.hidden = true;
        button.disabled = true;
        label.textContent = @json(__('Waiting for your device…'));

        try {
            const result = await window.omspPasskey.signIn();
            window.location.href = result.redirect;
            return;
        } catch (e) {
            if (!window.omspPasskey.cancelled(e)) {
                error.textContent = e.message;
                error.hidden = false;
            }
        }

        button.disabled = false;
        label.textContent = idle;
    });
})();
</script>
