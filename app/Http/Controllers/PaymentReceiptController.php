<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        Gate::forUser(request()->user())->authorize('view', $payment);
        abort_unless(in_array($payment->review_status, ['Approved', 'Recorded'], true) && in_array($payment->status, ['Paid', 'Partial'], true), 404);

        $payment->loadMissing(['homeowner.user', 'payer.user', 'duesSetting', 'recorder', 'reviewer']);

        return Pdf::loadView('pdf.payment-receipt', ['payment' => $payment])
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])
            ->download("hoa-receipt-{$payment->or_number}.pdf");
    }
}
