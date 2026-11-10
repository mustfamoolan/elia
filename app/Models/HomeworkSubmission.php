<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeworkSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_task_id',
        'student_id',
        'submitted_by',
        'note',
        'status',
        'feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(HomeworkTask::class, 'homework_task_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(HomeworkFile::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'submitted' => 'تم التسليم',
            'under_review' => 'قيد المراجعة',
            'reviewed' => 'تمت المراجعة',
            'needs_revision' => 'يحتاج تعديل',
            default => $this->status,
        };
    }
}
