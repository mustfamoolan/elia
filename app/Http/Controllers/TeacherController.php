<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Grade;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\ScheduleSlot;
use App\Models\Student;
use App\Models\User;
use App\Support\Eyal;
use App\Support\HomeworkStorage;
use App\Support\Notifier;
use App\Services\FcmService;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $pendingHomework = $this->teacherHomeworkQuery($request)
            ->whereIn('status', ['open', 'submitted', 'under_review'])
            ->count();

        $todaySlots = ScheduleSlot::query()
            ->whereIn('course_id', $courseIds)
            ->where('day_of_week', now()->dayOfWeek)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $homework = $this->teacherHomeworkQuery($request)
            ->with(['course', 'subject', 'student'])
            ->latest()
            ->take(6)
            ->get();

        $taskIds = $this->teacherHomeworkQuery($request)->pluck('id');

        $recentSubmissions = HomeworkSubmission::query()
            ->whereIn('homework_task_id', $taskIds)
            ->with(['task.course', 'task.subject', 'student'])
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
        $courses = $this->teacherCourses($request);

        return view('teacher.courses', [
            'courses' => $courses,
        ]);
    }

    public function course(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $userId = (int) $request->user()->id;
        $mySubjects = $this->subjectsForTeacherInCourse($userId, $course);

        $course->load([
            'stage',
            'students' => fn ($query) => $query->orderBy('name'),
            'scheduleSlots' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time'),
            'homeworkTasks' => fn ($query) => $query
                ->where(function ($q) use ($userId, $mySubjects) {
                    $q->where('teacher_id', $userId);
                    if ($mySubjects->isNotEmpty()) {
                        $q->orWhereIn('subject_id', $mySubjects->pluck('id')->all());
                    }
                })
                ->with('subject')
                ->latest()
                ->take(10),
        ]);

        return view('teacher.course', [
            'course' => $course,
            'mySubjects' => $mySubjects,
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
                'attendances as attendance_present' => fn ($query) => $query->whereIn('course_id', $courseIds)->whereIn('status', ['present', 'late', 'excused']),
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
            'status.*' => ['nullable', 'in:present,late,absent,excused'],
        ], [
            'course_id.required' => 'يرجى اختيار الدورة.',
            'date.required' => 'يرجى تحديد التاريخ.',
            'status.required' => 'يرجى تحديد حالة طالب واحد على الأقل.',
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $this->authorizeCourse($request, $course);

        $mySubjects = $this->subjectsForTeacherInCourse((int) $request->user()->id, $course);
        $defaultSubjectId = $mySubjects->first()?->id;

        $enrolled = $course->students()->pluck('students.id')->all();
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
                ],
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
                ->with(['grades.student', 'subject'])
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
            'subject_id' => ['nullable', 'exists:subjects,id'],
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

        $mySubjects = $this->subjectsForTeacherInCourse((int) $request->user()->id, $course);
        $subjectId = ! empty($validated['subject_id']) ? (int) $validated['subject_id'] : $mySubjects->first()?->id;

        $gradeResult = DB::transaction(function () use ($validated, $request, $course, $subjectId) {
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
                ],
            );

            $grade = Grade::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $validated['student_id']],
                ['score' => $validated['score'], 'recorded_by' => $request->user()->id],
            );

            return [$assessment, $grade];
        });

        if (! empty(Eyal::institute()['wa_notify_grades'])) {
            [$assessment, $grade] = $gradeResult;
            $studentModel = Student::find((int) $validated['student_id']);
            if ($studentModel) {
                WhatsAppService::sendToStudentAndParents(
                    $studentModel,
                    "إشعار درجة امتحان\n• الطالب: *{$studentModel->name}*\n• الامتحان: {$assessment->title} ({$course->name})\n• الدرجة: *{$grade->score} / {$assessment->max_score}*",
                    'grade-'.$grade->id.'-'.$grade->score
                );
            }
        }

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

        $status = $request->query('status');
        $courseFilter = $request->query('course');

        $tasks = $this->teacherHomeworkQuery($request)
            ->with(['course.stage', 'subject', 'student', 'files'])
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
            'subject_id' => ['nullable', 'exists:subjects,id'],
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

        $mySubjects = $this->subjectsForTeacherInCourse((int) $request->user()->id, $course);
        $subjectId = ! empty($validated['subject_id'])
            ? (int) $validated['subject_id']
            : $mySubjects->first()?->id;

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
            'أضاف المدرس '.$request->user()->name.' واجباً جديداً في دورة '.$course->name
                .($task->due_date ? ' — موعد التسليم '.$task->due_date->format('Y-m-d') : '').'.',
            ['created_by' => $request->user()->id],
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

        return redirect()
            ->route('teacher.homework.show', $task)
            ->with('success', 'تم إنشاء الواجب وإشعار الطلاب المشتركين وأولياء أمورهم.');
    }

    public function homeworkShow(Request $request, HomeworkTask $task)
    {
        $this->authorizeTask($request, $task);

        $task->load([
            'course.stage',
            'course.students' => fn ($query) => $query->orderBy('name'),
            'subject',
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['open', 'submitted', 'under_review', 'reviewed', 'needs_revision', 'closed'])],
        ], [
            'title.required' => 'يرجى إدخال عنوان الواجب.',
        ]);

        $task->update([
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
     * @var Collection<int, Course>|null
     */
    private ?Collection $cachedCourses = null;

    /** @var array<int, array<int, int>> */
    private array $cachedTeacherSubjectIds = [];

    /**
     * الكورسات التي يدرّس فيها هذا الأستاذ (سواء كمدرس أساسي أو مدرس لمادة أو أكثر داخل الكورس).
     *
     * @return Collection<int, Course>
     */
    private function teacherCourses(Request $request): Collection
    {
        if ($this->cachedCourses !== null) {
            return $this->cachedCourses;
        }

        Eyal::ensureCurriculumTables();
        $userId = (int) $request->user()->id;

        $courses = Course::query()
            ->where(function (Builder $query) use ($userId) {
                $query->where('teacher_id', $userId)
                    ->orWhereHas('subjects', fn (Builder $sq) => $sq->where('course_subject.teacher_id', $userId));
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
            ->map(function (Course $course) use ($userId) {
                $mySubjects = $course->subjects
                    ->filter(fn ($sub) => (int) ($sub->pivot?->teacher_id ?? 0) === $userId)
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

    /**
     * @return Collection<int, int>
     */
    private function courseIds(Request $request): Collection
    {
        return $this->teacherCourses($request)->pluck('id');
    }

    /**
     * المواد التي يدرّسها هذا الأستاذ داخل كورس محدد.
     */
    private function subjectsForTeacherInCourse(int $userId, Course $course): Collection
    {
        $course->loadMissing('subjects');

        $mine = $course->subjects
            ->filter(fn ($sub) => (int) ($sub->pivot?->teacher_id ?? 0) === $userId)
            ->values();

        if ($mine->isEmpty() && (int) $course->teacher_id === $userId) {
            return $course->subjects;
        }

        return $mine;
    }

    /**
     * استعلام الواجبات الخاصة بهذا المدرس فقط (واجبات مواده داخل الكورسات المسندة إليه).
     */
    private function teacherHomeworkQuery(Request $request): Builder
    {
        $userId = (int) $request->user()->id;
        $courseIds = $this->courseIds($request);

        return HomeworkTask::query()
            ->whereIn('course_id', $courseIds)
            ->where(fn (Builder $q) => $this->scopeHomeworkForTeacher($q, $userId));
    }

    private function scopeHomeworkForTeacher(Builder $query, int $userId): Builder
    {
        if (! array_key_exists($userId, $this->cachedTeacherSubjectIds)) {
            $this->cachedTeacherSubjectIds[$userId] = DB::table('course_subject')
                ->where('teacher_id', $userId)
                ->pluck('subject_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $mySubjectIds = $this->cachedTeacherSubjectIds[$userId];

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

        abort_unless($teachesSubjectInCourse, 403);
    }

    private function authorizeTask(Request $request, HomeworkTask $task): void
    {
        $userId = (int) $request->user()->id;

        if ((int) $task->teacher_id === $userId) {
            return;
        }

        if ($task->subject_id) {
            $teachesThisSubject = DB::table('course_subject')
                ->where('course_id', $task->course_id)
                ->where('subject_id', $task->subject_id)
                ->where('teacher_id', $userId)
                ->exists();

            abort_unless($teachesThisSubject, 403);

            return;
        }

        $task->loadMissing('course');
        $this->authorizeCourse($request, $task->course);
    }
}
