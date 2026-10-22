<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class InviteManagedUser
{
    public function handle(array $data, User $actor): User
    {
        if ($actor->trashed() || $actor->account_status !== 'Active' || ! $actor->hasRole('hoa_admin') || ! $actor->can('manage_users')) {
            throw new AuthorizationException;
        }
        $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')));
        Validator::make($data, [
            'email' => ['required', 'email:rfc', Rule::unique('users', 'email'), Rule::unique('user_email_reservations', 'email')],
        ])->validate();
        $data['password'] = 'Aa1!'.Str::password(64);

        return DB::transaction(function () use ($data, $actor): User {
            $user = app(CreatePendingUser::class)->handle($data, $actor);
            DB::afterCommit(fn () => Password::broker()->sendResetLink(['email' => $user->email]));

            return $user;
        });
    }
}
