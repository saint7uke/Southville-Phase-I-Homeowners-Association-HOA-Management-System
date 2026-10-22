<?php

namespace App\Providers;

use App\Events\UserAccountStatusChanged;
use App\Listeners\RecordAuthenticationActivity;
use App\Listeners\RecordSuccessfulLogin;
use App\Listeners\SendAccountStatusChangedNotification;
use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Payment;
use App\Models\PaymentDocument;
use App\Models\PaymentDocumentSource;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Observers\WorkflowNotificationObserver;
use App\Services\HouseholdDirectory;
use App\Services\PaymentReceiptResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->app->singleton(HouseholdDirectory::class);
        $this->app->scoped(PaymentReceiptResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentView::registerRenderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): View => view('components.locale-switcher'));
        FilamentView::registerRenderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): ?View => filament()->auth()->check() ? null : view('components.locale-toolbar'));
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.hooks.locale-meta'));
        Field::configureUsing(fn ($field) => $field->translateLabel());
        Column::configureUsing(fn ($column) => $column->translateLabel());
        Action::configureUsing(fn ($action) => $action->translateLabel());
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): View => view('filament.hooks.accessible-table-scroll-regions'),
        );

        Model::preventLazyLoading(! app()->isProduction());
        Project::observe(AuditObserver::class);
        ProjectImage::observe(AuditObserver::class);
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Password::defaults(fn () => Password::min(12)->mixedCase()->letters()->numbers()->symbols());

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
        Event::listen(DiagnosingHealth::class, static function (): void {
            DB::select('select 1');
        });

        foreach ([User::class, Homeowner::class, Payment::class, DuesSetting::class, DuesObligation::class, Complaint::class, ServiceRequest::class, Announcement::class, Certificate::class, ContactMessage::class, SystemSetting::class, CertificateTemplate::class, Household::class, HouseholdMember::class, PaymentDocument::class, PaymentDocumentSource::class] as $model) {
            $model::observe(AuditObserver::class);
        }

        Homeowner::saved(fn (Homeowner $resident) => app(HouseholdDirectory::class)->synchronize($resident));
        Homeowner::deleted(fn (Homeowner $resident) => app(HouseholdDirectory::class)->synchronize($resident));
        Homeowner::restored(fn (Homeowner $resident) => app(HouseholdDirectory::class)->synchronize($resident));
        User::saved(function (User $user): void {
            if ($user->wasChanged(['first_name', 'middle_name', 'last_name', 'suffix', 'account_status']) && $resident = $user->homeowner()->first()) {
                app(HouseholdDirectory::class)->synchronize($resident);
            }
        });
        User::deleted(function (User $user): void {
            if ($resident = $user->homeowner()->first()) {
                app(HouseholdDirectory::class)->synchronize($resident);
            }
        });

        foreach ([Complaint::class, ServiceRequest::class, Certificate::class, ContactMessage::class] as $model) {
            $model::observe(WorkflowNotificationObserver::class);
        }
    }
}
