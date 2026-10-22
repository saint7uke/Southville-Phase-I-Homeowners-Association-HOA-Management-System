<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaymentDocument;
use App\Services\ButawReceipts;
use App\Services\ButawReceiptSource;
use App\Services\OriginalCertificateRenderer;
use App\Services\PaymentReceiptResolver;
use App\Support\AuthenticatedActor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ButawReceiptController extends Controller
{
    public function reference(ButawReceiptSource $source)
    {
        try {
            $bytes = $source->review(app(AuthenticatedActor::class)->user());
        } catch (ValidationException $exception) {
            abort(409, $exception->validator->errors()->first());
        }

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="resibo-butaw-source-review.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(string $token, ButawReceipts $receipts)
    {
        try {
            $preview = $receipts->read($token, app(AuthenticatedActor::class)->user());
            $bytes = app(OriginalCertificateRenderer::class)->render($preview->snapshot['layout'], $preview->snapshot['values'], true);
        } catch (ValidationException $exception) {
            abort(410, __('The receipt preview is unavailable. Return to Certificates and generate a fresh preview.'));
        }

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="resibo-butaw-draft.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function download(PaymentDocument $paymentDocument, Request $request, PaymentReceiptResolver $receipts)
    {
        return $receipts->forDocument($paymentDocument, $request->user(), $request->boolean('view'));
    }
}
