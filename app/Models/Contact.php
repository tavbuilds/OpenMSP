<?php

namespace App\Models;

use App\Models\Concerns\WritesInTheirLanguage;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Portal-authenticatable contact person for a company.
 * Magic-link login only (no password). Separate from Filament admin Users.
 */
class Contact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use WritesInTheirLanguage;

    protected $fillable = [
        'company_id', 'name', 'email', 'language', 'phone', 'job_title', 'is_primary',
        'portal_last_login_at', 'is_demo',
    ];

    protected $hidden = [
        'remember_token',
        'magic_link_token',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_demo' => 'boolean',
            'portal_last_login_at' => 'datetime',
            'magic_link_expires_at' => 'datetime',
        ];
    }

    /** How long a sign-in link stays usable. */
    public const MAGIC_LINK_MINUTES = 30;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Contacts with an email may request a portal magic link. */
    public function canUsePortal(): bool
    {
        return filled($this->email);
    }

    /** Auth identifier for magic-link emails. */
    public function getEmailForMagicLink(): string
    {
        return (string) $this->email;
    }

    /**
     * Issues a fresh sign-in token and returns the plain value for the link.
     *
     * Issuing one invalidates the previous link, so a second request from
     * someone who mislaid the first does not leave two working ways in.
     */
    public function issueMagicLinkToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'magic_link_token' => hash('sha256', $token),
            'magic_link_expires_at' => now()->addMinutes(self::MAGIC_LINK_MINUTES),
        ])->save();

        return $token;
    }

    /**
     * Spends the token, if it is this contact's and still live.
     *
     * The update is the check: one statement, so two requests arriving
     * together — a human and the mail scanner that opened the link first —
     * cannot both come back true.
     */
    public function consumeMagicLinkToken(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $spent = static::query()
            ->whereKey($this->getKey())
            ->where('magic_link_token', hash('sha256', $token))
            ->where('magic_link_expires_at', '>', now())
            ->update([
                'magic_link_token' => null,
                'magic_link_expires_at' => null,
            ]);

        if ($spent !== 1) {
            return false;
        }

        $this->forceFill(['magic_link_token' => null, 'magic_link_expires_at' => null])->syncOriginal();

        return true;
    }

    /**
     * Signing out should not leave a link in the inbox that still works.
     *
     * Keyed rather than read-then-write: the auth guard hands back the model
     * it loaded when the session was resolved, which may predate the token
     * being issued, and trusting that copy would skip the clear.
     */
    public function revokeMagicLinkToken(): void
    {
        static::query()->whereKey($this->getKey())->update([
            'magic_link_token' => null,
            'magic_link_expires_at' => null,
        ]);

        $this->forceFill(['magic_link_token' => null, 'magic_link_expires_at' => null])->syncOriginal();
    }
}
