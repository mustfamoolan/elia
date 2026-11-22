<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\Eyal;
use App\Support\HomeworkStorage;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * API الطالب (Student Portal API)
 * المسار الأساسي: /api/v1/student/...
 * يختص بجميع صفحات ووظائف الطالب: لوحة الطالب، الكورسات والمواد، الواجبات ورفعها، الدرجات، الحضور، الأقساط، والجدول.
 * ============================================================================
 */
class StudentPortalApiController extends Controller
{
    /**
     * لوحة الطالب الكاملة (Student Dashboard & Profile)
     * GET /api/v1/student/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $this->resolveStudentArray($request);
        if (! $student) {
            return $this->noStudentResponse();
        }

        $grades = Eyal::gradesFor($student['student_no']);
        $attendance = Eyal::attendanceFor($student['student_no']);
        $payments = Eyal::paymentsFor($student['student_no']);
        $homework = Eyal::homeworkFor(null, $student['student_no'])->map(fn (array $h) => $this->appendFileUrls($h));

        $isEnrolled = (bool) ($student['is_enrolled'] ?? ! empty($student['courses']));
        $isFree = (bool) ($student['is_free'] ?? false);
        $hasDiscount = (bool) ($student['has_discount'] ?? false);
        $avgGrade = $grades->count() ? (int) round($grades->avg(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;

        $totalFee = $isEnrolled ? (int) ($student['fee'] ?? 0) : 0;
        $totalPaid = $isEnrolled ? (int) $payments->sum('amount') : 0;

        return response()->json([
            'status' => 'success',
            'student' => $student,
            'summary' => [
                'stage_name' => $student['stage_name'] ?? '—',
                'subjects_count' => count($student['subjects'] ?? []),
                'courses_count' => count($student['courses'] ?? []),
                'exams_count' => $grades->count(),
                'attendance_records_count' => $attendance->count(),
                'attendance_rate' => (int) ($student['attendance'] ?? 0),
                'average_grade_percent' => $avgGrade,
                'enrollment_status' => ! $isEnrolled ? 'unenrolled' : ($isFree ? 'free' : ($hasDiscount ? 'discounted' : 'enrolled')),
                'enrollment_label' => ! $isEnrolled ? 'غير مشترك' : ($isFree ? 'مجاني' : ($hasDiscount ? 'مشترك • خصم' : 'مشترك')),
                'is_enrolled' => $isEnrolled,
                'is_free' => $isFree,
                'has_discount' => $hasDiscount,
                'financial' => [
                    'is_enrolled' => $isEnrolled,
                    'total_fee' => $totalFee,
                    'total_paid' => $totalPaid,
                    'remaining' => $isEnrolled ? max(0, $totalFee - $totalPaid) : 0,
                ],
            ],
            'courses' => $student['courses'] ?? [],
            'recent_grades' => $grades->take(6)->values()->all(),
            'recent_homework' => $homework->take(6)->values()->all(),
            'recent_attendance' => $attendance->take(10)->values()->all(),
        ]);
    }

    /**
     * الكورسات والمواد المشترك بها الطالب
     * GET /api/v1/student/courses
     */
    public function courses(Request $request): JsonResponse
    {
        $student = $this->resolveStudentArray($request);
        if (! $student) {
            return $this->noStudentResponse();
        }

        return response()->json([
            'status' => 'success',
            'is_enrolled' => (bool) ($student['is_enrolled'] ?? ! empty($student['courses'])),
            'courses' => $student['courses'] ?? [],
            'subjects' => $student['subjects'] ?? [],
        ]);
    }

    /**
     * جميع واجبات الطالب مع المرفقات وحالة المراجعة
     * GET /api/v1/student/homework
     */
    public function homework(Request $request): JsonResponse
    {
        $student = $this->resolveStudentArray($request);
        if (! $student) {
            return $this->noStudentResponse();
        }

        $homework = Eyal::homeworkFor(null, $student['student_no'])
            ->map(fn (array $h) => $this->appendFileUrls($h))
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'student' => $student,
            'homework' => $homework,
        ]);
    }

    /**
     * رفع واجب أو تحضير جديد من الطالب لمدرس المادة
     * POST /api/v1/student/homework/upload
     */
    public function uploadHomework(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentModel = $this->resolveStudentModel($user);
        if (! $studentModel) {
            return $this->noStudentResponse();
        }

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => HomeworkStorage::fileRules(true),
        ], [
            'course_id.required' => 'يرجى اختيار الكورس.',
            'title.required' => 'يرجى إدخال عنوان الواجب.',
            'file.required' => 'يرجى إرفاق ملف أو صورة الواجب.',
        ]);

        $course = Course::with('subjects')->findOrFail($validated['course_id']);
        $subjectId = ! empty($validated['subject_id']) ? (int) $validated['subject_id'] : ($course->subjects->count() === 1 ? (int) $course->subjects->first()->id : null);

        $teacherId = DB::table('course_subject')
            ->where('course_id', $course->id)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->whereNotNull('teacher_id')
            ->value('teacher_id')
            ?: ($subjectId ? DB::table('subject_teacher')->where('subject_id', $subjectId)->value('teacher_id') : null)
            ?: $course->teacher_id;

        $task = DB::transaction(function () use ($validated, $studentModel, $course, $subjectId, $teacherId, $user, $request) {
            $task = HomeworkTask::create([
                'course_id' => $course->id,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId ? (int) $teacherId : null,
                'student_id' => $studentModel->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'source' => 'student',
                'is_class_task' => false,
                'status' => 'submitted',
                'created_by' => $user->id,
                'color' => 'violet',
            ]);

            $submission = HomeworkSubmission::create([
                'homework_task_id' => $task->id,
                'student_id' => $studentModel->id,
                'submitted_by' => $user->id,
                'note' => $validated['note'] ?? null,
                'status' => 'submitted',
            ]);

            HomeworkStorage::store($request->file('file'), $task, $submission, $user);

            return $task;
        });

        $this->notifyTeacher($task, $studentModel, $user);
        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم رفع الواجب وإرساله إلى مدرس المادة بنجاح.',
            'task_id' => (int) $task->id,
        ], 201);
    }

    /**
     * تسليم حل لواجب مطلوب من المدرس
     * POST /api/v1/student/homework/{task}/submit
     */
    public function submitHomework(Request $request, HomeworkTask $task): JsonResponse
    {
        $user = $request->user();
        $studentModel = $this->resolveStudentModel($user);
        if (! $studentModel) {
            return $this->noStudentResponse();
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => HomeworkStorage::fileRules(true),
        ], [
            'file.required' => 'يرجى إرفاق ملف أو صورة الحل.',
        ]);

        DB::transaction(function () use ($task, $studentModel, $user, $request, $validated) {
            $submission = HomeworkSubmission::updateOrCreate(
                ['homework_task_id' => $task->id, 'student_id' => $studentModel->id],
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

        $this->notifyTeacher($task, $studentModel, $user);
        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسليم حل الواجب للمدرس بنجاح.',
        ]);
    }

    /**
     * درجات وامتحانات الطالب
     * GET /api/v1/student/grades
     */
    public function grades(Request $request): JsonResponse
    {
        $student = $this->resolveStudentArray($request);
        if (! $student) {
            return $this->noStudentResponse();
        }

        $grades = Eyal::gradesFor($student['student_no']);
        $avg = $grades->count() ? (int) round($grades->avg(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;

        return response()->json([
            'status' => 'success',
            'average_percent' => $avg,
            'grades' => $grades->values()->all(),
        ]);
    }

    /**
     * سجل حضور وغياب وإجازات الطالب
     * GET /api/v1/student/attendance
     */
    public function attendance(Request $request): JsonResponse
    {
        $student = $this->resolveStudentArray($request);
        if (! $student) {
            return $this->noStudentResponse();
        }

        $attendance = Eyal::attendanceFor($student['student_no']);

        return response()->json([
            'status' => 'success',
            'attendance_rate' => (int) ($student['attendance'] ?? 0),
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
     * الحسابات المالية والدفعات للطالب
     * GET /api/v1/student/payments
     */
    public function payments(Request $request): JsonResponse
    {
        $student = $this->resolveStudentArray($request);
        if (! $student) {
            return $this->noStudentResponse();
        }

        $isEnrolled = (bool) ($student['is_enrolled'] ?? ! empty($student['courses']));
        $payments = $isEnrolled ? Eyal::paymentsFor($student['student_no']) : collect();
        $totalFee = $isEnrolled ? (int) ($student['fee'] ?? 0) : 0;
        $totalPaid = $isEnrolled ? (int) $payments->sum('amount') : 0;

        return response()->json([
            'status' => 'success',
            'is_enrolled' => $isEnrolled,
            'is_free' => (bool) ($student['is_free'] ?? false),
            'has_discount' => (bool) ($student['has_discount'] ?? false),
            'total_fee' => $totalFee,
            'total_paid' => $totalPaid,
            'remaining' => $isEnrolled ? max(0, $totalFee - $totalPaid) : 0,
            'payments' => $payments->values()->all(),
        ]);
    }

    /**
     * الجدول الأسبوعي للطالب
     * GET /api/v1/student/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $student = $this->resolveStudentArray($request);
        $allSlots = Eyal::schedule();

        if ($student && ! empty($student['stage_name'])) {
            $filtered = $allSlots->filter(fn (array $slot) => empty($slot['stage']) || $slot['stage'] === $student['stage_name']);
            if ($filtered->isNotEmpty()) {
                $allSlots = $filtered->values();
            }
        }

        return response()->json([
            'status' => 'success',
            'days' => config('eyal.days'),
            'schedule' => $allSlots->values()->all(),
            'schedule_by_day' => $allSlots->groupBy('day'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStudentArray(Request $request): ?array
    {
        return Eyal::studentForUser($request->user()->toEyalArray(), $request->query('student_id'));
    }

    private function resolveStudentModel(User $user): ?Student
    {
        return $user->student
            ?: Student::where('user_id', $user->id)->orWhere('student_no', $user->username)->first()
            ?: ($user->isParent() ? $user->children()->first() : null);
    }

    private function noStudentResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'code' => 'no_student',
            'message' => 'لا يوجد ملف طالب مرتبط بهذا الحساب حالياً.',
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

    private function notifyTeacher(HomeworkTask $task, Student $student, User $uploader): void
    {
        $task->loadMissing(['course', 'subject']);
        $recipients = $task->teacher_id ? collect([$task->teacher_id]) : User::where('role', 'admin')->pluck('id');
        if ($recipients->isEmpty()) {
            return;
        }

        $subjectLabel = $task->subject ? ' (مادة '.$task->subject->name.')' : '';
        Notifier::send(
            $recipients,
            'تحضير / واجب جديد من الطالب '.$student->name,
            'قام الطالب '.$student->name.' برفع واجب «'.$task->title.'»'.$subjectLabel.'.',
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
                    'api-st-hw-'.$task->id.'-'.$teacher->id
                );
            }
        }
    }
}
