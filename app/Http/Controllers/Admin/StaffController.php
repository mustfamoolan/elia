<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index()
    {
        $institute = Eyal::institute();

        return view('admin.staff', [
            'staff' => Eyal::staffAll(),
            'stages' => Eyal::stages(),
            'courses' => Eyal::coursesAll(),
            'staffEnabled' => (bool) ($institute['staff_enabled'] ?? true),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'subject' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'stage_ids' => ['nullable', 'array'],
            'stage_ids.*' => ['integer', 'exists:stages,id'],
        ], [
            'name.required' => 'يرجى إدخال اسم المدرس.',
            'username.required' => 'يرجى إدخال اسم المستخدم.',
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'role' => 'staff',
                'phone' => $validated['phone'] ?? null,
                'is_active' => true,
            ]);

            $staff = Staff::create([
                'user_id' => $user->id,
                'subject' => $validated['subject'] ?? null,
                'stage_label' => $validated['stage'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'hire_date' => now()->toDateString(),
                'is_public' => true,
                'color' => 'blue',
            ]);

            $this->syncTeacherAssignments(
                $user,
                $staff,
                $validated['course_ids'] ?? [],
                $validated['stage_ids'] ?? [],
                $validated['subject'] ?? null,
                $validated['stage'] ?? null,
            );
        });

        return back()->with('success', 'تمت إضافة المدرس وتعيين دوراته ومراحله بنجاح.');
    }

    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', Rule::unique('users', 'username')->ignore($staff->user_id)],
            'password' => ['nullable', 'string', 'min:6'],
            'subject' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'stage_ids' => ['nullable', 'array'],
            'stage_ids.*' => ['integer', 'exists:stages,id'],
        ], [
            'name.required' => 'يرجى إدخال اسم المدرس.',
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ]);

        DB::transaction(function () use ($validated, $staff) {
            $staff->user->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'phone' => $validated['phone'] ?? null,
            ]);

            if (! empty($validated['password'])) {
                $staff->user->update(['password' => $validated['password']]);
            }

            $staff->update([
                'subject' => $validated['subject'] ?? null,
                'stage_label' => $validated['stage'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]);

            $this->syncTeacherAssignments(
                $staff->user,
                $staff,
                $validated['course_ids'] ?? [],
                $validated['stage_ids'] ?? [],
                $validated['subject'] ?? null,
                $validated['stage'] ?? null,
            );
        });

        return back()->with('success', 'تم تحديث بيانات المدرس ودوراته ومراحله.');
    }

    public function toggleVisibility()
    {
        $current = (bool) Setting::get('features.staff_enabled', true);
        Setting::set('features.staff_enabled', ! $current, 'features');

        return response()->json(['active' => ! $current]);
    }

    public function destroy(Staff $staff)
    {
        DB::transaction(function () use ($staff) {
            Course::where('teacher_id', $staff->user_id)->update(['teacher_id' => null]);
            $staff->delete();
            $staff->user?->delete();
        });

        return back()->with('success', 'تم حذف المدرس وحساب الدخول.');
    }

    /**
     * ربط المدرس بالدورات والمراحل وإنشاء الدورات تلقائياً عند تحديد مراحل ومواد جديدة.
     *
     * @param  array<int, int|string>  $courseIds
     * @param  array<int, int|string>  $stageIds
     */
    private function syncTeacherAssignments(
        User $user,
        Staff $staff,
        array $courseIds,
        array $stageIds,
        ?string $subjectText,
        ?string $stageText
    ): void {
        $courseIds = array_values(array_unique(array_map('intval', array_filter($courseIds))));
        $stageIds = array_values(array_unique(array_map('intval', array_filter($stageIds))));

        // فك ارتباط الدورات السابقة التي أُزيلت من هذا المدرس
        Course::query()
            ->where('teacher_id', $user->id)
            ->when(! empty($courseIds), fn ($q) => $q->whereNotIn('id', $courseIds))
            ->update(['teacher_id' => null]);

        // إسناد الدورات المختارة مباشرة للمدرس
        if (! empty($courseIds)) {
            Course::query()->whereIn('id', $courseIds)->update(['teacher_id' => $user->id]);
        }

        // استخراج المواد المكتوبة (يدعم مادة أو عدة مواد مفصولة بفاصلة)
        $subjects = collect(preg_split('/[،,]+/u', (string) $subjectText))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        // إذا حدد المدير مراحل ومواد، نتأكد من وجود دورة لكل مرحلة ومادة وإسنادها للمدرس
        if (! empty($stageIds) && $subjects->isNotEmpty()) {
            $stages = Stage::whereIn('id', $stageIds)->get()->keyBy('id');

            foreach ($stageIds as $stageId) {
                $stageModel = $stages->get($stageId);
                if (! $stageModel) {
                    continue;
                }

                foreach ($subjects as $subj) {
                    // هل توجد دورة بنفس المرحلة والمادة؟
                    $existingCourse = Course::query()
                        ->where('stage_id', $stageId)
                        ->where(function ($q) use ($subj) {
                            $q->where('subject', $subj)
                                ->orWhere('name', $subj)
                                ->orWhere('name', 'like', '%'.$subj.'%');
                        })
                        ->where(function ($q) use ($user) {
                            $q->whereNull('teacher_id')->orWhere('teacher_id', $user->id);
                        })
                        ->first();

                    if ($existingCourse) {
                        $existingCourse->update([
                            'teacher_id' => $user->id,
                            'subject' => $existingCourse->subject ?: $subj,
                        ]);
                        $courseIds[] = $existingCourse->id;
                    } else {
                        $newCourse = Course::create([
                            'stage_id' => $stageId,
                            'teacher_id' => $user->id,
                            'name' => $subj.' — '.$stageModel->name,
                            'subject' => $subj,
                            'price' => 0,
                            'seats' => 30,
                            'is_active' => true,
                            'color' => 'blue',
                        ]);
                        $courseIds[] = $newCourse->id;
                    }
                }
            }
        }

        // تحديث ملصقات المادة والمرحلة في ملف المدرس بناءً على دوراته الفعلية
        $assignedCourses = Course::with('stage')->where('teacher_id', $user->id)->get();

        if ($assignedCourses->isNotEmpty()) {
            $autoSubject = $assignedCourses->map(fn (Course $c) => $c->subject ?: $c->name)->filter()->unique()->implode('، ');
            $autoStage = $assignedCourses->map(fn (Course $c) => $c->stage?->name)->filter()->unique()->implode('، ');

            $staff->update([
                'subject' => $subjectText ?: $autoSubject,
                'stage_label' => $stageText ?: $autoStage,
            ]);

            // ربط طلاب نفس المرحلة بهذه الدورات لضمان ظهورهم للمدرس فوراً
            $today = now()->toDateString();
            foreach ($assignedCourses as $course) {
                if (! $course->stage_id) {
                    continue;
                }

                $stageStudents = Student::query()
                    ->where('stage_id', $course->stage_id)
                    ->where('status', 'active')
                    ->whereDoesntHave('courses', fn ($q) => $q->where('courses.id', $course->id))
                    ->pluck('id')
                    ->all();

                if (! empty($stageStudents)) {
                    $syncPayload = [];
                    foreach ($stageStudents as $stId) {
                        $syncPayload[(int) $stId] = [
                            'fee' => 0,
                            'enrolled_at' => $today,
                            'status' => 'active',
                        ];
                    }
                    $course->students()->syncWithoutDetaching($syncPayload);
                }
            }
        }

        Eyal::flushCache();
    }
}
