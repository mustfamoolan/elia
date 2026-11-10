<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_name',
        'birth_date',
        'stage_id',
        'course_id',
        'parent_name',
        'phone',
        'parent_phone',
        'address',
        'note',
        'status',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'new' => 'جديد',
            'contacted' => 'تم التواصل',
            'accepted' => 'مقبول',
            'rejected' => 'مرفوض',
            'enrolled' => 'مسجل',
            default => $this->status,
        };
    }
}
