<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncEyalSession
{
    /**
     * إبقاء جلسة الواجهات الحالية (eyal_user) متزامنة مع مستخدم Laravel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $sessionUser = $request->session()->get('eyal_user');

            $needsSync = ! is_array($sessionUser)
                || ($sessionUser['id'] ?? null) !== $user->id
                || ($sessionUser['role'] ?? null) !== $user->role
                || ($sessionUser['name'] ?? null) !== $user->name
                || ($sessionUser['username'] ?? null) !== $user->username;

            if ($needsSync) {
                $request->session()->put('eyal_user', $user->toEyalArray());
            }
        }

        return $next($request);
    }
}
