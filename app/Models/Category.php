<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'name_ne', 'slug', 'emoji', 'options', 'keywords', 'typical_price_min', 'typical_price_max', 'status', 'sort_order'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'keywords' => 'array',
            'status' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function errands(): HasMany
    {
        return $this->hasMany(Errand::class);
    }

    public function isMain(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }

    /**
     * A main category matches itself and its subcategories.
     *
     * @return list<int>
     */
    public function matchingIds(): array
    {
        if (! $this->isMain()) {
            return [$this->id];
        }

        return [$this->id, ...$this->children()->pluck('id')->all()];
    }

    public function fullName(): string
    {
        return $this->parent ? "{$this->parent->name} › {$this->name}" : $this->name;
    }
}
