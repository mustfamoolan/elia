<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Grade;
use App\Models\HomeworkTask;
use App\Models\NotificationRecipient;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\RegistrationRequest;
use App\Models\ScheduleSlot;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * بيانات الواجهة المشتقة من قاعدة البيانات.
 *
 * تُعاد هنا المصفوفات بنفس مفاتيح الإعدادات القديمة (config/eyal.php)
 * حتى تبقى واجهات Blade الحالية كما هي دون تغيير في التصميم.
 */
class Eyal
{
    /**
     * إعدادات المعهد من قاعدة البيانات مع الرجوع إلى الإعدادات الثابتة.
     *
     * @return array<string, mixed>
     */
    public static function institute(): array
    {
        $config = config('eyal.institute', []);

        try {
            $settings = Setting::allAsArray();
        } catch (\Throwable) {
            $settings = [];
        }

        $institute = $config;

        foreach ([
            'name',
            'short_name',
            'address',
            'phone',
            'whatsapp',
            'whatsapp_intl',
            'phone_intl',
            'instagram',
            'tiktok',
            'facebook',
            'facebook_url',
            'maps_url',
            'work_hours',
            'announcement',
        ] as $key) {
            if (array_key_exists('institute.'.$key, $settings)) {
                $institute[$key] = $settings['institute.'.$key];
            }
        }

        $about = $settings['institute.about'] ?? [];
        $institute['about'] = array_merge(
            is_array($config['about'] ?? null) ? $config['about'] : [],
            is_array($about) ? $about : [],
        );

        $institute['primary_color'] = $settings['theme.primary_color'] ?? ($config['primary_color'] ?? '#0B3A63');
        $institute['secondary_color'] = $settings['theme.secondary_color'] ?? ($config['secondary_color'] ?? '#1565C0');
        $institute['staff_enabled'] = (bool) ($settings['features.staff_enabled'] ?? ($config['staff_enabled'] ?? true));
        $institute['parent_enabled'] = (bool) ($settings['features.parent_enabled'] ?? true);
        $institute['student_enabled'] = (bool) ($settings['features.student_enabled'] ?? true);
        $institute['announcement_bar'] = (bool) ($settings['features.announcement_bar'] ?? true);
        $institute['registrations_open'] = (bool) ($settings['features.registrations_open'] ?? true);
        $institute['chat_enabled'] = (bool) ($settings['features.chat_enabled'] ?? true);
        $institute['chat_parent_teacher_enabled'] = (bool) ($settings['features.chat_parent_teacher_enabled'] ?? true);
        $institute['chat_parent_admin_enabled'] = (bool) ($settings['features.chat_parent_admin_enabled'] ?? true);
        $institute['chat_staff_admin_enabled'] = (bool) ($settings['features.chat_staff_admin_enabled'] ?? true);

        return $institute;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function stages(): Collection
    {
        return Stage::query()
            ->withCount(['courses' => fn (Builder $query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Stage $stage) => [
                'id' => (string) $stage->id,
                'name' => $stage->name,
                'visible' => $stage->is_visible,
                'order' => $stage->sort_order,
                'icon' => $stage->icon,
                'color' => $stage->color,
                'description' => $stage->description,
                'courses' => (int) $stage->courses_count,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function courses(?string $stageId = null): Collection
    {
        $query = Course::query()
            ->with(['stage', 'teacher'])
            ->withCount('students')
            ->where('is_active', true);

        if ($stageId !== null && $stageId !== '') {
            $query->where('stage_id', $stageId);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(fn (Course $course) => [
                'id' => (string) $course->id,
                'name' => $course->name,
                'teacher' => $course->teacher?->name ?? '—',
                'stage' => (string) $course->stage_id,
                'stage_name' => $course->stage?->name ?? '—',
                'subject' => $course->subject,
                'price' => (int) $course->price,
                'seats' => (int) $course->seats,
                'enrolled' => (int) $course->students_count,
                'schedule' => $course->schedule_text,
                'description' => $course->description,
                'color' => $course->color,
                'active' => $course->is_active,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function offers(): Collection
    {
        return Offer::query()
            ->with('stage')
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Offer $offer) => [
                'id' => (string) $offer->id,
                'title' => $offer->title,
                'stage' => $offer->stage?->name ?? 'جميع المراحل',
                'old_price' => (int) $offer->old_price,
                'price' => (int) $offer->price,
                'discount' => $offer->discount_label,
                'expires' => $offer->ends_at?->toDateString() ?? '',
                'active' => $offer->is_active,
                'description' => $offer->description,
                'color' => $offer->color,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function announcements(): Collection
    {
        return Announcement::query()
            ->where('is_published', true)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => (string) $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'category' => $announcement->category,
                'date' => ($announcement->published_at ?? $announcement->created_at)?->toDateString() ?? '',
                'pinned' => $announcement->is_pinned,
                'color' => $announcement->color,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function schedule(): Collection
    {
        return ScheduleSlot::query()
            ->with(['course.stage', 'course.teacher'])
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ScheduleSlot $slot) => [
                'id' => (string) $slot->id,
                'course_id' => (string) ($slot->course_id ?? ''),
                'day' => $slot->day_name,
                'stage' => $slot->stage_name ?? $slot->course?->stage?->name ?? 'جميع المراحل',
                'course' => self::courseLabel($slot->course_name ?? $slot->course?->name ?? '—'),
                'time' => self::formatTimeRange($slot->start_time, $slot->end_time),
                'teacher' => $slot->teacher_name ?? $slot->course?->teacher?->name ?? 'الكادر التدريسي',
                'hall' => $slot->hall,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function staff(): Collection
    {
        $coursesByTeacher = Course::query()
            ->with('stage')
            ->whereNotNull('teacher_id')
            ->get()
            ->groupBy('teacher_id');

        return Staff::query()
            ->with('user')
            ->where('is_public', true)
            ->orderBy('id')
            ->get()
            ->map(function (Staff $member) use ($coursesByTeacher) {
                $tCourses = $coursesByTeacher->get($member->user_id, collect());
                $subjectsLabel = $tCourses->map(fn (Course $c) => $c->subject ?: $c->name)->filter()->unique()->implode('، ');
                $stagesLabel = $tCourses->map(fn (Course $c) => $c->stage?->name)->filter()->unique()->implode('، ');

                return [
                    'id' => (string) $member->id,
                    'name' => $member->user?->name ?? 'عضو الكادر',
                    'subject' => $member->subject ?: ($subjectsLabel ?: 'كادر تدريسي'),
                    'stage' => $member->stage_label ?: ($stagesLabel ?: 'جميع المراحل'),
                    'bio' => $member->bio,
                    'phone' => $member->phone,
                    'color' => $member->color,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function students(): Collection
    {
        return self::studentQuery()
            ->orderBy('student_no')
            ->get()
            ->map(fn (Student $student) => self::mapStudent($student))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function studentByNo(?string $studentNo): ?array
    {
        if (! $studentNo) {
            return null;
        }

        $student = self::studentQuery()
            ->where('student_no', (string) $studentNo)
            ->first();

        return $student ? self::mapStudent($student) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function studentByPhone(?string $phone): ?array
    {
        if (! $phone) {
            return null;
        }

        $student = self::studentQuery()
            ->where('phone', $phone)
            ->first();

        return $student ? self::mapStudent($student) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function searchStudent(string $term): ?array
    {
        $term = trim($term);

        if ($term === '') {
            return null;
        }

        $student = self::studentQuery()
            ->where(function (Builder $query) use ($term) {
                $query->where('student_no', $term)
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%');
            })
            ->first();

        return $student ? self::mapStudent($student) : null;
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return array<string, mixed>|null
     */
    /**
     * قائمة جميع أبناء ولي الأمر مع بياناتهم الأكاديمية
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function childrenForParent(int|array $parent): Collection
    {
        $parentId = is_array($parent) ? ($parent['id'] ?? null) : $parent;
        if (! $parentId) {
            return collect();
        }

        $students = self::studentQuery()
            ->whereHas('parents', fn (Builder $query) => $query->where('users.id', $parentId))
            ->orderBy('id')
            ->get();

        if ($students->isEmpty()) {
            $parentUser = User::find($parentId);
            if ($parentUser && $parentUser->phone) {
                $phoneMatches = Student::query()
                    ->where('guardian_phone', $parentUser->phone)
                    ->orWhere('phone', $parentUser->phone)
                    ->pluck('id');

                if ($phoneMatches->isNotEmpty()) {
                    foreach ($phoneMatches as $studentId) {
                        $parentUser->children()->syncWithoutDetaching([
                            (int) $studentId => ['relation' => 'ولي أمر', 'is_primary' => true],
                        ]);
                    }

                    $students = self::studentQuery()
                        ->whereHas('parents', fn (Builder $query) => $query->where('users.id', $parentId))
                        ->orderBy('id')
                        ->get();
                }
            }
        }

        return $students->map(fn (Student $student) => self::mapStudent($student));
    }

    public static function studentForUser(?array $user, int|string|null $selectedId = null): ?array
    {
        if (! $user) {
            return null;
        }

        $role = $user['role'] ?? null;
        $userId = $user['id'] ?? null;
        $studentNo = $user['student_no'] ?? null;

        if ($role === 'parent' && $userId) {
            $query = self::studentQuery()
                ->whereHas('parents', fn (Builder $query) => $query->where('users.id', $userId));

            if ($selectedId) {
                $student = (clone $query)->where(function ($q) use ($selectedId) {
                    $q->where('id', $selectedId)->orWhere('student_no', (string) $selectedId);
                })->first();

                if ($student) {
                    return self::mapStudent($student);
                }
            }

            $student = $query->first();

            if (! $student && $studentNo) {
                $student = self::studentQuery()->where('student_no', $studentNo)->first();
            }

            return $student ? self::mapStudent($student) : null;
        }

        if ($role === 'student') {
            $student = $userId
                ? self::studentQuery()->where('user_id', $userId)->first()
                : null;

            if (! $student && $studentNo) {
                $student = self::studentQuery()->where('student_no', $studentNo)->first();
            }

            return $student ? self::mapStudent($student) : null;
        }

        return null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function gradesFor(?string $studentNo): Collection
    {
        $student = self::findStudentModel($studentNo);

        if (! $student) {
            return collect();
        }

        return Grade::query()
            ->with(['assessment.course.stage'])
            ->where('student_id', $student->id)
            ->get()
            ->sortByDesc(fn (Grade $grade) => $grade->assessment?->assessed_at?->timestamp ?? 0)
            ->map(function (Grade $grade) use ($student) {
                $assessment = $grade->assessment;
                $course = $assessment?->course;

                return [
                    'id' => (string) $grade->id,
                    'student_no' => $student->student_no,
                    'student' => $student->name,
                    'course' => self::courseLabel($course?->name ?? '—'),
                    'exam' => $assessment?->title ?? '—',
                    'score' => (int) round((float) $grade->score),
                    'max' => (int) round((float) ($assessment?->max_score ?? 100)),
                    'date' => $assessment?->assessed_at?->toDateString() ?? '',
                    'stage' => $course?->stage?->name ?? $student->stage?->name ?? '—',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function attendanceFor(?string $studentNo): Collection
    {
        $student = self::findStudentModel($studentNo);

        if (! $student) {
            return collect();
        }

        return AttendanceRecord::query()
            ->with(['course.stage'])
            ->where('student_id', $student->id)
            ->orderByDesc('date')
            ->get()
            ->map(fn (AttendanceRecord $record) => [
                'id' => (string) $record->id,
                'student_no' => $student->student_no,
                'student' => $student->name,
                'date' => $record->date?->toDateString() ?? '',
                'status' => $record->status,
                'stage' => $record->course?->stage?->name ?? $student->stage?->name ?? '—',
                'subject' => $record->course?->subject ?: ($record->course?->name ?: 'حضور عام'),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function paymentsFor(?string $studentNo): Collection
    {
        $student = self::findStudentModel($studentNo);

        if (! $student) {
            return collect();
        }

        return Payment::query()
            ->where('student_id', $student->id)
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => (string) $payment->id,
                'student_no' => $student->student_no,
                'student' => $student->name,
                'amount' => (int) $payment->amount,
                'date' => $payment->paid_at?->toDateString() ?? '',
                'method' => $payment->method_label,
                'receipt' => $payment->receipt_no,
                'note' => $payment->note,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function homeworkFor(?string $stageName = null, ?string $studentNo = null): Collection
    {
        $query = HomeworkTask::query()
            ->with(['course.stage', 'teacher', 'files', 'submissions.files'])
            ->where('status', '!=', 'closed');

        $student = self::findStudentModel($studentNo);

        if ($student) {
            if ($student->stage_id && $student->courses()->count() === 0) {
                $stageCourseIds = Course::query()
                    ->where('stage_id', $student->stage_id)
                    ->where('is_active', true)
                    ->pluck('id');

                foreach ($stageCourseIds as $stageCourseId) {
                    $student->courses()->syncWithoutDetaching([
                        (int) $stageCourseId => [
                            'fee' => 0,
                            'enrolled_at' => now()->toDateString(),
                            'status' => 'active',
                        ],
                    ]);
                }
            }

            $query->where(function (Builder $query) use ($student) {
                $query->where(function (Builder $query) use ($student) {
                    $query->where('is_class_task', true)
                        ->whereHas('course.students', fn (Builder $query) => $query->where('students.id', $student->id));
                })->orWhere('student_id', $student->id);
            });
        } elseif ($stageName) {
            $query->whereHas('course.stage', fn (Builder $query) => $query->where('name', $stageName));
        }

        return $query
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (HomeworkTask $task) use ($student) {
                $submission = $student
                    ? $task->submissions->firstWhere('student_id', $student->id)
                    : null;

                return [
                    'id' => (string) $task->id,
                    'title' => $task->title,
                    'course' => self::courseLabel($task->course?->name ?? '—'),
                    'course_id' => (string) ($task->course_id ?? ''),
                    'stage' => $task->course?->stage?->name ?? '—',
                    'teacher' => $task->teacher?->name ?? 'الإدارة',
                    'due' => $task->due_date?->toDateString() ?? '',
                    'description' => $task->description,
                    'color' => $task->color ?? 'blue',
                    'submission_status' => $submission?->status,
                    'submission_label' => $submission?->status_label,
                    'feedback' => $submission?->feedback,
                    'files' => $task->files->map(fn ($file) => [
                        'id' => (string) $file->id,
                        'name' => $file->original_name,
                        'size' => $file->human_size,
                    ])->values()->all(),
                    'submission_files' => $submission
                        ? $submission->files->map(fn ($file) => [
                            'id' => (string) $file->id,
                            'name' => $file->original_name,
                            'size' => $file->human_size,
                        ])->values()->all()
                        : [],
                ];
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return Collection<int, array<string, mixed>>
     */
    public static function notificationsFor(?array $user): Collection
    {
        if (! $user) {
            return SystemNotification::query()
                ->with(['stage', 'course', 'user'])
                ->where('audience_type', 'all')
                ->latest()
                ->get()
                ->map(fn (SystemNotification $notification) => self::mapNotification($notification, false))
                ->values();
        }

        if (($user['role'] ?? null) === 'admin') {
            return SystemNotification::query()
                ->with(['stage', 'course', 'user', 'recipients'])
                ->latest()
                ->get()
                ->map(function (SystemNotification $notification) use ($user) {
                    $recipient = $notification->recipients->firstWhere('user_id', $user['id']);

                    return self::mapNotification($notification, $recipient?->read_at !== null);
                })
                ->values();
        }

        $userIds = self::notificationUserIds($user);

        return NotificationRecipient::query()
            ->with(['notification.stage', 'notification.course', 'notification.user'])
            ->whereIn('user_id', $userIds)
            ->get()
            ->filter(fn (NotificationRecipient $recipient) => $recipient->notification !== null)
            ->groupBy('notification_id')
            ->map(function (Collection $recipients) use ($user) {
                /** @var NotificationRecipient $recipient */
                $recipient = $recipients->firstWhere('user_id', $user['id']) ?? $recipients->first();

                return self::mapNotification($recipient->notification, $recipient->read_at !== null);
            })
            ->sortByDesc('date')
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    public static function unreadNotificationsCount(?array $user): int
    {
        if (! $user) {
            return 0;
        }

        return NotificationRecipient::query()
            ->whereIn('user_id', self::notificationUserIds($user))
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @param  array<string, mixed>  $student
     */
    public static function remaining(array $student): int
    {
        return max(0, (int) $student['fee'] - (int) $student['paid']);
    }

    /**
     * @return array<string, int>
     */
    public static function stats(): array
    {
        $attendanceRecords = AttendanceRecord::count();
        $present = AttendanceRecord::whereIn('status', ['present', 'late'])->count();
        $totalFees = (int) StudentCharge::sum('amount');
        $totalPaid = (int) Payment::sum('amount');
        $registrations = RegistrationRequest::count();
        $offers = Offer::count();
        $courses = Course::count();

        return [
            'students' => Student::count(),
            'stages' => Stage::where('is_visible', true)->count(),
            'registrations' => $registrations,
            'new_registrations' => RegistrationRequest::where('status', 'new')->count(),
            'accepted_registrations' => RegistrationRequest::where('status', 'accepted')->count(),
            'rejected_registrations' => RegistrationRequest::where('status', 'rejected')->count(),
            'attendance_rate' => $attendanceRecords > 0 ? (int) round(($present / $attendanceRecords) * 100) : 0,
            'absences' => AttendanceRecord::where('status', 'absent')->count(),
            'lates' => AttendanceRecord::where('status', 'late')->count(),
            'attendance_records' => $attendanceRecords,
            'exams' => Grade::count(),
            'fees' => $totalFees,
            'paid' => $totalPaid,
            'remaining' => max(0, $totalFees - $totalPaid),
            'courses' => $courses,
            'active_courses' => Course::where('is_active', true)->count(),
            'offers' => $offers,
            'active_offers' => Offer::where('is_active', true)->count(),
            'payments' => Payment::count(),
            'payments_total' => $totalPaid,
            'staff' => Staff::count(),
            'announcements' => Announcement::where('is_published', true)->count(),
        ];
    }

    /**
     * @return array<int, array{name: string, count: int, percent: int}>
     */
    public static function studentsByStage(): array
    {
        $total = max(1, Student::count());

        return Stage::query()
            ->withCount('students')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Stage $stage) => [
                'name' => $stage->name,
                'count' => (int) $stage->students_count,
                'percent' => (int) round(($stage->students_count / $total) * 100),
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, count: int, price: int}>
     */
    public static function studentsByCourse(): array
    {
        return Course::query()
            ->withCount('students')
            ->orderBy('id')
            ->get()
            ->map(fn (Course $course) => [
                'name' => $course->name,
                'count' => (int) $course->students_count,
                'price' => (int) $course->price,
            ])
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function registrations(): Collection
    {
        return RegistrationRequest::query()
            ->with(['stage', 'course'])
            ->latest()
            ->get()
            ->map(fn (RegistrationRequest $registration) => [
                'id' => (string) $registration->id,
                'name' => $registration->student_name,
                'parent' => $registration->parent_name,
                'phone' => $registration->phone,
                'stage_id' => (string) ($registration->stage_id ?? ''),
                'stage' => $registration->stage?->name ?? '—',
                'course_id' => (string) ($registration->course_id ?? ''),
                'course' => self::courseLabel($registration->course?->name ?? ''),
                'date' => $registration->created_at?->toDateString() ?? '',
                'status' => $registration->status,
                'notes' => $registration->note,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function payments(): Collection
    {
        return Payment::query()
            ->with('student')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => (string) $payment->id,
                'student_no' => $payment->student?->student_no ?? '',
                'student' => $payment->student?->name ?? '—',
                'amount' => (int) $payment->amount,
                'date' => $payment->paid_at?->toDateString() ?? '',
                'method' => $payment->method_label,
                'method_raw' => $payment->method,
                'receipt' => $payment->receipt_no,
                'note' => $payment->note,
            ])
            ->values();
    }

    /**
     * جميع الدورات (بما فيها المتوقفة) لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function coursesAll(): Collection
    {
        return Course::query()
            ->with(['stage', 'teacher'])
            ->withCount('students')
            ->orderBy('id')
            ->get()
            ->map(fn (Course $course) => [
                'id' => (string) $course->id,
                'name' => $course->name,
                'teacher' => $course->teacher?->name ?? '—',
                'teacher_id' => (string) ($course->teacher_id ?? ''),
                'stage' => (string) $course->stage_id,
                'stage_name' => $course->stage?->name ?? '—',
                'subject' => $course->subject,
                'price' => (int) $course->price,
                'seats' => (int) $course->seats,
                'enrolled' => (int) $course->students_count,
                'schedule' => $course->schedule_text,
                'description' => $course->description,
                'color' => $course->color,
                'active' => $course->is_active,
            ])
            ->values();
    }

    /**
     * جميع أعضاء الكادر (بما فيهم غير الظاهرين للعامة) مع دوراتهم ومراحلهم وطلابهم.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function staffAll(): Collection
    {
        $coursesByTeacher = Course::query()
            ->with('stage')
            ->withCount('students')
            ->whereNotNull('teacher_id')
            ->get()
            ->groupBy('teacher_id');

        return Staff::query()
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(function (Staff $member) use ($coursesByTeacher) {
                $tCourses = $coursesByTeacher->get($member->user_id, collect());
                $subjectsLabel = $tCourses->map(fn (Course $c) => $c->subject ?: $c->name)->filter()->unique()->implode('، ');
                $stagesLabel = $tCourses->map(fn (Course $c) => $c->stage?->name)->filter()->unique()->implode('، ');

                return [
                    'id' => (string) $member->id,
                    'user_id' => (string) $member->user_id,
                    'name' => $member->user?->name ?? 'عضو الكادر',
                    'username' => $member->user?->username,
                    'subject' => $member->subject ?: ($subjectsLabel ?: ''),
                    'stage' => $member->stage_label ?: ($stagesLabel ?: ''),
                    'bio' => $member->bio,
                    'phone' => $member->phone ?? $member->user?->phone,
                    'color' => $member->color,
                    'active' => (bool) $member->user?->is_active,
                    'is_public' => $member->is_public,
                    'course_ids' => $tCourses->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                    'stage_ids' => $tCourses->pluck('stage_id')->filter()->unique()->map(fn ($id) => (string) $id)->values()->all(),
                    'courses_list' => $tCourses->map(fn (Course $c) => [
                        'id' => (string) $c->id,
                        'name' => $c->name,
                        'subject' => $c->subject,
                        'stage_name' => $c->stage?->name ?? '—',
                        'students_count' => (int) $c->students_count,
                    ])->values()->all(),
                    'courses_count' => $tCourses->count(),
                    'students_count' => (int) $tCourses->sum('students_count'),
                ];
            })
            ->values();
    }

    /**
     * قائمة جميع أولياء الأمور مع أبنائهم المرتبطين بهم لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function parentsAll(): Collection
    {
        return User::query()
            ->where('role', 'parent')
            ->with(['children.stage'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $parent) => [
                'id' => (string) $parent->id,
                'name' => $parent->name,
                'username' => $parent->username,
                'phone' => $parent->phone ?? '',
                'active' => (bool) $parent->is_active,
                'created_at' => $parent->created_at?->toDateString() ?? '',
                'student_ids' => $parent->children->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'children' => $parent->children->map(fn (Student $st) => [
                    'id' => (string) $st->id,
                    'student_no' => $st->student_no,
                    'name' => $st->name,
                    'stage_name' => $st->stage?->name ?? '—',
                    'relation' => $st->pivot?->relation ?? 'ولي أمر',
                ])->values()->all(),
                'children_count' => $parent->children->count(),
            ])
            ->values();
    }

    /**
     * قائمة حسابات الدخول لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function usersList(): Collection
    {
        return User::query()
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
                'active' => $user->is_active,
                'phone' => $user->phone,
                'created_at' => $user->created_at?->toDateString() ?? '',
            ])
            ->values();
    }

    /**
     * جميع الدرجات لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function gradesAll(): Collection
    {
        return Grade::query()
            ->with(['assessment.course.stage', 'student.stage'])
            ->get()
            ->sortByDesc(fn (Grade $grade) => $grade->assessment?->assessed_at?->timestamp ?? 0)
            ->map(function (Grade $grade) {
                $assessment = $grade->assessment;
                $course = $assessment?->course;

                return [
                    'id' => (string) $grade->id,
                    'assessment_id' => (string) ($assessment?->id ?? ''),
                    'student_id' => (string) $grade->student_id,
                    'student_no' => $grade->student?->student_no ?? '',
                    'student' => $grade->student?->name ?? '—',
                    'course' => self::courseLabel($course?->name ?? '—'),
                    'course_id' => (string) ($course?->id ?? ''),
                    'exam' => $assessment?->title ?? '—',
                    'score' => (int) round((float) $grade->score),
                    'max' => (int) round((float) ($assessment?->max_score ?? 100)),
                    'date' => $assessment?->assessed_at?->toDateString() ?? '',
                    'stage' => $course?->stage?->name ?? $grade->student?->stage?->name ?? '—',
                ];
            })
            ->values();
    }

    /**
     * آخر سجلات الحضور لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function attendanceAll(int $limit = 10): Collection
    {
        return AttendanceRecord::query()
            ->with(['student', 'course.stage'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AttendanceRecord $record) => [
                'id' => (string) $record->id,
                'student_id' => (string) ($record->student_id ?? ''),
                'student_no' => $record->student?->student_no ?? '',
                'student' => $record->student?->name ?? '—',
                'course_id' => (string) ($record->course_id ?? ''),
                'date' => $record->date?->toDateString() ?? '',
                'status' => $record->status,
                'stage' => $record->course?->stage?->name ?? $record->student?->stage?->name ?? '—',
                'subject' => $record->course?->subject ?: ($record->course?->name ?: 'حضور عام'),
            ])
            ->values();
    }

    /**
     * جميع الواجبات لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function homeworkAll(): Collection
    {
        return HomeworkTask::query()
            ->with(['course.stage', 'teacher'])
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (HomeworkTask $task) => [
                'id' => (string) $task->id,
                'course_id' => (string) ($task->course_id ?? ''),
                'title' => $task->title,
                'course' => self::courseLabel($task->course?->name ?? '—'),
                'stage' => $task->course?->stage?->name ?? '—',
                'teacher' => $task->teacher?->name ?? 'الإدارة',
                'due' => $task->due_date?->toDateString() ?? '',
                'description' => $task->description,
                'color' => $task->color ?? 'blue',
                'status' => $task->status,
            ])
            ->values();
    }

    /**
     * جميع العروض (بما فيها غير النشطة) لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function offersAll(): Collection
    {
        return Offer::query()
            ->with('stage')
            ->orderBy('id')
            ->get()
            ->map(fn (Offer $offer) => [
                'id' => (string) $offer->id,
                'title' => $offer->title,
                'stage' => $offer->stage?->name ?? 'جميع المراحل',
                'stage_id' => (string) ($offer->stage_id ?? ''),
                'old_price' => (int) $offer->old_price,
                'price' => (int) $offer->price,
                'discount' => $offer->discount_label,
                'expires' => $offer->ends_at?->toDateString() ?? '',
                'active' => $offer->is_active,
                'description' => $offer->description,
                'color' => $offer->color,
            ])
            ->values();
    }

    /**
     * جميع الإعلانات (بما فيها غير المنشورة) لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function announcementsAll(): Collection
    {
        return Announcement::query()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => (string) $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'category' => $announcement->category,
                'date' => ($announcement->published_at ?? $announcement->created_at)?->toDateString() ?? '',
                'pinned' => $announcement->is_pinned,
                'published' => $announcement->is_published,
                'color' => $announcement->color,
            ])
            ->values();
    }

    private static function studentQuery(): Builder
    {
        return Student::query()
            ->with(['stage', 'courses', 'charges', 'payments', 'parents'])
            ->withCount([
                'attendances as attendance_total',
                'attendances as attendance_present' => fn (Builder $query) => $query->whereIn('status', ['present', 'late']),
                'grades as grades_total',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapStudent(Student $student): array
    {
        if ($student->stage_id && $student->courses->isEmpty()) {
            $stageCourses = Course::query()
                ->where('stage_id', $student->stage_id)
                ->where('is_active', true)
                ->get();

            if ($stageCourses->isNotEmpty()) {
                foreach ($stageCourses as $stageCourse) {
                    $student->courses()->syncWithoutDetaching([
                        $stageCourse->id => [
                            'fee' => 0,
                            'enrolled_at' => now()->toDateString(),
                            'status' => 'active',
                        ],
                    ]);
                }
                $student->setRelation('courses', $stageCourses);
            }
        }

        $totalAttendance = (int) ($student->attendance_total ?? $student->attendances()->count());
        $presentAttendance = (int) ($student->attendance_present ?? $student->attendances()->whereIn('status', ['present', 'late'])->count());
        $totalFees = (int) $student->charges->sum('amount');
        $totalPaid = (int) $student->payments->sum('amount');

        if ($totalFees === 0) {
            $totalFees = (int) $student->courses->sum(fn (Course $course) => (int) ($course->pivot->fee ?? 0));
        }

        return [
            'id' => (string) $student->id,
            'student_no' => $student->student_no,
            'name' => $student->name,
            'parent' => $student->parents->first()?->name ?? $student->guardian_name ?? '',
            'parent_id' => (string) ($student->parents->first()?->id ?? ''),
            'phone' => $student->phone ?? $student->guardian_phone ?? '',
            'stage' => (string) $student->stage_id,
            'stage_name' => $student->stage?->name ?? '—',
            'course_ids' => $student->courses
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all(),
            'subjects' => $student->courses
                ->map(fn (Course $course) => $course->subject ?: $course->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'courses' => $student->courses
                ->map(fn (Course $course) => [
                    'id' => (string) $course->id,
                    'name' => $course->name,
                    'subject' => $course->subject,
                ])
                ->values()
                ->all(),
            'study_type' => $student->study_type,
            'shift' => $student->shift,
            'fee' => $totalFees,
            'paid' => $totalPaid,
            'notes' => $student->notes ?? '',
            'has_account' => $student->user_id !== null,
            'attendance' => $totalAttendance > 0 ? (int) round(($presentAttendance / $totalAttendance) * 100) : 0,
            'grades_count' => (int) ($student->grades_total ?? $student->grades()->count()),
            'registered_at' => $student->enrollment_date?->toDateString() ?? '',
            'status' => match ($student->status) {
                'inactive' => 'غير نشط',
                'graduated' => 'متخرج',
                'suspended' => 'موقوف',
                default => 'نشط',
            },
        ];
    }

    private static function findStudentModel(?string $studentNo): ?Student
    {
        if (! $studentNo) {
            return null;
        }

        return Student::query()
            ->with('stage')
            ->where('student_no', (string) $studentNo)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<int, int>
     */
    private static function notificationUserIds(array $user): array
    {
        $ids = [(int) ($user['id'] ?? 0)];

        if (($user['role'] ?? null) === 'parent' && ! empty($user['id'])) {
            $children = Student::query()
                ->whereHas('parents', fn (Builder $query) => $query->where('users.id', $user['id']))
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all();

            $ids = array_merge($ids, $children);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapNotification(SystemNotification $notification, bool $read): array
    {
        $audienceRaw = 'all';
        if ($notification->audience_type === 'role') {
            $audienceRaw = $notification->audience_role === 'student' ? 'students' : 'parents';
        } elseif ($notification->audience_type === 'user' && $notification->user_id) {
            $stNo = Student::where('user_id', $notification->user_id)->value('student_no');
            if ($stNo) {
                $audienceRaw = (string) $stNo;
            }
        }

        return [
            'id' => (string) $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'audience' => $notification->audience_label,
            'audience_raw' => $audienceRaw,
            'date' => ($notification->created_at ?? now())->toDateString(),
            'read' => $read,
            'color' => match ($notification->audience_type) {
                'stage' => 'amber',
                'user' => 'emerald',
                'role' => 'violet',
                default => 'blue',
            },
        ];
    }

    private static function courseLabel(?string $name): string
    {
        if (! $name) {
            return '';
        }

        return trim((string) preg_replace('/^دورة\s+/u', '', $name));
    }

    private static function formatTimeRange(?string $start, ?string $end): string
    {
        $startLabel = self::formatTime($start);
        $endLabel = self::formatTime($end);

        if ($startLabel === '' && $endLabel === '') {
            return '';
        }

        if ($startLabel === '') {
            return $endLabel;
        }

        if ($endLabel === '') {
            return $startLabel;
        }

        return $startLabel.' – '.$endLabel;
    }

    private static function formatTime(?string $time): string
    {
        if (! $time) {
            return '';
        }

        try {
            $value = Carbon::createFromFormat('H:i:s', $time);
        } catch (\Throwable) {
            try {
                $value = Carbon::parse($time);
            } catch (\Throwable) {
                return '';
            }
        }

        $hour = (int) $value->format('G');
        $suffix = $hour >= 12 ? 'م' : 'ص';
        $hour = $hour % 12;
        $hour = $hour === 0 ? 12 : $hour;

        return $hour.':'.$value->format('i').' '.$suffix;
    }
}
