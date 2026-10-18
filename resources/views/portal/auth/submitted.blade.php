<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $statusTitle }} | Southville Phase I HOA</title>
    <x-favicon />
    @vite(['resources/css/landing.css'])
</head>
<body class="hoa-landing">
    <main class="hoa-auth-shell">
        <div class="hoa-auth-overlay"></div>
        <section class="hoa-auth-card hoa-auth-card-confirmation" aria-labelledby="submitted-title">
            <a href="{{ route('home') }}" class="hoa-auth-brand" aria-label="Go to homepage">
                <span class="hoa-brand-mark" aria-hidden="true">S1</span>
                <span class="hoa-auth-brand-title">Southville Phase I</span>
            </a>
            <div class="hoa-status-icon" aria-hidden="true">{{ $status === 'approved' ? '✓' : ($status === 'rejected' ? '!' : '…') }}</div>
            <p class="hoa-eyebrow"><span></span>Application status</p>
            <h1 id="submitted-title">{{ $statusTitle }}</h1>
            <p class="hoa-auth-lead">{{ $statusLead }}</p>
            <div class="hoa-status-pill hoa-status-pill-{{ $status }}" role="status"><span></span>{{ $statusLabel }}</div>

            @if($registeredEmail)
                <p class="hoa-hint">Application email: <strong>{{ $registeredEmail }}</strong></p>
            @endif

            @if($approvedAt)
                <p class="hoa-hint">Approved on <strong>{{ $approvedAt->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A') }}</strong>.</p>
                <p class="hoa-auth-lead">You may now sign in using the email and password you registered with.</p>
                <a class="hoa-button hoa-button-primary hoa-button-block" href="{{ route('homeowner.login') }}">Continue to homeowner login</a>
            @elseif($feedback)
                <div class="hoa-auth-alert" role="alert">
                    <strong>Admin feedback</strong>
                    <p>{{ $feedback }}</p>
                </div>
                <p class="hoa-auth-lead">Please contact the HOA office for instructions on correcting and resubmitting your application.</p>
            @else
                <p class="hoa-auth-lead">Please check again after the HOA Admin has reviewed your registration and documents.</p>
                <a class="hoa-button hoa-button-primary hoa-button-block" href="{{ route('homeowner.login') }}">Return to login</a>
            @endif

            <a class="hoa-button hoa-button-secondary hoa-button-block" href="{{ $statusUrl }}">Refresh status</a>
        </section>
    </main>
</body>
</html>
