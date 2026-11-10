<?php

namespace App\Support;

use App\Models\NotificationRecipient;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Notifier
{
    /**
     * إرسال إشعار لمجموعة مستخدمين محددين.
     *
     * @param  iterable<int, User|int>  $recipients
     * @param  array<string, mixed>  $attributes
     */
    public static function send(iterable $recipients, string $title, string $body, array $attributes = []): SystemNotification
    {
        $userIds = collect($recipients)
            ->map(fn ($recipient) => $recipient instanceof User ? $recipient->id : (int) $recipient)
            ->filter()
            ->unique()
            ->values();

        return DB::transaction(function () use ($userIds, $title, $body, $attributes) {
            $notification = SystemNotification::create(array_merge([
                'title' => $title,
                'body' => $body,
                'audience_type' => 'user',
                'audience_role' => null,
                'created_by' => $attributes['created_by'] ?? null,
            ], $attributes));

            foreach ($userIds as $userId) {
                NotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'user_id' => $userId,
                ]);
            }

            return $notification;
        });
    }

    /**
     * إرسال إشعار لكل أولياء أمور الطالب.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function sendToStudentAndParents(\App\Models\Student $student, string $title, string $body, array $attributes = []): SystemNotification
    {
        $recipients = self::studentRecipients($student);

        return self::send($recipients, $title, $body, $attributes);
    }

    /**
     * @return Collection<int, User>
     */
    public static function studentRecipients(\App\Models\Student $student): Collection
    {
        $users = $student->parents()->get();

        if ($student->user) {
            $users->push($student->user);
        }

        return $users->filter()->unique('id')->values();
    }

    /**
     * إرسال إشعار لطلاب دورة وأولياء أمورهم.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function sendToCourse(\App\Models\Course $course, string $title, string $body, array $attributes = []): SystemNotification
    {
        $course->loadMissing('students.parents', 'students.user');

        $recipients = collect();

        foreach ($course->students as $student) {
            $recipients = $recipients->merge(self::studentRecipients($student));
        }

        return self::send($recipients, $title, $body, array_merge([
            'audience_type' => 'course',
            'course_id' => $course->id,
        ], $attributes));
    }
}
