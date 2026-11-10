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
     * مشاركة إعدادات المعهد مع كل واجهات Blade.
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('institute', Eyal::institute());

        return $next($request);
    }
}
