@php
    $currentUser = session('eyal_user', ['name' => 'عضو الكادر', 'role_label' => 'مدرس']);
    $path = trim(request()->path(), '/');
    $teacherNav = [
        ['/teacher', 'لوحة المعلومات', 'layout-dashboard'],
        ['/teacher/courses', 'دوراتي', 'book-open'],
        ['/teacher/students', 'طلابي', 'users'],
        ['/teacher/attendance', 'الحضور', 'calendar-check'],
        ['/teacher/grades', 'الدرجات', 'clipboard-list'],
        ['/teacher/homework', 'الواجبات', 'list-todo'],
        ['/teacher/schedule', 'الجدول', 'calendar-days'],
        ['/teacher/profile', 'الملف الشخصي', 'circle-user'],
    ];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $institute['primary_color'] }}">
    <title>@yield('title', 'بوابة الكادر') — {{ $institute['short_name'] }}</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @stack('styles')
</head>
<body class="bg-cloud text-ink">
<div class="min-h-screen bg-cloud">
    <header class="sticky top-0 z-40 border-b border-primary/10 bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <a href="{{ url('/teacher') }}" class="flex items-center gap-3">
                <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo h-10 w-10 object-contain" draggable="false">
                <div class="leading-tight">
                    <div class="text-sm font-extrabold text-primary">بوابة الكادر التدريسي</div>
                    <div class="text-[11px] text-primary/60">
                        {{ $currentUser['name'] }}<span> (</span>{{ $currentUser['role_label'] }}<span>)</span>
                    </div>
                </div>
            </a>

            <div class="mr-auto flex items-center gap-2">
                <a class="btn btn-outline btn-sm" href="{{ url('/') }}">
                    <x-icon name="house" :size="14" /> الموقع
                </a>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">
                        <x-icon name="log-out" :size="14" />
                        <span> خروج</span>
                    </button>
                </form>
            </div>
        </div>

        <nav class="hide-scrollbar flex gap-1 overflow-x-auto border-t border-primary/5 px-3 py-2">
            @foreach ($teacherNav as [$href, $label, $icon])
                @php
                    $trimmed = trim($href, '/');
                    $active = $path === $trimmed || ($trimmed !== 'teacher' && str_starts_with($path, $trimmed.'/'));
                @endphp
                <a href="{{ url($href) }}"
                   class="flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-2 text-xs font-bold transition {{ $active ? 'bg-primary text-white shadow-soft' : 'text-primary hover:bg-primary/5' }}"
                   @if ($active) aria-current="page" @endif>
                    <x-icon :name="$icon" :size="15" />{{ $label }}
                </a>
            @endforeach
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @yield('content')
    </main>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

@if (session('success'))
    <div data-flash="{{ session('success') }}" data-flash-type="success" hidden></div>
@endif
@if (session('error'))
    <div data-flash="{{ session('error') }}" data-flash-type="danger" hidden></div>
@endif
@if ($errors->any())
    <div data-flash="{{ $errors->first() }}" data-flash-type="danger" hidden></div>
@endif

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
