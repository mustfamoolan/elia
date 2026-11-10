<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Eyal;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const TOGGLES = [
        'features.staff_enabled',
        'features.parent_enabled',
        'features.student_enabled',
        'features.announcement_bar',
        'features.registrations_open',
    ];

    public function index()
    {
        return view('admin.settings', [
            'users' => Eyal::usersList(),
            'stages' => Eyal::stages(),
        ]);
    }

    public function updateInstitute(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'work_hours' => ['nullable', 'string', 'max:255'],
            'announcement' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'يرجى إدخال اسم المعهد.',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set('institute.'.$key, $value, 'institute');
        }

        return back()->with('success', 'تم حفظ بيانات المعهد.');
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'primary_color.regex' => 'صيغة اللون الأساسي غير صحيحة.',
            'secondary_color.regex' => 'صيغة اللون الثانوي غير صحيحة.',
        ]);

        Setting::set('theme.primary_color', $validated['primary_color'], 'theme');
        Setting::set('theme.secondary_color', $validated['secondary_color'], 'theme');

        return back()->with('success', 'تم حفظ ألوان الواجهة.');
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'in:'.implode(',', self::TOGGLES)],
        ]);

        $current = (bool) Setting::get($validated['key'], true);
        Setting::set($validated['key'], ! $current, 'features');

        return response()->json(['active' => ! $current]);
    }
}
