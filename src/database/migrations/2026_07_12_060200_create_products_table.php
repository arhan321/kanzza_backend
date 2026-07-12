<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->unsignedBigInteger('selling_price');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->string('unit', 30)->default('pack');
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['name', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
