<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\PropertyAddressAvailability;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AddressAvailabilityController extends Controller
{
    public function __invoke(Request $request, PropertyAddressAvailability $availability): JsonResponse
    {
        $request->merge(['phase_suffix' => PropertyIdentity::suffix($request->input('phase_suffix'))]);
        $validated = $request->validate([
            'house_number' => ['prohibited'],
            'phase_suffix' => ['required', 'string', 'regex:/^[A-Z0-9]{1,10}$/D'],
            'street' => ['required', 'string', 'max:100'],
            'block' => ['required', 'string', 'max:20'],
            'lot' => ['required', 'string', 'max:20'],
            'ownership_type' => ['required', Rule::in(['Owner', 'Tenant', 'Co-owner'])],
        ]);

        $data = [
            ...PropertyAddress::normalizeForStorage($validated),
            'ownership_type' => $validated['ownership_type'],
        ];
        $available = $availability->available($data);

        return response()->json([
            'available' => $available,
            'message' => __($availability->message($data, $available)),
        ]);
    }
}
