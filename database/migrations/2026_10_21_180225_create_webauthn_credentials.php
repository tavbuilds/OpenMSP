<?php

use Laragear\WebAuthn\Models\WebAuthnCredential;

// The package's own table. It already carries an `alias`, which is what lets
// an operator tell their phone from their laptop when removing one.
return WebAuthnCredential::migration();
