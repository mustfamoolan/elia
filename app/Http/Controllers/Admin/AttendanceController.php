<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Stage;
use App\Models\Student;
use App\Support\Eyal;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $courseId = $request->query('course_id');

        $todayRecordsQuery = AttendanceRecord::query()->where('date', $date);
        if ($courseId) {
            $todayRecordsQuery->where('course_id', (int) $courseId);
        }

        $todayStatuses = $todayRecordsQuery
            ->pluck('status', 'student_id')
            ->all();

        return view('admin.attendance', [
            'students' => Eyal::students(),
            'stages' => Eyal::stages(),
            'courses' => Eyal::coursesAll(),
            'date' => $date,
            'selectedCourseId' => $courseId ? (int) $courseId : null,
            'todayStatuses' => $todayStatuses,
            'records' => Eyal::attendanceAll(30),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'status' => ['required', 'array'],
            'status.*' => ['nullable', 'in:present,late,absent'],
        ], [
            'date.required' => 'يرجى تحديد تاريخ الحضور.',
            'status.required' => 'يرجى تحديد حالة طالب واحد على الأقل.',
        ]);

        $explicitCourse = ! empty($validated['course_id'])
            ? Course::find($validated['course_id'])
            : null;

        $saved = 0;

        foreach ($validated['status'] as $studentId => $status) {
            if ($status === null || $status === '') {
                continue;
            }

            $student = Student::query()->with(['courses', 'stage'])->find($studentId);

            if (! $student) {
                continue;
            }

            $course = $explicitCourse
                ?? $student->courses->first()
                ?? ($student->stage_id ? Course::query()->where('stage_id', $student->stage_id)->first() : null)
                ?? $this->resolveFallbackCourse($student);

            $course->students()->syncWithoutDetaching([
                $student->id => [
                    'fee' => 0,
                    'enrolled_at' => now()->toDateString(),
                    'status' => 'active',
                ],
            ]);

            AttendanceRecord::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'student_id' => $student->id,
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
            ->route('admin.attendance', array_filter([
                'date' => $validated['date'],
                'course_id' => $validated['course_id'] ?? null,
            ]))
            ->with(
                $saved > 0 ? 'success' : 'error',
                $saved > 0
                    ? 'تم حفظ حضور '.$saved.' طالب بنجاح.'
                    : 'لم يتم حفظ أي سجل — يرجى تحديد حالة طالب واحد على الأقل (حاضر / متأخر / غائب).'
            );
    }

    private function resolveFallbackCourse(Student $student): Course
    {
        if ($student->stage_id) {
            $stageName = $student->stage?->name ?? Stage::find($student->stage_id)?->name ?? 'المرحلة';

            return Course::firstOrCreate(
                [
                    'stage_id' => $student->stage_id,
                    'subject' => 'عام',
                ],
                [
                    'name' => 'الحضور العام — '.$stageName,
                    'price' => 0,
                    'seats' => 50,
                    'is_active' => true,
                    'color' => 'blue',
                ]
            );
        }

        return Course::firstOrCreate(
            [
                'stage_id' => null,
                'subject' => 'عام',
            ],
            [
                'name' => 'الحضور العام للمعهد',
                'price' => 0,
                'seats' => 100,
                'is_active' => true,
                'color' => 'blue',
            ]
        );
    }
}
