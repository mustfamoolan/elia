<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,staff,student,parent'],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'يرجى إدخال الاسم.',
            'username.required' => 'يرجى إدخال اسم المستخدم.',
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
            'role.required' => 'يرجى اختيار الدور.',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'role' => $validated['role'],
                'phone' => $validated['phone'] ?? null,
                'is_active' => (bool) ($validated['active'] ?? true),
            ]);

            if ($user->role === 'staff') {
                Staff::create([
                    'user_id' => $user->id,
                    'is_public' => false,
                    'color' => 'blue',
                ]);
            }

            if ($user->role === 'student') {
                Student::create([
                    'user_id' => $user->id,
                    'student_no' => $this->nextStudentNumber(),
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'enrollment_date' => now()->toDateString(),
                    'study_type' => 'دروس تقوية',
                    'shift' => 'مسائي',
                    'status' => 'active',
                ]);
            }
        });

        return back()->with('success', 'تم إنشاء حساب الدخول.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'يرجى إدخال الاسم.',
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => (bool) ($validated['active'] ?? false),
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => $validated['password']]);
        }

        if ($user->role === 'student' && $user->student) {
            $user->student->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
            ]);
        }

        return back()->with('success', 'تم تحديث حساب الدخول.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي.');
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'لا يمكن حذف آخر حساب مدير.');
        }

        DB::transaction(function () use ($user) {
            if ($user->staff) {
                $user->staff->delete();
            }

            if ($user->student) {
                $user->student->update(['user_id' => null]);
            }

            if ($user->role === 'parent') {
                $user->children()->detach();
            }

            $user->delete();
        });

        return back()->with('success', 'تم حذف حساب الدخول.');
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
}
