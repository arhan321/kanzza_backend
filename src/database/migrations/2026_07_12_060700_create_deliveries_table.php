<?php

use App\Domain\Enums\DeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default(DeliveryStatus::Unassigned->value)->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('proof_image')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
