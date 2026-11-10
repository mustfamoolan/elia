<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Student;
use App\Support\Eyal;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index()
    {
        return view('admin.attendance', [
            'students' => Eyal::students(),
            'stages' => Eyal::stages(),
            'records' => Eyal::attendanceAll(10),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'status' => ['required', 'array'],
            'status.*' => ['nullable', 'in:present,late,absent'],
        ], [
            'date.required' => 'يرجى تحديد تاريخ الحضور.',
            'status.required' => 'يرجى تحديد حالة طالب واحد على الأقل.',
        ]);

        $saved = 0;

        foreach ($validated['status'] as $studentId => $status) {
            if ($status === null || $status === '') {
                continue;
            }

            $student = Student::query()->with('courses')->find($studentId);

            if (! $student) {
                continue;
            }

            $course = $student->courses->first()
                ?? Course::query()->where('stage_id', $student->stage_id)->first();

            if (! $course) {
                continue;
            }

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

        return back()->with('success', $saved > 0
            ? 'تم حفظ حضور '.$saved.' طالب.'
            : 'لم يتم حفظ أي سجل — تأكد من اختيار الحالة.');
    }
}
