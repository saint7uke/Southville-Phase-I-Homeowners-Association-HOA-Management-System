<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\View\View;

final class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::query()->visibleToResidents()->latest('published_at')->paginate(10);

        return view('portal.announcements.index', compact('announcements'));
    }

    public function show(Announcement $announcement): View
    {
        abort_unless(Announcement::query()->visibleToResidents()->whereKey($announcement)->exists(), 404);

        return view('portal.announcements.show', compact('announcement'));
    }
}
