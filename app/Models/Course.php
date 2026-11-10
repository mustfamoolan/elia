<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'teacher_id',
        'name',
        'subject',
        'description',
        'price',
        'seats',
        'schedule_text',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:0',
            'seats' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'course_student')
            ->withPivot(['fee', 'enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function homeworkTasks(): HasMany
    {
        return $this->hasMany(HomeworkTask::class);
    }

    public function getStageNameAttribute(): ?string
    {
        return $this->stage?->name;
    }

    public function getTeacherNameAttribute(): ?string
    {
        return $this->teacher?->name;
    }

    public function getEnrolledCountAttribute(): int
    {
        if (array_key_exists('students_count', $this->attributes)) {
            return (int) $this->attributes['students_count'];
        }

        return $this->students()->count();
    }
}
