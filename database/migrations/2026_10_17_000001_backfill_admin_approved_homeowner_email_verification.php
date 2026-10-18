<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->join('homeowners', 'homeowners.user_id', '=', 'users.id')
            ->where('users.account_status', 'Active')
            ->whereNotNull('users.approved_at')
            ->whereNotNull('users.approved_by')
            ->whereNull('users.email_verified_at')
            ->whereNull('users.deleted_at')
            ->whereNull('homeowners.deleted_at')
            ->select(['users.id', 'users.approved_at'])
            ->orderBy('users.id')
            ->each(function (object $user): void {
                if ($this->emailChangedAfterApproval((int) $user->id, (string) $user->approved_at)) {
                    return;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->whereNull('email_verified_at')
                    ->update(['email_verified_at' => $user->approved_at]);
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: a verification timestamp may have been
        // legitimately established after this migration ran.
    }

    private function emailChangedAfterApproval(int $userId, string $approvedAt): bool
    {
        return DB::table('audit_logs')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $userId)
            ->where('action', 'User.updated')
            ->where('created_at', '>', $approvedAt)
            ->orderBy('id')
            ->pluck('new_values')
            ->contains(function (mixed $payload): bool {
                if (is_array($payload)) {
                    return array_key_exists('email', $payload);
                }

                $decoded = json_decode((string) $payload, true);

                return is_array($decoded) && array_key_exists('email', $decoded);
            });
    }
};
