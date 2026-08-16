<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

final class AnnouncementController extends Controller
{
    public function latest(): JsonResponse
    {
        $items = Announcement::query()->published()->latest('published_at')->limit(5)->get(['id', 'title', 'content', 'category', 'published_at']);

        return response()->json(['data' => $items]);
    }
}
