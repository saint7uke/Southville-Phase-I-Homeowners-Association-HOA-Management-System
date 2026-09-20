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
use Illuminate\Support\Facades\Password;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('full_name')->label('Name')->searchable(['first_name', 'last_name']),
            TextColumn::make('email')->searchable()->copyable(),
            TextColumn::make('roles.name')->label('Role')->badge(),
            TextColumn::make('account_status')->label('Account status')->badge(),
            TextColumn::make('contact_number')->label('Contact'),
            TextColumn::make('last_login_at')->label('Last login')->dateTime()->sortable()->placeholder('Never'),
            TextColumn::make('last_login_ip')->label('Last IP')->toggleable(),
            TextColumn::make('last_login_panel')->label('Last panel')->badge()->placeholder('None'),
            TextColumn::make('created_at')->label('Registered')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc')->filters([
            SelectFilter::make('account_status')->options(UserAccountStatus::options()),
            TrashedFilter::make(),
        ])->recordActions([
            Action::make('sendPasswordReset')
                ->label('Send password reset')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->action(function (User $record): void {
                    $status = Password::broker('users')->sendResetLink(['email' => $record->email]);
                    Notification::make()->title(__($status))->color($status === Password::RESET_LINK_SENT ? 'success' : 'danger')->send();
                }),
            EditAction::make(),
        ])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
