@php
    $currentUser = session('eyal_user', ['name' => 'مدير المعهد', 'role_label' => 'مدير']);
    $path = trim(request()->path(), '/');
    $adminNav = [
        ['/admin', 'لوحة المعلومات', 'layout-dashboard'],
        ['/admin/reports', 'التقارير', 'chart-column'],
        ['/admin/students', 'الطلاب', 'users'],
        ['/admin/parents', 'أولياء الأمور', 'circle-user'],
        ['/admin/registrations', 'التسجيلات', 'file-text'],
        ['/admin/stages', 'المراحل', 'layers'],
        ['/admin/courses', 'الدورات', 'book-open'],
        ['/admin/staff', 'الكادر', 'user-round'],
        ['/admin/attendance', 'الحضور', 'calendar-check'],
        ['/admin/grades', 'الدرجات', 'clipboard-list'],
        ['/admin/homework', 'الواجبات', 'list-todo'],
        ['/admin/payments', 'الحسابات', 'wallet'],
        ['/admin/announcements', 'الإعلانات', 'megaphone'],
        ['/admin/offers', 'العروض', 'tag'],
        ['/admin/schedule', 'الجدول', 'calendar-days'],
        ['/admin/notifications', 'الإشعارات', 'bell'],
        ['/chat', 'المحادثات', 'message-square'],
        ['/admin/settings', 'الإعدادات', 'settings'],
    ];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $institute['primary_color'] }}">
    <title>@yield('title', 'لوحة الإدارة') — {{ $institute['short_name'] }}</title>

    @php $assetVer = \App\Support\Eyal::assetVersion(); @endphp
    <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ $assetVer }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}?v={{ $assetVer }}">
    @stack('styles')
</head>
<body class="bg-cloud text-ink">
<div class="min-h-screen bg-cloud">
    <header class="sticky top-0 z-40 border-b border-primary/10 bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <a href="{{ url('/admin') }}" class="flex items-center gap-3">
                <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo h-10 w-10 object-contain" draggable="false">
                <div class="leading-tight">
                    <div class="text-sm font-extrabold text-primary">لوحة إدارة المعهد</div>
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
            @foreach ($adminNav as [$href, $label, $icon])
                @php $active = $path === trim($href, '/'); @endphp
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

<script src="{{ asset('js/app.js') }}?v={{ $assetVer }}"></script>
@stack('scripts')
</body>
</html>
