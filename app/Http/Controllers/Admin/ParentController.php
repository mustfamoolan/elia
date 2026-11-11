<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParentController extends Controller
{
    public function index()
    {
        return view('admin.parents', [
            'parents' => Eyal::parentsAll(),
            'students' => Eyal::students(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateParent($request);

        DB::transaction(function () use ($validated) {
            $username = trim((string) ($validated['username'] ?? ''));
            if ($username === '') {
                $base = preg_replace('/\s+/', '', (string) ($validated['phone'] ?? '')) ?: 'parent';
                $username = $base;
                $counter = 1;
                while (User::withTrashed()->where('username', $username)->exists()) {
                    $username = $base.'_'.$counter++;
                }
            }

            $parent = User::create([
                'name' => $validated['name'],
                'username' => $username,
                'phone' => $validated['phone'] ?? null,
                'password' => ! empty($validated['password']) ? $validated['password'] : '123456',
                'role' => 'parent',
                'is_active' => (bool) ($validated['active'] ?? true),
            ]);

            $this->syncChildren($parent, $validated['student_ids'] ?? []);
        });

        return back()->with('success', 'تمت إضافة حساب ولي الأمر وربطه بالأبناء بنجاح.');
    }

    public function update(Request $request, User $parent)
    {
        abort_unless($parent->role === 'parent', 404);

        $validated = $this->validateParent($request, $parent);

        DB::transaction(function () use ($validated, $parent) {
            $username = trim((string) ($validated['username'] ?? ''));
            if ($username === '') {
                $username = $parent->username;
            }

            $payload = [
                'name' => $validated['name'],
                'username' => $username,
                'phone' => $validated['phone'] ?? null,
                'is_active' => (bool) ($validated['active'] ?? false),
            ];

            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }

            $parent->update($payload);

            $this->syncChildren($parent, $validated['student_ids'] ?? []);
        });

        return back()->with('success', 'تم تحديث بيانات ولي الأمر وربط الأبناء بنجاح.');
    }

    public function destroy(User $parent)
    {
        abort_unless($parent->role === 'parent', 404);

        DB::transaction(function () use ($parent) {
            $parent->children()->detach();
            $parent->delete();
        });

        return back()->with('success', 'تم حذف حساب ولي الأمر.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateParent(Request $request, ?User $parent = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'username' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('users', 'username')->ignore($parent?->id),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'active' => ['nullable', 'boolean'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ], [
            'name.required' => 'يرجى إدخال اسم ولي الأمر.',
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
        ]);
    }

    /**
     * @param  array<int, int|string>  $studentIds
     */
    private function syncChildren(User $parent, array $studentIds): void
    {
        $ids = collect($studentIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        // إذا لم يُحدد أي طالب يدوياً وكان رقم هاتف ولي الأمر يطابق طلاباً، نربطهم تلقائياً عند الإنشاء
        if ($ids->isEmpty() && $parent->wasRecentlyCreated && $parent->phone) {
            $ids = Student::query()
                ->where('guardian_phone', $parent->phone)
                ->orWhere('phone', $parent->phone)
                ->pluck('id');
        }

        $syncData = $ids->mapWithKeys(fn (int $id) => [
            $id => [
                'relation' => 'ولي أمر',
                'is_primary' => true,
            ],
        ])->all();

        $parent->children()->sync($syncData);

        if ($ids->isNotEmpty()) {
            Student::query()
                ->whereIn('id', $ids->all())
                ->get()
                ->each(function (Student $student) use ($parent) {
                    $student->update([
                        'guardian_name' => $parent->name,
                        'guardian_phone' => $parent->phone ?: $student->guardian_phone,
                    ]);
                });
        }

        Eyal::flushCache();
    }
}

