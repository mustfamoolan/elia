<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Staff;
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

            Staff::create([
                'user_id' => $user->id,
                'subject' => $validated['subject'] ?? null,
                'stage_label' => $validated['stage'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'hire_date' => now()->toDateString(),
                'is_public' => true,
                'color' => 'blue',
            ]);
        });

        return back()->with('success', 'تمت إضافة المدرس وحساب الدخول.');
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
        });

        return back()->with('success', 'تم تحديث بيانات المدرس.');
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
            $staff->delete();
            $staff->user?->delete();
        });

        return back()->with('success', 'تم حذف المدرس وحساب الدخول.');
    }
}
