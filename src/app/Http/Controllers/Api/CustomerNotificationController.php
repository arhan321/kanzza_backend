<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\CustomerNotification;
use App\Http\Resources\CustomerNotificationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerNotificationController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min((int) $request->query('per_page', 30), 100));

        return CustomerNotificationResource::collection(
            $request->user()
                ->customerNotifications()
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar notifikasi berhasil diambil.',
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'unread_count' => $request->user()
                ->customerNotifications()
                ->unread()
                ->count(),
        ], 'Jumlah notifikasi yang belum dibaca berhasil diambil.');
    }

    public function markAsRead(
        Request $request,
        CustomerNotification $notification,
    ): JsonResponse {
        $this->ensureOwnedByUser($request, $notification);
        $notification->markAsRead();

        return $this->success(
            new CustomerNotificationResource($notification->refresh()),
            'Notifikasi ditandai sudah dibaca.',
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()
            ->customerNotifications()
            ->unread()
            ->update(['read_at' => now()]);

        return $this->success([
            'updated_count' => $updated,
            'unread_count' => 0,
        ], 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function ensureOwnedByUser(
        Request $request,
        CustomerNotification $notification,
    ): void {
        if ($notification->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
