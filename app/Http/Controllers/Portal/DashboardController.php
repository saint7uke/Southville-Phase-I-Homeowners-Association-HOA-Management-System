<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $homeowner = request()->user()->homeowner()->withCount(['complaints', 'serviceRequests'])->firstOrFail();
        $announcements = Announcement::query()->published()->latest('published_at')->limit(4)->get();
        $payments = $homeowner->payments()->latest('payment_date')->limit(5)->get();

        return view('portal.dashboard', compact('homeowner', 'announcements', 'payments'));
    }
}
