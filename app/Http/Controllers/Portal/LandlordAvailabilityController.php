<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\LandlordMatcher;
use App\Support\PersonName;
use App\Support\PropertyAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LandlordAvailabilityController extends Controller
{
    public function __invoke(Request $request, LandlordMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'landlord_name' => ['required', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"],
            'house_number' => ['required', 'string', 'max:50'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['required', 'string', 'max:20'],
            'lot' => ['required', 'string', 'max:20'],
        ]);

        $data = [
            ...PropertyAddress::normalizeForStorage($validated),
            'landlord_name' => PersonName::normalize($validated['landlord_name']),
        ];
        $match = $matcher->match($data);

        return response()->json([
            'valid' => $match['valid'],
            'message' => __($match['message']),
        ]);
    }
}
