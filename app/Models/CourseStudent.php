<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseStudent extends Model
{
    use HasFactory;

    protected $table = 'course_student';

    protected $fillable = [
        'course_id',
        'student_id',
        'fee',
        'enrolled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:0',
            'enrolled_at' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
