<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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

        foreach ([User::class, Homeowner::class, Payment::class, Complaint::class, ServiceRequest::class, Announcement::class] as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
