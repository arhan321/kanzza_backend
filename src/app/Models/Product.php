<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'slug',
        'description',
        'cost_price',
        'selling_price',
        'stock',
        'minimum_stock',
        'unit',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'selling_price' => 'integer',
            'stock' => 'integer',
            'minimum_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->minimum_stock;
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $builder, string $search) => $builder->where(
                    fn (Builder $inner) => $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%"),
                ),
            )
            ->when(
                $filters['category_id'] ?? null,
                fn (Builder $builder, mixed $categoryId) => $builder->where(
                    'category_id',
                    $categoryId,
                ),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $builder) => $builder->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOL),
                ),
            )
            ->when(
                filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOL),
                fn (Builder $builder) => $builder->whereColumn('stock', '<=', 'minimum_stock'),
            );
    }
}
