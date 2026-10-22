<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Support\OperationalAccess;
use App\Support\PropertyIdentity;
use Barryvdh\DomPDF\Facade\Pdf;

final class HouseholdPdfController extends Controller
{
    public function __invoke(Household $household)
    {
        $actor = request()->user();
        $manager = OperationalAccess::allows($actor, 'manage_homeowners');
        $resident = $actor?->homeowner;
        abort_unless($manager || ($actor?->hasRole('homeowner') && $actor->hasVerifiedEmail() && $resident && PropertyIdentity::same($resident, $household)
            && HouseholdMember::query()->where('household_id', $household->id)->where('current_homeowner_id', $resident->id)->exists()), 404);
        $members = $household->members()->whereNull('ended_at')->get($manager
            ? ['full_name', 'relationship', 'date_of_birth', 'sex', 'residency_status']
            : ['full_name', 'relationship', 'residency_status']);

        return Pdf::loadView('pdf.household', compact('household', 'members', 'manager'))->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->download('hoa-household-'.$household->identifier.'.pdf')->header('Cache-Control', 'private, no-store');
    }
}
