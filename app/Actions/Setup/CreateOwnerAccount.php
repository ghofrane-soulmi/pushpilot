<?php

namespace App\Actions\Setup;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates the single owner account on first run (AUTH-2).
 *
 * A cache lock serialises concurrent submissions so two requests racing on a
 * fresh install can never both create an owner.
 */
final class CreateOwnerAccount
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        return Cache::lock('pushpilot:first-run-setup', 10)->block(5, function () use ($attributes): User {
            return DB::transaction(function () use ($attributes): User {
                if (self::isComplete()) {
                    throw new NotFoundHttpException;
                }

                $user = new User([
                    'name' => $attributes['name'],
                    'email' => $attributes['email'],
                    'password' => $attributes['password'],
                ]);
                $user->role = UserRole::Owner;
                $user->save();

                return $user;
            });
        });
    }

    public static function isComplete(): bool
    {
        return User::query()->exists();
    }
}
