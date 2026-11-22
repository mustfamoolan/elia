<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $isJson = $request->is('api/*') || $request->expectsJson();

        if (! $user) {
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'يجب تسجيل الدخول للوصول إلى هذه الخدمة.',
                ], 401);
            }

            return redirect()->guest(route('login'))->with('error', 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.');
        }

        if (! $user->is_active) {
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'هذا الحساب غير مفعّل. يرجى مراجعة إدارة المعهد.',
                ], 403);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'هذا الحساب غير مفعّل. يرجى مراجعة إدارة المعهد.');
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            if ($isJson) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لا تملك صلاحية الوصول إلى هذا القسم.',
                    'required_roles' => $roles,
                    'current_role' => $user->role,
                ], 403);
            }

            abort(403, 'لا تملك صلاحية الوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}
