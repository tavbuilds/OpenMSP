<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use App\Filament\Pages\Onboarding;
use App\Support\DemoAccount;
use App\Support\PlatformSettings;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use SensitiveParameter;

/**
 * First-run setup: available while no real administrator exists.
 * A view-only demo user does not close registration.
 */
class SetupAccount extends Register
{
    public function mount(): void
    {
        if (DemoAccount::hasRealOperator()) {
            $this->redirect(Filament::getLoginUrl());

            return;
        }

        parent::mount();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(#[SensitiveParameter] array $data): array
    {
        abort_if(DemoAccount::hasRealOperator(), 403);

        $data['role'] = UserRole::Admin->value;
        $data['is_demo'] = false;

        return $data;
    }

    public function getHeading(): string|Htmlable
    {
        return __('Create administrator account');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Welcome to :name. This is the first setup step. Next you choose the platform name and optionally email, Stripe, and an API token.', [
            'name' => PlatformSettings::name(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return Onboarding::getUrl();
    }
}
