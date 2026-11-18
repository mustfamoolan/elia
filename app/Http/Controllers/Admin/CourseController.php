<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Subject;
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
            'subjects' => Eyal::subjectsAll(),
            'teachers' => Eyal::staffAll(),
        ]);
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $course = Course::create($data['attributes']);
            $this->syncCourseSubjectsAndTeachers($course, $data['subject_ids'], $data['subject_teachers']);
        });

        return back()->with('success', 'تم إنشاء الكورس وتوزيع المواد على مدرسيها المختصين تلقائياً.');
    }

    public function update(Request $request, Course $course)
    {
        DB::transaction(function () use ($request, $course) {
            $data = $this->validated($request);
            $course->update($data['attributes']);
            $this->syncCourseSubjectsAndTeachers($course, $data['subject_ids'], $data['subject_teachers']);
        });

        return back()->with('success', 'تم تحديث الكورس ومواده ومدرسيه بنجاح.');
    }

    public function destroy(Course $course)
    {
        $course->delete();
        Eyal::flushCache();

        return back()->with('success', 'تم حذف الكورس.');
    }

    /**
     * ربط الكورس بالمواد المختارة وتعيين مدرس كل مادة تلقائياً من جدول توزيع المدرسين على المواد.
     *
     * @param  array<int, int|string>  $subjectIds
     * @param  array<int|string, int|string|null>  $subjectTeachersOverride
     */
    private function syncCourseSubjectsAndTeachers(
        Course $course,
        array $subjectIds,
        array $subjectTeachersOverride = []
    ): void {
        Eyal::ensureCurriculumTables();

        $subjectIds = array_values(array_unique(array_map('intval', array_filter($subjectIds))));

        $subjects = Subject::with('teachers:id,name')
            ->whereIn('id', $subjectIds)
            ->when($course->stage_id, fn ($q) => $q->where('stage_id', $course->stage_id))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $syncPayload = [];
        $assignedTeacherIds = [];

        foreach ($subjects as $subject) {
            // إذا تم تمرير مدرس مخصص للمادة وإلا نأخذ المدرس المختص بهذه المادة تلقائياً
            $overrideTeacherId = ! empty($subjectTeachersOverride[$subject->id])
                ? (int) $subjectTeachersOverride[$subject->id]
                : null;

            $autoTeacherId = $subject->teachers->first()?->id
                ? (int) $subject->teachers->first()->id
                : null;

            $teacherId = $overrideTeacherId ?: $autoTeacherId;

            if ($teacherId) {
                $assignedTeacherIds[] = $teacherId;
            }

            $syncPayload[(int) $subject->id] = [
                'teacher_id' => $teacherId,
            ];
        }

        $course->subjects()->sync($syncPayload);

        $subjectNames = $subjects->pluck('name')->filter()->unique()->implode(' + ');
        $uniqueTeachers = array_values(array_unique($assignedTeacherIds));

        $course->update([
            'subject' => $subjectNames ?: $course->subject ?: $course->name,
            'teacher_id' => $uniqueTeachers[0] ?? $course->teacher_id,
        ]);

        Eyal::flushCache();
    }

    /**
     * @return array{attributes: array<string, mixed>, subject_ids: array<int, mixed>, subject_teachers: array<int|string, mixed>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'stage' => ['required', 'exists:stages,id'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'subject_teachers' => ['nullable', 'array'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'يرجى إدخال اسم الكورس.',
            'stage.required' => 'يرجى اختيار المرحلة الدراسية للكورس.',
            'stage.exists' => 'المرحلة المختارة غير موجودة.',
        ]);

        return [
            'attributes' => [
                'name' => $validated['name'],
                'stage_id' => (int) $validated['stage'],
                'teacher_id' => $validated['teacher_id'] ?? null,
                'subject' => ! empty($validated['subject']) ? $validated['subject'] : $validated['name'],
                'price' => $validated['price'] ?? 0,
                'seats' => $validated['seats'] ?? 30,
                'schedule_text' => $validated['schedule'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['active'] ?? false),
                'color' => $request->input('color', 'blue'),
            ],
            'subject_ids' => $validated['subject_ids'] ?? [],
            'subject_teachers' => $validated['subject_teachers'] ?? [],
        ];
    }
}
