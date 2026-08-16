<?php

namespace App\Providers;

use App\Events\UserAccountStatusChanged;
use App\Listeners\RecordAuthenticationActivity;
use App\Listeners\RecordSuccessfulLogin;
use App\Listeners\SendAccountStatusChangedNotification;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by((string) $request->ip()),
        ]);
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip())));

        Event::listen(Login::class, RecordSuccessfulLogin::class);
        Event::listen(Login::class, RecordAuthenticationActivity::class);
        Event::listen(Failed::class, RecordAuthenticationActivity::class);
        Event::listen(Logout::class, RecordAuthenticationActivity::class);
        Event::listen(PasswordReset::class, RecordAuthenticationActivity::class);
        Event::listen(UserAccountStatusChanged::class, SendAccountStatusChangedNotification::class);

        foreach ([User::class, Homeowner::class, Payment::class, Complaint::class, ServiceRequest::class, Announcement::class] as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
