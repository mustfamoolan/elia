<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\HomeworkFile;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\Eyal;
use App\Support\HomeworkStorage;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HomeworkPortalController extends Controller
{
    public function submit(Request $request, HomeworkTask $task)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, ['student', 'parent'], true), 403);

        $validated = $request->validate([
            'student_id' => ['nullable', 'exists:students,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => HomeworkStorage::fileRules(true),
        ], [
            'file.required' => 'يرجى اختيار ملف الواجب.',
            'file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميغابايت.',
            'file.mimes' => 'يُسمح فقط بملفات PDF والصور وWord.',
        ]);

        $student = $this->resolveStudent($user, $validated['student_id'] ?? null);

        abort_unless($student && $this->taskAvailableTo($task, $student), 403);

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

            return $submission;
        });

        $this->notifyTeacher($task, $student, $user);

        if ($user->role === 'parent') {
            return redirect(url('/parent?child_id='.$student->id.'&tab=homework'))
                ->with('success', 'تم رفع الواجب وإرساله إلى مدرس المادة للمراجعة.');
        }

        return back()->with('success', 'تم رفع الواجب وإرساله إلى مدرس المادة للمراجعة.');
    }

    public function parentUpload(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, ['parent', 'student'], true), 403);

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
            'file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميغابايت.',
            'file.mimes' => 'يُسمح فقط بملفات PDF والصور وWord.',
        ]);

        $student = $this->resolveStudent($user, $validated['student_id'] ?? null);

        abort_unless($student, 403);

        // يجب أن يكون الطالب مشتركاً فعلياً بهذا الكورس
        $course = $student->courses()
            ->with('subjects')
            ->where('courses.id', $validated['course_id'])
            ->first();

        abort_unless($course, 403, 'الطالب غير مشترك في هذا الكورس.');

        Eyal::ensureCurriculumTables();

        // تحديد المادة والمدرس المختص بهذه المادة داخل الكورس تلقائياً
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
                'source' => $user->role === 'parent' ? 'parent' : 'student',
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

        $this->notifyTeacher($task, $student, $user, 'واجب جديد مرفوع للمراجعة');

        if ($user->role === 'parent') {
            return redirect(url('/parent?child_id='.$student->id.'&tab=homework'))
                ->with('success', 'تم رفع الواجب وتوجيهه تلقائياً إلى المدرس المختص بالمادة.');
        }

        return back()->with('success', 'تم رفع الواجب وتوجيهه تلقائياً إلى المدرس المختص بالمادة.');
    }

    public function download(HomeworkFile $file)
    {
        $user = request()->user();

        abort_unless($user && $this->canAccessFile($user, $file), 403);

        $disk = Storage::disk($file->disk)->exists($file->path)
            ? $file->disk
            : (Storage::disk('local')->exists($file->path) ? 'local' : (Storage::disk('public')->exists($file->path) ? 'public' : null));

        abort_unless($disk !== null, 404);

        $mime = strtolower(trim((string) $file->mime));
        if ($mime === '' || $mime === 'application/octet-stream') {
            $ext = strtolower(pathinfo((string) ($file->original_name ?: $file->path), PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg', 'jfif' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'svg' => 'image/svg+xml',
                'heic' => 'image/heic',
                'heif' => 'image/heif',
                'pdf' => 'application/pdf',
                default => $file->mime ?: 'application/octet-stream',
            };
        }

        if (request()->boolean('inline')) {
            $headers = [
                'Cache-Control' => 'private, max-age=86400',
            ];
            if ($mime) {
                $headers['Content-Type'] = $mime;
            }

            return Storage::disk($disk)->response($file->path, $file->original_name, $headers);
        }

        return Storage::disk($disk)->download($file->path, $file->original_name);
    }

    /**
     * استخراج المدرس المختص بالمادة داخل الكورس بدقة.
     */
    private function resolveTeacherForCourseSubject(Course $course, ?int $subjectId): ?int
    {
        if ($subjectId) {
            // 1. من جدول ربط مواد الكورس (course_subject)
            $courseSubjectTeacher = DB::table('course_subject')
                ->where('course_id', $course->id)
                ->where('subject_id', $subjectId)
                ->whereNotNull('teacher_id')
                ->value('teacher_id');

            if ($courseSubjectTeacher) {
                return (int) $courseSubjectTeacher;
            }

            // 2. من جدول ربط المدرسين بمواد المرحلة (subject_teacher)
            $subjectTeacher = DB::table('subject_teacher')
                ->where('subject_id', $subjectId)
                ->value('teacher_id');

            if ($subjectTeacher) {
                return (int) $subjectTeacher;
            }
        }

        // 3. إذا لم تحدد المادة وكان للكورس مادة واحدة بمدرس معين
        $firstCourseTeacher = DB::table('course_subject')
            ->where('course_id', $course->id)
            ->whereNotNull('teacher_id')
            ->value('teacher_id');

        if ($firstCourseTeacher) {
            return (int) $firstCourseTeacher;
        }

        return $course->teacher_id ? (int) $course->teacher_id : null;
    }

    private function resolveStudent(User $user, mixed $studentId = null): ?Student
    {
        if ($user->role === 'student') {
            return $user->student;
        }

        if ($user->role !== 'parent') {
            return null;
        }

        $children = $user->children();

        if ($studentId) {
            return $children->where('students.id', (int) $studentId)->first();
        }

        return $children->first();
    }

    private function taskAvailableTo(HomeworkTask $task, Student $student): bool
    {
        if ((int) $task->student_id === $student->id) {
            return true;
        }

        if (! $task->is_class_task) {
            return false;
        }

        return $task->course?->students()->where('students.id', $student->id)->exists() ?? false;
    }

    private function canAccessFile(User $user, HomeworkFile $file): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ((int) $file->uploaded_by === (int) $user->id) {
            return true;
        }

        $task = $file->task ?? $file->submission?->task;

        if (! $task) {
            return false;
        }

        if ((int) $task->created_by === (int) $user->id) {
            return true;
        }

        if ($user->role === 'staff') {
            $task->loadMissing('course');

            if ((int) $task->teacher_id === $user->id) {
                return true;
            }

            if ($task->subject_id) {
                $inCourseSubject = DB::table('course_subject')
                    ->where('course_id', $task->course_id)
                    ->where('subject_id', $task->subject_id)
                    ->where('teacher_id', $user->id)
                    ->exists();
                if ($inCourseSubject) {
                    return true;
                }

                $inSubjectTeacher = DB::table('subject_teacher')
                    ->where('subject_id', $task->subject_id)
                    ->where('teacher_id', $user->id)
                    ->exists();
                if ($inSubjectTeacher) {
                    return true;
                }
            }

            return (int) $task->course?->teacher_id === $user->id;
        }

        if ($user->role === 'student') {
            $student = $user->student ?: Student::where('student_no', $user->username)->first();

            return $student && $this->taskAvailableTo($task, $student);
        }

        if ($user->role === 'parent') {
            $childIds = $user->children()->pluck('students.id');

            foreach ($childIds as $childId) {
                $student = Student::find($childId);

                if ($student && $this->taskAvailableTo($task, $student)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function notifyTeacher(HomeworkTask $task, Student $student, User $uploader, ?string $title = null): void
    {
        $task->loadMissing(['course', 'subject']);

        $recipients = collect();

        if ($task->teacher_id) {
            $recipients->push($task->teacher_id);
        } else {
            $recipients = User::query()->where('role', 'admin')->pluck('id');
        }

        if ($recipients->isEmpty()) {
            return;
        }

        $subjectLabel = $task->subject ? ' (مادة '.$task->subject->name.')' : '';
        $notifTitle = $title ?? 'تحضير / واجب جديد من طالب';
        $notifBody = 'قام '.($uploader->role === 'parent' ? 'ولي الأمر' : 'الطالب').' '.$uploader->name
            .' برفع واجب «'.$task->title.'»'.$subjectLabel.' للطالب '.$student->name
            .' في كورس '.($task->course?->name ?? '—').'.';

        Notifier::send(
            $recipients,
            $notifTitle,
            $notifBody,
            [
                'audience_type' => 'user',
                'course_id' => $task->course_id,
                'created_by' => $uploader->id,
                'push_url' => '/teacher/homework/'.$task->id,
            ],
        );

        if ($task->teacher_id) {
            $teacher = User::with('staff')->find($task->teacher_id);
            $teacherPhone = $teacher?->phone ?: $teacher?->staff?->phone;
            if ($teacherPhone) {
                WhatsAppService::sendText(
                    $teacherPhone,
                    "مرحباً أ. {$teacher->name}،\nلديك *تحضير / واجب جديد* من الطالب *{$student->name}*{$subjectLabel}:\n• العنوان: {$task->title}\n• الدورة: ".($task->course?->name ?? '—'),
                    'hw-portal-teacher-'.$task->id.'-'.$teacher->id
                );
            }
        }
    }
}
