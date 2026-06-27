<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\SchoolAdmin;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /** Query notifikasi milik aktor yang sedang login (user internal / admin sekolah). */
    private function scope(Request $request): Builder
    {
        $actor = $request->user();
        $type = $actor instanceof SchoolAdmin ? 'school_admin' : 'user';

        return Notification::where('recipient_type', $type)
            ->where('recipient_id', $actor->id);
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->scope($request)
            ->when($request->has('is_read'), fn($q) => $q->where('is_read', $request->boolean('is_read')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success($notifications, 'Daftar notifikasi.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->scope($request)->where('is_read', false)->count();

        return $this->success(['unread' => $count], 'Jumlah notifikasi belum dibaca.');
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $actor = $request->user();
        $type = $actor instanceof SchoolAdmin ? 'school_admin' : 'user';

        // ownership guard
        if ($notification->recipient_type !== $type || $notification->recipient_id !== $actor->id) {
            return $this->error('Notifikasi bukan milik Anda.', 403);
        }

        $notification->update(['is_read' => true]);

        return $this->success(null, 'Notifikasi ditandai dibaca.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->scope($request)->where('is_read', false)->update(['is_read' => true]);

        return $this->success(null, 'Semua notifikasi ditandai dibaca.');
    }
}
