<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherResource\Pages;
use App\Models\Teacher;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Academic Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Teachers Management';
    }

    public static function getModelLabel(): string
    {
        return 'Teacher';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Teachers';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->label('Full Name')
                    ->required()
                    ->helperText('Full name of the teacher')
                    ->validationMessages([
                        'required' => 'Please enter the teacher full name.',
                    ]),

                Forms\Components\TextInput::make('nip')
                    ->label('NIP')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('National Identity Number for the teacher')
                    ->validationMessages([
                        'required' => 'Please enter the teacher NIP.',
                        'unique' => 'This NIP is already registered.',
                    ]),

                Forms\Components\TextInput::make('teacher_code')
                    ->label('Teacher Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Unique code for teacher identification')
                    ->validationMessages([
                        'required' => 'Please enter the teacher code.',
                        'unique' => 'This teacher code is already registered.',
                    ]),

                Forms\Components\TextInput::make('position')
                    ->label('Position')
                    ->required()
                    ->helperText('Job title or position')
                    ->validationMessages([
                        'required' => 'Please enter the teacher position.',
                    ]),

                Forms\Components\TextInput::make('department')
                    ->label('Department')
                    ->required()
                    ->helperText('Academic department')
                    ->validationMessages([
                        'required' => 'Please enter the department.',
                    ]),

                Forms\Components\DatePicker::make('join_date')
                    ->label('Join Date')
                    ->required()
                    ->helperText('Date when the teacher joined the institution')
                    ->validationMessages([
                        'required' => 'Please select the join date.',
                    ]),

                Forms\Components\TextInput::make('expertise')
                    ->label('Expertise')
                    ->placeholder('e.g., Programming, Database, Mathematics')
                    ->helperText('Areas of expertise (comma-separated)')
                    ->validationMessages([
                        'max' => 'Expertise cannot exceed 255 characters.',
                    ])
                    ->maxLength(255),

                Forms\Components\TextInput::make('certification')
                    ->label('Certification')
                    ->placeholder('e.g., S2 Computer Science, Microsoft Certified')
                    ->helperText('Professional certifications and qualifications')
                    ->validationMessages([
                        'max' => 'Certification cannot exceed 255 characters.',
                    ])
                    ->maxLength(255),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'retired' => 'Retired',
                    ])
                    ->required()
                    ->default('active')
                    ->helperText('Current employment status')
                    ->validationMessages([
                        'required' => 'Please select a status.',
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->tooltip('Full name of the teacher'),

                Tables\Columns\TextColumn::make('nip')
                    ->label('NIP')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->tooltip('Click to copy NIP'),

                Tables\Columns\TextColumn::make('teacher_code')
                    ->label('Teacher Code')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->tooltip('Click to copy teacher code'),

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('department')
                    ->label('Department')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'retired' => 'danger',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'inactive' => 'heroicon-o-x-circle',
                        'retired' => 'heroicon-o-exclamation-triangle',
                    }),

                Tables\Columns\TextColumn::make('join_date')
                    ->label('Join Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->tooltip('Date when teacher joined'),

                Tables\Columns\TextColumn::make('expertise')
                    ->label('Expertise')
                    ->limit(30)
                    ->tooltip('Teacher areas of expertise'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'retired' => 'Retired',
                    ])
                    ->default('active'),

                Tables\Filters\SelectFilter::make('department')
                    ->label('Department')
                    ->options(Teacher::distinct()->pluck('department', 'department')),

                Tables\Filters\Filter::make('join_date')
                    ->form([
                        Forms\Components\DatePicker::make('join_from')
                            ->label('Join Date From'),
                        Forms\Components\DatePicker::make('join_until')
                            ->label('Join Date Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['join_from'], fn (Builder $query, $date): Builder => $query->whereDate('join_date', '>=', $date))
                            ->when($data['join_until'], fn (Builder $query, $date): Builder => $query->whereDate('join_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ManageTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'view' => Pages\ViewTeacher::route('/{record}'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }
}
