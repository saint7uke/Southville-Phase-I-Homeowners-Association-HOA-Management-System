<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Homeowner;
use App\Models\User;
use App\Validation\HomeownerProfileValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ProfileAvailabilityController extends Controller
{
    public function __invoke(Request $request, HomeownerProfileValidator $profileValidator): JsonResponse
    {
        $user = $request->user('homeowner');
        abort_unless($user instanceof User && $user->can('update_own_profile'), 404);

        $homeowner = $user->homeowner;
        abort_unless($homeowner instanceof Homeowner, 404);

        $validator = Validator::make($request->all(), [
            'field' => ['required', 'in:email'],
            'email' => ['required_if:field,email', 'nullable', 'string', 'max:255'],
            'house_number' => ['prohibited'],
            'street' => ['prohibited'],
            'block' => ['prohibited'],
            'lot' => ['prohibited'],
        ], [], $profileValidator->attributes());

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();
        $result = $profileValidator->emailAvailability($user, $homeowner, $data['email']);

        return response()->json($result);
    }
}
