@extends('layouts.public')

@section('title', 'الإعلانات')

@section('content')
    @php
        $colors = [
            'blue' => ['bg-blue-50', 'text-blue-600'],
            'amber' => ['bg-amber-50', 'text-amber-600'],
            'slate' => ['bg-slate-100', 'text-slate-600'],
            'emerald' => ['bg-emerald-50', 'text-emerald-600'],
        ];
    @endphp

    <div class="fade-in">
        <h1 class="mb-1 text-2xl font-extrabold">إعلانات {{ $institute['short_name'] }}</h1>
        <p class="mb-4 text-sm text-primary/70">آخر الأخبار والتنبيهات والإعلانات الرسمية</p>

        @if ($announcements->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                <p class="text-sm font-bold text-primary/60">لا توجد إعلانات منشورة حالياً</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($announcements as $item)
                    @php [$bg, $color] = $colors[$item['color']] ?? ['bg-primary/5', 'text-primary']; @endphp
                    <article class="card p-5">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="chip {{ $bg }} {{ $color }}">
                                <x-icon name="megaphone" :size="13" /> {{ $item['category'] }}
                            </span>
                            @if ($item['pinned'])
                                <span class="chip bg-rose-50 text-rose-600">
                                    <x-icon name="star" :size="12" /> مثبّت
                                </span>
                            @endif
                            <span class="mr-auto flex items-center gap-1 text-[11px] font-bold text-primary/50">
                                <x-icon name="calendar" :size="13" />
                                {{ \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d F Y') }}
                            </span>
                        </div>
                        <h2 class="text-base font-extrabold text-primary">{{ $item['title'] }}</h2>
                        <p class="mt-1 text-sm leading-relaxed text-primary/70">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
