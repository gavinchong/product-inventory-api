<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['category_id', 'name', 'sku', 'description', 'price', 'stock'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'stock' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)->withTimestamps();
    }

    protected function stockStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->stock > 0 ? 'in_stock' : 'out_of_stock',
        );
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        foreach ([
            'min_price' => ['price', '>='],
            'max_price' => ['price', '<='],
            'min_stock' => ['stock', '>='],
            'max_stock' => ['stock', '<='],
        ] as $key => [$column, $operator]) {
            if (isset($filters[$key])) {
                $query->where($column, $operator, $filters[$key]);
            }
        }

        return $query;
    }
}
