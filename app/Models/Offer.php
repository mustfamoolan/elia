<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'stage_id',
        'discount_percent',
        'old_price',
        'price',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'old_price' => 'decimal:0',
            'price' => 'decimal:0',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getDiscountLabelAttribute(): string
    {
        return rtrim(rtrim((string) $this->discount_percent, '0'), '.').'%';
    }
}
