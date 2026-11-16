<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Sms\SmsEnrollment;
use App\Support\PhilippineMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class SmsVerificationController extends Controller
{
    private function actor(Request $request): ?User
    {
        $request->validate(['scope' => ['required', 'in:registration,profile']]);
        $user = Auth::guard('homeowner')->user() ?? Auth::guard('web')->user() ?? Auth::guard('admin')->user() ?? Auth::guard('staff')->user();
        abort_if($request->input('scope') === 'registration', 403);
        abort_unless($user instanceof User && ! $user->trashed() && $user->account_status === 'Active'
            && $user->hasRole('homeowner') && $user->can('update_own_profile'), 403);

        return $user;
    }

    public function request(Request $request, SmsEnrollment $enrollment): JsonResponse
    {
        $user = $this->actor($request);
        if (! $user) {
            $request->validate(['contact_number' => PhilippineMobile::inputRules()], [
                'contact_number.regex' => __('Enter an 11-digit Philippine mobile number beginning with 09.'),
            ]);
        }
        $number = $user?->contact_number ?? $request->input('contact_number');
        $verification = $enrollment->request($number, $enrollment->binding($user), (string) $request->ip());

        return response()->json(['verification_id' => $verification->id, 'masked_number' => PhilippineMobile::mask($verification->phone),
            'message' => __('Verification SMS Queued Successfully!'), 'expires_in' => 300, 'resend_after' => 60], 202);
    }

    public function confirm(Request $request, SmsEnrollment $enrollment): JsonResponse
    {
        $user = $this->actor($request);
        $data = $request->validate(['verification_id' => ['required', 'uuid'], 'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $enrollment->confirm($data['verification_id'], $data['code'], $enrollment->binding($user));

        return response()->json(['verified' => true, 'message' => __('Mobile Number Verified Successfully!')]);
    }

    public function preferences(Request $request, SmsEnrollment $enrollment): JsonResponse
    {
        $user = $this->actor($request);
        abort_unless($user instanceof User, 403);
        DB::transaction(function () use ($user, $request, $enrollment): void {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            $enrollment->apply($locked, $request->all(), $enrollment->binding($locked));
        });

        return response()->json(['saved' => true, 'message' => __('SMS Preferences Updated Successfully!')]);
    }
}
