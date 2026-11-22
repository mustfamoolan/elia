<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\RegistrationRequest;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use App\Services\FcmService;
use App\Services\WhatsAppService;
use App\Support\Eyal;
use App\Support\HomeworkStorage;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * API أولياء الأمور (Parent Portal API)
 * المسار الأساسي: /api/v1/parent/...
 * يختص بجميع صفحات ووظائف ولي الأمر: الأبناء، النظرة العامة، الواجبات ورفعها، الدرجات، الحضور، الدفعات، وتسجيل ابن جديد.
 * ============================================================================
 */
class ParentPortalApiController extends Controller
{
    /**
     * النظرة العامة الشاملة لولي الأمر والابن المختار (Overview & All Tabs Summary)
     * GET /api/v1/parent/dashboard?child_id=
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $userArr = $user->toEyalArray();
        $children = Eyal::childrenForParent($userArr);
        $selectedChildId = $request->query('child_id');
        $child = Eyal::studentForUser($userArr, $selectedChildId);

        if (! $child) {
            return response()->json([
                'status' => 'success',
                'has_children' => false,
                'children' => [],
                'selected_child' => null,
                'summary' => null,
                'stages' => Eyal::stages()->where('visible', true)->values()->all(),
                'available_courses' => Eyal::courses()->values()->all(),
            ]);
        }

        $grades = Eyal::gradesFor($child['student_no']);
        $attendance = Eyal::attendanceFor($child['student_no']);
        $payments = Eyal::paymentsFor($child['student_no']);
        $homework = Eyal::homeworkFor(null, $child['student_no'])->map(fn (array $h) => $this->appendFileUrls($h));

        $isEnrolled = (bool) ($child['is_enrolled'] ?? ! empty($child['courses']));
        $isFree = (bool) ($child['is_free'] ?? false);
        $hasDiscount = (bool) ($child['has_discount'] ?? false);

        $totalFee = $isEnrolled ? (int) ($child['fee'] ?? 0) : 0;
        $totalPaid = $isEnrolled ? (int) $payments->sum('amount') : 0;
        $remaining = $isEnrolled ? max(0, $totalFee - $totalPaid) : 0;
        $paidPercent = ($isEnrolled && $totalFee > 0) ? min(100, (int) round(($totalPaid / $totalFee) * 100)) : 100;

        $avgGrade = $grades->count() ? (int) round($grades->avg(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;
        $highestGrade = $grades->count() ? (int) round($grades->max(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;

        $submittedHwCount = $homework->filter(fn ($h) => ! empty($h['submission_status']))->count();
        $pendingHwCount = max(0, $homework->count() - $submittedHwCount);

        return response()->json([
            'status' => 'success',
            'has_children' => true,
            'children' => $children->values()->all(),
            'selected_child' => $child,
            'summary' => [
                'enrollment_status' => ! $isEnrolled ? 'unenrolled' : ($isFree ? 'free' : ($hasDiscount ? 'discounted' : 'enrolled')),
                'enrollment_label' => ! $isEnrolled ? 'غير مشترك' : ($isFree ? 'مشترك • مجاني' : ($hasDiscount ? 'مشترك • خصم' : 'مشترك')),
                'is_enrolled' => $isEnrolled,
                'is_free' => $isFree,
                'has_discount' => $hasDiscount,
                'attendance_rate' => (int) ($child['attendance'] ?? 0),
                'attendance_counts' => [
                    'total' => $attendance->count(),
                    'present' => $attendance->where('status', 'present')->count(),
                    'late' => $attendance->where('status', 'late')->count(),
                    'excused' => $attendance->where('status', 'excused')->count(),
                    'absent' => $attendance->where('status', 'absent')->count(),
                ],
                'grades_summary' => [
                    'exams_count' => $grades->count(),
                    'average_percent' => $avgGrade,
                    'highest_percent' => $highestGrade,
                ],
                'homework_summary' => [
                    'total' => $homework->count(),
                    'submitted' => $submittedHwCount,
                    'pending' => $pendingHwCount,
                ],
                'financial_summary' => [
                    'is_enrolled' => $isEnrolled,
                    'original_fee' => $isEnrolled ? (int) ($child['original_fee'] ?? $totalFee) : 0,
                    'total_fee' => $totalFee,
                    'total_paid' => $totalPaid,
                    'remaining' => $remaining,
                    'paid_percent' => $paidPercent,
                ],
            ],
            'homework' => $homework->values()->all(),
            'grades' => $grades->values()->all(),
            'attendance' => $attendance->values()->all(),
            'payments' => $isEnrolled ? $payments->values()->all() : [],
        ]);
    }

    /**
     * قائمة الأبناء المسجلين في حساب ولي الأمر مع كورساتهم وموادهم
     * GET /api/v1/parent/children
     */
    public function children(Request $request): JsonResponse
    {
        $children = Eyal::childrenForParent($request->user()->toEyalArray());

        return response()->json([
            'status' => 'success',
            'children_count' => $children->count(),
            'children' => $children->values()->all(),
        ]);
    }

    /**
     * واجبات وتحاضير الابن المختار
     * GET /api/v1/parent/homework?child_id=
     */
    public function homework(Request $request): JsonResponse
    {
        $child = $this->resolveChildArray($request);
        if (! $child) {
            return $this->noChildResponse();
        }

        $homework = Eyal::homeworkFor(null, $child['student_no'])
            ->map(fn (array $h) => $this->appendFileUrls($h))
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'child' => $child,
            'homework' => $homework,
        ]);
    }

    /**
     * رفع واجب أو تحضير جديد من ولي الأمر وتوجيهه تلقائياً لمدرس المادة المختص
     * POST /api/v1/parent/homework/upload
     */
    public function uploadHomework(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => ['nullable', 'exists:students,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => HomeworkStorage::fileRules(true),
        ], [
            'course_id.required' => 'يرجى اختيار الكورس المشترك به.',
            'title.required' => 'يرجى إدخال عنوان الواجب.',
            'file.required' => 'يرجى اختيار ملف أو صورة الواجب.',
        ]);

        $student = $this->resolveChildModel($user, $validated['student_id'] ?? null);
        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'الطالب المحدد غير مرتبط بحسابك.'], 403);
        }

        $course = $student->courses()
            ->with('subjects')
            ->where('courses.id', $validated['course_id'])
            ->first() ?? Course::with('subjects')->find($validated['course_id']);

        if (! $course) {
            return response()->json(['status' => 'error', 'message' => 'الكورس غير موجود.'], 404);
        }

        Eyal::ensureCurriculumTables();

        $subjectId = ! empty($validated['subject_id']) ? (int) $validated['subject_id'] : null;
        if (! $subjectId && $course->subjects->count() === 1) {
            $subjectId = (int) $course->subjects->first()->id;
        }

        $teacherId = $this->resolveTeacherForCourseSubject($course, $subjectId);

        $task = DB::transaction(function () use ($validated, $student, $course, $subjectId, $teacherId, $user, $request) {
            $task = HomeworkTask::create([
                'course_id' => $course->id,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'student_id' => $student->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'source' => 'parent',
                'is_class_task' => false,
                'status' => 'submitted',
                'created_by' => $user->id,
                'color' => 'violet',
            ]);

            $submission = HomeworkSubmission::create([
                'homework_task_id' => $task->id,
                'student_id' => $student->id,
                'submitted_by' => $user->id,
                'note' => $validated['note'] ?? null,
                'status' => 'submitted',
            ]);

            HomeworkStorage::store($request->file('file'), $task, $submission, $user);

            return $task;
        });

        $this->notifyAssignedTeacher($task, $student, $user);
        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم رفع الواجب وتوجيهه تلقائياً إلى المدرس المختص بالمادة.',
            'task_id' => (int) $task->id,
        ], 201);
    }

    /**
     * تسليم حل لواجب مطلوب من المدرس
     * POST /api/v1/parent/homework/{task}/submit
     */
    public function submitHomework(Request $request, HomeworkTask $task): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => ['nullable', 'exists:students,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => HomeworkStorage::fileRules(true),
        ], [
            'file.required' => 'يرجى اختيار ملف أو صورة الواجب.',
        ]);

        $student = $this->resolveChildModel($user, $validated['student_id'] ?? null);
        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'الطالب غير مرتبط بحسابك.'], 403);
        }

        DB::transaction(function () use ($task, $student, $user, $request, $validated) {
            $submission = HomeworkSubmission::updateOrCreate(
                ['homework_task_id' => $task->id, 'student_id' => $student->id],
                [
                    'submitted_by' => $user->id,
                    'note' => $validated['note'] ?? null,
                    'status' => 'submitted',
                    'feedback' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ],
            );

            HomeworkStorage::store($request->file('file'), $task, $submission, $user);
            $task->update(['status' => 'submitted']);
        });

        $this->notifyAssignedTeacher($task, $student, $user);
        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم رفع حل الواجب وإرساله إلى مدرس المادة للمراجعة.',
        ]);
    }

    /**
     * درجات وامتحانات الابن المختار
     * GET /api/v1/parent/grades?child_id=
     */
    public function grades(Request $request): JsonResponse
    {
        $child = $this->resolveChildArray($request);
        if (! $child) {
            return $this->noChildResponse();
        }

        $grades = Eyal::gradesFor($child['student_no']);
        $avg = $grades->count() ? (int) round($grades->avg(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;

        return response()->json([
            'status' => 'success',
            'child' => $child,
            'average_percent' => $avg,
            'grades' => $grades->values()->all(),
        ]);
    }

    /**
     * سجل حضور وغياب وإجازات الابن المختار
     * GET /api/v1/parent/attendance?child_id=
     */
    public function attendance(Request $request): JsonResponse
    {
        $child = $this->resolveChildArray($request);
        if (! $child) {
            return $this->noChildResponse();
        }

        $attendance = Eyal::attendanceFor($child['student_no']);

        return response()->json([
            'status' => 'success',
            'child' => $child,
            'attendance_rate' => (int) ($child['attendance'] ?? 0),
            'counts' => [
                'total' => $attendance->count(),
                'present' => $attendance->where('status', 'present')->count(),
                'late' => $attendance->where('status', 'late')->count(),
                'excused' => $attendance->where('status', 'excused')->count(),
                'absent' => $attendance->where('status', 'absent')->count(),
            ],
            'attendance' => $attendance->values()->all(),
        ]);
    }

    /**
     * الحسابات والدفعات المالية للابن المختار (مع عزل غير المشتركين بكورس)
     * GET /api/v1/parent/payments?child_id=
     */
    public function payments(Request $request): JsonResponse
    {
        $child = $this->resolveChildArray($request);
        if (! $child) {
            return $this->noChildResponse();
        }

        $isEnrolled = (bool) ($child['is_enrolled'] ?? ! empty($child['courses']));
        $payments = $isEnrolled ? Eyal::paymentsFor($child['student_no']) : collect();
        $totalFee = $isEnrolled ? (int) ($child['fee'] ?? 0) : 0;
        $totalPaid = $isEnrolled ? (int) $payments->sum('amount') : 0;

        return response()->json([
            'status' => 'success',
            'child' => $child,
            'is_enrolled' => $isEnrolled,
            'is_free' => (bool) ($child['is_free'] ?? false),
            'has_discount' => (bool) ($child['has_discount'] ?? false),
            'original_fee' => $isEnrolled ? (int) ($child['original_fee'] ?? $totalFee) : 0,
            'total_fee' => $totalFee,
            'total_paid' => $totalPaid,
            'remaining' => $isEnrolled ? max(0, $totalFee - $totalPaid) : 0,
            'payments' => $payments->values()->all(),
        ]);
    }

    /**
     * تسجيل ابن جديد من داخل حساب ولي الأمر
     * POST /api/v1/parent/register-child
     */
    public function registerChild(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'stage' => ['required', 'exists:stages,id'],
            'course' => ['nullable', 'exists:courses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'student_name.required' => 'يرجى إدخال اسم الابن.',
            'stage.required' => 'يرجى اختيار المرحلة الدراسية.',
        ]);

        $phone = ! empty($validated['phone']) ? $validated['phone'] : ($user->phone ?: '—');

        $registration = RegistrationRequest::create([
            'student_name' => $validated['student_name'],
            'parent_id' => $user->id,
            'parent_name' => $user->name,
            'parent_phone' => $phone,
            'phone' => $phone,
            'stage_id' => $validated['stage'],
            'course_id' => $validated['course'] ?? null,
            'note' => $validated['notes'] ?? null,
            'status' => 'new',
        ]);

        $stage = Stage::find($validated['stage']);
        FcmService::sendToAdmins(
            'طلب تسجيل ابن جديد (#'.$registration->id.')',
            $validated['student_name'].' — ولي الأمر: '.$user->name.' ('.($stage?->name ?? '').')',
            '/admin/registrations',
            ['type' => 'registration', 'registration_id' => (string) $registration->id]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'تم إرسال طلب تسجيل ابنك إلى إدارة المعهد بنجاح.',
            'registration_id' => (int) $registration->id,
        ], 201);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveChildArray(Request $request): ?array
    {
        return Eyal::studentForUser(
            $request->user()->toEyalArray(),
            $request->query('child_id') ?: $request->input('student_id')
        );
    }

    private function resolveChildModel(User $user, mixed $studentId = null): ?Student
    {
        $children = $user->children();
        if ($studentId) {
            return $children->where('students.id', (int) $studentId)->first()
                ?? Student::find((int) $studentId);
        }

        return $children->first();
    }

    private function noChildResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => 'no_child',
            'message' => 'لا يوجد طالب مرتبط بحسابك حالياً.',
        ], 404);
    }

    /**
     * @param  array<string, mixed>  $hw
     * @return array<string, mixed>
     */
    private function appendFileUrls(array $hw): array
    {
        $hw['files'] = collect($hw['files'] ?? [])->map(fn (array $f) => array_merge($f, [
            'inline_url' => url('/api/v1/homework/files/'.$f['id'].'?inline=1'),
            'download_url' => url('/api/v1/homework/files/'.$f['id']),
        ]))->values()->all();

        $hw['submission_files'] = collect($hw['submission_files'] ?? [])->map(fn (array $f) => array_merge($f, [
            'inline_url' => url('/api/v1/homework/files/'.$f['id'].'?inline=1'),
            'download_url' => url('/api/v1/homework/files/'.$f['id']),
        ]))->values()->all();

        return $hw;
    }

    private function resolveTeacherForCourseSubject(Course $course, ?int $subjectId): ?int
    {
        if ($subjectId) {
            $t = DB::table('course_subject')
                ->where('course_id', $course->id)
                ->where('subject_id', $subjectId)
                ->whereNotNull('teacher_id')
                ->value('teacher_id');
            if ($t) {
                return (int) $t;
            }

            $st = DB::table('subject_teacher')
                ->where('subject_id', $subjectId)
                ->value('teacher_id');
            if ($st) {
                return (int) $st;
            }
        }

        $first = DB::table('course_subject')
            ->where('course_id', $course->id)
            ->whereNotNull('teacher_id')
            ->value('teacher_id');

        return $first ? (int) $first : ($course->teacher_id ? (int) $course->teacher_id : null);
    }

    private function notifyAssignedTeacher(HomeworkTask $task, Student $student, User $uploader): void
    {
        $task->loadMissing(['course', 'subject']);
        $recipients = $task->teacher_id ? collect([$task->teacher_id]) : User::where('role', 'admin')->pluck('id');
        if ($recipients->isEmpty()) {
            return;
        }

        $subjectLabel = $task->subject ? ' (مادة '.$task->subject->name.')' : '';
        Notifier::send(
            $recipients,
            'تحضير / واجب جديد من ولي الأمر',
            'قام ولي الأمر '.$uploader->name.' برفع واجب «'.$task->title.'»'.$subjectLabel.' للطالب '.$student->name.'.',
            [
                'audience_type' => 'user',
                'course_id' => $task->course_id,
                'created_by' => $uploader->id,
                'push_url' => '/teacher/homework/'.$task->id,
            ]
        );

        if ($task->teacher_id) {
            $teacher = User::with('staff')->find($task->teacher_id);
            $teacherPhone = $teacher?->phone ?: $teacher?->staff?->phone;
            if ($teacherPhone) {
                WhatsAppService::sendText(
                    $teacherPhone,
                    "مرحباً أ. {$teacher->name}،\nلديك *تحضير / واجب جديد* من الطالب *{$student->name}*{$subjectLabel}:\n• العنوان: {$task->title}\n• الدورة: ".($task->course?->name ?? '—'),
                    'api-parent-hw-'.$task->id.'-'.$teacher->id
                );
            }
        }
    }
}
