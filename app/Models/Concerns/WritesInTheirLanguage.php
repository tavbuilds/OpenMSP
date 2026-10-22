<?php

namespace App\Models\Concerns;

use App\Support\LocaleCatalog;

/**
 * The language to write to this person in.
 *
 * Laravel asks a notifiable for this before rendering, so every mail
 * comes out in their language without a single caller having to think
 * about it. Null means "whatever the app is set to", which for a
 * scheduled command is the platform default — the right answer for
 * someone who has never picked one.
 */
trait WritesInTheirLanguage
{
    public function preferredLocale(): ?string
    {
        return LocaleCatalog::match($this->language);
    }
}
