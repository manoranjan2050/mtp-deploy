<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class CreateApiTokenAction
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function handle(User $user, string $name, array $abilities): NewAccessToken
    {
        $token = $user->createToken($name, $abilities);

        activity('auth')
            ->causedBy($user)
            ->withProperties(['token_name' => $name, 'abilities' => $abilities])
            ->log('created an API token');

        return $token;
    }
}
