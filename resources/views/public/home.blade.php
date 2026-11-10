@extends('layouts.public')

@section('title', 'الرئيسية')

@section('content')
    @php
        $sections = [
            ['/register', 'تسجيل طالب', 'graduation-cap', 'bg-blue-50', 'text-blue-600'],
            ['/stages', 'المراحل الدراسية', 'users', 'bg-indigo-50', 'text-indigo-600'],
            ['/courses', 'الدورات', 'book-open', 'bg-emerald-50', 'text-emerald-600'],
            ['/offers', 'العروض والأسعار', 'tag', 'bg-rose-50', 'text-rose-600'],
            ['/schedule', 'الجدول الدراسي', 'calendar-days', 'bg-amber-50', 'text-amber-600'],
            ['/announcements', 'الإعلانات', 'megaphone', 'bg-purple-50', 'text-purple-600'],
            ['/results', 'نتائج الطلاب', 'clipboard-list', 'bg-sky-50', 'text-sky-600'],
            ['/homework', 'الواجبات', 'list-todo', 'bg-orange-50', 'text-orange-600'],
            ['/parent', 'حساب ولي الأمر', 'circle-user', 'bg-teal-50', 'text-teal-600'],
            ['/contact', 'تواصل معنا', 'phone', 'bg-rose-50', 'text-rose-600'],
            ['/location', 'موقع المعهد', 'map-pin', 'bg-red-50', 'text-red-600'],
            ['/about', 'عن المعهد', 'info', 'bg-slate-100', 'text-slate-600'],
        ];

        if ($institute['staff_enabled']) {
            $sections[] = ['/staff', 'الكادر التدريسي', 'user-round', 'bg-violet-50', 'text-violet-600'];
        }
        if ($institute['parent_enabled']) {
            $sections[] = ['/parent', 'حساب ولي الأمر', 'circle-user', 'bg-teal-50', 'text-teal-600'];
        }
        if ($institute['student_enabled']) {
            $sections[] = ['/student', 'حساب الطالب', 'graduation-cap', 'bg-indigo-50', 'text-indigo-600'];
        }
    @endphp

    <div class="fade-in space-y-8">
        <section class="relative overflow-hidden rounded-3xl bg-primary p-6 text-white shadow-soft sm:p-10">
            <div class="absolute -left-16 -top-16 h-56 w-56 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-20 -right-10 h-64 w-64 rounded-full bg-white/5"></div>
            <div class="relative flex flex-col items-center gap-4 text-center sm:flex-row sm:text-right">
                <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo h-24 w-24 rounded-2xl bg-white p-2 shadow-lg object-contain" draggable="false">
                <div>
                    <h1 class="text-2xl font-extrabold leading-snug text-white sm:text-3xl">{{ $institute['name'] }}</h1>
                    <p class="mt-1 text-sm opacity-90">{{ $institute['address'] }}</p>
                    <p class="mt-1 text-xs opacity-75">
                        <span>الدوام: </span>{{ $institute['work_hours'] }}
                    </p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2 sm:justify-start">
                        <a class="btn btn-wa" href="{{ url('/register') }}">
                            <x-icon name="graduation-cap" :size="16" /> تسجيل طالب
                        </a>
                        <a href="https://wa.me/{{ $institute['whatsapp_intl'] }}?text={{ urlencode('السلام عليكم، أرغب بالاستفسار عن معهد آيلا التعليمي') }}"
                           target="_blank" rel="noopener" class="btn bg-white/15 text-white hover:bg-white/25">
                            تواصل واتساب
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="card p-3 text-center">
                <div class="text-xl font-extrabold text-primary">{{ $stats['students'] }}</div>
                <div class="text-[11px] font-bold text-primary/60">طالب مسجّل</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-extrabold text-primary">{{ $stats['courses'] }}</div>
                <div class="text-[11px] font-bold text-primary/60">دورة متاحة</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-extrabold text-primary">{{ $stats['stages'] }}</div>
                <div class="text-[11px] font-bold text-primary/60">مرحلة دراسية</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-xl font-extrabold text-primary">{{ $stats['attendance_rate'] }}%</div>
                <div class="text-[11px] font-bold text-primary/60">نسبة الحضور</div>
            </div>
        </section>

        <section>
            <h2 class="mb-3 flex items-center gap-2 text-lg font-extrabold">
                <x-icon name="star" :size="20" class="text-amber-400" />
                <span> أقسام التطبيق</span>
            </h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($sections as [$href, $label, $icon, $bg, $color])
                    <a class="card group flex flex-col items-center gap-2 p-4 transition hover:-translate-y-0.5 hover:shadow-soft" href="{{ url($href) }}">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $bg }} {{ $color }}">
                            <x-icon :name="$icon" :size="22" />
                        </div>
                        <span class="text-center text-xs font-bold text-primary sm:text-sm">{{ $label }}</span>
                        <x-icon name="arrow-left" :size="14" class="text-primary/30 transition group-hover:text-primary" />
                    </a>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-primary/10 bg-white p-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <x-icon name="whatsapp" :size="22" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-extrabold text-primary">هل لديك استفسار؟</div>
                    <div class="text-xs text-primary/60">فريق المعهد جاهز للإجابة على أسئلتك حول التسجيل والدورات.</div>
                </div>
                <a href="https://wa.me/{{ $institute['whatsapp_intl'] }}?text={{ urlencode('السلام عليكم، لدي استفسار حول التسجيل في معهد آيلا التعليمي') }}"
                   target="_blank" rel="noopener" class="btn btn-wa btn-sm">راسلنا الآن</a>
            </div>
        </section>
    </div>
@endsection
