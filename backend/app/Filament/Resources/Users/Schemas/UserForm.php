<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->unique(table: User::class, column: 'email', ignoreRecord: true),
                Select::make('role')
                    ->required()
                    ->options([
                        UserRole::Admin->value => 'Admin',
                        UserRole::Artisan->value => 'Artisan',
                        UserRole::Customer->value => 'Customer',
                    ])
                    ->native(false),
                Select::make('account_status')
                    ->label('Account status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'closed' => 'Closed (self-service)',
                    ])
                    ->default('active')
                    ->required()
                    ->native(false),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn ($operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->rule(Password::defaults()),
                TextInput::make('artisanProfile.specialty')
                    ->label('Artisan specialty')
                    ->maxLength(255)
                    ->visible(fn (callable $get): bool => $get('role') === UserRole::Artisan->value),
                Toggle::make('artisanProfile.is_active')
                    ->label('Artisan active')
                    ->default(true)
                    ->visible(fn (callable $get): bool => $get('role') === UserRole::Artisan->value),
            ]);
    }
}
