<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    public function index()
    {
        return view('admin.grades', [
            'grades' => Eyal::gradesAll(),
            'students' => Eyal::students(),
            'courses' => Eyal::coursesAll(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $request) {
            $student = Student::query()->where('student_no', $validated['student'])->firstOrFail();

            $assessment = $this->assessment($validated, $request->user()->id);

            Grade::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $student->id],
                ['score' => $validated['score'], 'recorded_by' => $request->user()->id],
            );
        });

        return back()->with('success', 'تم حفظ الدرجة.');
    }

    public function update(Request $request, Grade $grade)
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $request, $grade) {
            $student = Student::query()->where('student_no', $validated['student'])->firstOrFail();

            $assessment = $grade->assessment ?: new Assessment();

            $assessment->fill([
                'course_id' => $validated['course'],
                'title' => $validated['exam'],
                'type' => 'exam',
                'max_score' => $validated['max'],
                'assessed_at' => $validated['date'] ?? now()->toDateString(),
                'created_by' => $assessment->created_by ?? $request->user()->id,
            ])->save();

            $grade->update([
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'score' => $validated['score'],
                'recorded_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'تم تحديث الدرجة.');
    }

    public function destroy(Grade $grade)
    {
        DB::transaction(function () use ($grade) {
            $assessment = $grade->assessment;

            $grade->delete();

            if ($assessment && $assessment->grades()->count() === 0) {
                $assessment->delete();
            }
        });

        return back()->with('success', 'تم حذف الدرجة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'student' => ['required', 'string', 'exists:students,student_no'],
            'course' => ['required', 'exists:courses,id'],
            'exam' => ['required', 'string', 'max:255'],
            'score' => ['required', 'numeric', 'min:0'],
            'max' => ['required', 'numeric', 'min:1'],
            'date' => ['nullable', 'date'],
        ], [
            'student.required' => 'يرجى اختيار الطالب.',
            'student.exists' => 'الطالب المختار غير موجود.',
            'course.required' => 'يرجى اختيار المادة.',
            'exam.required' => 'يرجى إدخال اسم الامتحان.',
            'score.required' => 'يرجى إدخال الدرجة.',
            'max.required' => 'يرجى إدخال الدرجة الكاملة.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assessment(array $validated, ?int $createdBy): Assessment
    {
        return Assessment::firstOrCreate(
            [
                'course_id' => $validated['course'],
                'title' => $validated['exam'],
                'assessed_at' => $validated['date'] ?? now()->toDateString(),
            ],
            [
                'type' => 'exam',
                'max_score' => $validated['max'],
                'created_by' => $createdBy,
            ],
        );
    }
}
