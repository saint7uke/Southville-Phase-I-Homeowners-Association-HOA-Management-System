<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CheckEmailController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255'], 'exclude_id' => ['nullable', 'integer', Rule::exists('users', 'id')]]);
        $exists = User::withTrashed()->where('email', mb_strtolower(trim($data['email'])))->when($data['exclude_id'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))->exists();

        return response()->json(['data' => ['available' => ! $exists]]);
    }
}
