<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\RegistrationRequest;
use App\Models\Stage;
use App\Services\FcmService;
use App\Support\Eyal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * API الواجهات العامة للمعهد (Public Portal API)
 * المسار الأساسي: /api/v1/public/...
 * يشمل: الرئيسية، المراحل، الدورات، العروض، الجدول، الإعلانات، الكادر، النتائج، والتسجيل.
 * ============================================================================
 */
class PublicPortalApiController extends Controller
{
    /**
     * البيانات الأساسية للواجهة الرئيسية لتطبيق آيلا (Bootstrap / Home)
     * GET /api/v1/public/bootstrap
     */
    public function bootstrap(): JsonResponse
    {
        $inst = Eyal::institute();

        return response()->json([
            'status' => 'success',
            'institute' => [
                'name' => $inst['name'] ?? 'معهد آيلا التعليمي',
                'short_name' => $inst['short_name'] ?? ($inst['name'] ?? 'معهد آيلا التعليمي'),
                'address' => $inst['address'] ?? '',
                'phone' => $inst['phone'] ?? '',
                'phone_intl' => $inst['phone_intl'] ?? '',
                'whatsapp' => $inst['whatsapp'] ?? '',
                'whatsapp_intl' => $inst['whatsapp_intl'] ?? '',
                'instagram' => $inst['instagram'] ?? '',
                'tiktok' => $inst['tiktok'] ?? '',
                'facebook' => $inst['facebook'] ?? '',
                'facebook_url' => $inst['facebook_url'] ?? '',
                'maps_url' => $inst['maps_url'] ?? '',
                'lat' => (float) ($inst['lat'] ?? 30.0367708),
                'lng' => (float) ($inst['lng'] ?? 47.9226716),
                'work_hours' => $inst['work_hours'] ?? '',
                'announcement' => $inst['announcement'] ?? '',
                'announcement_bar' => (bool) ($inst['announcement_bar'] ?? true),
                'primary_color' => $inst['primary_color'] ?? '#0B3A63',
                'secondary_color' => $inst['secondary_color'] ?? '#1565C0',
                'staff_enabled' => (bool) ($inst['staff_enabled'] ?? true),
                'parent_enabled' => (bool) ($inst['parent_enabled'] ?? true),
                'student_enabled' => (bool) ($inst['student_enabled'] ?? true),
                'registrations_open' => (bool) ($inst['registrations_open'] ?? true),
                'chat_enabled' => (bool) ($inst['chat_enabled'] ?? true),
                'about' => $inst['about'] ?? [],
            ],
            'stats' => Eyal::stats(),
            'stages' => Eyal::stages()->where('visible', true)->values()->all(),
            'featured_courses' => Eyal::courses()->take(6)->values()->all(),
            'latest_announcements' => Eyal::announcements()->take(5)->values()->all(),
            'active_offers' => Eyal::offers()->take(4)->values()->all(),
        ]);
    }

    /**
     * قائمة المراحل الدراسية المتاحة
     * GET /api/v1/public/stages
     */
    public function stages(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'stages' => Eyal::stages()->where('visible', true)->values()->all(),
        ]);
    }

    /**
     * قائمة الدورات والمواد الدراسية (مع إمكانية الفلترة بالمرحلة ?stage=)
     * GET /api/v1/public/courses
     */
    public function courses(Request $request): JsonResponse
    {
        $stage = $request->query('stage');

        return response()->json([
            'status' => 'success',
            'active_stage' => $stage,
            'stages' => Eyal::stages()->where('visible', true)->values()->all(),
            'courses' => Eyal::courses($stage)->values()->all(),
        ]);
    }

    /**
     * العروض والخصومات المتاحة
     * GET /api/v1/public/offers
     */
    public function offers(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'offers' => Eyal::offers()->values()->all(),
        ]);
    }

    /**
     * الجدول الأسبوعي العام للحصص
     * GET /api/v1/public/schedule
     */
    public function schedule(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'days' => config('eyal.days'),
            'schedule' => Eyal::schedule()->values()->all(),
            'schedule_by_day' => Eyal::schedule()->groupBy('day'),
        ]);
    }

    /**
     * الإعلانات والأخبار
     * GET /api/v1/public/announcements
     */
    public function announcements(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'announcements' => Eyal::announcements()->values()->all(),
        ]);
    }

    /**
     * قائمة الواجبات العامة المنشورة للزوار
     * GET /api/v1/public/homework
     */
    public function homework(Request $request): JsonResponse
    {
        $stage = $request->query('stage');

        return response()->json([
            'status' => 'success',
            'homework' => Eyal::homeworkFor(is_string($stage) && $stage !== '' ? $stage : null)->values()->all(),
        ]);
    }

    /**
     * قائمة الإشعارات العامة للزوار
     * GET /api/v1/public/notifications
     */
    public function notifications(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'notifications' => Eyal::notificationsFor(null)->values()->all(),
        ]);
    }

    /**
     * الكادر التدريسي (مع إخفاء أرقام هواتف الكادر عن الطلاب وأولياء الأمور والزوار)
     * GET /api/v1/public/staff
     */
    public function staff(): JsonResponse
    {
        $inst = Eyal::institute();
        if (! (bool) ($inst['staff_enabled'] ?? true)) {
            return response()->json([
                'status' => 'success',
                'staff_enabled' => false,
                'staff' => [],
            ]);
        }

        $staff = Eyal::staff()->map(function (array $member) {
            unset($member['phone']);

            return $member;
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'staff_enabled' => true,
            'staff' => $staff,
        ]);
    }

    /**
     * البحث عن نتائج وحضور طالب برقم الطالب أو الاسم
     * GET /api/v1/public/results?q=101
     */
    public function results(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $student = $query !== '' ? Eyal::searchStudent($query) : null;

        return response()->json([
            'status' => 'success',
            'query' => $query,
            'student' => $student,
            'grades' => $student ? Eyal::gradesFor($student['student_no'])->values()->all() : [],
            'attendance' => $student ? Eyal::attendanceFor($student['student_no'])->values()->all() : [],
        ]);
    }

    /**
     * إرسال طلب تسجيل طالب جديد في المعهد
     * POST /api/v1/public/register-student
     */
    public function registerStudent(Request $request): JsonResponse
    {
        $institute = Eyal::institute();
        if (! (bool) ($institute['registrations_open'] ?? true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'عذراً، التسجيل الإلكتروني مغلق مؤقتاً من قبل إدارة المعهد.',
            ], 403);
        }

        $validated = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'parent_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'stage' => ['required', 'exists:stages,id'],
            'course' => ['nullable', 'exists:courses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'exists:users,id'],
        ], [
            'student_name.required' => 'يرجى إدخال اسم الطالب.',
            'parent_name.required' => 'يرجى إدخال اسم ولي الأمر.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'stage.required' => 'يرجى اختيار المرحلة الدراسية.',
        ]);

        $registration = RegistrationRequest::create([
            'student_name' => $validated['student_name'],
            'parent_id' => $validated['parent_id'] ?? $request->user()?->id,
            'parent_name' => $validated['parent_name'],
            'parent_phone' => $validated['phone'],
            'phone' => $validated['phone'],
            'stage_id' => $validated['stage'],
            'course_id' => $validated['course'] ?? null,
            'note' => $validated['notes'] ?? null,
            'status' => 'new',
        ]);

        $stage = Stage::find($validated['stage']);
        $course = ! empty($validated['course']) ? Course::find($validated['course']) : null;
        $institute = Eyal::institute();

        FcmService::sendToAdmins(
            'طلب تسجيل جديد (#'.$registration->id.')',
            $validated['student_name'].' — '.($stage?->name ?? 'مرحلة غير محددة'),
            '/admin/registrations',
            ['type' => 'registration', 'registration_id' => (string) $registration->id, 'tag' => 'reg-'.$registration->id]
        );

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

        return response()->json([
            'status' => 'success',
            'message' => 'تم استلام طلب التسجيل رقم #'.$registration->id.' بنجاح.',
            'registration_id' => (int) $registration->id,
            'whatsapp_url' => $whatsappUrl,
        ], 201);
    }
}
