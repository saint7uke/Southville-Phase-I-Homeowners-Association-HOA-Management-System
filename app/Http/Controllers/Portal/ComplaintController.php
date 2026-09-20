<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Complaints\CreateComplaint;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ComplaintController extends Controller
{
    public function index(): View
    {
        $complaints = request()->user()->homeowner->complaints()->latest()->paginate(10);

        return view('portal.complaints.index', compact('complaints'));
    }

    public function create(): View
    {
        return view('portal.complaints.create');
    }

    public function store(StoreComplaintRequest $request, CreateComplaint $action): RedirectResponse
    {
        $files = $request->file('attachments', []);
        if ($request->hasFile('attachment')) {
            $files[] = $request->file('attachment');
        }
        $complaint = $action->handle($request->user()->homeowner, $request->safe()->except(['attachment', 'attachments']), $files, $request->user());

        return redirect()->route('portal.complaints.show', $complaint)->with('success', __('Complaint submitted successfully.'));
    }

    public function show(Complaint $complaint): View
    {
        abort_unless($complaint->homeowner_id === request()->user()->homeowner->id, 404);
        $complaint->load('caseAttachments');

        return view('portal.complaints.show', compact('complaint'));
    }
}
