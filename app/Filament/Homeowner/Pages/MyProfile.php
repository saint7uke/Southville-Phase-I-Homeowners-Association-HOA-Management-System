<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Pages;

use App\Actions\Homeowners\UpdateOwnProfile;
use App\Actions\Users\ChangeOwnPassword;
use App\Models\User;
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

    protected static ?string $slug = 'my-profile';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'My Profile';

    protected string $view = 'filament.homeowner.pages.my-profile';

    /** @var array<string, mixed> */
    public array $profileData = [];

    /** @var array<string, mixed> */
    public array $passwordData = [];

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
        $this->passwordForm->fill();
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal information')
                    ->description('Keep your legal and contact details accurate. Changing your email requires verification.')
                    ->schema([
                        TextInput::make('first_name')->label('First name')->required()->maxLength(100)->autocomplete('given-name'),
                        TextInput::make('middle_name')->label('Middle name')->maxLength(100)->autocomplete('additional-name'),
                        TextInput::make('last_name')->label('Last name')->required()->maxLength(100)->autocomplete('family-name'),
                        Select::make('suffix')->options(['Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV', 'N/A' => 'N/A'])->placeholder('Select an option'),
                        Select::make('sex')->options(['Male' => 'Male', 'Female' => 'Female', 'Prefer not to say' => 'Prefer not to say'])->required()->placeholder('Select an option'),
                        DatePicker::make('date_of_birth')->label('Date of birth')->required()->maxDate(today())->live(),
                        Placeholder::make('age')->content(fn (Get $get): string => filled($get('date_of_birth')) ? Carbon::parse($get('date_of_birth'))->age.' years old' : 'Calculated from date of birth'),
                        TextInput::make('contact_number')->label('Contact number')->tel()->required()->length(11)->regex('/^09\d{9}$/')->autocomplete('tel')->helperText('Use an 11-digit Philippine mobile number beginning with 09.'),
                        TextInput::make('email')->label('Email address')->email()->required()->maxLength(255)->autocomplete('email')->helperText('A verification link is sent if this changes.'),
                    ])->columns(2),
                Section::make('Property and emergency contact')
                    ->schema([
                        TextInput::make('house_number')->label('House number')->required()->maxLength(50)->autocomplete('address-line1'),
                        TextInput::make('street')->required()->maxLength(100)->autocomplete('address-line2'),
                        TextInput::make('block')->required()->maxLength(20),
                        TextInput::make('lot')->required()->maxLength(20),
                        TextInput::make('phase')->required()->maxLength(50),
                        DatePicker::make('residency_date')->label('Move-in date')->required()->maxDate(today()),
                        Select::make('ownership_type')->label('Occupancy type')->options(['Owner' => 'Owner', 'Tenant' => 'Tenant', 'Co-owner' => 'Co-owner'])->required()->placeholder('Select an option'),
                        TextInput::make('emergency_contact_name')->label('Emergency contact name')->required()->maxLength(100)->autocomplete('name'),
                        TextInput::make('emergency_contact_number')->label('Emergency contact number')->tel()->required()->length(11)->regex('/^09\d{9}$/')->autocomplete('tel'),
                    ])->columns(2),
                Section::make('Profile photo')
                    ->description('JPEG, PNG, or WebP; 100–4000 pixels per side; maximum 2 MB. Location metadata is removed before private storage.')
                    ->schema([
                        FileUpload::make('profile_photo_upload')
                            ->label('Replacement profile photo')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->storeFiles(false)
                            ->imagePreviewHeight('180'),
                    ]),
            ])
            ->statePath('profileData');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Change password')
                    ->description('You will be signed out from every device after a successful password change.')
                    ->schema([
                        TextInput::make('current_password')->label('Current password')->password()->revealable()->required()->maxLength(255)->autocomplete('current-password'),
                        TextInput::make('password')->label('New password')->password()->revealable()->required()->rule(Password::default())->same('password_confirmation')->autocomplete('new-password'),
                        TextInput::make('password_confirmation')->label('Confirm new password')->password()->revealable()->required()->autocomplete('new-password'),
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

        Notification::make()->success()->title('Profile saved')->send();
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
            'house_number' => $homeowner->house_number,
            'street' => $homeowner->street,
            'block' => $homeowner->block,
            'lot' => $homeowner->lot,
            'phase' => $homeowner->phase,
            'residency_date' => $homeowner->residency_date?->toDateString(),
            'ownership_type' => $homeowner->ownership_type,
            'emergency_contact_name' => $homeowner->emergency_contact_name,
            'emergency_contact_number' => $homeowner->emergency_contact_number,
            'profile_photo_upload' => null,
        ]);
    }

    private function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 404);
        $user->loadMissing('homeowner');
        abort_unless($user->homeowner !== null, 404);

        return $user;
    }

    private function checkRateLimit(): bool
    {
        try {
            $this->rateLimit(5, 60);

            return true;
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->danger()
                ->title('Too many attempts')
                ->body("Try again in {$exception->secondsUntilAvailable} seconds.")
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
