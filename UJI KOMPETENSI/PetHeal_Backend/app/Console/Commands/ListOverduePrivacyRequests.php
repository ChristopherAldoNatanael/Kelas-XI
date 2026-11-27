<?php

namespace App\Console\Commands;

use App\Models\PrivacyRequest;
use Illuminate\Console\Command;

class ListOverduePrivacyRequests extends Command
{
    protected $signature = 'privacy:list-overdue';
    protected $description = 'Tampilkan permintaan PDP yang melewati SLA (read-only)';

    public function handle(): int
    {
        $query = PrivacyRequest::overdue()->orderBy('due_at');
        $count = (clone $query)->count();

        $this->info("Permintaan PDP lewat SLA: {$count}");

        if ($count > 0) {
            $this->table(
                ['id', 'user_id', 'clinic_id', 'type', 'status', 'due_at'],
                (clone $query)->limit(100)->get()->map(fn ($request) => [
                    $request->id,
                    $request->user_id,
                    $request->clinic_id,
                    $request->request_type,
                    $request->status,
                    $request->due_at?->toDateTimeString(),
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
