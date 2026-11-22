<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->bearerToken()
            ?: (string) $request->header('X-API-Token', '')
            ?: (string) $request->query('api_token', '');

        $apiToken = ApiToken::findValidToken($rawToken);

        if (! $apiToken || ! $apiToken->user) {
            return response()->json([
                'status' => 'error',
                'message' => 'يرجى تسجيل الدخول للمتابعة (رمز الجلسة غير صالح أو منتهي).',
            ], 401);
        }

        // تحديث وقت آخر استخدام مرة كل 5 دقائق لتجنب الضغط على قاعدة البيانات
        if (! $apiToken->last_used_at || $apiToken->last_used_at->lt(now()->subMinutes(5))) {
            $apiToken->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $user = $apiToken->user;
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('current_api_token', $apiToken);

        return $next($request);
    }
}
