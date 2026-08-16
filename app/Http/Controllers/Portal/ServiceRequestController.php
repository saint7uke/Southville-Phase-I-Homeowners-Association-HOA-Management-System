<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreServiceRequest;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ServiceRequestController extends Controller
{
    public function index(): View
    {
        $requests = request()->user()->homeowner->serviceRequests()->latest()->paginate(10);

        return view('portal.requests.index', compact('requests'));
    }

    public function create(): View
    {
        return view('portal.requests.create');
    }

    public function store(StoreServiceRequest $request, CreateServiceRequest $action): RedirectResponse
    {
        $serviceRequest = $action->handle($request->user()->homeowner, $request->validated());

        return redirect()->route('portal.requests.show', $serviceRequest)->with('success', __('Request submitted successfully.'));
    }

    public function show(ServiceRequest $serviceRequest): View
    {
        abort_unless($serviceRequest->homeowner_id === request()->user()->homeowner->id, 404);

        return view('portal.requests.show', compact('serviceRequest'));
    }
}
