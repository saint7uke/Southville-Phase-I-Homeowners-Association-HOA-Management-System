<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('full_name')->label(__('Name'))->tooltip(__('Name and Actions always remain visible.'))->searchable(['first_name', 'last_name']),
            TextColumn::make('email')->searchable()->copyable()->toggleable(),
            TextColumn::make('roles.name')->label(__('Role'))->badge()->toggleable(),
            TextColumn::make('account_status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->label(__('Account status'))->badge()->toggleable(),
            TextColumn::make('contact_number')->label(__('Contact'))->toggleable(),
            TextColumn::make('last_login_at')->label(__('Last login'))->dateTime()->sortable()->placeholder(__('Never'))->toggleable(),
            TextColumn::make('last_login_ip')->label(__('Last IP'))->toggleable(),
            TextColumn::make('last_login_panel')->label(__('Last panel'))->badge()->placeholder(__('None'))->toggleable(),
            TextColumn::make('created_at')->label(__('Registered'))->dateTime()->sortable()->toggleable(),
        ])->columnManager()->columnManagerTriggerAction(fn (Action $action) => $action->tooltip(__('Name and Actions always remain visible.')))->deferColumnManager()->persistColumnsInSession(false)->defaultSort('created_at', 'desc')->filters([
            SelectFilter::make('role')->label(__('Role'))->placeholder(__('All Roles'))->options(['hoa_admin' => __('Admin'), 'hoa_staff' => __('HOA Staff'), 'homeowner' => __('Homeowner')])->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $role) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $role)->where('guard_name', 'web')))),
            SelectFilter::make('account_status')->options(UserAccountStatus::options()),
            TrashedFilter::make(),
        ])->recordActions([
            Action::make('sendPasswordReset')
                ->label(__('Send password reset'))
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->action(function (User $record): void {
                    $delivered = true;
                    try {
                        Password::broker('users')->sendResetLink(['email' => $record->email]);
                    } catch (Throwable $exception) {
                        $delivered = false;
                        Log::warning('Managed-user password reset mail delivery failed.', [
                            'exception' => $exception::class,
                            'code' => 'password_reset_transport_failure',
                        ]);
                    }

                    Notification::make()
                        ->title($delivered ? __('Password reset requested') : __('Password reset could not be sent'))
                        ->body($delivered
                            ? __('If the account is eligible, a password reset link will be sent shortly.')
                            : __('Please try again later or contact the system operator.'))
                        ->status($delivered ? 'info' : 'danger')
                        ->send();
                }),
            EditAction::make()->button()->tooltip(__('Edit')),
        ])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
