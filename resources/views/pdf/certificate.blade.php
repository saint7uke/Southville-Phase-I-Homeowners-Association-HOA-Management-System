<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28mm 22mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #13293d; font-size: 12px; line-height: 1.7; }
        .certificate-header { position: relative; min-height: 78px; margin-bottom: 28px; padding: 8px 82px 0; }
        .certificate-logo { position: absolute; top: 0; left: 0; width: 72px; height: 72px; object-fit: contain; }
        .association { text-align: center; color: #0b5f8a; font-size: 16px; font-weight: bold; }
        .community { text-align: center; color: #4c6575; margin-top: 4px; }
        h1 { text-align: center; letter-spacing: 1px; font-size: 24px; margin-bottom: 34px; }
        .body { margin: 0 10mm; }
        .number { margin-top: 42px; color: #4c6575; font-size: 10px; }
        .signature { margin-top: 62px; width: 48%; border-top: 1px solid #13293d; text-align: center; padding-top: 6px; }
    </style>
</head>
<body>
    <header class="certificate-header">
        <img class="certificate-logo" src="{{ $logoDataUri }}" alt="Southville Phase I Homeowners Association logo">
        <div class="association">{{ mb_strtoupper($settings->hoa_name ?? 'Southville Phase I Homeowners Association') }}</div>
        <div class="community">{{ $settings->address ?? 'Brgy. Inocencio' }}</div>
    </header>
    <h1>{{ mb_strtoupper($certificate->type) }}</h1>
    <div class="body">
        <p>To whom it may concern:</p>
        <p>This is to certify that <strong>{{ $certificate->homeowner->user->full_name }}</strong>, resident of <strong>{{ $certificate->homeowner->full_address }}</strong>, is recorded with the Southville Phase I Homeowners Association.</p>
        @if($certificate->purpose)<p>This certificate is issued upon the resident's request for <strong>{{ $certificate->purpose }}</strong>.</p>@endif
        <p>Issued this {{ $certificate->issued_at->format('jS') }} day of {{ $certificate->issued_at->format('F Y') }}.</p>
        <div class="signature">{{ $certificate->issuer->full_name }}<br><span style="font-size:10px">Authorized HOA representative</span></div>
        <p class="number">Certificate no.: {{ $certificate->certificate_number }}@if($certificate->expires_at) · Valid until: {{ $certificate->expires_at->format('F d, Y') }}@endif</p>
    </div>
</body>
</html>
