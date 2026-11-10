<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'charge_id',
        'amount',
        'method',
        'receipt_no',
        'paid_at',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'paid_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(StudentCharge::class, 'charge_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'zaincash' => 'زين كاش',
            'transfer' => 'تحويل',
            default => 'نقدي',
        };
    }
}
