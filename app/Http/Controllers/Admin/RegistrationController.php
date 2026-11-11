<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\RegistrationRequest;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function index()
    {
        $registrations = Eyal::registrations();

        return view('admin.registrations', [
            'registrations' => $registrations,
            'counts' => [
                'all' => $registrations->count(),
                'new' => $registrations->where('status', 'new')->count(),
                'accepted' => $registrations->where('status', 'accepted')->count(),
                'rejected' => $registrations->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, RegistrationRequest $registration, string $status)
    {
        abort_unless(in_array($status, ['accepted', 'rejected'], true), 404);

        DB::transaction(function () use ($registration, $status, $request) {
            if ($status === 'accepted' && $registration->status !== 'accepted') {
                $this->createStudent($registration, $request->user()->id);
            }

            $registration->update([
                'status' => $status,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);
        });

        return back()->with('success', $status === 'accepted'
            ? 'تم قبول الطلب وإنشاء ملف الطالب.'
            : 'تم رفض طلب التسجيل.');
    }

    public function destroy(RegistrationRequest $registration)
    {
        $registration->delete();

        return back()->with('success', 'تم حذف طلب التسجيل.');
    }

    private function createStudent(RegistrationRequest $registration, ?int $processedBy): void
    {
        $exists = Student::query()
            ->where('name', $registration->student_name)
            ->where(function ($query) use ($registration) {
                $query->where('phone', $registration->phone)
                    ->orWhere('guardian_phone', $registration->phone);
            })
            ->exists();

        if ($exists) {
            return;
        }

        $max = Student::withTrashed()
            ->pluck('student_no')
            ->map(fn ($value) => (int) $value)
            ->max();

        $next = max(100, (int) $max) + 1;

        while (Student::withTrashed()->where('student_no', (string) $next)->exists()) {
            $next++;
        }

        $student = Student::create([
            'stage_id' => $registration->stage_id,
            'student_no' => (string) $next,
            'name' => $registration->student_name,
            'guardian_name' => $registration->parent_name,
            'guardian_phone' => $registration->parent_phone ?: $registration->phone,
            'phone' => $registration->phone,
            'address' => $registration->address,
            'birth_date' => $registration->birth_date,
            'enrollment_date' => now()->toDateString(),
            'study_type' => 'دروس تقوية',
            'shift' => 'مسائي',
            'status' => 'active',
            'notes' => $registration->note,
        ]);

        if ($registration->course_id) {
            $course = Course::find($registration->course_id);

            if ($course) {
                $student->courses()->attach($course->id, [
                    'fee' => $course->price,
                    'enrolled_at' => now()->toDateString(),
                    'status' => 'active',
                ]);

                if ((int) $course->price > 0) {
                    StudentCharge::create([
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                        'title' => 'اشتراك '.$course->name,
                        'amount' => $course->price,
                        'due_date' => now()->addDays(30)->toDateString(),
                        'created_by' => $processedBy,
                    ]);
                }
            }
        } elseif ($registration->stage_id) {
            $stageCourses = Course::query()
                ->where('stage_id', $registration->stage_id)
                ->where('is_active', true)
                ->pluck('id');

            foreach ($stageCourses as $stageCourseId) {
                $student->courses()->syncWithoutDetaching([
                    (int) $stageCourseId => [
                        'fee' => 0,
                        'enrolled_at' => now()->toDateString(),
                        'status' => 'active',
                    ],
                ]);
            }
        }

        // ربط الطالب بحساب ولي الأمر تلقائياً
        $parentId = $registration->parent_id;
        if (! $parentId && ($registration->parent_phone || $registration->phone)) {
            $parentUser = \App\Models\User::where('role', 'parent')
                ->where(function ($q) use ($registration) {
                    if ($registration->parent_phone) {
                        $q->where('phone', $registration->parent_phone);
                    }
                    if ($registration->phone) {
                        $q->orWhere('phone', $registration->phone);
                    }
                })->first();

            $parentId = $parentUser?->id;
        }

        if ($parentId) {
            $student->parents()->syncWithoutDetaching([
                $parentId => ['relation' => 'parent', 'is_primary' => true],
            ]);
        }
    }
}
