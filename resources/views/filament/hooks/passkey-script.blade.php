{{--
    The WebAuthn ceremonies, by hand.

    There is no asset pipeline in the container, so this is plain script on
    the page rather than a bundled package. It is small because the server
    does the hard parts: all this does is move binary between base64url and
    ArrayBuffer and hand the result back.
--}}
<script>
window.omspPasskey = (function () {
    const supported = () =>
        typeof window.PublicKeyCredential === 'function' &&
        typeof navigator.credentials?.create === 'function';

    const toBuffer = (value) => {
        const padded = value.replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(padded + '='.repeat((4 - padded.length % 4) % 4));
        return Uint8Array.from(raw, (c) => c.charCodeAt(0));
    };

    const toBase64Url = (buffer) =>
        btoa(String.fromCharCode(...new Uint8Array(buffer)))
            .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

    const csrf = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify(body ?? {}),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || '{{ __('Something went wrong. Try again.') }}');
        }

        return data;
    }

    // The server speaks base64url; navigator.credentials wants buffers.
    function prepareRequest(options) {
        return {
            ...options,
            challenge: toBuffer(options.challenge),
            allowCredentials: (options.allowCredentials ?? []).map((credential) => ({
                ...credential,
                id: toBuffer(credential.id),
            })),
        };
    }

    function prepareCreation(options) {
        return {
            ...options,
            challenge: toBuffer(options.challenge),
            user: { ...options.user, id: toBuffer(options.user.id) },
            excludeCredentials: (options.excludeCredentials ?? []).map((credential) => ({
                ...credential,
                id: toBuffer(credential.id),
            })),
        };
    }

    const packAssertion = (credential) => ({
        id: credential.id,
        rawId: toBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: toBase64Url(credential.response.clientDataJSON),
            authenticatorData: toBase64Url(credential.response.authenticatorData),
            signature: toBase64Url(credential.response.signature),
            userHandle: credential.response.userHandle
                ? toBase64Url(credential.response.userHandle)
                : null,
        },
    });

    /** Runs an assertion against options the caller already has. */
    async function assertWith(options) {
        const credential = await navigator.credentials.get({
            publicKey: prepareRequest(options),
        });

        if (!credential) {
            throw new Error('{{ __('No passkey was used.') }}');
        }

        return packAssertion(credential);
    }

    /** Discoverable sign-in: no email typed, the device offers the account. */
    async function signIn() {
        const options = await post(@json(route('passkeys.login.options')));
        const assertion = await assertWith(options);

        return post(@json(route('passkeys.login')), assertion);
    }

    async function register(alias, password) {
        const options = await post(@json(route('passkeys.register.options')));

        const credential = await navigator.credentials.create({
            publicKey: prepareCreation(options),
        });

        if (!credential) {
            throw new Error('{{ __('No passkey was created.') }}');
        }

        return post(@json(route('passkeys.register')), {
            id: credential.id,
            rawId: toBase64Url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: toBase64Url(credential.response.clientDataJSON),
                attestationObject: toBase64Url(credential.response.attestationObject),
            },
            alias: alias,
            password: password,
        });
    }

    /**
     * A cancelled prompt is not a failure worth shouting about — the
     * operator closed the dialog, which is an answer.
     */
    const cancelled = (error) =>
        error?.name === 'NotAllowedError' || error?.name === 'AbortError';

    return { supported, signIn, register, assertWith, cancelled };
})();
</script>
