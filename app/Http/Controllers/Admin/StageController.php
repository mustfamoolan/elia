<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stage;
use App\Support\Eyal;
use Illuminate\Http\Request;

class StageController extends Controller
{
    public function index()
    {
        return view('admin.stages', [
            'stages' => Eyal::stages(),
        ]);
    }

    public function store(Request $request)
    {
        Stage::create($this->validated($request));

        return back()->with('success', 'تمت إضافة المرحلة.');
    }

    public function update(Request $request, Stage $stage)
    {
        $stage->update($this->validated($request));

        return back()->with('success', 'تم تحديث المرحلة.');
    }

    public function toggle(Stage $stage)
    {
        $stage->update(['is_visible' => ! $stage->is_visible]);

        return response()->json(['active' => $stage->is_visible]);
    }

    public function destroy(Stage $stage)
    {
        $stage->delete();

        return back()->with('success', 'تم حذف المرحلة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'order' => ['nullable', 'integer', 'min:1'],
            'visible' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'يرجى إدخال اسم المرحلة.',
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['order'] ?? 1,
            'is_visible' => (bool) ($validated['visible'] ?? false),
            'icon' => $request->input('icon', 'layers'),
            'color' => $request->input('color', 'blue'),
        ];
    }
}
