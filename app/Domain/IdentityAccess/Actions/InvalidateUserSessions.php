<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvalidateUserSessions
{
    public function execute(User $user): void
    {
        $persistedUser = $this->persistedUser($user);

        DB::transaction(function () use ($persistedUser): void {
            $persistedUser->forceFill(['remember_token' => str()->random(60)])->save();
            DB::table('sessions')->where('user_id', $persistedUser->getKey())->delete();
        });
    }

    private function persistedUser(User $user): User
    {
        $keyName = $user->getKeyName();
        $key = $user->getKey();
        $originalKey = $user->getRawOriginal($keyName);

        if (! $user->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persistedUser = User::query()->whereKey($originalKey)->first();

        if ($persistedUser === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persistedUser;
    }
}
