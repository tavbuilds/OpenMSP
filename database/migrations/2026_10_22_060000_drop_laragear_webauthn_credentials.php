<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passkeys moved from laragear/webauthn, which is abandoned, to the
     * official laravel/passkeys. The two store a credential differently
     * enough that converting one into the other is not worth writing for a
     * feature days old, so the old table goes rather than sitting there
     * holding key material nothing reads.
     *
     * Anyone who enrolled a passkey before this adds it again. Nobody is
     * locked out by that: the password form never went away, which is why
     * passkeys were built beside it rather than instead of it.
     */
    public function up(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }

    public function down(): void
    {
        // Nothing to put back: the credentials it held cannot be recovered
        // from the new table, and an empty one would only mislead.
    }
};
