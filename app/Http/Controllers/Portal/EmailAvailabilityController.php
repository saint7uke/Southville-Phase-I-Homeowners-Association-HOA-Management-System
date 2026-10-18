<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\UserEmailReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EmailAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            throw ValidationException::withMessages([
                'email' => __('Enter a valid email address.'),
            ]);
        }

        return response()->json([
            'available' => ! UserEmailReservation::query()->where('email', $email)->exists(),
        ]);
    }
}
