<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $stageFilter = $request->query('stage');

        return view('admin.subjects', [
            'stages' => Eyal::stages(),
            'subjects' => Eyal::subjectsAll($stageFilter),
            'activeStage' => $stageFilter ? (string) $stageFilter : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'exists:stages,id'],
            'name' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'order' => ['nullable', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ], [
            'stage_id.required' => 'يرجى اختيار المرحلة الدراسية.',
            'stage_id.exists' => 'المرحلة المختارة غير موجودة.',
            'name.required' => 'يرجى إدخال اسم المادة.',
        ]);

        // يدعم إدخال مادة واحدة أو عدة مواد مفصولة بفاصلة لنفس المرحلة دفعة واحدة
        $names = collect(preg_split('/[،,]+/u', $validated['name']))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($names, $validated, $request) {
            $baseOrder = (int) ($validated['order'] ?? 1);
            $isActive = $request->has('active') ? (bool) $request->input('active') : true;

            foreach ($names as $index => $subjectName) {
                Subject::firstOrCreate(
                    [
                        'stage_id' => (int) $validated['stage_id'],
                        'name' => $subjectName,
                    ],
                    [
                        'description' => $validated['description'] ?? null,
                        'sort_order' => $baseOrder + $index,
                        'is_active' => $isActive,
                    ]
                );
            }
        });

        Eyal::flushCache();

        return back()->with('success', $names->count() > 1
            ? 'تمت إضافة المواد الدراسية للمرحلة بنجاح.'
            : 'تمت إضافة المادة الدراسية بنجاح.');
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'exists:stages,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'order' => ['nullable', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ], [
            'stage_id.required' => 'يرجى اختيار المرحلة الدراسية.',
            'name.required' => 'يرجى إدخال اسم المادة.',
        ]);

        $subject->update([
            'stage_id' => (int) $validated['stage_id'],
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['order'] ?? 1),
            'is_active' => (bool) ($validated['active'] ?? false),
        ]);

        Eyal::flushCache();

        return back()->with('success', 'تم تحديث المادة الدراسية.');
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();
        Eyal::flushCache();

        return back()->with('success', 'تم حذف المادة الدراسية.');
    }
}
