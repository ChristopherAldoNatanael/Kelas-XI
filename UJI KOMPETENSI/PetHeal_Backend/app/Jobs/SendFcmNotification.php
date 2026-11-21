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
        protected string $channelId = 'petheal_notifications',
    ) {}

    public function handle(FCMService $fcm): void
    {
        // Riwayat notifikasi sudah ditulis oleh FCMService::sendToUser() saat
        // request berjalan, jadi worker hanya mengirim ke perangkat.
        $fcm->deliverToUser($this->userId, $this->title, $this->body, $this->data, $this->channelId);
    }
}
