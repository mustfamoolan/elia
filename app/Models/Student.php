<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'stage_id',
        'student_no',
        'name',
        'guardian_name',
        'guardian_phone',
        'phone',
        'address',
        'birth_date',
        'enrollment_date',
        'study_type',
        'shift',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'enrollment_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot(['relation', 'is_primary'])
            ->withTimestamps();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_student')
            ->withPivot(['fee', 'enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function homeworkTasks(): HasMany
    {
        return $this->hasMany(HomeworkTask::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(StudentCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getStageNameAttribute(): ?string
    {
        return $this->stage?->name;
    }

    public function getAttendanceRateAttribute(): int
    {
        $total = $this->attendances()->count();

        if ($total === 0) {
            return 0;
        }

        $present = $this->attendances()
            ->whereIn('status', ['present', 'late'])
            ->count();

        return (int) round(($present / $total) * 100);
    }

    public function getTotalFeesAttribute(): int
    {
        return (int) $this->charges()->sum('amount');
    }

    public function getTotalPaidAttribute(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    public function getRemainingBalanceAttribute(): int
    {
        return max(0, $this->total_fees - $this->total_paid);
    }

    public function getHasAccountAttribute(): bool
    {
        return $this->user_id !== null;
    }
}
