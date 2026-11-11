<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index()
    {
        return view('admin.courses', [
            'courses' => Eyal::coursesAll(),
            'stages' => Eyal::stages(),
            'teachers' => Eyal::staffAll(),
        ]);
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $course = Course::create($this->validated($request));
            $this->syncStageStudentsAndTeacher($course);
        });

        return back()->with('success', 'تمت إضافة الدورة وربطها بالمرحلة والمدرس.');
    }

    public function update(Request $request, Course $course)
    {
        $oldTeacherId = $course->teacher_id;

        DB::transaction(function () use ($request, $course, $oldTeacherId) {
            $course->update($this->validated($request));
            $this->syncStageStudentsAndTeacher($course, $oldTeacherId);
        });

        return back()->with('success', 'تم تحديث الدورة.');
    }

    public function destroy(Course $course)
    {
        $teacherId = $course->teacher_id;
        $course->delete();

        if ($teacherId) {
            $this->refreshTeacherSummary((int) $teacherId);
        }

        return back()->with('success', 'تم حذف الدورة.');
    }

    private function syncStageStudentsAndTeacher(Course $course, ?int $oldTeacherId = null): void
    {
        if ($course->stage_id && $course->is_active) {
            $unassignedStudents = Student::query()
                ->where('stage_id', $course->stage_id)
                ->where('status', 'active')
                ->whereDoesntHave('courses')
                ->pluck('id');

            foreach ($unassignedStudents as $studentId) {
                $course->students()->syncWithoutDetaching([
                    (int) $studentId => [
                        'fee' => 0,
                        'enrolled_at' => now()->toDateString(),
                        'status' => 'active',
                    ],
                ]);
            }
        }

        if ($course->teacher_id) {
            $this->refreshTeacherSummary((int) $course->teacher_id);
        }

        if ($oldTeacherId && $oldTeacherId !== (int) $course->teacher_id) {
            $this->refreshTeacherSummary((int) $oldTeacherId);
        }
    }

    private function refreshTeacherSummary(int $teacherId): void
    {
        $user = User::with('staff')->find($teacherId);
        if (! $user || ! $user->staff) {
            return;
        }

        $courses = Course::query()
            ->where('teacher_id', $teacherId)
            ->with('stage')
            ->get();

        $subjectsLabel = $courses->map(fn (Course $c) => $c->subject ?: $c->name)->filter()->unique()->implode('، ');
        $stagesLabel = $courses->map(fn (Course $c) => $c->stage?->name)->filter()->unique()->implode('، ');

        $user->staff->update([
            'subject' => $subjectsLabel ?: $user->staff->subject,
            'stage_label' => $stagesLabel ?: $user->staff->stage_label,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stage' => ['nullable', 'exists:stages,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'يرجى إدخال اسم الدورة.',
        ]);

        return [
            'name' => $validated['name'],
            'stage_id' => $validated['stage'] ?? null,
            'teacher_id' => $validated['teacher_id'] ?? null,
            'subject' => ! empty($validated['subject']) ? $validated['subject'] : $validated['name'],
            'price' => $validated['price'] ?? 0,
            'seats' => $validated['seats'] ?? 0,
            'schedule_text' => $validated['schedule'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['active'] ?? false),
            'color' => $request->input('color', 'blue'),
        ];
    }
}
