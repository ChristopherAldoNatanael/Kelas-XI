<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Teacher created successfully';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure the user is created with the correct role
        if (isset($data['user_id'])) {
            $user = \App\Models\User::find($data['user_id']);
            if ($user && $user->role !== 'kurikulum') {
                $user->update(['role' => 'kurikulum']);
            }
        }

        return $data;
    }
}
