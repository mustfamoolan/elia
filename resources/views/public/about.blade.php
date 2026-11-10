@extends('layouts.public')

@section('title', 'عن المعهد')

@section('content')
    @php
        $about = $institute['about'];
        $blocks = [
            ['أهداف المعهد', 'target', 'text-emerald-600', 'bg-emerald-50', $about['goals']],
            ['مميزات المعهد', 'sparkles', 'text-blue-600', 'bg-blue-50', $about['features']],
            ['إنجازات المعهد', 'award', 'text-amber-600', 'bg-amber-50', $about['achievements']],
        ];
    @endphp

    <div class="fade-in mx-auto max-w-3xl">
        <div class="card mb-4 flex flex-col items-center gap-3 p-6 text-center">
            <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo h-24 w-24 rounded-2xl bg-primary/5 p-2 object-contain" draggable="false">
            <h1 class="text-2xl font-extrabold">{{ $institute['name'] }}</h1>
            <p class="text-sm text-primary/70">{{ $institute['address'] }}</p>
            <p class="text-sm leading-relaxed text-primary/80">{{ $about['intro'] }}</p>
            <div class="chip bg-emerald-50 text-emerald-700">
                <x-icon name="chart-column" :size="14" />
                {{ $about['success_rate'] }}
            </div>
        </div>

        <div class="space-y-4">
            @foreach ($blocks as [$title, $icon, $color, $bg, $items])
                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-lg font-extrabold">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $bg }} {{ $color }}">
                            <x-icon :name="$icon" :size="18" />
                        </span>
                        <span>{{ $title }}</span>
                    </h2>
                    <ul class="space-y-2">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-2 text-sm leading-relaxed text-primary/80">
                                <span class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                    <x-icon name="check" :size="11" :stroke="3" />
                                </span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <a href="{{ url('/register') }}" class="btn btn-dark">
                <x-icon name="graduation-cap" :size="16" /> سجّل طالباً الآن
            </a>
            <a href="{{ url('/contact') }}" class="btn btn-outline">
                <x-icon name="phone" :size="16" /> تواصل معنا
            </a>
        </div>
    </div>
@endsection
