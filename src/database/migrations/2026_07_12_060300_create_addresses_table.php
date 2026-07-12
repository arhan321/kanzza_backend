<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('recipient_name');
            $table->string('phone', 30);
            $table->text('full_address');
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('postal_code', 15)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
