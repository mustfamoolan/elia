<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Grade;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\ScheduleSlot;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use App\Support\Eyal;
use App\Support\HomeworkStorage;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $courses = $this->teacherCourses($request);
        $courseIds = $courses->pluck('id');

        $studentsCount = Student::query()
            ->whereHas('courses', fn ($query) => $query->whereIn('courses.id', $courseIds))
            ->count();

        $pendingHomework = HomeworkTask::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', ['open', 'submitted', 'under_review'])
            ->count();

        $todaySlots = ScheduleSlot::query()
            ->whereIn('course_id', $courseIds)
            ->where('day_of_week', now()->dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $homework = HomeworkTask::query()
            ->whereIn('course_id', $courseIds)
            ->with(['course', 'student'])
            ->latest()
            ->take(6)
            ->get();

        $recentSubmissions = HomeworkSubmission::query()
            ->whereHas('task', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->with(['task.course', 'student'])
            ->latest()
            ->take(5)
            ->get();

        return view('teacher.dashboard', [
            'user' => $user,
            'courses' => $courses,
            'studentsCount' => $studentsCount,
            'pendingHomework' => $pendingHomework,
            'todaySlots' => $todaySlots,
            'homework' => $homework,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }

    public function courses(Request $request)
    {
        $this->ensureTeacherAssignments($request->user());

        $courses = Course::query()
            ->where('teacher_id', $request->user()->id)
            ->with('stage')
            ->withCount(['students', 'homeworkTasks'])
            ->orderBy('name')
            ->get();

        return view('teacher.courses', [
            'courses' => $courses,
        ]);
    }

    public function course(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);

        $course->load([
            'stage',
            'students' => fn ($query) => $query->orderBy('name'),
            'scheduleSlots' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time'),
            'homeworkTasks' => fn ($query) => $query->latest()->take(10),
        ]);

        return view('teacher.course', [
            'course' => $course,
        ]);
    }

    public function students(Request $request)
    {
        $courseIds = $this->courseIds($request);

        $students = Student::query()
            ->whereHas('courses', fn ($query) => $query->whereIn('courses.id', $courseIds))
            ->with(['stage', 'courses' => fn ($query) => $query->whereIn('courses.id', $courseIds)])
            ->withCount([
                'attendances as attendance_total' => fn ($query) => $query->whereIn('course_id', $courseIds),
                'attendances as attendance_present' => fn ($query) => $query->whereIn('course_id', $courseIds)->whereIn('status', ['present', 'late']),
                'grades as grades_total' => fn ($query) => $query->whereHas('assessment', fn ($query) => $query->whereIn('course_id', $courseIds)),
            ])
            ->orderBy('name')
            ->get();

        return view('teacher.students', [
            'students' => $students,
        ]);
    }

    public function attendance(Request $request)
    {
        $courses = $this->teacherCourses($request);
        $selected = $courses->firstWhere('id', (int) $request->query('course')) ?? $courses->first();

        $date = $request->query('date', now()->toDateString());

        $students = collect();
        $records = collect();

        if ($selected) {
            $students = $selected->students()->with('stage')->orderBy('name')->get();
            $records = AttendanceRecord::query()
                ->where('course_id', $selected->id)
                ->where('date', $date)
                ->get()
                ->keyBy('student_id');
        }

        return view('teacher.attendance', [
            'courses' => $courses,
            'selected' => $selected,
            'date' => $date,
            'students' => $students,
            'records' => $records,
        ]);
    }

    public function attendanceStore(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'array'],
            'status.*' => ['nullable', 'in:present,late,absent'],
        ], [
            'course_id.required' => 'يرجى اختيار الدورة.',
            'date.required' => 'يرجى تحديد التاريخ.',
            'status.required' => 'يرجى تحديد حالة طالب واحد على الأقل.',
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        $enrolled = $course->students()->pluck('students.id')->all();
        $saved = 0;

        foreach ($validated['status'] as $studentId => $status) {
            if ($status === null || $status === '' || ! in_array((int) $studentId, $enrolled, true)) {
                continue;
            }

            AttendanceRecord::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'student_id' => (int) $studentId,
                    'date' => $validated['date'],
                ],
                [
                    'status' => $status,
                    'recorded_by' => $request->user()->id,
                ],
            );

            $saved++;
        }

        return redirect()
            ->route('teacher.attendance', ['course' => $course->id, 'date' => $validated['date']])
            ->with('success', $saved > 0 ? 'تم حفظ حضور '.$saved.' طالب.' : 'لم يتم حفظ أي سجل.');
    }

    public function grades(Request $request)
    {
        $courses = $this->teacherCourses($request);
        $selected = $courses->firstWhere('id', (int) $request->query('course')) ?? $courses->first();

        $students = collect();
        $assessments = collect();

        if ($selected) {
            $students = $selected->students()->orderBy('name')->get();
            $assessments = Assessment::query()
                ->where('course_id', $selected->id)
                ->with(['grades.student'])
                ->orderByDesc('assessed_at')
                ->orderByDesc('id')
                ->get();
        }

        return view('teacher.grades', [
            'courses' => $courses,
            'selected' => $selected,
            'students' => $students,
            'assessments' => $assessments,
        ]);
    }

    public function gradesStore(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'student_id' => ['required', 'exists:students,id'],
            'exam' => ['required', 'string', 'max:255'],
            'score' => ['required', 'numeric', 'min:0'],
            'max' => ['required', 'numeric', 'min:1'],
            'date' => ['nullable', 'date'],
        ], [
            'course_id.required' => 'يرجى اختيار الدورة.',
            'student_id.required' => 'يرجى اختيار الطالب.',
            'exam.required' => 'يرجى إدخال اسم الامتحان.',
            'score.required' => 'يرجى إدخال الدرجة.',
            'max.required' => 'يرجى إدخال الدرجة الكاملة.',
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        abort_unless($course->students()->where('students.id', $validated['student_id'])->exists(), 403);

        DB::transaction(function () use ($validated, $request, $course) {
            $assessment = Assessment::firstOrCreate(
                [
                    'course_id' => $course->id,
                    'title' => $validated['exam'],
                    'assessed_at' => $validated['date'] ?? now()->toDateString(),
                ],
                [
                    'type' => 'exam',
                    'max_score' => $validated['max'],
                    'created_by' => $request->user()->id,
                ],
            );

            Grade::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $validated['student_id']],
                ['score' => $validated['score'], 'recorded_by' => $request->user()->id],
            );
        });

        return redirect()
            ->route('teacher.grades', ['course' => $course->id])
            ->with('success', 'تم حفظ الدرجة.');
    }

    public function gradesDestroy(Request $request, Grade $grade)
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

        return back()->with('success', 'تم حذف الدرجة.');
    }

    public function homework(Request $request)
    {
        $courses = $this->teacherCourses($request);
        $courseIds = $courses->pluck('id');

        $status = $request->query('status');
        $courseFilter = $request->query('course');

        $tasks = HomeworkTask::query()
            ->whereIn('course_id', $courseIds)
            ->with(['course.stage', 'student', 'files'])
            ->withCount([
                'submissions',
                'submissions as pending_submissions' => fn ($query) => $query->whereIn('status', ['submitted', 'under_review']),
            ])
            ->when($courseFilter, fn ($query) => $query->where('course_id', $courseFilter))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->get();

        return view('teacher.homework', [
            'courses' => $courses,
            'tasks' => $tasks,
            'status' => $status,
            'courseFilter' => $courseFilter,
        ]);
    }

    public function homeworkStore(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:20'],
            'files' => HomeworkStorage::multipleRules(false),
            'files.*' => HomeworkStorage::fileRules(false),
        ], [
            'course_id.required' => 'يرجى اختيار الدورة.',
            'title.required' => 'يرجى إدخال عنوان الواجب.',
            'files.*.max' => 'حجم كل ملف يجب أن لا يتجاوز 10 ميغابايت.',
            'files.*.mimes' => 'يُسمح فقط بملفات PDF والصور وWord.',
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        $task = DB::transaction(function () use ($validated, $request, $course) {
            $task = HomeworkTask::create([
                'course_id' => $course->id,
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
            'أضاف المدرس '.$request->user()->name.' واجباً جديداً في دورة '.$course->name
                .($task->due_date ? ' — موعد التسليم '.$task->due_date->format('Y-m-d') : '').'.',
            ['created_by' => $request->user()->id],
        );

        return redirect()
            ->route('teacher.homework.show', $task)
            ->with('success', 'تم إنشاء الواجب وإشعار الطلاب وأولياء الأمور.');
    }

    public function homeworkShow(Request $request, HomeworkTask $task)
    {
        $this->authorizeTask($request, $task);

        $task->load([
            'course.stage',
            'course.students' => fn ($query) => $query->orderBy('name'),
            'student',
            'files',
            'submissions.student',
            'submissions.files',
            'submissions.submitter',
        ]);

        return view('teacher.homework-show', [
            'task' => $task,
        ]);
    }

    public function homeworkUpdate(Request $request, HomeworkTask $task)
    {
        $this->authorizeTask($request, $task);

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['open', 'submitted', 'under_review', 'reviewed', 'needs_revision', 'closed'])],
        ], [
            'course_id.required' => 'يرجى اختيار الدورة.',
            'title.required' => 'يرجى إدخال عنوان الواجب.',
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        $task->update([
            'course_id' => $course->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'color' => $validated['color'] ?? $task->color,
            'status' => $validated['status'] ?? $task->status,
        ]);

        return back()->with('success', 'تم تحديث الواجب بنجاح.');
    }

    public function homeworkDestroy(Request $request, HomeworkTask $task)
    {
        $this->authorizeTask($request, $task);
        $task->delete();

        return redirect()->route('teacher.homework')->with('success', 'تم حذف الواجب بنجاح.');
    }

    public function review(Request $request, HomeworkTask $task, HomeworkSubmission $submission)
    {
        $this->authorizeTask($request, $task);

        abort_unless((int) $submission->homework_task_id === (int) $task->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'reviewed', 'needs_revision'])],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.required' => 'يرجى اختيار حالة المراجعة.',
            'feedback.max' => 'الملاحظات طويلة جداً.',
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

        Notifier::sendToStudentAndParents(
            $submission->student,
            $label.': '.$task->title,
            'راجع المدرس '.$request->user()->name.' واجب «'.$task->title.'» في دورة '.($task->course?->name ?? '—').'.'
                .(! empty($validated['feedback']) ? ' ملاحظات: '.$validated['feedback'] : ''),
            ['created_by' => $request->user()->id, 'course_id' => $task->course_id],
        );

        return back()->with('success', 'تم حفظ المراجعة وإشعار الطالب وأولياء الأمور.');
    }

    public function schedule(Request $request)
    {
        $courses = $this->teacherCourses($request);

        $slots = ScheduleSlot::query()
            ->whereIn('course_id', $courses->pluck('id'))
            ->where('is_active', true)
            ->with('course.stage')
            ->orderBy('start_time')
            ->get();

        $days = [
            6 => 'السبت',
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
        ];

        $byDay = collect($days)->mapWithKeys(
            fn (string $name, int $day) => [$day => $slots->where('day_of_week', $day)->values()],
        );

        return view('teacher.schedule', [
            'courses' => $courses,
            'days' => $days,
            'byDay' => $byDay,
        ]);
    }

    public function profile(Request $request)
    {
        return view('teacher.profile', [
            'user' => $request->user()->load('staff'),
        ]);
    }

    public function profileUpdate(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'يرجى إدخال الاسم.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
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
                    'subject' => $validated['subject'] ?? $user->staff->subject,
                    'bio' => $validated['bio'] ?? $user->staff->bio,
                    'phone' => $validated['phone'] ?? $user->staff->phone,
                ]);
            }
        });

        return back()->with('success', 'تم تحديث بيانات الحساب.');
    }

    /**
     * @var \Illuminate\Support\Collection<int, Course>|null
     */
    private $cachedCourses = null;

    /**
     * @return \Illuminate\Support\Collection<int, Course>
     */
    private function teacherCourses(Request $request)
    {
        if ($this->cachedCourses !== null) {
            return $this->cachedCourses;
        }

        $this->ensureTeacherAssignments($request->user());

        $this->cachedCourses = Course::query()
            ->where('teacher_id', $request->user()->id)
            ->with('stage:id,name')
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return $this->cachedCourses;
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function courseIds(Request $request)
    {
        return $this->teacherCourses($request)->pluck('id');
    }

    /**
     * يتأكد من ربط دورات المدرس وطلاب المراحل غير المربوطين تلقائياً (مع الكاش الذكي لمنع التكرار في كل نقرة)
     */
    private function ensureTeacherAssignments(User $user): void
    {
        Eyal::remember('teacher_synced.'.$user->id, 1800, function () use ($user): bool {
            $user->loadMissing('staff');

            $courses = Course::query()->where('teacher_id', $user->id)->get();

            // إذا لم يكن للمدرس دورات مربوطة ولكن لديه مادة/مرحلة نصية في جدول الكادر، نربط الدورات المطابقة التي بلا مدرس
            if ($courses->isEmpty() && $user->staff) {
                $subjects = collect(preg_split('/[،,+]+/u', (string) $user->staff->subject))
                    ->map(fn ($s) => trim(preg_replace('/^(دورة|مادة)\s+/u', '', (string) $s)))
                    ->filter()
                    ->values();

                $stageNames = collect(preg_split('/[،,]+/u', (string) $user->staff->stage_label))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->values();

                $stageIds = $stageNames->isNotEmpty()
                    ? Stage::query()->whereIn('name', $stageNames->all())->pluck('id')
                    : collect();

                if ($subjects->isNotEmpty() || $stageIds->isNotEmpty()) {
                    $matching = Course::query()
                        ->whereNull('teacher_id')
                        ->when($subjects->isNotEmpty(), function ($q) use ($subjects) {
                            $q->where(function ($subQ) use ($subjects) {
                                foreach ($subjects as $sub) {
                                    $bare = preg_replace('/^ال/u', '', $sub);
                                    $subQ->orWhere('subject', 'like', '%'.$sub.'%')
                                        ->orWhere('name', 'like', '%'.$sub.'%')
                                        ->orWhere('subject', 'like', '%'.$bare.'%')
                                        ->orWhere('name', 'like', '%'.$bare.'%');
                                }
                            });
                        })
                        ->when($stageIds->isNotEmpty(), fn ($q) => $q->whereIn('stage_id', $stageIds->all()))
                        ->get();

                    foreach ($matching as $course) {
                        $course->update(['teacher_id' => $user->id]);
                    }

                    $courses = Course::query()->where('teacher_id', $user->id)->get();
                }
            }

            // ربط جميع طلاب المرحلة الفعالين بدورات مرحلتهم لدى هذا المدرس لضمان ظهورهم الكامل
            $today = now()->toDateString();
            foreach ($courses as $course) {
                if (! $course->stage_id) {
                    continue;
                }

                $unassignedStudentIds = Student::query()
                    ->where('stage_id', $course->stage_id)
                    ->where('status', 'active')
                    ->whereDoesntHave('courses', fn ($q) => $q->where('courses.id', $course->id))
                    ->pluck('id');

                if ($unassignedStudentIds->isNotEmpty()) {
                    $syncPayload = [];
                    foreach ($unassignedStudentIds as $studentId) {
                        $syncPayload[(int) $studentId] = [
                            'fee' => 0,
                            'enrolled_at' => $today,
                            'status' => 'active',
                        ];
                    }
                    $course->students()->syncWithoutDetaching($syncPayload);
                }
            }

            return true;
        });
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless((int) $course->teacher_id === (int) $request->user()->id, 403);
    }

    private function authorizeTask(Request $request, HomeworkTask $task): void
    {
        $task->loadMissing('course');

        abort_unless(
            (int) $task->course?->teacher_id === (int) $request->user()->id,
            403,
        );
    }
}
