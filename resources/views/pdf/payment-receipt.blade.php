<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #13293d; font-size: 11px; }
        .header { border-bottom: 3px solid #0b5f8a; padding-bottom: 14px; }
        .brand { color: #0b5f8a; font-size: 20px; font-weight: bold; }
        .sub { color: #4c6575; margin-top: 4px; }
        h1 { margin: 28px 0 6px; font-size: 22px; }
        .receipt-number { color: #0b5f8a; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { width: 36%; text-align: left; background: #eaf4f8; color: #23485e; }
        th, td { padding: 10px; border: 1px solid #c6d9e3; vertical-align: top; }
        .amount { margin-top: 24px; padding: 18px; background: #eaf4f8; border-left: 5px solid #0b5f8a; }
        .amount strong { display: block; font-size: 22px; color: #0b5f8a; margin-top: 5px; }
        .footer { position: fixed; bottom: -10mm; color: #5b6f7d; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Southville Phase I Homeowners Association</div>
        <div class="sub">Brgy. Inocencio - Official payment receipt</div>
    </div>

    <h1>Payment Receipt</h1>
    <p class="receipt-number">Official receipt: {{ $payment->or_number }}</p>

    <table>
        <tr><th>Resident</th><td>{{ $payment->homeowner->user->full_name }}</td></tr>
        <tr><th>Property</th><td>{{ $payment->homeowner->full_address }}</td></tr>
        <tr><th>Dues type</th><td>{{ $payment->duesSetting->name }}</td></tr>
        <tr><th>Covered period</th><td>{{ $payment->covered_period }}</td></tr>
        <tr><th>Payment date</th><td>{{ $payment->payment_date->format('F d, Y') }}</td></tr>
        <tr><th>Method</th><td>{{ $payment->payment_method }}</td></tr>
        <tr><th>Reviewed by</th><td>{{ $payment->reviewer?->full_name ?? $payment->recorder?->full_name ?? 'HOA Office' }}</td></tr>
    </table>

    <div class="amount">
        Amount received
        <strong>PHP {{ number_format((float) $payment->amount_paid, 2) }}</strong>
        @if($payment->balance !== '0.00') Remaining balance after this payment: PHP {{ number_format((float) $payment->balance, 2) }} @endif
    </div>

    <p class="footer">Generated {{ now()->format('F d, Y h:i A') }}. This document is system-generated and valid only with its official receipt number.</p>
</body>
</html>
