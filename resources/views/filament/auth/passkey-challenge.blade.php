{{--
    The password was accepted; this is the second step.

    Alpine rather than a plain script: Filament swaps this in over the login
    form through Livewire, and a <script> that arrives by DOM morph is never
    executed. The helper itself is already on the page — the login form's
    render hook put it there before this form replaced it.

    The hidden field the form validates is filled here, so the challenge
    cannot be passed by pressing the button and nothing else.
--}}
<div
    data-options="{{ json_encode($options) }}"
    x-data="{
        busy: false,
        error: '',
        idle: @js(__('Use your passkey')),
        waiting: @js(__('Waiting for your device…')),

        async run() {
            if (! window.omspPasskey?.supported()) {
                this.error = @js(__('This browser cannot use passkeys. Sign in with your authenticator app instead.'));
                return;
            }

            this.error = '';
            this.busy = true;

            try {
                // Read the options now rather than at init: a failed attempt
                // re-renders this form with a fresh challenge, and signing
                // the stale one would fail for the wrong reason.
                const assertion = await window.omspPasskey.assertWith(
                    JSON.parse(this.$root.dataset.options)
                );

                // Deferred set — the third argument. A live set would make a
                // round trip, that round trip re-renders this form, and the
                // re-render mints a new challenge that the assertion we just
                // signed no longer matches.
                $wire.set('data.multiFactor.passkey.assertion', JSON.stringify(assertion), false);

                await $wire.authenticate();
            } catch (e) {
                if (! window.omspPasskey.cancelled(e)) {
                    this.error = e.message ?? @js(__('That passkey was not recognised.'));
                }
            }

            this.busy = false;
        },
    }"
>
    <button type="button" class="fi-btn fi-btn-size-md omsp-passkey-btn"
            x-on:click="run()" x-bind:disabled="busy">
        <span x-text="busy ? waiting : idle"></span>
    </button>

    <p class="omsp-passkey-error" x-show="error" x-text="error" x-cloak></p>
</div>
