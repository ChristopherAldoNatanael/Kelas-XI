<?php

namespace App\Jobs;

use App\Services\FCMService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFcmNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $userId,
        protected string $title,
        protected string $body,
        protected array $data = [],
    ) {}

    public function handle(FCMService $fcm): void
    {
        $fcm->sendToUser($this->userId, $this->title, $this->body, $this->data);
    }
}
