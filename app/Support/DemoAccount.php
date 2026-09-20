<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class DemoAccount
{
    public const USERNAME = 'test';

    public const PASSWORD = 'test';

    public const EMAIL = 'test@demo.local';

    public const NAME = 'Demo';

    public static function resolveLoginEmail(string $input): string
    {
        $v = strtolower(trim($input));
        if (in_array($v, [self::USERNAME, 'demo', self::EMAIL], true)) {
            return self::EMAIL;
        }

        return trim($input);
    }

    public static function hasRealOperator(): bool
    {
        if (! Schema::hasTable('users')) {
            return false;
        }

        return User::query()->real()->exists();
    }

    public static function exists(): bool
    {
        if (! Schema::hasTable('users')) {
            return false;
        }

        return User::query()->where('is_demo', true)->exists()
            || User::query()->where('email', self::EMAIL)->exists();
    }

    public static function ensure(): User
    {
        if ($existing = User::query()->where('email', self::EMAIL)->first()) {
            $existing->forceFill([
                'name' => self::NAME,
                'role' => UserRole::Viewer,
                'is_demo' => true,
            ])->save();

            return $existing;
        }

        return User::query()->create([
            'name' => self::NAME,
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
            'role' => UserRole::Viewer,
            'is_demo' => true,
            'email_verified_at' => now(),
        ]);
    }

    public static function purge(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        User::query()
            ->where(function ($query): void {
                $query->where('is_demo', true)->orWhere('email', self::EMAIL);
            })
            ->get()
            ->each(function (User $user): void {
                $user->tokens()->delete();
                $user->delete();
            });
    }

    /** Public demo deploy: keep the view-only login until a real admin exists. */
    public static function ensureIfConfigured(): void
    {
        if (! config('app.demo_login')) {
            return;
        }
        if (self::hasRealOperator()) {
            self::purge();

            return;
        }
        if (! Schema::hasTable('users') || ! Schema::hasTable('companies')) {
            return;
        }
        DemoData::seed();
        self::ensure();
    }
}
