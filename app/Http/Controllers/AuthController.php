<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->homeFor(Auth::user()));
        }

        return view('public.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'يرجى إدخال اسم المستخدم أو رقم الطالب.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
        ]);

        $username = trim((string) $request->input('username'));
        $password = (string) $request->input('password');

        $throttleKey = 'login:'.Str::transliterate(Str::lower($username).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => 'محاولات تسجيل دخول كثيرة. يرجى المحاولة بعد '.$seconds.' ثانية.',
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $credentials = [
            'username' => $username,
            'password' => $password,
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = User::where('username', $username)->first();

            $message = $user && ! $user->is_active
                ? 'هذا الحساب غير مفعّل. يرجى مراجعة إدارة المعهد.'
                : 'اسم المستخدم أو كلمة المرور غير صحيحة.';

            throw ValidationException::withMessages(['username' => $message]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $request->session()->put('eyal_user', $user->toEyalArray());

        return redirect($this->homeFor($user));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->forget('eyal_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'تم تسجيل الخروج بنجاح.');
    }

    private function homeFor(User $user): string
    {
        return match ($user->role) {
            'admin' => '/admin',
            'staff' => '/teacher',
            'parent' => '/parent',
            default => '/student',
        };
    }
}
