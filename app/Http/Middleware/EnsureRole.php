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

        if (! $user) {
            return $request->expectsJson()
                ? abort(401)
                : redirect()->guest(route('login'))->with('error', 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'هذا الحساب غير مفعّل. يرجى مراجعة إدارة المعهد.');
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}
