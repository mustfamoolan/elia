<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_task_id',
        'homework_submission_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(HomeworkTask::class, 'homework_task_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(HomeworkSubmission::class, 'homework_submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }

    public function getIsImageAttribute(): bool
    {
        if ($this->mime && str_starts_with(strtolower($this->mime), 'image/')) {
            return true;
        }

        $ext = strtolower(pathinfo((string) ($this->original_name ?: $this->path), PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true);
    }
}
