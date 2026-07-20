<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function cashierOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function customerNotifications(): HasMany
    {
        return $this->hasMany(CustomerNotification::class);
    }

    public function cashierNotifications(): HasMany
    {
        return $this->hasMany(CashierNotification::class);
    }

    public function isRole(UserRole|string ...$roles): bool
    {
        $currentRole = $this->role instanceof UserRole
            ? $this->role->value
            : (string) $this->role;

        foreach ($roles as $role) {
            $expectedRole = $role instanceof UserRole ? $role->value : $role;

            if ($currentRole === $expectedRole) {
                return true;
            }
        }

        return false;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $builder, string $search) => $builder->where(
                    fn (Builder $inner) => $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"),
                ),
            )
            ->when(
                $filters['role'] ?? null,
                fn (Builder $builder, string $role) => $builder->where('role', $role),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $builder, string $status) => $builder->where('status', $status),
            );
    }
}
