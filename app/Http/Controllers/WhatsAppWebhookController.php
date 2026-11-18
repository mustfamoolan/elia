<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Student;
use App\Services\GeminiService;
use App\Services\WhatsAppService;
use App\Support\Eyal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');

        if (! WhatsAppService::verifyWebhookSignature($rawBody, $signature)) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        $event = (string) ($request->input('event') ?? $request->header('X-Webhook-Event', ''));
        if ($event !== 'message.received') {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $data = $request->input('data', []);
        $from = (string) ($data['from'] ?? '');
        $fromName = (string) ($data['from_name'] ?? 'مستخدم واتساب');
        $isGroup = (bool) ($data['is_group'] ?? false);
        $text = trim((string) ($data['text'] ?? ''));

        if ($from === '' || $text === '') {
            return response()->json(['ok' => true, 'skipped' => 'empty']);
        }

        $institute = Eyal::institute();
        $targetGroupJid = trim((string) ($institute['wa_ai_group_jid'] ?? ''));

        // 1) معالجة رسائل الكروب/القناة المحددة لقراءة الواجبات بالذكاء الاصطناعي
        if ($isGroup && ! empty($institute['wa_ai_group_enabled']) && $targetGroupJid !== '' && $from === $targetGroupJid) {
            if (GeminiService::isEnabled()) {
                try {
                    $reply = $this->handleGroupHomeworkViaGemini($text, $fromName);
                    if ($reply !== '') {
                        WhatsAppService::sendText($from, $reply, 'wh-grp-'.md5($from.$text.date('YmdHi')));
                    }
                } catch (\Throwable $e) {
                    Log::warning('WhatsApp group AI error: '.$e->getMessage());
                }
            }

            return response()->json(['ok' => true, 'processed' => 'group_homework']);
        }

        // 2) الرد الآلي الذكي على المحادثات الخاصة لأولياء الأمور (إن تم تفعيله)
        if (! $isGroup && ! empty($institute['wa_ai_private_reply']) && GeminiService::isEnabled()) {
            $phoneDigits = preg_replace('/\D+/', '', explode('@', $from)[0]) ?? '';
            $localPhone = str_starts_with($phoneDigits, '964') ? '0'.substr($phoneDigits, 3) : $phoneDigits;

            $student = Student::query()
                ->with(['stage:id,name', 'courses:id,name'])
                ->where('guardian_phone', 'like', '%'.substr($phoneDigits, -9))
                ->orWhere('phone', 'like', '%'.substr($phoneDigits, -9))
                ->first();

            if ($student) {
                $reply = $this->handlePrivateParentQuery($text, $student, $fromName);
                if ($reply !== '') {
                    WhatsAppService::sendText($from, $reply, 'wh-priv-'.md5($from.$text.date('YmdHi')));
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function handleGroupHomeworkViaGemini(string $text, string $senderName): string
    {
        $apiKey = GeminiService::apiKey();
        $model = GeminiService::model();
        if ($apiKey === '') {
            return '';
        }

        $prompt = "أنت مساعد ذكي لمعهد آيلا التعليمي. وصلت الرسالة التالية في مجموعة الواتساب من ({$senderName}):\n\"{$text}\"\n\n"
            ."إذا كانت الرسالة تحتوي على واجب مدرسي أو طلب توضيح واجب، لخّص الواجب بشكل مرتب مع ذكر المادة إن وجدت في سطرين باللغة العربية. وإذا كانت مجرد دردشة عادية لا تخص الواجبات فأجب بـ: IGNORE";

        $res = Http::timeout(10)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            ['contents' => [['parts' => [['text' => $prompt]]]]]
        );

        if (! $res->successful()) {
            return '';
        }

        $out = trim((string) ($res->json('candidates.0.content.parts.0.text') ?? ''));

        return ($out === '' || strtoupper($out) === 'IGNORE') ? '' : $out;
    }

    private function handlePrivateParentQuery(string $text, Student $student, string $senderName): string
    {
        $apiKey = GeminiService::apiKey();
        $model = GeminiService::model();
        if ($apiKey === '') {
            return '';
        }

        $data = Eyal::studentByNo($student->student_no) ?? [];
        $rate = $data['attendance'] ?? 0;
        $fee = number_format((int) ($data['fee'] ?? 0));
        $paid = number_format((int) ($data['paid'] ?? 0));
        $rem = number_format(max(0, (int) ($data['fee'] ?? 0) - (int) ($data['paid'] ?? 0)));
        $stageName = $data['stage_name'] ?? 'غير محدد';

        $prompt = "أنت مساعد ذكي رسمي لمعهد آيلا التعليمي ترد على ولي الأمر ({$senderName}) عبر واتساب بأسلوب مهذب ومختصر.\n"
            ."بيانات الطالب: الاسم={$student->name}، المرحلة={$stageName}، نسبة الحضور={$rate}%، الاشتراك={$fee} د.ع، المدفوع={$paid} د.ع، المتبقي={$rem} د.ع.\n"
            ."رسالة ولي الأمر: \"{$text}\"\n"
            .'أجب على استفساره بدقة واختصار من هذه البيانات فقط.';

        $res = Http::timeout(10)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            ['contents' => [['parts' => [['text' => $prompt]]]]]
        );

        return $res->successful()
            ? trim((string) ($res->json('candidates.0.content.parts.0.text') ?? ''))
            : '';
    }
}
