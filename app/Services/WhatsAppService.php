<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;
use App\Support\Eyal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * تنسيق رقم الهاتف إلى الصيغة الدولية (مثال: 07701234567 -> 9647701234567).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // أرقام العراق المحلية تبدأ بـ 07 (11 رقم)
        if (str_starts_with($digits, '07') && strlen($digits) === 11) {
            $digits = '964'.substr($digits, 1);
        } elseif (str_starts_with($digits, '7') && strlen($digits) === 10) {
            $digits = '964'.$digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * إرسال رسالة نصية عبر بوابة WhatsApp API الخاصة بالمعهد.
     *
     * @return array{ok: bool, status: int, message: string, data?: mixed}
     */
    public static function sendText(?string $phone, string $message, ?string $idempotencyKey = null, bool $force = false): array
    {
        try {
            $institute = Eyal::institute();
            if (! $force && empty($institute['wa_api_enabled'])) {
                return ['ok' => false, 'status' => 0, 'message' => 'بوابة الواتساب غير مفعلة'];
            }

            $baseUrl = rtrim((string) ($institute['wa_api_url'] ?? 'https://whtsappapi.almoq3.com/api/v1'), '/');
            $apiKey = trim((string) Setting::get('wa.api_key', ''));
            if ($baseUrl === '' || $apiKey === '') {
                return ['ok' => false, 'status' => 0, 'message' => 'مفتاح WhatsApp API Key غير مضبوط'];
            }

            $to = self::normalizePhone($phone);
            if (! $to) {
                return ['ok' => false, 'status' => 422, 'message' => 'رقم الهاتف غير صالح'];
            }

            $headers = [
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ];
            if ($idempotencyKey) {
                $headers['Idempotency-Key'] = mb_substr($idempotencyKey, 0, 120);
            }

            $response = Http::withHeaders($headers)
                ->timeout(6)
                ->post($baseUrl.'/send/text', [
                    'to' => $to,
                    'message' => trim($message),
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'status' => $response->status(),
                    'message' => (string) ($response->json('message') ?? 'تمت إضافة الرسالة لطابور الإرسال'),
                    'data' => $response->json('data'),
                ];
            }

            return [
                'ok' => false,
                'status' => $response->status(),
                'message' => (string) ($response->json('error') ?? $response->json('message') ?? 'فشل الإرسال'),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp API sendText failed: '.$e->getMessage());

            return ['ok' => false, 'status' => 500, 'message' => 'تعذر الاتصال ببوابة الواتساب'];
        }
    }

    /**
     * إرسال رسالة واتساب لأولياء أمور الطالب (أو الطالب نفسه إن توفر رقمه).
     */
    public static function sendToStudentAndParents(Student $student, string $message, ?string $idempotencyPrefix = null): int
    {
        $student->loadMissing('parents:id,phone');

        $phones = collect([
            $student->guardian_phone,
            $student->phone,
        ])
            ->merge($student->parents->pluck('phone'))
            ->map(fn ($p) => self::normalizePhone($p))
            ->filter()
            ->unique()
            ->values();

        $sent = 0;
        foreach ($phones as $idx => $phone) {
            $idem = $idempotencyPrefix ? $idempotencyPrefix.'-'.$phone : null;
            $res = self::sendText($phone, $message, $idem);
            if ($res['ok']) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * فحص حالة اتصال جلسة الواتساب في البوابة.
     *
     * @return array{ok: bool, connected: bool, status_text: string, raw?: mixed}
     */
    public static function status(): array
    {
        try {
            $institute = Eyal::institute();
            $baseUrl = rtrim((string) ($institute['wa_api_url'] ?? 'https://whtsappapi.almoq3.com/api/v1'), '/');
            $apiKey = trim((string) Setting::get('wa.api_key', ''));

            if ($baseUrl === '' || $apiKey === '') {
                return ['ok' => false, 'connected' => false, 'status_text' => 'يرجى إدخال API Key وحفظه أولاً'];
            }

            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(6)->get($baseUrl.'/status');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'connected' => false,
                    'status_text' => (string) ($response->json('message') ?? $response->json('error') ?? 'فشل التحقق من الحالة ('.$response->status().')'),
                ];
            }

            $data = $response->json();
            $rawStatus = strtolower((string) ($data['status'] ?? $data['data']['status'] ?? ''));
            $connected = in_array($rawStatus, ['connected', 'open', 'ready', 'authenticated'], true)
                || ($data['connected'] ?? false) === true;

            return [
                'ok' => true,
                'connected' => $connected,
                'status_text' => $connected ? 'متصل وجاهز للإرسال' : ('الحالة: '.($rawStatus ?: 'غير متصل')),
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'connected' => false, 'status_text' => 'تعذر الاتصال بالبوابة'];
        }
    }

    /**
     * جلب رمز QR Code لتسجيل دخول الواتساب عبر X-API-Key.
     *
     * @return array{ok: bool, status: string, qr_base64?: string, phone_number?: string, message?: string}
     */
    public static function getQr(): array
    {
        try {
            $institute = Eyal::institute();
            $baseUrl = rtrim((string) ($institute['wa_api_url'] ?? 'https://whtsappapi.almoq3.com/api/v1'), '/');
            $apiKey = trim((string) Setting::get('wa.api_key', ''));

            if ($baseUrl === '' || $apiKey === '') {
                return ['ok' => false, 'status' => 'unconfigured', 'message' => 'يرجى إدخال وحفظ X-API-Key أولاً'];
            }

            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get($baseUrl.'/qr');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => (string) ($response->json('error') ?? $response->json('message') ?? 'تعذر جلب رمز QR'),
                ];
            }

            $data = $response->json() ?? [];

            return [
                'ok' => true,
                'status' => (string) ($data['status'] ?? 'unknown'),
                'qr_base64' => (string) ($data['qr_base64'] ?? ''),
                'phone_number' => (string) ($data['phone_number'] ?? ''),
                'project_name' => (string) ($data['project_name'] ?? ''),
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'status' => 'error', 'message' => 'تعذر الاتصال ببوابة الواتساب'];
        }
    }

    /**
     * قطع اتصال جلسة الواتساب عبر X-API-Key.
     *
     * @return array{ok: bool, message: string}
     */
    public static function logout(): array
    {
        try {
            $institute = Eyal::institute();
            $baseUrl = rtrim((string) ($institute['wa_api_url'] ?? 'https://whtsappapi.almoq3.com/api/v1'), '/');
            $apiKey = trim((string) Setting::get('wa.api_key', ''));

            if ($baseUrl === '' || $apiKey === '') {
                return ['ok' => false, 'message' => 'مفتاح API غير مضبوط'];
            }

            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(8)->post($baseUrl.'/logout');

            return [
                'ok' => $response->successful(),
                'message' => $response->successful() ? 'تم تسجيل الخروج وقطع الاتصال بنجاح' : 'تعذر قطع الاتصال',
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'تعذر الاتصال ببوابة الواتساب'];
        }
    }

    /**
     * جلب قائمة المجموعات وجهات الاتصال المتزامنة من الواتساب.
     *
     * @return array{ok: bool, contacts: array<int, array<string, mixed>>, message?: string}
     */
    public static function listContacts(): array
    {
        try {
            $institute = Eyal::institute();
            $baseUrl = rtrim((string) ($institute['wa_api_url'] ?? 'https://whtsappapi.almoq3.com/api/v1'), '/');
            $apiKey = trim((string) Setting::get('wa.api_key', ''));

            if ($baseUrl === '' || $apiKey === '') {
                return ['ok' => false, 'contacts' => [], 'message' => 'يرجى إدخال وحفظ X-API-Key أولاً'];
            }

            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get($baseUrl.'/contacts');

            if (! $response->successful()) {
                return ['ok' => false, 'contacts' => [], 'message' => 'تعذر جلب جهات الاتصال والمجموعات'];
            }

            $rows = $response->json('data') ?? [];

            return [
                'ok' => true,
                'contacts' => is_array($rows) ? $rows : [],
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'contacts' => [], 'message' => 'تعذر الاتصال ببوابة الواتساب'];
        }
    }

    /**
     * التحقق من توقيع الويب هوك (X-Webhook-Signature: sha256=...) باستخدام HMAC-SHA256.
     */
    public static function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = trim((string) Setting::get('wa.webhook_secret', ''));
        if ($secret === '') {
            return true;
        }

        if (! $signatureHeader) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, trim($signatureHeader));
    }
}
