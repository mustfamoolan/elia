<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\Eyal;
use Illuminate\Http\Request;

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
        Course::create($this->validated($request));

        return back()->with('success', 'تمت إضافة الدورة.');
    }

    public function update(Request $request, Course $course)
    {
        $course->update($this->validated($request));

        return back()->with('success', 'تم تحديث الدورة.');
    }

    public function destroy(Course $course)
    {
        $course->delete();

        return back()->with('success', 'تم حذف الدورة.');
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
            'subject' => $validated['subject'] ?? null,
            'price' => $validated['price'] ?? 0,
            'seats' => $validated['seats'] ?? 0,
            'schedule_text' => $validated['schedule'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['active'] ?? false),
            'color' => $request->input('color', 'blue'),
        ];
    }
}
