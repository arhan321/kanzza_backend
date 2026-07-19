<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MvcBackendFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer Baru',
            'email' => 'CUSTOMER@EXAMPLE.COM',
            'phone' => '081234567890',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'device_name' => 'feature-test',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'customer@example.com')
            ->assertJsonPath('data.user.role', UserRole::Customer->value);

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_customer_order_reserves_stock(): void
    {
        $customer = $this->createUser(UserRole::Customer);
        $product = $this->createProduct(stock: 10);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/orders', [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.order_status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.payment_method', PaymentMethod::Midtrans->value)
            ->assertJsonPath('data.grand_total', 30000);

        $this->assertSame(7, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Reservation->value,
            'quantity' => -3,
        ]);
    }

    public function test_customer_receives_and_can_read_order_notification(): void
    {
        $customer = $this->createUser(UserRole::Customer);
        $product = $this->createProduct();
        Sanctum::actingAs($customer);

        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->json('data.id');

        $notification = CustomerNotification::query()
            ->where('user_id', $customer->id)
            ->where('order_id', $orderId)
            ->where('event', 'order_created')
            ->firstOrFail();

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.event', 'order_created')
            ->assertJsonPath('data.0.order_id', $orderId)
            ->assertJsonPath('data.0.is_read', false);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_paid_midtrans_notification_is_created_only_once(): void
    {
        config(['midtrans.server_key' => 'test-server-key']);

        $customer = $this->createUser(UserRole::Customer);
        $product = $this->createProduct();
        $order = Order::createOnline($customer, [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'attempt_number' => 1,
            'provider' => 'midtrans',
            'midtrans_order_id' => 'KZ-PAID-NOTIFICATION-TEST',
            'gross_amount' => $order->grand_total,
            'status' => PaymentStatus::Pending,
        ]);
        $order->update(['payment_status' => PaymentStatus::Pending]);
        $grossAmount = number_format($order->grand_total, 2, '.', '');
        $payload = [
            'order_id' => $payment->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'transaction_id' => 'MIDTRANS-PAID-TEST',
            'signature_key' => hash(
                'sha512',
                $payment->midtrans_order_id.'200'.$grossAmount.'test-server-key',
            ),
        ];

        $this->postJson('/api/v1/payments/midtrans/notification', $payload)
            ->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value);

        $this->postJson('/api/v1/payments/midtrans/notification', $payload)
            ->assertOk();

        $this->assertSame(
            1,
            CustomerNotification::query()
                ->where('user_id', $customer->id)
                ->where('order_id', $order->id)
                ->where('event', 'payment_confirmed')
                ->count(),
        );

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_delivery_shipping_is_calculated_at_five_thousand_per_kilometer(): void
    {
        config([
            'business.store.latitude' => 0.0,
            'business.store.longitude' => 0.0,
            'business.shipping.rate_per_km' => 5000,
            'business.shipping.max_distance_km' => 100,
            'business.shipping.distance_tolerance_km' => 0.1,
        ]);

        $customer = $this->createUser(UserRole::Customer);
        $product = $this->createProduct();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Rumah',
            'recipient_name' => $customer->name,
            'phone' => $customer->phone,
            'full_address' => 'Alamat test',
            'latitude' => 0.0,
            'longitude' => 0.01,
            'is_default' => true,
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders', [
            'delivery_method' => DeliveryMethod::Delivery->value,
            'address_id' => $address->id,
            'distance_km' => 2.5,
            'shipping_cost' => 1,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.shipping_distance_km', 2.5)
            ->assertJsonPath('data.shipping_cost', 12500)
            ->assertJsonPath('data.grand_total', 22500);
    }

    public function test_customer_can_create_midtrans_payment(): void
    {
        config([
            'midtrans.is_production' => false,
            'midtrans.server_key' => 'test-server-key',
        ]);
        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-test',
                'redirect_url' => 'https://example.test/payment',
            ], 201),
        ]);

        $customer = $this->createUser(UserRole::Customer);
        $product = $this->createProduct();
        $order = Order::createOnline($customer, [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);
        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/orders/{$order->id}/payment")
            ->assertOk()
            ->assertJsonPath('data.snap_token', 'snap-token-test')
            ->assertJsonPath('data.status', PaymentStatus::Pending->value);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'attempt_number' => 1,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_cashier_transaction_reduces_stock_as_sale(): void
    {
        $cashier = $this->createUser(UserRole::Cashier);
        $product = $this->createProduct(stock: 8);
        Sanctum::actingAs($cashier);

        $this->postJson('/api/v1/cashier/transactions', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'payment_amount' => 25000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.channel', OrderChannel::Cashier->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.change_amount', 5000);

        $this->assertSame(6, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Sale->value,
            'quantity' => -2,
        ]);
    }

    public function test_midtrans_cancel_notification_restores_stock_only_once(): void
    {
        config(['midtrans.server_key' => 'test-server-key']);

        $customer = $this->createUser(UserRole::Customer);
        $product = $this->createProduct(stock: 10);
        $order = Order::createOnline($customer, [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'attempt_number' => 1,
            'provider' => 'midtrans',
            'midtrans_order_id' => 'KZ-CANCEL-TEST',
            'gross_amount' => $order->grand_total,
            'status' => PaymentStatus::Pending,
        ]);
        $order->update(['payment_status' => PaymentStatus::Pending]);
        $grossAmount = number_format($order->grand_total, 2, '.', '');
        $payload = [
            'order_id' => $payment->midtrans_order_id,
            'status_code' => '202',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'cancel',
            'signature_key' => hash(
                'sha512',
                $payment->midtrans_order_id.'202'.$grossAmount.'test-server-key',
            ),
        ];

        $this->assertSame(8, $product->refresh()->stock);

        $this->postJson('/api/v1/payments/midtrans/notification', [
            ...$payload,
            'signature_key' => str_repeat('0', 128),
        ])->assertForbidden();
        $this->assertSame(8, $product->refresh()->stock);

        $this->postJson('/api/v1/payments/midtrans/notification', $payload)
            ->assertOk()
            ->assertJsonPath('data.payment_status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.order_status', OrderStatus::Cancelled->value);

        $this->assertSame(10, $product->refresh()->stock);
        $this->assertSame(
            1,
            StockMovement::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', StockMovementType::Restoration->value)
                ->count(),
        );

        $this->postJson('/api/v1/payments/midtrans/notification', $payload)->assertOk();

        $this->assertSame(10, $product->refresh()->stock);
        $this->assertSame(
            1,
            StockMovement::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', StockMovementType::Restoration->value)
                ->count(),
        );
    }

    public function test_owner_can_assign_driver_and_driver_can_finish_delivery(): void
    {
        $customer = $this->createUser(UserRole::Customer);
        $owner = $this->createUser(UserRole::Owner);
        $driver = $this->createUser(UserRole::Driver);
        $order = Order::query()->create([
            'order_number' => 'ORD-DELIVERY-TEST',
            'customer_id' => $customer->id,
            'channel' => OrderChannel::Online,
            'order_status' => OrderStatus::Ready,
            'payment_status' => PaymentStatus::Paid,
            'delivery_method' => DeliveryMethod::Delivery,
            'payment_method' => PaymentMethod::Midtrans,
            'subtotal' => 10000,
            'shipping_cost' => 10000,
            'discount' => 0,
            'grand_total' => 20000,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $deliveryId = $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => $driver->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::Assigned->value)
            ->json('data.id');

        Sanctum::actingAs($driver);

        foreach ([
            DeliveryStatus::PickedUp,
            DeliveryStatus::OnDelivery,
            DeliveryStatus::Delivered,
        ] as $status) {
            $this->patchJson("/api/v1/driver/deliveries/{$deliveryId}/status", [
                'status' => $status->value,
            ])
                ->assertOk()
                ->assertJsonPath('data.status', $status->value);
        }

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->order_status);
    }

    public function test_cod_is_paid_only_after_driver_confirms_payment_received(): void
    {
        config([
            'business.store.latitude' => 0.0,
            'business.store.longitude' => 0.0,
            'business.shipping.rate_per_km' => 5000,
            'business.shipping.max_distance_km' => 100,
            'business.shipping.distance_tolerance_km' => 0.1,
        ]);

        $customer = $this->createUser(UserRole::Customer);
        $owner = $this->createUser(UserRole::Owner);
        $driver = $this->createUser(UserRole::Driver);
        $product = $this->createProduct(stock: 10);
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'label' => 'Rumah COD',
            'recipient_name' => $customer->name,
            'phone' => $customer->phone,
            'full_address' => 'Alamat COD test',
            'latitude' => 0.0,
            'longitude' => 0.01,
            'is_default' => true,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/orders', [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'payment_method' => PaymentMethod::Cash->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $orderId = $this->postJson('/api/v1/orders', [
            'delivery_method' => DeliveryMethod::Delivery->value,
            'payment_method' => PaymentMethod::Cash->value,
            'address_id' => $address->id,
            'distance_km' => 2.0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.order_status', OrderStatus::Confirmed->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value)
            ->assertJsonPath('data.payment_method', PaymentMethod::Cash->value)
            ->assertJsonPath('data.shipping_cost', 10000)
            ->assertJsonPath('data.grand_total', 30000)
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);

        $this->assertSame(8, $product->refresh()->stock);
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);

        $this->postJson("/api/v1/orders/{$order->id}/payment")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        Sanctum::actingAs($owner);

        foreach ([OrderStatus::Processing, OrderStatus::Ready] as $status) {
            $this->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => $status->value,
            ])->assertOk();
        }

        $deliveryId = $this->postJson("/api/v1/orders/{$order->id}/assign-driver", [
            'driver_id' => $driver->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::Assigned->value)
            ->json('data.id');

        Sanctum::actingAs($driver);

        foreach ([DeliveryStatus::PickedUp, DeliveryStatus::OnDelivery] as $status) {
            $this->patchJson("/api/v1/driver/deliveries/{$deliveryId}/status", [
                'status' => $status->value,
            ])->assertOk();
        }

        $this->patchJson("/api/v1/driver/deliveries/{$deliveryId}/status", [
            'status' => DeliveryStatus::Delivered->value,
            'payment_received' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_received');

        $this->assertSame(PaymentStatus::Unpaid, $order->refresh()->payment_status);
        $this->assertDatabaseHas('deliveries', [
            'id' => $deliveryId,
            'status' => DeliveryStatus::OnDelivery->value,
            'cod_payment_received_at' => null,
        ]);

        $this->patchJson("/api/v1/driver/deliveries/{$deliveryId}/status", [
            'status' => DeliveryStatus::Delivered->value,
            'payment_received' => true,
            'notes' => 'Pesanan tiba dan uang COD diterima lengkap.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryStatus::Delivered->value)
            ->assertJsonPath('data.cod_payment_received', true)
            ->assertJsonPath('data.cod_payment_received_by.id', $driver->id)
            ->assertJsonPath('data.order.order_status', OrderStatus::Delivered->value)
            ->assertJsonPath('data.order.payment_status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.order.payment_amount', 30000);

        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Delivered, $order->order_status);
        $this->assertSame(30000, $order->payment_amount);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(8, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'product_id' => $product->id,
            'type' => StockMovementType::Sale->value,
            'quantity' => -2,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'type' => StockMovementType::Reservation->value,
        ]);
        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'event' => 'payment_confirmed',
        ]);
    }

    private function createUser(UserRole $role): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->create([
            'name' => ucfirst($role->value).' Test',
            'email' => $role->value.$sequence.'@example.com',
            'phone' => '0810000000'.$sequence,
            'password' => 'secret123',
            'role' => $role,
            'status' => UserStatus::Active,
        ]);
    }

    private function createProduct(int $stock = 10): Product
    {
        static $sequence = 0;
        $sequence++;

        return Product::query()->create([
            'sku' => 'SKU-TEST-'.$sequence,
            'name' => 'Produk Test '.$sequence,
            'slug' => 'produk-test-'.$sequence,
            'cost_price' => 7000,
            'selling_price' => 10000,
            'stock' => $stock,
            'minimum_stock' => 2,
            'unit' => 'pack',
            'is_active' => true,
        ]);
    }
}
