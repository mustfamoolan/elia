<?php

namespace App\Services;

use App\Models\PushToken;
use App\Models\Setting;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * إرسال إشعار Push عبر Firebase Cloud Messaging HTTP v1 لمجموعة من المستخدمين.
     *
     * @param  iterable<int, User|int>  $users
     * @param  array<string, string>  $data
     */
    public static function sendToUsers(iterable $users, string $title, string $body, string $url = '/', array $data = []): int
    {
        try {
            $institute = Eyal::institute();
            if (empty($institute['push_enabled'])) {
                return 0;
            }

            $userIds = collect($users)
                ->map(fn ($u) => $u instanceof User ? (int) $u->id : (int) $u)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($userIds)) {
                return 0;
            }

            Eyal::ensurePushTables();

            $tokens = PushToken::query()
                ->whereIn('user_id', $userIds)
                ->pluck('token')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($tokens)) {
                return 0;
            }

            $serviceAccount = self::getServiceAccount();
            if (! $serviceAccount) {
                return 0;
            }

            $accessToken = self::getAccessToken($serviceAccount);
            if (! $accessToken) {
                return 0;
            }

            $projectId = (string) ($serviceAccount['project_id'] ?? $institute['firebase_project_id'] ?? '');
            if ($projectId === '') {
                return 0;
            }

            $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
            $absoluteUrl = str_starts_with($url, 'http') ? $url : url($url);
            $iconUrl = url('/assets/logo.png');

            $payloadData = array_map(static fn ($v) => (string) $v, array_merge([
                'url' => $absoluteUrl,
                'title' => $title,
                'body' => $body,
            ], $data));

            $sent = 0;
            $staleTokens = [];

            foreach ($tokens as $token) {
                $message = [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $payloadData,
                        'webpush' => [
                            'headers' => [
                                'Urgency' => 'high',
                            ],
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                                'icon' => $iconUrl,
                                'badge' => $iconUrl,
                                'dir' => 'rtl',
                                'lang' => 'ar',
                                'renotify' => true,
                                'tag' => (string) ($data['tag'] ?? 'eyal-notif'),
                            ],
                            'fcm_options' => [
                                'link' => $absoluteUrl,
                            ],
                        ],
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                                'sound' => 'default',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ],
                ];

                $response = Http::withToken($accessToken)
                    ->timeout(6)
                    ->post($endpoint, $message);

                if ($response->successful()) {
                    $sent++;
                    continue;
                }

                $errorCode = (string) ($response->json('error.details.0.errorCode')
                    ?? $response->json('error.status')
                    ?? '');

                if (in_array($errorCode, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
                    $staleTokens[] = $token;
                }
            }

            if (! empty($staleTokens)) {
                PushToken::query()->whereIn('token', $staleTokens)->delete();
            }

            return $sent;
        } catch (\Throwable $e) {
            Log::warning('FCM send failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * إرسال إشعار لجميع حسابات الإدارة الفعالة.
     *
     * @param  array<string, string>  $data
     */
    public static function sendToAdmins(string $title, string $body, string $url = '/admin', array $data = []): int
    {
        $adminIds = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        return self::sendToUsers($adminIds, $title, $body, $url, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function getServiceAccount(): ?array
    {
        $raw = (string) Setting::get('firebase.service_account_json', '');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * توليد Google OAuth2 Access Token باستخدام RS256 JWT (بدون مكتبات خارجية).
     *
     * @param  array<string, mixed>  $sa
     */
    private static function getAccessToken(array $sa): ?string
    {
        $cacheKey = 'eyal.fcm.oauth_token.'.md5((string) $sa['client_email']);

        try {
            $cached = Cache::store('file')->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable) {
            // ignore
        }

        $now = time();
        $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::base64UrlEncode(json_encode([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsigned = $header.'.'.$claims;
        $signature = '';
        $privateKey = str_replace('\n', "\n", (string) $sa['private_key']);

        if (! @openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = $unsigned.'.'.self::base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(6)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $token = (string) $response->json('access_token', '');
        if ($token === '') {
            return null;
        }

        try {
            Cache::store('file')->put($cacheKey, $token, 3000);
        } catch (\Throwable) {
            // ignore
        }

        return $token;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
