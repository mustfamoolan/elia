@php
    $currentUser = session('eyal_user');
    $unread = $currentUser ? \App\Support\Eyal::unreadNotificationsCount($currentUser) : 0;
    $accountUrl = '/login';
    if ($currentUser) {
        $accountUrl = match ($currentUser['role']) {
            'admin' => '/admin',
            'staff' => '/teacher',
            'parent' => '/parent',
            default => '/student',
        };
    }
    $path = trim(request()->path(), '/');
    $isActive = fn (string $p) => $path === trim($p, '/');
    $navLinks = [
        ['/', 'الرئيسية', 'house'],
        ['/about', 'عن المعهد', 'info'],
        ['/stages', 'المراحل الدراسية', 'layers'],
        ['/courses', 'الدورات', 'book-open'],
        ['/offers', 'العروض والأسعار', 'tag'],
        ['/schedule', 'الجدول الدراسي', 'calendar-days'],
        ['/announcements', 'الإعلانات', 'megaphone'],
        ['/results', 'نتائج الطلاب', 'clipboard-list'],
        ['/homework', 'الواجبات', 'list-todo'],
    ];
    // Add parent link only if parent_enabled is true
    if ($institute['parent_enabled']) {
        $navLinks[] = ['/parent', 'حساب ولي الأمر', 'circle-user'];
    }
    // Add student link only if student_enabled is true
    if ($institute['student_enabled']) {
        $navLinks[] = ['/student', 'حساب الطالب', 'graduation-cap'];
    }
    // Add staff link only if staff_enabled is true
    if ($institute['staff_enabled']) {
        $navLinks[] = ['/staff', 'الكادر التدريسي', 'user-round'];
    }
    if ($currentUser && ($currentUser['role'] ?? '') !== 'student') {
        $navLinks[] = ['/chat', 'المحادثات والدعم', 'message-square'];
    }
    $navLinks[] = ['/notifications', 'الإشعارات', 'bell'];
    $navLinks[] = ['/contact', 'تواصل معنا', 'phone'];
    $navLinks[] = ['/location', 'موقع المعهد', 'map-pin'];
    $navLinks[] = ['/register', 'تسجيل طالب جديد', 'user-plus'];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $institute['primary_color'] }}">
    <meta name="description" content="{{ $institute['name'] }} — {{ $institute['address'] }}. {{ $institute['about']['intro'] }}">
    <title>@yield('title', $institute['name']) — {{ $institute['short_name'] }}</title>

    <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    @stack('styles')
</head>
<body class="bg-cloud text-ink">
<div class="min-h-screen bg-cloud pb-24">
    <header class="sticky top-0 z-40 border-b border-primary/10 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
            <button type="button" class="lg:hidden" data-drawer-open aria-label="القائمة">
                <x-icon name="menu" :size="24" class="text-primary" />
            </button>

            <a class="flex items-center gap-2" href="{{ url('/') }}">
                <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo h-11 w-11 object-contain" draggable="false">
                <div class="leading-tight">
                    <div class="text-sm font-extrabold text-primary sm:text-base">{{ $institute['name'] }}</div>
                    <div class="text-[10px] font-bold text-primary/60">{{ $institute['address'] }}</div>
                </div>
            </a>

            <div class="mr-auto flex items-center gap-2">
                <a class="relative rounded-xl p-2 hover:bg-primary/5" href="{{ url('/notifications') }}" aria-label="الإشعارات">
                    <x-icon name="bell" :size="20" class="text-primary" />
                    @if ($unread > 0)
                        <span class="absolute left-1.5 top-1.5 flex h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white"></span>
                    @endif
                </a>

                @if ($currentUser)
                    <div class="flex items-center gap-1">
                        <a class="btn btn-outline btn-sm" href="{{ url($accountUrl) }}">حسابي</a>
                        <form method="POST" action="{{ url('/logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm" title="خروج">
                                <x-icon name="log-out" :size="14" />
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ url('/login') }}" class="btn btn-dark btn-sm">
                        <x-icon name="log-in" :size="14" />
                        <span>دخول</span>
                    </a>
                @endif
            </div>
        </div>

        @if (!empty($institute['announcement']))
            <div class="bg-primary text-white">
                <div class="mx-auto max-w-5xl px-4 py-1.5 text-center text-xs font-bold">
                    <span>🔔 </span>{{ $institute['announcement'] }}
                </div>
            </div>
        @endif
    </header>

    {{-- قائمة الهاتف الجانبية --}}
    <div class="drawer-overlay" data-drawer-overlay></div>
    <aside class="drawer-panel" data-drawer-panel aria-hidden="true">
        <div class="flex items-center gap-3 border-b border-primary/10 p-4">
            <img src="{{ asset('assets/logo.png') }}" alt="" class="brand-logo h-12 w-12 rounded-xl object-contain">
            <div class="leading-tight">
                <div class="text-sm font-extrabold text-primary">{{ $institute['name'] }}</div>
                <div class="text-[11px] text-primary/60">{{ $institute['address'] }}</div>
            </div>
            <button type="button" class="mr-auto rounded-full p-2 hover:bg-primary/5" data-drawer-close aria-label="إغلاق">
                <x-icon name="x" :size="20" class="text-primary" />
            </button>
        </div>
        <nav class="hide-scrollbar flex-1 space-y-1 overflow-y-auto p-3">
            @foreach ($navLinks as [$href, $label, $icon])
                <a href="{{ url($href) }}" class="drawer-link {{ $isActive($href) ? 'bg-primary/5' : '' }}">
                    <x-icon :name="$icon" :size="18" class="text-primary/70" />
                    <span>{{ $label }}</span>
                    @if ($isActive($href))
                        <span class="mr-auto h-1.5 w-1.5 rounded-full bg-secondary"></span>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="space-y-2 border-t border-primary/10 p-4">
            @if ($currentUser)
                <div class="flex items-center gap-2 rounded-xl bg-primary/5 p-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-xs font-extrabold text-white">
                        {{ mb_substr($currentUser['name'], 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-extrabold text-primary">{{ $currentUser['name'] }}</div>
                        <div class="text-[10px] text-primary/60">{{ $currentUser['role_label'] }}</div>
                    </div>
                </div>
                <a href="{{ url($accountUrl) }}" class="btn btn-outline w-full">حسابي</a>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost w-full">
                        <x-icon name="log-out" :size="16" /> تسجيل الخروج
                    </button>
                </form>
            @else
                <a href="{{ url('/login') }}" class="btn btn-dark w-full">
                    <x-icon name="log-in" :size="16" /> تسجيل الدخول
                </a>
                <a href="{{ url('/register') }}" class="btn btn-outline w-full">تسجيل طالب جديد</a>
            @endif
        </div>
    </aside>

    <main class="mx-auto max-w-5xl px-4 py-6">
        @yield('content')
    </main>

    <div class="bottom-nav fixed bottom-0 left-0 right-0 z-40 border-t border-primary/10 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-around px-2 py-2">
            <a class="flex flex-col items-center gap-0.5 {{ $isActive('/') ? 'text-secondary' : 'text-primary' }}" href="{{ url('/') }}">
                <x-icon name="house" :size="20" />
                <span class="text-[10px] font-bold">الرئيسية</span>
            </a>
            <a href="tel:+{{ $institute['phone_intl'] }}" class="flex flex-col items-center gap-0.5 text-primary">
                <x-icon name="phone" :size="20" />
                <span class="text-[10px] font-bold">اتصال</span>
            </a>
            <a href="https://wa.me/{{ $institute['whatsapp_intl'] }}?text={{ urlencode('السلام عليكم، أرغب بالاستفسار عن معهد آيلا التعليمي') }}" target="_blank" rel="noopener" class="flex flex-col items-center gap-0.5 text-emerald-600">
                <x-icon name="whatsapp" :size="20" />
                <span class="text-[10px] font-bold">واتساب</span>
            </a>
            <a class="flex flex-col items-center gap-0.5 {{ $isActive('/courses') ? 'text-secondary' : 'text-primary' }}" href="{{ url('/courses') }}">
                <x-icon name="book-open" :size="20" />
                <span class="text-[10px] font-bold">الدورات</span>
            </a>
            <a class="flex flex-col items-center gap-0.5 {{ in_array($path, ['student', 'parent', 'login', 'admin']) ? 'text-secondary' : 'text-primary' }}" href="{{ url($accountUrl) }}">
                <x-icon name="circle-user" :size="20" />
                <span class="text-[10px] font-bold">حسابي</span>
            </a>
        </div>
    </div>

    <footer class="mt-10 bg-primary py-8 text-center text-white">
        <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo mx-auto mb-2 h-14 w-14 rounded-full bg-white/10 p-1 object-contain" draggable="false">
        <div class="text-sm font-extrabold">{{ $institute['name'] }}</div>
        <div class="mt-1 text-xs opacity-80">{{ $institute['address'] }}</div>
        <div class="mt-1 text-xs opacity-80">
            <span>هاتف: </span>{{ $institute['phone'] }}<span> – واتساب: </span>{{ $institute['whatsapp'] }}
        </div>
        <div class="mt-3 text-[11px] opacity-60">
            <span>© </span>{{ date('Y') }}<span> {{ $institute['short_name'] }} – جميع الحقوق محفوظة</span>
        </div>
    </footer>
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
