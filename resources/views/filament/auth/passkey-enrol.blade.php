@include('filament.hooks.passkey-script')

{{-- The one place a passkey is created. Shared by the Passkeys page and the
     "set up 2FA" screen, because an operator sent to that screen with no
     factor yet must be able to enrol one without leaving it. --}}
<div data-passkey-add hidden>
    <p class="omsp-prose">
        {{ __('Give it a name you will recognise later, confirm your password, and your device will ask for your fingerprint, face, or PIN.') }}
    </p>

    <div class="omsp-passkey-form">
        <label>
            <span>{{ __('Name') }}</span>
            <input type="text" class="fi-input" data-passkey-alias
                   maxlength="60" placeholder="{{ __('Work laptop') }}">
        </label>

        <label>
            <span>{{ __('Your password') }}</span>
            {{-- Adding a key is adding a way in; an unattended session must
                 not be enough to do it. --}}
            <input type="password" class="fi-input" data-passkey-password
                   autocomplete="current-password">
        </label>

        <button type="button" class="fi-btn fi-btn-size-md omsp-passkey-btn" data-passkey-button>
            <span data-passkey-label>{{ __('Add passkey') }}</span>
        </button>
    </div>

    <p class="omsp-passkey-error" data-passkey-error hidden></p>
</div>

<p class="omsp-prose" data-passkey-unsupported hidden>
    {{ __('This browser cannot create passkeys. Try a current version of Chrome, Edge, Safari, or Firefox, over HTTPS.') }}
</p>

<script>
(function () {
    const add = document.querySelector('[data-passkey-add]');
    const unsupported = document.querySelector('[data-passkey-unsupported]');
    if (!add) return;

    if (!window.omspPasskey?.supported()) {
        if (unsupported) unsupported.hidden = false;
        return;
    }

    add.hidden = false;

    const button = add.querySelector('[data-passkey-button]');
    const label = add.querySelector('[data-passkey-label]');
    const error = add.querySelector('[data-passkey-error]');
    const alias = add.querySelector('[data-passkey-alias]');
    const password = add.querySelector('[data-passkey-password]');
    const idle = label.textContent;

    button.addEventListener('click', async () => {
        error.hidden = true;

        if (!password.value) {
            error.textContent = @json(__('Enter your password first.'));
            error.hidden = false;
            return;
        }

        button.disabled = true;
        label.textContent = @json(__('Waiting for your device…'));

        try {
            await window.omspPasskey.register(alias.value, password.value);
            window.location.reload();
            return;
        } catch (e) {
            if (!window.omspPasskey.cancelled(e)) {
                error.textContent = e.message;
                error.hidden = false;
            }
        }

        password.value = '';
        button.disabled = false;
        label.textContent = idle;
    });
})();
</script>
