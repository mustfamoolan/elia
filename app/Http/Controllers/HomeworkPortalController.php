<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\HomeworkFile;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\Student;
use App\Models\User;
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

        $submission = DB::transaction(function () use ($task, $student, $user, $request, $validated) {
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

        return back()->with('success', 'تم رفع الواجب وإرساله إلى المدرس للمراجعة.');
    }

    public function parentUpload(Request $request)
    {
        $user = $request->user();

        abort_unless($user->role === 'parent', 403);

        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => HomeworkStorage::fileRules(true),
        ], [
            'student_id.required' => 'يرجى اختيار الابن.',
            'course_id.required' => 'يرجى اختيار الدورة.',
            'title.required' => 'يرجى إدخال عنوان الواجب.',
            'file.required' => 'يرجى اختيار ملف الواجب.',
            'file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميغابايت.',
            'file.mimes' => 'يُسمح فقط بملفات PDF والصور وWord.',
        ]);

        $student = $this->resolveStudent($user, $validated['student_id']);

        abort_unless($student, 403);

        $course = $student->courses()
            ->where('courses.id', $validated['course_id'])
            ->first();

        if (! $course && $student->stage_id) {
            $stageCourse = Course::query()
                ->where('id', $validated['course_id'])
                ->where('stage_id', $student->stage_id)
                ->first();

            if ($stageCourse) {
                $student->courses()->syncWithoutDetaching([
                    $stageCourse->id => [
                        'fee' => 0,
                        'enrolled_at' => now()->toDateString(),
                        'status' => 'active',
                    ],
                ]);
                $course = $stageCourse;
            }
        }

        abort_unless($course, 403, 'الدورة غير مرتبطة بهذا الطالب.');

        $task = DB::transaction(function () use ($validated, $student, $course, $user, $request) {
            $task = HomeworkTask::create([
                'course_id' => $course->id,
                'teacher_id' => $course->teacher_id,
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

        $this->notifyTeacher($task, $student, $user, 'واجب جديد من ولي الأمر');

        return back()->with('success', 'تم رفع الواجب وإرساله إلى مدرس الدورة للمراجعة.');
    }

    public function download(HomeworkFile $file)
    {
        $user = request()->user();

        abort_unless($user && $this->canAccessFile($user, $file), 403);

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        if (request()->boolean('inline')) {
            $headers = [
                'Cache-Control' => 'private, max-age=86400',
            ];
            if ($file->mime) {
                $headers['Content-Type'] = $file->mime;
            }

            return Storage::disk($file->disk)->response($file->path, $file->original_name, $headers);
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
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

        $task = $file->task ?? $file->submission?->task;

        if (! $task) {
            return false;
        }

        if ($user->role === 'staff') {
            $task->loadMissing('course');

            if ((int) $task->teacher_id === $user->id || (int) $task->course?->teacher_id === $user->id) {
                return true;
            }

            if ($task->course?->stage_id && $user->stages()->where('stages.id', $task->course->stage_id)->exists()) {
                return true;
            }

            return false;
        }

        if ($user->role === 'student') {
            $student = $user->student;

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
        $task->loadMissing('course');

        $recipients = collect();

        if ($task->teacher_id) {
            $recipients->push($task->teacher_id);
        } else {
            $recipients = User::query()->where('role', 'admin')->pluck('id');
        }

        if ($recipients->isEmpty()) {
            return;
        }

        Notifier::send(
            $recipients,
            $title ?? 'تسليم واجب جديد',
            'قام '.($uploader->role === 'parent' ? 'ولي الأمر' : 'الطالب').' '.$uploader->name
                .' برفع واجب «'.$task->title.'» للطالب '.$student->name
                .' في دورة '.($task->course?->name ?? '—').'.',
            [
                'audience_type' => 'user',
                'course_id' => $task->course_id,
                'created_by' => $uploader->id,
            ],
        );
    }
}
