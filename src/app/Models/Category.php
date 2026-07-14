<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $builder, string $search) => $builder->where(
                    'name',
                    'like',
                    "%{$search}%",
                ),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $builder) => $builder->where(
                    'is_active',
                    filter_var($filters['is_active'], FILTER_VALIDATE_BOOL),
                ),
            );
    }
}
