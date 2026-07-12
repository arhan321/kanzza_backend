<?php

use App\Domain\Enums\DeliveryMethod;
use App\Domain\Enums\OrderChannel;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PaymentMethod;
use App\Domain\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 60)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20)->default(OrderChannel::Online->value)->index();
            $table->string('order_status', 30)->default(OrderStatus::PendingPayment->value)->index();
            $table->string('payment_status', 30)->default(PaymentStatus::Unpaid->value)->index();
            $table->string('delivery_method', 20)->default(DeliveryMethod::Delivery->value);
            $table->string('payment_method', 20)->default(PaymentMethod::Midtrans->value);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->unsignedBigInteger('payment_amount')->nullable();
            $table->unsignedBigInteger('change_amount')->nullable();
            $table->json('address_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['cashier_id', 'created_at']);
            $table->index(['order_status', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
