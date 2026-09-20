<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

class UserAdministration
{
    public static function isLastAdmin(User $user): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return User::query()
            ->where('role', UserRole::Admin)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }

    public static function canDelete(User $actor, User $target): bool
    {
        if (! $actor->canAdminister()) {
            return false;
        }

        if ($actor->is($target)) {
            return false;
        }

        return ! self::isLastAdmin($target);
    }

    public static function canChangeRole(User $target, UserRole $newRole): bool
    {
        if ($newRole === UserRole::Admin) {
            return true;
        }

        return ! self::isLastAdmin($target);
    }

    public static function resetPassword(User $target, string $password, bool $revokeTokens = true): void
    {
        $target->update(['password' => $password]);

        if ($revokeTokens) {
            self::revokeTokens($target);
        }
    }

    public static function revokeTokens(User $target): int
    {
        return $target->tokens()->delete();
    }

    public static function revokeAllTokens(): int
    {
        return PersonalAccessToken::query()->delete();
    }

    public static function createToken(User $user, string $name, array $abilities = ['*']): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Token name is required.');
        }

        return $user->createToken($name, $abilities === [] ? ['*'] : $abilities)->plainTextToken;
    }
}
