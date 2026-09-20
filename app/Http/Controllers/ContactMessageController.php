<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ContactMessageController extends Controller
{
    public function __invoke(StoreContactMessageRequest $request): RedirectResponse|JsonResponse
    {
        ContactMessage::query()->create([
            ...$request->safe()->except('website'),
            'ip_address' => $request->ip(),
            'status' => 'New',
        ]);

        $message = __('Thank you. The HOA office has received your message.');

        return $request->expectsJson()
            ? response()->json(['data' => ['message' => $message]], 201, ['Location' => url('/#contact')])
            : redirect('/#contact')->with('contact_success', $message);
    }
}
