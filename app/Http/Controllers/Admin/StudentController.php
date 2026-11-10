<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index()
    {
        return view('admin.students', [
            'students' => Eyal::students(),
            'stages' => Eyal::stages(),
            'parents' => User::query()
                ->where('role', 'parent')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateStudent($request);

        DB::transaction(function () use ($validated, $request) {
            $studentNo = $this->nextStudentNumber();

            $student = Student::create([
                'stage_id' => $validated['stage'] ?? null,
                'student_no' => $studentNo,
                'name' => $validated['student_name'],
                'guardian_name' => $validated['parent_name'] ?? null,
                'guardian_phone' => $validated['phone'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'enrollment_date' => now()->toDateString(),
                'study_type' => $validated['study_type'] ?? 'دروس تقوية',
                'shift' => $validated['shift'] ?? 'مسائي',
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncCourses($student, $validated['subjects'] ?? null);
            $this->syncCharge($student, (int) ($validated['fee'] ?? 0), $request->user()->id);
            $this->syncParent($student, $validated['parent_id'] ?? null);

            if (! empty($validated['has_account'])) {
                $this->createAccount($student, $validated['password'] ?? null);
            }
        });

        return back()->with('success', 'تمت إضافة الطالب بنجاح.');
    }

    public function update(Request $request, Student $student)
    {
        $validated = $this->validateStudent($request, $student);

        DB::transaction(function () use ($validated, $request, $student) {
            $student->update([
                'stage_id' => $validated['stage'] ?? null,
                'name' => $validated['student_name'],
                'guardian_name' => $validated['parent_name'] ?? null,
                'guardian_phone' => $validated['phone'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'study_type' => $validated['study_type'] ?? $student->study_type,
                'shift' => $validated['shift'] ?? $student->shift,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncCourses($student, $validated['subjects'] ?? null);
            $this->syncCharge($student, (int) ($validated['fee'] ?? 0), $request->user()->id);
            $this->syncParent($student, $validated['parent_id'] ?? null);

            if (! empty($validated['has_account'])) {
                if ($student->user_id) {
                    if (! empty($validated['password'])) {
                        $student->user->update(['password' => $validated['password']]);
                    }
                } else {
                    $this->createAccount($student, $validated['password'] ?? null);
                }
            }
        });

        return back()->with('success', 'تم تحديث بيانات الطالب.');
    }

    public function destroy(Student $student)
    {
        DB::transaction(function () use ($student) {
            if ($student->user_id) {
                $student->user->update(['is_active' => false]);
            }

            $student->delete();
        });

        return back()->with('success', 'تم حذف الطالب.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStudent(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'stage' => ['nullable', 'exists:stages,id'],
            'parent_id' => ['nullable', 'exists:users,id'],
            'subjects' => ['nullable', 'string', 'max:500'],
            'study_type' => ['nullable', 'string', 'max:40'],
            'shift' => ['nullable', 'string', 'max:20'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'has_account' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ], [
            'student_name.required' => 'يرجى إدخال اسم الطالب.',
            'stage.exists' => 'المرحلة المختارة غير متاحة.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ]);
    }

    private function nextStudentNumber(): string
    {
        $max = Student::withTrashed()
            ->pluck('student_no')
            ->map(fn ($value) => (int) $value)
            ->max();

        $next = max(100, (int) $max) + 1;

        while (Student::withTrashed()->where('student_no', (string) $next)->exists()) {
            $next++;
        }

        return (string) $next;
    }

    private function syncCourses(Student $student, ?string $subjects): void
    {
        $names = collect(preg_split('/[،,]+/u', (string) $subjects))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return;
        }

        $courseIds = Course::query()
            ->whereIn('subject', $names->all())
            ->pluck('id')
            ->all();

        $current = $student->courses()->pluck('courses.id')->all();

        $student->courses()->detach(array_values(array_diff($current, $courseIds)));

        foreach ($courseIds as $courseId) {
            if (! in_array($courseId, $current, true)) {
                $student->courses()->attach($courseId, [
                    'fee' => 0,
                    'enrolled_at' => now()->toDateString(),
                    'status' => 'active',
                ]);
            }
        }
    }

    private function syncCharge(Student $student, int $fee, ?int $createdBy): void
    {
        if ($fee <= 0) {
            return;
        }

        $charge = $student->charges()->oldest('id')->first();

        if ($charge) {
            $charge->update(['amount' => $fee]);
        } else {
            StudentCharge::create([
                'student_id' => $student->id,
                'title' => 'اشتراك دراسي',
                'amount' => $fee,
                'due_date' => now()->addDays(30)->toDateString(),
                'created_by' => $createdBy,
            ]);
        }
    }

    private function syncParent(Student $student, mixed $parentId): void
    {
        if (! $parentId) {
            return;
        }

        $student->parents()->sync([
            (int) $parentId => ['relation' => 'ولي أمر', 'is_primary' => true],
        ]);
    }

    private function createAccount(Student $student, ?string $password): void
    {
        $username = $student->student_no;

        if (User::withTrashed()->where('username', $username)->exists()) {
            $username .= '-'.$student->id;
        }

        $user = User::create([
            'name' => $student->name,
            'username' => $username,
            'password' => $password ?: '123456',
            'role' => 'student',
            'phone' => $student->phone,
            'is_active' => true,
        ]);

        $student->update(['user_id' => $user->id]);
    }
}
