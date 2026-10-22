<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentReceiptResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment, Request $request, PaymentReceiptResolver $receipts): Response
    {
        return $receipts->forPayment($payment, $request->user(), $request->boolean('view'));
    }
}
