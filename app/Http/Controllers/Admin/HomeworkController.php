<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\HomeworkTask;
use App\Services\WhatsAppService;
use App\Support\Eyal;
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
