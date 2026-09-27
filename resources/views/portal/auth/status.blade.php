<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account status | Southville Phase I HOA</title>
    <x-favicon />
    @vite(['resources/css/app.css'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main id="main-content" class="auth-shell" tabindex="-1">
        <section class="auth-card" aria-labelledby="status-heading">
            <a class="auth-brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">S1</span>Southville Phase I</a>
            <h1 id="status-heading">Account status</h1>
            @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif

            @if (auth()->user()->account_status === 'Active' && ! auth()->user()->hasVerifiedEmail())
                <p>Verify your email address before continuing. Use the verification link sent to <strong>{{ auth()->user()->email }}</strong>.</p>
                <form method="POST" action="{{ route('portal.verification.resend') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">Resend verification email</button>
                </form>
            @else
                @switch(auth()->user()->account_status)
                    @case('Pending')
                        <p>Your registration is awaiting HOA Admin review. You will be able to use the portal after approval.</p>
                        @break
                    @case('Rejected')
                        <p>Your registration was not approved.</p>
                        @if(auth()->user()->rejection_reason)<div class="alert alert-error"><strong>Reason:</strong> {{ auth()->user()->rejection_reason }}</div>@endif
                        @break
                    @default
                        <p>This account is currently inactive. Contact the HOA office for assistance.</p>
                @endswitch
            @endif

            <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="button button-primary" type="submit">Log out</button></form>
        </section>
    </main>
</body>
</html>
