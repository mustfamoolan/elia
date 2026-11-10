@extends('layouts.public')

@section('title', 'تسجيل الدخول')

@section('content')
    <div class="fade-in mx-auto max-w-sm">
        <div class="card p-6">
            <div class="mb-4 flex flex-col items-center gap-2 text-center">
                <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo h-20 w-20 rounded-2xl bg-primary/5 p-2 object-contain" draggable="false">
                <h1 class="text-xl font-extrabold">تسجيل الدخول</h1>
                <p class="text-xs text-primary/60">حساب المدير • الموظف • المدرس • الطالب • ولي الأمر</p>
            </div>

            @if (session('error'))
                <div class="mb-3 rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-700">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-3 rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ url('/login') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="label" for="username">اسم المستخدم / رقم الطالب</label>
                    <input id="username" name="username" class="input" placeholder="اسم المستخدم أو رقم الطالب أو الهاتف"
                           autocomplete="username" autocapitalize="none" value="{{ old('username') }}" required>
                </div>
                <div>
                    <label class="label" for="password">كلمة المرور</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" class="input pl-10" placeholder="••••••••"
                               autocomplete="current-password" required>
                        <button type="button" class="absolute left-3 top-1/2 -translate-y-1/2 text-primary/40 hover:text-primary"
                                data-toggle-password="password" aria-label="إظهار كلمة المرور">
                            <x-icon name="eye" :size="18" />
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-dark w-full">
                    <x-icon name="log-in" :size="16" /> دخول
                </button>
            </form>

            <div class="mt-4 flex items-center justify-between text-[11px] font-bold">
                <a href="{{ url('/register') }}" class="text-secondary hover:underline">تسجيل طالب جديد</a>
                <a href="{{ url('/') }}" class="text-primary/50 hover:text-primary">العودة للرئيسية</a>
            </div>
        </div>
    </div>
@endsection
