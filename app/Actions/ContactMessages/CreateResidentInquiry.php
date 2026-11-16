<?php

declare(strict_types=1);

namespace App\Actions\ContactMessages;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CreateResidentInquiry
{
    public static function eligible(?User $user): bool
    {
        return $user !== null && ! $user->trashed() && $user->account_status === 'Active'
            && $user->hasVerifiedEmail() && $user->hasRole('homeowner')
            && ! $user->hasAnyRole(['hoa_admin', 'hoa_staff']) && $user->can('submit_own_complaint')
            && $user->homeowner()->exists();
    }

    public function handle(User $actor, array $data): ContactMessage
    {
        return DB::transaction(function () use ($actor, $data): ContactMessage {
            $actor = User::lockForUpdate()->findOrFail($actor->id);
            abort_unless(self::eligible($actor), 403);
            $validated = Validator::make($data, [
                'subject' => ['required', 'string', 'max:160'],
                'message' => ['required', 'string', 'max:5000', 'not_regex:/\A\s*\z/u'],
            ])->validate();

            return ContactMessage::create([
                'user_id' => $actor->id, 'name' => $actor->full_name, 'email' => $actor->email,
                'phone' => $actor->contact_number, 'subject' => trim($validated['subject']),
                'message' => trim($validated['message']), 'status' => 'New',
            ]);
        });
    }
}
