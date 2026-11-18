<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use App\Services\FcmService;
use App\Support\Eyal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:web,android,ios'],
            'device_label' => ['nullable', 'string', 'max:120'],
        ]);

        Eyal::ensurePushTables();

        $userId = (int) $request->user()->id;

        PushToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $userId,
                'platform' => $validated['platform'] ?? 'web',
                'device_label' => $validated['device_label'] ?? mb_substr((string) $request->userAgent(), 0, 120),
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        Eyal::ensurePushTables();

        PushToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request): JsonResponse
    {
        $institute = Eyal::institute();
        if (empty($institute['firebase_client_configured']) || empty($institute['firebase_has_service_account'])) {
            return response()->json([
                'ok' => false,
                'message' => 'يرجى إكمال مفاتيح Firebase وملف Service Account JSON وحفظها أولاً.',
            ], 422);
        }

        $sent = FcmService::sendToUsers(
            [$request->user()->id],
            'اختبار إشعارات '.$institute['short_name'],
            'تم ربط إشعارات Firebase بنجاح وتصل الآن إلى جهازك.',
            '/admin/settings',
            ['type' => 'test', 'tag' => 'eyal-test']
        );

        return response()->json([
            'ok' => $sent > 0,
            'sent' => $sent,
            'message' => $sent > 0
                ? 'تم إرسال الإشعار التجريبي بنجاح إلى '.$sent.' جهاز.'
                : 'لم يتم العثور على جهاز مشترك لحسابك بعد — فعّل إذن الإشعارات من المتصفح أولاً.',
        ]);
    }
}
