<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Models\ClassModel;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use App\Filament\Resources\UserResource\Pages;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationGroup(): ?string
    {
        return 'User Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Users Management';
    }

    public static function getModelLabel(): string
    {
        return 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Users';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-users';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'Please enter the full name.',
                        'max' => 'Name cannot exceed 255 characters.',
                    ]),

                Forms\Components\TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'Please enter the email address.',
                        'email' => 'Please enter a valid email address.',
                        'unique' => 'This email address is already registered.',
                    ]),

                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required(fn(string $context): bool => $context === 'create')
                    ->minLength(8)
                    ->dehydrateStateUsing(fn($state) => !empty($state) ? Hash::make($state) : null)
                    ->dehydrated(fn($state) => filled($state))
                    ->helperText('Leave empty to keep current password when editing')
                    ->validationMessages([
                        'required' => 'Please enter a password.',
                        'min' => 'Password must be at least 8 characters.',
                    ]),

                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Administrator',
                        'kurikulum' => 'Kurikulum (Teacher)',
                        'kepala_sekolah' => 'Kepala Sekolah',
                        'siswa' => 'Siswa (Student)',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state !== 'siswa') {
                            $set('class_id', null);
                        }
                        if ($state !== 'kurikulum') {
                            $set('mata_pelajaran', null);
                        }
                    })
                    ->validationMessages([
                        'required' => 'Please select a role.',
                    ]),

                Forms\Components\Select::make('class_id')
                    ->label('Class (for Students)')
                    ->options(ClassModel::pluck('nama_kelas', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn(callable $get) => $get('role') === 'siswa')
                    ->helperText('Select the class for this student'),

                Forms\Components\TextInput::make('mata_pelajaran')
                    ->label('Subject (for Teachers)')
                    ->visible(fn(callable $get) => $get('role') === 'kurikulum')
                    ->helperText('Subject taught by this teacher'),

                Forms\Components\Toggle::make('is_banned')
                    ->label('Banned')
                    ->default(false)
                    ->helperText('Check to ban this user from the system'),

                Forms\Components\Hidden::make('email_verified_at')
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->tooltip('Click to copy email'),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'admin' => 'danger',
                        'kurikulum' => 'success',
                        'kepala_sekolah' => 'warning',
                        'siswa' => 'info',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'admin' => 'Administrator',
                        'kurikulum' => 'Teacher',
                        'kepala_sekolah' => 'Principal',
                        'siswa' => 'Student',
                    }),

                TextColumn::make('class.nama_kelas')
                    ->label('Class')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('mata_pelajaran')
                    ->label('Subject')
                    ->sortable()
                    ->searchable(),

                IconColumn::make('is_banned')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn(bool $state): string => $state ? 'Banned' : 'Active'),

                // TextColumn::make('email_verified_at')
                //     ->label('Verified')
                //     ->dateTime('M d, Y')
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),

                // TextColumn::make('created_at')
                //     ->label('Created')
                //     ->dateTime('M d, Y H:i')
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Administrator',
                        'kurikulum' => 'Teacher',
                        'kepala_sekolah' => 'Principal',
                        'siswa' => 'Student',
                    ]),

                TernaryFilter::make('is_banned')
                    ->label('Status')
                    ->placeholder('All users')
                    ->trueLabel('Banned only')
                    ->falseLabel('Active only'),

                SelectFilter::make('class_id')
                    ->label('Class')
                    ->options(ClassModel::pluck('nama_kelas', 'id')),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),

                EditAction::make()
                    ->iconButton()
                    ->color('primary'),

                Action::make('ban')
                    ->label('Ban/Unban')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color(fn(User $record): string => $record->is_banned ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn(User $record): string => $record->is_banned ? 'Unban User' : 'Ban User')
                    ->modalDescription(fn(User $record): string => $record->is_banned
                        ? 'Are you sure you want to unban this user? They will regain access to the system.'
                        : 'Are you sure you want to ban this user? They will lose access to the system.')
                    ->modalSubmitActionLabel(fn(User $record): string => $record->is_banned ? 'Unban' : 'Ban')
                    ->action(function (User $record): void {
                        $record->update(['is_banned' => !$record->is_banned]);
                    })
                    ->visible(fn() => auth()->check() && auth()->user()->role === 'admin'),

                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete User')
                    ->modalDescription('Are you sure you want to delete this user? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete it'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),

                    BulkAction::make('ban_selected')
                        ->label('Ban Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Ban Selected Users')
                        ->modalDescription('Are you sure you want to ban the selected users? They will lose access to the system.')
                        ->modalSubmitActionLabel('Ban Users')
                        ->action(fn(Collection $records) => $records->each->update(['is_banned' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn() => auth()->check() && auth()->user()->role === 'admin'),

                    BulkAction::make('unban_selected')
                        ->label('Unban Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Unban Selected Users')
                        ->modalDescription('Are you sure you want to unban the selected users? They will regain access to the system.')
                        ->modalSubmitActionLabel('Unban Users')
                        ->action(fn(Collection $records) => $records->each->update(['is_banned' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn() => auth()->check() && auth()->user()->role === 'admin'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Only admin users can access this resource
        if (auth()->check() && auth()->user()->role === 'admin') {
            return $query;
        }

        // If not admin, return empty query (no access)
        return $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }
}
