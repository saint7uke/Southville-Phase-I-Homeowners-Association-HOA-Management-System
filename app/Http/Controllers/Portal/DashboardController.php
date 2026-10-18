<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Payment;
use App\Services\PropertyDuesAccounts;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $homeowner = request()->user()->homeowner()->withCount(['complaints', 'serviceRequests'])->firstOrFail();
        $announcements = Announcement::query()->visibleToResidents()->latest('published_at')->limit(4)->get();
        $payments = Payment::query()->where(fn ($query) => $query
            ->where('payer_homeowner_id', $homeowner->id)
            ->orWhere('homeowner_id', $homeowner->id))
            ->latest('payment_date')->limit(5)->get();
        $liable = app(PropertyDuesAccounts::class)->primaryForAddress($homeowner->address_key);
        $duesObligations = ($liable && app(PropertyDuesAccounts::class)->payerIsEligible($homeowner->loadMissing('user'), $liable->loadMissing('user'))
            ? $liable->duesObligations()
            : $homeowner->duesObligations())
            ->with('duesSetting')
            ->whereIn('status', ['Pending', 'Partial', 'Overdue'])
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        return view('portal.dashboard', compact('homeowner', 'announcements', 'payments', 'duesObligations'));
    }
}
