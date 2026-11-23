<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Grade;
use App\Models\HomeworkFile;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\ScheduleSlot;
use App\Models\Student;
use App\Services\FcmService;
use App\Services\GeminiService;
use App\Services\WhatsAppService;
use App\Support\Eyal;
use App\Support\HomeworkStorage;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * ============================================================================
 * API الكادر التدريسي (Teacher Portal API)
 * المسار الأساسي: /api/v1/teacher/...
 * يختص بجميع صفحات ووظائف المدرس: لوحة المدرس، دوراته، طلابه، الحضور والغياب، الدرجات، الواجبات ومراجعتها، الجدول، والملف الشخصي.
 * ============================================================================
 */
class TeacherPortalApiController extends Controller
{
    /** @var Collection<int, Course>|null */
    private ?Collection $cachedCourses = null;

    /** @var array<int, array<int, int>> */
    private array $cachedTeacherSubjectIds = [];

    /**
     * لوحة تحكم المدرس الرئيسية (Teacher Dashboard)
     * GET /api/v1/teacher/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        if (Cache::add('eyal.gemini.bg_scan_lock', 1, 45)) {
            dispatch(function (): void {
                try {
                    app(GeminiService::class)->processUncheckedRecentMessages(25);
                } catch (\Throwable) {
                    // ignore background scan errors
                }
            })->afterResponse();
        }

        $user = $request->user()->load('staff');
        $courses = $this->teacherCourses($request);
        $courseIds = $courses->pluck('id');

        $studentsCount = Student::query()
            ->whereHas('courses', fn ($q) => $q->whereIn('courses.id', $courseIds))
            ->count();

        $pendingHomeworkCount = $this->teacherHomeworkQuery($request)
            ->whereIn('status', ['open', 'submitted', 'under_review'])
            ->count();

        $todaySlots = ScheduleSlot::query()
            ->whereIn('course_id', $courseIds)
            ->where('day_of_week', now()->dayOfWeek)
            ->where('is_active', true)
            ->with('course.stage:id,name')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ScheduleSlot $slot) => [
                'id' => (int) $slot->id,
                'course_id' => (int) $slot->course_id,
                'course_name' => $slot->course?->name ?? $slot->course_name,
                'stage_name' => $slot->course?->stage?->name ?? $slot->stage_name,
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'hall' => $slot->hall,
            ]);

        $recentHomework = $this->teacherHomeworkQuery($request)
            ->with(['course.stage:id,name', 'subject:id,name', 'student:id,name,student_no', 'files'])
            ->withCount([
                'submissions',
                'submissions as pending_submissions' => fn ($q) => $q->whereIn('status', ['submitted', 'under_review']),
            ])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (HomeworkTask $task) => $this->formatHomeworkTask($task));

        $taskIds = $this->teacherHomeworkQuery($request)->pluck('id');

        $recentSubmissions = HomeworkSubmission::query()
            ->whereIn('homework_task_id', $taskIds)
            ->with(['task.course:id,name', 'task.subject:id,name', 'student:id,name,student_no', 'files'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (HomeworkSubmission $sub) => [
                'id' => (int) $sub->id,
                'task_id' => (int) $sub->homework_task_id,
                'task_title' => $sub->task?->title ?? '—',
                'course_name' => $sub->task?->course?->name ?? '—',
                'subject_name' => $sub->task?->subject?->name ?? '',
                'student_id' => (int) $sub->student_id,
                'student_name' => $sub->student?->name ?? 'طالب',
                'student_no' => $sub->student?->student_no ?? '',
                'status' => $sub->status,
                'status_label' => $sub->status_label,
                'note' => $sub->note,
                'feedback' => $sub->feedback,
                'submitted_at' => $sub->created_at?->format('Y-m-d H:i'),
                'files' => $sub->files->map(fn (HomeworkFile $f) => $this->formatHomeworkFile($f))->values()->all(),
            ]);

        return response()->json([
            'status' => 'success',
            'teacher' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'subject' => $user->staff?->subject,
                'stage_label' => $user->staff?->stage_label,
            ],
            'stats' => [
                'courses_count' => $courses->count(),
                'students_count' => $studentsCount,
                'pending_homework_count' => $pendingHomeworkCount,
                'today_slots_count' => $todaySlots->count(),
            ],
            'today_slots' => $todaySlots->values()->all(),
            'courses' => $courses->map(fn (Course $c) => $this->formatCourse($c))->values()->all(),
            'recent_homework' => $recentHomework->values()->all(),
            'recent_submissions' => $recentSubmissions->values()->all(),
        ]);
    }

    /**
     * قائمة دورات ومواد المدرس
     * GET /api/v1/teacher/courses
     */
    public function courses(Request $request): JsonResponse
    {
        $courses = $this->teacherCourses($request)
            ->map(fn (Course $c) => $this->formatCourse($c))
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'courses' => $courses,
        ]);
    }

    /**
     * تفاصيل دورة محددة للمدرس مع الطلاب والجدول والواجبات
     * GET /api/v1/teacher/courses/{course}
     */
    public function courseShow(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $userId = (int) $request->user()->id;
        $mySubjects = $this->subjectsForTeacherInCourse($userId, $course);

        $course->load([
            'stage:id,name',
            'students' => fn ($q) => $q->with('stage:id,name')->orderBy('name'),
            'scheduleSlots' => fn ($q) => $q->orderBy('day_of_week')->orderBy('start_time'),
            'homeworkTasks' => fn ($q) => $q
                ->where(function ($hq) use ($userId, $mySubjects) {
                    $hq->where('teacher_id', $userId);
                    if ($mySubjects->isNotEmpty()) {
                        $hq->orWhereIn('subject_id', $mySubjects->pluck('id')->all());
                    }
                })
                ->with(['subject:id,name', 'student:id,name,student_no', 'files'])
                ->withCount(['submissions'])
                ->latest()
                ->take(15),
        ]);

        return response()->json([
            'status' => 'success',
            'course' => $this->formatCourse($course),
            'my_subjects' => $mySubjects->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])->values()->all(),
            'students' => $course->students->map(fn (Student $st) => [
                'id' => (int) $st->id,
                'student_no' => $st->student_no,
                'name' => $st->name,
                'stage_name' => $st->stage?->name ?? '—',
                'attendance_rate' => $st->attendance_rate,
            ])->values()->all(),
            'schedule_slots' => $course->scheduleSlots->map(fn (ScheduleSlot $slot) => [
                'id' => (int) $slot->id,
                'day_of_week' => (int) $slot->day_of_week,
                'start_time' => substr((string) $slot->start_time, 0, 5),
                'end_time' => substr((string) $slot->end_time, 0, 5),
                'hall' => $slot->hall,
            ])->values()->all(),
            'homework_tasks' => $course->homeworkTasks->map(fn (HomeworkTask $t) => $this->formatHomeworkTask($t))->values()->all(),
        ]);
    }

    /**
     * قائمة جميع طلاب المدرس مع نسبة حضورهم وعدد امتحاناتهم
     * GET /api/v1/teacher/students
     */
    public function students(Request $request): JsonResponse
    {
        $courseIds = $this->courseIds($request);

        $students = Student::query()
            ->whereHas('courses', fn ($q) => $q->whereIn('courses.id', $courseIds))
            ->with(['stage:id,name', 'courses' => fn ($q) => $q->whereIn('courses.id', $courseIds)])
            ->withCount([
                'attendances as attendance_total' => fn ($q) => $q->whereIn('course_id', $courseIds),
                'attendances as attendance_present' => fn ($q) => $q->whereIn('course_id', $courseIds)->whereIn('status', ['present', 'late', 'excused']),
                'grades as grades_total' => fn ($q) => $q->whereHas('assessment', fn ($aq) => $aq->whereIn('course_id', $courseIds)),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Student $st) {
                $total = (int) $st->attendance_total;
                $present = (int) $st->attendance_present;
                $rate = $total > 0 ? (int) round(($present / $total) * 100) : 100;

                return [
                    'id' => (int) $st->id,
                    'student_no' => $st->student_no,
                    'name' => $st->name,
                    'stage_name' => $st->stage?->name ?? '—',
                    'attendance_rate' => $rate,
                    'attendance_total' => $total,
                    'grades_count' => (int) $st->grades_total,
                    'courses' => $st->courses->map(fn (Course $c) => [
                        'id' => (int) $c->id,
                        'name' => $c->name,
                    ])->values()->all(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'students' => $students->values()->all(),
        ]);
    }

    /**
     * جلب قائمة الطلاب وسجل الحضور لدورة وتاريخ محدد
     * GET /api/v1/teacher/attendance?course_id=&date=
     */
    public function attendance(Request $request): JsonResponse
    {
        $courses = $this->teacherCourses($request);
        $selected = $courses->firstWhere('id', (int) $request->query('course_id', $request->query('course'))) ?? $courses->first();
        $date = $request->query('date', now()->toDateString());

        $studentsPayload = [];

        if ($selected) {
            $students = $selected->students()->with('stage:id,name')->orderBy('name')->get();
            $records = AttendanceRecord::query()
                ->where('course_id', $selected->id)
                ->where('date', $date)
                ->get()
                ->keyBy('student_id');

            foreach ($students as $st) {
                $rec = $records->get($st->id);
                $studentsPayload[] = [
                    'student_id' => (int) $st->id,
                    'student_no' => $st->student_no,
                    'name' => $st->name,
                    'stage_name' => $st->stage?->name ?? '—',
                    'status' => $rec?->status,
                    'status_label' => $rec?->status_label,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'date' => $date,
            'selected_course' => $selected ? $this->formatCourse($selected) : null,
            'courses' => $courses->map(fn (Course $c) => $this->formatCourse($c))->values()->all(),
            'students' => $studentsPayload,
        ]);
    }

    /**
     * حفظ الحضور والغياب والإجازات للطلاب مع إشعار أولياء الأمور (Push + WhatsApp)
     * POST /api/v1/teacher/attendance
     */
    public function attendanceStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'array'],
            'status.*' => ['nullable', 'in:present,late,absent,excused'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        $mySubjects = $this->subjectsForTeacherInCourse((int) $request->user()->id, $course);
        $defaultSubjectId = $mySubjects->first()?->id;
        $enrolled = $course->students()->pluck('students.id')->map(fn ($id) => (int) $id)->all();
        $saved = 0;

        foreach ($validated['status'] as $studentId => $status) {
            if ($status === null || $status === '' || ! in_array((int) $studentId, $enrolled, true)) {
                continue;
            }

            $record = AttendanceRecord::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'student_id' => (int) $studentId,
                    'date' => $validated['date'],
                ],
                [
                    'subject_id' => $defaultSubjectId,
                    'status' => $status,
                    'recorded_by' => $request->user()->id,
                ]
            );

            $studentModel = Student::with(['parents', 'user'])->find((int) $studentId);
            if ($studentModel) {
                $recipients = Notifier::studentRecipients($studentModel);
                if ($recipients->isNotEmpty()) {
                    FcmService::sendToUsers(
                        $recipients,
                        'تحديث الحضور والغياب — '.$studentModel->name,
                        'تم تسجيل حالة الطالب ('.$record->status_label.') في '.$course->name.' بتاريخ '.$validated['date'],
                        '/parent/attendance',
                        ['type' => 'attendance', 'tag' => 'att-'.$studentModel->id.'-'.$validated['date']]
                    );
                }
                if (! empty(Eyal::institute()['wa_notify_attendance'])) {
                    WhatsAppService::sendToStudentAndParents(
                        $studentModel,
                        "مرحباً ولي الأمر الكريم،\nتم تسجيل حالة الطالب *{$studentModel->name}*:\n• الحالة: *{$record->status_label}*\n• الدورة: {$course->name}\n• التاريخ: {$validated['date']}",
                        'att-'.$studentModel->id.'-'.$validated['date'].'-'.$status
                    );
                }
            }

            $saved++;
        }

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حفظ حضور '.$saved.' طالب بنجاح.',
            'saved_count' => $saved,
        ]);
    }

    /**
     * جلب الامتحانات والدرجات لدورة محددة
     * GET /api/v1/teacher/grades?course_id=
     */
    public function grades(Request $request): JsonResponse
    {
        $courses = $this->teacherCourses($request);
        $selected = $courses->firstWhere('id', (int) $request->query('course_id', $request->query('course'))) ?? $courses->first();

        $students = [];
        $assessments = [];

        if ($selected) {
            $students = $selected->students()->orderBy('name')->get(['students.id', 'students.student_no', 'students.name'])
                ->map(fn (Student $st) => [
                    'id' => (int) $st->id,
                    'student_no' => $st->student_no,
                    'name' => $st->name,
                ])->values()->all();

            $mySubjects = $this->subjectsForTeacherInCourse((int) $request->user()->id, $selected);
            $mySubjectIds = $mySubjects->pluck('id')->all();

            $assessments = Assessment::query()
                ->where('course_id', $selected->id)
                ->where(function ($q) use ($request, $mySubjectIds) {
                    $q->where('created_by', $request->user()->id);
                    if (! empty($mySubjectIds)) {
                        $q->orWhereIn('subject_id', $mySubjectIds);
                    }
                    $q->orWhereNull('created_by');
                })
                ->with(['grades.student:id,student_no,name', 'subject:id,name'])
                ->orderByDesc('assessed_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Assessment $a) => [
                    'id' => (int) $a->id,
                    'title' => $a->title,
                    'subject_id' => $a->subject_id ? (int) $a->subject_id : null,
                    'subject_name' => $a->subject?->name ?? '',
                    'max_score' => (float) $a->max_score,
                    'date' => $a->assessed_at?->toDateString(),
                    'grades' => $a->grades->map(fn (Grade $g) => [
                        'id' => (int) $g->id,
                        'student_id' => (int) $g->student_id,
                        'student_no' => $g->student?->student_no ?? '',
                        'student_name' => $g->student?->name ?? '—',
                        'score' => (float) $g->score,
                        'max_score' => (float) $a->max_score,
                    ])->values()->all(),
                ])->values()->all();
        }

        return response()->json([
            'status' => 'success',
            'selected_course' => $selected ? $this->formatCourse($selected) : null,
            'courses' => $courses->map(fn (Course $c) => $this->formatCourse($c))->values()->all(),
            'students' => $students,
            'assessments' => $assessments,
        ]);
    }

    /**
     * رصد درجة امتحان لطالب مع إشعار الواتساب
     * POST /api/v1/teacher/grades
     */
    public function gradesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'student_id' => ['required', 'exists:students,id'],
            'exam' => ['required', 'string', 'max:255'],
            'score' => ['required', 'numeric', 'min:0'],
            'max' => ['required', 'numeric', 'min:1'],
            'date' => ['nullable', 'date'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        $mySubjects = $this->subjectsForTeacherInCourse((int) $request->user()->id, $course);
        $subjectId = ! empty($validated['subject_id']) ? (int) $validated['subject_id'] : $mySubjects->first()?->id;

        [$assessment, $grade] = DB::transaction(function () use ($validated, $request, $course, $subjectId) {
            $assessment = Assessment::firstOrCreate(
                [
                    'course_id' => $course->id,
                    'title' => $validated['exam'],
                    'assessed_at' => $validated['date'] ?? now()->toDateString(),
                ],
                [
                    'subject_id' => $subjectId,
                    'type' => 'exam',
                    'max_score' => $validated['max'],
                    'created_by' => $request->user()->id,
                ]
            );

            $grade = Grade::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $validated['student_id']],
                ['score' => $validated['score'], 'recorded_by' => $request->user()->id]
            );

            return [$assessment, $grade];
        });

        if (! empty(Eyal::institute()['wa_notify_grades'])) {
            $studentModel = Student::find((int) $validated['student_id']);
            if ($studentModel) {
                WhatsAppService::sendToStudentAndParents(
                    $studentModel,
                    "إشعار درجة امتحان\n• الطالب: *{$studentModel->name}*\n• الامتحان: {$assessment->title} ({$course->name})\n• الدرجة: *{$grade->score} / {$assessment->max_score}*",
                    'grade-'.$grade->id.'-'.$grade->score
                );
            }
        }

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حفظ الدرجة بنجاح.',
            'grade_id' => (int) $grade->id,
        ], 201);
    }

    /**
     * حذف درجة امتحان
     * DELETE /api/v1/teacher/grades/{grade}
     */
    public function gradesDestroy(Request $request, Grade $grade): JsonResponse
    {
        $course = $grade->assessment?->course;
        abort_unless($course, 404);
        $this->authorizeCourse($request, $course);

        DB::transaction(function () use ($grade) {
            $assessment = $grade->assessment;
            $grade->delete();
            if ($assessment && $assessment->grades()->count() === 0) {
                $assessment->delete();
            }
        });

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الدرجة.',
        ]);
    }

    /**
     * قائمة جميع الواجبات والتحاضير المسندة للمدرس (بما فيها الموزعة تلقائياً عبر الذكاء الاصطناعي)
     * GET /api/v1/teacher/homework?course_id=&status=
     */
    public function homework(Request $request): JsonResponse
    {
        if (Cache::add('eyal.gemini.bg_scan_lock', 1, 45)) {
            dispatch(function (): void {
                try {
                    app(GeminiService::class)->processUncheckedRecentMessages(25);
                } catch (\Throwable) {
                    // ignore background scan errors
                }
            })->afterResponse();
        }

        $courses = $this->teacherCourses($request);
        $status = $request->query('status');
        $courseFilter = $request->query('course_id', $request->query('course'));

        $tasks = $this->teacherHomeworkQuery($request)
            ->with(['course.stage:id,name', 'subject:id,name', 'student:id,name,student_no', 'files'])
            ->withCount([
                'submissions',
                'submissions as pending_submissions' => fn ($q) => $q->whereIn('status', ['submitted', 'under_review']),
            ])
            ->when($courseFilter, fn ($q) => $q->where('course_id', (int) $courseFilter))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get()
            ->map(fn (HomeworkTask $t) => $this->formatHomeworkTask($t));

        return response()->json([
            'status' => 'success',
            'courses' => $courses->map(fn (Course $c) => $this->formatCourse($c))->values()->all(),
            'tasks' => $tasks->values()->all(),
        ]);
    }

    /**
     * إنشاء واجب جديد لطلاب الدورة
     * POST /api/v1/teacher/homework
     */
    public function homeworkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:20'],
            'files' => HomeworkStorage::multipleRules(false),
            'files.*' => HomeworkStorage::fileRules(false),
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        $mySubjects = $this->subjectsForTeacherInCourse((int) $request->user()->id, $course);
        $subjectId = ! empty($validated['subject_id']) ? (int) $validated['subject_id'] : $mySubjects->first()?->id;

        $task = DB::transaction(function () use ($validated, $request, $course, $subjectId) {
            $task = HomeworkTask::create([
                'course_id' => $course->id,
                'subject_id' => $subjectId,
                'teacher_id' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'source' => 'teacher',
                'is_class_task' => true,
                'status' => 'open',
                'created_by' => $request->user()->id,
                'color' => $validated['color'] ?? 'blue',
            ]);

            foreach ($request->file('files', []) as $file) {
                HomeworkStorage::store($file, $task, null, $request->user());
            }

            return $task;
        });

        Notifier::sendToCourse(
            $course,
            'واجب جديد: '.$task->title,
            'أضاف المدرس '.$request->user()->name.' واجباً جديداً في دورة '.$course->name.'.',
            ['created_by' => $request->user()->id]
        );

        if (! empty(Eyal::institute()['wa_notify_homework'])) {
            $course->loadMissing('students.parents');
            $dueTxt = $task->due_date ? "\n• موعد التسليم: {$task->due_date->format('Y-m-d')}" : '';
            foreach ($course->students as $st) {
                WhatsAppService::sendToStudentAndParents(
                    $st,
                    "واجب جديد للطالب *{$st->name}*\n• العنوان: {$task->title}\n• الدورة: {$course->name}{$dueTxt}",
                    'hw-'.$task->id.'-'.$st->id
                );
            }
        }

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم نشر الواجب وإشعار الطلاب وأولياء أمورهم بنجاح.',
            'task' => $this->formatHomeworkTask($task->load(['course.stage', 'subject', 'files'])),
        ], 201);
    }

    /**
     * تفاصيل واجب محدد مع الصور المرفقة وجميع تسليمات الطلاب
     * GET /api/v1/teacher/homework/{task}
     */
    public function homeworkShow(Request $request, HomeworkTask $task): JsonResponse
    {
        $this->authorizeTask($request, $task);

        $task->load([
            'course.stage:id,name',
            'course.students' => fn ($q) => $q->orderBy('name'),
            'subject:id,name',
            'student:id,name,student_no',
            'files',
            'submissions.student:id,name,student_no',
            'submissions.files',
            'submissions.submitter:id,name,role',
        ]);

        $submittedStudentIds = $task->submissions->pluck('student_id')->all();
        $missingStudents = $task->is_class_task
            ? ($task->course?->students->whereNotIn('id', $submittedStudentIds) ?? collect())
            : ($task->student && ! in_array($task->student->id, $submittedStudentIds, true) ? collect([$task->student]) : collect());

        return response()->json([
            'status' => 'success',
            'task' => $this->formatHomeworkTask($task),
            'submissions' => $task->submissions->map(fn (HomeworkSubmission $sub) => [
                'id' => (int) $sub->id,
                'student_id' => (int) $sub->student_id,
                'student_name' => $sub->student?->name ?? 'طالب',
                'student_no' => $sub->student?->student_no ?? '',
                'submitted_by_name' => $sub->submitter?->name ?? '—',
                'status' => $sub->status,
                'status_label' => $sub->status_label,
                'note' => $sub->note,
                'feedback' => $sub->feedback,
                'submitted_at' => $sub->created_at?->format('Y-m-d H:i'),
                'reviewed_at' => $sub->reviewed_at?->format('Y-m-d H:i'),
                'files' => $sub->files->map(fn (HomeworkFile $f) => $this->formatHomeworkFile($f))->values()->all(),
            ])->values()->all(),
            'missing_students' => $missingStudents->map(fn (Student $st) => [
                'id' => (int) $st->id,
                'student_no' => $st->student_no,
                'name' => $st->name,
            ])->values()->all(),
        ]);
    }

    /**
     * تعديل بيانات وحالة الواجب
     * PUT /api/v1/teacher/homework/{task}
     */
    public function homeworkUpdate(Request $request, HomeworkTask $task): JsonResponse
    {
        $this->authorizeTask($request, $task);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['open', 'submitted', 'under_review', 'reviewed', 'needs_revision', 'closed'])],
        ]);

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'color' => $validated['color'] ?? $task->color,
            'status' => $validated['status'] ?? $task->status,
        ]);

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث الواجب بنجاح.',
            'task' => $this->formatHomeworkTask($task->fresh(['course.stage', 'subject', 'student', 'files'])),
        ]);
    }

    /**
     * حذف واجب
     * DELETE /api/v1/teacher/homework/{task}
     */
    public function homeworkDestroy(Request $request, HomeworkTask $task): JsonResponse
    {
        $this->authorizeTask($request, $task);
        $task->delete();
        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الواجب بنجاح.',
        ]);
    }

    /**
     * مراجعة تسليم طالب وكتابة ملاحظات المدرس (مع إشعار الطالب وولي الأمر عبر التطبيق + Push + WhatsApp)
     * POST /api/v1/teacher/homework/{task}/submissions/{submission}/review
     */
    public function reviewSubmission(Request $request, HomeworkTask $task, HomeworkSubmission $submission): JsonResponse
    {
        $this->authorizeTask($request, $task);
        abort_unless((int) $submission->homework_task_id === (int) $task->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'reviewed', 'needs_revision'])],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $request, $task, $submission) {
            $submission->update([
                'status' => $validated['status'],
                'feedback' => $validated['feedback'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $task->update([
                'status' => $validated['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'feedback' => $validated['feedback'] ?? null,
            ]);
        });

        $label = match ($validated['status']) {
            'reviewed' => 'تمت مراجعة الواجب',
            'needs_revision' => 'الواجب يحتاج تعديلاً',
            default => 'الواجب قيد المراجعة',
        };

        if ($submission->student) {
            Notifier::sendToStudentAndParents(
                $submission->student,
                $label.': '.$task->title,
                'راجع المدرس '.$request->user()->name.' واجب «'.$task->title.'» في دورة '.($task->course?->name ?? '—').'.'
                    .(! empty($validated['feedback']) ? ' ملاحظات: '.$validated['feedback'] : ''),
                ['created_by' => $request->user()->id, 'course_id' => $task->course_id]
            );

            if (! empty(Eyal::institute()['wa_notify_homework'])) {
                $feedbackTxt = ! empty($validated['feedback']) ? "\n• ملاحظات المدرس: {$validated['feedback']}" : '';
                WhatsAppService::sendToStudentAndParents(
                    $submission->student,
                    "{$label}\n• الطالب: *{$submission->student->name}*\n• الواجب: {$task->title}\n• الدورة: ".($task->course?->name ?? '—')."\n• المدرس: أ. {$request->user()->name}{$feedbackTxt}",
                    'hw-rev-'.$submission->id.'-'.$validated['status']
                );
            }
        }

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حفظ المراجعة وإشعار الطالب وأولياء الأمور.',
        ]);
    }

    /**
     * الجدول الأسبوعي للمدرس
     * GET /api/v1/teacher/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $courses = $this->teacherCourses($request);

        $slots = ScheduleSlot::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->where('is_active', true)
            ->with('course.stage:id,name')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ScheduleSlot $s) => [
                'id' => (int) $s->id,
                'course_id' => (int) $s->course_id,
                'course_name' => $s->course?->name ?? $s->course_name,
                'stage_name' => $s->course?->stage?->name ?? $s->stage_name,
                'day_of_week' => (int) $s->day_of_week,
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time' => substr((string) $s->end_time, 0, 5),
                'hall' => $s->hall,
            ]);

        return response()->json([
            'status' => 'success',
            'slots' => $slots->values()->all(),
            'slots_by_day' => $slots->groupBy('day_of_week'),
        ]);
    }

    /**
     * جلب بيانات الملف الشخصي للمدرس
     * GET /api/v1/teacher/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('staff');

        return response()->json([
            'status' => 'success',
            'profile' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'phone' => $user->phone ?: $user->staff?->phone,
                'subject' => $user->staff?->subject,
                'stage_label' => $user->staff?->stage_label,
                'bio' => $user->staff?->bio,
                'hire_date' => $user->staff?->hire_date?->toDateString(),
            ],
        ]);
    }

    /**
     * تحديث بيانات الملف الشخصي للمدرس
     * PUT /api/v1/teacher/profile
     */
    public function profileUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated, $user) {
            $data = [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
            ];
            if (! empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }
            $user->update($data);

            if ($user->staff) {
                $user->staff->update([
                    'bio' => $validated['bio'] ?? $user->staff->bio,
                    'phone' => $validated['phone'] ?? $user->staff->phone,
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث بيانات الملف الشخصي بنجاح.',
        ]);
    }

    /* -------------------------------------------------------------------------
     | الدوال المساعدة الداخلية لبوابة المدرس
     | ------------------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    private function formatCourse(Course $c): array
    {
        return [
            'id' => (int) $c->id,
            'name' => $c->name,
            'subject' => $c->subject,
            'stage_id' => $c->stage_id ? (int) $c->stage_id : null,
            'stage_name' => $c->stage?->name ?? '—',
            'schedule_text' => $c->schedule_text,
            'students_count' => (int) ($c->students_count ?? 0),
            'homework_tasks_count' => (int) ($c->homework_tasks_count ?? 0),
            'my_subjects' => ($c->mySubjects ?? collect())->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHomeworkTask(HomeworkTask $task): array
    {
        return [
            'id' => (int) $task->id,
            'course_id' => (int) $task->course_id,
            'course_name' => $task->course?->name ?? '—',
            'stage_name' => $task->course?->stage?->name ?? '—',
            'subject_id' => $task->subject_id ? (int) $task->subject_id : null,
            'subject_name' => $task->subject?->name ?? '',
            'student_id' => $task->student_id ? (int) $task->student_id : null,
            'student_name' => $task->student?->name ?? null,
            'student_no' => $task->student?->student_no ?? null,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toDateString(),
            'source' => $task->source,
            'source_label' => $task->source_label,
            'is_class_task' => (bool) $task->is_class_task,
            'status' => $task->status,
            'status_label' => $task->status_label,
            'feedback' => $task->feedback,
            'color' => $task->color ?? 'blue',
            'submissions_count' => (int) ($task->submissions_count ?? $task->submissions?->count() ?? 0),
            'pending_submissions' => (int) ($task->pending_submissions ?? 0),
            'created_at' => $task->created_at?->format('Y-m-d H:i'),
            'files' => ($task->files ?? collect())->map(fn (HomeworkFile $f) => $this->formatHomeworkFile($f))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHomeworkFile(HomeworkFile $f): array
    {
        return [
            'id' => (int) $f->id,
            'name' => $f->original_name,
            'mime' => $f->mime,
            'size' => $f->human_size,
            'is_image' => (bool) $f->is_image,
            'inline_url' => url('/api/v1/homework/files/'.$f->id.'?inline=1'),
            'download_url' => url('/api/v1/homework/files/'.$f->id),
        ];
    }

    private function teacherCourses(Request $request): Collection
    {
        if ($this->cachedCourses !== null) {
            return $this->cachedCourses;
        }

        Eyal::ensureCurriculumTables();
        $userId = (int) $request->user()->id;
        $mySubjectIds = $this->teacherSubjectIds($userId);

        $courses = Course::query()
            ->where(function (Builder $query) use ($userId, $mySubjectIds) {
                $query->where('teacher_id', $userId)
                    ->orWhereHas('subjects', fn (Builder $sq) => $sq->where('course_subject.teacher_id', $userId))
                    ->orWhereHas('homeworkTasks', fn (Builder $hq) => $hq->where('teacher_id', $userId));

                if (! empty($mySubjectIds)) {
                    $query->orWhereHas('subjects', fn (Builder $sq) => $sq->whereIn('subjects.id', $mySubjectIds));
                }
            })
            ->with([
                'stage:id,name',
                'subjects' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ])
            ->withCount([
                'students',
                'homeworkTasks' => fn (Builder $hq) => $this->scopeHomeworkForTeacher($hq, $userId),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Course $course) use ($userId, $mySubjectIds) {
                $mySubjects = $course->subjects
                    ->filter(fn ($sub) => (int) ($sub->pivot?->teacher_id ?? 0) === $userId || in_array((int) $sub->id, $mySubjectIds, true))
                    ->values();

                if ($mySubjects->isNotEmpty()) {
                    $course->subject = $mySubjects->pluck('name')->implode(' + ');
                }

                $course->setRelation('mySubjects', $mySubjects);

                return $course;
            });

        $this->cachedCourses = $courses;

        return $this->cachedCourses;
    }

    private function courseIds(Request $request): Collection
    {
        return $this->teacherCourses($request)->pluck('id');
    }

    private function subjectsForTeacherInCourse(int $userId, Course $course): Collection
    {
        $course->loadMissing('subjects');
        $mySubjectIds = $this->teacherSubjectIds($userId);

        $mine = $course->subjects
            ->filter(fn ($sub) => (int) ($sub->pivot?->teacher_id ?? 0) === $userId || in_array((int) $sub->id, $mySubjectIds, true))
            ->values();

        if ($mine->isEmpty() && (int) $course->teacher_id === $userId) {
            return $course->subjects;
        }

        return $mine;
    }

    private function teacherHomeworkQuery(Request $request): Builder
    {
        $userId = (int) $request->user()->id;
        $courseIds = $this->courseIds($request);
        $mySubjectIds = $this->teacherSubjectIds($userId);

        return HomeworkTask::query()
            ->where(function (Builder $q) use ($userId, $courseIds, $mySubjectIds) {
                $q->where('teacher_id', $userId);

                if (! empty($mySubjectIds)) {
                    $q->orWhere(function (Builder $sq) use ($mySubjectIds) {
                        $sq->whereNull('teacher_id')
                            ->whereIn('subject_id', $mySubjectIds);
                    });
                }

                if ($courseIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $cq) use ($courseIds, $userId) {
                        $cq->whereIn('course_id', $courseIds)
                            ->where(fn (Builder $inner) => $this->scopeHomeworkForTeacher($inner, $userId));
                    });
                }
            });
    }

    /**
     * @return array<int, int>
     */
    private function teacherSubjectIds(int $userId): array
    {
        if (! array_key_exists($userId, $this->cachedTeacherSubjectIds)) {
            $fromCourseSubject = DB::table('course_subject')
                ->where('teacher_id', $userId)
                ->pluck('subject_id');

            $fromSubjectTeacher = DB::table('subject_teacher')
                ->where('teacher_id', $userId)
                ->pluck('subject_id');

            $this->cachedTeacherSubjectIds[$userId] = $fromCourseSubject
                ->merge($fromSubjectTeacher)
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->cachedTeacherSubjectIds[$userId];
    }

    private function scopeHomeworkForTeacher(Builder $query, int $userId): Builder
    {
        $mySubjectIds = $this->teacherSubjectIds($userId);

        return $query->where(function (Builder $q) use ($userId, $mySubjectIds) {
            $q->where('teacher_id', $userId);

            if (! empty($mySubjectIds)) {
                $q->orWhere(function (Builder $sq) use ($mySubjectIds) {
                    $sq->whereNull('teacher_id')
                        ->whereIn('subject_id', $mySubjectIds);
                });
            }
        });
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        $userId = (int) $request->user()->id;

        if ((int) $course->teacher_id === $userId) {
            return;
        }

        $teachesSubjectInCourse = DB::table('course_subject')
            ->where('course_id', $course->id)
            ->where('teacher_id', $userId)
            ->exists();

        if ($teachesSubjectInCourse) {
            return;
        }

        $mySubjectIds = $this->teacherSubjectIds($userId);
        if (! empty($mySubjectIds)) {
            $hasMySubject = DB::table('course_subject')
                ->where('course_id', $course->id)
                ->whereIn('subject_id', $mySubjectIds)
                ->exists();
            if ($hasMySubject) {
                return;
            }
        }

        $hasAssignedHomework = HomeworkTask::where('course_id', $course->id)
            ->where('teacher_id', $userId)
            ->exists();

        abort_unless($hasAssignedHomework, 403);
    }

    private function authorizeTask(Request $request, HomeworkTask $task): void
    {
        $userId = (int) $request->user()->id;

        if ((int) $task->teacher_id === $userId) {
            return;
        }

        if ($task->subject_id && in_array((int) $task->subject_id, $this->teacherSubjectIds($userId), true)) {
            return;
        }

        $task->loadMissing('course');
        $this->authorizeCourse($request, $task->course);
    }
}
