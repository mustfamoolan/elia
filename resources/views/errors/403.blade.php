@extends('layouts.public')

@section('title', 'غير مصرح')

@section('content')
    <div class="fade-in mx-auto max-w-md">
        <div class="card p-8 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                <x-icon name="shield" :size="32" />
            </div>
            <h1 class="text-xl font-extrabold">لا تملك صلاحية الوصول</h1>
            <p class="mt-2 text-xs font-bold text-primary/60">
                هذه الصفحة تخص دوراً آخر. يرجى العودة إلى لوحتك الخاصة أو تسجيل الدخول بحساب مناسب.
            </p>

            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                @auth
                    <a href="{{ auth()->user()->role === 'admin' ? url('/admin') : (auth()->user()->role === 'staff' ? url('/teacher') : (auth()->user()->role === 'parent' ? url('/parent') : url('/student'))) }}" class="btn btn-dark btn-sm">
                        <x-icon name="layout-dashboard" :size="15" /> لوحتي
                    </a>
                @endauth
                <a href="{{ url('/') }}" class="btn btn-outline btn-sm">
                    <x-icon name="house" :size="15" /> الرئيسية
                </a>
            </div>
        </div>
    </div>
@endsection
