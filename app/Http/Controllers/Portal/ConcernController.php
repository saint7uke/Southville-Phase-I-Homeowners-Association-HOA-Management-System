<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Complaints\CreateComplaint;
use App\Actions\ContactMessages\CreateResidentInquiry;
use App\Http\Controllers\Controller;
use App\Services\ResidentConcerns;
use Illuminate\Http\Request;

final class ConcernController extends Controller
{
    public function index(Request $request, ResidentConcerns $service)
    {
        $request->validate([
            'type' => ['nullable', 'in:Complaint,Inquiry'],
            'status' => ['nullable', 'in:Pending,Under Review,Resolved,Rejected,New,In Progress,Awaiting Resident,Closed'],
            'page' => ['nullable', 'integer', 'min:1'],
            'concern' => ['nullable', 'string', 'regex:/\A(Complaint|Inquiry):[1-9][0-9]*\z/'],
        ]);

        return view('portal.concerns', [
            'concerns' => $service->listing($request->user(), (string) $request->query('type', ''), (string) $request->query('status', ''), (int) $request->query('page', 1)),
            'detail' => $request->filled('concern') ? $service->detail($request->user(), (string) $request->query('concern')) : null,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(CreateResidentInquiry::eligible($request->user()), 403);
        $data = $request->validate(['kind' => ['required', 'in:Complaint,Inquiry']]);
        if ($data['kind'] === 'Inquiry') {
            $record = app(CreateResidentInquiry::class)->handle($request->user(), $request->all());
            $title = 'Inquiry Submitted Successfully!';
        } else {
            $record = app(CreateComplaint::class)->handle($request->user()->homeowner, $request->except(['_token', 'kind', 'attachments']), $request->file('attachments', []), $request->user());
            $title = 'Complaint Submitted Successfully!';
        }

        return redirect()->route('portal.concerns.index', ['concern' => $data['kind'].':'.$record->id])
            ->with('success_title', __($title))->with('success', __($title));
    }
}
