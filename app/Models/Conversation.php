<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'student_id',
        'creator_id',
        'last_message_at',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'is_closed' => 'boolean',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function otherUser(int $currentUserId): ?User
    {
        return $this->users->firstWhere('id', '!==', $currentUserId);
    }

    public function unreadCountFor(int $userId): int
    {
        $participant = $this->participants->firstWhere('user_id', $userId);
        $lastReadAt = $participant?->last_read_at;

        if (!$lastReadAt) {
            return $this->messages()->where('sender_id', '!=', $userId)->count();
        }

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('created_at', '>', $lastReadAt)
            ->count();
    }

    public function titleFor(User $user): string
    {
        if ($this->title) {
            return $this->title;
        }

        $other = $this->otherUser($user->id);

        if ($other) {
            $label = $other->name;
            if ($this->student) {
                $label .= ' — حول الطالب: ' . $this->student->name;
            }
            return $label;
        }

        return match ($this->type) {
            'parent_admin' => 'محادثة الإدارة مع ولي الأمر',
            'parent_teacher' => 'محادثة المدرس مع ولي الأمر',
            'staff_admin' => 'محادثة الإدارة مع الكادر',
            default => 'محادثة',
        };
    }
}

