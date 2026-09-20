<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Portal-authenticatable contact person for a company.
 * Magic-link login only (no password). Separate from Filament admin Users.
 */
class Contact extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'company_id', 'name', 'email', 'phone', 'job_title', 'is_primary',
        'portal_last_login_at', 'is_demo',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_demo' => 'boolean',
            'portal_last_login_at' => 'datetime',
        ];
    }

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
}
