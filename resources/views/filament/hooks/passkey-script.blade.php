{{--
    The browser half of laravel/passkeys.

    There is no asset pipeline in the container, so this is plain script on
    the page rather than a bundled client. It stays small because the server
    does the hard parts: all this does is move binary between base64url and
    ArrayBuffer and hand the result back in the shape the package expects.
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

    async function send(method, url, body) {
        const response = await fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            // Laravel answers a failed rule with {errors: {field: [msg]}};
            // show the message rather than "422".
            const first = data.errors && Object.values(data.errors)[0];
            throw new Error(
                (Array.isArray(first) ? first[0] : null) ||
                data.message ||
                '{{ __('Something went wrong. Try again.') }}'
            );
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
        const { options } = await send('GET', @json(route('passkey.login-options')));
        const credential = await assertWith(options);

        return send('POST', @json(route('passkey.login')), { credential });
    }

    async function register(name, password) {
        const { options } = await send('GET', @json(route('passkey.registration-options')));

        const created = await navigator.credentials.create({
            publicKey: prepareCreation(options),
        });

        if (!created) {
            throw new Error('{{ __('No passkey was created.') }}');
        }

        return send('POST', @json(route('passkey.store')), {
            name: name || @json(__('Passkey')),
            password: password,
            credential: {
                id: created.id,
                rawId: toBase64Url(created.rawId),
                type: created.type,
                response: {
                    clientDataJSON: toBase64Url(created.response.clientDataJSON),
                    attestationObject: toBase64Url(created.response.attestationObject),
                },
            },
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
