<?php

namespace App\Application\Services;

use App\Domain\Enums\DeliveryMethod;
use App\Domain\Enums\DeliveryStatus;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\UserRole;
use App\Domain\Repositories\DeliveryRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        private readonly DeliveryRepositoryInterface $deliveries,
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    public function assignDriver(User $actor, Order $order, User $driver): Delivery
    {
        if (! $actor->isRole(UserRole::Owner, UserRole::Cashier)) {
            abort(403, 'Anda tidak memiliki akses untuk menugaskan driver.');
        }

        if (! $driver->isRole(UserRole::Driver) || ! $driver->isActive()) {
            throw ValidationException::withMessages([
                'driver_id' => ['User yang dipilih bukan driver aktif.'],
            ]);
        }

        if ($order->payment_status !== PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan belum dibayar.'],
            ]);
        }

        if ($order->delivery_method !== DeliveryMethod::Delivery) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan pickup tidak memerlukan driver.'],
            ]);
        }

        if (! in_array($order->order_status, [OrderStatus::Ready, OrderStatus::Assigned], true)) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan harus berstatus ready sebelum ditugaskan ke driver.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $order, $driver): Delivery {
            $delivery = $this->deliveries->createOrUpdateForOrder($order, [
                'driver_id' => $driver->id,
                'assigned_by' => $actor->id,
                'status' => DeliveryStatus::Assigned,
                'assigned_at' => now(),
            ]);

            $this->orders->update($order, [
                'order_status' => OrderStatus::Assigned,
            ]);

            return $delivery->refresh()->load([
                'order.items',
                'order.customer',
                'driver',
            ]);
        });
    }

    public function paginateForDriver(User $driver, array $filters): LengthAwarePaginator
    {
        if (! $driver->isRole(UserRole::Driver)) {
            abort(403, 'Hanya driver yang dapat melihat daftar pengiriman ini.');
        }

        return $this->deliveries->paginateForDriver(
            $driver,
            $filters,
            min((int) ($filters['per_page'] ?? 15), 100),
        );
    }

    public function showForDriver(User $driver, Delivery $delivery): Delivery
    {
        if ($delivery->driver_id !== $driver->id) {
            abort(403, 'Pengiriman ini tidak ditugaskan kepada Anda.');
        }

        return $delivery->load(['order.items', 'order.customer', 'driver']);
    }

    public function updateDriverStatus(
        User $driver,
        Delivery $delivery,
        DeliveryStatus $targetStatus,
        array $data,
    ): Delivery {
        if ($delivery->driver_id !== $driver->id) {
            abort(403, 'Pengiriman ini tidak ditugaskan kepada Anda.');
        }

        $allowedTransitions = [
            DeliveryStatus::Assigned->value => [DeliveryStatus::PickedUp],
            DeliveryStatus::PickedUp->value => [DeliveryStatus::OnDelivery],
            DeliveryStatus::OnDelivery->value => [DeliveryStatus::Delivered],
        ];

        $allowedTargets = $allowedTransitions[$delivery->status->value] ?? [];

        if (! in_array($targetStatus, $allowedTargets, true)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Perubahan status dari {$delivery->status->value} ke {$targetStatus->value} tidak diizinkan.",
                ],
            ]);
        }

        return DB::transaction(function () use (
            $delivery,
            $targetStatus,
            $data,
        ): Delivery {
            $deliveryData = [
                'status' => $targetStatus,
                'notes' => $data['notes'] ?? $delivery->notes,
            ];

            $orderStatus = match ($targetStatus) {
                DeliveryStatus::PickedUp => OrderStatus::PickedUp,
                DeliveryStatus::OnDelivery => OrderStatus::OnDelivery,
                DeliveryStatus::Delivered => OrderStatus::Delivered,
                default => OrderStatus::Assigned,
            };

            if ($targetStatus === DeliveryStatus::PickedUp) {
                $deliveryData['picked_up_at'] = now();
            }

            if ($targetStatus === DeliveryStatus::Delivered) {
                $deliveryData['delivered_at'] = now();
                $deliveryData['proof_image'] = $data['proof_image_path']
                    ?? $delivery->proof_image;
            }

            $updatedDelivery = $this->deliveries->update($delivery, $deliveryData);
            $this->orders->update($delivery->order, [
                'order_status' => $orderStatus,
            ]);

            return $updatedDelivery;
        });
    }
}
