<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\ActionFeedback;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;

final class PanelLogoutResponse implements LogoutResponse
{
    public function toResponse($request)
    {
        return redirect()->route('login')->with('action_feedback', ActionFeedback::flash('success', 'Logged Out Successfully!', ''));
    }
}
