<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ScheduleSlot;
use App\Support\Eyal;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    private const DAYS = [
        'السبت' => 6,
        'الأحد' => 0,
        'الاثنين' => 1,
        'الثلاثاء' => 2,
        'الأربعاء' => 3,
        'الخميس' => 4,
        'الجمعة' => 5,
    ];

    public function index()
    {
        return view('admin.schedule', [
            'schedule' => Eyal::schedule()->groupBy('day'),
            'days' => config('eyal.days'),
            'courses' => Eyal::coursesAll(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => ['required', 'string', 'in:'.implode(',', array_keys(self::DAYS))],
            'course' => ['required', 'exists:courses,id'],
            'hall' => ['nullable', 'string', 'max:100'],
            'time' => ['nullable', 'string', 'max:100'],
            'teacher' => ['nullable', 'string', 'max:255'],
        ], [
            'day.required' => 'يرجى اختيار اليوم.',
            'course.required' => 'يرجى اختيار الدورة.',
        ]);

        $course = Course::with(['stage', 'teacher'])->findOrFail($validated['course']);
        [$start, $end] = $this->parseTimeRange($validated['time'] ?? null);

        ScheduleSlot::create([
            'course_id' => $course->id,
            'day_of_week' => self::DAYS[$validated['day']],
            'start_time' => $start,
            'end_time' => $end,
            'hall' => $validated['hall'] ?? null,
            'course_name' => $course->name,
            'stage_name' => $course->stage?->name,
            'teacher_name' => $validated['teacher'] ?? $course->teacher?->name,
            'is_active' => true,
        ]);

        return back()->with('success', 'تمت إضافة الموعد.');
    }

    public function destroy(ScheduleSlot $schedule)
    {
        $schedule->delete();

        return back()->with('success', 'تم حذف الموعد.');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseTimeRange(?string $value): array
    {
        $value = trim((string) $value);

        preg_match_all('/(\d{1,2})[:.](\d{2})\s*(ص|م)?/u', $value, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return ['16:00:00', '18:00:00'];
        }

        $start = $this->toTwentyFour($matches[0]);

        if (isset($matches[1])) {
            $end = $this->toTwentyFour($matches[1]);
        } else {
            $end = date('H:i:s', strtotime($start.' +1 hour'));
        }

        return [$start, $end];
    }

    /**
     * @param  array<int, string>  $match
     */
    private function toTwentyFour(array $match): string
    {
        $hour = (int) ($match[1] ?? 0);
        $minute = (int) ($match[2] ?? 0);
        $suffix = $match[3] ?? '';

        if ($suffix === 'م' && $hour < 12) {
            $hour += 12;
        } elseif ($suffix === 'ص' && $hour === 12) {
            $hour = 0;
        } elseif ($suffix === '' && $hour > 0 && $hour < 8) {
            $hour += 12;
        }

        return sprintf('%02d:%02d:00', $hour % 24, $minute % 60);
    }
}
