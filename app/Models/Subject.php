<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_teacher', 'subject_id', 'teacher_id')
            ->withPivot(['stage_id'])
            ->withTimestamps();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_subject', 'subject_id', 'course_id')
            ->withPivot(['teacher_id'])
            ->withTimestamps();
    }
}
