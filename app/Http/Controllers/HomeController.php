<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Project;
use App\Models\SystemSetting;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $announcements = Announcement::query()->visibleToPublic()->latest('published_at')->limit(3)->get();
        $settings = SystemSetting::current();
        $projects = Project::query()->published()->with('images')->latest('project_date')->limit(12)->get()->map(fn ($project) => [
            'id' => $project->id, 'title' => $project->title, 'description' => $project->description, 'date' => $project->project_date->toDateString(),
            'images' => $project->images->map(fn ($image) => ['url' => route('projects.image', $image), 'alt' => $image->alt_text])->all(),
        ])->all();
        $branding = [
            'hoaName' => $settings->hoa_name,
            'contactEmail' => $settings->contact_email,
            'address' => $settings->address,
            'logoUrl' => $settings->logo_path ? route('branding.logo') : asset('images/HOA.png'),
        ];

        return view('landing.index', compact('announcements', 'branding', 'projects'));
    }
}
