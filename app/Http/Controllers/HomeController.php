<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\SystemSetting;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $announcements = Announcement::query()->visibleToPublic()->latest('published_at')->limit(3)->get();
        $settings = SystemSetting::current();
        $branding = [
            'hoaName' => $settings->hoa_name,
            'contactEmail' => $settings->contact_email,
            'address' => $settings->address,
            'logoUrl' => $settings->logo_path ? route('branding.logo') : asset('images/HOA.png'),
        ];

        return view('landing.index', compact('announcements', 'branding'));
    }
}
