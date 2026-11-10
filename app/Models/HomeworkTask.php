<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeworkTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'teacher_id',
        'student_id',
        'title',
        'description',
        'due_date',
        'source',
        'is_class_task',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'feedback',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_class_task' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(HomeworkFile::class);
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            'parent' => 'ولي أمر',
            'student' => 'طالب',
            'admin' => 'إدارة',
            default => 'مدرس',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'مفتوح',
            'submitted' => 'بانتظار المراجعة',
            'under_review' => 'قيد المراجعة',
            'reviewed' => 'تمت المراجعة',
            'needs_revision' => 'يحتاج تعديل',
            'closed' => 'مغلق',
            default => $this->status,
        };
    }
}
