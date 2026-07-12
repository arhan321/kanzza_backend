<?php

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->unique()->after('email');
            $table->string('role', 20)->default(UserRole::Customer->value)->index()->after('password');
            $table->string('status', 20)->default(UserStatus::Active->value)->index()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'role', 'status', 'last_login_at']);
        });
    }
};
