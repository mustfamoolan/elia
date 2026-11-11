<?php

namespace App\Http\Middleware;

use App\Support\Eyal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareInstituteData
{
    /**
     * مشاركة إعدادات المعهد مع واجهات Blade وتخطي طلبات الـ JSON السريعة.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->expectsJson() && ! $request->ajax()) {
            View::share('institute', Eyal::institute());
        }

        return $next($request);
    }
}
