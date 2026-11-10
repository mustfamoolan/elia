@extends('layouts.public')

@section('title', 'الدورات')

@section('content')
    @php
        $palette = [
            'blue' => ['bg-blue-50', 'text-blue-600'],
            'violet' => ['bg-violet-50', 'text-violet-600'],
            'emerald' => ['bg-emerald-50', 'text-emerald-600'],
            'sky' => ['bg-sky-50', 'text-sky-600'],
            'amber' => ['bg-amber-50', 'text-amber-600'],
            'rose' => ['bg-rose-50', 'text-rose-600'],
        ];
    @endphp

    <div class="fade-in">
        <h1 class="mb-1 text-2xl font-extrabold">الدورات</h1>
        <p class="mb-4 text-sm text-primary/70">جميع الدورات المتوفرة في المعهد – سجّل الآن عبر واتساب</p>

        <div class="hide-scrollbar mb-4 flex gap-2 overflow-x-auto pb-1">
            <a href="{{ url('/courses') }}" class="chip whitespace-nowrap {{ ! $activeStage ? 'bg-primary text-white' : 'bg-white text-primary' }}">الكل</a>
            @foreach ($stages as $stage)
                <a href="{{ url('/courses?stage='.$stage['id']) }}"
                   class="chip whitespace-nowrap {{ $activeStage === $stage['id'] ? 'bg-primary text-white' : 'bg-white text-primary' }}">{{ $stage['name'] }}</a>
            @endforeach
        </div>

        @if ($courses->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                <p class="text-sm font-bold text-primary/60">لا توجد دورات متاحة حالياً</p>
                <a href="{{ url('/contact') }}" class="btn btn-outline btn-sm mt-2">تواصل مع الإدارة</a>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($courses as $course)
                    @php [$bg, $color] = $palette[$course['color']] ?? ['bg-primary/5', 'text-primary']; @endphp
                    <article class="card course-card flex flex-col p-5">
                        <div class="mb-3 flex items-start gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $bg }} {{ $color }}">
                                <x-icon name="book-open" :size="22" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-extrabold text-primary">{{ $course['name'] }}</h2>
                                <div class="text-xs text-primary/60">{{ $course['teacher'] }} • {{ $course['stage_name'] }}</div>
                            </div>
                            <span class="chip {{ $bg }} {{ $color }} shrink-0">{{ $course['subject'] }}</span>
                        </div>

                        <p class="mb-3 text-xs leading-relaxed text-primary/70">{{ $course['description'] }}</p>

                        <div class="space-y-1.5 text-xs text-primary/70">
                            <div class="flex items-center gap-2">
                                <x-icon name="calendar-days" :size="14" class="text-primary/40" />
                                <span>{{ $course['schedule'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="users" :size="14" class="text-primary/40" />
                                <span>{{ $course['enrolled'] }} من {{ $course['seats'] }} مقعد</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="progress">
                                <span style="width: {{ (int) round(($course['enrolled'] / max(1, $course['seats'])) * 100) }}%"></span>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-2 border-t border-primary/5 pt-3">
                            <div class="text-sm font-extrabold text-primary">{{ number_format($course['price']) }} <span class="text-[10px] font-bold text-primary/50">د.ع</span></div>
                            <div class="mr-auto flex items-center gap-2">
                                <a href="{{ url('/register') }}" class="btn btn-outline btn-sm">تسجيل</a>
                                <a href="https://wa.me/{{ $institute['whatsapp_intl'] }}?text={{ urlencode('السلام عليكم، أرغب بالاستفسار عن '.$course['name']) }}"
                                   target="_blank" rel="noopener" class="btn btn-wa btn-sm">
                                    <x-icon name="whatsapp" :size="14" /> واتساب
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
