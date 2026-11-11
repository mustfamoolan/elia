<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot(['relation', 'is_primary'])
            ->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['last_read_at'])
            ->withTimestamps();
    }

    public function totalUnreadChatsCount(): int
    {
        $conversationIds = $this->conversations()->pluck('conversations.id');
        if ($conversationIds->isEmpty()) {
            return 0;
        }

        $participants = ConversationParticipant::where('user_id', $this->id)
            ->whereIn('conversation_id', $conversationIds)
            ->get()
            ->keyBy('conversation_id');

        $unread = 0;
        foreach ($conversationIds as $cid) {
            $lastRead = $participants[$cid]?->last_read_at;
            $query = Message::where('conversation_id', $cid)
                ->where('sender_id', '!=', $this->id);

            if ($lastRead) {
                $query->where('created_at', '>', $lastRead);
            }

            $unread += $query->count();
        }

        return $unread;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'مدير',
            'staff' => 'مدرس',
            'parent' => 'ولي أمر',
            default => 'طالب',
        };
    }

    /**
     * التوافق مع الواجهات الحالية التي تقرأ الجلسة.
     *
     * @return array<string, mixed>
     */
    public function toEyalArray(): array
    {
        $studentNo = $this->student?->student_no;

        if ($this->isParent()) {
            $studentNo = $this->children()->first()?->student_no;
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'role' => $this->role,
            'role_label' => $this->roleLabel(),
            'student_no' => $studentNo,
            'active' => $this->is_active,
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
