<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectResource\Pages;
use App\Models\Subject;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Academic Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Subjects Management';
    }

    public static function getModelLabel(): string
    {
        return 'Subject';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Subjects';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-book-open';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->label('Subject Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'Please enter the subject name.',
                        'unique' => 'This subject name is already registered.',
                    ]),

                Forms\Components\TextInput::make('kode')
                    ->label('Subject Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'Please enter the subject code.',
                        'unique' => 'This subject code is already registered.',
                    ]),

                Forms\Components\Select::make('category')
                    ->label('Category')
                    ->options([
                        'wajib' => 'Wajib (Required)',
                        'peminatan' => 'Peminatan (Specialization)',
                        'mulok' => 'MULOK (Local Content)',
                    ])
                    ->required()
                    ->default('wajib')
                    ->validationMessages([
                        'required' => 'Please select a subject category.',
                    ]),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->maxLength(500)
                    ->validationMessages([
                        'max' => 'Description cannot exceed 500 characters.',
                    ]),

                Forms\Components\TextInput::make('credit_hours')
                    ->label('Credit Hours')
                    ->required()
                    ->numeric()
                    ->default(2)
                    ->minValue(1)
                    ->maxValue(10)
                    ->validationMessages([
                        'required' => 'Please enter the credit hours.',
                        'min' => 'Credit hours must be at least 1.',
                        'max' => 'Credit hours cannot exceed 10.',
                    ]),

                Forms\Components\TextInput::make('semester')
                    ->label('Semester')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(8)
                    ->validationMessages([
                        'required' => 'Please enter the semester.',
                        'min' => 'Semester must be at least 1.',
                        'max' => 'Semester cannot exceed 8.',
                    ]),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->required()
                    ->default('active')
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
                    ->label('Subject Name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('kode')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'wajib' => 'success',
                        'peminatan' => 'warning',
                        'mulok' => 'info',
                    }),

                Tables\Columns\TextColumn::make('credit_hours')
                    ->label('Credit Hours')
                    ->sortable()
                    ->numeric(),

                Tables\Columns\TextColumn::make('semester')
                    ->label('Semester')
                    ->sortable()
                    ->numeric(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'wajib' => 'Wajib (Required)',
                        'peminatan' => 'Peminatan (Specialization)',
                        'mulok' => 'MULOK (Local Content)',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\SelectFilter::make('semester')
                    ->label('Semester')
                    ->options(Subject::distinct()->pluck('semester', 'semester')->sort()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->successNotificationTitle('Subject updated successfully!')
                    ->slideOver(false),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSubjects::route('/'),
        ];
    }
}
