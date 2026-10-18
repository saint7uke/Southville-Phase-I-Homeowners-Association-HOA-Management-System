<?php

declare(strict_types=1);

use App\Support\PersonName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeowners', function (Blueprint $table): void {
            $table->foreignId('landlord_homeowner_id')
                ->nullable()
                ->after('landlord_contact')
                ->constrained('homeowners')
                ->nullOnDelete();
        });

        DB::table('homeowners')
            ->where('ownership_type', 'Tenant')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($tenants): void {
                foreach ($tenants as $tenant) {
                    $candidates = DB::table('homeowners as landlords')
                        ->join('users', 'users.id', '=', 'landlords.user_id')
                        ->where('landlords.address_key', $tenant->address_key)
                        ->whereIn('landlords.ownership_type', ['Owner', 'Co-owner'])
                        ->where('landlords.status', 'Active')
                        ->whereNull('landlords.deleted_at')
                        ->where('users.account_status', 'Active')
                        ->whereNull('users.deleted_at')
                        ->get([
                            'landlords.id',
                            'users.first_name',
                            'users.middle_name',
                            'users.last_name',
                            'users.suffix',
                        ])
                        ->filter(function ($candidate) use ($tenant): bool {
                            $fullName = collect([
                                $candidate->first_name,
                                $candidate->middle_name,
                                $candidate->last_name,
                                $candidate->suffix,
                            ])->filter(fn ($part): bool => filled($part) && $part !== 'N/A')->implode(' ');

                            return PersonName::key($fullName) === PersonName::key($tenant->landlord_name);
                        });

                    if ($candidates->count() === 1) {
                        DB::table('homeowners')->where('id', $tenant->id)->update([
                            'landlord_homeowner_id' => $candidates->first()->id,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('homeowners', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('landlord_homeowner_id');
        });
    }
};
