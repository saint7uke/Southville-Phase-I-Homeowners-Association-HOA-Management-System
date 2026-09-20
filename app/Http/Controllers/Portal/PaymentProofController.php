<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Payments\SubmitPaymentProof;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SubmitPaymentProofRequest;
use App\Models\DuesObligation;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentProofController extends Controller
{
    public function store(SubmitPaymentProofRequest $request, DuesObligation $duesObligation, SubmitPaymentProof $submit): RedirectResponse
    {
        abort_unless($request->user()->homeowner?->id === $duesObligation->homeowner_id, 404);
        $submit->handle($request->user(), $duesObligation, $request->validated(), $request->file('proof'));

        return back()->with('success', __('Payment proof submitted for staff review. Your balance changes only after approval.'));
    }

    public function download(Payment $payment): StreamedResponse
    {
        abort_unless(request()->user()->can('view', $payment), 404);
        abort_unless(filled($payment->proof_path) && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path, $payment->proof_original_name ?: 'payment-proof');
    }
}
