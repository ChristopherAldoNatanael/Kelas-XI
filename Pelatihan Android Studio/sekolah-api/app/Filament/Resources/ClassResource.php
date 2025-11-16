<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassResource\Pages;
use App\Models\ClassModel;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClassResource extends Resource
{
    protected static ?string $model = ClassModel::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Academic Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Classes Management';
    }

    public static function getModelLabel(): string
    {
        return 'Class';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Classes';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-building-library';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Class Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('e.g., X-RPL-1, XI-TKJ-A, XII-MM-B')
                    ->validationMessages([
                        'required' => 'Please enter the class name.',
                        'unique' => 'This class name is already registered.',
                    ]),

                Forms\Components\TextInput::make('level')
                    ->label('Level')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(13)
                    ->helperText('Class level (1-13)')
                    ->validationMessages([
                        'required' => 'Please enter the class level.',
                        'min' => 'Level must be at least 1.',
                        'max' => 'Level cannot exceed 13.',
                    ]),

                Forms\Components\TextInput::make('major')
                    ->label('Major')
                    ->required()
                    ->helperText('Study program or specialization')
                    ->validationMessages([
                        'required' => 'Please enter the major.',
                    ]),

                Forms\Components\TextInput::make('academic_year')
                    ->label('Academic Year')
                    ->required()
                    ->default(date('Y') . '/' . (date('Y') + 1))
                    ->helperText('Format: YYYY/YYYY (e.g., 2024/2025)')
                    ->validationMessages([
                        'required' => 'Please enter the academic year.',
                    ]),

                Forms\Components\TextInput::make('capacity')
                    ->label('Capacity')
                    ->required()
                    ->numeric()
                    ->default(36)
                    ->minValue(1)
                    ->maxValue(100)
                    ->helperText('Maximum number of students')
                    ->validationMessages([
                        'required' => 'Please enter the class capacity.',
                        'min' => 'Capacity must be at least 1.',
                        'max' => 'Capacity cannot exceed 100.',
                    ]),

                Forms\Components\Select::make('homeroom_teacher_id')
                    ->label('Homeroom Teacher')
                    ->options(User::where('role', 'kurikulum')->pluck('nama', 'id'))
                    ->searchable()
                    ->preload()
                    ->helperText('Select the homeroom teacher for this class')
                    ->validationMessages([
                        'exists' => 'Please select a valid homeroom teacher.',
                    ]),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->required()
                    ->default('active')
                    ->helperText('Current class status')
                    ->validationMessages([
                        'required' => 'Please select a status.',
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Class Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->tooltip('Full class name and identifier'),

                Tables\Columns\TextColumn::make('level')
                    ->label('Level')
                    ->sortable()
                    ->numeric()
                    ->badge()
                    ->color('primary')
                    ->tooltip('Class level/grade'),

                Tables\Columns\TextColumn::make('major')
                    ->label('Major')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->tooltip('Study program or specialization'),

                Tables\Columns\TextColumn::make('academic_year')
                    ->label('Academic Year')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->tooltip('Click to copy academic year'),

                Tables\Columns\TextColumn::make('homeroom_teacher.nama')
                    ->label('Homeroom Teacher')
                    ->sortable()
                    ->searchable()
                    ->limit(25)
                    ->tooltip('Assigned homeroom teacher'),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->sortable()
                    ->numeric()
                    ->alignCenter()
                    ->tooltip('Maximum number of students'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'inactive' => 'heroicon-o-x-circle',
                    })
                    ->tooltip('Current class status'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('When this class was created'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->label('Level')
                    ->options(ClassModel::distinct()->pluck('level', 'level')->sort())
                    ->default(null),

                Tables\Filters\SelectFilter::make('major')
                    ->label('Major')
                    ->options(ClassModel::distinct()->pluck('major', 'major')),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active'),

                Tables\Filters\SelectFilter::make('academic_year')
                    ->label('Academic Year')
                    ->options(ClassModel::distinct()->pluck('academic_year', 'academic_year')->sort()),

                Tables\Filters\Filter::make('homeroom_teacher')
                    ->form([
                        Forms\Components\Select::make('homeroom_teacher_id')
                            ->label('Homeroom Teacher')
                            ->options(User::where('role', 'kurikulum')->pluck('nama', 'id'))
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['homeroom_teacher_id'], fn (Builder $query, $teacherId): Builder => $query->where('homeroom_teacher_id', $teacherId));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton(),

                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->color('primary'),

                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Class')
                    ->modalDescription('Are you sure you want to delete this class? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete it'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'active']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'inactive']))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageClasses::route('/'),
            'create' => Pages\CreateClass::route('/create'),
            'view' => Pages\ViewClass::route('/{record}'),
            'edit' => Pages\EditClass::route('/{record}/edit'),
        ];
    }
}
