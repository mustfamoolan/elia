<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Stage;
use App\Models\Subject;
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
            'subjects' => Eyal::subjectsAll(),
            'staffEnabled' => (bool) ($institute['staff_enabled'] ?? true),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'stage_ids' => ['nullable', 'array'],
            'stage_ids.*' => ['integer', 'exists:stages,id'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
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
                'subject' => null,
                'stage_label' => null,
                'bio' => $validated['bio'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'hire_date' => now()->toDateString(),
                'is_public' => true,
                'color' => 'blue',
            ]);

            $this->syncTeacherAssignments(
                $user,
                $staff,
                $validated['stage_ids'] ?? [],
                $validated['subject_ids'] ?? [],
            );
        });

        return back()->with('success', 'تمت إضافة المدرس وتعيين مراحله ومواده الدراسية بنجاح.');
    }

    public function update(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', Rule::unique('users', 'username')->ignore($staff->user_id)],
            'password' => ['nullable', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'stage_ids' => ['nullable', 'array'],
            'stage_ids.*' => ['integer', 'exists:stages,id'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
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
                'bio' => $validated['bio'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]);

            $this->syncTeacherAssignments(
                $staff->user,
                $staff,
                $validated['stage_ids'] ?? [],
                $validated['subject_ids'] ?? [],
            );
        });

        return back()->with('success', 'تم تحديث بيانات المدرس ومراحله ومواده بنجاح.');
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
            DB::table('course_subject')->where('teacher_id', $staff->user_id)->update(['teacher_id' => null]);
            $staff->delete();
            $staff->user?->delete();
        });

        Eyal::flushCache();

        return back()->with('success', 'تم حذف المدرس وحساب الدخول.');
    }

    /**
     * ربط المدرس بالمراحل والمواد المختارة فقط بدون إنشاء أي كورسات وهمية أو إلحاق طلاب تلقائي.
     *
     * @param  array<int, int|string>  $stageIds
     * @param  array<int, int|string>  $subjectIds
     */
    private function syncTeacherAssignments(
        User $user,
        Staff $staff,
        array $stageIds,
        array $subjectIds,
    ): void {
        Eyal::ensureCurriculumTables();

        $stageIds = array_values(array_unique(array_map('intval', array_filter($stageIds))));
        $subjectIds = array_values(array_unique(array_map('intval', array_filter($subjectIds))));

        // فقط المواد التي تنتمي للمراحل المختارة (أو نضم مراحل المواد المختارة تلقائياً)
        $subjects = Subject::with('stage:id,name')
            ->whereIn('id', $subjectIds)
            ->when(! empty($stageIds), fn ($q) => $q->whereIn('stage_id', $stageIds))
            ->get();

        $validSubjectIds = $subjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $allStageIds = collect($stageIds)
            ->merge($subjects->pluck('stage_id'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $user->teachingStages()->sync($allStageIds);

        $subjectSyncPayload = [];
        foreach ($subjects as $sub) {
            $subjectSyncPayload[(int) $sub->id] = [
                'stage_id' => (int) $sub->stage_id,
            ];
        }
        $user->teachingSubjects()->sync($subjectSyncPayload);

        // تحديث الكورسات القائمة التي تحتوي على هذه المواد:
        // 1) إزالة هذا المدرس من المواد التي لم يعد يدرّسها داخل الكورسات
        DB::table('course_subject')
            ->where('teacher_id', $user->id)
            ->when(! empty($validSubjectIds), fn ($q) => $q->whereNotIn('subject_id', $validSubjectIds))
            ->update(['teacher_id' => null]);

        // 2) تعيين هذا المدرس تلقائياً لأي مادة من مواده داخل الكورسات إذا لم يكن لها مدرس معيّن
        if (! empty($validSubjectIds)) {
            DB::table('course_subject')
                ->whereIn('subject_id', $validSubjectIds)
                ->whereNull('teacher_id')
                ->update(['teacher_id' => $user->id]);
        }

        // تحديث ملصق المواد والمراحل للعرض السريع في ملف المدرس
        $stagesModels = Stage::whereIn('id', $allStageIds)->orderBy('sort_order')->get(['id', 'name']);
        $subjectsLabel = $subjects->pluck('name')->unique()->implode('، ');
        $stagesLabel = $stagesModels->pluck('name')->unique()->implode('، ');

        $staff->update([
            'subject' => $subjectsLabel ?: null,
            'stage_label' => $stagesLabel ?: null,
        ]);

        Eyal::flushCache();
    }
}
