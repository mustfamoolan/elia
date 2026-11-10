@extends('layouts.public')

@section('title', 'الإشعارات')

@section('content')
    @php $user = session('eyal_user'); @endphp

    <div class="fade-in mx-auto max-w-2xl">
        <h1 class="mb-1 text-2xl font-extrabold">الإشعارات</h1>
        <p class="mb-4 text-sm text-primary/70">الإشعارات الموجّهة إليك من إدارة المعهد</p>

        @if (! $user)
            <div class="card mb-4 flex items-center gap-3 border-r-4 border-r-secondary p-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                    <x-icon name="bell" :size="20" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-extrabold text-primary">سجّل الدخول لعرض كل إشعاراتك</div>
                    <div class="text-[11px] text-primary/60">الإشعارات الخاصة بالطلاب وأولياء الأمور تظهر بعد تسجيل الدخول.</div>
                </div>
                <a href="{{ url('/login') }}" class="btn btn-outline btn-sm">دخول</a>
            </div>
        @endif

        @if ($notifications->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                <p class="text-sm font-bold text-primary/60">لا توجد إشعارات حالياً</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($notifications as $item)
                    @php
                        $colors = [
                            'amber' => ['bg-amber-50', 'text-amber-600'],
                            'emerald' => ['bg-emerald-50', 'text-emerald-600'],
                            'blue' => ['bg-blue-50', 'text-blue-600'],
                        ];
                        [$bg, $color] = $colors[$item['color']] ?? ['bg-primary/5', 'text-primary'];
                    @endphp
                    <article class="card flex items-start gap-3 p-4">
                        <div class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $bg }} {{ $color }}">
                            <x-icon name="bell" :size="20" />
                            @if (! $item['read'])
                                <span class="absolute -left-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white"></span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-extrabold text-primary">{{ $item['title'] }}</h2>
                                <span class="chip bg-primary/5 text-primary/60">{{ $item['audience'] }}</span>
                            </div>
                            <p class="mt-1 text-xs leading-relaxed text-primary/70">{{ $item['body'] }}</p>
                            <div class="mt-2 flex items-center gap-1 text-[10px] font-bold text-primary/40">
                                <x-icon name="clock" :size="12" />
                                {{ \Illuminate\Support\Carbon::parse($item['date'])->format('Y/m/d') }}
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
