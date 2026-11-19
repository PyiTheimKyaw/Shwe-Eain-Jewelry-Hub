<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->sortable(),
                TextColumn::make('account_status')
                    ->label('Account status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                IconColumn::make('force_password_reset')
                    ->label('Password reset required')
                    ->boolean(),
                IconColumn::make('artisanProfile.is_active')
                    ->label('Artisan active')
                    ->boolean()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'artisan' => 'Artisan',
                        'customer' => 'Customer',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleAccountStatus')
                    ->label(fn (User $record): string => $record->account_status === 'active' ? 'Suspend' : 'Activate')
                    ->icon(fn (User $record): string => $record->account_status === 'active' ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (User $record): string => $record->account_status === 'active' ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $next = $record->account_status === 'active' ? 'suspended' : 'active';
                        $record->update(['account_status' => $next]);

                        Notification::make()
                            ->title('Account status updated')
                            ->body("{$record->email} is now {$next}.")
                            ->success()
                            ->send();
                    }),
                Action::make('forcePasswordReset')
                    ->label('Forced password reset')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update(['force_password_reset' => true]);
                        $record->tokens()->delete();

                        Notification::make()
                            ->title('Password reset enforced')
                            ->body("All active tokens revoked for {$record->email}.")
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
