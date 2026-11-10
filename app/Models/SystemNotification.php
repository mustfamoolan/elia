<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemNotification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'title',
        'body',
        'audience_type',
        'audience_role',
        'stage_id',
        'course_id',
        'user_id',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }

    public function getAudienceLabelAttribute(): string
    {
        return match ($this->audience_type) {
            'role' => match ($this->audience_role) {
                'student' => 'جميع الطلاب',
                'parent' => 'جميع أولياء الأمور',
                'staff' => 'جميع الكادر',
                default => 'حسب الدور',
            },
            'stage' => 'طلاب '.($this->stage?->name ?? 'مرحلة'),
            'course' => 'طلاب '.($this->course?->name ?? 'دورة'),
            'user' => $this->user?->name ?? 'مستخدم',
            default => 'جميع المستخدمين',
        };
    }
}
