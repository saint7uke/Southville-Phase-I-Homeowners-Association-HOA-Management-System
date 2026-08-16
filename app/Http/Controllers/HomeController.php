<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $announcements = Announcement::query()->published()->latest('published_at')->limit(3)->get();

        return view('landing.index', compact('announcements'));
    }
}
