<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Resident login | Southville Phase I HOA</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body><main class="auth-shell"><section class="auth-card" aria-labelledby="login-title">
    <a class="auth-brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">S1</span>Southville Phase I</a>
    <h1 id="login-title">Resident login</h1><p>Access your requests, complaints, payments, and community updates.</p>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="error-summary" role="alert" tabindex="-1"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('portal.login.store') }}">@csrf
        <div class="form-grid">
            <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email', session('registered_email')) }}" required autocomplete="email" aria-describedby="email-error" @error('email') aria-invalid="true" @enderror>@error('email')<p id="email-error" class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password" aria-describedby="password-error" @error('password') aria-invalid="true" @enderror>@error('password')<p id="password-error" class="error">{{ $message }}</p>@enderror</div>
            <label><input name="remember" type="checkbox" value="1"> Keep me signed in on this device</label>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit">Log in</button><a href="{{ route('portal.register') }}">Register as resident</a></div>
    </form>
</section></main></body></html>
