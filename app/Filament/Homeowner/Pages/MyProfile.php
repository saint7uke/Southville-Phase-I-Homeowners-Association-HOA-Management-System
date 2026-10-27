<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Pages;

use App\Actions\Homeowners\UpdateOwnProfile;
use App\Actions\Users\ChangeOwnPassword;
use App\Models\User;
use App\Support\PropertyIdentity;
use App\Validation\HomeownerProfileValidator;
use BackedEnum;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $passwordForm
 * @property-read Schema $profileForm
 */
final class MyProfile extends Page
{
    use WithRateLimiting;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'My Profile';

    protected static string|\UnitEnum|null $navigationGroup = 'My Home';

    protected static ?string $slug = 'my-profile';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'My Profile';

    protected string $view = 'filament.homeowner.pages.my-profile';

    /** @var array<string, mixed> */
    public array $profileData = [];

    /** @var array<string, mixed> */
    public array $passwordData = [];

    /** @var list<array{name: string, contact: string, lease_start: string, lease_end: string, status: string}> */
    public array $tenantSummaries = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->hasRole('homeowner')
            && $user->can('update_own_profile')
            && $user->homeowner()->exists();
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 404);
        $this->fillProfileForm();
        $this->loadTenantSummaries();
        $this->passwordForm->fill();
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Personal information'))->extraAttributes(['data-i18n-section' => 'Personal information'])
                    ->description(__('Keep your legal and contact details accurate. Changing your email requires verification.'))
                    ->schema([
                        TextInput::make('first_name')->label(__('First name'))->required()->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->autocomplete('given-name')->extraFieldWrapperAttributes(self::validationAttributes('first_name')),
                        TextInput::make('middle_name')->label(__('Middle name'))->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->autocomplete('additional-name')->extraFieldWrapperAttributes(self::validationAttributes('middle_name')),
                        TextInput::make('last_name')->label(__('Last name'))->required()->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->autocomplete('family-name')->extraFieldWrapperAttributes(self::validationAttributes('last_name')),
                        Select::make('suffix')->options(['Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV', 'N/A' => 'N/A'])->placeholder(__('Select an option'))->extraFieldWrapperAttributes(self::validationAttributes('suffix')),
                        Select::make('sex')->options(['Male' => __('Male'), 'Female' => __('Female'), 'Prefer not to say' => __('Prefer not to say')])->required()->placeholder(__('Select an option'))->extraFieldWrapperAttributes(self::validationAttributes('sex')),
                        DatePicker::make('date_of_birth')->label(__('Date of birth'))->required()->maxDate(today()->subDay())->live()->extraFieldWrapperAttributes(self::validationAttributes('date_of_birth')),
                        Placeholder::make('age')->content(fn (Get $get): string => filled($get('date_of_birth')) ? Carbon::parse($get('date_of_birth'))->age.' years old' : 'Calculated from date of birth'),
                        TextInput::make('contact_number')->label(__('Contact number'))->tel()->required()->maxLength(20)->regex('/^09\d{9}$/')->mutateStateForValidationUsing(self::normalizePhone(...))->dehydrateStateUsing(self::normalizePhone(...))->autocomplete('tel')->helperText(__('Use an 11-digit Philippine mobile number beginning with 09. Spaces, periods, and hyphens are accepted.'))->extraFieldWrapperAttributes(self::validationAttributes('contact_number')),
                        TextInput::make('email')->label(__('Email address'))->email()->required()->maxLength(255)->autocomplete('email')->helperText(__('A verification link is sent if this changes.'))->extraFieldWrapperAttributes(self::validationAttributes('email')),
                    ])->columns(2),
                Section::make(__('Property and emergency contact'))->extraAttributes(['data-i18n-section' => 'Property and emergency contact'])
                    ->description(__('Contact the HOA to change the registered property address.'))
                    ->schema([
                        Placeholder::make('registered_street')->label(__('Street'))->content(fn (): string => (string) Filament::auth()->user()->homeowner->street),
                        Placeholder::make('registered_block')->label(__('Block'))->content(fn (): string => (string) Filament::auth()->user()->homeowner->block),
                        Placeholder::make('registered_lot')->label(__('Lot'))->content(fn (): string => (string) Filament::auth()->user()->homeowner->lot),
                        Placeholder::make('registered_phase')->label(__('Registered phase'))->content(fn (): string => PropertyIdentity::phase(Filament::auth()->user()->homeowner)),
                        DatePicker::make('residency_date')->label(__('Move-in date'))->required()->maxDate(today())->afterOrEqual('date_of_birth')->extraFieldWrapperAttributes(self::validationAttributes('residency_date')),
                        Select::make('ownership_type')->label(__('Occupancy type'))->options(['Owner' => __('Owner'), 'Tenant' => __('Tenant'), 'Co-owner' => __('Co-owner')])->required()->disabled()->dehydrated()->helperText(__('Contact the HOA to change the occupancy type.')),
                        TextInput::make('emergency_contact_name')->label(__('Emergency contact name'))->required()->maxLength(100)->regex("/^[\pL\s'.-]+$/u")->autocomplete('name')->extraFieldWrapperAttributes(self::validationAttributes('emergency_contact_name')),
                        TextInput::make('emergency_contact_number')->label(__('Emergency contact number'))->tel()->required()->maxLength(20)->regex('/^09\d{9}$/')->mutateStateForValidationUsing(self::normalizePhone(...))->dehydrateStateUsing(self::normalizePhone(...))->autocomplete('tel')->extraFieldWrapperAttributes(self::validationAttributes('emergency_contact_number')),
                    ])->columns(2),
                Section::make(__('Profile photo'))->extraAttributes(['data-i18n-section' => 'Profile photo'])
                    ->description(__('JPEG, PNG, or WebP; 100–4000 pixels per side; maximum 2 MB. Location metadata is removed before private storage.'))
                    ->schema([
                        FileUpload::make('profile_photo_upload')
                            ->label(__('Replacement profile photo'))
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->storeFiles(false)
                            ->imagePreviewHeight('180')
                            ->extraFieldWrapperAttributes(self::validationAttributes('profile_photo_upload')),
                    ]),
            ])
            ->statePath('profileData');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Change password'))->extraAttributes(['data-i18n-section' => 'Change password'])
                    ->description(__('You will be signed out from every device after a successful password change.'))
                    ->schema([
                        TextInput::make('current_password')->label(__('Current password'))->password()->revealable()->required()->maxLength(255)->autocomplete('current-password')->extraFieldWrapperAttributes(self::passwordValidationAttributes('current_password')),
                        TextInput::make('password')->label(__('New password'))->password()->revealable()->required()->maxLength(255)->rule(Password::default())->same('password_confirmation')->autocomplete('new-password')->helperText(__('At least 12 characters with uppercase, lowercase, number, and symbol.'))->extraFieldWrapperAttributes(self::passwordValidationAttributes('password')),
                        TextInput::make('password_confirmation')->label(__('Confirm new password'))->password()->revealable()->required()->maxLength(255)->autocomplete('new-password')->extraFieldWrapperAttributes(self::passwordValidationAttributes('password_confirmation')),
                    ]),
            ])
            ->statePath('passwordData');
    }

    public function saveProfile(UpdateOwnProfile $updateProfile, HomeownerProfileValidator $validator): void
    {
        if (! $this->checkRateLimit()) {
            return;
        }

        $state = $this->profileForm->getState();
        $emailChanged = $this->user()->email !== ($validator->normalize($state)['email'] ?? null);

        try {
            $updateProfile->handle($this->user(), $state);
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception, 'profileData');
        }

        Notification::make()->success()->title(__('Profile updated successfully!'))->send();
        $this->fillProfileForm();

        if ($emailChanged) {
            $this->redirect((string) Filament::getEmailVerificationPromptUrl());
        }
    }

    public function savePassword(ChangeOwnPassword $changePassword): void
    {
        if (! $this->checkRateLimit()) {
            return;
        }

        try {
            $changePassword->handle($this->user(), $this->passwordForm->getState(), 'homeowner');
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception, 'passwordData');
        }

        Auth::guard('homeowner')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl(), navigate: false);
    }

    private function fillProfileForm(): void
    {
        $user = $this->user()->load('homeowner');
        $homeowner = $user->homeowner;

        $this->profileForm->fill([
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'suffix' => $user->suffix,
            'sex' => $user->sex,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'contact_number' => $user->contact_number,
            'email' => $user->email,
            'residency_date' => $homeowner->residency_date?->toDateString(),
            'ownership_type' => $homeowner->ownership_type,
            'emergency_contact_name' => $homeowner->emergency_contact_name,
            'emergency_contact_number' => $homeowner->emergency_contact_number,
            'profile_photo_upload' => null,
        ]);
    }

    private function loadTenantSummaries(): void
    {
        $homeowner = $this->user()->homeowner;
        if (! in_array($homeowner->ownership_type, ['Owner', 'Co-owner'], true)) {
            $this->tenantSummaries = [];

            return;
        }

        $this->tenantSummaries = $homeowner->tenants()
            ->with('user')
            ->orderBy('lease_start_date')
            ->get()
            ->map(fn ($tenant): array => [
                'name' => $tenant->user->full_name,
                'contact' => $tenant->user->contact_number,
                'lease_start' => $tenant->lease_start_date?->toFormattedDateString() ?? 'Not provided',
                'lease_end' => $tenant->lease_end_date?->toFormattedDateString() ?? 'Not provided',
                'status' => (string) $tenant->user->account_status,
            ])
            ->all();
    }

    private function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 404);
        $user->loadMissing('homeowner');
        abort_unless($user->homeowner !== null, 404);

        return $user;
    }

    /** @return array<string, string> */
    private static function validationAttributes(string $field): array
    {
        return ['data-profile-field-wrapper' => $field];
    }

    /** @return array<string, string> */
    private static function passwordValidationAttributes(string $field): array
    {
        return ['data-password-field-wrapper' => $field];
    }

    private static function normalizePhone(?string $state): string
    {
        return HomeownerProfileValidator::normalizePhone($state);
    }

    private function checkRateLimit(): bool
    {
        try {
            $this->rateLimit(5, 60);

            return true;
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->danger()
                ->title(__('Too many attempts'))
                ->body(__('Try again in :seconds seconds.', ['seconds' => $exception->secondsUntilAvailable]))
                ->send();

            return false;
        }
    }

    private function prefixValidationErrors(ValidationException $exception, string $statePath): ValidationException
    {
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors["{$statePath}.{$field}"] = $messages;
        }

        return ValidationException::withMessages($errors);
    }
}
