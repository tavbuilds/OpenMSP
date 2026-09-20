<?php

namespace App\Filament\Auth;

use App\Support\AuthLockout;
use App\Support\DemoAccount;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => DemoAccount::resolveLoginEmail((string) ($data['email'] ?? '')),
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $email = DemoAccount::resolveLoginEmail((string) ($this->data['email'] ?? ''));
        AuthLockout::assert(request(), $email);

        try {
            $response = parent::authenticate();
        } catch (ValidationException $e) {
            AuthLockout::hit(request(), $email);
            throw $e;
        }

        if (Filament::auth()->check()) {
            AuthLockout::clear(request(), $email);
        }

        return $response;
    }

    public function getSubheading(): string | Htmlable | null
    {
        if (DemoAccount::hasRealOperator()) {
            return null;
        }

        return new HtmlString(
            '<a href="'.e(url('/admin/register')).'" class="underline">'.e(__('First install — create an account')).'</a>'
        );
    }
}
