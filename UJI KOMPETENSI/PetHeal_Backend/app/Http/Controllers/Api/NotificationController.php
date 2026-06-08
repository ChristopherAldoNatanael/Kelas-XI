<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 100);

        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Notification $notification) => $this->transform($notification));

        $unreadCount = Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Notifications loaded',
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markRead(int $id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $this->transform($notification),
        ]);
    }

    public function markAllRead()
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    public function clearAll()
    {
        Notification::where('user_id', Auth::id())->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications cleared',
        ]);
    }

    private function transform(Notification $notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => (string) $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'type' => $notification->type,
            'pet_name' => $data['pet_name'] ?? null,
            'status' => $data['status'] ?? null,
            'date' => $data['date'] ?? ($data['next_visit'] ?? null),
            'timestamp' => ($notification->created_at?->timestamp ?? now()->timestamp) * 1000,
            'is_read' => $notification->read_at !== null,
        ];
    }
}
