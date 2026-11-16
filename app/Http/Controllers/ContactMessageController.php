<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ContactMessages\CreateResidentInquiry;
use App\Http\Requests\StoreContactMessageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ContactMessageController extends Controller
{
    public function __invoke(StoreContactMessageRequest $request): RedirectResponse|JsonResponse
    {
        $contact = app(CreateResidentInquiry::class)->handle($request->user(), $request->validated());

        $message = __('Thank you. The HOA office has received your message.');

        return $request->expectsJson()
            ? response()->json(['data' => ['message' => $message, 'reference' => $contact->reference]], 201)
            : redirect('/homeowner/my-concerns')->with(['success' => $message, 'success_title' => __('Inquiry Submitted Successfully!')]);
    }
}
