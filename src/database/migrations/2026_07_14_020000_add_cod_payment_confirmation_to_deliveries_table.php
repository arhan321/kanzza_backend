<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->timestamp('cod_payment_received_at')
                ->nullable()
                ->after('delivered_at');
            $table->foreignId('cod_payment_received_by')
                ->nullable()
                ->after('cod_payment_received_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cod_payment_received_by');
            $table->dropColumn('cod_payment_received_at');
        });
    }
};
