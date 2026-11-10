<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\RegistrationRequest;
use App\Models\Stage;
use App\Support\Eyal;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'stats' => Eyal::stats(),
            'courses' => Eyal::courses()->take(3),
            'announcements' => Eyal::announcements()->take(2),
        ]);
    }

    public function about()
    {
        return view('public.about');
    }

    public function stages()
    {
        return view('public.stages', [
            'stages' => Eyal::stages()->where('visible', true)->values(),
        ]);
    }

    public function courses(Request $request)
    {
        $stage = $request->query('stage');

        return view('public.courses', [
            'courses' => Eyal::courses($stage),
            'stages' => Eyal::stages()->where('visible', true)->values(),
            'activeStage' => $stage,
        ]);
    }

    public function offers()
    {
        return view('public.offers', [
            'offers' => Eyal::offers(),
        ]);
    }

    public function schedule()
    {
        return view('public.schedule', [
            'schedule' => Eyal::schedule()->groupBy('day'),
            'days' => config('eyal.days'),
        ]);
    }

    public function announcements()
    {
        return view('public.announcements', [
            'announcements' => Eyal::announcements(),
        ]);
    }

    public function results(Request $request)
    {
        $user = session('eyal_user');
        $student = Eyal::studentForUser($user);

        $query = trim((string) $request->query('q', ''));

        if (! $student && $query !== '') {
            $student = Eyal::searchStudent($query);
        }

        return view('public.results', [
            'query' => $query,
            'student' => $student,
            'grades' => $student ? Eyal::gradesFor($student['student_no']) : collect(),
            'attendance' => $student ? Eyal::attendanceFor($student['student_no']) : collect(),
        ]);
    }

    public function homework()
    {
        $user = session('eyal_user');
        $student = Eyal::studentForUser($user);

        return view('public.homework', [
            'student' => $student,
            'homework' => Eyal::homeworkFor($student['stage_name'] ?? null, $student['student_no'] ?? null),
        ]);
    }

    public function parentPage()
    {
        $user = session('eyal_user');
        $child = null;
        $notice = null;

        if (! $user) {
            $notice = 'guest';
        } elseif ($user['role'] === 'parent') {
            $child = Eyal::studentForUser($user);

            if (! $child) {
                $notice = 'no_child';
            }
        } elseif ($user['role'] === 'student') {
            $notice = 'student';
        } else {
            $notice = 'admin';
        }

        return view('public.parent', [
            'child' => $child,
            'notice' => $notice,
            'grades' => $child ? Eyal::gradesFor($child['student_no']) : collect(),
            'attendance' => $child ? Eyal::attendanceFor($child['student_no']) : collect(),
            'payments' => $child ? Eyal::paymentsFor($child['student_no']) : collect(),
            'homework' => $child ? Eyal::homeworkFor(null, $child['student_no']) : collect(),
        ]);
    }

    public function studentPage()
    {
        $user = session('eyal_user');
        $student = null;
        $notice = null;

        if (! $user) {
            $notice = 'guest';
        } elseif (in_array($user['role'], ['student', 'parent'], true)) {
            $student = Eyal::studentForUser($user);

            if (! $student) {
                $notice = 'no_student';
            }
        } else {
            $notice = 'admin';
        }

        return view('public.student', [
            'student' => $student,
            'notice' => $notice,
            'grades' => $student ? Eyal::gradesFor($student['student_no']) : collect(),
            'attendance' => $student ? Eyal::attendanceFor($student['student_no']) : collect(),
            'payments' => $student ? Eyal::paymentsFor($student['student_no']) : collect(),
            'homework' => $student ? Eyal::homeworkFor(null, $student['student_no']) : collect(),
        ]);
    }

    public function contact()
    {
        return view('public.contact');
    }

    public function location()
    {
        return view('public.location');
    }

    public function staff()
    {
        return view('public.staff', [
            'staff' => Eyal::staff(),
        ]);
    }

    public function notifications()
    {
        return view('public.notifications', [
            'notifications' => Eyal::notificationsFor(session('eyal_user')),
        ]);
    }

    public function register()
    {
        return view('public.register', [
            'stages' => Eyal::stages()->where('visible', true)->values(),
            'courses' => Eyal::courses(),
        ]);
    }

    public function storeRegistration(Request $request)
    {
        $validated = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'parent_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'stage' => ['required', 'exists:stages,id'],
            'course' => ['nullable', 'exists:courses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'student_name.required' => 'يرجى إدخال اسم الطالب.',
            'parent_name.required' => 'يرجى إدخال اسم ولي الأمر.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'stage.required' => 'يرجى اختيار المرحلة الدراسية.',
            'stage.exists' => 'المرحلة المختارة غير متاحة.',
            'course.exists' => 'الدورة المختارة غير متاحة.',
        ]);

        $registration = RegistrationRequest::create([
            'student_name' => $validated['student_name'],
            'parent_name' => $validated['parent_name'],
            'phone' => $validated['phone'],
            'stage_id' => $validated['stage'],
            'course_id' => $validated['course'] ?? null,
            'note' => $validated['notes'] ?? null,
            'status' => 'new',
        ]);

        $stage = Stage::find($validated['stage']);
        $course = ! empty($validated['course']) ? Course::find($validated['course']) : null;
        $institute = Eyal::institute();

        $lines = array_values(array_filter([
            'السلام عليكم، أرغب بتسجيل طالب في '.$institute['name'].':',
            'اسم الطالب: '.$validated['student_name'],
            'اسم ولي الأمر: '.$validated['parent_name'],
            'رقم الهاتف: '.$validated['phone'],
            'المرحلة: '.($stage?->name ?? '—'),
            $course ? 'الدورة/المادة: '.$course->name : '',
            ! empty($validated['notes']) ? 'ملاحظات: '.$validated['notes'] : '',
        ]));

        $whatsappUrl = 'https://wa.me/'.$institute['whatsapp_intl'].'?text='.rawurlencode(implode("\n", $lines));
        $message = 'تم استلام طلب التسجيل رقم #'.$registration->id.' بنجاح. يمكنك إكمال التواصل عبر واتساب.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'whatsapp_url' => $whatsappUrl,
                'registration_id' => $registration->id,
            ]);
        }

        return redirect()
            ->route('register')
            ->with('success', $message)
            ->with('whatsapp_url', $whatsappUrl);
    }
}
