<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreServiceRequest;
use App\Models\Certificate;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ServiceRequestController extends Controller
{
    public function index(): View
    {
        $requests = request()->user()->homeowner->serviceRequests()->with('certificate')->latest()->paginate(10);
        $standalone = Certificate::whereNull('service_request_id')->whereHas('homeowner', fn ($query) => $query->where('user_id', request()->user()->id))->latest('issued_at')->get();

        return view('portal.requests.index', compact('requests', 'standalone'));
    }

    public function create(): View
    {
        return view('portal.requests.create');
    }

    public function store(StoreServiceRequest $request, CreateServiceRequest $action): RedirectResponse
    {
        $serviceRequest = $action->handle($request->user()->homeowner, $request->safe()->except('attachments'), $request->file('attachments', []), $request->user());

        return redirect()->route('portal.requests.show', $serviceRequest)->with('success_title', __('Service Request Submitted Successfully!'))->with('success', __('Request submitted successfully.'));
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        abort_unless($serviceRequest->homeowner_id === request()->user()->homeowner->id, 404);
        $serviceRequest->load('caseAttachments', 'certificate');

        return view('portal.requests.show', compact('serviceRequest'));
    }
}
