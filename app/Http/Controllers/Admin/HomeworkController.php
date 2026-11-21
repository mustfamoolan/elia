<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\HomeworkTask;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\Eyal;
use App\Support\Notifier;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    public function index()
    {
        return view('admin.homework', [
            'homework' => Eyal::homeworkAll(),
            'courses' => Eyal::coursesAll(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $course = Course::findOrFail($validated['course']);

        $task = HomeworkTask::create([
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due'] ?? null,
            'source' => 'admin',
            'is_class_task' => true,
            'status' => 'open',
            'created_by' => $request->user()->id,
            'color' => 'blue',
        ]);

        Notifier::sendToCourse(
            $course,
            'واجب جديد: '.$task->title,
            'أضافت الإدارة واجباً جديداً «'.$task->title.'» في دورة '.$course->name
                .($task->due_date ? ' — موعد التسليم '.$task->due_date->format('Y-m-d') : '').'.',
            ['created_by' => $request->user()->id],
        );

        if ($course->teacher_id) {
            Notifier::send(
                [$course->teacher_id],
                'واجب جديد مسند لدورتك: '.$task->title,
                'تم إسناد واجب جديد «'.$task->title.'» في دورة '.$course->name.'.',
                [
                    'audience_type' => 'user',
                    'course_id' => $course->id,
                    'created_by' => $request->user()->id,
                    'push_url' => '/teacher/homework/'.$task->id,
                ]
            );

            $teacher = User::with('staff')->find($course->teacher_id);
            $teacherPhone = $teacher?->phone ?: $teacher?->staff?->phone;
            if ($teacherPhone) {
                $dueTxt = $task->due_date ? "\n• موعد التسليم: {$task->due_date->format('Y-m-d')}" : '';
                WhatsAppService::sendText(
                    $teacherPhone,
                    "مرحباً أ. {$teacher->name}،\nتم إسناد *واجب جديد* لدورتك:\n• العنوان: {$task->title}\n• الدورة: {$course->name}{$dueTxt}",
                    'hw-admin-teacher-'.$task->id.'-'.$teacher->id
                );
            }
        }

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

        return back()->with('success', 'تمت إضافة الواجب.');
    }

    public function update(Request $request, HomeworkTask $homework)
    {
        $validated = $this->validated($request);
        $course = Course::findOrFail($validated['course']);

        $homework->update([
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due'] ?? null,
        ]);

        return back()->with('success', 'تم تحديث الواجب.');
    }

    public function destroy(HomeworkTask $homework)
    {
        $homework->delete();

        return back()->with('success', 'تم حذف الواجب.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'course' => ['required', 'exists:courses,id'],
            'due' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => 'يرجى إدخال عنوان الواجب.',
            'course.required' => 'يرجى اختيار المادة.',
        ]);
    }
}
