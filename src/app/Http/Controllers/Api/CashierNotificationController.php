<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CashierNotificationResource;
use App\Models\CashierNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierNotificationController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min((int) $request->query('per_page', 30), 100));

        return CashierNotificationResource::collection(
            $request->user()
                ->cashierNotifications()
                ->latest()
                ->paginate($perPage)
                ->withQueryString(),
        )->additional([
            'success' => true,
            'message' => 'Daftar notifikasi kasir berhasil diambil.',
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'unread_count' => $request->user()
                ->cashierNotifications()
                ->unread()
                ->count(),
        ], 'Jumlah notifikasi kasir yang belum dibaca berhasil diambil.');
    }

    public function markAsRead(
        Request $request,
        CashierNotification $notification,
    ): JsonResponse {
        $this->ensureOwnedByUser($request, $notification);
        $notification->markAsRead();

        return $this->success(
            new CashierNotificationResource($notification->refresh()),
            'Notifikasi kasir ditandai sudah dibaca.',
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()
            ->cashierNotifications()
            ->unread()
            ->update(['read_at' => now()]);

        return $this->success([
            'updated_count' => $updated,
            'unread_count' => 0,
        ], 'Semua notifikasi kasir ditandai sudah dibaca.');
    }

    private function ensureOwnedByUser(
        Request $request,
        CashierNotification $notification,
    ): void {
        if ($notification->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
