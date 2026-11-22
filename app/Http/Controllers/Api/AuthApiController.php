<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\PushToken;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * API المصادقة والدخول لتطبيقات الموبايل (Auth API)
 * المسار الأساسي: /api/v1/auth/...
 * ============================================================================
 */
class AuthApiController extends Controller
{
    /**
     * تسجيل الدخول الموحد (أولياء الأمور، الطلاب، الكادر التدريسي، أو الإدارة)
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:30'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string', 'in:parent,student,staff,admin'],
        ], [
            'username.required' => 'يرجى إدخال اسم المستخدم أو رقم الطالب.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
        ]);

        $username = trim($validated['username']);
        $throttleKey = 'api_login:'.Str::transliterate(Str::lower($username).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'status' => 'error',
                'message' => 'محاولات تسجيل دخول كثيرة. يرجى المحاولة بعد '.$seconds.' ثانية.',
            ], 429);
        }

        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'status' => 'error',
                'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'هذا الحساب غير مفعّل حالياً. يرجى مراجعة إدارة المعهد.',
            ], 403);
        }

        if (! empty($validated['allowed_roles']) && ! in_array($user->role, $validated['allowed_roles'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'هذا الحساب مخصص لفئة ('.$user->roleLabel().') ولا يمكن استخدامه في هذا التطبيق.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Eyal::touchUserPresence((int) $user->id);

        $issued = ApiToken::issueForUser($user, $validated['device_name'] ?? 'ayla-mobile');

        // ربط رمز إشعارات Firebase FCM إن تم تمريره مع تسجيل الدخول
        if (! empty($validated['fcm_token'])) {
            $this->registerPushToken(
                $user,
                $validated['fcm_token'],
                $validated['platform'] ?? 'android',
                $validated['device_name'] ?? 'ayla-mobile'
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الدخول بنجاح.',
            'token' => $issued['plain_token'],
            'token_type' => 'Bearer',
            'user' => $this->formatUserPayload($user),
            'institute' => $this->publicInstituteConfig(),
        ]);
    }

    /**
     * جلب بيانات الحساب المسجل حالياً (مع الأبناء لولي الأمر أو بيانات الطالب أو ملف المدرس)
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        Eyal::touchUserPresence((int) $user->id);

        return response()->json([
            'status' => 'success',
            'user' => $this->formatUserPayload($user),
            'institute' => $this->publicInstituteConfig(),
        ]);
    }

    /**
     * تسجيل الخروج وإلغاء رمز الدخول الحالي
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $currentToken */
        $currentToken = $request->attributes->get('current_api_token');
        if ($currentToken) {
            $currentToken->delete();
        }

        $fcmToken = trim((string) $request->input('fcm_token', ''));
        if ($fcmToken !== '') {
            Eyal::ensurePushTables();
            PushToken::where('token', $fcmToken)->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * تسجيل أو تحديث رمز إشعارات الموبايل (FCM Push Token)
     * POST /api/v1/auth/push-token
     */
    public function updatePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['nullable', 'string', 'max:30'],
            'device_label' => ['nullable', 'string', 'max:120'],
        ]);

        $this->registerPushToken(
            $request->user(),
            $validated['token'],
            $validated['platform'] ?? 'android',
            $validated['device_label'] ?? 'ayla-mobile'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'تم تفعيل إشعارات الهاتف الفورية بنجاح.',
        ]);
    }

    private function registerPushToken(User $user, string $token, string $platform, string $deviceLabel): void
    {
        try {
            Eyal::ensurePushTables();
            PushToken::updateOrCreate(
                ['token' => trim($token)],
                [
                    'user_id' => $user->id,
                    'platform' => substr(trim($platform) ?: 'android', 0, 30),
                    'device_label' => substr(trim($deviceLabel) ?: 'ayla-mobile', 0, 120),
                    'last_seen_at' => now(),
                ]
            );
        } catch (\Throwable) {
            // ignore push token errors
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUserPayload(User $user): array
    {
        $base = [
            'id' => (int) $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'role' => $user->role,
            'role_label' => $user->roleLabel(),
            'portal_type' => match ($user->role) {
                'parent' => 'parent_portal',
                'student' => 'student_portal',
                'staff' => 'teacher_portal',
                'admin' => 'admin_portal',
                default => 'student_portal',
            },
            'unread_chats_count' => $user->totalUnreadChatsCount(),
            'unread_notifications_count' => Eyal::unreadNotificationsCount($user->toEyalArray()),
        ];

        if ($user->isParent()) {
            $children = Eyal::childrenForParent((int) $user->id);
            $base['children_count'] = $children->count();
            $base['children'] = $children->values()->all();
            $base['default_child_id'] = $children->first()['id'] ?? null;
        } elseif ($user->isStudent()) {
            $student = Eyal::studentForUser($user->toEyalArray());
            $base['student'] = $student;
            $base['student_no'] = $student['student_no'] ?? $user->username;
        } elseif ($user->isStaff()) {
            $user->loadMissing('staff');
            $base['staff_profile'] = [
                'subject' => $user->staff?->subject,
                'stage_label' => $user->staff?->stage_label,
                'bio' => $user->staff?->bio,
                'hire_date' => $user->staff?->hire_date?->toDateString(),
            ];
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicInstituteConfig(): array
    {
        $inst = Eyal::institute();

        return [
            'name' => $inst['name'] ?? 'معهد آيلا التعليمي',
            'address' => $inst['address'] ?? '',
            'phone' => $inst['phone'] ?? '',
            'whatsapp' => $inst['whatsapp'] ?? '',
            'whatsapp_intl' => $inst['whatsapp_intl'] ?? '',
            'work_hours' => $inst['work_hours'] ?? '',
            'announcement' => $inst['announcement'] ?? '',
            'announcement_bar' => (bool) ($inst['announcement_bar'] ?? true),
            'primary_color' => $inst['primary_color'] ?? '#0B3A63',
            'secondary_color' => $inst['secondary_color'] ?? '#1F5C99',
            'features' => [
                'staff_enabled' => (bool) ($inst['staff_enabled'] ?? true),
                'parent_enabled' => (bool) ($inst['parent_enabled'] ?? true),
                'student_enabled' => (bool) ($inst['student_enabled'] ?? true),
                'registrations_open' => (bool) ($inst['registrations_open'] ?? true),
                'chat_enabled' => (bool) ($inst['chat_enabled'] ?? true),
                'chat_parent_teacher_enabled' => (bool) ($inst['chat_parent_teacher_enabled'] ?? true),
                'chat_parent_admin_enabled' => (bool) ($inst['chat_parent_admin_enabled'] ?? true),
                'chat_parents_group_enabled' => (bool) ($inst['chat_parents_group_enabled'] ?? true),
                'chat_staff_group_enabled' => (bool) ($inst['chat_staff_group_enabled'] ?? true),
            ],
        ];
    }
}
