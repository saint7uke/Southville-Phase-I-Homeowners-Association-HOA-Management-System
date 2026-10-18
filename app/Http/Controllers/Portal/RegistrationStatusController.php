<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

final class RegistrationStatusController extends Controller
{
    public function show(Request $request): View
    {
        $userId = (int) $request->query('user');
        abort_unless($userId > 0, 404);

        $user = User::query()->with('homeowner')->findOrFail($userId);
        if ($request->user() !== null && ! $request->user()->is($user)) {
            abort(404);
        }

        abort_unless($user->hasRole('homeowner') && $user->homeowner !== null, 404);

        return view('portal.auth.submitted', $this->statusData($request, $user));
    }

    public function authenticated(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('homeowner'), 403);

        $user->load('homeowner');
        abort_unless($user->homeowner !== null, 404);

        return view('portal.auth.submitted', $this->statusData($request, $user));
    }

    /** @return array<string, mixed> */
    private function statusData(Request $request, User $user): array
    {
        $accountStatus = UserAccountStatus::tryFrom((string) $user->account_status);
        $status = match ($accountStatus) {
            UserAccountStatus::Active => 'approved',
            UserAccountStatus::Rejected => 'rejected',
            default => 'pending',
        };

        $statusUrl = $request->routeIs('homeowner.register.submitted')
            ? $request->fullUrl()
            : URL::temporarySignedRoute(
                'homeowner.register.submitted',
                now()->addHours(24),
                ['user' => $user->id],
                false,
            );

        return [
            'status' => $status,
            'statusLabel' => match ($status) {
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                default => 'Pending verification',
            },
            'statusTitle' => match ($status) {
                'approved' => 'Your application is approved',
                'rejected' => 'Action is needed on your application',
                default => 'Your application is under review',
            },
            'statusLead' => match ($status) {
                'approved' => 'Your homeowner registration and verification have been approved by the HOA Admin.',
                'rejected' => 'The HOA Admin could not approve your application in its current form.',
                default => 'Your application is awaiting verification by the HOA Admin.',
            },
            'feedback' => $status === 'rejected' ? $user->rejection_reason : null,
            'approvedAt' => $status === 'approved' ? $user->approved_at : null,
            'statusUrl' => $statusUrl,
            'registeredEmail' => $user->email,
        ];
    }
}
